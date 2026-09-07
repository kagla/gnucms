<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** 관리 콘솔의 전체 글 목록 (게시판을 가로지른다). */
final class AdminPostsTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testGuestCannotSeeAllPosts(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        $this->assertLoginRedirect($this->get($app, '/admin/posts'), '/admin/posts');
    }

    #[DataProvider('connectionProvider')]
    public function testAdminSeesPostsAcrossBoards(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app);
        $this->loginAsAdmin($app);

        $body = $this->body($this->get($app, '/admin/posts'));

        self::assertStringContainsString('전체 글', $body);
        self::assertStringContainsString('자유 글', $body);
        self::assertStringContainsString('공지 글', $body);
        // 어느 게시판 글인지 함께 보인다.
        self::assertStringContainsString('자유게시판', $body);
        self::assertStringContainsString('공지사항', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testBoardFilterAndSearchNarrowTheList(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app);
        $this->loginAsAdmin($app);

        $filtered = $this->body($this->get($app, '/admin/posts', ['board' => 'notice']));
        self::assertStringContainsString('공지 글', $filtered);
        self::assertStringNotContainsString('자유 글', $filtered);

        $searched = $this->body($this->get($app, '/admin/posts', ['q' => '자유']));
        self::assertStringContainsString('자유 글', $searched);
        self::assertStringNotContainsString('공지 글', $searched);
    }

    /** 대시보드의 게시글 카드가 이 화면으로 이어져야 한다. */
    #[DataProvider('connectionProvider')]
    public function testDashboardPostCardLinksToTheList(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app);
        $this->loginAsAdmin($app);

        self::assertStringContainsString('href="/admin/posts"', $this->body($this->get($app, '/admin')));
    }

    private function seed(App $app): void
    {
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $app->boardService()->create($acl, ['board_key' => 'notice', 'name' => '공지사항']);
        $app->postService()->create($acl, 'free', ['title' => '자유 글', 'content' => '본문']);
        $app->postService()->create($acl, 'notice', ['title' => '공지 글', 'content' => '본문']);
    }

    private function loginAsAdmin(App $app): void
    {
        $id = $app->users()->create(
            'posts-admin@example.com',
            password_hash('admin-password-123', PASSWORD_DEFAULT),
            '전체글 관리자',
            true
        );
        $app->users()->verifyEmail($id);

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'email'      => 'posts-admin@example.com',
            'password'   => 'admin-password-123',
        ]);
    }
}
