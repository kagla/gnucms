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

    public function __construct(private App $app, private Requests $requests, private Ledger $ledger)
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
        $this->requests->touchCheckout($id, $revision);
        $request['config_revision'] = $revision;
        $order = self::order($request);
        $callbackUrl = $callbackBase . '?id=' . $id . '&state=' . CallbackToken::create($this->app, $order);
        return $this->app->inicisGateway()->checkout($order, ['name' => $request['buyer_name'], 'phone' => $request['phone'], 'email' => ''], $returnUrl, $callbackUrl, $device === 'mobile' ? 'mobile' : 'web');
    }

    /** 인증 결과 콜백. 승인 후 조회로 확정한다. 호출자가 ExecutionLock 안에서 부른다. */
    public function complete(string $id, array $callback): void
    {
        $request = $this->requests->find($id);
        $this->app->inicisGateway()->complete(self::order($request), $callback);
        $this->sync($id, 'customer');
    }

    /** 결제사 조회 결과를 요청과 원장에 반영한다. 결제창을 연 적 없으면 조회하지 않는다. */
    public function sync(string $id, string $actor): array
    {
        $request = $this->requests->find($id);
        if ($request['config_revision'] === '') return $request;
        $payment = $this->app->inicisGateway()->fetch(self::order($request));
        if (!in_array($payment['status'] ?? 'NOT_FOUND', ['PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true)) return $request;
        if (!($payment['valid'] ?? false) || ($request['transaction_id'] !== '' && $request['transaction_id'] !== ($payment['transaction_id'] ?? ''))) {
            if (!$request['needs_review']) $this->requests->setReview($id, true, $actor, '결제사 조회 결과가 요청의 상점·금액·거래번호와 일치하지 않습니다.');
            return $this->requests->find($id);
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
        if (($payment['open_cancellations'] ?? 0) > 0 && !$request['needs_review']) $this->requests->setReview($id, true, $actor, '보류 중인 환불 요청이 있습니다.');
        return $this->requests->find($id);
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
