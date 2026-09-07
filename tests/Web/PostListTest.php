<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PostListTest extends WebTestCase
{
    private function seed(App $app, int $count): void
    {
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판', 'per_page' => 2]);

        for ($i = 1; $i <= $count; $i++) {
            $app->postService()->create($acl, 'free', [
                'title'   => '글 제목 ' . $i,
                'content' => '내용 ' . $i,
            ]);
        }
    }

    /** @dataProvider connectionProvider */
    public function testPostsAreListed(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app, 1);

        $response = $this->get($app, '/boards/free');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('글 제목 1', $this->body($response));
        self::assertStringContainsString('자유게시판', $this->body($response));
    }

    /** @dataProvider connectionProvider */
    public function testAuthorAvatarAppearsBeforeNicknameInTableView(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $userId = $app->users()->create(
            'avatar@example.com', password_hash('avatar-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $file = '0123456789abcdef0123456789abcdef.png';
        $app->users()->updateAvatar($userId, $file, 'upload');
        $this->seed($app, 1);

        $body = $this->body($this->get($app, '/boards/free'));
        $imageAt = strpos($body, '/media/avatars/' . $file);
        $nameAt = strpos($body, 'post-list-author-name');

        self::assertNotFalse($imageAt);
        self::assertNotFalse($nameAt);
        self::assertLessThan($nameAt, $imageAt);
    }

    /** @dataProvider connectionProvider */
    public function testLegacyBoardUrlRedirectsToClearCanonicalUrl(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        $response = $this->get($app, '/b/free', ['page' => '2']);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/boards/free?page=2', $response->getHeaderLine('Location'));
    }

    /** @dataProvider connectionProvider */
    public function testSecondPageShowsOlderPosts(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app, 3);

        $body = $this->body($this->get($app, '/boards/free', ['page' => '2']));

        self::assertStringContainsString('글 제목 1', $body);
        self::assertStringNotContainsString('글 제목 3', $body);
    }

    /** @dataProvider connectionProvider */
    public function testSearchFiltersPosts(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app, 3);

        $body = $this->body($this->get($app, '/boards/free', ['q' => '제목 2']));

        self::assertStringContainsString('글 제목 2', $body);
        self::assertStringNotContainsString('글 제목 1', $body);
    }

    /**
     * 한글 검색 외에 영문 제목도 대소문자 구분 없이 검색되는지 확인한다.
     *
     * @dataProvider connectionProvider
     */
    public function testSearchIsCaseInsensitiveForAsciiTitles(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $app->postService()->create($acl, 'free', ['title' => 'Hello World', 'content' => '내용']);

        $body = $this->body($this->get($app, '/boards/free', ['q' => 'hello']));

        self::assertStringContainsString('Hello World', $body);
    }

    /**
     * ?q[]=x 처럼 배열로 온 검색어는 Validator 가 "Array to string conversion"
     * 경고 없이 검증 실패(422)로 처리해야 한다. failOnWarning="true" 라서 경고가
     * 나면 이 테스트 자체가 실패로 끝난다.
     *
     * @dataProvider connectionProvider
     */
    public function testArrayQueryParameterFailsValidationInsteadOfCrashing(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app, 1);

        $response = $this->get($app, '/boards/free', ['q' => ['x']]);

        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * ?include_deleted[]=x 처럼 배열로 온 값은 Validator::bool() 이 (string) 캐스팅
     * 하면서 "Array to string conversion" 경고를 냈었다. bool() 은 원래도 인식하지
     * 못하는 문자열이면 조용히 기본값으로 떨어지므로(예: "nonsense" -> false), 배열도
     * 검증 실패가 아니라 그와 같은 방식으로 기본값 처리되어 200 이어야 한다.
     *
     * @dataProvider connectionProvider
     */
    public function testArrayIncludeDeletedQueryParameterDoesNotCrash(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->seed($app, 1);

        $response = $this->get($app, '/boards/free', ['include_deleted' => ['x']]);

        self::assertSame(200, $response->getStatusCode());
    }

    /** @dataProvider connectionProvider */
    public function testUnknownBoardRendersNotFoundPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $response = $this->get($app, '/boards/없는게시판');

        self::assertSame(404, $response->getStatusCode());
    }

    /** @dataProvider connectionProvider */
    public function testUnreadableBoardRedirectsToLogin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'secret',
            'name'      => '관리자전용',
            'perm_read' => 'admin',
        ]);

        $response = $this->get($app, '/boards/secret');

        $this->assertLoginRedirect($response, '/boards/secret');
    }

    /** @dataProvider connectionProvider */
    public function testGuestCannotOpenMemberWriteForm(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);

        $response = $this->get($app, '/boards/free/new');

        $this->assertLoginRedirect($response, '/boards/free/new');
    }

    /**
     * per_page 는 호출자가 정할 수 있고 (예전 하한은 1), total_pages 는 글 수만큼
     * 커질 수 있다. 예전 페이지네이션 템플릿은 1..total_pages 전체를 그렸으므로,
     * per_page=1 인 대형 게시판에서는 글 하나마다 링크 하나가 생겼다 — 공유 호스팅에서
     * 메모리 한도로 죽을 수 있는 크기다. 지금은 현재 페이지 앞뒤 창(5개)만 그려야 한다.
     *
     * @dataProvider connectionProvider
     */
    public function testLargeTotalPagesRendersBoundedPagerLinks(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판', 'per_page' => 1]);
        for ($i = 1; $i <= 40; $i++) {
            $app->postService()->create($acl, 'free', ['title' => '글 ' . $i, 'content' => '내용 ' . $i]);
        }

        $body = $this->body($this->get($app, '/boards/free'));

        // total_pages 는 40 이지만, 창(앞뒤 5개) + 첫/끝 페이지만 링크가 되어야 한다.
        // 예전처럼 1..total_pages 를 통째로 그렸다면 "?page=" 가 40번 가까이 나온다.
        $pageLinks = substr_count($body, '?page=');
        self::assertGreaterThan(0, $pageLinks);
        self::assertLessThanOrEqual(13, $pageLinks);
    }

    #[DataProvider('connectionProvider')]
    public function testAllPostsAndBoardListShareTheSameTablePartial(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'free', ['title' => '첫 글', 'content' => '본문입니다']);

        $all = $this->body($this->get($app, '/posts'));
        $board = $this->body($this->get($app, '/boards/free'));

        // 한 조각이 두 화면을 그린다: 표 클래스가 같고, 각자 자기 칸을 낸다.
        self::assertStringContainsString('class="table table-zebra posts-table"', $all);
        self::assertStringContainsString('class="table table-zebra posts-table"', $board);
        self::assertStringContainsString('<th class="post-col-board">게시판</th>', $all);
        self::assertStringNotContainsString('<th class="post-col-board">게시판</th>', $board);
        self::assertStringNotContainsString('&amp;amp;', $all);
    }

    /**
     * 위 테스트의 '&amp;amp;' 검사는 글이 하나뿐이라 total_pages 가 1 이 되어 페이저 자체가
     * 그려지지 않는다 — 즉 검사할 & 가 애초에 없어 항상 통과하는 헛검사였다. 검색어와 분류를
     * 함께 건 상태에서 실제로 페이저 링크(q=...&category=...&page=2)가 나오게 만들어
     * 이스케이프가 정말 동작하는지 확인한다.
     */
    #[DataProvider('connectionProvider')]
    public function testCombinedSearchAndCategoryQueryIsEscapedOnceInPagerAndChips(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key'    => 'free',
            'name'         => '자유',
            'per_page'     => 5,
            'use_category' => true,
            'categories'   => ['질문', '잡담'],
        ]);

        // '질문' 분류이면서 제목에 '글' 이 들어간 글을 per_page(5) 보다 많이 만들어
        // 검색어+분류를 함께 걸었을 때도 total_pages > 1 이 되게 한다.
        for ($i = 1; $i <= 7; $i++) {
            $app->postService()->create($acl, 'free', [
                'title'    => '글 ' . $i,
                'content'  => '내용 ' . $i,
                'category' => '질문',
            ]);
        }
        // 분류가 다르거나 검색어에 안 걸리는 글도 섞어, 필터가 실제로 좁히는지 확인한다.
        $app->postService()->create($acl, 'free', ['title' => '잡담 글', 'content' => '내용', 'category' => '잡담']);

        $body = $this->body($this->get($app, '/boards/free', ['q' => '글', 'category' => '질문', 'page' => '1']));

        // 페이저 링크(q=글&category=질문&page=2)와 '잡담' 분류 칩(q=글&category=잡담) 이
        // 실제로 몸에 있어야 검사가 헛돌지 않는다.
        $pagerRaw = 'q=' . rawurlencode('글') . '&category=' . rawurlencode('질문') . '&page=2';
        $chipRaw  = 'q=' . rawurlencode('글') . '&category=' . rawurlencode('잡담');

        // 원본(순수 &)은 절대 그대로 나오면 안 되고, 딱 한 번 이스케이프된 형태(&amp;)만 있어야 한다.
        self::assertStringNotContainsString($pagerRaw, $body);
        self::assertStringNotContainsString($chipRaw, $body);
        self::assertStringContainsString(str_replace('&', '&amp;', $pagerRaw), $body);
        self::assertStringContainsString(str_replace('&', '&amp;', $chipRaw), $body);
        self::assertStringNotContainsString('&amp;amp;', $body);
    }
}
