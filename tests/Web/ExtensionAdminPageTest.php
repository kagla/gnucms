<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
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
            $this->assertLoginRedirect($this->get($app, '/admin/' . $section), '/admin/' . $section);
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
        $this->assertLoginRedirect($this->post($app, $url, ['enabled' => '1']));
        $memberId = $app->users()->create('ext-member@example.com', '', '회원');
        $this->sessionUser($memberId);
        self::assertSame(403, $this->post($app, $url, ['enabled' => '1', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $adminId = $app->users()->create('ext-admin@example.com', '', '관리자', true);
        $this->sessionUser($adminId);
        self::assertSame(403, $this->post($app, $url, ['enabled' => '1'])->getStatusCode());
        $csrf = $_SESSION['csrf_token'];
        self::assertSame(404, $this->get($app, '/plugins/demo/ping')->getStatusCode());
        self::assertSame(422, $this->post($app, $url, ['enabled' => ['1'], 'csrf_token' => $csrf])->getStatusCode());
        $response = $this->post($app, $url, ['enabled' => '1', 'csrf_token' => $csrf]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/plugins?saved=1', $response->getHeaderLine('Location'));
        self::assertSame(['plugins/demo'], (new StateStore($app->storageDir() . '/extensions'))->read());
        self::assertSame('extension running', $this->body($this->get($app, '/plugins/demo/ping')));
        self::assertSame(403, $this->post($app, '/plugins/demo/save', [])->getStatusCode());
        self::assertSame(200, $this->post($app, '/plugins/demo/save', ['csrf_token' => $csrf])->getStatusCode());
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertStringContainsString('&lt;script&gt;name&lt;/script&gt;', $body);
        self::assertStringNotContainsString('<script>name</script>', $body);
        self::assertStringContainsString('value="1" checked', $body);
        self::assertStringNotContainsString('name&lt;/script&gt;', $this->body($this->get($app, '/admin/modules')));
        $this->sessionUser($memberId);
        self::assertSame(403, $this->get($app, '/plugins/demo/admin')->getStatusCode());
        $this->sessionUser($adminId);
        self::assertSame(200, $this->get($app, '/plugins/demo/admin')->getStatusCode());
        self::assertSame(303, $this->post($app, $url, ['enabled' => '0', 'csrf_token' => $csrf])->getStatusCode());
        self::assertSame(404, $this->get($app, '/plugins/demo/ping')->getStatusCode());
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

    #[DataProvider('connectionProvider')]
    public function testBundledCmsExcludesShopAndCanDisableItsPreviousEnablement(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['extensions' => ['root' => dirname(__DIR__, 2)]], 'default');
        $adminId = $app->users()->create('bundled-admin@example.test', '', '관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($adminId);
        $state = new StateStore($app->storageDir() . '/extensions');
        $remaining = ['plugins/bizppurio'];
        $state->update(static fn (array $enabled): array => $remaining);
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertStringNotContainsString('작은 쇼핑몰', $body);
        self::assertStringNotContainsString('name="enabled[shop]"', $body);
        self::assertStringContainsString('예약 안내문', $body);

        // 이전 설치의 사용 설정이 남아 있어도 제거한 모듈은 실행되지 않는다.
        $state->update(static fn (array $enabled): array => [...$enabled, 'modules/shop']);
        foreach (['/shop', '/shop/orders', '/admin/shop', '/admin/shop/products', '/modules/shop', '/modules/shop/admin'] as $path) {
            self::assertSame(404, $this->get($app, $path)->getStatusCode(), $path);
        }
        foreach (['/', '/account', '/admin/modules', '/plugins/bizppurio/settings'] as $path) {
            $response = $this->get($app, $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            $body = $this->body($response);
            self::assertStringNotContainsString('작은 쇼핑몰', $body);
            self::assertStringNotContainsString('쇼핑몰 관리', $body);
            self::assertStringNotContainsString('href="/shop', $body);
            self::assertStringNotContainsString('href="/admin/shop', $body);
            self::assertStringNotContainsString('내 주문', $body);
        }
        $response = $this->post($app, '/admin/modules/shop/state', ['enabled' => '0', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($remaining, $state->read());
        self::assertStringNotContainsString('name="enabled[shop]"', $this->body($this->get($app, '/admin/modules')));
    }

    #[DataProvider('connectionProvider')]
    public function testBundledCmsExcludesPaymentPluginsAndCanDisablePreviousEnablement(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['extensions' => ['root' => dirname(__DIR__, 2)]], 'default');
        $adminId = $app->users()->create('payments-removed-admin@example.test', '', '관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($adminId);
        $state = new StateStore($app->storageDir() . '/extensions');
        $remaining = ['plugins/bizppurio'];
        $providers = ['inicis', 'kcp', 'kspay', 'toss'];
        $payments = array_map(static fn (string $id): string => 'plugins/payment-' . $id, $providers);
        $state->update(static fn (array $enabled): array => $remaining);
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertStringNotContainsString('payment-', $body);
        self::assertStringContainsString('비즈뿌리오', $body);
        self::assertStringContainsString('메시지 형식', $body);

        $state->update(static fn (array $enabled): array => [...$enabled, ...$payments]);
        foreach ($payments as $key) {
            $path = '/' . $key . '/settings';
            self::assertSame(404, $this->get($app, $path)->getStatusCode(), $path);
            self::assertSame(404, $this->post($app, $path, ['action' => 'install', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode(), $path);
        }
        foreach (['/admin/plugins', '/admin/modules', '/plugins/bizppurio/settings'] as $path) {
            $response = $this->get($app, $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringNotContainsString('href="/plugins/payment-', $this->body($response));
        }
        foreach ($providers as $id) {
            $response = $this->post($app, '/admin/plugins/payment-' . $id . '/state', ['enabled' => '0', 'csrf_token' => $_SESSION['csrf_token']]);
            self::assertSame(303, $response->getStatusCode());
        }
        self::assertSame($remaining, $state->read());
        self::assertStringNotContainsString('payment-', $this->body($this->get($app, '/admin/plugins')));
        self::assertSame(404, $this->post($app, '/admin/plugins/payment-inicis/state', ['enabled' => '1', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        self::assertSame($remaining, $state->read());
    }

    #[DataProvider('connectionProvider')]
    public function testBundledCmsExcludesAlimtalkModuleAndPreservesItsProvider(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['extensions' => ['root' => dirname(__DIR__, 2)]], 'default');
        $adminId = $app->users()->create('alimtalk-removed-admin@example.test', '', '관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($adminId);
        $state = new StateStore($app->storageDir() . '/extensions');
        $state->update(static fn (array $enabled): array => ['plugins/bizppurio']);
        self::assertStringNotContainsString('name="enabled[alimtalk]"', $this->body($this->get($app, '/admin/modules')));

        $state->update(static fn (array $enabled): array => [...$enabled, 'modules/alimtalk']);
        foreach (['home', 'templates', 'send', 'history', 'detail'] as $page) {
            $path = '/modules/alimtalk/' . $page;
            self::assertSame(404, $this->get($app, $path)->getStatusCode(), $path);
            self::assertSame(404, $this->post($app, $path, ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode(), $path);
        }
        foreach (['/admin/modules', '/admin/plugins', '/plugins/bizppurio/settings'] as $path) {
            $response = $this->get($app, $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringNotContainsString('href="/modules/alimtalk/', $this->body($response));
        }
        $response = $this->post($app, '/admin/modules/alimtalk/state', ['enabled' => '0', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(['plugins/bizppurio'], $state->read());
        self::assertStringNotContainsString('name="enabled[alimtalk]"', $this->body($this->get($app, '/admin/modules')));
        self::assertSame(404, $this->post($app, '/admin/modules/alimtalk/state', ['enabled' => '1', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testPublicAddressIsVisibleWithAndWithoutAnAdminEntry(array $dbConfig): void
    {
        $bootstrap = <<<'PHP'
<?php return static function ($context): void {
    $context->route('GET', '/', static fn ($request, $response) => $response);
    $context->route('GET', '/admin', static fn ($request, $response) => $response, admin: true);
};
PHP;
        $this->package('modules/shop', ['route_prefix' => '/shop', 'public_path' => '/', 'entry_path' => '/admin'], $bootstrap);
        $this->package('modules/public', ['route_prefix' => '/public', 'public_path' => '/'], $bootstrap);
        $app = $this->makeApp($dbConfig, [], 'default');
        $adminId = $app->users()->create('public-links@example.test', '', '관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($adminId);
        $manager = new Manager(new Catalog($this->extensionRoot), new StateStore($app->storageDir() . '/extensions'));
        $manager->setEnabledMany(['modules/shop' => true, 'modules/public' => true]);
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertSame(2, substr_count($body, 'class="extension-public-entry"'));
        foreach (['/shop', '/public'] as $url) {
            self::assertStringContainsString('href="' . $url . '">' . $url . '</a>', $body);
            self::assertStringContainsString('class="btn btn-ghost btn-square btn-xs" href="' . $url . '" target="_blank" rel="noopener noreferrer" title="사용자 화면을 새 창으로 열기"', $body);
        }
        self::assertStringNotContainsString('사용자 화면 열기', $body);
        self::assertStringContainsString('href="/shop/admin"', $body);
        $manager->setEnabled('modules/shop', false);
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertSame(1, substr_count($body, 'class="extension-public-entry"'));
        self::assertStringNotContainsString('href="/shop"', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testBulkFormHasTwoSaveButtonsAndSortsByActualToggleOrder(array $dbConfig): void
    {
        $this->package('plugins/alpha');
        $this->package('plugins/bravo');
        $app = $this->makeApp($dbConfig, [], 'default');
        $id = $app->users()->create('bulk-admin@example.com', '', '일괄 관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($id);
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertSame(2, substr_count($body, '>저장</button>'));
        self::assertStringContainsString('form="extension-state-form"', $body);
        self::assertStringContainsString('<th scope="col">사용 상태</th>', $body);
        self::assertStringNotContainsString('<th scope="col">상태</th>', $body);
        self::assertStringNotContainsString('<th scope="col">사용 여부</th>', $body);
        $input = [
            'csrf_token' => $_SESSION['csrf_token'], 'complete' => '1',
            'original' => ['alpha' => '0', 'bravo' => '0'],
            'enabled' => ['alpha' => '1', 'bravo' => '1'],
            'changed_order' => '["bravo","alpha"]',
        ];
        $response = $this->post($app, '/admin/plugins/state', $input);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/plugins?saved=1', $response->getHeaderLine('Location'));
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertLessThan(strpos($body, '<strong>alpha</strong>'), strpos($body, '<strong>bravo</strong>'));

        $input['original'] = ['alpha' => '1', 'bravo' => '1'];
        $input['enabled']['alpha'] = '0';
        $input['changed_order'] = '["alpha"]';
        self::assertSame(303, $this->post($app, '/admin/plugins/state', $input)->getStatusCode());
        $body = $this->body($this->get($app, '/admin/plugins'));
        self::assertLessThan(strpos($body, '<strong>bravo</strong>'), strpos($body, '<strong>alpha</strong>'));
        self::assertSame(['plugins/bravo'], (new StateStore($app->storageDir() . '/extensions'))->read());
    }

    #[DataProvider('connectionProvider')]
    public function testBulkSaveRejectsUnauthorizedMalformedAndPartialRequests(array $dbConfig): void
    {
        $this->package('plugins/alpha');
        $app = $this->makeApp($dbConfig);
        $url = '/admin/plugins/state';
        $this->assertLoginRedirect($this->post($app, $url, []));
        $member = $app->users()->create('bulk-member@example.com', '', '일괄 회원');
        $this->sessionUser($member);
        self::assertSame(403, $this->post($app, $url, ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $admin = $app->users()->create('bulk-admin@example.com', '', '일괄 관리자', true);
        $this->sessionUser($admin);
        self::assertSame(403, $this->post($app, $url, [])->getStatusCode());
        $input = ['csrf_token' => $_SESSION['csrf_token'], 'complete' => '1',
            'enabled' => ['alpha' => '1'], 'original' => ['alpha' => '0'], 'changed_order' => '["alpha"]'];
        foreach ([
            ['complete' => '0'], ['original' => []], ['enabled' => ['alpha' => []]],
            ['enabled' => ['../modules/test' => '1']], ['changed_order' => '["other"]'], ['changed_order' => '{"bad":"alpha"}'],
        ] as $invalid) {
            self::assertSame(422, $this->post($app, $url, array_replace($input, $invalid))->getStatusCode());
            self::assertSame([], (new StateStore($app->storageDir() . '/extensions'))->read());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testFailedBulkSavePreservesSelectionsAndOtherSectionsAreNotOverwritten(array $dbConfig): void
    {
        $this->package('plugins/provider');
        $this->package('plugins/consumer', ['requires' => ['plugins/provider']]);
        $this->package('modules/standalone');
        $app = $this->makeApp($dbConfig, [], 'default');
        $admin = $app->users()->create('bulk-admin@example.com', '', '일괄 관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($admin);
        $store = new StateStore($app->storageDir() . '/extensions');
        $manager = new Manager(new Catalog($this->extensionRoot), $store);
        $manager->setEnabledMany(['plugins/provider' => true, 'modules/standalone' => true]);
        // 오래 열린 화면의 미변경 provider 값으로 다른 관리자의 활성화를 덮어쓰지 않는다.
        $input = ['csrf_token' => $_SESSION['csrf_token'], 'complete' => '1',
            'original' => ['provider' => '0', 'consumer' => '0'], 'enabled' => ['provider' => '0', 'consumer' => '1']];
        self::assertSame(303, $this->post($app, '/admin/plugins/state', $input)->getStatusCode());
        self::assertSame(['modules/standalone', 'plugins/consumer', 'plugins/provider'], $store->read());
        $before = $store->snapshot();
        $input['original'] = ['provider' => '1', 'consumer' => '1'];
        $input['enabled'] = ['provider' => '0', 'consumer' => '1'];
        $response = $this->post($app, '/admin/plugins/state', $input);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame($before, $store->snapshot());
        self::assertStringContainsString('사용 중입니다', $this->body($response));
        self::assertStringNotContainsString('name="enabled[provider]" value="1" checked', $this->body($response));
        self::assertStringContainsString('name="enabled[consumer]" value="1" checked', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testDisabledBootstrapIsNeverReadBeforeAdminAndCsrfValidation(array $dbConfig): void
    {
        $this->package('modules/testable', ['entry_path' => '/preview', 'admin_test' => true], <<<'PHP'
<?php
file_put_contents(__DIR__ . '/executed.marker', 'loaded');
return static function ($context): void {
    // 공개 라우트라도 관리자 테스트 진입점이 먼저 권한을 검사한다.
    $context->route('GET', '/preview', static fn ($request, $response) => $response);
    $context->route('POST', '/preview', static fn ($request, $response) => $response);
};
PHP);
        $marker = $this->extensionRoot . '/modules/testable/executed.marker';
        $app = $this->makeApp($dbConfig);
        $url = '/admin/modules/testable/test';
        $this->assertLoginRedirect($this->get($app, $url), $url);
        self::assertFileDoesNotExist($marker);
        $id = $app->users()->create('test-member@example.com', '', '테스트 회원');
        $this->sessionUser($id);
        self::assertSame(403, $this->get($app, $url)->getStatusCode());
        self::assertFileDoesNotExist($marker);
        $id = $app->users()->create('test-admin@example.com', '', '테스트 관리자', true);
        $this->sessionUser($id);
        self::assertSame(403, $this->post($app, $url, [])->getStatusCode());
        self::assertFileDoesNotExist($marker);
        self::assertSame(200, $this->get($app, $url)->getStatusCode());
        self::assertFileExists($marker);
        self::assertSame(404, $this->get($app, '/modules/testable/preview')->getStatusCode());
        self::assertFileDoesNotExist($app->storageDir() . '/extensions/enabled.json');
    }

    #[DataProvider('connectionProvider')]
    public function testAdminTestRequiresOptInValidMetadataAndActiveRequiredDependencies(array $dbConfig): void
    {
        $this->package('modules/no-test');
        $this->package('modules/unsafe-link', ['entry_path' => '//example.com', 'admin_test' => true]);
        $this->package('modules/dependent', ['entry_path' => '/preview', 'admin_test' => true, 'requires' => ['plugins/missing']]);
        $this->package('modules/no-route', ['entry_path' => '/preview', 'admin_test' => true]);
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('test-admin@example.com', '', '테스트 관리자', true);
        $this->get($app, '/login');
        $this->sessionUser($id);
        self::assertSame(404, $this->get($app, '/admin/modules/no-test/test')->getStatusCode());
        self::assertSame(404, $this->get($app, '/admin/modules/unsafe-link/test')->getStatusCode());
        self::assertSame(422, $this->get($app, '/admin/modules/dependent/test')->getStatusCode());
        self::assertSame(404, $this->get($app, '/admin/modules/no-route/test')->getStatusCode());
        $body = $this->body($this->get($app, '/admin/modules'));
        self::assertStringNotContainsString('href="//example.com', $body);
        self::assertFileDoesNotExist($app->storageDir() . '/extensions/enabled.json');
    }
}
