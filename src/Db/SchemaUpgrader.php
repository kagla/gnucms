<?php

declare(strict_types=1);

namespace GnuCms\Db;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use Throwable;

/**
 * 코드를 올린 뒤 첫 요청에서 스키마를 새 판으로 옮긴다. 관리 서버는 없다.
 *
 * 순서: 도장 비교 → 최근 실패면 건너뜀 → 파일 잠금 → 백업(SQLite) → migrateAll → 기록.
 * 실패하면 도장을 찍지 않고 upgrade-failed.json 을 남긴 뒤 MaintenanceRequired 를 던진다.
 * 그 파일이 RETRY_AFTER_SECONDS 안이면 다시 시도하지 않고 바로 점검 화면으로 보낸다.
 * 그 뒤 재시도할 때는 표식에 실패 당시의 도장(stamp)도 같이 적어 두고, 지금 도장과
 * 같을 때만 그 백업을 재사용한다 — 다르면(그 사이 판이 또 바뀜) 남의 백업이므로 새로 뜬다.
 */
final class SchemaUpgrader
{
    public const KEEP_BACKUPS = 5;
    public const RETRY_AFTER_SECONDS = 60;

    private Connection $db;
    private string $storageDir;
    /** @var callable */
    private $migrate;
    /** @var callable */
    private $log;

