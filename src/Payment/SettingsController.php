<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** 설정 → 결제(이니시스). 전체 관리자 전용이며 POST는 세션 CSRF를 검사한다. */
final class SettingsController
{
    public function __construct(private Settings $settings)
    {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->settings->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        foreach ($input as $value) if (!is_string($value) && !is_int($value)) throw DomainError::validation(['input' => '단일 입력값을 사용해 주세요.']);
        $environment = is_string($input['environment'] ?? null) ? Settings::environment($input['environment']) : 'test';
        $notice = '';
        $errors = [];
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'save') {
                    $this->settings->save($environment, $input);
                    $notice = '설정을 저장했습니다. 상점 코드와 환경을 확인한 뒤 API 실행을 허용해 주세요.';
                } elseif (in_array($action, ['enable', 'disable'], true)) {
                    $this->settings->enable($environment, $action === 'enable');
                    $notice = $action === 'enable' ? 'API 실행을 허용했습니다.' : 'API 실행을 정지했습니다.';
                } else {
                    throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                }
            }
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $errors = $e->status() >= 500 ? ['설정 저장에 실패했습니다. 암호화 키와 저장소 상태를 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]);
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/payment_settings',
            ['fields' => ProviderConfig::fields($this->settings->provider), 'manual' => ProviderConfig::manual($this->settings->provider),
                'label' => Settings::PROVIDERS[$this->settings->provider], 'environment' => $environment,
                'settings' => $this->settings->summary($environment), 'notice' => $notice, 'errors' => $errors]
        );
    }
}
