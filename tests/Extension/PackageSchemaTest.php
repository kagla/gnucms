<?php

declare(strict_types=1);

namespace GnuCms\Tests\Extension;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PackageSchemaTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testPartialInstallResumesAndRetainsOwnershipForBackup(array $config): void
    {
        $config['prefix'] = 'ex' . bin2hex(random_bytes(4)) . '_';
        $db = Connection::create($config);
        (new Schema($db))->create();
        $root = sys_get_temp_dir() . '/gnucms-schema-' . bin2hex(random_bytes(5));
        $schema = new PackageSchema($db, $root);
        try {
            try {
                $schema->install('plugins/example', 1, ['ext_first', 'ext_second'], static function (Connection $db): void {
                    $db->execute('CREATE TABLE ' . $db->table('ext_first') . ' (id INTEGER PRIMARY KEY)');
                    $db->execute('INSERT INTO ' . $db->table('ext_first') . ' (id) VALUES (1)');
                    throw new \RuntimeException('simulated interruption');
                });
                self::fail('must fail first');
            } catch (DomainError $e) { self::assertSame(503, $e->status()); }
            self::assertSame('failed', $schema->status('plugins/example')['state']);
            self::assertSame(['ext_first'], $schema->backupTables());
            $schema->install('plugins/example', 1, ['ext_first', 'ext_second'], static function (Connection $db): void {
                $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table('ext_second') . ' (id INTEGER PRIMARY KEY)');
            });
            self::assertTrue($schema->current('plugins/example', 1));
            self::assertCount(2, $schema->backupTables());
            self::assertSame(1, (int) $db->selectOne('SELECT id FROM ' . $db->table('ext_first'))['id']);
            $schema->install('plugins/example', 1, ['ext_first', 'ext_second'], static fn () => self::fail('must be idempotent'));
            try { $schema->install('plugins/other', 1, ['ext_first'], static fn () => null); self::fail('ownership conflict'); }
            catch (DomainError $e) { self::assertSame(422, $e->status()); }
            $db->execute('DROP TABLE ' . $db->table('ext_second'));
            try { $schema->backupTables(); self::fail('must not silently lose table'); } catch (DomainError $e) { self::assertSame(503, $e->status()); }
        } finally {
            foreach (['ext_first', 'ext_second'] as $table) $db->execute('DROP TABLE IF EXISTS ' . $db->table($table));
            (new Schema($db))->drop();
            if (is_dir($root)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                rmdir($root);
            }
        }
    }
}
