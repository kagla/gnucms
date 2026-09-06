<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Payment\PortOneGateway;
use GnuCms\Payment\Settings;
use GnuCms\Payment\SettingsController;

return static function (Context $context): void {
    $settings = new Settings($context->app, 'kspay');
    $context->provide('gateway.v1', new PortOneGateway($settings));
    $controller = new SettingsController($settings);
    $context->route('GET', '/settings', [$controller, 'handle'], admin: true);
    $context->route('POST', '/settings', [$controller, 'handle'], admin: true);
};
