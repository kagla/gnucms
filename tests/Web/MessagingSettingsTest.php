<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Messaging\MessagingService;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MessagingSettingsTest extends WebTestCase
{
    private string $root;
    private App $app;
    private FakeTransport $http;

    private function setupApp(array $config): MessagingService
    {
        $this->root = sys_get_temp_dir() . '/gnucms-msg-settings-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ms' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $this->http = new FakeTransport();
        $service = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($service);
        return $service;
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
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsRequireAdminAndCsrfAndKapiKeyStaysHidden(array $config): void
    {
        $service = $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/settings/messaging'), '/admin/settings/messaging');
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/admin/settings/messaging')->getStatusCode());
        $this->signIn(true);
        $page = $this->get($this->app, '/admin/settings/messaging');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('알림톡·문자 설정', $this->body($page));
        self::assertStringContainsString('href="/admin/settings/messaging"', $this->body($page));
        self::assertStringNotContainsString('데이터 설치·갱신', $this->body($page));
        self::assertSame('no-store', $page->getHeaderLine('Cache-Control'));
        $key = bin2hex(random_bytes(24));
        $settings = ['action' => 'save', 'environment' => 'test', 'account' => 'kapi-web-test',
            'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'kapi_key' => $key];
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $settings)->getStatusCode());
        $saved = $this->post($this->app, '/admin/settings/messaging', $settings + ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $saved->getStatusCode());
        self::assertStringNotContainsString($key, $this->body($saved));
        self::assertStringContainsString('name="clear_kapi_key"', $this->body($saved));
        self::assertTrue($service->status('test')['kapi_configured']);
        $failed = $this->post($this->app, '/admin/settings/messaging', array_replace($settings, ['from' => 'invalid']) + ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(422, $failed->getStatusCode());
        self::assertStringNotContainsString($key, $this->body($failed));
        foreach (['/admin/settings/messaging'] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $path);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('action="/cms' . $path . '"', $this->body($response));
        }
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testWebAccountMustVerifyBeforeEnablingAndEnvironmentPolicyIsShown(array $config): void
    {
        $service = $this->setupApp($config);
        $this->signIn(true);
        $response = $this->post($this->app, '/admin/settings/messaging', ['action' => 'save', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'account_type' => 'web', 'account' => 'web-account', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('value="web" selected', $this->body($response));
        self::assertStringContainsString('value="enable" disabled', $this->body($response));
        self::assertSame('web', $service->status('test')['account_type']);
        self::assertSame(422, $this->post($this->app, '/admin/settings/messaging', ['action' => 'enable', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $service->connect('test');
        $enabled = $this->post($this->app, '/admin/settings/messaging', ['action' => 'enable', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $enabled->getStatusCode());
        self::assertTrue($service->status('test')['enabled']);
        $service->settings->save('live', ['account' => 'live-account', 'password' => bin2hex(random_bytes(20)), 'from' => '0212345678']);
        foreach (['test', 'live'] as $environment) {
            $html = $this->body($this->get($this->app, '/admin/settings/messaging', ['environment' => $environment]));
            self::assertStringContainsString('테스트 환경', $html);
            self::assertStringNotContainsString('검수 환경', $html);
            self::assertStringNotContainsString('name="test_only"', $html);
            if ($environment === 'test') self::assertStringContainsString('name="test_phone"', $html);
            else { self::assertStringNotContainsString('name="test_phone"', $html); self::assertStringContainsString('발송 대상: 입력한 국내 휴대폰 번호', $html); }
        }
        self::assertSame(1, $this->http->count('/v1/token'));
    }

    #[DataProvider('connectionProvider')]
    public function testSavedPasswordRevealRequiresAdminCsrfAndCurrentAccountSettings(array $config): void
    {
        $service = $this->setupApp($config);
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'test-account', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $settings = $service->settings->summary('test');
        $input = ['action' => 'reveal-password', 'environment' => 'test', 'account' => $settings['account'], 'revision' => $settings['revision']];
        $this->assertLoginRedirect($this->post($this->app, '/admin/settings/messaging', $input));
        $this->signIn(false);
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $input + ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/admin/settings/messaging', ['action' => 'reveal-password']));
        self::assertStringNotContainsString($password, $page);
        self::assertStringContainsString('aria-controls="password"', $page);
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $input)->getStatusCode());
        $input['csrf_token'] = $_SESSION['csrf_token'];
        foreach (['revision' => bin2hex(random_bytes(16)), 'account' => 'another-account', 'environment' => 'live'] as $key => $value) {
            $response = $this->post($this->app, '/admin/settings/messaging', array_replace($input, [$key => $value]));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString($password, $this->body($response));
        }
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms/admin/settings/messaging')->withParsedBody($input);
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($password, json_decode($this->body($response), true, 8, JSON_THROW_ON_ERROR)['password']);
        self::assertSame($settings, $service->settings->summary('test'));
    }

    #[DataProvider('connectionProvider')]
    public function testAuthenticationFailureShowsCodeWithoutSecrets(array $config): void
    {
        $service = $this->setupApp($config);
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'web-account', 'account_type' => 'web', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->signIn(true);
        foreach ([3007 => 'API 연동용 모듈 비밀번호가 유효하지 않습니다.', 3010 => '해당 계정의 REST API 사용 가능 여부'] as $code => $expected) {
            $this->http->respond = static fn (): array => ['status' => 400, 'body' => ['code' => $code, 'description' => $password]];
            $response = $this->post($this->app, '/admin/settings/messaging', ['action' => 'connect', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
            self::assertSame(503, $response->getStatusCode());
            self::assertStringContainsString('코드 ' . $code, $this->body($response));
            self::assertStringContainsString($expected, $this->body($response));
            self::assertStringNotContainsString($password, $this->body($response));
            self::assertFalse($service->status('test')['api_verified']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testWebhookUsesTokenNotSessionAndAcceptsLegacyPath(array $config): void
    {
        $service = $this->setupApp($config);
        $service->settings->save('test', ['account' => 'test-account', 'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'webhook_ips' => '127.0.0.1']);
        $settings = $service->settings->read('test');
        $this->signIn(true);
        $shown = $this->post($this->app, '/admin/settings/messaging', ['action' => 'webhook', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertStringContainsString('/messaging/bizppurio/result?environment=test&amp;token=', $this->body($shown));
        $send = function (string $path, string $token, string $ip) use ($settings): \Psr\Http\Message\ResponseInterface {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms' . $path . '?'
                . http_build_query(['environment' => 'test', 'token' => $token]), ['REMOTE_ADDR' => $ip])->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode(['MEDIA' => 'AT', 'MSGID' => 'unmatched', 'CMSGID' => 'missing', 'PHONE' => '01000000000',
                'RESULT' => '7000', 'UNIXTIME' => (string) time()], JSON_THROW_ON_ERROR));
            return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        };
        self::assertSame(403, $send('/messaging/bizppurio/result', 'invalid', '127.0.0.1')->getStatusCode());
        self::assertSame(403, $send('/messaging/bizppurio/result', $settings['webhook_token'], '192.0.2.1')->getStatusCode());
        foreach (['/messaging/bizppurio/result', '/plugins/bizppurio/result'] as $path) {
            $response = $send($path, $settings['webhook_token'], '127.0.0.1');
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertSame('', $response->getHeaderLine('Set-Cookie'));
            self::assertSame('{"accepted":true}', (string) $response->getBody());
        }
    }
}
