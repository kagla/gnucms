<?php

declare(strict_types=1);

namespace GnuCms\Tests\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Payment\Gateway;
use GnuCms\Support\Clock;

final class FakeGateway implements Gateway
{
    public array $payments = [];
    public array $calls = [];
    public bool $timeout = false;
    public bool $lostResponse = false;
    public string $revision;
    public function __construct() { $this->revision = bin2hex(random_bytes(16)); }
    public function id(): string { return 'inicis'; }
    public function label(): string { return '테스트 결제'; }
    public function available(string $environment): bool { return true; }
    public function configuration(string $environment): array { return ['revision' => $this->revision]; }
    public function checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array { return ['kind' => 'inicis', 'script' => 'https://stgstdpay.inicis.com/stdjs/INIStdPay.js', 'fields' => ['oid' => $order['id'], 'price' => (string) $order['total'], 'returnUrl' => $callbackUrl]]; }
    public function complete(array $order, array $callback): void {}
    public function paid(array $order): void
    {
        $this->payments[$order['id']] = ['status' => 'PAID', 'valid' => true, 'transaction_id' => bin2hex(random_bytes(16)),
            'paid_at' => Clock::timestamp(), 'cancelled' => 0, 'cancellations' => []];
    }
    public function fetch(array $order): array { return $this->payments[$order['id']] ?? ['status' => 'NOT_FOUND']; }
    public function cancel(array $order, int $amount, int $remaining, string $reason, string $key): array
    {
        $this->calls[] = compact('amount', 'remaining', 'reason', 'key');
        if ($this->timeout) throw DomainError::serviceUnavailable('테스트 통신 단절');
        $payment = &$this->payments[$order['id']];
        if ($remaining !== (int) $order['total'] - $payment['cancelled']) throw DomainError::validation(['remaining' => '잔액 불일치']);
        $payment['cancelled'] += $amount;
        $payment['status'] = $payment['cancelled'] === (int) $order['total'] ? 'CANCELLED' : 'PARTIAL_CANCELLED';
        $payment['cancellations'][] = ['id' => bin2hex(random_bytes(16)), 'amount' => $amount, 'reason' => $reason, 'at' => Clock::timestamp()];
        if ($this->lostResponse) throw DomainError::serviceUnavailable('테스트 응답 유실');
        return [];
    }
}
