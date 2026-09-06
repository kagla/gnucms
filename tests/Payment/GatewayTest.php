<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Extension\RuntimePermit;
use GnuCms\Payment\{InicisGateway, KcpGateway, KspayGateway, Journal, Settings, StreamTransport};
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';

final class GatewayTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private Settings $settings;
    private FakeTransport $http;
    private InicisGateway $gateway;
    private array $config;
    private array $order;
    private bool $shopInstalled = false;

    private function setupGateway(array $db): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-payment-' . bin2hex(random_bytes(10));
        $db['prefix'] = 'pg' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $db, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        $this->settings = new Settings($this->app, 'inicis'); $this->settings->install(); $this->settings->install();
        $this->config = Fixtures::config('inicis'); $this->settings->save('test', $this->config); $this->settings->enable('test', true);
        $this->http = new FakeTransport(); $this->gateway = new InicisGateway($this->settings, $this->http);
        $this->order = ['id' => bin2hex(random_bytes(16)), 'provider' => 'inicis', 'environment' => 'test', 'config_revision' => $this->settings->summary('test')['revision'],
            'total' => 13000, 'order_name' => '상품', 'created_at' => time()];
    }

    private function checkout(string $device = 'web'): array
    {
        return $this->gateway->checkout($this->order, ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test'],
            'https://shop.example.test/cms/modules/shop/return?id=' . $this->order['id'], 'https://shop.example.test/cms/modules/shop/callback?id=' . $this->order['id'], $device);
    }

    private function authResponse(): array
    {
        return ['resultCode' => '0000', 'mid' => $this->config['merchant_id'], 'orderNumber' => $this->order['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
    }

    private function response(array $data): void { $this->http->responses[] = ['status' => 200, 'body' => $data]; }
    private function rejected(callable $work): void
    {
        try { $work(); self::fail('거절되어야 합니다.'); } catch (DomainError $e) { self::assertGreaterThanOrEqual(400, $e->status()); }
    }

    private function approve(): array
    {
        $this->checkout();
        $tid = bin2hex(random_bytes(20));
        $this->response(['resultCode' => '0000', 'mid' => $this->config['merchant_id'], 'MOID' => $this->order['id'], 'TotPrice' => '13000', 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']);
        $this->gateway->complete($this->order, $this->authResponse());
        $this->order['transaction_id'] = $tid;
        return ['resultCode' => 'SUCCESS', 'mid' => $this->config['merchant_id'], 'oid' => $this->order['id'], 'price' => '13000', 'tid' => $tid,
            'transactionStatus' => 'APPROVAL', 'paymethod' => 'Card', 'approvedDate' => '20260906', 'approvedTime' => '120000', 'cardInfo' => ['currencyCode' => 'WON']];
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            if ($this->shopInstalled) foreach (array_reverse(\GnuCms\Modules\Shop\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            foreach (array_keys(Settings::PROVIDERS) as $id) foreach (['transactions', 'settings'] as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_' . $table));
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testEncryptedMerchantSettingsRotationAndRestorePermit(array $db): void
    {
        $this->setupGateway($db);
        $revision = $this->order['config_revision'];
        $raw = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('pay_inicis_settings') . " WHERE id = 'test'")['payload'];
        self::assertStringNotContainsString($this->config['api_key'], $raw);
        self::assertArrayNotHasKey('api_key', $this->settings->summary('test'));
        $next = array_replace($this->config, ['api_key' => bin2hex(random_bytes(16)), 'client_ip' => '192.0.2.20']);
        $this->settings->save('test', $next); self::assertFalse($this->settings->available('test'));
        self::assertSame($next['api_key'], $this->settings->credentials($revision)['api_key']);
        self::assertSame($next['client_ip'], $this->settings->credentials($revision)['client_ip']);
        self::assertSame($this->config['api_key'], $this->settings->revision($revision)['api_key']);
        $this->rejected(fn () => $this->settings->save('test', ['merchant_id' => 'other00000']));
        $this->settings->enable('test', true); (new RuntimePermit($this->root))->revokeAll();
        self::assertFalse($this->settings->available('test'));
        foreach (['kcp' => KcpGateway::class, 'kspay' => KspayGateway::class] as $id => $class) {
            $settings = new Settings($this->app, $id); $settings->install(); $settings->save('test', Fixtures::config($id)); $settings->enable('test', true);
            $gateway = new $class($settings, $this->http);
            self::assertFalse($gateway->available('test')); self::assertFalse($gateway->available('live'));
            $this->rejected(fn () => $gateway->configuration('test'));
        }
        self::assertSame([], $this->http->calls);
    }

    public function testMerchantInputPreservesPrivateKeyPasswordAndRejectsMismatchedCertificate(): void
    {
        $input = Fixtures::config('kcp', ' ' . bin2hex(random_bytes(16)) . ' ');
        $validated = \GnuCms\Payment\ProviderConfig::validate('kcp', $input, []);
        self::assertSame($input['private_key_password'], $validated['private_key_password']);
        self::assertSame($validated, \GnuCms\Payment\ProviderConfig::validate('kcp', ['merchant_id' => ' T9999 '], $validated));
        $other = Fixtures::config('kcp');
        $this->rejected(fn () => \GnuCms\Payment\ProviderConfig::validate('kcp', array_replace($input, ['certificate' => $other['certificate']]), []));
    }

    #[DataProvider('connectionProvider')]
    public function testVersionOneUpgradeAddsJournalAndRequiresFreshMerchantConfiguration(array $db): void
    {
        $this->setupGateway($db);
        $schema = new \GnuCms\Extension\PackageSchema($this->app->db(), $this->root);
        $schema->install('plugins/payment-kspay', 1, ['pay_kspay_settings'], static function ($db): void {
            $db->execute('CREATE TABLE ' . $db->table('pay_kspay_settings') . ' (id VARCHAR(32) PRIMARY KEY, payload ' . $db->dialect()->typeMap()['{TEXT}'] . ' NOT NULL)' . $db->dialect()->tableSuffix());
        });
        $cipher = new \GnuCms\Mail\SecretCipher((string) $this->app->config('auth.secret'));
        $payload = $cipher->encrypt(json_encode(['environment' => 'test', 'revision' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));
        $this->app->db()->execute('INSERT INTO ' . $this->app->db()->table('pay_kspay_settings') . ' (id, payload) VALUES (?, ?)', ['test', $payload]);
        $settings = new Settings($this->app, 'kspay'); self::assertFalse($settings->ready());
        $settings->install(); $settings->install(); self::assertTrue($settings->ready());
        self::assertFalse($settings->summary('test')['configured']); self::assertFalse($settings->available('test'));
        self::assertSame([], $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('pay_kspay_transactions')));
        self::assertSame($payload, $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('pay_kspay_settings') . " WHERE id = 'test'")['payload']);
        $settings->save('test', Fixtures::config('kspay'));
        self::assertTrue($settings->summary('test')['configured']); self::assertFalse($settings->available('test'));
    }

    #[DataProvider('connectionProvider')]
    public function testDesktopAndMobileFormsSignServerStoredOrderAndHideSecrets(array $db): void
    {
        $this->setupGateway($db);
        $options = $this->checkout(); $fields = $options['fields'];
        self::assertSame('inicis', $options['kind']); self::assertSame($this->config['merchant_id'], $fields['mid']);
        self::assertSame('13000', $fields['price']); self::assertSame('WON', $fields['currency']);
        self::assertSame(hash('sha256', 'oid=' . $this->order['id'] . '&price=13000&timestamp=' . $fields['timestamp']), $fields['signature']);
        self::assertSame(hash('sha256', 'oid=' . $this->order['id'] . '&price=13000&signKey=' . $this->config['sign_key'] . '&timestamp=' . $fields['timestamp']), $fields['verification']);
        foreach (['sign_key', 'hash_key', 'api_key'] as $key) self::assertStringNotContainsString($this->config[$key], json_encode($options));
        $mobile = $this->checkout('mobile'); $m = $mobile['fields'];
        self::assertSame('https://stgmobile.inicis.com/smart/payment/', $mobile['action']);
        self::assertSame(base64_encode(hash('sha512', '13000' . $this->order['id'] . $m['P_TIMESTAMP'] . $this->config['hash_key'], true)), $m['P_CHKFAKE']);
        self::assertSame([], $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testCallbacksCannotChangeMerchantOrderAmountEnvironmentOrDestinationAndReplayDoesNotApproveAgain(array $db): void
    {
        $this->setupGateway($db); $this->checkout(); $callback = $this->authResponse();
        foreach ([['mid' => 'other00000'], ['orderNumber' => bin2hex(random_bytes(16))], ['authUrl' => 'https://evil.test/api/payAuth'], ['idc_name' => 'fc'], ['authToken' => ['bad']], ['netCancelUrl' => 'https://stgstdpay.inicis.com.evil.test/api/netCancel']] as $change) $this->rejected(fn () => $this->gateway->complete($this->order, array_replace($callback, $change)));
        self::assertSame([], $this->http->calls);
        $payment = $this->approve();
        $this->gateway->complete($this->order, $callback); self::assertCount(1, $this->http->calls);
        self::assertSame('13000', $this->http->calls[0]['body']['price']);
        $this->response($payment); self::assertTrue($this->gateway->fetch($this->order)['valid']);
        $last = end($this->http->calls); self::assertSame('https://stginiapi.inicis.com/v2/pg/inquiry', $last['url']);
        self::assertSame($payment['tid'], $last['body']['data']['tid']);
        $signed = $last['body'];
        self::assertSame(hash('sha512', $this->config['api_key'] . $this->config['merchant_id'] . 'inquiry' . $signed['timestamp'] . StreamTransport::json($signed['data'])), $signed['hashData']);
        foreach ([['mid' => 'other00000'], ['oid' => bin2hex(random_bytes(16))], ['price' => '1'], ['tid' => 'wrong'], ['cardInfo' => ['currencyCode' => 'USD']], ['cardInfo' => []], ['approvedDate' => '20260230']] as $change) {
            $this->response(array_replace($payment, $change)); self::assertFalse($this->gateway->fetch($this->order)['valid']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testApprovalTimeoutUsesNetworkCancelAndPersistsUncertaintyBeforeRetry(array $db): void
    {
        $this->setupGateway($db); $this->checkout(); $callback = $this->authResponse();
        $this->http->responses[] = DomainError::serviceUnavailable('timeout'); $this->response(['resultCode' => '0000']);
        $this->rejected(fn () => $this->gateway->complete($this->order, $callback));
        self::assertSame('pending', (new Journal($this->settings))->read($this->order['id'])['approval']);
        self::assertStringEndsWith('/api/netCancel', $this->http->calls[1]['url']);
        $this->gateway->complete($this->order, $callback); self::assertCount(2, $this->http->calls);
        $this->rejected(fn () => $this->checkout());
    }

    #[DataProvider('connectionProvider')]
    public function testMobileAuthorizationUsesServerMerchantAndVerifiesApprovedOrder(array $db): void
    {
        $this->setupGateway($db); $this->checkout('mobile');
        $callback = ['P_STATUS' => '00', 'P_AMT' => '13000', 'P_TID' => bin2hex(random_bytes(20)), 'P_REQ_URL' => 'https://stgmobile.inicis.com/smart/payReq.ini', 'idc_name' => 'stg'];
        $this->rejected(fn () => $this->gateway->complete($this->order, array_replace($callback, ['P_AMT' => '1'])));
        self::assertSame([], $this->http->calls);
        $this->response(['P_STATUS' => '00', 'P_MID' => $this->config['merchant_id'], 'P_OID' => 'wrong-order', 'P_AMT' => '13000', 'P_TYPE' => 'CARD', 'P_TID' => bin2hex(random_bytes(20))]);
        $this->response(['P_STATUS' => '00']);
        $this->rejected(fn () => $this->gateway->complete($this->order, $callback));
        self::assertSame($this->config['merchant_id'], $this->http->calls[0]['body']['P_MID']);
        self::assertSame('https://stgmobile.inicis.com/smart/payNetCancel.ini', $this->http->calls[1]['url']);
        self::assertSame($this->order['id'], $this->http->calls[1]['body']['P_OID']);
        self::assertSame('pending', (new Journal($this->settings))->read($this->order['id'])['approval']);
    }

    #[DataProvider('connectionProvider')]
    public function testPartialRefundUsesRemainingBalanceAndSeparateCancellationTransaction(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve(); $key = 'refund-' . bin2hex(random_bytes(16)); $partTid = bin2hex(random_bytes(20));
        $this->response(['resultCode' => '00', 'prtcTid' => $partTid, 'prtcDate' => '20260906', 'prtcTime' => '130000', 'prtcPrice' => '3000', 'prtcRemains' => '10000']);
        $refund = $this->gateway->cancel($this->order, 3000, 13000, '반품', $key);
        $last = end($this->http->calls); self::assertSame('10000', $last['body']['data']['confirmPrice']);
        self::assertSame('3000', $last['body']['data']['price']); self::assertSame($partTid, $refund['id']);
        self::assertSame($refund, $this->gateway->cancel($this->order, 3000, 13000, '반품', $key)); self::assertCount(2, $this->http->calls);
        $this->rejected(fn () => $this->gateway->cancel($this->order, 4000, 13000, '반품', $key));
        $payment = array_replace($payment, ['transactionStatus' => 'PART_CANCEL', 'availablePartCancelPrice' => '10000', 'partCancelTransInfo' => [['tid' => $partTid, 'requestDate' => '20260906', 'requestTime' => '130000', 'requestPrice' => '3000']]]);
        $this->response($payment); $verified = $this->gateway->fetch($this->order);
        self::assertTrue($verified['valid']); self::assertSame(3000, $verified['cancelled']); self::assertSame('반품', $verified['cancellations'][0]['reason']);
    }

    #[DataProvider('connectionProvider')]
    public function testLostRefundResponseCannotBeRetriedAndCanOnlyAttachVerifiedCancellation(array $db): void
    {
        $this->setupGateway($db); $payment = $this->approve(); $key = 'refund-' . bin2hex(random_bytes(16));
        $this->http->responses[] = DomainError::serviceUnavailable('timeout');
        $this->rejected(fn () => $this->gateway->cancel($this->order, 13000, 13000, '반품', $key));
        $this->rejected(fn () => $this->gateway->cancel($this->order, 13000, 13000, '반품', $key));
        self::assertCount(2, $this->http->calls);
        $payment = array_replace($payment, ['transactionStatus' => 'CANCEL', 'cancelDate' => '20260906', 'cancelTime' => '130000']);
        $this->response($payment); $this->rejected(fn () => $this->gateway->confirmRefund($this->order, $key, 'wrong'));
        $this->response($payment); $this->gateway->confirmRefund($this->order, $key, $payment['tid'] . '-full');
        $this->response($payment); $verified = $this->gateway->fetch($this->order);
        self::assertTrue($verified['valid']); self::assertSame(0, $verified['open_cancellations']); self::assertSame(13000, $verified['cancelled']);
    }

    #[DataProvider('connectionProvider')]
    public function testDirectGatewayApprovalAndRefundUpdateShopMoneyAndStockExactlyOnce(array $db): void
    {
        $this->setupGateway($db);
        $shop = new \GnuCms\Modules\Shop\Service($this->app, ['inicis' => $this->gateway]);
        $shop->install(); $this->shopInstalled = true;
        $shop->saveSettings(['name' => '테스트 상점', 'seller' => '상호', 'owner' => '대표', 'business_number' => '000', 'phone' => '01000000000', 'email' => 'shop@example.test',
            'address' => '주소', 'return_address' => '반품 주소', 'policy' => '정책', 'shipping' => 3000, 'free_shipping' => 50000, 'environment' => 'test', 'open' => '1']);
        $productId = $shop->catalog->save(['name' => '상품', 'description' => '상품 설명', 'price' => 10000, 'stock' => 2, 'active' => '1']);
        $variant = $shop->catalog->product($productId)['variants'][0]['id'];
        $this->order = $shop->createOrder('test-buyer', [$variant => 1], ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test', 'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'inicis', bin2hex(random_bytes(16)), 13000);
        $payment = $this->approve();
        $this->response($payment); $paid = $shop->sync($this->order['id']);
        self::assertSame('paid', $paid['status']);
        $this->response($payment); $shop->sync($this->order['id']);
        $this->response($payment);
        $this->response(['resultCode' => '00', 'cancelDate' => '20260906', 'cancelTime' => '140000']);
        $cancelled = array_replace($payment, ['transactionStatus' => 'CANCEL', 'cancelDate' => '20260906', 'cancelTime' => '140000']);
        $this->response($cancelled);
        $refundId = $shop->claims->refund($this->order['id'], ['request_key' => bin2hex(random_bytes(16)), 'reason' => '취소'], 'admin');
        $this->response($cancelled); $refunded = $shop->sync($this->order['id']);
        self::assertSame('refunded', $refunded['status']); self::assertSame(13000, (int) $refunded['refunded']);
        self::assertSame('succeeded', $shop->store->get('shop_refunds', $refundId)['status']);
        self::assertSame(2, (int) $shop->store->get('shop_variants', $variant)['stock']);
        $entries = $this->app->db()->select('SELECT amount FROM ' . $this->app->db()->table('shop_money') . ' WHERE order_id = ?', [$this->order['id']]);
        self::assertCount(2, $entries); self::assertSame(0, array_sum(array_column($entries, 'amount')));
    }

    public function testTransportOnlyAllowsDocumentedPgHttpsDestinations(): void
    {
        foreach (['http://iniapi.inicis.com/v2/pg/refund', 'https://user@iniapi.inicis.com/v2/pg/refund', 'https://iniapi.inicis.com.evil.test/v2/pg/refund', 'https://127.0.0.1/v2/pg/refund', 'https://iniapi.inicis.com/v2/pg/refund?redirect=https://evil.test'] as $url) self::assertFalse(StreamTransport::allowed($url));
        self::assertTrue(StreamTransport::allowed('https://iniapi.inicis.com/v2/pg/refund'));
        self::assertTrue(StreamTransport::allowed('https://spl.kcp.co.kr/gw/mod/v1/cancel'));
        self::assertTrue(StreamTransport::allowed('https://pay.ksnet.co.kr/kspay/webfep/api/v1/card/cancel'));
    }
}
