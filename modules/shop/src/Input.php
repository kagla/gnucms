<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;

final class Input
{
    public static function text(mixed $value, string $label, int $max = 200, bool $optional = false): string
    {
        if (!is_string($value) || strlen($value) > $max * 4 || (!$optional && trim($value) === '')
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value) || !preg_match('//u', $value)
            || mb_strlen($value, 'UTF-8') > $max) {
            throw DomainError::validation([$label => $label . ' 입력을 확인해 주세요.']);
        }
        return trim($value);
    }

    public static function integer(mixed $value, string $label, int $max = 999999999, int $min = 0): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]{0,9})$/D', (string) $value)
            || (int) $value < $min || (int) $value > $max) throw DomainError::validation([$label => $label . '은 ' . $min . ' ~ ' . $max . ' 범위의 정수로 입력해 주세요.']);
        return (int) $value;
    }

    public static function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{32}$/D', $value)) throw DomainError::notFound('항목을 찾을 수 없습니다.');
        return $value;
    }

    public static function customer(array $input): array
    {
        $result = [];
        foreach (['name' => '받는 분', 'phone' => '연락처', 'email' => '이메일', 'postcode' => '우편번호',
            'address' => '주소', 'address_detail' => '상세 주소', 'memo' => '배송 요청사항'] as $key => $label) {
            $result[$key] = self::text($input[$key] ?? '', $label, $key === 'memo' ? 600 : 200, in_array($key, ['memo', 'address_detail'], true));
        }
        $result['phone'] = str_replace(['-', ' '], '', $result['phone']);
        if (!preg_match('/^0[0-9]{8,10}$/D', $result['phone'])) throw DomainError::validation(['phone' => '국내 연락처를 입력해 주세요.']);
        if (!filter_var($result['email'], FILTER_VALIDATE_EMAIL) || strlen($result['email']) > 100) throw DomainError::validation(['email' => '이메일 주소를 확인해 주세요.']);
        if (!preg_match('/^[0-9]{5}$/D', $result['postcode'])) throw DomainError::validation(['postcode' => '다섯 자리 우편번호를 입력해 주세요.']);
        return $result;
    }

    public static function date(mixed $value): string
    {
        $value = self::text($value, '날짜', 10);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Asia/Seoul'));
        if ($date === false || $date->format('Y-m-d') !== $value) throw DomainError::validation(['date' => '날짜를 확인해 주세요.']);
        return $value;
    }
}
