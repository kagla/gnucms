<?php

declare(strict_types=1);

namespace GnuCms\Db;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/**
 * DDL 은 치환자 3개({AUTO_PK}, {DATETIME}, {TEXT})만 방언별로 바뀌고
 * 나머지는 세 DB 공통 문법이다.
 */
final class Schema
{
    public const TABLES = [
        'boards', 'posts', 'comments', 'users', 'user_tokens', 'user_identities',
        'site_settings', 'contents', 'consent_uses', 'consents_given', 'notifications',
        'password_attempts', 'login_events', 'write_rate_limits',
        'extension_schemas',
    ];

    private const INDEXES = [
        'ux_boards_key', 'ix_posts_list', 'ix_posts_category', 'ix_comments_post',
        'ux_users_email', 'ux_users_display_name', 'ux_user_tokens_hash', 'ix_user_tokens_user',
        'ux_user_identities_provider', 'ix_user_identities_user', 'ux_site_settings_key',
        'ux_contents_slug', 'ix_contents_public', 'ix_contents_listing', 'ix_notifications_user',
        'ux_password_attempts', 'ix_comments_parent',
        'ux_consent_uses', 'ix_consent_uses_content', 'ux_consents_given', 'ix_consents_given_content',
        'ix_login_events_user', 'ix_login_events_ip', 'ix_login_events_time',
        'ux_write_rate_limits',
    ];

