<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Db\SchemaUpgrader;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\PackageSchema;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LegacyPackageAdoptionTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-adopt-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    /** 플러그인 판 1(channel 컬럼·인덱스 없음)과 결제 플러그인 판 2가 설치된 v22 DB를 흉내 낸다. */
    private function legacyDatabase(array $config): Connection
    {
        $config['prefix'] = 'lg' . bin2hex(random_bytes(3)) . '_';
        $db = $this->freshDatabase($config);
        foreach (['bp_receipts', 'bp_attempts', 'bp_dispatches', 'bp_templates', 'bp_settings', 'pay_inicis_transactions', 'pay_inicis_settings'] as $table) {
            $db->execute('DROP TABLE IF EXISTS ' . $db->table($table));
        }
        $text = $db->dialect()->typeMap()['{TEXT}'];
        $suffix = $db->dialect()->tableSuffix();
        $db->execute('CREATE TABLE ' . $db->table('bp_settings') . ' (environment VARCHAR(8) PRIMARY KEY, revision VARCHAR(32) NOT NULL, payload ' . $text . ' NOT NULL)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_templates') . ' (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, code VARCHAR(30) NOT NULL, revision VARCHAR(32) NOT NULL, payload ' . $text . ' NOT NULL, enabled SMALLINT NOT NULL, UNIQUE (environment, code))' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_dispatches') . ' (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, config_revision VARCHAR(32) NOT NULL, idempotency_key VARCHAR(64) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL, template_id VARCHAR(32) NOT NULL, template_name VARCHAR(100) NOT NULL, payload ' . $text . ' NOT NULL, phone_mask VARCHAR(30) NOT NULL, phone_hash VARCHAR(64) NOT NULL, submission VARCHAR(16) NOT NULL, delivery VARCHAR(16) NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, retry_at BIGINT NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_attempts') . ' (id VARCHAR(32) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, sequence_no INTEGER NOT NULL, refkey VARCHAR(32) NOT NULL UNIQUE, messagekey VARCHAR(128) NOT NULL, submission VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL, http_status INTEGER NOT NULL, created_at BIGINT NOT NULL)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_receipts') . ' (id VARCHAR(64) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, attempt_id VARCHAR(32) NOT NULL, message_id VARCHAR(128) NOT NULL, message_key VARCHAR(128) NOT NULL, media VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL, event_at BIGINT NOT NULL, received_at BIGINT NOT NULL, matched SMALLINT NOT NULL)' . $suffix);
        foreach (['pay_inicis_settings', 'pay_inicis_transactions'] as $table) {
            $db->execute('CREATE TABLE ' . $db->table($table) . ' (id VARCHAR(32) PRIMARY KEY, payload ' . $text . ' NOT NULL)' . $suffix);
        }
        $db->execute('INSERT INTO ' . $db->table('bp_settings') . ' (environment, revision, payload) VALUES (?, ?, ?)', ['test', 'rev1', 'encrypted-settings']);
        $db->execute('INSERT INTO ' . $db->table('bp_dispatches') . ' (id, environment, config_revision, idempotency_key, request_hash, template_id, template_name, payload, phone_mask, phone_hash, submission, delivery, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [str_repeat('a', 32), 'test', 'rev1', 'key-1', 'hash', str_repeat('b', 32), '안내', 'encrypted', '010-****-0000', 'phash', 'accepted', 'pending', 1, 1]);
        $db->execute('INSERT INTO ' . $db->table('pay_inicis_settings') . ' (id, payload) VALUES (?, ?)', ['test', 'encrypted-merchant']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['plugins/bizppurio', 1, json_encode(['bp_settings', 'bp_templates', 'bp_dispatches', 'bp_attempts', 'bp_receipts']), 'ready']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['plugins/payment-inicis', 2, json_encode(['pay_inicis_settings', 'pay_inicis_transactions']), 'ready']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['modules/demo-reservation', 1, json_encode(['demo_reservations']), 'failed']);
        $db->execute('UPDATE ' . $db->table('site_settings') . " SET setting_value = '22.legacy' WHERE setting_key = 'system.schema_version'");
        return $db;
    }

    #[DataProvider('connectionProvider')]
    public function testMigrationAdoptsPluginTablesKeepsRowsAndDropsRegistryEntries(array $config): void
    {
        $db = $this->legacyDatabase($config);
        $schema = new Schema($db);
        $schema->migrateAll();
        $schema->migrateAll();
        self::assertSame('encrypted-settings', $db->selectOne('SELECT payload FROM ' . $db->table('bp_settings') . " WHERE environment = 'test'")['payload']);
        self::assertSame('encrypted-merchant', $db->selectOne('SELECT payload FROM ' . $db->table('pay_inicis_settings') . " WHERE id = 'test'")['payload']);
        self::assertSame('at', $db->selectOne('SELECT channel FROM ' . $db->table('bp_dispatches'))['channel']);
        $keys = array_column($db->select('SELECT package_key FROM ' . $db->table('extension_schemas')), 'package_key');
        self::assertSame(['modules/demo-reservation'], $keys);
        self::assertSame([], (new PackageSchema($db, $this->root))->backupTables());
        self::assertSame($schema->stamp(), $schema->storedStamp());
    }

    #[DataProvider('connectionProvider')]
    public function testUpgraderRetiresAbsorbedPackagesFromEnabledState(array $config): void
    {
        $db = $this->legacyDatabase($config);
        $store = new StateStore($this->root . '/extensions');
        $store->update(static fn (): array => [...Catalog::ABSORBED, 'plugins/demo-message']);
        (new SchemaUpgrader($db, $this->root, null, static function (): void {}))->run();
        self::assertSame(['plugins/demo-message'], $store->read());
    }

    #[DataProvider('connectionProvider')]
    public function testUpgraderDoesNotCreateStateFileWhenNoneExists(array $config): void
    {
        $db = $this->legacyDatabase($config);
        (new SchemaUpgrader($db, $this->root, null, static function (): void {}))->run();
        self::assertFileDoesNotExist($this->root . '/extensions/enabled.json');
    }
}
