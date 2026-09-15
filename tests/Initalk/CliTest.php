<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Status;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-cli-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/storage', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function runCli(string $command, string $configFile): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/initalk.php', $command, '--config=' . $configFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testExpireAndPurgeRunAgainstTheConfiguredDatabase(): void
    {
        $config = ['db' => ['dsn' => 'sqlite:' . $this->root . '/board.sqlite', 'username' => null, 'password' => null], 'storage' => ['dir' => $this->root . '/storage'],
            'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']];
        $configFile = $this->root . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
        $app = new App($config);
        (new Schema($app->db()))->create();
        $request = $app->initalk()->requests->create(['product_name' => '상품', 'product_detail' => '', 'buyer_name' => '구매자', 'phone' => '01023457891', 'amount' => '1000'], 'test', 48, 1, '운영자');
        $app->db()->update('initalk_requests', ['expires_at' => time() - 10], 'id = :id', ['id' => $request['id']]);
        [$code, $out] = $this->runCli('expire', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('expire: 1건', $out);
        self::assertSame(Status::EXPIRED, (new App($config))->initalk()->requests->find($request['id'])['status']);
        $app->db()->update('initalk_requests', ['status_changed_at' => time() - 100 * 86400], 'id = :id', ['id' => $request['id']]);
        [$code, $out] = $this->runCli('purge', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('purge: 1건', $out);
        [$code, $out] = $this->runCli('sync', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('sync: 0건', $out);
        [$code, , $err] = $this->runCli('bogus', $configFile);
        self::assertSame(1, $code);
        self::assertStringContainsString('사용법', $err);
    }

    /** #13 조회하지 못한 건이 있으면 0으로 끝내지 않는다. 출력에는 id 앞자리만 남는다. */
    public function testSyncExitsNonZeroWhenARequestCannotBeSynced(): void
    {
        $secret = bin2hex(random_bytes(32));
        $config = ['db' => ['dsn' => 'sqlite:' . $this->root . '/board.sqlite', 'username' => null, 'password' => null], 'storage' => ['dir' => $this->root . '/storage'],
            'auth' => ['secret' => $secret], 'app' => ['url' => 'https://shop.example.test']];
        $configFile = $this->root . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
        $app = new App($config);
        (new Schema($app->db()))->create();
        $request = $app->initalk()->requests->create(['product_name' => '상품', 'product_detail' => '', 'buyer_name' => '구매자', 'phone' => '01023457891', 'amount' => '1000'], 'test', 48, 1, '운영자');
        // 있지도 않은 결제 설정 판을 붙여 두면 조회가 외부 통신 전에 반드시 실패한다.
        $app->initalk()->requests->touchCheckout($request['id'], str_repeat('a', 32));
        [$code, $out, $err] = $this->runCli('sync', $configFile);
        self::assertSame(1, $code, $out . $err);
        self::assertStringContainsString('sync: 0건', $out);
        self::assertStringContainsString('실패 1건', $err);
        self::assertStringContainsString(substr($request['id'], 0, 8), $err);
        self::assertStringNotContainsString($request['id'], $err);
        self::assertStringNotContainsString($secret, $out . $err);
        self::assertStringNotContainsString('01023457891', $out . $err);
    }

    /** #13 배포 뒤 첫 웹 요청보다 cron이 먼저 돌아도 스키마를 맞추고 끝낸다. */
    public function testTheFirstCommandBringsTheSchemaUpToDate(): void
    {
        $fresh = $this->root . '/fresh';
        mkdir($fresh . '/storage', 0700, true);
        $config = ['db' => ['dsn' => 'sqlite:' . $fresh . '/board.sqlite', 'username' => null, 'password' => null], 'storage' => ['dir' => $fresh . '/storage'],
            'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']];
        $configFile = $fresh . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
        self::assertFileDoesNotExist($fresh . '/board.sqlite');
        [$code, $out, $err] = $this->runCli('expire', $configFile);
        self::assertSame(0, $code, $out . $err);
        self::assertStringContainsString('expire: 0건', $out);
        self::assertSame(0, (new App($config))->initalk()->requests->search(['environment' => 'test'], 1)['total']);
    }
}
