<?php

declare(strict_types=1);

namespace GnuCms\Payment;

interface Transport
{
    /** @return array{status:int,body:array} */
    public function request(string $method, string $url, array $headers, ?array $body): array;
}
