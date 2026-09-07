<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

final class TransportFailure extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('비즈뿌리오 응답을 확인하지 못했습니다.');
    }
}
