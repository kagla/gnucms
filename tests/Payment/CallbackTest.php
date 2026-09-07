<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\App;
use GnuCms\Payment\CallbackToken;
use PHPUnit\Framework\TestCase;

final class CallbackTest extends TestCase
{
    public function testCallbackTokenIsBoundToOrderProviderAndConfiguration(): void
    {
        $app = new App(['auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $order = ['id' => bin2hex(random_bytes(16)), 'provider' => 'inicis', 'config_revision' => bin2hex(random_bytes(16))];
        $token = CallbackToken::create($app, $order);
        self::assertTrue(CallbackToken::verify($app, $order, $token));
        foreach (['id', 'provider', 'config_revision'] as $key) self::assertFalse(CallbackToken::verify($app, array_replace($order, [$key => 'other']), $token));
        foreach (['', null, [], bin2hex(random_bytes(32))] as $bad) self::assertFalse(CallbackToken::verify($app, $order, $bad));
        self::assertFalse(CallbackToken::verify(new App(['auth' => ['secret' => bin2hex(random_bytes(32))]]), $order, $token));
    }
}
