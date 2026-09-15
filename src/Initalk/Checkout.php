<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Payment\CallbackToken;
use GnuCms\Support\Clock;

/** 이니시스 Gateway 계약 위에서 결제 요청의 결제창·승인·조회·환불을 처리한다. */
final class Checkout
{
    public const RESTART_GRACE = 10;

    public function __construct(private App $app, private Requests $requests, private Ledger $ledger, private Events $events)
    {
    }

    /** Gateway가 받는 주문 배열. oid는 요청 id다. */
    public static function order(array $request): array
    {
        return ['id' => $request['id'], 'provider' => 'inicis', 'environment' => $request['environment'], 'config_revision' => $request['config_revision'],
            'total' => (int) $request['amount'], 'order_name' => $request['product_name'],
            'transaction_id' => $request['transaction_id'] !== '' ? $request['transaction_id'] : null, 'created_at' => (int) $request['created_at']];
    }

    public static function device(string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent) ? 'mobile' : 'web';
    }

    public function start(string $id, string $device, string $returnUrl, string $callbackBase): array
    {
        $request = $this->requests->find($id);
        $now = Clock::timestamp();
        if (!Status::canPay($request['status']) || $request['expires_at'] <= $now) throw DomainError::validation(['payment' => '결제할 수 없는 요청입니다.']);
        if ($request['checkout_started_at'] !== null && $now - $request['checkout_started_at'] < self::RESTART_GRACE) {
            throw DomainError::validation(['payment' => '결제창을 여는 중입니다. 잠시 후 다시 시도해 주세요.']);
        }
        $settings = $this->app->paymentSettings();
        $settings->requireEnabled($request['environment']);
        $revision = (string) $settings->summary($request['environment'])['revision'];
        $request['config_revision'] = $revision;
        $order = self::order($request);
        $callbackUrl = $callbackBase . '?id=' . $id . '&state=' . CallbackToken::create($this->app, $order);
        $form = $this->app->inicisGateway()->checkout($order, ['name' => $request['buyer_name'], 'phone' => $request['phone'], 'email' => ''], $returnUrl, $callbackUrl, $device === 'mobile' ? 'mobile' : 'web');
        // 게이트웨이가 결제창을 내준 뒤에만 기록한다. 거부된 시도가 만료 보호(30분)를 연장하면 안 된다.
        $this->requests->touchCheckout($id, $revision);
        return $form;
    }

    /** 인증 결과 콜백. 승인 후 조회로 확정한다. 호출자가 ExecutionLock 안에서 부른다. */
    public function complete(string $id, array $callback): void
    {
        $request = $this->requests->find($id);
        // 인증 중에 결제전 취소·만료 처리가 끼어들었으면 승인하지 않는다. 승인하지 않은 인증 토큰은 그대로 실효된다(과금 없음).
        if (!Status::canPay($request['status']) && $request['status'] !== Status::EXPIRED) {
            throw DomainError::validation(['payment' => '결제할 수 없는 상태의 요청입니다. 결제가 승인되지 않았습니다.']);
        }
        $this->app->inicisGateway()->complete(self::order($request), $callback);
        $this->sync($id, 'customer');
    }

    /** 결제사 조회 결과를 요청과 원장에 반영한다. 결제창을 연 적 없으면 조회하지 않는다. */
    public function sync(string $id, string $actor): array
    {
        $request = $this->requests->find($id);
        if ($request['config_revision'] === '') return $request;
        $order = self::order($request);
        $payment = $this->app->inicisGateway()->fetch($order);
        if (!in_array($payment['status'] ?? 'NOT_FOUND', ['PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true)) return $request;
        if (!($payment['valid'] ?? false) || ($request['transaction_id'] !== '' && $request['transaction_id'] !== ($payment['transaction_id'] ?? ''))) {
            if (!$request['needs_review']) $this->requests->setReview($id, true, $actor, '결제사 조회 결과가 요청의 상점·금액·거래번호와 일치하지 않습니다.');
            return $this->requests->find($id);
        }
        // 대조: 결과를 확인하지 못한 환불 신청을 조회에 보이는 같은 금액의 취소에 연결한다. 연결한 만큼 보류가 줄어든다.
        if (($payment['open_cancellations'] ?? 0) > 0) {
            $payment['open_cancellations'] -= $this->linkPendingRefunds($order, $payment['cancellations']);
        }
        if ($request['paid_at'] === null) {
            $request = $this->requests->markPaid($id, (int) $payment['paid_at'], (string) $payment['transaction_id'], $actor);
            $this->ledger->record('approve', $id, $request['amount'], (int) $payment['paid_at'], (string) $payment['transaction_id']);
        }
        foreach ($payment['cancellations'] as $cancel) $this->ledger->record('refund', $id, (int) $cancel['amount'], (int) $cancel['at'], (string) $cancel['id']);
        $cancelled = (int) ($payment['cancelled'] ?? 0);
        if ($cancelled > $request['refunded_amount'] && Status::canRefund($request['status'])) {
            $request = $this->requests->applyRefund($id, $cancelled, $actor, '결제사 조회로 확인한 취소 반영');
        }
        if (($payment['open_cancellations'] ?? 0) > 0) {
            if (!$request['needs_review']) $this->requests->setReview($id, true, $actor, '보류 중인 환불 요청이 있습니다.');
        } elseif ($request['needs_review'] && $cancelled === $request['refunded_amount']) {
            // 조회 결과가 상점·금액·거래번호와 맞고 보류 환불도 없으면 확인할 것이 남지 않았다.
            $this->requests->setReview($id, false, $actor, '대조 완료');
        }
        return $this->requests->find($id);
    }

    /** 보류 신청을 PG 조회의 같은 금액 취소에 연결한다. 연결한 건수를 돌려준다. 짝이 없으면 보류로 남긴다. */
    private function linkPendingRefunds(array $order, array $cancellations): int
    {
        $gateway = $this->app->inicisGateway();
        $linked = 0;
        foreach ($gateway->pendingRefunds($order) as $key => $entry) {
            foreach ($cancellations as $cancel) {
                if ((int) ($cancel['amount'] ?? 0) !== $entry['amount']) continue;
                try {
                    $gateway->confirmRefund($order, (string) $key, (string) $cancel['id']);
                    $linked++;
                    break;
                } catch (DomainError $e) {
                    // 이미 다른 신청에 연결된 취소이거나 조회가 그 사이 달라졌다. 다음 후보로 넘어간다.
                }
            }
        }
        return $linked;
    }

    /** 운영자가 PG에서 미처리를 확인한 2시간 경과 환불 신청을 종료한다. 게이트웨이의 검사를 그대로 쓴다. */
    public function closeUnprocessedRefund(string $id, string $key, string $actor): array
    {
        $request = $this->requests->find($id);
        $this->app->inicisGateway()->confirmUnprocessedRefund(self::order($request), $key);
        $this->events->record($id, 'refund_closed', $actor, '미처리 환불 신청 종료 ' . substr($key, -8));
        return $this->sync($id, $actor);
    }

    public function refund(string $id, int $amount, string $reason, string $refundKey, string $actor): array
    {
        $request = $this->requests->find($id);
        if (!Status::canRefund($request['status'])) throw DomainError::validation(['status' => '결제 완료 상태에서만 환불할 수 있습니다.']);
        $remaining = $request['amount'] - $request['refunded_amount'];
        if ($amount < 1 || $amount > $remaining) throw DomainError::validation(['amount' => '환불 금액은 1원 이상 남은 금액(' . number_format($remaining) . '원) 이하여야 합니다.']);
        if (!preg_match('/^[a-f0-9]{32}$/D', $refundKey)) throw DomainError::validation(['refund_key' => '환불 요청 키를 확인해 주세요. 화면을 새로 연 뒤 다시 시도해 주세요.']);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 80 || preg_match('/[\r\n]/', $reason)) throw DomainError::validation(['reason' => '환불 사유를 1~80자로 입력해 주세요.']);
        $cancelKey = 'initalk-' . $id . '-' . $refundKey;
        try {
            $result = $this->app->inicisGateway()->cancel(self::order($request), $amount, $remaining, $reason, $cancelKey);
        } catch (DomainError $e) {
            // 같은 요청 키의 재제출: 그 사이 다른 환불이 반영되어 remaining이 달라졌을 수 있다. remaining을 다시 추정해 재전송하지 않고 PG 조회로 대조한다.
            if ($e->status() !== 422 || ($e->details()['refund'] ?? '') !== '같은 요청 키의 환불 내용이 다릅니다.') throw $e;
            return $this->sync($id, $actor);
        }
        if (!$this->ledger->record('refund', $id, (int) $result['amount'], (int) $result['at'], (string) $result['id'])) {
            // 같은 요청 키의 재제출: 결제사는 저장된 결과를 돌려준다. 조회로 대조만 한다.
            return $this->sync($id, $actor);
        }
        return $this->requests->applyRefund($id, $request['refunded_amount'] + (int) $result['amount'], $actor, '환불 ' . number_format((int) $result['amount']) . '원: ' . $reason);
    }
}
