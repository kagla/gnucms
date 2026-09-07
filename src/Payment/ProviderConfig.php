<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** 리셀러 코드는 가맹점 등록용이며 결제 설정·전문에 넣지 않는다. */
final class ProviderConfig
{
    public static function fields(string $provider): array
    {
        $names = match ($provider) {
            'inicis' => ['merchant_id' => '상점 아이디 (MID)', 'sign_key' => '웹표준 결제 SignKey', 'hash_key' => '모바일 금액 위변조 Hash Key', 'api_key' => 'INIAPI Key', 'client_ip' => '결제 요청 서버 IPv4 주소'],
            'kcp' => ['merchant_id' => '상점 코드 (site_cd)', 'certificate' => '서비스 인증서 (PEM)', 'private_key' => '개인키 (PEM)', 'private_key_password' => '개인키 비밀번호'],
            'kspay' => ['merchant_id' => '상점 아이디 (MID)', 'api_key' => 'pgapi 인증키'],
            'toss' => ['merchant_id' => '상점 아이디 (MID)', 'client_key' => 'API 개별 연동 클라이언트 키 (ck)', 'secret_key' => 'API 개별 연동 시크릿 키 (sk)'],
        };
        $fields = [];
        foreach ($names as $key => $label) $fields[$key] = ['label' => $label, 'secret' => !in_array($key, ['merchant_id', 'client_ip'], true), 'multiline' => in_array($key, ['certificate', 'private_key'], true)];
        return $fields;
    }

    public static function manual(string $provider): string
    {
        return match ($provider) {
            'inicis' => 'https://manual.inicis.com/pay/',
            'kcp' => 'https://developer.kcp.co.kr/guide/litepay',
            'kspay' => 'https://paydev.ksnet.co.kr/kspay/webfep/doc',
            'toss' => 'https://docs.tosspayments.com/guides/v2/payment-window/integration',
        };
    }

    public static function validate(string $provider, array $input, array $before, string $environment = 'test'): array
    {
        $data = [];
        foreach (self::fields($provider) as $key => $field) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || strlen($value) > 16384 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) throw DomainError::validation([$key => '결제 연동 값을 확인해 주세요.']);
            if ($key !== 'private_key_password') $value = trim($value);
            if ($value === '' && $field['secret'] && ($data['merchant_id'] ?? '') === ($before['merchant_id'] ?? null)) $value = $before[$key] ?? '';
            if ($value === '' && $key !== 'private_key_password') throw DomainError::validation([$key => $field['label'] . '을 입력해 주세요.']);
            if (!$field['multiline'] && preg_match('/[\r\n]/', $value)) throw DomainError::validation([$key => '한 줄로 입력해 주세요.']);
            $data[$key] = $value;
        }
        $pattern = match ($provider) { 'inicis' => '/^[A-Za-z0-9]{10}$/D', 'kcp' => '/^[A-Z0-9]{5}$/D', 'kspay' => '/^[0-9]{10}$/D', 'toss' => '/^[A-Za-z0-9_-]{1,14}$/D' };
        if (!preg_match($pattern, $data['merchant_id'])) throw DomainError::validation(['merchant_id' => 'PG에서 발급한 상점 코드를 확인해 주세요.']);
        if ($provider === 'inicis') {
            if (!filter_var($data['client_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw DomainError::validation(['client_ip' => '서버의 IPv4 주소를 입력해 주세요.']);
            foreach (['sign_key', 'hash_key', 'api_key'] as $key) if (strlen($data[$key]) < 16 || preg_match('/\s/', $data[$key])) throw DomainError::validation([$key => '발급받은 인증키를 확인해 주세요.']);
        }
        if ($provider === 'kcp') {
            $cert = @openssl_x509_read($data['certificate']);
            $key = @openssl_pkey_get_private($data['private_key'], $data['private_key_password']);
            if ($cert === false || $key === false || !openssl_x509_check_private_key($cert, $key)
                || (openssl_pkey_get_details($key)['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) throw DomainError::validation(['certificate' => '서로 일치하는 RSA 인증서·개인키와 비밀번호를 입력해 주세요.']);
        }
        if ($provider === 'toss') {
            Settings::environment($environment);
            foreach (['client_key' => 'ck', 'secret_key' => 'sk'] as $key => $type) {
                if (!preg_match('/^' . $environment . '_' . $type . '_[A-Za-z0-9_-]{16,200}$/D', $data[$key])) {
                    throw DomainError::validation([$key => '선택한 환경의 API 개별 연동 키를 입력해 주세요. 주문서형·결제창형 연동 키(gck/gsk)는 사용할 수 없습니다.']);
                }
            }
        }
        return $data;
    }
}
