<?php

declare(strict_types=1);

namespace GnuCms\Tests\Extension;

use GnuCms\Extension\ExternalRequests;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class ExternalRequestsTest extends TestCase
{
    public function testOnlyRegisteredPathBypassesPipelineAndAuthenticationPrecedesParsing(): void
    {
        $called = 0;
        $secret = bin2hex(random_bytes(32));
        $middleware = new ExternalRequests(['/plugins/example/result' => [
            static fn ($request): bool => $request->getHeaderLine('X-Test-Authorization') === $secret,
            static function ($request, $response) use (&$called) {
                $called++;
                self::assertSame(['result' => 'ok'], $request->getParsedBody());
                $response->getBody()->write('{"accepted":true}');
                return $response;
            }, 64]], '/cms');
        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(418); }
        };
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('POST', '/cms/plugins/example/result')->withHeader('Content-Type', 'application/json');
        $request->getBody()->write('{"result":"ok"}');
        self::assertSame(403, $middleware->process($request, $fallback)->getStatusCode());
        self::assertSame(0, $called);
        $request = $request->withHeader('X-Test-Authorization', $secret);
        $response = $middleware->process($request, $fallback);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame(1, $called);
        self::assertSame(405, $middleware->process($request->withMethod('GET'), $fallback)->getStatusCode());
        self::assertSame(415, $middleware->process($request->withoutHeader('Content-Type'), $fallback)->getStatusCode());
        self::assertSame(413, $middleware->process($request->withHeader('Content-Length', '1000'), $fallback)->getStatusCode());
        self::assertSame(418, $middleware->process($factory->createServerRequest('POST', '/cms/plugins/example/other'), $fallback)->getStatusCode());
        self::assertSame(418, $middleware->process($factory->createServerRequest('POST', '/plugins/example/result'), $fallback)->getStatusCode());
    }

    public function testMalformedOversizedAndStorageFailureNeverAcknowledge(): void
    {
        $middleware = new ExternalRequests(['/callback' => [static fn (): bool => true,
            static fn () => throw new \RuntimeException('internal'), 16]]);
        $fallback = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(404); }
        };
        foreach (['{' => 400, '[]' => 400, str_repeat('x', 17) => 413, '{"ok":true}' => 503] as $body => $status) {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/callback')->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($body);
            $response = $middleware->process($request, $fallback);
            self::assertSame($status, $response->getStatusCode());
            self::assertSame('{"accepted":false}', (string) $response->getBody());
        }
    }
}
