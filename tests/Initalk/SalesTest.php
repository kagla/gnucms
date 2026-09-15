<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Sales;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SalesTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-sales-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'il' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
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

    private function kst(string $datetime): int { return (new \DateTimeImmutable($datetime, new \DateTimeZone('Asia/Seoul')))->getTimestamp(); }

    private function paid(string $env, int $amount, int $paidAt, string $tid): string
    {
        $request = $this->app->initalk()->requests->create(['product_name' => '상품', 'product_detail' => '', 'buyer_name' => '구매자', 'phone' => '01023457891', 'amount' => (string) $amount], $env, 48, 1, '운영자');
        $this->app->initalk()->requests->markPaid($request['id'], $paidAt, $tid, 'customer');
        $this->app->initalk()->ledger->record('approve', $request['id'], $amount, $paidAt, $tid);
        return $request['id'];
    }

    public function testMonthRangeUsesSeoulTime(): void
    {
        [$from, $until] = Sales::monthRange(2026, 9);
        self::assertSame($this->kst('2026-09-01 00:00:00'), $from);
        self::assertSame($this->kst('2026-09-30 23:59:59'), $until);
        [$from, $until] = Sales::monthRange(2026, 2);
        self::assertSame($this->kst('2026-02-28 23:59:59'), $until);
    }

    #[DataProvider('connectionProvider')]
    public function testDailySummaryCalendarPayoutAndCsv(array $config): void
    {
        $this->setupApp($config);
        $sales = $this->app->initalk()->sales;
        $a = $this->paid('test', 15800, $this->kst('2026-09-02 10:00:00'), 'TIDA');
        $b = $this->paid('test', 50000, $this->kst('2026-09-02 15:00:00'), 'TIDB');
        $this->paid('test', 7000, $this->kst('2026-09-28 23:30:00'), 'TIDC');
        $this->paid('live', 99000, $this->kst('2026-09-03 09:00:00'), 'TIDL');
        $this->app->initalk()->ledger->record('refund', $b, 20000, $this->kst('2026-09-05 12:00:00'), 'TIDB-P1');
        [$from, $until] = Sales::monthRange(2026, 9);
        $daily = $sales->daily('test', $from, $until);
        self::assertSame(['2026-09-02', '2026-09-05', '2026-09-28'], array_column($daily, 'date'));
        self::assertSame(['date' => '2026-09-02', 'approve_count' => 2, 'approve_amount' => 65800, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 65800], $daily[0]);
        self::assertSame(['date' => '2026-09-05', 'approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 1, 'refund_amount' => 20000, 'net' => -20000], $daily[1]);
        self::assertSame(['approve_count' => 3, 'approve_amount' => 72800, 'refund_count' => 1, 'refund_amount' => 20000, 'net' => 52800], $sales->summary('test', $from, $until));
        self::assertSame([], $sales->daily('test', $this->kst('2026-10-01 00:00:00'), $this->kst('2026-10-31 23:59:59')));
        $calendar = $sales->calendar('test', 2026, 9, 3);
        self::assertSame(['2026-09-05' => 65800, '2026-09-08' => -20000], $calendar);
        self::assertSame(['2026-10-01' => 7000], $sales->calendar('test', 2026, 10, 3));
        Clock::freeze('2026-09-06 00:00:00');
        self::assertSame(-20000 + 7000, $sales->expectedPayout('test', 3, Clock::timestamp()));
        Clock::freeze('2026-09-01 00:00:00');
        self::assertSame(52800, $sales->expectedPayout('test', 3, Clock::timestamp()));
        $csv = $sales->csv('test', $from, $until);
        self::assertStringStartsWith("\xEF\xBB\xBF일시,주문번호,상품명,구매자명,휴대폰,구분,금액,참조\n", $csv);
        self::assertStringContainsString('2026-09-02 10:00,IT-20260915-0001,상품,구매자,010-****-7891,승인,15800,TIDA', $csv);
        self::assertStringContainsString(',환불,-20000,TIDB-P1', $csv);
        self::assertStringNotContainsString('TIDL', $csv);
        self::assertStringNotContainsString('01023457891', $csv);
    }
}
