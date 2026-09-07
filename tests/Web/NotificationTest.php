<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * 사이트 안 알림함. 내 글에 댓글이 달리면 머리글 종에 표시가 뜨고,
 * 알림을 누르면 그 댓글 자리로 간다.
 */
final class NotificationTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testCommentOnMyPostRaisesTheBellBadge(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);

        self::assertStringNotContainsString('bell-dot', $this->body($this->get($app, '/notifications')));

        $this->commentAsGuest($app, $postId, '손님이 남긴 댓글');

        $body = $this->body($this->get($app, '/notifications'));
        self::assertStringContainsString('bell-dot', $body, '안 읽은 알림이 있으면 종에 표시가 붙는다');
        self::assertStringContainsString('손님', $body);
        self::assertStringContainsString('내 글에 댓글을 달았습니다', $body);
    }

    /** 내가 쓴 댓글로 나에게 알림이 오면 안 된다. */
    #[DataProvider('connectionProvider')]
    public function testMyOwnCommentDoesNotNotifyMe(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);

        $this->post($app, '/posts/' . $postId . '/comments', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'content'    => '내가 단 댓글',
        ]);

        $body = $this->body($this->get($app, '/notifications'));
        self::assertStringContainsString('아직 알림이 없습니다', $body);
        self::assertStringNotContainsString('bell-dot', $body);
    }

    /** 알림을 누르면 읽음이 되고 해당 댓글 자리로 보낸다. */
    #[DataProvider('connectionProvider')]
    public function testOpeningANotificationMarksItReadAndJumpsToTheComment(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);
        $this->commentAsGuest($app, $postId, '보러 오세요');

        $id = $this->firstNotificationId($app, $postId);
        $response = $this->get($app, '/notifications/' . $id);

        self::assertSame(303, $response->getStatusCode());
        self::assertMatchesRegularExpression(
            '#^/posts/' . $postId . '\#comment-[0-9]+$#',
            $response->getHeaderLine('Location')
        );
        self::assertStringNotContainsString('bell-dot', $this->body($this->get($app, '/notifications')));
    }

    /** 알림 id 를 바꿔 넣어도 남의 알림은 볼 수 없다. */
    #[DataProvider('connectionProvider')]
    public function testAnotherMembersNotificationIsNotReachable(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);
        $this->commentAsGuest($app, $postId, '남의 알림이 될 댓글');
        $id = $this->firstNotificationId($app, $postId);

        $this->logout($app);
        $this->loginAs($app, 'other@example.com', '다른 회원');

        self::assertSame(404, $this->get($app, '/notifications/' . $id)->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotSeeTheNotificationBox(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->logout($app);

        $this->assertLoginRedirect($this->get($app, '/notifications'), '/notifications');
        self::assertStringNotContainsString(
            '/notifications',
            $this->body($this->get($app, '/')),
            '게스트 머리글에는 알림 링크가 없어야 한다'
        );
    }

    #[DataProvider('connectionProvider')]
    public function testMarkAllReadClearsTheBadge(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);
        $this->commentAsGuest($app, $postId, '첫 댓글');
        $this->commentAsGuest($app, $postId, '둘째 댓글');

        $this->get($app, '/notifications');
        $response = $this->post($app, '/notifications/read-all', ['csrf_token' => $_SESSION['csrf_token'] ?? '']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringNotContainsString('bell-dot', $this->body($this->get($app, '/notifications')));
    }

    #[DataProvider('connectionProvider')]
    public function testMarkAllReadNeedsTheCsrfToken(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $postId = $this->seedPostByLoggedInMember($app);
        $this->commentAsGuest($app, $postId, '지워지면 안 되는 알림');
        $this->get($app, '/notifications');

        self::assertSame(403, $this->post($app, '/notifications/read-all', ['csrf_token' => 'wrong'])->getStatusCode());
        self::assertStringContainsString('bell-dot', $this->body($this->get($app, '/notifications')));
    }

    /** 기존 설치에 알림 표가 없어도 화면이 멈추면 안 된다. */
    #[DataProvider('connectionProvider')]
    public function testPagesStillRenderWithoutTheNotificationsTable(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유게시판']);
        $app->db()->execute('DROP TABLE ' . $app->db()->q('notifications'));
        $app->db()->execute(
            'UPDATE ' . $app->db()->q('site_settings') . ' SET setting_value = ? WHERE setting_key = ?',
            ['2', 'system.schema_version']
        );

        // 부팅할 때 스스로 표를 다시 만든다.
        self::assertSame(200, $this->get($app, '/boards/free')->getStatusCode());
        self::assertSame(0, (int) $app->db()->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $app->db()->q('notifications')
        )['c'], '표가 되살아났으면 이 질의가 성공한다');
    }

    private function firstNotificationId(App $app, int $postId): int
    {
        return (int) $app->db()->selectOne(
            'SELECT id FROM ' . $app->db()->q('notifications') . ' WHERE post_id = ? ORDER BY id DESC',
            [$postId]
        )['id'];
    }

    private function commentAsGuest(App $app, int $postId, string $content): void
    {
        // $_SESSION 을 비워도 다음 요청에서 session_start() 가 저장소에서 되살린다.
        // 정말로 로그아웃하려면 로그아웃 경로를 거쳐야 한다.
        $this->logout($app);
        $this->get($app, '/posts/' . $postId);
        $this->post($app, '/posts/' . $postId . '/comments', [
            'csrf_token'  => $_SESSION['csrf_token'] ?? '',
            'author_name' => '손님',
            'password'    => 'guest-pass-1',
            'content'     => $content,
        ]);
        $this->loginAs($app, 'writer@example.com', '글쓴이');
    }

    private function logout(App $app): void
    {
        $this->get($app, '/');
        $this->post($app, '/logout', ['csrf_token' => $_SESSION['csrf_token'] ?? '']);
    }

    /** 로그인한 회원이 글 하나를 남긴 상태를 만든다. 세션은 그대로 로그인 상태로 둔다. */
    private function seedPostByLoggedInMember(App $app): int
    {
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유게시판', 'perm_comment' => 'guest',
        ]);
        $this->loginAs($app, 'writer@example.com', '글쓴이');

        $this->get($app, '/boards/free/new');
        $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'title'      => '알림이 붙을 글',
            'content'    => '본문입니다.',
        ]);

        return (int) $app->db()->selectOne('SELECT MAX(id) AS id FROM ' . $app->db()->q('posts'))['id'];
    }

    private function loginAs(App $app, string $email, string $name): void
    {
        if ($app->users()->findByEmail($email) === null) {
            $id = $app->users()->create($email, password_hash('member-password-1', PASSWORD_DEFAULT), $name, false);
            $app->users()->verifyEmail($id);
        }

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
            'email'      => $email,
            'password'   => 'member-password-1',
        ]);
    }
}
