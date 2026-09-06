<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use PHPUnit\Framework\Attributes\DataProvider;
use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Cms\CmsRepository;
use GnuCms\Mail\MailSettingsRepository;
use GnuCms\Tests\Support\WebTestCase;

final class SchemaTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testCreatesAllTables(array $config): void
    {
        $db = $this->freshDatabase($config);

        self::assertCount(15, Schema::TABLES);

        foreach (Schema::TABLES as $table) {
            $this->assertSame(
                $table === 'site_settings' ? 11 : 0,
                (int) $db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->q($table))['c'],
                $table . ' 테이블의 초기 행 수가 올바라야 한다'
            );
        }
    }

    #[DataProvider('connectionProvider')]
    public function testMigrationMovesMailSettingsIntoNamespacedSiteSettings(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->execute('CREATE TABLE ' . $db->q('mail_settings') . ' (
            setting_key VARCHAR(50) NOT NULL, setting_value TEXT NOT NULL, updated_at VARCHAR(30) NOT NULL)');
        $this->insertLegacy($db, 'mail_settings', [
            'setting_key' => 'host', 'setting_value' => 'smtp.example.com',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        $db->execute('UPDATE ' . $db->q('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'system.schema_version']);

        (new Schema($db))->ensureCurrent();

        self::assertSame('smtp.example.com', (new MailSettingsRepository($db))->all()['host']);
        self::assertArrayNotHasKey('mail.host', (new CmsRepository($db))->settings());
        $this->assertTableMissing($db, 'mail_settings');
    }

    #[DataProvider('connectionProvider')]
    public function testMigrationMovesSiteStateIntoSystemSettings(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->execute('CREATE TABLE ' . $db->q('site_state')
            . ' (state_key VARCHAR(50) NOT NULL, state_value VARCHAR(191) NOT NULL)');
        $this->insertLegacy($db, 'site_state', ['state_key' => 'first_admin_claimed', 'state_value' => '1']);
        $this->insertLegacy($db, 'site_state', ['state_key' => 'consent_footer_defaulted', 'state_value' => '1']);
        $db->execute('UPDATE ' . $db->q('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'system.first_admin_claimed']);
        $db->execute('UPDATE ' . $db->q('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'system.schema_version']);

        (new Schema($db))->ensureCurrent();

        self::assertSame('1', $db->selectOne('SELECT setting_value FROM ' . $db->q('site_settings')
            . " WHERE setting_key = 'system.first_admin_claimed'")['setting_value']);
        self::assertSame('1', $db->selectOne('SELECT setting_value FROM ' . $db->q('site_settings')
            . " WHERE setting_key = 'system.consent_footer_defaulted'")['setting_value']);
        self::assertArrayNotHasKey('system.first_admin_claimed', (new CmsRepository($db))->settings());
        $this->assertTableMissing($db, 'site_state');
    }

    #[DataProvider('connectionProvider')]
    public function testCreateIsIdempotent(array $config): void
    {
        $db = $this->freshDatabase($config);
        $schema = new Schema($db);

        $schema->create();
        $schema->create();

        $this->assertTrue($schema->exists());
    }

    #[DataProvider('connectionProvider')]
    public function testBoardMigrationAddsGuestAuthorIpColumns(array $dbConfig): void
    {
        $db = $this->freshDatabase($dbConfig);
        $db->execute('ALTER TABLE ' . $db->table('posts') . ' DROP COLUMN author_ip');
        $db->execute('ALTER TABLE ' . $db->table('comments') . ' DROP COLUMN author_ip');

        (new Schema($db))->migrateBoards();

        self::assertSame(0, (int) $db->selectOne(
            'SELECT COUNT(author_ip) AS c FROM ' . $db->table('posts')
        )['c']);
        self::assertSame(0, (int) $db->selectOne(
            'SELECT COUNT(author_ip) AS c FROM ' . $db->table('comments')
        )['c']);
    }

    #[DataProvider('connectionProvider')]
    public function testWriteRateLimitMigrationCreatesCounterTable(array $dbConfig): void
    {
        $db = $this->freshDatabase($dbConfig);
        $db->execute('DROP TABLE ' . $db->table('write_rate_limits'));

        (new Schema($db))->migrateWriteRateLimits();

        self::assertSame(0, (int) $db->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $db->table('write_rate_limits')
        )['c']);
    }

    #[DataProvider('connectionProvider')]
    public function testProfileImageMigrationAddsUserColumns(array $dbConfig): void
    {
        $db = $this->freshDatabase($dbConfig);
        $db->execute('ALTER TABLE ' . $db->q('users') . ' DROP COLUMN avatar_file');
        $db->execute('ALTER TABLE ' . $db->q('users') . ' DROP COLUMN avatar_source');

        (new Schema($db))->migrateProfileImages();

        $id = $db->insert('users', [
            'email' => 'avatar@example.com', 'email_verified' => 1, 'password_hash' => null,
            'display_name' => '사진회원', 'is_admin' => 0, 'status' => 'active', 'session_epoch' => 0,
            'avatar_file' => '0123456789abcdef0123456789abcdef.png', 'avatar_source' => 'upload',
            'created_at' => '2026-09-02 00:00:00', 'updated_at' => '2026-09-02 00:00:00',
        ]);
        $row = $db->selectOne('SELECT avatar_file, avatar_source FROM users WHERE id = ?', [$id]);
        self::assertSame('0123456789abcdef0123456789abcdef.png', $row['avatar_file']);
        self::assertSame('upload', $row['avatar_source']);
    }

    #[DataProvider('connectionProvider')]
    public function testDropRemovesEverything(array $config): void
    {
        $db = $this->freshDatabase($config);
        $schema = new Schema($db);

        $schema->drop();

        $this->assertFalse($schema->exists());
    }

    #[DataProvider('connectionProvider')]
    public function testAutoIncrementPrimaryKeyWorks(array $config): void
    {
        $db = $this->freshDatabase($config);

        $first = $db->insert('boards', $this->boardRow('a'));
        $second = $db->insert('boards', $this->boardRow('b'));

        $this->assertGreaterThan((int) $first, (int) $second);
    }

    #[DataProvider('connectionProvider')]
    public function testDatetimeColumnRoundTripsUtcString(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->insert('boards', $this->boardRow('c'));

        $row = $db->selectOne('SELECT created_at FROM boards WHERE board_key = ?', ['c']);

        $this->assertSame('2026-08-26 01:02:03', substr((string) $row['created_at'], 0, 19));
    }

    #[DataProvider('connectionProvider')]
    public function testBoardKeyIsUnique(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->insert('boards', $this->boardRow('dup'));

        $this->expectException(\GnuCms\Error\DomainError::class);
        $db->insert('boards', $this->boardRow('dup'));
    }

    #[DataProvider('connectionProvider')]
    public function testAccountMigrationRenamesLegacyNameWithoutLosingItsValue(array $config): void
    {
        $db = $this->freshDatabase($config);
        if ($db->dialect()->name() === 'mysql') {
            $db->execute('ALTER TABLE users CHANGE display_name name VARCHAR(100) NOT NULL');
        } else {
            $db->execute('ALTER TABLE users RENAME COLUMN display_name TO name');
        }

        $id = $db->insert('users', [
            'email' => 'legacy@example.com',
            'email_verified' => 1,
            'password_hash' => null,
            'name' => '기존 표시 이름',
            'is_admin' => 0,
            'status' => 'active',
            'session_epoch' => 0,
            'created_at' => '2026-08-28 01:02:03',
            'updated_at' => '2026-08-28 01:02:03',
        ]);

        (new Schema($db))->migrateAccounts();

        $user = $db->selectOne('SELECT display_name FROM users WHERE id = ?', [$id]);
        self::assertSame('기존 표시 이름', $user['display_name']);
    }

    #[DataProvider('connectionProvider')]
    public function testCmsMigrationAddsDefaultThemeToExistingSettings(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->delete('site_settings', 'setting_key = :key', ['key' => 'theme']);

        (new Schema($db))->migrateCms();

        $setting = $db->selectOne(
            'SELECT setting_value FROM site_settings WHERE setting_key = ?',
            ['theme']
        );
        self::assertSame('default', $setting['setting_value']);
    }

    #[DataProvider('connectionProvider')]
    public function testMigrationCopiesLegacyRegistrationChoiceToSocialSignup(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->delete('site_settings', 'setting_key = :key', ['key' => 'social_registration_enabled']);
        $db->delete('site_settings', 'setting_key = :key', ['key' => 'password_login_enabled']);
        $db->delete('site_settings', 'setting_key = :key', ['key' => 'social_login_enabled']);
        $db->execute('UPDATE ' . $db->q('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'registration_enabled']);

        (new Schema($db))->migrateAll();

        self::assertSame('0', $db->selectOne(
            'SELECT setting_value FROM ' . $db->q('site_settings') . ' WHERE setting_key = ?',
            ['social_registration_enabled']
        )['setting_value']);
        self::assertSame('1', $db->selectOne(
            'SELECT setting_value FROM ' . $db->q('site_settings') . ' WHERE setting_key = ?',
            ['password_login_enabled']
        )['setting_value']);
        self::assertSame('1', $db->selectOne(
            'SELECT setting_value FROM ' . $db->q('site_settings') . ' WHERE setting_key = ?',
            ['social_login_enabled']
        )['setting_value']);
    }

    /** 옛 판에서 올라와도 새 칸과 새 표가 빠짐없이 생기고, 기존 동의가 그대로 옮겨진다. */
    #[DataProvider('connectionProvider')]
    public function testMigrationAddsConsentUsesAndMovesRecords(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $db = $app->db();

        // VERSION 14의 옛 컬럼과 동의 표를 되살린다.
        $db->execute('ALTER TABLE ' . $db->q('contents') . ' ADD COLUMN consent_key VARCHAR(20) NULL');
        $db->execute('ALTER TABLE ' . $db->q('contents')
            . ' ADD COLUMN consent_order INTEGER NOT NULL DEFAULT 0');
        $db->execute('ALTER TABLE ' . $db->q('contents')
            . ' ADD COLUMN consent_required INTEGER NOT NULL DEFAULT 1');
        $db->execute('CREATE TABLE ' . $db->q('user_consents') . ' (
            id BIGINT NULL, user_id BIGINT NOT NULL, consent_type VARCHAR(20) NOT NULL,
            content_id BIGINT NOT NULL, content_updated_at TEXT NOT NULL, agreed SMALLINT NOT NULL DEFAULT 1,
            agreed_at TEXT NOT NULL)');

        // 옛 칸이 있던 시절부터 있던 약관 글을 미리 만들어 둔다.
        $id = $app->cms()->createPage([
            'slug' => 'terms', 'title' => '이용약관', 'content' => '본문',
            'seo_description' => null, 'status' => 'published', 'show_in_menu' => 0, 'sort_order' => 0,
            'consent_key' => 'terms', 'consent_order' => 10, 'consent_required' => 1,
        ]);

        // 옛 모양을 되살린다. DROP COLUMN 을 못 쓰는 판이면 이 단언은 건너뛴다.
        $canDropColumn = true;
        try {
            $db->execute('ALTER TABLE ' . $db->q('contents') . ' DROP COLUMN is_consent');
        } catch (DomainError $e) {
            $canDropColumn = false;
        }
        if (!$canDropColumn) {
            // 칸을 못 지웠으니 마이그레이션이 채울 값을 미리 손으로 채워, 나머지 갈래는 그대로 돈다.
            $db->execute('UPDATE ' . $db->q('contents')
                . ' SET is_consent = 1 WHERE consent_key IS NOT NULL');
        }

        // 옛 모양으로 되돌린다: 붙임 표를 지우고 판 도장을 낮춘다.
        $db->execute('DROP TABLE IF EXISTS ' . $db->q('consent_uses'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->q('consents_given'));
        $db->execute('UPDATE ' . $db->q('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'system.schema_version']);

        $userId = $app->users()->create('a@example.com', password_hash('x', PASSWORD_DEFAULT), 'A', false);
        $this->insertLegacy($db, 'user_consents', [
            'user_id' => $userId, 'consent_type' => 'terms', 'content_id' => $id,
            'content_updated_at' => '2026-01-01 00:00:00', 'agreed' => 1,
            'agreed_at' => '2026-01-01 00:00:00',
        ]);
        // 옮기기는 한 줄만 되는 게 아니다. 동의 안 함(0) 도 그대로 넘어와야 한다.
        $otherId = $app->users()->create('b@example.com', password_hash('x', PASSWORD_DEFAULT), 'B', false);
        $this->insertLegacy($db, 'user_consents', [
            'user_id' => $otherId, 'consent_type' => 'terms', 'content_id' => $id,
            'content_updated_at' => '2026-01-01 00:00:00', 'agreed' => 0,
            'agreed_at' => '2026-01-02 00:00:00',
        ]);

        (new Schema($db))->ensureCurrent();

        if ($canDropColumn) {
            $page = $db->selectOne('SELECT is_consent FROM ' . $db->q('contents') . ' WHERE id = ?', [$id]);
            self::assertSame(1, (int) $page['is_consent']);
        }

        $use = $db->selectOne('SELECT * FROM ' . $db->q('consent_uses')
            . ' WHERE scope = ? AND content_id = ?', ['signup', $id]);
        self::assertNotNull($use);
        self::assertSame(1, (int) $use['required']);
        self::assertSame(10, (int) $use['sort_order']);

        $given = $db->selectOne('SELECT * FROM ' . $db->q('consents_given')
            . ' WHERE subject_type = ? AND subject_id = ?', ['user', $userId]);
        self::assertNotNull($given);
        self::assertSame('signup', $given['scope']);
        self::assertSame('terms', $given['consent_type']);
        self::assertSame(1, (int) $given['agreed']);

        $other = $db->selectOne('SELECT * FROM ' . $db->q('consents_given')
            . ' WHERE subject_type = ? AND subject_id = ?', ['user', $otherId]);
        self::assertNotNull($other, '두 번째 줄도 함께 넘어와야 한다');
        self::assertSame('signup', $other['scope']);
        self::assertSame(0, (int) $other['agreed'], '동의 안 함은 안 함 그대로 남는다');
        self::assertSame(2, (int) $db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->q('consents_given'))['c']);
        $this->assertTableMissing($db, 'user_consents');
        self::assertFalse($this->columnExists($db, 'contents', 'consent_key'));
    }

    private function boardRow(string $key): array
    {
        return [
            'board_key'    => $key,
            'name'         => '게시판 ' . $key,
            'description'  => null,
            'categories'   => '[]',
            'managers'     => '[]',
            'perm_read'    => 'guest',
            'perm_write'   => 'member',
            'perm_comment' => 'member',
            'use_secret'   => 0,
            'use_file'     => 0,
            'use_category' => 0,
            'show_in_header' => 0,
            'per_page'     => 20,
            'sort_order'   => 0,
            'created_at'   => '2026-08-26 01:02:03',
            'updated_at'   => '2026-08-26 01:02:03',
        ];
    }
    /** 옛 설치에 겹치는 표시 이름이 있으면 마이그레이션이 숫자를 붙여 갈라 놓고 고유 인덱스를 건다. */
    #[DataProvider('connectionProvider')]
    public function testMigrationDeduplicatesDisplayNames(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $db = $app->db();
        $db->execute($db->dialect()->name() === 'mysql'
            ? 'DROP INDEX ' . $db->q('ux_users_display_name') . ' ON ' . $db->q('users')
            : 'DROP INDEX IF EXISTS ' . $db->q('ux_users_display_name'));
        foreach (['a@example.com', 'b@example.com', 'c@example.com'] as $email) {
            $db->insert('users', [
                'email' => $email, 'email_verified' => 1, 'password_hash' => 'x', 'display_name' => '홍길동',
                'is_admin' => 0, 'status' => 'active', 'session_epoch' => 0,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
            ]);
        }
        $db->execute('UPDATE ' . $db->q('site_settings') . ' SET setting_value = ? WHERE setting_key = ?', ['0', 'system.schema_version']);

        (new Schema($db))->ensureCurrent();

        $names = array_column($db->select('SELECT display_name FROM ' . $db->q('users') . ' ORDER BY id ASC'), 'display_name');
        self::assertSame(['홍길동', '홍길동2', '홍길동3'], $names);
        self::assertNotNull($app->users()->findByDisplayName('홍길동2'));
    }

    /** 자동 증가 ID가 없는 옛 테이블 fixture는 lastInsertId를 요청하지 않는다. */
    private function insertLegacy(Connection $db, string $table, array $row): void
    {
        $db->execute('INSERT INTO ' . $db->q($table) . ' ('
            . implode(', ', array_map($db->q(...), array_keys($row))) . ') VALUES ('
            . implode(', ', array_fill(0, count($row), '?')) . ')', array_values($row));
    }

    private function assertTableMissing(Connection $db, string $table): void
    {
        try {
            $db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->q($table));
            self::fail($table . ' 테이블이 없어야 한다');
        } catch (DomainError $e) {
            self::assertTrue(true);
        }
    }

    private function columnExists(Connection $db, string $table, string $column): bool
    {
        try {
            $db->selectOne('SELECT ' . $column . ' FROM ' . $db->q($table) . ' LIMIT 1');
            return true;
        } catch (DomainError $e) {
            return false;
        }
    }

    #[DataProvider('connectionProvider')]
    public function testPostsHaveNoticeScope(array $config): void
    {
        $db = $this->freshDatabase($config);

        $db->execute(
            'INSERT INTO ' . $db->q('posts')
            . ' (board_id, title, content, author_name, is_notice, is_secret, view_count, comment_count, created_at, updated_at)'
            . ' VALUES (1, ?, ?, ?, 1, 0, 0, 0, ?, ?)',
            ['공지', '본문', '관리자', '2026-08-31 00:00:00', '2026-08-31 00:00:00']
        );

        $row = $db->selectOne('SELECT notice_scope FROM ' . $db->q('posts'));
        self::assertSame('board', $row['notice_scope'], '기본값은 이 게시판 공지다');
    }

}
