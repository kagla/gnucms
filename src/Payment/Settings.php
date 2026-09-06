<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;
use GnuCms\Extension\RuntimePermit;
use GnuCms\Mail\SecretCipher;

/** 이전 주문의 조회·환불에 필요한 암호화 설정 판을 보존한다. */
final class Settings
{
    public const PROVIDERS = ['inicis' => 'KG이니시스', 'kcp' => 'NHN KCP', 'kspay' => 'KSPay (KSNET)', 'toss' => '토스페이먼츠'];
    private SecretCipher $cipher;
    private string $table;

    public function __construct(public readonly App $app, public readonly string $provider)
    {
        if (!isset(self::PROVIDERS[$provider])) throw DomainError::internal('결제 플러그인을 확인해 주세요.');
        $this->table = 'pay_' . $provider . '_settings';
        $this->cipher = new SecretCipher((string) $app->config('auth.secret'));
    }

    public function key(): string { return 'plugins/payment-' . $this->provider; }
    public function ready(): bool { return $this->schema()->current($this->key(), 2); }
    private function schema(): PackageSchema { return new PackageSchema($this->app->db(), $this->app->storageDir()); }

    public function install(): void
    {
        $this->schema()->install($this->key(), 2, [$this->table, 'pay_' . $this->provider . '_transactions'], function ($db): void {
            $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table($this->table)
                . ' (id VARCHAR(32) PRIMARY KEY, payload ' . $db->dialect()->typeMap()['{TEXT}'] . ' NOT NULL)'
                . $db->dialect()->tableSuffix());
            $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table('pay_' . $this->provider . '_transactions')
                . ' (id VARCHAR(32) PRIMARY KEY, payload ' . $db->dialect()->typeMap()['{TEXT}'] . ' NOT NULL)'
                . $db->dialect()->tableSuffix());
        });
    }

    public static function environment(string $environment): string
    {
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '결제 환경을 확인해 주세요.']);
        return $environment;
    }

    private function row(string $id): ?array
    {
        if (!$this->ready()) return null;
        $row = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table($this->table) . ' WHERE id = ?', [$id]);
        return $row === null ? null : json_decode($this->cipher->decrypt($row['payload']), true, 16, JSON_THROW_ON_ERROR);
    }

    public function current(string $environment): ?array
    {
        $row = $this->row(self::environment($environment));
        return ($row['integration'] ?? '') === 'direct-v1' ? $row : null;
    }

    public function revision(string $revision): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $revision)) throw DomainError::validation(['revision' => '결제 설정 판을 확인해 주세요.']);
        $row = $this->row($revision);
        if (($row['integration'] ?? '') !== 'direct-v1') throw DomainError::serviceUnavailable('직접 연동 설정으로 생성한 주문이 아닙니다.');
        return $row;
    }

    /** 주문의 상점은 보존하고 같은 상점의 인증키·서버 주소 변경을 과거 주문에 적용한다. */
    public function credentials(string $revision): array
    {
        $row = $this->revision($revision);
        $current = $this->current($row['environment']);
        if ($current !== null && $current['merchant_id'] === $row['merchant_id']) {
            foreach (ProviderConfig::fields($this->provider) as $key => $field) {
                if ($field['secret'] || $key === 'client_ip') $row[$key] = $current[$key];
            }
        }
        return $row;
    }

    public function available(string $environment): bool
    {
        $row = $this->current($environment);
        return $row !== null && (new RuntimePermit($this->app->storageDir()))->allowed($this->key() . '/' . $environment, $row['revision']);
    }

    public function requireEnabled(string $environment): void
    {
        if (!$this->available($environment)) throw DomainError::serviceUnavailable('결제 플러그인의 API 실행을 허용해 주세요.');
    }

    public function summary(string $environment): array
    {
        $row = $this->current($environment);
        return ['configured' => $row !== null, 'enabled' => $this->available($environment),
            'merchant_id' => $row['merchant_id'] ?? '', 'client_ip' => $row['client_ip'] ?? '',
            'revision' => $row['revision'] ?? '', 'environment' => $environment];
    }

    public function save(string $environment, array $input): void
    {
        ExecutionLock::settings($this->app->storageDir(), fn () => $this->saveUnlocked($environment, $input));
    }

    private function saveUnlocked(string $environment, array $input): void
    {
        self::environment($environment);
        if (!$this->ready()) throw DomainError::serviceUnavailable('결제 플러그인 데이터를 먼저 설치해 주세요.');
        $before = $this->row($environment);
        $data = ProviderConfig::validate($this->provider, $input, $this->current($environment) ?? [], $environment)
            + ['integration' => 'direct-v1', 'environment' => $environment, 'revision' => bin2hex(random_bytes(16))];
        (new RuntimePermit($this->app->storageDir()))->set($this->key() . '/' . $environment, null);
        $payload = $this->cipher->encrypt(json_encode($data, JSON_THROW_ON_ERROR));
        $db = $this->app->db();
        $db->transaction(function () use ($db, $data, $environment, $before, $payload): void {
            $db->execute('INSERT INTO ' . $db->table($this->table) . ' (id, payload) VALUES (?, ?)', [$data['revision'], $payload]);
            if ($before === null) $db->execute('INSERT INTO ' . $db->table($this->table) . ' (id, payload) VALUES (?, ?)', [$environment, $payload]);
            else $db->update($this->table, ['payload' => $payload], 'id = :id', ['id' => $environment]);
        });
    }

    public function enable(string $environment, bool $enabled): void
    {
        ExecutionLock::settings($this->app->storageDir(), function () use ($environment, $enabled): void {
            $row = $this->current($environment);
            if ($enabled && $row === null) throw DomainError::validation(['settings' => '설정을 먼저 저장해 주세요.']);
            (new RuntimePermit($this->app->storageDir()))->set($this->key() . '/' . $environment, $enabled ? $row['revision'] : null);
        });
    }
}
