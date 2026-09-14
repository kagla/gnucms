<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * bin/migrate.php는 Schema::create()/migrateAll()만 부르고 끝나서, 배포에서 옮겨온
 * storage/extensions/enabled.json에 코어로 흡수된 패키지 키가 남아 있어도 그 CLI 경로로는
 * 정리되지 않았다(SchemaUpgrader::run()의 나머지 도장·백업 로직 없이 정리 단계만 필요).
 * 실제 bin/migrate.php 스크립트를 서브프로세스로 실행해 그 배선을 검증한다.
 */
final class MigrateCliTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/' . GNUCMS_ID . '-migrate-cli-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->root);
    }

    #[DataProvider('connectionProvider')]
    public function testCliRetiresLegacyPackagesAfterMigratingALegacyDatabase(array $config): void
    {
        if (($config['dsn'] ?? '') === 'sqlite::memory:') {
            // 서브프로세스가 같은 DB를 봐야 하므로 :memory: 대신 실 파일을 쓴다.
            $config['dsn'] = 'sqlite:' . $this->root . '/board.sqlite';
        }
        $config['prefix'] = 'cli' . bin2hex(random_bytes(3)) . '_';

        // 판 22 시절 bp_settings만 설치하고 나머지 bp_* 표는 없는 DB를 흉내 낸다(레지스트리 등록 포함).
        $db = Connection::create($config);
        $schema = new Schema($db);
        $schema->drop();
        $schema->create();
        foreach (['bp_templates', 'bp_dispatches', 'bp_attempts', 'bp_receipts', 'bp_settings'] as $table) {
            $db->execute('DROP TABLE IF EXISTS ' . $db->table($table));
        }
        $text = $db->dialect()->typeMap()['{TEXT}'];
        $suffix = $db->dialect()->tableSuffix();
        $db->execute('CREATE TABLE ' . $db->table('bp_settings')
            . ' (environment VARCHAR(8) PRIMARY KEY, revision VARCHAR(32) NOT NULL, payload ' . $text . ' NOT NULL)' . $suffix);
        $db->execute('INSERT INTO ' . $db->table('bp_settings') . ' (environment, revision, payload) VALUES (?, ?, ?)',
            ['test', 'rev1', 'secret-payload']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['plugins/bizppurio', 1, json_encode(['bp_settings']), 'ready']);
        $db->execute("UPDATE " . $db->table('site_settings') . " SET setting_value = '22.legacy' WHERE setting_key = 'system.schema_version'");

        $storageDir = $this->root . '/storage';
        (new StateStore($storageDir . '/extensions'))->update(static fn (): array => ['plugins/bizppurio', 'plugins/keep']);

        $configFile = $this->root . '/config.php';
        file_put_contents($configFile, "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export(['db' => $config, 'storage' => ['dir' => $storageDir]], true) . ";\n");

        [$status, $output] = $this->runMigrate($configFile);
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('코어로 흡수된 패키지', $output);

        // 등록 삭제·상태 정리 둘 다 CLI 경로만으로 끝났는지 확인한다.
        self::assertSame(['plugins/keep'], (new StateStore($storageDir . '/extensions'))->read());
        $keys = array_column($db->select('SELECT package_key FROM ' . $db->table('extension_schemas')), 'package_key');
        self::assertSame([], $keys);
        // 데이터도 보존하고, 판 22에는 없던 나머지 bp_* 표도 새로 채워졌다(개별 생성 보강).
        self::assertSame('secret-payload', $db->selectOne('SELECT payload FROM ' . $db->table('bp_settings')
            . " WHERE environment = 'test'")['payload']);
        self::assertTrue((new Schema($db))->exists());
        self::assertNotNull($db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->table('bp_dispatches')));

        (new Schema($db))->drop();
    }

    private function runMigrate(string $configFile): array
    {
        $script = dirname(__DIR__, 2) . '/bin/migrate.php';
        $output = [];
        $status = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($configFile) . ' 2>&1',
            $output,
            $status
        );

        return [$status, implode("\n", $output)];
    }
}
