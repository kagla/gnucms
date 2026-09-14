<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\Error\DomainError;
use Psr\Http\Message\ServerRequestInterface;

/** 관리자·회원 POST의 세션 CSRF 토큰 검사. 코어 컨트롤러와 확장 라우트가 함께 쓴다. */
final class Csrf
{
    public static function assert(ServerRequestInterface $request): void
    {
        $input = $request->getParsedBody();
        $given = is_array($input) ? ($input['csrf_token'] ?? null) : null;
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($given) || !is_string($expected) || $expected === '' || !hash_equals($expected, $given)) {
            throw DomainError::forbidden('요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
    }
}
