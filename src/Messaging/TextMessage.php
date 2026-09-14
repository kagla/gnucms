<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\Error\DomainError;

/** EUC-KR 길이를 확인하되 변환되지 않는 유니코드도 원문 그대로 API에 전달한다. */
final class TextMessage
{
    public static function normalize(array $input): array
    {
        $type = $input['type'] ?? 'sms';
        if (!in_array($type, ['sms', 'lms'], true)) throw DomainError::validation(['type' => 'SMS 또는 LMS를 선택해 주세요.']);
        $message = str_replace(["\r\n", "\r"], "\n", Input::text($input['message'] ?? null, '본문', 2000));
        $subject = Input::text($input['subject'] ?? '', '제목', 64, true);
        if ($type === 'sms' && $subject !== '') throw DomainError::validation(['subject' => '제목을 사용하려면 LMS를 선택해 주세요.']);
        if (preg_match('/[\r\n]/', $subject)) throw DomainError::validation(['subject' => '제목은 한 줄로 입력해 주세요.']);
        [$bytes, $knownBytes] = self::bytes($message, '본문');
        [$subjectBytes, $knownSubjectBytes] = self::bytes($subject, '제목');
        $limit = $type === 'sms' ? 90 : 2000;
        if ($knownBytes > $limit) throw DomainError::validation(['message' => strtoupper($type) . ' 본문은 EUC-KR 기준 ' . $limit . '바이트까지 입력할 수 있습니다. '
            . ($bytes === null ? '길이를 확인할 수 있는 부분만 ' : '현재 ') . $knownBytes . '바이트입니다.']);
        if ($knownSubjectBytes > 64) throw DomainError::validation(['subject' => 'LMS 제목은 EUC-KR 기준 64바이트까지 입력할 수 있습니다.']);
        $content = ['message' => $message];
        if ($subject !== '') $content['subject'] = $subject;
        return ['type' => $type, 'message' => $message, 'subject' => $subject, 'bytes' => $bytes,
            'subject_bytes' => $subjectBytes, 'limit' => $limit, 'content' => [$type => $content]];
    }

    /** @return array{?int, int} 전체 길이(확정 불가 시 null), 변환 가능한 부분의 길이 */
    private static function bytes(string $value, string $field): array
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw DomainError::validation([$field => $field . '에 사용할 수 없는 제어 문자가 있습니다.']);
        }
        $iconv = function_exists('iconv');
        if (!$iconv && !function_exists('mb_convert_encoding')) {
            throw DomainError::serviceUnavailable('문자 길이 검사를 위해 PHP iconv 또는 mbstring 확장이 필요합니다.');
        }
        $knownBytes = 0;
        $exact = true;
        $widths = [];
        preg_match_all('/./us', $value, $characters);
        foreach ($characters[0] as $character) {
            if (ord($character) < 128) { $knownBytes++; continue; }
            if (!array_key_exists($character, $widths)) {
                if ($iconv) {
                    $encoded = @iconv('UTF-8', 'EUC-KR', $character);
                    $decoded = $encoded === false ? false : @iconv('EUC-KR', 'UTF-8', $encoded);
                } else {
                    $encoded = mb_convert_encoding($character, 'EUC-KR', 'UTF-8');
                    $decoded = mb_convert_encoding($encoded, 'UTF-8', 'EUC-KR');
                }
                // 치환된 문자의 길이를 원문 길이로 표시하거나 원문에 반영하지 않는다.
                $widths[$character] = $encoded !== false && $decoded === $character ? strlen($encoded) : 0;
            }
            $knownBytes += $widths[$character];
            if ($widths[$character] === 0) $exact = false;
        }
        return [$exact ? $knownBytes : null, $knownBytes];
    }
}
