<?php

declare(strict_types=1);

namespace GnuCms\Tests\Bizppurio;

use GnuCms\Plugins\Bizppurio\HttpTransport;
use GnuCms\Support\Clock;

require_once dirname(__DIR__, 2) . '/plugins/bizppurio/autoload.php';

final class FakeTransport implements HttpTransport
{
    public array $requests = [];
    public ?\Closure $respond = null;

    public function post(string $environment, string $path, array $headers, array $body): array
    {
        $this->requests[] = compact('environment', 'path', 'headers', 'body');
        if ($this->respond !== null) {
            $result = ($this->respond)($environment, $path, $headers, $body);
            if ($result !== null) return $result;
        }
        return ['status' => 200, 'body' => match ($path) {
            '/v1/token' => ['accesstoken' => bin2hex(random_bytes(24)), 'type' => 'Bearer',
                'expired' => (new \DateTimeImmutable('@' . (Clock::timestamp() + 86400)))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('YmdHis')],
            '/v3/message' => ['code' => 1000, 'messagekey' => 'm' . $body['refkey'], 'refkey' => $body['refkey']],
            '/v2/report' => ['code' => 1000],
        }];
    }

    public function count(string $path): int
    {
        return count(array_filter($this->requests, static fn (array $r): bool => $r['path'] === $path));
    }
}
