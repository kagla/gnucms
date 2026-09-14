<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;

/** 사람이 보는 주문번호 IT-YYYYMMDD-NNNN. 날짜는 Asia/Seoul, 순번은 하루 단위다. */
final class RequestNumber
{
    public static function next(Connection $db, int $timestamp): string
    {
        $prefix = 'IT-' . (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Ymd') . '-';
        $row = $db->selectOne('SELECT MAX(number) AS last FROM ' . $db->table('initalk_requests') . ' WHERE number LIKE ?', [$prefix . '%']);
        $sequence = is_string($row['last'] ?? null) && $row['last'] !== '' ? (int) substr($row['last'], -4) + 1 : 1;
        if ($sequence > 9999) throw DomainError::serviceUnavailable('오늘 만들 수 있는 결제 요청 번호를 모두 사용했습니다.');
        return $prefix . sprintf('%04d', $sequence);
    }
}
