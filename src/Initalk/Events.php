<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;

/** 요청별 상태·발송·결제·환불 이력. 상세 화면의 타임라인이다. */
final class Events
{
    public function __construct(private Connection $db)
    {
    }

    public function record(string $requestId, string $type, string $actor, string $note = ''): void
    {
        $this->db->insert('initalk_events', ['request_id' => $requestId, 'type' => $type, 'actor' => mb_substr($actor, 0, 100),
            'note' => mb_substr($note, 0, 1000), 'created_at' => Clock::timestamp()]);
    }

    public function forRequest(string $requestId): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('initalk_events') . ' WHERE request_id = ? ORDER BY id', [$requestId]);
    }
}
