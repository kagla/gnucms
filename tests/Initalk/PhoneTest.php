<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\Error\DomainError;
use GnuCms\Initalk\Phone;
use PHPUnit\Framework\TestCase;

final class PhoneTest extends TestCase
{
    public function testNormalizeMaskFormatAndHash(): void
    {
        self::assertSame('01012345678', Phone::normalize('010-1234-5678'));
        self::assertSame('01012345678', Phone::normalize(' 010 1234 5678 '));
        self::assertSame('010-****-5678', Phone::mask('01012345678'));
        self::assertSame('010-1234-5678', Phone::format('01012345678'));
        self::assertSame('011-***-5678', Phone::mask('0111235678'));
        $secret = bin2hex(random_bytes(16));
        self::assertSame(Phone::hash('01012345678', $secret), Phone::hash('01012345678', $secret));
        self::assertNotSame(Phone::hash('01012345678', $secret), Phone::hash('01012345679', $secret));
        self::assertSame(64, strlen(Phone::hash('01012345678', $secret)));
        self::assertSame('', Phone::mask(''));
        self::assertSame('', Phone::format(''));
        foreach (['0212345678', '010123', '', null, ['x']] as $bad) {
            try {
                Phone::normalize($bad);
                self::fail('rejected');
            } catch (DomainError $e) {
                self::assertSame(422, $e->status());
            }
        }
    }
}
