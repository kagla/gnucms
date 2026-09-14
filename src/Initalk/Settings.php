<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;

/** site_settings의 initalk.* 키. 알림톡 템플릿 선택은 저장 시 변수·버튼을 검증한다. */
final class Settings
{
    /** 알림톡 템플릿에 제공하는 변수 이름(스펙 §7). */
    public const VARIABLES = ['상점명', '구매자명', '요청일', '상품명', '금액', '결제기한', '고객센터', '결제토큰', '주문번호'];
    public const TOKEN_VARIABLE = '#{결제토큰}';

    public function __construct(private App $app)
    {
    }

    /** @return array{store_name:string,support_phone:string,expiry_hours:int,environment:string,template:array{test:string,live:string},settlement_days:int} */
    public function read(): array
    {
        $all = $this->app->cms()->settings();
        $hours = (int) ($all['initalk.expiry_hours'] ?? 48);
        $days = (int) ($all['initalk.settlement_days'] ?? 3);
        $environment = $all['initalk.environment'] ?? 'test';
        return [
            'store_name' => (string) ($all['initalk.store_name'] ?? ($all['site_name'] ?? 'GNUCMS')),
            'support_phone' => (string) ($all['initalk.support_phone'] ?? ''),
            'expiry_hours' => $hours >= 1 && $hours <= 720 ? $hours : 48,
            'environment' => in_array($environment, ['test', 'live'], true) ? (string) $environment : 'test',
            'template' => ['test' => (string) ($all['initalk.template.test'] ?? ''), 'live' => (string) ($all['initalk.template.live'] ?? '')],
            'settlement_days' => $days >= 0 && $days <= 60 ? $days : 3,
        ];
    }

    public function templateId(string $environment): string
    {
        return $this->read()['template'][$environment] ?? '';
    }

    public function save(array $input): void
    {
        $storeName = trim((string) ($input['store_name'] ?? ''));
        if ($storeName === '' || mb_strlen($storeName) > 40 || preg_match('/[\r\n]/', $storeName)) throw DomainError::validation(['store_name' => '상점명을 1~40자로 입력해 주세요.']);
        $support = trim((string) ($input['support_phone'] ?? ''));
        if ($support !== '' && !preg_match('/^[0-9-]{7,20}$/D', $support)) throw DomainError::validation(['support_phone' => '고객센터 번호는 숫자와 하이픈으로 입력해 주세요.']);
        $hours = $input['expiry_hours'] ?? '';
        if (!is_scalar($hours) || !preg_match('/^\d{1,3}$/D', (string) $hours) || (int) $hours < 1 || (int) $hours > 720) throw DomainError::validation(['expiry_hours' => '기본 결제기한은 1~720시간입니다.']);
        $environment = $input['environment'] ?? '';
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '테스트 또는 운영 환경을 선택해 주세요.']);
        $days = $input['settlement_days'] ?? '';
        if (!is_scalar($days) || !preg_match('/^\d{1,2}$/D', (string) $days) || (int) $days > 60) throw DomainError::validation(['settlement_days' => '정산 주기는 0~60일입니다.']);
        $templates = [];
        foreach (['test', 'live'] as $env) {
            $id = trim((string) ($input['template_' . $env] ?? ''));
            if ($id !== '') $this->assertTemplate($env, $id);
            $templates[$env] = $id;
        }
        $this->app->cms()->saveSettings([
            'initalk.store_name' => $storeName, 'initalk.support_phone' => $support, 'initalk.expiry_hours' => (string) (int) $hours,
            'initalk.environment' => $environment, 'initalk.template.test' => $templates['test'], 'initalk.template.live' => $templates['live'],
            'initalk.settlement_days' => (string) (int) $days,
        ]);
    }

    /** 템플릿이 이 환경의 사용 가능한 템플릿이고, 변수가 제공 집합 안이며, #{결제토큰}이 든 웹링크 버튼이 있어야 한다. */
    public function assertTemplate(string $environment, string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw DomainError::validation(['template_' . $environment => '알림톡 템플릿을 선택해 주세요.']);
        try {
            $template = $this->app->messaging()->templateAction('get', ['id' => $id]);
        } catch (DomainError $e) {
            throw DomainError::validation(['template_' . $environment => '알림톡 템플릿을 찾을 수 없습니다.']);
        }
        if ($template['environment'] !== $environment) throw DomainError::validation(['template_' . $environment => '선택한 환경의 템플릿이 아닙니다.']);
        if (!$template['enabled']) throw DomainError::validation(['template_' . $environment => '사용 중지된 템플릿입니다.']);
        $unknown = array_diff($template['variables'], self::VARIABLES);
        if ($unknown !== []) throw DomainError::validation(['template_' . $environment => '이니톡 결제가 제공하지 않는 변수가 있습니다: ' . implode(', ', $unknown)]);
        $hasToken = false;
        foreach ($template['buttons'] as $button) {
            foreach (['url_mobile', 'url_pc'] as $field) {
                if (str_contains((string) ($button[$field] ?? ''), self::TOKEN_VARIABLE)) $hasToken = true;
            }
        }
        if (!$hasToken) throw DomainError::validation(['template_' . $environment => '#{결제토큰}이 들어간 웹링크 버튼이 필요합니다.']);
        return $template;
    }
}
