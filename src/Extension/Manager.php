<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\App;
use GnuCms\Error\DomainError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;
use Throwable;

final class Manager
{
    private array $runtimeErrors = [];
    private array $services = [];

    public function __construct(private Catalog $catalog, private StateStore $state)
    {
    }

    public function packages(): array
    {
        $packages = $this->catalog->all();
        $snapshot = $this->state->snapshot();
        $enabled = $snapshot['enabled'];
        foreach ($enabled as $key) {
            if (!isset($packages[$key])) {
                [$section, $id] = explode('/', $key, 2);
                $packages[$key] = [
                    'key' => $key, 'id' => $id, 'section' => $section, 'name' => $id,
                    'description' => '', 'version' => '', 'requires' => [], 'optional' => [],
                    'entry_path' => null, 'admin_test' => false,
                    'error' => '활성화된 패키지의 파일을 찾을 수 없습니다.',
                ];
            }
        }
        foreach ($packages as $key => &$package) {
            $package['enabled'] = in_array($key, $enabled, true);
            $package['error'] = $package['error'] ?? $this->runtimeErrors[$key] ?? null;
        }
        unset($package);
        $rank = array_flip($snapshot['recent']);
        uasort($packages, static fn (array $a, array $b): int =>
            (($rank[$a['key']] ?? PHP_INT_MAX) <=> ($rank[$b['key']] ?? PHP_INT_MAX))
            ?: strcmp($a['key'], $b['key']));
        return $packages;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        $this->setEnabledMany([$key => $enabled]);
    }

    /** 전체 후보 상태를 검증한 후 한 번에 저장한다. $recentFirst는 마지막 조작부터 나열한다. */
    public function setEnabledMany(array $changes, array $recentFirst = []): void
    {
        if ($changes === []) {
            return;
        }
        $this->state->update(function (array $active) use ($changes): array {
            $packages = $this->catalog->all();
            $candidate = $active;
            foreach ($changes as $key => $enabled) {
                if (!is_bool($enabled)) {
                    throw DomainError::validation(['extension' => '사용 여부를 확인해 주세요.']);
                }
                if (!isset($packages[$key]) && !in_array($key, $active, true)) {
                    throw DomainError::notFound('확장을 찾을 수 없습니다.');
                }
                $candidate = array_values(array_diff($candidate, [$key]));
                if ($enabled) {
                    $candidate[] = $key;
                }
            }
            foreach ($changes as $key => $enabled) {
                if ($enabled) {
                    $this->order($key, $packages, $candidate, [], []);
                    continue;
                }
                foreach ($packages as $id => $package) {
                    if ($id !== $key && in_array($id, $candidate, true) && in_array($key, $package['requires'], true)) {
                        try {
                            $this->order($id, $packages, $active, [], []);
                        } catch (DomainError $e) {
                            // 배포로 생긴 순환·누락 상태도 관리자에서 끄며 복구할 수 있다.
                            continue;
                        }
                        throw DomainError::validation(['extension' => $package['name'] . '에서 사용 중입니다. 먼저 해당 확장을 꺼 주세요.']);
                    }
                }
            }
            return $candidate;
        }, array_merge($recentFirst, array_keys($changes)));
    }

