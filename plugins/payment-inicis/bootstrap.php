<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Payment\InicisGateway;
use GnuCms\Payment\Settings;
use GnuCms\Payment\SettingsController;

return static function (Context $context): void {
    $settings = new Settings($context->app, 'inicis');
    $context->provide('gateway.v2', new InicisGateway($settings));
    $controller = new SettingsController($settings);
    $context->route('GET', '/settings', [$controller, 'handle'], admin: true);
    $context->route('POST', '/settings', [$controller, 'handle'], admin: true);
};
