<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\Payment\WebhookSignature;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class WebhookTest extends TestCase
{
    public function testSignatureCoversRawBodyTimestampAndIdentityWithReplayWindow(): void
    {
        $key = random_bytes(32); $now = time(); $id = bin2hex(random_bytes(16));
        $body = '{"type":"Transaction.Paid","data":{"paymentId":"' . bin2hex(random_bytes(16)) . '"}}';
        $signature = base64_encode(hash_hmac('sha256', $id . '.' . $now . '.' . $body, $key, true));
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/modules/shop/webhook')
            ->withHeader('webhook-id', $id)->withHeader('webhook-timestamp', (string) $now)
            ->withHeader('webhook-signature', 'v2,invalid v1,' . $signature);
        $request->getBody()->write($body);
        self::assertTrue(WebhookSignature::verify($request, 'whsec_' . base64_encode($key), $now));
        self::assertSame($body, $request->getBody()->getContents(), '인증 후 JSON 파싱을 위해 스트림을 되감는다.');
        self::assertFalse(WebhookSignature::verify($request, base64_encode(random_bytes(32)), $now));
        self::assertFalse(WebhookSignature::verify($request, base64_encode($key), $now + 301));
        self::assertFalse(WebhookSignature::verify($request, base64_encode($key), $now - 301));
        self::assertFalse(WebhookSignature::verify($request->withHeader('webhook-id', 'other'), base64_encode($key), $now));
        $request->getBody()->write(' ');
        self::assertFalse(WebhookSignature::verify($request, base64_encode($key), $now));
        self::assertFalse(WebhookSignature::verify($request->withoutHeader('webhook-signature'), base64_encode($key), $now));
    }
}
