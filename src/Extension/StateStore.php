<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\Error\DomainError;

/** 잠금 아래 읽고 원자적으로 교체한다. 비활성화해도 패키지 데이터는 지우지 않는다. */
final class StateStore
{
    public function __construct(private string $directory)
    {
    }

    public function read(): array
    {
        $file = $this->directory . '/enabled.json';
        if (!file_exists($file)) {
            return [];
        }
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state) || !array_is_list($state)) {
            throw DomainError::serviceUnavailable('확장 사용 상태를 읽을 수 없습니다. 저장 파일을 확인해 주세요.');
        }
        foreach ($state as $id) {
            if (!is_string($id) || !preg_match('~^(plugins|modules)/[a-z][a-z0-9_-]{0,63}$~D', $id)) {
                throw DomainError::serviceUnavailable('확장 사용 상태 파일이 올바르지 않습니다.');
            }
        }
        return array_values(array_unique($state));
    }

    /** @param callable(array):array $update */
    public function update(callable $update): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw DomainError::serviceUnavailable('확장 설정을 저장할 폴더를 만들 수 없습니다.');
        }
        $lock = @fopen($this->directory . '/state.lock', 'c');
        if ($lock === false) {
            throw DomainError::serviceUnavailable('확장 설정 잠금 파일을 열 수 없습니다.');
        }
        $temporary = false;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw DomainError::serviceUnavailable('확장 설정을 잠글 수 없습니다.');
            }
            $state = array_values(array_unique($update($this->read())));
            sort($state, SORT_STRING);
            $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
            $temporary = @tempnam($this->directory, '.state-');
            if ($temporary === false || @file_put_contents($temporary, $json) !== strlen($json)
                || !@rename($temporary, $this->directory . '/enabled.json')) {
                throw DomainError::serviceUnavailable('확장 사용 상태를 저장하지 못했습니다.');
            }
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
