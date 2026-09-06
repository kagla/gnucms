<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use PHPUnit\Framework\TestCase;
use GnuCms\Db\Connection;
use GnuCms\Db\Schema;

/**
 * 데이터 제공자로 사용 가능한 DB 를 모두 돌린다. SQLite 는 항상 돌고,
 * MySQL/MariaDB는 환경변수가 있을 때만 추가된다.
 */
abstract class DatabaseTestCase extends TestCase
{
    public static function connectionProvider(): array
    {
        $cases = [
            'sqlite' => [['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null]],
        ];

        $mysql = getenv('TEST_MYSQL_DSN');
        if (is_string($mysql) && $mysql !== '') {
            $cases['mysql'] = [[
                'dsn'      => $mysql,
                'username' => getenv('TEST_MYSQL_USER') ?: null,
                'password' => getenv('TEST_MYSQL_PASS') ?: null,
            ]];
        }

        return $cases;
    }

    protected function freshDatabase(array $config): Connection
    {
        $db = Connection::create($config);
        $schema = new Schema($db);
        $schema->drop();
        $schema->create();

        return $db;
    }
}
