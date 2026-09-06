<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\Error\DomainError;

/** 백업에서 제외하는 명시적 외부 실행 허용값. 복원 시 전부 해제한다. */
final class RuntimePermit
{
    public function __construct(private string $storageDir)
    {
    }

    public function allowed(string $key, string $revision): bool
    {
        $path = $this->path($key);
        $value = is_file($path) ? @file_get_contents($path) : false;
        return is_string($value) && hash_equals(hash('sha256', $revision), $value);
    }

    public function set(string $key, ?string $revision): void
    {
        $path = $this->path($key);
        if ($revision === null) {
            if (file_exists($path) && !unlink($path)) throw DomainError::serviceUnavailable('발송 정지를 저장하지 못했습니다.');
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw DomainError::serviceUnavailable('실행 허용 폴더를 만들지 못했습니다.');
        }
        $tmp = tempnam($dir, '.permit-');
        try {
            if ($tmp === false || file_put_contents($tmp, hash('sha256', $revision)) !== 64 || !rename($tmp, $path)) {
                throw DomainError::serviceUnavailable('실행 허용값을 저장하지 못했습니다.');
            }
        } finally {
            if (is_string($tmp) && is_file($tmp)) unlink($tmp);
        }
    }

    public function revokeAll(): void
    {
        foreach (glob($this->storageDir . '/extensions-runtime/permits/*') ?: [] as $path) {
            if (is_file($path) && !unlink($path)) throw DomainError::serviceUnavailable('확장 실행 허용값을 해제하지 못했습니다.');
        }
        $dir = $this->storageDir . '/extensions-runtime';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw DomainError::serviceUnavailable('확장 런타임 폴더를 만들지 못했습니다.');
        $tmp = tempnam($dir, '.generation-');
        try {
            if ($tmp === false || file_put_contents($tmp, bin2hex(random_bytes(32))) !== 64 || !rename($tmp, $dir . '/generation')) {
                throw DomainError::serviceUnavailable('확장 캐시를 무효화하지 못했습니다.');
            }
        } finally {
            if (is_string($tmp) && is_file($tmp)) unlink($tmp);
        }
    }

    public function generation(): string
    {
        $path = $this->storageDir . '/extensions-runtime/generation';
        if (!is_file($path)) return 'initial';
        $value = file_get_contents($path);
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw DomainError::serviceUnavailable('확장 런타임 상태를 확인해 주세요.');
        return $value;
    }

    private function path(string $key): string
    {
        return $this->storageDir . '/extensions-runtime/permits/' . hash('sha256', $key);
    }
}
