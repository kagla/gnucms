<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Account\ConsentTrace;
use GnuCms\Error\DomainError;
use GnuCms\Support\IpAddress;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use GnuCms\View\View;
use GnuCms\Web\LoginRedirect;
use Psr\Http\Message\UploadedFileInterface;

final class AuthController
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function loginForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $returnUrl = LoginRedirect::fromRequest($request);
        if ($returnUrl !== null && !$this->app->guestAcl()->identity()->isGuest()) {
            return $response->withStatus(303)->withHeader('Location', $returnUrl);
        }
        return View::fromRequest($request)->render($response, 'auth/login', [
            'errors' => [], 'values' => [], 'unverified_email' => null, 'turnstile_required' => false,
            'return_url' => $returnUrl,
        ]);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $returnUrl = LoginRedirect::fromRequest($request);
        $identifier = isset($input['email']) && is_scalar($input['email'])
            ? strtolower(trim((string) $input['email'])) : null;
        $throttleKey = $identifier !== null && filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false
            ? 'login:' . $identifier : null;
        try {
            if ($throttleKey !== null) {
                // 이미 잠긴 요청으로 Cloudflare를 호출하지 않는다. AccountService도 비밀번호 검사 전에 재확인한다.
                $this->app->passwordThrottle()->assertNotLocked($throttleKey, 'email');
                if ($this->app->passwordThrottle()->requiresCaptcha($throttleKey)) {
                    $this->verifyTurnstile($request, $input, 'login', true);
                }
            }
            $user = $this->app->accountService()->authenticate($input);
        } catch (DomainError $e) {
            $known = $identifier === null ? null : $this->app->users()->findByEmail($identifier);
            $this->recordLogin($request, $known === null ? null : (int) $known['id'], $identifier, 'failure');
            if ($e->status() !== 422) {
                throw $e;
            }
            $details = $e->details();
            $email = isset($input['email']) && is_scalar($input['email']) ? (string) $input['email'] : '';
            return View::fromRequest($request)->render(
                $response->withStatus(422),
                'auth/login',
                [
                    'errors' => $details,
                    'values' => ['email' => $email],
                    'return_url' => $returnUrl,
                    // 비밀번호까지 맞았는데 인증만 안 된 사람에게는 '다시 보내기' 를 내준다.
                    'unverified_email' => isset($details['unverified']) ? $email : null,
                    'turnstile_required' => $throttleKey !== null
                        && $this->app->passwordThrottle()->requiresCaptcha($throttleKey),
                ]
            );
        }
        if (!$this->app->cmsService()->settings()['password_login_enabled'] && !$user['is_admin']) {
            $this->recordLogin($request, (int) $user['id'], $identifier, 'failure');
            throw DomainError::forbidden('현재 일반 회원 로그인을 허용하지 않습니다.');
        }
        $this->recordLogin($request, (int) $user['id'], $identifier, 'success');
        $this->storeSession($user);

        return $response->withStatus(303)->withHeader('Location', LoginRedirect::destination($request, $returnUrl));
    }

    public function registerForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertAnyRegistrationEnabled();
        return View::fromRequest($request)->render($response, 'auth/register', [
            'errors' => [], 'values' => [], 'legal' => $this->registrationLegal(),
        ]);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertRegularRegistrationEnabled();
        $input = $this->input($request);
        $this->assertCsrf($input);
        $avatarFile = null;
        try {
            $this->verifyTurnstile($request, $input, 'register');
            $upload = $request->getUploadedFiles()['profile_image'] ?? null;
            if ($upload instanceof UploadedFileInterface && $upload->getError() !== UPLOAD_ERR_NO_FILE) {
                $avatarFile = $this->app->avatars()->storeUpload($upload);
            }
            $user = $this->app->accountService()->register($input, $this->consentTrace($request));
        } catch (DomainError $e) {
            $this->app->avatars()->delete($avatarFile);
            if ($e->status() !== 422) {
                throw $e;
            }
            $values = ['email' => $input['email'] ?? ''];
            foreach ($this->app->cmsService()->consentDocuments('signup') as $doc) {
                $values['agree_' . $doc['id']] = isset($input['agree_' . $doc['id']]);
            }
            return View::fromRequest($request)->render(
                $response->withStatus(422),
                'auth/register',
                ['errors' => $e->details(), 'values' => $values, 'legal' => $this->registrationLegal()]
            );
        } catch (\Throwable $e) {
            $this->app->avatars()->delete($avatarFile);
            throw $e;
        }
        if ($avatarFile !== null && $user['newly_created']) {
            try {
                $this->app->users()->updateAvatar((int) $user['id'], $avatarFile, 'upload');
            } catch (\Throwable $e) {
                $this->app->avatars()->delete($avatarFile);
                throw $e;
            }
        } elseif ($avatarFile !== null) {
            $this->app->avatars()->delete($avatarFile);
        }
        if ($user['newly_created'] && $user['is_admin'] && $user['email_verified']) {
            $this->recordLogin($request, (int) $user['id'], (string) $user['email'], 'success');
            $this->storeSession($user);
            return $this->redirectTo($request, $response, 'admin.index');
        }
        return View::fromRequest($request)->render($response, 'auth/check_email');
    }

    private function assertAnyRegistrationEnabled(): void
    {
        $settings = $this->app->cmsService()->settings();
        if (!$settings['registration_enabled'] && !$settings['social_registration_enabled']) {
            throw DomainError::forbidden('현재 새 회원가입을 받지 않습니다.');
        }
    }

    private function assertRegularRegistrationEnabled(): void
    {
        if (!$this->app->cmsService()->settings()['registration_enabled']) {
            throw DomainError::forbidden('현재 일반 회원가입을 받지 않습니다.');
        }
    }

    private function registrationLegal(): ?array
    {
        return $this->app->users()->countAll() === 0 ? null : $this->app->cmsService()->legalDocuments();
    }

    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        $this->app->accountService()->verifyEmail(is_scalar($token) ? (string) $token : '');
        return View::fromRequest($request)->render($response, 'auth/verified');
    }

    /** 인증 메일을 다시 보낸다. 없는 이메일이나 이미 인증된 계정이면 조용히 같은 화면을 낸다. */
    public function resendVerification(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $returnUrl = LoginRedirect::fromRequest($request);
        $email = isset($input['email']) && is_scalar($input['email']) ? (string) $input['email'] : '';
        try {
            $this->verifyTurnstile($request, $input, 'verification_resend');
            $this->app->accountService()->resendVerification($email);
        } catch (DomainError $e) {
            if ($e->status() === 422 && isset($e->details()['turnstile'])) {
                return View::fromRequest($request)->render($response->withStatus(422), 'auth/login', [
                    'errors' => $e->details(), 'values' => ['email' => $email],
                    'unverified_email' => $email, 'turnstile_required' => false,
                    'return_url' => $returnUrl,
                ]);
            }
            if ($e->status() !== 422) {
                throw $e;
            }
            // 메일이 안 나가면 로그인 화면에서 그 사실을 말해 준다. 조용히 '보냈다' 고 하면 사람이 기다리기만 한다.
            return View::fromRequest($request)->render($response->withStatus(422), 'auth/login', [
                'errors' => ['email' => '인증 메일을 보내지 못했습니다. 잠시 뒤 다시 시도해 주세요.'],
                'values' => ['email' => $email], 'unverified_email' => $email, 'turnstile_required' => false,
                'return_url' => $returnUrl,
            ]);
        }
        return View::fromRequest($request)->render($response, 'auth/check_email');
    }

    public function forgotForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return View::fromRequest($request)->render($response, 'auth/forgot', ['errors' => [], 'values' => []]);
    }

    public function forgot(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $email = isset($input['email']) && is_scalar($input['email']) ? (string) $input['email'] : '';
        try {
            $this->verifyTurnstile($request, $input, 'password_reset');
            $this->app->accountService()->requestPasswordReset($email);
        } catch (DomainError $e) {
            if ($e->status() !== 422) {
                throw $e;
            }
            return View::fromRequest($request)->render($response->withStatus(422), 'auth/forgot', [
                'errors' => $e->details(), 'values' => ['email' => $email],
            ]);
        }
        return View::fromRequest($request)->render($response, 'auth/reset_sent');
    }

    public function resetForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $request->getQueryParams()['token'] ?? '';
        return View::fromRequest($request)->render($response, 'auth/reset', [
            'token' => is_scalar($token) ? (string) $token : '',
            'errors' => [],
        ]);
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        try {
            $this->app->accountService()->resetPassword($input);
        } catch (DomainError $e) {
            if ($e->status() !== 422) {
                throw $e;
            }
            return View::fromRequest($request)->render($response->withStatus(422), 'auth/reset', [
                'token' => isset($input['token']) && is_scalar($input['token']) ? (string) $input['token'] : '',
                'errors' => $e->details(),
            ]);
        }
        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        return View::fromRequest($request)->render($response, 'auth/reset_done');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        unset($_SESSION['user_id'], $_SESSION['session_epoch']);
        session_regenerate_id(true);

        return $this->homeRedirect($request, $response);
    }

    private function input(ServerRequestInterface $request): array
    {
        $input = $request->getParsedBody();
        return is_array($input) ? $input : [];
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

    private function recordLogin(ServerRequestInterface $request, ?int $userId,
        ?string $identifier, string $result): void
    {
        $server = $request->getServerParams();
        $ip = \GnuCms\Support\IpAddress::fromServer($server);
        $ua = $request->getHeaderLine('User-Agent');
        $this->app->loginEvents()->record(
            $userId, $identifier, 'password', $result, $ip, $ua === '' ? null : $ua
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

    private function verifyTurnstile(ServerRequestInterface $request, array $input,
        string $action, bool $failOpen = false): void
    {
        $token = isset($input['cf-turnstile-response']) && is_scalar($input['cf-turnstile-response'])
            ? (string) $input['cf-turnstile-response'] : '';
        try {
            $this->app->turnstile()->verify(
                $token,
                IpAddress::fromServer($request->getServerParams()),
                $action
            );
        } catch (DomainError $e) {
            if (!$failOpen || $e->code() !== 'SERVICE_UNAVAILABLE') {
                throw $e;
            }
        }
    }

    private function storeSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_epoch'] = $user['session_epoch'];
    }

    private function homeRedirect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->redirectTo($request, $response, 'boards.index');
    }

    private function redirectTo(ServerRequestInterface $request, ResponseInterface $response, string $route): ResponseInterface
    {
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor($route);
        return $response->withHeader('Location', $url)->withStatus(303);
    }
}
