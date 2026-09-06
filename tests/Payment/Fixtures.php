<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

final class Fixtures
{
    public static function config(string $provider, ?string $password = null): array
    {
        if ($provider === 'inicis') return ['merchant_id' => 'gnu' . substr(bin2hex(random_bytes(4)), 0, 7), 'sign_key' => bin2hex(random_bytes(32)),
            'hash_key' => bin2hex(random_bytes(16)), 'api_key' => bin2hex(random_bytes(16)), 'client_ip' => '192.0.2.10'];
        if ($provider === 'kspay') return ['merchant_id' => '2999900000', 'api_key' => base64_encode(random_bytes(48))];
        $file = tempnam(sys_get_temp_dir(), 'gnucms-test-openssl-');
        file_put_contents($file, "[req]\ndistinguished_name=dn\n[dn]\nCN=example.test\n");
        try {
            $options = ['config' => $file, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
            $key = openssl_pkey_new($options);
            $password ??= bin2hex(random_bytes(16)); openssl_pkey_export($key, $private, $password, $options);
            $csr = openssl_csr_new(['commonName' => 'example.test'], $key, $options);
            $cert = openssl_csr_sign($csr, null, $key, 1, $options); openssl_x509_export($cert, $certificate);
        } finally { unlink($file); }
        return ['merchant_id' => 'T9999', 'certificate' => $certificate, 'private_key' => $private, 'private_key_password' => $password];
    }
}
