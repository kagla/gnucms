<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

/** 비회원 글쓰기: 폼이 이름·비밀번호 칸을 내주고, 새 주소는 /new 다. */
final class GuestWriteTest extends WebTestCase
{
    private function makeGuestBoard(array $dbConfig): \GnuCms\App
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['guest_write_enabled' => '1']);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유', 'perm_write' => 'guest',
        ]);

        return $app;
    }

    #[DataProvider('connectionProvider')]
    public function testGuestWritingIsDisabledByDefault(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유', 'perm_write' => 'guest',
        ]);

        $response = $this->get($app, '/boards/free/new');

        $this->assertLoginRedirect($response, '/boards/free/new');
    }

    #[DataProvider('connectionProvider')]
    public function testGuestSeesNameAndPasswordFieldsOnWriteForm(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);

        $body = $this->body($this->get($app, '/boards/free/new'));

        self::assertStringContainsString('name="author_name"', $body);
        self::assertStringContainsString('name="password"', $body);
        self::assertStringContainsString('name="password" minlength="8"', $body);
        self::assertStringContainsString('수정·삭제에 씁니다', $body);
        self::assertStringContainsString("'비밀번호를 '+minLength+'자 이상 입력해 주세요.'", $body);
        self::assertStringContainsString('서버에서 허용한 업로드 용량을 초과했습니다.', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testMemberDoesNotSeeGuestFields(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $id = $app->users()->create('user@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '회원사람');
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'user@example.com', 'password' => 'member-password-123',
        ]);

        $body = $this->body($this->get($app, '/boards/free/new'));

        self::assertStringNotContainsString('name="author_name"', $body);
        self::assertStringNotContainsString('name="password"', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testMemberPostAndCommentDoNotStoreIp(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $post = $app->postService()->create($this->adminAcl(), 'free', [
            'title' => '회원 글', 'content' => '본문입니다',
        ], '203.0.113.90');
        $app->commentService()->create($this->adminAcl(), (int) $post['id'], [
            'content' => '회원 댓글',
        ], '203.0.113.91');

        self::assertNull($app->db()->selectOne(
            'SELECT author_ip FROM ' . $app->db()->table('posts') . ' WHERE id = ?',
            [(int) $post['id']]
        )['author_ip']);
        self::assertNull($app->db()->selectOne(
            'SELECT author_ip FROM ' . $app->db()->table('comments') . ' WHERE post_id = ?',
            [(int) $post['id']]
        )['author_ip']);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCanWriteAPostThroughTheForm(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');

        $created = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님의 글', 'content' => '본문입니다',
            'author_name' => '지나가던손님', 'password' => 'guest-pass-123',
        ], ['REMOTE_ADDR' => '203.0.113.77']);

        self::assertSame(303, $created->getStatusCode());
        $show = $this->body($this->get($app, $created->getHeaderLine('Location')));
        self::assertStringContainsString('손님의 글', $show);
        self::assertStringContainsString('지나가던손님', $show);
        self::assertStringContainsString('203.0.xxx.77', $show);
        self::assertStringNotContainsString('203.0.113.77', $show);
        self::assertMatchesRegularExpression('/<time[^>]*>[^<]+<\/time>\s*<span class="author-ip">203\.0\.xxx\.77<\/span>/', $show);
        self::assertStringNotContainsString('203.0.xxx.77', $this->body($this->get($app, '/boards/free')));
        $stored = $app->db()->selectOne('SELECT author_ip FROM ' . $app->db()->table('posts') . ' WHERE title = ?', ['손님의 글']);
        self::assertSame('203.0.113.77', $stored['author_ip']);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestMissingPasswordSeesTheErrorOnItsField(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');

        $response = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님의 글', 'content' => '본문입니다', 'author_name' => '지나가던손님',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->body($response);
        // 비밀번호 칸 바로 아래에 오류 문구가 보여야 한다. 예전에는 칸 자체가 없어 아무것도 안 보였다.
        self::assertStringContainsString('name="password"', $body);
        self::assertStringContainsString('필수 항목입니다', $body);
        // 다시 그린 폼에 입력값이 남는다.
        self::assertStringContainsString('지나가던손님', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testEmptyWriteShowsMissingFieldSummary(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');

        $response = $this->post($app, '/boards/free/new', ['csrf_token' => $_SESSION['csrf_token']]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->body($response);
        self::assertStringContainsString('name="author_name"', $body);
        self::assertStringContainsString('fieldset is-invalid', $body);
        self::assertStringNotContainsString('data-post-error-summary', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testOldWriteUrlsCannotOpenFormsOrCreatePosts(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'notice', 'name' => '공지사항', 'perm_write' => 'guest',
        ]);
        $factory = new ServerRequestFactory();

        foreach (['', '/cms'] as $base) {
            $kernel = Kernel::create($app, dirname(__DIR__, 2) . '/templates', $base);
            foreach (['free', 'notice'] as $key) {
                $new = $kernel->handle($factory->createServerRequest('GET', $base . '/boards/' . $key . '/new'));
                self::assertSame(200, $new->getStatusCode());
                foreach (['/boards/', '/b/'] as $prefix) {
                    $url = $base . $prefix . $key . '/write';
                    $get = $kernel->handle($factory->createServerRequest('GET', $url));
                    self::assertSame(404, $get->getStatusCode());
                    self::assertSame('', $get->getHeaderLine('Location'));
                    $post = $kernel->handle($factory->createServerRequest('POST', $url)->withParsedBody([
                        'csrf_token' => $_SESSION['csrf_token'],
                        'title' => '등록되면 안 되는 글', 'content' => '본문',
                        'author_name' => '손님', 'password' => 'guest-pass-123',
                    ]));
                    self::assertSame(404, $post->getStatusCode());
                    self::assertSame('', $post->getHeaderLine('Location'));
                }
            }
        }

        self::assertSame(0, (int) $app->db()->selectOne('SELECT COUNT(*) AS n FROM ' . $app->db()->table('posts'))['n']);
    }

    #[DataProvider('connectionProvider')]
    public function testWrongGuestPasswordSaysSoInsteadOfAskingToLogIn(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');
        $created = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님의 글', 'content' => '본문입니다',
            'author_name' => '지나가던손님', 'password' => 'guest-pass-123',
        ]);
        $postUrl = $created->getHeaderLine('Location');

        $response = $this->post($app, $postUrl . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '고친 제목', 'content' => '본문입니다', 'password' => 'wrong-pass-999',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->body($response);
        self::assertStringContainsString('비밀번호가 올바르지 않습니다', $body);
        self::assertStringNotContainsString('로그인이 필요합니다', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testMissingGuestPasswordAsksForIt(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');
        $created = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님의 글', 'content' => '본문입니다',
            'author_name' => '지나가던손님', 'password' => 'guest-pass-123',
        ]);

        $response = $this->post($app, $created->getHeaderLine('Location') . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '고친 제목', 'content' => '본문입니다',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('비밀번호를 입력해 주세요', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testGuestEditingMemberPostStillGetsUnauthorized(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $post = $app->postService()->create($this->adminAcl(), 'free', ['title' => '회원 글', 'content' => '본문']);

        try {
            $app->postService()->update(new \GnuCms\Auth\Acl(\GnuCms\Auth\Identity::guest()), $post['id'], [
                'title' => '가로채기', 'content' => '본문', 'password' => 'whatever-123',
            ]);
            self::fail('401 이 나와야 한다');
        } catch (\GnuCms\Error\DomainError $e) {
            // 회원 글은 비밀번호가 소유 증명이 아니므로 기존대로 로그인 안내다.
            self::assertSame(401, $e->status());
            self::assertSame('로그인이 필요합니다.', $e->getMessage());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testWrongGuestPasswordOnDeleteSaysSoToo(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');
        $created = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님의 글', 'content' => '본문입니다',
            'author_name' => '지나가던손님', 'password' => 'guest-pass-123',
        ]);

        $response = $this->post($app, $created->getHeaderLine('Location') . '/delete', [
            'csrf_token' => $_SESSION['csrf_token'], 'password' => 'wrong-pass-999',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $body = $this->body($response);
        self::assertStringContainsString('비밀번호가 올바르지 않습니다', $body);
        self::assertStringNotContainsString('입력값을 확인해 주세요', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestNameLongerThanTwentyCharsIsRejected(array $dbConfig): void
    {
        $app = $this->makeGuestBoard($dbConfig);
        $this->get($app, '/boards/free/new');

        $response = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님 글', 'content' => '본문',
            'author_name' => str_repeat('가', 21), 'password' => 'guest-pass-123',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('20자', $this->body($response));
    }
}
