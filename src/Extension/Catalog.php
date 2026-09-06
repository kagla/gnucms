<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\Error\DomainError;
use RuntimeException;

/** 목록 조회에서는 패키지 PHP를 실행하지 않는다. */
final class Catalog
{
    public const API_VERSION = 1;

    public function __construct(private string $root)
    {
    }

    public function all(): array
    {
        $packages = [];
        foreach (['plugins' => 'plugin', 'modules' => 'module'] as $section => $type) {
            $root = $this->root . '/' . $section;
            $entries = is_dir($root) ? @scandir($root) : [];
            if ($entries === false) {
                throw DomainError::serviceUnavailable('확장 폴더를 읽을 수 없습니다: ' . $section);
            }
            foreach ($entries as $id) {
                $directory = $root . '/' . $id;
                if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id) || !is_dir($directory)) {
                    continue;
                }
                $key = $section . '/' . $id;
                $package = [
                    'key' => $key, 'id' => $id, 'section' => $section, 'name' => $id,
                    'description' => '', 'version' => '', 'requires' => [], 'optional' => [],
                    'entry_path' => null, 'admin_test' => false,
                    'directory' => $directory, 'error' => null,
                ];
                try {
                    if (is_link($root) || is_link($directory) || is_link($directory . '/extension.json')
                        || is_link($directory . '/bootstrap.php')) {
                        throw new RuntimeException('패키지 폴더와 진입 파일에는 심볼릭 링크를 사용할 수 없습니다.');
                    }
                    $raw = @file_get_contents($directory . '/extension.json');
                    $manifest = is_string($raw) ? json_decode($raw, true) : null;
                    if (!is_array($manifest) || ($manifest['id'] ?? null) !== $id
                        || ($manifest['type'] ?? null) !== $type) {
                        throw new RuntimeException('확장 설명 파일의 ID와 종류를 확인해 주세요.');
                    }
                    foreach (['name' => 100, 'description' => 1000, 'version' => 50] as $field => $max) {
                        $value = $manifest[$field] ?? ($field === 'description' ? '' : null);
                        if (!is_string($value) || strlen($value) > $max || ($field !== 'description' && trim($value) === '')) {
                            throw new RuntimeException('확장 이름·설명·버전 형식을 확인해 주세요.');
                        }
                        $package[$field] = $value;
                    }
                    if (($manifest['api'] ?? null) !== self::API_VERSION) {
                        throw new RuntimeException('지원하지 않는 확장 API 버전입니다.');
                    }
                    $entry = $manifest['entry_path'] ?? null;
                    $adminTest = $manifest['admin_test'] ?? false;
                    if (($entry !== null && (!is_string($entry)
                        || !preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$~D', $entry)))
                        || !is_bool($adminTest) || ($adminTest && $entry === null)) {
                        throw new RuntimeException('실행 주소 또는 관리자 테스트 설정이 올바르지 않습니다.');
                    }
                    $package['entry_path'] = $entry;
                    $package['admin_test'] = $adminTest;
                    foreach (['requires', 'optional'] as $field) {
                        $dependencies = $manifest[$field] ?? [];
                        if (!is_array($dependencies) || !array_is_list($dependencies)) {
                            throw new RuntimeException('의존 확장 목록이 올바르지 않습니다.');
                        }
                        foreach ($dependencies as $dependency) {
                            if (!is_string($dependency) || !preg_match('~^(plugins|modules)/[a-z][a-z0-9_-]{0,63}$~D', $dependency)) {
                                throw new RuntimeException('의존 확장 ID가 올바르지 않습니다.');
                            }
                        }
                        $package[$field] = array_values(array_unique($dependencies));
                    }
                    if (!is_file($directory . '/bootstrap.php') || !is_readable($directory . '/bootstrap.php')) {
                        throw new RuntimeException('패키지 실행 파일이 없습니다.');
                    }
                } catch (RuntimeException $e) {
                    $package['error'] = $e->getMessage();
                }
                $packages[$key] = $package;
            }
        }
        ksort($packages, SORT_STRING);
        return $packages;
    }
}
