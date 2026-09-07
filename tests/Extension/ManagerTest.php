<?php

declare(strict_types=1);

namespace GnuCms\Tests\Extension;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\ExtensionFixtures;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ManagerTest extends TestCase
{
    use ExtensionFixtures;

    private StateStore $state;
    private Manager $manager;

    protected function setUp(): void
    {
        $this->createExtensionRoot();
        $this->state = new StateStore($this->extensionRoot . '/storage/extensions');
        $this->manager = new Manager(new Catalog($this->extensionRoot), $this->state);
    }

    protected function tearDown(): void
    {
        $this->removeExtensionRoot();
    }

    public function testDiscoveryDoesNotExecuteDisabledCodeAndRejectsInvalidPackages(): void
    {
        $this->package('plugins/disabled', [], '<?php throw new RuntimeException("must not run");');
        $this->package('modules/wrong', ['type' => 'plugin']);
        $this->package('plugins/future', ['api' => 999]);
        $packages = $this->manager->packages();
        self::assertFalse($packages['plugins/disabled']['enabled']);
        self::assertNotNull($packages['modules/wrong']['error']);
        self::assertNotNull($packages['plugins/future']['error']);
        $this->manager->boot(new App([]), AppFactory::create());
        self::assertNull($this->manager->packages()['plugins/disabled']['error']);
        self::assertDirectoryDoesNotExist($this->extensionRoot . '/storage');
    }

    public function testTogglePreservesOtherSelectionsAndPackageData(): void
    {
        $this->package('plugins/one');
        $this->package('modules/two');
        $this->manager->setEnabled('plugins/one', true);
        $this->manager->setEnabled('modules/two', true);
        file_put_contents($this->extensionRoot . '/storage/data.txt', 'keep');
        $this->manager->setEnabled('plugins/one', false);
        self::assertSame(['modules/two'], $this->state->read());
        self::assertSame('keep', file_get_contents($this->extensionRoot . '/storage/data.txt'));
        self::assertFileExists($this->extensionRoot . '/plugins/one/bootstrap.php');
    }

    public function testPublicNavigationIncludesOnlyEnabledBootedRoutes(): void
    {
        $route = '<?php return static function ($context): void { $context->route("GET", "/catalog", static fn ($request, $response) => $response); };';
        $this->package('modules/shop', ['public_path' => '/catalog'], $route);
        $this->package('modules/disabled', ['public_path' => '/catalog'], $route);
        $this->package('modules/missing', ['public_path' => '/catalog']);
        $this->package('modules/broken', ['public_path' => '/catalog'], '<?php throw new RuntimeException("broken");');
        $this->package('modules/invalid', ['public_path' => '//outside.example']);
        self::assertNotNull($this->manager->packages()['modules/invalid']['error']);
        $this->manager->setEnabledMany(['modules/shop' => true, 'modules/missing' => true, 'modules/broken' => true]);
        $slim = AppFactory::create(); $slim->setBasePath('/cms');
        $this->manager->boot(new App([]), $slim);
        self::assertSame(['modules/shop' => ['name' => 'shop', 'url' => '/cms/modules/shop/catalog', 'base_url' => '/cms/modules/shop']], $this->manager->navigation());
        $this->manager->setEnabled('modules/shop', false);
        $this->manager->boot(new App([]), AppFactory::create());
        self::assertSame([], $this->manager->navigation());
    }

    public function testDependencyMustBeEnabledAndCannotBeDisabledWhileInUse(): void
    {
        $this->package('plugins/message');
        $this->package('modules/booking', ['requires' => ['plugins/message']]);
        try {
            $this->manager->setEnabled('modules/booking', true);
            self::fail('Missing dependency should block activation');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertSame([], $this->state->read());
        }
        $this->manager->setEnabled('plugins/message', true);
        $this->manager->setEnabled('modules/booking', true);
        try {
            $this->manager->setEnabled('plugins/message', false);
            self::fail('An active dependent must block deactivation');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertCount(2, $this->state->read());
        }
        $this->manager->setEnabled('modules/booking', false);
        $this->manager->setEnabled('plugins/message', false);
        self::assertSame([], $this->state->read());
    }

