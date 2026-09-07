<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;

final class Input
{
    public static function text(mixed $value, string $field, int $max, bool $empty = false): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0")
            || (!$empty && trim($value) === '') || preg_match_all('/./us', $value) > $max) {
            throw DomainError::validation([$field => $field . ' 입력값을 확인해 주세요.']);
        }
        return $value;
    }

    public static function environment(mixed $value): string
    {
        if (!in_array($value, ['test', 'live'], true)) throw DomainError::validation(['environment' => '검수 또는 운영 환경을 선택해 주세요.']);
        return $value;
    }

    public static function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/D', $value)) throw DomainError::validation(['id' => '대상을 확인해 주세요.']);
        return $value;
    }

    public static function phone(mixed $value): string
    {
        $value = str_replace(['-', ' '], '', self::text($value, '수신번호', 20));
        if (!preg_match('/^(?:010\d{8}|01[16789]\d{7,8})$/D', $value)) throw DomainError::validation(['phone' => '국내 휴대폰 번호를 입력해 주세요.']);
        return $value;
    }
}
