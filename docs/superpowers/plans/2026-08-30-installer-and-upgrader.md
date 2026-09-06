# 단계형 설치기와 스키마 업그레이더 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 다섯 단계 설치기(`public/install.php`)와, 코드를 올린 뒤 첫 요청에서 백업 → 마이그레이션 → 실패 시 점검 화면을 스스로 처리하는 `SchemaUpgrader` 를 만든다.

**Architecture:** `src/Db/SchemaUpgrader` 가 `Kernel::create()` 에서 `Schema::ensureCurrent()` 를 대신한다. 옮기지 못하면 `MaintenanceRequired` 를 던지고 `public/index.php` 가 `MaintenancePage` 로 503 을 낸다. 설치기는 `src/Install/{ServerCheck,DbSetup,InstallSession,Installer}` 네 클래스로 나누고 `public/install.php` 는 단계 라우팅과 HTML 만 맡는다.

**Tech Stack:** PHP 8.1+, PDO(SQLite/MySQL/MariaDB), Slim 4, PHPUnit 10. 설치기와 점검 화면은 Slim·테마·DB 없이 도는 독립 HTML.

설계 원문: `docs/superpowers/specs/2026-08-30-installer-and-upgrader-design.md`

## Global Constraints

- PHP 최소 판: `8.1.0` (`composer.json` 의 `>=8.1`). 서버는 composer·npm·컴파일이 없다. 빌드가 필요한 자산은 넣지 않는다.
- 문구는 한국어, 프로젝트의 말투(~합니다, 짧은 문장). 오류 원문은 방문자 화면에 내지 않는다.
- 설정 파일에서 `cors`, `auth.ttl`, `auth.leeway`, `bootstrap_admin` 은 더 이상 쓰지 않는다.
- 백업은 SQLite 만, `storage/backups/board-v{옛판}-{Ymd-His}.sqlite`, 최근 5개만 남긴다.
- 점검 화면은 503 + `Retry-After: 30`. 실패 뒤 재시도는 60초 간격.
- 테스트: `./vendor/bin/phpunit` 전부 초록. 기존 311개는 그대로 통과해야 한다.
- 커밋 메시지는 한국어 `feat:`/`fix:`/`test:`/`docs:` 접두어, 끝에 `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- 템플릿 출력은 전부 `$this->e()` 를 거친다(`templates/default/README.txt` 규칙).

## File Structure

| 파일 | 책임 |
|---|---|
| `src/Db/Schema.php` (수정) | `stamp()` 공개, `storedStamp()` 추가 |
| `src/Db/MaintenanceRequired.php` (신규) | 옮기지 못했을 때 던지는 예외. 종류(busy/failed)와 백업 경로 |
| `src/Db/SchemaUpgrader.php` (신규) | 도장 비교 → 잠금 → 백업 → 마이그레이션 → 기록. `status()` 로 관리 콘솔 표시값 |
| `src/Web/MaintenancePage.php` (신규) | 503 점검 HTML |
| `src/Web/BasePath.php` (수정) | `siblingUrl()` — index.php 가 install.php 주소를 만들 때 |
| `src/App.php` (수정) | `storageDir()`, `schemaUpgrader()` |
| `src/Web/Kernel.php` (수정) | `ensureCurrent()` → `schemaUpgrader()->run()` |
| `public/index.php` (수정) | 설정 없으면 install.php 로 302, `MaintenanceRequired` 잡기 |
| `src/Web/Controller/AdminCmsController.php`, `templates/default/admin/settings.php`, `public/themes/default/theme.css` (수정) | "데이터 구조" 카드 |
| `src/Install/ServerCheck.php` (신규) | 1단계 점검 목록 |
| `src/Install/DbSetup.php` (신규) | 2단계: 칸 → DSN, 접속 시험 |
| `src/Install/InstallSession.php` (신규) | 단계 게이트와 값 보관 |
| `src/Install/Installer.php` (재작성) | 3·4단계 검증, 5단계 마무리 |
| `public/install.php` (재작성) | 단계 라우팅 + HTML |
| `config/config.sample.php`, `README.md`, `.gitignore` (수정) | 설정 키 정리, 설치 안내, backups 무시 |

---

### Task 1: Schema 도장 공개 + SchemaUpgrader + MaintenanceRequired

**Files:**
- Modify: `src/Db/Schema.php:59-89`
- Create: `src/Db/MaintenanceRequired.php`
- Create: `src/Db/SchemaUpgrader.php`
- Test: `tests/Db/SchemaUpgraderTest.php`

**Interfaces:**
- Consumes: `GnuCms\Db\Connection` (`selectOne`, `execute`, `q`, `pdo`, `dialect()->name()`), `GnuCms\Db\Schema` (`migrateAll()`, `VERSION`), `GnuCms\Support\Clock::now()`.
- Produces:
  - `Schema::stamp(): string` (public), `Schema::storedStamp(): ?string`
  - `MaintenanceRequired::__construct(string $kind, ?string $backup = null, ?\Throwable $previous = null)`, `kind(): string` (`MaintenanceRequired::BUSY|FAILED`), `backup(): ?string`
  - `SchemaUpgrader::__construct(Connection $db, string $storageDir, ?callable $migrate = null, ?callable $log = null)`, `run(): void` (throws `MaintenanceRequired`), `status(): array{version, stamp, upgraded_at: ?string, backup: ?string, can_backup: bool, keep: int, backups: list<array{name: string, size: int, mtime: int}>}`, 상수 `KEEP_BACKUPS = 5`, `RETRY_AFTER_SECONDS = 60`.

- [ ] **Step 1: Schema 의 stamp() 를 공개하고 storedStamp() 를 더한다**

`src/Db/Schema.php` 에서 `private function stamp(): string` 을 `public function stamp(): string` 으로 바꾸고, `ensureCurrent()` 를 다음으로 교체한다:

```php
    /** DB 에 적힌 도장. site_settings 가 없는 아주 오래된 설치면 null. */
    public function storedStamp(): ?string
    {
        try {
            $row = $this->db->selectOne(
                'SELECT setting_value FROM ' . $this->db->q('site_settings') . ' WHERE setting_key = ?',
                ['schema_version']
            );
        } catch (DomainError $e) {
            return null;
        }

        return $row === null ? null : (string) $row['setting_value'];
    }

    /**
     * DB 스키마를 코드에 맞춘다. 이미 최신이면 설정값 하나만 읽고 끝난다.
     * 운영 요청 경로는 SchemaUpgrader::run() 이 백업·잠금을 두르고 이 일을 한다.
     * 이 메서드는 설치기(기존 DB 이어 쓰기)와 테스트가 쓴다.
     */
    public function ensureCurrent(): void
    {
        if ($this->storedStamp() === $this->stamp()) {
            return;
        }

        $this->migrateAll();
    }
