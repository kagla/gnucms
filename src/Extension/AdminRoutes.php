<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;
use Slim\Routing\RouteContext;

/** 코어는 이 공통 연결점만 호출하고 개별 패키지를 알지 않는다. */
final class AdminRoutes
{
    public static function register(SlimApp $slim, App $app): void
    {
        $manager = new Manager(
            new Catalog((string) $app->config('extensions.root', dirname(__DIR__, 2))),
            new StateStore($app->storageDir() . '/extensions')
        );
        $pages = [
            'plugins' => [
                'title' => '플러그인',
                'description' => '알림톡처럼 여러 모듈에서 함께 사용할 수 있는 기능을 관리합니다.',
                'icon' => 'sparkle',
            ],
            'modules' => [
                'title' => '모듈',
                'description' => '예약 시스템처럼 독립적으로 동작하거나 플러그인을 조합해 사용하는 프로그램을 관리합니다.',
                'icon' => 'grid',
            ],
        ];

        foreach ($pages as $section => $page) {
            $slim->get('/admin/' . $section, static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ) use ($app, $section, $page, $manager): ResponseInterface {
                $app->guestAcl()->assertGlobalAdmin();
                return self::render($request, $response, $manager, $section, $page);
            })->setName('admin.' . $section);

            $slim->post('/admin/' . $section . '/{id:[a-z][a-z0-9_-]{0,63}}/state', static function (
                ServerRequestInterface $request,
                ResponseInterface $response,
                array $args
            ) use ($app, $section, $page, $manager): ResponseInterface {
                $app->guestAcl()->assertGlobalAdmin();
                Context::assertCsrf($request);
                $input = $request->getParsedBody();
                try {
                    if (!in_array($input['enabled'] ?? null, ['0', '1'], true)) {
                        throw DomainError::validation(['extension' => '사용 여부를 확인해 주세요.']);
                    }
                    $manager->setEnabled($section . '/' . $args['id'], $input['enabled'] === '1');
                } catch (DomainError $e) {
                    return self::render($request, $response->withStatus($e->status()), $manager, $section, $page,
                        $e->details()['extension'] ?? $e->getMessage());
                }
                $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.' . $section);
                return $response->withStatus(303)->withHeader('Location', $url . '?saved=1');
            })->setName('admin.' . $section . '.state');
        }

        try {
            $manager->boot($app, $slim);
        } catch (DomainError $e) {
            // 상태 파일이 손상돼도 코어와 복구용 관리 화면은 열 수 있어야 한다.
        }
    }

    private static function render(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Manager $manager,
        string $section,
        array $page,
        ?string $error = null
    ): ResponseInterface {
        $packages = [];
        try {
            $packages = array_filter($manager->packages(), static fn (array $item): bool => $item['section'] === $section);
        } catch (DomainError $e) {
            $error = $e->getMessage();
            $response = $response->withStatus($e->status());
        }
        return View::fromRequest($request)->render($response, 'admin/extensions/index', [
            'extension_section' => $section,
            'extension_page' => $page,
            'packages' => $packages,
            'extension_error' => $error,
            'saved' => $error === null && ($request->getQueryParams()['saved'] ?? '') === '1',
        ]);
    }
}
