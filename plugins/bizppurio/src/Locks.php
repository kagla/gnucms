<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;

final class Locks
{
    public static function run(string $path, callable $work, int $mode = LOCK_EX): mixed
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw DomainError::serviceUnavailable('작업 잠금 폴더를 만들지 못했습니다.');
        $lock = @fopen($path, 'c');
        if ($lock === false) throw DomainError::serviceUnavailable('작업 잠금을 열지 못했습니다.');
        try {
            if (!flock($lock, $mode | LOCK_NB)) throw DomainError::serviceUnavailable('다른 작업이 진행 중입니다. 잠시 후 다시 시도해 주세요.');
            return $work();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
