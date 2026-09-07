<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

interface HttpTransport
{
    /** @return array{status:int,body:array} Throws TransportFailure on uncertain transport/response. */
    public function post(string $environment, string $path, array $headers, array $body): array;
}
