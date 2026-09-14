<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\App;

final class CallbackToken
{
    public static function create(App $app, array $order): string
    {
        return hash_hmac('sha256', 'shop-callback:' . $order['id'] . ':' . $order['provider'] . ':' . $order['config_revision'], (string) $app->config('auth.secret'));
    }

    public static function verify(App $app, array $order, mixed $token): bool
    {
        return is_string($token) && strlen($token) === 64 && hash_equals(self::create($app, $order), $token);
    }
}
