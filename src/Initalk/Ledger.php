<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;

/** 결제사 조회·환불 응답으로 확인된 승인·환불만 기록하는 확정 원장. 같은 참조는 한 번만 넣는다. */
final class Ledger
{
    public function __construct(private Connection $db)
    {
    }

    public function record(string $kind, string $requestId, int $amount, int $at, string $reference): bool
    {
        $id = substr(hash('sha256', $kind . ':' . $requestId . ':' . $reference), 0, 64);
        if ($this->db->selectOne('SELECT id FROM ' . $this->db->table('initalk_ledger') . ' WHERE id = ?', [$id]) !== null) return false;
        $this->db->insert('initalk_ledger', ['id' => $id, 'request_id' => $requestId, 'kind' => $kind, 'amount' => $amount, 'at' => $at,
            'reference' => mb_substr($reference, 0, 100), 'created_at' => Clock::timestamp()]);
        return true;
    }

    public function forRequest(string $requestId): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('initalk_ledger') . ' WHERE request_id = ? ORDER BY at, id', [$requestId]);
    }

    /** 기간 안의 원장 행에 요청의 주문번호·상품명·구매자명·마스킹 번호를 붙인다. */
    public function between(string $environment, int $from, int $until): array
    {
        $l = $this->db->table('initalk_ledger');
        $r = $this->db->table('initalk_requests');
        return $this->db->select('SELECT l.*, r.number, r.product_name, r.buyer_name, r.phone_mask FROM ' . $l . ' l JOIN ' . $r . ' r ON r.id = l.request_id'
            . ' WHERE r.environment = ? AND l.at >= ? AND l.at <= ? ORDER BY l.at, l.id', [$environment, $from, $until]);
    }
}
