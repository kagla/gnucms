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
    private array $routes = [];
    private array $services = [];

    public function __construct(
        public readonly App $app,
        public readonly string $key,
        private array $availableServices
    ) {
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
    public function route(string $method, string $path, callable $handler, bool $admin = false): void
    {
        if (!in_array($method, ['GET', 'POST'], true)
            || !preg_match('~^/(?:[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*)?$~D', $path)) {
            throw new InvalidArgumentException('확장 라우트의 메서드 또는 경로가 올바르지 않습니다.');
        }
        $url = '/extensions/' . $this->key . $path;
        if (isset($this->routes[$method . ' ' . $url])) {
            throw new InvalidArgumentException('확장 라우트가 중복됩니다.');
        }
        $app = $this->app;
        $this->routes[$method . ' ' . $url] = [$method, $url, static function ($request, $response, $args) use ($handler, $admin, $method, $app) {
            if ($admin) {
                $app->guestAcl()->assertGlobalAdmin();
            }
            if ($method === 'POST') {
                self::assertCsrf($request);
            }
            return $handler($request, $response, $args);
        }];
    }

    public function routes(): array
    {
        return array_values($this->routes);
    }

    public function services(): array
    {
        return $this->services;
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
