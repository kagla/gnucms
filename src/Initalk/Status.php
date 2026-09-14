<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

/** 결제 요청 상태와 허용 전이(스펙 §3). */
final class Status
{
    public const CREATED = 'created';
    public const WAITING = 'waiting';
    public const PAID = 'paid';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';
    public const REFUNDED = 'refunded';

    public const LABELS = [
        self::CREATED => '결제생성', self::WAITING => '결제대기중', self::PAID => '결제완료',
        self::EXPIRED => '기한만료', self::CANCELLED => '결제전취소', self::REFUNDED => '환불완료',
    ];

    /** 결제 페이지에서 결제를 시작할 수 있는 상태. */
    public const OPEN = [self::CREATED, self::WAITING];

    public static function canSend(string $status): bool { return in_array($status, [self::CREATED, self::WAITING, self::EXPIRED], true); }
    public static function canCancel(string $status): bool { return in_array($status, [self::CREATED, self::WAITING, self::EXPIRED], true); }
    public static function canPay(string $status): bool { return in_array($status, self::OPEN, true); }
    public static function canRefund(string $status): bool { return $status === self::PAID; }

    public static function label(string $status): string { return self::LABELS[$status] ?? $status; }
}