```

- [ ] **Step 2: 기존 스위트가 그대로 통과하는지 본다**

Run: `./vendor/bin/phpunit --filter SchemaTest`
Expected: OK (변경은 이름 공개와 같은 동작의 재배치뿐).

- [ ] **Step 3: 실패하는 테스트를 쓴다**

`tests/Db/SchemaUpgraderTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Connection;
use GnuCms\Db\MaintenanceRequired;
use GnuCms\Db\Schema;
use GnuCms\Db\SchemaUpgrader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SchemaUpgraderTest extends TestCase
{
    private string $storage;
    private Connection $db;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . '/' . GNUCMS_ID . '-upgrader-' . bin2hex(random_bytes(4));
        mkdir($this->storage, 0775, true);
        $this->db = Connection::create(['dsn' => 'sqlite::memory:']);
        (new Schema($this->db))->create();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage . '/backups/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->storage . '/backups');
        foreach (glob($this->storage . '/logs/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->storage . '/logs');
        @unlink($this->storage . '/upgrade.lock');
        @unlink($this->storage . '/upgrade-failed.json');
        @rmdir($this->storage);
    }

    public function testDoesNothingWhenStampMatches(): void
    {
        $calls = 0;
        $this->upgrader(function () use (&$calls): void { $calls++; })->run();

        self::assertSame(0, $calls);
        self::assertDirectoryDoesNotExist($this->storage . '/backups');
    }

    public function testUpgradesWithBackupAndRecordsStamp(): void
    {
        $this->setStoredStamp('9.oldhash');

        $this->upgrader()->run();

        $schema = new Schema($this->db);
        self::assertSame($schema->stamp(), $schema->storedStamp());
        $backups = glob($this->storage . '/backups/*.sqlite') ?: [];
        self::assertCount(1, $backups);
        self::assertMatchesRegularExpression('~/board-v9-\d{8}-\d{6}\.sqlite$~', $backups[0]);
        self::assertSame($backups[0], $this->setting('schema_backup'));
        self::assertNotNull($this->setting('schema_upgraded_at'));
        // 백업은 열 수 있는 SQLite 파일이고 표가 들어 있다.
        $copy = Connection::create(['dsn' => 'sqlite:' . $backups[0]]);
        self::assertTrue((new Schema($copy))->exists());
        self::assertFileDoesNotExist($this->storage . '/upgrade-failed.json');
    }

    public function testKeepsOnlyFiveBackups(): void
    {
        mkdir($this->storage . '/backups', 0775, true);
        for ($i = 1; $i <= 5; $i++) {
            touch($this->storage . '/backups/board-v1-20260101-00000' . $i . '.sqlite');
        }
        $this->setStoredStamp('9.oldhash');

        $this->upgrader()->run();

        $names = array_map('basename', glob($this->storage . '/backups/*.sqlite') ?: []);
        self::assertCount(5, $names);
        self::assertNotContains('board-v1-20260101-000001.sqlite', $names);
        self::assertContains('board-v1-20260101-000005.sqlite', $names);
    }

    public function testFailureWritesMarkerAndThrows(): void
    {
        $this->setStoredStamp('9.oldhash');
        $lines = [];
        $upgrader = $this->upgrader(
            static function (): void { throw new RuntimeException('column boom'); },
            static function (string $line) use (&$lines): void { $lines[] = $line; }
        );

        try {
            $upgrader->run();
            self::fail('MaintenanceRequired 가 나와야 한다');
        } catch (MaintenanceRequired $e) {
            self::assertSame(MaintenanceRequired::FAILED, $e->kind());
            self::assertNotNull($e->backup());
            self::assertFileExists((string) $e->backup());
        }

        self::assertSame('9.oldhash', (new Schema($this->db))->storedStamp());
        $marker = json_decode((string) file_get_contents($this->storage . '/upgrade-failed.json'), true);
        self::assertSame('column boom', $marker['message']);
        self::assertEqualsWithDelta(time(), $marker['at'], 5);
        self::assertStringContainsString('column boom', implode("\n", $lines));
    }

    public function testRecentFailureSkipsRetry(): void
    {
        $this->setStoredStamp('9.oldhash');
        file_put_contents($this->storage . '/upgrade-failed.json', json_encode(['at' => time(), 'message' => 'x', 'backup' => '/tmp/b.sqlite']));
        $calls = 0;

        try {
            $this->upgrader(function () use (&$calls): void { $calls++; })->run();
            self::fail('MaintenanceRequired 가 나와야 한다');
        } catch (MaintenanceRequired $e) {
            self::assertSame(MaintenanceRequired::FAILED, $e->kind());
            self::assertSame('/tmp/b.sqlite', $e->backup());
        }
        self::assertSame(0, $calls);
    }

    public function testOldFailureIsRetriedAndMarkerRemovedOnSuccess(): void
    {
        $this->setStoredStamp('9.oldhash');
        file_put_contents($this->storage . '/upgrade-failed.json', json_encode(['at' => time() - 61, 'message' => 'x', 'backup' => null]));

        $this->upgrader()->run();

        self::assertFileDoesNotExist($this->storage . '/upgrade-failed.json');
        $schema = new Schema($this->db);
        self::assertSame($schema->stamp(), $schema->storedStamp());
    }

    public function testBusyWhenAnotherRequestHoldsTheLock(): void
    {
        $this->setStoredStamp('9.oldhash');
        $held = fopen($this->storage . '/upgrade.lock', 'c');
        self::assertTrue(flock($held, LOCK_EX));
        $calls = 0;

        try {
            $this->upgrader(function () use (&$calls): void { $calls++; })->run();
            self::fail('MaintenanceRequired 가 나와야 한다');
        } catch (MaintenanceRequired $e) {
            self::assertSame(MaintenanceRequired::BUSY, $e->kind());
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }
        self::assertSame(0, $calls);
    }

    public function testStatusListsBackupsNewestFirst(): void
    {
        mkdir($this->storage . '/backups', 0775, true);
        touch($this->storage . '/backups/board-v8-20260101-000000.sqlite');
        touch($this->storage . '/backups/board-v9-20260201-000000.sqlite');

        $status = $this->upgrader()->status();

        self::assertSame(Schema::VERSION, $status['version']);
        self::assertSame((new Schema($this->db))->stamp(), $status['stamp']);
        self::assertTrue($status['can_backup']);
        self::assertSame(5, $status['keep']);
        self::assertNull($status['upgraded_at']);
        self::assertSame(
            ['board-v9-20260201-000000.sqlite', 'board-v8-20260101-000000.sqlite'],
            array_column($status['backups'], 'name')
        );
    }

    private function upgrader(?callable $migrate = null, ?callable $log = null): SchemaUpgrader
    {
        return new SchemaUpgrader($this->db, $this->storage, $migrate, $log ?? static function (string $line): void {});
    }

    private function setStoredStamp(string $stamp): void
    {
        $this->db->execute('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', [$stamp, 'schema_version']);
    }

    private function setting(string $key): ?string
    {
        $row = $this->db->selectOne('SELECT setting_value FROM site_settings WHERE setting_key = ?', [$key]);
        return $row === null ? null : (string) $row['setting_value'];
    }
}
```

- [ ] **Step 4: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Db/SchemaUpgraderTest.php`
Expected: 오류 — `Class "GnuCms\Db\SchemaUpgrader" not found`.

- [ ] **Step 5: MaintenanceRequired 를 만든다**

`src/Db/MaintenanceRequired.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Db;

use RuntimeException;
use Throwable;

/**
 * 스키마를 새 판으로 옮기지 못해 요청을 처리할 수 없을 때 던진다.
 * Slim 바깥(Kernel::create)에서 나므로 public/index.php 가 잡아 점검 화면을 낸다.
 */
final class MaintenanceRequired extends RuntimeException
{
    public const BUSY = 'busy';
    public const FAILED = 'failed';

    private string $kind;
    private ?string $backup;

    public function __construct(string $kind, ?string $backup = null, ?Throwable $previous = null)
    {
        parent::__construct(
            $kind === self::BUSY
                ? '데이터 구조를 새 판으로 옮기는 중입니다.'
                : '데이터 구조를 새 판으로 옮기지 못했습니다.',
            0,
            $previous
        );
        $this->kind = $kind;
        $this->backup = $backup;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    /** 옮기기 전에 만든 백업 파일 경로. 없으면 null. */
    public function backup(): ?string
    {
        return $this->backup;
    }
}
```

- [ ] **Step 6: SchemaUpgrader 를 만든다**

`src/Db/SchemaUpgrader.php`:

```php
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
            @file_put_contents($dir . '/error.log', '[' . gmdate('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
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
        $lock = @fopen($this->storageDir . '/upgrade.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }
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
                $backup = $this->backup($stored);
                ($this->migrate)();
                $this->upsertSetting('schema_upgraded_at', Clock::now());
                $this->upsertSetting('schema_backup', $backup ?? '');
                @unlink($this->failurePath());
            } catch (Throwable $e) {
                ($this->log)('[schema-upgrade] ' . get_class($e) . ': ' . $e->getMessage());
                $this->writeFailure($e->getMessage(), $backup);
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
        $backup = $this->setting('schema_backup');

        return [
            'version'     => Schema::VERSION,
            'stamp'       => (new Schema($this->db))->stamp(),
            'upgraded_at' => $this->setting('schema_upgraded_at'),
            'backup'      => $backup === null || $backup === '' ? null : $backup,
            'can_backup'  => $this->db->dialect()->name() === 'sqlite',
            'keep'        => self::KEEP_BACKUPS,
            'backups'     => $backups,
        ];
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

    /** 최근 KEEP_BACKUPS 개만 남긴다. 이름에 일시가 들어 있어 이름 역순이 최신순이다. */
    private function prune(): void
    {
        foreach (array_slice($this->backupFiles(), self::KEEP_BACKUPS) as $old) {
            @unlink($old);
        }
    }

    /** @return string[] 최신순 */
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

    /** @return array{at: int, message: string, backup: ?string}|null */
    private function readFailure(): ?array
    {
        if (!is_file($this->failurePath())) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->failurePath()), true);

        return is_array($data) ? $data : null;
    }

    private function writeFailure(string $message, ?string $backup): void
    {
        @file_put_contents(
            $this->failurePath(),
            json_encode(['at' => time(), 'message' => $message, 'backup' => $backup], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function setting(string $key): ?string
    {
        try {
            $row = $this->db->selectOne(
                'SELECT setting_value FROM ' . $this->db->q('site_settings') . ' WHERE setting_key = ?',
                [$key]
            );
        } catch (DomainError $e) {
            return null;
        }

        return $row === null ? null : (string) $row['setting_value'];
    }

    private function upsertSetting(string $key, string $value): void
    {
        $table = $this->db->q('site_settings');
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
```

- [ ] **Step 7: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Db/SchemaUpgraderTest.php`
Expected: OK (8 tests).

`VACUUM INTO` 는 SQLite 3.27+ 이다. 서버의 `php -r 'echo SQLite3::version()["versionString"];'` 가 그보다 낮으면 실패하는데, 이 서버(PHP 8.4)는 문제없다.

- [ ] **Step 8: 커밋**

```bash
git add src/Db/Schema.php src/Db/MaintenanceRequired.php src/Db/SchemaUpgrader.php tests/Db/SchemaUpgraderTest.php
git commit -m "feat: 스키마를 옮기기 전에 백업하고 실패를 기록하는 SchemaUpgrader

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: 점검 화면과 진입점 연결

**Files:**
- Create: `src/Web/MaintenancePage.php`
- Modify: `src/Web/BasePath.php` (메서드 하나 추가)
- Modify: `src/App.php` (메서드 둘 추가)
- Modify: `src/Web/Kernel.php:30-31`
- Modify: `public/index.php`
- Test: `tests/Web/MaintenancePageTest.php`, `tests/Web/BasePathTest.php` (추가), `tests/Web/KernelUpgradeTest.php`

**Interfaces:**
- Consumes: `MaintenanceRequired`, `SchemaUpgrader` (Task 1).
- Produces: `MaintenancePage::html(MaintenanceRequired $e): string`, `MaintenancePage::send(MaintenanceRequired $e): void`; `BasePath::siblingUrl(string $scriptName, string $file): string`; `App::storageDir(): string`, `App::schemaUpgrader(): SchemaUpgrader`.

- [ ] **Step 1: 실패하는 테스트 셋을 쓴다**

`tests/Web/MaintenancePageTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Db\MaintenanceRequired;
use GnuCms\Web\MaintenancePage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MaintenancePageTest extends TestCase
{
    public function testBusyPageAsksToReload(): void
    {
        $html = MaintenancePage::html(new MaintenanceRequired(MaintenanceRequired::BUSY));

        self::assertStringContainsString('옮기는 중입니다', $html);
        self::assertStringContainsString('새로고침', $html);
        self::assertStringNotContainsString('error.log', $html);
    }

    public function testFailedPageNamesLogAndBackupButHidesCause(): void
    {
        $e = new MaintenanceRequired(
            MaintenanceRequired::FAILED,
            '/srv/site/storage/backups/board-v9-20260830-010203.sqlite',
            new RuntimeException('SQLSTATE[HY000] secret-detail')
        );

        $html = MaintenancePage::html($e);

        self::assertStringContainsString('옮기지 못했습니다', $html);
        self::assertStringContainsString('storage/logs/error.log', $html);
        self::assertStringContainsString('board-v9-20260830-010203.sqlite', $html);
        self::assertStringNotContainsString('secret-detail', $html);
    }

    public function testFailedPageWithoutBackupOmitsBackupLine(): void
    {
        $html = MaintenancePage::html(new MaintenanceRequired(MaintenanceRequired::FAILED));

        self::assertStringNotContainsString('백업', $html);
    }
}
```

`tests/Web/BasePathTest.php` 에 메서드를 더한다(기존 클래스 안, 마지막 메서드 뒤):

```php
    public function testSiblingUrlReplacesScriptFileName(): void
    {
        self::assertSame('/install.php', BasePath::siblingUrl('/index.php', 'install.php'));
        self::assertSame('/board/install.php', BasePath::siblingUrl('/board/index.php', 'install.php'));
        self::assertSame('/install.php', BasePath::siblingUrl('', 'install.php'));
        self::assertSame('/install.php', BasePath::siblingUrl('index.php', 'install.php'));
    }
```

`tests/Web/KernelUpgradeTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Db\MaintenanceRequired;
use GnuCms\Db\Schema;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class KernelUpgradeTest extends WebTestCase
{
    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir() . '/' . GNUCMS_ID . '-kernel-upgrade-' . bin2hex(random_bytes(4));
        mkdir($this->storage, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage . '/backups/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->storage . '/backups');
        @unlink($this->storage . '/upgrade.lock');
        @rmdir($this->storage);
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testOldStampIsUpgradedOnFirstRequest(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['storage' => ['dir' => $this->storage]]);
        $app->db()->execute('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', ['9.oldhash', 'schema_version']);

        $response = $this->get($app, '/');

        self::assertSame(200, $response->getStatusCode());
        $schema = new Schema($app->db());
        self::assertSame($schema->stamp(), $schema->storedStamp());
        if ($app->db()->dialect()->name() === 'sqlite') {
            self::assertCount(1, glob($this->storage . '/backups/board-v9-*.sqlite') ?: []);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testLockedUpgradeRaisesMaintenance(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['storage' => ['dir' => $this->storage]]);
        $app->db()->execute('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', ['9.oldhash', 'schema_version']);
        $held = fopen($this->storage . '/upgrade.lock', 'c');
        flock($held, LOCK_EX);

        try {
            $this->expectException(MaintenanceRequired::class);
            $this->get($app, '/');
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }
    }
}
```

`WebTestCase` 에 `setUp/tearDown` 이 없으면 `parent::` 호출은 PHPUnit 의 빈 메서드로 간다. 문제없다.

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Web/MaintenancePageTest.php tests/Web/BasePathTest.php tests/Web/KernelUpgradeTest.php`
Expected: MaintenancePage 클래스 없음, `siblingUrl` 없음, KernelUpgrade 는 `testOldStamp…` 가 통과할 수 있으나(ensureCurrent 가 이미 옮기므로) 백업 단언에서 실패, `testLocked…` 는 예외가 안 나 실패.

- [ ] **Step 3: MaintenancePage 를 만든다**

`src/Web/MaintenancePage.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\Db\MaintenanceRequired;

/**
 * 스키마를 옮기는 중이거나 옮기지 못했을 때 내는 503 화면.
 * DB·테마·Slim 없이 그린다. 방문자에게 오류 원문은 보이지 않는다.
 */
final class MaintenancePage
{
    public static function send(MaintenanceRequired $e): void
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 30');
        header('Cache-Control: no-store');
        echo self::html($e);
    }

    public static function html(MaintenanceRequired $e): string
    {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $name = $h(GNUCMS);
        if ($e->kind() === MaintenanceRequired::BUSY) {
            $title = '잠시만 기다려 주세요';
            $body = '<p>데이터 구조를 새 판으로 옮기는 중입니다. 잠시 뒤 새로고침해 주세요.</p>';
        } else {
            $title = '점검이 필요합니다';
            $body = '<p>데이터 구조를 새 판으로 옮기지 못했습니다. 관리자가 <code>storage/logs/error.log</code> 를 확인해야 합니다.</p>';
            if ($e->backup() !== null) {
                $body .= '<p>옮기기 전 백업: <code>' . $h(basename($e->backup())) . '</code> (<code>storage/backups/</code>)</p>';
            }
        }

        return '<!doctype html><html lang="ko"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta http-equiv="refresh" content="30">'
            . '<title>' . $h($title) . ' · ' . $name . '</title>'
            . '<style>'
            . ':root{color-scheme:light dark;--bg:#f4f8fd;--panel:#fff;--fg:#0f172a;--muted:#64748b;--line:#dbe4f0;--primary:#2f7fe0}'
            . '@media(prefers-color-scheme:dark){:root{--bg:#0b1220;--panel:#111a2b;--fg:#e5edf8;--muted:#94a3b8;--line:#243043}}'
            . '*{box-sizing:border-box}body{margin:0;padding:64px 16px;background:var(--bg);color:var(--fg);font:15px/1.7 system-ui,-apple-system,"Segoe UI","Noto Sans KR",sans-serif}'
            . 'main{max-width:560px;margin:auto;padding:36px;border:1px solid var(--line);border-radius:20px;background:var(--panel)}'
            . 'h1{margin:0 0 12px;font-size:26px;letter-spacing:-.03em}p{margin:0 0 10px}code{padding:2px 6px;border-radius:6px;background:rgba(47,127,224,.12)}'
            . '.brand{color:var(--primary);font-weight:800;margin-bottom:22px}'
            . '</style></head><body><main><div class="brand">' . $name . '</div><h1>' . $h($title) . '</h1>' . $body . '</main></body></html>';
    }
}
```

- [ ] **Step 4: BasePath::siblingUrl 을 더한다**

`src/Web/BasePath.php` 의 마지막 메서드 뒤에:

```php
    /**
     * 같은 폴더의 다른 스크립트 주소. index.php 가 설치기로 보낼 때 쓴다.
     * "/board/index.php" + "install.php" → "/board/install.php". 스크립트 이름이 비면 루트로 본다.
     */
    public static function siblingUrl(string $scriptName, string $file): string
    {
        $dir = str_replace('\\', '/', dirname($scriptName));
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return '/' . $file;
        }

        return rtrim($dir, '/') . '/' . $file;
    }
