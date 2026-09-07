<?php

declare(strict_types=1);

namespace GnuCms\Web;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;

/** 로그인 복귀 주소는 현재 설치 안의 경로만 허용한다. */
final class LoginRedirect
{
    public static function validate(mixed $url, string $home): ?string
    {
        if (!is_string($url) || $url === '' || strlen($url) > 4096
            || !str_starts_with($url, '/') || str_starts_with($url, '//')
            || preg_match('~[\x00-\x20\x7f\\\\]~', $url)
            || preg_match('~[\x00-\x1f\x7f]~', rawurldecode($url))) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['host']) || isset($parts['scheme']) || !isset($parts['path'])) {
            return null;
        }
        $path = rawurldecode($parts['path']);
        if (!str_starts_with($parts['path'], $home) || !str_starts_with($path, $home)
            || str_starts_with($path, '//') || preg_match('~[\x00-\x20\x7f\\\\%?#]~', $path)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) {
            return null;
        }
        $base = rtrim($home, '/');
        if (in_array(rtrim($path, '/'), [$base . '/login', $base . '/logout', $base . '/auth'], true)
            || str_starts_with($path, $base . '/auth/')) {
            return null;
        }
        return $url;
    }

    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $url = is_array($input) ? ($input['url'] ?? null) : null;
        $home = RouteContext::fromRequest($request)->getRouteParser()->urlFor('boards.index');
        return self::validate($url, $home);
    }

    public static function loginUrl(RouteParserInterface $router, ?string $url = null): string
    {
        $url = self::validate($url, $router->urlFor('boards.index'));
        return $router->urlFor('auth.login', [], $url === null ? [] : ['url' => $url]);
    }

    public static function destination(ServerRequestInterface $request, mixed $url): string
    {
        $home = RouteContext::fromRequest($request)->getRouteParser()->urlFor('boards.index');
        return self::validate($url, $home) ?? $home;
    }
}
