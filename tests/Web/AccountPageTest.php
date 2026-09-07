<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Auth\Acl;
use GnuCms\Auth\Identity;
use GnuCms\Error\DomainError;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AccountPageTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenAccountPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect($this->get($app, '/account'), '/account');
    }

    #[DataProvider('connectionProvider')]
    public function testMemberEditsNameAndPasswordAndKeepsSession(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('me@example.com', password_hash('old-password-123', PASSWORD_DEFAULT), '나', false);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'me@example.com', 'password' => 'old-password-123',
        ]);

        $form = $this->body($this->get($app, '/account'));
        self::assertStringContainsString('회원정보 수정', $form);
        self::assertStringContainsString('me@example.com', $form);
        self::assertStringContainsString('name="current_password"', $form);
        self::assertStringContainsString('name="profile_image"', $form);

        // 이름만 바꾼다. 비밀번호 칸은 비워 둔다.
        $renamed = $this->post($app, '/account', [
            'csrf_token' => $_SESSION['csrf_token'], 'display_name' => '새이름',
            'current_password' => '', 'password' => '', 'password_confirmation' => '',
        ]);
        self::assertSame(303, $renamed->getStatusCode(), $this->body($renamed));
        self::assertSame('새이름', $app->users()->findById($id)['display_name']);
        self::assertStringContainsString('새이름', $this->body($this->get($app, '/')), '머리글의 이름이 바뀌어야 한다');

        // 현재 비밀번호가 틀리면 막힌다.
        $wrong = $this->post($app, '/account', [
            'csrf_token' => $_SESSION['csrf_token'], 'display_name' => '새이름',
            'current_password' => 'nope', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ]);
        self::assertSame(422, $wrong->getStatusCode());
        self::assertStringContainsString('현재 비밀번호가 올바르지 않습니다', $this->body($wrong));

        // 맞으면 바뀌고, 지금 세션은 살아 있다.
        $changed = $this->post($app, '/account', [
            'csrf_token' => $_SESSION['csrf_token'], 'display_name' => '새이름',
            'current_password' => 'old-password-123', 'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ]);
        self::assertSame(303, $changed->getStatusCode(), $this->body($changed));
        self::assertTrue(password_verify('new-password-456', (string) $app->users()->findById($id)['password_hash']));
        self::assertSame(200, $this->get($app, '/account')->getStatusCode(), '비밀번호를 바꿔도 지금 세션은 살아 있어야 한다');
    }

    #[DataProvider('connectionProvider')]
    public function testSocialOnlyAccountDoesNotShowPasswordChangeFields(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->createSocial('social@example.com', '소셜회원');
        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();

        $form = $this->body($this->get($app, '/account'));
        self::assertStringContainsString('표시 이름을 바꿉니다.', $form);
        self::assertStringNotContainsString('비밀번호 바꾸기', $form);
        self::assertStringNotContainsString('name="current_password"', $form);
        self::assertStringNotContainsString('name="password"', $form);
        self::assertStringNotContainsString('name="password_confirmation"', $form);

        $renamed = $this->post($app, '/account', [
            'csrf_token' => $_SESSION['csrf_token'], 'display_name' => '새소셜회원',
        ]);
        self::assertSame(303, $renamed->getStatusCode(), $this->body($renamed));
        self::assertSame('새소셜회원', $app->users()->findById($id)['display_name']);
    }

    #[DataProvider('connectionProvider')]
    public function testPasswordMemberWithdrawsAndEmailCanBeReused(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->users()->create('owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '관리자', true);
        $id = $app->users()->create('leave@example.com', password_hash('leave-password-123', PASSWORD_DEFAULT), '떠날회원');
        $app->users()->verifyEmail($id);
        $app->identities()->attach($id, 'google', 'old-google-uid');
        $postId = $app->posts()->create([
            'board_id' => 1, 'title' => '남길 글', 'content' => '본문', 'author_id' => (string) $id,
            'author_name' => '떠날회원', 'author_ip' => '198.51.100.20',
        ]);
        $commentId = $app->comments()->create([
            'board_id' => 1, 'post_id' => $postId, 'content' => '남길 댓글',
            'author_id' => (string) $id, 'author_name' => '떠날회원', 'author_ip' => '198.51.100.21',
        ]);
        $app->loginEvents()->record($id, 'leave@example.com', 'password', 'success', '198.51.100.22', 'Test');

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'leave@example.com',
            'password' => 'leave-password-123',
        ]);
        $page = $this->body($this->get($app, '/account'));
        self::assertStringContainsString('회원 탈퇴', $page);
        self::assertStringContainsString('작성한 글과 댓글은 삭제되지 않고', $page);

        $wrong = $this->post($app, '/account/withdraw', [
            'csrf_token' => $_SESSION['csrf_token'], 'withdraw_current_password' => 'wrong',
            'confirm_withdrawal' => '1',
        ]);
        self::assertSame(422, $wrong->getStatusCode());

        $withdrawn = $this->post($app, '/account/withdraw', [
            'csrf_token' => $_SESSION['csrf_token'], 'withdraw_current_password' => 'leave-password-123',
            'confirm_withdrawal' => '1',
        ], ['REMOTE_ADDR' => '203.0.113.30']);
        self::assertSame(303, $withdrawn->getStatusCode(), $this->body($withdrawn));
        self::assertSame('/account/withdrawn', $withdrawn->getHeaderLine('Location'));

        $old = $app->users()->findById($id);
        self::assertSame('withdrawn', $old['status']);
        self::assertSame('203.0.113.30', $old['withdrawn_ip']);
        self::assertNotNull($old['withdrawn_at']);
        self::assertNull($old['password_hash']);
        self::assertNotSame('leave@example.com', $old['email']);
        self::assertSame(0, $app->identities()->countForUser($id));
        self::assertSame('탈퇴한 회원', $app->posts()->find($postId)['author_name']);
        self::assertNull($app->posts()->find($postId)['author_ip']);
        self::assertSame('탈퇴한 회원', $app->comments()->find($commentId)['author_name']);
        self::assertNull($app->comments()->find($commentId)['author_ip']);
        $event = $app->db()->selectOne(
            'SELECT login_identifier, client_ip FROM login_events WHERE user_id = ? ORDER BY id ASC LIMIT 1', [$id]
        );
        self::assertNull($event['login_identifier']);
        self::assertSame('198.51.100.22', $event['client_ip']);
        $this->assertLoginRedirect($this->get($app, '/account'), '/account');

        $newId = $app->users()->create('leave@example.com', password_hash('new-password-123', PASSWORD_DEFAULT), '새회원');
        $app->identities()->attach($newId, 'google', 'old-google-uid');
        self::assertNotSame($id, $newId);
        self::assertSame(1, $app->identities()->countForUser($newId));
    }

    #[DataProvider('connectionProvider')]
    public function testLastAdminCannotWithdraw(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'owner@example.com',
            'password' => 'owner-password-123',
        ]);
        $response = $this->post($app, '/account/withdraw', [
            'csrf_token' => $_SESSION['csrf_token'], 'withdraw_current_password' => 'owner-password-123',
            'confirm_withdrawal' => '1',
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('마지막 관리자는 탈퇴할 수 없습니다', $this->body($response));
        self::assertSame('active', $app->users()->findById($id)['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testProfileMenuLinksToAccountNotTerms(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->createPage([
            'slug' => 'service', 'title' => '이용약관', 'content' => '본문', 'seo_description' => null,
            'status' => 'published', 'show_in_menu' => 1, 'sort_order' => 0, 'is_consent' => 1,
        ]);
        $id = $app->users()->create('me@example.com', password_hash('old-password-123', PASSWORD_DEFAULT), '나', false);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'me@example.com', 'password' => 'old-password-123',
        ]);
        $body = $this->body($this->get($app, '/'));
        self::assertMatchesRegularExpression('#class="[^"]*user-menu"[^>]*>(?:(?!</ul>).)*/account#s', $body, '프로필 메뉴에 회원정보 수정이 있어야 한다');
        self::assertDoesNotMatchRegularExpression('#class="[^"]*user-menu"[^>]*>(?:(?!</ul>).)*/terms/#s', $body, '프로필 메뉴에 약관이 있으면 안 된다');
        self::assertStringContainsString('/terms/service', $body, '약관은 하단에는 그대로 있어야 한다');
    }
    /** 표시 이름은 겹치지 않는다. 가입 때 자동으로 지은 이름이 겹치면 숫자를 붙이고, 직접 고를 때 겹치면 막는다. */
    #[DataProvider('connectionProvider')]
    public function testDisplayNamesAreUnique(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $users = $app->users();
        $a = $users->createRegistered('kagla@a.example', password_hash('x-password-123', PASSWORD_DEFAULT), 'kagla');
        $b = $users->createRegistered('kagla@b.example', password_hash('x-password-123', PASSWORD_DEFAULT), 'kagla');
        $c = $users->createSocial('kagla@c.example', 'Kagla');
        self::assertSame('kagla', $users->findById($a)['display_name']);
        self::assertSame('kagla2', $users->findById($b)['display_name']);
        self::assertSame('Kagla3', $users->findById($c)['display_name'], '대소문자만 다른 이름도 겹친 것으로 본다');

        try {
            $app->accountService()->updateProfile($b, ['display_name' => 'KAGLA']);
            self::fail('남이 쓰는 이름으로는 못 바꾼다');
        } catch (DomainError $e) {
            self::assertArrayHasKey('display_name', $e->details());
        }
        // 한글 2자·영문 4자 미만은 안 된다. 자동 이름이 짧으면 '회원' 으로 대신한다.
        // 공백·기호는 안 된다.
        foreach (['홍 길동', 'kagla!', '홍길동_', 'kim lee', '홍길동.'] as $bad) {
            try {
                $app->accountService()->updateProfile($b, ['display_name' => $bad]);
                self::fail($bad . ' 은 막아야 한다');
            } catch (DomainError $e) {
                self::assertArrayHasKey('display_name', $e->details(), $bad);
            }
        }
        // 자동 이름은 허용되지 않는 글자를 걷어 낸다.
        $e = $users->createRegistered('kim.lee@e.example', password_hash('x-password-123', PASSWORD_DEFAULT), 'kim.lee');
        self::assertSame('kimlee', $users->findById($e)['display_name']);
        $f = $users->createSocial('hong@f.example', '홍 길동');
        self::assertSame('홍길동', $users->findById($f)['display_name']);
        foreach (['가', 'ab', 'kim', '김a'] as $short) {
            try {
                $app->accountService()->updateProfile($b, ['display_name' => $short]);
                self::fail($short . ' 은 너무 짧아 막아야 한다');
            } catch (DomainError $e) {
                self::assertArrayHasKey('display_name', $e->details(), $short);
            }
        }
        foreach (['홍길', 'abcd', '김ab'] as $ok) {
            $app->accountService()->updateProfile($b, ['display_name' => $ok]);
            self::assertSame($ok, $users->findById($b)['display_name']);
        }
        $app->accountService()->updateProfile($b, ['display_name' => 'kagla2']);
        $d = $users->createRegistered('kim@d.example', password_hash('x-password-123', PASSWORD_DEFAULT), 'kim');
        self::assertSame('회원', $users->findById($d)['display_name'], '영문 3자 자동 이름은 회원으로 대신한다');

        // 자기 이름을 그대로 두는 것은 겹침이 아니다.
        $app->accountService()->updateProfile($b, ['display_name' => 'kagla2']);
        self::assertSame('kagla2', $users->findById($b)['display_name']);

        try {
            $app->adminService()->updateMember(new Acl(Identity::user('1', '관리자', true)), $c, [
                'email' => 'kagla@c.example', 'display_name' => 'kagla', 'status' => 'active',
            ]);
            self::fail('관리자도 남이 쓰는 이름으로는 못 바꾼다');
        } catch (DomainError $e) {
            self::assertArrayHasKey('display_name', $e->details());
        }
    }

}
