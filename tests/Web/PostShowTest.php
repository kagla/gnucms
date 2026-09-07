<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;

final class PostShowTest extends WebTestCase
{
    /** @dataProvider connectionProvider */
    public function testMemberAvatarAppearsOnPostAndComment(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $userId = $app->users()->create(
            'avatar@example.com', password_hash('avatar-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        self::assertSame(1, $userId);
        $file = '0123456789abcdef0123456789abcdef.png';
        $app->users()->updateAvatar($userId, $file, 'upload');
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'free', 'name' => '자유게시판', 'perm_comment' => 'member',
        ]);
        $post = $app->postService()->create($acl, 'free', ['title' => '사진 글', 'content' => '본문']);
        $app->commentService()->create($acl, $post['id'], ['content' => '사진 댓글']);

        $body = $this->body($this->get($app, '/posts/' . $post['id']));

        self::assertSame(2, substr_count($body, '/media/avatars/' . $file),
            '글 작성자와 댓글 작성자 프로필에 각각 이미지가 나와야 한다');
    }

    /** @dataProvider connectionProvider */
    public function testPostAndCommentTreeAreRendered(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key'    => 'free',
            'name'         => '자유게시판',
            'perm_comment' => 'guest',
        ]);
        $post = $app->postService()->create($acl, 'free', ['title' => '제목', 'content' => '본문입니다']);

        $parent = $app->commentService()->create($acl, $post['id'], ['content' => '부모 댓글']);
        $app->commentService()->create($acl, $post['id'], [
            'content'   => '자식 댓글',
            'parent_id' => $parent['id'],
        ]);

        $response = $this->get($app, '/posts/' . $post['id']);
        $body = $this->body($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('본문입니다', $body);
        self::assertStringContainsString('부모 댓글', $body);
        self::assertStringContainsString('자식 댓글', $body);
    }

    /** @dataProvider connectionProvider */
    public function testBoardCanShowItsListBelowTheView(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'with-list', 'name' => '목록 있는 게시판',
            'show_list_below_view' => true, 'list_type' => 'news',
        ]);
        $app->boardService()->create($acl, ['board_key' => 'without-list', 'name' => '목록 없는 게시판']);
        $shown = $app->postService()->create($acl, 'with-list', ['title' => '현재 글', 'content' => '본문']);
        $app->postService()->create($acl, 'with-list', ['title' => '함께 보일 글', 'content' => '본문']);
        $hidden = $app->postService()->create($acl, 'without-list', ['title' => '기본 글', 'content' => '본문']);

        $shownBody = $this->body($this->get($app, '/posts/' . $shown['id']));
        self::assertStringContainsString('class="below-view-list" id="below-view-list"', $shownBody);
        self::assertStringContainsString('id="below-view-list-title">목록</h2>', $shownBody);
        self::assertStringContainsString('함께 보일 글', $shownBody);
        self::assertStringContainsString('list-row is-current-post', $shownBody);

        $hiddenBody = $this->body($this->get($app, '/posts/' . $hidden['id']));
        self::assertStringNotContainsString('class="below-view-list"', $hiddenBody);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('connectionProvider')]
    public function testListBelowViewUsesTheSamePaginationAsBoardList(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'paged', 'name' => '페이지 게시판',
            'show_list_below_view' => true, 'per_page' => 10,
        ]);
        $current = $app->postService()->create($acl, 'paged', ['title' => '첫 글', 'content' => '본문']);
        for ($i = 1; $i <= 10; $i++) {
            $app->postService()->create($acl, 'paged', ['title' => '추가 글 ' . $i, 'content' => '본문']);
        }

        $firstPage = $this->body($this->get($app, '/posts/' . $current['id']));
        self::assertStringContainsString(
            'href="/posts/' . $current['id'] . '?page=2#below-view-list"', $firstPage
        );

        $secondPage = $this->body($this->get($app, '/posts/' . $current['id'], ['page' => '2']));
        self::assertStringContainsString('class="is-current-post"', $secondPage);
        self::assertStringContainsString('첫 글', $secondPage);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('connectionProvider')]
    public function testNextPostCrossesToTheNextEmbeddedListPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'boundary', 'name' => '경계 게시판',
            'show_list_below_view' => true, 'per_page' => 10,
        ]);
        $posts = [];
        for ($i = 1; $i <= 11; $i++) {
            $posts[] = $app->postService()->create($acl, 'boundary', [
                'title' => '글 ' . $i, 'content' => '본문',
            ]);
        }

        // 최신순 1페이지의 마지막 글은 id=2, 그 다음(더 오래된) 글 id=1은 2페이지다.
        $body = $this->body($this->get($app, '/posts/' . $posts[1]['id']));
        self::assertStringContainsString(
            'post-adjacent-next" rel="next" href="/posts/' . $posts[0]['id']
                . '?page=2"',
            $body
        );
    }

    /** @dataProvider connectionProvider */
    public function testAdjacentNavigationFollowsBoardOrAllPostsContext(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $app->boardService()->create($acl, ['board_key' => 'gallery', 'name' => '갤러리']);
        $freeOld = $app->postService()->create($acl, 'free', ['title' => '자유 이전', 'content' => '본문']);
        $gallery = $app->postService()->create($acl, 'gallery', ['title' => '갤러리 사이 글', 'content' => '본문']);
        $freeNew = $app->postService()->create($acl, 'free', ['title' => '자유 다음', 'content' => '본문']);

        $boardBody = $this->body($this->get($app, '/posts/' . $freeOld['id']));
        self::assertStringNotContainsString('게시판 기준', $boardBody);
        self::assertStringContainsString('href="/posts/' . $freeNew['id'] . '"', $boardBody);
        self::assertStringNotContainsString('갤러리 사이 글', $boardBody);

        $allBody = $this->body($this->get($app, '/posts/' . $freeOld['id'], ['scope' => 'all']));
        self::assertStringNotContainsString('전체 글 기준', $allBody);
        self::assertStringContainsString('갤러리 사이 글', $allBody);
        self::assertStringContainsString('post-adjacent-previous" rel="prev" href="/posts/'
            . $gallery['id'] . '?scope=all"', $allBody);
        self::assertStringContainsString('href="/posts"', $allBody);

        $allList = $this->body($this->get($app, '/posts'));
        self::assertStringContainsString('/posts/' . $freeOld['id'] . '?scope=all', $allList);
    }

    /** @dataProvider connectionProvider */
    public function testViewCountIncreasesOnlyOncePerSession(array $dbConfig): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION['viewed_posts']);
        session_write_close();
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $post = $app->postService()->create($acl, 'free', ['title' => '제목', 'content' => '본문']);

        $this->get($app, '/posts/' . $post['id']);
        $this->get($app, '/posts/' . $post['id']);

        self::assertSame(1, (int) $app->posts()->find((int) $post['id'])['view_count']);
    }

    /**
     * 본문은 편집기가 보내는 HTML 을 허용한다. 대신 저장과 출력 두 곳에서 정화하므로
     * 서식은 살아남고 스크립트·이벤트 속성은 사라져야 한다.
     *
     * @dataProvider connectionProvider
     */
    public function testDangerousHtmlIsStrippedWhileFormattingSurvives(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $post = $app->postService()->create($acl, 'free', [
            'title'   => '제목',
            'content' => '<p>안녕 <strong>굵게</strong></p>'
                . '<script>alert(1)</script>'
                . '<img src="x" onerror="alert(1)">'
                . '<a href="javascript:alert(1)">링크</a>',
        ]);

        $body = $this->body($this->get($app, '/posts/' . $post['id']));

        self::assertStringContainsString('<strong>굵게</strong>', $body);
        // 레이아웃 자체가 <script> 를 쓰므로 태그가 아니라 심어 둔 값으로 판단한다.
        self::assertStringNotContainsString('alert(1)', $body);
        self::assertStringNotContainsString('onerror', $body);
        self::assertStringNotContainsString('javascript:', $body);
    }

    /** 평문으로 저장된 옛 글도 줄바꿈을 지키며 그대로 보여야 한다. */
    #[\PHPUnit\Framework\Attributes\DataProvider('connectionProvider')]
    public function testPlainTextPostStillRendersWithLineBreaks(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $post = $app->postService()->create($acl, 'free', [
            'title'   => '제목',
            'content' => "첫 줄\n둘째 줄",
        ]);

        $body = $this->body($this->get($app, '/posts/' . $post['id']));

        self::assertStringContainsString('첫 줄', $body);
        self::assertStringContainsString('<br', $body);
    }

    /** @dataProvider connectionProvider */
    public function testMissingPostRendersNotFoundPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        self::assertSame(404, $this->get($app, '/posts/99999')->getStatusCode());
    }

    /** @dataProvider connectionProvider */
    public function testLegacyPostUrlRedirectsPermanentlyToCanonicalUrl(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        $response = $this->get($app, '/p/2', ['from' => 'bookmark']);

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/posts/2?from=bookmark', $response->getHeaderLine('Location'));
    }

    /**
     * 비밀글 판정은 게시판 읽기 권한과 별개다. 비회원(게스트)이 남의 비밀글을 열면
     * 403 이어야 한다 — 로그인해도 소용없다는 뜻. board.perm_read 자체는 guest 라서
     * 게시판 읽기는 통과하고, PostService::loadForRead() 의 비밀글 검사가 막는다.
     *
     * @dataProvider connectionProvider
     */
    public function testSecretPostIsDeniedToGuestWith403(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key'  => 'free',
            'name'       => '자유게시판',
            'use_secret' => true,
        ]);
        $post = $app->postService()->create($acl, 'free', [
            'title'     => '비밀글',
            'content'   => '아무나 보면 안 됨',
            'is_secret' => true,
        ]);

        $response = $this->get($app, '/posts/' . $post['id']);

        self::assertSame(403, $response->getStatusCode());
    }

    /** @dataProvider connectionProvider */
    public function testGuestSecretPostCanBeUnlockedWithItsPassword(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유게시판', 'perm_write' => 'guest', 'use_secret' => true,
        ]);
        $guest = new \GnuCms\Auth\Acl(\GnuCms\Auth\Identity::guest());
        $post = $app->postService()->create($guest, 'free', [
            'title' => '손님 비밀글', 'content' => '작성자만 볼 본문', 'is_secret' => true,
            'author_name' => '손님', 'password' => 'guest-pass-123',
        ]);

        $prompt = $this->get($app, '/posts/' . $post['id']);
        self::assertSame(200, $prompt->getStatusCode());
        self::assertStringContainsString('비밀글 보기', $this->body($prompt));
        self::assertStringNotContainsString('작성자만 볼 본문', $this->body($prompt));

        $wrong = $this->post($app, '/posts/' . $post['id'] . '/password', [
            'csrf_token' => $_SESSION['csrf_token'], 'password' => 'wrong-password',
        ]);
        self::assertSame(422, $wrong->getStatusCode());
        self::assertStringContainsString('비밀번호가 올바르지 않습니다', $this->body($wrong));

        $unlocked = $this->post($app, '/posts/' . $post['id'] . '/password', [
            'csrf_token' => $_SESSION['csrf_token'], 'password' => 'guest-pass-123',
        ]);
        self::assertSame(303, $unlocked->getStatusCode());
        self::assertSame('/posts/' . $post['id'], $unlocked->getHeaderLine('Location'));

        $shown = $this->get($app, '/posts/' . $post['id']);
        self::assertSame(200, $shown->getStatusCode());
        self::assertStringContainsString('작성자만 볼 본문', $this->body($shown));
    }

    /** @dataProvider connectionProvider */
    public function testOnlySecretPostOwnerOrAdminCanWriteComments(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'secret-owner', 'name' => '비밀글',
            'perm_write' => 'member', 'perm_comment' => 'guest', 'use_secret' => true,
        ]);
        $owner = new \GnuCms\Auth\Acl(\GnuCms\Auth\Identity::user('42', '원글쓴이', false));
        $other = new \GnuCms\Auth\Acl(\GnuCms\Auth\Identity::user('43', '다른회원', false));
        $post = $app->postService()->create($owner, 'secret-owner', [
            'title' => '회원 비밀글', 'content' => '본문', 'is_secret' => '1',
        ]);
        $loaded = $app->postService()->loadForRead($owner, (int) $post['id'], null);

        self::assertTrue($owner->canCommentOnPost($loaded['board'], $loaded['post']));
        self::assertTrue($this->adminAcl()->canCommentOnPost($loaded['board'], $loaded['post']));
        self::assertFalse($other->canCommentOnPost($loaded['board'], $loaded['post']));
    }

    /**
     * perm_read = admin 인 게시판은 게스트를 로그인으로 보낸다.
     * BoardService::getEntity() -> Acl::assertCanRead()의 401 판정을 웹에서 변환한다.
     *
     * @dataProvider connectionProvider
     */
    public function testPostInAdminOnlyBoardRedirectsGuestToLogin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'secret',
            'name'      => '관리자전용',
            'perm_read' => 'admin',
        ]);
        $post = $app->postService()->create($acl, 'secret', ['title' => '제목', 'content' => '본문']);

        $response = $this->get($app, '/posts/' . $post['id']);

        $this->assertLoginRedirect($response, '/posts/' . $post['id']);
    }
}
