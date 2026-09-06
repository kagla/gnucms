<?php

declare(strict_types=1);

namespace GnuCms\Db\Dialect;

use PDO;

interface DialectInterface
{
    /** 'sqlite' | 'mysql' */
    public function name(): string;

    /** 식별자를 방언에 맞게 인용한다. 인용 문자가 섞인 이름은 거부한다. */
    public function quoteIdentifier(string $name): string;

    /** DDL 치환자 -> 실제 타입. 키는 {AUTO_PK}, {DATETIME}, {TEXT} */
    public function typeMap(): array;

    /** CREATE TABLE 뒤에 붙는 문자열. MySQL 만 엔진/문자셋이 필요하다. */
    public function tableSuffix(): string;

    /** 직전 INSERT에서 생성한 자동 증가 기본키. */
    public function lastInsertId(PDO $pdo): string;

    /** 접속 직후 세션 설정. 시간대를 UTC 로 맞추는 것이 목적이다. */
    public function afterConnect(PDO $pdo): void;
}
