<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Messaging\Input;

/** 구매자 휴대폰 번호의 정규화·해시·마스킹. 원문은 암호화해서만 저장한다. */
final class Phone
{
    public static function normalize(mixed $value): string
    {
        return Input::phone($value);
    }

    public static function hash(string $digits, string $secret): string
    {
        return hash_hmac('sha256', 'initalk:phone:' . $digits, $secret);
    }

    public static function mask(string $digits): string
    {
        if ($digits === '') return '';
        $parts = self::parts($digits);
        return $parts[0] . '-' . str_repeat('*', strlen($parts[1])) . '-' . $parts[2];
    }

    public static function format(string $digits): string
    {
        if ($digits === '') return '';
        return implode('-', self::parts($digits));
    }

    /** @return array{string,string,string} */
    private static function parts(string $digits): array
    {
        $middle = strlen($digits) === 10 ? 3 : 4;
        return [substr($digits, 0, 3), substr($digits, 3, $middle), substr($digits, 3 + $middle)];
    }
}
