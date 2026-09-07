<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\UploadedFile;

final class AdminPageTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenAdminPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        $this->assertLoginRedirect($this->get($app, '/admin'), '/admin');
        $this->assertLoginRedirect($this->get($app, '/admin/boards'), '/admin/boards');
        $this->assertLoginRedirect($this->get($app, '/admin/login-history'), '/admin/login-history');
    }

    #[DataProvider('connectionProvider')]
    public function testOnlyAdminCanReviewAndManageAllLoginHistory(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $memberId = $app->users()->create(
            'member@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '일반회원'
        );
        $otherId = $app->users()->create(
            'other@example.com', password_hash('other-password-123', PASSWORD_DEFAULT), '다른회원'
        );
        $app->loginEvents()->record(
            $memberId, 'member@example.com', 'password', 'failure', '198.51.100.9', 'Test Browser'
        );
        $app->loginEvents()->record(
            $memberId, 'member@example.com', 'google', 'success', '203.0.113.8', 'Mobile Test'
        );
        $app->loginEvents()->record(
            $otherId, 'other@example.com', 'password', 'success', '192.0.2.4', 'Other Browser'
        );

        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $memberId;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
        self::assertSame(403, $this->get($app, '/admin/login-history')->getStatusCode());
        self::assertStringNotContainsString('/admin/login-history', $this->body($this->get($app, '/')));

        session_start();
        $_SESSION['user_id'] = $adminId;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
        $response = $this->get($app, '/admin/login-history');
        $body = $this->body($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1>로그인 기록</h1>', $body);
        self::assertStringContainsString('class="admin-body login-history-body"', $body);
        self::assertStringContainsString('<details class="card history-maintenance">', $body);
        self::assertStringContainsString('href="/admin/login-history" class="menu-active"', $body);
        self::assertStringContainsString('일반회원', $body);
        self::assertStringContainsString('Google', $body);
        self::assertStringContainsString('>성공</span>', $body);
        self::assertStringContainsString('>실패</span>', $body);
        self::assertStringContainsString('203.0.113.8', $body);
        self::assertStringContainsString('Mobile Test', $body);
        self::assertLessThan(strpos($body, '198.51.100.9'), strpos($body, '203.0.113.8'));
        self::assertStringContainsString('href="/admin/login-history?member=' . $memberId . '&amp;q=', $body);
        self::assertStringContainsString(
            'href="/admin/login-history?ip=203.0.113.8&amp;q=203.0.113.8"', $body
        );

        $memberFiltered = $this->body($this->get($app, '/admin/login-history', [
            'member' => $memberId, 'q' => '일반회원',
        ]));
        self::assertStringContainsString('선택한 회원', $memberFiltered);
        self::assertStringContainsString('name="q" value="일반회원"', $memberFiltered);
        self::assertStringContainsString('Mobile Test', $memberFiltered);
        self::assertStringNotContainsString('Other Browser', $memberFiltered);
        $ipFiltered = $this->body($this->get($app, '/admin/login-history', [
            'ip' => '203.0.113.8', 'q' => '203.0.113.8',
        ]));
        self::assertStringContainsString('IP <code>203.0.113.8</code>', $ipFiltered);
        self::assertStringContainsString('name="q" value="203.0.113.8"', $ipFiltered);
        self::assertStringContainsString('Mobile Test', $ipFiltered);
        self::assertStringNotContainsString('Test Browser', $ipFiltered);
        $searched = $this->body($this->get($app, '/admin/login-history', ['q' => 'other@example.com']));
        self::assertStringContainsString('“other@example.com” 검색 결과', $searched);
        self::assertStringContainsString('Other Browser', $searched);
        self::assertStringNotContainsString('Mobile Test', $searched);
        self::assertStringContainsString('Mobile Test', $this->body(
            $this->get($app, '/admin/login-history', ['q' => 'mobile test'])
        ));

        for ($i = 0; $i < 51; $i++) {
            $app->loginEvents()->record(
                $adminId, 'admin@example.com', 'password', 'success', '203.0.113.10', 'Page Test ' . $i
            );
        }
        $firstPage = $this->body($this->get($app, '/admin/login-history'));
        self::assertStringContainsString('총 54건', $firstPage);
        self::assertStringContainsString('href="/admin/login-history?page=2"', $firstPage);
        self::assertStringContainsString('Other Browser', $this->body(
            $this->get($app, '/admin/login-history', ['page' => 2])
        ));
        self::assertStringContainsString(
            'href="/admin/login-history?ip=203.0.113.10&amp;page=2"',
            $this->body($this->get($app, '/admin/login-history', ['ip' => '203.0.113.10']))
        );
        self::assertStringContainsString(
            'href="/admin/login-history?q=Page%20Test&amp;page=2"',
            $this->body($this->get($app, '/admin/login-history', ['q' => 'Page Test']))
        );

        foreach ([
            ['198.51.100.20', 'Old Event', '2024-12-31 23:59:59'],
            ['198.51.100.21', 'Boundary Event', '2025-01-01 00:00:00'],
        ] as $event) {
            $app->db()->insert('login_events', [
                'user_id' => $memberId, 'login_identifier' => 'member@example.com',
                'auth_method' => 'password', 'result' => 'success', 'client_ip' => $event[0],
                'user_agent' => $event[1], 'created_at' => $event[2],
            ]);
        }
        $deleted = $this->post($app, '/admin/login-history/delete', [
            'csrf_token' => $_SESSION['csrf_token'], 'before' => '2025-01-01',
        ]);
        self::assertSame(303, $deleted->getStatusCode());
        self::assertSame('/admin/login-history?deleted=1', $deleted->getHeaderLine('Location'));
        self::assertSame(0, (int) $app->db()->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $app->db()->table('login_events') . ' WHERE user_agent = ?', ['Old Event']
        )['c']);
        self::assertSame(1, (int) $app->db()->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $app->db()->table('login_events') . ' WHERE user_agent = ?', ['Boundary Event']
        )['c']);
        self::assertStringContainsString('1건을 삭제했습니다', $this->body(
            $this->get($app, '/admin/login-history', ['deleted' => 1])
        ));

        $invalidDelete = $this->post($app, '/admin/login-history/delete', [
            'csrf_token' => $_SESSION['csrf_token'], 'before' => '2999-01-01',
        ]);
        self::assertSame(422, $invalidDelete->getStatusCode());
        $invalidBody = $this->body($invalidDelete);
        self::assertStringContainsString('오늘 또는 그 이전 날짜', $invalidBody);
        self::assertStringContainsString('<details class="card history-maintenance" open>', $invalidBody);

        $site = $this->body($this->get($app, '/'));
        self::assertMatchesRegularExpression(
            '#href="/admin/login-history"[^>]*>.*?로그인 기록</a>\s*</li>\s*<li>\s*<form[^>]+action="/logout"#s',
            $site,
            '관리자 프로필 메뉴에서 로그인 기록이 로그아웃 바로 위에 있어야 한다'
        );
    }

    #[DataProvider('connectionProvider')]
    public function testAdminEmailLogsInAndRendersDashboard(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['theme' => 'modern']);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);

        $this->get($app, '/login');
        $login = $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);
        $dashboard = $this->get($app, '/admin');

        self::assertSame(303, $login->getStatusCode());
        self::assertSame(200, $dashboard->getStatusCode());
        self::assertStringContainsString('class="admin-page"', $this->body($dashboard));
        self::assertStringNotContainsString('<body class="theme-modern">', $this->body($dashboard));
        // 기본 테마가 claude-sky(하늘빛)로 바뀌며 대시보드 제목이 인사말이 됐다.
        self::assertStringContainsString('님, 오늘도 반가워요</h1>', $this->body($dashboard));
        self::assertStringNotContainsString('<header class="site-header">', $this->body($dashboard));
        self::assertStringContainsString('class="admin-sidebar"', $this->body($dashboard));
        self::assertStringContainsString('>관리 콘솔</strong>', $this->body($dashboard));
        self::assertStringContainsString('admin-fold', $this->body($dashboard));
        self::assertStringContainsString('for="admin-drawer"', $this->body($dashboard));
        self::assertStringContainsString('class="admin-user"', $this->body($dashboard));
        self::assertStringContainsString('class="admin-version"', $this->body($dashboard));
        $released = preg_match('/^\d+\.\d+\.\d+$/D', GNUCMS_VERSION) === 1;
        $versionUrl = GNUCMS_REPOSITORY_URL . ($released ? '/releases/tag/v' . GNUCMS_VERSION : '');
        self::assertStringContainsString('href="' . $versionUrl . '"', $this->body($dashboard));
        self::assertStringContainsString('>v' . GNUCMS_VERSION . '</small>',
            $this->body($dashboard));
        self::assertMatchesRegularExpression(
            '#<ul class="[^"]*admin-user-menu[^"]*"[^>]*>\s*'
            . '<li class="menu-title">.*?</li>\s*<li>\s*<form[^>]+action="/logout"#s',
            $this->body($dashboard),
            '관리자 프로필 메뉴에는 이름과 로그아웃만 표시한다'
        );
        self::assertStringContainsString('href="/admin" class="menu-active"', $this->body($dashboard));
        self::assertStringContainsString('회원 관리', $this->body($dashboard));
        self::assertStringNotContainsString('title="메일 설정"', $this->body($dashboard));

        $mail = $this->get($app, '/admin/mail');
        self::assertSame(200, $mail->getStatusCode());
        self::assertStringContainsString('href="/admin/settings" class="menu-active"', $this->body($mail));
        self::assertStringContainsString('class="tab tab-active" aria-current="page" href="/admin/mail"', $this->body($mail));
        self::assertStringContainsString('smtp.gmail.com', $this->body($mail));
        $saved = $this->post($app, '/admin/mail', [
            'csrf_token' => $_SESSION['csrf_token'], 'enabled' => '1', 'provider' => 'gmail',
            'host' => 'smtp.gmail.com', 'port' => '465', 'encryption' => 'ssl',
            'username' => 'owner@gmail.com', 'password' => 'google-app-password',
            'from_email' => 'owner@gmail.com', 'from_name' => GNUCMS,
        ]);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame('/admin/mail?saved=1', $saved->getHeaderLine('Location'));
        $savedMail = $this->body($this->get($app, '/admin/mail'));
        self::assertStringNotContainsString('google-app-password', $savedMail);
        self::assertStringContainsString('placeholder="••••••••••••••••"', $savedMail);
        self::assertStringContainsString('data-mail-password-toggle', $savedMail);
        $revealed = $this->post($app, '/admin/mail/password', ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $revealed->getStatusCode());
        self::assertSame('no-store', $revealed->getHeaderLine('Cache-Control'));
        self::assertSame(['password' => 'google-app-password'], json_decode($this->body($revealed), true));
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanCreateBoardFromWebForm(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);

        $boardsPage = $this->get($app, '/admin/boards');
        self::assertSame(200, $boardsPage->getStatusCode());
        self::assertStringContainsString('<h1>게시판 관리</h1>', $this->body($boardsPage));
        self::assertStringContainsString('href="/admin/boards" class="menu-active"', $this->body($boardsPage));
        self::assertStringContainsString('/admin/boards/new', $this->body($boardsPage));

        $response = $this->post($app, '/admin/boards/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'board_key' => 'notice', 'name' => '공지사항', 'description' => '중요한 소식',
            'categories_text' => "안내, 업데이트\n새 소식", 'managers_text' => '',
            'perm_read' => 'guest', 'perm_write' => 'admin', 'perm_comment' => 'member',
            'per_page' => '20', 'sort_order' => '-10', 'use_category' => '1', 'show_in_header' => '1',
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/boards?saved=1', $response->getHeaderLine('Location'));
        $board = $app->boards()->findByKey('notice');
        self::assertSame('공지사항', $board['name']);
        self::assertSame(['안내, 업데이트', '새 소식'], $board['categories']);
        self::assertSame('admin', $board['perm_write']);
        self::assertSame(1, $board['show_in_header']);
        self::assertStringContainsString('href="/boards/notice">공지사항</a>', $this->body($this->get($app, '/')));
        $boardPage = $this->body($this->get($app, '/boards/notice'));
        self::assertMatchesRegularExpression('#<nav class="tabs tabs-border"[^>]*>(.*?)</nav>#s', $boardPage);
        preg_match('#<nav class="tabs tabs-border"[^>]*>(.*?)</nav>#s', $boardPage, $headerTabs);
        self::assertSame(1, substr_count($headerTabs[1] ?? '', 'href="/boards/notice"'),
            '현재 게시판은 활성 탭으로만 한 번 나와야 한다');
        self::assertStringContainsString('class="tab tab-active" href="/boards/notice" aria-current="page"',
            $headerTabs[1] ?? '');

        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'gallery', 'name' => '갤러리', 'show_in_header' => '1', 'sort_order' => '20',
        ]);
        $galleryPage = $this->body($this->get($app, '/boards/gallery'));
        preg_match('#<nav class="tabs tabs-border"[^>]*>(.*?)</nav>#s', $galleryPage, $galleryTabs);
        self::assertMatchesRegularExpression(
            '#href="/boards/notice"[^>]*>공지사항</a>.*class="tab tab-active" href="/boards/gallery" aria-current="page">갤러리</a>#s',
            $galleryTabs[1] ?? '',
            '선택된 게시판도 관리자가 정한 원래 순서에 있어야 한다'
        );
        $savedPage = $this->get($app, '/admin/boards?saved=1');
        self::assertStringContainsString('공지사항', $this->body($savedPage));
        self::assertStringContainsString('상단 메뉴', $this->body($savedPage));
        self::assertStringContainsString('게시판 설정을 저장했습니다.', $this->body($savedPage));
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanEditMemberInformation(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com',
            password_hash('admin-password-123', PASSWORD_DEFAULT),
            '관리자',
            true
        );
        $app->users()->verifyEmail($adminId);
        $memberId = $app->users()->create(
            'member@example.com',
            password_hash('member-password-123', PASSWORD_DEFAULT),
            'member',
            false
        );
        $app->users()->verifyEmail($memberId);

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $members = $this->get($app, '/admin/members');
        self::assertSame(200, $members->getStatusCode());
        self::assertStringContainsString('/admin/members/' . $memberId . '/edit', $this->body($members));
        $form = $this->get($app, '/admin/members/' . $memberId . '/edit');
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString('>회원 수정</h1>', $this->body($form));
        self::assertStringContainsString('이메일·비밀번호', $this->body($form));

        $saved = $this->post($app, '/admin/members/' . $memberId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'new-member@example.com',
            'display_name' => '새표시이름',
            'status' => 'blocked',
        ]);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame('/admin/members?saved=1', $saved->getHeaderLine('Location'));
        $updated = $app->users()->findById($memberId);
        self::assertSame('new-member@example.com', $updated['email']);
        self::assertSame('새표시이름', $updated['display_name']);
        self::assertSame('blocked', $updated['status']);

        $blockedOwner = $this->post($app, '/admin/members/' . $adminId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'admin@example.com',
            'display_name' => '관리자',
            'status' => 'blocked',
        ]);
        self::assertSame(422, $blockedOwner->getStatusCode());
        self::assertStringContainsString('현재 로그인한 관리자 계정은 차단할 수 없습니다.', $this->body($blockedOwner));
        self::assertSame('active', $app->users()->findById($adminId)['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanUploadAndRemoveMemberProfileImage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $memberId = $app->users()->create(
            'member@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '일반회원'
        );
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $form = $this->body($this->get($app, '/admin/members/' . $memberId . '/edit'));
        self::assertStringContainsString('enctype="multipart/form-data"', $form);
        self::assertStringContainsString('name="profile_image"', $form);

        $temporary = tempnam(sys_get_temp_dir(), GNUCMS_ID . '-admin-avatar-');
        file_put_contents($temporary, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));
        $size = filesize($temporary);
        $saved = $this->postWithFiles($app, '/admin/members/' . $memberId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com',
            'display_name' => '일반회원', 'status' => 'active',
        ], ['profile_image' => new UploadedFile($temporary, 'profile.png', 'image/png', $size ?: null)]);

        self::assertSame(303, $saved->getStatusCode(), $this->body($saved));
        $member = $app->users()->findById($memberId);
        $avatar = $member['avatar_file'];
        self::assertNotEmpty($avatar);
        self::assertSame('upload', $member['avatar_source']);
        self::assertFileExists($app->avatars()->image((string) $avatar)['path']);
        $savedForm = $this->body($this->get($app, '/admin/members/' . $memberId . '/edit'));
        self::assertStringContainsString('name="remove_profile_image"', $savedForm);
        self::assertStringContainsString('/media/avatars/' . $avatar, $savedForm);

        $removed = $this->post($app, '/admin/members/' . $memberId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com',
            'display_name' => '일반회원', 'status' => 'active', 'remove_profile_image' => '1',
        ]);
        self::assertSame(303, $removed->getStatusCode(), $this->body($removed));
        $member = $app->users()->findById($memberId);
        self::assertNull($member['avatar_file']);
        self::assertNull($member['avatar_source']);
    }

    #[DataProvider('connectionProvider')]
    public function testMemberFormShowsLinkedSocialLoginProvider(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $app->users()->verifyEmail($adminId);
        $memberId = $app->users()->createSocial('social@example.com', '소셜회원');
        $app->identities()->attach($memberId, 'google', 'google-user-8');
        $app->loginEvents()->record(
            $memberId, 'social@example.com', 'google', 'success', '203.0.113.81', 'Login History Test'
        );

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $body = $this->body($this->get($app, '/admin/members/' . $memberId . '/edit'));
        self::assertStringContainsString('로그인 방식', $body);
        self::assertStringContainsString('Google 소셜 로그인', $body);
        self::assertStringContainsString('social-provider-badge social-google', $body);
        self::assertStringNotContainsString('>이메일·비밀번호</span>', $body);
        self::assertStringContainsString('최근 로그인 이력', $body);
        self::assertStringContainsString('203.0.113.81', $body);
        self::assertStringContainsString('Login History Test', $body);
        self::assertStringContainsString('>성공</span>', $body);
    }

    /** 회원 수정 화면에 가입 동의 내역이 붙는다. 동의하지 않은 항목도 함께 나온다. */
    #[DataProvider('connectionProvider')]
    public function testMemberFormShowsConsentHistory(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com',
            password_hash('admin-password-123', PASSWORD_DEFAULT),
            '관리자',
            true
        );
        $app->users()->verifyEmail($adminId);
        // 새 동의 저장소는 문서에 붙은 필드 대신 consent_uses 붙임으로 자리를 표현한다.
        foreach ([['terms', '이용약관', true], ['marketing', '마케팅 정보 수신', false]] as $doc) {
            $id = $app->cms()->createPage([
                'slug' => $doc[0], 'title' => $doc[1], 'content' => $doc[1] . ' 본문',
                'seo_description' => null, 'status' => 'published', 'show_in_menu' => 0,
                'sort_order' => 0, 'is_consent' => 1,
            ]);
            $app->consentUses()->attach('signup', $id, $doc[2], 0);
        }
        $memberId = $app->users()->create(
            'member@example.com',
            password_hash('member-password-123', PASSWORD_DEFAULT),
            'member',
            false
        );
        $app->users()->verifyEmail($memberId);
        $app->consents()->record('user', $memberId, 'signup', $app->cms()->findBySlug('terms'), true, null);
        $app->consents()->record('user', $memberId, 'signup', $app->cms()->findBySlug('marketing'), false, null);

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $body = $this->body($this->get($app, '/admin/members/' . $memberId . '/edit'));
        self::assertStringContainsString('가입 동의 내역', $body);
        self::assertStringContainsString('이용약관', $body);
        self::assertStringContainsString('마케팅 정보 수신', $body);
        self::assertStringContainsString('안 함', $body);
    }
    /** 관리자 자신의 비밀번호도 회원 수정에서 바꾼다. 바꾼 뒤에도 지금 세션은 살아 있어야 한다. */
    #[DataProvider('connectionProvider')]
    public function testAdminChangesOwnPasswordFromMemberForm(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com',
            password_hash('admin-password-123', PASSWORD_DEFAULT),
            '관리자',
            true
        );
        $app->users()->verifyEmail($adminId);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'],
            'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        // 비워 두면 비밀번호는 그대로다.
        $kept = $this->post($app, '/admin/members/' . $adminId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'display_name' => '관리자', 'status' => 'active', 'password' => '', 'password_confirmation' => '',
        ]);
        self::assertSame(303, $kept->getStatusCode());
        self::assertTrue(password_verify('admin-password-123', (string) $app->users()->findById($adminId)['password_hash']));

        // 확인 칸이 다르면 막힌다.
        $mismatch = $this->post($app, '/admin/members/' . $adminId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'display_name' => '관리자', 'status' => 'active',
            'password' => 'new-password-456', 'password_confirmation' => 'other',
        ]);
        self::assertSame(422, $mismatch->getStatusCode());
        self::assertStringContainsString('비밀번호가 일치하지 않습니다', $this->body($mismatch));

        // 제대로 바꾸면 새 비밀번호가 들어가고, 지금 세션은 끊기지 않는다.
        $changed = $this->post($app, '/admin/members/' . $adminId . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'display_name' => '관리자', 'status' => 'active',
            'password' => 'new-password-456', 'password_confirmation' => 'new-password-456',
        ]);
        self::assertSame(303, $changed->getStatusCode(), $this->body($changed));
        self::assertTrue(password_verify('new-password-456', (string) $app->users()->findById($adminId)['password_hash']));
        self::assertSame(200, $this->get($app, '/admin')->getStatusCode(), '내 비밀번호를 바꿔도 지금 세션은 살아 있어야 한다');

        // 옛 화면은 없다.
        self::assertSame(404, $this->get($app, '/admin/password')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsPageShowsSchemaStatus(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);

        $settings = $this->body($this->get($app, '/admin/settings'));
        self::assertStringNotContainsString('판 번호', $settings);
        $body = $this->body($this->get($app, '/admin/settings/maintenance'));

        self::assertStringContainsString('데이터베이스 상태', $body);
        self::assertStringContainsString('<dt>판 번호</dt><dd>' . \GnuCms\Db\Schema::VERSION . ' ', $body);
        self::assertStringContainsString('설치 이후 없음', $body);
    }

    /** 옮긴 시각·백업 목록·비SQLite 안내, 세 갈래를 모두 확인한다. */
    #[DataProvider('connectionProvider')]
    public function testSettingsPageShowsSchemaBackupsAndUpgradedAt(array $dbConfig): void
    {
        $tmp = sys_get_temp_dir() . '/' . GNUCMS_ID . '-settings-' . bin2hex(random_bytes(4));
        $backupsDir = $tmp . '/backups';
        $older = $backupsDir . '/board-v8-20260101-000000.sqlite';
        $newer = $backupsDir . '/board-v9-20260201-000000.sqlite';

        try {
            mkdir($backupsDir, 0775, true);
            file_put_contents($older, 'x');
            touch($newer, (int) strtotime('2026-02-01 00:00:05 UTC'));

            $app = $this->makeApp($dbConfig, ['storage' => ['dir' => $tmp]]);
            $app->db()->execute(
                'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                ['system.schema_upgraded_at', '2026-08-30 01:02:03', '2026-08-30 01:02:03']
            );
            $app->db()->execute(
                'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
                ['system.schema_backup', '/x/storage/backups/board-v9-20260201-000000.sqlite', '2026-08-30 01:02:03']
            );
            $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
            $app->users()->verifyEmail($id);
            $this->get($app, '/login');
            $this->post($app, '/login', [
                'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
            ]);

            $body = $this->body($this->get($app, '/admin/settings/maintenance'));

            self::assertStringContainsString('2026-08-30 10:02:03 Asia/Seoul', $body);
            self::assertStringContainsString('<dt>마지막 백업</dt><dd>board-v9-20260201-000000.sqlite</dd>', $body);

            if ($app->db()->dialect()->name() !== 'sqlite') {
                self::assertStringContainsString('스키마 갱신 직전 자동 DB 백업은 SQLite에서만', $body);
                return;
            }

            self::assertStringContainsString('2026-02-01 09:00:05', $body);
            self::assertStringContainsString('schema-backups', $body);
            self::assertStringNotContainsString('설치 이후 없음', $body);
            $newerPos = strpos($body, 'board-v9-20260201-000000.sqlite');
            $olderPos = strpos($body, 'board-v8-20260101-000000.sqlite');
            self::assertIsInt($newerPos);
            self::assertIsInt($olderPos);
            self::assertLessThan($olderPos, $newerPos, '최신 백업이 먼저 나와야 한다');

            $deleted = $this->post($app, '/admin/schema-backups/' . basename($older) . '/delete', [
                'csrf_token' => $_SESSION['csrf_token'],
            ]);
            self::assertSame(303, $deleted->getStatusCode(), $this->body($deleted));
            self::assertStringContainsString('schema_backup_deleted=', $deleted->getHeaderLine('Location'));
            self::assertFileDoesNotExist($older);
            $afterDelete = $this->body($this->get($app, '/admin/settings/maintenance', [
                'schema_backup_deleted' => basename($older),
            ]));
            self::assertStringContainsString('자동 DB 백업을 삭제했습니다', $afterDelete);
        } finally {
            @unlink($older);
            @unlink($newer);
            @rmdir($backupsDir);
            @rmdir($tmp);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsPageSavesAttachmentLimits(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);

        $general = $this->body($this->get($app, '/admin/settings'));
        self::assertStringNotContainsString('name="guest_write_enabled"', $general);
        $page = $this->body($this->get($app, '/admin/settings/writing'));
        self::assertStringContainsString('회원·글쓰기 설정', $page);
        self::assertStringContainsString('name="guest_write_enabled"', $page);
        // 스위치 설명은 게시판 설정과의 우선순위까지 말해 준다.
        self::assertStringContainsString('이 스위치가 꺼져 있으면 회원만 글을 쓸 수 있습니다', $page);
        self::assertStringContainsString('name="post_min_chars"', $page);
        self::assertStringContainsString('name="attach_max_mb"', $page);
        self::assertStringContainsString('name="attach_limit"', $page);
        self::assertStringContainsString('name="post_rate_interval"', $page);
        self::assertStringContainsString('name="comment_rate_day"', $page);
        self::assertStringContainsString('0 = 무제한', $page);

        $base = ['csrf_token' => $_SESSION['csrf_token']];
        $saved = $this->post($app, '/admin/settings/writing', $base + [
            'guest_write_enabled' => '1', 'attach_max_mb' => '20', 'attach_limit' => '0',
            'post_min_chars' => '10', 'comment_min_chars' => '0',
            'post_rate_interval' => '12', 'post_rate_10m' => '4', 'post_rate_day' => '15',
            'comment_rate_interval' => '3', 'comment_rate_10m' => '12', 'comment_rate_day' => '60',
        ]);
        self::assertSame(303, $saved->getStatusCode());
        $settings = $app->cmsService()->settings();
        self::assertSame(20, $settings['attach_max_mb']);
        self::assertSame(0, $settings['attach_limit']);
        self::assertSame(10, $settings['post_min_chars']);
        self::assertSame(12, $settings['post_rate_interval']);
        self::assertSame(60, $settings['comment_rate_day']);
        self::assertTrue($settings['guest_write_enabled']);

        // Validator::int 는 범위를 벗어나면 실패가 아니라 잘라낸다(clamp)이므로
        // 여기서는 422 가 아니라 1024 로 잘린 값을 확인한다.
        $clamped = $this->post($app, '/admin/settings/writing', $base + [
            'attach_max_mb' => '2000', 'attach_limit' => '5',
            'post_min_chars' => '0', 'comment_min_chars' => '0',
        ]);
        self::assertSame(303, $clamped->getStatusCode());
        self::assertSame(1024, $app->cmsService()->settings()['attach_max_mb']);
    }

    #[DataProvider('connectionProvider')]
    public function testAbandonedUploadsCanBeCleanedFromSettings(array $dbConfig): void
    {
        $this->purgeTestUploads();
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $abandoned = $app->attachments()->upload($this->adminAcl(), 'free', $this->fakeUpload('버려짐.txt', 'x'));
        touch($abandoned['path'], time() - 90000);

        $page = $this->body($this->get($app, '/admin/settings/maintenance'));
        self::assertStringContainsString('업로드 파일 정리', $page);
        self::assertStringContainsString('삭제 예정 <strong>1개</strong>', $page);
        self::assertStringContainsString(basename($abandoned['path']), $page);
        self::assertStringContainsString('정리 대상 1개 삭제', $page);

        $cleaned = $this->post($app, '/admin/uploads/gc', ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(303, $cleaned->getStatusCode());
        self::assertStringContainsString('gc=1', $cleaned->getHeaderLine('Location'));
        self::assertFileDoesNotExist($abandoned['path']);

        $after = $this->body($this->get($app, '/admin/settings/maintenance', ['gc' => '1']));
        self::assertStringContainsString('버려진 파일 1개를 정리했습니다', $after);

        // 정리할 게 없을 때(gc=0)는 성공 알림이 아니라 안내 문구를 보여야 한다.
        $cleanedAgain = $this->post($app, '/admin/uploads/gc', ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertStringContainsString('gc=0', $cleanedAgain->getHeaderLine('Location'));
        $emptyGc = $this->body($this->get($app, '/admin/settings/maintenance', ['gc' => '0']));
        self::assertStringContainsString('정리할 파일이 없습니다', $emptyGc);
        self::assertStringNotContainsString('버려진 파일 0개를 정리했습니다', $emptyGc);
        self::assertStringContainsString('현재 정리할 업로드 파일이 없습니다', $emptyGc);
        self::assertStringNotContainsString('정리 대상 0개 삭제', $emptyGc);
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanConfigureSocialLoginFromSettings(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example']]);
        $id = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $page = $this->body($this->get($app, '/admin/settings/social'));
        self::assertStringContainsString('소셜 로그인', $page);
        self::assertStringContainsString('https://community.example/auth/google/callback', $page);
        self::assertStringContainsString('Google 로그인 사용', $page);
        self::assertStringContainsString('네이버 로그인 사용', $page);
        self::assertStringContainsString('카카오 로그인 사용', $page);
        self::assertStringContainsString('https://console.cloud.google.com/auth/clients', $page);
        self::assertStringContainsString('https://developers.naver.com/apps/#/list', $page);
        self::assertStringContainsString('https://developers.kakao.com/console/app', $page);
        self::assertSame(3, substr_count($page, '키 발급·관리'));
        self::assertSame(7, substr_count($page, 'rel="noopener noreferrer"'));
        self::assertStringContainsString('네이버 개발자센터 애플리케이션의 Client ID', $page);
        self::assertStringContainsString('JavaScript 키가 아닌 REST API 키', $page);
        self::assertStringContainsString('Client Secret (선택)', $page);
        self::assertStringContainsString('이메일 주소를 필수로 설정', $page);
        self::assertStringContainsString('카카오계정(이메일)을 필수로 설정', $page);
        self::assertStringContainsString('카카오 이메일 제공 동의 설정 방법', $page);
        self::assertStringContainsString('카카오 로그인 → 동의항목 → 개인정보', $page);
        self::assertStringContainsString('추가 기능 신청 → 개인정보 동의항목', $page);
        self::assertStringContainsString('https://developers.kakao.com/docs/ko/kakaologin/prerequisite', $page);
        self::assertStringContainsString('https://community.example/auth/kakao/callback', $page);
        self::assertSame(3, substr_count($page, 'data-pw-label="Client Secret"'));
        self::assertStringContainsString('Client Secret 표시', $page);

        $incomplete = $this->post($app, '/admin/settings/social', [
            'csrf_token' => $_SESSION['csrf_token'], 'google_enabled' => '1',
            'google_client_id' => 'google-client-id', 'naver_client_id' => '', 'kakao_client_id' => '',
        ]);
        self::assertSame(422, $incomplete->getStatusCode());
        self::assertStringContainsString('Google Client Secret을 입력해 주세요', $this->body($incomplete));

        $saved = $this->post($app, '/admin/settings/social', [
            'csrf_token' => $_SESSION['csrf_token'], 'google_enabled' => '1',
            'google_client_id' => 'google-client-id', 'google_client_secret' => 'google-client-secret',
            'naver_client_id' => '', 'kakao_client_id' => '',
        ]);
        self::assertSame(303, $saved->getStatusCode(), $this->body($saved));
        self::assertSame('/admin/settings/social?saved=1', $saved->getHeaderLine('Location'));

        $stored = $app->oauthSettings()->all();
        self::assertSame('1', $stored['google.enabled']);
        self::assertSame('google-client-id', $stored['google.client_id']);
        self::assertStringStartsWith('v2:', $stored['google.client_secret']);
        self::assertStringNotContainsString('google-client-secret', $stored['google.client_secret']);
        self::assertSame([['key' => 'google', 'label' => 'Google']], $app->providerRegistry()->options());

        $savedPage = $this->body($this->get($app, '/admin/settings/social'));
        self::assertStringNotContainsString('google-client-secret', $savedPage);
        self::assertStringContainsString('placeholder="••••••••••••••••"', $savedPage);
        self::assertStringContainsString('data-oauth-secret-toggle', $savedPage);
        self::assertStringContainsString(
            'data-secret-url="/admin/settings/social/google/secret"', $savedPage
        );
        $revealed = $this->post($app, '/admin/settings/social/google/secret', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(200, $revealed->getStatusCode());
        self::assertSame('no-store', $revealed->getHeaderLine('Cache-Control'));
        self::assertSame(['secret' => 'google-client-secret'], json_decode($this->body($revealed), true));
        self::assertStringContainsString('Google로 계속', $this->body($this->get($app, '/login')));

        $preserved = $this->post($app, '/admin/settings/social', [
            'csrf_token' => $_SESSION['csrf_token'], 'google_enabled' => '1',
            'google_client_id' => 'changed-client-id', 'google_client_secret' => '',
            'naver_client_id' => '', 'kakao_client_id' => '',
        ]);
        self::assertSame(303, $preserved->getStatusCode());
        self::assertSame('google-client-secret', $app->oauthSettingsService()->runtime()['google']['client_secret']);

        $kakaoWithSecret = $this->post($app, '/admin/settings/social', [
            'csrf_token' => $_SESSION['csrf_token'], 'kakao_enabled' => '1',
            'google_client_id' => '', 'naver_client_id' => '',
            'kakao_client_id' => 'kakao-rest-api-key', 'kakao_client_secret' => 'wrong-secret',
        ]);
        self::assertSame(303, $kakaoWithSecret->getStatusCode());
        self::assertSame('wrong-secret', $app->oauthSettingsService()->runtime()['kakao']['client_secret']);

        $clearedKakaoSecret = $this->post($app, '/admin/settings/social', [
            'csrf_token' => $_SESSION['csrf_token'], 'kakao_enabled' => '1',
            'google_client_id' => '', 'naver_client_id' => '',
            'kakao_client_id' => 'kakao-rest-api-key', 'kakao_client_secret_clear' => '1',
        ]);
        self::assertSame(303, $clearedKakaoSecret->getStatusCode());
        self::assertSame('', $app->oauthSettingsService()->runtime()['kakao']['client_secret']);
        self::assertSame([['key' => 'kakao', 'label' => '카카오']], $app->providerRegistry()->options());
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanConfigureTurnstileWithoutExposingSecret(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [
            'app' => ['url' => 'https://community.example'],
            'turnstile' => [
                'enabled' => false,
                'site_key' => '',
                'secret_key' => '',
                'hostname' => '',
                'transport' => static fn (): array => ['success' => true],
            ],
        ]);
        $id = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);

        $page = $this->body($this->get($app, '/admin/settings/security'));
        self::assertStringContainsString('자동 등록 방지', $page);
        self::assertStringContainsString('Cloudflare Turnstile 사용', $page);
        self::assertStringContainsString('value="community.example"', $page);
        self::assertStringContainsString('Cloudflare에서 키 발급·관리', $page);

        $incomplete = $this->post($app, '/admin/settings/security', [
            'csrf_token' => $_SESSION['csrf_token'], 'enabled' => '1',
            'site_key' => '', 'secret_key' => '', 'hostname' => 'community.example',
        ]);
        self::assertSame(422, $incomplete->getStatusCode());
        self::assertStringContainsString('Site Key를 입력해 주세요', $this->body($incomplete));
        self::assertStringContainsString('Secret Key를 입력해 주세요', $this->body($incomplete));

        $saved = $this->post($app, '/admin/settings/security', [
            'csrf_token' => $_SESSION['csrf_token'], 'enabled' => '1',
            'site_key' => 'turnstile-site-key', 'secret_key' => 'turnstile-secret-key',
            'hostname' => 'community.example',
        ]);
        self::assertSame(303, $saved->getStatusCode(), $this->body($saved));
        self::assertSame('/admin/settings/security?saved=1', $saved->getHeaderLine('Location'));

        $stored = $app->turnstileSettings()->all();
        self::assertSame('1', $stored['enabled']);
        self::assertSame('turnstile-site-key', $stored['site_key']);
        self::assertStringStartsWith('v2:', $stored['secret_key']);
        self::assertStringNotContainsString('turnstile-secret-key', $stored['secret_key']);
        self::assertArrayNotHasKey('turnstile.secret_key', $app->cms()->settings());
        self::assertSame('turnstile-secret-key', $app->turnstileSettingsService()->runtime()['secret_key']);
        self::assertTrue($app->turnstile()->isEnabled());
        self::assertSame('turnstile-site-key', $app->turnstile()->siteKey());

        $savedPage = $this->body($this->get($app, '/admin/settings/security'));
        self::assertStringNotContainsString('turnstile-secret-key', $savedPage);
        self::assertStringContainsString('placeholder="••••••••••••••••"', $savedPage);
        self::assertStringContainsString('data-turnstile-secret-toggle', $savedPage);
        $revealed = $this->post($app, '/admin/settings/security/secret', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(200, $revealed->getStatusCode());
        self::assertSame('no-store', $revealed->getHeaderLine('Cache-Control'));
        self::assertSame(['secret' => 'turnstile-secret-key'], json_decode($this->body($revealed), true));
        self::assertStringContainsString('data-sitekey="turnstile-site-key"', $this->body(
            $this->get($app, '/forgot-password')
        ));

        $preserved = $this->post($app, '/admin/settings/security', [
            'csrf_token' => $_SESSION['csrf_token'], 'enabled' => '1',
            'site_key' => 'changed-site-key', 'secret_key' => '', 'hostname' => 'community.example',
        ]);
        self::assertSame(303, $preserved->getStatusCode());
        self::assertSame('turnstile-secret-key', $app->turnstileSettingsService()->runtime()['secret_key']);

        $cleared = $this->post($app, '/admin/settings/security', [
            'csrf_token' => $_SESSION['csrf_token'], 'site_key' => 'changed-site-key',
            'hostname' => 'community.example', 'secret_key_clear' => '1',
        ]);
        self::assertSame(303, $cleared->getStatusCode());
        self::assertSame('', $app->turnstileSettingsService()->runtime()['secret_key']);
        self::assertFalse($app->turnstile()->isEnabled());
    }

}
