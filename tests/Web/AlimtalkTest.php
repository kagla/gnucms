<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Plugins\Bizppurio\Service;
use GnuCms\Tests\Bizppurio\FakeTransport;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once dirname(__DIR__, 2) . '/plugins/bizppurio/autoload.php';

final class AlimtalkTest extends WebTestCase
{
    private string $root;
    private App $app;

    public function testControllerSendsOnlyConfirmedSnapshotAndRejectsActionsInAnotherEnvironment(): void
    {
        require_once dirname(__DIR__, 2) . '/modules/alimtalk/src/Controller.php';
        $before = $_SESSION ?? [];
        $calls = [];
        $services = [
            'ready' => static fn (): bool => true,
            'status' => static fn (): array => ['configured' => true, 'enabled' => true, 'api_verified' => true,
                'account_type' => 'web', 'account' => 'test-account', 'test_only' => true, 'test_phone' => '01000000000'],
            'templates' => static fn (): array => [],
            'preview' => static fn (): array => ['environment' => 'test', 'template_id' => 'template', 'revision' => 'revision',
                'config_revision' => 'settings', 'message' => '안내입니다.', 'buttons' => []],
            'send' => static function (array $input) use (&$calls): array { $calls[] = $input; return ['id' => 'dispatch']; },
            'detail' => static fn (): array => ['environment' => 'live'],
            'retry' => static function () use (&$calls): void { $calls[] = 'retry'; },
            'refresh-result' => static function () use (&$calls): void { $calls[] = 'refresh'; },
        ];
        $controller = new \GnuCms\Modules\Alimtalk\Controller($services);
        $slim = \Slim\Factory\AppFactory::create();
        foreach (['send', 'detail'] as $page) {
            $slim->post('/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response));
        }
        $slim->addRoutingMiddleware();
        $slim->add(new \GnuCms\Web\Middleware\ViewMiddleware(\GnuCms\Tests\Support\AdminViewFixture::view()));
        $post = static fn (string $page, array $body) => $slim->handle((new ServerRequestFactory())->createServerRequest('POST', '/' . $page)->withParsedBody($body));
        try {
            self::assertSame(200, $post('send', ['action' => 'preview', 'phone' => '01000000000', 'variables' => ['이름' => '원본']])->getStatusCode());
            $token = array_key_last($_SESSION['alimtalk_previews']);
            self::assertSame(303, $post('send', ['action' => 'send', 'confirmation' => $token, 'phone' => '01011111111', 'variables' => ['이름' => '변조']])->getStatusCode());
            self::assertSame('01000000000', $calls[0]['phone']);
            self::assertSame(['이름' => '원본'], $calls[0]['variables']);
            self::assertSame($token, $calls[0]['idempotency_key']);
            foreach (['retry', 'refresh-result'] as $action) {
                self::assertSame(404, $post('detail', ['action' => $action, 'id' => 'live-dispatch', 'environment' => 'test'])->getStatusCode());
            }
            self::assertCount(1, $calls);
        } finally {
            $_SESSION = $before;
        }
    }

    private function setupApp(array $config, ?FakeTransport $http = null): Service
    {
        $this->root = sys_get_temp_dir() . '/gnucms-alimtalk-web-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'aw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabledMany(['plugins/bizppurio' => true, 'modules/alimtalk' => true]);
        return new Service($this->app, $http ?? new FakeTransport());
    }

    private function signIn(bool $admin): void
    {
        $id = $this->app->users()->create(($admin ? 'admin' : 'member') . '@example.com', '', $admin ? '운영자' : '일반회원', $admin);
        $this->get($this->app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach (array_reverse(\GnuCms\Plugins\Bizppurio\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testAdminInstallationCsrfAndSubdirectoryForms(array $config): void
    {
        $service = $this->setupApp($config);
        self::assertFalse($service->ready());
        $this->assertLoginRedirect($this->get($this->app, '/modules/alimtalk/home'), '/modules/alimtalk/home');
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/plugins/bizppurio/settings')->getStatusCode());
        $this->signIn(true);
        self::assertSame(200, $this->get($this->app, '/plugins/bizppurio/settings')->getStatusCode());
        self::assertFalse($service->ready(), 'GET must not migrate');
        self::assertSame(403, $this->post($this->app, '/plugins/bizppurio/settings', ['action' => 'install'])->getStatusCode());
        $response = $this->post($this->app, '/plugins/bizppurio/settings', ['action' => 'install', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($service->ready());
        foreach (['/plugins/bizppurio/settings', '/modules/alimtalk/templates', '/modules/alimtalk/send', '/modules/alimtalk/history'] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $path);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('action="/cms' . $path . '"', $this->body($response));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
        self::assertSame(404, $this->get($this->app, '/admin/plugins/bizppurio/test')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testPreviewIsEscapedAndCannotSendWithoutServerConfirmation(array $config): void
    {
        $service = $this->setupApp($config);
        $service->install();
        $service->settings->save('test', ['account' => 'test-account', 'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000']);
        $template = $service->templates->save('test', ['code' => 'hello', 'name' => '<b>안내</b>', 'message' => '#{이름}님 안내입니다.']);
        $this->signIn(true);
        $response = $this->post($this->app, '/modules/alimtalk/send', ['action' => 'preview', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'template_id' => $template['id'], 'revision' => $template['revision'], 'phone' => '01000000000', 'variables' => ['이름' => '<script>alert(1)</script>']]);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;', $this->body($response));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($response));
        self::assertStringContainsString('name="confirmation"', $this->body($response));
        self::assertSame(0, $service->history(['environment' => 'test'])['total']);
        $response = $this->post($this->app, '/modules/alimtalk/send', ['action' => 'send', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'], 'confirmation' => 'not-a-confirmation']);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $service->history(['environment' => 'test'])['total']);
    }

    #[DataProvider('connectionProvider')]
    public function testWebAccountSettingsAndSendPageExposeVerificationAndPortalEntry(array $config): void
    {
        $service = $this->setupApp($config);
        $service->install();
        $this->signIn(true);
        $response = $this->post($this->app, '/plugins/bizppurio/settings', ['action' => 'save', 'csrf_token' => $_SESSION['csrf_token'],
            'account_type' => 'web', 'account' => 'web-account', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('value="web" selected', $this->body($response));
        self::assertStringContainsString('value="enable" disabled', $this->body($response));
        self::assertSame('web', $service->status('test')['account_type']);
        self::assertSame(422, $this->post($this->app, '/plugins/bizppurio/settings', ['action' => 'enable', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $response = $this->get($this->app, '/modules/alimtalk/send');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('GNUCMS 웹발송', $this->body($response));
        self::assertStringContainsString('웹발송 계정', $this->body($response));
        self::assertStringContainsString('href="https://www.bizppurio.com/" target="_blank" rel="noopener noreferrer"', $this->body($response));
        self::assertSame(0, $service->history(['environment' => 'test'])['total']);
        $service->connect('test');
        $service->settings->setEnabled('test', true);
        $response = $this->get($this->app, '/modules/alimtalk/send');
        self::assertStringContainsString('API 인증: 확인 완료', $this->body($response));
        self::assertStringContainsString('발송 허용', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testAuthenticationPageShowsActualSafeErrorInsteadOfGenericIpAdvice(array $config): void
    {
        $http = new FakeTransport();
        $service = $this->setupApp($config, $http);
        $service->install();
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'web-account', 'account_type' => 'web', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $controller = new \GnuCms\Plugins\Bizppurio\SettingsController($service);
        $slim = \Slim\Factory\AppFactory::create();
        $slim->post('/settings', [$controller, 'handle']);
        $slim->addRoutingMiddleware();
        $slim->add(new \GnuCms\Web\Middleware\ViewMiddleware(\GnuCms\Tests\Support\AdminViewFixture::view()));
        foreach ([3007 => 'API 연동용 모듈 비밀번호가 유효하지 않습니다. 홈페이지 로그인 비밀번호와 별도입니다.', 3010 => '해당 계정의 REST API 사용 가능 여부'] as $code => $expected) {
            $http->respond = static fn (): array => ['status' => 400, 'body' => ['code' => $code, 'description' => $password]];
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/settings')->withParsedBody(['action' => 'connect', 'environment' => 'test']);
            $response = $slim->handle($request);
            self::assertSame(503, $response->getStatusCode());
            self::assertStringContainsString('코드 ' . $code, $this->body($response));
            self::assertStringContainsString($expected, $this->body($response));
            self::assertStringNotContainsString($password, $this->body($response));
            self::assertFalse($service->status('test')['api_verified']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testSavedPasswordRevealRequiresAdminCsrfAndCurrentAccountSettings(array $config): void
    {
        $service = $this->setupApp($config);
        $service->install();
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'test-account', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $settings = $service->settings->summary('test');
        $input = ['action' => 'reveal-password', 'environment' => 'test', 'account' => $settings['account'], 'revision' => $settings['revision']];
        $this->assertLoginRedirect($this->post($this->app, '/plugins/bizppurio/settings', $input));
        $this->signIn(false);
        self::assertSame(403, $this->post($this->app, '/plugins/bizppurio/settings', $input + ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/plugins/bizppurio/settings?action=reveal-password'));
        self::assertStringNotContainsString($password, $page);
        self::assertStringContainsString('aria-controls="password"', $page);
        self::assertSame(403, $this->post($this->app, '/plugins/bizppurio/settings', $input)->getStatusCode());
        $input['csrf_token'] = $_SESSION['csrf_token'];
        foreach (['revision' => bin2hex(random_bytes(16)), 'account' => 'another-account', 'environment' => 'live'] as $key => $value) {
            $response = $this->post($this->app, '/plugins/bizppurio/settings', array_replace($input, [$key => $value]));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString($password, $this->body($response));
        }
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms/plugins/bizppurio/settings')->withParsedBody($input);
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($password, json_decode($this->body($response), true, 8, JSON_THROW_ON_ERROR)['password']);
        self::assertSame($settings, $service->settings->summary('test'));
    }

    #[DataProvider('connectionProvider')]
    public function testWebhookUsesTokenNotSessionAndWorksWhenSendingStopped(array $config): void
    {
        $service = $this->setupApp($config);
        $service->install();
        $service->settings->save('test', ['account' => 'test-account', 'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'webhook_ips' => '127.0.0.1']);
        $settings = $service->settings->read('test');
        $send = function (string $token, string $ip) use ($settings): \Psr\Http\Message\ResponseInterface {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms/plugins/bizppurio/result?'
                . http_build_query(['environment' => 'test', 'token' => $token]), ['REMOTE_ADDR' => $ip])->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode(['MEDIA' => 'AT', 'MSGID' => 'unmatched', 'CMSGID' => 'missing', 'PHONE' => '01000000000',
                'RESULT' => '7000', 'UNIXTIME' => (string) time()], JSON_THROW_ON_ERROR));
            return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        };
        self::assertSame(403, $send('invalid', '127.0.0.1')->getStatusCode());
        self::assertSame(403, $send($settings['webhook_token'], '192.0.2.1')->getStatusCode());
        $response = $send($settings['webhook_token'], '127.0.0.1');
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertSame('{"accepted":true}', (string) $response->getBody());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }
}
