<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\App;
use GnuCms\Error\DomainError;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/** 신뢰하는 패키지의 등록 API. 요청 처리는 route()의 콜백 안에서 수행한다. */
final class Context
{
    public const TEST_ATTRIBUTE = 'gnucms.extension_test';

    private array $routes = [];
    private array $services = [];
    private array $external = [];
    public readonly string $routePrefix;

    public function __construct(
        public readonly App $app,
        public readonly string $key,
        private array $availableServices,
        ?string $routePrefix = null,
        public readonly ?string $adminRoutePrefix = null
    ) {
        if ($routePrefix !== null && !RoutePrefix::valid($routePrefix)) {
            throw new InvalidArgumentException('확장 기본 주소가 올바르지 않습니다.');
        }
        $this->routePrefix = $routePrefix ?? '/' . $key;
        if ($adminRoutePrefix !== null && !RoutePrefix::validAdmin($adminRoutePrefix)) {
            throw new InvalidArgumentException('확장 관리자 주소가 올바르지 않습니다.');
        }
    }

    public function path(string $path): string { return RoutePrefix::path($this->routePrefix, $path); }

    /** 기존 폼·북마크·외부 콜백도 동일한 권한·인증 검사를 거친다. */
    private function paths(string $path): array
    {
        $paths = [$this->path($path), '/' . $this->key . $path];
        if ($path === '/') $paths[] = $this->routePrefix . '/';
        return array_values(array_unique($paths));
    }

    public function provide(string $name, object $service): void
    {
        if ($name === '' || isset($this->services[$name])) {
            throw new InvalidArgumentException('서비스 이름이 비어 있거나 중복됩니다.');
        }
        $this->services[$name] = $service;
    }

    public function service(string $package, string $name): ?object
    {
        return $this->availableServices[$package][$name] ?? null;
    }

    /** 초기 API는 고정 경로의 GET/POST를 제공한다. POST에는 CSRF 검사가 적용된다. */
    public function route(string $method, string $path, callable $handler, bool $admin = false, ?string $legacyPath = null): void
    {
        if (!in_array($method, ['GET', 'POST'], true)
            || !preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$~D', $path)
            || ($legacyPath !== null && (!$admin || $this->adminRoutePrefix === null
                || !preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$~D', $legacyPath)))) {
            throw new InvalidArgumentException('확장 라우트의 메서드 또는 경로가 올바르지 않습니다.');
        }
        $paths = $this->paths($legacyPath ?? $path);
        if ($admin && $this->adminRoutePrefix !== null) {
            array_unshift($paths, RoutePrefix::path($this->adminRoutePrefix, $path));
            if ($path === '/') $paths[] = $this->adminRoutePrefix . '/';
        }
        $paths = array_values(array_unique($paths));
        foreach ($paths as $url) {
            if (isset($this->routes[$method . ' ' . $url]) || isset($this->external[$url])) {
                throw new InvalidArgumentException('확장 라우트가 중복됩니다.');
            }
        }
        $app = $this->app;
        $wrapped = static function ($request, $response, $args) use ($handler, $admin, $method, $app) {
            if ($admin) {
                $app->guestAcl()->assertGlobalAdmin();
            }
            if ($method === 'POST') {
                self::assertCsrf($request);
            }
            return $handler($request, $response, $args);
        };
        foreach ($paths as $url) $this->routes[$method . ' ' . $url] = [$method, $url, $wrapped];
    }

    public function routes(): array
    {
        return array_values($this->routes);
    }

    public function services(): array
    {
        return $this->services;
    }

    /** API 2: 세션과 독립적인 콜백. 기본 JSON, PG 인증 결과는 폼 형식을 명시한다. */
    public function externalPost(string $path, callable $authenticate, callable $handler, int $maxBytes = 65536, string $contentType = 'application/json'): void
    {
        if (!preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$~D', $path)
            || $maxBytes < 1 || $maxBytes > 1048576 || !in_array($contentType, ['application/json', 'application/x-www-form-urlencoded'], true)) {
            throw new InvalidArgumentException('외부 콜백 경로나 크기 제한이 올바르지 않습니다.');
        }
        foreach ($this->paths($path) as $url) {
            if (isset($this->external[$url]) || isset($this->routes['POST ' . $url]) || isset($this->routes['GET ' . $url])) {
                throw new InvalidArgumentException('확장 라우트가 중복됩니다.');
            }
        }
        foreach ($this->paths($path) as $url) $this->external[$url] = [$authenticate, $handler, $maxBytes, $contentType];
    }

    public function externalRoutes(): array
    {
        return $this->external;
    }

    public static function assertCsrf(ServerRequestInterface $request): void
    {
        $input = $request->getParsedBody();
        $given = is_array($input) ? ($input['csrf_token'] ?? null) : null;
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($given) || !is_string($expected) || $expected === '' || !hash_equals($expected, $given)) {
            throw DomainError::forbidden('요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
    }
}
