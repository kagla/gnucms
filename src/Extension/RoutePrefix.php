<?php

declare(strict_types=1);

namespace GnuCms\Extension;

/** 패키지 ID와 별개로 사용하는 사이트 내부의 고정 주소. */
final class RoutePrefix
{
    public static function valid(mixed $prefix): bool
    {
        return is_string($prefix) && preg_match('~^/[a-z][a-z0-9_-]{0,63}$~D', $prefix) === 1
            && !in_array($prefix, ['/admin', '/modules', '/plugins', '/assets', '/themes', '/vendor', '/uploads', '/config', '/storage'], true);
    }

    public static function path(string $prefix, string $path): string
    {
        return $prefix . ($path === '/' ? '' : $path);
    }

    public static function validAdmin(mixed $prefix): bool
    {
        return is_string($prefix) && preg_match('~^/admin/[a-z][a-z0-9_-]{0,63}$~D', $prefix) === 1;
    }
}
