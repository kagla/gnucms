<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\App;
use GnuCms\Auth\Identity;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Payment\{Journal, Settings, StreamTransport, TossGateway};
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class TossGatewayTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private Settings $settings;
    private FakeTransport $http;
    private TossGateway $gateway;
    private array $config;
    private array $order;
    private bool $shopInstalled = false;

    private function setupGateway(array $db): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-toss-' . bin2hex(random_bytes(10));
        $db['prefix'] = 'ts' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $db, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        $this->app->setIdentity(Identity::user('buyer', '테스트', true));
        $this->settings = new Settings($this->app, 'toss'); $this->settings->install(); $this->settings->install();
        $this->config = Fixtures::config('toss'); $this->settings->save('test', $this->config); $this->settings->enable('test', true);
        $this->http = new FakeTransport(); $this->gateway = new TossGateway($this->settings, $this->http);
        $this->order = ['id' => bin2hex(random_bytes(16)), 'provider' => 'toss', 'environment' => 'test', 'config_revision' => $this->settings->summary('test')['revision'],
            'total' => 13000, 'order_name' => str_repeat('상품', 60), 'user_id' => 'buyer', 'created_at' => time()];
    }

    private function checkout(string $device = 'web'): array
    {
        return $this->gateway->checkout($this->order, ['name' => '구매자'], 'https://shop.example.test/cms/modules/shop/return?id=' . $this->order['id'],
            'https://shop.example.test/cms/modules/shop/callback?id=' . $this->order['id'] . '&state=' . bin2hex(random_bytes(32)), $device);
    }

    private function payment(): array
    {
        return ['paymentKey' => bin2hex(random_bytes(100)), 'orderId' => $this->order['id'], 'mId' => $this->config['merchant_id'], 'totalAmount' => 13000,
            'balanceAmount' => 13000, 'currency' => 'KRW', 'type' => 'NORMAL', 'method' => '카드', 'useEscrow' => false, 'taxFreeAmount' => 0,
            'status' => 'DONE', 'approvedAt' => '2026-09-06T12:00:00+09:00', 'cancels' => null, 'isPartialCancelable' => true];
    }

    private function authCallback(array $payment): array { return ['paymentKey' => $payment['paymentKey'], 'orderId' => $this->order['id'], 'amount' => '13000']; }
    private function response(array $body, int $status = 200): void { $this->http->responses[] = compact('status', 'body'); }
    private function rejected(callable $work): void
    {
        try { $work(); self::fail('거절되어야 합니다.'); } catch (DomainError $e) { self::assertGreaterThanOrEqual(400, $e->status()); }
    }

    private function approve(): array
    {
        $this->checkout(); $payment = $this->payment(); $this->response($payment);
        $this->gateway->complete($this->order, $this->authCallback($payment));
        return $payment;
    }

    private function cancelResult(array $payment, int $amount): array
    {
        $payment['balanceAmount'] -= $amount;
        $payment['status'] = $payment['balanceAmount'] === 0 ? 'CANCELED' : 'PARTIAL_CANCELED';
        $payment['lastTransactionKey'] = bin2hex(random_bytes(32));
        $payment['cancels'][] = ['transactionKey' => $payment['lastTransactionKey'], 'cancelAmount' => $amount,
            'canceledAt' => '2026-09-06T13:00:00+09:00', 'cancelStatus' => 'DONE', 'cancelReason' => '외부 문구는 환불 신청 연결에 신뢰하지 않음'];
        return $payment;
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            if ($this->shopInstalled) foreach (array_reverse(\GnuCms\Modules\Shop\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            foreach (['transactions', 'settings'] as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_toss_' . $table));
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testEnvironmentKeysEncryptionRotationAndPermit(array $db): void
    {
        $this->setupGateway($db);
        self::assertTrue($this->gateway->available('test')); self::assertFalse($this->gateway->available('live'));
        $raw = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('pay_toss_settings') . " WHERE id = 'test'")['payload'];
        self::assertStringNotContainsString($this->config['secret_key'], $raw);
        self::assertArrayNotHasKey('secret_key', $this->settings->summary('test'));
        $this->rejected(fn () => $this->settings->save('live', $this->config));
        $this->rejected(fn () => $this->settings->save('test', array_replace($this->config, ['client_key' => str_replace('_ck_', '_gck_', $this->config['client_key'])])));
        $this->rejected(fn () => $this->settings->save('test', array_replace($this->config, ['secret_key' => str_replace('test_', 'live_', $this->config['secret_key'])])));
        $this->rejected(fn () => $this->settings->save('test', ['merchant_id' => 'another']));
        $next = 'test_sk_' . bin2hex(random_bytes(24));
        $this->settings->save('test', ['merchant_id' => $this->config['merchant_id'], 'secret_key' => $next]);
        self::assertSame($next, $this->settings->credentials($this->order['config_revision'])['secret_key']);
        self::assertSame($this->config['client_key'], $this->settings->current('test')['client_key']);
        self::assertFalse($this->gateway->available('test'));
        $this->rejected(fn () => $this->checkout());
        $this->settings->save('live', array_map(static fn ($value) => str_replace('test_', 'live_', $value), $this->config));
        $this->settings->enable('live', true); self::assertTrue($this->gateway->available('live'));
        self::assertSame([], $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testCheckoutUsesStoredOrderAndPublicKeyOnBothDevices(array $db): void
    {
        $this->setupGateway($db);
        foreach (['web', 'mobile'] as $device) {
            $payment = $this->checkout($device);
            self::assertSame('toss', $payment['kind']);
            self::assertSame(['currency' => 'KRW', 'value' => 13000], $payment['request']['amount']);
            self::assertSame($this->order['id'], $payment['request']['orderId']);
            self::assertSame(100, mb_strlen($payment['request']['orderName']));
            self::assertStringStartsWith('https://shop.example.test/cms/modules/shop/toss-return?', $payment['request']['successUrl']);
            self::assertSame($this->config['client_key'], $payment['client_key']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{50}$/D', $payment['customer_key']);
            self::assertStringNotContainsString($this->config['secret_key'], json_encode($payment));
        }
        self::assertSame([], $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testTamperedCallbacksCannotSendApproval(array $db): void
    {
        $this->setupGateway($db); $this->checkout(); $callback = $this->authCallback($this->payment());
        foreach ([['amount' => '1'], ['amount' => ['13000']], ['orderId' => bin2hex(random_bytes(16))], ['paymentKey' => '../confirm'], ['paymentKey' => str_repeat('a', 201)]] as $bad) {
            $this->rejected(fn () => $this->gateway->complete($this->order, array_replace($callback, $bad)));
        }
        self::assertSame('ready', (new Journal($this->settings))->read($this->order['id'])['approval']);
        self::assertSame([], $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testApprovalUsesBasicAuthIsIdempotentAndQueryVerifiesPayment(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve();
        $this->gateway->complete($this->order, $this->authCallback($payment));
        self::assertCount(1, $this->http->calls);
        self::assertSame('Basic ' . base64_encode($this->config['secret_key'] . ':'), $this->http->calls[0]['headers']['Authorization']);
        self::assertSame('approve-' . $this->order['id'], $this->http->calls[0]['headers']['Idempotency-Key']);
        self::assertSame(13000, $this->http->calls[0]['body']['amount']);
        $this->response($payment); $found = $this->gateway->fetch($this->order);
        self::assertTrue($found['valid']); self::assertSame('PAID', $found['status']); self::assertSame($payment['paymentKey'], $found['transaction_id']);
        self::assertSame('GET', $this->http->calls[1]['method']); self::assertNull($this->http->calls[1]['body']);
        self::assertStringEndsWith('/orders/' . $this->order['id'], $this->http->calls[1]['url']);
        self::assertSame(['tid' => $payment['paymentKey']], (new Journal($this->settings))->read($this->order['id'])['approved']);
    }

    #[DataProvider('connectionProvider')]
    public function testLostApprovalResponseRecoversByOrderWithoutReapproval(array $db): void
    {
        $this->setupGateway($db); $this->checkout(); $payment = $this->payment();
        $this->http->responses[] = DomainError::serviceUnavailable('통신 중단');
        $this->rejected(fn () => $this->gateway->complete($this->order, $this->authCallback($payment)));
        $this->gateway->complete($this->order, $this->authCallback($payment)); self::assertCount(1, $this->http->calls);
        $this->rejected(fn () => $this->checkout());
        $this->response($payment); self::assertTrue($this->gateway->fetch($this->order)['valid']);
    }

    #[DataProvider('connectionProvider')]
    public function testMismatchedApprovalStaysPending(array $db): void
    {
        $this->setupGateway($db); $this->checkout(); $payment = $this->payment();
        $this->response(array_replace($payment, ['mId' => 'another']));
        $this->rejected(fn () => $this->gateway->complete($this->order, $this->authCallback($payment)));
        self::assertSame('pending', (new Journal($this->settings))->read($this->order['id'])['approval']);
        $this->gateway->complete($this->order, $this->authCallback($payment)); self::assertCount(1, $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testQueryRejectsChangedIdentityTotalsCurrencyMethodAndCancellationRecords(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve();
        foreach ([['mId' => 'another'], ['orderId' => 'another'], ['paymentKey' => 'another'], ['currency' => 'USD'], ['method' => '가상계좌'],
            ['type' => 'BILLING'], ['totalAmount' => 14000], ['balanceAmount' => 12000], ['approvedAt' => '2026-02-30T12:00:00+09:00'], ['taxFreeAmount' => 1000], ['useEscrow' => true]] as $bad) {
            $this->response(array_replace($payment, $bad)); self::assertFalse($this->gateway->fetch($this->order)['valid'], json_encode($bad));
        }
        $partial = $this->cancelResult($payment, 3000);
        foreach ([array_replace($partial, ['balanceAmount' => 9000]), array_replace($partial, ['cancels' => [$partial['cancels'][0], $partial['cancels'][0]]]),
            array_replace($partial, ['cancels' => [array_replace($partial['cancels'][0], ['cancelStatus' => 'IN_PROGRESS'])]])] as $bad) {
            $this->response($bad); self::assertFalse($this->gateway->fetch($this->order)['valid']);
        }
        $this->response(array_replace($payment, ['method' => '간편결제'])); self::assertTrue($this->gateway->fetch($this->order)['valid']);
        $this->response(['code' => 'NOT_FOUND_PAYMENT'], 404); self::assertSame('NOT_FOUND', $this->gateway->fetch($this->order)['status']);
        $this->response(['code' => 'UNAUTHORIZED_KEY'], 401); $this->rejected(fn () => $this->gateway->fetch($this->order));
    }

    #[DataProvider('connectionProvider')]
    public function testPartialThenFullRefundVerifiesBalanceAndLinksOnlyNewCancel(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve(); $partial = $this->cancelResult($payment, 3000);
        $key = 'refund-' . bin2hex(random_bytes(16));
        $this->response($payment); $this->response($partial);
        $result = $this->gateway->cancel($this->order, 3000, 13000, '부분 환불', $key);
        self::assertSame($partial['lastTransactionKey'], $result['id']);
        self::assertSame($key, $this->http->calls[2]['headers']['Idempotency-Key']);
        self::assertSame(13000, $this->http->calls[2]['body']['refundableAmount']);
        self::assertSame(3000, $this->http->calls[2]['body']['cancelAmount']);
        self::assertSame($result, $this->gateway->cancel($this->order, 3000, 13000, '부분 환불', $key)); self::assertCount(3, $this->http->calls);
        $full = $this->cancelResult($partial, 10000); $full['cancels'] = array_reverse($full['cancels']);
        $this->response($partial); $this->response($full);
        $result = $this->gateway->cancel($this->order, 10000, 10000, '나머지 환불', 'refund-' . bin2hex(random_bytes(16)));
        self::assertSame($full['lastTransactionKey'], $result['id']);
        $this->response($full); $found = $this->gateway->fetch($this->order);
        self::assertTrue($found['valid']); self::assertSame('CANCELLED', $found['status']); self::assertSame(13000, $found['cancelled']);
        self::assertSame(['나머지 환불', '부분 환불'], array_column($found['cancellations'], 'reason'));
    }

    #[DataProvider('connectionProvider')]
    public function testUncertainRefundDoesNotResendOrTrustExternalReason(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve(); $key = 'refund-' . bin2hex(random_bytes(16));
        $this->response($payment); $this->http->responses[] = DomainError::serviceUnavailable('통신 중단');
        $this->rejected(fn () => $this->gateway->cancel($this->order, 3000, 13000, '환불', $key));
        $this->rejected(fn () => $this->gateway->cancel($this->order, 3000, 13000, '환불', $key));
        self::assertCount(3, $this->http->calls);
        $this->response($this->cancelResult($payment, 3000)); $found = $this->gateway->fetch($this->order);
        self::assertSame(1, $found['open_cancellations']); self::assertSame('', $found['cancellations'][0]['reason']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefundRejectsStaleBalanceUnsupportedPartialAndBadResponse(array $db): void
    {
        $this->setupGateway($db);
        foreach (['balance', 'partial', 'response'] as $case) {
            $this->order['id'] = bin2hex(random_bytes(16)); $payment = $this->approve();
            $this->response($case === 'balance' ? $this->cancelResult($payment, 1000) : array_replace($payment, ['isPartialCancelable' => $case !== 'partial']));
            if ($case === 'response') $this->response($this->cancelResult($payment, 4000));
            $count = count($this->http->calls);
            $this->rejected(fn () => $this->gateway->cancel($this->order, 3000, 13000, '환불', 'refund-' . bin2hex(random_bytes(16))));
            self::assertCount($count + ($case === 'response' ? 2 : 1), $this->http->calls);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testShopUpgradePreservesOrdersAndReconcilesLongPaymentKeyAndStock(array $db): void
    {
        $this->setupGateway($db);
        $shop = new \GnuCms\Modules\Shop\Service($this->app, ['toss' => $this->gateway]); $shop->install(); $this->shopInstalled = true;
        $shop->saveSettings(['name' => '상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'test', 'open' => '1']);
        $id = $shop->catalog->save(['name' => '상품', 'active' => '1', 'price' => 10000, 'stock' => 2]);
        $p = $shop->catalog->product($id); $variant = $p['variants'][0]['id'];
        $this->order = $shop->createOrder('buyer', [$variant => 1], ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'toss', bin2hex(random_bytes(16)), 13000);
        $connection = $this->app->db();
        if ($connection->dialect()->name() === 'mysql') {
            $connection->execute('ALTER TABLE ' . $connection->table('shop_orders') . ' MODIFY transaction_id VARCHAR(100) NULL');
            $connection->execute('ALTER TABLE ' . $connection->table('shop_money') . ' MODIFY reference VARCHAR(100) NOT NULL');
        }
        $connection->update('extension_schemas', ['schema_version' => 1], 'package_key = :key', ['key' => 'modules/shop']);
        self::assertFalse($shop->ready()); $shop->install(); $shop->install(); self::assertTrue($shop->ready());
        self::assertSame($this->order['id'], $shop->store->get('shop_orders', $this->order['id'])['id']);
        $payment = $this->approve(); $this->response($payment); $shop->sync($this->order['id']);
        $stored = $shop->store->get('shop_orders', $this->order['id']);
        self::assertSame('paid', $stored['status']); self::assertSame($payment['paymentKey'], $stored['transaction_id']);
        self::assertSame($payment['paymentKey'], $connection->selectOne('SELECT reference FROM ' . $connection->table('shop_money'))['reference']);
        $full = $this->cancelResult($payment, 13000); $this->response($full); $shop->sync($this->order['id']);
        self::assertSame('refunded', $shop->store->get('shop_orders', $this->order['id'])['status']);
        self::assertSame(2, (int) $shop->store->get('shop_variants', $variant)['stock']);
        self::assertSame(0, (int) $connection->selectOne('SELECT SUM(amount) AS total FROM ' . $connection->table('shop_money'))['total']);
    }

    public function testTransportLimitsTossMethodsAndPaths(): void
    {
        $base = 'https://api.tosspayments.com/v1/payments/';
        self::assertTrue(StreamTransport::allowed($base . 'confirm'));
        self::assertTrue(StreamTransport::allowed($base . str_repeat('a', 200) . '/cancel'));
        self::assertTrue(StreamTransport::allowed($base . 'orders/' . str_repeat('a', 32), 'GET'));
        foreach ([$base . 'confirm?x=1', $base . '../confirm', $base . str_repeat('a', 201) . '/cancel', 'https://api.tosspayments.com.evil.test/v1/payments/confirm', 'http://api.tosspayments.com/v1/payments/confirm'] as $url) self::assertFalse(StreamTransport::allowed($url));
        self::assertFalse(StreamTransport::allowed($base . 'confirm', 'GET'));
        self::assertFalse(StreamTransport::allowed($base . 'orders/' . str_repeat('a', 32)));
        self::assertFalse(StreamTransport::allowed('https://iniapi.inicis.com/v2/pg/refund', 'GET'));
    }
}
