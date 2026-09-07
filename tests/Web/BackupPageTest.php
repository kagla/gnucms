<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\WebTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\UploadedFile;

final class BackupPageTest extends WebTestCase
{
    private string $root;
    private array $dbConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/' . GNUCMS_ID . '-backup-web-' . bin2hex(random_bytes(4));
        foreach (['uploads', 'editor', 'avatars'] as $directory) {
            mkdir($this->root . '/' . $directory, 0775, true);
        }
        $this->dbConfig = ['dsn' => 'sqlite:' . $this->root . '/board.sqlite'];
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        parent::tearDown();
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

    public function testAdminCreatesVerifiesDownloadsAndRestoresFullBackup(): void
    {
        $config = [
            'storage' => ['dir' => $this->root],
            'uploads' => ['dir' => $this->root . '/uploads', 'allowed_ext' => ['txt'], 'max_bytes' => 1024],
            'editor' => ['dir' => $this->root . '/editor'],
        ];
        $app = $this->makeApp($this->dbConfig, $config);
        $app->cms()->saveSettings(['timezone' => 'Pacific/Honolulu']);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $app->users()->verifyEmail($adminId);
        $app->db()->execute(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
            ['web_backup_test', 'before', '2026-09-04 00:00:00']
        );
        file_put_contents($this->root . '/uploads/file', 'before');
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $page = $this->body($this->get($app, '/admin/settings/maintenance'));
        self::assertStringContainsString('전체 수동 백업', $page);
        self::assertStringContainsString('php bin/backup.php create --format=zip|tar', $page);
        self::assertStringContainsString('현재 사이트 시간대(<code>Pacific/Honolulu</code>)', $page);
        self::assertStringContainsString('TAR 백업 만들기', $page);
        self::assertStringContainsString('내려받은 백업 가져오기', $page);
        self::assertStringContainsString('name="backup_file"', $page);
        if (class_exists(\ZipArchive::class)) {
            self::assertStringContainsString('ZIP 백업 만들기', $page);
        }

        $invalid = $this->post($app, '/admin/backups', [
            'csrf_token' => $_SESSION['csrf_token'], 'format' => 'rar',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('zip 또는 tar', $this->body($invalid));

        $extension = 'tar';
        Clock::freeze('2026-09-05 05:30:45');
        try {
            $created = $this->post($app, '/admin/backups', [
                'csrf_token' => $_SESSION['csrf_token'], 'format' => $extension,
            ]);
        } finally {
            Clock::unfreeze();
        }
        self::assertSame(303, $created->getStatusCode(), $this->body($created));
        parse_str((string) parse_url($created->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $name = (string) ($query['backup_created'] ?? '');
        self::assertSame('gnucms-sqlite-20260904-193045.tar', $name);

        $listed = $this->body($this->get($app, '/admin/settings/maintenance', ['backup_created' => $name]));
        self::assertStringContainsString($name, $listed);
        self::assertStringContainsString('전체 백업을 만들고 검증했습니다', $listed);
        self::assertStringContainsString('/admin/backups/' . $name, $listed);
        self::assertStringContainsString('class="backup-safe-actions"', $listed);
        self::assertStringContainsString('class="backup-danger-actions"', $listed);
        self::assertStringNotContainsString('btn btn-xs btn-error btn-outline join-item', $listed);
        self::assertStringContainsString('계속하려면 아래 칸에 <strong>복원</strong>을 입력하세요.', $listed);
        self::assertStringContainsString('placeholder="복원"', $listed);

        $download = $this->get($app, '/admin/backups/' . $name);
        self::assertSame(200, $download->getStatusCode());
        self::assertSame($extension === 'zip' ? 'application/zip' : 'application/x-tar',
            $download->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment; filename="' . $name . '"',
            $download->getHeaderLine('Content-Disposition'));
        $downloadedContents = $this->body($download);
        self::assertGreaterThan(0, strlen($downloadedContents));

        $withoutCsrf = $this->upload($app, '/admin/backups/upload', []);
        self::assertSame(403, $withoutCsrf->getStatusCode());
        $uploadUrl = '/admin/backups/upload?csrf_token=' . urlencode($_SESSION['csrf_token']);
        $missingUpload = $this->upload($app, $uploadUrl, []);
        self::assertSame(422, $missingUpload->getStatusCode());
        self::assertStringContainsString('백업 파일이 전송되지 않았습니다', $this->body($missingUpload));

        $invalidPath = $this->root . '/invalid-backup.tar';
        file_put_contents($invalidPath, 'not a GNUCMS backup');
        $invalidName = 'gnucms-sqlite-20260901-000000.tar';
        $invalidUpload = $this->upload($app, $uploadUrl, [
            'backup_file' => new UploadedFile(
                $invalidPath,
                $invalidName,
                'application/x-tar',
                filesize($invalidPath) ?: null
            ),
        ]);
        self::assertSame(422, $invalidUpload->getStatusCode());
        self::assertStringContainsString('GNUCMS 백업 형식을 읽을 수 없습니다', $this->body($invalidUpload));
        self::assertFileDoesNotExist($this->root . '/backups/manual/' . $invalidName);

        $downloadedPath = $this->root . '/downloaded-' . $name;
        file_put_contents($downloadedPath, $downloadedContents);
        $app->backups()->delete($name);
        self::assertFileDoesNotExist($this->root . '/backups/manual/' . $name);
        $uploaded = $this->upload($app, $uploadUrl, [
            'backup_file' => new UploadedFile(
                $downloadedPath,
                '다운로드한 백업 (1).tar',
                'application/x-tar',
                filesize($downloadedPath) ?: null
            ),
        ]);
        self::assertSame(303, $uploaded->getStatusCode(), $this->body($uploaded));
        self::assertStringContainsString('backup_uploaded=', $uploaded->getHeaderLine('Location'));
        self::assertStringEndsWith('#backup-uploaded', $uploaded->getHeaderLine('Location'));
        self::assertFileExists($this->root . '/backups/manual/' . $name);
        self::assertFileExists($this->root . '/backups/manual/' . $name . '.verified.json');
        $afterUpload = $this->body($this->get($app, '/admin/settings/maintenance', [
            'backup_uploaded' => $name,
        ]));
        self::assertStringContainsString('백업을 업로드하고 검증했습니다', $afterUpload);
        self::assertStringContainsString('id="backup-uploaded" class="is-uploaded-backup"', $afterUpload);
        self::assertStringContainsString('backup-uploaded-badge">방금 업로드</span>', $afterUpload);
        self::assertSame(1, substr_count($afterUpload, 'id="backup-uploaded"'));

        $verified = $this->post($app, '/admin/backups/' . $name . '/verify', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $verified->getStatusCode());
        self::assertStringContainsString('backup_verified=', $verified->getHeaderLine('Location'));

        $notConfirmed = $this->post($app, '/admin/backups/' . $name . '/restore', [
            'csrf_token' => $_SESSION['csrf_token'], 'confirmation' => 'restore',
        ]);
        self::assertSame(422, $notConfirmed->getStatusCode());
        self::assertStringContainsString('“복원”을 정확히 입력', $this->body($notConfirmed));

        $app->db()->execute(
            'UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', ['after', 'web_backup_test']
        );
        file_put_contents($this->root . '/uploads/file', 'after');
        $restored = $this->post($app, '/admin/backups/' . $name . '/restore', [
            'csrf_token' => $_SESSION['csrf_token'], 'confirmation' => '복원',
        ]);
        self::assertSame(303, $restored->getStatusCode(), $this->body($restored));
        self::assertStringContainsString('safety_backup=', $restored->getHeaderLine('Location'));
        self::assertSame('before', file_get_contents($this->root . '/uploads/file'));
        $fresh = Connection::create($this->dbConfig);
        self::assertSame('before', $fresh->selectOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = ?', ['web_backup_test']
        )['setting_value']);

        $deleted = $this->post($app, '/admin/backups/' . $name . '/delete', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $deleted->getStatusCode(), $this->body($deleted));
        self::assertStringContainsString('backup_deleted=', $deleted->getHeaderLine('Location'));
        self::assertFileDoesNotExist($this->root . '/backups/manual/' . $name);
        self::assertFileDoesNotExist($this->root . '/backups/manual/' . $name . '.verified.json');
        $afterDelete = $this->body($this->get($app, '/admin/settings/maintenance', ['backup_deleted' => $name]));
        self::assertStringContainsString($name, $afterDelete);
        self::assertStringContainsString('백업을 삭제했습니다', $afterDelete);

        $this->post($app, '/logout', ['csrf_token' => $_SESSION['csrf_token']]);
        $this->assertLoginRedirect($this->get($app, '/admin/backups/' . $name), '/admin/backups/' . $name);
    }
}
