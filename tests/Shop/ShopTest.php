<?php

declare(strict_types=1);

namespace GnuCms\Tests\Shop;

use GnuCms\App;
use GnuCms\Db\Schema as CoreSchema;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Modules\Shop\Schema;
use GnuCms\Modules\Shop\Service;
use GnuCms\Modules\Shop\Store;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class ShopTest extends DatabaseTestCase
{
    private App $app;
    private Service $shop;
    private FakeGateway $gateway;
    private string $root;

    private function setupShop(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-shop-' . Store::id();
        mkdir($this->root, 0700, true);
        if ($config['dsn'] === 'sqlite::memory:') $config['dsn'] = 'sqlite:' . $this->root . '/shop.sqlite';
        $config['prefix'] = 'sh' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new CoreSchema($this->app->db()))->create();
        $this->gateway = new FakeGateway();
        $this->shop = new Service($this->app, ['inicis' => $this->gateway]);
        $this->shop->install();
        $this->shop->saveSettings(['name' => '테스트 상점', 'seller' => '테스트', 'owner' => '운영자', 'business_number' => '000-00-00000',
            'phone' => '01000000000', 'email' => 'shop@example.test', 'address' => '테스트 주소', 'return_address' => '테스트 반품 주소',
            'policy' => '테스트용 정책', 'shipping' => '3000', 'free_shipping' => '50000', 'environment' => 'live', 'open' => '1']);
    }

    private function product(int $stock = 5, bool $options = false): array
    {
        $id = $this->shop->catalog->save(['name' => '<b>셔츠</b>', 'description' => '설명', 'price' => '10000', 'stock' => (string) $stock, 'active' => '1',
            'option1_name' => $options ? '색상' : '', 'option1_values' => $options ? '검정,흰색' : '',
            'option2_name' => $options ? '크기' : '', 'option2_values' => $options ? 'M,L' : '']);
        return $this->shop->catalog->product($id, true);
    }

    private function customer(): array
    {
        return ['name' => '테스트 구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000',
            'address' => '테스트 주소', 'address_detail' => '', 'memo' => '', 'consent' => '1'];
    }

    private function order(array $product, int $quantity = 1, ?string $key = null): array
    {
        $cart = [$product['variants'][0]['id'] => $quantity];
        return $this->shop->createOrder('member', $cart, $this->customer(), 'inicis', $key ?? Store::id(), $this->shop->quote($cart)['total']);
    }

    private function ship(array $order): void
    {
        $this->gateway->paid($order);
        $this->shop->sync($order['id']);
        $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '000000'], 'admin');
    }

    private function claim(array $order, string $kind, int $quantity = 1, string $replacement = ''): string
    {
        $item = $this->shop->store->items($order['id'])[0];
        return $this->shop->claims->request($order['id'], ['kind' => $kind, 'item_id' => $item['id'], 'quantity' => $quantity,
            'replacement_id' => $replacement, 'reason' => '테스트 사유', 'request_key' => Store::id()], 'member');
    }

    private function receive(string $claim, bool $restock = true): void
    {
        $this->shop->claims->handle($claim, 'approve', [], 'admin');
        $this->shop->claims->handle($claim, 'receive', ['restock' => $restock ? '1' : '0'], 'admin');
    }

    private function rejected(callable $work): void
    {
        try { $work(); self::fail('입력을 거절해야 합니다.'); } catch (DomainError $e) { self::assertContains($e->status(), [403, 404, 422, 503]); }
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_inicis_settings'));
            foreach (array_reverse(Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            (new CoreSchema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        Clock::unfreeze();
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testVariantPricesAndStocksAreSavedTogetherAndUsedForOrders(array $config): void
    {
        $this->setupShop($config);
        $input = ['name' => '옵션별 상품', 'active' => '1', 'option1_name' => '색상', 'option1_values' => '검정,흰색',
            'variants' => [
                ['option1' => '검정', 'option2' => '', 'price' => '12000', 'stock' => '3'],
                ['option1' => '흰색', 'option2' => '', 'price' => '18000', 'stock' => '7'],
            ]];
        $id = $this->shop->catalog->save($input);
        $product = $this->shop->catalog->product($id);
        [$black, $white] = $product['variants'];
        self::assertSame([12000, 18000], array_map('intval', array_column($product['variants'], 'price')));
        self::assertSame([3, 7], array_map('intval', array_column($product['variants'], 'stock')));
        $cart = [$black['id'] => 2, $white['id'] => 1];
        self::assertSame(42000, $this->shop->quote($cart)['subtotal']);
        $order = $this->shop->createOrder('member', $cart, $this->customer(), 'inicis', Store::id(), 45000);
        self::assertSame(1, (int) $this->shop->store->get('shop_variants', $black['id'])['stock']);
        self::assertSame(6, (int) $this->shop->store->get('shop_variants', $white['id'])['stock']);
        $fresh = $this->shop->catalog->product($id);
        $edit = $input + ['id' => $id, 'version' => $fresh['version']];
        $edit['variants'] = array_map(static fn ($row) => array_intersect_key($row, array_flip(['id', 'version', 'option1', 'option2', 'price', 'stock'])), $fresh['variants']);
        $edit['variants'][0]['price'] = 15000;
        $edit['variants'][0]['stock'] = 9;
        $this->shop->catalog->save($edit);
        self::assertSame(48000, $this->shop->quote($cart)['subtotal']);
        self::assertSame(42000, array_sum(array_map(static fn ($item) => (int) $item['price'] * (int) $item['quantity'], $this->shop->store->items($order['id']))));
        self::assertSame(9, (int) $this->shop->store->get('shop_variants', $black['id'])['stock']);
        self::assertSame(1, (int) $this->app->db()->selectOne('SELECT COUNT(*) AS n FROM ' . $this->app->db()->table('shop_stock') . " WHERE variant_id = ? AND kind = 'adjustment'", [$black['id']])['n']);
    }

    #[DataProvider('connectionProvider')]
    public function testVariantBatchRejectsTamperingAndRollsBackOnConcurrentStockChange(array $config): void
    {
        $this->setupShop($config); $product = $this->product(5, true);
        $input = ['id' => $product['id'], 'version' => $product['version'], 'name' => '덮어쓰면 안 됨', 'active' => '1',
            'option1_name' => '색상', 'option1_values' => '검정,흰색', 'option2_name' => '크기', 'option2_values' => 'L,M',
            'variants' => array_map(static fn ($row) => array_intersect_key($row, array_flip(['id', 'version', 'option1', 'option2', 'price', 'stock'])), $product['variants'])];
        foreach (['missing', 'duplicate', 'foreign', 'negative', 'zero-price', 'nested', 'extra'] as $case) {
            $bad = $input;
            if ($case === 'missing') array_pop($bad['variants']);
            if ($case === 'duplicate') $bad['variants'][1] = $bad['variants'][0];
            if ($case === 'foreign') $bad['variants'][0]['id'] = Store::id();
            if ($case === 'negative') $bad['variants'][0]['stock'] = '-1';
            if ($case === 'zero-price') $bad['variants'][0]['price'] = '0';
            if ($case === 'nested') $bad['variants'][0]['price'] = ['bad'];
            if ($case === 'extra') $bad['variants'][0]['option1'] = '미등록 색상';
            $this->rejected(fn () => $this->shop->catalog->save($bad));
            self::assertSame($product['name'], $this->shop->catalog->product($product['id'])['name'], $case);
        }
        $input['variants'][0]['price'] = 9999; $input['variants'][0]['stock'] = 100;
        $last = $product['variants'][3]['id'];
        $this->shop->store->stock($last, -1, 'reserve', 'test');
        $this->rejected(fn () => $this->shop->catalog->save($input));
        $after = $this->shop->catalog->product($product['id']);
        self::assertSame($product['name'], $after['name']);
        self::assertSame((int) $product['version'], (int) $after['version']);
        self::assertSame(10000, (int) $after['variants'][0]['price']);
        self::assertSame(5, (int) $after['variants'][0]['stock']);
        self::assertSame(4, (int) $after['variants'][3]['stock']);
        self::assertSame(0, (int) $this->app->db()->selectOne('SELECT COUNT(*) AS n FROM ' . $this->app->db()->table('shop_stock') . " WHERE kind = 'adjustment'")['n']);
    }

    #[DataProvider('connectionProvider')]
    public function testInstallationIsIdempotentAndBackupOwnsEveryTable(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $this->shop->install();
        self::assertTrue($this->shop->ready());
        self::assertSame($p['name'], $this->shop->catalog->product($p['id'])['name']);
        self::assertEqualsCanonicalizing(Schema::TABLES, (new PackageSchema($this->app->db(), $this->root))->backupTables());
    }

    #[DataProvider('connectionProvider')]
    public function testTwoOptionLimitSnapshotsAndStockChangesAreAtomic(array $config): void
    {
        $this->setupShop($config); $p = $this->product(2, true);
        self::assertSame('색상명', \GnuCms\Modules\Shop\Input::text('색상명', '옵션 이름', 3));
        $this->rejected(fn () => \GnuCms\Modules\Shop\Input::text('색상이름', '옵션 이름', 3));
        $colors = $this->shop->catalog->save(['name' => 'BLUE 50%', 'option1_name' => '색상', 'option1_values' => 'Blue,blue', 'active' => '1']);
        self::assertCount(2, $this->shop->catalog->product($colors)['variants']);
        self::assertCount(1, $this->shop->catalog->listing(query: 'blue 50%'));
        self::assertCount(0, $this->shop->catalog->listing(query: '_'));
        self::assertCount(4, $p['variants']);
        $key = Store::id(); $order = $this->order($p, 2, $key);
        self::assertSame($order['id'], $this->shop->createOrder('member', [], [], 'inicis', $key, 0)['id']);
        self::assertSame(0, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        $this->rejected(fn () => $this->order($p));
        $this->rejected(fn () => $this->shop->catalog->saveVariant(['variant_id' => $p['variants'][0]['id'], 'version' => $p['variants'][0]['version'], 'price' => 1, 'stock' => 100]));
        $this->rejected(fn () => $this->shop->catalog->save(['name' => '상품', 'option3_name' => '재질']));
        $this->rejected(fn () => $this->shop->createOrder('another', [], [], 'inicis', $key, 0));
        self::assertStringNotContainsString('buyer@example.test', $order['customer']);
        $this->rejected(fn () => $this->shop->detail($order['id'], 'another'));
        $this->shop->cancelPending($order['id'], 'member'); $this->shop->cancelPending($order['id'], 'member');
        self::assertSame(2, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
    }

    #[DataProvider('connectionProvider')]
    public function testPriceTamperingAndMismatchedPaymentNeverAllowShipping(array $config): void
    {
        $this->setupShop($config); $p = $this->product();
        $this->rejected(fn () => $this->shop->createOrder('member', [$p['variants'][0]['id'] => 1], $this->customer(), 'inicis', Store::id(), 1));
        $order = $this->order($p); $this->gateway->paid($order);
        $this->gateway->payments[$order['id']]['valid'] = false;
        $this->shop->sync($order['id']);
        $this->rejected(fn () => $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '1'], 'admin'));
        self::assertSame('pending', $this->shop->store->get('shop_orders', $order['id'])['status']);
        self::assertSame([], $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('shop_money')));
    }

    #[DataProvider('connectionProvider')]
    public function testRepeatedPaymentAndFullRefundRestoreStockOnlyOnce(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p, 2);
        $this->gateway->paid($order); $this->shop->sync($order['id']); $this->shop->sync($order['id']);
        $paidSnapshot = $this->gateway->payments[$order['id']];
        $key = Store::id();
        $id = $this->shop->claims->refund($order['id'], ['request_key' => $key, 'reason' => '취소'], 'admin');
        self::assertSame($id, $this->shop->claims->refund($order['id'], ['request_key' => $key], 'admin'));
        $this->shop->sync($order['id']);
        self::assertCount(1, $this->gateway->calls);
        self::assertSame(5, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $order['id'])['status']);
        self::assertCount(2, $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('shop_money')));
        $this->gateway->payments[$order['id']] = $paidSnapshot; $this->shop->sync($order['id']);
        self::assertSame(23000, (int) $this->shop->store->get('shop_orders', $order['id'])['refunded']);
    }

    #[DataProvider('connectionProvider')]
    public function testPartialReturnRefundsExactQuantityAndShippingOnlyOnce(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p, 3); $this->ship($order);
        $claim = $this->claim($order, 'return');
        $this->rejected(fn () => $this->shop->claims->refund($order['id'], ['claim_id' => $claim, 'request_key' => Store::id()], 'admin'));
        $this->receive($claim);
        $this->shop->claims->refund($order['id'], ['claim_id' => $claim, 'request_key' => Store::id(), 'include_shipping' => '1'], 'admin');
        self::assertSame(13000, $this->gateway->calls[0]['amount']);
        self::assertSame(3, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        $second = $this->claim($order, 'return', 2); $this->receive($second, false);
        $this->shop->claims->refund($order['id'], ['claim_id' => $second, 'request_key' => Store::id(), 'include_shipping' => '1'], 'admin');
        self::assertSame(20000, $this->gateway->calls[1]['amount']);
        self::assertSame(3, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $order['id'])['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testExchangeTracksReplacementAndCanBeReturnedWithoutNewRevenue(array $config): void
    {
        $this->setupShop($config); $p = $this->product(5, true); $order = $this->order($p); $this->ship($order);
        $replacement = $p['variants'][1]['id']; $claim = $this->claim($order, 'exchange', 1, $replacement); $this->receive($claim);
        $this->shop->claims->handle($claim, 'exchange', ['carrier' => '택배', 'tracking' => '2'], 'admin');
        self::assertSame(5, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        self::assertSame(4, (int) $this->shop->store->get('shop_variants', $replacement)['stock']);
        $this->rejected(fn () => $this->shop->claims->handle($claim, 'exchange', ['carrier' => '택배', 'tracking' => '2'], 'admin'));
        $items = $this->shop->store->items($order['id']);
        self::assertCount(2, $items);
        $item = array_values(array_filter($items, static fn ($i) => $i['exchange_claim_id'] !== ''))[0];
        $return = $this->shop->claims->request($order['id'], ['kind' => 'return', 'item_id' => $item['id'], 'quantity' => 1, 'reason' => '반품', 'request_key' => Store::id()], 'member');
        $this->receive($return);
        $this->shop->claims->refund($order['id'], ['claim_id' => $return, 'request_key' => Store::id()], 'admin');
        self::assertSame(5, (int) $this->shop->store->get('shop_variants', $replacement)['stock']);
        $date = (new \DateTimeImmutable('@' . Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d');
        $report = $this->shop->settlement->report($date, $date);
        self::assertSame(1, (int) $report['sold_quantity']); self::assertSame(1, $report['returned_quantity']);
        self::assertSame(1, $report['exchanged_quantity']); self::assertSame(13000, $report['gross']);
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownRefundBlocksNewRefundAndLostResponseRecoversByQuery(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p); $this->gateway->paid($order); $this->shop->sync($order['id']);
        $this->gateway->lostResponse = true;
        $this->rejected(fn () => $this->shop->claims->refund($order['id'], ['request_key' => Store::id()], 'admin'));
        self::assertSame('paid', $this->shop->store->get('shop_orders', $order['id'])['status']);
        $this->rejected(fn () => $this->shop->claims->refund($order['id'], ['request_key' => Store::id()], 'admin'));
        $this->shop->sync($order['id']);
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $order['id'])['status']);
        self::assertCount(1, $this->gateway->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testLatePaymentIsCancelledWithoutReservingStockAgain(array $config): void
    {
        $this->setupShop($config); $p = $this->product(1); $order = $this->order($p);
        $this->shop->cancelPending($order['id'], 'member');
        $this->gateway->paid($order); $this->shop->sync($order['id']); $this->shop->sync($order['id']);
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $order['id'])['status']);
        self::assertSame(1, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        self::assertCount(1, $this->gateway->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testPendingClaimBlocksShippingAndRejectionReleasesClaimQuantity(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p); $this->gateway->paid($order); $this->shop->sync($order['id']);
        $claim = $this->claim($order, 'cancel');
        $this->rejected(fn () => $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '1'], 'admin'));
        $this->shop->claims->handle($claim, 'reject', ['note' => '고객 요청 철회'], 'admin');
        $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '1'], 'admin');
        $return = $this->claim($order, 'return');
        $this->rejected(fn () => $this->claim($order, 'return'));
        $this->shop->claims->handle($return, 'reject', ['note' => '철회'], 'admin');
        self::assertNotEmpty($this->claim($order, 'return'));
    }

    #[DataProvider('connectionProvider')]
    public function testLastUnitCannotBePurchasedByTwoProcesses(array $config): void
    {
        $this->setupShop($config); $p = $this->product(1);
        $file = $this->root . '/worker.json';
        file_put_contents($file, json_encode(['db' => $this->app->config('db'), 'storage' => ['dir' => $this->root],
            'auth' => ['secret' => $this->app->config('auth.secret')], 'variant' => $p['variants'][0]['id']], JSON_THROW_ON_ERROR));
        chmod($file, 0600);
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/OrderWorker.php', $file], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process); $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) { self::assertSame("ready\n", fgets($pipes[1])); }
        foreach ($workers as [, $pipes]) { fwrite($pipes[0], "go\n"); fclose($pipes[0]); }
        $outcomes = [];
        foreach ($workers as [$process, $pipes]) {
            $outcomes[] = trim(stream_get_contents($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            fclose($pipes[1]); fclose($pipes[2]); self::assertSame(0, proc_close($process));
        }
        sort($outcomes); self::assertSame(['ordered', 'rejected'], $outcomes);
        self::assertCount(1, $this->shop->orders(null));
        self::assertSame(0, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
    }

    #[DataProvider('connectionProvider')]
    public function testSettlementUsesKoreanDayAndSeparatesTestPaymentsAndPayoutDifferences(array $config): void
    {
        $this->setupShop($config); $p = $this->product(10);
        Clock::freeze('2026-09-05 14:59:59'); $order = $this->order($p); $this->gateway->paid($order); $this->shop->sync($order['id']);
        Clock::freeze('2026-09-05 15:00:00'); $second = $this->order($p, 2); $this->gateway->paid($second); $this->shop->sync($second['id']);
        $this->shop->claims->refund($order['id'], ['request_key' => Store::id()], 'admin');
        $testSettings = $this->shop->settings(); $testSettings['environment'] = 'test'; $testSettings['open'] = '1';
        $this->shop->saveSettings($testSettings); $testOrder = $this->order($p); $this->gateway->paid($testOrder); $this->shop->sync($testOrder['id']);
        $report = $this->shop->settlement->report('2026-09-06', '2026-09-06');
        self::assertSame(23000, $report['gross']); self::assertSame(13000, $report['refunds']); self::assertSame(10000, $report['net']);
        self::assertSame(2, (int) $report['sold_quantity']); self::assertSame(1, $report['cancelled_quantity']);
        self::assertSame(13000, $this->shop->settlement->report('2026-09-06', '2026-09-06', 'test')['gross']);
        $data = ['environment' => 'live', 'provider' => 'inicis', 'batch_ref' => 'test-batch', 'sales_from' => '2026-09-06', 'sales_to' => '2026-09-06',
            'deposit_date' => '2026-09-06', 'gross' => 23000, 'refunds' => 13000, 'fees' => 300, 'adjustment' => -100, 'deposited' => 9500, 'request_key' => Store::id()];
        $id = $this->shop->settlement->savePayout($data, 'admin');
        self::assertSame($id, $this->shop->settlement->savePayout($data, 'admin'));
        $report = $this->shop->settlement->report('2026-09-06', '2026-09-06');
        self::assertSame(-100, $report['payouts'][0]['deposit_difference']);
        self::assertSame(0, $report['payouts'][0]['sales_difference']); self::assertSame(0, $report['payouts'][0]['refund_difference']);
        $csv = \GnuCms\Modules\Shop\Settlement::csv($report);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv); self::assertStringNotContainsString('buyer@example.test', $csv);
        $this->rejected(fn () => $this->shop->settlement->report('2026-09-07', '2026-09-06'));
        $this->rejected(fn () => $this->shop->settlement->report('2026-02-30', '2026-03-01'));
    }

    #[DataProvider('connectionProvider')]
    public function testReturnCostDeductionAndManualRefundAttachmentPreserveCounts(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p); $this->ship($order);
        $claim = $this->claim($order, 'return'); $this->receive($claim);
        $this->gateway->timeout = true;
        $this->rejected(fn () => $this->shop->claims->refund($order['id'], ['claim_id' => $claim, 'request_key' => Store::id(), 'deduction' => 2000], 'admin'));
        $refund = $this->app->db()->selectOne('SELECT * FROM ' . $this->app->db()->table('shop_refunds') . ' WHERE order_id = ?', [$order['id']]);
        self::assertSame(8000, (int) $refund['amount']);
        $this->gateway->timeout = false;
        $this->gateway->cancel($order, 8000, 13000, '결제사 관리자 수동 반품', Store::id());
        $this->shop->sync($order['id']);
        self::assertSame('pending', $this->shop->store->get('shop_refunds', $refund['id'])['status']);
        $reference = $this->gateway->payments[$order['id']]['cancellations'][0]['id'];
        $this->shop->claims->attachExternalRefund($refund['id'], $reference, '관리자에서 금액·신청 일치 확인', 'admin');
        self::assertSame(1, (int) $this->shop->store->items($order['id'])[0]['returned']);
        self::assertSame(5, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        $this->rejected(fn () => $this->shop->claims->attachExternalRefund($refund['id'], $reference, '중복', 'admin'));
        $this->shop->acknowledgeReview($order['id'], '상품 수량과 취소 금액 확인', 'admin');
        self::assertSame(0, (int) $this->shop->store->get('shop_orders', $order['id'])['needs_review']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefundRetryUsesSameKeyAndCannotAutomaticallyRestartAfterWindow(array $config): void
    {
        $this->setupShop($config); Clock::freeze('2026-09-06 01:00:00');
        $p = $this->product(); $order = $this->order($p); $this->gateway->paid($order); $this->shop->sync($order['id']); $this->gateway->timeout = true;
        $this->rejected(fn () => $this->shop->claims->refund($order['id'], ['request_key' => Store::id()], 'admin'));
        $refund = $this->app->db()->selectOne('SELECT * FROM ' . $this->app->db()->table('shop_refunds') . ' WHERE order_id = ?', [$order['id']]);
        $this->rejected(fn () => $this->shop->claims->submitRefund($refund['id']));
        self::assertSame($this->gateway->calls[0], $this->gateway->calls[1]);
        Clock::freeze('2026-09-06 03:00:01');
        $this->rejected(fn () => $this->shop->claims->submitRefund($refund['id'])); self::assertCount(2, $this->gateway->calls);
        $this->shop->claims->closeUnprocessedRefund($refund['id'], '결제사 담당자가 미처리를 확인함', 'admin');
        self::assertSame('failed', $this->shop->store->get('shop_refunds', $refund['id'])['status']);
        self::assertSame(4, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
    }

    #[DataProvider('connectionProvider')]
    public function testShippingRechecksProviderAndPartialReturnStillAllowsDeliveryCompletion(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p);
        $this->gateway->paid($order); $this->shop->sync($order['id']);
        $this->gateway->cancel($order, 13000, 13000, '결제사에서 취소', Store::id());
        $this->rejected(fn () => $this->shop->fulfill($order['id'], 'ship', ['carrier' => '택배', 'tracking' => '1'], 'admin'));
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $order['id'])['status']);
        $second = $this->order($p, 2); $this->ship($second);
        $claim = $this->claim($second, 'return'); $this->receive($claim);
        $this->shop->claims->refund($second['id'], ['claim_id' => $claim, 'request_key' => Store::id()], 'admin');
        $this->shop->fulfill($second['id'], 'deliver', [], 'admin');
        self::assertSame('delivered', $this->shop->store->get('shop_orders', $second['id'])['status']);
        $exchange = $this->claim($second, 'exchange', 1, $p['variants'][0]['id']); $this->receive($exchange);
        $this->gateway->payments[$second['id']]['open_cancellations'] = 1;
        $this->shop->sync($second['id']);
        $this->rejected(fn () => $this->shop->acknowledgeReview($second['id'], '미확정 취소 확인 중', 'admin'));
        $this->gateway->payments[$second['id']]['open_cancellations'] = 0;
        $this->shop->acknowledgeReview($second['id'], '취소 미진행 확인, 검수한 교환 처리 계속', 'admin');
        $this->shop->claims->handle($exchange, 'exchange', ['carrier' => '택배', 'tracking' => '2'], 'admin');
        self::assertSame('completed', $this->shop->store->get('shop_claims', $exchange)['status']);
        $third = $this->order($p); $this->ship($third);
        $exchange = $this->claim($third, 'exchange', 1, $p['variants'][0]['id']); $this->receive($exchange);
        $this->gateway->cancel($third, 13000, 13000, '결제사에서 전액 취소', Store::id());
        $this->shop->sync($third['id']);
        $this->shop->acknowledgeReview($third['id'], '결제사 전액 취소 확인', 'admin');
        $this->rejected(fn () => $this->shop->claims->handle($exchange, 'exchange', ['carrier' => '택배', 'tracking' => '3'], 'admin'));
        $this->shop->claims->handle($exchange, 'reject', ['note' => '외부 전액 환불 확인, 회수품은 재고 화면에서 별도 대조'], 'admin');
        self::assertFalse($this->shop->claims->hasOpen($third['id']));
        $fourth = $this->order($p); $this->ship($fourth);
        $exchange = $this->claim($fourth, 'exchange', 1, $p['variants'][0]['id']); $this->receive($exchange);
        $this->shop->claims->handle($exchange, 'convert-return', ['note' => '고객이 교환 대신 환불 요청'], 'admin');
        $this->shop->claims->refund($fourth['id'], ['claim_id' => $exchange, 'request_key' => Store::id(), 'include_shipping' => '1'], 'admin');
        self::assertSame('refunded', $this->shop->store->get('shop_orders', $fourth['id'])['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testCostInputsValidateAndOrderSnapshotsStayPrivateAndUnchanged(array $config): void
    {
        $this->setupShop($config);
        $input = ['name' => '원가 상품', 'price' => '10000', 'cost_price' => '4500', 'stock' => '5', 'active' => '1',
            'option1_name' => '색상', 'option1_values' => '검정,흰색', 'variants' => [
                ['option1' => '검정', 'price' => '10000', 'stock' => '5', 'cost_price' => '6000'],
                ['option1' => '흰색', 'price' => '10000', 'stock' => '5', 'cost_price' => '0']]];
        $id = $this->shop->catalog->save($input); $p = $this->shop->catalog->product($id, true);
        self::assertSame([6000, 0], array_map('intval', array_column($p['variants'], 'cost_price')));
        foreach (['-1', '1.5', '100000001', 'abc', ['2']] as $bad) {
            $this->rejected(fn () => $this->shop->catalog->save(array_replace($input, ['cost_price' => $bad])));
            $rows = $input['variants']; $rows[1]['cost_price'] = $bad;
            $this->rejected(fn () => $this->shop->catalog->save(array_replace($input, ['variants' => $rows])));
        }
        self::assertCount(1, $this->shop->catalog->listing(true));
        $order = $this->order($p);
        $v = $this->shop->store->get('shop_variants', $p['variants'][0]['id']);
        $this->shop->catalog->saveVariant(['variant_id' => $v['id'], 'version' => $v['version'], 'price' => '10000', 'stock' => $v['stock'], 'cost_price' => '8000']);
        self::assertSame(6000, (int) $this->shop->store->items($order['id'])[0]['cost_price']);
        self::assertArrayNotHasKey('cost_price', $this->shop->detail($order['id'], 'member')['items'][0]);
        self::assertArrayNotHasKey('cost_price', $this->shop->catalog->product($id)['variants'][0]);
        self::assertArrayNotHasKey('cost_price', $this->shop->quote([$v['id'] => 1])['items'][0]);
        self::assertSame(6000, (int) $this->shop->detail($order['id'], null, true)['items'][0]['cost_price']);
        $v = $this->shop->store->get('shop_variants', $v['id']);
        $this->shop->catalog->saveVariant(['variant_id' => $v['id'], 'version' => $v['version'], 'price' => '11000', 'stock' => $v['stock']]);
        $v = $this->shop->store->get('shop_variants', $v['id']);
        self::assertSame(8000, (int) $v['cost_price'], 'older forms preserve stored costs');
        $this->shop->catalog->saveVariant(['variant_id' => $v['id'], 'version' => $v['version'], 'price' => '11000', 'stock' => $v['stock'], 'cost_price' => '']);
        self::assertNull($this->shop->store->get('shop_variants', $v['id'])['cost_price']);
    }

    #[DataProvider('connectionProvider')]
    public function testProfitUsesPaymentPeriodAndHandlesReturnExchangeAndDamagedGoods(array $config): void
    {
        $this->setupShop($config); Clock::freeze('2026-09-05 01:00:00');
        $id = $this->shop->catalog->save(['name' => '손익 상품', 'price' => '10000', 'stock' => '5', 'active' => '1',
            'option1_name' => '색상', 'option1_values' => '검정,흰색', 'variants' => [
                ['option1' => '검정', 'price' => '10000', 'stock' => '5', 'cost_price' => '4000'],
                ['option1' => '흰색', 'price' => '10000', 'stock' => '5', 'cost_price' => '7000']]]);
        $p = $this->shop->catalog->product($id, true); $order = $this->order($p, 2); $this->ship($order);
        $profit = fn () => $this->shop->settlement->report('2026-09-05', '2026-09-05', 'live', 'inicis')['profit'];
        self::assertSame(12000, $profit()['gross_profit']);
        self::assertSame(60.0, $profit()['margin_rate']);
        $this->shop->store->update('shop_variants', $p['variants'][0]['id'], ['cost_price' => 9000]);
        self::assertSame(8000, $profit()['cost_amount']);
        Clock::freeze('2026-09-06 01:00:00');
        $later = $this->order($p); $this->gateway->paid($later); $this->shop->sync($later['id']);
        self::assertSame(20000, $profit()['product_amount']);
        Clock::freeze('2026-09-07 01:00:00');
        $claim = $this->claim($order, 'return'); $this->receive($claim);
        $this->shop->claims->refund($order['id'], ['claim_id' => $claim, 'request_key' => Store::id(), 'deduction' => 1000], 'admin');
        self::assertSame(11000, $profit()['net_product_amount']);
        self::assertSame(4000, $profit()['cost_amount']);
        self::assertSame(7000, $profit()['gross_profit']);
        $exchange = $this->claim($order, 'exchange', 1, $p['variants'][1]['id']); $this->receive($exchange);
        $this->shop->claims->handle($exchange, 'exchange', ['carrier' => '택배', 'tracking' => '2'], 'admin');
        self::assertSame(7000, $profit()['cost_amount']);
        self::assertSame(4000, $profit()['gross_profit']);
        self::assertSame(2, $profit()['products'][0]['sold_quantity'], 'exchange is not a second sale');
        $replacement = array_values(array_filter($this->shop->store->items($order['id']), static fn ($i) => $i['exchange_claim_id'] !== ''))[0];
        self::assertSame(7000, (int) $replacement['cost_price']);
        $this->shop->store->update('shop_variants', $p['variants'][1]['id'], ['cost_price' => 9500]);
        $return = $this->shop->claims->request($order['id'], ['kind' => 'return', 'item_id' => $replacement['id'], 'quantity' => '1', 'reason' => '불량 반품', 'request_key' => Store::id()], 'member');
        $this->receive($return, false);
        $this->shop->claims->refund($order['id'], ['claim_id' => $return, 'request_key' => Store::id()], 'admin');
        self::assertSame(7000, $profit()['cost_amount'], 'damaged goods remain a cost');
        self::assertSame(-6000, $profit()['gross_profit']);
        $this->shop->claims->refund($later['id'], ['request_key' => Store::id()], 'admin');
        $cancelled = $this->shop->settlement->report('2026-09-06', '2026-09-06')['profit'];
        self::assertSame(0, $cancelled['net_product_amount']); self::assertSame(0, $cancelled['cost_amount']);
        self::assertSame(0, $cancelled['gross_profit']); self::assertNull($cancelled['margin_rate']);
        self::assertSame([], $this->shop->settlement->report('2026-09-05', '2026-09-05', 'test')['profit']['products']);
        self::assertSame([], $this->shop->settlement->report('2026-09-05', '2026-09-05', 'live', 'toss')['profit']['products']);
    }

    #[DataProvider('connectionProvider')]
    public function testMissingCostsAndUnallocatedRefundsNeverProduceMisleadingProfit(array $config): void
    {
        $this->setupShop($config); Clock::freeze('2026-09-05 01:00:00');
        $p = $this->product(); $order = $this->order($p); $this->ship($order);
        $profit = fn () => $this->shop->settlement->report('2026-09-05', '2026-09-05')['profit'];
        self::assertSame(1, $profit()['missing_cost_quantity']); self::assertNull($profit()['cost_amount']); self::assertNull($profit()['gross_profit']);
        $this->shop->store->update('shop_variants', $p['variants'][0]['id'], ['cost_price' => 0]);
        $free = $this->order($p); $this->ship($free);
        self::assertSame(0, (int) $this->shop->store->items($free['id'])[0]['cost_price']);
        self::assertSame(1, $profit()['missing_cost_quantity']);
        $this->gateway->cancel($order, 3000, 13000, '외부 부분 환불', Store::id()); $this->shop->sync($order['id']);
        self::assertSame(1, $profit()['unallocated_refund_orders']); self::assertNull($profit()['net_product_amount']);
        $this->gateway->cancel($order, 10000, 10000, '외부 잔액 환불', Store::id()); $this->shop->sync($order['id']);
        self::assertSame(0, $profit()['unallocated_refund_orders']); self::assertSame(10000, $profit()['net_product_amount']);
        self::assertNull($profit()['gross_profit'], 'unrecovered legacy goods still have unknown cost');
    }

    #[DataProvider('connectionProvider')]
    public function testCostMigrationKeepsLegacyDataAndResumesPartialUpgrade(array $config): void
    {
        $this->setupShop($config); $p = $this->product(); $order = $this->order($p);
        $db = $this->app->db();
        foreach (['shop_variants', 'shop_items'] as $table) $db->execute('ALTER TABLE ' . $db->table($table) . ' DROP COLUMN cost_price');
        $db->update('extension_schemas', ['schema_version' => 3], 'package_key = :key', ['key' => Schema::KEY]);
        self::assertTrue($this->shop->ready()); self::assertTrue($this->shop->images->ready()); self::assertFalse($this->shop->costing->ready());
        $this->rejected(fn () => $this->shop->catalog->save(['name' => '갱신 전 원가', 'cost_price' => '1']));
        $this->shop->install();
        self::assertNull($this->shop->store->get('shop_variants', $p['variants'][0]['id'])['cost_price']);
        self::assertNull($this->shop->store->items($order['id'])[0]['cost_price']);
        self::assertSame(4, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        $this->shop->store->update('shop_variants', $p['variants'][0]['id'], ['cost_price' => 4500]);
        $db->execute('ALTER TABLE ' . $db->table('shop_items') . ' DROP COLUMN cost_price');
        $db->update('extension_schemas', ['schema_version' => 3, 'state' => 'failed'], 'package_key = :key', ['key' => Schema::KEY]);
        $this->shop->install(); $this->shop->install();
        self::assertTrue($this->shop->costing->ready());
        self::assertSame(4500, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['cost_price']);
        self::assertNull($this->shop->store->items($order['id'])[0]['cost_price']);
    }

    public function testBackupRestoresOrderImageAndPaymentConfigurationButRevokesExecution(): void
    {
        $this->setupShop(['dsn' => 'sqlite::memory:']); $p = $this->product(); $order = $this->order($p);
        $this->gateway->paid($order); $this->shop->sync($order['id']);
        $settings = new \GnuCms\Payment\Settings($this->app, 'inicis'); $settings->install();
        $settings->save('live', \GnuCms\Tests\Payment\Fixtures::config('inicis'));
        $settings->enable('live', true);
        $revision = $settings->summary('live')['revision'];
        (new \GnuCms\Payment\Journal($settings))->change($order['id'], static fn () => ['approval' => 'pending', 'refunds' => []]);
        $state = new \GnuCms\Extension\StateStore($this->root . '/extensions');
        $state->update(static fn () => ['modules/shop', 'plugins/payment-inicis']);
        $source = $this->root . '/source.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=');
        file_put_contents($source, $png);
        $file = static fn () => new \Slim\Psr7\UploadedFile($source, 'input.png', 'image/png', strlen($png));
        $this->shop->images->append($p['id'], [$file(), $file()], (int) $p['version']);
        $productImages = $this->shop->catalog->product($p['id']);
        $this->shop->images->reorder($p['id'], array_reverse(array_column($productImages['images'], 'id')), (int) $productImages['version']);
        $imageSnapshot = $this->shop->catalog->product($p['id']);
        $image = $imageSnapshot['image'];
        $saved = $this->app->backups()->create('shop-test', 'tar');
        $this->shop->claims->refund($order['id'], ['request_key' => Store::id()], 'admin');
        unlink($this->root . '/uploads/shop/' . $image);
        $state->update(static fn () => []);
        $this->app->backups()->restore($saved['name']);
        $this->app = new App(['db' => $this->app->config('db'), 'storage' => ['dir' => $this->root], 'auth' => ['secret' => $this->app->config('auth.secret')]]);
        $restored = new Service($this->app);
        self::assertSame('paid', $restored->detail($order['id'], 'member')['status']);
        self::assertSame('buyer@example.test', $restored->detail($order['id'], 'member')['customer_data']['email']);
        self::assertSame(4, (int) $restored->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        self::assertSame($png, file_get_contents($this->root . '/uploads/shop/' . $image));
        self::assertSame($imageSnapshot['images'], $restored->catalog->product($p['id'])['images']);
        self::assertSame($image, $restored->catalog->product($p['id'])['image']);
        self::assertSame(['modules/shop', 'plugins/payment-inicis'], $state->read());
        $settings = new \GnuCms\Payment\Settings($this->app, 'inicis');
        self::assertSame($revision, $settings->summary('live')['revision']);
        self::assertSame('pending', (new \GnuCms\Payment\Journal($settings))->read($order['id'])['approval']);
        self::assertFalse($settings->available('live'));
    }

    public function testMaintenanceCliExpiresOrdersAndHonorsRestoreLockAndDisabledModule(): void
    {
        $this->setupShop(['dsn' => 'sqlite::memory:']); $p = $this->product(1); $order = $this->order($p);
        $this->shop->store->update('shop_orders', $order['id'], ['expires_at' => time() - 1]);
        $state = new \GnuCms\Extension\StateStore($this->root . '/extensions');
        $state->update(static fn () => ['modules/shop']);
        $file = $this->root . '/config.php';
        file_put_contents($file, '<?php return ' . var_export(['db' => $this->app->config('db'), 'storage' => ['dir' => $this->root],
            'auth' => ['secret' => $this->app->config('auth.secret')]], true) . ';');
        chmod($file, 0600);
        $run = static function (string $action) use ($file): array {
            $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/modules/shop/bin/maintenance.php', $action, '--config=' . $file],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); return [proc_close($process), $out, $err];
        };
        $lock = fopen($this->root . '/upgrade.lock', 'c'); flock($lock, LOCK_EX);
        try { self::assertSame(1, $run('expire')[0]); } finally { flock($lock, LOCK_UN); fclose($lock); }
        self::assertSame([0, "1건을 만료 처리했습니다.\n", ''], $run('expire'));
        self::assertSame([0, "0건 조회, 0건 재확인 필요\n", ''], $run('reconcile'));
        self::assertSame(1, (int) $this->shop->store->get('shop_variants', $p['variants'][0]['id'])['stock']);
        $state->update(static fn () => []);
        self::assertSame(1, $run('expire')[0]);
    }
}
