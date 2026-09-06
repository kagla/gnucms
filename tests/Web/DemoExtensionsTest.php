<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\ExtensionFixtures;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class DemoExtensionsTest extends WebTestCase
{
    use ExtensionFixtures;

    private const PLUGIN = '/plugins/demo-message/preview';
    private const MODULE = '/modules/demo-reservation/preview';

    protected function setUp(): void
    {
        parent::setUp();
        // require_once가 동일 클래스를 다시 로드하지 않도록 복사 경로는 유지하고 데이터만 매번 초기화한다.
        static $root = null;
        $root ??= sys_get_temp_dir() . '/gnucms-demo-tests-' . bin2hex(random_bytes(8));
        $this->extensionRoot = $root;
        mkdir($this->extensionRoot, 0700, true);
        foreach (['plugins/demo-message', 'modules/demo-reservation'] as $key) {
            $source = dirname(__DIR__, 2) . '/' . $key;
            $target = $this->extensionRoot . '/' . $key;
            mkdir($target, 0700, true);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $destination = $target . substr($file->getPathname(), strlen($source));
                $file->isDir() ? mkdir($destination) : copy($file->getPathname(), $destination);
            }
        }
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

    private function manager(App $app): Manager
    {
        return new Manager(new Catalog($this->extensionRoot), new StateStore($app->storageDir() . '/extensions'));
    }

    private function signIn(App $app, bool $admin = true): void
    {
        $id = $app->users()->create(($admin ? 'demo-admin' : 'demo-member') . '@example.com', '', $admin ? '데모 관리자' : '데모 회원', $admin);
        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
    }

    private function reservationInput(array $overrides = []): array
    {
        return array_replace([
            'csrf_token' => $_SESSION['csrf_token'], 'name' => '방문자',
            'date' => '2028-02-29', 'time' => '14:30', 'guests' => '2',
        ], $overrides);
    }

    #[DataProvider('connectionProvider')]
    public function testPackagesAppearSeparatelyAndAreDisabledByDefault(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $this->signIn($app);
        $plugins = $this->body($this->get($app, '/admin/plugins'));
        $modules = $this->body($this->get($app, '/admin/modules'));
        self::assertStringContainsString('데모 · 메시지 형식', $plugins);
        self::assertStringNotContainsString('데모 · 예약 안내문', $plugins);
        self::assertStringContainsString('데모 · 예약 안내문', $modules);
        self::assertStringNotContainsString('데모 · 메시지 형식', $modules);
        self::assertSame(404, $this->get($app, self::PLUGIN)->getStatusCode());
        self::assertSame(404, $this->get($app, self::MODULE)->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testModuleRunsWithMissingPluginAndSwitchesFormattingWhenEnabled(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $manager = $this->manager($app);
        rename($this->extensionRoot . '/plugins/demo-message', $this->extensionRoot . '/plugins/.demo-message');
        $manager->setEnabled('modules/demo-reservation', true);
        $this->signIn($app);
        self::assertStringContainsString('독립 실행', $this->body($this->get($app, self::MODULE)));
        $response = $this->post($app, self::MODULE, $this->reservationInput());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('2028-02-29 14:30', $this->body($response));
        self::assertStringNotContainsString('[데모 알림', $this->body($response));

        rename($this->extensionRoot . '/plugins/.demo-message', $this->extensionRoot . '/plugins/demo-message');
        $manager->setEnabled('plugins/demo-message', true);
        $body = $this->body($this->post($app, self::MODULE, $this->reservationInput()));
        self::assertStringContainsString('메시지 플러그인 연결됨', $body);
        self::assertStringContainsString('[데모 알림 · 예약 안내]', $body);
        $manager->setEnabled('plugins/demo-message', false);
        $body = $this->body($this->post($app, self::MODULE, $this->reservationInput()));
        self::assertStringContainsString('독립 실행', $body);
        self::assertStringNotContainsString('[데모 알림', $body);
        $manager->setEnabled('modules/demo-reservation', false);
        self::assertSame(404, $this->get($app, self::MODULE)->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testPreviewRoutesRequireAdminAndCsrf(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->manager($app)->setEnabled('plugins/demo-message', true);
        $this->manager($app)->setEnabled('modules/demo-reservation', true);
        foreach ([self::PLUGIN, self::MODULE] as $url) {
            self::assertSame(401, $this->get($app, $url)->getStatusCode());
            self::assertSame(401, $this->post($app, $url, [])->getStatusCode());
        }
        $this->signIn($app, false);
        foreach ([self::PLUGIN, self::MODULE] as $url) {
            self::assertSame(403, $this->get($app, $url)->getStatusCode());
            self::assertSame(403, $this->post($app, $url, ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        }
        $this->signIn($app);
        foreach ([self::PLUGIN, self::MODULE] as $url) {
            self::assertSame(200, $this->get($app, $url)->getStatusCode());
            self::assertSame(403, $this->post($app, $url, [])->getStatusCode());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testPluginEscapesInputAndRejectsInvalidValues(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->manager($app)->setEnabled('plugins/demo-message', true);
        $this->signIn($app);
        $input = ['csrf_token' => $_SESSION['csrf_token'], 'title' => '안내', 'body' => '<script>alert(1)</script>'];
        $response = $this->post($app, self::PLUGIN, $input);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('[데모 알림 · 안내]', $this->body($response));
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->body($response));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($response));
        foreach ([['title' => []], ['body' => []], ['body' => str_repeat('가', 1001)], ['title' => "제목\n다음 줄"]] as $invalid) {
            $response = $this->post($app, self::PLUGIN, array_replace($input, $invalid));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString('id="result-title"', $this->body($response));
        }
    }

    #[DataProvider('connectionProvider')]
    public function testModuleValidatesDatesTimesAndCountsAndEscapesNames(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->manager($app)->setEnabled('modules/demo-reservation', true);
        $this->signIn($app);
        $response = $this->post($app, self::MODULE, $this->reservationInput(['name' => '<b>방문자</b>']));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;방문자&lt;/b&gt;', $this->body($response));
        self::assertStringNotContainsString('<b>방문자</b>', $this->body($response));
        foreach ([['name' => []], ['date' => '2027-02-29'], ['date' => "2028-01\0-01"], ['time' => '24:00'], ['guests' => '21'], ['guests' => '2.5'], ['guests' => []]] as $invalid) {
            $response = $this->post($app, self::MODULE, $this->reservationInput($invalid));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString('id="result-title"', $this->body($response));
        }
    }

    #[DataProvider('connectionProvider')]
    public function testFormsAndAdminLinksRespectSubdirectoryInstallation(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->manager($app)->setEnabled('plugins/demo-message', true);
        $this->manager($app)->setEnabled('modules/demo-reservation', true);
        $this->signIn($app);
        foreach ([self::PLUGIN, self::MODULE] as $url) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $url);
            $response = Kernel::create($app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('action="/cms' . $url . '"', $this->body($response));
            self::assertStringContainsString('href="/cms/admin/plugins"', $this->body($response));
            self::assertStringContainsString('href="/cms/admin/modules"', $this->body($response));
        }
    }

    #[DataProvider('connectionProvider')]
    public function testModuleEntryLinksAndAdminTestWithoutChangingUsageState(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $url = '/admin/modules/demo-reservation/test';
        self::assertSame(401, $this->get($app, $url)->getStatusCode());
        self::assertSame(401, $this->post($app, $url, [])->getStatusCode());
        $this->signIn($app, false);
        self::assertSame(403, $this->get($app, $url)->getStatusCode());
        self::assertSame(403, $this->post($app, $url, $this->reservationInput())->getStatusCode());
        $this->signIn($app);
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertStringContainsString('실행 주소:', $body);
        self::assertStringContainsString('href="' . $url . '" title="관리자 테스트로 열기">' . self::MODULE . '</a>', $body);
        self::assertStringContainsString('href="' . $url . '" target="_blank" rel="noopener noreferrer" title="관리자 테스트를 새 창으로 열기"', $body);
        self::assertStringContainsString(self::MODULE, $body);
        self::assertStringContainsString('href="' . $url . '"', $body);
        self::assertStringContainsString('>관리자 테스트</a>', $body);
        self::assertStringNotContainsString('>바로가기</a>', $body);

        $response = $this->get($app, $url);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('사용 설정은 변경되지 않습니다.', $this->body($response));
        self::assertStringContainsString('action="' . $url . '"', $this->body($response));
        self::assertSame(403, $this->post($app, $url, [])->getStatusCode());
        $response = $this->post($app, $url, $this->reservationInput());
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('2028-02-29 14:30', $this->body($response));
        self::assertSame(422, $this->post($app, $url, $this->reservationInput(['guests' => '99']))->getStatusCode());
        self::assertSame(404, $this->get($app, self::MODULE)->getStatusCode());
        self::assertFileDoesNotExist($app->storageDir() . '/extensions/enabled.json');

        $this->manager($app)->setEnabled('plugins/demo-message', true);
        $store = new StateStore($app->storageDir() . '/extensions');
        $before = $store->snapshot();
        $response = $this->post($app, $url, $this->reservationInput());
        self::assertStringContainsString('[데모 알림 · 예약 안내]', $this->body($response));
        self::assertSame($before, $store->snapshot());
        $this->manager($app)->setEnabled('modules/demo-reservation', true);
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertStringContainsString('href="' . self::MODULE . '"', $body);
        self::assertStringNotContainsString('>바로가기</a>', $body);
        self::assertStringContainsString('href="' . self::MODULE . '" target="_blank" rel="noopener noreferrer" title="새 창으로 열기"', $body);
        self::assertStringNotContainsString('>관리자 테스트</a>', $body);
        self::assertSame(200, $this->get($app, self::MODULE)->getStatusCode());
        self::assertSame(404, $this->get($app, '/extensions' . self::MODULE)->getStatusCode());
        self::assertSame(404, $this->get($app, '/extensions' . self::PLUGIN)->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testAdminTestLinksAndFormRespectSubdirectoryInstallation(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $this->signIn($app);
        $testUrl = '/cms/admin/modules/demo-reservation/test';
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms/admin/modules');
        $response = Kernel::create($app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertStringContainsString('/cms' . self::MODULE, $this->body($response));
        self::assertStringContainsString('href="' . $testUrl . '"', $this->body($response));
        $request = (new ServerRequestFactory())->createServerRequest('GET', $testUrl);
        $response = Kernel::create($app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="' . $testUrl . '"', $this->body($response));
        $request = (new ServerRequestFactory())->createServerRequest('POST', $testUrl)->withParsedBody($this->reservationInput());
        $response = Kernel::create($app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertFileDoesNotExist($app->storageDir() . '/extensions/enabled.json');
    }
}
