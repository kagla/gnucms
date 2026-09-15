<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Requests;
use GnuCms\Initalk\Status;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RequestsTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private Requests $requests;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-req-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ir' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $this->requests = $this->app->initalk()->requests;
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

    private function input(array $overrides = []): array
    {
        return $overrides + ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니',
            'phone' => '010-2345-7891', 'amount' => '15,800'];
    }

    private function rejected(callable $work, string $field): void
    {
        try { $work(); self::fail('rejected: ' . $field); } catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey($field, $e->details()); }
    }

    #[DataProvider('connectionProvider')]
    public function testCreateNumbersTokensEncryptionAndLookups(array $config): void
    {
        $this->setupApp($config);
        $first = $this->requests->create($this->input(), 'test', 48, 7, '운영자');
        $second = $this->requests->create($this->input(['buyer_name' => '홍길동', 'phone' => '01011112222', 'expiry_hours' => '2']), 'test', 48, 7, '운영자');
        self::assertSame('IT-20260915-0001', $first['number']);
        self::assertSame('IT-20260915-0002', $second['number']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $first['id']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{27}$/D', $first['url_token']);
        self::assertSame(Status::CREATED, $first['status']);
        self::assertSame('결제생성', $first['status_label']);
        self::assertSame(15800, $first['amount']);
        self::assertSame('01023457891', $first['phone']);
        self::assertSame('010-****-7891', $first['phone_mask']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $first['expires_at']);
        self::assertSame(Clock::timestamp() + 2 * 3600, $second['expires_at']);
        self::assertSame(7, $first['created_by']);
        $raw = $this->app->db()->selectOne('SELECT phone, phone_hash FROM ' . $this->app->db()->table('initalk_requests') . ' WHERE id = ?', [$first['id']]);
        self::assertStringNotContainsString('01023457891', $raw['phone']);
        self::assertSame(64, strlen($raw['phone_hash']));
        self::assertSame($first['id'], $this->requests->findByToken($first['url_token'])['id']);
        self::assertNull($this->requests->findByToken('short'));
        self::assertNull($this->requests->findByToken(str_repeat('a', 27)));
        self::assertSame('created', $this->app->initalk()->events->forRequest($first['id'])[0]['type']);
        try { $this->requests->find(str_repeat('0', 32)); self::fail('not found'); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testValidationRules(array $config): void
    {
        $this->setupApp($config);
        $create = fn (array $o) => $this->requests->create($this->input($o), 'test', 48, 1, '운영자');
        $this->rejected(fn () => $create(['product_name' => '']), 'product_name');
        $this->rejected(fn () => $create(['product_name' => str_repeat('가', 31)]), 'product_name');
        $this->rejected(fn () => $create(['product_detail' => str_repeat('a', 151)]), 'product_detail');
        $this->rejected(fn () => $create(['buyer_name' => "김\n이니"]), 'buyer_name');
        $this->rejected(fn () => $create(['phone' => '02-123-4567']), 'phone');
        $this->rejected(fn () => $create(['amount' => '99']), 'amount');
        $this->rejected(fn () => $create(['amount' => '100000000']), 'amount');
        $this->rejected(fn () => $create(['amount' => '1e3']), 'amount');
        $this->rejected(fn () => $create(['expiry_hours' => '721']), 'expiry_hours');
        $this->rejected(fn () => $create(['expiry_hours' => '0']), 'expiry_hours');
        self::assertSame(100, $create(['amount' => '100', 'product_detail' => ''])['amount']);
        self::assertSame(0, $this->requests->search(['environment' => 'live'], 1)['total']);
    }

    #[DataProvider('connectionProvider')]
    public function testSearchCountsRecentAndCustomerSummary(array $config): void
    {
        $this->setupApp($config);
        $a = $this->requests->create($this->input(), 'test', 48, 1, '운영자');
        $b = $this->requests->create($this->input(['buyer_name' => '홍길동', 'phone' => '01011112222', 'amount' => '5000', 'product_name' => '수강료']), 'test', 48, 1, '운영자');
        $c = $this->requests->create($this->input(['phone' => '01023457891', 'amount' => '30000']), 'live', 48, 1, '운영자');
        $this->requests->markPaid($a['id'], Clock::timestamp() + 60, 'StdpayCARD0001', 'customer');
        $this->requests->cancel($b['id'], '운영자');
        self::assertSame(['created' => 0, 'waiting' => 0, 'paid' => 1, 'expired' => 0, 'cancelled' => 1, 'refunded' => 0], $this->requests->counts('test'));
        self::assertSame(['created' => 1, 'waiting' => 0, 'paid' => 0, 'expired' => 0, 'cancelled' => 0, 'refunded' => 0], $this->requests->counts('live'));
        $all = $this->requests->search(['environment' => 'test'], 1);
        self::assertSame(2, $all['total']);
        self::assertSame([$b['id'], $a['id']], array_column($all['items'], 'id'));
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'phone' => '010-1111-2222'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'buyer_name' => '길동'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'product_name' => '핸드크림'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'number' => $a['number']], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'amount' => '5000'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'status' => 'paid'], 1)['total']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'sendable' => '1'], 1)['total']);
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'sendable' => '0'], 1)['total']);
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'from' => '2026-09-15', 'until' => '2026-09-15'], 1)['total']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'from' => '2026-09-16'], 1)['total']);
        self::assertSame([$c['id']], array_column($this->requests->recent('live', 10), 'id'));
        $summary = $this->requests->customerSummary('01023457891', 'test');
        self::assertSame(['count' => 1, 'total' => 15800, 'last_paid_at' => Clock::timestamp() + 60], $summary);
        self::assertSame(['count' => 0, 'total' => 0, 'last_paid_at' => null], $this->requests->customerSummary('01099998888', 'test'));
        self::assertStringNotContainsString('01023457891', json_encode($all['items']));
    }

    /** #17 검색값의 LIKE 와일드카드(%_)는 두 DB 모두에서 글자 그대로 찾는다. */
    #[DataProvider('connectionProvider')]
    public function testSearchTreatsWildcardsInTheQueryAsLiterals(array $config): void
    {
        $this->setupApp($config);
        $this->requests->create($this->input(['product_name' => '수강료 50% 할인']), 'test', 48, 1, '운영자');
        $this->requests->create($this->input(['product_name' => '수강료 5000원']), 'test', 48, 1, '운영자');
        $this->requests->create($this->input(['buyer_name' => 'A_B']), 'test', 48, 1, '운영자');
        $this->requests->create($this->input(['buyer_name' => 'AxB']), 'test', 48, 1, '운영자');
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'product_name' => '수강료'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'product_name' => '50%'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'buyer_name' => 'A_B'], 1)['total']);
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'buyer_name' => 'A'], 1)['total']);
    }

    /** #5 달력에 없는 날짜는 예외가 아니라 무시한다(정규식에 안 맞는 값과 같게). */
    #[DataProvider('connectionProvider')]
    public function testSearchIgnoresImpossibleDates(array $config): void
    {
        $this->setupApp($config);
        $this->requests->create($this->input(), 'test', 48, 1, '운영자');
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'from' => '2026-13-45', 'until' => '2026-02-30'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'from' => '2026-09-15', 'until' => '2026-09-15'], 1)['total']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'from' => '2026-09-16'], 1)['total']);
        self::assertTrue(Requests::isCalendarDate('2026-02-28'));
        self::assertFalse(Requests::isCalendarDate('2026-02-30'));
        self::assertFalse(Requests::isCalendarDate('2026-2-8'));
        self::assertFalse(Requests::isCalendarDate('오늘'));
    }

    /** #3·#10 CLI sync 대상: 창 안의 결제창(만료 포함)과 최근 확인 필요 건만. */
    #[DataProvider('connectionProvider')]
    public function testNeedingSyncCoversExpiredCheckoutsAndIsBoundedByTheWindow(array $config): void
    {
        $this->setupApp($config);
        $revision = str_repeat('a', 32);
        $untouched = $this->requests->create($this->input(), 'test', 48, 1, '운영자');
        $expired = $this->requests->create($this->input(['expiry_hours' => '1']), 'test', 48, 1, '운영자');
        $this->requests->touchCheckout($expired['id'], $revision);
        $flagged = $this->requests->create($this->input(), 'test', 48, 1, '운영자');
        $this->requests->touchCheckout($flagged['id'], $revision);
        $this->requests->setReview($flagged['id'], true, 'system', '조회 불일치');
        Clock::freeze('2026-09-15 05:00:00');
        self::assertSame(1, $this->requests->expire());
        self::assertSame(Status::EXPIRED, $this->requests->find($expired['id'])['status']);
        $ids = $this->requests->needingSync(7 * 86400);
        self::assertContains($expired['id'], $ids);
        self::assertContains($flagged['id'], $ids);
        self::assertNotContains($untouched['id'], $ids);
        // 창 밖으로 나간 확인 필요 건은 CLI가 영원히 다시 조회하지 않는다.
        Clock::freeze('2026-09-30 05:00:00');
        self::assertSame([], $this->requests->needingSync(7 * 86400));
    }

    #[DataProvider('connectionProvider')]
    public function testTransitionsExpiryProtectionRefundAndPurge(array $config): void
    {
        $this->setupApp($config);
        $r = $this->requests->create($this->input(['expiry_hours' => '1']), 'test', 48, 1, '운영자');
        $this->requests->recordDispatch($r['id'], str_repeat('d', 32), 1, 'accepted', '운영자');
        $after = $this->requests->find($r['id']);
        self::assertSame(Status::WAITING, $after['status']);
        self::assertSame(1, $after['dispatch_count']);
        self::assertSame(str_repeat('d', 32), $after['last_dispatch_id']);
        $this->requests->recordDispatch($r['id'], str_repeat('e', 32), 2, 'rejected', '운영자');
        self::assertSame(Status::WAITING, $this->requests->find($r['id'])['status']);
        $events = $this->app->initalk()->events->forRequest($r['id']);
        self::assertSame('dispatch_failed', end($events)['type']);
        // 만료: 결제창을 연 지 30분이 안 된 건은 보호한다.
        Clock::freeze('2026-09-15 04:30:00');
        $this->requests->touchCheckout($r['id'], bin2hex(random_bytes(16)));
        self::assertSame(0, $this->requests->expire());
        Clock::freeze('2026-09-15 05:10:00');
        self::assertSame(1, $this->requests->expire());
        self::assertSame(Status::EXPIRED, $this->requests->find($r['id'])['status']);
        self::assertFalse($this->requests->expireOne($r['id']));
        $this->rejected(fn () => $this->requests->markPaid(str_repeat('0', 32), 1, 'x', 'customer'), 'status');
        // 만료 → 연장 → 결제 → 부분 환불 → 전액 환불
        $extended = $this->requests->extend($r['id'], 24, '운영자');
        self::assertSame(Status::CREATED, $extended['status']);
        self::assertSame(Clock::timestamp() + 24 * 3600, $extended['expires_at']);
        $paid = $this->requests->markPaid($r['id'], Clock::timestamp(), 'StdpayCARD0002', 'customer');
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame('StdpayCARD0002', $paid['transaction_id']);
        $this->rejected(fn () => $this->requests->cancel($r['id'], '운영자'), 'status');
        $partial = $this->requests->applyRefund($r['id'], 5800, '운영자', '부분 환불');
        self::assertSame(Status::PAID, $partial['status']);
        self::assertSame(5800, $partial['refunded_amount']);
        $full = $this->requests->applyRefund($r['id'], 15800, '운영자', '전액 환불');
        self::assertSame(Status::REFUNDED, $full['status']);
        $this->rejected(fn () => $this->requests->extend($r['id'], 24, '운영자'), 'status');
        $this->requests->setReview($r['id'], true, 'system', '조회 불일치');
        self::assertSame(1, $this->requests->find($r['id'])['needs_review']);
        // 개인정보 정리: 90일 지난 종료 건만
        self::assertSame(0, $this->requests->purge());
        Clock::freeze('2026-12-20 00:00:00');
        self::assertSame(1, $this->requests->purge());
        $purged = $this->requests->find($r['id']);
        self::assertSame('', $purged['phone']);
        self::assertSame('', $purged['phone_mask']);
        self::assertSame('', $purged['buyer_name']);
        self::assertSame('플로럴 핸드크림 30ml', $purged['product_name']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'phone' => '01023457891'], 1)['total']);
        $cancelled = $this->requests->create($this->input(['phone' => '01099998888']), 'test', 48, 1, '운영자');
        $this->requests->cancel($cancelled['id'], '운영자');
        $this->rejected(fn () => $this->requests->applyRefund($cancelled['id'], 15800, '운영자', '환불'), 'status');
    }
}