    public function testCustomPrefixAndLegacyPathsShareHandlersIncludingExternalCallbacks(): void
    {
        $this->package('modules/store', ['route_prefix' => '/store', 'public_path' => '/'], <<<'PHP'
<?php
return static function ($context): void {
    $context->route('GET', '/', static function ($request, $response) use ($context) {
        $response->getBody()->write($context->path('/cart'));
        return $response;
    });
    $context->externalPost('/callback', static fn ($request) => ($request->getQueryParams()['state'] ?? '') === 'valid',
        static fn ($request, $response) => $response->withStatus(303)->withHeader('Location', $context->path('/orders')));
};
PHP);
        $this->manager->setEnabled('modules/store', true);
        $slim = AppFactory::create(); $slim->setBasePath('/cms');
        $this->manager->boot(new App([]), $slim);
        self::assertSame(['name' => 'store', 'url' => '/cms/store', 'base_url' => '/cms/store'], $this->manager->navigation()['modules/store']);
        $factory = new ServerRequestFactory();
        foreach (['/cms/store', '/cms/store/', '/cms/modules/store/'] as $path) {
            self::assertSame('/store/cart', (string) $slim->handle($factory->createServerRequest('GET', $path))->getBody());
        }
        foreach (['/cms/store/callback', '/cms/modules/store/callback'] as $path) {
            $request = $factory->createServerRequest('POST', $path . '?state=valid')->withHeader('Content-Type', 'application/json');
            $request->getBody()->write('{"accepted":true}');
            self::assertSame('/store/orders', $slim->handle($request)->getHeaderLine('Location'));
            self::assertSame(403, $slim->handle($factory->createServerRequest('POST', $path))->getStatusCode());
            self::assertSame(405, $slim->handle($request->withMethod('GET'))->getStatusCode());
        }
    }

    public function testInvalidPrefixesCannotActivate(): void
    {
        foreach (['/', '//store', '/store/cart', '/Store', '/store?query=1', '/store#hash', '/%73tore', '/admin', '/modules', '/plugins', '/themes', '/vendor', '/assets', '/uploads', '/..', ['bad']] as $prefix) {
            $this->package('modules/store', ['route_prefix' => $prefix]);
            self::assertNotNull($this->manager->packages()['modules/store']['error']);
            try {
                $this->manager->setEnabled('modules/store', true);
                self::fail('Invalid prefix must not activate');
            } catch (DomainError $error) {
                self::assertSame(422, $error->status());
            }
        }
        self::assertSame([], $this->state->read());
    }

    public function testAdminPrefixValidationAndCollisionsProtectCoreRoutes(): void
    {
        foreach (['/shop', '/admin', '/admin/shop/edit', '/admin/Shop', '//admin/shop', '/admin/shop?x=1', ['bad']] as $prefix) {
            $this->package('modules/store', ['admin_route_prefix' => $prefix]);
            self::assertNotNull($this->manager->packages()['modules/store']['error']);
        }
        $this->package('modules/store', ['admin_route_prefix' => '/admin/shop'], '<?php file_put_contents(__DIR__ . "/ran", "unexpected");');
        $this->manager->setEnabled('modules/store', true);
        foreach (['/admin/shop', '/admin/shop/products', '/admin/{section}/products'] as $path) {
            $slim = AppFactory::create();
            $slim->get($path, static fn ($request, $response) => $response);
            $this->manager->boot(new App([]), $slim);
            self::assertStringContainsString('겹칩니다', $this->manager->packages()['modules/store']['error']);
            self::assertFileDoesNotExist($this->extensionRoot . '/modules/store/ran');
            self::assertCount(1, $slim->getRouteCollector()->getRoutes());
        }
    }

    public function testPrefixCollisionDoesNotReplaceCoreRoutesOrRunPackageCode(): void
    {
        $this->package('modules/conflict', ['route_prefix' => '/login'], '<?php file_put_contents(__DIR__ . "/ran", "unexpected");');
        $this->manager->setEnabled('modules/conflict', true);
        $slim = AppFactory::create();
        $slim->get('/login', static fn ($request, $response) => $response->withStatus(204));
        $this->manager->boot(new App([]), $slim);
        self::assertStringContainsString('겹칩니다', $this->manager->packages()['modules/conflict']['error']);
        self::assertFileDoesNotExist($this->extensionRoot . '/modules/conflict/ran');
        self::assertSame([], $this->manager->navigation());
        self::assertCount(1, $slim->getRouteCollector()->getRoutes());
        self::assertSame(204, $slim->handle((new ServerRequestFactory())->createServerRequest('GET', '/login'))->getStatusCode());
    }

