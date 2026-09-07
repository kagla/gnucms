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
        return $this->snapshot()['enabled'];
    }

    /** 기존 ID 배열도 읽고 다음 저장부터 순서 이력을 포함한 형식으로 옮긴다. */
    public function snapshot(): array
    {
        $file = $this->directory . '/enabled.json';
        if (!file_exists($file)) {
            return ['enabled' => [], 'recent' => []];
        }
        $raw = is_file($file) ? @file_get_contents($file) : false;
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state)) {
            throw DomainError::serviceUnavailable('확장 사용 상태를 읽을 수 없습니다. 저장 파일을 확인해 주세요.');
        }
        if (array_is_list($state)) {
            $state = ['enabled' => $state, 'recent' => []];
        } elseif (($state['version'] ?? null) !== 2) {
            throw DomainError::serviceUnavailable('확장 사용 상태 파일이 올바르지 않습니다.');
        }
        foreach (['enabled', 'recent'] as $field) {
            if (!isset($state[$field]) || !is_array($state[$field]) || !array_is_list($state[$field])) {
                throw DomainError::serviceUnavailable('확장 사용 상태 파일이 올바르지 않습니다.');
            }
            foreach ($state[$field] as $id) {
                if (!is_string($id) || !preg_match('~^(plugins|modules)/[a-z][a-z0-9_-]{0,63}$~D', $id)) {
                    throw DomainError::serviceUnavailable('확장 사용 상태 파일이 올바르지 않습니다.');
                }
            }
            $state[$field] = array_values(array_unique($state[$field]));
        }
        return ['enabled' => $state['enabled'], 'recent' => $state['recent']];
    }

    /**
     * @param callable(array):array $update
     * @param string[] $recentFirst 최근에 조작한 ID부터
     */
    public function update(callable $update, array $recentFirst = []): void
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
            $before = $this->snapshot();
            $enabled = array_values(array_unique($update($before['enabled'])));
            $changed = array_merge(array_diff($enabled, $before['enabled']), array_diff($before['enabled'], $enabled));
            // 동일 값 저장은 최근 순서를 바꾸지 않는다. 끈 항목의 이력도 유지한다.
            $recent = array_values(array_unique(array_merge(
                array_intersect($recentFirst, $changed), $changed, $before['recent']
            )));
            sort($enabled, SORT_STRING);
            $json = json_encode(['version' => 2, 'enabled' => $enabled, 'recent' => $recent], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
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
