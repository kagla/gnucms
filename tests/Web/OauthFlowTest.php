<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Oauth\ProviderInterface;
use GnuCms\Oauth\ProviderRegistry;
use GnuCms\Oauth\SocialProfile;
use GnuCms\Tests\Support\CollectingMailer;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class OauthFlowTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testMultipleStatesAreSingleUseAndCanCompleteOutOfOrder(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example.com']]);
        $app->users()->create('owner@example.com', password_hash('password123', PASSWORD_DEFAULT), '관리자', true);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));

        $first = $this->stateFrom($this->get($app, '/auth/google', ['url' => '/account']));
        $second = $this->stateFrom($this->get($app, '/auth/google', ['url' => '/notifications']));
        self::assertNotSame($first, $second);

        $completedSecond = $this->get($app, '/auth/google/callback', ['state' => $second, 'code' => 'second', 'url' => '//outside.example']);
        self::assertSame(303, $completedSecond->getStatusCode());
        self::assertSame('/notifications', $completedSecond->getHeaderLine('Location'));

        $completedFirst = $this->get($app, '/auth/google/callback', ['state' => $first, 'code' => 'first']);
        self::assertSame(303, $completedFirst->getStatusCode());
        self::assertSame('/account', $completedFirst->getHeaderLine('Location'));
        self::assertSame([], $_SESSION['oauth_state_urls']['google']);

        $replayed = $this->get($app, '/auth/google/callback', ['state' => $first, 'code' => 'replay']);
        self::assertSame(403, $replayed->getStatusCode(), $this->body($replayed));
        self::assertSame(2, $app->users()->countAll(), '관리자와 소셜 회원 한 명만 있어야 한다');
    }

    public function testLoginPageCarriesDestinationIntoSocialLoginAndPendingEmailCompletion(): void
    {
        $app = $this->makeApp(['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null], [
            'app' => ['url' => 'https://community.example.com'],
        ], 'default');
        $this->get($app, '/login');
        $app->users()->create('owner@example.com', password_hash('password123', PASSWORD_DEFAULT), '관리자', true);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));
        $mailer = new CollectingMailer();
        $app->setMailer($mailer);
        $destination = '/account?tab=profile';
        $page = $this->get($app, '/login', ['url' => $destination]);
        self::assertStringContainsString('/auth/google?' . http_build_query(['url' => $destination]), $this->body($page));
        $state = $this->stateFrom($this->get($app, '/auth/google', ['url' => $destination]));
        self::assertSame(200, $this->get($app, '/auth/google/callback', ['state' => $state, 'code' => 'unverified'])->getStatusCode());
        self::assertSame($destination, $_SESSION['oauth_pending']['return_url']);
        $sent = $this->post($app, '/auth/email', ['csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com']);
        self::assertSame(200, $sent->getStatusCode());
        self::assertCount(1, $mailer->messages);
        self::assertSame(1, preg_match('~[?&]token=([a-f0-9]+)~', $mailer->messages[0]['body'], $matches));
        $completed = $this->get($app, '/auth/complete', ['token' => $matches[1], 'url' => '//outside.example']);
        self::assertSame(303, $completed->getStatusCode());
        self::assertSame($destination, $completed->getHeaderLine('Location'));
        self::assertArrayNotHasKey('oauth_pending', $_SESSION);
    }

    #[DataProvider('connectionProvider')]
    public function testUnsupportedGithubRouteIsNotAvailable(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, [
            'oauth' => [
                'github' => ['client_id' => 'id', 'client_secret' => 'secret'],
            ],
        ]);

        self::assertSame(404, $this->get($app, '/auth/github')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testPendingEmailCannotBeResentImmediately(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example.com']]);
        $mailer = new CollectingMailer();
        $app->setMailer($mailer);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));

        $state = $this->stateFrom($this->get($app, '/auth/google'));
        $pending = $this->get($app, '/auth/google/callback', ['state' => $state, 'code' => 'unverified']);
        self::assertSame(200, $pending->getStatusCode());

        $body = ['csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com'];
        self::assertSame(200, $this->post($app, '/auth/email', $body)->getStatusCode());
        self::assertCount(1, $mailer->messages);

        $tooSoon = $this->post($app, '/auth/email', $body);
        self::assertSame(422, $tooSoon->getStatusCode());
        self::assertStringContainsString('1분 뒤', $this->body($tooSoon));
        self::assertCount(1, $mailer->messages);
    }

    #[DataProvider('connectionProvider')]
    public function testNaverWithoutEmailIsRejectedAndKakaoVerifiedEmailCompletesSignup(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example.com']]);
        $app->users()->create('owner@example.com', password_hash('password123', PASSWORD_DEFAULT), '관리자', true);
        $mailer = new CollectingMailer();
        $app->setMailer($mailer);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeNaver(), $this->fakeKakao()]));

        $naverState = $this->stateFrom($this->get($app, '/auth/naver'));
        $naver = $this->get($app, '/auth/naver/callback', [
            'state' => $naverState, 'code' => 'naver-code',
        ]);
        self::assertSame(422, $naver->getStatusCode());
        self::assertStringContainsString('이메일 주소를 제공받지 못했습니다', $this->body($naver));
        self::assertNull($app->identities()->findUser('naver', 'naver-user-1'));
        self::assertCount(0, $mailer->messages);

        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        $kakaoState = $this->stateFrom($this->get($app, '/auth/kakao'));
        self::assertSame(303, $this->get($app, '/auth/kakao/callback', [
            'state' => $kakaoState, 'code' => 'kakao-code',
        ])->getStatusCode());
        $kakaoMember = $app->users()->findByEmail('kakao-member@example.com');
        self::assertNotNull($kakaoMember);
        self::assertTrue($app->identities()->belongsToUser(
            (int) $kakaoMember['id'], 'kakao', 'kakao-user-1'
        ));
        self::assertSame(2, $app->users()->countAll());
    }

    #[DataProvider('connectionProvider')]
    public function testSocialMemberReauthenticatesToWithdrawAndCanJoinAgain(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example.com']]);
        $app->users()->create('owner@example.com', password_hash('password123', PASSWORD_DEFAULT), '관리자', true);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));

        $state = $this->stateFrom($this->get($app, '/auth/google'));
        self::assertSame(303, $this->get($app, '/auth/google/callback', [
            'state' => $state, 'code' => 'join',
        ])->getStatusCode());
        $member = $app->users()->findByEmail('social@example.com');
        self::assertNotNull($member);
        $oldId = (int) $member['id'];

        $reauthState = $this->stateFrom($this->get($app, '/auth/google', ['purpose' => 'withdraw']));
        $reauthenticated = $this->get($app, '/auth/google/callback', [
            'state' => $reauthState, 'code' => 'withdraw-reauth',
        ]);
        self::assertSame(303, $reauthenticated->getStatusCode());
        self::assertSame('/account?withdraw=verified#withdrawal', $reauthenticated->getHeaderLine('Location'));

        $withdrawn = $this->post($app, '/account/withdraw', [
            'csrf_token' => $_SESSION['csrf_token'], 'confirm_withdrawal' => '1',
        ], ['REMOTE_ADDR' => '198.51.100.91']);
        self::assertSame(303, $withdrawn->getStatusCode(), $this->body($withdrawn));
        $old = $app->users()->findById($oldId);
        self::assertSame('withdrawn', $old['status']);
        self::assertSame('198.51.100.91', $old['withdrawn_ip']);
        self::assertSame([], $app->identities()->listForUser($oldId));

        $newState = $this->stateFrom($this->get($app, '/auth/google'));
        self::assertSame(303, $this->get($app, '/auth/google/callback', [
            'state' => $newState, 'code' => 'rejoin',
        ])->getStatusCode());
        $newMember = $app->users()->findByEmail('social@example.com');
        self::assertNotNull($newMember);
        self::assertNotSame($oldId, (int) $newMember['id']);
        self::assertTrue($app->identities()->belongsToUser(
            (int) $newMember['id'], 'google', 'google-user-1'
        ));

        $oldEvents = $app->loginEvents()->recentForUser($oldId, 20);
        self::assertCount(2, $oldEvents, '최초 로그인과 탈퇴 재인증 이력을 모두 보존해야 한다');
        self::assertSame(['success', 'success'], array_column($oldEvents, 'result'));
    }

    #[DataProvider('connectionProvider')]
    public function testDisabledSocialSignupStillAllowsExistingSocialMemberLogin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig, ['app' => ['url' => 'https://community.example.com']]);
        $app->users()->create('owner@example.com', password_hash('password123', PASSWORD_DEFAULT), '관리자', true);
        $memberId = $app->users()->createSocial('social@example.com', '기존소셜회원');
        $app->identities()->attach($memberId, 'google', 'google-user-1');
        $app->cms()->saveSettings([
            'registration_enabled' => '0', 'social_registration_enabled' => '0',
        ]);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));

        $state = $this->stateFrom($this->get($app, '/auth/google'));
        $existing = $this->get($app, '/auth/google/callback', [
            'state' => $state, 'code' => 'existing',
        ]);
        self::assertSame(303, $existing->getStatusCode());
        self::assertSame($memberId, (int) $_SESSION['user_id']);

        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        $newState = $this->stateFrom($this->get($app, '/auth/google'));
        $newMember = $this->get($app, '/auth/google/callback', [
            'state' => $newState, 'code' => 'new-disabled',
        ]);
        self::assertSame(403, $newMember->getStatusCode());
        self::assertStringContainsString('현재 신규 소셜 회원가입을 받지 않습니다.', $this->body($newMember));
        self::assertNull($app->users()->findByEmail('new-social@example.com'));

        $pendingState = $this->stateFrom($this->get($app, '/auth/google'));
        $app->cmsService()->saveGeneralSettings($this->adminAcl(), [
            'site_name' => GNUCMS, 'site_tagline' => '소개', 'home_title' => '홈', 'home_intro' => '본문',
            'theme' => 'default', 'password_login_enabled' => '1', 'registration_enabled' => '0',
            'social_login_enabled' => '0', 'social_registration_enabled' => '1',
        ]);
        self::assertFalse($app->cmsService()->settings()['social_registration_enabled'],
            '소셜 로그인을 끄면 신규 소셜 가입도 자동으로 꺼져야 한다');
        $stoppedCallback = $this->get($app, '/auth/google/callback', [
            'state' => $pendingState, 'code' => 'existing',
        ]);
        self::assertSame(403, $stoppedCallback->getStatusCode());
        self::assertStringContainsString('현재 소셜 회원 로그인을 허용하지 않습니다.',
            $this->body($stoppedCallback));
    }

    private function stateFrom(\Psr\Http\Message\ResponseInterface $response): string
    {
        self::assertSame(302, $response->getStatusCode());
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertArrayHasKey('state', $query);
        return (string) $query['state'];
    }

    #[DataProvider('connectionProvider')]
    public function testSocialLoginResumesTheSavedShoppingDestination(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->users()->create('owner@example.test', '', '관리자', true);
        $app->setProviderRegistry(new ProviderRegistry([], [$this->fakeGoogle()]));
        $state = $this->stateFrom($this->get($app, '/auth/google'));
        session_start();
        \GnuCms\Web\LoginDestination::remember('/modules/shop/checkout');
        $_SESSION['shop_cart'] = ['selected-variant' => 2];
        session_write_close();
        $response = $this->get($app, '/auth/google/callback', ['state' => $state, 'code' => 'verified']);
        self::assertSame('/modules/shop/checkout', $response->getHeaderLine('Location'));
        self::assertSame(['selected-variant' => 2], $_SESSION['shop_cart']);
        self::assertArrayNotHasKey('login_destination', $_SESSION);
    }

    private function fakeGoogle(): ProviderInterface
    {
        return new class implements ProviderInterface {
            public function key(): string { return 'google'; }
            public function label(): string { return 'Google'; }
            public function authorizationUrl(string $state): string
            {
                return 'https://accounts.example.test/authorize?state=' . rawurlencode($state);
            }
            public function fetchProfile(string $code, string $state = ''): SocialProfile
            {
                if ($code === 'new-disabled') {
                    return new SocialProfile(
                        'google', 'google-user-2', 'new-social@example.com', true, '신규소셜회원'
                    );
                }
                if ($code === 'unverified') {
                    return new SocialProfile(
                        'google', 'google-unverified', 'unverified@example.com', false, '구글회원'
                    );
                }
                return new SocialProfile('google', 'google-user-1', 'social@example.com', true, '소셜회원');
            }
        };
    }

    private function fakeNaver(): ProviderInterface
    {
        return new class implements ProviderInterface {
            public function key(): string { return 'naver'; }
            public function label(): string { return '네이버'; }
            public function authorizationUrl(string $state): string
            {
                return 'https://nid.example.test/authorize?state=' . rawurlencode($state);
            }
            public function fetchProfile(string $code, string $state = ''): SocialProfile
            {
                return new SocialProfile('naver', 'naver-user-1', null, false, '네이버회원');
            }
        };
    }

    private function fakeKakao(): ProviderInterface
    {
        return new class implements ProviderInterface {
            public function key(): string { return 'kakao'; }
            public function label(): string { return '카카오'; }
            public function authorizationUrl(string $state): string
            {
                return 'https://kauth.example.test/authorize?state=' . rawurlencode($state);
            }
            public function fetchProfile(string $code, string $state = ''): SocialProfile
            {
                return new SocialProfile(
                    'kakao', 'kakao-user-1', 'kakao-member@example.com', true, '카카오회원'
                );
            }
        };
    }
}
