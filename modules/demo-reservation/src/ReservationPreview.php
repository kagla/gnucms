<?php

declare(strict_types=1);

namespace GnuCmsDemo\Modules\Reservation;

use Closure;
use DateTimeImmutable;
use GnuCms\Validation\Validator;

/** 예약 저장·발송 없이 입력 검증과 선택 서비스 조합을 보여주는 예제. */
final class ReservationPreview
{
    /** @param Closure(string, string):string|null $format */
    public function __construct(private ?Closure $format)
    {
    }

    public function usesPlugin(): bool
    {
        return $this->format !== null;
    }

    public function generate(array $input): string
    {
        $validator = new Validator($input);
        $name = $validator->requiredString('name', 60);
        $date = $validator->requiredString('date', 10);
        $time = $validator->requiredString('time', 5);
        $guests = $validator->requiredString('guests', 2);
        if (preg_match('/[\r\n]/', $name)) {
            $validator->fail('name', '이름은 한 줄로 입력해 주세요.');
        }
        // 정규식과 역변환을 함께 검사해 2월 30일 같은 자동 보정 날짜를 거부한다.
        $parsed = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if ($parsed === false || $parsed->format('Y-m-d') !== $date || substr($date, 0, 4) === '0000') {
            $validator->fail('date', '올바른 날짜를 입력해 주세요.');
        }
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
            $validator->fail('time', '올바른 시간을 입력해 주세요.');
        }
        if (!preg_match('/^(?:[1-9]|1[0-9]|20)$/D', $guests)) {
            $validator->fail('guests', '인원은 1~20명으로 입력해 주세요.');
        }
        $validator->check();

        $body = $name . "님, 예약 안내문 예시입니다.\n일시: " . $date . ' ' . $time . "\n인원: " . $guests . '명';
        return $this->format === null ? $body : ($this->format)('예약 안내', $body);
    }
}