```

- [ ] **Step 5: App 에 storageDir()/schemaUpgrader() 를 더한다**

`src/App.php` 의 `db()` 메서드 바로 뒤에:

```php
    /** storage/ 절대 경로. 설정 storage.dir 가 있으면 그것을 쓴다(테스트·특수 배치용). */
    public function storageDir(): string
    {
        return rtrim((string) $this->config('storage.dir', dirname(__DIR__) . '/storage'), '/');
    }

    public function schemaUpgrader(): SchemaUpgrader
    {
        return new SchemaUpgrader($this->db(), $this->storageDir());
    }
```

파일 위 `use` 목록에 `use GnuCms\Db\SchemaUpgrader;` 를 더한다.

- [ ] **Step 6: Kernel 이 업그레이더를 부르게 한다**

`src/Web/Kernel.php` 30–31행을 교체:

```php
        // 배포 뒤 첫 요청에서 스스로 새 판으로 옮긴다. 백업·잠금·실패 기록은 SchemaUpgrader 가 한다.
        // 못 옮기면 MaintenanceRequired 가 나고 public/index.php 가 점검 화면을 낸다.
        $app->schemaUpgrader()->run();
```

`use GnuCms\Db\Schema;` 가 Kernel 안에서 더 이상 안 쓰이면 지운다: `grep -n "Schema" src/Web/Kernel.php`.

- [ ] **Step 7: index.php 를 고친다**

`public/index.php` 를 다음으로 바꾼다:

```php
<?php

declare(strict_types=1);

use GnuCms\App;
use GnuCms\Db\MaintenanceRequired;
use GnuCms\Web\BasePath;
use GnuCms\Web\Kernel;
use GnuCms\Web\MaintenancePage;

ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    // 아직 설치 전이다. 설치기가 있으면 그리로 보내고, 없으면 무엇을 해야 하는지만 알린다.
    if (is_file(__DIR__ . '/install.php')) {
        header('Location: ' . BasePath::siblingUrl($scriptName, 'install.php'), true, 302);
        exit;
    }
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><p>설치가 필요합니다. public/install.php 를 올리고 브라우저로 여세요.</p>';
    exit;
}

/** @var array $config */
$config = require $configFile;

// mod_rewrite 가 있으면 SCRIPT_NAME 이 REQUEST_URI 에 나타나지 않는다.
// 없으면 /index.php/b/free 형태로 들어오므로 그만큼을 기준 경로로 잘라낸다.
$basePath = BasePath::resolve($scriptName, $requestUri);

// rewrite 가 없는 호스팅에서 사람이 가장 먼저 입력할 만한 주소가 바로 뒤에 아무것도
// 붙지 않은 "/index.php" 다. 그 경우 라우트 "/" 와 맞지 않으므로(=/index.php/ 만
// 맞는다) 슬래시를 붙여 다시 보낸다. 결정 로직은 BasePath::redirectTarget() 에
// 뽑아 두고 표로 테스트한다 (tests/Web/BasePathTest.php).
$redirectTarget = BasePath::redirectTarget($scriptName, $requestUri);
if ($redirectTarget !== null) {
    header('Location: ' . $redirectTarget, true, 302);
    exit;
}

// 템플릿 변경이 즉시 반영되도록 운영 환경에서도 파일 캐시를 사용하지 않는다.
try {
    Kernel::create(new App($config), __DIR__ . '/../templates', $basePath)->run();
} catch (MaintenanceRequired $e) {
    // 스키마를 옮기는 중이거나 옮기지 못했다. Slim 바깥에서 나므로 여기서 화면을 낸다.
    MaintenancePage::send($e);
}
```

- [ ] **Step 8: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Web/MaintenancePageTest.php tests/Web/BasePathTest.php tests/Web/KernelUpgradeTest.php tests/Web/EntryPointTest.php`
Expected: OK.

- [ ] **Step 9: 전체 스위트**

Run: `./vendor/bin/phpunit`
Expected: OK. `SchemaTest` 의 `ensureCurrent()` 호출은 그대로 남아 있어 통과한다.

- [ ] **Step 10: 커밋**

```bash
git add src/Web/MaintenancePage.php src/Web/BasePath.php src/App.php src/Web/Kernel.php public/index.php tests/Web/MaintenancePageTest.php tests/Web/BasePathTest.php tests/Web/KernelUpgradeTest.php
git commit -m "feat: 첫 요청에서 백업하며 옮기고 못 옮기면 점검 화면을 낸다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: 관리 콘솔 "데이터 구조" 카드

**Files:**
- Modify: `src/Web/Controller/AdminCmsController.php:23-50`
- Modify: `templates/default/admin/settings.php` (폼 `</section>` 뒤)
- Modify: `public/themes/default/theme.css` (끝에 추가)
- Test: `tests/Web/AdminPageTest.php` (메서드 추가)

**Interfaces:**
- Consumes: `App::schemaUpgrader()->status()` (Task 2).
- Produces: 템플릿 데이터 `schema` (status() 배열 그대로).

- [ ] **Step 1: 실패하는 테스트를 쓴다**

`tests/Web/AdminPageTest.php` 클래스 끝에:

```php
    #[DataProvider('connectionProvider')]
    public function testSettingsPageShowsSchemaStatus(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);

        $body = $this->body($this->get($app, '/admin/settings'));

        self::assertStringContainsString('데이터 구조', $body);
        self::assertStringContainsString('<dt>판 번호</dt><dd>' . \GnuCms\Db\Schema::VERSION . ' ', $body);
        self::assertStringContainsString('설치 이후 없음', $body);
    }
```

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit --filter testSettingsPageShowsSchemaStatus`
Expected: FAIL — '데이터 구조' 없음.

- [ ] **Step 3: 컨트롤러가 status() 를 넘긴다**

`src/Web/Controller/AdminCmsController.php` 의 `settingsForm()` 렌더 데이터에 `'schema' => $this->app->schemaUpgrader()->status(),` 를 더하고, `settings()` 의 422 재렌더 데이터에도 같은 줄을 더한다. 두 곳 모두 배열 마지막 원소로 넣는다.

- [ ] **Step 4: 템플릿 카드를 더한다**

`templates/default/admin/settings.php` 에서 폼을 감싼 `</section>` 바로 뒤, `<?php $this->stop() ?>` 앞에:

```php
<section class="card schema-card">
  <div class="card-body">
    <h2 class="card-title"><?= $this->icon('shield', 18) ?> 데이터 구조</h2>
    <p class="card-sub">코드를 새로 올리면 첫 요청에서 스스로 새 판으로 옮깁니다. SQLite 는 옮기기 전에 백업합니다.</p>
    <dl class="schema-facts">
      <div><dt>판 번호</dt><dd><?= $this->e($schema['version']) ?> <small class="schema-stamp"><?= $this->e($schema['stamp']) ?></small></dd></div>
      <div><dt>마지막으로 옮긴 시각</dt><dd><?= $schema['upgraded_at'] !== null ? $this->e($schema['upgraded_at']) . ' UTC' : '설치 이후 없음' ?></dd></div>
    </dl>
    <?php if (!$schema['can_backup']): ?>
      <p class="schema-note">MySQL/MariaDB는 앱이 백업하지 못합니다. mysqldump 같은 DB 도구로 백업하세요.</p>
    <?php elseif ($schema['backups'] === []): ?>
      <p class="schema-note">아직 백업이 없습니다. 판이 바뀔 때 <code>storage/backups/</code> 에 최근 <?= (int) $schema['keep'] ?>개까지 남깁니다.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="table table-sm schema-backups">
          <thead><tr><th>백업 파일</th><th>크기</th><th>만든 시각</th></tr></thead>
          <tbody>
          <?php foreach ($schema['backups'] as $backup): ?>
            <tr>
              <td><code><?= $this->e($backup['name']) ?></code></td>
              <td><?= $this->e(number_format($backup['size'] / 1024, 1)) ?> KB</td>
              <td><?= $this->e(gmdate('Y-m-d H:i', $backup['mtime'])) ?> UTC</td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <p class="schema-note">되돌리려면 사이트를 잠시 멈추고 <code>storage/board.sqlite</code> 를 백업 파일로 바꿉니다.</p>
    <?php endif ?>
  </div>
</section>
```

같은 파일의 템플릿 안내 문구 `선택한 템플릿에 없는 화면과 파일은 default 템플릿을 사용합니다.` 는 지금 코드와 다르므로(테마 간 폴백 없음) `템플릿은 화면 전부를 가집니다. 정적 파일만 없으면 default 의 것을 씁니다.` 로 고친다.

- [ ] **Step 5: CSS**

`public/themes/default/theme.css` 끝에:

```css
/* 관리 콘솔 > 사이트 설정 > 데이터 구조 */
.schema-card { margin-top: 1.25rem; }
.schema-facts { display: grid; gap: .5rem 1.5rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); margin: .75rem 0 1rem; }
.schema-facts dt { font-size: .8rem; color: color-mix(in oklch, currentColor 60%, transparent); }
.schema-facts dd { margin: 0; font-weight: 600; }
.schema-stamp { font-weight: 400; font-size: .75rem; opacity: .6; }
.schema-note { font-size: .875rem; opacity: .75; margin-top: .75rem; }
```

- [ ] **Step 6: 통과를 확인한다**

Run: `./vendor/bin/phpunit --filter AdminPageTest`
Expected: OK.

- [ ] **Step 7: 커밋**

```bash
git add src/Web/Controller/AdminCmsController.php templates/default/admin/settings.php public/themes/default/theme.css tests/Web/AdminPageTest.php
git commit -m "feat: 사이트 설정에 스키마 판과 백업 목록을 보인다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: ServerCheck (설치 1단계)

**Files:**
- Create: `src/Install/ServerCheck.php`
- Test: `tests/Install/ServerCheckTest.php`

**Interfaces:**
- Produces: `ServerCheck::__construct(string $configDir, string $storageDir, ?array $extensions = null, ?string $phpVersion = null, ?array $apacheModules = null)`, `run(): array{ok: bool, items: list<array{label: string, ok: bool, required: bool, note: string}>}`, 상수 `MIN_PHP = '8.1.0'`.

- [ ] **Step 1: 실패하는 테스트를 쓴다**

`tests/Install/ServerCheckTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Install;

use GnuCms\Install\ServerCheck;
use PHPUnit\Framework\TestCase;

final class ServerCheckTest extends TestCase
{
    private const ALL = ['Core', 'pdo', 'pdo_sqlite', 'mbstring', 'fileinfo', 'openssl', 'gd'];

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

