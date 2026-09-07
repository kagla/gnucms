<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Cms\ContentImageService;
use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Catalog
{
    public function __construct(private Store $store, private ProductImages $images, private HtmlSanitizer $sanitizer, private ContentImageService $contentImages, private Costing $costing) {}

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
        $products = $db->select('SELECT p.*, (SELECT MIN(v.price) FROM ' . $db->table('shop_variants') . ' v WHERE v.product_id = p.id AND v.active = 1) AS min_price,
            (SELECT SUM(v.stock) FROM ' . $db->table('shop_variants') . ' v WHERE v.product_id = p.id AND v.active = 1) AS available_stock
            FROM ' . $db->table('shop_products') . ' p WHERE ' . $where . ' ORDER BY p.created_at DESC, p.id LIMIT 24 OFFSET ' . $offset, $params);
        if (!$admin || $products === []) return $products;
        // 현재 목록에 필요한 옵션만 한 번에 읽는다. 품절 값은 포함하고 제거된 조합은 제외한다.
        $ids = array_column($products, 'id');
        $variants = $db->select('SELECT product_id, option1, option2 FROM ' . $db->table('shop_variants')
            . ' WHERE active = 1 AND product_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY option1, option2', $ids);
        $values = [];
        foreach ($variants as $variant) foreach ([1, 2] as $n) {
            $value = $variant['option' . $n];
            if ($value !== '' && !in_array($value, $values[$variant['product_id']][$n] ?? [], true)) $values[$variant['product_id']][$n][] = $value;
        }
        foreach ($products as &$product) {
            $product['options'] = [];
            foreach ([1, 2] as $n) if ($product['option' . $n . '_name'] !== '') {
                $product['options'][] = ['name' => $product['option' . $n . '_name'], 'values' => $values[$product['id']][$n] ?? []];
            }
        }
        unset($product);
        return $products;
    }

    public function product(string $id, bool $admin = false): array
    {
        $row = $this->store->get('shop_products', $id);
        if (!$admin && !(int) $row['active']) throw DomainError::notFound('판매 중인 상품이 아닙니다.');
        $row['variants'] = $this->store->db->select('SELECT * FROM ' . $this->store->db->table('shop_variants')
            . ' WHERE product_id = ?' . ($admin ? '' : ' AND active = 1') . ' ORDER BY option1, option2', [$id]);
        if (!$admin) foreach ($row['variants'] as &$variant) unset($variant['cost_price']);
        unset($variant);
        $row['images'] = $this->images->listing($row);
        return $row;
    }

    /** 옵션 이름과 값을 서버에서 검증하고 전체 조합을 계산한다. */
    public function combinations(array $input): array
    {
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
        $combinations = [];
        foreach ($values[1] as $one) foreach ($values[2] as $two) $combinations[] = ['option1' => $one, 'option2' => $two];
        return $combinations;
    }

    public static function rows(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 100) {
            throw DomainError::validation(['variants' => '옵션 조합은 최대 100개까지 입력해 주세요.']);
        }
        foreach ($rows as $row) {
            if (!is_array($row)) throw DomainError::validation(['variants' => '옵션 입력을 확인해 주세요.']);
            foreach ($row as $key => $value) {
                if (!in_array($key, ['option1', 'option2', 'id', 'version', 'price', 'cost_price', 'stock'], true) || (!is_string($value) && !is_int($value))) {
                    throw DomainError::validation(['variants' => '옵션 입력을 확인해 주세요.']);
                }
            }
        }
        return $rows;
    }

    /** 입력 중인 가격·재고와 기존 옵션 ID를 유지하며 조합 표를 다시 만든다. */
    public function draft(array $input, array $existing = []): array
    {
        $known = []; $draft = [];
        foreach ($existing as $row) $known[json_encode([$row['option1'], $row['option2']])] = $row;
        foreach (self::rows($input['variants'] ?? []) as $row) $draft[json_encode([$row['option1'] ?? '', $row['option2'] ?? ''])] = $row;
        $rows = [];
        foreach ($this->combinations($input) as $combination) {
            $key = json_encode(array_values($combination));
            $rows[] = $combination + ($draft[$key] ?? $known[$key] ?? [])
                + ['id' => '', 'version' => '', 'price' => $input['price'] ?? 10000, 'cost_price' => $input['cost_price'] ?? '', 'stock' => $input['stock'] ?? 0];
        }
        return $rows;
    }

    public function save(array $input, array $uploads = []): string
    {
        if (!array_is_list($uploads)) throw DomainError::validation(['images' => '이미지 파일을 다시 선택해 주세요.']);
        foreach ($uploads as $upload) if (!$upload instanceof \Psr\Http\Message\UploadedFileInterface) {
            throw DomainError::validation(['images' => '이미지 파일을 다시 선택해 주세요.']);
        }
        $uploads = array_values(array_filter($uploads, static fn ($file) => $file->getError() !== UPLOAD_ERR_NO_FILE));
        $order = ($input['gallery_present'] ?? '') === '1' ? ProductImages::order($input['image_order'] ?? []) : null;
        $name = Input::text($input['name'] ?? '', '상품명', 150);
        $description = $this->sanitizer->clean(Input::text($input['description'] ?? '', '상품 설명', 15000, true));
        $imageKey = ($input['image_key'] ?? '') === '' ? null : Input::id($input['image_key']);
        $combinations = $this->combinations($input);
        $names = [1 => trim($input['option1_name'] ?? ''), 2 => trim($input['option2_name'] ?? '')];
        $submitted = null;
        if (array_key_exists('variants', $input)) {
            $submitted = [];
            foreach (self::rows($input['variants']) as $row) {
                $key = json_encode([Input::text($row['option1'] ?? '', '옵션값', 60, true), Input::text($row['option2'] ?? '', '옵션값', 60, true)]);
                if (isset($submitted[$key])) throw DomainError::validation(['variants' => '중복된 옵션 조합이 있습니다.']);
                $submitted[$key] = $row + ['id' => '', 'version' => ''];
                Input::integer($row['price'] ?? null, '옵션 판매가', 100000000, 1);
                Input::integer($row['stock'] ?? null, '옵션 재고', 1000000);
                if (array_key_exists('cost_price', $row)) $this->validateCost($row['cost_price']);
            }
            if (count($submitted) !== count($combinations)) throw DomainError::validation(['variants' => '옵션 조합을 적용하고 모든 조합의 가격과 재고를 입력해 주세요.']);
            foreach ($combinations as $combination) if (!isset($submitted[json_encode(array_values($combination))])) {
                throw DomainError::validation(['variants' => '옵션 이름·값을 변경했다면 옵션 조합 적용을 눌러 주세요.']);
            }
        }
        $new = ($input['id'] ?? '') === '';
        $id = $new ? Store::id() : Input::id($input['id']);
        $price = Input::integer($input['price'] ?? '1000', '기본 가격', 100000000, 1);
        $stock = Input::integer($input['stock'] ?? '0', '기본 재고', 1000000);
        $cost = array_key_exists('cost_price', $input) ? $this->validateCost($input['cost_price']) : null;
        $written = [];
        try {
            $saved = $this->store->db->transaction(function () use ($input, $id, $new, $name, $description, $names, $combinations, $submitted, $price, $stock, $cost, $uploads, $order, &$written): string {
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
                // 개별 옵션 판을 비교하여 주문으로 바뀐 재고를 오래된 화면에서 덮어쓰지 않는다.
                $activeIds = [];
                foreach ($combinations as $combination) {
                    $one = $combination['option1']; $two = $combination['option2'];
                    $existing = $db->selectOne('SELECT * FROM ' . $db->table('shop_variants') . ' WHERE product_id = ? AND option1 = ? AND option2 = ?', [$id, $one, $two]);
                    $row = $submitted[json_encode([$one, $two])] ?? null;
                    if ($row !== null && ($row['id'] !== ($existing['id'] ?? ''))) {
                        throw DomainError::validation(['variants' => '상품 옵션이 변경되었습니다. 새로고침 후 다시 입력해 주세요.']);
                    }
                    if ($existing !== null) {
                        if ($row !== null) $this->saveVariant(['variant_id' => $existing['id'], 'version' => $row['version'], 'price' => $row['price'], 'stock' => $row['stock']]
                            + (array_key_exists('cost_price', $row) ? ['cost_price' => $row['cost_price']] : []));
                        $activeIds[] = $existing['id'];
                        continue;
                    }
                    $variant = Store::id();
                    $variantPrice = $row === null ? $price : (int) $row['price'];
                    $variantStock = $row === null ? $stock : (int) $row['stock'];
                    $this->store->insert('shop_variants', ['id' => $variant, 'product_id' => $id, 'option1' => $one,
                        'option2' => $two, 'price' => $variantPrice, 'stock' => 0, 'active' => 1, 'version' => 1]
                        + ($this->costing->ready() ? ['cost_price' => $row !== null && array_key_exists('cost_price', $row) ? Costing::amount($row['cost_price']) : $cost] : []));
                    if ($variantStock > 0) $this->store->stock($variant, $variantStock, 'initial', $id);
                    $activeIds[] = $variant;
                }
                $db->execute('UPDATE ' . $db->table('shop_variants') . ' SET active = 0, version = version + 1 WHERE product_id = ?', [$id]);
                foreach ($activeIds as $variant) $this->store->update('shop_variants', $variant, ['active' => 1]);
                $this->images->save($id, $uploads, $order, $written);
                return $id;
            });
        } catch (\Throwable $error) {
            $this->images->discard($written);
            throw $error;
        }
        // 현재 편집에서 올린 파일만 정리한다. 저장 실패나 다른 편집 창의 이미지는 건드리지 않는다.
        if ($imageKey !== null) $this->contentImages->sync($imageKey, $description);
        return $saved;
    }

    public function saveVariant(array $input): void
    {
        $id = Input::id($input['variant_id'] ?? null);
        $version = Input::integer($input['version'] ?? null, '옵션 판');
        $price = Input::integer($input['price'] ?? null, '가격', 100000000, 1);
        $stock = Input::integer($input['stock'] ?? null, '판매 가능 재고', 1000000);
        $cost = array_key_exists('cost_price', $input) ? ['cost_price' => $this->validateCost($input['cost_price'])] : [];
        $this->store->db->transaction(function () use ($id, $version, $price, $stock, $cost): void {
            $db = $this->store->db;
            if ($db->execute('UPDATE ' . $db->table('shop_variants') . ' SET version = version + 1 WHERE id = ? AND version = ?', [$id, $version]) !== 1) {
                throw DomainError::validation(['stock' => '주문 또는 재고 변경이 발생했습니다. 새로고침 후 다시 입력해 주세요.']);
            }
            $old = $this->store->get('shop_variants', $id);
            $this->store->update('shop_variants', $id, ['price' => $price] + $cost);
            if ($stock !== (int) $old['stock']) $this->store->stock($id, $stock - (int) $old['stock'], 'adjustment', 'admin');
        });
    }

    private function validateCost(mixed $value): ?int
    {
        if (!$this->costing->ready()) throw DomainError::validation(['cost' => '원가 입력을 사용하려면 쇼핑몰 데이터를 먼저 업데이트해 주세요.']);
        return Costing::amount($value);
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
