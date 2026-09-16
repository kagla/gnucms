<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

interface HttpTransport
{
    /** @return array{status:int,body:array,headers?:array<string,string>} headers는 소문자 이름. Throws TransportFailure on uncertain transport/response. */
    public function post(string $environment, string $path, array $headers, array $body): array;
}
