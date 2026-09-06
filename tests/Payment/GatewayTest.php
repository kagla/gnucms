<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Extension\RuntimePermit;
use GnuCms\Payment\PortOneGateway;
use GnuCms\Payment\Settings;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class GatewayTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-payment-' . bin2hex(random_bytes(10));
        $config['prefix'] = 'pg' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
    }

    private function config(): array
    {
        return ['store_id' => 'store-' . bin2hex(random_bytes(16)), 'channel_key' => 'channel-key-' . bin2hex(random_bytes(16)),
            'api_secret' => bin2hex(random_bytes(32)), 'webhook_secret' => 'whsec_' . base64_encode(random_bytes(32))];
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            foreach (array_keys(Settings::PROVIDERS) as $id) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table('pay_' . $id . '_settings'));
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testAllThreeProvidersRegisterVerifyAndCancelUsingTheirOwnChannels(array $dbConfig): void
    {
        $this->setupApp($dbConfig);
        foreach (['inicis' => 'INICIS_V2', 'kcp' => 'KCP_V2', 'kspay' => 'KSNET'] as $id => $pg) {
            $settings = new Settings($this->app, $id); $settings->install(); $settings->install();
            $config = $this->config(); $settings->save('test', $config); $settings->enable('test', true);
            $http = new FakeTransport(); $gateway = new PortOneGateway($settings, $http);
            $order = ['id' => bin2hex(random_bytes(16)), 'provider' => $id, 'environment' => 'test', 'config_revision' => $settings->summary('test')['revision'], 'total' => 13000, 'order_name' => '상품'];
            $http->responses[] = ['status' => 200, 'body' => []];
            $request = $gateway->checkout($order, ['name' => '구매자', 'phone' => '01000000000', 'email' => 'buyer@example.test'], 'https://shop.example.test/cms/modules/shop/return', 'https://shop.example.test/cms/modules/shop/webhook');
            self::assertSame($config['channel_key'], $request['channelKey']); self::assertSame('CARD', $request['payMethod']);
            self::assertArrayNotHasKey('api_secret', $request);
            self::assertSame(13000, $http->calls[0]['body']['totalAmount']); self::assertSame(0, $http->calls[0]['body']['taxFreeAmount']);
            self::assertStringEndsWith('/pre-register', $http->calls[0]['url']);
            $payment = ['id' => $order['id'], 'storeId' => $config['store_id'], 'channel' => ['key' => $config['channel_key'], 'type' => 'TEST', 'pgProvider' => $pg],
                'currency' => 'KRW', 'amount' => ['total' => 13000, 'taxFree' => 0, 'cancelled' => 0], 'status' => 'PAID',
                'method' => ['type' => 'PaymentMethodCard'], 'transactionId' => bin2hex(random_bytes(16)), 'paidAt' => '2026-09-06T12:00:00+09:00', 'pgResponse' => 'DO NOT STORE'];
            $http->responses[] = ['status' => 200, 'body' => $payment];
            $verified = $gateway->fetch($order); self::assertTrue($verified['valid']); self::assertArrayNotHasKey('pgResponse', $verified);
            foreach ([['currency' => 'USD'], ['amount' => ['total' => 1, 'taxFree' => 0, 'cancelled' => 0]], ['storeId' => 'wrong'],
                ['channel' => ['key' => $config['channel_key'], 'type' => 'LIVE', 'pgProvider' => $pg]], ['channel' => ['key' => 'wrong', 'type' => 'TEST', 'pgProvider' => $pg]],
                ['method' => ['type' => 'PaymentMethodVirtualAccount']], ['transactionId' => ''], ['paidAt' => 'not-a-date']] as $change) {
                $http->responses[] = ['status' => 200, 'body' => array_replace($payment, $change)];
                self::assertFalse($gateway->fetch($order)['valid']);
            }
            $cancel = ['status' => 'SUCCEEDED', 'id' => bin2hex(random_bytes(16)), 'totalAmount' => 3000,
                'reason' => '반품', 'requestedAt' => '2026-09-06T12:30:00+09:00'];
            $partial = array_replace($payment, ['status' => 'PARTIAL_CANCELLED', 'amount' => ['total' => 13000, 'taxFree' => 0, 'cancelled' => 3000], 'cancellations' => [$cancel]]);
            $http->responses[] = ['status' => 200, 'body' => $partial];
            self::assertTrue($gateway->fetch($order)['valid']);
            $partial['cancellations'][] = $cancel; $partial['amount']['cancelled'] = 6000;
            $http->responses[] = ['status' => 200, 'body' => $partial];
            self::assertFalse($gateway->fetch($order)['valid']);
            $http->responses[] = ['status' => 200, 'body' => ['cancellation' => ['status' => 'SUCCEEDED']]];
            $key = 'refund-' . bin2hex(random_bytes(16));
            $gateway->cancel($order, 3000, 13000, '반품', $key);
            $last = end($http->calls);
            self::assertSame(13000, $last['body']['currentCancellableAmount']); self::assertSame(3000, $last['body']['amount']);
            self::assertSame('"' . $key . '"', $last['headers']['Idempotency-Key']);
            self::assertSame('PortOne ' . $config['api_secret'], $last['headers']['Authorization']);
            $rotated = array_replace($config, ['api_secret' => bin2hex(random_bytes(32)), 'webhook_secret' => base64_encode(random_bytes(32)),
                'channel_key' => 'channel-key-' . bin2hex(random_bytes(16))]);
            $settings->save('test', $rotated); $settings->enable('test', true);
            $http->responses[] = ['status' => 200, 'body' => $payment];
            self::assertTrue($gateway->fetch($order)['valid'], '과거 주문의 원래 채널을 확인해야 합니다.');
            $last = end($http->calls);
            self::assertSame('PortOne ' . $rotated['api_secret'], $last['headers']['Authorization']);
            self::assertSame($rotated['webhook_secret'], $settings->credentials($order['config_revision'])['webhook_secret']);
            self::assertSame($config['api_secret'], $settings->revision($order['config_revision'])['api_secret']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testSecretsAreEncryptedOldRevisionSurvivesAndRestoreRevokesExecution(array $dbConfig): void
    {
        $this->setupApp($dbConfig); $settings = new Settings($this->app, 'kcp'); $settings->install();
        $before = $this->config(); $settings->save('live', $before); $settings->enable('live', true);
        $revision = $settings->summary('live')['revision'];
        $raw = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('pay_kcp_settings') . " WHERE id = 'live'")['payload'];
        self::assertStringNotContainsString($before['api_secret'], $raw);
        self::assertArrayNotHasKey('api_secret', $settings->summary('live'));
        $settings->save('live', $this->config()); self::assertFalse($settings->available('live'));
        self::assertSame($before['api_secret'], $settings->revision($revision)['api_secret']);
        $settings->enable('live', true); self::assertTrue($settings->available('live'));
        (new RuntimePermit($this->root))->revokeAll(); self::assertFalse($settings->available('live'));
        self::assertTrue($settings->ready());
    }
}
