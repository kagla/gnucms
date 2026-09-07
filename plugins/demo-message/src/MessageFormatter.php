<?php

declare(strict_types=1);

namespace GnuCmsDemo\Plugins\Message;

use GnuCms\Validation\Validator;

/** HTML과 외부 발송을 모르는 순수 텍스트 서비스. */
final class MessageFormatter
{
    public function format(string $title, string $body): string
    {
        $validator = new Validator(['title' => $title, 'body' => $body]);
        $title = $validator->requiredString('title', 60);
        $body = $validator->requiredString('body', 1000);
        if (preg_match('/[\r\n]/', $title)) {
            $validator->fail('title', '제목은 한 줄로 입력해 주세요.');
        }
        $validator->check();

        return '[데모 알림 · ' . $title . "]\n" . $body;
    }
}
