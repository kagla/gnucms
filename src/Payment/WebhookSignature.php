<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use Psr\Http\Message\ServerRequestInterface;

final class WebhookSignature
{
    public static function verify(ServerRequestInterface $request, string $secret, int $now): bool
    {
        $id = $request->getHeaderLine('webhook-id');
        $timestamp = $request->getHeaderLine('webhook-timestamp');
        $signature = $request->getHeaderLine('webhook-signature');
        if ($id === '' || strlen($id) > 200 || !preg_match('/^\d{10}$/D', $timestamp)
            || abs($now - (int) $timestamp) > 300 || strlen($signature) > 2000) return false;
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false || strlen($key) < 16) return false;
        $stream = $request->getBody();
        if (!$stream->isSeekable() || ($stream->getSize() ?? 0) > 65536) return false;
        $stream->rewind();
        $raw = $stream->read(65537);
        $stream->rewind();
        if (strlen($raw) > 65536) return false;
        $expected = base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $raw, $key, true));
        foreach (preg_split('/\s+/', trim($signature)) as $part) {
            if (str_starts_with($part, 'v1,') && hash_equals($expected, substr($part, 3))) return true;
        }
        return false;
    }
}
