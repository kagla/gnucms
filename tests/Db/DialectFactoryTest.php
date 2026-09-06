<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use PHPUnit\Framework\TestCase;
use GnuCms\Db\DialectFactory;
use GnuCms\Error\DomainError;

final class DialectFactoryTest extends TestCase
{
    public function testResolvesSqlite(): void
    {
        $this->assertSame('sqlite', DialectFactory::fromDsn('sqlite::memory:')->name());
    }

    public function testResolvesMysql(): void
    {
        $this->assertSame('mysql', DialectFactory::fromDsn('mysql:host=localhost;dbname=b')->name());
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(DomainError::class);
        DialectFactory::fromDsn('oracle:host=localhost');
    }

    public function testQuotingDiffersPerDialect(): void
    {
        $this->assertSame('"posts"', DialectFactory::fromDsn('sqlite::memory:')->quoteIdentifier('posts'));
        $this->assertSame('`posts`', DialectFactory::fromDsn('mysql:host=h')->quoteIdentifier('posts'));
    }

    public function testEveryDialectDefinesAllTypePlaceholders(): void
    {
        foreach (['sqlite::memory:', 'mysql:host=h'] as $dsn) {
            $map = DialectFactory::fromDsn($dsn)->typeMap();
            $this->assertArrayHasKey('{AUTO_PK}', $map, $dsn);
            $this->assertArrayHasKey('{DATETIME}', $map, $dsn);
            $this->assertArrayHasKey('{TEXT}', $map, $dsn);
        }
    }

    public function testIdentifierWithQuoteCharacterIsRejected(): void
    {
        $this->expectException(DomainError::class);
        DialectFactory::fromDsn('mysql:host=h')->quoteIdentifier('posts`; DROP TABLE posts; --');
    }
}
