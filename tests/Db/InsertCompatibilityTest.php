<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Connection;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InsertCompatibilityTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testSettingsAndRenamedAutoIdTableCanBeInsertedInOneTransaction(array $config): void
    {
        $config['prefix'] = 'Ic' . bin2hex(random_bytes(4)) . '_';
        $db = Connection::create($config);
        try {
            $db->execute('CREATE TABLE ' . $db->table('settings') . ' (setting_key VARCHAR(40) PRIMARY KEY, value VARCHAR(40))');
            $db->execute('CREATE TABLE ' . $db->table('original') . ' (id ' . $db->dialect()->typeMap()['{AUTO_PK}'] . ', name VARCHAR(40))');
            $db->execute('ALTER TABLE ' . $db->table('original') . ' RENAME TO ' . $db->table('renamed'));
            $db->transaction(function () use ($db): void {
                $db->insert('settings', ['setting_key' => 'shop', 'value' => 'saved']);
                self::assertSame('1', $db->insert('renamed', ['name' => 'first']));
                $db->insert('settings', ['setting_key' => 'payment', 'value' => 'saved']);
                self::assertSame('2', $db->insert('renamed', ['name' => 'second']));
            });
            self::assertCount(2, $db->select('SELECT * FROM ' . $db->table('settings')));
        } finally {
            foreach (['settings', 'original', 'renamed'] as $table) $db->execute('DROP TABLE IF EXISTS ' . $db->table($table));
        }
    }
}
