<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

final class Fixtures
{
    /** 이니시스 상점 설정 입력값. 실제 계정과 무관한 난수다. */
    public static function config(string $provider = 'inicis'): array
    {
        if ($provider !== 'inicis') throw new \InvalidArgumentException('inicis fixture only');
        return ['merchant_id' => 'gnu' . substr(bin2hex(random_bytes(4)), 0, 7), 'sign_key' => bin2hex(random_bytes(32)),
            'hash_key' => bin2hex(random_bytes(16)), 'api_key' => bin2hex(random_bytes(16)), 'client_ip' => '192.0.2.10'];
    }
}
