<?php

declare(strict_types=1);

// 실제 계정·서버를 사용하지 않는 브라우저 회귀 테스트용 렌더링.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/modules/shop/autoload.php';

use GnuCms\App;
use GnuCms\Auth\Identity;
use GnuCms\Db\Schema;
use GnuCms\Modules\Shop\Controller;
use GnuCms\Modules\Shop\Service;
use GnuCms\Modules\Shop\Store;
use GnuCms\Tests\Shop\FakeGateway;
use GnuCms\View\PhpView;
use GnuCms\Web\Middleware\ViewMiddleware;
use Slim\Psr7\Factory\ServerRequestFactory;

$scenario = $argv[1] ?? 'catalog'; $base = $argv[2] ?? '/cms';
$toss = in_array($scenario, ['toss-payment', 'toss-return'], true);
$root = sys_get_temp_dir() . '/gnucms-shop-browser-' . Store::id();
$app = new App(['db' => ['dsn' => 'sqlite::memory:'], 'storage' => ['dir' => $root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test/cms']]);
(new Schema($app->db()))->create();
$app->setIdentity(Identity::user('browser', '운영자', true));
$provider = $toss ? 'toss' : 'inicis';
$gateway = new FakeGateway();
if ($toss) {
    $settings = new \GnuCms\Payment\Settings($app, 'toss'); $settings->install();
    $keys = array_map(static fn ($value) => str_replace('test_', 'live_', $value), \GnuCms\Tests\Payment\Fixtures::config('toss'));
    $settings->save('live', $keys); $settings->enable('live', true);
    $gateway = new \GnuCms\Payment\TossGateway($settings);
}
$shop = new Service($app, [$provider => $gateway]); $shop->install();
$shop->saveSettings(['name' => '소소한 상점', 'seller' => '소소한 상점', 'owner' => '테스트 운영자', 'business_number' => '000-00-00000', 'commerce_number' => '테스트',
    'phone' => '01000000000', 'email' => 'test@example.test', 'address' => '테스트 주소', 'return_address' => '테스트 반품 주소', 'policy' => '배송과 반품은 고객센터 또는 내 주문에서 안내받으실 수 있습니다.',
    'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'live', 'open' => '1']);
$p = null;
foreach (['매일 입는 코튼 셔츠', '작은 캔버스 가방', '포근한 니트'] as $name) {
    $id = $shop->catalog->save(['name' => $name, 'description' => '편안한 일상을 위한 제품입니다.', 'active' => '1', 'price' => 29000, 'stock' => 12,
        'option1_name' => '색상', 'option1_values' => '크림,그린', 'option2_name' => '사이즈', 'option2_values' => 'M,L']);
    $p ??= $shop->catalog->product($id);
}
$cart = [$p['variants'][0]['id'] => 2];
$order = $shop->createOrder('browser', $cart, ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '테스트 주소', 'consent' => '1'], $provider, Store::id(), 58000);
$pay = in_array($scenario, ['payment', 'toss-payment'], true);
$page = $pay ? 'order' : $scenario;
if ($page === 'manage-order') {
    $gateway->paid($order); $shop->sync($order['id']);
    $shop->fulfill($order['id'], 'ship', ['carrier' => '테스트 택배', 'tracking' => '000000000'], 'browser');
    $shop->claims->request($order['id'], ['kind' => 'return', 'item_id' => $shop->store->items($order['id'])[0]['id'], 'quantity' => 1, 'reason' => '반품을 신청합니다.', 'request_key' => Store::id()], 'browser');
    $exchange = $shop->claims->request($order['id'], ['kind' => 'exchange', 'item_id' => $shop->store->items($order['id'])[0]['id'], 'quantity' => 1,
        'replacement_id' => $p['variants'][1]['id'], 'reason' => '옵션 교환을 신청합니다.', 'request_key' => Store::id()], 'browser');
    $shop->claims->handle($exchange, 'approve', [], 'browser');
    $shop->claims->handle($exchange, 'receive', ['restock' => '1'], 'browser');
}
$_SESSION = ['csrf_token' => bin2hex(random_bytes(16)), 'shop_cart' => $cart];
$slim = \Slim\Factory\AppFactory::create(); $slim->setBasePath($base);
$view = new PhpView([dirname(__DIR__, 2) . '/templates/default'], $slim->getRouteCollector()->getRouteParser(), $base, static fn ($p) => '', static fn ($p) => '');
$controller = new Controller($shop);
if ($scenario === 'toss-return') $controller = new \GnuCms\Modules\Shop\TossReturnController($shop);
$slim->map(['GET', 'POST'], '/modules/shop/' . $page, static fn ($request, $response) => $scenario === 'toss-return' ? $controller->handle($request, $response) : $controller->handle($page, $request, $response));
$slim->addRoutingMiddleware(); $slim->add(new ViewMiddleware($view));
$query = in_array($page, ['order', 'manage-order'], true) ? '?id=' . $order['id'] : ($page === 'product' || $page === 'products' ? '?id=' . $p['id'] : '');
if ($scenario === 'toss-return') $query = '?' . http_build_query(['id' => $order['id'], 'state' => \GnuCms\Payment\CallbackToken::create($app, $order), 'paymentKey' => bin2hex(random_bytes(100)), 'orderId' => $order['id'], 'amount' => '58000']);
$request = (new ServerRequestFactory())->createServerRequest($pay ? 'POST' : 'GET', $base . '/modules/shop/' . $page . $query);
if ($pay) $request = $request->withParsedBody(['id' => $order['id'], 'action' => 'pay', 'csrf_token' => $_SESSION['csrf_token']]);
try { echo (string) $slim->handle($request)->getBody(); }
finally {
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
