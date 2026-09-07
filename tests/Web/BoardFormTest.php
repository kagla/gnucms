<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** 게시판 설정 폼의 표시와 저장 계약. */
final class BoardFormTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testEditFormShowsBoardKeyWithoutMakingItSubmittable(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);
        $this->loginAsAdmin($app);

        $body = $this->body($this->get($app, '/admin/boards/free/edit'));

        self::assertStringContainsString('게시판 키', $body);
        self::assertStringContainsString('value="free" readonly', $body);
        // 키는 만든 뒤 바꿀 수 없다. 폼에 name 이 없어야 값이 실려 가지 않는다.
        self::assertStringNotContainsString('name="board_key"', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testCreateFormStillAsksForBoardKey(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->loginAsAdmin($app);

        self::assertStringContainsString(
            'name="board_key"',
            $this->body($this->get($app, '/admin/boards/new'))
        );
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanEnableListBelowPostView(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);
        $this->loginAsAdmin($app);

        $form = $this->body($this->get($app, '/admin/boards/free/edit'));
        self::assertStringContainsString('name="show_list_below_view"', $form);
        self::assertStringContainsString('게시글 보기 아래에 목록 표시', $form);

        $response = $this->post($app, '/admin/boards/free/edit', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '', 'name' => '자유게시판', 'description' => '',
            'perm_read' => 'guest', 'perm_write' => 'member', 'perm_comment' => 'member',
            'categories_text' => '', 'managers_text' => '', 'list_type' => 'list',
            'home_limit' => '5', 'per_page' => '20', 'sort_order' => '0',
            'show_list_below_view' => '1',
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertTrue($app->boardService()->get($app->guestAcl(), 'free')['show_list_below_view']);
    }

    /** 칩 UI 는 덧붙인 것이고, JS 가 꺼지면 textarea 가 그대로 쓰인다. */
    #[DataProvider('connectionProvider')]
    public function testCategoryChipUiKeepsTextareaFallback(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유게시판', 'use_category' => true,
            'categories' => ['잡담', '질문'],
        ]);
        $this->loginAsAdmin($app);

        $body = $this->body($this->get($app, '/admin/boards/free/edit'));

        self::assertStringContainsString('data-tag-input', $body);
        self::assertStringContainsString('data-tag-store', $body);
        self::assertStringContainsString('name="categories_text"', $body);
        self::assertStringContainsString("잡담\n질문", $body);
    }

    /** 칩 UI 는 같은 textarea 를 채우므로 저장 계약(줄바꿈 구분)은 그대로다. */
    #[DataProvider('connectionProvider')]
    public function testSavingCategoriesKeepsNewlineContract(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);
        $this->loginAsAdmin($app);

        $this->get($app, '/admin/boards/free/edit');
        $this->post($app, '/admin/boards/free/edit', [
            'csrf_token'      => $_SESSION['csrf_token'] ?? '',
            'name'            => '자유게시판',
            'description'     => '',
            'perm_read'       => 'guest',
            'perm_write'      => 'member',
            'perm_comment'    => 'member',
            'use_category'    => '1',
            'categories_text' => "잡담\n질문\n후기",
            'list_type'       => 'gallery',
            'per_page'        => '20',
            'sort_order'      => '0',
        ]);

        $board = $app->boardService()->get($app->guestAcl(), 'free');

        self::assertSame(['잡담', '질문', '후기'], $board['categories']);
        self::assertSame('gallery', $board['list_type']);
    }

/**
     * 분류를 쓰는 게시판인데 고르지 않으면 이유를 보여 줘야 한다.
     * 예전에는 화면에서도 서버에서도 아무 말 없이 막혀 왜 안 되는지 알 수 없었다.
     */
    #[DataProvider('connectionProvider')]
    public function testMissingCategoryExplainsItself(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['guest_write_enabled' => '1']);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유게시판',
            'use_category' => true, 'categories' => ['잡담', '질문'], 'perm_write' => 'guest',
        ]);

        $this->get($app, '/boards/free/new');
        $response = $this->post($app, '/boards/free/new', [
            'csrf_token'  => $_SESSION['csrf_token'] ?? '',
            'author_name' => '아무개',
            'password'    => 'pass-1234',
            'title'       => '제목',
            'content'     => '본문',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('분류를 선택해 주세요', $this->body($response));
    }

    /** 분류를 쓰지 않는 게시판에서는 분류 없이도 글이 써진다. */
    #[DataProvider('connectionProvider')]
    public function testBoardWithoutCategoriesStillAcceptsPosts(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['guest_write_enabled' => '1']);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'plain', 'name' => '분류없음', 'perm_write' => 'guest',
        ]);

        $this->get($app, '/boards/plain/new');
        $response = $this->post($app, '/boards/plain/new', [
            'csrf_token'  => $_SESSION['csrf_token'] ?? '',
            'author_name' => '아무개',
            'password'    => 'pass-1234',
            'title'       => '제목',
            'content'     => '본문',
        ]);

        self::assertSame(303, $response->getStatusCode());
    }

    private function loginAsAdmin(App $app): void
    {
        $id = $app->users()->create(
            'form-admin@example.com',
            password_hash('admin-password-123', PASSWORD_DEFAULT),
            '폼 관리자',
            true
        );
        $app->users()->verifyEmail($id);

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'email'      => 'form-admin@example.com',
            'password'   => 'admin-password-123',
        ]);
    }
}
