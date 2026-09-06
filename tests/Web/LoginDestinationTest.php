<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Web\LoginDestination;
use GnuCms\Support\Clock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LoginDestinationTest extends TestCase
{
    private function destination(): string
    {
        $slim = AppFactory::create(); $slim->setBasePath('/cms');
        $slim->get('/', static fn ($request, $response) => $response)->setName('boards.index');
        $slim->get('/login', static fn ($request, $response) => $response->withHeader('Location', LoginDestination::consume($request)));
        $slim->addRoutingMiddleware();
        return $slim->handle((new ServerRequestFactory())->createServerRequest('GET', '/cms/login'))->getHeaderLine('Location');
    }

    public function testDestinationIsSingleUseAndExpires(): void
    {
        $path = '/cms/modules/shop/order?id=' . bin2hex(random_bytes(16));
        LoginDestination::remember($path);
        self::assertSame($path, $this->destination());
        self::assertSame('/cms/', $this->destination());
        LoginDestination::remember($path);
        $_SESSION['login_destination']['expires'] = Clock::timestamp() - 1;
        self::assertSame('/cms/', $this->destination());
    }

    public static function unsafePaths(): array
    {
        return array_map(static fn ($path) => [$path], [
            'https://outside.example', '//outside.example', '/other/page', '/cms-other/page',
            '/cms/../outside', '/cms/%2e%2e/outside', '/cms/%252e%252e/outside',
            '/cms/\\outside', '/cms/%5coutside', "/cms/\r\nLocation: https://outside.example",
            '/cms/%0d%0aLocation:outside', '/cms/./outside',
        ]);
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafePathsFallBackToTheSiteHome(string $path): void
    {
        LoginDestination::remember($path);
        self::assertSame('/cms/', $this->destination());
        self::assertArrayNotHasKey('login_destination', $_SESSION);
    }
}
