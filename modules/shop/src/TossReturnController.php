<?php

declare(strict_types=1);

namespace GnuCms\Modules\Shop;

use GnuCms\Error\DomainError;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\TossGateway;
use GnuCms\View\PhpView;
use Slim\Routing\RouteContext;

/** 토스의 GET 인증 결과를 기존 HMAC 인증 POST 승인 경로로 전달한다. GET은 결제를 승인하지 않는다. */
final class TossReturnController
{
    public function __construct(private Service $shop) {}

    public function handle($request, $response)
    {
        $query = $request->getQueryParams();
        if (!$this->shop->ready() || !is_string($query['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $query['id'])) throw DomainError::forbidden('결제 인증 결과를 확인할 수 없습니다.');
        $order = $this->shop->store->get('shop_orders', $query['id']);
        if (!CallbackToken::verify($this->shop->app, $order, $query['state'] ?? null)) throw DomainError::forbidden('결제 인증 결과를 확인할 수 없습니다.');
        $fields = TossGateway::callback($order, $query);
        $route = RouteContext::fromRequest($request);
        $view = new PhpView([dirname(__DIR__) . '/templates'], $route->getRouteParser(), $route->getBasePath(), static fn ($p) => '', static fn ($p) => '');
        $nonce = base64_encode(random_bytes(24));
        $url = $route->getBasePath() . '/modules/shop/callback?' . http_build_query(['id' => $order['id'], 'state' => $query['state']], '', '&', PHP_QUERY_RFC3986);
        return $view->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Content-Security-Policy', "default-src 'none'; script-src 'nonce-" . $nonce . "'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"),
            'toss-return', ['fields' => $fields, 'action' => $url, 'nonce' => $nonce]);
    }
}
