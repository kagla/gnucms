<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Status;
use GnuCms\Messaging\MessagingService;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport as MessagingTransport;
use GnuCms\Tests\Payment\FakeTransport as PaymentTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InitalkAdminTest extends WebTestCase
{
    private App $app;
    private string $root;
    private MessagingTransport $messagingHttp;
    private PaymentTransport $paymentHttp;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-admin-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ia' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']]);
        Clock::freeze('2026-09-15 03:00:00');
        $this->messagingHttp = new MessagingTransport();
        $messaging = new MessagingService($this->app, $this->messagingHttp);
        $this->app->setMessaging($messaging);
        $messaging->settings->save('test', ['account' => 'initalk-web', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01023457891']);
        $messaging->settings->setEnabled('test', true);
        $template = $messaging->templates->save('test', ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님 #{금액}원 결제기한 #{결제기한}", 'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}']]]);
        $this->app->initalk()->settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-4954', 'expiry_hours' => '48', 'environment' => 'test',
            'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '3']);
        $this->merchant = Fixtures::config('inicis');
        $this->app->paymentSettings()->save('test', $this->merchant);
        $this->app->paymentSettings()->enable('test', true);
        $this->paymentHttp = new PaymentTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->paymentHttp));
    }

    private function signIn(bool $admin): void
    {
        $id = $this->app->users()->create(($admin ? 'admin' : 'member') . '@example.com', '', $admin ? '운영자' : '일반회원', $admin);
        $this->get($this->app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
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

    private function csrf(): string { return $_SESSION['csrf_token']; }

    #[DataProvider('connectionProvider')]
    public function testScreensRequireAdminAndCsrfAndCreateSendCancelFlowWorks(array $config): void
    {
        $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/initalk'), '/admin/initalk');
        $this->signIn(false);
        foreach (['/admin/initalk', '/admin/initalk/requests', '/admin/initalk/requests/new', '/admin/initalk/settings'] as $path) self::assertSame(403, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(true);
        self::assertSame(200, $this->get($this->app, '/admin/initalk')->getStatusCode());
        self::assertStringContainsString('이니톡 결제', $this->body($this->get($this->app, '/admin')));
        $list = $this->get($this->app, '/admin/initalk/requests');
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('조회된 결제 요청이 없습니다', $this->body($list));
        self::assertSame('no-store', $list->getHeaderLine('Cache-Control'));
        $form = ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니', 'phone' => '010-2345-7891', 'amount' => '15,800', 'expiry_hours' => '', 'send_now' => '1'];
        self::assertSame(403, $this->post($this->app, '/admin/initalk/requests/new', $form)->getStatusCode());
        $invalid = $this->post($this->app, '/admin/initalk/requests/new', ['csrf_token' => $this->csrf()] + array_replace($form, ['amount' => '50']));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('금액은 100원 이상', $this->body($invalid));
        self::assertStringContainsString('value="플로럴 핸드크림 30ml"', $this->body($invalid));
        $created = $this->post($this->app, '/admin/initalk/requests/new', ['csrf_token' => $this->csrf()] + $form);
        self::assertSame(303, $created->getStatusCode());
        self::assertMatchesRegularExpression('~^/admin/initalk/requests/[a-f0-9]{32}\?created=1&sent=1$~', $created->getHeaderLine('Location'));
        self::assertSame(1, $this->messagingHttp->count('/v3/message'));
        $path = (string) parse_url($created->getHeaderLine('Location'), PHP_URL_PATH);
        $id = substr($path, -32);
        $detail = $this->body($this->get($this->app, $path, ['created' => '1', 'sent' => '1']));
        self::assertStringContainsString('IT-20260915-0001', $detail);
        self::assertStringContainsString('결제대기중', $detail);
        self::assertStringContainsString('010-2345-7891', $detail);
        self::assertStringContainsString('https://shop.example.test/pay/', $detail);
        self::assertStringContainsString('알림톡을 발송했습니다', $detail);
        $request = $this->app->initalk()->requests->find($id);
        self::assertSame(Status::WAITING, $request['status']);
        // 통합조회: 검색·카운트·마스킹
        $list = $this->body($this->get($this->app, '/admin/initalk/requests', ['phone' => '010-2345-7891']));
        self::assertStringContainsString('IT-20260915-0001', $list);
        self::assertStringContainsString('010-****-7891', $list);
        self::assertStringNotContainsString('010-2345-7891', $list);
        self::assertStringContainsString('name="ids[]"', $list);
        self::assertStringContainsString('조회된 결제 요청이 없습니다', $this->body($this->get($this->app, '/admin/initalk/requests', ['status' => 'paid'])));
        // 고객 확인 JSON
        $customer = $this->post($this->app, '/admin/initalk/customer', ['csrf_token' => $this->csrf(), 'phone' => '010-2345-7891']);
        self::assertSame('application/json; charset=utf-8', $customer->getHeaderLine('Content-Type'));
        self::assertSame(['count' => 0, 'total' => 0, 'last_paid_at' => ''], json_decode($this->body($customer), true));
        // 재발송·취소
        $resent = $this->post($this->app, $path . '/send', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $resent->getStatusCode());
        self::assertSame(2, $this->messagingHttp->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->find($id)['dispatch_count']);
        self::assertSame(403, $this->post($this->app, $path . '/cancel', [])->getStatusCode());
        self::assertSame(303, $this->post($this->app, $path . '/cancel', ['csrf_token' => $this->csrf()])->getStatusCode());
        self::assertSame(Status::CANCELLED, $this->app->initalk()->requests->find($id)['status']);
        $rejected = $this->post($this->app, $path . '/send', ['csrf_token' => $this->csrf()]);
        self::assertSame(422, $rejected->getStatusCode());
        self::assertStringContainsString('이 상태에서는 알림톡을 보낼 수 없습니다', $this->body($rejected));
        self::assertSame(404, $this->get($this->app, '/admin/initalk/requests/' . str_repeat('0', 32))->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testBulkActionsAndSettingsAndPurge(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $ids = [];
        foreach (['01023457891', '01023457891', '01011112222'] as $phone) {
            $ids[] = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => $phone, 'amount' => '50000'], 'test', 48, 1, '운영자')['id'];
        }
        $bulk = $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'send', 'ids' => $ids, 'environment' => 'test']);
        self::assertSame(303, $bulk->getStatusCode());
        parse_str((string) parse_url($bulk->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertSame(['environment' => 'test', 'sent' => '2', 'failed' => '1'], $query);
        self::assertStringContainsString('알림톡 2건을 발송했습니다', $this->body($this->get($this->app, '/admin/initalk/requests', $query)));
        $cancel = $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'cancel', 'ids' => [$ids[2]], 'environment' => 'test']);
        self::assertSame(303, $cancel->getStatusCode());
        self::assertSame(Status::CANCELLED, $this->app->initalk()->requests->find($ids[2])['status']);
        self::assertSame(303, $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'send', 'environment' => 'test'])->getStatusCode());
        // 설정 화면
        $settings = $this->body($this->get($this->app, '/admin/initalk/settings'));
        self::assertStringContainsString('value="이니 상점"', $settings);
        self::assertStringContainsString('결제 안내', $settings);
        $saved = $this->post($this->app, '/admin/initalk/settings', ['csrf_token' => $this->csrf(), 'store_name' => '새 상점', 'support_phone' => '', 'expiry_hours' => '24', 'environment' => 'test',
            'template_test' => $this->app->initalk()->settings->templateId('test'), 'template_live' => '', 'settlement_days' => '2']);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame('새 상점', $this->app->initalk()->settings->read()['store_name']);
        $bad = $this->post($this->app, '/admin/initalk/settings', ['csrf_token' => $this->csrf(), 'store_name' => '', 'support_phone' => '', 'expiry_hours' => '24', 'environment' => 'test', 'template_test' => '', 'template_live' => '', 'settlement_days' => '2']);
        self::assertSame(422, $bad->getStatusCode());
        self::assertStringContainsString('상점명을 1~40자로', $this->body($bad));
        // 개인정보 정리
        Clock::freeze('2026-12-20 00:00:00');
        $purged = $this->post($this->app, '/admin/initalk/purge', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $purged->getStatusCode());
        self::assertStringContainsString('purged=1', $purged->getHeaderLine('Location'));
        self::assertSame('', $this->app->initalk()->requests->find($ids[2])['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefundAndSyncActionsUseTheGateway(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $id = $request['id'];
        $this->app->initalk()->requests->touchCheckout($id, $this->app->paymentSettings()->summary('test')['revision']);
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->app->initalk()->requests->markPaid($id, Clock::timestamp(), $tid, 'customer');
        $this->app->initalk()->ledger->record('approve', $id, 50000, Clock::timestamp(), $tid);
        $detail = $this->body($this->get($this->app, '/admin/initalk/requests/' . $id));
        self::assertStringContainsString('결제완료', $detail);
        self::assertStringContainsString($tid, $detail);
        preg_match('/name="refund_key" value="([a-f0-9]{32})"/', $detail, $m);
        self::assertSame(403, $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['amount' => '10000', 'reason' => '일부', 'refund_key' => $m[1]])->getStatusCode());
        $this->paymentHttp->responses[] = ['status' => 200, 'body' => ['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '10000', 'prtcRemains' => '40000', 'prtcTid' => $tid . 'P1']];
        $refunded = $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['csrf_token' => $this->csrf(), 'amount' => '10,000', 'reason' => '일부 환불', 'refund_key' => $m[1]]);
        self::assertSame(303, $refunded->getStatusCode());
        self::assertSame(10000, $this->app->initalk()->requests->find($id)['refunded_amount']);
        $detail = $this->body($this->get($this->app, '/admin/initalk/requests/' . $id, ['refunded' => '1']));
        self::assertStringContainsString('환불을 처리했습니다', $detail);
        self::assertStringContainsString('40,000', $detail);
        $over = $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['csrf_token' => $this->csrf(), 'amount' => '40001', 'reason' => '초과', 'refund_key' => bin2hex(random_bytes(16))]);
        self::assertSame(422, $over->getStatusCode());
        $this->paymentHttp->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $id, 'price' => '50000', 'tid' => $tid,
            'transactionStatus' => 'PART_CANCEL', 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120000', 'cardInfo' => ['currencyCode' => 'WON'],
            'availablePartCancelPrice' => '40000', 'partCancelTransInfo' => [['tid' => $tid . 'P1', 'requestDate' => '20260916', 'requestTime' => '100000', 'requestPrice' => '10000']]]];
        $synced = $this->post($this->app, '/admin/initalk/requests/' . $id . '/sync', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $synced->getStatusCode());
        self::assertSame(Status::PAID, $this->app->initalk()->requests->find($id)['status']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($id));
    }

    #[DataProvider('connectionProvider')]
    public function testCsvImportPreviewsThenCreatesAndSends(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $sample = $this->body($this->get($this->app, '/admin/initalk/requests/import/sample'));
        self::assertStringStartsWith("\xEF\xBB\xBF상품명", $sample);
        $csv = "상품명,구매자명,휴대폰번호,금액\n수강료,홍길동,010-2345-7891,50000\n교재비,김이니,010-2345-7891,\"30,000\"\n";
        $tmp = tempnam(sys_get_temp_dir(), 'gnucms-csv-');
        file_put_contents($tmp, $csv);
        $file = new \Slim\Psr7\UploadedFile($tmp, '9월.csv', 'text/csv', strlen($csv), UPLOAD_ERR_OK);
        $preview = $this->postWithFiles($this->app, '/admin/initalk/requests/import', ['csrf_token' => $this->csrf(), 'send_now' => '1'], ['file' => $file]);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('2건 · 합계 80,000원', $this->body($preview));
        preg_match('/name="token" value="([a-f0-9]{32})"/', $this->body($preview), $m);
        $confirmed = $this->post($this->app, '/admin/initalk/requests/import/confirm', ['csrf_token' => $this->csrf(), 'token' => $m[1]]);
        self::assertSame(303, $confirmed->getStatusCode());
        parse_str((string) parse_url($confirmed->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertSame('2', $query['imported']);
        self::assertSame('2', $query['sent']);
        self::assertSame(2, $this->messagingHttp->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->search(['environment' => 'test', 'batch' => $query['batch']], 1)['total']);
        self::assertSame(422, $this->post($this->app, '/admin/initalk/requests/import/confirm', ['csrf_token' => $this->csrf(), 'token' => $m[1]])->getStatusCode());
        file_put_contents($tmp, "상품명,구매자명,휴대폰번호,금액\n,홍길동,010-2345-7891,50000\n");
        $bad = $this->postWithFiles($this->app, '/admin/initalk/requests/import', ['csrf_token' => $this->csrf()], ['file' => new \Slim\Psr7\UploadedFile($tmp, 'bad.csv', 'text/csv', 60, UPLOAD_ERR_OK)]);
        self::assertSame(422, $bad->getStatusCode());
        self::assertStringContainsString('2행: 상품명', $this->body($bad));
        @unlink($tmp);
    }

    #[DataProvider('connectionProvider')]
    public function testQrEndpointIsAdminOnlyAndRendersTheLink(array $config): void
    {
        $this->setupApp($config);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $this->assertLoginRedirect($this->get($this->app, '/admin/initalk/requests/' . $request['id'] . '/qr.svg'), '/admin/initalk/requests/' . $request['id'] . '/qr.svg');
        $this->signIn(true);
        $svg = $this->get($this->app, '/admin/initalk/requests/' . $request['id'] . '/qr.svg');
        self::assertSame(200, $svg->getStatusCode());
        self::assertSame('image/svg+xml; charset=utf-8', $svg->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('<svg', $this->body($svg));
        self::assertStringContainsString('qr.svg', $this->body($this->get($this->app, '/admin/initalk/requests/' . $request['id'])));
        self::assertSame(404, $this->get($this->app, '/admin/initalk/requests/' . str_repeat('0', 32) . '/qr.svg')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testDashboardAndSalesScreens(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $this->app->initalk()->requests->markPaid($request['id'], Clock::timestamp(), 'TID1', 'customer');
        $this->app->initalk()->ledger->record('approve', $request['id'], 50000, Clock::timestamp(), 'TID1');
        $dashboard = $this->body($this->get($this->app, '/admin/initalk'));
        self::assertStringContainsString('2026년 9월 매출 현황', $dashboard);
        self::assertStringContainsString('50,000원', $dashboard);
        self::assertStringContainsString($request['number'], $dashboard);
        $sales = $this->body($this->get($this->app, '/admin/initalk/sales', ['environment' => 'test', 'month' => '2026-09']));
        self::assertStringContainsString('2026-09-15', $sales);
        self::assertStringContainsString('정산 캘린더', $sales);
        self::assertStringContainsString('<div class="initalk-cal-amount">50,000</div>', $sales);
        $csv = $this->get($this->app, '/admin/initalk/sales/export', ['environment' => 'test', 'month' => '2026-09']);
        self::assertSame('text/csv; charset=utf-8', $csv->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment; filename="initalk-sales-20260901-20260930.csv"', $csv->getHeaderLine('Content-Disposition'));
        self::assertStringContainsString($request['number'] . ',수강료,홍길동,010-****-7891,승인,50000,TID1', $this->body($csv));
        self::assertSame(422, $this->get($this->app, '/admin/initalk/sales', ['from' => '2026-01-01', 'until' => '2026-06-30'])->getStatusCode());
        self::assertSame(422, $this->get($this->app, '/admin/initalk/sales', ['from' => '2026-13-01', 'until' => '2026-13-05'])->getStatusCode());
        self::assertSame(422, $this->get($this->app, '/admin/initalk/sales', ['from' => '2026-02-30', 'until' => '2026-03-01'])->getStatusCode());
    }
}
