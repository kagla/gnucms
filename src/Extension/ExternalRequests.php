<?php

declare(strict_types=1);

namespace GnuCms\Extension;

use GnuCms\Error\DomainError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

/** 실제 등록된 고정 경로만 처리하며 세션·HTML 오류·분석을 거치지 않는다. */
final class ExternalRequests implements MiddlewareInterface
{
    public function __construct(private array $routes, private string $basePath = '')
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $route = null;
        foreach ($this->routes as $url => $candidate) {
            if ($path === $this->basePath . $url) {
                $route = $candidate;
                break;
            }
        }
        if ($route === null) {
            return $handler->handle($request);
        }
        try {
            [$authenticate, $receive, $limit] = $route;
            if ($request->getMethod() !== 'POST') {
                return $this->error(405)->withHeader('Allow', 'POST');
            }
            if ($authenticate($request) !== true) {
                return $this->error(403);
            }
            if (strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0])) !== 'application/json') {
                return $this->error(415);
            }
            $declared = $request->getHeaderLine('Content-Length');
            if (($declared !== '' && (!ctype_digit($declared) || (float) $declared > $limit))
                || ($request->getBody()->getSize() ?? 0) > $limit) {
                return $this->error(413);
            }
            $body = $request->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $raw = '';
            while (!$body->eof() && strlen($raw) <= $limit) {
                $part = $body->read(min(8192, $limit + 1 - strlen($raw)));
                if ($part === '') break;
                $raw .= $part;
            }
            if (strlen($raw) > $limit) return $this->error(413);
            $input = json_decode($raw, true, 32);
            if (!is_array($input) || array_is_list($input) || json_last_error() !== JSON_ERROR_NONE) {
                return $this->error(400);
            }
            return $this->headers($receive($request->withParsedBody($input), new Response(), []));
        } catch (DomainError $e) {
            return $this->error($e->status());
        } catch (Throwable $e) {
            // 원문·URL·업체 payload에는 인증값과 수신정보가 있을 수 있다.
            return $this->error(503);
        }
    }

    private function error(int $status): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write('{"accepted":false}');
        return $this->headers($response);
    }

    private function headers(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Referrer-Policy', 'no-referrer')->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
