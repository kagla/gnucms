<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

use GnuCms\Payment\Transport;

final class FakeTransport implements Transport
{
    public array $calls = [];
    public array $responses = [];
    public function request(string $method, string $url, array $headers, ?array $body): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) throw $response;
        return $response ?? throw new \RuntimeException('테스트 응답이 없습니다.');
    }
}
