<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use GnuCms\Tests\Support\AdminViewFixture;

$base = $argv[1] ?? ''; $environment = $argv[2] ?? 'test';
$view = AdminViewFixture::view($base);
echo $view->fetch('admin/messaging_settings', [
    'base' => $base, 'environment' => $environment, 'ready' => true,
    'settings' => [
        'configured' => true, 'enabled' => false, 'account' => 'browser-test', 'account_type' => 'module',
        'revision' => str_repeat('a', 32), 'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678',
        'test_phone' => '01000000000', 'webhook_ips' => [], 'api_verified' => false, 'kapi_configured' => true, 'password_length' => 20, 'kapi_key_length' => 36,
    ],
    'notice' => '', 'errors' => [], 'webhook' => null,
]);
