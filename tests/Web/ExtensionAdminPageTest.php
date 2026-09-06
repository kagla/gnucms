<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExtensionAdminPageTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testExtensionPagesRequireGlobalAdmin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        foreach (['plugins', 'modules'] as $section) {
            self::assertSame(401, $this->get($app, '/admin/' . $section)->getStatusCode());
        }

        $memberId = $app->users()->create('extension-member@example.com', '', '일반회원');
        session_start();
        $_SESSION['user_id'] = $memberId;
        $_SESSION['session_epoch'] = 0;
        session_write_close();

        foreach (['plugins', 'modules'] as $section) {
            self::assertSame(403, $this->get($app, '/admin/' . $section)->getStatusCode());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testSeparateMenusAndPagesForAdmin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $adminId = $app->users()->create('extension-admin@example.com', '', '관리자', true);
        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $adminId;
        $_SESSION['session_epoch'] = 0;
        session_write_close();

        foreach (['plugins' => '플러그인', 'modules' => '모듈'] as $section => $title) {
            $response = $this->get($app, '/admin/' . $section);
            self::assertSame(200, $response->getStatusCode());
            $body = $this->body($response);
            self::assertStringContainsString('<h1>' . $title . '</h1>', $body);
            self::assertStringContainsString('href="/admin/plugins"', $body);
            self::assertStringContainsString('href="/admin/modules"', $body);
            self::assertStringContainsString(
                'href="/admin/' . $section . '" class="menu-active" aria-current="page"', $body
            );
            self::assertSame(1, substr_count($body, 'aria-current="page"'));
            self::assertStringContainsString('목록과 사용 여부 설정 기능은 아직 제공되지 않습니다.', $body);
        }
    }
}
