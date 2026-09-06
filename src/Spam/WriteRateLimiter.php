<?php

declare(strict_types=1);

namespace GnuCms\Spam;

use GnuCms\Auth\Acl;
use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use GnuCms\Support\IpAddress;

/** 회원 ID 또는 비회원 IP별 글·댓글 등록 빈도를 DB에서 원자적으로 제한한다. */
final class WriteRateLimiter
{
    private Connection $db;

    /** @var array<string,array<int,array{0:int,1:int}>> action => [[window seconds, limit], ...] */
    private array $rules;

    public function __construct(Connection $db, array $rules)
    {
        $this->db = $db;
        $this->rules = $rules;
    }

    public function consume(string $action, Acl $acl, ?string $clientIp): void
    {
        // 관리자가 공지·정리 작업을 하다가 운영 기능에서 잠기지 않게 한다.
        if ($acl->isGlobalAdmin()) {
            return;
        }

        $rules = array_values(array_filter(
            $this->rules[$action] ?? [],
            static fn (array $rule): bool => ($rule[0] ?? 0) > 0 && ($rule[1] ?? 0) > 0
        ));
        if ($rules === []) {
            return;
        }

        $actorKey = $this->actorKey($acl, $clientIp);
        $now = Clock::timestamp();

        // 긴 창부터 같은 순서로 잠가 교착 가능성을 줄인다. 하나라도 막히면 앞선 예약도 롤백된다.
        usort($rules, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $this->db->transaction(function () use ($action, $actorKey, $now, $rules): void {
            foreach ($rules as $rule) {
                $this->consumeRule($action, $actorKey, (int) $rule[0], (int) $rule[1], $now);
            }
        });

        // 행 수는 작성자×규칙 수로 제한되지만 떠난 방문자의 행은 남으므로 가끔 정리한다.
        if (random_int(1, 100) === 1) {
            $this->db->delete('write_rate_limits', 'window_started_at < :cutoff', [
                'cutoff' => $now - 172800,
            ]);
        }
    }

    private function consumeRule(string $action, string $actorKey, int $seconds, int $limit, int $now): void
    {
        $table = $this->db->table('write_rate_limits');
        $expired = $now - $seconds;
        $params = [
            'action' => $action,
            'actor' => $actorKey,
            'seconds' => $seconds,
            'started' => $now,
            'hits' => 1,
            'expired1' => $expired,
            'expired2' => $expired,
            'expired3' => $expired,
            'now2' => $now,
            'limit1' => $limit,
        ];

        if ($this->db->dialect()->name() === 'mysql') {
            // MySQL은 충돌 UPDATE에 WHERE를 붙일 수 없어, 한도에 닿으면 같은 값을 대입해
            // rowCount=0이 되게 한다. hit_count를 먼저 계산해야 만료 전 시작 시각을 볼 수 있다.
            $changed = $this->db->execute(
                'INSERT INTO ' . $table
                . ' (action, actor_key, window_seconds, window_started_at, hit_count)'
                . ' VALUES (:action, :actor, :seconds, :started, :hits)'
                . ' ON DUPLICATE KEY UPDATE'
                . ' hit_count = IF(window_started_at <= :expired1, 1,'
                . ' IF(hit_count < :limit1, hit_count + 1, hit_count)),'
                . ' window_started_at = IF(window_started_at <= :expired2, :now2, window_started_at)',
                array_diff_key($params, ['expired3' => true])
            );
        } else {
            // SQLite는 ON CONFLICT 문법을 지원한다. WHERE가 거짓이면
            // 기존 행을 건드리지 않아 rowCount=0으로 정확히 거절할 수 있다.
            $changed = $this->db->execute(
                'INSERT INTO ' . $table
                . ' (action, actor_key, window_seconds, window_started_at, hit_count)'
                . ' VALUES (:action, :actor, :seconds, :started, :hits)'
                . ' ON CONFLICT (action, actor_key, window_seconds) DO UPDATE SET'
                . ' hit_count = CASE WHEN ' . $table . '.window_started_at <= :expired1'
                . ' THEN 1 ELSE ' . $table . '.hit_count + 1 END,'
                . ' window_started_at = CASE WHEN ' . $table . '.window_started_at <= :expired2'
                . ' THEN :now2 ELSE ' . $table . '.window_started_at END'
                . ' WHERE ' . $table . '.window_started_at <= :expired3'
                . ' OR ' . $table . '.hit_count < :limit1',
                $params
            );
        }

        if ($changed !== 0) {
            return;
        }

        $row = $this->db->selectOne(
            'SELECT window_started_at FROM ' . $table
            . ' WHERE action = ? AND actor_key = ? AND window_seconds = ?',
            [$action, $actorKey, $seconds]
        );
        $retryAfter = max(1, ((int) ($row['window_started_at'] ?? $now)) + $seconds - $now);
        $subject = $action === 'comment' ? '댓글을' : '글을';

        throw DomainError::tooManyRequests(
            $subject . ' 너무 빠르게 등록하고 있습니다. ' . $this->waitLabel($retryAfter) . ' 후 다시 시도해 주세요.',
            $retryAfter
        );
    }

    private function actorKey(Acl $acl, ?string $clientIp): string
    {
        $identity = $acl->identity();
        if (!$identity->isGuest()) {
            return 'member:' . hash('sha256', (string) $identity->sub());
        }

        return 'guest:' . hash('sha256', IpAddress::normalize($clientIp) ?? 'unknown');
    }

    private function waitLabel(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . '초';
        }
        if ($seconds < 3600) {
            return (int) ceil($seconds / 60) . '분';
        }

        return (int) ceil($seconds / 3600) . '시간';
    }
}
