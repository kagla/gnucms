<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** 토스페이먼츠 API 개별 연동 키와 V2 카드·간편결제 통합결제창. */
final class TossGateway extends DirectGateway
{
    private const API = 'https://api.tosspayments.com/v1/payments/';

    public function checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array
    {
        if (!str_ends_with((string) parse_url($callbackUrl, PHP_URL_PATH), '/callback')) throw DomainError::validation(['url' => '결제 인증 결과 주소를 확인해 주세요.']);
        $config = $this->prepare($order, $returnUrl, $callbackUrl);
        $customerKey = substr(hash_hmac('sha256', 'toss-customer:' . ($order['user_id'] ?? $order['id']), (string) $this->settings->app->config('auth.secret')), 0, 50);
        return ['kind' => 'toss', 'fields' => [], 'script' => 'https://js.tosspayments.com/v2/standard',
            'client_key' => $config['client_key'], 'customer_key' => $customerKey, 'request' => [
                'method' => 'CARD', 'amount' => ['currency' => 'KRW', 'value' => (int) $order['total']],
                'orderId' => $order['id'], 'orderName' => mb_substr($order['order_name'], 0, 100),
                'successUrl' => preg_replace('~/callback(?=\?|$)~', '/toss-return', $callbackUrl, 1),
                'failUrl' => $returnUrl, 'customerName' => mb_substr($customer['name'], 0, 100),
                'card' => ['flowMode' => 'DEFAULT'], 'taxFreeAmount' => 0,
            ]];
    }

