<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;

/** 비즈뿌리오 발송 엔진의 조립과 공개 API. 생성자에서 쓰기·외부 통신을 하지 않는다. */
final class MessagingService
{
    public readonly Settings $settings;
    public readonly Templates $templates;
    public readonly Dispatch $dispatch;
    public readonly Results $results;
    public readonly Api $api;
    public readonly KapiTemplates $remoteTemplates;

    public function __construct(App $app, ?HttpTransport $transport = null)
    {
        $db = $app->db();
        $storage = $app->storageDir();
        $secret = (string) $app->config('auth.secret', '');
        $cipher = new SecretCipher($secret);
        $store = new Store($db);
        $this->settings = new Settings($store, $cipher, $storage);
        $this->templates = new Templates($store, $this->settings);
        $transport ??= new StreamTransport();
        $this->api = new Api($transport, $cipher, $storage);
        $this->remoteTemplates = new KapiTemplates($transport, $this->settings, $this->templates);
        $this->dispatch = new Dispatch($store, $this->settings, $this->templates, $this->api, $cipher, $secret, $storage);
        $this->results = new Results($store, $this->settings, $this->dispatch, $storage);
    }

    /** 테이블은 코어 스키마가 보장한다. 플러그인 시절의 "데이터 설치" 단계는 없다. */
    public function ready(): bool { return true; }
    public function requireReady(): void {}

    public function connect(string $environment): void { $this->settings->checkConnection($environment, $this->api); }

    public function status(string $environment): array
    {
        Input::environment($environment);
        return array_intersect_key($this->settings->summary($environment), array_flip([
            'configured', 'enabled', 'api_verified', 'account_type', 'account', 'test_only', 'test_phone', 'kapi_configured',
        ]));
    }

    public function preview(array $input): array
    {
        if (!is_array($input['variables'] ?? [])) throw DomainError::validation(['variables' => '변수 입력을 확인해 주세요.']);
        return $this->templates->preview(Input::id($input['template_id'] ?? null), $input['variables'] ?? [], isset($input['revision']) ? Input::id($input['revision']) : null);
    }

    public function send(array $input): array { return $this->dispatch->send($input); }
    public function history(array $input): array { return $this->dispatch->history($input); }
    public function detail(string $id): array { return $this->channelDetail($id, false); }
    public function retry(string $id): array { $this->detail($id); return $this->dispatch->retry($id); }
    public function refresh(string $id): void { $this->detail($id); $this->dispatch->refreshResult($id); }
    public function purge(): int { return $this->dispatch->purge(); }

    public function previewText(array $input): array
    {
        $environment = Input::environment($input['environment'] ?? null);
        $phone = Input::phone($input['phone'] ?? null);
        $text = TextMessage::normalize($input);
        $settings = $this->settings->read($environment);
        if ($settings === null) throw DomainError::validation(['settings' => '알림톡·문자 설정에 계정과 발신번호를 먼저 저장해 주세요.']);
        if ($settings['test_only'] && $phone !== $settings['test_phone']) throw DomainError::validation(['phone' => '현재 지정된 테스트 번호로만 발송할 수 있습니다.']);
        return $text + ['environment' => $environment, 'phone' => $phone, 'from' => $settings['from'], 'config_revision' => $settings['revision']];
    }

    public function sendText(array $input): array { return $this->dispatch->sendText($input); }
    public function textHistory(array $input): array { return $this->dispatch->history($input, true); }
    public function textDetail(string $id): array { return $this->channelDetail($id, true); }
    public function retryText(string $id): array { $this->textDetail($id); return $this->dispatch->retry($id); }
    public function refreshText(string $id): void { $this->textDetail($id); $this->dispatch->refreshResult($id); }
    public function purgeText(): int { return $this->dispatch->purge(true); }

    private function channelDetail(string $id, bool $text): array
    {
        // 상세는 알림톡·문자 모두 수신번호 원문을 준다. 목록은 마스킹만 보여 준다.
        $detail = $this->dispatch->detail($id, true);
        if (!in_array($detail['channel'], $text ? ['sms', 'lms'] : ['at'], true)) throw DomainError::notFound('이 채널의 발송 이력이 아닙니다.');
        return $detail;
    }

    public function templateAction(string $action, array $input): array
    {
        return match ($action) {
            'list' => $this->templates->all(Input::environment($input['environment'] ?? null)),
            'get' => $this->templates->get(Input::id($input['id'] ?? null)),
            'save' => $this->templates->save(Input::environment($input['environment'] ?? null), $input),
            'remote-list' => $this->remoteTemplates->listing(Input::environment($input['environment'] ?? null), $input),
            'remote-detail' => $this->remoteTemplates->detail(Input::environment($input['environment'] ?? null), $input),
            'remote-import' => $this->remoteTemplates->import(Input::environment($input['environment'] ?? null), $input),
            'remote-import-all' => $this->remoteTemplates->importAll(Input::environment($input['environment'] ?? null), $input),
            'enable' => $this->templates->setEnabled(Input::id($input['id'] ?? null), Input::id($input['revision'] ?? null), ($input['enabled'] ?? '') === '1'),
            'delete' => $this->templates->delete(Input::id($input['id'] ?? null), Input::id($input['revision'] ?? null)),
            default => throw DomainError::validation(['action' => '템플릿 작업을 확인해 주세요.']),
        };
    }
}
