<?php

declare(strict_types=1);

namespace GnuCms\Db;

use PDO;
use PDOException;
use GnuCms\Db\Dialect\DialectInterface;
use GnuCms\Error\DomainError;
use Throwable;

/**
 * PDO 얇은 래퍼. 여기서 쓰는 SQL 은 지원 DB 공통 문법이어야 하며,
 * 방언 차이는 전부 DialectInterface 를 통해서만 표현한다.
 */
final class Connection
{
    /** @var PDO */
    private $pdo;

    /** @var DialectInterface */
    private $dialect;

    /** @var string */
    private $prefix;

    private function __construct(PDO $pdo, DialectInterface $dialect, string $prefix)
    {
        $this->pdo = $pdo;
        $this->dialect = $dialect;
        $this->prefix = $prefix;
    }

    public static function create(array $dbConfig): self
    {
        $dsn = (string) ($dbConfig['dsn'] ?? '');
        if ($dsn === '') {
            throw DomainError::internal('db.dsn 설정이 비어 있습니다.');
        }
        $prefix = (string) ($dbConfig['prefix'] ?? '');
        if ($prefix !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]{0,28}_$/D', $prefix) !== 1) {
            throw DomainError::internal('db.prefix 는 영문으로 시작하고 영문·숫자·밑줄만 쓰며, 밑줄로 끝나는 30자 이하 문자열이어야 합니다.');
        }

        $dialect = DialectFactory::fromDsn($dsn);

        try {
            $pdo = new PDO(
                $dsn,
                $dbConfig['username'] ?? null,
                $dbConfig['password'] ?? null,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            throw DomainError::internal('DB 접속에 실패했습니다: ' . $e->getMessage());
        }

        $dialect->afterConnect($pdo);

        return new self($pdo, $dialect, $prefix);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function dialect(): DialectInterface
    {
        return $this->dialect;
    }

    public function q(string $identifier): string
    {
        return $this->dialect->quoteIdentifier($identifier);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** 설정된 프리픽스를 붙인 실제 테이블 이름. */
    public function tableName(string $logicalName): string
    {
        return $this->prefix . $logicalName;
    }

    /** 테이블 이름 전용 인용. 컬럼 인용인 q() 와 구분해 실수로 프리픽스를 붙이지 않는다. */
    public function table(string $logicalName): string
    {
        return $this->q($this->tableName($logicalName));
    }

    /** 같은 DB에 설치한 사이트끼리 인덱스 이름이 겹치지 않도록 격리한다. */
    public function index(string $logicalName): string
    {
        return $this->q($this->prefix . $logicalName);
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @return string 생성된 기본키 */
    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $quoted = array_map([$this, 'q'], $columns);
        $placeholders = array_map(static function (string $c): string {
            return ':' . $c;
        }, $columns);

        $sql = 'INSERT INTO ' . $this->table($table)
            . ' (' . implode(', ', $quoted) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        $this->run($sql, $data);

        return $this->dialect->lastInsertId($this->pdo);
    }

    /**
     * @param string $where 이름 파라미터(`:name`)만 쓸 수 있다. 위치 파라미터(`?`)는 거부한다.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        foreach ($whereParams as $key => $ignored) {
            if (is_int($key)) {
                throw DomainError::internal(
                    'update() 의 WHERE 절에는 이름 파라미터(:name)만 쓸 수 있습니다.'
                    . ' PDO 는 한 문장에서 이름과 위치 파라미터를 섞는 것을 금지하는데,'
                    . ' SQLite 만 이를 눈감아 주어 SQLite 테스트로는 잡히지 않습니다.'
                );
            }
        }

        $assignments = [];
        $params = [];
        foreach ($data as $column => $value) {
            $assignments[] = $this->q($column) . ' = :set_' . $column;
            $params['set_' . $column] = $value;
        }

        $sql = 'UPDATE ' . $this->table($table)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $where;

        return $this->execute($sql, array_merge($params, $whereParams));
    }

    public function delete(string $table, string $where, array $whereParams = []): int
    {
        return $this->execute('DELETE FROM ' . $this->table($table) . ' WHERE ' . $where, $whereParams);
    }

    /**
     * @return mixed 콜백의 반환값
     */
    public function transaction(callable $fn)
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function run(string $sql, array $params): \PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params === [] ? null : $params);

            return $statement;
        } catch (PDOException $e) {
            throw DomainError::internal('쿼리 실행에 실패했습니다: ' . $e->getMessage() . ' | SQL: ' . $sql);
        }
    }
}
