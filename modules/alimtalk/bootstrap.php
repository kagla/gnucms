<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Modules\Alimtalk\Controller;

require_once __DIR__ . '/src/Controller.php';

return static function (Context $context): void {
    $services = [];
    foreach (['ready', 'preview', 'send', 'templates', 'history', 'detail', 'retry', 'refresh-result', 'purge', 'status'] as $name) {
        $service = $context->service('plugins/bizppurio', $name . '.v1');
        if (!$service instanceof Closure) throw new RuntimeException('알림톡 서비스 계약을 사용할 수 없습니다.');
        $services[$name] = $service;
    }
    $controller = new Controller($services);
    foreach (['home', 'templates', 'send', 'history', 'detail'] as $page) {
        foreach (['GET', 'POST'] as $method) {
            $context->route($method, '/' . $page, static fn ($request, $response) => $controller->handle($page, $request, $response), admin: true);
        }
    }
};
