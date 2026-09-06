<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

final class StreamTransport implements Transport
{
    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        if (!self::allowed($url) || $method !== 'POST') throw DomainError::internal('결제 API 경로가 올바르지 않습니다.');
        $contentType = $headers['Content-Type'] ?? 'application/json';
        $form = str_starts_with($contentType, 'application/x-www-form-urlencoded');
        $lines = ['Accept: application/json', 'Content-Type: ' . $contentType];
        unset($headers['Content-Type']);
        foreach ($headers as $name => $value) {
            if (preg_match('/[\r\n]/', $name . $value)) throw DomainError::internal('결제 인증 설정을 확인해 주세요.');
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $lines), 'timeout' => 60,
                'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0,
                'content' => $body === null ? '' : ($form ? http_build_query($body, '', '&', PHP_QUERY_RFC3986) : self::json($body))],
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
        if ($data === null && is_string($raw) && str_contains($url, 'mobile.inicis.com/smart/')) {
            parse_str($raw, $data);
            foreach ($data as $key => $value) {
                if (!is_string($value)) throw DomainError::serviceUnavailable('결제 응답 형식이 올바르지 않습니다.');
                if (!mb_check_encoding($value, 'UTF-8')) $data[$key] = mb_convert_encoding($value, 'UTF-8', 'EUC-KR');
            }
        }
        if (($meta['timed_out'] ?? false) || !is_array($data) || strlen($raw) > 1048576 || $status === 0) {
            throw DomainError::serviceUnavailable('결제사 응답을 확인하지 못했습니다. 결제 상태를 다시 조회해 주세요.');
        }
        return ['status' => $status, 'body' => $data];
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function allowed(string $url): bool
    {
        return (bool) preg_match('~^https://(?:(?:stg)?iniapi\.inicis\.com/v2/pg/(?:inquiry|refund|partialRefund)|(?:fc|ks|stg)stdpay\.inicis\.com/api/[A-Za-z0-9]+|(?:fc|ks|stg)mobile\.inicis\.com/smart/(?:payReq|payNetCancel)\.ini|(?:stg-)?spl\.kcp\.co\.kr/(?:std/inquery|std/brpay/treg|gw/enc/v1/payment|gw/mod/v1/cancel)|pay(?:dev)?\.ksnet\.co\.kr/kspay/webfep/api/v1/card/cancel)$~D', $url);
    }
}
