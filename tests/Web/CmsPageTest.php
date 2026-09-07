<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\UploadedFile;

final class CmsPageTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testPublishedPageAppearsInMenuAndDraftIsHidden(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->createPage([
            'slug' => 'about', 'title' => '소개', 'content' => '우리 사이트 소개입니다.',
            'seo_description' => null, 'status' => 'published', 'show_in_menu' => 1, 'sort_order' => 0,
        ]);
        $draftId = $app->cms()->createPage([
            'slug' => 'draft', 'title' => '초안', 'content' => '아직 공개하지 않습니다.',
            'seo_description' => null, 'status' => 'draft', 'show_in_menu' => 1, 'sort_order' => 1,
        ]);

        $home = $this->get($app, '/');
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('href="/content/about"', $this->body($home));
        self::assertStringNotContainsString('href="/content/draft"', $this->body($home));
        self::assertSame(200, $this->get($app, '/content/about')->getStatusCode());
        self::assertSame(404, $this->get($app, '/content/draft')->getStatusCode());
        $this->assertLoginRedirect($this->get($app, '/admin/content/' . $draftId . '/preview'), '/admin/content/' . $draftId . '/preview');
        $legacy = $this->get($app, '/page/about');
        self::assertSame(301, $legacy->getStatusCode());
        self::assertSame('/content/about', $legacy->getHeaderLine('Location'));
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanAddVerificationAnalyticsAndAdsenseHeadCode(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create(
            'owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '소유자', true
        );
        $app->users()->verifyEmail($id);
        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'owner@example.com',
            'password' => 'owner-password-123',
        ]);

        $form = $this->body($this->get($app, '/admin/settings'));
        self::assertStringContainsString('name="site_verification_html"', $form);
        self::assertStringContainsString('name="analytics_html"', $form);
        self::assertStringContainsString('name="adsense_html"', $form);
        self::assertStringContainsString('name="timezone"', $form);
        self::assertStringContainsString('value="Asia/Seoul" selected', $form);

        $verification = '<meta name="naver-site-verification" content="naver-token">';
        $analytics = '<script data-analytics-code>window.analyticsLoaded=true;</script>';
        $adsense = '<script async data-ad-client="ca-pub-test"></script>';
        $general = [
            'csrf_token' => $_SESSION['csrf_token'],
            'site_name' => GNUCMS,
            'site_tagline' => '가볍게 시작하는 기초 커뮤니티',
            'home_title' => '가볍게 시작하고, 오래 이어지는 공간',
            'home_intro' => '필요한 페이지와 커뮤니티를 한곳에서 운영하세요.',
            'theme' => 'default',
            'timezone' => 'America/New_York',
            'password_login_enabled' => '1',
            'social_login_enabled' => '1',
            'registration_enabled' => '1',
            'social_registration_enabled' => '1',
            'site_verification_html' => $verification,
            'analytics_html' => $analytics,
            'adsense_html' => $adsense,
        ];
        $saved = $this->post($app, '/admin/settings', $general);
        self::assertSame(303, $saved->getStatusCode());

        $home = $this->body($this->get($app, '/'));
        self::assertStringContainsString($verification, $home);
        self::assertStringContainsString($analytics, $home);
        self::assertStringContainsString($adsense, $home);

        $admin = $this->body($this->get($app, '/admin/settings'));
        self::assertSame('America/New_York', $app->cmsService()->settings()['timezone']);
        self::assertStringContainsString('value="America/New_York" selected', $admin);
        self::assertStringNotContainsString($analytics, $admin, '관리 화면에서는 외부 스크립트를 실행하지 않는다.');
        self::assertStringContainsString('&lt;script data-analytics-code&gt;', $admin);

        $invalid = $this->post($app, '/admin/settings', array_replace($general, [
            'site_verification_html' => '</head><body>잘못된 코드</body>',
        ]));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('html, head, body 태그는 입력할 수 없습니다.', $this->body($invalid));
    }

    #[DataProvider('connectionProvider')]
    public function testRegistrationCanBeDisabled(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create(
            'member@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '기존회원', false
        );
        $app->users()->verifyEmail($id);
        $app->cms()->saveSettings([
            'registration_enabled' => '0', 'social_registration_enabled' => '0',
        ]);

        self::assertSame(403, $this->get($app, '/register')->getStatusCode());
        self::assertStringNotContainsString('회원가입</a>', $this->body($this->get($app, '/')));
        $this->get($app, '/login');
        self::assertSame(303, $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com',
            'password' => 'member-password-123',
        ])->getStatusCode(), '가입을 모두 막아도 기존 일반 회원은 로그인할 수 있어야 한다');
    }

    #[DataProvider('connectionProvider')]
    public function testRegularAndSocialRegistrationCanBeControlledSeparately(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [
            'oauth' => ['google' => ['client_id' => 'id', 'client_secret' => 'secret']],
        ]);
        $save = function (bool $regular, bool $social) use ($app): void {
            $app->cmsService()->saveGeneralSettings($this->adminAcl(), [
                'site_name' => GNUCMS,
                'site_tagline' => '가볍게 시작하는 기초 커뮤니티',
                'home_title' => '가볍게 시작하고, 오래 이어지는 공간',
                'home_intro' => '필요한 페이지와 커뮤니티를 한곳에서 운영하세요.',
                'theme' => 'default',
                'password_login_enabled' => '1',
                'social_login_enabled' => '1',
                'registration_enabled' => $regular ? '1' : '0',
                'social_registration_enabled' => $social ? '1' : '0',
            ]);
        };

        $save(true, true);
        $both = $this->body($this->get($app, '/register'));
        self::assertStringContainsString('action="/register"', $both);
        self::assertStringContainsString('Google로 계속하기', $both);
        self::assertStringContainsString('또는 이메일로 계속', $both);

        $save(false, true);
        $socialOnly = $this->body($this->get($app, '/register'));
        self::assertStringNotContainsString('action="/register"', $socialOnly);
        self::assertStringContainsString('Google로 계속하기', $socialOnly);
        self::assertStringNotContainsString('또는 이메일로 계속', $socialOnly);

        $save(true, false);
        $regularOnly = $this->body($this->get($app, '/register'));
        self::assertStringContainsString('action="/register"', $regularOnly);
        self::assertStringNotContainsString('Google로 계속하기', $regularOnly);

        $save(false, false);
        self::assertSame(403, $this->get($app, '/register')->getStatusCode());
        self::assertStringNotContainsString('회원가입</a>', $this->body($this->get($app, '/login')));
    }

    #[DataProvider('connectionProvider')]
    public function testDisabledLoginForcesItsSignupOffButAdminPasswordLoginRemains(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [
            'oauth' => ['google' => ['client_id' => 'id', 'client_secret' => 'secret']],
        ]);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $app->users()->verifyEmail($adminId);
        $memberId = $app->users()->create(
            'member@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '일반회원', false
        );
        $app->users()->verifyEmail($memberId);

        $app->cmsService()->saveGeneralSettings($this->adminAcl(), [
            'site_name' => GNUCMS, 'site_tagline' => '소개', 'home_title' => '홈', 'home_intro' => '본문',
            'theme' => 'default',
            'password_login_enabled' => '0', 'registration_enabled' => '1',
            'social_login_enabled' => '0', 'social_registration_enabled' => '1',
        ]);
        $stored = $app->cms()->settings();
        self::assertSame('0', $stored['registration_enabled']);
        self::assertSame('0', $stored['social_registration_enabled']);
        self::assertSame(403, $this->get($app, '/register')->getStatusCode());

        $loginPage = $this->body($this->get($app, '/login'));
        self::assertStringContainsString('일반 회원 로그인이 중지되어 있습니다.', $loginPage);
        self::assertStringNotContainsString('Google로 계속하기', $loginPage);
        $memberLogin = $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com',
            'password' => 'member-password-123',
        ]);
        self::assertSame(403, $memberLogin->getStatusCode());
        self::assertStringContainsString('현재 일반 회원 로그인을 허용하지 않습니다.', $this->body($memberLogin));

        $adminLogin = $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com',
            'password' => 'admin-password-123',
        ]);
        self::assertSame(303, $adminLogin->getStatusCode(), '관리자 잠금을 막기 위해 비밀번호 로그인은 남긴다');
        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        self::assertSame(403, $this->get($app, '/auth/google')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testOwnerCanCreatePageFromAdmin(array $dbConfig): void
    {
        $editorRoot = sys_get_temp_dir() . '/' . GNUCMS_ID . '-web-editor-' . bin2hex(random_bytes(5));
        $app = $this->makeApp($dbConfig, ['editor' => ['dir' => $editorRoot, 'max_bytes' => 1024 * 1024]]);
        $id = $app->users()->create('owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '소유자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'owner@example.com', 'password' => 'owner-password-123',
        ]);

        $settingsPage = $this->get($app, '/admin/settings');
        self::assertSame(200, $settingsPage->getStatusCode());
        self::assertStringContainsString('name="theme"', $this->body($settingsPage));
        self::assertStringContainsString('default (기본)', $this->body($settingsPage));
        self::assertStringContainsString('신규 일반 회원가입 허용', $this->body($settingsPage));
        self::assertStringContainsString('신규 소셜 회원가입 허용', $this->body($settingsPage));
        self::assertStringContainsString('일반 회원 로그인 허용', $this->body($settingsPage));
        self::assertStringContainsString('소셜 회원 로그인 허용', $this->body($settingsPage));
        self::assertStringContainsString('data-login-toggle="regular"', $this->body($settingsPage));
        self::assertStringContainsString('data-signup-toggle="social"', $this->body($settingsPage));
        $settingsSaved = $this->post($app, '/admin/settings', [
            'csrf_token' => $_SESSION['csrf_token'],
            'site_name' => GNUCMS,
            'site_tagline' => '가볍게 시작하는 기초 커뮤니티',
            'home_title' => '가볍게 시작하고, 오래 이어지는 공간',
            'home_intro' => '필요한 페이지와 커뮤니티를 한곳에서 운영하세요.',
            'theme' => 'default',
            'password_login_enabled' => '1',
            'social_login_enabled' => '1',
            'registration_enabled' => '1',
            'social_registration_enabled' => '1',
        ]);
        self::assertSame(303, $settingsSaved->getStatusCode());
        self::assertSame('default', $app->cms()->settings()['theme']);
        self::assertSame('1', $app->cms()->settings()['registration_enabled']);
        self::assertSame('1', $app->cms()->settings()['social_registration_enabled']);
        $documents = $this->get($app, '/admin/content');
        self::assertStringContainsString('<th class="right">정렬</th>', $this->body($documents));
        self::assertStringContainsString('colspan="6"', $this->body($documents));
        self::assertSame(200, $documents->getStatusCode());
        self::assertStringContainsString('<h1>내용 관리</h1>', $this->body($documents));
        self::assertStringNotContainsString('<h1>페이지 관리</h1>', $this->body($documents));
        self::assertSame(200, $this->get($app, '/admin/content/new')->getStatusCode());
        self::assertStringContainsString('/vendor/ckeditor4/ckeditor.js', $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString('data-cms-editor', $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString("input.multiple=true", $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString("items:['" . ucfirst(GNUCMS_ID) . "Images'", $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString('navigator.sendBeacon', $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString('data-uploaded-images', $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString(
            "extraAllowedContent:'img[alt,src,title]'",
            $this->body($this->get($app, '/admin/content/new'))
        );
        self::assertStringContainsString('refreshStoredImages', $this->body($this->get($app, '/admin/content/new')));
        self::assertStringContainsString('getDocumentElement', $this->body($this->get($app, '/admin/content/new')));
        $createForm = $this->body($this->get($app, '/admin/content/new'));
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $createForm, $keyMatch));
        $imageKey = $keyMatch[1];

        $temporaryImage = tempnam(sys_get_temp_dir(), GNUCMS_ID . '-web-png-');
        self::assertNotFalse($temporaryImage);
        file_put_contents($temporaryImage, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));
        $imageSize = filesize($temporaryImage);
        self::assertNotFalse($imageSize);
        $imageUpload = $this->upload(
            $app,
            '/admin/editor/images?csrf_token=' . rawurlencode($_SESSION['csrf_token'])
                . '&image_key=' . $imageKey,
            ['upload' => new UploadedFile($temporaryImage, 'pixel.png', 'image/png', $imageSize)]
        );
        self::assertSame(200, $imageUpload->getStatusCode());
        $uploadedImage = json_decode($this->body($imageUpload), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $uploadedImage['uploaded']);
        self::assertSame(200, $this->get($app, $uploadedImage['url'])->getStatusCode());
        $storedImage = $editorRoot . substr($uploadedImage['url'], strlen('/media/editor'));

        $discardTemporary = tempnam(sys_get_temp_dir(), GNUCMS_ID . '-discard-png-');
        self::assertNotFalse($discardTemporary);
        file_put_contents($discardTemporary, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));
        $discardSize = filesize($discardTemporary);
        self::assertNotFalse($discardSize);
        $discardUpload = $this->upload(
            $app,
            '/admin/editor/images?csrf_token=' . rawurlencode($_SESSION['csrf_token'])
                . '&image_key=' . $imageKey,
            ['upload' => new UploadedFile($discardTemporary, 'discard.png', 'image/png', $discardSize)]
        );
        $discardImage = json_decode($this->body($discardUpload), true, 512, JSON_THROW_ON_ERROR);
        $discardPath = $editorRoot . substr($discardImage['url'], strlen('/media/editor'));
        self::assertFileExists($discardPath);
        $discard = $this->post(
            $app,
            '/admin/editor/images/discard?csrf_token=' . rawurlencode($_SESSION['csrf_token'])
                . '&image_key=' . $imageKey,
            ['files' => [$discardImage['fileName']]]
        );
        self::assertSame(204, $discard->getStatusCode());
        self::assertFileDoesNotExist($discardPath);
        $legacy = $this->get($app, '/admin/pages');
        self::assertSame(301, $legacy->getStatusCode());
        self::assertSame('/admin/content', $legacy->getHeaderLine('Location'));
        $legacyDocuments = $this->get($app, '/admin/documents');
        self::assertSame(301, $legacyDocuments->getStatusCode());
        self::assertSame('/admin/content', $legacyDocuments->getHeaderLine('Location'));

        $legalSetup = $this->post($app, '/admin/terms/setup', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $legalSetup->getStatusCode());
        self::assertSame('draft', $app->cms()->findBySlug('service')['status']);
        self::assertSame('draft', $app->cms()->findBySlug('privacy')['status']);
        // 약관 관리에는 약관 전부가 나오고, 내용 관리에는 안 나온다.
        $legalPage = $this->get($app, '/admin/terms');
        self::assertSame(200, $legalPage->getStatusCode());
        self::assertStringContainsString('이용약관', $this->body($legalPage));
        self::assertStringContainsString('>/terms/service<', $this->body($legalPage));
        self::assertStringNotContainsString('이용약관', $this->body($this->get($app, '/admin/content')));

        // 옛 주소는 없어졌다.
        self::assertSame(404, $this->get($app, '/admin/terms/service')->getStatusCode());
        self::assertSame(404, $this->get($app, '/admin/legal')->getStatusCode());

        // 붙임을 화면에서 저장할 수 있다.
        $terms = $app->cms()->findBySlug('service');
        $termsEditForm = $this->get($app, '/admin/content/' . $terms['id'] . '/edit');
        self::assertSame(200, $termsEditForm->getStatusCode());
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $this->body($termsEditForm), $termsKeyMatch));
        $termsImageKey = $termsKeyMatch[1];
        $saved = $this->post($app, '/admin/terms/uses', [
            'csrf_token' => $_SESSION['csrf_token'],
            'usage' => [(string) $terms['id'] => 'signup'],
            'required' => [(string) $terms['id'] => '1'],
            'sort_order' => [(string) $terms['id'] => '10'],
        ]);
        self::assertSame(303, $saved->getStatusCode(), $this->body($saved));
        $uses = $app->consentUses()->listForScope('signup');
        self::assertCount(1, $uses);
        self::assertSame(1, (int) $uses[0]['required']);
        self::assertSame(10, (int) $uses[0]['use_sort_order']);

        // 편집 폼에서 신청서·등록 동의에 자리 이름을 주면 form:{이름} 으로 붙는다.
        $named = $this->post($app, '/admin/content/' . $terms['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'service', 'title' => $terms['title'],
            'content' => $terms['content'], 'status' => $terms['status'],
            'show_in_menu' => '1', 'sort_order' => (string) $terms['sort_order'],
            'image_key' => $termsImageKey, 'consent_usage' => 'form',
            'consent_scope_key' => 'event-2026', 'consent_required' => '1', 'consent_order' => '10',
        ]);
        self::assertSame(303, $named->getStatusCode(), $this->body($named));
        self::assertSame([], $app->consentUses()->listForScope('signup'));
        self::assertCount(1, $app->consentUses()->listForScope('form:event-2026'));

        // 목록 화면에는 자리 이름 칸이 없다. form 을 그대로 두고 일괄 저장해도 이름이 지켜진다.
        $bulk = $this->post($app, '/admin/terms/uses', [
            'csrf_token' => $_SESSION['csrf_token'],
            'usage' => [(string) $terms['id'] => 'form'],
            'required' => [(string) $terms['id'] => '1'],
            'sort_order' => [(string) $terms['id'] => '10'],
        ]);
        self::assertSame(303, $bulk->getStatusCode(), $this->body($bulk));
        self::assertCount(1, $app->consentUses()->listForScope('form:event-2026'),
            '일괄 저장이 자리 이름을 지우면 안 된다.');

        // 안내만 으로 바꾸면 어느 자리에도 붙지 않는다.
        $detached = $this->post($app, '/admin/terms/uses', [
            'csrf_token' => $_SESSION['csrf_token'],
            'usage' => [(string) $terms['id'] => 'none'],
        ]);
        self::assertSame(303, $detached->getStatusCode(), $this->body($detached));
        self::assertSame([], $app->consentUses()->listForScope('signup'));
        self::assertSame([], $app->consentUses()->listForScope('form'));

        // 약관을 폼에서 고쳐 저장하면 약관 관리로 돌아가야 한다.
        $termsEdit = $this->post($app, '/admin/content/' . $terms['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'service', 'title' => $terms['title'],
            'content' => $terms['content'], 'status' => $terms['status'],
            'show_in_menu' => $terms['show_in_menu'] ? '1' : '0',
            'sort_order' => (string) $terms['sort_order'], 'image_key' => $termsImageKey,
        ]);
        self::assertSame(303, $termsEdit->getStatusCode(), $this->body($termsEdit));
        self::assertSame('/admin/terms?saved=1', $termsEdit->getHeaderLine('Location'));

        // 약관 관리에서 새 약관을 바로 만들 수 있다.
        $termsCreateForm = $this->body($this->get($app, '/admin/terms/new'));
        self::assertStringContainsString('약관 만들기', $termsCreateForm);
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $termsCreateForm, $newTermsKey));
        $termsCreated = $this->post($app, '/admin/terms/new', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'location', 'title' => '위치기반 서비스 약관',
            'content' => '<p>위치 약관 본문입니다.</p>', 'status' => 'published', 'show_in_menu' => '0',
            'sort_order' => '40', 'image_key' => $newTermsKey[1],
        ]);
        self::assertSame(303, $termsCreated->getStatusCode(), $this->body($termsCreated));
        self::assertSame('/admin/terms?created=1', $termsCreated->getHeaderLine('Location'));
        $location = $app->cms()->findBySlug('location');
        self::assertSame(1, (int) $location['is_consent'], '약관 관리에서 만든 내용에는 약관 표시가 붙는다.');
        self::assertStringContainsString('위치기반 서비스 약관', $this->body($this->get($app, '/admin/terms')));

        // 관리자 화면 밖에서 만든 약관도 약관 관리 목록에 나온다.
        $app->cms()->createPage([
            'slug' => 'marketing', 'title' => '마케팅 활용 동의', 'content' => '마케팅 활용 동의 내용입니다.',
            'seo_description' => null, 'status' => 'draft', 'show_in_menu' => 0, 'sort_order' => 30,
            'is_consent' => 1,
        ]);
        self::assertStringContainsString('마케팅 활용 동의', $this->body($this->get($app, '/admin/terms')));

        // 한 약관에 누가 동의했는지 따로 볼 수 있다.
        $app->consents()->record('user', 1, 'signup', $app->cms()->findBySlug('service'), true, null);
        $view = $this->get($app, '/admin/terms/' . $terms['id'] . '/consents');
        self::assertSame(200, $view->getStatusCode());
        self::assertStringContainsString('동의 현황', $this->body($view));

        // 옛 주소들은 새 정식 주소로 보낸다. 되돌림은 공개 여부와 무관하다.
        self::assertSame(301, $this->get($app, '/terms/terms')->getStatusCode());
        self::assertSame('/terms/service', $this->get($app, '/terms/terms')->getHeaderLine('Location'));
        self::assertSame('/terms/service', $this->get($app, '/content/terms')->getHeaderLine('Location'));
        self::assertSame('/terms/service', $this->get($app, '/terms')->getHeaderLine('Location'));

        // 약관이 아닌 내용을 /terms 밑으로 열면 정식 주소인 /content 로 보낸다.
        $app->cms()->createPage([
            'slug' => 'about-us', 'title' => '소개', 'content' => '<p>소개 본문입니다.</p>',
            'seo_description' => null, 'status' => 'published', 'show_in_menu' => 0, 'sort_order' => 0,
        ]);
        self::assertSame(200, $this->get($app, '/content/about-us')->getStatusCode());
        self::assertSame(301, $this->get($app, '/terms/about-us')->getStatusCode());
        self::assertSame('/content/about-us', $this->get($app, '/terms/about-us')->getHeaderLine('Location'));

        $draftId = $app->cms()->createPage([
            'slug' => 'private-note', 'title' => '비공개 안내', 'content' => '관리자 미리보기',
            'seo_description' => null, 'status' => 'draft', 'show_in_menu' => 0, 'sort_order' => 0,
        ]);
        $draftPreview = $this->get($app, '/admin/content/' . $draftId . '/preview');
        self::assertSame(200, $draftPreview->getStatusCode());
        self::assertStringContainsString('아직 공개되지 않은 초안 미리보기', $this->body($draftPreview));

        $response = $this->post($app, '/admin/content/new', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'guide', 'title' => '이용안내',
            'content' => '<p>간단한 이용안내입니다.</p><img src="' . $uploadedImage['url'] . '">',
            'status' => 'published', 'show_in_menu' => '1', 'sort_order' => '10',
            'image_key' => $imageKey,
        ]);

        self::assertSame(303, $response->getStatusCode());
        $saved = $app->cms()->findPublishedBySlug('guide');
        self::assertSame('이용안내', $saved['title']);
        self::assertSame($imageKey, $saved['image_key']);
        $documents = $this->body($this->get($app, '/admin/content'));
        self::assertStringContainsString('data-label="정렬" class="right">10</td>', $documents);
        $preview = $this->get($app, '/admin/content/' . $saved['id'] . '/preview');
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('공개된 내용 미리보기', $this->body($preview));

        // 약관이 아닌 일반 내용은 폼에서 고쳐 저장하면 내용 관리로 돌아간다.
        $pageEdit = $this->post($app, '/admin/content/' . $saved['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'guide', 'title' => $saved['title'],
            'content' => $saved['content'], 'status' => $saved['status'],
            'show_in_menu' => $saved['show_in_menu'] ? '1' : '0',
            'sort_order' => (string) $saved['sort_order'], 'image_key' => $saved['image_key'],
        ]);
        self::assertSame(303, $pageEdit->getStatusCode(), $this->body($pageEdit));
        self::assertSame('/admin/content?saved=1', $pageEdit->getHeaderLine('Location'));

        $deleted = $this->post($app, '/admin/content/' . $saved['id'] . '/delete', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $deleted->getStatusCode());
        self::assertSame('/admin/content?deleted=1', $deleted->getHeaderLine('Location'));
        self::assertNull($app->cms()->findPageById((int) $saved['id']));
        self::assertNotNull($app->cms()->findDeletedPageById((int) $saved['id']));
        self::assertFileExists($storedImage, '휴지통에서는 복원을 위해 이미지를 보존해야 한다.');
        self::assertSame(404, $this->get($app, '/content/guide')->getStatusCode());

        $trash = $this->get($app, '/admin/content/trash');
        self::assertSame(200, $trash->getStatusCode());
        self::assertStringContainsString('이용안내', $this->body($trash));
        $restored = $this->post($app, '/admin/content/trash/' . $saved['id'] . '/restore', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $restored->getStatusCode());
        self::assertSame('/admin/content/trash?restored=1', $restored->getHeaderLine('Location'));
        self::assertSame('draft', $app->cms()->findPageById((int) $saved['id'])['status']);
        self::assertNull($app->cms()->findDeletedPageById((int) $saved['id']));
        self::assertSame(404, $this->get($app, '/content/guide')->getStatusCode());

        $this->post($app, '/admin/content/' . $saved['id'] . '/delete', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        $permanent = $this->post($app, '/admin/content/trash/' . $saved['id'] . '/delete', [
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
        self::assertSame(303, $permanent->getStatusCode());
        self::assertSame('/admin/content/trash?deleted=1', $permanent->getHeaderLine('Location'));
        self::assertNull($app->cms()->findDeletedPageById((int) $saved['id']));
        self::assertFileDoesNotExist($storedImage, '완전 삭제하면 콘텐츠 전용 폴더의 이미지도 삭제해야 한다.');
        @rmdir($editorRoot);
    }

    /** 약관 붙이기와 동의 현황은 관리자만 열 수 있고, 표를 지나온 요청만 받는다. */
    #[DataProvider('connectionProvider')]
    public function testGuestCannotReachConsentRoutes(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $pageId = $app->cms()->createPage([
            'slug' => 'terms', 'title' => '이용약관', 'content' => '본문', 'seo_description' => null,
            'status' => 'published', 'show_in_menu' => 0, 'sort_order' => 0, 'is_consent' => 1,
        ]);

        // 표를 받아 두고 손님인 채로 보낸다. 표가 맞아도 관리자가 아니면 막힌다.
        $this->get($app, '/login');
        $uses = $this->post($app, '/admin/terms/uses', [
            'csrf_token' => $_SESSION['csrf_token'], 'scope' => 'signup',
        ]);
        $this->assertLoginRedirect($uses);
        $this->assertLoginRedirect($this->get($app, '/admin/terms'), '/admin/terms');
        $this->assertLoginRedirect($this->get($app, '/admin/terms/' . $pageId . '/consents'), '/admin/terms/' . $pageId . '/consents');

        // 관리자로 들어와도 표가 없는 요청은 받지 않는다.
        $ownerId = $app->users()->create('owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '소유자', true);
        $app->users()->verifyEmail($ownerId);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'owner@example.com', 'password' => 'owner-password-123',
        ]);
        self::assertSame(200, $this->get($app, '/admin/terms')->getStatusCode());
        self::assertSame(403, $this->post($app, '/admin/terms/uses', ['scope' => 'signup'])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testConsentToggleRoundTrip(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('owner@example.com', password_hash('owner-password-123', PASSWORD_DEFAULT), '소유자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'owner@example.com', 'password' => 'owner-password-123',
        ]);

        $createForm = $this->body($this->get($app, '/admin/content/new'));
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $createForm, $keyMatch));
        $imageKey = $keyMatch[1];

        // 내용 관리에서는 약관 표시를 실어 보내도 무시된다. 약관은 약관 관리에서만 만든다.
        $created = $this->post($app, '/admin/content/new', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'refund-policy', 'title' => '환불 규정',
            'content' => '<p>환불 규정 내용입니다.</p>', 'status' => 'published', 'show_in_menu' => '1',
            'sort_order' => '0', 'image_key' => $imageKey, 'is_consent' => '1',
        ]);
        self::assertSame(303, $created->getStatusCode());
        self::assertSame('/admin/content?saved=1', $created->getHeaderLine('Location'));

        $saved = $app->cms()->findPublishedBySlug('refund-policy');
        self::assertSame(0, (int) $saved['is_consent'], '내용 관리로는 약관을 만들 수 없다.');

        $acl = $app->guestAcl();
        self::assertContains('refund-policy', array_column($app->cmsService()->contents($acl), 'slug'));
        self::assertNotContains('refund-policy', array_column($app->cmsService()->consentPages($acl), 'slug'));

        // 약관 관리에서 만들면 표시가 붙고 내용 관리 목록에서는 빠진다.
        $legalForm = $this->body($this->get($app, '/admin/terms/new'));
        self::assertSame(1, preg_match('/name="image_key" value="([a-f0-9]{32})"/', $legalForm, $legalKey));
        $this->post($app, '/admin/terms/new', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'consent-doc', 'title' => '수집 동의',
            'content' => '<p>수집 동의 본문입니다.</p>', 'status' => 'published', 'show_in_menu' => '0',
            'sort_order' => '0', 'image_key' => $legalKey[1],
        ]);
        $consentDoc = $app->cms()->findPublishedBySlug('consent-doc');
        self::assertSame(1, (int) $consentDoc['is_consent']);
        self::assertNotContains('consent-doc', array_column($app->cmsService()->contents($acl), 'slug'));
        self::assertContains('consent-doc', array_column($app->cmsService()->consentPages($acl), 'slug'));

        // 수정 폼에는 약관 칸이 없다. 그래도 저장할 때 약관 표시가 지워지면 안 된다.
        $edited = $this->post($app, '/admin/content/' . $consentDoc['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'consent-doc', 'title' => '수집 동의 (수정)',
            'content' => '<p>수집 동의 본문을 고쳤습니다.</p>', 'status' => 'published', 'show_in_menu' => '0',
            'sort_order' => '0', 'image_key' => $legalKey[1],
        ]);
        self::assertSame(303, $edited->getStatusCode());
        self::assertSame('/admin/terms?saved=1', $edited->getHeaderLine('Location'));

        $afterEdit = $app->cms()->findPageById((int) $consentDoc['id']);
        self::assertSame('수집 동의 (수정)', $afterEdit['title']);
        self::assertSame(1, (int) $afterEdit['is_consent'], 'is_consent 를 보내지 않아도 표시가 남아야 한다.');

        // 수정 요청에 약관 표시를 실어 보내도 일반 내용이 약관이 되지 않는다.
        $sneak = $this->post($app, '/admin/content/' . $saved['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'], 'slug' => 'refund-policy', 'title' => '환불 규정',
            'content' => '<p>환불 규정 내용입니다.</p>', 'status' => 'published', 'show_in_menu' => '1',
            'sort_order' => '0', 'image_key' => $imageKey, 'is_consent' => '1',
        ]);
        self::assertSame(303, $sneak->getStatusCode(), $this->body($sneak));
        self::assertSame('/admin/content?saved=1', $sneak->getHeaderLine('Location'));
        self::assertSame(0, (int) $app->cms()->findPageById((int) $saved['id'])['is_consent'],
            '수정 요청으로는 약관 여부를 바꿀 수 없다.');
    }
}
