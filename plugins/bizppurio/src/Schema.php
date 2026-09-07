<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Db\Connection;
use GnuCms\Extension\PackageSchema;

final class Schema
{
    public const KEY = 'plugins/bizppurio';
    public const VERSION = 1;
    public const TABLES = ['bp_settings', 'bp_templates', 'bp_dispatches', 'bp_attempts', 'bp_receipts'];

    public static function install(PackageSchema $schema): void
    {
        $schema->install(self::KEY, self::VERSION, self::TABLES, static function (Connection $db): void {
            $definitions = [
                'bp_settings' => 'environment VARCHAR(8) PRIMARY KEY, revision VARCHAR(32) NOT NULL, payload {TEXT} NOT NULL',
                'bp_templates' => 'id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, code VARCHAR(30) NOT NULL,
                    revision VARCHAR(32) NOT NULL, payload {TEXT} NOT NULL, enabled SMALLINT NOT NULL,
                    UNIQUE (environment, code)',
                'bp_dispatches' => 'id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, config_revision VARCHAR(32) NOT NULL,
                    idempotency_key VARCHAR(64) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL,
                    template_id VARCHAR(32) NOT NULL, template_name VARCHAR(100) NOT NULL,
                    payload {TEXT} NOT NULL, phone_mask VARCHAR(30) NOT NULL, phone_hash VARCHAR(64) NOT NULL,
                    submission VARCHAR(16) NOT NULL, delivery VARCHAR(16) NOT NULL, created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL, retry_at BIGINT NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0',
                'bp_attempts' => 'id VARCHAR(32) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, sequence_no INTEGER NOT NULL, refkey VARCHAR(32) NOT NULL UNIQUE,
                    messagekey VARCHAR(128) NOT NULL, submission VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL,
                    http_status INTEGER NOT NULL, created_at BIGINT NOT NULL',
                'bp_receipts' => 'id VARCHAR(64) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, attempt_id VARCHAR(32) NOT NULL,
                    message_id VARCHAR(128) NOT NULL, message_key VARCHAR(128) NOT NULL, media VARCHAR(16) NOT NULL,
                    result_code VARCHAR(16) NOT NULL, event_at BIGINT NOT NULL, received_at BIGINT NOT NULL,
                    matched SMALLINT NOT NULL',
            ];
            foreach ($definitions as $table => $definition) {
                $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table($table) . ' ('
                    . strtr($definition, $db->dialect()->typeMap()) . ')' . $db->dialect()->tableSuffix());
            }
            // 인덱스도 실패 후 재실행한다. MySQL은 IF NOT EXISTS를 지원하지 않는다.
            foreach (['bp_list' => ['bp_dispatches', 'created_at'], 'bp_tries' => ['bp_attempts', 'dispatch_id'],
                'bp_results' => ['bp_receipts', 'dispatch_id']] as $index => [$table, $column]) {
                $physical = $db->prefix() . $index;
                $exists = match ($db->dialect()->name()) {
                    'sqlite' => $db->selectOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$physical]),
                    'mysql' => $db->selectOne('SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$db->tableName($table), $physical]),
                };
                if ($exists === null) $db->execute('CREATE INDEX ' . $db->index($index) . ' ON ' . $db->table($table) . ' (' . $db->q($column) . ')');
            }
        });
    }
}
