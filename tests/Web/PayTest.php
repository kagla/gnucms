<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Payment\FakeTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PayTest extends WebTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-pay-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'py' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test/cms']]);
        Clock::freeze('2026-09-15 03:00:00');
        $this->app->cms()->saveSettings(['initalk.store_name' => '이니 상점', 'initalk.environment' => 'test', 'initalk.support_phone' => '1588-4954']);
        $this->merchant = Fixtures::config('inicis');
        $this->app->paymentSettings()->save('test', $this->merchant);
        $this->app->paymentSettings()->enable('test', true);
        $this->http = new FakeTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->http));
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

    /** WebTestCase 가 이미 request(App, ...) 를 protected 로 가지고 있어 이름이 겹친다. */
    private function makeRequest(): array
    {
        return $this->app->initalk()->requests->create(['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    /** 하위 경로(/cms) 설치를 흉내 낸다. 모든 요청은 /cms 기준으로 보낸다. */
    private function handle(string $method, string $path, array $body = [], array $server = [], ?string $rawBody = null, string $contentType = ''): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/cms' . $path, $server);
        if ($rawBody !== null) { $request->getBody()->write($rawBody); $request = $request->withHeader('Content-Type', $contentType); }
        elseif ($body !== []) $request = $request->withParsedBody($body);
        return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
    }

    #[DataProvider('connectionProvider')]
    public function testPaymentPageStartCallbackAndCompletion(array $config): void
    {
        $this->setupApp($config);
        $r = $this->makeRequest();
        $path = '/pay/' . $r['url_token'];
        self::assertSame(404, $this->handle('GET', '/pay/' . str_repeat('x', 27))->getStatusCode());
        $page = $this->handle('GET', $path);
        self::assertSame(200, $page->getStatusCode());
        self::assertSame('no-store', $page->getHeaderLine('Cache-Control'));
        self::assertSame('no-referrer', $page->getHeaderLine('Referrer-Policy'));
        $html = $this->body($page);
        self::assertStringContainsString('김이니 님', $html);
        self::assertStringContainsString('이니 상점', $html);
        self::assertStringContainsString('플로럴 핸드크림 30ml', $html);
        self::assertStringContainsString('15,800', $html);
        self::assertStringContainsString('action="/cms' . $path . '/start"', $html);
        self::assertStringNotContainsString('01023457891', $html);
        self::assertStringNotContainsString('daisyui', $html);
        self::assertSame(403, $this->handle('POST', $path . '/start', ['x' => '1'])->getStatusCode());
        session_start();
        $csrf = $_SESSION['csrf_token'];
        session_write_close();
        $desktop = $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)']);
        self::assertSame(200, $desktop->getStatusCode());
        self::assertStringContainsString('INIStdPay.js', $this->body($desktop));
        self::assertStringContainsString('name="oid" value="' . $r['id'] . '"', $this->body($desktop));
        self::assertStringContainsString('https://shop.example.test/cms/pay/callback?id=' . $r['id'] . '&amp;state=', $this->body($desktop));
        Clock::freeze('2026-09-15 03:00:30');
        $mobile = $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)']);
        self::assertSame(200, $mobile->getStatusCode());
        self::assertStringContainsString('accept-charset="EUC-KR"', $this->body($mobile));
        self::assertStringContainsString('stgmobile.inicis.com/smart/payment/', $this->body($mobile));
        self::assertStringContainsString('name="P_OID" value="' . $r['id'] . '"', $this->body($mobile));
        self::assertSame([], $this->http->calls);
        // 복귀(닫기)는 아직 미결제이므로 실패 안내와 함께 결제 페이지로 돌아간다.
        $back = $this->handle('GET', $path . '/return');
        self::assertSame(303, $back->getStatusCode());
        self::assertSame('/cms' . $path . '?failed=1', $back->getHeaderLine('Location'));
        self::assertSame('no-referrer', $back->getHeaderLine('Referrer-Policy'));
        self::assertStringContainsString('결제가 완료되지 않았습니다', $this->body($this->handle('GET', $path . '?failed=1')));
        // 콜백: 잘못된 state → 403, 올바른 state → 승인·조회 후 완료 화면
        $stored = $this->app->initalk()->requests->find($r['id']);
        $state = CallbackToken::create($this->app, Checkout::order($stored));
        $callback = ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $r['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
        $post = fn (string $stateValue) => $this->handle('POST', '/pay/callback?' . http_build_query(['id' => $r['id'], 'state' => $stateValue]), [], [], http_build_query($callback), 'application/x-www-form-urlencoded');
        self::assertSame(403, $post(bin2hex(random_bytes(32)))->getStatusCode());
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $r['id'], 'TotPrice' => '15800', 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $r['id'], 'price' => '15800', 'tid' => $tid,
            'transactionStatus' => 'APPROVAL', 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120500', 'cardInfo' => ['currencyCode' => 'WON']]];
        $done = $post($state);
        self::assertSame(303, $done->getStatusCode());
        self::assertSame('/cms' . $path, $done->getHeaderLine('Location'));
        self::assertSame('', $done->getHeaderLine('Set-Cookie'));
        $paid = $this->app->initalk()->requests->find($r['id']);
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame($tid, $paid['transaction_id']);
        $final = $this->body($this->handle('GET', $path));
        self::assertStringContainsString('결제가 완료되었습니다', $final);
        self::assertStringContainsString($r['number'], $final);
        self::assertStringNotContainsString('action="/cms' . $path . '/start"', $final);
        // 이미 결제된 요청의 결제 시작은 거부한다.
        self::assertSame(422, $this->handle('POST', $path . '/start', ['csrf_token' => $csrf])->getStatusCode());
    }

    /** 인증(state)은 맞지만 승인 결과가 주문과 다르면 500을 이니시스에 보이지 않고 확인 필요로 표시한다. */
    #[DataProvider('connectionProvider')]
    public function testCallbackApprovalFailureMarksReviewWithoutFailingTheRequest(array $config): void
    {
        $this->setupApp($config);
        $r = $this->makeRequest();
        $path = '/pay/' . $r['url_token'];
        session_start();
        $csrf = $_SESSION['csrf_token'];
        session_write_close();
        self::assertSame(200, $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)'])->getStatusCode());
        $stored = $this->app->initalk()->requests->find($r['id']);
        $state = CallbackToken::create($this->app, Checkout::order($stored));
        $callback = ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $r['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
        // 승인 응답의 금액이 주문과 다르다 → 게이트웨이가 망취소를 시도하고(응답 없음, 무시) 원래 오류를 던진다.
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $r['id'], 'TotPrice' => '999', 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $response = $this->handle('POST', '/pay/callback?' . http_build_query(['id' => $r['id'], 'state' => $state]), [], [], http_build_query($callback), 'application/x-www-form-urlencoded');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/cms' . $path, $response->getHeaderLine('Location'));
        $after = $this->app->initalk()->requests->find($r['id']);
        self::assertSame(1, $after['needs_review']);
        self::assertSame(Status::CREATED, $after['status']);
        self::assertNull($after['paid_at']);
    }

    /** #3 콜백이 두 번 와도(재전송·중복 제출) 결제완료 건을 확인 필요로 표시하지 않는다. */
    #[DataProvider('connectionProvider')]
    public function testDuplicateCallbackLeavesThePaidRequestClean(array $config): void
    {
        $this->setupApp($config);
        $r = $this->makeRequest();
        $path = '/pay/' . $r['url_token'];
        $this->handle('GET', $path);
        session_start();
        $csrf = $_SESSION['csrf_token'];
        session_write_close();
        self::assertSame(200, $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)'])->getStatusCode());
        $stored = $this->app->initalk()->requests->find($r['id']);
        $state = CallbackToken::create($this->app, Checkout::order($stored));
        $callback = ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $r['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
        $post = fn () => $this->handle('POST', '/pay/callback?' . http_build_query(['id' => $r['id'], 'state' => $state]), [], [], http_build_query($callback), 'application/x-www-form-urlencoded');
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $r['id'], 'TotPrice' => '15800', 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $r['id'], 'price' => '15800', 'tid' => $tid,
            'transactionStatus' => 'APPROVAL', 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120500', 'cardInfo' => ['currencyCode' => 'WON']]];
        self::assertSame(303, $post()->getStatusCode());
        self::assertSame(Status::PAID, $this->app->initalk()->requests->find($r['id'])['status']);
        $calls = count($this->http->calls);
        self::assertSame(303, $post()->getStatusCode());
        $after = $this->app->initalk()->requests->find($r['id']);
        self::assertSame(Status::PAID, $after['status']);
        self::assertSame(0, $after['needs_review']);
        self::assertSame($calls, count($this->http->calls));
        self::assertSame(1, count(array_filter($this->http->calls, static fn (array $call): bool => str_contains($call['url'], 'payAuth'))));
        self::assertCount(1, $this->app->initalk()->ledger->forRequest($r['id']));
    }

    /** #1 결제전 취소된 요청에는 인증 콜백이 와도 승인을 보내지 않는다(고객이 과금되지 않는다). */
    #[DataProvider('connectionProvider')]
    public function testCallbackAfterAdminCancelNeverSendsAnApproval(array $config): void
    {
        $this->setupApp($config);
        $r = $this->makeRequest();
        $path = '/pay/' . $r['url_token'];
        $this->handle('GET', $path);
        session_start();
        $csrf = $_SESSION['csrf_token'];
        session_write_close();
        self::assertSame(200, $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)'])->getStatusCode());
        $stored = $this->app->initalk()->requests->find($r['id']);
        $state = CallbackToken::create($this->app, Checkout::order($stored));
        // 고객이 카드 인증을 하는 사이 운영자가 결제전 취소를 눌렀다.
        $this->app->initalk()->requests->cancel($r['id'], '운영자');
        $calls = count($this->http->calls);
        $callback = ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $r['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
        $response = $this->handle('POST', '/pay/callback?' . http_build_query(['id' => $r['id'], 'state' => $state]), [], [], http_build_query($callback), 'application/x-www-form-urlencoded');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/cms' . $path, $response->getHeaderLine('Location'));
        self::assertSame($calls, count($this->http->calls), '승인 요청을 보내지 않아야 한다');
        $after = $this->app->initalk()->requests->find($r['id']);
        self::assertSame(Status::CANCELLED, $after['status']);
        self::assertNull($after['paid_at']);
        self::assertSame(1, $after['needs_review']);
        self::assertSame([], $this->app->initalk()->ledger->forRequest($r['id']));
    }

    #[DataProvider('connectionProvider')]
    public function testClosedRequestsAndStoppedApiShowGuidanceInsteadOfForms(array $config): void
    {
        $this->setupApp($config);
        $expired = $this->makeRequest();
        $cancelled = $this->makeRequest();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        Clock::freeze('2026-09-18 00:00:00');
        $html = $this->body($this->handle('GET', '/pay/' . $expired['url_token']));
        self::assertStringContainsString('결제 기한이 지났습니다', $html);
        self::assertStringContainsString('1588-4954', $html);
        self::assertStringNotContainsString('/start"', $html);
        self::assertSame(Status::EXPIRED, $this->app->initalk()->requests->find($expired['id'])['status']);
        $html = $this->body($this->handle('GET', '/pay/' . $cancelled['url_token']));
        self::assertStringContainsString('취소된 결제 요청입니다', $html);
        Clock::freeze('2026-09-15 03:00:00');
        $open = $this->makeRequest();
        $this->app->paymentSettings()->enable('test', false);
        $html = $this->body($this->handle('GET', '/pay/' . $open['url_token']));
        self::assertStringContainsString('지금은 결제할 수 없습니다', $html);
        self::assertStringNotContainsString('/start"', $html);
        self::assertSame([], $this->http->calls);
    }
}
