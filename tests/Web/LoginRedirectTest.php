<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use GnuCms\Web\LoginRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LoginRedirectTest extends WebTestCase
{
    #[DataProvider('destinations')]
    public function testOnlyLocalPathsInsideTheInstallationAreAccepted(mixed $url, string $home, ?string $expected): void
    {
        self::assertSame($expected, LoginRedirect::validate($url, $home));
    }

    public static function destinations(): array
    {
        $cases = [];
        foreach (['/', '/admin', '/admin?query=two%20words&page=2', '/posts/1#comments', '/posts?q=%25'] as $url) {
            $cases['valid ' . $url] = [$url, '/', $url];
        }
        foreach (['/cms/admin', '/cms/게시판', '/cms/index.php/admin?q=1'] as $url) {
            $cases['subdirectory ' . $url] = [$url, '/cms/', $url];
        }
        foreach ([null, [], 123, '', 'admin', 'https://outside.example/admin', '//outside.example',
            '///outside.example', '/\\outside.example', "/admin\r\nLocation: https://outside.example",
            '/%2foutside.example', '/%5coutside.example', '/%252foutside.example', '/admin%0d%0aLocation:outside',
            '/admin?x=%0d%0a', '/./admin', '/admin/../login', '/%2e%2e/outside', '/%252e%252e/outside',
            '/login', '/login?url=/admin', '/logout', '/auth/google/callback?code=example', str_repeat('/a', 2050),
        ] as $index => $url) {
            $cases['invalid ' . $index] = [$url, '/', null];
        }
        foreach (['/admin', '/cms-other/admin', '/cms/../admin', '/cms/%2e%2e/admin',
            '/cms/%252e%252e/admin', '/cms/login', '/cms/auth/google', '/cms/%5c../admin'] as $url) {
            $cases['outside installation ' . $url] = [$url, '/cms/', null];
        }
        return $cases;
    }

    #[DataProvider('connectionProvider')]
    public function testPasswordLoginPreservesDestinationAndQueryAfterFailure(array $dbConfig): void
    {
        foreach (['', '/cms', '/cms/index.php'] as $base) {
            $app = $this->makeApp($dbConfig, [], 'default');
            $this->get($app, '/login');
            $id = $app->users()->create('redirect-admin@example.com', password_hash('redirect-password-123', PASSWORD_DEFAULT), '관리자', true);
            $app->users()->verifyEmail($id);
            $kernel = Kernel::create($app, dirname(__DIR__, 2) . '/templates', $base);
            $factory = new ServerRequestFactory();
            $destination = $base . '/admin/login-history?q=two%20words&page=2';
            $redirect = $kernel->handle($factory->createServerRequest('GET', $destination));
            $this->assertLoginRedirect($redirect, $destination, $base);

            $login = $kernel->handle($factory->createServerRequest('GET', $redirect->getHeaderLine('Location')));
            $hidden = 'name="url" value="' . htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') . '"';
            self::assertStringContainsString($hidden, $this->body($login));
            $input = ['csrf_token' => $_SESSION['csrf_token'], 'email' => 'redirect-admin@example.com', 'password' => 'incorrect', 'url' => $destination];
            $failed = $kernel->handle($factory->createServerRequest('POST', $base . '/login')->withParsedBody($input));
            self::assertSame(422, $failed->getStatusCode());
            self::assertStringContainsString($hidden, $this->body($failed));

            $input['password'] = 'redirect-password-123';
            $success = $kernel->handle($factory->createServerRequest('POST', $base . '/login')->withParsedBody($input));
            self::assertSame(303, $success->getStatusCode());
            self::assertSame($destination, $success->getHeaderLine('Location'));
            self::assertSame(200, $kernel->handle($factory->createServerRequest('GET', $destination))->getStatusCode());
            $alreadyLoggedIn = $kernel->handle($factory->createServerRequest('GET', $redirect->getHeaderLine('Location')));
            self::assertSame($destination, $alreadyLoggedIn->getHeaderLine('Location'));
            $kernel->handle($factory->createServerRequest('POST', $base . '/logout')->withParsedBody(['csrf_token' => $_SESSION['csrf_token']]));
        }
    }

    public function testForgedPostDestinationCannotLeaveSiteAndMemberStillCannotAccessAdmin(): void
    {
        $app = $this->makeApp(['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null], [], 'default');
        $this->get($app, '/login');
        $id = $app->users()->create('redirect-member@example.com', password_hash('redirect-password-123', PASSWORD_DEFAULT), '회원');
        $app->users()->verifyEmail($id);
        $input = ['csrf_token' => $_SESSION['csrf_token'], 'email' => 'redirect-member@example.com', 'password' => 'redirect-password-123'];

        $invalidForm = $this->get($app, '/login', ['url' => '//outside.example']);
        self::assertStringContainsString('name="url" value=""', $this->body($invalidForm));
        $csrf = $this->post($app, '/login', array_replace($input, ['csrf_token' => 'wrong', 'url' => '/admin']));
        self::assertSame(403, $csrf->getStatusCode());
        self::assertArrayNotHasKey('user_id', $_SESSION);

        $success = $this->post($app, '/login', $input + ['url' => '//outside.example']);
        self::assertSame('/', $success->getHeaderLine('Location'));
        $target = $this->get($app, '/login', ['url' => '/admin']);
        self::assertSame('/admin', $target->getHeaderLine('Location'));
        self::assertSame(403, $this->get($app, '/admin')->getStatusCode());
    }
}
