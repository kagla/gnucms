<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Account\ConsentTrace;
use GnuCms\Error\DomainError;
use GnuCms\Oauth\SocialProfile;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use GnuCms\View\View;
use GnuCms\Web\LoginRedirect;

final class OauthController
{
    private const TTL = 600;
    private const MAX_PENDING_STATES = 5;
    private const EMAIL_RESEND_SECONDS = 60;

    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $key = (string) ($args['provider'] ?? '');
        $provider = $this->app->providerRegistry()->get($key);
        $purpose = ($request->getQueryParams()['purpose'] ?? '') === 'withdraw' ? 'withdraw' : 'login';
        if ($purpose === 'login' && !$this->app->cmsService()->settings()['social_login_enabled']) {
            throw DomainError::forbidden('현재 소셜 회원 로그인을 허용하지 않습니다.');
        }
        if ($purpose === 'withdraw') {
            $identity = $this->app->guestAcl()->identity();
            if ($identity->isGuest()) {
                throw DomainError::unauthorized('로그인이 필요합니다.');
            }
            $connected = array_filter(
                $this->app->identities()->listForUser((int) $identity->sub()),
                static fn(array $row): bool => (string) $row['provider'] === $key
            );
            if ($connected === []) {
                throw DomainError::forbidden('이 회원에게 연결된 소셜 계정이 아닙니다.');
            }
        }
        $state = bin2hex(random_bytes(32));
        $hash = hash('sha256', $state);
        $states = isset($_SESSION['oauth_states'][$key]) && is_array($_SESSION['oauth_states'][$key])
            ? $_SESSION['oauth_states'][$key] : [];
        $now = time();
        $states = array_filter($states, static fn($expiresAt): bool => (int) $expiresAt >= $now);
        $states[$hash] = $now + self::TTL;
        if (count($states) > self::MAX_PENDING_STATES) {
            $states = array_slice($states, -self::MAX_PENDING_STATES, null, true);
        }
        $_SESSION['oauth_states'][$key] = $states;
        // 복귀 주소를 state마다 보관해 여러 로그인 창의 목적지가 섞이지 않게 한다.
        $urls = $_SESSION['oauth_state_urls'][$key] ?? [];
        $urls = is_array($urls) ? array_intersect_key($urls, $states) : [];
        if ($purpose === 'login') {
            $urls[$hash] = LoginRedirect::fromRequest($request);
        }
        $_SESSION['oauth_state_urls'][$key] = $urls;
        if ($purpose === 'withdraw') {
            $_SESSION['oauth_state_purposes'][$key][$hash] = 'withdraw';
        }

