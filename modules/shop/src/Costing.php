<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\App;
use GnuCms\Extension\PackageSchema;

/** 선택 기간에 결제된 주문의 현재 상품 손익. 원가는 주문·교환 출고 당시 값을 사용한다. */
final class Costing
{
    private Store $store;
    private PackageSchema $schema;

    public function __construct(App $app)
    {
        $this->store = new Store($app->db());
        $this->schema = new PackageSchema($app->db(), $app->storageDir());
    }

    public function ready(): bool { return $this->schema->current(Schema::KEY, Schema::VERSION); }

    public static function amount(mixed $value): ?int
    {
        return $value === '' ? null : Input::integer($value, '원가', 100000000);
    }

    public function report(int $start, int $end, string $environment, string $provider): array
    {
        return $this->store->db->transaction(fn () => $this->summarize($start, $end, $environment, $provider));
    }

    private function summarize(int $start, int $end, string $environment, string $provider): array
    {
        $db = $this->store->db;
        $where = 'o.environment = ? AND o.paid_at > 0 AND o.paid_at >= ? AND o.paid_at < ?';
        $params = [$environment, $start, $end];
        if ($provider !== '') { $where .= ' AND o.provider = ?'; $params[] = $provider; }
        $orders = array_column($db->select('SELECT o.id, o.total, o.refunded, o.shipped_at FROM ' . $db->table('shop_orders') . ' o WHERE ' . $where, $params), null, 'id');
        $items = $db->select('SELECT i.*, (SELECT COALESCE(SUM(c.quantity), 0) FROM ' . $db->table('shop_claims')
            . " c WHERE c.item_id = i.id AND c.status = 'completed' AND c.restock = 1 AND c.kind IN ('return', 'exchange')) AS recovered_quantity FROM "
            . $db->table('shop_items') . ' i JOIN ' . $db->table('shop_orders') . ' o ON o.id = i.order_id WHERE ' . $where
            . ' ORDER BY i.exchange_claim_id, i.name, i.id', $params);
        $refunds = $db->select('SELECT r.order_id, r.amount, r.shipping_amount, c.kind, i.product_id FROM ' . $db->table('shop_refunds')
            . ' r JOIN ' . $db->table('shop_orders') . ' o ON o.id = r.order_id LEFT JOIN ' . $db->table('shop_claims')
            . ' c ON c.id = r.claim_id LEFT JOIN ' . $db->table('shop_items') . " i ON i.id = c.item_id WHERE r.status = 'succeeded' AND " . $where, $params);
        $byOrder = [];
        foreach ($items as $item) {
            $order = $orders[$item['order_id']];
            $row = &$byOrder[$item['order_id']][$item['product_id']];
            $row ??= ['product_id' => $item['product_id'], 'name' => $item['name'], 'sold_quantity' => 0, 'product_amount' => 0,
                'product_refunds' => 0, 'cost_amount' => 0, 'missing_cost_quantity' => 0, 'unallocated_refunds' => false];
            if ($item['exchange_claim_id'] === '') {
                $row['sold_quantity'] += (int) $item['quantity'];
                $row['product_amount'] += (int) $item['quantity'] * (int) $item['price'];
            }
            $consumed = (int) $order['shipped_at'] === 0 && (int) $order['refunded'] === (int) $order['total']
                ? 0 : max(0, (int) $item['quantity'] - (int) $item['recovered_quantity']);
            if ($item['cost_price'] === null) $row['missing_cost_quantity'] += $consumed;
            else $row['cost_amount'] += $consumed * (int) $item['cost_price'];
            unset($row);
        }
        $knownRefunds = [];
        foreach ($refunds as $refund) {
            $knownRefunds[$refund['order_id']] = ($knownRefunds[$refund['order_id']] ?? 0) + (int) $refund['amount'];
            if ($refund['kind'] === 'return' && isset($byOrder[$refund['order_id']][$refund['product_id']])) {
                $byOrder[$refund['order_id']][$refund['product_id']]['product_refunds'] += (int) $refund['amount'] - (int) $refund['shipping_amount'];
            }
        }
        $products = []; $unallocated = 0;
        foreach ($byOrder as $orderId => $rows) {
            $order = $orders[$orderId];
            $fullRefund = (int) $order['refunded'] === (int) $order['total'];
            $unknownRefund = !$fullRefund && (int) $order['refunded'] !== ($knownRefunds[$orderId] ?? 0);
            if ($unknownRefund) $unallocated++;
            foreach ($rows as $id => $row) {
                if ($fullRefund) $row['product_refunds'] = $row['product_amount'];
                $row['unallocated_refunds'] = $unknownRefund;
                if (!isset($products[$id])) { $products[$id] = $row; continue; }
                foreach (['sold_quantity', 'product_amount', 'product_refunds', 'cost_amount', 'missing_cost_quantity'] as $field) $products[$id][$field] += $row[$field];
                $products[$id]['unallocated_refunds'] = $products[$id]['unallocated_refunds'] || $unknownRefund;
            }
        }
        $totals = ['product_amount' => 0, 'product_refunds' => 0, 'cost_amount' => 0, 'missing_cost_quantity' => 0, 'unallocated_refunds' => $unallocated > 0];
        foreach ($products as &$product) {
            foreach (['product_amount', 'product_refunds', 'cost_amount', 'missing_cost_quantity'] as $field) $totals[$field] += $product[$field];
            $product = self::calculate($product);
        }
        unset($product);
        return self::calculate($totals) + ['products' => array_values($products), 'unallocated_refund_orders' => $unallocated];
    }

    private static function calculate(array $row): array
    {
        $row['net_product_amount'] = $row['unallocated_refunds'] ? null : $row['product_amount'] - $row['product_refunds'];
        if ($row['missing_cost_quantity'] > 0) $row['cost_amount'] = null;
        $row['gross_profit'] = $row['cost_amount'] === null || $row['net_product_amount'] === null ? null : $row['net_product_amount'] - $row['cost_amount'];
        $row['margin_rate'] = $row['gross_profit'] === null || $row['net_product_amount'] <= 0 ? null : round($row['gross_profit'] / $row['net_product_amount'] * 100, 1);
        return $row;
    }
}
