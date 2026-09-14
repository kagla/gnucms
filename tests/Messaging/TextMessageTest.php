<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

use GnuCms\Error\DomainError;
use GnuCms\Messaging\TextMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextMessageTest extends TestCase
{
    public function testEucKrLimitsAndNewlinesPreserveTheTransmittedUtf8Content(): void
    {
        foreach ([['sms', str_repeat('가', 45), '', 90], ['sms', str_repeat('a', 90), '', 90],
            ['lms', str_repeat('가', 1000), str_repeat('나', 32), 2000], ['lms', str_repeat('a', 2000), '', 2000],
            ['sms', "가\r\nA\rB", '', 6]] as [$type, $message, $subject, $bytes]) {
            $result = TextMessage::normalize(compact('type', 'message', 'subject'));
            self::assertSame($bytes, $result['bytes']);
            self::assertSame($type, $result['type']);
            self::assertSame(str_replace(["\r\n", "\r"], "\n", $message), $result['content'][$type]['message']);
            if ($subject === '') self::assertArrayNotHasKey('subject', $result['content'][$type]);
            else self::assertSame($subject, $result['content'][$type]['subject']);
        }
    }

    public static function invalidMessages(): array
    {
        return [
            'sms korean overflow' => [['message' => str_repeat('가', 45) . 'a']],
            'sms ascii overflow' => [['message' => str_repeat('a', 91)]],
            'lms overflow' => [['type' => 'lms', 'message' => str_repeat('가', 1000) . 'a']],
            'subject overflow' => [['type' => 'lms', 'subject' => str_repeat('가', 32) . 'a']],
            'sms subject' => [['subject' => '제목']], 'multiline subject' => [['type' => 'lms', 'subject' => "가\n나"]],
            'empty' => [['message' => '']], 'whitespace' => [['message' => " \n "]],
            'sms overflow with unicode' => [['message' => str_repeat('가', 46) . 'ʕ']],
            'lms overflow with unicode' => [['type' => 'lms', 'message' => str_repeat('가', 1001) . 'ʕ']],
            'subject overflow with unicode' => [['type' => 'lms', 'subject' => str_repeat('가', 33) . 'ʕ']],
            'unicode character cap' => [['type' => 'lms', 'message' => str_repeat('ʕ', 2001)]],
            'control' => [['message' => "abc\x1b"]], 'null byte' => [['message' => "a\0b"]],
            'invalid utf8' => [['message' => "\xff"]], 'array' => [['message' => []]],
            'type array' => [['type' => []]], 'unsupported type' => [['type' => 'mms']],
        ];
    }

    public function testUnicodeArtworkAndEmojiRemainUnchangedWithUnknownByteLength(): void
    {
        foreach (['sms', 'lms'] as $type) {
            foreach ([TextFixtures::ART, '안내 😀', '힣 ᆺ •̄'] as $message) {
                $subject = $type === 'lms' ? 'ʕ 안내 😀' : '';
                $result = TextMessage::normalize(compact('type', 'message', 'subject'));
                self::assertSame($type, $result['type']);
                self::assertNull($result['bytes']);
                self::assertSame($type === 'lms' ? null : 0, $result['subject_bytes']);
                self::assertSame($message, $result['message']);
                self::assertSame($message, $result['content'][$type]['message']);
                if ($subject !== '') self::assertSame($subject, $result['content'][$type]['subject']);
            }
        }
        $result = TextMessage::normalize(['type' => 'lms', 'message' => '본문', 'subject' => 'ʕ']);
        self::assertSame(4, $result['bytes']);
        self::assertNull($result['subject_bytes']);
    }

    #[DataProvider('invalidMessages')]
    public function testRejectsInvalidInputWithoutTruncatingOrChangingType(array $input): void
    {
        $this->expectException(DomainError::class);
        TextMessage::normalize($input + ['message' => '안내', 'type' => 'sms']);
    }
}