    /** GET 복귀 화면과 POST 승인에서 동일한 주문·금액 검사를 적용한다. */
    public static function callback(array $order, array $input): array
    {
        $key = self::value($input, 'paymentKey', 200);
        if (($order['provider'] ?? '') !== 'toss' || !preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $key)
            || ($input['orderId'] ?? null) !== $order['id'] || self::amount($input['amount'] ?? null) !== (int) $order['total']) {
            throw DomainError::validation(['payment' => '결제 인증 결과의 주문번호와 금액을 확인해 주세요.']);
        }
        return ['paymentKey' => $key, 'orderId' => $order['id'], 'amount' => (int) $order['total']];
    }

    protected function validateCallback(array $config, array $order, array $callback): void { self::callback($order, $callback); }

    protected function approve(array $config, array $order, array $callback): array
    {
        $input = self::callback($order, $callback);
        $raw = $this->request(self::API . 'confirm', $input, headers: $this->headers($config, 'approve-' . $order['id']));
        $payment = $this->payment($config, $order, $raw);
        if (!$payment['valid'] || $payment['status'] !== 'PAID' || $payment['transaction_id'] !== $input['paymentKey']) {
            throw DomainError::serviceUnavailable('승인 결과가 주문과 일치하지 않습니다. 결제사에서 처리 여부를 확인해 주세요.');
        }
        return ['tid' => $payment['transaction_id']];
    }

    protected function query(array $config, array $order, array $state): array
    {
        // 승인 응답이 유실되어 paymentKey를 저장하지 못했어도 주문번호로 복구한다.
        $response = $this->http->request('GET', self::API . 'orders/' . $order['id'], $this->headers($config), null);
        if ($response['status'] === 404 && ($response['body']['code'] ?? '') === 'NOT_FOUND_PAYMENT') return ['status' => 'NOT_FOUND', 'valid' => false, 'cancellations' => []];
        if ($response['status'] !== 200) throw DomainError::serviceUnavailable('토스페이먼츠 결제 조회에 실패했습니다. 잠시 후 다시 확인해 주세요.');
        $payment = $this->payment($config, $order, $response['body']);
        $known = $state['approved']['tid'] ?? $order['transaction_id'] ?? null;
        if ($known !== null && $payment['transaction_id'] !== $known) $payment['valid'] = false;
        return $payment;
    }

    protected function refund(array $config, array $order, array $state, int $amount, int $remaining, string $reason, string $key): array
    {
        $before = $this->query($config, $order, $state);
        if (!($before['valid'] ?? false) || (int) $order['total'] - $before['cancelled'] !== $remaining
            || ($amount < $remaining && !$before['partial_cancelable'])) {
            throw DomainError::serviceUnavailable('PG 잔액과 부분 취소 가능 여부를 확인해 주세요.');
        }
        $reason = mb_substr($reason, 0, 200);
        $raw = $this->request(self::API . $before['transaction_id'] . '/cancel',
            ['cancelReason' => $reason, 'cancelAmount' => $amount, 'currency' => 'KRW', 'refundableAmount' => $remaining],
            headers: $this->headers($config, $key));
        $after = $this->payment($config, $order, $raw);
        $oldIds = array_column($before['cancellations'], 'id');
        $new = array_values(array_filter($after['cancellations'], static fn ($cancel) => !in_array($cancel['id'], $oldIds, true)));
        if (!$after['valid'] || $after['transaction_id'] !== $before['transaction_id']
            || $after['cancelled'] !== $before['cancelled'] + $amount || count($new) !== 1
            || $new[0]['amount'] !== $amount || $new[0]['id'] !== ($raw['lastTransactionKey'] ?? null)) {
            throw DomainError::serviceUnavailable('취소 결과가 요청과 일치하지 않습니다. 자동 재전송 없이 PG 내역을 확인해 주세요.');
        }
        return $new[0];
    }

    private function headers(array $config, ?string $key = null): array
    {
        $headers = ['Authorization' => 'Basic ' . base64_encode($config['secret_key'] . ':')];
        if ($key !== null) $headers['Idempotency-Key'] = $key;
        return $headers;
    }

    /** PG 응답 원문·카드 정보 대신 원장에 필요한 값만 반환한다. */
    private function payment(array $config, array $order, array $raw): array
    {
        $status = match ($raw['status'] ?? '') { 'DONE' => 'PAID', 'PARTIAL_CANCELED' => 'PARTIAL_CANCELLED', 'CANCELED' => 'CANCELLED', default => 'PENDING' };
        $tid = $raw['paymentKey'] ?? '';
        $total = self::amount($raw['totalAmount'] ?? null);
        $balance = self::amount($raw['balanceAmount'] ?? null);
        $at = self::isoDate($raw['approvedAt'] ?? null);
        $valid = is_string($tid) && (bool) preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $tid)
            && ($raw['mId'] ?? '') === $config['merchant_id'] && ($raw['orderId'] ?? '') === $order['id']
            && ($raw['currency'] ?? '') === 'KRW' && ($raw['type'] ?? '') === 'NORMAL'
            && in_array($raw['method'] ?? '', ['카드', '간편결제'], true)
            && ($raw['useEscrow'] ?? null) === false && self::amount($raw['taxFreeAmount'] ?? null) === 0
            && $total === (int) $order['total'] && $balance >= 0 && $balance <= $total && $at > 0
            && match ($status) { 'PAID' => $balance === $total, 'PARTIAL_CANCELLED' => $balance > 0 && $balance < $total, 'CANCELLED' => $balance === 0, default => false };
        $cancels = $raw['cancels'] ?? [];
        if (!is_array($cancels) || !array_is_list($cancels)) { $valid = false; $cancels = []; }
        $cancellations = []; $seen = [];
        foreach ($cancels as $cancel) {
            if (!is_array($cancel)) { $valid = false; continue; }
            $id = $cancel['transactionKey'] ?? null;
            $amount = self::amount($cancel['cancelAmount'] ?? null);
            $time = self::isoDate($cancel['canceledAt'] ?? null);
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) || isset($seen[$id])
                || ($cancel['cancelStatus'] ?? '') !== 'DONE' || $amount < 1 || $time < 1) { $valid = false; continue; }
            $seen[$id] = true;
            $cancellations[] = ['id' => $id, 'amount' => $amount, 'at' => $time, 'reason' => ''];
        }
        $cancelled = $total - $balance;
        $valid = $valid && array_sum(array_column($cancellations, 'amount')) === $cancelled;
        return ['status' => $status, 'valid' => $valid, 'transaction_id' => is_string($tid) ? $tid : '', 'paid_at' => $at,
            'cancelled' => $cancelled, 'cancellations' => $cancellations, 'partial_cancelable' => ($raw['isPartialCancelable'] ?? false) === true];
    }

    private static function isoDate(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/D', $value)) return 0;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) ? $date->getTimestamp() : 0;
    }
}
