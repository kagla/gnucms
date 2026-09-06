<?php

declare(strict_types=1);

namespace GnuCms\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Support\Clock;
use PharData;
use Psr\Http\Message\UploadedFileInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * DB와 운영 중 생긴 파일을 GNUCMS 전용 ZIP/TAR 아카이브로 묶고 검증한다.
 *
 * 아카이브 안의 manifest.json에는 모든 파일의 크기와 SHA-256이 들어간다. 복원은
 * 그 목록에 있는 파일만 안전한 임시 경로로 푼 뒤 같은 파일시스템 안에서 바꿔 끼운다.
 * 원격 DB는 네이티브 덤프까지만 만들고, 웹에서의 자동 복원은 SQLite 파일 DB로 제한한다.
 */
final class BackupManager
{
    public const FORMAT = 'gnucms-full-backup';
    public const FORMAT_VERSION = 1;

    private Connection $db;
    private array $config;
    private string $storageDir;
    private ?string $configFile;
    private string $projectRoot;
    private DateTimeZone $timezone;

    public function __construct(
        Connection $db,
        array $config,
        string $storageDir,
        ?string $configFile = null,
        string $timezone = 'Asia/Seoul'
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->projectRoot = dirname(__DIR__, 2);
        $this->storageDir = $this->absolutePath($storageDir);
        if ($this->storageDir === '/' || $this->storageDir === $this->projectRoot) {
            throw new RuntimeException('storage.dir은 프로젝트 루트와 구분된 안전한 하위 경로여야 합니다.');
        }
        $this->configFile = $configFile === null ? null : $this->absolutePath($configFile);
        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Throwable $e) {
            $this->timezone = new DateTimeZone('Asia/Seoul');
        }
    }

    /** @return array{driver:string,can_create:bool,unavailable_reason:?string,can_restore:bool,preferred_format:?string,available_formats:list<string>,archives:list<array<string,mixed>>,instructions:list<string>} */
    public function status(): array
    {
        $driver = $this->driver();
        $binary = $driver === 'mysql' ? 'mysqldump' : null;
        $availableFormats = $this->availableArchiveFormats();
        $hasArchiveSupport = $availableFormats !== [];
        $canCreate = $hasArchiveSupport && ($driver === 'sqlite'
            || ($binary !== null && function_exists('proc_open') && $this->findExecutable($binary) !== null));
        $reason = null;
        if (!$canCreate) {
            $reason = !$hasArchiveSupport
                ? '서버에 zip과 phar 확장이 모두 없어 백업 파일을 만들 수 없습니다.'
                : (!function_exists('proc_open')
                ? '서버에서 외부 명령 실행이 꺼져 있습니다. CLI에서 DB 도구로 덤프한 뒤 파일과 함께 보관해 주세요.'
                : $binary . ' 명령을 찾을 수 없습니다. 서버 또는 호스팅 관리 화면에서 DB 덤프를 먼저 만드세요.');
        }

        return [
            'driver' => $driver,
            'can_create' => $canCreate,
            'unavailable_reason' => $reason,
            'can_restore' => $hasArchiveSupport && $this->canRestoreSqlite(),
            'preferred_format' => $availableFormats[0] ?? null,
            'available_formats' => $availableFormats,
            'archives' => $this->archives(),
            'instructions' => $this->restoreInstructions($driver),
        ];
    }

    /** 전체 수동 백업을 만들고 생성 직후 다시 검증한다. */
    public function create(string $reason = 'manual', ?string $format = null): array
    {
        return $this->withLock(fn (): array => $this->createUnlocked($reason, $format));
    }

    /**
     * @return array{valid:bool,name:string,created_at:string,driver:string,file_count:int,size:int,sha256:string}
     */
    public function verify(string $archive): array
    {
        $path = $this->resolveArchive($archive);
        $archiveHandle = $this->openArchiveForRead($path);
        try {
            $manifest = $this->readManifestFromArchive($archiveHandle, $path);
            $files = $manifest['files'] ?? null;
            if (!is_array($files) || $files === []) {
                throw new RuntimeException('백업 파일 목록이 없거나 비어 있습니다.');
            }

            $seenDatabase = false;
            foreach ($files as $entry => $metadata) {
                if (!is_string($entry) || !$this->validEntryName($entry) || !is_array($metadata)) {
                    throw new RuntimeException('백업 파일 목록에 안전하지 않은 경로가 있습니다.');
                }
                $size = $metadata['size'] ?? null;
                $sha256 = $metadata['sha256'] ?? null;
                if (!is_int($size) || $size < 0 || !is_string($sha256)
                    || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
                    throw new RuntimeException('백업 파일의 무결성 정보가 올바르지 않습니다: ' . $entry);
                }
                $actual = $this->archiveEntryIntegrity($archiveHandle, $path, $entry);
                if ($actual === null || $actual['size'] !== $size || $actual['sha256'] !== $sha256) {
                    throw new RuntimeException('백업 파일의 체크섬이 일치하지 않습니다: ' . $entry);
                }
                if ($entry === (string) ($manifest['database']['path'] ?? '')) {
                    $seenDatabase = true;
                }
            }
            if (!$seenDatabase) {
                throw new RuntimeException('백업에 데이터베이스 파일이 없습니다.');
            }

            $configEntry = $manifest['config']['path'] ?? null;
            if (!is_string($configEntry) || !str_starts_with($configEntry, 'config/')
                || !isset($files[$configEntry])) {
                throw new RuntimeException('백업에 복원용 설정 파일이 없습니다.');
            }
            $components = $manifest['components'] ?? null;
            foreach (['uploads', 'editor', 'avatars'] as $component) {
                if (!is_array($components) || !is_array($components[$component] ?? null)
                    || ($components[$component]['path'] ?? null) !== 'files/' . $component
                    || !is_bool($components[$component]['present'] ?? null)) {
                    throw new RuntimeException('백업 구성 요소 정보가 올바르지 않습니다: ' . $component);
                }
            }

            $driver = (string) ($manifest['database']['driver'] ?? '');
            $databaseEntry = (string) ($manifest['database']['path'] ?? '');
            $this->verifyDatabaseEntry($archiveHandle, $path, $databaseEntry, $driver, $manifest['database']['prefix'] ?? '');
        } finally {
            $this->closeArchive($archiveHandle);
        }

        $result = [
            'valid' => true,
            'name' => basename($path),
            'created_at' => (string) $manifest['created_at'],
            'driver' => $driver,
            'file_count' => count($files),
            'size' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
        ];
        $this->writeVerification($path, $result);

        return $result;
    }

    /**
     * SQLite 전체 백업을 복원한다. 현재 상태의 전체 백업을 같은 잠금 안에서 먼저 만든다.
     * 설정 파일은 아카이브에 들어 있지만 실행 중 자동 교체하지 않는다.
     *
     * @return array{restored:string,safety_backup:string}
     */
    public function restore(string $archive): array
    {
        $verified = $this->verify($archive);
        if ($this->driver() !== 'sqlite' || $verified['driver'] !== 'sqlite') {
            throw new RuntimeException('웹과 GNUCMS CLI의 자동 복원은 SQLite 백업끼리만 지원합니다.');
        }
        if ($this->sqliteDatabasePath() === null) {
            throw new RuntimeException('메모리 SQLite 또는 확인할 수 없는 SQLite 경로는 복원할 수 없습니다.');
        }
        $manifest = $this->readManifest($this->resolveArchive($archive));
        if (($manifest['database']['prefix'] ?? '') !== $this->db->prefix()) {
            throw new RuntimeException('백업과 현재 설치의 테이블 프리픽스가 달라 복원할 수 없습니다.');
        }

        return $this->withLock(function () use ($archive): array {
            $safety = $this->createUnlocked('pre-restore');
            // 최초 검증과 실제 교체 사이에 파일이 바뀌는 경우까지 막는다.
            $this->verify($archive);
            $path = $this->resolveArchive($archive);
            $manifest = $this->readManifest($path);
            $this->restoreSqliteArchive($path, $manifest);

            return ['restored' => basename($path), 'safety_backup' => (string) $safety['name']];
        });
    }

    /** 다운로드할 수동 백업의 실제 경로. 이름 검증과 디렉터리 고정을 함께 한다. */
    public function downloadPath(string $archive): string
    {
        return $this->resolveArchive($archive);
    }

    /** 저장 폴더 안의 수동 백업과 그 검증 기록을 함께 삭제한다. */
    public function delete(string $archive): array
    {
        if ($archive === '' || $archive !== basename($archive)) {
            throw new RuntimeException('삭제할 백업 파일 이름이 올바르지 않습니다.');
        }

        return $this->withLock(function () use ($archive): array {
            $path = $this->resolveArchive($archive);
            if (!unlink($path)) {
                throw new RuntimeException('백업 파일을 삭제하지 못했습니다: ' . $archive);
            }
            @unlink($path . '.verified.json');

            return ['deleted' => $archive];
        });
    }

    /** 내려받아 보관하던 전체 백업을 저장 폴더로 가져오고 즉시 검증한다. */
    public function storeUpload(UploadedFileInterface $upload): array
    {
        $error = $upload->getError();
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('백업 파일이 PHP의 웹 업로드 용량 제한을 초과했습니다.');
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('업로드할 ZIP 또는 TAR 백업 파일을 선택해 주세요.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('백업 파일 업로드에 실패했습니다. 오류 코드: ' . $error);
        }
        $size = $upload->getSize();
        if ($size !== null && $size < 1) {
            throw new RuntimeException('빈 백업 파일은 업로드할 수 없습니다.');
        }

        $clientName = $upload->getClientFilename();
        $clientName = is_string($clientName) ? trim(str_replace('\\', '/', $clientName)) : '';
        $format = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        if ($clientName === '' || !in_array($format, ['zip', 'tar'], true)) {
            throw new RuntimeException('업로드할 파일은 ZIP 또는 TAR 형식이어야 합니다.');
        }
        if (!in_array($format, $this->availableArchiveFormats(), true)) {
            throw new RuntimeException($format === 'zip'
                ? 'ZIP 백업을 올리고 검증하려면 PHP zip 확장이 필요합니다.'
                : 'TAR 백업을 올리고 검증하려면 PHP phar 확장이 필요합니다.');
        }

        return $this->withLock(function () use ($upload, $format): array {
            $this->ensureDirectory($this->archiveDir(), 0700);
            $temporary = $this->archiveDir() . '/.uploading-' . bin2hex(random_bytes(8)) . '.' . $format;
            $stored = null;

            try {
                $upload->moveTo($temporary);
                if (!is_file($temporary) || is_link($temporary)) {
                    throw new RuntimeException('업로드한 백업 파일을 안전하게 저장하지 못했습니다.');
                }
                @chmod($temporary, 0600);

                $manifest = $this->readManifest($temporary);
                try {
                    $createdAt = (new DateTimeImmutable((string) $manifest['created_at']))
                        ->setTimezone($this->timezone);
                } catch (Throwable $e) {
                    throw new RuntimeException('백업의 생성 시각이 올바르지 않습니다.', 0, $e);
                }
                if (preg_match('/^\d{8}-\d{6}$/D', $createdAt->format('Ymd-His')) !== 1) {
                    throw new RuntimeException('백업의 생성 시각이 올바르지 않습니다.');
                }
                $destination = $this->uniqueArchivePath(
                    (string) $manifest['database']['driver'],
                    $format,
                    $createdAt
                );
                if (!rename($temporary, $destination)) {
                    throw new RuntimeException('업로드한 백업 파일을 저장하지 못했습니다.');
                }
                $stored = $destination;
                @chmod($stored, 0600);

                return $this->verify(basename($stored));
            } catch (Throwable $e) {
                @unlink($temporary);
                if ($stored !== null && is_file($stored)) {
                    @unlink($stored);
                    @unlink($stored . '.verified.json');
                }
                throw $e;
            }
        });
    }

    /** @return list<array<string,mixed>> */
    private function archives(): array
    {
        $items = [];
        $paths = array_merge(
            glob($this->archiveDir() . '/gnucms-*.zip') ?: [],
            glob($this->archiveDir() . '/gnucms-*.tar') ?: []
        );
        foreach ($paths as $path) {
            try {
                $manifest = $this->readManifest($path);
                $verification = $this->readVerification($path);
                $items[] = [
                    'name' => basename($path),
                    'size' => (int) filesize($path),
                    'mtime' => (int) filemtime($path),
                    'created_at' => (string) $manifest['created_at'],
                    'driver' => (string) $manifest['database']['driver'],
                    'reason' => (string) ($manifest['reason'] ?? 'manual'),
                    'verified_at' => $verification['verified_at'] ?? null,
                ];
            } catch (Throwable $e) {
                $items[] = [
                    'name' => basename($path), 'size' => (int) filesize($path),
                    'mtime' => (int) filemtime($path), 'created_at' => null,
                    'driver' => null, 'reason' => null, 'verified_at' => null,
                    'error' => '형식을 읽을 수 없음',
                ];
            }
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['name'], (string) $a['name']));

        return $items;
    }

    private function createUnlocked(string $reason, ?string $format = null): array
    {
        $this->ensureDirectory($this->archiveDir(), 0700);
        $mediaRoots = $this->mediaRoots();
        $this->assertMediaRoots($mediaRoots);
        $driver = $this->driver();
        $archiveFormat = $this->resolveArchiveFormat($format);
        $createdAt = $this->siteNow();
        $token = bin2hex(random_bytes(6));
        $databasePath = $this->archiveDir() . '/.database-' . $token;
        $temporaryArchive = $this->archiveDir() . '/.building-' . $token . '.' . $archiveFormat;
        $databaseEntry = match ($driver) {
            'sqlite' => 'database/sqlite.sqlite',
            'mysql' => 'database/mysql.sql',
            default => throw new RuntimeException('지원하지 않는 DB 드라이버입니다: ' . $driver),
        };

        $archive = null;
        try {
            $this->dumpDatabase($databasePath, $driver);
            $archive = $this->openArchiveForWrite($temporaryArchive, $archiveFormat);
            $files = [];
            $this->addFile($archive, $databasePath, $databaseEntry, $files);

            $configEntry = 'config/config.php';
            if ($this->configFile !== null && is_file($this->configFile)) {
                $this->addFile($archive, $this->configFile, $configEntry, $files);
            } else {
                $configEntry = 'config/runtime.php';
                $this->addString(
                    $archive,
                    "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($this->exportableConfig($this->config), true) . ";\n",
                    $configEntry,
                    $files
                );
            }

            $components = [];
            foreach ($mediaRoots as $name => $root) {
                $present = is_dir($root);
                $components[$name] = ['path' => 'files/' . $name, 'present' => $present];
                if ($present) {
                    $this->addDirectory($archive, $root, 'files/' . $name, $files);
                }
            }

            $manifest = [
                'format' => self::FORMAT,
                'format_version' => self::FORMAT_VERSION,
                'created_at' => $createdAt->format('Y-m-d\TH:i:sP'),
                'application_version' => GNUCMS_VERSION,
                'schema_version' => Schema::VERSION,
                'reason' => $reason,
                'database' => [
                    'driver' => $driver,
                    'format' => $driver === 'mysql' ? 'sql' : 'sqlite3',
                    'path' => $databaseEntry,
                    'prefix' => $this->db->prefix(),
                ],
                'config' => ['path' => $configEntry, 'automatic_restore' => false],
                'components' => $components,
                'files' => $files,
            ];
            $this->writeArchiveString($archive, 'manifest.json', (string) json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n");
            $completedArchive = $archive;
            $archive = null;
            $this->closeArchive($completedArchive, true);

            $final = $this->uniqueArchivePath($driver, $archiveFormat, $createdAt);
            if (!rename($temporaryArchive, $final)) {
                throw new RuntimeException('완성한 백업 파일을 저장하지 못했습니다.');
            }
            @chmod($final, 0600);
            try {
                return $this->verify(basename($final));
            } catch (Throwable $e) {
                @unlink($final);
                throw $e;
            }
        } finally {
            if (is_object($archive)) {
                $this->closeArchive($archive);
            }
            @unlink($databasePath);
            @unlink($temporaryArchive);
        }
    }

    private function dumpDatabase(string $destination, string $driver): void
    {
        if ($driver === 'sqlite') {
            $this->db->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $destination) . "'");
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('지원하지 않는 DB 드라이버입니다: ' . $driver);
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('서버에서 외부 명령 실행이 꺼져 있어 DB 덤프를 만들 수 없습니다.');
        }

        $dsn = $this->parseDsn((string) ($this->config['db']['dsn'] ?? ''));
        $database = (string) ($dsn['dbname'] ?? '');
        if ($database === '') {
            throw new RuntimeException('DB DSN에서 데이터베이스 이름(dbname)을 찾을 수 없습니다.');
        }
        $username = (string) ($this->config['db']['username'] ?? '');
        $password = (string) ($this->config['db']['password'] ?? '');
        $tables = array_map(fn (string $table): string => $this->db->tableName($table), Schema::TABLES);

        $binary = $this->findExecutable('mysqldump');
        if ($binary === null) {
            throw new RuntimeException('mysqldump 명령을 찾을 수 없습니다.');
        }
        $command = [$binary, '--single-transaction', '--quick', '--skip-lock-tables', '--triggers',
            '--hex-blob', '--default-character-set=utf8mb4'];
        if (($dsn['unix_socket'] ?? '') !== '') {
            $command[] = '--socket=' . $dsn['unix_socket'];
        } else {
            $command[] = '--host=' . (string) ($dsn['host'] ?? 'localhost');
            $command[] = '--port=' . (string) ($dsn['port'] ?? '3306');
        }
        $command[] = '--user=' . $username;
        $command[] = $database;
        array_push($command, ...$tables);
        $this->runDump($command, $destination, $password === '' ? [] : ['MYSQL_PWD' => $password]);
    }

    private function runDump(array $command, string $destination, array $extraEnvironment): void
    {
        $errorFile = $destination . '.stderr';
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        foreach ($extraEnvironment as $key => $value) {
            $environment[$key] = $value;
        }
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $destination, 'wb'],
            2 => ['file', $errorFile, 'wb'],
        ], $pipes, null, $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('DB 덤프 명령을 시작하지 못했습니다.');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $status = proc_close($process);
        $error = is_file($errorFile) ? trim((string) file_get_contents($errorFile)) : '';
        @unlink($errorFile);
        if ($status !== 0 || !is_file($destination) || filesize($destination) === 0) {
            @unlink($destination);
            throw new RuntimeException('DB 덤프 생성에 실패했습니다.' . ($error === '' ? '' : ' ' . $error));
        }
    }

    private function addDirectory(object $archive, string $root, string $prefix, array &$files): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->isLink()) {
                continue;
            }
            $source = $item->getPathname();
            $relative = str_replace('\\', '/', substr($source, strlen(rtrim($root, '/\\')) + 1));
            $entry = $prefix . '/' . $relative;
            if (!$this->validEntryName($entry)) {
                throw new RuntimeException('백업할 파일 경로를 안전하게 표현할 수 없습니다: ' . $relative);
            }
            $this->addFile($archive, $source, $entry, $files);
        }
    }

    private function addFile(object $archive, string $source, string $entry, array &$files): void
    {
        $size = filesize($source);
        $hash = hash_file('sha256', $source);
        if ($size === false || $hash === false) {
            throw new RuntimeException('백업할 파일을 읽을 수 없습니다: ' . $source);
        }
        if ($archive instanceof ZipArchive) {
            if (!$archive->addFile($source, $entry)) {
                throw new RuntimeException('백업 아카이브에 파일을 추가하지 못했습니다: ' . $entry);
            }
            $archive->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0600 << 16);
        } else {
            $archive->addFile($source, $entry);
            $archive[$entry]->chmod(0600);
        }
        $files[$entry] = ['size' => (int) $size, 'sha256' => $hash];
    }

    private function addString(object $archive, string $contents, string $entry, array &$files): void
    {
        $this->writeArchiveString($archive, $entry, $contents);
        $files[$entry] = ['size' => strlen($contents), 'sha256' => hash('sha256', $contents)];
    }

    private function readManifest(string $path): array
    {
        $archive = $this->openArchiveForRead($path);
        try {
            return $this->readManifestFromArchive($archive, $path);
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), 'GNUCMS 백업 형식을 읽을 수 없습니다:')) {
                throw $e;
            }
            throw new RuntimeException('GNUCMS 백업 형식을 읽을 수 없습니다: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->closeArchive($archive);
        }
    }

    private function readManifestFromArchive(object $archive, string $path): array
    {
        try {
            $stat = $this->archiveEntryStat($archive, 'manifest.json');
            if ($stat === null || $stat['size'] > 32 * 1048576) {
                throw new RuntimeException('manifest.json을 찾을 수 없습니다.');
            }
            $contents = $this->archiveEntryContents($archive, $path, 'manifest.json');
            $manifest = is_string($contents) ? json_decode($contents, true, 32, JSON_THROW_ON_ERROR) : null;
        } catch (Throwable $e) {
            throw new RuntimeException('GNUCMS 백업 형식을 읽을 수 없습니다: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT
            || ($manifest['format_version'] ?? null) !== self::FORMAT_VERSION
            || !is_string($manifest['created_at'] ?? null)
            || !in_array($manifest['database']['driver'] ?? null, ['sqlite', 'mysql'], true)
            || !is_string($manifest['database']['prefix'] ?? '')) {
            throw new RuntimeException('지원하는 GNUCMS 전체 백업 형식이 아닙니다.');
        }

        return $manifest;
    }

    private function verifyDatabaseEntry(object $archive, string $archivePath, string $entry, string $driver, string $prefix): void
    {
        if (!$this->validEntryName($entry)) {
            throw new RuntimeException('데이터베이스 파일 경로가 올바르지 않습니다.');
        }
        if ($driver === 'mysql') {
            $handle = $this->openArchiveEntryStream($archive, $archivePath, $entry);
            $head = is_resource($handle) ? fread($handle, 65536) : false;
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (!is_string($head) || stripos($head, 'dump') === false) {
                throw new RuntimeException('MySQL/MariaDB SQL dump 형식이 아닙니다.');
            }
            return;
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException('지원하지 않는 DB 백업 형식입니다.');
        }

        $temporary = $this->archiveDir() . '/.verify-' . bin2hex(random_bytes(6)) . '.sqlite';
        try {
            $this->copyArchiveEntry($archive, $archivePath, $entry, $temporary);
            $copy = Connection::create(['dsn' => 'sqlite:' . $temporary, 'prefix' => $prefix]);
            $integrity = $copy->pdo()->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok' || !(new Schema($copy))->exists()) {
                throw new RuntimeException('SQLite 무결성 검사에 실패했습니다.');
            }
            $copy = null;
        } catch (Throwable $e) {
            throw new RuntimeException('SQLite 백업을 열거나 검사할 수 없습니다: ' . $e->getMessage(), 0, $e);
        } finally {
            @unlink($temporary);
        }
    }

    private function restoreSqliteArchive(string $archive, array $manifest): void
    {
        $databaseTarget = $this->sqliteDatabasePath();
        if ($databaseTarget === null) {
            throw new RuntimeException('복원할 SQLite 파일 경로를 확인할 수 없습니다.');
        }
        $mediaRoots = $this->mediaRoots();
        $this->assertRestoreTargets($databaseTarget, $mediaRoots);

        $token = bin2hex(random_bytes(6));
        $prepared = [];
        try {
            $archiveHandle = $this->openArchiveForRead($archive);
            try {
                $databaseNew = $this->siblingTemporary($databaseTarget, 'new', $token);
                $this->ensureDirectory(dirname($databaseNew));
                $this->copyArchiveEntry(
                    $archiveHandle,
                    $archive,
                    (string) $manifest['database']['path'],
                    $databaseNew
                );
                $databasePermissions = is_file($databaseTarget) ? @fileperms($databaseTarget) : false;
                $databaseMode = is_int($databasePermissions) ? ($databasePermissions & 0777) : 0600;
                @chmod($databaseNew, $databaseMode);
                $prepared[] = ['target' => $databaseTarget, 'new' => $databaseNew, 'type' => 'file'];

                $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
                foreach ($mediaRoots as $name => $target) {
                    $new = $this->siblingTemporary($target, 'new', $token);
                    $permissions = is_dir($target) ? @fileperms($target) : false;
                    $mode = is_int($permissions) ? ($permissions & 0777) : 0775;
                    $this->ensureDirectory($new, $mode);
                    $prefix = 'files/' . $name . '/';
                    foreach ($files as $entry => $ignored) {
                        if (!is_string($entry) || !str_starts_with($entry, $prefix)) {
                            continue;
                        }
                        $relative = substr($entry, strlen($prefix));
                        if ($relative === '' || !$this->validRelativePath($relative)) {
                            throw new RuntimeException('복원할 파일 경로가 올바르지 않습니다: ' . $entry);
                        }
                        $destination = $new . '/' . $relative;
                        $this->ensureDirectory(dirname($destination));
                        $this->copyArchiveEntry($archiveHandle, $archive, $entry, $destination);
                        @chmod($destination, 0644);
                    }
                    $prepared[] = ['target' => $target, 'new' => $new, 'type' => 'dir'];
                }
            } finally {
                $this->closeArchive($archiveHandle);
            }

            // WAL 모드였던 SQLite의 옛 sidecar가 새 DB에 붙지 않게 먼저 비우고 따로 치운다.
            // 이 요청은 복원 뒤 DB를 더 쓰지 않으며 다음 요청은 새 연결을 만든다.
            $this->db->pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $sidecars = [];
            foreach (['-wal', '-shm', '-journal'] as $suffix) {
                $sidecar = $databaseTarget . $suffix;
                if (!file_exists($sidecar)) continue;
                $oldSidecar = $this->siblingTemporary($sidecar, 'old', $token);
                if (!rename($sidecar, $oldSidecar)) {
                    foreach ($sidecars as $saved) @rename($saved['old'], $saved['target']);
                    throw new RuntimeException('SQLite 임시 파일을 안전하게 분리하지 못했습니다: ' . $sidecar);
                }
                $sidecars[] = ['target' => $sidecar, 'old' => $oldSidecar];
            }
            try {
                $this->swapPrepared($prepared, $token);
            } catch (Throwable $e) {
                foreach ($sidecars as $saved) @rename($saved['old'], $saved['target']);
                throw $e;
            }
            foreach ($sidecars as $saved) $this->removePath($saved['old']);
        } catch (Throwable $e) {
            foreach ($prepared as $item) {
                $this->removePath((string) $item['new']);
            }
            throw $e;
        }
    }

    private function swapPrepared(array $prepared, string $token): void
    {
        $swapped = [];
        try {
            foreach ($prepared as $item) {
                $target = (string) $item['target'];
                $new = (string) $item['new'];
                $old = $this->siblingTemporary($target, 'old', $token);
                $hadTarget = file_exists($target) || is_link($target);
                if ($hadTarget && !rename($target, $old)) {
                    throw new RuntimeException('현재 데이터를 안전 보관 경로로 옮기지 못했습니다: ' . $target);
                }
                if (!rename($new, $target)) {
                    if ($hadTarget) {
                        @rename($old, $target);
                    }
                    throw new RuntimeException('복원 데이터를 적용하지 못했습니다: ' . $target);
                }
                $swapped[] = ['target' => $target, 'old' => $old, 'had_target' => $hadTarget];
            }
        } catch (Throwable $e) {
            foreach (array_reverse($swapped) as $item) {
                $this->removePath((string) $item['target']);
                if ($item['had_target']) {
                    @rename((string) $item['old'], (string) $item['target']);
                }
            }
            throw $e;
        }

        foreach ($swapped as $item) {
            if ($item['had_target']) {
                $this->removePath((string) $item['old']);
            }
        }
    }

    private function assertRestoreTargets(string $database, array $roots): void
    {
        $targets = array_merge([$database], array_values($roots));
        foreach ($targets as $target) {
            if ($target === '/' || $target === $this->projectRoot || $target === $this->storageDir
                || is_link($target)) {
                throw new RuntimeException('안전하지 않은 복원 대상 경로입니다: ' . $target);
            }
        }
        for ($i = 0, $count = count($targets); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->pathsOverlap($targets[$i], $targets[$j])) {
                    throw new RuntimeException('복원 대상 경로가 서로 겹칩니다. 설정을 확인해 주세요.');
                }
            }
        }
    }

    private function assertMediaRoots(array $roots): void
    {
        $values = array_values($roots);
        foreach ($values as $root) {
            // storage 자체나 그 상위 폴더를 파일 원본으로 잡으면 방금 만드는 백업을 다시
            // 백업하는 재귀가 생길 수 있다. 정상 기본값(storage/uploads 등)은 허용한다.
            if ($root === '/' || $root === $this->projectRoot || $root === $this->storageDir
                || str_starts_with($this->storageDir . '/', rtrim($root, '/') . '/') || is_link($root)) {
                throw new RuntimeException('안전하지 않은 업로드 원본 경로입니다: ' . $root);
            }
        }
        for ($i = 0, $count = count($values); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->pathsOverlap($values[$i], $values[$j])) {
                    throw new RuntimeException('업로드 원본 경로가 서로 겹칩니다. 설정을 확인해 주세요.');
                }
            }
        }
    }

    /** @return list<string> */
    private function availableArchiveFormats(): array
    {
        $formats = [];
        if (class_exists(ZipArchive::class)) {
            $formats[] = 'zip';
        }
        if (class_exists(PharData::class)) {
            $formats[] = 'tar';
        }

        return $formats;
    }

    private function preferredArchiveFormat(): string
    {
        $formats = $this->availableArchiveFormats();
        if ($formats === []) {
            throw new RuntimeException('서버에 zip과 phar 확장이 모두 없습니다.');
        }

        return $formats[0];
    }

    private function resolveArchiveFormat(?string $format): string
    {
        if ($format === null) {
            return $this->preferredArchiveFormat();
        }

        $format = strtolower(trim($format));
        if (!in_array($format, ['zip', 'tar'], true)) {
            throw new RuntimeException('백업 형식은 zip 또는 tar만 선택할 수 있습니다.');
        }
        if (!in_array($format, $this->availableArchiveFormats(), true)) {
            throw new RuntimeException($format === 'zip'
                ? 'ZIP 백업을 만들려면 PHP zip 확장이 필요합니다.'
                : 'TAR 백업을 만들려면 PHP phar 확장이 필요합니다.');
        }

        return $format;
    }

    private function openArchiveForWrite(string $path, string $format): object
    {
        if ($format === 'zip') {
            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('서버에 zip 확장이 없습니다.');
            }
            $archive = new ZipArchive();
            $opened = $archive->open($path, ZipArchive::CREATE | ZipArchive::EXCL);
            if ($opened !== true) {
                throw new RuntimeException('ZIP 백업 파일을 만들 수 없습니다.');
            }

            return $archive;
        }
        if ($format !== 'tar' || !class_exists(PharData::class)) {
            throw new RuntimeException('서버에 phar 확장이 없습니다.');
        }

        return new PharData($path);
    }

    private function openArchiveForRead(string $path): object
    {
        try {
            if (str_ends_with(strtolower($path), '.zip')) {
                if (!class_exists(ZipArchive::class)) {
                    throw new RuntimeException('ZIP 백업을 읽으려면 PHP zip 확장이 필요합니다.');
                }
                $archive = new ZipArchive();
                if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
                    throw new RuntimeException('ZIP 아카이브가 손상되었거나 올바르지 않습니다.');
                }

                return $archive;
            }
            if (!class_exists(PharData::class)) {
                throw new RuntimeException('TAR 백업을 읽으려면 PHP phar 확장이 필요합니다.');
            }

            return new PharData($path);
        } catch (Throwable $e) {
            throw new RuntimeException('GNUCMS 백업 형식을 읽을 수 없습니다: ' . $e->getMessage(), 0, $e);
        }
    }

    private function closeArchive(object $archive, bool $mustSucceed = false): void
    {
        if ($archive instanceof ZipArchive && !$archive->close() && $mustSucceed) {
            throw new RuntimeException('ZIP 백업 파일을 완성하지 못했습니다.');
        }
    }

    private function writeArchiveString(object $archive, string $entry, string $contents): void
    {
        if ($archive instanceof ZipArchive) {
            if (!$archive->addFromString($entry, $contents)) {
                throw new RuntimeException('백업 아카이브에 파일을 추가하지 못했습니다: ' . $entry);
            }
            $archive->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0600 << 16);
            return;
        }
        $archive->addFromString($entry, $contents);
        $archive[$entry]->chmod(0600);
    }

    /** @return array{size:int}|null */
    private function archiveEntryStat(object $archive, string $entry): ?array
    {
        if ($archive instanceof ZipArchive) {
            $stat = $archive->statName($entry);
            return is_array($stat) && isset($stat['size']) ? ['size' => (int) $stat['size']] : null;
        }
        if (!isset($archive[$entry]) || !$archive[$entry]->isFile()) {
            return null;
        }

        return ['size' => (int) $archive[$entry]->getSize()];
    }

    private function archiveEntryContents(object $archive, string $archivePath, string $entry): string
    {
        $stream = $this->openArchiveEntryStream($archive, $archivePath, $entry);
        try {
            $contents = stream_get_contents($stream);
            if (!is_string($contents)) {
                throw new RuntimeException('백업 파일을 읽을 수 없습니다: ' . $entry);
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /** @return resource */
    private function openArchiveEntryStream(object $archive, string $archivePath, string $entry)
    {
        $stream = $archive instanceof ZipArchive
            ? $archive->getStream($entry)
            : @fopen($this->entryUri($archivePath, $entry), 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('백업 아카이브에서 파일을 읽을 수 없습니다: ' . $entry);
        }

        return $stream;
    }

    /** @return array{size:int,sha256:string}|null */
    private function archiveEntryIntegrity(object $archive, string $archivePath, string $entry): ?array
    {
        $stat = $this->archiveEntryStat($archive, $entry);
        if ($stat === null) {
            return null;
        }
        $stream = $this->openArchiveEntryStream($archive, $archivePath, $entry);
        try {
            $hash = hash_init('sha256');
            $bytes = hash_update_stream($hash, $stream);
            if ($bytes === false) {
                throw new RuntimeException('백업 파일의 체크섬을 계산할 수 없습니다: ' . $entry);
            }

            return ['size' => $bytes, 'sha256' => hash_final($hash)];
        } finally {
            fclose($stream);
        }
    }

    private function copyArchiveEntry(object $archive, string $archivePath, string $entry, string $destination): void
    {
        $input = $this->openArchiveEntryStream($archive, $archivePath, $entry);
        $output = @fopen($destination, 'xb');
        if (!is_resource($output)) {
            fclose($input);
            @unlink($destination);
            throw new RuntimeException('백업 파일을 임시 경로로 복사하지 못했습니다.');
        }
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('백업 파일을 읽는 중 오류가 발생했습니다.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    private function withLock(callable $callback): array
    {
        $this->ensureDirectory($this->storageDir);
        $path = $this->storageDir . '/backup.lock';
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('백업 잠금 파일을 만들 수 없습니다: ' . $path);
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('다른 백업 또는 복원 작업이 진행 중입니다.');
        }
        // CLI 백업이 웹의 자동 스키마 갱신과 겹쳐 중간 구조를 담지 않게 같은 잠금도 잡는다.
        $upgradePath = $this->storageDir . '/upgrade.lock';
        $upgradeHandle = @fopen($upgradePath, 'c');
        if ($upgradeHandle === false || !flock($upgradeHandle, LOCK_EX | LOCK_NB)) {
            if (is_resource($upgradeHandle)) fclose($upgradeHandle);
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('데이터베이스 구조 갱신이 진행 중입니다. 잠시 뒤 다시 시도해 주세요.');
        }
        try {
            return $callback();
        } finally {
            flock($upgradeHandle, LOCK_UN);
            fclose($upgradeHandle);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function resolveArchive(string $archive): string
    {
        $name = basename($archive);
        if (preg_match('/^gnucms-(?:sqlite|mysql)-\d{8}-\d{6}(?:-\d+)?\.(?:zip|tar)$/D', $name) !== 1) {
            throw new RuntimeException('백업 파일 이름이 올바르지 않습니다.');
        }
        // 웹 라우트는 basename만 전달하므로 저장 폴더 밖을 읽을 수 없다. CLI에서는
        // 재해 복구를 위해 다시 올린 아카이브의 절대·상대 경로도 직접 지정할 수 있다.
        $path = $name === $archive ? $this->archiveDir() . '/' . $name : realpath($archive);
        if (!is_string($path)) {
            throw new RuntimeException('백업 파일을 찾을 수 없습니다: ' . $name);
        }
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('백업 파일을 찾을 수 없습니다: ' . $name);
        }

        return $path;
    }

    private function uniqueArchivePath(string $driver, string $format, DateTimeImmutable $createdAt): string
    {
        $base = $this->archiveDir() . '/gnucms-' . $driver . '-' . $createdAt->format('Ymd-His');
        $path = $base . '.' . $format;
        for ($number = 2; file_exists($path) || is_link($path); $number++) {
            $path = $base . '-' . $number . '.' . $format;
        }

        return $path;
    }

    private function siteNow(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . Clock::timestamp()))->setTimezone($this->timezone);
    }

    private function writeVerification(string $archive, array $result): void
    {
        $data = [
            'verified_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'archive_size' => (int) filesize($archive),
            'archive_mtime' => (int) filemtime($archive),
            'archive_sha256' => $result['sha256'],
        ];
        @file_put_contents(
            $archive . '.verified.json',
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            LOCK_EX
        );
        @chmod($archive . '.verified.json', 0600);
    }

    private function readVerification(string $archive): array
    {
        $path = $archive . '.verified.json';
        if (!is_file($path)) {
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return [];
        }
        if (!is_array($data) || ($data['archive_size'] ?? -1) !== filesize($archive)
            || ($data['archive_mtime'] ?? -1) !== filemtime($archive)) {
            return [];
        }

        return $data;
    }

    private function entryUri(string $archive, string $entry): string
    {
        return 'phar://' . $archive . '/' . $entry;
    }

    private function validEntryName(string $entry): bool
    {
        return strlen($entry) <= 1024
            && preg_match('~^(?:database|config|files)/(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+$~D', $entry) === 1
            && !str_contains($entry, '..');
    }

    private function validRelativePath(string $path): bool
    {
        return $path !== '' && $path[0] !== '/' && !str_contains($path, '..')
            && preg_match('~^(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+$~D', $path) === 1;
    }

    private function driver(): string
    {
        return $this->db->dialect()->name();
    }

    private function sqliteDatabasePath(): ?string
    {
        $dsn = (string) ($this->config['db']['dsn'] ?? '');
        if (!str_starts_with(strtolower($dsn), 'sqlite:')) {
            return null;
        }
        $path = substr($dsn, 7);
        if ($path === '' || $path === ':memory:') {
            return null;
        }

        return $this->absolutePath($path);
    }

    private function canRestoreSqlite(): bool
    {
        if ($this->driver() !== 'sqlite') return false;
        $database = $this->sqliteDatabasePath();
        if ($database === null) return false;
        try {
            $this->assertRestoreTargets($database, $this->mediaRoots());
            $this->assertMediaRoots($this->mediaRoots());
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array{uploads:string,editor:string,avatars:string} */
    private function mediaRoots(): array
    {
        $uploads = (string) ($this->config['uploads']['dir'] ?? $this->storageDir . '/uploads');
        $editor = (string) ($this->config['editor']['dir'] ?? dirname($uploads) . '/editor');

        return [
            'uploads' => $this->absolutePath($uploads),
            'editor' => $this->absolutePath($editor),
            'avatars' => $this->storageDir . '/avatars',
        ];
    }

    private function archiveDir(): string
    {
        return $this->storageDir . '/backups/manual';
    }

    private function parseDsn(string $dsn): array
    {
        $colon = strpos($dsn, ':');
        $body = $colon === false ? '' : substr($dsn, $colon + 1);
        $parts = [];
        foreach (explode(';', $body) as $pair) {
            $equals = strpos($pair, '=');
            if ($equals === false) continue;
            $parts[strtolower(trim(substr($pair, 0, $equals)))] = trim(substr($pair, $equals + 1));
        }

        return $parts;
    }

    private function findExecutable(string $name): ?string
    {
        $path = getenv('PATH');
        if (!is_string($path)) return null;
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
            if (is_file($candidate) && is_executable($candidate)) return $candidate;
        }

        return null;
    }

    /** 비SQLite DB의 덤프 파일을 안전하게 적용하는 운영 절차. 비밀번호는 명령에 넣지 않는다. */
    private function restoreInstructions(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                '관리 화면 또는 php bin/backup.php restore 명령으로 복원할 수 있습니다.',
                '아카이브의 config/config.php는 자동 교체하지 않으므로 다른 서버로 옮길 때 별도로 비교·적용하세요.',
            ];
        }
        $dsn = $this->parseDsn((string) ($this->config['db']['dsn'] ?? ''));
        $host = (string) ($dsn['host'] ?? 'localhost');
        $port = (string) ($dsn['port'] ?? '3306');
        $database = (string) ($dsn['dbname'] ?? 'DATABASE');
        $username = (string) ($this->config['db']['username'] ?? 'USER');
        return [
            '백업 ZIP 또는 TAR에서 database/mysql.sql을 먼저 압축 해제합니다.',
            'mysql --host=' . escapeshellarg($host) . ' --port=' . escapeshellarg($port)
                . ' --user=' . escapeshellarg($username) . ' ' . escapeshellarg($database) . ' < database/mysql.sql',
            '복원 전에 서비스 쓰기를 중지하고, DB 비밀번호는 프롬프트나 안전한 옵션 파일로 입력하세요.',
        ];
    }

    /** 객체·리소스·콜백은 임시 App 설정에서만 나타나므로 fallback 설정 사본에서는 제외한다. */
    private function exportableConfig(array $config): array
    {
        $result = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->exportableConfig($value);
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('빈 파일 경로는 사용할 수 없습니다.');
        }
        if ($path[0] !== '/') {
            $path = $this->projectRoot . '/' . $path;
        }
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return '/' . implode('/', $parts);
    }

    private function pathsOverlap(string $a, string $b): bool
    {
        $a = rtrim($a, '/');
        $b = rtrim($b, '/');
        return $a === $b || str_starts_with($a . '/', $b . '/') || str_starts_with($b . '/', $a . '/');
    }

    private function siblingTemporary(string $target, string $kind, string $token): string
    {
        return dirname($target) . '/.' . basename($target) . '.gnucms-restore-' . $kind . '-' . $token;
    }

    private function ensureDirectory(string $directory, int $mode = 0775): void
    {
        if (!is_dir($directory) && !mkdir($directory, $mode, true) && !is_dir($directory)) {
            throw new RuntimeException('폴더를 만들 수 없습니다: ' . $directory);
        }
    }

    private function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}
