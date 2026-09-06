<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

/** 운영 서버에서 추가 설치가 필요 없는 PHP HTTPS 전송. */
final class StreamTransport implements HttpTransport
{
    public function post(string $environment, string $path, array $headers, array $body): array
    {
        if (!in_array($environment, ['test', 'live'], true) || !in_array($path, ['/v1/token', '/v3/message', '/v2/report'], true)) {
            throw new TransportFailure();
        }
        $host = $environment === 'live' ? 'https://api.bizppurio.com' : 'https://dev-api.bizppurio.com';
        $lines = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json', 'Connection: close'];
        foreach ($headers as $name => $value) {
            if ($name !== 'Authorization' || !is_string($value) || preg_match('/[\r\n]/', $value)) throw new TransportFailure();
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'header' => implode("\r\n", $lines),
                'content' => json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $deadline = microtime(true) + 10;
        $stream = @fopen($host . $path, 'rb', false, $context);
        if ($stream === false) throw new TransportFailure();
        try {
            $raw = '';
            while (!feof($stream) && strlen($raw) <= 65536) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) throw new TransportFailure();
                stream_set_timeout($stream, (int) $remaining, (int) (($remaining - (int) $remaining) * 1000000));
                $part = @fread($stream, min(8192, 65537 - strlen($raw)));
                if ($part === false || ($part === '' && !feof($stream))) throw new TransportFailure();
                $raw .= $part;
            }
            $meta = stream_get_meta_data($stream);
            if (!is_string($raw) || strlen($raw) > 65536 || !empty($meta['timed_out'])) throw new TransportFailure();
            $status = 0;
            foreach ($meta['wrapper_data'] ?? [] as $line) {
                if (preg_match('~^HTTP/\S+ (\d{3})~', $line, $match)) $status = (int) $match[1];
            }
            $data = json_decode($raw, true, 32);
            if (!is_array($data) || array_is_list($data) || $status < 200 || ($status >= 300 && $status < 400)) throw new TransportFailure();
            return ['status' => $status, 'body' => $data];
        } finally {
            fclose($stream);
        }
    }
}