    public function testMissingRequiredExtensionFails(): void
    {
        $result = $this->check(array_diff(self::ALL, ['openssl']))->run();

        self::assertFalse($result['ok']);
        $openssl = $this->item($result, 'openssl 확장');
        self::assertFalse($openssl['ok']);
        self::assertTrue($openssl['required']);
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

    public function testOldPhpFails(): void
    {
        $result = $this->check(self::ALL, '8.0.30')->run();

        self::assertFalse($result['ok']);
        self::assertFalse($this->item($result, 'PHP')['ok']);
    }

    public function testOptionalItemsDoNotBlock(): void
    {
        $result = $this->check(array_diff(self::ALL, ['gd']), null, ['mod_dir'])->run();

        self::assertTrue($result['ok']);
        self::assertFalse($this->item($result, 'gd 확장')['ok']);
        self::assertFalse($this->item($result, 'gd 확장')['required']);
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
```

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/ServerCheckTest.php`
Expected: 클래스 없음 오류.

- [ ] **Step 3: ServerCheck 를 만든다**

`src/Install/ServerCheck.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Install;

/**
 * 설치 1단계. 이 서버에서 GNUCMS 가 돌 수 있는지 항목별로 본다.
 * 확장 목록·PHP 판·아파치 모듈은 주입할 수 있어 테스트가 실제 서버에 매이지 않는다.
 */
final class ServerCheck
{
    public const MIN_PHP = '8.1.0';
    public const DRIVERS = ['pdo_sqlite', 'pdo_mysql'];

    private string $configDir;
    private string $storageDir;
    /** @var string[] */
    private array $extensions;
    private string $phpVersion;
    /** @var string[]|null null 이면 감지 불가(아파치 모듈 API 가 없는 환경) */
    private ?array $apacheModules;

    /**
     * @param string[]|null $extensions     실제 대신 쓸 확장 목록
     * @param string[]|null $apacheModules  실제 대신 쓸 아파치 모듈 목록. 생략하면 apache_get_modules() 가 있을 때만 읽는다
     */
    public function __construct(
        string $configDir,
        string $storageDir,
        ?array $extensions = null,
        ?string $phpVersion = null,
        ?array $apacheModules = null
    ) {
        $this->configDir = rtrim($configDir, '/');
        $this->storageDir = rtrim($storageDir, '/');
        $this->extensions = array_map('strtolower', $extensions ?? get_loaded_extensions());
        $this->phpVersion = $phpVersion ?? PHP_VERSION;
        $this->apacheModules = $apacheModules ?? (function_exists('apache_get_modules') ? apache_get_modules() : null);
    }

    /** @return array{ok: bool, items: list<array{label: string, ok: bool, required: bool, note: string}>} */
    public function run(): array
    {
        $items = [];
        $items[] = $this->item('PHP ' . self::MIN_PHP . ' 이상', version_compare($this->phpVersion, self::MIN_PHP, '>='), true, '지금 ' . $this->phpVersion);
        $items[] = $this->item('PDO 확장', $this->has('pdo'), true, 'DB 접속에 씁니다');

        $drivers = array_values(array_filter(self::DRIVERS, fn (string $d): bool => $this->has($d)));
        $items[] = $this->item(
            'PDO 드라이버 (sqlite·mysql 중 하나)',
            $drivers !== [],
            true,
            $drivers === [] ? '하나도 없습니다. 호스팅에 요청하세요' : '있음: ' . implode(', ', $drivers)
        );

        foreach ([
            'mbstring' => '한글 처리',
            'fileinfo' => '첨부 파일 종류 판별',
            'openssl'  => '메일 TLS 와 비밀값 암호화',
        ] as $ext => $why) {
            $items[] = $this->item($ext . ' 확장', $this->has($ext), true, $why);
        }

        $items[] = $this->item('config/ 쓰기 가능', is_dir($this->configDir) && is_writable($this->configDir), true, $this->configDir);
        $items[] = $this->item('storage/ 쓰기 가능', is_dir($this->storageDir) && is_writable($this->storageDir), true, $this->storageDir);

        $items[] = $this->item('gd 확장', $this->has('gd'), false, '없으면 사진 축소본을 만들지 못합니다');

        $rewrite = $this->apacheModules === null ? null : in_array('mod_rewrite', $this->apacheModules, true);
        $items[] = $this->item(
            'mod_rewrite',
            $rewrite ?? true,
            false,
            $rewrite === null
                ? '감지할 수 없습니다. 없으면 주소가 /index.php/… 꼴이 됩니다'
                : ($rewrite ? '깔끔한 주소를 씁니다' : '없습니다. 주소가 /index.php/… 꼴이 됩니다')
        );

        $ok = true;
        foreach ($items as $item) {
            if ($item['required'] && !$item['ok']) {
                $ok = false;
            }
        }

        return ['ok' => $ok, 'items' => $items];
    }

    private function has(string $extension): bool
    {
        return in_array(strtolower($extension), $this->extensions, true);
    }

    /** @return array{label: string, ok: bool, required: bool, note: string} */
    private function item(string $label, bool $ok, bool $required, string $note): array
    {
        return ['label' => $label, 'ok' => $ok, 'required' => $required, 'note' => $note];
    }
}
```

- [ ] **Step 4: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/ServerCheckTest.php`
Expected: OK (8 tests, root 이면 1 skipped).

- [ ] **Step 5: 커밋**

```bash
git add src/Install/ServerCheck.php tests/Install/ServerCheckTest.php
git commit -m "feat: 설치 1단계 서버 점검 목록

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: DbSetup (설치 2단계)

**Files:**
- Create: `src/Install/DbSetup.php`
- Test: `tests/Install/DbSetupTest.php`

**Interfaces:**
- Consumes: `Connection::create()`, `Schema::exists()`, `DomainError::validation()`.
- Produces: `DbSetup::TYPES` (`['sqlite' => 'SQLite', 'mysql' => 'MySQL / MariaDB']`), `DbSetup::availableTypes(?array $extensions = null): string[]`, `DbSetup::dsnFrom(array $input): array{dsn: string, username: ?string, password: ?string}`, `DbSetup::probe(array $dbConfig): array{dialect: string, has_tables: bool, has_admin: bool}`.

- [ ] **Step 1: 실패하는 테스트를 쓴다**

`tests/Install/DbSetupTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Install;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Install\DbSetup;
use PHPUnit\Framework\TestCase;

final class DbSetupTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . GNUCMS_ID . '-dbsetup-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/board.sqlite');
        @rmdir($this->dir);
    }

    public function testAvailableTypesFollowLoadedDrivers(): void
    {
        self::assertSame(['sqlite', 'mysql'], DbSetup::availableTypes(['pdo', 'pdo_sqlite', 'pdo_mysql']));
        self::assertSame([], DbSetup::availableTypes(['pdo']));
    }

    public function testSqliteDsnFromAbsolutePath(): void
    {
        $db = DbSetup::dsnFrom(['type' => 'sqlite', 'sqlite_path' => $this->dir . '/board.sqlite']);

        self::assertSame(['dsn' => 'sqlite:' . $this->dir . '/board.sqlite', 'username' => null, 'password' => null], $db);
    }

    public function testSqliteRejectsRelativePathAndUnwritableFolder(): void
    {
        $this->assertValidation(['type' => 'sqlite', 'sqlite_path' => 'storage/board.sqlite'], 'sqlite_path');
        $this->assertValidation(['type' => 'sqlite', 'sqlite_path' => '/nonexistent-' . bin2hex(random_bytes(3)) . '/board.sqlite'], 'sqlite_path');
    }

    public function testMysqlDsnIsAssembled(): void
    {
        $db = DbSetup::dsnFrom(['type' => 'mysql', 'host' => 'db.local', 'port' => '3307', 'name' => 'site', 'user' => 'u', 'password' => 'p']);

        self::assertSame('mysql:host=db.local;port=3307;dbname=site;charset=utf8mb4', $db['dsn']);
        self::assertSame('u', $db['username']);
        self::assertSame('p', $db['password']);
    }

    public function testMysqlDsnUsesDefaultPort(): void
    {
        $db = DbSetup::dsnFrom(['type' => 'mysql', 'host' => 'localhost', 'name' => 'site', 'user' => 'u']);

        self::assertSame('mysql:host=localhost;port=3306;dbname=site;charset=utf8mb4', $db['dsn']);
        self::assertSame('', $db['password']);
    }

    public function testServerFieldsAreValidated(): void
    {
        try {
            DbSetup::dsnFrom(['type' => 'mysql', 'host' => 'a;b', 'port' => '70000', 'name' => '', 'user' => '']);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertSame(['host', 'port', 'name', 'user'], array_keys($e->details()));
        }
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->assertValidation(['type' => 'oracle'], 'type');
    }

    public function testProbeReportsEmptyThenTablesThenAdmin(): void
    {
        $config = DbSetup::dsnFrom(['type' => 'sqlite', 'sqlite_path' => $this->dir . '/board.sqlite']);

        $empty = DbSetup::probe($config);
        self::assertSame(['dialect' => 'sqlite', 'has_tables' => false, 'has_admin' => false], $empty);

        $db = Connection::create($config);
        (new Schema($db))->create();
        self::assertSame(['dialect' => 'sqlite', 'has_tables' => true, 'has_admin' => false], DbSetup::probe($config));

        $db->insert('users', [
            'email' => 'a@example.com', 'email_verified' => 1, 'password_hash' => 'x', 'display_name' => '관리자',
            'is_admin' => 1, 'status' => 'active', 'session_epoch' => 0,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);
        self::assertTrue(DbSetup::probe($config)['has_admin']);
    }

    public function testProbeFailureIsAValidationError(): void
    {
        try {
            DbSetup::probe(['dsn' => 'mysql:host=127.0.0.1;port=1;dbname=nope', 'username' => 'x', 'password' => 'y']);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertArrayHasKey('_', $e->details());
        }
    }

    private function assertValidation(array $input, string $field): void
    {
        try {
            DbSetup::dsnFrom($input);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertArrayHasKey($field, $e->details());
        }
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/DbSetupTest.php`
Expected: 클래스 없음 오류.

- [ ] **Step 3: DbSetup 을 만든다**

`src/Install/DbSetup.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Install;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;

/**
 * 설치 2단계. 종류별 칸을 DSN 으로 조립하고 접속을 시험한다.
 * 사람이 DSN 문법을 알 필요가 없게 하는 것이 목적이다.
 */
final class DbSetup
{
    public const TYPES = [
        'sqlite' => 'SQLite',
        'mysql'  => 'MySQL / MariaDB',
    ];

    private const MYSQL_PORT = 3306;

    /**
     * 이 서버에서 쓸 수 있는 종류. pdo_{종류} 확장이 있어야 한다.
     *
     * @param string[]|null $extensions 실제 대신 쓸 확장 목록
     * @return string[]
     */
    public static function availableTypes(?array $extensions = null): array
    {
        $loaded = array_map('strtolower', $extensions ?? get_loaded_extensions());

        return array_values(array_filter(
            array_keys(self::TYPES),
            static fn (string $type): bool => in_array('pdo_' . $type, $loaded, true)
        ));
    }

    /** @return array{dsn: string, username: ?string, password: ?string} */
    public static function dsnFrom(array $input): array
    {
        $type = (string) ($input['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            throw DomainError::validation(['type' => 'DB 종류를 고르세요.']);
        }

        if ($type === 'sqlite') {
            $path = trim((string) ($input['sqlite_path'] ?? ''));
            if ($path === '') {
                throw DomainError::validation(['sqlite_path' => 'SQLite 파일 경로를 적어 주세요.']);
            }
            if ($path[0] !== '/') {
                throw DomainError::validation(['sqlite_path' => '절대 경로로 적어 주세요. 예) /home/user/site/storage/board.sqlite']);
            }
            $folder = dirname($path);
            if (!is_dir($folder) || !is_writable($folder)) {
                throw DomainError::validation(['sqlite_path' => '그 폴더에 쓸 수 없습니다: ' . $folder]);
            }

            return ['dsn' => 'sqlite:' . $path, 'username' => null, 'password' => null];
        }

        $errors = [];
        $host = trim((string) ($input['host'] ?? ''));
        $portRaw = trim((string) ($input['port'] ?? ''));
        $port = $portRaw === '' ? self::MYSQL_PORT : (int) $portRaw;
        $name = trim((string) ($input['name'] ?? ''));
        $user = trim((string) ($input['user'] ?? ''));

        if ($host === '' || preg_match('/^[A-Za-z0-9_.\-]+$/', $host) !== 1) {
            $errors['host'] = '호스트는 영문·숫자·점·하이픈만 씁니다.';
        }
        if ($port < 1 || $port > 65535 || ($portRaw !== '' && (string) $port !== $portRaw)) {
            $errors['port'] = '포트는 1~65535 사이의 숫자입니다.';
        }
        if ($name === '' || preg_match('/^[A-Za-z0-9_.\-]+$/', $name) !== 1) {
            $errors['name'] = 'DB 이름은 영문·숫자·밑줄·점·하이픈만 씁니다.';
        }
        if ($user === '') {
            $errors['user'] = 'DB 계정을 적어 주세요.';
        }
        if ($errors !== []) {
            throw DomainError::validation($errors);
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';

        return ['dsn' => $dsn, 'username' => $user, 'password' => (string) ($input['password'] ?? '')];
    }

    /**
     * 접속해 보고 무엇이 들어 있는지 알려 준다. 못 붙으면 422 ('_' 칸).
     *
     * @param array{dsn: string, username: ?string, password: ?string} $dbConfig
     * @return array{dialect: string, has_tables: bool, has_admin: bool}
     */
    public static function probe(array $dbConfig): array
    {
        try {
            $db = Connection::create($dbConfig);
        } catch (DomainError $e) {
            throw DomainError::validation(['_' => $e->getMessage()]);
        }

        $hasTables = (new Schema($db))->exists();
        $hasAdmin = false;
        if ($hasTables) {
            try {
                $row = $db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->q('users') . ' WHERE is_admin = 1');
                $hasAdmin = (int) ($row['c'] ?? 0) > 0;
            } catch (DomainError $e) {
                // users 표가 없는 아주 오래된 설치. 관리자가 없다고 본다.
                $hasAdmin = false;
            }
        }

        return ['dialect' => $db->dialect()->name(), 'has_tables' => $hasTables, 'has_admin' => $hasAdmin];
    }
}
```

- [ ] **Step 4: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/DbSetupTest.php`
Expected: OK (9 tests). `testProbeFailure…` 는 포트 1 접속 거부에 1초쯤 걸릴 수 있다.

- [ ] **Step 5: 커밋**

```bash
git add src/Install/DbSetup.php tests/Install/DbSetupTest.php
git commit -m "feat: 설치 2단계 DB 칸을 DSN 으로 조립하고 접속을 시험한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: InstallSession (단계 게이트)

**Files:**
- Create: `src/Install/InstallSession.php`
- Test: `tests/Install/InstallSessionTest.php`

**Interfaces:**
- Produces: `InstallSession::__construct(array &$store)`, `LAST_STEP = 5`, `done(): int`, `complete(int $step): void`, `allowedStep(int $requested): int`, `get(string $key): ?array`, `set(string $key, array $data): void`, `reset(): void`.

- [ ] **Step 1: 실패하는 테스트를 쓴다**

`tests/Install/InstallSessionTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Install;

use GnuCms\Install\InstallSession;
use PHPUnit\Framework\TestCase;

final class InstallSessionTest extends TestCase
{
    public function testFreshSessionOnlyOpensStepOne(): void
    {
        $store = [];
        $s = new InstallSession($store);

        self::assertSame(0, $s->done());
        self::assertSame(1, $s->allowedStep(1));
        self::assertSame(1, $s->allowedStep(4));
        self::assertSame(1, $s->allowedStep(0));
    }

    public function testCompletingOpensTheNextStepOnly(): void
    {
        $store = [];
        $s = new InstallSession($store);
        $s->complete(1);
        $s->complete(2);

        self::assertSame(2, $s->done());
        self::assertSame(3, $s->allowedStep(3));
        self::assertSame(3, $s->allowedStep(5));
        self::assertSame(2, $s->allowedStep(2));
    }

    public function testCompletingLowerStepDoesNotRewind(): void
    {
        $store = [];
        $s = new InstallSession($store);
        $s->complete(3);
        $s->complete(1);

        self::assertSame(3, $s->done());
    }

    public function testAllowedStepNeverExceedsLast(): void
    {
        $store = [];
        $s = new InstallSession($store);
        $s->complete(5);

        self::assertSame(5, $s->allowedStep(9));
    }

    public function testValuesLiveInTheGivenArray(): void
    {
        $store = [];
        $s = new InstallSession($store);
        $s->set('db', ['dsn' => 'sqlite::memory:']);

        self::assertSame(['dsn' => 'sqlite::memory:'], $s->get('db'));
        self::assertNull($s->get('site'));
        self::assertSame(['dsn' => 'sqlite::memory:'], $store['data']['db']);

        $again = new InstallSession($store);
        self::assertSame(['dsn' => 'sqlite::memory:'], $again->get('db'));
    }

    public function testResetClearsEverything(): void
    {
        $store = [];
        $s = new InstallSession($store);
        $s->complete(4);
        $s->set('db', ['dsn' => 'x']);

        $s->reset();

        self::assertSame(0, $s->done());
        self::assertNull($s->get('db'));
        self::assertSame(['done' => 0], $store);
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/InstallSessionTest.php`
Expected: 클래스 없음 오류.

- [ ] **Step 3: InstallSession 을 만든다**

`src/Install/InstallSession.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Install;

/**
 * 설치 단계 사이의 값과 "어디까지 끝냈나" 를 배열(보통 $_SESSION)에 둔다.
 * hidden 칸으로 DB 비밀번호를 실어 나르지 않기 위해서다.
 */
final class InstallSession
{
    public const LAST_STEP = 5;

    /** @var array<string, mixed> */
    private array $store;

    /** @param array<string, mixed> $store 참조로 받는다. install.php 는 $_SESSION 을 넘긴다 */
    public function __construct(array &$store)
    {
        $this->store = &$store;
        if (!isset($this->store['done'])) {
            $this->store['done'] = 0;
        }
    }

    /** 끝낸 마지막 단계 번호. 아무것도 안 했으면 0. */
    public function done(): int
    {
        return (int) $this->store['done'];
    }

    public function complete(int $step): void
    {
        $this->store['done'] = max($this->done(), $step);
    }

    /** 요청한 단계가 아직 열리지 않았으면 열린 마지막 단계로 낮춘다. */
    public function allowedStep(int $requested): int
    {
        return max(1, min($requested, $this->done() + 1, self::LAST_STEP));
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        return isset($this->store['data'][$key]) && is_array($this->store['data'][$key])
            ? $this->store['data'][$key]
            : null;
    }

    /** @param array<string, mixed> $data */
    public function set(string $key, array $data): void
    {
        $this->store['data'][$key] = $data;
    }

    public function reset(): void
    {
        $this->store = ['done' => 0];
    }
}
```

- [ ] **Step 4: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/InstallSessionTest.php`
Expected: OK (6 tests).

- [ ] **Step 5: 커밋**

```bash
git add src/Install/InstallSession.php tests/Install/InstallSessionTest.php
git commit -m "feat: 설치 단계 게이트와 값 보관

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Installer 재작성 (3·4단계 검증, 5단계 마무리) + 설정 샘플 정리

**Files:**
- Rewrite: `src/Install/Installer.php`
- Modify: `config/config.sample.php`
- Modify: `.gitignore`
- Rewrite: `tests/Install/InstallerTest.php`

**Interfaces:**
- Consumes: `Connection`, `Schema::create()/ensureCurrent()`, `UserRepository` (`findByEmail`, `create`, `verifyEmail`, `uniqueDisplayName`, `displayNameHasBadChars`, `displayNameTooShort`, `displayNameRule`), `Validator` (`requiredString`, `requiredPassword`, `fail`, `check`), `Clock::now()`, `Base64Url::encode()`.
- Produces: `Installer::__construct(string $configPath, string $storageDir, ?string $installScript = null)`, `isInstalled(): bool`, `static siteFrom(array $input): array{site_name, app_url, mail_from}`, `static adminFrom(array $input): array{email, display_name, password}`, `finish(array $dbConfig, array $site, ?array $admin, bool $reuse = false): array{dialect: string, admin_email: ?string, config_path: string, self_deleted: ?bool}`.

- [ ] **Step 1: 테스트를 새로 쓴다** (기존 `tests/Install/InstallerTest.php` 를 통째로 바꾼다)

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Install;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Install\Installer;
use PHPUnit\Framework\TestCase;

final class InstallerTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/' . GNUCMS_ID . '-install-' . bin2hex(random_bytes(4));
        mkdir($this->workDir . '/config', 0775, true);
        mkdir($this->workDir . '/storage', 0775, true);
        mkdir($this->workDir . '/public', 0775, true);
        file_put_contents($this->workDir . '/public/install.php', '<?php // 설치기');
    }

    protected function tearDown(): void
    {
        foreach (['config/config.php', 'storage/board.sqlite', 'public/install.php'] as $file) {
            @unlink($this->workDir . '/' . $file);
        }
        foreach (['config', 'storage/uploads', 'storage/editor', 'storage/logs', 'storage', 'public', ''] as $dir) {
            @rmdir(rtrim($this->workDir . '/' . $dir, '/'));
        }
    }

    public function testNotInstalledInitially(): void
    {
        self::assertFalse($this->installer()->isInstalled());
    }

    public function testFinishCreatesSchemaAdminConfigAndDeletesItself(): void
    {
        $result = $this->installer()->finish($this->dbConfig(), $this->site(), $this->admin());

        self::assertSame('sqlite', $result['dialect']);
        self::assertSame('owner@example.com', $result['admin_email']);
        self::assertTrue($result['self_deleted']);
        self::assertFileDoesNotExist($this->workDir . '/public/install.php');
        self::assertTrue($this->installer()->isInstalled());

        $config = require $this->configPath();
        $db = Connection::create($config['db']);
        self::assertTrue((new Schema($db))->exists());
        self::assertSame('내 커뮤니티', $db->selectOne("SELECT setting_value FROM site_settings WHERE setting_key = 'site_name'")['setting_value']);

        $user = $db->selectOne('SELECT * FROM users WHERE email = ?', ['owner@example.com']);
        self::assertSame(1, (int) $user['is_admin']);
        self::assertSame(1, (int) $user['email_verified']);
        self::assertSame('사이트지기', $user['display_name']);
        self::assertTrue(password_verify('secret-pass-123', (string) $user['password_hash']));
        self::assertSame('1', $db->selectOne("SELECT state_value FROM site_state WHERE state_key = 'first_admin_claimed'")['state_value']);
    }

    public function testGeneratedConfigHasOnlyLiveKeys(): void
    {
        $this->installer()->finish($this->dbConfig(), $this->site(), $this->admin());
        $config = require $this->configPath();

        self::assertGreaterThanOrEqual(43, strlen($config['auth']['secret']));
        self::assertSame(['secret'], array_keys($config['auth']));
        self::assertArrayNotHasKey('cors', $config);
        self::assertArrayNotHasKey('bootstrap_admin', $config);
        self::assertSame('https://community.example.com', $config['app']['url']);
        self::assertSame('no-reply@example.com', $config['mail']['from']);
        self::assertSame($this->workDir . '/storage/editor', $config['editor']['dir']);
        self::assertDirectoryExists($config['editor']['dir']);
        self::assertFalse($config['debug']);
        self::assertSame('0640', substr(sprintf('%o', fileperms($this->configPath())), -4));
    }

    public function testReuseKeepsExistingTablesAndSkipsAdmin(): void
    {
        $db = Connection::create($this->dbConfig());
        (new Schema($db))->create();
        $db->execute("UPDATE site_settings SET setting_value = '9.old' WHERE setting_key = 'schema_version'");
        $db->insert('boards', [
            'slug' => 'free', 'name' => '자유', 'description' => '', 'list_type' => 'list', 'perm_read' => 'all',
            'perm_write' => 'member', 'perm_comment' => 'member', 'managers' => '[]', 'sort_order' => 1,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $result = $this->installer()->finish($this->dbConfig(), $this->site(), null, true);

        self::assertNull($result['admin_email']);
        self::assertSame(1, (int) $db->selectOne('SELECT COUNT(*) AS c FROM boards')['c']);
        self::assertSame((new Schema($db))->stamp(), (new Schema($db))->storedStamp());
        self::assertSame(0, (int) $db->selectOne('SELECT COUNT(*) AS c FROM users')['c']);
    }

    public function testSecondFinishIsRefused(): void
    {
        $this->installer()->finish($this->dbConfig(), $this->site(), $this->admin());

        try {
            $this->installer()->finish($this->dbConfig(), $this->site(), $this->admin());
            self::fail('두 번째 설치는 거부되어야 한다');
        } catch (DomainError $e) {
            self::assertSame(403, $e->status());
        }
    }

    public function testUnreachableDatabaseIsReported(): void
    {
        try {
            $this->installer()->finish(['dsn' => 'mysql:host=127.0.0.1;port=1;dbname=nope', 'username' => 'x', 'password' => 'y'], $this->site(), $this->admin());
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertArrayHasKey('_', $e->details());
        }
        self::assertFileDoesNotExist($this->configPath());
    }

    public function testWithoutInstallScriptSelfDeletedIsNull(): void
    {
        $installer = new Installer($this->configPath(), $this->workDir . '/storage');

        $result = $installer->finish($this->dbConfig(), $this->site(), $this->admin());

        self::assertNull($result['self_deleted']);
        self::assertFileExists($this->workDir . '/public/install.php');
    }

    public function testSiteFromValidatesUrlAndMail(): void
    {
        self::assertSame(
            ['site_name' => '내 커뮤니티', 'app_url' => 'https://community.example.com', 'mail_from' => 'no-reply@example.com'],
            Installer::siteFrom(['site_name' => '내 커뮤니티', 'app_url' => 'https://community.example.com/', 'mail_from' => 'No-Reply@example.com'])
        );

        try {
            Installer::siteFrom(['site_name' => '', 'app_url' => 'javascript:alert(1)', 'mail_from' => "bad\naddress"]);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(['site_name', 'app_url', 'mail_from'], array_keys($e->details()));
        }
    }

    public function testAdminFromValidatesLikeRegistration(): void
    {
        self::assertSame(
            ['email' => 'owner@example.com', 'display_name' => '사이트지기', 'password' => 'secret-pass-123'],
            Installer::adminFrom($this->admin() + ['password_confirmation' => 'secret-pass-123'])
        );

        try {
            Installer::adminFrom(['email' => 'nope', 'display_name' => 'a b', 'password' => 'short', 'password_confirmation' => 'other']);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(['email', 'display_name', 'password', 'password_confirmation'], array_keys($e->details()));
        }

        try {
            Installer::adminFrom(['email' => 'a@example.com', 'display_name' => '김', 'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123']);
            self::fail('짧은 표시 이름은 거부되어야 한다');
        } catch (DomainError $e) {
            self::assertArrayHasKey('display_name', $e->details());
        }
    }

    private function installer(): Installer
    {
        return new Installer($this->configPath(), $this->workDir . '/storage', $this->workDir . '/public/install.php');
    }

    private function configPath(): string
    {
        return $this->workDir . '/config/config.php';
    }

    /** @return array{dsn: string, username: ?string, password: ?string} */
    private function dbConfig(): array
    {
        return ['dsn' => 'sqlite:' . $this->workDir . '/storage/board.sqlite', 'username' => null, 'password' => null];
    }

    private function site(): array
    {
        return ['site_name' => '내 커뮤니티', 'app_url' => 'https://community.example.com', 'mail_from' => 'no-reply@example.com'];
    }

    private function admin(): array
    {
        return ['email' => 'owner@example.com', 'display_name' => '사이트지기', 'password' => 'secret-pass-123'];
    }
}
```

`boards` 표의 컬럼 이름은 `src/Db/Schema.php` 의 `CREATE TABLE boards` 를 보고 맞춘다 — 위 insert 가 NOT NULL 컬럼을 빠뜨렸으면 그 컬럼을 채운다. 위 목록에 없는 NOT NULL 컬럼이 있으면 테스트의 insert 에 더한다.

- [ ] **Step 2: 실패를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/InstallerTest.php`
Expected: `finish()`/`siteFrom()`/`adminFrom()` 없음 오류.

- [ ] **Step 3: Installer 를 다시 쓴다**

`src/Install/Installer.php` 전체:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Install;

use GnuCms\Account\UserRepository;
use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Support\Base64Url;
use GnuCms\Support\Clock;
use GnuCms\Validation\Validator;
use Throwable;

/**
 * 설치 3·4단계의 검증과 5단계의 마무리.
 * 순서: 표 → 사이트 이름 → 관리자 → config.php → install.php 삭제.
 * config.php 를 맨 마지막에 쓰므로 중간에 실패해도 "반쯤 설치된" 상태가 남지 않는다.
 */
final class Installer
{
    private string $configPath;
    private string $storageDir;
    /** @var string|null install.php 경로. null 이면 스스로 지우지 않는다 */
    private ?string $installScript;

    public function __construct(string $configPath, string $storageDir, ?string $installScript = null)
    {
        $this->configPath = $configPath;
        $this->storageDir = rtrim($storageDir, '/');
        $this->installScript = $installScript;
    }

    public function isInstalled(): bool
    {
        return is_file($this->configPath);
    }

    /** @return array{site_name: string, app_url: string, mail_from: string} */
    public static function siteFrom(array $input): array
    {
        $v = new Validator($input);
        $siteName = trim($v->requiredString('site_name', 50));
        $appUrl = rtrim($v->requiredString('app_url', 500), '/');
        $mailFrom = strtolower($v->requiredString('mail_from', 254));
        if ($appUrl !== '' && filter_var($appUrl, FILTER_VALIDATE_URL) === false) {
            $v->fail('app_url', '올바른 http 또는 https 주소를 입력해 주세요.');
        } elseif ($appUrl !== '' && !in_array((string) parse_url($appUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $v->fail('app_url', '사이트 주소는 http 또는 https로 시작해야 합니다.');
        }
        if ($mailFrom !== '' && filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
            $v->fail('mail_from', '올바른 이메일 주소를 입력해 주세요.');
        }
        $v->check();

        return ['site_name' => $siteName, 'app_url' => $appUrl, 'mail_from' => $mailFrom];
    }

    /** 회원가입과 같은 규칙. @return array{email: string, display_name: string, password: string} */
    public static function adminFrom(array $input): array
    {
        $v = new Validator($input);
        $email = strtolower(trim($v->requiredString('email', 254)));
        $name = trim($v->requiredString('display_name', 100));
        $password = $v->requiredPassword('password');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $v->fail('email', '올바른 이메일 주소를 입력해 주세요.');
        }
        if ($name !== '' && UserRepository::displayNameHasBadChars($name)) {
            $v->fail('display_name', '표시 이름은 한글·영문·숫자만 쓸 수 있습니다.');
        } elseif ($name !== '' && UserRepository::displayNameTooShort($name)) {
            $v->fail('display_name', UserRepository::displayNameRule());
        }
        if ((string) ($input['password_confirmation'] ?? '') !== (string) ($input['password'] ?? '')) {
            $v->fail('password_confirmation', '비밀번호 확인이 다릅니다.');
        }
        $v->check();

        return ['email' => $email, 'display_name' => $name, 'password' => $password];
    }

    /**
     * @param array{dsn: string, username: ?string, password: ?string} $dbConfig
     * @param array{site_name: string, app_url: string, mail_from: string} $site
     * @param array{email: string, display_name: string, password: string}|null $admin null 이면 관리자를 만들지 않는다(이어 쓰기)
     * @param bool $reuse 이미 표가 있는 DB 를 이어 쓴다. 표를 새로 만들지 않고 새 판으로 옮긴다
     * @return array{dialect: string, admin_email: ?string, config_path: string, self_deleted: ?bool}
     */
    public function finish(array $dbConfig, array $site, ?array $admin, bool $reuse = false): array
    {
        if ($this->isInstalled()) {
            throw DomainError::forbidden('이미 설치되어 있습니다. 다시 설치하려면 config/config.php 를 지우세요.');
        }

        try {
            $db = Connection::create($dbConfig);
            $schema = new Schema($db);
            if ($reuse) {
                $schema->ensureCurrent();
            } else {
                $schema->create();
            }
        } catch (Throwable $e) {
            throw DomainError::validation(['_' => 'DB 에 연결하거나 표를 만들지 못했습니다: ' . $e->getMessage()]);
        }

        $this->ensureStorageDirectories();

        $db->execute(
            'UPDATE ' . $db->q('site_settings') . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
            [$site['site_name'], Clock::now(), 'site_name']
        );

        $adminEmail = null;
        if ($admin !== null) {
            $users = new UserRepository($db);
            if ($users->findByEmail($admin['email']) !== null) {
                throw DomainError::validation(['email' => '이미 있는 이메일입니다.']);
            }
            $id = $users->create(
                $admin['email'],
                password_hash($admin['password'], PASSWORD_DEFAULT),
                $users->uniqueDisplayName($admin['display_name']),
                true
            );
            $users->verifyEmail($id);
            $db->execute(
                'UPDATE ' . $db->q('site_state') . ' SET state_value = ? WHERE state_key = ?',
                ['1', 'first_admin_claimed']
            );
            $adminEmail = $admin['email'];
        }

        $this->writeConfig($dbConfig, $site);

        $selfDeleted = null;
        if ($this->installScript !== null) {
            $selfDeleted = !is_file($this->installScript) || @unlink($this->installScript);
        }

        return [
            'dialect'      => $db->dialect()->name(),
            'admin_email'  => $adminEmail,
            'config_path'  => $this->configPath,
            'self_deleted' => $selfDeleted,
        ];
    }

    /**
     * @param array{dsn: string, username: ?string, password: ?string} $dbConfig
     * @param array{site_name: string, app_url: string, mail_from: string} $site
     */
    private function writeConfig(array $dbConfig, array $site): void
    {
        $config = [
            'app' => [
                'url' => $site['app_url'],
            ],
            'mail' => [
                'from' => $site['mail_from'],
            ],
            'oauth' => [
                'google' => ['client_id' => '', 'client_secret' => ''],
                'naver'  => ['client_id' => '', 'client_secret' => ''],
                'kakao'  => ['client_id' => '', 'client_secret' => ''],
                'github' => ['client_id' => '', 'client_secret' => ''],
            ],
            'db' => [
                'dsn'      => $dbConfig['dsn'],
                'username' => ($dbConfig['username'] ?? '') === '' ? null : $dbConfig['username'],
                'password' => ($dbConfig['password'] ?? '') === '' ? null : $dbConfig['password'],
            ],
            'auth' => [
                'secret' => Base64Url::encode(random_bytes(32)),
            ],
            'uploads' => [
                'dir'         => $this->storageDir . '/uploads',
                'max_bytes'   => 5 * 1024 * 1024,
                'allowed_ext' => [
                    'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'txt',
                    'hwp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                ],
            ],
            'editor' => [
                'dir'       => $this->storageDir . '/editor',
                'max_bytes' => 5 * 1024 * 1024,
            ],
            'log' => [
                'file' => $this->storageDir . '/logs/error.log',
            ],
            'debug' => false,
        ];

        $php = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents($this->configPath, $php, LOCK_EX) === false) {
            throw DomainError::internal('설정 파일을 쓰지 못했습니다: ' . $this->configPath);
        }
        @chmod($this->configPath, 0640);
    }

    private function ensureStorageDirectories(): void
    {
        foreach ([$this->storageDir . '/uploads', $this->storageDir . '/editor', $this->storageDir . '/logs'] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw DomainError::internal('디렉터리를 만들 수 없습니다: ' . $directory);
            }
        }
    }
}
```

- [ ] **Step 4: config.sample.php 에서 죽은 키를 뺀다**

`config/config.sample.php` 에서 다음을 지운다: `auth` 의 `ttl`·`leeway` 줄과 그 위 주석 "호스트 앱과 공유하는 시크릿…" 을 `// 세션·메일 비밀번호 암호화에 쓰는 시크릿. 32바이트 이상 임의 문자열. 설치기가 만들어 준다.` 로 바꾼다; `bootstrap_admin` 블록 전체(주석 포함); `cors` 블록 전체(주석 포함).

- [ ] **Step 5: .gitignore 에 백업·잠금·실패 표식을 더한다**

`.gitignore` 의 `# 로그` 블록 앞에:

```
# 스키마를 옮기기 전 백업과 진행 표식
/storage/backups/
/storage/upgrade.lock
/storage/upgrade-failed.json
```

- [ ] **Step 6: 통과를 확인한다**

Run: `./vendor/bin/phpunit tests/Install/`
Expected: OK (Installer 10 + ServerCheck 8 + DbSetup 9 + InstallSession 6).

- [ ] **Step 7: 커밋**

```bash
git add src/Install/Installer.php config/config.sample.php .gitignore tests/Install/InstallerTest.php
git commit -m "feat: 설치 마무리를 표·관리자·설정·자기 삭제 순서로 다시 짠다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: public/install.php 다섯 단계 화면

**Files:**
- Rewrite: `public/install.php`
- Test: `tests/Web/EntryPointTest.php` (기존 통과 유지), 수동 확인 스크립트

**Interfaces:**
- Consumes: `ServerCheck`, `DbSetup`, `InstallSession`, `Installer` (Task 4–7). `DomainError::details()`.

- [ ] **Step 1: install.php 를 다시 쓴다**

`public/install.php` 전체:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use GnuCms\Error\DomainError;
use GnuCms\Install\DbSetup;
use GnuCms\Install\Installer;
use GnuCms\Install\InstallSession;
use GnuCms\Install\ServerCheck;

$root = dirname(__DIR__);
$installer = new Installer($root . '/config/config.php', $root . '/storage', __FILE__);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** @param array<string, string> $errors */
function err(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="error">' . h($errors[$field]) . '</p>' : '';
}

function field(string $label, string $name, string $value, array $errors, string $type = 'text', string $hint = '', string $attrs = ''): string
{
    return '<label>' . h($label) . ($hint !== '' ? '<span class="hint">' . h($hint) . '</span>' : '')
        . '<input name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '" ' . $attrs . '></label>'
        . err($errors, $name);
}

function page(int $step, string $title, string $body): void
{
    $names = ['서버 점검', '데이터베이스', '사이트', '관리자', '완료'];
    $steps = '';
    foreach ($names as $i => $name) {
        $n = $i + 1;
        $cls = $n < $step ? 'done' : ($n === $step ? 'now' : '');
        $steps .= '<li class="' . $cls . '"><span>' . $n . '</span>' . h($name) . '</li>';
    }
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($title) . ' · ' . h(GNUCMS) . ' 설치</title>'
        . '<style>'
        . ':root{color-scheme:light;--bg:#f4f8fd;--panel:#fff;--fg:#0f172a;--muted:#64748b;--line:#dbe4f0;--primary:#2f7fe0;--danger:#d92d20;--ok:#1a7f4b}'
        . '@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#0b1220;--panel:#111a2b;--fg:#e5edf8;--muted:#94a3b8;--line:#243043;--primary:#6aa6f0;--danger:#ff8b81;--ok:#5ad28f}}'
        . '*{box-sizing:border-box}body{margin:0;padding:40px 16px;background:var(--bg);color:var(--fg);font:15px/1.65 system-ui,-apple-system,"Segoe UI","Noto Sans KR",sans-serif}'
        . 'main{max-width:680px;margin:auto;padding:clamp(24px,5vw,40px);border:1px solid var(--line);border-radius:20px;background:var(--panel)}'
        . '.brand{color:var(--primary);font-weight:800;margin-bottom:18px}'
        . 'ol.steps{display:flex;gap:6px;list-style:none;margin:0 0 26px;padding:0;font-size:12px;color:var(--muted)}'
        . 'ol.steps li{flex:1;padding:6px 0;border-top:3px solid var(--line)}ol.steps li span{display:block;font-weight:800}'
        . 'ol.steps li.now{border-color:var(--primary);color:var(--fg)}ol.steps li.done{border-color:var(--ok)}'
        . 'h1{margin:0 0 8px;font-size:26px;letter-spacing:-.03em}.intro{margin:0 0 22px;color:var(--muted)}'
        . 'label{display:block;margin-top:16px;font-weight:700}.hint{display:block;color:var(--muted);font-weight:400;font-size:12px}'
        . 'input,select{width:100%;margin-top:6px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--fg);font:inherit}'
        . 'input:focus,select:focus{outline:0;border-color:var(--primary);box-shadow:0 0 0 3px rgba(47,127,224,.18)}'
        . '.radios{display:flex;gap:8px;margin-top:6px}.radios label{flex:1;margin:0;padding:10px;border:1px solid var(--line);border-radius:10px;text-align:center;font-weight:600;cursor:pointer}'
        . '.radios input{width:auto;margin:0 6px 0 0}.radios label.off{opacity:.45}'
        . '.error{margin:4px 0 0;color:var(--danger);font-size:13px}.alert{padding:12px 14px;border-radius:10px;background:rgba(217,45,32,.08);color:var(--danger);margin-bottom:12px}'
        . '.notice{padding:12px 14px;border-radius:10px;background:rgba(47,127,224,.08);margin:14px 0}'
        . '.done{padding:16px 18px;border-radius:12px;background:rgba(26,127,75,.1);color:var(--ok)}'
        . 'table{width:100%;border-collapse:collapse;margin-top:8px}td,th{padding:8px 6px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}'
        . '.ok{color:var(--ok);font-weight:800}.bad{color:var(--danger);font-weight:800}.opt{color:var(--muted);font-size:12px}'
        . '.actions{display:flex;justify-content:space-between;align-items:center;margin-top:26px}'
        . 'button{min-height:44px;padding:0 20px;border:0;border-radius:10px;background:var(--primary);color:#fff;font:inherit;font-weight:750;cursor:pointer}'
        . 'a{color:var(--primary);font-weight:700}code{padding:2px 5px;border-radius:5px;background:rgba(47,127,224,.12)}'
        . 'dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px}dt{color:var(--muted)}dd{margin:0}'
        . '.pw{position:relative}.pw button{position:absolute;right:6px;bottom:6px;min-height:0;padding:6px 8px;background:transparent;color:var(--muted);font-size:12px}'
        . '</style></head><body><main><div class="brand">' . h(GNUCMS) . '</div><ol class="steps">' . $steps . '</ol>'
        . '<h1>' . h($title) . '</h1>' . $body . '</main>'
        . '<script>document.querySelectorAll("[data-show]").forEach(function(b){b.addEventListener("click",function(){var i=document.getElementById(b.dataset.show);i.type=i.type==="password"?"text":"password";b.textContent=i.type==="password"?"보기":"숨기기"})})</script>'
        . '</body></html>';
}

function redirectTo(int $step): void
{
    header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?') . '?step=' . $step, true, 303);
    exit;
}

if ($installer->isInstalled()) {
    page(5, '이미 설치되어 있습니다', '<p>다시 설치하려면 서버에서 <code>config/config.php</code> 를 지우고 이 화면을 새로고침하세요.</p><p><a href="./">사이트로 이동</a></p>');
    exit;
}

session_name('gnucms_install');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();
$session = new InstallSession($_SESSION);

$configDir = $root . '/config';
$storageDir = $root . '/storage';
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requested = (int) ($_GET['step'] ?? 1);
$step = $session->allowedStep($requested);
if ($requested !== $step && $method === 'GET') {
    redirectTo($step);
}
$errors = [];
$post = $method === 'POST' ? $_POST : [];

// ---------- 1. 서버 점검 ----------
if ($step === 1) {
    $result = (new ServerCheck($configDir, $storageDir))->run();
    if ($method === 'POST' && $result['ok']) {
        $session->complete(1);
        redirectTo(2);
    }
    $rows = '';
    foreach ($result['items'] as $item) {
        $rows .= '<tr><td class="' . ($item['ok'] ? 'ok' : 'bad') . '">' . ($item['ok'] ? '✓' : '✗') . '</td>'
            . '<td>' . h($item['label']) . (!$item['required'] ? ' <span class="opt">권장</span>' : '') . '</td>'
            . '<td class="opt">' . h($item['note']) . '</td></tr>';
    }
    $body = '<p class="intro">이 서버에서 ' . h(GNUCMS) . ' 가 돌 수 있는지 봅니다.</p><table>' . $rows . '</table>';
    $body .= $result['ok']
        ? '<form method="post"><div class="actions"><span></span><button type="submit">다음</button></div></form>'
        : '<p class="alert">✗ 표시된 필수 항목을 고친 뒤 <a href="?step=1">다시 점검</a>하세요.</p>';
    page(1, '서버 점검', $body);
    exit;
}

// ---------- 2. 데이터베이스 ----------
if ($step === 2) {
    $types = DbSetup::availableTypes();
    $saved = $session->get('db') ?? [];
    $values = array_merge([
        'type' => in_array('sqlite', $types, true) ? 'sqlite' : (string) ($types[0] ?? ''),
        'sqlite_path' => $storageDir . '/board.sqlite',
        'host' => 'localhost', 'port' => '', 'name' => '', 'user' => '',
    ], (array) ($saved['input'] ?? []), $post);
    $probe = null;
    if ($method === 'POST') {
        try {
            $dbConfig = DbSetup::dsnFrom($post);
            $probe = DbSetup::probe($dbConfig);
            if ($probe['has_tables'] && (string) ($post['reuse'] ?? '') !== '1') {
                $errors['reuse'] = '이 DB 에는 이미 ' . h(GNUCMS) . ' 표가 있습니다. 이어 쓰려면 아래를 확인하세요.';
            } else {
                $input = $post;
                unset($input['password'], $input['reuse']);
                $session->set('db', ['config' => $dbConfig, 'probe' => $probe, 'input' => $input, 'reuse' => $probe['has_tables']]);
                $session->complete(2);
                redirectTo(3);
            }
        } catch (DomainError $e) {
            $errors = $e->details() !== [] ? $e->details() : ['_' => $e->getMessage()];
        }
    }
    $radios = '';
    foreach (DbSetup::TYPES as $key => $label) {
        $on = in_array($key, $types, true);
        $radios .= '<label class="' . ($on ? '' : 'off') . '"><input type="radio" name="type" value="' . h($key) . '"'
            . ($values['type'] === $key ? ' checked' : '') . ($on ? '' : ' disabled') . '>' . h($label)
            . ($on ? '' : '<span class="hint">드라이버 없음</span>') . '</label>';
    }
    $body = '<p class="intro">SQLite 는 파일 하나로 끝나고, MySQL/MariaDB는 DB 서버 접속 정보가 필요합니다.</p>'
        . (isset($errors['_']) ? '<p class="alert">' . h($errors['_']) . '</p>' : '')
        . '<form method="post"><div class="radios">' . $radios . '</div>' . err($errors, 'type')
        . '<div id="sqlite">' . field('SQLite 파일 경로', 'sqlite_path', $values['sqlite_path'], $errors, 'text', '웹에서 접근할 수 없는 폴더의 절대 경로') . '</div>'
        . '<div id="server">'
        . field('호스트', 'host', $values['host'], $errors)
        . field('포트', 'port', $values['port'], $errors, 'text', '비우면 기본값 (MySQL/MariaDB 3306)', 'inputmode="numeric"')
        . field('DB 이름', 'name', $values['name'], $errors)
        . field('DB 계정', 'user', $values['user'], $errors)
        . field('DB 비밀번호', 'password', '', $errors, 'password', '', 'autocomplete="off"')
        . '</div>';
    if (isset($errors['reuse'])) {
        $body .= '<div class="notice"><p>' . $errors['reuse'] . '</p><label style="margin:0;font-weight:600"><input type="checkbox" name="reuse" value="1" style="width:auto;margin-right:6px">기존 데이터베이스를 이어 씁니다 (표를 새로 만들지 않고 새 판으로 옮깁니다)</label></div>';
    }
    $body .= '<div class="actions"><a href="?step=1">← 이전</a><button type="submit">접속 시험 후 다음</button></div></form>'
        . '<script>function sw(){var t=document.querySelector("input[name=type]:checked");var s=t&&t.value==="sqlite";document.getElementById("sqlite").style.display=s?"":"none";document.getElementById("server").style.display=s?"none":""}document.querySelectorAll("input[name=type]").forEach(function(r){r.addEventListener("change",sw)});sw()</script>';
    page(2, '데이터베이스', $body);
    exit;
}

// ---------- 3. 사이트 ----------
if ($step === 3) {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('/^[A-Za-z0-9.\-:\[\]]+$/D', $host) !== 1) {
        $host = 'localhost';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $mailHost = preg_replace('/:\d+$/', '', trim($host, '[]')) ?: 'localhost';
    $values = array_merge([
        'site_name' => GNUCMS,
        'app_url' => $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/'),
        'mail_from' => 'no-reply@' . $mailHost,
    ], $session->get('site') ?? [], $post);
    if ($method === 'POST') {
        try {
            $session->set('site', Installer::siteFrom($post));
            $session->complete(3);
            $db = $session->get('db') ?? [];
            redirectTo(!empty($db['probe']['has_admin']) ? 5 : 4);
        } catch (DomainError $e) {
            $errors = $e->details();
        }
    }
    $body = '<p class="intro">나중에 관리 콘솔에서 바꿀 수 있습니다.</p><form method="post">'
        . field('사이트 이름', 'site_name', $values['site_name'], $errors, 'text', '', 'maxlength="50" required')
        . field('사이트 주소', 'app_url', $values['app_url'], $errors, 'url', '인증 메일과 비밀번호 재설정 링크에 씁니다', 'required')
        . field('발신 이메일', 'mail_from', $values['mail_from'], $errors, 'email', '인증 메일을 보낼 주소. 운영 도메인의 주소를 권장합니다', 'required')
        . '<div class="actions"><a href="?step=2">← 이전</a><button type="submit">다음</button></div></form>';
    page(3, '사이트', $body);
    exit;
}

// ---------- 4. 첫 관리자 ----------
if ($step === 4) {
    $db = $session->get('db') ?? [];
    if (!empty($db['probe']['has_admin'])) {
        $session->complete(4);
        redirectTo(5);
    }
    $values = array_merge(['email' => '', 'display_name' => ''], $session->get('admin') ?? [], $post);
    if ($method === 'POST') {
        try {
            $session->set('admin', Installer::adminFrom($post));
            $session->complete(4);
            redirectTo(5);
        } catch (DomainError $e) {
            $errors = $e->details();
        }
    }
    $body = '<p class="intro">이 계정이 전역 관리자가 됩니다. 이메일 인증은 건너뜁니다.</p><form method="post">'
        . field('이메일', 'email', $values['email'], $errors, 'email', '', 'required autocomplete="username"')
        . field('표시 이름', 'display_name', $values['display_name'], $errors, 'text', '한글·영문·숫자만. 한글 2자 또는 영문 4자 이상', 'required')
        . '<label>비밀번호<span class="hint">8자 이상</span><div class="pw"><input id="pw1" name="password" type="password" autocomplete="new-password" required><button type="button" data-show="pw1">보기</button></div></label>' . err($errors, 'password')
        . '<label>비밀번호 확인<div class="pw"><input id="pw2" name="password_confirmation" type="password" autocomplete="new-password" required><button type="button" data-show="pw2">보기</button></div></label>' . err($errors, 'password_confirmation')
        . '<div class="actions"><a href="?step=3">← 이전</a><button type="submit">다음</button></div></form>';
    page(4, '첫 관리자', $body);
    exit;
}

// ---------- 5. 완료 ----------
$db = $session->get('db') ?? [];
$site = $session->get('site') ?? [];
$admin = $session->get('admin');
$reuse = !empty($db['reuse']);
if ($method === 'POST') {
    try {
        $result = $installer->finish((array) $db['config'], $site, $reuse && !empty($db['probe']['has_admin']) ? null : $admin, $reuse);
        $session->reset();
        session_destroy();
        $body = '<div class="done"><p><strong>설치가 끝났습니다.</strong> 사용 중인 DB: ' . h($result['dialect']) . '</p>'
            . ($result['admin_email'] !== null ? '<p>관리자: <code>' . h($result['admin_email']) . '</code></p>' : '')
            . '</div>';
        $body .= $result['self_deleted'] === false
            ? '<p class="alert"><strong>install.php 를 지우지 못했습니다.</strong> 지금 <code>public/install.php</code> 를 손으로 삭제하세요. 남겨 두면 설정 파일을 지운 사람이 재설치할 수 있습니다.</p>'
            : '<p class="notice">설치기(<code>install.php</code>)는 스스로 삭제했습니다.</p>';
        $body .= '<p><a href="./login">로그인하러 가기</a> · <a href="./">사이트로 이동</a></p>';
        page(5, '완료', $body);
        exit;
    } catch (DomainError $e) {
        $errors = $e->details() !== [] ? $e->details() : ['_' => $e->getMessage()];
    }
}
$dbLabel = DbSetup::TYPES[(string) ($db['input']['type'] ?? '')] ?? (string) ($db['probe']['dialect'] ?? '');
$body = '<p class="intro">아래 내용으로 설치합니다. 표를 만들고, 관리자를 만들고, <code>config/config.php</code> 를 씁니다.</p>';
foreach ($errors as $message) {
    $body .= '<p class="alert">' . h((string) $message) . '</p>';
}
$body .= '<dl><dt>데이터베이스</dt><dd>' . h($dbLabel) . ($reuse ? ' (기존 DB 이어 쓰기)' : '') . '</dd>'
    . '<dt>사이트 이름</dt><dd>' . h((string) ($site['site_name'] ?? '')) . '</dd>'
    . '<dt>사이트 주소</dt><dd>' . h((string) ($site['app_url'] ?? '')) . '</dd>'
    . '<dt>발신 이메일</dt><dd>' . h((string) ($site['mail_from'] ?? '')) . '</dd>'
    . '<dt>관리자</dt><dd>' . ($admin !== null && empty($db['probe']['has_admin']) ? h($admin['email']) . ' (' . h($admin['display_name']) . ')' : '기존 DB 의 관리자를 그대로 씁니다') . '</dd></dl>'
    . '<form method="post"><div class="actions"><a href="?step=' . (empty($db['probe']['has_admin']) ? 4 : 3) . '">← 이전</a><button type="submit">설치</button></div></form>';
page(5, '설치 확인', $body);
```

- [ ] **Step 2: 문법과 진입점 테스트**

Run: `php -l public/install.php && ./vendor/bin/phpunit tests/Web/EntryPointTest.php`
Expected: `No syntax errors`, OK. (CLI 에서는 `config.php` 가 있으므로 "이미 설치되어 있습니다" 화면만 찍고 0 으로 끝난다.)

- [ ] **Step 3: 임시 복사본에서 실제 설치를 끝까지 돌린다**

스크래치에 저장소를 복사하고(설정·DB 제외) 내장 서버로 다섯 단계를 curl 로 밟는다:

```bash
S=/tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/install-run
rm -rf "$S" && mkdir -p "$S" && cd /home/kagla/gnucms.com
git ls-files -z | xargs -0 -I{} cp --parents {} "$S"/ && cp -r vendor "$S"/ && mkdir -p "$S"/storage/{uploads,editor,logs,cache}
cd "$S" && php -S 127.0.0.1:8099 -t public > "$S"/server.log 2>&1 &
sleep 1
J="$S/cookies.txt"; U=http://127.0.0.1:8099
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' $U/                       # 302 → /install.php
curl -s -c $J -b $J $U/install.php?step=1 | grep -o '<h1>[^<]*' ; curl -s -c $J -b $J -X POST -o /dev/null -w '%{http_code} %{redirect_url}\n' $U/install.php?step=1
curl -s -c $J -b $J -X POST -o /dev/null -w '%{http_code} %{redirect_url}\n' --data-urlencode type=sqlite --data-urlencode sqlite_path=$S/storage/board.sqlite "$U/install.php?step=2"
curl -s -c $J -b $J -X POST -o /dev/null -w '%{http_code} %{redirect_url}\n' --data-urlencode site_name=설치시험 --data-urlencode app_url=http://127.0.0.1:8099 --data-urlencode mail_from=no-reply@example.com "$U/install.php?step=3"
curl -s -c $J -b $J -X POST -o /dev/null -w '%{http_code} %{redirect_url}\n' --data-urlencode email=owner@example.com --data-urlencode display_name=사이트지기 --data-urlencode password=secret-pass-123 --data-urlencode password_confirmation=secret-pass-123 "$U/install.php?step=4"
curl -s -c $J -b $J $U/install.php?step=5 | grep -o '<dd>[^<]*' 
curl -s -c $J -b $J -X POST $U/install.php?step=5 | grep -o '설치가 끝났습니다\|스스로 삭제\|지우지 못했습니다'
ls "$S"/public/install.php 2>&1 | head -1; ls "$S"/config/config.php
curl -s -o /dev/null -w '%{http_code}\n' $U/                                        # 200
curl -s -o /dev/null -w '%{http_code}\n' $U/install.php                             # 404 (삭제됨)
kill %1
```

Expected: 302→install.php, 각 POST 303 → 다음 단계, 5단계 요약에 값이 보이고, 마무리 뒤 `config.php` 생성·`install.php` 삭제·`/` 200.

또한 단계 건너뛰기 검사: 새 쿠키로 `?step=4` 를 GET 하면 `?step=1` 로 303 이어야 한다.

- [ ] **Step 4: 스크린샷으로 화면을 본다** (있으면 헤드리스 크롬)

```bash
CHROME=$(command -v google-chrome || command -v chromium || command -v chromium-browser)
[ -n "$CHROME" ] && "$CHROME" --headless=new --disable-gpu --window-size=900,1000 --screenshot="$S"/step1.png "http://127.0.0.1:8099/install.php?step=1"
```

내장 서버를 다시 띄우고(설치 전 상태로 복사본을 새로 만들어) 1·2·4단계를 찍어 본다. 라디오 전환, 눈 단추가 동작하는지 보고, 어긋난 것은 CSS/JS 를 고친다.

- [ ] **Step 5: 커밋**

```bash
git add public/install.php
git commit -m "feat: 설치기를 서버 점검·DB·사이트·관리자·완료 다섯 단계로 나눈다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: 문서·전체 검증·라이브 확인

**Files:**
- Modify: `README.md` (설치 절), `docs/template-development.md` (12절 한 줄)
- 검증: 전체 스위트, 스모크, 라이브 업그레이드

- [ ] **Step 1: README 의 설치 절을 고친다**

`README.md` 14–23행(`## 설치` 부터 `DSN 을 어떻게 적는지는…` 까지)을 다음으로 바꾼다:

```markdown
## 설치

1. 파일 전체를 올린다. 문서 루트는 `public/` 을 가리키게 한다.
   문서 루트를 바꿀 수 없는 호스팅이라면 `public/` 안의 내용을 루트에 두고 나머지 폴더를
   그 위 디렉터리에 둔다. `storage/` 가 웹으로 접근 가능한 위치에 있으면 안 된다.
2. 브라우저로 사이트를 연다. 설정 파일이 없으면 `install.php` 로 자동 이동한다.
3. 다섯 단계를 따라간다: 서버 점검 → 데이터베이스(종류를 고르고 접속 시험) → 사이트 이름·주소·발신 메일
   → 첫 관리자 → 완료. `config/config.php` 는 마지막에 쓰인다.
4. 설치가 끝나면 `public/install.php` 는 스스로 삭제된다. 못 지웠다고 나오면 손으로 지운다.
5. 로그인해 관리 콘솔에서 게시판을 만든다.

### 코드를 새 판으로 올릴 때

파일만 덮어쓰면 된다. 첫 요청에서 앱이 DB 의 스키마 판을 견주어 다르면 스스로 옮긴다.
SQLite 는 옮기기 전에 `storage/backups/` 에 복사본을 남긴다(최근 5개). MySQL/MariaDB는
앱이 백업하지 못하므로 올리기 전에 `mysqldump` 로 받아 둔다.

옮기지 못하면 방문자에게 503 점검 화면이 나가고 `storage/logs/error.log` 에 원인이 남는다.
원인을 고치면 60초 뒤 요청에서 다시 시도한다. 되돌리려면 `storage/board.sqlite` 를 백업
파일로 바꾼다. 관리 콘솔 > 사이트 설정 아래에서 판 번호와 백업 목록을 볼 수 있다.
```

`### DB 를 나중에 바꾸려면` 절(130–165행)의 재설치 순서 중 `bootstrap_admin` 언급 두 곳을 지운다: 1번은 "**먼저 지금 `config/config.php` 의 `auth.secret` 을 복사해 둔다.** 재설치는 시크릿을 새로 만든다. 그대로 두면 저장된 메일 비밀번호를 풀 수 없게 된다." 로, 4번은 "새로 생긴 `config/config.php` 의 `auth.secret` 을 1번에서 복사해 둔 값으로 되돌린다." 로 바꾼다. 3번은 "`public/install.php` 를 다시 올리고 새 DB 로 설치한다. 2단계에서 표가 없는 빈 DB 를 고른다." 로 바꾼다. 5번은 지운다(스스로 삭제). README 의 나머지 API 시절 절은 이 작업 범위 밖이다(문서 머리의 안내문이 이미 그렇게 말한다).

- [ ] **Step 2: 템플릿 개발 안내 12절**

`docs/template-development.md` 12절 마지막 줄 뒤에 한 줄:

```markdown
  옮기는 일은 `SchemaUpgrader` 가 첫 요청에서 한다(백업 → 마이그레이션 → 실패 시 503 점검 화면).
```

- [ ] **Step 3: 전체 스위트와 문법**

Run: `./vendor/bin/phpunit && for f in $(git ls-files '*.php'); do php -l $f > /dev/null || echo "SYNTAX $f"; done`
Expected: OK (기존 311 + 새 테스트 ≈ 45), 문법 오류 없음.

- [ ] **Step 4: 스모크**

Run: `php /tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/smoke.php` (없으면 `curl -s -o /dev/null -w '%{http_code}\n' https://gnucms.gnuboard.net/{,posts,login,register,terms/service}` 로 대신)
Expected: 전부 200.

- [ ] **Step 5: 라이브에서 자동 업그레이드를 확인한다**

```bash
cd /home/kagla/gnucms.com
cp storage/board.sqlite "storage/board-before-upgrader-$(date +%Y%m%d-%H%M%S).sqlite"
sqlite3 storage/board.sqlite "UPDATE site_settings SET setting_value = '9.manual' WHERE setting_key = 'schema_version';"
curl -s -o /dev/null -w '%{http_code}\n' https://gnucms.gnuboard.net/     # 200
sqlite3 storage/board.sqlite "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('schema_version','schema_upgraded_at','schema_backup');"
ls -la storage/backups/
```

Expected: 200, `schema_version` 이 새 도장, `schema_upgraded_at` 채워짐, `storage/backups/board-v9-*.sqlite` 하나. 웹 서버 사용자(www-data)가 `storage/` 에 쓸 수 있어야 한다 — 백업 폴더가 안 생기면 `ls -ld storage` 로 소유·권한을 보고 고친다.

관리 콘솔 `/admin/settings` 를 열어 "데이터 구조" 카드에 판 번호와 백업 파일이 보이는지 헤드리스 크롬으로 찍어 본다.

- [ ] **Step 6: 커밋**

```bash
git add README.md docs/template-development.md
git commit -m "docs: 다섯 단계 설치와 자동 업그레이드 안내

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
