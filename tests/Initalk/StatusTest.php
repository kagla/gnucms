<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\Initalk\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testTransitionsFollowTheSpecDiagram(): void
    {
        self::assertSame(['created', 'waiting', 'paid', 'expired', 'cancelled', 'refunded'], array_keys(Status::LABELS));
        foreach (['created', 'waiting', 'expired'] as $status) {
            self::assertTrue(Status::canSend($status), $status);
            self::assertTrue(Status::canCancel($status), $status);
        }
        foreach (['paid', 'cancelled', 'refunded'] as $status) {
            self::assertFalse(Status::canSend($status), $status);
            self::assertFalse(Status::canCancel($status), $status);
        }
        self::assertSame(['created', 'waiting'], Status::OPEN);
        self::assertTrue(Status::canPay('created'));
        self::assertTrue(Status::canPay('waiting'));
        self::assertFalse(Status::canPay('expired'));
        self::assertTrue(Status::canRefund('paid'));
        self::assertFalse(Status::canRefund('refunded'));
        self::assertSame('결제대기중', Status::LABELS['waiting']);
    }
}
