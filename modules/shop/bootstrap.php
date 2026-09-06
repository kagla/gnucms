<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Modules\Shop\Controller;
use GnuCms\Modules\Shop\Images;
use GnuCms\Modules\Shop\Input;
use GnuCms\Modules\Shop\Service;
use GnuCms\Payment\Gateway;

require_once __DIR__ . '/autoload.php';

return static function (Context $context): void {
    $gateways = [];
    foreach (array_keys(\GnuCms\Payment\Settings::PROVIDERS) as $id) {
        $gateway = $context->service('plugins/payment-' . $id, 'gateway.v2');
        if ($gateway instanceof Gateway) $gateways[$id] = $gateway;
    }
    $service = new Service($context->app, $gateways);
    $controller = new Controller($service, $context->routePrefix, $context->adminRoutePrefix);
    $context->route('GET', '/toss-return', [new \GnuCms\Modules\Shop\TossReturnController($service, $context->routePrefix), 'handle']);
    $context->route('GET', '/', static fn ($request, $response) => $controller->handle('catalog', $request, $response));
    foreach (['catalog', 'product', 'cart', 'checkout', 'orders', 'order', 'return', 'login'] as $page) {
        $context->route('GET', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response));
        if (in_array($page, ['cart', 'checkout', 'order'], true)) $context->route('POST', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response));
    }
    foreach (['admin', 'products', 'products/new', 'products/edit', 'settings', 'manage-order', 'settlement', 'inventory', 'export'] as $page) {
        $context->route('GET', $page === 'admin' ? '/' : '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response), admin: true, legacyPath: $page === 'admin' ? '/admin' : null);
        if (!in_array($page, ['inventory', 'export'], true)) $context->route('POST', $page === 'admin' ? '/' : '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response), admin: true, legacyPath: $page === 'admin' ? '/admin' : null);
    }
    $context->route('GET', '/image', static function ($request, $response) use ($context) {
        return (new Images($context->app))->response(Input::text($request->getQueryParams()['file'] ?? '', '이미지', 100), $response);
    });
    $context->externalPost('/callback', static function ($request) use ($service): bool {
        $query = $request->getQueryParams();
        if (!is_string($query['id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $query['id']) || !$service->ready()) return false;
        try {
            $order = $service->store->get('shop_orders', $query['id']);
            return \GnuCms\Payment\CallbackToken::verify($service->app, $order, $query['state'] ?? null);
        } catch (\Throwable) { return false; }
    }, static function ($request, $response) use ($service, $context) {
        return \GnuCms\Payment\ExecutionLock::run($service->app->storageDir(), static function () use ($service, $request, $response, $context) {
            $id = $request->getQueryParams()['id'];
            $order = $service->store->get('shop_orders', $id);
            try {
                $service->gateway($order['provider'])->complete($order, $request->getParsedBody());
                $service->sync($id);
            } catch (\GnuCms\Error\DomainError $error) {
                if ($error->status() >= 500) {
                    $service->store->update('shop_orders', $id, ['needs_review' => 1]);
                    $service->store->event($id, 'payment', 'approval_review', 'PG 승인·조회 결과를 확인해 주세요.');
                }
            }
            $base = rtrim((string) parse_url((string) $service->app->config('app.url', ''), PHP_URL_PATH), '/');
            return $response->withStatus(303)->withHeader('Location', $base . $context->path('/order') . '?id=' . $id);
        });
    }, contentType: 'application/x-www-form-urlencoded');
};
