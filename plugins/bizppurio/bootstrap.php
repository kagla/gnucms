<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Plugins\Bizppurio\Service;
use GnuCms\Plugins\Bizppurio\SettingsController;

require_once __DIR__ . '/autoload.php';

return static function (Context $context): void {
    $service = new Service($context->app);
    foreach (['preview.v1' => 'preview', 'send.v1' => 'send', 'templates.v1' => 'templateAction',
        'history.v1' => 'history', 'detail.v1' => 'detail', 'retry.v1' => 'retry',
        'refresh-result.v1' => 'refresh', 'purge.v1' => 'purge', 'ready.v1' => 'ready', 'status.v1' => 'status'] as $name => $method) {
        $context->provide($name, $service->$method(...));
    }
    $controller = new SettingsController($service);
    $context->route('GET', '/settings', [$controller, 'handle'], admin: true);
    $context->route('POST', '/settings', [$controller, 'handle'], admin: true);
    $context->externalPost('/result', $service->results->authenticate(...), static function ($request, $response) use ($service) {
        $service->requireReady();
        $service->results->receive($request->getQueryParams()['environment'], $request->getParsedBody());
        $response->getBody()->write('{"accepted":true}');
        return $response;
    });
};