    /** @var Connection */
    private $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function exists(): bool
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('boards'));

            return true;
        } catch (DomainError $e) {
            // Throwable 이 아니라 DomainError 로 좁혀 잡는다. Connection 은 PDOException 을
            // DomainError 로 감싸므로 "테이블 없음" 은 여기로 온다. Throwable 까지 잡으면
            // Connection 이나 Schema 자체의 버그(TypeError 등)가 "테이블 없음" 으로
            // 둔갑해 조용히 묻힌다.
            return false;
        }
    }

    /**
     * 코드가 요구하는 스키마 판. 컬럼을 늘릴 때마다 하나씩 올린다.
     * DB 에 적힌 값이 이 값보다 낮으면 ensureCurrent() 가 마이그레이션을 돌린다.
     */
    public const VERSION = '22';

    /**
     * DB 에 적어 두는 도장. 판 번호 뒤에 이 파일의 내용 해시를 붙인다.
     *
     * 판 번호만 적어 두면, 판을 올린 뒤 마이그레이션을 더 손볼 때 그 사이 들어온 요청이
     * '다 됐다' 도장을 먼저 찍어 버린다. 그러면 나중에 추가한 칸은 영영 건너뛴다.
     * 파일이 바뀌면 도장도 달라지므로 그런 어긋남이 스스로 풀린다.
     * migrate* 는 모두 멱등이라 한 번 더 도는 값은 싸다.
     */
    public function stamp(): string
    {
        $hash = hash_file('xxh128', __FILE__);
        return self::VERSION . '.' . substr($hash === false ? '' : $hash, 0, 12);
    }

    /** DB 에 적힌 도장. site_settings 가 없는 아주 오래된 설치면 null. */
    public function storedStamp(): ?string
    {
        try {
            $row = $this->db->selectOne(
                'SELECT setting_value FROM ' . $this->db->table('site_settings') . ' WHERE setting_key = ?',
                ['system.schema_version']
            );
        } catch (DomainError $e) {
            return null;
        }

        if ($row !== null) {
            return (string) $row['setting_value'];
        }

        // VERSION 14 이하 설치가 새 이름으로 옮겨지기 전의 도장.
        $legacy = $this->db->selectOne(
            'SELECT setting_value FROM ' . $this->db->table('site_settings') . ' WHERE setting_key = ?',
            ['schema_version']
        );
        return $legacy === null ? null : (string) $legacy['setting_value'];
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

    /** 지금까지의 마이그레이션을 순서대로 모두 적용한다. */
    public function migrateAll(): void
    {
        $this->migrateAccounts();
        $this->migrateDisplayNames();
        // 표 이름을 먼저 옮겨야 migrateCms() 가 빈 표를 새로 만들지 않는다.
        $this->migrateContentTableName();
        $this->migrateCms();
        $this->migrateLoginSettings();
        $this->migrateRegistrationSettings();
        $this->migrateDefaultTheme();
        $this->migrateBoards();
        $this->migrateEditorImages();
        $this->migrateNotifications();
        $this->migratePasswordThrottle();
        $this->migrateLoginEvents();
        $this->migrateWriteRateLimits();
        $this->migrateProfileImages();
        $this->migrateExtensionSchemas();
        $stamp = $this->stamp();
        $this->ensureSiteSetting('system.schema_version', $stamp);
        $this->db->execute(
            'UPDATE ' . $this->db->table('site_settings')
            . ' SET setting_value = ? WHERE setting_key = ?',
            [$stamp, 'system.schema_version']
        );
        foreach (['schema_version', 'schema_upgraded_at', 'schema_backup'] as $legacyKey) {
            $this->db->delete('site_settings', 'setting_key = :key', ['key' => $legacyKey]);
        }
    }

    public function create(): void
    {
        if ($this->exists()) {
            return;
        }

        foreach ($this->statements() as $sql) {
            $this->db->execute($this->expand($sql));
        }

        // 새로 만든 스키마는 이미 최신이다. 첫 요청에서 헛돌지 않게 표시해 둔다.
        $this->ensureSiteSetting('system.schema_version', $this->stamp());
    }

    public function migrateExtensionSchemas(): void
    {
        if (!$this->tableExists('extension_schemas')) {
            foreach ($this->extensionSchemaStatements() as $sql) $this->db->execute($this->expand($sql));
        }
    }

    private function extensionSchemaStatements(): array
    {
        return ['CREATE TABLE extension_schemas (
            package_key VARCHAR(80) PRIMARY KEY,
            schema_version INTEGER NOT NULL,
            table_names {TEXT} NOT NULL,
            state VARCHAR(16) NOT NULL
        ){SUFFIX}'];
    }

    /** 기존 게시판 설치에 회원 테이블만 안전하게 추가한다. */
    public function migrateAccounts(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('users'));
        } catch (DomainError $e) {
            foreach ($this->accountStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
            return;
        }

        try {
            $this->db->selectOne('SELECT email_verified FROM ' . $this->db->table('users') . ' LIMIT 1');
        } catch (DomainError $e) {
            $this->db->execute(
                'ALTER TABLE ' . $this->db->table('users')
                . ' ADD COLUMN email_verified SMALLINT NOT NULL DEFAULT 0'
            );
        }

        try {
            $this->db->selectOne('SELECT display_name FROM ' . $this->db->table('users') . ' LIMIT 1');
        } catch (DomainError $e) {
            $this->renameUserDisplayNameColumn();
        }

        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('user_tokens'));
        } catch (DomainError $e) {
            foreach ($this->tokenStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }

        $this->addColumnIfMissing('users', 'registered_ip', 'VARCHAR(45) NULL');
        $this->addColumnIfMissing('users', 'withdrawn_ip', 'VARCHAR(45) NULL');
        $this->addColumnIfMissing('users', 'withdrawn_at',
            $this->db->dialect()->typeMap()['{DATETIME}'] . ' NULL');

        $this->migrateOauth();
    }

    /**
     * 기존 설치에 게시판 목록 형태(list_type) 컬럼을 추가한다.
     * migrateAccounts()/migrateCms() 와 같은 방식으로, 업그레이드할 때 한 번 부른다.
     */
    public function migrateBoards(): void
    {
        $this->addColumnIfMissing('boards', 'list_type', 'VARCHAR(20) NOT NULL DEFAULT \'list\'');
        $this->addColumnIfMissing('boards', 'home_limit', 'INTEGER NOT NULL DEFAULT 5');
        $this->addColumnIfMissing('boards', 'show_in_header', 'SMALLINT NOT NULL DEFAULT 0');
        $this->addColumnIfMissing('boards', 'show_list_below_view', 'SMALLINT NOT NULL DEFAULT 0');

        // 공지가 이 게시판만인지 전체인지. 옛 공지는 전부 이 게시판 공지로 본다.
        $this->addColumnIfMissing('posts', 'notice_scope', "VARCHAR(10) NOT NULL DEFAULT 'board'");
        $this->addColumnIfMissing('posts', 'author_ip', 'VARCHAR(45) NULL');
        $this->addColumnIfMissing('comments', 'author_ip', 'VARCHAR(45) NULL');
    }

    /** 글·댓글 본문 편집기가 올린 이미지를 묶어 두는 키. 업그레이드할 때 한 번 부른다. */
    public function migrateEditorImages(): void
    {
        foreach (['posts', 'comments'] as $table) {
            $this->addColumnIfMissing($table, 'image_key', 'VARCHAR(32) NULL');
        }
    }

    public function migrateProfileImages(): void
    {
        $this->addColumnIfMissing('users', 'avatar_file', 'VARCHAR(40) NULL');
        $this->addColumnIfMissing('users', 'avatar_source', 'VARCHAR(10) NULL');
    }

    /** 알림함 표. 기존 설치에는 없으므로 업그레이드할 때 만든다. */
    public function migrateNotifications(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('notifications'));
        } catch (DomainError $e) {
            foreach ($this->notificationStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }
    }

    /** 비밀번호 대입 방어 기록. 없으면 만든다. */
    public function migratePasswordThrottle(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('password_attempts'));
        } catch (DomainError $e) {
            foreach ($this->passwordThrottleStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }
    }

    /** 성공·실패 로그인 이력. 잠금 집계(password_attempts)와 달리 지우지 않고 한 건씩 남긴다. */
    public function migrateLoginEvents(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('login_events'));
        } catch (DomainError $e) {
            foreach ($this->loginEventStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }
    }

    /** 글·댓글 도배 방지 카운터. 기존 설치에는 없으므로 업그레이드할 때 만든다. */
    public function migrateWriteRateLimits(): void
    {
        if ($this->tableExists('write_rate_limits')) {
            return;
        }
        foreach ($this->writeRateLimitStatements() as $sql) {
            $this->db->execute($this->expand($sql));
        }
    }

    /**
     * 컬럼이 없으면 더한다. 표 자체가 없으면 아직 설치 전이므로 그냥 넘어간다
     * (표를 만드는 일은 create() 의 몫이다).
     */
    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table($table));
        } catch (DomainError $e) {
            return;
        }

        try {
            $this->db->selectOne('SELECT ' . $column . ' FROM ' . $this->db->table($table) . ' LIMIT 1');
        } catch (DomainError $e) {
            $this->db->execute('ALTER TABLE ' . $this->db->table($table)
                . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    public function migrateCms(): void
    {
        if (!$this->tableExists('site_settings')) {
            foreach ($this->settingsStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }
        $this->ensureSiteSetting('theme', 'default');
        $this->migrateSystemSettings();
        $this->migrateMailSettings();

        if (!$this->tableExists('contents')) {
            foreach ($this->contentStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }

        $this->addColumnIfMissing('contents', 'deleted_at',
            $this->db->dialect()->typeMap()['{DATETIME}'] . ' NULL');
        $this->addColumnIfMissing('contents', 'image_key', 'VARCHAR(32) NULL');

        $hadConsentKey = $this->columnExists('contents', 'consent_key');
        $hadConsentOrder = $this->columnExists('contents', 'consent_order');
        $hadConsentRequired = $this->columnExists('contents', 'consent_required');
        if (!$this->columnExists('contents', 'is_consent')) {
            $this->db->execute('ALTER TABLE ' . $this->db->table('contents')
                . ' ADD COLUMN is_consent SMALLINT NOT NULL DEFAULT 0');
            if ($hadConsentKey) {
                $this->db->execute('UPDATE ' . $this->db->table('contents')
                    . ' SET is_consent = 1 WHERE consent_key IS NOT NULL');
            } else {
                $this->db->execute('UPDATE ' . $this->db->table('contents')
                    . " SET is_consent = 1 WHERE slug IN ('terms', 'privacy')");
            }
        }

        if (!$this->tableExists('consent_uses')) {
            foreach ($this->consentUseStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
            $required = $hadConsentRequired ? 'consent_required' : '1 AS consent_required';
            $order = $hadConsentOrder ? 'consent_order' : '0 AS consent_order';
            $rows = $this->db->select('SELECT id, ' . $required . ', ' . $order . ' FROM '
                . $this->db->table('contents') . ' WHERE is_consent = 1');
            foreach ($rows as $row) {
                $this->db->insert('consent_uses', [
                    'scope' => 'signup',
                    'content_id' => (int) $row['id'],
                    'required' => (int) $row['consent_required'],
                    'sort_order' => (int) $row['consent_order'],
                    'created_at' => Clock::now(),
                ]);
            }
        }

        // 이용약관의 slug 를 terms -> service 로 옮긴다. 정식 주소가 /terms/{slug} 가 되면서
        // /terms/terms 라는 어색한 주소가 생기기 때문이다. service 자리가 이미 차 있으면 건드리지 않는다.
        $termsRow = $this->db->selectOne('SELECT id FROM ' . $this->db->table('contents')
            . " WHERE slug = 'terms' AND is_consent = 1 AND deleted_at IS NULL");
        if ($termsRow !== null) {
            $taken = $this->db->selectOne('SELECT id FROM ' . $this->db->table('contents')
                . " WHERE slug = 'service'");
            if ($taken === null) {
                $this->db->execute('UPDATE ' . $this->db->table('contents')
                    . " SET slug = 'service' WHERE id = ?", [(int) $termsRow['id']]);
            }
        }

        // 약관의 show_in_menu 는 이제 '하단에 표시' 라는 뜻이다. 표시를 걸러 내기 시작하면
        // 기존 약관이 하단에서 통째로 사라지므로, 처음 한 번만 전부 켜 준다.
        // 파일이 바뀔 때마다 마이그레이션이 다시 도니, 관리자가 끈 것을 되켜지 않게 잠근다.
        if ($this->siteSetting('system.consent_footer_defaulted') === null) {
            $this->db->execute('UPDATE ' . $this->db->table('contents')
                . ' SET show_in_menu = 1 WHERE is_consent = 1');
            $this->ensureSiteSetting('system.consent_footer_defaulted', '1');
        }

        if (!$this->tableExists('consents_given')) {
            foreach ($this->consentsGivenStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }
        if ($this->tableExists('user_consents')) {
            $rows = $this->db->select('SELECT * FROM ' . $this->db->table('user_consents'));
            foreach ($rows as $row) {
                $exists = $this->db->selectOne('SELECT id FROM ' . $this->db->table('consents_given')
                    . " WHERE subject_type = 'user' AND subject_id = ? AND scope = 'signup' AND content_id = ?",
                    [(int) $row['user_id'], (int) $row['content_id']]);
                if ($exists !== null) {
                    continue;
                }
                $this->db->insert('consents_given', [
                    'subject_type' => 'user',
                    'subject_id' => (int) $row['user_id'],
                    'scope' => 'signup',
                    'content_id' => (int) $row['content_id'],
                    'consent_type' => (string) $row['consent_type'],
                    'content_updated_at' => (string) $row['content_updated_at'],
                    'agreed' => (int) ($row['agreed'] ?? 1),
                    'agreed_at' => (string) $row['agreed_at'],
                    'agreed_ip' => null,
                    'agreed_ua' => null,
                ]);
            }
            $this->db->execute('DROP TABLE ' . $this->db->table('user_consents'));
        }

        $this->dropIndexIfExists('contents', 'ux_contents_consent');
        $this->dropIndexIfExists('contents', 'ix_contents_is_consent');
        foreach (['consent_key', 'consent_order', 'consent_required'] as $column) {
            $this->dropColumnIfExists('contents', $column);
        }
        $this->createIndexIfMissing('ix_contents_listing', 'CREATE INDEX ix_contents_listing ON contents'
            . ' (is_consent, deleted_at, status, show_in_menu, sort_order, id)');
        $this->createIndexIfMissing('ix_comments_parent', 'CREATE INDEX ix_comments_parent ON comments (parent_id)');

        if ($this->tableExists('site_state')) {
            $this->db->execute('DROP TABLE ' . $this->db->table('site_state'));
        }
    }

    public function drop(): void
    {
        $tables = array_merge(self::TABLES, ['user_consents', 'mail_settings', 'site_state']);
        foreach (array_reverse($tables) as $table) {
            try {
                $this->db->execute('DROP TABLE IF EXISTS ' . $this->db->table($table));
            } catch (DomainError $e) {
                // 이미 없는 경우는 성공으로 본다.
            }
        }
    }

    private function expand(string $sql): string
    {
        $map = $this->db->dialect()->typeMap();
        $map['{SUFFIX}'] = $this->db->dialect()->tableSuffix();

        $sql = strtr($sql, $map);
        $identifiers = array_merge(self::INDEXES, self::TABLES);
        usort($identifiers, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '/\\b(?:' . implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            $identifiers
        )) . ')\\b/';

        return (string) preg_replace_callback($pattern, function (array $match): string {
            return in_array($match[0], self::INDEXES, true)
                ? $this->db->index($match[0])
                : $this->db->table($match[0]);
        }, $sql);
    }

    /** @return string[] */
    private function statements(): array
    {
        return array_merge([
            'CREATE TABLE boards (
                id            {AUTO_PK},
                board_key     VARCHAR(50)  NOT NULL,
                name          VARCHAR(100) NOT NULL,
                description   {TEXT}       NULL,
                categories    {TEXT}       NULL,
                managers      {TEXT}       NULL,
                perm_read     VARCHAR(10)  NOT NULL DEFAULT \'guest\',
                perm_write    VARCHAR(10)  NOT NULL DEFAULT \'member\',
                perm_comment  VARCHAR(10)  NOT NULL DEFAULT \'member\',
                use_secret    SMALLINT     NOT NULL DEFAULT 0,
                use_file      SMALLINT     NOT NULL DEFAULT 0,
                use_category  SMALLINT     NOT NULL DEFAULT 0,
                list_type     VARCHAR(20)  NOT NULL DEFAULT \'list\',
                home_limit    INTEGER      NOT NULL DEFAULT 5,
                show_in_header SMALLINT    NOT NULL DEFAULT 0,
                show_list_below_view SMALLINT NOT NULL DEFAULT 0,
                per_page      INTEGER      NOT NULL DEFAULT 20,
                sort_order    INTEGER      NOT NULL DEFAULT 0,
                created_at    {DATETIME}   NOT NULL,
                updated_at    {DATETIME}   NOT NULL
            ){SUFFIX}',

            'CREATE UNIQUE INDEX ux_boards_key ON boards (board_key)',

            'CREATE TABLE posts (
                id             {AUTO_PK},
                board_id       BIGINT       NOT NULL,
                category       VARCHAR(50)  NULL,
                title          VARCHAR(200) NOT NULL,
                content        {TEXT}       NOT NULL,
                author_id      VARCHAR(64)  NULL,
                author_name    VARCHAR(100) NOT NULL,
                author_ip      VARCHAR(45)  NULL,
                guest_password VARCHAR(255) NULL,
                is_notice      SMALLINT     NOT NULL DEFAULT 0,
                notice_scope   VARCHAR(10)  NOT NULL DEFAULT \'board\',
                is_secret      SMALLINT     NOT NULL DEFAULT 0,
                view_count     INTEGER      NOT NULL DEFAULT 0,
                comment_count  INTEGER      NOT NULL DEFAULT 0,
                attachments    {TEXT}       NULL,
                image_key      VARCHAR(32)  NULL,
                created_at     {DATETIME}   NOT NULL,
                updated_at     {DATETIME}   NOT NULL,
                deleted_at     {DATETIME}   NULL
            ){SUFFIX}',

            'CREATE INDEX ix_posts_list ON posts (board_id, deleted_at, is_notice, id)',
            'CREATE INDEX ix_posts_category ON posts (board_id, category)',

            'CREATE TABLE comments (
                id             {AUTO_PK},
                board_id       BIGINT       NOT NULL,
                post_id        BIGINT       NOT NULL,
                parent_id      BIGINT       NULL,
                depth          SMALLINT     NOT NULL DEFAULT 0,
                content        {TEXT}       NOT NULL,
                author_id      VARCHAR(64)  NULL,
                author_name    VARCHAR(100) NOT NULL,
                author_ip      VARCHAR(45)  NULL,
                guest_password VARCHAR(255) NULL,
                is_secret      SMALLINT     NOT NULL DEFAULT 0,
                image_key      VARCHAR(32)  NULL,
                created_at     {DATETIME}   NOT NULL,
                updated_at     {DATETIME}   NOT NULL,
                deleted_at     {DATETIME}   NULL
            ){SUFFIX}',

            'CREATE INDEX ix_comments_post ON comments (post_id, id)',
            'CREATE INDEX ix_comments_parent ON comments (parent_id)',
        ], $this->accountStatements(), $this->settingsStatements(), $this->contentStatements(),
            $this->consentUseStatements(), $this->consentsGivenStatements(),
            $this->notificationStatements(),
            $this->passwordThrottleStatements(), $this->loginEventStatements(),
            $this->writeRateLimitStatements(), $this->extensionSchemaStatements());
    }

    private function accountStatements(): array
    {
        return array_merge([
            $this->usersTableStatement(),
            'CREATE UNIQUE INDEX ux_users_email ON users (email)',
            'CREATE UNIQUE INDEX ux_users_display_name ON users (display_name)',
        ], $this->tokenStatements(), $this->identityStatements());
    }

    private function usersTableStatement(): string
    {
        return 'CREATE TABLE users (
                id             {AUTO_PK},
                email          VARCHAR(191) NOT NULL,
                email_verified SMALLINT     NOT NULL DEFAULT 0,
                password_hash  VARCHAR(255) NULL,
                display_name   VARCHAR(100) NOT NULL,
                is_admin       SMALLINT     NOT NULL DEFAULT 0,
                status         VARCHAR(10)  NOT NULL DEFAULT \'active\',
                session_epoch  INTEGER      NOT NULL DEFAULT 0,
                registered_ip  VARCHAR(45)  NULL,
                withdrawn_ip   VARCHAR(45)  NULL,
                withdrawn_at   {DATETIME}   NULL,
                avatar_file    VARCHAR(40)  NULL,
                avatar_source  VARCHAR(10)  NULL,
                created_at     {DATETIME}   NOT NULL,
                updated_at     {DATETIME}   NOT NULL
            ){SUFFIX}';
    }

    private function tokenStatements(): array
    {
        return [
            'CREATE TABLE user_tokens (
                id         {AUTO_PK},
                user_id    BIGINT      NOT NULL,
                purpose    VARCHAR(20) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at {DATETIME}  NOT NULL,
                used_at    {DATETIME}  NULL,
                created_at {DATETIME}  NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_user_tokens_hash ON user_tokens (token_hash)',
            'CREATE INDEX ix_user_tokens_user ON user_tokens (user_id, purpose)',
        ];
    }

    private function identityStatements(): array
    {
        return [
            'CREATE TABLE user_identities (
                id           {AUTO_PK},
                user_id      BIGINT       NOT NULL,
                provider     VARCHAR(20)  NOT NULL,
                provider_uid VARCHAR(191) NOT NULL,
                created_at   {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_user_identities_provider ON user_identities (provider, provider_uid)',
            'CREATE INDEX ix_user_identities_user ON user_identities (user_id)',
        ];
    }

    private function settingsStatements(): array
    {
        return [
            'CREATE TABLE site_settings (
                setting_key   VARCHAR(50)  NOT NULL,
                setting_value {TEXT}       NOT NULL,
                updated_at    {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_site_settings_key ON site_settings (setting_key)',
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('password_login_enabled', '1', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('social_login_enabled', '1', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('site_name', '" . GNUCMS . "', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('site_tagline', '가볍게 시작하는 기초 커뮤니티', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('home_title', '가볍게 시작하고, 오래 이어지는 공간', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('home_intro', '필요한 페이지와 커뮤니티를 한곳에서 운영하세요.', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('registration_enabled', '1', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('social_registration_enabled', '1', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('theme', 'default', '2026-01-01 00:00:00')",
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('system.first_admin_claimed', '0', '2026-01-01 00:00:00')",
        ];
    }

    /**
     * pages 표를 contents 로 옮긴다.
     * 관리 화면(내용 관리)도 주소(/content/{slug})도 이미 '내용' 인데 표만 pages 였다.
     * 표 이름만 바꾸면 인덱스는 그대로 따라오지만, 새로 설치한 곳과 이름이 갈리므로
     * 인덱스도 새 이름으로 다시 만든다. 여러 번 돌려도 안전하다.
     */
    private function migrateContentTableName(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('contents'));
            return; // 이미 새 이름이다
        } catch (DomainError $e) {
            // 아직 옛 이름이거나, 둘 다 없다
        }

        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('pages'));
        } catch (DomainError $e) {
            return; // 옛 표도 없다. migrateCms() 가 새로 만든다
        }

        $this->db->execute(
            'ALTER TABLE ' . $this->db->table('pages') . ' RENAME TO ' . $this->db->table('contents')
        );

        $mysql = $this->db->dialect()->name() === 'mysql';
        foreach ([
            ['ux_pages_slug', 'CREATE UNIQUE INDEX ux_contents_slug ON contents (slug)'],
            ['ix_pages_public', 'CREATE INDEX ix_contents_public ON contents (status, show_in_menu, sort_order, id)'],
        ] as [$oldIndex, $createSql]) {
            try {
                $this->db->execute($mysql
                    ? 'DROP INDEX ' . $this->db->index($oldIndex) . ' ON ' . $this->db->table('contents')
                    : 'DROP INDEX ' . $this->db->index($oldIndex));
            } catch (DomainError $e) {
                // 옛 인덱스가 없으면 그대로 둔다
            }
            try {
                $this->db->execute($this->expand($createSql));
            } catch (DomainError $e) {
                // 이미 새 이름이면 그대로 둔다
            }
        }
    }

    /** 기존 기본 테마 사용자만 새 기본 디자인으로 옮기고, 직접 고른 테마는 보존한다. */
    /** 이제 없는 옛 테마 이름이 설정에 남아 있으면 default 로 돌린다. */
    private function migrateDefaultTheme(): void
    {
        $gone = ['agy-ohouse', 'atlas', 'aurora', 'basic', 'bloom', 'classic', 'claude-idus', 'claude-kurly',
            'claude-sky', 'claude-idus-cdn', 'codex-bloom', 'codex-idus', 'codex-idus-cdn', 'codex-idus-preline',
            'codex-preline', 'compact', 'cozy', 'daylight', 'harbor', 'haus', 'horizon', 'lumen', 'modern',
            'native', 'nova', 'studio'];
        $marks = implode(',', array_fill(0, count($gone), '?'));
        $this->db->execute(
            'UPDATE ' . $this->db->table('site_settings')
            . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ? AND setting_value IN (' . $marks . ')',
            array_merge(['default', '2026-08-30 00:00:00', 'theme'], $gone)
        );
    }

    /** 하나였던 가입 스위치를 일반·소셜로 나누되 기존 운영자의 켜짐/꺼짐 선택은 보존한다. */
    private function migrateRegistrationSettings(): void
    {
        $legacy = $this->siteSetting('registration_enabled') ?? '1';
        $this->ensureSiteSetting('social_registration_enabled', $legacy === '1' ? '1' : '0');
    }

    /** 로그인 허용 스위치가 없던 설치는 기존처럼 두 로그인 방식을 모두 허용한다. */
    private function migrateLoginSettings(): void
    {
        $this->ensureSiteSetting('password_login_enabled', '1');
        $this->ensureSiteSetting('social_login_enabled', '1');
    }

    private function ensureSiteSetting(string $key, string $value): void
    {
        $existing = $this->db->selectOne(
            'SELECT setting_key FROM ' . $this->db->table('site_settings') . ' WHERE setting_key = ?',
            [$key]
        );
        if ($existing === null) {
            $this->db->execute(
                'INSERT INTO ' . $this->db->table('site_settings')
                . ' (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                [$key, $value, '2026-08-28 00:00:00']
            );
        }
    }

    private function siteSetting(string $key): ?string
    {
        $row = $this->db->selectOne(
            'SELECT setting_value FROM ' . $this->db->table('site_settings') . ' WHERE setting_key = ?',
            [$key]
        );
        return $row === null ? null : (string) $row['setting_value'];
    }

    /** 같은 key/value 모양의 옛 메일 설정을 mail.* 이름 공간으로 옮긴다. */
    private function migrateMailSettings(): void
    {
        if (!$this->tableExists('mail_settings')) {
            return;
        }
        foreach ($this->db->select('SELECT setting_key, setting_value FROM '
            . $this->db->table('mail_settings')) as $row) {
            $key = 'mail.' . (string) $row['setting_key'];
            $value = (string) $row['setting_value'];
            $this->ensureSiteSetting($key, $value);
            // 부분 이전 뒤 재시도할 때는 아직 운영 중이던 옛 표의 값을 최종값으로 본다.
            $this->db->execute('UPDATE ' . $this->db->table('site_settings')
                . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
                [$value, Clock::now(), $key]);
        }
        $this->db->execute('DROP TABLE ' . $this->db->table('mail_settings'));
    }

    /** 첫 관리자 선점과 일회성 마이그레이션 표식을 system.* 설정으로 옮긴다. */
    private function migrateSystemSettings(): void
    {
        $legacy = [];
        if ($this->tableExists('site_state')) {
            foreach ($this->db->select('SELECT state_key, state_value FROM '
                . $this->db->table('site_state')) as $row) {
                $legacy[(string) $row['state_key']] = (string) $row['state_value'];
            }
        }

        if (isset($legacy['first_admin_claimed'])) {
            $this->ensureSiteSetting('system.first_admin_claimed', $legacy['first_admin_claimed']);
            $this->db->execute('UPDATE ' . $this->db->table('site_settings')
                . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
                [$legacy['first_admin_claimed'], Clock::now(), 'system.first_admin_claimed']);
        } else {
            $users = $this->tableExists('users')
                ? $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('users')) : ['c' => 0];
            $claimed = (int) ($users['c'] ?? 0) > 0 ? '1' : '0';
            $this->ensureSiteSetting('system.first_admin_claimed', $claimed);
            if ($claimed === '1') {
                $this->db->execute('UPDATE ' . $this->db->table('site_settings')
                    . ' SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
                    ['1', Clock::now(), 'system.first_admin_claimed']);
            }
        }

        if (isset($legacy['consent_footer_defaulted'])) {
            $this->ensureSiteSetting('system.consent_footer_defaulted', $legacy['consent_footer_defaulted']);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table($table));
            return true;
        } catch (DomainError $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        try {
            // SQLite는 존재하지 않는 "column"을 문자열 리터럴로 받아들이는 호환 모드가
            // 있어 여기서는 내부 상수로만 들어오는 인용 없는 이름을 쓴다.
            $this->db->selectOne('SELECT ' . $column . ' FROM '
                . $this->db->table($table) . ' LIMIT 1');
            return true;
        } catch (DomainError $e) {
            return false;
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if ($this->columnExists($table, $column)) {
            $this->db->execute('ALTER TABLE ' . $this->db->table($table)
                . ' DROP COLUMN ' . $this->db->q($column));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            $sql = 'DROP INDEX ' . $this->db->index($index);
            if ($this->db->dialect()->name() === 'mysql') {
                $sql .= ' ON ' . $this->db->table($table);
            }
            $this->db->execute($sql);
        } catch (DomainError $e) {
            // 옛 판에 없거나 이미 정리됐으면 그대로 둔다.
        }
    }

    private function createIndexIfMissing(string $index, string $sql): void
    {
        try {
            $this->db->execute($this->expand($sql));
        } catch (DomainError $e) {
            // 이미 있으면 그대로 둔다.
        }
    }

    private function contentStatements(): array
    {
        return [
            'CREATE TABLE contents (
                id              {AUTO_PK},
                slug            VARCHAR(100) NOT NULL,
                title           VARCHAR(200) NOT NULL,
                content         {TEXT}       NOT NULL,
                seo_description VARCHAR(300) NULL,
                status          VARCHAR(10)  NOT NULL DEFAULT \'draft\',
                show_in_menu    SMALLINT     NOT NULL DEFAULT 0,
                sort_order      INTEGER      NOT NULL DEFAULT 0,
                created_at      {DATETIME}   NOT NULL,
                updated_at      {DATETIME}   NOT NULL,
                published_at    {DATETIME}   NULL,
                deleted_at      {DATETIME}   NULL,
                image_key       VARCHAR(32)  NULL,
                is_consent       SMALLINT     NOT NULL DEFAULT 0
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_contents_slug ON contents (slug)',
            'CREATE INDEX ix_contents_public ON contents (status, show_in_menu, sort_order, id)',
            'CREATE INDEX ix_contents_listing ON contents'
                . ' (is_consent, deleted_at, status, show_in_menu, sort_order, id)',
        ];
    }

    /**
     * 알림함. 회원에게만 쌓이므로 user_id 는 users.id 를 문자열로 담는
     * posts.author_id / comments.author_id 와 같은 형태로 맞춘다.
     */
    private function notificationStatements(): array
    {
        return [
            'CREATE TABLE notifications (
                id          {AUTO_PK},
                user_id     VARCHAR(64)  NOT NULL,
                kind        VARCHAR(20)  NOT NULL,
                post_id     BIGINT       NOT NULL,
                comment_id  BIGINT       NULL,
                actor_name  VARCHAR(100) NOT NULL,
                subject     VARCHAR(200) NOT NULL,
                is_read     SMALLINT     NOT NULL DEFAULT 0,
                created_at  {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE INDEX ix_notifications_user ON notifications (user_id, is_read, id)',
        ];
    }

    /**
     * first_failed_at 은 유닉스 초를 담는 칸이라 MySQL 의 INTEGER(4바이트, 2038년 만료)로는
     * 부족해 BIGINT 를 쓴다. fail_count 는 5 안팎의 작은 값이라 INTEGER 로 충분하다.
     * migratePasswordThrottle() 은 표가 없을 때만 새로 만드는 멱등 마이그레이션이라,
     * 이 표는 이번 릴리스에서 처음 생기는 것이고 이보다 앞서 INTEGER 로 만들어진
     * MySQL 설치는 아직 존재하지 않는다 — 그래서 폭을 넓히는 별도 ALTER 는 필요 없다.
     */
    private function passwordThrottleStatements(): array
    {
        return [
            'CREATE TABLE password_attempts (
                id              {AUTO_PK},
                attempt_key     VARCHAR(120) NOT NULL,
                client_ip       VARCHAR(64)  NOT NULL,
                fail_count      INTEGER      NOT NULL DEFAULT 0,
                first_failed_at BIGINT       NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_password_attempts ON password_attempts (attempt_key, client_ip)',
        ];
    }

    private function loginEventStatements(): array
    {
        return [
            'CREATE TABLE login_events (
                id               {AUTO_PK},
                user_id          BIGINT       NULL,
                login_identifier VARCHAR(191) NULL,
                auth_method      VARCHAR(20)  NOT NULL,
                result           VARCHAR(20)  NOT NULL,
                client_ip        VARCHAR(45)  NULL,
                user_agent       VARCHAR(255) NULL,
                created_at       {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE INDEX ix_login_events_user ON login_events (user_id, id)',
            'CREATE INDEX ix_login_events_ip ON login_events (client_ip, id)',
            'CREATE INDEX ix_login_events_time ON login_events (created_at, id)',
        ];
    }

    private function writeRateLimitStatements(): array
    {
        return [
            'CREATE TABLE write_rate_limits (
                action            VARCHAR(20) NOT NULL,
                actor_key         VARCHAR(80) NOT NULL,
                window_seconds    INTEGER     NOT NULL,
                window_started_at BIGINT      NOT NULL,
                hit_count         INTEGER     NOT NULL DEFAULT 0
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_write_rate_limits
                ON write_rate_limits (action, actor_key, window_seconds)',
        ];
    }

    /** 약관을 어디에 붙였는지. 필수·선택과 차례는 약관이 아니라 이 붙임이 갖는다. */
    private function consentUseStatements(): array
    {
        return [
            'CREATE TABLE consent_uses (
                id          {AUTO_PK},
                scope       VARCHAR(40)  NOT NULL,
                content_id  BIGINT       NOT NULL,
                required    SMALLINT     NOT NULL DEFAULT 1,
                sort_order  INTEGER      NOT NULL DEFAULT 0,
                created_at  {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_consent_uses ON consent_uses (scope, content_id)',
            'CREATE INDEX ix_consent_uses_content ON consent_uses (content_id)',
        ];
    }

    /**
     * 동의 기록. 회원뿐 아니라 비회원 제출 건에도 달 수 있게 subject 로 받는다.
     * agreed_ip / agreed_ua 는 '동의를 받았다'를 입증하기 위한 증적이라
     * 동의 대상이 아니다. 대신 처리방침에 고지하고 보관기간을 지킨다.
     */
    private function consentsGivenStatements(): array
    {
        return [
            'CREATE TABLE consents_given (
                id                  {AUTO_PK},
                subject_type        VARCHAR(20)  NOT NULL,
                subject_id          BIGINT       NOT NULL,
                scope               VARCHAR(40)  NOT NULL,
                content_id          BIGINT       NOT NULL,
                consent_type        VARCHAR(100) NOT NULL,
                content_updated_at  {DATETIME}   NOT NULL,
                agreed              SMALLINT     NOT NULL DEFAULT 1,
                agreed_at           {DATETIME}   NOT NULL,
                agreed_ip           VARCHAR(45)  NULL,
                agreed_ua           VARCHAR(255) NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_consents_given ON consents_given'
                . ' (subject_type, subject_id, scope, content_id)',
            'CREATE INDEX ix_consents_given_content ON consents_given (content_id)',
        ];
    }

    /**
     * 표시 이름을 고유하게 만든다. 이미 겹치는 이름은 나중 가입자부터 뒤에 2, 3, … 을 붙인 뒤
     * 고유 인덱스를 건다. 앱은 대소문자를 가리지 않고 막고, 인덱스는 같은 글자의 중복을 막는 뒷문이다.
     */
    private function migrateDisplayNames(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('users'));
        } catch (DomainError $e) {
            return;
        }
        $rows = $this->db->select('SELECT id, display_name FROM ' . $this->db->table('users') . ' ORDER BY id ASC');
        $seen = [];
        foreach ($rows as $row) {
            $name = (string) $row['display_name'];
            $key = mb_strtolower($name);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                continue;
            }
            for ($n = 2; ; $n++) {
                $candidate = mb_substr($name, 0, 100 - mb_strlen((string) $n)) . $n;
                if (!isset($seen[mb_strtolower($candidate)])) {
                    break;
                }
            }
            $seen[mb_strtolower($candidate)] = true;
            $this->db->execute('UPDATE ' . $this->db->table('users') . ' SET display_name = ? WHERE id = ?',
                [$candidate, (int) $row['id']]);
        }
        try {
            $this->db->execute('CREATE UNIQUE INDEX ' . $this->db->index('ux_users_display_name')
                . ' ON ' . $this->db->table('users') . ' (display_name)');
        } catch (DomainError $e) {
            // 이미 있으면 그대로 둔다
        }
    }

    private function migrateOauth(): void
    {
        try {
            $this->db->selectOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('user_identities'));
        } catch (DomainError $e) {
            foreach ($this->identityStatements() as $sql) {
                $this->db->execute($this->expand($sql));
            }
        }

        $name = $this->db->dialect()->name();
        if ($name === 'sqlite') {
            $columns = $this->db->select('PRAGMA table_info(' . $this->db->table('users') . ')');
            foreach ($columns as $column) {
                if (($column['name'] ?? '') === 'password_hash' && (int) ($column['notnull'] ?? 0) === 1) {
                    $this->rebuildSqliteUsers();
                    break;
                }
            }
        } elseif ($name === 'mysql') {
            $this->db->execute('ALTER TABLE ' . $this->db->table('users') . ' MODIFY password_hash VARCHAR(255) NULL');
        } elseif ($name === 'pgsql') {
            $this->db->execute('ALTER TABLE ' . $this->db->table('users') . ' ALTER COLUMN password_hash DROP NOT NULL');
        }
    }

    private function renameUserDisplayNameColumn(): void
    {
        if ($this->db->dialect()->name() === 'mysql') {
            $this->db->execute('ALTER TABLE ' . $this->db->table('users')
                . ' CHANGE ' . $this->db->q('name') . ' ' . $this->db->q('display_name')
                . ' VARCHAR(100) NOT NULL');
            return;
        }
        $this->db->execute('ALTER TABLE ' . $this->db->table('users')
            . ' RENAME COLUMN ' . $this->db->q('name') . ' TO ' . $this->db->q('display_name'));
    }

    private function rebuildSqliteUsers(): void
    {
        $this->db->transaction(function (): void {
            $this->db->execute('ALTER TABLE ' . $this->db->table('users')
                . ' RENAME TO ' . $this->db->table('users_before_oauth'));
            $this->db->execute($this->expand($this->usersTableStatement()));
            $columns = 'id, email, email_verified, password_hash, display_name, is_admin, status, session_epoch, created_at, updated_at';
            $this->db->execute('INSERT INTO ' . $this->db->table('users') . ' (' . $columns . ') SELECT '
                . $columns . ' FROM ' . $this->db->table('users_before_oauth'));
            $this->db->execute('DROP TABLE ' . $this->db->table('users_before_oauth'));
            $this->db->execute('CREATE UNIQUE INDEX ' . $this->db->index('ux_users_email')
                . ' ON ' . $this->db->table('users') . ' (email)');
            $this->db->execute('CREATE UNIQUE INDEX ' . $this->db->index('ux_users_display_name')
                . ' ON ' . $this->db->table('users') . ' (display_name)');
        });
    }
}