        return $response->withHeader('Location', $provider->authorizationUrl($state))->withStatus(302);
    }

    public function callback(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $key = (string) ($args['provider'] ?? '');
        $query = $request->getQueryParams();
        $state = isset($query['state']) && is_scalar($query['state']) ? (string) $query['state'] : '';
        $hash = hash('sha256', $state);
        $states = isset($_SESSION['oauth_states'][$key]) && is_array($_SESSION['oauth_states'][$key])
            ? $_SESSION['oauth_states'][$key] : [];
        $expiresAt = $states[$hash] ?? null;
        unset($states[$hash]);
        $_SESSION['oauth_states'][$key] = $states;
        $purpose = $_SESSION['oauth_state_purposes'][$key][$hash] ?? 'login';
        unset($_SESSION['oauth_state_purposes'][$key][$hash]);
        $returnUrl = $_SESSION['oauth_state_urls'][$key][$hash] ?? null;
        unset($_SESSION['oauth_state_urls'][$key][$hash]);
        if ($state === '' || $expiresAt === null || (int) $expiresAt < time()) {
            $this->recordSocialLogin($request, null, null, $key, 'failure');
            throw DomainError::forbidden('소셜 로그인 요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
        if ($purpose === 'login' && !$this->app->cmsService()->settings()['social_login_enabled']) {
            $this->recordSocialLogin($request, null, null, $key, 'failure');
            throw DomainError::forbidden('현재 소셜 회원 로그인을 허용하지 않습니다.');
        }
        if (isset($query['error'])) {
            $this->recordSocialLogin($request, null, null, $key, 'failure');
            throw DomainError::validation(['oauth' => '소셜 로그인이 취소되었습니다.']);
        }
        $code = isset($query['code']) && is_scalar($query['code']) ? (string) $query['code'] : '';
        try {
            $profile = $this->app->socialAuthService()->profile($key, $code, $state);
        } catch (DomainError $e) {
            $this->recordSocialLogin($request, null, null, $key, 'failure');
            throw $e;
        }
        if ($purpose === 'withdraw') {
            $identity = $this->app->guestAcl()->identity();
            $userId = $identity->isGuest() ? 0 : (int) $identity->sub();
            if ($userId < 1 || !$this->app->identities()->belongsToUser(
                $userId, $profile->provider, $profile->uid
            )) {
                $this->recordSocialLogin($request, $userId > 0 ? $userId : null,
                    $profile->email, $key, 'failure');
                throw DomainError::forbidden('현재 회원에게 연결된 소셜 계정으로 인증해 주세요.');
            }
            $_SESSION['withdraw_reauth'] = ['user_id' => $userId, 'expires_at' => time() + 300];
            $this->recordSocialLogin($request, $userId, $profile->email, $key, 'success');
            $url = RouteContext::fromRequest($request)->getRouteParser()
                ->urlFor('account.edit', [], ['withdraw' => 'verified']);
            return $response->withHeader('Location', $url . '#withdrawal')->withStatus(303);
        }
        try {
            $user = $this->app->socialAuthService()->resolve($profile, $this->consentTrace($request));
        } catch (DomainError $e) {
            $this->recordSocialLogin($request, null, $profile->email, $key, 'failure');
            throw $e;
        }
        if ($user !== null) {
            $this->recordSocialLogin($request, (int) $user['id'], $profile->email, $key, 'success');
            $this->storeSession($user);
            return $this->loginRedirect($request, $response, $returnUrl);
        }

        $_SESSION['oauth_pending'] = [
            'profile' => $profile->toArray(),
            'expires_at' => time() + self::TTL,
            'return_url' => $returnUrl,
        ];
        return View::fromRequest($request)->render($response, 'auth/social_email', [
            'provider_label' => $this->app->providerRegistry()->get($key)->label(),
            'errors' => [],
            'values' => ['email' => $profile->email ?? ''],
        ]);
    }

    public function email(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertSocialLoginEnabled();
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        $this->assertCsrf($input);
        $pending = $this->pending();
        $profile = SocialProfile::fromArray((array) $pending['profile']);
        $email = isset($input['email']) && is_scalar($input['email']) ? (string) $input['email'] : '';
        $token = bin2hex(random_bytes(32));
        try {
            $lastSentAt = (int) ($pending['email_sent_at'] ?? 0);
            if ($lastSentAt > time() - self::EMAIL_RESEND_SECONDS) {
                throw DomainError::validation([
                    'email' => '확인 메일은 1분 뒤 다시 보낼 수 있습니다.',
                ]);
            }
            $email = $this->app->socialAuthService()->sendPendingEmail($profile, $email, $token);
        } catch (DomainError $e) {
            if ($e->status() !== 422) {
                throw $e;
            }
            return View::fromRequest($request)->render($response->withStatus(422), 'auth/social_email', [
                'provider_label' => $this->app->providerRegistry()->get($profile->provider)->label(),
                'errors' => $e->details(),
                'values' => ['email' => $email],
            ]);
        }
        $_SESSION['oauth_pending']['email'] = $email;
        $_SESSION['oauth_pending']['token_hash'] = hash('sha256', $token);
        $_SESSION['oauth_pending']['email_sent_at'] = time();

        return View::fromRequest($request)->render($response, 'auth/social_email_sent');
    }

    public function complete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertSocialLoginEnabled();
        $pending = $this->pending();
        $profile = SocialProfile::fromArray((array) $pending['profile']);
        $token = $request->getQueryParams()['token'] ?? '';
        $token = is_scalar($token) ? (string) $token : '';
        if ($token === '' || empty($pending['token_hash'])
            || !hash_equals((string) $pending['token_hash'], hash('sha256', $token))) {
            throw DomainError::validation(['token' => '확인 링크가 올바르지 않거나 이미 사용되었습니다.']);
        }
        $user = $this->app->socialAuthService()->complete(
            $profile,
            (string) ($pending['email'] ?? ''),
            $this->consentTrace($request)
        );
        $this->recordSocialLogin($request, (int) $user['id'], (string) ($pending['email'] ?? ''),
            $profile->provider, 'success');
        unset($_SESSION['oauth_pending']);
        $this->storeSession($user);

        return $this->loginRedirect($request, $response, $pending['return_url'] ?? null);
    }

    private function pending(): array
    {
        $pending = $_SESSION['oauth_pending'] ?? null;
        if (!is_array($pending) || (int) ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['oauth_pending']);
            throw DomainError::validation(['oauth' => '소셜 로그인 요청이 만료되었습니다. 다시 시도해 주세요.']);
        }
        return $pending;
    }

    private function assertSocialLoginEnabled(): void
    {
        if (!$this->app->cmsService()->settings()['social_login_enabled']) {
            throw DomainError::forbidden('현재 소셜 회원 로그인을 허용하지 않습니다.');
        }
    }

    /** 동의 증적. 프록시를 신뢰하지 않으므로 REMOTE_ADDR 만 쓴다. */
    private function consentTrace(ServerRequestInterface $request): ConsentTrace
    {
        $server = $request->getServerParams();
        $ip = isset($server['REMOTE_ADDR']) && is_scalar($server['REMOTE_ADDR'])
            ? (string) $server['REMOTE_ADDR'] : null;
        $ua = $request->getHeaderLine('User-Agent');

        return new ConsentTrace($ip, $ua === '' ? null : $ua);
    }

    private function recordSocialLogin(ServerRequestInterface $request, ?int $userId,
        ?string $identifier, string $provider, string $result): void
    {
        $ip = \GnuCms\Support\IpAddress::fromServer($request->getServerParams());
        $ua = $request->getHeaderLine('User-Agent');
        $this->app->loginEvents()->record(
            $userId, $identifier, $provider, $result, $ip, $ua === '' ? null : $ua
        );
    }

    private function assertCsrf(array $input): void
    {
        $expected = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        $given = isset($input['csrf_token']) && is_scalar($input['csrf_token']) ? (string) $input['csrf_token'] : '';
        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            throw DomainError::forbidden('요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
    }

    private function storeSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_epoch'] = $user['session_epoch'];
    }

    private function loginRedirect(ServerRequestInterface $request, ResponseInterface $response, mixed $returnUrl): ResponseInterface
    {
        $url = LoginRedirect::destination($request, $returnUrl);
        return $response->withHeader('Location', $url)->withStatus(303);
    }
}
