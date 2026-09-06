<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use GnuCms\View\PhpView;
use Psr\Http\Message\UriInterface;
use Slim\Interfaces\RouteParserInterface;

/** 컨트롤러·브라우저 단위 검증에서 운영 서버 없이 공통 관리자 템플릿을 렌더링한다. */
final class AdminViewFixture
{
    public static function view(string $base = '', ?callable $htmlRenderer = null): PhpView
    {
        $routes = new class($base) implements RouteParserInterface {
            public function __construct(private string $base) {}
            public function relativeUrlFor(string $routeName, array $data = [], array $queryParams = []): string
            {
                $path = match ($routeName) {
                    'boards.index' => '/', 'admin.index' => '/admin', 'auth.login' => '/login', 'auth.logout' => '/logout',
                    'auth.register' => '/register', 'account.edit' => '/account', 'seo.rss' => '/rss.xml',
                    default => '/' . str_replace('.', '/', $routeName),
                };
                return $path . ($data ? '/' . implode('/', array_map('rawurlencode', $data)) : '') . ($queryParams ? '?' . http_build_query($queryParams) : '');
            }
            public function urlFor(string $routeName, array $data = [], array $queryParams = []): string { return $this->base . $this->relativeUrlFor($routeName, $data, $queryParams); }
            public function fullUrlFor(UriInterface $uri, string $routeName, array $data = [], array $queryParams = []): string { return $uri->getScheme() . '://' . $uri->getAuthority() . $this->urlFor($routeName, $data, $queryParams); }
        };
        $view = new PhpView([dirname(__DIR__, 2) . '/templates/default'], $routes, $base,
            static fn (string $path): string => $base . '/themes/default/' . $path, $htmlRenderer ?? static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        foreach ([
            'site' => ['site_name' => 'GNUCMS 테스트', 'site_tagline' => '운영 화면 검증', 'timezone' => 'Asia/Seoul'],
            'current_user' => ['is_guest' => false, 'is_admin' => true, 'display_name' => '운영자', 'avatar_file' => null],
            'csrf_token' => $_SESSION['csrf_token'] ?? 'browser-test-csrf', 'unread_notifications' => 0,
            'registration_available' => true, 'header_boards' => [], 'site_menu' => [], 'legal_pages' => [],
            'site_url' => 'https://gnucms.test' . $base, 'base_path' => $base, 'active_theme' => 'default', 'available_themes' => ['default'],
        ] as $key => $value) $view->addGlobal($key, $value);
        return $view;
    }
}
