<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\View\PhpView;
use GnuCms\View\View;
use Slim\Routing\RouteContext;

final class SettingsController
{
    public function __construct(private Settings $settings) {}

    public function handle($request, $response)
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        foreach ($input as $value) if (!is_string($value) && !is_int($value)) throw DomainError::validation(['input' => '단일 입력값을 사용해 주세요.']);
        $environment = is_string($input['environment'] ?? null) ? Settings::environment($input['environment']) : 'test';
        $notice = ''; $errors = [];
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'install') $this->settings->install();
                elseif ($action === 'save') $this->settings->save($environment, $input);
                elseif (in_array($action, ['enable', 'disable'], true)) {
                    if ($action === 'enable' && !in_array($this->settings->provider, ['inicis', 'toss'], true)) throw DomainError::validation(['payment' => '직접 연동 규격 확인이 완료되지 않아 결제 실행을 허용할 수 없습니다.']);
                    $this->settings->enable($environment, $action === 'enable');
                }
                else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                $notice = $action === 'save' ? '설정을 저장했습니다. 상점 코드와 환경을 확인한 뒤 API 실행을 허용해 주세요.' : '처리했습니다.';
            }
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $errors = $e->status() >= 500 ? ['설정 저장에 실패했습니다. 데이터 설치와 암호화 키를 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]);
        }
        $route = RouteContext::fromRequest($request);
        $siteView = View::fromRequest($request);
        $paths = [__DIR__ . '/templates'];
        if ($siteView instanceof PhpView && $siteView->exists('extensions/payment/settings')) {
            $paths = [dirname($siteView->resolve('extensions/payment/settings')), ...$paths];
        }
        $view = new PhpView($paths, $route->getRouteParser(), $route->getBasePath(), static fn ($p) => '', static fn ($p) => '');
        return $view->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'settings', [
            'fields' => ProviderConfig::fields($this->settings->provider), 'manual' => ProviderConfig::manual($this->settings->provider),
            'integration_ready' => in_array($this->settings->provider, ['inicis', 'toss'], true),
            'label' => Settings::PROVIDERS[$this->settings->provider], 'key' => $this->settings->key(), 'environment' => $environment,
            'ready' => $this->settings->ready(), 'settings' => $this->settings->summary($environment), 'notice' => $notice,
            'errors' => $errors, 'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }
}
