<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/** 설정 → 알림톡·문자. 전체 관리자 전용이며 POST는 세션 CSRF를 검사한다. */
final class SettingsController
{
    public function __construct(private App $app)
    {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $service = $this->app->messaging();
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        $environment = Input::environment($input['environment'] ?? 'test');
        $notice = '';
        $errors = [];
        $webhook = null;
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'reveal-password' || $action === 'reveal-kapi-key') {
                    // 저장된 비밀값은 화면 HTML에 넣지 않고, 눈 아이콘이 현재 설정 판을 확인한 JSON 요청으로만 받는다.
                    $field = $action === 'reveal-password' ? 'password' : 'kapi_key';
                    $settings = $service->settings->read($environment);
                    if ($settings === null || ($input['revision'] ?? null) !== $settings['revision'] || ($input['account'] ?? null) !== $settings['account']) {
                        throw DomainError::validation([$field => '계정 설정이 변경되었습니다. 화면을 새로 연 뒤 확인해 주세요.']);
                    }
                    if (($settings[$field] ?? '') === '') throw DomainError::validation([$field => '저장된 값이 없습니다. 화면을 새로 연 뒤 확인해 주세요.']);
                    $response->getBody()->write(json_encode([$field => $settings[$field]], JSON_THROW_ON_ERROR));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
                        ->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
                        ->withHeader('X-Content-Type-Options', 'nosniff');
                } elseif ($action === 'save') {
                    // 결과 송신 IP에 GNUCMS 서버 자신의 IP를 넣으면 비즈뿌리오의 결과 전달이 모두 거부되므로 막는다.
                    $own = $request->getServerParams()['SERVER_ADDR'] ?? '';
                    if (is_string($own) && $own !== '' && in_array($own, preg_split('/[\s,]+/', trim((string) ($input['webhook_ips'] ?? '')), -1, PREG_SPLIT_NO_EMPTY), true)) {
                        throw DomainError::validation(['webhook_ips' => '결과 송신 IP에는 비즈뿌리오가 결과를 보내는 IP를 적습니다. GNUCMS 서버 자신의 IP(' . $own . ')는 넣을 수 없습니다. 모르면 비워 두세요.']);
                    }
                    $service->settings->save($environment, $input);
                    $notice = '설정을 저장했습니다. 발송은 정지 상태입니다.';
                } elseif ($action === 'copy-from') {
                    // 계정 하나로 검수·운영을 함께 쓰는 경우 다른 환경의 계정·키·발신번호를 그대로 가져온다. 발송 허용은 꺼진 채 시작한다.
                    $source = Input::environment($input['source'] ?? '');
                    $label = $source === 'live' ? '운영' : '테스트';
                    if ($source === $environment) throw DomainError::validation(['source' => '같은 환경으로는 복사할 수 없습니다.']);
                    $from = $service->settings->read($source);
                    if ($from === null) throw DomainError::validation(['source' => $label . ' 환경에 저장된 설정이 없습니다.']);
                    $target = $service->settings->read($environment);
                    if ($environment === 'test' && ($target['test_phone'] ?? '') === '') throw DomainError::validation(['test_phone' => '테스트 환경은 테스트 수신번호가 필요합니다. 먼저 저장한 뒤 복사해 주세요.']);
                    $service->settings->save($environment, ['account_type' => $from['account_type'], 'account' => $from['account'], 'password' => $from['password'],
                        'senderkey' => $from['senderkey'], 'kapi_key' => $from['kapi_key'] ?? '', 'from' => $from['from'], 'test_phone' => $target['test_phone'] ?? '',
                        'webhook_ips' => implode(' ', $from['webhook_ips'] ?? [])]);
                    $notice = $label . ' 환경의 설정을 복사했습니다. 인증 연결 확인 후 발송을 허용해 주세요. 템플릿 사본은 환경별이므로 템플릿 탭에서 따로 가져옵니다.';
                } elseif (in_array($action, ['enable', 'disable'], true)) {
                    $service->settings->setEnabled($environment, $action === 'enable');
                    $notice = $action === 'enable' ? '발송을 허용했습니다.' : '발송을 정지했습니다. 결과 수신은 계속됩니다.';
                } elseif ($action === 'connect') {
                    $service->connect($environment);
                    $notice = 'API 인증을 확인했습니다. 발송을 허용한 뒤 발송 화면에서 수신을 확인해 주세요. 메시지는 발송하지 않았습니다.';
                } elseif ($action === 'webhook') {
                    $settings = $service->settings->read($environment);
                    if ($settings === null) throw DomainError::validation(['settings' => '계정을 먼저 저장해 주세요.']);
                    $webhook = RouteContext::fromRequest($request)->getBasePath() . '/messaging/bizppurio/result?'
                        . http_build_query(['environment' => $environment, 'token' => $settings['webhook_token']]);
                    $notice = '아래 경로 앞에 사이트의 HTTPS 도메인을 붙여 결과 수신 URL로 등록해 주세요.';
                } else {
                    throw DomainError::validation(['action' => '설정 작업을 확인해 주세요.']);
                }
            }
        } catch (DomainError $e) {
            $errors = $e->code() === 'BIZPPURIO_AUTH' ? [$e->getMessage()]
                : ($e->status() >= 500 ? ['작업을 완료하지 못했습니다. 서버 연결·저장소 상태를 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]));
            $response = $response->withStatus($e->status());
        } catch (Throwable $e) {
            $errors = ['작업을 완료하지 못했습니다. 서버 연결·저장소 상태를 확인해 주세요.'];
            $response = $response->withStatus(503);
        }
        try {
            $settings = $service->settings->summary($environment);
        } catch (Throwable $e) {
            $settings = ['configured' => false, 'enabled' => false];
            $errors = ['저장된 설정을 읽지 못했습니다. 암호화 키와 DB 복구 상태를 확인해 주세요.'];
            $response = $response->withStatus(503);
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/messaging_settings',
            ['base' => RouteContext::fromRequest($request)->getBasePath(), 'environment' => $environment, 'ready' => true,
                'settings' => $settings, 'notice' => $notice, 'errors' => $errors, 'webhook' => $webhook,
                'other_configured' => $service->settings->read($environment === 'live' ? 'test' : 'live') !== null]
        );
    }
}
