<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Db\Schema;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ErrorPageTest extends WebTestCase
{
    private const SQLITE = ['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null];

    #[DataProvider('connectionProvider')]
    public function testLoginRequiredRequestRedirectsWithinTheInstallationPath(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [], 'default');
        $factory = new ServerRequestFactory();

        foreach (['', '/cms', '/cms/index.php'] as $base) {
            foreach (['/admin', '/account'] as $path) {
                $kernel = Kernel::create($app, dirname(__DIR__, 2) . '/templates', $base);
                $response = $kernel->handle($factory->createServerRequest('GET', $base . $path));
                $this->assertLoginRedirect($response, $base . $path, $base);
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                $login = $kernel->handle($factory->createServerRequest('GET', $response->getHeaderLine('Location')));
                self::assertSame(200, $login->getStatusCode());
                self::assertStringContainsString('name="url" value="' . $base . $path . '"', $this->body($login));
            }
        }
    }

    public function testForbiddenErrorKeepsHomeActionForSignedInMember(): void
    {
        $app = $this->makeApp(self::SQLITE, [], 'default');
        $id = $app->users()->createSocial('error-member@example.com', '일반회원');
        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();

        $response = $this->get($app, '/admin');

        self::assertSame(403, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '~<div class="card-actions">\s*<a[^>]+href="/"[^>]*>.*?홈으로 가기</a>~s',
            $this->body($response)
        );
    }

    public function testLoginRequiredJsonRequestKeepsUnauthorizedResponse(): void
    {
        $app = $this->makeApp(self::SQLITE);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/admin')
            ->withHeader('Accept', 'application/json');
        $response = Kernel::create($app, dirname(__DIR__, 2) . '/templates', '')->handle($request);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertSame(
            ['error' => ['message' => '로그인이 필요합니다.', 'status' => 401]],
            json_decode($this->body($response), true)
        );
    }

    /**
     * ErrorPageMiddleware 는 HttpNotFoundException 만 이름으로 잡고 나머지 Slim 라우팅
     * 예외(HttpMethodNotAllowedException 등)는 Throwable 로 떨어져 500 이 됐었다.
     * "/" 는 GET 만 등록되어 있으므로 POST 는 405 여야 한다.
     */
    public function testMethodNotAllowedRenders405NotInternalError(): void
    {
        $app = $this->makeApp(self::SQLITE);

        $response = $this->request($app, 'POST', '/');

        self::assertSame(405, $response->getStatusCode());
    }

    /**
     * debug=false 는 모든 웹 테스트 중 유일하게 이 테스트에서만 켠다. 내부 예외의
     * 클래스명·SQL 원문 같은 세부사항이 방문자 화면에 새면 안 된다는 보안 성질을
     * 증명하는 자리다.
     */
    public function testInternalErrorHidesDetailsWhenDebugIsOff(): void
    {
        $app = $this->makeApp(self::SQLITE, ['debug' => false]);

        // posts 테이블을 지워서 정상 요청이 SQL 오류로 실패하게 만든다.
        // Connection 은 이 오류를 DomainError::internal() 로 감싸는데, 그 메시지 안에
        // 원본 PDO 오류 문구와 SQL 문 전체가 들어 있다 — 새면 안 되는 내부 정보다.
        (new Schema($app->db()))->drop();

        $response = $this->get($app, '/');
        $body = $this->body($response);

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('문제가 계속되면', $body);
        self::assertStringNotContainsString('DomainError', $body);
        self::assertStringNotContainsString('SQL:', $body);
        self::assertStringNotContainsString('no such table', $body);
    }

    /**
     * ErrorPageMiddleware::render() 가 DomainError::details() 를 버리고 있었다.
     * q 가 100자를 넘으면 검증 실패(422)가 나는데, 화면에는 제목도 일반 문구고
     * 어느 필드가 문제인지도 안 나왔다 — 폼이 없는 화면(GET 검색창)에서 특히 아쉽다.
     */
    public function testValidationErrorRendersFieldDetails(): void
    {
        $app = $this->makeApp(self::SQLITE);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);

        $response = $this->get($app, '/boards/free', ['q' => str_repeat('가', 101)]);
        $body = $this->body($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('입력값을 확인해 주세요', $body);
        // 'q' 하나만 찾으면 페이지 어디든 영문자 q 가 있기만 해도 우연히 통과한다
        // (한글 UI 문구에는 없어서 지금은 통과하지만 근거가 안 된다). error.php
        // 는 필드명을 <strong> 태그로 감싸 렌더링하므로 그 맥락까지 확인한다.
        self::assertStringContainsString('<strong>q</strong>', $body);
        self::assertStringContainsString('100자를 넘을 수 없습니다', $body);
    }
}
