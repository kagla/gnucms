<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Store
{
    public function __construct(public readonly Connection $db) {}
    public static function id(): string { return bin2hex(random_bytes(16)); }

    public function insert(string $table, array $row): void
    {
        $columns = array_map($this->db->q(...), array_keys($row));
        $this->db->execute('INSERT INTO ' . $this->db->table($table) . ' (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
    }

    public function get(string $table, string $id): array
    {
        return $this->db->selectOne('SELECT * FROM ' . $this->db->table($table) . ' WHERE id = ?', [Input::id($id)])
            ?? throw DomainError::notFound('항목을 찾을 수 없습니다.');
    }

    public function update(string $table, string $id, array $data): void
    {
        $this->db->update($table, $data, 'id = :id', ['id' => $id]);
    }

    /** 트랜잭션의 첫 SQL로 호출해 세 DB 모두에서 주문 단위 쓰기를 직렬화한다. */
    public function lockOrder(string $id): array
    {
        if (!$this->db->pdo()->inTransaction()) throw DomainError::internal('주문 잠금에는 트랜잭션이 필요합니다.');
        if ($this->db->execute('UPDATE ' . $this->db->table('shop_orders') . ' SET version = version + 1 WHERE id = ?', [Input::id($id)]) !== 1) {
            throw DomainError::notFound('주문을 찾을 수 없습니다.');
        }
        return $this->get('shop_orders', $id);
    }

    public function items(string $id): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('shop_items') . ' WHERE order_id = ? ORDER BY id', [$id]);
    }

    public function stock(string $variant, int $delta, string $kind, string $reference): void
    {
        $changed = $this->db->execute('UPDATE ' . $this->db->table('shop_variants')
            . ' SET stock = stock + ?, version = version + 1 WHERE id = ? AND stock + ? >= 0 AND stock + ? <= 1000000', [$delta, $variant, $delta, $delta]);
        if ($changed !== 1) throw DomainError::validation(['stock' => '선택한 옵션의 재고가 부족하거나 재고 한도를 초과합니다.']);
        $this->insert('shop_stock', ['id' => self::id(), 'variant_id' => $variant, 'delta' => $delta,
            'kind' => $kind, 'reference' => $reference, 'created_at' => Clock::timestamp()]);
    }

    public function event(string $order, string $actor, string $kind, string $note = ''): void
    {
        $this->insert('shop_events', ['id' => self::id(), 'order_id' => $order, 'actor' => $actor,
            'kind' => $kind, 'note' => $note, 'created_at' => Clock::timestamp()]);
    }
}
