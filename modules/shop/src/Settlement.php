<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Settlement
{
    public function __construct(private Store $store, private Costing $costing) {}

    private function period(string $from, string $to): array
    {
        $start = new \DateTimeImmutable(Input::date($from), new \DateTimeZone('Asia/Seoul'));
        $end = new \DateTimeImmutable(Input::date($to), new \DateTimeZone('Asia/Seoul'));
        if ($end < $start || $start->diff($end)->days > 366) throw DomainError::validation(['period' => '조회 기간은 순서대로 최대 367일을 선택해 주세요.']);
        return [$start->getTimestamp(), $end->modify('+1 day')->getTimestamp()];
    }

    public function report(string $from, string $to, string $environment = 'live', string $provider = ''): array
    {
        [$start, $end] = $this->period($from, $to);
        \GnuCms\Payment\Settings::environment($environment);
        if ($provider !== '' && !isset(\GnuCms\Payment\Settings::PROVIDERS[$provider])) throw DomainError::validation(['provider' => '결제사를 확인해 주세요.']);
        $db = $this->store->db;
        $params = [$environment, $start, $end];
        $filter = '';
        if ($provider !== '') { $filter = ' AND provider = ?'; $params[] = $provider; }
        $totals = $db->selectOne('SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END),0) AS gross,
            COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END),0) AS refunds,
            COALESCE(SUM(amount),0) AS net FROM ' . $db->table('shop_money') . ' WHERE environment = ? AND occurred_at >= ? AND occurred_at < ?' . $filter, $params);
        $daily = $db->select('SELECT * FROM ' . $db->table('shop_money') . ' WHERE environment = ? AND occurred_at >= ? AND occurred_at < ?' . $filter . ' ORDER BY occurred_at, id', $params);
        $oFilter = $provider === '' ? '' : ' AND o.provider = ?';
        $products = $db->select('SELECT i.product_id, i.name, COALESCE(SUM(i.quantity),0) AS sold_quantity,
            COALESCE(SUM(i.quantity * i.price),0) AS product_amount FROM ' . $db->table('shop_items') . ' i JOIN ' . $db->table('shop_orders')
            . " o ON o.id = i.order_id WHERE o.environment = ? AND o.paid_at >= ? AND o.paid_at < ? AND i.exchange_claim_id = ''" . $oFilter . ' GROUP BY i.product_id, i.name ORDER BY i.name', $params);
        $claims = $db->selectOne('SELECT COALESCE(SUM(CASE WHEN c.kind = \'return\' THEN c.quantity ELSE 0 END),0) AS returned_quantity,
            COALESCE(SUM(CASE WHEN c.kind = \'exchange\' THEN c.quantity ELSE 0 END),0) AS exchanged_quantity
            FROM ' . $db->table('shop_claims') . ' c JOIN ' . $db->table('shop_orders') . " o ON o.id = c.order_id WHERE o.environment = ?
            AND c.updated_at >= ? AND c.updated_at < ? AND c.status = 'completed'" . $oFilter, $params);
        $cancelled = $db->selectOne('SELECT COALESCE(SUM(i.quantity),0) AS n FROM ' . $db->table('shop_items') . ' i JOIN ' . $db->table('shop_orders')
            . " o ON o.id = i.order_id WHERE o.environment = ? AND o.shipped_at = 0 AND o.status = 'refunded' AND i.exchange_claim_id = '' AND EXISTS (SELECT 1 FROM "
            . $db->table('shop_money') . " m WHERE m.order_id = o.id AND m.kind = 'refund' AND m.occurred_at >= ? AND m.occurred_at < ?)" . $oFilter, $params);
        $pParams = [$environment, $from, $to];
        if ($provider !== '') $pParams[] = $provider;
        $payouts = $db->select('SELECT * FROM ' . $db->table('shop_payouts') . ' WHERE environment = ? AND deposit_date >= ? AND deposit_date <= ?'
            . $filter . ' ORDER BY deposit_date DESC, id', $pParams);
        foreach ($payouts as &$payout) {
            [$pStart, $pEnd] = $this->period($payout['sales_from'], $payout['sales_to']);
            $internal = $db->selectOne('SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END),0) AS gross,
                COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END),0) AS refunds FROM ' . $db->table('shop_money')
                . ' WHERE environment = ? AND provider = ? AND occurred_at >= ? AND occurred_at < ?', [$environment, $payout['provider'], $pStart, $pEnd]);
            $payout['expected'] = (int) $payout['gross'] - (int) $payout['refunds'] - (int) $payout['fees'] + (int) $payout['adjustment'];
            $payout['deposit_difference'] = (int) $payout['deposited'] - $payout['expected'];
            $payout['sales_difference'] = (int) $payout['gross'] - (int) $internal['gross'];
            $payout['refund_difference'] = (int) $payout['refunds'] - (int) $internal['refunds'];
        }
        unset($payout);
        return ['from' => $from, 'to' => $to, 'environment' => $environment, 'provider' => $provider,
            'gross' => (int) $totals['gross'], 'refunds' => (int) $totals['refunds'], 'net' => (int) $totals['net'],
            'sold_quantity' => array_sum(array_column($products, 'sold_quantity')),
            'cancelled_quantity' => (int) $cancelled['n'], 'returned_quantity' => (int) $claims['returned_quantity'],
            'exchanged_quantity' => (int) $claims['exchanged_quantity'], 'entries' => $daily, 'products' => $products, 'payouts' => $payouts,
            'profit' => $this->costing->ready() ? $this->costing->report($start, $end, $environment, $provider) : null];
    }

    public function savePayout(array $input, string $actor): string
    {
        $environment = \GnuCms\Payment\Settings::environment(Input::text($input['environment'] ?? '', '환경', 4));
        $provider = Input::text($input['provider'] ?? '', '결제사', 10);
        if (!isset(\GnuCms\Payment\Settings::PROVIDERS[$provider])) throw DomainError::validation(['provider' => '결제사를 선택해 주세요.']);
        $row = ['environment' => $environment, 'provider' => $provider,
            'batch_ref' => Input::text($input['batch_ref'] ?? '', '정산 번호', 100),
            'sales_from' => Input::date($input['sales_from'] ?? ''), 'sales_to' => Input::date($input['sales_to'] ?? ''),
            'deposit_date' => Input::date($input['deposit_date'] ?? ''), 'note' => Input::text($input['note'] ?? '', '정산 메모', 1000, true)];
        $this->period($row['sales_from'], $row['sales_to']);
        foreach (['gross', 'refunds', 'fees'] as $key) $row[$key] = Input::integer($input[$key] ?? null, $key, 9999999999);
        foreach (['adjustment', 'deposited'] as $key) {
            $raw = $input[$key] ?? '0';
            if ((!is_string($raw) && !is_int($raw)) || !preg_match('/^-?(0|[1-9][0-9]{0,9})$/D', (string) $raw)) throw DomainError::validation([$key => '원 단위 정수를 입력해 주세요.']);
            $row[$key] = (int) $raw;
        }
        $key = Input::id($input['request_key'] ?? null);
        return $this->store->db->transaction(function () use ($row, $input, $key, $actor): string {
            $db = $this->store->db;
            if (($input['id'] ?? '') === '') {
                $existing = $db->selectOne('SELECT id FROM ' . $db->table('shop_payouts') . ' WHERE request_key = ?', [$key]);
                if ($existing !== null) return $existing['id'];
                if ($db->selectOne('SELECT id FROM ' . $db->table('shop_payouts') . ' WHERE environment = ? AND provider = ? AND batch_ref = ?', [$row['environment'], $row['provider'], $row['batch_ref']]) !== null) {
                    throw DomainError::validation(['batch_ref' => '이미 등록한 정산 번호입니다. 기존 정산을 수정해 주세요.']);
                }
                $id = Store::id();
                $this->store->insert('shop_payouts', ['id' => $id, 'request_key' => $key, 'created_at' => Clock::timestamp(), 'version' => 1] + $row);
            } else {
                $id = Input::id($input['id']);
                $version = Input::integer($input['version'] ?? null, '정산 판');
                $old = $this->store->get('shop_payouts', $id);
                if ($db->update('shop_payouts', $row + ['version' => $version + 1], 'id = :id AND version = :version', ['id' => $id, 'version' => $version]) !== 1) {
                    throw DomainError::validation(['version' => '정산 내역이 변경되었습니다. 새로고침 후 수정해 주세요.']);
                }
                $this->store->event('', $actor, 'payout_previous', json_encode(array_intersect_key($old, array_flip(['id', 'gross', 'refunds', 'fees', 'adjustment', 'deposited'])), JSON_THROW_ON_ERROR));
            }
            $this->store->event('', $actor, 'payout_saved', $id);
            return $id;
        });
    }

    public function inventory(int $page = 1): array
    {
        return $this->store->db->select('SELECT v.*, p.name, (SELECT COALESCE(SUM(i.quantity),0) FROM ' . $this->store->db->table('shop_items')
            . ' i JOIN ' . $this->store->db->table('shop_orders') . " o ON o.id = i.order_id WHERE i.variant_id = v.id AND o.stock_released = 0 AND o.status IN ('pending','paid','packing')) AS reserved FROM " . $this->store->db->table('shop_variants') . ' v JOIN '
            . $this->store->db->table('shop_products') . ' p ON p.id = v.product_id ORDER BY p.name, v.option1, v.option2 LIMIT 100 OFFSET ' . ((max(1, min(10000, $page)) - 1) * 100));
    }

    public static function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['일시(한국)', '주문번호', '결제사', '환경', '구분', '금액(원)', '거래번호'], ',', '"', '');
        foreach ($report['entries'] as $entry) {
            $cells = [(new \DateTimeImmutable('@' . $entry['occurred_at']))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i:s'),
                $entry['order_id'], $entry['provider'], $entry['environment'], $entry['kind'], (int) $entry['amount'], $entry['reference']];
            $cells = array_map(static function ($cell) {
                return is_string($cell) && preg_match('/^[\s]*[=+@\-\t\r]/', $cell) ? "'" . $cell : $cell;
            }, $cells);
            fputcsv($stream, $cells, ',', '"', '');
        }
        rewind($stream); $csv = stream_get_contents($stream); fclose($stream);
        return $csv;
    }
}
