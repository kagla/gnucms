<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;

/** 승인·취소 요청을 전송하기 전에 기록한다. 카드 정보와 PG 응답 원문은 저장하지 않는다. */
final class Journal
{
    private SecretCipher $cipher;
    private string $table;

    public function __construct(private Settings $settings)
    {
        $this->cipher = new SecretCipher((string) $settings->app->config('auth.secret'));
        $this->table = 'pay_' . $settings->provider . '_transactions';
    }

    public function read(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw DomainError::validation(['order' => '주문번호를 확인해 주세요.']);
        $db = $this->settings->app->db();
        $row = $db->selectOne('SELECT payload FROM ' . $db->table($this->table) . ' WHERE id = ?', [$id]);
        return $row === null ? [] : json_decode($this->cipher->decrypt($row['payload']), true, 32, JSON_THROW_ON_ERROR);
    }

    public function change(string $id, callable $change): array
    {
        $this->read($id);
        $db = $this->settings->app->db();
        // 결제 설정 행이 항상 있으므로 최초 주문 기록 생성도 두 DB에서 직렬화된다.
        return $db->transaction(function () use ($db, $id, $change): array {
            $db->execute('UPDATE ' . $db->table('pay_' . $this->settings->provider . '_settings') . ' SET payload = payload WHERE id IN (?, ?)', ['test', 'live']);
            $before = $this->read($id);
            $after = $change($before);
            $payload = $this->cipher->encrypt(json_encode($after, JSON_THROW_ON_ERROR));
            if ($before === []) $db->execute('INSERT INTO ' . $db->table($this->table) . ' (id, payload) VALUES (?, ?)', [$id, $payload]);
            else $db->update($this->table, ['payload' => $payload], 'id = :id', ['id' => $id]);
            return $after;
        });
    }
}
