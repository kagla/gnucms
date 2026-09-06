<?php

declare(strict_types=1);

namespace GnuCms\Tests\Maintenance;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Maintenance\BackupManager;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Slim\Psr7\UploadedFile;

final class BackupManagerTest extends DatabaseTestCase
{
    private string $root;
    private array $config;
    private Connection $db;
    private BackupManager $manager;
    private string $configFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/' . GNUCMS_ID . '-full-backup-' . bin2hex(random_bytes(4));
        foreach (['uploads/2026/09', 'editor/content-key', 'avatars'] as $directory) {
            mkdir($this->root . '/' . $directory, 0775, true);
        }
        file_put_contents($this->root . '/uploads/2026/09/attachment', 'attachment-before');
        file_put_contents($this->root . '/editor/content-key/image.jpg', 'editor-before');
        file_put_contents($this->root . '/avatars/avatar.png', 'avatar-before');

        $this->config = [
            'db' => ['dsn' => 'sqlite:' . $this->root . '/board.sqlite'],
            'storage' => ['dir' => $this->root],
            'uploads' => ['dir' => $this->root . '/uploads'],
            'editor' => ['dir' => $this->root . '/editor'],
            'auth' => ['secret' => 'test-secret-that-must-stay-private'],
        ];
        $this->configFile = $this->root . '/config.php';
        file_put_contents($this->configFile, "<?php return ['auth' => ['secret' => 'original-secret']];\n");
        $this->db = Connection::create($this->config['db']);
        (new Schema($this->db))->create();
        $this->db->execute(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
            ['backup_test', 'before', '2026-09-04 00:00:00']
        );
        $this->manager = new BackupManager($this->db, $this->config, $this->root, $this->configFile);
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (!is_dir($this->root)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->root);
    }

    public function testCreatesFullArchiveAndVerifiesEveryComponent(): void
    {
        $result = $this->manager->create();

        self::assertTrue($result['valid']);
        self::assertSame('sqlite', $result['driver']);
        $extension = class_exists(\ZipArchive::class) ? 'zip' : 'tar';
        self::assertMatchesRegularExpression('/^gnucms-sqlite-\d{8}-\d{6}\.' . $extension . '$/', $result['name']);
        $archive = $this->root . '/backups/manual/' . $result['name'];
        self::assertFileExists($archive);
        self::assertFileExists($archive . '.verified.json');

        $manifest = json_decode($this->archiveContents($archive, 'manifest.json'), true);
        self::assertSame(BackupManager::FORMAT, $manifest['format']);
        self::assertSame(BackupManager::FORMAT_VERSION, $manifest['format_version']);
        self::assertSame('database/sqlite.sqlite', $manifest['database']['path']);
        self::assertSame('config/config.php', $manifest['config']['path']);
        self::assertArrayHasKey('files/uploads/2026/09/attachment', $manifest['files']);
        self::assertArrayHasKey('files/editor/content-key/image.jpg', $manifest['files']);
        self::assertArrayHasKey('files/avatars/avatar.png', $manifest['files']);
        self::assertStringContainsString(
            'original-secret',
            $this->archiveContents($archive, 'config/config.php')
        );

        $status = $this->manager->status();
        self::assertTrue($status['can_create']);
        self::assertTrue($status['can_restore']);
        self::assertSame($extension, $status['preferred_format']);
        self::assertNotNull($status['archives'][0]['verified_at']);
    }

    #[DataProvider('connectionProvider')]
    public function testBacksUpSupportedDatabasesWithPrefixedTables(array $dbConfig): void
    {
        if (str_starts_with($dbConfig['dsn'], 'sqlite:')) {
            $dbConfig['dsn'] = 'sqlite:' . $this->root . '/prefixed.sqlite';
        }
        $dbConfig['prefix'] = 'backup_' . bin2hex(random_bytes(4)) . '_';
        $db = Connection::create($dbConfig);
        $schema = new Schema($db);
        $config = array_replace($this->config, ['db' => $dbConfig]);
        $manager = new BackupManager($db, $config, $this->root, $this->configFile);
        $status = $manager->status();
        if (!$status['can_create']) {
            self::markTestSkipped($status['unavailable_reason']);
        }

        try {
            $schema->create();
            $db->execute(
                'INSERT INTO ' . $db->table('site_settings') . ' (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                ['backup_test', 'prefixed-backup-sentinel', '2026-09-04 00:00:00']
            );
            $result = $manager->create('manual', 'tar');
            $driver = $db->dialect()->name();
            self::assertTrue($result['valid']);
            self::assertSame($driver, $result['driver']);
            $archive = $this->root . '/backups/manual/' . $result['name'];
            $manifest = json_decode($this->archiveContents($archive, 'manifest.json'), true);
            self::assertSame($dbConfig['prefix'], $manifest['database']['prefix']);
            $contents = $this->archiveContents($archive, $manifest['database']['path']);
            self::assertStringContainsString('prefixed-backup-sentinel', $contents);

            if ($driver === 'mysql') {
                self::assertSame('sql', $manifest['database']['format']);
                self::assertSame('database/mysql.sql', $manifest['database']['path']);
                self::assertStringContainsString('CREATE TABLE `' . $db->tableName('site_settings') . '`', $contents);
                self::assertFalse($status['can_restore']);
                self::assertStringContainsString('mysql --host=', implode("\n", $status['instructions']));
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('자동 복원은 SQLite 백업끼리만');
                $manager->restore($result['name']);
            } else {
                self::assertSame('sqlite3', $manifest['database']['format']);
                self::assertSame('database/sqlite.sqlite', $manifest['database']['path']);
                self::assertTrue($status['can_restore']);

                try {
                    $this->manager->restore($result['name']);
                    self::fail('프리픽스가 다른 설치에 복원할 수 없어야 합니다.');
                } catch (RuntimeException $e) {
                    self::assertStringContainsString('테이블 프리픽스가 달라', $e->getMessage());
                    self::assertSame('before', $this->db->selectOne(
                        'SELECT setting_value FROM site_settings WHERE setting_key = ?', ['backup_test']
                    )['setting_value']);
                }

                $db->execute('DELETE FROM ' . $db->table('site_settings') . ' WHERE setting_key = ?', ['backup_test']);
                $manager->restore($result['name']);
                $restored = Connection::create($dbConfig);
                self::assertSame('prefixed-backup-sentinel', $restored->selectOne(
                    'SELECT setting_value FROM ' . $restored->table('site_settings') . ' WHERE setting_key = ?', ['backup_test']
                )['setting_value']);
            }
        } finally {
            $schema->drop();
        }
    }

    public function testRejectsUnsupportedDatabaseInArchiveFilename(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('백업 파일 이름이 올바르지 않습니다');
        $this->manager->downloadPath('gnucms-unsupported-20260904-000000.tar');
    }

    public function testRejectsUnsupportedDatabaseInManifest(): void
    {
        $archive = $this->archiveWithUnsupportedDatabase();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('지원하는 GNUCMS 전체 백업 형식이 아닙니다');
        $this->manager->verify($archive);
    }

    public function testRejectsUnsupportedDatabaseInRenamedUploadAndRemovesTemporaryFile(): void
    {
        $archive = $this->archiveWithUnsupportedDatabase();
        $upload = new UploadedFile($archive, 'backup.tar', 'application/x-tar', filesize($archive) ?: null);

        try {
            $this->manager->storeUpload($upload);
            self::fail('지원하지 않는 DB 백업 업로드가 허용되었습니다.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('지원하는 GNUCMS 전체 백업 형식이 아닙니다', $e->getMessage());
            self::assertSame([], glob($this->root . '/backups/manual/.uploading-*'));
            self::assertSame([], glob($this->root . '/backups/manual/*.tar'));
        }
    }

    public function testRejectsAFileThatIsNotAGnuCmsArchive(): void
    {
        $extension = class_exists(\ZipArchive::class) ? 'zip' : 'tar';
        $name = 'gnucms-sqlite-20260904-000000.' . $extension;
        mkdir($this->root . '/backups/manual', 0775, true);
        file_put_contents($this->root . '/backups/manual/' . $name, 'not an archive');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GNUCMS 백업 형식을 읽을 수 없습니다');
        $this->manager->verify($name);
    }

    public function testRejectsAnArchiveWhoseContentsNoLongerMatchTheManifest(): void
    {
        $result = $this->manager->create();
        $path = $this->root . '/backups/manual/' . $result['name'];
        if (str_ends_with($path, '.zip')) {
            $archive = new \ZipArchive();
            self::assertTrue($archive->open($path));
            self::assertTrue($archive->addFromString('files/uploads/2026/09/attachment', 'tampered'));
            self::assertTrue($archive->close());
        } else {
            $archive = new \PharData($path);
            $archive->addFromString('files/uploads/2026/09/attachment', 'tampered');
            unset($archive);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('체크섬이 일치하지 않습니다');
        $this->manager->verify($result['name']);
    }

    public function testStillVerifiesAndRestoresTarBackupsWhenZipIsPreferred(): void
    {
        if (!class_exists(\PharData::class)) {
            self::markTestSkipped('PHP phar 확장이 필요합니다.');
        }
        $created = $this->manager->create('manual', 'tar');
        $name = $created['name'];

        $verified = $this->manager->verify($name);
        self::assertTrue($verified['valid']);
        self::assertStringEndsWith('.tar', $verified['name']);

        file_put_contents($this->root . '/uploads/2026/09/attachment', 'changed-after-tar');
        $restored = $this->manager->restore($name);
        self::assertSame($name, $restored['restored']);
        self::assertSame('attachment-before', file_get_contents($this->root . '/uploads/2026/09/attachment'));
    }

    public function testCreatesZipWhenItIsExplicitlySelected(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('PHP zip 확장이 필요합니다.');
        }

        $created = $this->manager->create('manual', 'zip');

        self::assertTrue($created['valid']);
        self::assertStringEndsWith('.zip', $created['name']);
    }

    public function testUsesSiteTimezoneForFilenameAndManifestDate(): void
    {
        Clock::freeze('2026-09-04 16:30:45');
        $manager = new BackupManager(
            $this->db,
            $this->config,
            $this->root,
            $this->configFile,
            'Asia/Seoul'
        );

        $created = $manager->create('manual', 'tar');
        $archive = $this->root . '/backups/manual/' . $created['name'];
        $manifest = json_decode($this->archiveContents($archive, 'manifest.json'), true);

        self::assertSame('gnucms-sqlite-20260905-013045.tar', $created['name']);
        self::assertSame('2026-09-05T01:30:45+09:00', $manifest['created_at']);
    }

    public function testRejectsAnUnknownArchiveFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('zip 또는 tar');
        $this->manager->create('manual', 'rar');
    }

    public function testDeletesStoredArchiveAndItsVerificationRecord(): void
    {
        $created = $this->manager->create();
        $path = $this->root . '/backups/manual/' . $created['name'];
        self::assertFileExists($path);
        self::assertFileExists($path . '.verified.json');

        $deleted = $this->manager->delete($created['name']);

        self::assertSame($created['name'], $deleted['deleted']);
        self::assertFileDoesNotExist($path);
        self::assertFileDoesNotExist($path . '.verified.json');
        self::assertSame([], $this->manager->status()['archives']);
    }

    public function testRenamedUploadedBackupGetsAUniqueCanonicalNameWithoutOverwriting(): void
    {
        $created = $this->manager->create('manual', 'tar');
        $archive = $this->root . '/backups/manual/' . $created['name'];
        $duplicate = $this->root . '/duplicate.tar';
        copy($archive, $duplicate);
        $originalHash = hash_file('sha256', $archive);

        $uploaded = $this->manager->storeUpload(new UploadedFile(
            $duplicate,
            '내가 바꾼 백업 이름 (1).tar',
            'application/x-tar',
            filesize($duplicate) ?: null
        ));

        self::assertSame($originalHash, hash_file('sha256', $archive));
        self::assertFileExists($archive . '.verified.json');
        self::assertNotSame($created['name'], $uploaded['name']);
        self::assertMatchesRegularExpression('/-2\.tar$/', $uploaded['name']);
        self::assertFileExists($this->root . '/backups/manual/' . $uploaded['name']);
        self::assertFileExists($this->root . '/backups/manual/' . $uploaded['name'] . '.verified.json');
    }

    public function testDeleteOnlyAcceptsAStoredBackupName(): void
    {
        $created = $this->manager->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('삭제할 백업 파일 이름이 올바르지 않습니다');
        $this->manager->delete('../' . $created['name']);
    }

    public function testRestoresDatabaseAndFilesAfterMakingSafetyBackup(): void
    {
        $this->db->pdo()->exec('PRAGMA journal_mode = WAL');
        $backup = $this->manager->create();
        $this->db->execute(
            'UPDATE site_settings SET setting_value = ? WHERE setting_key = ?',
            ['after', 'backup_test']
        );
        file_put_contents($this->root . '/uploads/2026/09/attachment', 'attachment-after');
        file_put_contents($this->root . '/uploads/2026/09/extra', 'extra');
        file_put_contents($this->configFile, "<?php return ['changed' => true];\n");

        $result = $this->manager->restore($backup['name']);

        self::assertSame($backup['name'], $result['restored']);
        self::assertNotSame($backup['name'], $result['safety_backup']);
        self::assertFileExists($this->root . '/backups/manual/' . $result['safety_backup']);
        self::assertSame('attachment-before', file_get_contents($this->root . '/uploads/2026/09/attachment'));
        self::assertFileDoesNotExist($this->root . '/uploads/2026/09/extra');
        self::assertSame('editor-before', file_get_contents($this->root . '/editor/content-key/image.jpg'));
        self::assertSame('avatar-before', file_get_contents($this->root . '/avatars/avatar.png'));
        self::assertStringContainsString("'changed' => true", (string) file_get_contents($this->configFile),
            '실행 중인 설정 파일은 자동 교체하지 않아야 한다');

        $restored = Connection::create($this->config['db']);
        self::assertSame('before', $restored->selectOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = ?', ['backup_test']
        )['setting_value']);
        self::assertSame('ok', $restored->pdo()->query('PRAGMA integrity_check')->fetchColumn());
        self::assertCount(2, $this->manager->status()['archives']);
    }

    public function testDoesNotRestoreWithoutAFileBackedSqliteDatabase(): void
    {
        $memory = Connection::create(['dsn' => 'sqlite::memory:']);
        (new Schema($memory))->create();
        $manager = new BackupManager($memory, [
            'db' => ['dsn' => 'sqlite::memory:'],
            'uploads' => ['dir' => $this->root . '/uploads'],
            'editor' => ['dir' => $this->root . '/editor'],
        ], $this->root . '/memory-storage');
        $backup = $manager->create();

        self::assertFalse($manager->status()['can_restore']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('메모리 SQLite');
        $manager->restore($backup['name']);
    }

    public function testRejectsAnUploadRootThatWouldRecursivelyIncludeBackups(): void
    {
        $config = $this->config;
        $config['uploads']['dir'] = $this->root;
        $manager = new BackupManager($this->db, $config, $this->root, $this->configFile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('안전하지 않은 업로드 원본 경로');
        $manager->create();
    }

    private function archiveWithUnsupportedDatabase(): string
    {
        $created = $this->manager->create('manual', 'tar');
        $archive = $this->root . '/backups/manual/' . $created['name'];
        $manifest = json_decode($this->archiveContents($archive, 'manifest.json'), true);
        $manifest['database']['driver'] = 'unsupported';
        $tar = new \PharData($archive);
        $tar->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        unset($tar);

        return $archive;
    }

    private function archiveContents(string $archive, string $entry): string
    {
        if (str_ends_with($archive, '.zip')) {
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($archive));
            $contents = $zip->getFromName($entry);
            self::assertIsString($contents);
            self::assertTrue($zip->close());

            return $contents;
        }

        $contents = file_get_contents('phar://' . $archive . '/' . $entry);
        self::assertIsString($contents);

        return $contents;
    }

}
