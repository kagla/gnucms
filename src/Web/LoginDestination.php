<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\Support\Clock;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/** 로그인 전 서버가 선택한 화면을 같은 설치 경로 안에서 한 번만 복원한다. */
final class LoginDestination
{
    public static function remember(string $path): void
    {
        $_SESSION['login_destination'] = ['path' => $path, 'expires' => Clock::timestamp() + 1800];
    }

    public static function consume(ServerRequestInterface $request): string
    {
        $destination = $_SESSION['login_destination'] ?? null;
        unset($_SESSION['login_destination']);
        $route = RouteContext::fromRequest($request);
        $fallback = $route->getRouteParser()->urlFor('boards.index');
        if (!is_array($destination) || !is_string($destination['path'] ?? null)
            || !is_int($destination['expires'] ?? null) || $destination['expires'] < Clock::timestamp()) {
            return $fallback;
        }
        $path = $destination['path'];
        $decoded = rawurldecode($path);
        $base = $route->getBasePath();
        if (!str_starts_with($path, $base . '/') || !str_starts_with($decoded, '/')
            || str_starts_with($decoded, '//') || preg_match('/[\\x00-\\x20\\x7f\\\\\\\\]/', $decoded)
            || preg_match('~(?:^|/)\\.\\.?(?:/|[?#]|$)~', $decoded) || str_contains($decoded, '%')) {
            return $fallback;
        }
        return $path;
    }
}
