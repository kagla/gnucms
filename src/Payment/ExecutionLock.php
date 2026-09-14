<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** 결제 중 복원을 막는다. 결제 요청끼리의 동시성은 DB와 결제 요청 키로 제어한다. */
final class ExecutionLock
{
    public static function run(string $storage, callable $work): mixed
    {
        return self::file($storage . '/upgrade.lock', LOCK_SH, $work);
    }

    public static function settings(string $storage, callable $work): mixed
    {
        return self::run($storage, static fn () => self::file($storage . '/extensions-runtime/payment-settings.lock', LOCK_EX, $work));
    }

    private static function file(string $path, int $mode, callable $work): mixed
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw DomainError::serviceUnavailable('결제 작업 폴더를 만들지 못했습니다.');
        $handle = fopen($path, 'c');
        if ($handle === false) throw DomainError::serviceUnavailable('결제 작업 잠금을 열지 못했습니다.');
        if (!flock($handle, $mode | LOCK_NB)) { fclose($handle); throw DomainError::serviceUnavailable('백업·복원 또는 설정 변경 중입니다. 잠시 뒤 다시 시도해 주세요.'); }
        try { return $work(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
