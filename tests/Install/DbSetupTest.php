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
        self::assertSame(['sqlite', 'mysql'], array_keys(DbSetup::TYPES));
        self::assertSame(['sqlite', 'mysql'], DbSetup::availableTypes(['pdo', 'pdo_sqlite', 'pdo_mysql', 'pdo_unsupported']));
        self::assertSame(['sqlite'], DbSetup::availableTypes(['pdo', 'pdo_sqlite']));
        self::assertSame(['mysql'], DbSetup::availableTypes(['pdo', 'pdo_mysql']));
        self::assertSame([], DbSetup::availableTypes(['pdo', 'pdo_unsupported']));
        self::assertSame([], DbSetup::availableTypes(['pdo']));
    }

    public function testSqliteDsnFromAbsolutePath(): void
    {
        $db = DbSetup::dsnFrom(['type' => 'sqlite', 'sqlite_path' => $this->dir . '/board.sqlite']);

        self::assertSame(['dsn' => 'sqlite:' . $this->dir . '/board.sqlite', 'username' => null, 'password' => null, 'prefix' => ''], $db);
    }

    public function testSqliteRejectsRelativePathAndUnwritableFolder(): void
    {
        $this->assertValidation(['type' => 'sqlite', 'sqlite_path' => 'storage/board.sqlite'], 'sqlite_path');
        $this->assertValidation(['type' => 'sqlite', 'sqlite_path' => '/nonexistent-' . bin2hex(random_bytes(3)) . '/board.sqlite'], 'sqlite_path');
    }

    public function testSqliteRejectsPathUnderPublic(): void
    {
        $this->assertValidation(
            ['type' => 'sqlite', 'sqlite_path' => dirname(__DIR__, 2) . '/www/board.sqlite'],
            'sqlite_path'
        );
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

    public function testPrefixIsValidatedAndSeparatesSites(): void
    {
        $base = ['type' => 'sqlite', 'sqlite_path' => $this->dir . '/board.sqlite'];
        $firstConfig = DbSetup::dsnFrom($base + ['prefix' => 'first_']);
        $secondConfig = DbSetup::dsnFrom($base + ['prefix' => 'second_']);

        $first = Connection::create($firstConfig);
        $second = Connection::create($secondConfig);
        (new Schema($first))->create();
        (new Schema($second))->create();
        $first->insert('boards', $this->boardRow('first'));
        $second->insert('boards', $this->boardRow('second'));

        self::assertSame('first_boards', $first->tableName('boards'));
        self::assertSame('first', $first->selectOne('SELECT board_key FROM ' . $first->table('boards'))['board_key']);
        self::assertSame('second', $second->selectOne('SELECT board_key FROM ' . $second->table('boards'))['board_key']);
        self::assertTrue(DbSetup::probe($firstConfig)['has_tables']);
        self::assertTrue(DbSetup::probe($secondConfig)['has_tables']);

        $this->assertValidation($base + ['prefix' => 'bad-prefix_'], 'prefix');
        $this->assertValidation($base + ['prefix' => 'missing_separator'], 'prefix');
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

    private function boardRow(string $key): array
    {
        return [
            'board_key' => $key, 'name' => $key, 'description' => null, 'categories' => '[]',
            'managers' => '[]', 'perm_read' => 'guest', 'perm_write' => 'member',
            'perm_comment' => 'member', 'use_secret' => 0, 'use_file' => 0, 'use_category' => 0,
            'list_type' => 'list', 'home_limit' => 5, 'show_in_header' => 0, 'per_page' => 20,
            'sort_order' => 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ];
    }
}
