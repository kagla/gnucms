<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Payment\FakeTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CheckoutTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-checkout-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ic' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))],
            'app' => ['url' => 'https://shop.example.test']]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
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

    private function request(): array
    {
        return $this->app->initalk()->requests->create(['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    private function authCallback(array $request): array
    {
        return ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $request['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
    }

    private function respond(array $body): void { $this->http->responses[] = ['status' => 200, 'body' => $body]; }

    private function approval(array $request, string $tid, int $price = 15800): array
    {
        return ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $request['id'], 'TotPrice' => (string) $price, 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON'];
    }

    private function inquiry(array $request, string $tid, string $status = 'APPROVAL', int $price = 15800, array $partials = []): array
    {
        $cancelled = array_sum(array_column($partials, 'requestPrice'));
        return ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $request['id'], 'price' => (string) $price, 'tid' => $tid,
            'transactionStatus' => $status, 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120500', 'cardInfo' => ['currencyCode' => 'WON'],
            'availablePartCancelPrice' => (string) ($price - $cancelled), 'partCancelTransInfo' => $partials, 'cancelDate' => '20260916', 'cancelTime' => '090000'];
    }

    /** 결제창 → 승인 → 조회까지 끝낸 결제완료 요청. */
    private function paidRequest(string &$tid): array
    {
        $request = $this->request();
        $checkout = $this->app->initalk()->checkout;
        $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid));
        $checkout->complete($request['id'], $this->authCallback($request));
        return $this->app->initalk()->requests->find($request['id']);
    }

    #[DataProvider('connectionProvider')]
    public function testStartCompleteSyncAndRefunds(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $form = $checkout->start($request['id'], 'web', 'https://shop.example.test/pay/' . $request['url_token'] . '/return', 'https://shop.example.test/pay/callback');
        self::assertSame('inicis', $form['kind']);
        self::assertSame($request['id'], $form['fields']['oid']);
        self::assertSame('15800', $form['fields']['price']);
        self::assertStringStartsWith('https://shop.example.test/pay/callback?id=' . $request['id'] . '&state=', $form['fields']['returnUrl']);
        $started = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Clock::timestamp(), $started['checkout_started_at']);
        self::assertSame($this->app->paymentSettings()->summary('test')['revision'], $started['config_revision']);
        try { $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('too soon'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-15 03:00:20');
        $mobile = $checkout->start($request['id'], 'mobile', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        self::assertSame('form', $mobile['kind']);
        self::assertSame('EUC-KR', $mobile['charset']);
        self::assertSame($request['id'], $mobile['fields']['P_OID']);
        self::assertSame([], $this->http->calls);
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid));
        $checkout->complete($request['id'], $this->authCallback($request));
        $paid = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame($tid, $paid['transaction_id']);
        self::assertSame((new \DateTimeImmutable('2026-09-15 12:05:00', new \DateTimeZone('Asia/Seoul')))->getTimestamp(), $paid['paid_at']);
        $ledger = $this->app->initalk()->ledger->forRequest($request['id']);
        self::assertSame([['approve', 15800, $tid]], array_map(static fn (array $row): array => [$row['kind'], (int) $row['amount'], $row['reference']], $ledger));
        // 콜백 재전송은 새 승인을 만들지 않는다. 결제완료 상태에서는 결제사에 아무것도 보내지 않고 거부한다(#1).
        $calls = count($this->http->calls);
        try { $checkout->complete($request['id'], $this->authCallback($request)); self::fail('resent'); } catch (DomainError $e) { self::assertArrayHasKey('payment', $e->details()); }
        self::assertCount(1, $this->app->initalk()->ledger->forRequest($request['id']));
        self::assertSame($calls, count($this->http->calls));
        // 부분 환불
        $key = bin2hex(random_bytes(16));
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '5800', 'prtcRemains' => '10000', 'prtcTid' => $tid . 'P1']);
        $partial = $checkout->refund($request['id'], 5800, '일부 반품', $key, '운영자');
        self::assertSame(Status::PAID, $partial['status']);
        self::assertSame(5800, $partial['refunded_amount']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($request['id']));
        // 같은 키로 다시 요청하면 결제사에 다시 보내지 않고 조회로 대조한다.
        $calls = count($this->http->calls);
        $this->respond($this->inquiry($request, $tid, 'PART_CANCEL', 15800, [['tid' => $tid . 'P1', 'requestDate' => '20260916', 'requestTime' => '100000', 'requestPrice' => '5800']]));
        $again = $checkout->refund($request['id'], 5800, '일부 반품', $key, '운영자');
        self::assertSame(5800, $again['refunded_amount']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($request['id']));
        self::assertSame($calls + 1, count($this->http->calls));
        try { $checkout->refund($request['id'], 10001, '초과', bin2hex(random_bytes(16)), '운영자'); self::fail('over'); } catch (DomainError $e) { self::assertArrayHasKey('amount', $e->details()); }
        // 남은 금액 전액 환불. 총액(15,800)보다 작은 금액이므로 게이트웨이는 부분취소 API를 쓴다.
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260917', 'prtcTime' => '110000', 'prtcPrice' => '10000', 'prtcRemains' => '0', 'prtcTid' => $tid . 'P2']);
        $full = $checkout->refund($request['id'], 10000, '전체 반품', bin2hex(random_bytes(16)), '운영자');
        self::assertSame(Status::REFUNDED, $full['status']);
        self::assertSame(15800, $full['refunded_amount']);
        self::assertCount(3, $this->app->initalk()->ledger->forRequest($request['id']));
        $between = $this->app->initalk()->ledger->between('test', 0, Clock::timestamp() + 86400 * 10);
        self::assertSame(3, count($between));
        self::assertSame($request['number'], $between[0]['number']);
        self::assertSame('010-****-7891', $between[0]['phone_mask']);
    }

    #[DataProvider('connectionProvider')]
    public function testMismatchedInquiryFlagsReviewInsteadOfPaying(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid, 'APPROVAL', 15000));
        $checkout->complete($request['id'], $this->authCallback($request));
        $after = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::CREATED, $after['status']);
        self::assertSame(1, $after['needs_review']);
        self::assertNull($after['paid_at']);
        self::assertSame([], $this->app->initalk()->ledger->forRequest($request['id']));
        $fresh = $this->request(); self::assertSame($fresh['status'], $checkout->sync($fresh['id'], '운영자')['status']); self::assertCount(2, $this->http->calls);
    }

    /** #3 대조가 끝나면 확인 필요 표시를 내린다. */
    #[DataProvider('connectionProvider')]
    public function testCleanSyncClearsTheReviewFlag(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid, 'APPROVAL', 15000));
        $checkout->complete($request['id'], $this->authCallback($request));
        self::assertSame(1, $this->app->initalk()->requests->find($request['id'])['needs_review']);
        // 조회가 요청의 상점·금액·거래번호와 일치하면 확인 필요를 내린다.
        $this->respond($this->inquiry($request, $tid));
        $after = $checkout->sync($request['id'], '운영자');
        self::assertSame(Status::PAID, $after['status']);
        self::assertSame(0, $after['needs_review']);
        $events = $this->app->initalk()->events->forRequest($request['id']);
        self::assertSame('review_cleared', end($events)['type']);
        self::assertSame('대조 완료', end($events)['note']);
    }

    /** #11 결제창이 거부되면 만료 보호(30분)를 연장하지 않는다. */
    #[DataProvider('connectionProvider')]
    public function testRejectedCheckoutDoesNotExtendTheExpiryProtection(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        $first = $this->app->initalk()->requests->find($request['id'])['checkout_started_at'];
        self::assertSame(Clock::timestamp(), $first);
        // 승인 응답의 금액이 달라 승인이 미확정으로 남는다 → 게이트웨이가 새 결제창을 막는다.
        $this->respond($this->approval($request, 'StdpayCARD' . bin2hex(random_bytes(10)), 999));
        try { $checkout->complete($request['id'], $this->authCallback($request)); self::fail('approval'); } catch (DomainError $e) { self::assertGreaterThanOrEqual(500, $e->status()); }
        Clock::freeze('2026-09-15 03:30:00');
        try { $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('pending'); }
        catch (DomainError $e) { self::assertSame('이미 요청한 결제 결과를 먼저 확인해 주세요.', $e->details()['payment'] ?? ''); }
        self::assertSame($first, $this->app->initalk()->requests->find($request['id'])['checkout_started_at']);
    }

    /** #2 (a) 결과를 확인하지 못한 환불은 조회가 같은 금액의 취소를 찾으면 연결되고, 그 뒤 새 환불이 다시 열린다. */
    #[DataProvider('connectionProvider')]
    public function testSyncLinksAPendingRefundToTheMatchingCancellation(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $tid = '';
        $request = $this->paidRequest($tid);
        // 부분취소 응답의 금액이 어긋나 게이트웨이가 거절한다 → 저널에 보류가 남는다.
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '4000', 'prtcRemains' => '10000', 'prtcTid' => $tid . 'P1']);
        try { $checkout->refund($request['id'], 5800, '일부 반품', bin2hex(random_bytes(16)), '운영자'); self::fail('rejected'); } catch (DomainError $e) { self::assertSame(503, $e->status()); }
        self::assertCount(1, $this->app->initalk()->ledger->forRequest($request['id']));
        // 보류가 남아 있는 동안에는 새 키의 환불도 막힌다.
        try { $checkout->refund($request['id'], 5800, '다시 반품', bin2hex(random_bytes(16)), '운영자'); self::fail('blocked'); }
        catch (DomainError $e) { self::assertSame('기존 환불을 PG에서 확인해 주세요.', $e->details()['refund'] ?? ''); }
        // 조회에 같은 금액의 취소가 보이면 보류 신청에 연결하고 원장·환불 누적액도 같은 실행에서 맞춘다.
        $partials = [['tid' => $tid . 'P1', 'requestDate' => '20260916', 'requestTime' => '100000', 'requestPrice' => '5800']];
        $this->respond($this->inquiry($request, $tid, 'PART_CANCEL', 15800, $partials));
        $this->respond($this->inquiry($request, $tid, 'PART_CANCEL', 15800, $partials));
        $after = $checkout->sync($request['id'], '운영자');
        self::assertSame(Status::PAID, $after['status']);
        self::assertSame(5800, $after['refunded_amount']);
        self::assertSame(0, $after['needs_review']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($request['id']));
        self::assertSame([], $this->app->inicisGateway()->pendingRefunds(Checkout::order($request)));
        // 보류가 풀렸으므로 새 환불을 다시 받는다.
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260917', 'prtcTime' => '110000', 'prtcPrice' => '1000', 'prtcRemains' => '9000', 'prtcTid' => $tid . 'P2']);
        $third = $checkout->refund($request['id'], 1000, '추가 반품', bin2hex(random_bytes(16)), '운영자');
        self::assertSame(6800, $third['refunded_amount']);
        self::assertCount(3, $this->app->initalk()->ledger->forRequest($request['id']));
    }

    /** #2 (b) PG에 취소가 없으면 2시간이 지난 뒤에만 미처리로 종료할 수 있다. */
    #[DataProvider('connectionProvider')]
    public function testUnprocessedRefundIsClosedOnlyAfterTwoHours(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $tid = '';
        $request = $this->paidRequest($tid);
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '4000', 'prtcRemains' => '10000', 'prtcTid' => $tid . 'P1']);
        try { $checkout->refund($request['id'], 5800, '일부 반품', bin2hex(random_bytes(16)), '운영자'); self::fail('rejected'); } catch (DomainError $e) { self::assertSame(503, $e->status()); }
        $pending = $this->app->inicisGateway()->pendingRefunds(Checkout::order($request));
        self::assertCount(1, $pending);
        $key = (string) array_key_first($pending);
        // 조회에 취소가 없다 → 연결할 것이 없고 보류가 남아 확인 필요로 표시된다.
        $this->respond($this->inquiry($request, $tid));
        $synced = $checkout->sync($request['id'], '운영자');
        self::assertSame(0, $synced['refunded_amount']);
        self::assertSame(1, $synced['needs_review']);
        try { $checkout->closeUnprocessedRefund($request['id'], $key, '운영자'); self::fail('too soon'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-15 06:00:00');
        $this->respond($this->inquiry($request, $tid));
        $this->respond($this->inquiry($request, $tid));
        $closed = $checkout->closeUnprocessedRefund($request['id'], $key, '운영자');
        self::assertSame(0, $closed['refunded_amount']);
        self::assertSame(0, $closed['needs_review']);
        self::assertSame([], $this->app->inicisGateway()->pendingRefunds(Checkout::order($request)));
        $events = array_column($this->app->initalk()->events->forRequest($request['id']), 'type');
        self::assertContains('refund_closed', $events);
        // 종료 뒤에는 새 환불을 다시 받는다.
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '120000', 'prtcPrice' => '5800', 'prtcRemains' => '10000', 'prtcTid' => $tid . 'P9']);
        $again = $checkout->refund($request['id'], 5800, '다시 반품', bin2hex(random_bytes(16)), '운영자');
        self::assertSame(5800, $again['refunded_amount']);
    }

    #[DataProvider('connectionProvider')]
    public function testStartRejectsClosedOrExpiredRequestsAndStoppedApi(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $cancelled = $this->request();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        try { $checkout->start($cancelled['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('cancelled'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $late = $this->request();
        Clock::freeze('2026-09-18 00:00:00');
        try { $checkout->start($late['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('expired'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-15 03:00:00');
        $this->app->paymentSettings()->enable('test', false);
        $open = $this->request();
        try { $checkout->start($open['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('stopped'); } catch (DomainError $e) { self::assertSame(503, $e->status()); }
        self::assertSame('mobile', Checkout::device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'));
        self::assertSame('web', Checkout::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
        self::assertSame([], $this->http->calls);
    }
}
