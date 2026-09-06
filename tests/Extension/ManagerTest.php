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
        $response = $slim->handle((new ServerRequestFactory())->createServerRequest('GET', '/extensions/modules/booking/ping'));
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
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/extensions/modules/booking/ping');
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
}
