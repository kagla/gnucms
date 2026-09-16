<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

use GnuCms\Messaging\StreamTransport;
use GnuCms\Messaging\TransportFailure;
use PHPUnit\Framework\TestCase;

final class StreamTransportTest extends TestCase
{
    public function testDecodeAcceptsValidJsonObjects(): void
    {
        self::assertSame(['status' => 200, 'body' => ['code' => 1000, 'messagekey' => 'k'], 'headers' => ['ratelimit-reset' => '3']],
            StreamTransport::decode(200, '{"code":1000,"messagekey":"k"}', ['ratelimit-reset' => '3']));
    }

    public function testDecodeReadsTheVendorsUnquotedErrorBodyOnServerErrors(): void
    {
        // 비즈뿌리오 검수 서버는 500에서 따옴표 없는 본문을 돌려준다: { code: 9000, description: "unknown error" }
        $decoded = StreamTransport::decode(500, '{ code: 9000, description: "unknown error" }', []);
        self::assertSame(500, $decoded['status']);
        self::assertSame(9000, $decoded['body']['code']);
        self::assertSame('unknown error', $decoded['body']['description']);
    }

    public function testDecodeRejectsUnreadableOrRedirectResponses(): void
    {
        foreach ([[200, 'not json'], [200, '[1,2]'], [302, '{"code":1000}'], [500, '<html>error</html>'], [199, '{"code":1000}']] as [$status, $raw]) {
            try { StreamTransport::decode($status, $raw, []); self::fail("must reject $status $raw"); }
            catch (TransportFailure $e) { self::assertTrue(true); }
        }
    }
}
