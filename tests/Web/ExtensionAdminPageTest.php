<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\ExtensionFixtures;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExtensionAdminPageTest extends WebTestCase
{
    use ExtensionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createExtensionRoot();
    }

    protected function tearDown(): void
    {
        $this->removeExtensionRoot();
        parent::tearDown();
    }

    protected function makeApp(array $dbConfig, array $configOverrides = [], ?string $theme = null): App
    {
        return parent::makeApp($dbConfig, array_replace([
            'extensions' => ['root' => $this->extensionRoot],
            'storage' => ['dir' => $this->extensionRoot . '/storage'],
        ], $configOverrides), $theme);
    }

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
            self::assertStringContainsString('등록된 ' . $title . '이 없습니다', $body);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testTogglePersistsAndControlsExecutionWithAdminAndCsrfChecks(array $dbConfig): void
    {
        $this->package('plugins/demo', ['name' => '<script>name</script>'], <<<'PHP'
<?php
return static function ($context): void {
    $context->route('GET', '/ping', static function ($request, $response) {
        $response->getBody()->write('extension running');
        return $response;
    });
    $context->route('POST', '/save', static fn ($request, $response) => $response);
    $context->route('GET', '/admin', static fn ($request, $response) => $response, true);
};
PHP);
        $app = $this->makeApp($dbConfig, [], 'default');
        $url = '/admin/plugins/demo/state';
        self::assertSame(401, $this->post($app, $url, ['enabled' => '1'])->getStatusCode());
        $memberId = $app->users()->create('ext-member@example.com', '', '회원');
        $this->sessionUser($memberId);
        self::assertSame(403, $this->post($app, $url, ['enabled' => '1', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $adminId = $app->users()->create('ext-admin@example.com', '', '관리자', true);
        $this->sessionUser($adminId);
        self::assertSame(403, $this->post($app, $url, ['enabled' => '1'])->getStatusCode());
        $csrf = $_SESSION['csrf_token'];
        self::assertSame(404, $this->get($app, '/extensions/plugins/demo/ping')->getStatusCode());
        self::assertSame(422, $this->post($app, $url, ['enabled' => ['1'], 'csrf_token' => $csrf])->getStatusCode());
        $response = $this->post($app, $url, ['enabled' => '1', 'csrf_token' => $csrf]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/plugins?saved=1', $response->getHeaderLine('Location'));
        self::assertSame(['plugins/demo'], (new StateStore($app->storageDir() . '/extensions'))->read());
        self::assertSame('extension running', $this->body($this->get($app, '/extensions/plugins/demo/ping')));
        self::assertSame(403, $this->post($app, '/extensions/plugins/demo/save', [])->getStatusCode());
        self::assertSame(200, $this->post($app, '/extensions/plugins/demo/save', ['csrf_token' => $csrf])->getStatusCode());
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertStringContainsString('&lt;script&gt;name&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>name</script>', $body);
        self::assertStringContainsString('value="1" checked', $body);
        self::assertStringNotContainsString('name&lt;/script&gt;', $this->body($this->get($app, '/admin/modules')));
        $this->sessionUser($memberId);
        self::assertSame(403, $this->get($app, '/extensions/plugins/demo/admin')->getStatusCode());
        $this->sessionUser($adminId);
        self::assertSame(200, $this->get($app, '/extensions/plugins/demo/admin')->getStatusCode());
        self::assertSame(303, $this->post($app, $url, ['enabled' => '0', 'csrf_token' => $csrf])->getStatusCode());
        self::assertSame(404, $this->get($app, '/extensions/plugins/demo/ping')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testCorruptStateLeavesCoreAndRecoveryPageAccessible(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $adminId = $app->users()->create('ext-recovery@example.com', '', '관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($adminId);
        mkdir($app->storageDir() . '/extensions', 0700, true);
        file_put_contents($app->storageDir() . '/extensions/enabled.json', '{broken');
        self::assertSame(200, $this->get($app, '/admin')->getStatusCode());
        $response = $this->get($app, '/admin/plugins');
        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('확장 사용 상태를 읽을 수 없습니다.', $this->body($response));
    }

    private function sessionUser(int $id): void
    {
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
    }
}