    /**
     * @param callable|null $migrate 실제 마이그레이션 대신 부를 것(테스트용). 기본은 Schema::migrateAll()
     * @param callable|null $log     한 줄을 받는 기록 함수. 기본은 storage/logs/error.log 에 덧붙임
     */
    public function __construct(Connection $db, string $storageDir, ?callable $migrate = null, ?callable $log = null)
    {
        $this->db = $db;
        $this->storageDir = rtrim($storageDir, '/');
        $this->migrate = $migrate ?? [new Schema($db), 'migrateAll'];
        $this->log = $log ?? function (string $line): void {
            $dir = $this->storageDir . '/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $wrote = is_dir($dir)
                ? @file_put_contents($dir . '/error.log', '[' . gmdate('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX)
                : false;
            if ($wrote === false) {
                error_log($line);
            }
        };
    }

    public function run(): void
    {
        $schema = new Schema($this->db);
        $stored = $schema->storedStamp();
        if ($stored === $schema->stamp()) {
            return;
        }

        $failed = $this->readFailure();
        if ($failed !== null && time() - (int) ($failed['at'] ?? 0) < self::RETRY_AFTER_SECONDS) {
            throw new MaintenanceRequired(MaintenanceRequired::FAILED, $failed['backup'] ?? null);
        }

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
        $lockPath = $this->storageDir . '/upgrade.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            ($this->log)('[schema-upgrade] 잠금 파일을 만들 수 없습니다: ' . $lockPath);
            throw new MaintenanceRequired(MaintenanceRequired::FAILED);
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new MaintenanceRequired(MaintenanceRequired::BUSY);
        }

        try {
            // 잠금을 잡는 사이 다른 요청이 끝냈을 수 있다.
            $stored = $schema->storedStamp();
            if ($stored === $schema->stamp()) {
                return;
            }

            $backup = null;
            try {
                // 실패 뒤 재시도라면, 그 실패 이전의 원본 스냅숏을 그대로 쓴다.
                // 매번 새로 VACUUM 하면 5개까지만 남기는 정리 때문에 다섯 번
                // 재시도한 뒤에는 첫 시도 이전의 깨끗한 백업이 사라진다.
                // 단, 그 표식이 지금 판(도장)에서 실패했을 때 남긴 것이어야 한다.
                // 도장이 다르면(예: 그 사이 다른 배포로 판이 또 바뀜) 남의 백업을
                // 쓰는 셈이라 새로 뜬다.
                $reusable = $failed['backup'] ?? null;
                if (is_string($reusable) && $reusable !== '' && is_file($reusable) && ($failed['stamp'] ?? null) === $stored) {
                    $backup = $reusable;
                } else {
                    $backup = $this->backup($stored);
                }
                ($this->migrate)();
                $this->retireLegacyPackages();
                $this->upsertSetting('system.schema_upgraded_at', Clock::now());
                $this->upsertSetting('system.schema_backup', $backup ?? '');
                @unlink($this->failurePath());
            } catch (Throwable $e) {
                ($this->log)('[schema-upgrade] ' . get_class($e) . ': ' . $e->getMessage());
                $this->writeFailure($e->getMessage(), $backup, $stored);
                throw new MaintenanceRequired(MaintenanceRequired::FAILED, $backup, $e);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * 관리 콘솔에 보일 값.
     *
     * @return array{version: string, stamp: string, upgraded_at: ?string, backup: ?string, can_backup: bool, keep: int, backups: list<array{name: string, size: int, mtime: int}>}
     */
    public function status(): array
    {
        $backups = [];
        foreach ($this->backupFiles() as $file) {
            $backups[] = ['name' => basename($file), 'size' => (int) filesize($file), 'mtime' => (int) filemtime($file)];
        }
        $backup = $this->setting('system.schema_backup');

        return [
            'version'     => Schema::VERSION,
            'stamp'       => (new Schema($this->db))->stamp(),
            'upgraded_at' => $this->setting('system.schema_upgraded_at'),
            'backup'      => $backup === null || $backup === '' ? null : $backup,
            'can_backup'  => $this->db->dialect()->name() === 'sqlite',
            'keep'        => self::KEEP_BACKUPS,
            'backups'     => $backups,
        ];
    }

    /**
     * 관리 화면에서 선택한 자동 SQLite 백업 하나를 삭제한다.
     * 스키마 갱신과 겹치지 않게 같은 잠금을 사용하며 백업 폴더 밖 경로는 받지 않는다.
     *
     * @return array{deleted:string}
     */
    public function deleteBackup(string $name): array
    {
        if ($name === '' || $name !== basename($name)
            || preg_match('/^board-v[0-9A-Za-z]+-\d{8}-\d{6}(?:-\d+)?\.sqlite$/D', $name) !== 1) {
            throw new \RuntimeException('삭제할 자동 DB 백업 파일 이름이 올바르지 않습니다.');
        }
        $directory = $this->storageDir . '/backups';
        $path = $directory . '/' . $name;

        $lock = @fopen($this->storageDir . '/upgrade.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('데이터베이스 구조 갱신 잠금 파일을 열 수 없습니다.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new \RuntimeException('데이터베이스 구조 갱신이 진행 중입니다. 잠시 뒤 다시 시도해 주세요.');
        }
        try {
            if (!is_dir($directory) || is_link($directory)) {
                throw new \RuntimeException('자동 DB 백업 폴더를 안전하게 열 수 없습니다.');
            }
            if (!is_file($path) || is_link($path)) {
                throw new \RuntimeException('자동 DB 백업 파일을 찾을 수 없습니다: ' . $name);
            }
            if (!unlink($path)) {
                throw new \RuntimeException('자동 DB 백업 파일을 삭제하지 못했습니다: ' . $name);
            }
            if ($this->setting('system.schema_backup') === $path) {
                $this->upsertSetting('system.schema_backup', '');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['deleted' => $name];
    }

    /** 코어로 흡수한 패키지의 사용 상태를 지운다. 상태 파일이 없으면 만들지 않는다. */
    private function retireLegacyPackages(): void
    {
        $directory = $this->storageDir . '/extensions';
        if (!is_file($directory . '/enabled.json')) {
            return;
        }
        $legacy = ['plugins/bizppurio', 'plugins/payment-inicis', 'modules/alimtalk', 'modules/sms'];
        try {
            $store = new \GnuCms\Extension\StateStore($directory);
            if (array_intersect($store->read(), $legacy) === []) {
                return;
            }
            $store->update(static fn (array $enabled): array => array_values(array_diff($enabled, $legacy)));
        } catch (DomainError $e) {
            // 손상된 상태 파일은 확장 관리 화면이 안내한다. 스키마 갱신을 막지 않는다.
            ($this->log)('[schema-upgrade] 확장 사용 상태를 정리하지 못했습니다: ' . $e->getMessage());
        }
    }

    /** SQLite 면 VACUUM INTO 로 일관된 복사본을 만들고 경로를 돌려준다. 다른 DB 는 null. */
    private function backup(?string $storedStamp): ?string
    {
        if ($this->db->dialect()->name() !== 'sqlite') {
            return null;
        }
        $dir = $this->storageDir . '/backups';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('백업 폴더를 만들 수 없습니다: ' . $dir);
        }
        $old = $storedStamp === null ? '0' : (string) strtok($storedStamp, '.');
        $base = $dir . '/board-v' . preg_replace('/[^0-9A-Za-z]/', '', $old) . '-' . gmdate('Ymd-His');
        $path = $base . '.sqlite';
        for ($n = 2; is_file($path); $n++) {
            $path = $base . '-' . $n . '.sqlite';
        }
        // VACUUM INTO 는 쓰는 중에도 안전한 스냅숏을 만든다. 경로의 작은따옴표는 두 겹으로 피한다.
        $this->db->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $path) . "'");
        $this->prune();

        return $path;
    }

    /** 최근 KEEP_BACKUPS 개만 남긴다. 이름이 판 번호 다음 일시 순이라 자연 정렬 역순이 최신순이다. */
    private function prune(): void
    {
        foreach (array_slice($this->backupFiles(), self::KEEP_BACKUPS) as $old) {
            @unlink($old);
        }
    }

    /** @return string[] 최신순. 이름을 판 번호(자연 정렬) 다음 일시로 내림차순 비교한다. */
    private function backupFiles(): array
    {
        $files = glob($this->storageDir . '/backups/board-v*.sqlite') ?: [];
        usort($files, static fn (string $a, string $b): int => strnatcmp($b, $a));

        return $files;
    }

    private function failurePath(): string
    {
        return $this->storageDir . '/upgrade-failed.json';
    }

    /** @return array{at: int, message: string, backup: ?string, stamp: ?string}|null */
    private function readFailure(): ?array
    {
        if (!is_file($this->failurePath())) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->failurePath()), true);

        return is_array($data) ? $data : null;
    }

    /** $stamp 는 실패 당시 storedStamp() — 재시도 때 같은 판인지 맞춰 보는 도장이다. */
    private function writeFailure(string $message, ?string $backup, ?string $stamp): void
    {
        $wrote = @file_put_contents(
            $this->failurePath(),
            json_encode(['at' => time(), 'message' => $message, 'backup' => $backup, 'stamp' => $stamp], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        if ($wrote === false) {
            ($this->log)('[schema-upgrade] 실패 표식을 쓸 수 없습니다: ' . $this->failurePath());
        }
    }

    private function setting(string $key): ?string
    {
        try {
            $row = $this->db->selectOne(
                'SELECT setting_value FROM ' . $this->db->table('site_settings') . ' WHERE setting_key = ?',
                [$key]
            );
        } catch (DomainError $e) {
            return null;
        }

        return $row === null ? null : (string) $row['setting_value'];
    }

    private function upsertSetting(string $key, string $value): void
    {
        $table = $this->db->table('site_settings');
        $now = Clock::now();
        if ($this->setting($key) === null) {
            $this->db->execute(
                'INSERT INTO ' . $table . ' (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                [$key, $value, $now]
            );
            return;
        }
        $this->db->execute(
            'UPDATE ' . $table . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
            [$value, $now, $key]
        );
    }
}
