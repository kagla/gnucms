<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Mail\SecretCipher;

/** 패키지 조립과 관리 서비스. 생성자에서 쓰기·외부 통신을 하지 않는다. */
final class Service
{
    public readonly PackageSchema $schema;
    public readonly Settings $settings;
    public readonly Templates $templates;
    public readonly Dispatch $dispatch;
    public readonly Results $results;
    public readonly Api $api;

    public function __construct(App $app, ?HttpTransport $transport = null)
    {
        $db = $app->db();
        $storage = $app->storageDir();
        $secret = (string) $app->config('auth.secret', '');
        $cipher = new SecretCipher($secret);
        $store = new Store($db);
        $this->schema = new PackageSchema($db, $storage);
        $this->settings = new Settings($store, $cipher, $storage);
        $this->templates = new Templates($store, $this->settings);
        $this->api = new Api($transport ?? new StreamTransport(), $cipher, $storage);
        $this->dispatch = new Dispatch($store, $this->settings, $this->templates, $this->api, $cipher, $secret, $storage);
        $this->results = new Results($store, $this->settings, $this->dispatch, $storage);
    }

    public function ready(): bool { return $this->schema->current(Schema::KEY, Schema::VERSION); }
    public function requireReady(): void { if (!$this->ready()) throw DomainError::serviceUnavailable('플러그인 설정에서 데이터를 먼저 설치·갱신해 주세요.'); }
    public function install(): void { Schema::install($this->schema); }

    public function connect(string $environment): void { $this->requireReady(); $this->settings->checkConnection($environment, $this->api); }

    public function status(string $environment): array
    {
        Input::environment($environment);
        if (!$this->ready()) return ['configured' => false, 'enabled' => false, 'api_verified' => false, 'account_type' => 'module'];
        return array_intersect_key($this->settings->summary($environment), array_flip([
            'configured', 'enabled', 'api_verified', 'account_type', 'account', 'test_only', 'test_phone',
        ]));
    }

    public function preview(array $input): array
    {
        $this->requireReady();
        if (!is_array($input['variables'] ?? [])) throw DomainError::validation(['variables' => '변수 입력을 확인해 주세요.']);
        return $this->templates->preview(Input::id($input['template_id'] ?? null), $input['variables'] ?? [], isset($input['revision']) ? Input::id($input['revision']) : null);
    }

    public function send(array $input): array { $this->requireReady(); return $this->dispatch->send($input); }
    public function history(array $input): array { $this->requireReady(); return $this->dispatch->history($input); }
    public function detail(string $id): array { $this->requireReady(); return $this->dispatch->detail($id); }
    public function retry(string $id): array { $this->requireReady(); return $this->dispatch->retry($id); }
    public function refresh(string $id): void { $this->requireReady(); $this->dispatch->refreshResult($id); }
    public function purge(): int { $this->requireReady(); return $this->dispatch->purge(); }

    public function templateAction(string $action, array $input): array
    {
        $this->requireReady();
        return match ($action) {
            'list' => $this->templates->all(Input::environment($input['environment'] ?? null)),
            'get' => $this->templates->get(Input::id($input['id'] ?? null)),
            'save' => $this->templates->save(Input::environment($input['environment'] ?? null), $input),
            default => throw DomainError::validation(['action' => '템플릿 작업을 확인해 주세요.']),
        };
    }
}
