<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\CsvImport;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CsvImportTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): CsvImport
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-csv-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'iv' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $_SESSION = [];
        return $this->app->initalk()->import;
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        $_SESSION = [];
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function testSampleParsesAndHeadersMayBeReorderedOrExtended(): void
    {
        $import = new CsvImport(new App(['auth' => ['secret' => 'x']]), null, null, null);
        $sample = CsvImport::sample();
        self::assertStringStartsWith("\xEF\xBB\xBF상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)\n", $sample);
        $parsed = $import->parse($sample, 48);
        self::assertSame([], $parsed['errors']);
        self::assertCount(2, $parsed['rows']);
        self::assertSame(2, $parsed['rows'][0]['line']);
        $reordered = "금액,휴대폰번호,구매자명,메모,상품명\n\"12,000\",010-1111-2222,홍길동,무시되는 열,\"수강료, 9월\"\n\n";
        $parsed = $import->parse($reordered, 24);
        self::assertSame([], $parsed['errors']);
        self::assertSame(['line' => 2, 'product_name' => '수강료, 9월', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01011112222', 'amount' => 12000, 'expiry_hours' => 24], $parsed['rows'][0]);
        $cp949 = mb_convert_encoding("상품명,구매자명,휴대폰번호,금액\r\n한글 상품,김이니,01023457891,5000\r\n", 'CP949', 'UTF-8');
        $parsed = $import->parse($cp949, 48);
        self::assertSame([], $parsed['errors']);
        self::assertSame('한글 상품', $parsed['rows'][0]['product_name']);
    }

    public function testRowErrorsAreReportedByLineAndLimitsApply(): void
    {
        $import = new CsvImport(new App(['auth' => ['secret' => 'x']]), null, null, null);
        $parsed = $import->parse("상품명,구매자명,휴대폰번호,금액\n좋은 상품,김이니,02-123-4567,5000\n,홍길동,01011112222,5000\n정상,이몽룡,01011113333,50\n정상,성춘향,01011114444,7000\n", 48);
        self::assertCount(1, $parsed['rows']);
        self::assertSame(4, $parsed['total']);
        self::assertCount(3, $parsed['errors']);
        self::assertStringStartsWith('2행: ', $parsed['errors'][0]);
        self::assertStringStartsWith('3행: ', $parsed['errors'][1]);
        self::assertStringStartsWith('4행: ', $parsed['errors'][2]);
        self::assertStringNotContainsString('01011112222', json_encode($parsed['errors']));
        $missing = $import->parse("상품명,구매자명\n상품,이름\n", 48);
        self::assertSame([], $missing['rows']);
        self::assertStringContainsString('휴대폰번호', $missing['errors'][0]);
        $tooMany = "상품명,구매자명,휴대폰번호,금액\n" . str_repeat("상품,이름,01011112222,1000\n", 501);
        self::assertStringContainsString('500행', $import->parse($tooMany, 48)['errors'][0]);
        self::assertStringContainsString('1MB', $import->parse(str_repeat('a', 1048577), 48)['errors'][0]);
        self::assertStringContainsString('비어', $import->parse('', 48)['errors'][0]);
    }

    #[DataProvider('connectionProvider')]
    public function testRememberAndConfirmCreateABatchOfRequests(array $config): void
    {
        $import = $this->setupApp($config);
        $parsed = $import->parse("상품명,구매자명,휴대폰번호,금액,결제기한(시간)\n수강료,홍길동,01011112222,50000,12\n교재비,김이니,01023457891,30000,\n", 48);
        self::assertSame([], $parsed['errors']);
        $token = $import->remember($parsed, '9월_청구.csv', false);
        self::assertSame('9월_청구.csv', $import->pending($token)['filename']);
        self::assertNull($import->pending('missing'));
        $summary = $import->confirm($token, 'test', 7, '운영자');
        self::assertSame(2, $summary['created']);
        self::assertSame(0, $summary['failed']);
        self::assertSame(0, $summary['sent']);
        self::assertNull($import->pending($token));
        $batch = $this->app->db()->selectOne('SELECT * FROM ' . $this->app->db()->table('initalk_batches') . ' WHERE id = ?', [$summary['batch_id']]);
        self::assertSame('9월_청구.csv', $batch['filename']);
        self::assertSame(2, (int) $batch['total']);
        $rows = $this->app->initalk()->requests->search(['environment' => 'test', 'batch' => $summary['batch_id']], 1);
        self::assertSame(2, $rows['total']);
        $byName = array_column($rows['items'], null, 'buyer_name');
        self::assertSame(Clock::timestamp() + 12 * 3600, $byName['홍길동']['expires_at']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $byName['김이니']['expires_at']);
        Clock::freeze('2026-09-15 03:11:00');
        self::assertNull($import->pending($token));
    }
}
