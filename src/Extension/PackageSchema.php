<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use Throwable;

/** 명시적 관리 작업에서만 실행한다. 테이블 소유권은 패키지가 없어져도 보존한다. */
final class PackageSchema
{
    public function __construct(private Connection $db, private string $storageDir)
    {
    }

    public function status(string $key): ?array
    {
        return $this->db->selectOne('SELECT * FROM ' . $this->db->table('extension_schemas') . ' WHERE package_key = ?', [$key]);
    }

    public function current(string $key, int $version): bool
    {
        $row = $this->status($key);
        return $row !== null && $row['state'] === 'ready' && (int) $row['schema_version'] === $version;
    }

    /** @param list<string> $tables @param callable(Connection,int):void $migrate */
    public function install(string $key, int $version, array $tables, callable $migrate): void
    {
        if (!preg_match('~^(plugins|modules)/[a-z][a-z0-9_-]{0,63}$~D', $key) || $version < 1 || $tables === []) {
            throw DomainError::validation(['schema' => '확장 스키마 선언이 올바르지 않습니다.']);
        }
        foreach ($tables as $table) self::assertTable($table);
        if ($this->db->pdo()->inTransaction()) throw DomainError::internal('스키마 갱신을 트랜잭션 안에서 실행할 수 없습니다.');
        if (!is_dir($this->storageDir)) mkdir($this->storageDir, 0700, true);
        $lock = fopen($this->storageDir . '/upgrade.lock', 'c');
        if ($lock === false) throw DomainError::serviceUnavailable('스키마 잠금을 열지 못했습니다.');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw DomainError::serviceUnavailable('백업 또는 구조 갱신이 진행 중입니다.');
            $before = $this->status($key);
            if ($before !== null && (int) $before['schema_version'] > $version) {
                throw DomainError::serviceUnavailable('더 최신 버전으로 만든 확장 데이터입니다.');
            }
            if ($this->current($key, $version)) return;
            $owned = [];
            foreach ($this->db->select('SELECT * FROM ' . $this->db->table('extension_schemas')) as $row) {
                foreach (self::decodeTables($row['table_names']) as $table) $owned[$table] = $row['package_key'];
            }
            foreach ($tables as $table) {
                if ((isset($owned[$table]) && $owned[$table] !== $key)
                    || (!isset($owned[$table]) && $this->exists($table))) {
                    throw DomainError::validation(['schema' => '다른 데이터와 확장 테이블 이름이 충돌합니다.']);
                }
            }
            $all = array_values(array_unique(array_merge($tables, $before === null ? [] : self::decodeTables($before['table_names']))));
            if ($this->db->dialect()->name() === 'sqlite') {
                $dir = $this->storageDir . '/backups/extensions';
                if (!is_dir($dir)) mkdir($dir, 0700, true);
                $backup = $dir . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.sqlite';
                $this->db->execute("VACUUM INTO '" . str_replace("'", "''", $backup) . "'");
                chmod($backup, 0600);
            }
            $encoded = json_encode($all, JSON_THROW_ON_ERROR);
            if ($before === null) {
                $this->db->execute('INSERT INTO ' . $this->db->table('extension_schemas')
                    . ' (package_key, schema_version, table_names, state) VALUES (?, 0, ?, ?)', [$key, $encoded, 'installing']);
            } else {
                $this->db->update('extension_schemas', ['table_names' => $encoded, 'state' => 'installing'], 'package_key = :key', ['key' => $key]);
            }
            try {
                $migrate($this->db, (int) ($before['schema_version'] ?? 0));
                foreach ($all as $table) {
                    if (!$this->exists($table)) throw DomainError::internal('확장 테이블 생성이 완료되지 않았습니다.');
                }
                $this->db->update('extension_schemas', ['schema_version' => $version, 'state' => 'ready'], 'package_key = :key', ['key' => $key]);
            } catch (Throwable $e) {
                $this->db->update('extension_schemas', ['state' => 'failed'], 'package_key = :key', ['key' => $key]);
                throw DomainError::serviceUnavailable('확장 데이터 설치에 실패했습니다. 원인을 점검한 뒤 다시 실행해 주세요.');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** 백업은 활성 여부나 패키지 PHP를 읽지 않는다. 실패한 설치의 실제 테이블도 포함한다. */
    public function backupTables(): array
    {
        $tables = [];
        if (!$this->exists('extension_schemas')) return [];
        foreach ($this->db->select('SELECT * FROM ' . $this->db->table('extension_schemas')) as $row) {
            foreach (self::decodeTables($row['table_names']) as $table) {
                if ($this->exists($table)) $tables[] = $table;
                elseif ($row['state'] === 'ready') throw DomainError::serviceUnavailable('설치된 확장 테이블이 없어 백업을 중단했습니다.');
            }
        }
        return array_values(array_unique($tables));
    }

    public function exists(string $table): bool
    {
        $physical = $this->db->tableName($table);
        return match ($this->db->dialect()->name()) {
            'sqlite' => $this->db->selectOne("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$physical]) !== null,
            'mysql' => $this->db->selectOne('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$physical]) !== null,
        };
    }

    private static function decodeTables(string $encoded): array
    {
        $tables = json_decode($encoded, true);
        if (!is_array($tables) || !array_is_list($tables)) throw DomainError::internal('확장 테이블 등록 정보가 손상되었습니다.');
        foreach ($tables as $table) self::assertTable($table);
        return $tables;
    }

    private static function assertTable(mixed $table): void
    {
        if (!is_string($table) || !preg_match('/^[a-z][a-z0-9_]{0,29}$/D', $table) || in_array($table, Schema::TABLES, true)) {
            throw DomainError::internal('확장 테이블 이름이 올바르지 않습니다.');
        }
    }
}
