<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** KSNET pgapi 직접 취소. 결제창·승인·조회 규격 확보 전에는 결제를 받지 않는다. */
final class KspayGateway extends DirectGateway
{
    public function available(string $environment): bool { return false; }
    public function configuration(string $environment): array { throw DomainError::serviceUnavailable('KSNET 결제창·승인·조회 연동 문서가 필요합니다.'); }
    public function checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array { throw DomainError::serviceUnavailable('KSNET 결제창 연동 확인 후 사용할 수 있습니다.'); }
    protected function validateCallback(array $config, array $order, array $callback): void { throw DomainError::forbidden('KSNET 승인 연동이 준비되지 않았습니다.'); }
    protected function approve(array $config, array $order, array $callback): array { throw DomainError::serviceUnavailable('KSNET 승인 연동 문서가 필요합니다.'); }
    protected function query(array $config, array $order, array $state): array { throw DomainError::serviceUnavailable('KSNET 거래 조회 연동 문서가 필요합니다.'); }

    protected function refund(array $config, array $order, array $state, int $amount, int $remaining, string $reason, string $key): array
    {
        $tid = $order['transaction_id'] ?? $state['approved']['tid'] ?? '';
        if ($tid === '') throw DomainError::validation(['refund' => '승인 거래번호가 필요합니다.']);
        $full = $amount === (int) $order['total'];
        $body = ['mid' => $config['merchant_id'], 'payload' => $key, 'orgTradeKeyType' => 'TID', 'orgTradeKey' => $tid, 'cancelType' => $full ? 'FULL' : 'PARTIAL'];
        if (!$full) {
            $sequence = count($state['refunds']);
            if ($sequence > 9) throw DomainError::validation(['refund' => 'KSPay 부분취소는 최대 9회입니다.']);
            $body += ['cancelTotalAmount' => (string) $amount, 'cancelTaxFreeAmount' => '0', 'cancelSeq' => (string) $sequence];
        }
        $response = $this->request('https://pay' . ($config['environment'] === 'test' ? 'dev' : '') . '.ksnet.co.kr/kspay/webfep/api/v1/card/cancel', $body, false, ['Authorization' => 'pgapi ' . $config['api_key']]);
        $data = $response['data'] ?? [];
        if (($response['code'] ?? '') !== 'A0200' || ($data['respCode'] ?? '') !== '0000' || ($data['tid'] ?? '') !== $tid
            || ($data['payload'] ?? '') !== $key || self::amount($data['cancelAmount'] ?? null) !== $amount) throw DomainError::serviceUnavailable('KSNET 취소 결과가 확인되지 않았습니다.');
        $at = self::date($data['tradeDateTime'] ?? '');
        if ($at < 1) throw DomainError::serviceUnavailable('KSNET 취소 시간을 확인하지 못했습니다.');
        return ['id' => self::value($response, 'aid', 100), 'amount' => $amount, 'at' => $at];
    }
}
