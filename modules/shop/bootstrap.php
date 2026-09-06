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
    foreach (['inicis', 'kcp', 'kspay'] as $id) {
        $gateway = $context->service('plugins/payment-' . $id, 'gateway.v1');
        if ($gateway instanceof Gateway) $gateways[$id] = $gateway;
    }
    $service = new Service($context->app, $gateways);
    $controller = new Controller($service);
    foreach (['catalog', 'product', 'cart', 'checkout', 'orders', 'order', 'return'] as $page) {
        $context->route('GET', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response));
        if (in_array($page, ['cart', 'checkout', 'order'], true)) $context->route('POST', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response));
    }
    foreach (['admin', 'products', 'settings', 'manage-order', 'settlement', 'inventory', 'export'] as $page) {
        $context->route('GET', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response), admin: true);
        if (!in_array($page, ['inventory', 'export'], true)) $context->route('POST', '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response), admin: true);
    }
    $context->route('GET', '/image', static function ($request, $response) use ($context) {
        return (new Images($context->app))->response(Input::text($request->getQueryParams()['file'] ?? '', '이미지', 100), $response);
    });
    $context->externalPost('/webhook', static function ($request) use ($service): bool {
        $query = $request->getQueryParams();
        if (!is_string($query['provider'] ?? null) || !is_string($query['revision'] ?? null)) return false;
        $gateway = $service->gateways[$query['provider']] ?? null;
        return $gateway !== null && $gateway->authenticateWebhook($request, $query['revision']);
    }, static function ($request, $response) use ($service) {
        return \GnuCms\Payment\ExecutionLock::run($service->app->storageDir(), static function () use ($service, $request, $response) {
        $service->requireReady();
        $body = $request->getParsedBody();
        $id = Input::id($body['data']['paymentId'] ?? null);
        $order = $service->store->get('shop_orders', $id);
        $query = $request->getQueryParams();
        if ($order['provider'] !== $query['provider'] || $order['config_revision'] !== $query['revision']) throw \GnuCms\Error\DomainError::forbidden('주문 결제 설정이 다릅니다.');
        $service->sync($id);
        $response->getBody()->write('{"accepted":true}');
        return $response;
        });
    });
};