    public function boot(App $app, SlimApp $slim): void
    {
        $this->runtimeErrors = [];
        $this->services = [];
        $packages = $this->catalog->all();
        $active = $this->state->read();
        $order = [];
        foreach ($active as $key) {
            try {
                $order = $this->order($key, $packages, $active, [], $order);
            } catch (DomainError $e) {
                $this->runtimeErrors[$key] = $e->details()['extension'] ?? $e->getMessage();
            }
        }
        $services = [];
        foreach ($order as $key) {
            $package = $packages[$key];
            foreach ($package['requires'] as $dependency) {
                if (isset($this->runtimeErrors[$dependency])) {
                    $this->runtimeErrors[$key] = '필수 확장을 실행하지 못했습니다: ' . $dependency;
                    continue 2;
                }
            }
            try {
                $context = new Context($app, $key, $services);
                $register = (static fn (string $file) => require $file)($package['directory'] . '/bootstrap.php');
                if (!is_callable($register)) {
                    throw new \RuntimeException('Invalid extension entry point');
                }
                $register($context);
                // 등록이 모두 성공한 패키지만 서비스와 라우트를 공개한다.
                $services[$key] = $context->services();
                foreach ($context->routes() as [$method, $url, $handler]) {
                    $slim->map([$method], $url, $handler);
                }
            } catch (Throwable $e) {
                // 업체 API 키 등이 포함될 수 있으므로 예외 원문을 화면이나 로그에 남기지 않는다.
                $this->runtimeErrors[$key] = '확장 실행에 실패했습니다. 패키지를 점검하거나 사용을 꺼 주세요.';
            }
        }
        $this->services = $services;
    }

    /** 관리자 전용 요청에서만 미사용 패키지를 일시적으로 불러온다. 상태 파일에는 쓰지 않는다. */
    public function test(App $app, string $key, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // bootstrap.php를 읽기 전에 권한과 CSRF를 검사한다.
        $app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') {
            Context::assertCsrf($request);
        }
        $packages = $this->catalog->all();
        $package = $packages[$key] ?? null;
        if ($package === null || !$package['admin_test']) {
            throw DomainError::notFound('관리자 테스트를 지원하는 확장이 아닙니다.');
        }
        $active = $this->state->read();
        $this->order($key, $packages, array_values(array_unique([...$active, $key])), [], []);
        foreach ($package['requires'] as $dependency) {
            if (isset($this->runtimeErrors[$dependency])) {
                throw DomainError::serviceUnavailable('필수 확장을 실행하지 못했습니다: ' . $dependency);
            }
        }
        try {
            $context = new Context($app, $key, $this->services);
            $register = (static fn (string $file) => require $file)($package['directory'] . '/bootstrap.php');
            if (!is_callable($register)) {
                throw new \RuntimeException('Invalid extension entry point');
            }
            $register($context);
        } catch (Throwable $e) {
            throw DomainError::serviceUnavailable('확장 테스트를 준비하지 못했습니다. 패키지를 확인해 주세요.');
        }
        foreach ($context->routes() as [$method, $url, $handler]) {
            if ($method === $request->getMethod() && $url === '/extensions/' . $key . $package['entry_path']) {
                return $handler($request->withAttribute(Context::TEST_ATTRIBUTE, true), $response, [])
                    ->withHeader('Cache-Control', 'no-store')
                    ->withHeader('X-Robots-Tag', 'noindex, nofollow');
            }
        }
        throw DomainError::notFound('패키지에 테스트할 실행 경로가 등록되어 있지 않습니다.');
    }

    private function order(string $key, array $packages, array $active, array $visiting, array $ordered): array
    {
        if (isset($visiting[$key])) {
            throw DomainError::validation(['extension' => '확장 간 순환 의존성이 있습니다: ' . $key]);
        }
        if (in_array($key, $ordered, true)) {
            return $ordered;
        }
        $package = $packages[$key] ?? null;
        if ($package === null || $package['error'] !== null || !in_array($key, $active, true)) {
            throw DomainError::validation(['extension' => '필요한 확장을 사용할 수 없습니다: ' . $key
                . ($package !== null && $package['error'] !== null ? ' (' . $package['error'] . ')' : '')]);
        }
        $visiting[$key] = true;
        foreach ($package['requires'] as $dependency) {
            $ordered = $this->order($dependency, $packages, $active, $visiting, $ordered);
        }
        foreach ($package['optional'] as $dependency) {
            if (in_array($dependency, $active, true)) {
                try {
                    $ordered = $this->order($dependency, $packages, $active, $visiting, $ordered);
                } catch (DomainError $e) {
                    // 선택 확장이 없어도 모듈 자체는 실행할 수 있다.
                }
            }
        }
        $ordered[] = $key;
        return $ordered;
    }
}
