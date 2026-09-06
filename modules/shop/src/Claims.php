<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Claims
{
    private Store $store;
    public function __construct(private Service $service) { $this->store = $service->store; }

    public function hasOpen(string $order): bool
    {
        $db = $this->store->db;
        return $db->selectOne('SELECT id FROM ' . $db->table('shop_claims') . " WHERE order_id = ? AND status NOT IN ('completed','rejected') LIMIT 1", [$order]) !== null
            || $db->selectOne('SELECT id FROM ' . $db->table('shop_refunds') . " WHERE order_id = ? AND status = 'pending' LIMIT 1", [$order]) !== null;
    }

    public function request(string $orderId, array $input, string $actor): string
    {
        $kind = Input::text($input['kind'] ?? '', '신청 종류', 10);
        if (!in_array($kind, ['return', 'exchange', 'cancel'], true)) throw DomainError::validation(['kind' => '취소·반품·교환 중 선택해 주세요.']);
        $reason = Input::text($input['reason'] ?? '', '신청 사유', 1000);
        $key = Input::id($input['request_key'] ?? null);
        return $this->store->db->transaction(function () use ($orderId, $input, $actor, $kind, $reason, $key): string {
            $order = $this->store->lockOrder($orderId);
            $previous = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('shop_claims') . ' WHERE request_key = ?', [$key]);
            if ($previous !== null) {
                if ($previous['order_id'] !== $orderId) throw DomainError::validation(['request' => '신청 키가 다른 주문에서 사용되었습니다.']);
                return $previous['id'];
            }
            if ((int) $order['needs_review']) throw DomainError::validation(['payment' => '운영자가 결제 상태를 확인 중입니다.']);
            $itemId = ''; $quantity = 0; $replacement = '';
            if ($kind === 'cancel') {
                if (!in_array($order['status'], ['paid', 'packing'], true) || $this->hasOpen($orderId)) throw DomainError::validation(['order' => '배송 전 결제 주문만 취소 신청할 수 있습니다.']);
            } else {
                if (!in_array($order['status'], ['shipped', 'delivered'], true)) throw DomainError::validation(['order' => '배송 중 또는 배송 완료 상품만 신청할 수 있습니다.']);
                $itemId = Input::id($input['item_id'] ?? null);
                $item = $this->store->get('shop_items', $itemId);
                if ($item['order_id'] !== $orderId) throw DomainError::notFound('주문 상품을 찾을 수 없습니다.');
                $quantity = Input::integer($input['quantity'] ?? null, '신청 수량', 99, 1);
                $held = $this->store->db->selectOne('SELECT COALESCE(SUM(quantity), 0) AS n FROM ' . $this->store->db->table('shop_claims')
                    . " WHERE item_id = ? AND status NOT IN ('completed','rejected')", [$itemId]);
                if ($quantity > (int) $item['quantity'] - (int) $item['returned'] - (int) $item['exchanged'] - (int) $held['n']) {
                    throw DomainError::validation(['quantity' => '이미 처리했거나 신청 중인 수량을 제외해 주세요.']);
                }
                if ($kind === 'exchange') {
                    $replacement = Input::id($input['replacement_id'] ?? null);
                    $target = $this->store->get('shop_variants', $replacement);
                    if ($target['product_id'] !== $item['product_id'] || !(int) $target['active'] || (int) $target['price'] !== (int) $item['price']) {
                        throw DomainError::validation(['exchange' => '같은 상품의 동일 가격 옵션으로 교환할 수 있습니다. 차액이 있으면 반품 후 다시 주문해 주세요.']);
                    }
                }
            }
            $id = Store::id(); $now = Clock::timestamp();
            $this->store->insert('shop_claims', ['id' => $id, 'order_id' => $orderId, 'item_id' => $itemId, 'request_key' => $key,
                'kind' => $kind, 'quantity' => $quantity, 'reason' => $reason, 'status' => 'requested', 'replacement_id' => $replacement,
                'restock' => 0, 'carrier' => '', 'tracking' => '', 'note' => '', 'created_at' => $now, 'updated_at' => $now]);
            $this->store->event($orderId, $actor, $kind . '_requested');
            return $id;
        });
    }

    public function handle(string $claimId, string $action, array $input, string $actor): void
    {
        $initial = $this->store->get('shop_claims', $claimId);
        if ($action === 'exchange') $this->service->sync($initial['order_id']);
        $this->store->db->transaction(function () use ($claimId, $initial, $action, $input, $actor): void {
            $order = $this->store->lockOrder($initial['order_id']);
            $claim = $this->store->get('shop_claims', $claimId);
            $data = [];
            if ($action === 'approve' && $claim['status'] === 'requested') $data = ['status' => 'approved'];
            elseif ($action === 'reject' && in_array($claim['status'], ['requested', 'approved', 'received'], true)) {
                $data = ['status' => 'rejected', 'note' => Input::text($input['note'] ?? '', '거절 사유', 1000)];
            } elseif ($action === 'convert-return' && $claim['kind'] === 'exchange' && $claim['status'] === 'received') {
                $data = ['kind' => 'return', 'replacement_id' => '', 'note' => Input::text($input['note'] ?? '', '반품 전환 사유', 1000)];
            } elseif ($action === 'receive' && $claim['status'] === 'approved' && $claim['kind'] !== 'cancel') {
                $data = ['status' => 'received', 'restock' => ($input['restock'] ?? '') === '1' ? 1 : 0,
                    'note' => Input::text($input['note'] ?? '', '검수 메모', 1000, true)];
            } elseif ($action === 'exchange' && $claim['status'] === 'received' && $claim['kind'] === 'exchange') {
                $original = $this->store->get('shop_items', $claim['item_id']);
                if ((int) $order['needs_review'] || !in_array($order['status'], ['shipped', 'delivered'], true)
                    || (int) $order['total'] - (int) $order['refunded'] < (int) $original['price'] * (int) $claim['quantity']) {
                    throw DomainError::validation(['payment' => '교환 상품의 결제 잔액과 주문 상태를 확인해 주세요.']);
                }
                $target = $this->store->get('shop_variants', $claim['replacement_id']);
                if (!(int) $target['active']) throw DomainError::validation(['exchange' => '교환할 옵션의 판매가 중지되었습니다.']);
                $data = ['status' => 'completed', 'carrier' => Input::text($input['carrier'] ?? '', '교환 택배사', 80),
                    'tracking' => Input::text($input['tracking'] ?? '', '교환 운송장', 80)];
                // 회수품을 먼저 반영하면 동일 옵션 불량 교환도 처리할 수 있다.
                if ((int) $claim['restock']) $this->store->stock($this->store->get('shop_items', $claim['item_id'])['variant_id'], (int) $claim['quantity'], 'exchange_return', $claimId);
                $this->store->stock($claim['replacement_id'], -(int) $claim['quantity'], 'exchange_ship', $claimId);
                $this->store->db->execute('UPDATE ' . $this->store->db->table('shop_items') . ' SET exchanged = exchanged + ? WHERE id = ?', [(int) $claim['quantity'], $claim['item_id']]);
                $this->store->insert('shop_items', ['id' => Store::id(), 'order_id' => $order['id'], 'variant_id' => $target['id'],
                    'product_id' => $target['product_id'], 'name' => $original['name'],
                    'options' => implode(' / ', array_filter([$target['option1'], $target['option2']])) . ' (교환 출고)',
                    'price' => (int) $original['price'], 'quantity' => (int) $claim['quantity'], 'returned' => 0, 'exchanged' => 0,
                    'exchange_claim_id' => $claimId]);
            }
            if ($data === []) throw DomainError::validation(['claim' => '현재 신청 상태에서 처리할 수 없습니다.']);
            $this->store->update('shop_claims', $claimId, $data + ['updated_at' => Clock::timestamp()]);
            $this->store->event($claim['order_id'], $actor, 'claim_' . $action, $data['note'] ?? '');
        });
    }

    public function refund(string $orderId, array $input, string $actor): string
    {
        $key = Input::id($input['request_key'] ?? null);
        $claimId = ($input['claim_id'] ?? '') === '' ? '' : Input::id($input['claim_id']);
        $refundId = $this->store->db->transaction(function () use ($orderId, $input, $actor, $key, $claimId): string {
            $order = $this->store->lockOrder($orderId);
            $old = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('shop_refunds') . ' WHERE request_key = ?', [$key]);
            if ($old !== null) {
                if ($old['order_id'] !== $orderId) throw DomainError::validation(['request' => '다른 주문의 환불 키입니다.']);
                return $old['id'];
            }
            if ((int) $order['needs_review'] || (int) $order['paid_at'] === 0) throw DomainError::validation(['refund' => '결제 상태를 먼저 확인해 주세요.']);
            if ($this->store->db->selectOne('SELECT id FROM ' . $this->store->db->table('shop_refunds') . " WHERE order_id = ? AND status = 'pending' LIMIT 1", [$orderId]) !== null) {
                throw DomainError::validation(['refund' => '기존 환불 결과를 확인한 뒤 새 환불을 진행해 주세요.']);
            }
            $remaining = (int) $order['total'] - (int) $order['refunded'];
            $shipping = 0; $deduction = 0;
            if ($claimId !== '') {
                $claim = $this->store->get('shop_claims', $claimId);
                if ($claim['order_id'] !== $orderId) throw DomainError::notFound('이 주문의 신청이 아닙니다.');
                if ($claim['kind'] === 'return' && $claim['status'] === 'received') {
                    $item = $this->store->get('shop_items', $claim['item_id']);
                    $shipping = ($input['include_shipping'] ?? '') === '1' ? (int) $order['shipping'] - (int) $order['shipping_refunded'] : 0;
                    $deduction = Input::integer($input['deduction'] ?? '0', '합의된 반품 비용', 1000000);
                    $amount = (int) $item['price'] * (int) $claim['quantity'] + $shipping - $deduction;
                } elseif ($claim['kind'] === 'cancel' && in_array($claim['status'], ['requested', 'approved'], true)
                    && in_array($order['status'], ['paid', 'packing'], true)) {
                    $amount = $remaining;
                    $shipping = (int) $order['shipping'] - (int) $order['shipping_refunded'];
                } else throw DomainError::validation(['claim' => '반품 검수 또는 배송 전 취소 신청을 확인해 주세요.']);
            } else {
                if (!in_array($order['status'], ['paid', 'packing'], true) || $this->hasOpen($orderId)) throw DomainError::validation(['refund' => '출고 전 주문만 직접 전액 취소할 수 있습니다. 반품은 신청 건에서 처리해 주세요.']);
                $amount = $remaining;
                $shipping = (int) $order['shipping'] - (int) $order['shipping_refunded'];
            }
            if ($amount < 1 || $amount > $remaining) throw DomainError::validation(['refund' => '환불 가능 금액을 초과했습니다.']);
            $id = Store::id();
            $reason = Input::text($input['reason'] ?? '주문 취소·반품', '환불 사유', 150) . ' [shop:' . $id . ']';
            $this->store->insert('shop_refunds', ['id' => $id, 'order_id' => $orderId, 'claim_id' => $claimId, 'request_key' => $key,
                'amount' => $amount, 'remaining' => $remaining, 'shipping_amount' => $shipping, 'deduction' => $deduction, 'reason' => $reason,
                'status' => 'pending', 'provider_ref' => null, 'created_at' => Clock::timestamp(), 'updated_at' => Clock::timestamp()]);
            if ($claimId !== '') $this->store->update('shop_claims', $claimId, ['status' => 'refund_pending', 'updated_at' => Clock::timestamp()]);
            $this->store->event($orderId, $actor, 'refund_requested', (string) $amount . '원');
            return $id;
        });
        $this->submitRefund($refundId);
        return $refundId;
    }

    public function submitRefund(string $refundId): void
    {
        $refund = $this->store->get('shop_refunds', $refundId);
        if ($refund['status'] === 'succeeded') return;
        if ($refund['status'] !== 'pending') throw DomainError::validation(['refund' => '종료된 환불입니다.']);
        $this->service->sync($refund['order_id']);
        $refund = $this->store->get('shop_refunds', $refundId);
        if ($refund['status'] === 'succeeded') return;
        if (Clock::timestamp() - (int) $refund['created_at'] > 7200) {
            throw DomainError::validation(['refund' => '결제사 관리자에서 결과를 확인하고 결제 상태 조회 또는 외부 환불 연결을 사용해 주세요.']);
        }
        $order = $this->store->get('shop_orders', $refund['order_id']);
        if ((int) $order['needs_review'] || (int) $order['total'] - (int) $order['refunded'] !== (int) $refund['remaining']) {
            throw DomainError::validation(['refund' => '결제 잔액이 변경되었습니다. 기존 환불 결과를 확인해 주세요.']);
        }
        $this->service->gateway($order['provider'])->cancel($order, (int) $refund['amount'], (int) $refund['remaining'], $refund['reason'], 'refund-' . $refundId);
        $this->service->sync($order['id']);
    }

    /** 주문 잠금과 원장 기록을 가진 동일 트랜잭션에서만 호출한다. */
    public function applyCancellation(array $order, array $cancel): bool
    {
        if (!preg_match('/\[shop:([a-f0-9]{32})\]/', $cancel['reason'], $match)) return false;
        $refund = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('shop_refunds') . ' WHERE id = ? AND order_id = ?', [$match[1], $order['id']]);
        if ($refund === null || (int) $refund['amount'] !== $cancel['amount']) return false;
        if ($refund['status'] === 'succeeded') return $refund['provider_ref'] === $cancel['id'];
        if ($refund['status'] !== 'pending') return false;
        $this->finishRefund($order, $refund, $cancel['id']);
        return true;
    }

    private function finishRefund(array $order, array $refund, string $reference): void
    {
        if ($refund['claim_id'] !== '') {
            $claim = $this->store->get('shop_claims', $refund['claim_id']);
            if ($claim['kind'] === 'return') {
                $item = $this->store->get('shop_items', $claim['item_id']);
                if ((int) $claim['restock']) $this->store->stock($item['variant_id'], (int) $claim['quantity'], 'return', $claim['id']);
                $this->store->db->execute('UPDATE ' . $this->store->db->table('shop_items') . ' SET returned = returned + ? WHERE id = ?', [(int) $claim['quantity'], $item['id']]);
            }
            $this->store->update('shop_claims', $claim['id'], ['status' => 'completed', 'updated_at' => Clock::timestamp()]);
        }
        $this->store->update('shop_refunds', $refund['id'], ['status' => 'succeeded', 'provider_ref' => $reference, 'updated_at' => Clock::timestamp()]);
        $this->store->db->execute('UPDATE ' . $this->store->db->table('shop_orders') . ' SET shipping_refunded = shipping_refunded + ? WHERE id = ?', [(int) $refund['shipping_amount'], $order['id']]);
        $this->store->event($order['id'], 'payment', 'refund_succeeded', (string) $refund['amount'] . '원');
    }

    /** 결제사 관리자에서 수동 처리한 취소를 운영자가 기존 신청에 명시적으로 연결한다. */
    public function attachExternalRefund(string $refundId, string $reference, string $note, string $actor): void
    {
        $reference = Input::text($reference, '외부 취소 ID', 100);
        $note = Input::text($note, '결제사 확인 메모', 1000);
        $initial = $this->store->get('shop_refunds', $refundId);
        $order = $this->store->get('shop_orders', $initial['order_id']);
        $gateway = $this->service->gateway($order['provider']);
        if ($gateway instanceof \GnuCms\Payment\DirectGateway) $gateway->confirmRefund($order, 'refund-' . $refundId, $reference);
        $this->service->sync($initial['order_id']);
        $this->store->db->transaction(function () use ($refundId, $initial, $reference, $note, $actor): void {
            $order = $this->store->lockOrder($initial['order_id']);
            $refund = $this->store->get('shop_refunds', $refundId);
            $money = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('shop_money')
                . " WHERE order_id = ? AND kind = 'refund' AND reference = ?", [$order['id'], $reference]);
            $used = $this->store->db->selectOne('SELECT id FROM ' . $this->store->db->table('shop_refunds') . ' WHERE order_id = ? AND provider_ref = ?', [$order['id'], $reference]);
            if ($refund['status'] !== 'pending' || $money === null || -(int) $money['amount'] !== (int) $refund['amount'] || $used !== null) {
                throw DomainError::validation(['refund' => '아직 연결하지 않은 같은 금액의 확정 취소를 선택해 주세요.']);
            }
            $this->finishRefund($order, $refund, $reference);
            $this->store->event($order['id'], $actor, 'external_refund_attached', $note);
        });
    }

    public function closeUnprocessedRefund(string $refundId, string $note, string $actor): void
    {
        $note = Input::text($note, '결제사 미처리 확인 메모', 1000);
        $refund = $this->store->get('shop_refunds', $refundId);
        if (Clock::timestamp() - (int) $refund['created_at'] <= 7200) throw DomainError::validation(['refund' => '2시간 이내에는 기존 환불의 상태를 확인해 주세요.']);
        $order = $this->store->get('shop_orders', $refund['order_id']);
        $gateway = $this->service->gateway($order['provider']);
        if ($gateway instanceof \GnuCms\Payment\DirectGateway) $gateway->confirmUnprocessedRefund($order, 'refund-' . $refundId);
        $payment = $this->service->gateway($order['provider'])->fetch($order);
        if (!($payment['valid'] ?? false) || ($payment['open_cancellations'] ?? 0) > 0
            || (int) $order['total'] - (int) ($payment['cancelled'] ?? -1) !== (int) $refund['remaining']) {
            throw DomainError::validation(['refund' => '진행 중이거나 반영된 취소가 있습니다. 상태를 조회해 주세요.']);
        }
        $this->store->db->transaction(function () use ($refund, $note, $actor): void {
            $order = $this->store->lockOrder($refund['order_id']);
            $current = $this->store->get('shop_refunds', $refund['id']);
            if ($current['status'] !== 'pending' || (int) $order['total'] - (int) $order['refunded'] !== (int) $refund['remaining']) throw DomainError::validation(['refund' => '환불 상태가 변경되었습니다.']);
            $this->store->update('shop_refunds', $refund['id'], ['status' => 'failed', 'updated_at' => Clock::timestamp()]);
            if ($refund['claim_id'] !== '') {
                $claim = $this->store->get('shop_claims', $refund['claim_id']);
                $this->store->update('shop_claims', $claim['id'], ['status' => $claim['kind'] === 'return' ? 'received' : 'approved', 'updated_at' => Clock::timestamp()]);
            }
            $this->store->event($order['id'], $actor, 'refund_unprocessed_confirmed', $note);
        });
    }
}
