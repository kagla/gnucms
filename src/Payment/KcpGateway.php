<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** KCP LITE PAY 직접 연동. 조회 서명 규격 확인 전에는 상점에서 선택할 수 없다. */
final class KcpGateway extends DirectGateway
{
    public function available(string $environment): bool { return false; }
    public function configuration(string $environment): array { throw DomainError::serviceUnavailable('KCP 거래조회 연동 확인 후 사용할 수 있습니다.'); }

    private function api(array $config, string $path, array $body): array
    {
        return $this->request('https://' . ($config['environment'] === 'test' ? 'stg-' : '') . 'spl.kcp.co.kr/' . $path,
            ['site_cd' => $config['merchant_id'], 'kcp_cert_info' => str_replace(["\r", "\n"], '', $config['certificate'])] + $body);
    }

    private static function sign(array $config, string $message): string
    {
        $key = openssl_pkey_get_private($config['private_key'], $config['private_key_password']);
        if ($key === false || !openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) throw DomainError::serviceUnavailable('KCP 서명 키를 확인해 주세요.');
        return base64_encode($signature);
    }

    public function checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array
    {
        $config = $this->prepare($order, $returnUrl, $callbackUrl);
        $type = $device === 'mobile' ? 'mobile' : 'web';
        $data = $this->api($config, 'std/brpay/treg', ['ordr_idxx' => $order['id'], 'pay_method' => 'CARD', 'good_mny' => (int) $order['total'],
            'good_name' => mb_strcut($order['order_name'], 0, 100, 'UTF-8'), 'reg_type' => $type, 'ret_URL' => $callbackUrl, 'fail_url' => $returnUrl,
            'kcp_sign_data' => self::sign($config, $config['merchant_id'] . '^' . $order['total'] . '^CARD^' . $type . '^' . $order['id'])]);
        if (($data['res_cd'] ?? '') !== '0000') throw DomainError::serviceUnavailable('KCP 거래 등록을 확인하지 못했습니다.');
        $url = self::value($data, 'pay_url', 4096);
        $host = parse_url($url, PHP_URL_HOST);
        if (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_USER) !== null
            || !is_string($host) || !str_ends_with($host, '.kcp.co.kr') || parse_url($url, PHP_URL_PORT) !== null) throw DomainError::serviceUnavailable('KCP 결제창 주소를 확인하지 못했습니다.');
        return ['kind' => 'form', 'action' => $url, 'charset' => 'UTF-8', 'fields' => ['ordr_idxx' => $order['id']]];
    }

    protected function validateCallback(array $config, array $order, array $callback): void
    {
        if (isset($callback['res_cd']) && $callback['res_cd'] !== '0000') throw DomainError::validation(['payment' => '카드 인증이 완료되지 않았습니다.']);
        if (($callback['ordr_idxx'] ?? '') !== $order['id'] || ($callback['tran_cd'] ?? '') !== '00100000') throw DomainError::forbidden('인증 주문번호와 결제수단이 다릅니다.');
        self::value($callback, 'enc_data'); self::value($callback, 'enc_info');
    }

    protected function approve(array $config, array $order, array $callback): array
    {
        $data = $this->api($config, 'gw/enc/v1/payment', ['enc_data' => $callback['enc_data'], 'enc_info' => $callback['enc_info'], 'tran_cd' => $callback['tran_cd'],
            'ordr_idxx' => $order['id'], 'ordr_no' => $order['id'], 'ordr_mony' => (int) $order['total'], 'pay_type' => 'PACA']);
        if (($data['res_cd'] ?? '') !== '0000' || ($data['order_no'] ?? '') !== $order['id'] || ($data['pay_method'] ?? '') !== 'PACA'
            || self::amount($data['amount'] ?? null) !== (int) $order['total']) throw DomainError::serviceUnavailable('KCP 승인 결과가 주문과 일치하지 않습니다.');
        return ['tid' => self::value($data, 'tno', 14)];
    }

    protected function query(array $config, array $order, array $state): array
    {
        // 공개 /reference/search의 서명식에는 mod_type이 있으나 요청 항목/값이 없다.
        // 테스트 조회도 S032(접근권한 없음)로 차단되어 규격을 확인하지 못했다.
        // 값이나 조회 결과를 추측해 결제/정산 상태를 확정하지 않는다.
        throw DomainError::serviceUnavailable('KCP 조회 서명 규격과 조회 API 권한 확인이 필요합니다.');
    }

    protected function refund(array $config, array $order, array $state, int $amount, int $remaining, string $reason, string $key): array
    {
        $tid = $order['transaction_id'] ?? $state['approved']['tid'] ?? '';
        if ($tid === '') throw DomainError::validation(['refund' => '승인 거래번호가 필요합니다.']);
        $full = $amount === (int) $order['total']; $type = $full ? 'STSC' : 'STPC';
        $body = ['tno' => $tid, 'mod_type' => $type, 'mod_desc' => mb_strcut($reason, 0, 100, 'UTF-8'),
            'kcp_sign_data' => self::sign($config, $config['merchant_id'] . '^' . $tid . '^' . $type)];
        if (!$full) $body += ['mod_mny' => $amount, 'rem_mny' => $remaining];
        $data = $this->api($config, 'gw/mod/v1/cancel', $body);
        if (($data['res_cd'] ?? '') !== '0000' || ($data['tno'] ?? '') !== $tid
            || (!$full && (self::amount($data['mod_mny'] ?? null) !== $amount || self::amount($data['rem_mny'] ?? null) !== $remaining - $amount))) throw DomainError::serviceUnavailable('KCP 환불 결과를 확인하지 못했습니다.');
        $at = self::date($data['canc_time'] ?? '');
        if ($at < 1) throw DomainError::serviceUnavailable('KCP 환불 시간을 확인하지 못했습니다.');
        return ['id' => $full ? $tid . '-full' : self::value($data, 'mod_pcan_seq_no', 14), 'amount' => $amount, 'at' => $at];
    }
}
