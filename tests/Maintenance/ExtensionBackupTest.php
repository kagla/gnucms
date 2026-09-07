<?php

declare(strict_types=1);

namespace GnuCms\Tests\Maintenance;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Extension\PackageSchema;
use GnuCms\Maintenance\BackupManager;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ExtensionBackupTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testNativeBackupContainsRegisteredTablesWithoutPackageFiles(array $config): void
    {
        $root = sys_get_temp_dir() . '/gnucms-ext-dump-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        $config['prefix'] = 'eb' . bin2hex(random_bytes(4)) . '_';
        if (str_starts_with($config['dsn'], 'sqlite:')) $config['dsn'] = 'sqlite:' . $root . '/data.sqlite';
        $db = Connection::create($config);
        $schema = new Schema($db);
        $schema->create();
        try {
            $packages = new PackageSchema($db, $root);
            $packages->install('plugins/missing-code', 1, ['ext_payload'], static function (Connection $db): void {
                $db->execute('CREATE TABLE ' . $db->table('ext_payload') . ' (id INTEGER PRIMARY KEY, content TEXT NOT NULL)');
                $db->execute('INSERT INTO ' . $db->table('ext_payload') . ' (id, content) VALUES (1, ?)', ['extension-backup-sentinel']);
            });
            $manager = new BackupManager($db, ['db' => $config, 'storage' => ['dir' => $root]], $root);
            if (!$manager->status()['can_create']) self::markTestSkipped('이 환경에서 네이티브 DB 백업 도구를 사용할 수 없습니다.');
            $saved = $manager->create('test', 'tar');
            self::assertTrue($saved['valid']);
            $archive = new \PharData($root . '/backups/manual/' . $saved['name']);
            if ($db->dialect()->name() === 'sqlite') {
                $copy = $root . '/copy.sqlite';
                file_put_contents($copy, $archive['database/sqlite.sqlite']->getContent());
                $restored = Connection::create(['dsn' => 'sqlite:' . $copy, 'prefix' => $config['prefix']]);
                self::assertSame('extension-backup-sentinel', $restored->selectOne('SELECT content FROM ' . $restored->table('ext_payload'))['content']);
                $db->execute('UPDATE ' . $db->table('ext_payload') . " SET content = 'changed'");
                $manager->restore($saved['name']);
                $reopened = Connection::create($config);
                $db = $reopened;
                $schema = new Schema($db);
                self::assertSame('extension-backup-sentinel', $reopened->selectOne('SELECT content FROM ' . $reopened->table('ext_payload'))['content']);
                $otherConfig = ['dsn' => 'sqlite:' . $root . '/other.sqlite'];
                $other = Connection::create($otherConfig);
                (new Schema($other))->create();
                $otherManager = new BackupManager($other, ['db' => $otherConfig, 'storage' => ['dir' => $root]], $root);
                try {
                    $otherManager->restore($saved['name']);
                    self::fail('다른 접두사의 백업을 자동 복원해서는 안 됩니다.');
                } catch (\RuntimeException $e) {
                    self::assertStringContainsString('테이블 프리픽스가 달라', $e->getMessage());
                }
                self::assertTrue((new Schema(Connection::create($otherConfig)))->exists());
            } elseif ($db->dialect()->name() === 'mysql') {
                $sql = $archive['database/mysql.sql']->getContent();
                self::assertStringContainsString($db->tableName('ext_payload'), $sql);
                self::assertStringContainsString('extension-backup-sentinel', $sql);
            }
        } finally {
            $db->execute('DROP TABLE IF EXISTS ' . $db->table('ext_payload'));
            $schema->drop();
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
