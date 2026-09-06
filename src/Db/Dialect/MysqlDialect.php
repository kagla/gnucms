<?php

declare(strict_types=1);

namespace GnuCms\Db\Dialect;

use PDO;
use GnuCms\Error\DomainError;

final class MysqlDialect implements DialectInterface
{
    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $name): string
    {
        if (strpos($name, '`') !== false) {
            throw DomainError::internal('식별자에 인용 문자를 쓸 수 없습니다: ' . $name);
        }

        return '`' . $name . '`';
    }

    public function typeMap(): array
    {
        return [
            '{AUTO_PK}'  => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
            '{DATETIME}' => 'DATETIME',
            '{TEXT}'     => 'LONGTEXT',
        ];
    }

    public function tableSuffix(): string
    {
        return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    public function lastInsertId(PDO $pdo): string
    {
        return (string) $pdo->lastInsertId();
    }

    public function afterConnect(PDO $pdo): void
    {
        // 잘림을 오류로 만들고, 시간대를 UTC 로 고정한다.
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $pdo->exec("SET SESSION time_zone = '+00:00'");
    }
}
