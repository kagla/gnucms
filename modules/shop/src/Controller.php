<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use GnuCms\View\View;
use Slim\Routing\RouteContext;

final class Controller
{
    public function __construct(private Service $service, private string $routePrefix = '/modules/shop', private ?string $adminRoutePrefix = null) {}

    public function handle(string $page, $request, $response)
    {
        if ($page === 'admin' && $request->getMethod() === 'POST' && ($request->getParsedBody()['action'] ?? '') === 'install') {
            return $this->unlocked($page, $request, $response);
        }
        return \GnuCms\Payment\ExecutionLock::run($this->service->app->storageDir(), fn () => $this->unlocked($page, $request, $response));
    }

    private function unlocked(string $page, $request, $response)
    {
        $identity = $this->service->app->guestAcl()->identity();
        $user = $identity->sub();
        $admin = $identity->isAdmin();
        $route = RouteContext::fromRequest($request);
        $base = $route->getBasePath();
        $publicUrl = $base . $this->routePrefix;
        $adminUrl = $base . ($this->adminRoutePrefix ?? '/admin/shop');
        $adminPage = in_array($page, ['admin', 'products', 'products/new', 'products/edit', 'settings', 'manage-order', 'settlement', 'inventory', 'export'], true);
        $url = $adminPage ? $adminUrl : $publicUrl;
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        foreach ($input as $key => $value) {
            if ($key === 'variants' && in_array($page, ['products', 'products/new', 'products/edit'], true)) $input[$key] = Catalog::rows($value);
            elseif ($key === 'image_ids' && in_array($page, ['products', 'products/edit'], true)) $input[$key] = ProductImages::ids($value);
            elseif ($key === 'image_order' && in_array($page, ['products', 'products/new', 'products/edit'], true)) $input[$key] = ProductImages::order($value);
            elseif (!is_string($value) && !is_int($value)) throw DomainError::validation(['input' => '단일 입력값을 사용해 주세요.']);
        }
        if ($adminPage) {
            $this->service->app->guestAcl()->assertGlobalAdmin();
            if ($page === 'products' && ($input['id'] ?? '') !== '') $page = 'products/edit';
            if ($page === 'products' && $request->getMethod() === 'POST') $page = 'products/new';
            $canonical = $adminUrl . ($page === 'admin' ? '' : '/' . $page);
            if ($request->getMethod() === 'GET' && $request->getUri()->getPath() !== $canonical) {
                return $this->redirect($response, $canonical . ($input === [] ? '' : '?' . http_build_query($input)));
            }
        }
        $publicPages = ['catalog', 'product', 'cart', 'return'];
        if ($page === 'login' && $user !== null) return $this->redirect($response, $url . '/catalog');
        if (!in_array($page, $publicPages, true) && $user === null) {
            $destination = $url . '/' . ($page === 'login' ? 'catalog' : $page);
            if ($page === 'order') $destination .= '?id=' . Input::id($input['id'] ?? null);
            \GnuCms\Web\LoginDestination::remember($destination);
            return $response->withStatus(303)->withHeader('Location', $base . '/login');
        }
        if ($page === 'return') {
            $id = Input::id($input['id'] ?? null);
            return $response->withStatus(303)->withHeader('Location', $url . '/order?id=' . $id);
        }
        $ready = $this->service->ready();
        $data = ['page' => $page, 'url' => $url, 'public_url' => $publicUrl, 'admin_url' => $adminUrl, 'base' => $base, 'ready' => $ready, 'admin' => $admin,
            'user' => $user, 'settings' => $this->service->settings(), 'errors' => [], 'notice' => '', 'input' => $input,
            'images_ready' => $this->service->images->ready(), 'costs_ready' => $this->service->costing->ready(), 'products' => [], 'product' => null, 'orders' => [], 'order' => null, 'quote' => null, 'payment' => null,
            'report' => null, 'inventory' => [], 'payout' => null, 'stock_events' => [], 'money' => [],
            'csrf_token' => $_SESSION['csrf_token'] ?? '', 'request_key' => Store::id(),
            'providers' => \GnuCms\Payment\Settings::PROVIDERS, 'gateways' => [], 'cart' => $this->cart(),
            'now' => Clock::timestamp(), 'page_number' => Input::integer($input['p'] ?? '1', '페이지', 100000, 1)];
        $data['can_order'] = $ready && $data['settings']['open'] && ($admin || $data['settings']['environment'] === 'live');
        foreach ($this->service->gateways as $gateway) {
            if ($gateway->available($data['settings']['environment'])) $data['gateways'][$gateway->id()] = $gateway->label();
        }
        $orderPage = in_array($page, ['order', 'manage-order'], true);
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($page === 'admin' && $action === 'install') {
                    $this->service->install();
                    return $this->redirect($response, $adminUrl);
                }
                $this->service->requireReady();
                if ($page === 'cart') {
                    $cart = $this->cart();
                    if ($action === 'clear') $cart = [];
                    elseif (in_array($action, ['add', 'update', 'remove'], true)) {
                        $variant = Input::id($input['variant_id'] ?? null);
                        if ($action === 'remove') unset($cart[$variant]);
                        else {
                            $quantity = Input::integer($input['quantity'] ?? null, '수량', 99, 1);
                            $cart[$variant] = $action === 'add' ? ($cart[$variant] ?? 0) + $quantity : $quantity;
                            $this->service->quote($cart);
                        }
                    } else throw DomainError::validation(['action' => '장바구니 작업을 확인해 주세요.']);
                    $_SESSION['shop_cart'] = $cart;
                    return $this->redirect($response, $url . '/cart');
                } elseif ($page === 'checkout') {
                    if ($data['settings']['environment'] === 'test' && !$admin) throw DomainError::validation(['shop' => '판매 준비 중입니다.']);
                    $key = Input::id($input['request_key'] ?? null);
                    $preview = $_SESSION['shop_previews'][$key] ?? null;
                    if (!is_array($preview) || $preview['user'] !== $user || $preview['expires'] < Clock::timestamp()
                        || ($preview['environment'] ?? '') !== $data['settings']['environment']
                        || $preview['policy'] !== hash('sha256', $data['settings']['policy'])) {
                        throw DomainError::validation(['checkout' => '주문 확인 화면이 만료되거나 정책이 변경되었습니다. 주문 내용을 다시 확인해 주세요.']);
                    }
                    $provider = Input::text($input['provider'] ?? '', '결제사', 10);
                    $order = $this->service->createOrder($user, $preview['cart'], $input, $provider, $key, $preview['total']);
                    if (!($preview['submitted'] ?? false)) {
                        $cart = $this->cart();
                        foreach ($preview['cart'] as $variant => $qty) {
                            $cart[$variant] = max(0, ($cart[$variant] ?? 0) - $qty);
                            if ($cart[$variant] === 0) unset($cart[$variant]);
                        }
                        $_SESSION['shop_cart'] = $cart;
                        $_SESSION['shop_previews'][$key]['submitted'] = true;
                    }
                    return $this->redirect($response, $url . '/order?id=' . $order['id']);
                } elseif ($page === 'settings') {
                    $this->service->saveSettings($input);
                    return $this->redirect($response, $url . '/settings');
                } elseif (in_array($page, ['products/new', 'products/edit'], true)) {
                    if ($action === 'build') {
                        $data['form_variants'] = $this->service->catalog->draft($input, ($input['id'] ?? '') === '' ? [] : $this->service->catalog->product(Input::id($input['id']), true)['variants']);
                    } elseif ($action === 'save') {
                        $files = $request->getUploadedFiles()['images'] ?? [];
                        if (!is_array($files)) throw DomainError::validation(['images' => '이미지 파일을 다시 선택해 주세요.']);
                        $id = $this->service->catalog->save($input, $files);
                    } elseif ($action === 'variant') {
                        $this->service->catalog->saveVariant($input);
                        $id = $this->service->store->get('shop_variants', Input::id($input['variant_id']))['product_id'];
                    } elseif ($action === 'image') {
                        $id = Input::id($input['id'] ?? null);
                        $product = $this->service->store->get('shop_products', $id);
                        $uploaded = $request->getUploadedFiles();
                        $files = $uploaded['images'] ?? (isset($uploaded['image']) ? [$uploaded['image']] : []);
                        if (!is_array($files)) throw DomainError::validation(['images' => '상품 이미지를 선택해 주세요.']);
                        $version = Input::integer($input['version'] ?? (isset($uploaded['image']) ? $product['version'] : null), '상품 판');
                        $this->service->images->append($id, $files, $version);
                    } elseif ($action === 'image-order') {
                        $id = Input::id($input['id'] ?? null);
                        $this->service->images->reorder($id, $input['image_ids'] ?? null, Input::integer($input['version'] ?? null, '상품 판'));
                    } else throw DomainError::validation(['action' => '상품 작업을 확인해 주세요.']);
                    if ($action !== 'build') return $this->redirect($response, $adminUrl . '/products/edit?id=' . $id . (in_array($action, ['image', 'image-order'], true) ? '&images_saved=1#product-images' : '&saved=1'));
                } elseif ($page === 'admin' && $action === 'expire') {
                    $data['notice'] = $this->service->expire() . '건의 미결제 주문을 만료 처리했습니다.';
                } elseif ($orderPage) {
                    $id = Input::id($input['id'] ?? null);
                    $order = $this->service->detail($id, $user, $page === 'manage-order' && $admin);
                    if ($action === 'sync') $this->service->sync($id);
                    elseif ($action === 'cancel-pending') $this->service->cancelPending($id, $user);
                    elseif ($action === 'claim') $this->service->claims->request($id, $input, $user);
                    elseif ($action === 'pay' && $page === 'order') {
                        if ($order['environment'] === 'test' && !$admin) throw DomainError::validation(['payment' => '테스트 주문은 운영자만 결제할 수 있습니다.']);
                        $root = rtrim((string) $this->service->app->config('app.url', ''), '/');
                        $device = preg_match('/Android|iPhone|iPad|iPod/i', $request->getHeaderLine('User-Agent')) ? 'mobile' : 'web';
                        $data['payment'] = $this->service->checkout($id, $root . $this->routePrefix . '/return?id=' . $id,
                            $root . $this->routePrefix . '/callback?id=' . $id . '&state=' . \GnuCms\Payment\CallbackToken::create($this->service->app, $order), $device);
                    } elseif ($page === 'manage-order') {
                        if (in_array($action, ['pack', 'ship', 'deliver'], true)) $this->service->fulfill($id, $action, $input, $user);
                        elseif ($action === 'review') $this->service->acknowledgeReview($id, Input::text($input['note'] ?? '', '확인 메모', 1000), $user);
                        elseif ($action === 'refund') $this->service->claims->refund($id, $input, $user);
                        elseif ($action === 'claim-action') {
                            $claimId = Input::id($input['claim_id'] ?? null);
                            if ($this->service->store->get('shop_claims', $claimId)['order_id'] !== $id) throw DomainError::notFound('신청을 찾을 수 없습니다.');
                            $this->service->claims->handle($claimId, Input::text($input['claim_action'] ?? '', '작업', 20), $input, $user);
                        } elseif (in_array($action, ['retry-refund', 'attach-refund', 'close-refund'], true)) {
                            $refundId = Input::id($input['refund_id'] ?? null);
                            if ($this->service->store->get('shop_refunds', $refundId)['order_id'] !== $id) throw DomainError::notFound('환불을 찾을 수 없습니다.');
                            if ($action === 'retry-refund') $this->service->claims->submitRefund($refundId);
                            elseif ($action === 'close-refund') $this->service->claims->closeUnprocessedRefund($refundId, Input::text($input['note'] ?? '', '미처리 확인 메모', 1000), $user);
                            else $this->service->claims->attachExternalRefund($refundId, Input::text($input['reference'] ?? '', '취소 ID', 100), Input::text($input['note'] ?? '', '확인 메모', 1000), $user);
                        } else throw DomainError::validation(['action' => '주문 작업을 확인해 주세요.']);
                    } else throw DomainError::validation(['action' => '주문 작업을 확인해 주세요.']);
                    if ($data['payment'] === null) return $this->redirect($response, $url . '/' . $page . '?id=' . $id);
                } elseif ($page === 'settlement') {
                    $id = $this->service->settlement->savePayout($input, $user);
                    return $this->redirect($response, $url . '/settlement?edit=' . $id . '&environment=' . $input['environment']);
                } else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
            }
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->status() >= 500 ? ['처리 결과를 확인하지 못했습니다. 주문의 결제 상태를 조회하고 기존 환불 내역을 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]);
        }
        if ($ready) {
            try {
                if (in_array($page, ['catalog', 'products'], true)) {
                    $data['products'] = $this->service->catalog->listing($page === 'products', $data['page_number'], Input::text($input['q'] ?? '', '검색어', 100, true));
                }
                if ($page === 'products/edit') $data['product'] = $this->service->catalog->product(Input::id($input['id'] ?? null), true);
                if ($page === 'product') $data['product'] = $this->service->catalog->product(Input::id($input['id'] ?? null));
                if (in_array($page, ['cart', 'checkout'], true) && $this->cart() !== []) $data['quote'] = $this->service->quote($this->cart());
                if ($page === 'checkout' && $data['quote'] !== null) {
                    $previews = $_SESSION['shop_previews'] ?? [];
                    foreach ($previews as $key => $preview) if ($preview['expires'] < Clock::timestamp()) unset($previews[$key]);
                    if (count($previews) >= 10) array_shift($previews);
                    $key = Store::id();
                    $previews[$key] = ['cart' => $this->cart(), 'total' => $data['quote']['total'], 'user' => $user,
                        'environment' => $data['settings']['environment'],
                        'expires' => Clock::timestamp() + 600, 'policy' => hash('sha256', $data['settings']['policy'])];
                    $_SESSION['shop_previews'] = $previews;
                    $data['request_key'] = $key;
                }
                if (in_array($page, ['orders', 'admin'], true)) $data['orders'] = $this->service->orders($page === 'orders' ? $user : null,
                    Input::text($input['status'] ?? '', '상태', 24, true), $data['page_number'], $page === 'admin' ? Input::text($input['environment'] ?? '', '환경', 4, true) : '');
                if ($orderPage) {
                    $data['order'] = $this->service->detail(Input::id($input['id'] ?? null), $user, $page === 'manage-order' && $admin);
                    $data['money'] = $this->service->app->db()->select('SELECT * FROM ' . $this->service->app->db()->table('shop_money') . ' WHERE order_id = ? ORDER BY occurred_at', [$data['order']['id']]);
                    foreach ($data['order']['items'] as $item) $data['replacement_options'][$item['id']] = $this->service->catalog->product($item['product_id'], true)['variants'];
                }
                if ($page === 'inventory') {
                    $data['inventory'] = $this->service->settlement->inventory($data['page_number']);
                    $data['stock_events'] = $this->service->app->db()->select('SELECT s.*, v.option1, v.option2, p.name FROM ' . $this->service->app->db()->table('shop_stock')
                        . ' s JOIN ' . $this->service->app->db()->table('shop_variants') . ' v ON v.id = s.variant_id JOIN ' . $this->service->app->db()->table('shop_products')
                        . ' p ON p.id = v.product_id ORDER BY s.created_at DESC, s.id LIMIT 100');
                }
                if (in_array($page, ['settlement', 'export'], true)) {
                    $today = (new \DateTimeImmutable('@' . Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'));
                    $data['report'] = $this->service->settlement->report($input['from'] ?? $today->format('Y-m-01'), $input['to'] ?? $today->format('Y-m-d'), $input['environment'] ?? 'live', $input['provider'] ?? '');
                    if (($input['edit'] ?? '') !== '') $data['payout'] = $this->service->store->get('shop_payouts', Input::id($input['edit']));
                    if ($page === 'export') {
                        $response->getBody()->write(Settlement::csv($data['report']));
                        return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="shop-settlement.csv"')->withHeader('Cache-Control', 'no-store');
                    }
                }
            } catch (DomainError $e) {
                $response = $response->withStatus($e->status());
                $data['errors'][] = $e->status() >= 500 ? '자료를 읽지 못했습니다. 설치와 연결 설정을 확인해 주세요.' : $e->getMessage();
            }
        }
        if (in_array($page, ['products/new', 'products/edit'], true)) {
            $product = $data['product'];
            $values = $product ?? ['id' => '', 'version' => 0, 'name' => '', 'description' => '', 'option1_name' => '', 'option2_name' => '', 'active' => 0];
            unset($values['variants'], $values['images']);
            foreach ([1, 2] as $n) $values['option' . $n . '_values'] = implode(', ', array_unique(array_column(array_filter($product['variants'] ?? [], static fn ($v) => (int) $v['active'] === 1), 'option' . $n)));
            $values['price'] = $product['variants'][0]['price'] ?? 10000;
            $values['cost_price'] = '';
            $values['stock'] = 0;
            $editing = $request->getMethod() === 'POST' && in_array($input['action'] ?? '', ['save', 'build'], true);
            $data['form_images'] = $product['images'] ?? [];
            if ($editing) {
                $images = array_column($data['form_images'], null, 'id');
                $order = array_values(array_filter($input['image_order'] ?? [], static fn ($id) => isset($images[$id])));
                if (count($order) === count($images)) $data['form_images'] = array_map(static fn ($id) => $images[$id], $order);
                $pendingFiles = $request->getUploadedFiles()['images'] ?? [];
                foreach (is_array($pendingFiles) ? $pendingFiles : [$pendingFiles] as $file) if ($file instanceof \Psr\Http\Message\UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $data['notice'] = '선택한 새 이미지는 아직 저장되지 않았습니다. 이미지 파일을 다시 선택한 뒤 상품 저장을 눌러 주세요.';
                    break;
                }
            }
            $data['form_values'] = $editing ? array_replace($values, $input, ['active' => ($input['active'] ?? '') === '1' ? 1 : 0]) : $values;
            // 업로드 묶음은 편집 창마다 분리하고, 검증 오류·서버 조합 적용 때는 그대로 이어 쓴다.
            if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($data['form_values']['image_key'] ?? ''))) $data['form_values']['image_key'] = Store::id();
            $data['form_values']['description'] = $this->service->app->htmlSanitizer()->clean((string) $data['form_values']['description']);
            if (!isset($data['form_variants'])) {
                $data['form_variants'] = $editing && isset($input['variants']) ? $input['variants'] : $this->service->catalog->draft($values, $product['variants'] ?? []);
            }
            if (($input['saved'] ?? '') === '1') $data['notice'] = '상품을 저장했습니다.';
        }
        $data['status_labels'] = ['attention' => '확인 필요한 주문', 'pending' => '결제 대기', 'paid' => '결제 완료', 'packing' => '상품 준비', 'shipped' => '배송 중', 'delivered' => '배송 완료',
            'cancelled' => '주문 취소', 'refunded' => '전액 환불', 'requested' => '접수', 'approved' => '승인·회수 대기', 'received' => '회수·검수 완료',
            'refund_pending' => '환불 확인 중', 'completed' => '처리 완료', 'rejected' => '거절', 'succeeded' => '환불 완료', 'failed' => '미처리 확인'];
        $data['time'] = static fn ($timestamp) => (int) $timestamp > 0 ? (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i') : '—';
        $view = View::forExtension($request, 'shop', dirname(__DIR__) . '/templates');
        return $view->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'page', $data);
    }

    private function cart(): array { return is_array($_SESSION['shop_cart'] ?? null) ? $_SESSION['shop_cart'] : []; }
    private function redirect($response, string $url) { return $response->withStatus(303)->withHeader('Location', $url); }
}