    public function testPrefixCollisionWithAnotherPackagesCallbackIsRejected(): void
    {
        $this->package('modules/first', ['route_prefix' => '/store'], <<<'PHP'
<?php return static function ($context): void {
    $context->externalPost('/callback', static fn ($request) => false, static fn ($request, $response) => $response);
};
PHP);
        $this->package('modules/second', ['route_prefix' => '/store', 'requires' => ['modules/first']], '<?php throw new RuntimeException("must not run");');
        $this->manager->setEnabledMany(['modules/first' => true, 'modules/second' => true]);
        $this->manager->boot(new App([]), AppFactory::create());
        self::assertNull($this->manager->packages()['modules/first']['error']);
        self::assertStringContainsString('겹칩니다', $this->manager->packages()['modules/second']['error']);
    }

    public function testEnabledPackagesRegisterRoutesAndShareServicesInDependencyOrder(): void
    {
        $this->package('plugins/message', [], <<<'PHP'
<?php
return static function ($context): void {
    $context->provide('sender', new class {
        public function text(): string { return 'message service'; }
    });
};
PHP);
        $this->package('modules/booking', ['requires' => ['plugins/message']], <<<'PHP'
<?php
return static function ($context): void {
    $sender = $context->service('plugins/message', 'sender');
    $context->route('GET', '/ping', static function ($request, $response) use ($sender) {
        $response->getBody()->write($sender->text());
        return $response;
    });
};
PHP);
        $this->manager->setEnabled('plugins/message', true);
        $this->manager->setEnabled('modules/booking', true);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        $response = $slim->handle((new ServerRequestFactory())->createServerRequest('GET', '/modules/booking/ping'));
        self::assertSame('message service', (string) $response->getBody());
        $this->manager->setEnabled('modules/booking', false);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertCount(0, $slim->getRouteCollector()->getRoutes());
    }

    public function testBrokenBootstrapDoesNotPublishPartialRoutesOrRunDependents(): void
    {
        $this->package('plugins/broken', [], <<<'PHP'
<?php
return static function ($context): void {
    $context->route('GET', '/partial', static fn ($request, $response) => $response);
    throw new RuntimeException('private exception contents');
};
PHP);
        $this->package('modules/dependent', ['requires' => ['plugins/broken']]);
        $this->manager->setEnabled('plugins/broken', true);
        $this->manager->setEnabled('modules/dependent', true);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertCount(0, $slim->getRouteCollector()->getRoutes());
        $packages = $this->manager->packages();
        self::assertNotNull($packages['plugins/broken']['error']);
        self::assertNotNull($packages['modules/dependent']['error']);
        self::assertStringNotContainsString('private exception contents', $packages['plugins/broken']['error']);
    }

    public function testChangedManifestCycleBlocksExecution(): void
    {
        $this->package('plugins/a', ['requires' => ['modules/b']]);
        $this->package('modules/b', ['requires' => ['plugins/a']]);
        $this->state->update(static fn (array $state): array => ['plugins/a', 'modules/b']);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertCount(0, $slim->getRouteCollector()->getRoutes());
        self::assertStringContainsString('순환', $this->manager->packages()['plugins/a']['error']);
        $this->manager->setEnabled('plugins/a', false);
        self::assertSame(['modules/b'], $this->state->read());
    }

    public function testOptionalPluginCanBeAddedAndRemovedWithoutDisablingModule(): void
    {
        $this->package('modules/booking', ['optional' => ['plugins/message']], <<<'PHP'
<?php
return static function ($context): void {
    $sender = $context->service('plugins/message', 'sender');
    $context->route('GET', '/ping', static function ($request, $response) use ($sender) {
        $response->getBody()->write($sender === null ? 'booking only' : 'booking with message');
        return $response;
    });
};
PHP);
        $this->manager->setEnabled('modules/booking', true);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/modules/booking/ping');
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertSame('booking only', (string) $slim->handle($request)->getBody());
        $this->package('plugins/message', [], '<?php return static function ($context): void { $context->provide("sender", new stdClass()); };');
        $this->manager->setEnabled('plugins/message', true);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertSame('booking with message', (string) $slim->handle($request)->getBody());
        $this->manager->setEnabled('plugins/message', false);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        self::assertSame('booking only', (string) $slim->handle($request)->getBody());
        self::assertSame(['modules/booking'], $this->state->read());
    }

    public function testSymlinkAndMissingEntryCannotBeEnabled(): void
    {
        $this->package('plugins/missing');
        unlink($this->extensionRoot . '/plugins/missing/bootstrap.php');
        $this->package('plugins/linked');
        unlink($this->extensionRoot . '/plugins/linked/bootstrap.php');
        symlink($this->extensionRoot . '/plugins/missing/extension.json', $this->extensionRoot . '/plugins/linked/bootstrap.php');
        foreach (['plugins/missing', 'plugins/linked'] as $key) {
            try {
                $this->manager->setEnabled($key, true);
                self::fail('Invalid package must not activate');
            } catch (DomainError $e) {
                self::assertSame(422, $e->status());
                self::assertSame([], $this->state->read());
            }
        }
    }

