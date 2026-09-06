<?php

declare(strict_types=1);

namespace GnuCms\Tests\Install;

use GnuCms\Install\ServerCheck;
use PHPUnit\Framework\TestCase;

final class ServerCheckTest extends TestCase
{
    private const ALL = ['Core', 'pdo', 'pdo_sqlite', 'mbstring', 'fileinfo', 'openssl', 'phar', 'gd', 'zip'];

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . GNUCMS_ID . '-check-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/config', 0775, true);
        mkdir($this->dir . '/storage', 0775, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir . '/config', 0775);
        @rmdir($this->dir . '/config');
        @rmdir($this->dir . '/storage');
        @rmdir($this->dir);
    }

    public function testPassesWhenEverythingIsPresent(): void
    {
        $result = $this->check(self::ALL)->run();

        self::assertTrue($result['ok']);
        foreach ($result['items'] as $item) {
            self::assertTrue($item['ok'], $item['label']);
        }
    }

    public function testMissingOpenSslExtensionFails(): void
    {
        $result = $this->check(array_diff(self::ALL, ['openssl']))->run();

        self::assertFalse($result['ok']);
        $openssl = $this->item($result, 'openssl 확장');
        self::assertFalse($openssl['ok']);
        self::assertTrue($openssl['required']);
    }

    public function testMissingPharExtensionFails(): void
    {
        $result = $this->check(array_diff(self::ALL, ['phar']))->run();

        self::assertFalse($result['ok']);
        self::assertTrue($this->item($result, 'phar 확장')['required']);
    }

    public function testNoPdoDriverFails(): void
    {
        $result = $this->check(array_diff(self::ALL, ['pdo_sqlite']))->run();

        self::assertFalse($result['ok']);
        self::assertStringContainsString('하나도 없습니다', $this->item($result, 'PDO 드라이버')['note']);
    }

    public function testListsAvailableDrivers(): void
    {
        $result = $this->check(array_merge(self::ALL, ['pdo_mysql']))->run();

        self::assertSame('있음: pdo_sqlite, pdo_mysql', $this->item($result, 'PDO 드라이버')['note']);
    }

    public function testUnsupportedPdoDriverDoesNotSatisfyRequirements(): void
    {
        $extensions = array_merge(array_diff(self::ALL, ['pdo_sqlite']), ['pdo_unsupported']);
        $result = $this->check($extensions)->run();

        self::assertFalse($result['ok']);
        self::assertFalse($this->item($result, 'PDO 드라이버')['ok']);
    }

    public function testOldPhpFails(): void
    {
        $result = $this->check(self::ALL, '8.1.99')->run();

        self::assertFalse($result['ok']);
        self::assertFalse($this->item($result, 'PHP')['ok']);
    }

    public function testMinimumPhpPasses(): void
    {
        $result = $this->check(self::ALL, '8.2.0')->run();

        self::assertTrue($result['ok']);
        self::assertTrue($this->item($result, 'PHP')['ok']);
    }

    public function testOptionalItemsDoNotBlock(): void
    {
        $result = $this->check(array_diff(self::ALL, ['gd', 'zip']), null, ['mod_dir'])->run();

        self::assertTrue($result['ok']);
        self::assertFalse($this->item($result, 'gd 확장')['ok']);
        self::assertFalse($this->item($result, 'gd 확장')['required']);
        self::assertFalse($this->item($result, 'zip 확장')['ok']);
        self::assertFalse($this->item($result, 'zip 확장')['required']);
        self::assertFalse($this->item($result, 'mod_rewrite')['ok']);
        self::assertFalse($this->item($result, 'mod_rewrite')['required']);
    }

    public function testUnknownRewriteStateIsReportedAsNote(): void
    {
        $result = $this->check(self::ALL, null, null)->run();

        $rewrite = $this->item($result, 'mod_rewrite');
        self::assertTrue($rewrite['ok']);
        self::assertStringContainsString('감지할 수 없습니다', $rewrite['note']);
    }

    public function testUnwritableConfigDirFails(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('root 는 쓰기 금지를 무시한다');
        }
        chmod($this->dir . '/config', 0555);

        $result = $this->check(self::ALL)->run();

        self::assertFalse($result['ok']);
        self::assertFalse($this->item($result, 'config/')['ok']);
    }

    private function check(array $extensions, ?string $php = null, ?array $modules = null): ServerCheck
    {
        return new ServerCheck($this->dir . '/config', $this->dir . '/storage', array_values($extensions), $php, $modules);
    }

    private function item(array $result, string $labelPrefix): array
    {
        foreach ($result['items'] as $item) {
            if (str_starts_with($item['label'], $labelPrefix)) {
                return $item;
            }
        }
        self::fail('항목이 없다: ' . $labelPrefix);
    }
}
