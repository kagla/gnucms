<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

final class StreamTransport implements Transport
{
    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        // 목적지를 고정한다. 사용자 입력 URL과 리다이렉트는 전송하지 않는다.
        if (!preg_match('~^https://api\.portone\.io/payments/[a-f0-9]{32}(?:/cancel|/pre-register)?(?:\?storeId=[A-Za-z0-9%_-]+)?$~D', $url)
            || !in_array($method, ['GET', 'POST'], true)) {
            throw DomainError::internal('결제 API 경로가 올바르지 않습니다.');
        }
        $lines = ['Accept: application/json', 'Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            if (preg_match('/[\r\n]/', $name . $value)) throw DomainError::internal('결제 인증 설정을 확인해 주세요.');
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $lines), 'timeout' => 60,
                'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0,
                'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT],
        ]);
        $handle = @fopen($url, 'rb', false, $context);
        if ($handle === false) throw DomainError::serviceUnavailable('결제사 응답을 확인하지 못했습니다. 결제 상태를 다시 조회해 주세요.');
        try {
            $raw = stream_get_contents($handle, 1048577);
            $meta = stream_get_meta_data($handle);
        } finally {
            fclose($handle);
        }
        $status = 0;
        foreach ($meta['wrapper_data'] ?? [] as $line) {
            if (preg_match('~^HTTP/\S+ (\d{3})~', $line, $m)) $status = (int) $m[1];
        }
        $data = is_string($raw) ? json_decode($raw, true, 64) : null;
        if (($meta['timed_out'] ?? false) || !is_array($data) || strlen($raw) > 1048576 || $status === 0) {
            throw DomainError::serviceUnavailable('결제사 응답을 확인하지 못했습니다. 결제 상태를 다시 조회해 주세요.');
        }
        return ['status' => $status, 'body' => $data];
    }
}
