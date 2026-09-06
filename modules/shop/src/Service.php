<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Mail\SecretCipher;
use GnuCms\Payment\Gateway;
use GnuCms\Support\Clock;

final class Service
{
    public readonly Store $store;
    public readonly Catalog $catalog;
    public readonly Claims $claims;
    public readonly Settlement $settlement;
    private SecretCipher $cipher;

    /** @param array<string,Gateway> $gateways */
    public function __construct(public readonly App $app, public readonly array $gateways = [])
    {
        $this->store = new Store($app->db());
        $this->catalog = new Catalog($this->store);
        $this->cipher = new SecretCipher((string) $app->config('auth.secret'));
        $this->claims = new Claims($this);
        $this->settlement = new Settlement($this->store);
    }

    public function ready(): bool { return $this->schema()->current(Schema::KEY, Schema::VERSION); }
    public function install(): void { Schema::install($this->schema()); }
    private function schema(): PackageSchema { return new PackageSchema($this->app->db(), $this->app->storageDir()); }
    public function requireReady(): void { if (!$this->ready()) throw DomainError::serviceUnavailable('쇼핑몰 데이터를 먼저 설치해 주세요.'); }

    public function settings(): array
    {
        $row = $this->ready() ? $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('shop_settings') . " WHERE id = 'settings'") : null;
        return ($row === null ? [] : json_decode($row['payload'], true, 16, JSON_THROW_ON_ERROR)) + [
            'name' => '작은 쇼핑몰', 'environment' => 'test', 'open' => false, 'shipping' => 3000, 'free_shipping' => 50000,
            'seller' => '', 'owner' => '', 'business_number' => '', 'commerce_number' => '', 'phone' => '',
            'email' => '', 'address' => '', 'return_address' => '', 'policy' => '',
        ];
    }

    public function saveSettings(array $input): void
    {
        $this->requireReady();
        $row = [];
        foreach (['name', 'seller', 'owner', 'business_number', 'commerce_number', 'phone', 'email', 'address', 'return_address', 'policy'] as $key) {
            $row[$key] = Input::text($input[$key] ?? '', $key, $key === 'policy' ? 12000 : 300, $key !== 'name');
        }
        $row['environment'] = \GnuCms\Payment\Settings::environment(Input::text($input['environment'] ?? '', '환경', 4));
        $row['shipping'] = Input::integer($input['shipping'] ?? null, '배송비', 1000000);
        $row['free_shipping'] = Input::integer($input['free_shipping'] ?? null, '무료배송 기준');
        $row['open'] = ($input['open'] ?? '') === '1';
        if ($row['open']) foreach (['seller', 'owner', 'business_number', 'phone', 'email', 'address', 'return_address', 'policy'] as $key) {
            if ($row[$key] === '') throw DomainError::validation(['settings' => '판매자 정보와 배송·반품 정책을 입력한 뒤 판매를 열어 주세요.']);
        }
        $payload = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $db = $this->app->db();
        $db->transaction(function () use ($db, $payload): void {
            if ($db->selectOne('SELECT id FROM ' . $db->table('shop_settings') . " WHERE id = 'settings'") === null) {
                $this->store->insert('shop_settings', ['id' => 'settings', 'payload' => $payload]);
            } else $this->store->update('shop_settings', 'settings', ['payload' => $payload]);
        });
    }

    public function gateway(string $provider): Gateway
    {
        return $this->gateways[$provider] ?? throw DomainError::serviceUnavailable('주문의 결제 플러그인을 활성화해 주세요.');
    }

    public function quote(array $cart): array
    {
        $this->requireReady();
        $quote = $this->catalog->quote($cart);
        $settings = $this->settings();
        $shipping = $settings['free_shipping'] > 0 && $quote['subtotal'] >= $settings['free_shipping'] ? 0 : $settings['shipping'];
        return $quote + ['shipping' => $shipping, 'total' => $quote['subtotal'] + $shipping];
    }

    public function createOrder(string $user, array $cart, array $input, string $provider, string $key, int $expectedTotal): array
    {
        $this->requireReady();
        $key = Input::id($key);
        $user = Input::text($user, '회원', 100);
        $db = $this->app->db();
        $existing = $db->selectOne('SELECT * FROM ' . $db->table('shop_orders') . ' WHERE checkout_key = ?', [$key]);
        if ($existing !== null) {
            if ($existing['user_id'] !== $user) throw DomainError::forbidden('주문을 확인할 수 없습니다.');
            return $existing;
        }
        $this->expire();
        $settings = $this->settings();
        if (!$settings['open']) throw DomainError::validation(['shop' => '현재 주문 접수를 쉬고 있습니다.']);
        if (($input['consent'] ?? '') !== '1') throw DomainError::validation(['consent' => '배송·반품 정책 및 주문 개인정보 처리에 동의해 주세요.']);
        $customer = Input::customer($input);
        $config = $this->gateway($provider)->configuration($settings['environment']);
        $quote = $this->quote($cart);
        if ($quote['total'] !== $expectedTotal) throw DomainError::validation(['total' => '가격 또는 배송비가 변경되었습니다. 주문 금액을 다시 확인해 주세요.']);
        $id = Store::id(); $now = Clock::timestamp();
        $row = ['id' => $id, 'user_id' => $user, 'checkout_key' => $key,
            'order_name' => $quote['items'][0]['name'] . (count($quote['items']) > 1 ? ' 외 ' . (count($quote['items']) - 1) . '건' : ''),
            'customer' => $this->cipher->encrypt(json_encode($customer + ['policy' => $settings['policy'], 'consented_at' => $now], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'provider' => $provider, 'environment' => $settings['environment'], 'config_revision' => $config['revision'],
            'status' => 'pending', 'total' => $quote['total'], 'shipping' => $quote['shipping'], 'refunded' => 0,
            'shipping_refunded' => 0, 'transaction_id' => null, 'checkout_started' => 0, 'paid_at' => 0, 'created_at' => $now,
            'expires_at' => $now + 1800, 'stock_released' => 0, 'carrier' => '', 'tracking' => '', 'shipped_at' => 0,
            'delivered_at' => 0, 'version' => 1, 'needs_review' => 0];
        try {
            $db->transaction(function () use ($db, $row, $quote, $user): void {
                $count = $db->selectOne('SELECT COUNT(*) AS n FROM ' . $db->table('shop_orders') . " WHERE user_id = ? AND status = 'pending'", [$user]);
                if ((int) $count['n'] >= 5) throw DomainError::validation(['orders' => '대기 중인 주문을 결제하거나 취소한 뒤 주문해 주세요.']);
                foreach ($quote['items'] as $item) {
                    $changed = $db->execute('UPDATE ' . $db->table('shop_variants') . ' SET stock = stock - ?, version = version + 1
                        WHERE id = ? AND active = 1 AND version = ? AND stock >= ?', [$item['quantity'], $item['variant_id'], $item['variant_version'], $item['quantity']]);
                    if ($changed !== 1) throw DomainError::validation(['stock' => '상품의 가격 또는 재고가 변경되었습니다. 장바구니를 다시 확인해 주세요.']);
                    $this->store->insert('shop_stock', ['id' => Store::id(), 'variant_id' => $item['variant_id'], 'delta' => -$item['quantity'],
                        'kind' => 'order', 'reference' => $row['id'], 'created_at' => Clock::timestamp()]);
                }
                $this->store->insert('shop_orders', $row);
                foreach ($quote['items'] as $item) {
                    unset($item['variant_version'], $item['image']);
                    $this->store->insert('shop_items', ['id' => Store::id(), 'order_id' => $row['id'], 'returned' => 0, 'exchanged' => 0] + $item);
                }
                $this->store->event($row['id'], $user, 'created', '배송·반품 정책 동의 기록 저장');
            });
        } catch (\Throwable $e) {
            $existing = $db->selectOne('SELECT * FROM ' . $db->table('shop_orders') . ' WHERE checkout_key = ?', [$row['checkout_key']]);
            if ($existing !== null && $existing['user_id'] === $user) return $existing;
            throw $e;
        }
        return $row;
    }

    public function detail(string $id, ?string $user = null, bool $admin = false): array
    {
        $this->requireReady();
        $order = $this->store->get('shop_orders', $id);
        if (!$admin && ($user === null || $order['user_id'] !== $user)) throw DomainError::notFound('주문을 찾을 수 없습니다.');
        $order['customer_data'] = $this->customer($order);
        $order['items'] = $this->store->items($id);
        foreach (['claims', 'refunds', 'events'] as $kind) $order[$kind] = $this->app->db()->select('SELECT * FROM ' . $this->app->db()->table('shop_' . $kind)
            . ' WHERE order_id = ? ORDER BY created_at, id', [$id]);
        return $order;
    }

    public function customer(array $order): array { return json_decode($this->cipher->decrypt($order['customer']), true, 16, JSON_THROW_ON_ERROR); }

    public function orders(?string $user, string $status = '', int $page = 1, string $environment = ''): array
    {
        $params = []; $where = '1 = 1';
        if ($user !== null) { $where .= ' AND user_id = ?'; $params[] = $user; }
        if ($status === 'attention') $where .= " AND (needs_review = 1 OR EXISTS (SELECT 1 FROM " . $this->app->db()->table('shop_claims')
            . " c WHERE c.order_id = o.id AND c.status NOT IN ('completed','rejected')) OR EXISTS (SELECT 1 FROM " . $this->app->db()->table('shop_refunds') . " r WHERE r.order_id = o.id AND r.status = 'pending'))";
        elseif ($status !== '') { $where .= ' AND status = ?'; $params[] = Input::text($status, '상태', 24); }
        if ($environment !== '') { $where .= ' AND environment = ?'; $params[] = \GnuCms\Payment\Settings::environment($environment); }
        return $this->app->db()->select('SELECT o.*, (SELECT COUNT(*) FROM ' . $this->app->db()->table('shop_claims')
            . " c WHERE c.order_id = o.id AND c.status NOT IN ('completed','rejected')) AS open_claims FROM " . $this->app->db()->table('shop_orders') . ' o WHERE ' . $where
            . ' ORDER BY created_at DESC, id LIMIT 30 OFFSET ' . ((max(1, min(100000, $page)) - 1) * 30), $params);
    }

    public function checkout(string $id, string $returnUrl, string $callbackUrl, string $device = 'web'): array
    {
        $order = $this->app->db()->transaction(function () use ($id): array {
            $order = $this->store->lockOrder($id);
            if ($order['status'] !== 'pending' || (int) $order['expires_at'] < Clock::timestamp()) throw DomainError::validation(['order' => '결제 가능한 주문이 아닙니다.']);
            $this->store->update('shop_orders', $id, ['checkout_started' => Clock::timestamp()]);
            return $order;
        });
        return $this->gateway($order['provider'])->checkout($order, $this->customer($order), $returnUrl, $callbackUrl, $device);
    }

    public function cancelPending(string $id, string $actor): void
    {
        $this->app->db()->transaction(function () use ($id, $actor): void {
            $order = $this->store->lockOrder($id);
            if ($order['status'] === 'cancelled') return;
            if ($order['status'] !== 'pending') throw DomainError::validation(['status' => '결제된 주문은 환불을 요청해 주세요.']);
            $this->release($order);
            $this->store->update('shop_orders', $id, ['status' => 'cancelled']);
            $this->store->event($id, $actor, 'cancelled', '미결제 주문 취소. 뒤늦은 승인은 조회 시 자동 전액 취소.');
        });
    }

    public function expire(): int
    {
        $rows = $this->app->db()->select('SELECT id FROM ' . $this->app->db()->table('shop_orders') . " WHERE status = 'pending' AND expires_at < ? ORDER BY expires_at LIMIT 100", [Clock::timestamp()]);
        foreach ($rows as $row) $this->cancelPending($row['id'], 'expiry');
        return count($rows);
    }

    public function release(array $order): void
    {
        if ((int) $order['stock_released']) return;
        foreach ($this->store->items($order['id']) as $item) {
            $qty = (int) $item['quantity'] - (int) $item['returned'];
            if ($qty > 0) $this->store->stock($item['variant_id'], $qty, 'cancel', $order['id']);
        }
        $this->store->update('shop_orders', $order['id'], ['stock_released' => 1]);
    }

    /** 브라우저 인증 결과는 계기일 뿐이며, 상태는 인증된 결제 조회 응답만으로 반영한다. */
    public function sync(string $id): array
    {
        $order = $this->store->get('shop_orders', $id);
        $this->store->update('shop_orders', $id, ['checked_at' => Clock::timestamp()]);
        $payment = $this->gateway($order['provider'])->fetch($order);
        if ($payment['status'] === 'NOT_FOUND') return $order;
        $late = $this->app->db()->transaction(function () use ($id, $payment): bool {
            $order = $this->store->lockOrder($id);
            if (!in_array($payment['status'], ['PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true)) return false;
            if (!($payment['valid'] ?? false)) {
                $this->store->update('shop_orders', $id, ['needs_review' => 1]);
                $this->store->event($id, 'payment', 'mismatch', '주문번호·상점·환경·통화·금액을 확인해 주세요.');
                return false;
            }
            if ($order['transaction_id'] !== null && $order['transaction_id'] !== $payment['transaction_id']) {
                $this->store->update('shop_orders', $id, ['needs_review' => 1]);
                return false;
            }
            // 오래된 동시 조회가 이미 반영된 환불을 되돌리지 못한다.
            if ((int) $payment['cancelled'] < (int) $order['refunded']) return false;
            if ((int) $order['paid_at'] === 0) {
                $this->store->insert('shop_money', ['id' => hash('sha256', 'paid:' . $id), 'order_id' => $id,
                    'environment' => $order['environment'], 'provider' => $order['provider'], 'kind' => 'payment',
                    'amount' => (int) $order['total'], 'occurred_at' => $payment['paid_at'], 'reference' => $payment['transaction_id']]);
                $this->store->update('shop_orders', $id, ['paid_at' => $payment['paid_at'], 'transaction_id' => $payment['transaction_id']]);
                $this->store->event($id, 'payment', 'paid');
            }
            $unmatched = false;
            foreach ($payment['cancellations'] as $cancel) {
                $moneyId = hash('sha256', $id . ':refund:' . $cancel['id']);
                if ($this->app->db()->selectOne('SELECT id FROM ' . $this->app->db()->table('shop_money') . ' WHERE id = ?', [$moneyId]) !== null) continue;
                $this->store->insert('shop_money', ['id' => $moneyId, 'order_id' => $id, 'environment' => $order['environment'],
                    'provider' => $order['provider'], 'kind' => 'refund', 'amount' => -$cancel['amount'], 'occurred_at' => $cancel['at'], 'reference' => $cancel['id']]);
                if (!$this->claims->applyCancellation($order, $cancel)) $unmatched = true;
            }
            $late = (int) $order['stock_released'] === 1 && (int) $payment['cancelled'] < (int) $order['total'];
            $status = $order['status'];
            if ($status === 'pending') $status = 'paid';
            if ((int) $payment['cancelled'] === (int) $order['total']) {
                if ((int) $order['shipped_at'] === 0) $this->release($order);
                $status = 'refunded';
            }
            $this->store->update('shop_orders', $id, ['status' => $status, 'refunded' => (int) $payment['cancelled'],
                'needs_review' => $late || $unmatched || ($payment['open_cancellations'] ?? 0) > 0 || (int) $order['needs_review'] ? 1 : 0]);
            return $late;
        });
        if ($late) {
            // 재고가 해제된 뒤 도착한 결제는 출고하지 않고 같은 키로 전액 취소한다.
            $current = $this->store->get('shop_orders', $id);
            $remaining = (int) $current['total'] - (int) $current['refunded'];
            $now = Clock::timestamp();
            $canCancel = $this->app->db()->transaction(function () use ($id, $now): bool {
                $locked = $this->store->lockOrder($id);
                if ((int) $locked['refunded'] !== 0) return false;
                if ((int) $locked['late_cancel_at'] === 0) $this->store->update('shop_orders', $id, ['late_cancel_at' => $now]);
                return (int) $locked['late_cancel_at'] === 0 || $now - (int) $locked['late_cancel_at'] < 7200;
            });
            if ($canCancel) $this->gateway($current['provider'])->cancel($current, $remaining, $remaining, '만료·취소된 주문의 지연 결제', 'late-' . $id);
            // 재귀 재시도로 중복 취소하지 않는다. 다음 조회에서 확정한다.
        }
        return $this->store->get('shop_orders', $id);
    }

    public function fulfill(string $id, string $action, array $input, string $actor): void
    {
        if (in_array($action, ['pack', 'ship'], true)) $this->sync($id);
        $this->app->db()->transaction(function () use ($id, $action, $input, $actor): void {
            $order = $this->store->lockOrder($id);
            if ((int) $order['needs_review'] || ($action !== 'deliver' && (int) $order['refunded'] > 0) || $this->claims->hasOpen($id)) {
                throw DomainError::validation(['order' => '결제 확인과 반품·환불 처리를 완료한 뒤 출고해 주세요.']);
            }
            $data = match ($action) {
                'pack' => $order['status'] === 'paid' ? ['status' => 'packing'] : [],
                'ship' => in_array($order['status'], ['paid', 'packing'], true) ? ['status' => 'shipped',
                    'carrier' => Input::text($input['carrier'] ?? '', '택배사', 80),
                    'tracking' => Input::text($input['tracking'] ?? '', '운송장 번호', 80), 'shipped_at' => Clock::timestamp()] : [],
                'deliver' => $order['status'] === 'shipped' ? ['status' => 'delivered', 'delivered_at' => Clock::timestamp()] : [],
                default => [],
            };
            if ($data === []) throw DomainError::validation(['status' => '현재 주문 상태에서 처리할 수 없습니다.']);
            $this->store->update('shop_orders', $id, $data);
            $this->store->event($id, $actor, $action);
        });
    }

    public function acknowledgeReview(string $id, string $note, string $actor): void
    {
        $note = Input::text($note, '결제·재고 확인 메모', 1000);
        $this->sync($id);
        $order = $this->store->get('shop_orders', $id);
        $payment = $this->gateway($order['provider'])->fetch($order);
        if (!($payment['valid'] ?? false) || ($payment['open_cancellations'] ?? 0) > 0) throw DomainError::validation(['review' => '확정된 결제 정보가 주문과 일치해야 확인을 마칠 수 있습니다.']);
        $this->app->db()->transaction(function () use ($id, $note, $actor, $payment): void {
            $order = $this->store->lockOrder($id);
            $pendingRefund = $this->app->db()->selectOne('SELECT id FROM ' . $this->app->db()->table('shop_refunds')
                . " WHERE order_id = ? AND status = 'pending' LIMIT 1", [$id]) !== null;
            if (($payment['transaction_id'] ?? '') !== $order['transaction_id'] || (int) $payment['cancelled'] !== (int) $order['refunded']
                || $pendingRefund || ((int) $order['stock_released'] && (int) $order['refunded'] !== (int) $order['total'])) {
                throw DomainError::validation(['review' => '보류 환불과 결제 잔액을 먼저 확인해 주세요.']);
            }
            $this->store->update('shop_orders', $id, ['needs_review' => 0]);
            $this->store->event($id, $actor, 'review_completed', $note);
        });
    }
}
