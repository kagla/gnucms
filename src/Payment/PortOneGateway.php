<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use Psr\Http\Message\ServerRequestInterface;

final class PortOneGateway implements Gateway
{
    public function __construct(public readonly Settings $settings, private Transport $http = new StreamTransport()) {}
    public function id(): string { return $this->settings->provider; }
    public function label(): string { return Settings::PROVIDERS[$this->id()]; }
    public function available(string $environment): bool { return $this->settings->available($environment); }

    public function configuration(string $environment): array
    {
        $this->settings->requireEnabled($environment);
        return $this->settings->summary($environment);
    }

    private function credentials(array $order): array
    {
        if (($order['provider'] ?? '') !== $this->id() || !preg_match('/^[a-f0-9]{32}$/D', $order['id'] ?? '')) {
            throw DomainError::validation(['payment' => '주문의 결제사를 확인해 주세요.']);
        }
        $row = $this->settings->credentials($order['config_revision']);
        if ($row['environment'] !== $order['environment']) throw DomainError::validation(['payment' => '결제 환경이 다릅니다.']);
        $this->settings->requireEnabled($row['environment']);
        return $row;
    }

    private function request(array $config, string $method, string $id, string $path = '', ?array $body = null, ?string $key = null): array
    {
        $url = 'https://api.portone.io/payments/' . $id . $path;
        $headers = ['Authorization' => 'PortOne ' . $config['api_secret']];
        if ($key !== null) {
            if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) throw DomainError::internal('결제 요청 키를 확인해 주세요.');
            $headers['Idempotency-Key'] = '"' . $key . '"';
        }
        if ($method === 'GET') $url .= '?storeId=' . rawurlencode($config['store_id']);
        else $body = ['storeId' => $config['store_id']] + ($body ?? []);
        return $this->http->request($method, $url, $headers, $body);
    }

    public function checkout(array $order, array $customer, string $returnUrl, string $webhookUrl): array
    {
        $config = $this->credentials($order);
        foreach ([$returnUrl, $webhookUrl] as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_USER) !== null) throw DomainError::validation(['url' => '설정의 사이트 주소(app.url)를 공개 HTTPS 주소로 지정해 주세요.']);
        }
        $registered = $this->request($config, 'POST', $order['id'], '/pre-register', [
            'totalAmount' => (int) $order['total'], 'taxFreeAmount' => 0, 'currency' => 'KRW',
        ], 'register-' . $order['id']);
        if ($registered['status'] !== 200) throw DomainError::serviceUnavailable('결제 사전 등록에 실패했습니다. 주문에서 다시 결제해 주세요.');
        return ['storeId' => $config['store_id'], 'channelKey' => $config['channel_key'],
            'paymentId' => $order['id'], 'orderName' => $order['order_name'], 'totalAmount' => (int) $order['total'],
            'currency' => 'KRW', 'payMethod' => 'CARD', 'taxFreeAmount' => 0,
            'customer' => ['fullName' => $customer['name'], 'phoneNumber' => $customer['phone'], 'email' => $customer['email']],
            'redirectUrl' => $returnUrl, 'noticeUrls' => [$webhookUrl], 'customData' => ['orderId' => $order['id']]];
    }

    public function fetch(array $order): array
    {
        $config = $this->credentials($order);
        $response = $this->request($config, 'GET', $order['id']);
        if ($response['status'] === 404 && ($response['body']['type'] ?? '') === 'PAYMENT_NOT_FOUND') return ['status' => 'NOT_FOUND'];
        if ($response['status'] !== 200) throw DomainError::serviceUnavailable('결제 상태를 조회하지 못했습니다. 잠시 뒤 다시 확인해 주세요.');
        $data = $response['body'];
        $providers = match ($this->id()) { 'inicis' => ['INICIS_V2', 'HTML5_INICIS'], 'kcp' => ['KCP_V2', 'KCP'], 'kspay' => ['KSNET'] };
        $valid = ($data['id'] ?? '') === $order['id'] && ($data['storeId'] ?? '') === $config['store_id']
            && ($data['channel']['key'] ?? '') === $config['channel_key']
            && ($data['channel']['type'] ?? '') === strtoupper($order['environment'])
            && in_array($data['channel']['pgProvider'] ?? '', $providers, true)
            && ($data['currency'] ?? '') === 'KRW' && ($data['amount']['total'] ?? null) === (int) $order['total']
            && ($data['amount']['taxFree'] ?? null) === 0;
        $status = $data['status'] ?? '';
        if (!in_array($status, ['READY', 'PENDING', 'FAILED', 'PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true)) {
            throw DomainError::serviceUnavailable('아직 확정되지 않은 결제 상태입니다. 다시 조회해 주세요.');
        }
        $paid = in_array($status, ['PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true);
        if ($paid) $valid = $valid && ($data['method']['type'] ?? '') === 'PaymentMethodCard'
            && is_string($data['transactionId'] ?? null) && strlen($data['transactionId']) > 0 && strlen($data['transactionId']) <= 100;
        $paidAt = self::timestamp($data['paidAt'] ?? '') ?: (int) ($order['paid_at'] ?? 0);
        if ($paid && $paidAt < 1) $valid = false;
        $cancelled = $data['amount']['cancelled'] ?? 0;
        if (!is_int($cancelled) || $cancelled < 0 || $cancelled > (int) $order['total']) $valid = false;
        if (($status === 'PAID' && $cancelled !== 0) || ($status === 'CANCELLED' && $cancelled !== (int) $order['total'])
            || ($status === 'PARTIAL_CANCELLED' && ($cancelled < 1 || $cancelled >= (int) $order['total']))) $valid = false;
        $rows = $data['cancellations'] ?? [];
        if (!is_array($rows) || !array_is_list($rows)) throw DomainError::serviceUnavailable('결제 취소 내역 형식을 확인하지 못했습니다. 다시 조회해 주세요.');
        $cancellations = []; $seen = []; $open = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) { $valid = false; continue; }
            if (($row['status'] ?? '') === 'REQUESTED') $open++;
            if (($row['status'] ?? '') !== 'SUCCEEDED') continue;
            if (!is_string($row['id'] ?? null) || $row['id'] === '' || strlen($row['id']) > 100 || isset($seen[$row['id']]) || !is_int($row['totalAmount'] ?? null)
                || $row['totalAmount'] < 1) { $valid = false; continue; }
            $seen[$row['id']] = true;
            $at = self::timestamp($row['cancelledAt'] ?? $row['requestedAt'] ?? '');
            if ($at < 1) $valid = false;
            $cancellations[] = ['id' => $row['id'], 'amount' => $row['totalAmount'],
                'reason' => is_string($row['reason'] ?? null) ? $row['reason'] : '',
                'at' => $at];
        }
        if ($paid && array_sum(array_column($cancellations, 'amount')) !== $cancelled) $valid = false;
        return ['status' => $status, 'valid' => $valid, 'transaction_id' => $data['transactionId'] ?? '', 'open_cancellations' => $open,
            'paid_at' => $paidAt, 'cancelled' => $cancelled, 'cancellations' => $cancellations];
    }

    private static function timestamp(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) return 0;
        $time = strtotime($value);
        return $time === false || $time < 1 ? 0 : $time;
    }

    public function cancel(array $order, int $amount, int $remaining, string $reason, string $key): array
    {
        if ($amount < 1 || $amount > $remaining || $remaining > (int) $order['total']) throw DomainError::validation(['amount' => '환불 가능 잔액을 확인해 주세요.']);
        $config = $this->credentials($order);
        $response = $this->request($config, 'POST', $order['id'], '/cancel', [
            'amount' => $amount, 'currentCancellableAmount' => $remaining, 'taxFreeAmount' => 0,
            'reason' => $reason, 'requester' => 'ADMIN',
        ], $key);
        // 응답이 실패여도 서버 조회로 실제 취소 여부를 확인한다. 원문에는 개인정보가 있어 저장하지 않는다.
        if ($response['status'] !== 200) throw DomainError::serviceUnavailable('환불 결과를 확인하지 못했습니다. 환불 상태 조회로 확인해 주세요.');
        return $response['body'];
    }

    public function authenticateWebhook(ServerRequestInterface $request, string $revision): bool
    {
        try {
            $config = $this->settings->credentials($revision);
            return $this->available($config['environment']) && WebhookSignature::verify($request, $config['webhook_secret'], Clock::timestamp());
        } catch (\Throwable) { return false; }
    }
}
