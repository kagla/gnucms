<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Modules\Shop\Service;
use GnuCms\Modules\Shop\Store;
use GnuCms\Payment\Settings;
use GnuCms\Tests\Shop\FakeGateway;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class ShopTest extends WebTestCase
{
    private App $app;
    private Service $shop;
    private string $root;

    private function setupShop(array $config, bool $install = true): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-shop-web-' . Store::id();
        $config['prefix'] = 'sw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']]);
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabledMany(['modules/shop' => true, 'plugins/payment-inicis' => true, 'plugins/payment-kcp' => true, 'plugins/payment-kspay' => true, 'plugins/payment-toss' => true]);
        $this->shop = new Service($this->app, ['inicis' => new FakeGateway()]);
        if ($install) $this->shop->install();
    }

    private function signIn(bool $admin): string
    {
        $id = $this->app->users()->create(bin2hex(random_bytes(4)) . '@example.test', '', ($admin ? '운영자' : '구매자') . bin2hex(random_bytes(3)), $admin);
        $this->get($this->app, '/login');
        session_start(); $_SESSION['user_id'] = $id; $_SESSION['session_epoch'] = 0; session_write_close();
        return (string) $id;
    }

    private function csrf(array $body): array { return $body + ['csrf_token' => $_SESSION['csrf_token']]; }

    private function product(): array
    {
        $id = $this->shop->catalog->save(['name' => '<script>상품</script>', 'description' => '상품 설명', 'price' => 10000, 'stock' => 10, 'active' => '1']);
        return $this->shop->catalog->product($id);
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach (array_reverse(\GnuCms\Modules\Shop\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            foreach (array_keys(Settings::PROVIDERS) as $id) {
                $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_transactions'));
                $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_settings'));
            }
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testInstallationAuthorizationAndAllAdminPagesWithSubdirectory(array $config): void
    {
        $this->setupShop($config, false);
        self::assertSame(200, $this->get($this->app, '/modules/shop/catalog')->getStatusCode());
        self::assertFalse($this->shop->ready());
        self::assertSame(401, $this->get($this->app, '/modules/shop/admin')->getStatusCode());
        $this->signIn(false);
        foreach (['admin', 'products', 'settings', 'inventory', 'settlement', 'export'] as $page) self::assertSame(403, $this->get($this->app, '/modules/shop/' . $page)->getStatusCode());
        $this->signIn(true);
        self::assertSame(403, $this->post($this->app, '/modules/shop/admin', ['action' => 'install'])->getStatusCode());
        self::assertSame(303, $this->post($this->app, '/modules/shop/admin', $this->csrf(['action' => 'install']))->getStatusCode());
        self::assertTrue($this->shop->ready());
        $p = $this->product();
        foreach (['admin', 'products', 'settings', 'inventory', 'settlement', 'catalog', 'product?id=' . $p['id']] as $page) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms/modules/shop/' . $page);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode(), $page . ': ' . substr($this->body($response), 0, 200));
            self::assertStringContainsString('/cms/modules/shop/', $this->body($response));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
        foreach (array_keys(Settings::PROVIDERS) as $id) {
            self::assertSame(200, $this->get($this->app, '/plugins/payment-' . $id . '/settings')->getStatusCode());
            self::assertFalse((new Settings($this->app, $id))->ready());
            self::assertSame(200, $this->post($this->app, '/plugins/payment-' . $id . '/settings', $this->csrf(['action' => 'install']))->getStatusCode());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testMemberCheckoutSnapshotDuplicatePostAndOrderPrivacy(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $user = $this->signIn(false);
        $this->shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'live', 'open' => '1']);
        $settings = new Settings($this->app, 'inicis'); $settings->install();
        $settings->save('live', \GnuCms\Tests\Payment\Fixtures::config('inicis'));
        $settings->enable('live', true);
        self::assertSame(303, $this->post($this->app, '/modules/shop/cart', $this->csrf(['action' => 'add', 'variant_id' => $p['variants'][0]['id'], 'quantity' => '2']))->getStatusCode());
        $response = $this->get($this->app, '/modules/shop/checkout');
        self::assertSame(200, $response->getStatusCode()); self::assertStringContainsString('23,000', $this->body($response));
        self::assertStringContainsString('&lt;script&gt;상품&lt;/script&gt;', $this->body($response));
        $key = array_key_last($_SESSION['shop_previews']);
        $body = $this->csrf(['request_key' => $key, 'provider' => 'inicis', 'name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'address_detail' => '', 'consent' => '1']);
        $response = $this->post($this->app, '/modules/shop/checkout', $body);
        self::assertSame(303, $response->getStatusCode()); $location = $response->getHeaderLine('Location');
        self::assertStringContainsString('/modules/shop/order?id=', $location);
        self::assertSame($location, $this->post($this->app, '/modules/shop/checkout', $body)->getHeaderLine('Location'));
        self::assertCount(1, $this->shop->orders($user));
        self::assertSame(200, $this->get($this->app, $location)->getStatusCode());
        $this->signIn(false);
        self::assertSame(404, $this->get($this->app, $location)->getStatusCode());
        self::assertStringNotContainsString('buyer@example.test', $this->body($this->get($this->app, $location)));
    }

    #[DataProvider('connectionProvider')]
    public function testForgedCallbacksAndMalformedInputsCannotMutateState(array $config): void
    {
        $this->setupShop($config); $this->signIn(true);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/modules/shop/callback?id=' . Store::id())->withHeader('Content-Type', 'application/json');
        $request->getBody()->write('{"type":"Transaction.Paid","data":{"paymentId":"' . Store::id() . '"}}');
        self::assertSame(403, Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '')->handle($request)->getStatusCode());
        self::assertSame(422, $this->get($this->app, '/modules/shop/catalog', ['q' => ['bad']])->getStatusCode());
        self::assertSame(403, $this->post($this->app, '/modules/shop/settings', ['open' => '1'])->getStatusCode());
        self::assertSame([], $this->shop->orders(null));
    }

    #[DataProvider('connectionProvider')]
    public function testTossSettingsCheckoutAndAuthenticatedReturnOnlyPreparePost(array $config): void
    {
        $this->setupShop($config);
        self::assertSame(401, $this->get($this->app, '/plugins/payment-toss/settings')->getStatusCode());
        $user = $this->signIn(true); $path = '/plugins/payment-toss/settings';
        self::assertSame(200, $this->post($this->app, $path, $this->csrf(['action' => 'install']))->getStatusCode());
        $credentials = \GnuCms\Tests\Payment\Fixtures::config('toss');
        $response = $this->post($this->app, $path, $this->csrf($credentials + ['action' => 'save', 'environment' => 'test']));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString($credentials['secret_key'], $this->body($response));
        self::assertSame(200, $this->post($this->app, $path, $this->csrf(['action' => 'enable', 'environment' => 'test']))->getStatusCode());
        $settings = new Settings($this->app, 'toss'); self::assertTrue($settings->available('test'));
        $this->shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'test', 'open' => '1']);
        $p = $this->product();
        $shop = new Service($this->app, ['toss' => new \GnuCms\Payment\TossGateway($settings)]);
        $order = $shop->createOrder($user, [$p['variants'][0]['id'] => 1], ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'toss', Store::id(), 13000);
        $response = $this->post($this->app, '/modules/shop/order', $this->csrf(['id' => $order['id'], 'action' => 'pay']));
        self::assertSame(200, $response->getStatusCode()); self::assertStringContainsString('https://js.tosspayments.com/v2/standard', $this->body($response));
        self::assertStringNotContainsString($credentials['secret_key'], $this->body($response));
        $query = ['id' => $order['id'], 'state' => \GnuCms\Payment\CallbackToken::create($this->app, $order),
            'paymentKey' => bin2hex(random_bytes(100)), 'orderId' => $order['id'], 'amount' => '13000'];
        $this->app->setIdentity(\GnuCms\Auth\Identity::guest());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms/modules/shop/toss-return?' . http_build_query($query));
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode(), $this->body($response));
        self::assertStringContainsString('action="/cms/modules/shop/callback?', $this->body($response));
        self::assertStringContainsString('name="paymentKey" value="' . $query['paymentKey'] . '"', $this->body($response));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control')); self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString("form-action 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('pending', $shop->store->get('shop_orders', $order['id'])['status']);
        self::assertSame('ready', (new \GnuCms\Payment\Journal($settings))->read($order['id'])['approval']);
        self::assertSame(403, $this->get($this->app, '/modules/shop/toss-return', array_replace($query, ['state' => str_repeat('0', 64)]))->getStatusCode());
        foreach ([['amount' => '1'], ['orderId' => Store::id()], ['paymentKey' => ['bad']], ['paymentKey' => '"><script>']] as $bad) {
            self::assertSame(422, $this->get($this->app, '/modules/shop/toss-return', array_replace($query, $bad))->getStatusCode());
        }
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/modules/shop/callback?' . http_build_query(['id' => $order['id'], 'state' => $query['state']]))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query(['paymentKey' => $query['paymentKey'], 'orderId' => $order['id'], 'amount' => '1']));
        self::assertSame(303, Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '')->handle($request)->getStatusCode());
        self::assertSame('ready', (new \GnuCms\Payment\Journal($settings))->read($order['id'])['approval']);
    }
}