    public function testMissingActivePackageCanBeDisabled(): void
    {
        $this->state->update(static fn (array $state): array => ['plugins/missing']);
        self::assertNotNull($this->manager->packages()['plugins/missing']['error']);
        $this->manager->setEnabled('plugins/missing', false);
        self::assertSame([], $this->state->read());
    }

    public function testCorruptStateIsNeverOverwritten(): void
    {
        $this->package('plugins/one');
        $this->manager->setEnabled('plugins/one', true);
        $file = $this->extensionRoot . '/storage/extensions/enabled.json';
        file_put_contents($file, '{broken');
        try {
            $this->manager->setEnabled('plugins/one', false);
            self::fail('Corrupt state must fail closed');
        } catch (DomainError $e) {
            self::assertSame(503, $e->status());
            self::assertSame('{broken', file_get_contents($file));
        }
    }

    public function testBulkSaveValidatesFinalDependenciesAndDoesNotPartiallySave(): void
    {
        $this->package('plugins/provider');
        $this->package('plugins/consumer', ['requires' => ['plugins/provider']]);
        $this->package('plugins/other');
        // 제출 순서가 의존 순서와 달라도 함께 활성화할 수 있다.
        $this->manager->setEnabledMany(['plugins/consumer' => true, 'plugins/provider' => true]);
        $before = $this->state->snapshot();
        try {
            $this->manager->setEnabledMany(['plugins/other' => true, 'plugins/provider' => false]);
            self::fail('Dependent extension must block the whole save');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertSame($before, $this->state->snapshot());
        }
        $this->manager->setEnabledMany(['plugins/provider' => false, 'plugins/consumer' => false]);
        self::assertSame([], $this->state->read());
    }

    public function testRecentToggleOrderPersistsForBothOnAndOffAndIgnoresUnchangedValues(): void
    {
        foreach (['alpha', 'bravo', 'charlie'] as $id) {
            $this->package('plugins/' . $id);
        }
        $this->manager->setEnabledMany(['plugins/alpha' => true, 'plugins/bravo' => true], ['plugins/bravo', 'plugins/alpha']);
        self::assertSame(['plugins/bravo', 'plugins/alpha', 'plugins/charlie'], array_keys($this->manager->packages()));
        $this->manager->setEnabled('plugins/alpha', false);
        $fresh = new Manager(new Catalog($this->extensionRoot), new StateStore($this->extensionRoot . '/storage/extensions'));
        self::assertSame(['plugins/alpha', 'plugins/bravo', 'plugins/charlie'], array_keys($fresh->packages()));
        self::assertFalse($fresh->packages()['plugins/alpha']['enabled']);
        $this->manager->setEnabled('plugins/bravo', true);
        self::assertSame(['plugins/alpha', 'plugins/bravo', 'plugins/charlie'], array_keys($fresh->packages()));
    }

    public function testLegacyStateIsReadWithoutModificationAndMigratesOnSave(): void
    {
        $this->package('plugins/old');
        mkdir($this->extensionRoot . '/storage/extensions', 0700, true);
        $file = $this->extensionRoot . '/storage/extensions/enabled.json';
        file_put_contents($file, '["plugins/old"]');
        self::assertTrue($this->manager->packages()['plugins/old']['enabled']);
        self::assertSame('["plugins/old"]', file_get_contents($file));
        $this->manager->setEnabled('plugins/old', false);
        self::assertSame(['enabled' => [], 'recent' => ['plugins/old']], $this->state->snapshot());
        self::assertSame(2, json_decode(file_get_contents($file), true)['version']);
    }

    public function testInvalidHistoryIsNotSilentlyDiscarded(): void
    {
        mkdir($this->extensionRoot . '/storage/extensions', 0700, true);
        $file = $this->extensionRoot . '/storage/extensions/enabled.json';
        $invalid = '{"version":2,"enabled":[],"recent":["../wrong"]}';
        file_put_contents($file, $invalid);
        try {
            $this->state->update(static fn (array $active): array => []);
            self::fail('Invalid history must be preserved for recovery');
        } catch (DomainError $e) {
            self::assertSame(503, $e->status());
            self::assertSame($invalid, file_get_contents($file));
        }
    }
}
