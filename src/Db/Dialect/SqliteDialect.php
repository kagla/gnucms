<?php

declare(strict_types=1);

namespace GnuCms\Db\Dialect;

use PDO;
use GnuCms\Error\DomainError;

final class SqliteDialect implements DialectInterface
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $name): string
    {
        if (strpos($name, '"') !== false) {
            throw DomainError::internal('식별자에 인용 문자를 쓸 수 없습니다: ' . $name);
        }

        return '"' . $name . '"';
    }

    public function typeMap(): array
    {
        return [
            '{AUTO_PK}'  => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{DATETIME}' => 'TEXT',
            '{TEXT}'     => 'TEXT',
        ];
    }

    public function tableSuffix(): string
    {
        return '';
    }

    public function lastInsertId(PDO $pdo): string
    {
        return (string) $pdo->lastInsertId();
    }

    public function afterConnect(PDO $pdo): void
    {
        // 공유 호스팅에서 동시 쓰기 시 즉시 실패하지 않도록 5초 대기한다.
        $pdo->exec('PRAGMA busy_timeout = 5000');
    }
}
