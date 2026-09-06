<?php

declare(strict_types=1);

namespace GnuCms\Db\Dialect;

use PDO;
use GnuCms\Error\DomainError;

final class PgsqlDialect implements DialectInterface
{
    public function name(): string
    {
        return 'pgsql';
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
            '{AUTO_PK}'  => 'BIGSERIAL PRIMARY KEY',
            '{DATETIME}' => 'TIMESTAMP',
            '{TEXT}'     => 'TEXT',
        ];
    }

    public function tableSuffix(): string
    {
        return '';
    }

    public function lastInsertId(PDO $pdo, string $table): string
    {
        // 설정 테이블처럼 자동 증가 id가 없는 INSERT도 허용한다. 테이블이
        // 변경된 뒤에도 시퀀스의 실제 이름을 사용하며 없는 시퀀스를 조회해
        // PostgreSQL 트랜잭션 전체를 실패 상태로 만들지 않는다.
        $query = $pdo->prepare('SELECT pg_get_serial_sequence(c.oid::regclass::text, a.attname)
            FROM pg_class c JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE c.oid = to_regclass(?) AND a.attname = \'id\' AND NOT a.attisdropped');
        $query->execute([$this->quoteIdentifier($table)]);
        $sequence = $query->fetchColumn();
        return is_string($sequence) && $sequence !== '' ? (string) $pdo->lastInsertId($sequence) : '';
    }

    public function afterConnect(PDO $pdo): void
    {
        $pdo->exec("SET TIME ZONE 'UTC'");
    }
}
