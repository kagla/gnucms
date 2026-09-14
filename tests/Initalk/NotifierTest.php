<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Notifier;
use GnuCms\Initalk\Status;
use GnuCms\Messaging\MessagingService;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotifierTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;

    private function setupApp(array $config): array
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-notify-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'in' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $this->http = new FakeTransport();
        $messaging = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($messaging);
        $messaging->settings->save('test', ['account' => 'initalk-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01023457891']);
        $messaging->settings->setEnabled('test', true);
        $template = $messaging->templates->save('test', ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.\n■ 상품명: #{상품명}\n■ 금액: #{금액}원\n■ 결제기한: #{결제기한}\n* 고객센터: #{고객센터}",
            'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}', 'url_pc' => 'https://shop.example.test/pay/#{결제토큰}']]]);
        $this->app->initalk()->settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-4954', 'expiry_hours' => '48',
            'environment' => 'test', 'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '3']);
        return $template;
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function request(array $overrides = []): array
    {
        return $this->app->initalk()->requests->create($overrides + ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    public function testVariablesAreFormattedForTheTemplate(): void
    {
        $request = ['buyer_name' => '김이니', 'created_at' => 1789441200, 'product_name' => '크림', 'amount' => 15800, 'expires_at' => 1789441200 + 48 * 3600,
            'url_token' => 'tok_abc', 'number' => 'IT-20260915-0001'];
        $config = ['store_name' => '이니 상점', 'support_phone' => '1588-4954'];
        $variables = Notifier::variables($request, $config, ['상점명', '구매자명', '요청일', '상품명', '금액', '결제기한', '고객센터', '결제토큰', '주문번호']);
        self::assertSame(['상점명' => '이니 상점', '구매자명' => '김이니', '요청일' => '9월 15일', '상품명' => '크림', '금액' => '15,800',
            '결제기한' => '2026년 09월 17일 12:00', '고객센터' => '1588-4954', '결제토큰' => 'tok_abc', '주문번호' => 'IT-20260915-0001'], $variables);
        self::assertSame(['상점명' => '이니 상점'], Notifier::variables($request, $config, ['상점명']));
        self::assertSame('상점 문의', Notifier::variables($request, ['store_name' => 'x', 'support_phone' => ''], ['고객센터'])['고객센터']);
        $this->expectException(DomainError::class);
        Notifier::variables($request, $config, ['없는변수']);
    }

    #[DataProvider('connectionProvider')]
    public function testSendResendExtendAndGuards(array $config): void
    {
        $this->setupApp($config);
        $notifier = $this->app->initalk()->notifier;
        $request = $this->request();
        $result = $notifier->send($request['id'], '운영자');
        self::assertSame('accepted', $result['submission']);
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('01023457891', $body['to']);
        self::assertStringContainsString('[이니 상점] 김이니님, 9월 15일 요청하신', $body['content']['at']['message']);
        self::assertStringContainsString('15,800원', $body['content']['at']['message']);
        self::assertSame('https://shop.example.test/pay/' . $request['url_token'], $body['content']['at']['button'][0]['url_mobile']);
        $after = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::WAITING, $after['status']);
        self::assertSame(1, $after['dispatch_count']);
        self::assertSame($result['id'], $after['last_dispatch_id']);
        // 재발송은 새 멱등키로 두 번째 발송을 만든다.
        $second = $notifier->send($request['id'], '운영자');
        self::assertNotSame($result['id'], $second['id']);
        self::assertSame(2, $this->http->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->find($request['id'])['dispatch_count']);
        // 만료 건은 기한을 연장한 뒤 보낸다.
        Clock::freeze('2026-09-18 00:00:00');
        self::assertSame(1, $this->app->initalk()->requests->expire());
        $third = $notifier->send($request['id'], '운영자');
        self::assertSame('accepted', $third['submission']);
        $extended = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::WAITING, $extended['status']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $extended['expires_at']);
        // 취소 건·발송 정지·템플릿 없음은 거부한다.
        $cancelled = $this->request();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        try { $notifier->send($cancelled['id'], '운영자'); self::fail('cancelled'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $this->app->messaging()->settings->setEnabled('test', false);
        try { $notifier->send($request['id'], '운영자'); self::fail('stopped'); } catch (DomainError $e) { self::assertArrayHasKey('messaging', $e->details()); }
        $this->app->messaging()->settings->setEnabled('test', true);
        $this->app->cms()->saveSettings(['initalk.template.test' => '']);
        try { $notifier->send($request['id'], '운영자'); self::fail('no template'); } catch (DomainError $e) { self::assertArrayHasKey('template', $e->details()); }
        self::assertSame(3, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testSendManyReportsPerRequestResults(array $config): void
    {
        $this->setupApp($config);
        $a = $this->request();
        $b = $this->request(['phone' => '01011112222']);
        $c = $this->request();
        $this->app->initalk()->requests->cancel($c['id'], '운영자');
        $summary = $this->app->initalk()->notifier->sendMany([$a['id'], $b['id'], $c['id'], str_repeat('0', 32)], '운영자');
        self::assertSame(1, $summary['sent']);
        self::assertSame(3, $summary['failed']);
        self::assertArrayHasKey($b['id'], $summary['errors']);
        self::assertArrayHasKey($c['id'], $summary['errors']);
        self::assertStringNotContainsString('01011112222', json_encode($summary));
    }
}
