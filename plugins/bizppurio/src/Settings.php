<?php

declare(strict_types=1);

namespace GnuCms\Plugins\Bizppurio;

use GnuCms\Error\DomainError;
use GnuCms\Extension\RuntimePermit;
use GnuCms\Mail\SecretCipher;

final class Settings
{
    public function __construct(private Store $store, private SecretCipher $cipher, private string $storageDir)
    {
    }

    public function read(string $environment): ?array
    {
        Input::environment($environment);
        $row = $this->store->db->selectOne('SELECT * FROM ' . $this->store->db->table('bp_settings') . ' WHERE environment = ?', [$environment]);
        if ($row === null) return null;
        $payload = json_decode($this->cipher->decrypt($row['payload']), true, 32, JSON_THROW_ON_ERROR);
        return $payload + ['environment' => $environment, 'revision' => $row['revision'], 'account_type' => 'module'];
    }

    public function summary(string $environment): array
    {
        $settings = $this->read($environment);
        if ($settings === null) return ['environment' => $environment, 'account_type' => 'module', 'configured' => false, 'enabled' => false, 'api_verified' => false];
        unset($settings['password'], $settings['webhook_token']);
        return $settings + ['configured' => true, 'enabled' => $this->enabled($settings), 'api_verified' => $this->verified($settings)];
    }

    public function save(string $environment, array $input): void
    {
        Input::environment($environment);
        $this->mutate(function () use ($environment, $input): void {
            $before = $this->read($environment);
            $accountType = $input['account_type'] ?? $before['account_type'] ?? 'module';
            if (!in_array($accountType, ['module', 'web'], true)) throw DomainError::validation(['account_type' => '계정 유형을 확인해 주세요.']);
            $account = Input::text($input['account'] ?? null, '계정', 20);
            if (!preg_match('/^[A-Za-z0-9_.@-]+$/D', $account)) throw DomainError::validation(['account' => '계정 형식을 확인해 주세요.']);
            $password = Input::text($input['password'] ?? '', '비밀번호', 500, true);
            if ($password === '') $password = $before['password'] ?? '';
            if ($password === '') throw DomainError::validation(['password' => '계정 비밀번호를 입력해 주세요.']);
            $sender = Input::text($input['senderkey'] ?? null, '발신프로필 키', 40);
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $sender)) throw DomainError::validation(['senderkey' => '발신프로필 키를 확인해 주세요.']);
            $from = Input::text($input['from'] ?? null, '발신번호', 16);
            if (!preg_match('/^\d{8,16}$/D', $from)) throw DomainError::validation(['from' => '발신번호는 숫자로 입력해 주세요.']);
            $testPhone = Input::phone($input['test_phone'] ?? null);
            $allowedIps = [];
            $ips = Input::text($input['webhook_ips'] ?? '', '웹훅 송신 IP', 1000, true);
            foreach (preg_split('/[\s,]+/', trim($ips), -1, PREG_SPLIT_NO_EMPTY) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) throw DomainError::validation(['webhook_ips' => '확인된 송신 IP를 공백으로 구분해 입력해 주세요.']);
                $allowedIps[] = $ip;
            }
            $changedIdentity = $before !== null && ($account !== $before['account'] || $sender !== $before['senderkey']);
            if ($changedIdentity) {
                $pending = $this->store->db->selectOne('SELECT id FROM ' . $this->store->db->table('bp_dispatches')
                    . " WHERE environment = ? AND (submission IN ('sending','unknown') OR (submission = 'accepted' AND delivery IN ('pending','uncertain'))) LIMIT 1", [$environment]);
                if ($pending !== null) throw DomainError::validation(['account' => '미완료 발송을 확인한 뒤 계정·프로필을 변경해 주세요.']);
                // 다른 프로필의 승인 템플릿을 잘못 사용하는 것을 막는다.
                $this->store->db->update('bp_templates', ['enabled' => 0], 'environment = :env', ['env' => $environment]);
            }
            $payload = ['account_type' => $accountType, 'account' => $account, 'password' => $password, 'senderkey' => $sender, 'from' => $from,
                'test_phone' => $testPhone, 'test_only' => ($input['test_only'] ?? '1') !== '0',
                'webhook_ips' => array_values(array_unique($allowedIps)),
                'webhook_token' => $before['webhook_token'] ?? bin2hex(random_bytes(32))];
            (new RuntimePermit($this->storageDir))->set($this->permitKey($environment), null);
            (new RuntimePermit($this->storageDir))->set($this->permitKey($environment) . '/api-verified', null);
            $row = ['revision' => bin2hex(random_bytes(16)), 'payload' => $this->cipher->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))];
            if ($before === null) $this->store->insert('bp_settings', ['environment' => $environment] + $row);
            else $this->store->db->update('bp_settings', $row, 'environment = :env', ['env' => $environment]);
        });
    }

    public function setEnabled(string $environment, bool $enabled): void
    {
        $this->mutate(function () use ($environment, $enabled): void {
            $settings = $this->read($environment);
            if ($enabled && $settings === null) throw DomainError::validation(['settings' => '연결 설정을 먼저 저장해 주세요.']);
            if ($enabled && $settings['account_type'] === 'web' && !$this->verified($settings)) {
                throw DomainError::validation(['connection' => '웹발송 계정은 API 인증 연결을 확인한 뒤 GNUCMS 발송을 허용할 수 있습니다.']);
            }
            (new RuntimePermit($this->storageDir))->set($this->permitKey($environment), $enabled ? $settings['revision'] : null);
        });
    }

    public function enabled(array $settings): bool
    {
        return (new RuntimePermit($this->storageDir))->allowed($this->permitKey($settings['environment']), $settings['revision'])
            && (($settings['account_type'] ?? 'module') !== 'web' || $this->verified($settings));
    }

    public function verified(array $settings): bool
    {
        return (new RuntimePermit($this->storageDir))->allowed($this->permitKey($settings['environment']) . '/api-verified', $settings['revision']);
    }

    /** 현재 설정으로 실제 인증을 다시 확인한다. 기존 캐시만으로 권한을 판단하지 않는다. */
    public function checkConnection(string $environment, Api $api): void
    {
        $this->mutate(function () use ($environment, $api): void {
            $settings = $this->read($environment);
            if ($settings === null) throw DomainError::validation(['settings' => '계정을 먼저 저장해 주세요.']);
            $permits = new RuntimePermit($this->storageDir);
            $permits->set($this->permitKey($environment), null);
            $permits->set($this->permitKey($environment) . '/api-verified', null);
            $api->token($settings, true);
            $permits->set($this->permitKey($environment) . '/api-verified', $settings['revision']);
        });
    }

    public function lockPath(): string
    {
        return $this->storageDir . '/extensions-runtime/bizppurio/settings.lock';
    }

    public function mutate(callable $work): mixed
    {
        return Locks::run($this->storageDir . '/upgrade.lock', fn () => Locks::run($this->lockPath(), $work), LOCK_SH);
    }

    private function permitKey(string $environment): string
    {
        return Schema::KEY . '/' . Input::environment($environment);
    }
}
