<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Catalog
{
    public function __construct(private Store $store) {}

    public function listing(bool $admin = false, int $page = 1, string $query = ''): array
    {
        $db = $this->store->db;
        $where = $admin ? '1 = 1' : 'p.active = 1';
        $params = [];
        if ($query !== '') {
            $where .= " AND LOWER(p.name) LIKE LOWER(?) ESCAPE '!'";
            $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Input::text($query, '검색어', 100)) . '%';
        }
        $offset = (max(1, min(100000, $page)) - 1) * 24;
        return $db->select('SELECT p.*, (SELECT MIN(v.price) FROM ' . $db->table('shop_variants') . ' v WHERE v.product_id = p.id AND v.active = 1) AS min_price,
            (SELECT SUM(v.stock) FROM ' . $db->table('shop_variants') . ' v WHERE v.product_id = p.id AND v.active = 1) AS available_stock
            FROM ' . $db->table('shop_products') . ' p WHERE ' . $where . ' ORDER BY p.created_at DESC, p.id LIMIT 24 OFFSET ' . $offset, $params);
    }

    public function product(string $id, bool $admin = false): array
    {
        $row = $this->store->get('shop_products', $id);
        if (!$admin && !(int) $row['active']) throw DomainError::notFound('판매 중인 상품이 아닙니다.');
        $row['variants'] = $this->store->db->select('SELECT * FROM ' . $this->store->db->table('shop_variants')
            . ' WHERE product_id = ?' . ($admin ? '' : ' AND active = 1') . ' ORDER BY option1, option2', [$id]);
        return $row;
    }

    public function save(array $input): string
    {
        $name = Input::text($input['name'] ?? '', '상품명', 150);
        $description = Input::text($input['description'] ?? '', '상품 설명', 15000, true);
        $names = []; $values = [];
        for ($i = 1; $i <= 2; $i++) {
            $names[$i] = Input::text($input['option' . $i . '_name'] ?? '', '옵션 ' . $i . ' 이름', 60, true);
            $raw = Input::text($input['option' . $i . '_values'] ?? '', '옵션 ' . $i . ' 값', 1000, true);
            $values[$i] = $raw === '' ? [''] : array_map('trim', explode(',', $raw));
            if (count($values[$i]) > 20 || count(array_unique($values[$i])) !== count($values[$i])
                || (($names[$i] === '') !== ($raw === ''))) throw DomainError::validation(['options' => '옵션 이름과 값을 함께 입력하고 중복값은 제거해 주세요.']);
            foreach ($values[$i] as $value) Input::text($value, '옵션값', 60, $raw === '');
        }
        if (($names[1] === '' && $names[2] !== '') || ($names[1] !== '' && $names[1] === $names[2])
            || count($values[1]) * count($values[2]) > 100 || isset($input['option3_name'])) {
            throw DomainError::validation(['options' => '옵션 종류는 최대 두 개, 조합은 최대 100개입니다.']);
        }
        $new = ($input['id'] ?? '') === '';
        $id = $new ? Store::id() : Input::id($input['id']);
        $price = Input::integer($input['price'] ?? '1000', '기본 가격', 100000000, 1);
        $stock = Input::integer($input['stock'] ?? '0', '기본 재고', 1000000);
        return $this->store->db->transaction(function () use ($input, $id, $new, $name, $description, $names, $values, $price, $stock): string {
            $db = $this->store->db;
            $row = ['name' => $name, 'description' => $description, 'option1_name' => $names[1], 'option2_name' => $names[2],
                'active' => ($input['active'] ?? '') === '1' ? 1 : 0];
            if ($new) $this->store->insert('shop_products', ['id' => $id, 'image' => '', 'version' => 1, 'created_at' => Clock::timestamp()] + $row);
            else {
                $version = Input::integer($input['version'] ?? null, '상품 판');
                if ($db->update('shop_products', $row + ['version' => $version + 1], 'id = :id AND version = :version', ['id' => $id, 'version' => $version]) !== 1) {
                    throw DomainError::validation(['version' => '상품이 변경되었습니다. 새로고침 후 다시 저장해 주세요.']);
                }
            }
            $db->execute('UPDATE ' . $db->table('shop_variants') . ' SET active = 0, version = version + 1 WHERE product_id = ?', [$id]);
            foreach ($values[1] as $one) foreach ($values[2] as $two) {
                $existing = $db->selectOne('SELECT id FROM ' . $db->table('shop_variants') . ' WHERE product_id = ? AND option1 = ? AND option2 = ?', [$id, $one, $two]);
                if ($existing !== null) { $this->store->update('shop_variants', $existing['id'], ['active' => 1]); continue; }
                $variant = Store::id();
                $this->store->insert('shop_variants', ['id' => $variant, 'product_id' => $id, 'option1' => $one,
                    'option2' => $two, 'price' => $price, 'stock' => 0, 'active' => 1, 'version' => 1]);
                if ($stock > 0) $this->store->stock($variant, $stock, 'initial', $id);
            }
            return $id;
        });
    }

    public function saveVariant(array $input): void
    {
        $id = Input::id($input['variant_id'] ?? null);
        $version = Input::integer($input['version'] ?? null, '옵션 판');
        $price = Input::integer($input['price'] ?? null, '가격', 100000000, 1);
        $stock = Input::integer($input['stock'] ?? null, '판매 가능 재고', 1000000);
        $this->store->db->transaction(function () use ($id, $version, $price, $stock): void {
            $db = $this->store->db;
            if ($db->execute('UPDATE ' . $db->table('shop_variants') . ' SET version = version + 1 WHERE id = ? AND version = ?', [$id, $version]) !== 1) {
                throw DomainError::validation(['stock' => '주문 또는 재고 변경이 발생했습니다. 새로고침 후 다시 입력해 주세요.']);
            }
            $old = $this->store->get('shop_variants', $id);
            $this->store->update('shop_variants', $id, ['price' => $price]);
            if ($stock !== (int) $old['stock']) $this->store->stock($id, $stock - (int) $old['stock'], 'adjustment', 'admin');
        });
    }

    public function quote(array $cart): array
    {
        if ($cart === [] || count($cart) > 20) throw DomainError::validation(['cart' => '장바구니에 1 ~ 20개 옵션을 담아 주세요.']);
        $items = []; $sum = 0;
        ksort($cart);
        foreach ($cart as $id => $quantity) {
            $quantity = Input::integer($quantity, '주문 수량', 99, 1);
            $variant = $this->store->get('shop_variants', Input::id($id));
            $product = $this->store->get('shop_products', $variant['product_id']);
            if (!(int) $variant['active'] || !(int) $product['active']) throw DomainError::validation(['cart' => '판매가 중지된 상품을 장바구니에서 삭제해 주세요.']);
            if ((int) $variant['stock'] < $quantity) throw DomainError::validation(['stock' => $product['name'] . '의 재고가 부족합니다.']);
            $options = [];
            foreach ([1, 2] as $n) if ($variant['option' . $n] !== '') $options[] = $product['option' . $n . '_name'] . ': ' . $variant['option' . $n];
            $items[] = ['variant_id' => $id, 'product_id' => $product['id'], 'name' => $product['name'],
                'options' => implode(' / ', $options), 'price' => (int) $variant['price'], 'quantity' => $quantity,
                'variant_version' => (int) $variant['version'], 'image' => $product['image']];
            $sum += (int) $variant['price'] * $quantity;
        }
        if ($sum > 999000000) throw DomainError::validation(['total' => '주문 금액 한도를 초과했습니다.']);
        return ['items' => $items, 'subtotal' => $sum];
    }
}
