<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Db\Connection;

final class Store
{
    public function __construct(public readonly Connection $db)
    {
    }

    public function insert(string $table, array $row): void
    {
        $this->db->execute('INSERT INTO ' . $this->db->table($table) . ' ('
            . implode(', ', array_map($this->db->q(...), array_keys($row))) . ') VALUES ('
            . implode(', ', array_fill(0, count($row), '?')) . ')', array_values($row));
    }

    public function find(string $table, string $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM ' . $this->db->table($table) . ' WHERE id = ?', [$id]);
    }

    public function update(string $table, string $id, array $row): void
    {
        $this->db->update($table, $row, 'id = :id', ['id' => $id]);
    }
}
