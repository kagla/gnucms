<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\App;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;

/** 확장 관리 화면의 공통 진입점. 개별 패키지의 실행 코드는 등록하지 않는다. */
final class AdminRoutes
{
    public static function register(SlimApp $slim, App $app): void
    {
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
            ) use ($app, $section, $page): ResponseInterface {
                $app->guestAcl()->assertGlobalAdmin();

                return View::fromRequest($request)->render($response, 'admin/extensions/index', [
                    'extension_section' => $section,
                    'extension_page' => $page,
                ]);
            })->setName('admin.' . $section);
        }
    }
}
