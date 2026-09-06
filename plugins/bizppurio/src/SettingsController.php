<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;
use GnuCms\View\PhpView;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;
use Throwable;

final class SettingsController
{
    public function __construct(private Service $service)
    {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        $environment = Input::environment($input['environment'] ?? 'test');
        $notice = '';
        $errors = [];
        $webhook = null;
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'install') {
                    $this->service->install();
                    $notice = '알림톡 데이터를 준비했습니다. 계정 설정을 저장해 주세요.';
                } else {
                    $this->service->requireReady();
                    if ($action === 'reveal-password') {
                        $settings = $this->service->settings->read($environment);
                        if ($settings === null || ($input['revision'] ?? null) !== $settings['revision']
                            || ($input['account'] ?? null) !== $settings['account']) {
                            throw DomainError::validation(['password' => '계정 설정이 변경되었습니다. 화면을 새로 연 뒤 확인해 주세요.']);
                        }
                        $response->getBody()->write(json_encode(['password' => $settings['password']], JSON_THROW_ON_ERROR));
                        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
                            ->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
                            ->withHeader('X-Content-Type-Options', 'nosniff');
                    } elseif ($action === 'save') {
                        $this->service->settings->save($environment, $input);
                        $notice = '설정을 저장했습니다. 발송은 정지 상태입니다.';
                    } elseif (in_array($action, ['enable', 'disable'], true)) {
                        $this->service->settings->setEnabled($environment, $action === 'enable');
                        $notice = $action === 'enable' ? '발송을 허용했습니다.' : '발송을 정지했습니다. 결과 수신은 계속됩니다.';
                    } elseif ($action === 'connect') {
                        $this->service->connect($environment);
                        $notice = 'API 인증을 확인했습니다. 발송을 허용한 뒤 테스트 번호로 수신을 확인해 주세요. 메시지는 발송하지 않았습니다.';
                    } elseif ($action === 'webhook') {
                        $settings = $this->service->settings->read($environment);
                        if ($settings === null) throw DomainError::validation(['settings' => '계정을 먼저 저장해 주세요.']);
                        $webhook = RouteContext::fromRequest($request)->getBasePath() . '/plugins/bizppurio/result?'
                            . http_build_query(['environment' => $environment, 'token' => $settings['webhook_token']]);
                        $notice = '아래 경로 앞에 사이트의 HTTPS 도메인을 붙여 결과 수신 URL로 등록해 주세요.';
                    } else throw DomainError::validation(['action' => '설정 작업을 확인해 주세요.']);
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
        $ready = $this->service->ready();
        try {
            $settings = $ready ? $this->service->settings->summary($environment) : ['configured' => false, 'enabled' => false];
        } catch (Throwable $e) {
            $settings = ['configured' => false, 'enabled' => false];
            $errors = ['저장된 설정을 읽지 못했습니다. 암호화 키와 DB 복구 상태를 확인해 주세요.'];
            $response = $response->withStatus(503);
        }
        $routes = RouteContext::fromRequest($request);
        $view = new PhpView([dirname(__DIR__) . '/templates'], $routes->getRouteParser(), $routes->getBasePath(),
            static fn (string $path): string => '', static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        return $view->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'settings', [
            'base' => $routes->getBasePath(), 'environment' => $environment, 'ready' => $ready, 'settings' => $settings,
            'notice' => $notice, 'errors' => $errors, 'webhook' => $webhook, 'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }
}
