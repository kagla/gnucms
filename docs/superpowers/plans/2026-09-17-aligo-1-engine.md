# 알리고 발송 엔진 구현 계획 (1/3)

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 알리고 계정으로 카카오 알림톡과 문자(SMS·LMS)를 보내고 결과를 확인하는 엔진과 관리자 화면을 코어에 넣는다.

**Architecture:** `src/Aligo/`에 알리고 연동을 모은다. 알림톡(`kakaoapi.aligo.in`)과 문자(`apis.aligo.in`)는 호스트·인증 파라미터·응답 필드·인코딩이 달라 `AlimtalkApi`·`SmsApi`로 나누고, 그 아래 HTTP 실행부(`Transport`)와 그 위 발송 작업·수신자·결과 갱신은 공유한다. 설정은 `site_settings`에 `aligo.` 접두사로 넣고 API 키만 암호화한다.

**Tech Stack:** PHP 8.1+, Slim 4, PDO(SQLite·MySQL), PHPUnit 10.5. 새 Composer 의존성 없음.

**Spec:** [docs/superpowers/specs/2026-09-17-aligo-messaging-design.md](../specs/2026-09-17-aligo-messaging-design.md)

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다. 호스팅 cron을 요구하지 않는다.
- 모든 PHP 파일은 `declare(strict_types=1);`로 시작하고 클래스는 `final`로 선언한다 (코어 관례).
- 네임스페이스는 `GnuCms\Aligo`, 테스트는 `GnuCms\Tests\Aligo`.
- 화면 문구·주석·커밋 본문의 설명은 한국어, 커밋 제목은 영어 conventional commit (`feat:`, `fix:`, `test:`, `docs:`).
- DB 접근은 `GnuCms\Db\Connection`의 `select`/`selectOne`/`execute`/`insert`/`update`/`delete`/`transaction`만 쓴다. 표 이름은 반드시 `$db->table('이름')`을 거친다.
- 오류는 `GnuCms\Error\DomainError`로 올린다 (`validation(array $details)`, `serviceUnavailable(string)`, `internal(string)`).
- API 키·비밀값을 예외 메시지·로그·화면에 절대 싣지 않는다.
- 발송은 재시도하지 않는다. 조회 계열만 재시도한다.
- 테스트는 실제 알리고를 부르지 않는다. `Transport`를 가짜로 끼운다.
- 관련 테스트를 먼저 돌리고, DB·라우팅을 건드린 뒤에는 `./vendor/bin/phpunit` 전체도 돌린다 (`AGENTS.md`).

### Web 테스트 규약

`tests/Web/`는 `GnuCms\Tests\Support\WebTestCase`를 쓴다. 이 클래스에는 `makeApp(array $dbConfig)`, `get($app, $path)`, `post($app, $path, $body)`, `body($response)`, `assertLoginRedirect($response, $path)`가 있고 **`adminApp()`이나 `guestApp()` 같은 것은 없다.** 모든 Web 테스트는 `#[DataProvider('connectionProvider')]`를 달고 `array $dbConfig`를 받는다.

관리자로 로그인하는 방법은 정해져 있다. 아래 헬퍼를 각 Web 테스트 파일에 private으로 하나 둔다:

```php
    private function adminApp(array $dbConfig): \GnuCms\App
    {
        $app = $this->makeApp($dbConfig);
        $adminId = $app->users()->create(
            'admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true
        );
        $this->get($app, '/login');
        session_start();
        $_SESSION['user_id'] = $adminId;
        $_SESSION['session_epoch'] = 0;
        session_write_close();

        return $app;
    }
```

**모든 POST 본문에 `'csrf_token' => $_SESSION['csrf_token']`를 넣는다.** 넣지 않으면 CSRF 검사에서 막힌다.

---

### Task 1: Transport — HTTP 실행부와 테스트용 가짜

**Files:**
- Create: `src/Aligo/Transport.php`
- Create: `src/Aligo/StreamTransport.php`
- Create: `src/Aligo/TransportFailure.php`
- Create: `tests/Support/FakeAligoTransport.php`
- Test: `tests/Aligo/StreamTransportTest.php`

**Interfaces:**
- Consumes: 없음 (첫 작업)
- Produces:
  - `interface GnuCms\Aligo\Transport { public function post(string $url, array $fields, int $timeout = 10): array; }` — 반환은 `['status' => int, 'body' => string]`
  - `final class GnuCms\Aligo\StreamTransport implements Transport`
  - `final class GnuCms\Aligo\TransportFailure extends \RuntimeException` — 네트워크 실패
  - `final class GnuCms\Tests\Support\FakeAligoTransport implements Transport` — `queue(int $status, string $body)`로 응답을 쌓고, `$requests` 공개 배열에 `['url' => string, 'fields' => array]`를 기록

- [ ] **Step 1: Write the failing test**

`tests/Aligo/StreamTransportTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\StreamTransport;
use GnuCms\Aligo\TransportFailure;
use PHPUnit\Framework\TestCase;

final class StreamTransportTest extends TestCase
{
    public function testEncodesFieldsAsFormBody(): void
    {
        $transport = new StreamTransport();
        // 실제 요청 없이 본문 구성만 확인한다.
        self::assertSame('key=a+b&user_id=%ED%99%8D', $transport->encode(['key' => 'a b', 'user_id' => '홍']));
    }

    public function testDroppedFieldsWithNullValueAreNotSent(): void
    {
        $transport = new StreamTransport();
        self::assertSame('key=a', $transport->encode(['key' => 'a', 'title' => null]));
    }

    public function testUnreachableHostFails(): void
    {
        $transport = new StreamTransport();
        $this->expectException(TransportFailure::class);
        $transport->post('https://127.0.0.1:1/none', ['key' => 'x'], 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/StreamTransportTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\StreamTransport" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/Transport.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

interface Transport
{
    /** @param array<string,string|null> $fields @return array{status:int,body:string} */
    public function post(string $url, array $fields, int $timeout = 10): array;
}
```

`src/Aligo/TransportFailure.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

final class TransportFailure extends \RuntimeException
{
}
```

`src/Aligo/StreamTransport.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

final class StreamTransport implements Transport
{
    /** 값이 null 인 항목은 보내지 않는다. 알리고는 빈 문자열과 미전송을 다르게 본다. */
    public function encode(array $fields): string
    {
        $given = array_filter($fields, static fn ($value): bool => $value !== null);

        return http_build_query($given, '', '&', PHP_QUERY_RFC1738);
    }

    public function post(string $url, array $fields, int $timeout = 10): array
    {
        $body = $this->encode($fields);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\n",
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new TransportFailure('알리고 서버에 연결하지 못했습니다.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return ['status' => $status, 'body' => $response];
    }
}
```

`tests/Support/FakeAligoTransport.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use GnuCms\Aligo\Transport;
use GnuCms\Aligo\TransportFailure;

final class FakeAligoTransport implements Transport
{
    /** @var list<array{url:string,fields:array}> */
    public array $requests = [];
    /** @var list<array{status:int,body:string}|string> */
    private array $responses = [];

    public function queue(int $status, string $body): void
    {
        $this->responses[] = ['status' => $status, 'body' => $body];
    }

    /** 네트워크 실패를 흉내 낸다. */
    public function queueFailure(): void
    {
        $this->responses[] = 'fail';
    }

    public function post(string $url, array $fields, int $timeout = 10): array
    {
        $this->requests[] = ['url' => $url, 'fields' => $fields];
        $next = array_shift($this->responses);
        if ($next === null) {
            throw new \LogicException('준비된 응답이 없습니다: ' . $url);
        }
        if ($next === 'fail') {
            throw new TransportFailure('테스트용 연결 실패');
        }

        return $next;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/StreamTransportTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/Transport.php src/Aligo/StreamTransport.php src/Aligo/TransportFailure.php \
  tests/Support/FakeAligoTransport.php tests/Aligo/StreamTransportTest.php
git commit -m "feat: add the Aligo HTTP transport and its test double"
```

---

### Task 2: PhoneNumber — 번호 정규화와 검증

**Files:**
- Create: `src/Aligo/PhoneNumber.php`
- Test: `tests/Aligo/PhoneNumberTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - `PhoneNumber::normalize(string $value): string` — 숫자만 남긴다. 국내 휴대폰이 아니면 `DomainError::validation`
  - `PhoneNumber::isMobile(string $value): bool` — 예외 없이 판정
  - `PhoneNumber::format(string $digits): string` — `010-1234-5678` 표시용
  - `PhoneNumber::mask(string $digits): string` — `010-****-5678` 목록용
  - `PhoneNumber::normalizeSender(string $value): string` — 발신번호. 휴대폰·지역번호·대표번호 모두 허용

- [ ] **Step 1: Write the failing test**

`tests/Aligo/PhoneNumberTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\PhoneNumber;
use GnuCms\Error\DomainError;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function testStripsHyphensAndSpaces(): void
    {
        self::assertSame('01012345678', PhoneNumber::normalize('010-1234-5678'));
        self::assertSame('01012345678', PhoneNumber::normalize(' 010 1234 5678 '));
        self::assertSame('01112345678', PhoneNumber::normalize('011-1234-5678'));
    }

    public function testRejectsWhatIsNotAKoreanMobileNumber(): void
    {
        foreach (['0212345678', '15881234', '821012345678', '010123456', '', 'abc'] as $value) {
            self::assertFalse(PhoneNumber::isMobile($value), $value . ' 는 휴대폰이 아니다');
        }
        $this->expectException(DomainError::class);
        PhoneNumber::normalize('02-1234-5678');
    }

    public function testFormatsAndMasks(): void
    {
        self::assertSame('010-1234-5678', PhoneNumber::format('01012345678'));
        self::assertSame('010-****-5678', PhoneNumber::mask('01012345678'));
    }

    public function testSenderAcceptsLandlineAndRepresentativeNumbers(): void
    {
        self::assertSame('0212345678', PhoneNumber::normalizeSender('02-1234-5678'));
        self::assertSame('15881234', PhoneNumber::normalizeSender('1588-1234'));
        self::assertSame('01012345678', PhoneNumber::normalizeSender('010-1234-5678'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/PhoneNumberTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\PhoneNumber" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/PhoneNumber.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;

/** 국내 번호를 한 곳에서만 다룬다. 저장은 숫자만, 표시할 때만 하이픈을 넣는다. */
final class PhoneNumber
{
    private const MOBILE = '/^01[016789]\d{7,8}$/D';
    private const SENDER = '/^(01[016789]\d{7,8}|0[2-6]\d{7,9}|1[0-9]{3}\d{4})$/D';

    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }

    public static function isMobile(string $value): bool
    {
        return preg_match(self::MOBILE, self::digits($value)) === 1;
    }

    public static function normalize(string $value): string
    {
        $digits = self::digits($value);
        if (preg_match(self::MOBILE, $digits) !== 1) {
            throw DomainError::validation(['phone' => '국내 휴대폰 번호를 입력해 주세요.']);
        }

        return $digits;
    }

    public static function normalizeSender(string $value): string
    {
        $digits = self::digits($value);
        if (preg_match(self::SENDER, $digits) !== 1) {
            throw DomainError::validation(['sender' => '발신번호를 확인해 주세요. 휴대폰·지역번호·대표번호를 쓸 수 있습니다.']);
        }

        return $digits;
    }

    public static function format(string $digits): string
    {
        $digits = self::digits($digits);
        if (preg_match('/^(01[016789])(\d{3,4})(\d{4})$/D', $digits, $m) === 1) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(02)(\d{3,4})(\d{4})$/D', $digits, $m) === 1) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(0[3-6]\d)(\d{3,4})(\d{4})$/D', $digits, $m) === 1) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (preg_match('/^(1\d{3})(\d{4})$/D', $digits, $m) === 1) {
            return $m[1] . '-' . $m[2];
        }

        return $digits;
    }

    /** 이력 목록용. 가운데만 가리고 앞뒤는 남겨 누구인지 구분할 수 있게 한다. */
    public static function mask(string $digits): string
    {
        $formatted = self::format($digits);
        $parts = explode('-', $formatted);
        if (count($parts) !== 3) {
            return $formatted;
        }

        return $parts[0] . '-' . str_repeat('*', strlen($parts[1])) . '-' . $parts[2];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/PhoneNumberTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/PhoneNumber.php tests/Aligo/PhoneNumberTest.php
git commit -m "feat: normalize and mask Korean phone numbers in one place"
```

---

### Task 3: MessageText — EUC-KR 바이트와 SMS·LMS 판정

**Files:**
- Create: `src/Aligo/MessageText.php`
- Test: `tests/Aligo/MessageTextTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - `MessageText::toEucKr(string $utf8): string` — 변환 불가 글자가 있으면 `DomainError::validation(['body' => ...])`
  - `MessageText::byteLength(string $utf8): int` — EUC-KR 기준 바이트
  - `MessageText::channelFor(string $body): string` — `'sms'`(90바이트 이하) 또는 `'lms'`
  - `MessageText::assertFits(string $body, ?string $title): void` — LMS 2000바이트·제목 44바이트 초과 시 거절

**Why:** 문자 API는 EUC-KR을 쓰고 SMS/LMS 경계가 EUC-KR 기준 90바이트다. 한글이 2바이트라 UTF-8 기준(3바이트)으로 재면 45자에서 잘못 LMS로 넘긴다.

- [ ] **Step 1: Write the failing test**

`tests/Aligo/MessageTextTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\MessageText;
use GnuCms\Error\DomainError;
use PHPUnit\Framework\TestCase;

final class MessageTextTest extends TestCase
{
    public function testCountsBytesInEucKrNotUtf8(): void
    {
        // 한글은 EUC-KR 2바이트, UTF-8 3바이트다. 45자는 EUC-KR 90바이트로 아직 SMS다.
        self::assertSame(90, MessageText::byteLength(str_repeat('가', 45)));
        self::assertSame('sms', MessageText::channelFor(str_repeat('가', 45)));
        self::assertSame('lms', MessageText::channelFor(str_repeat('가', 46)));
        self::assertSame('sms', MessageText::channelFor(str_repeat('a', 90)));
        self::assertSame('lms', MessageText::channelFor(str_repeat('a', 91)));
    }

    public function testRejectsCharactersEucKrCannotCarry(): void
    {
        try {
            MessageText::toEucKr('주문이 완료되었습니다 🎉');
            self::fail('이모지는 거절해야 한다');
        } catch (DomainError $e) {
            self::assertArrayHasKey('body', $e->details());
            self::assertStringContainsString('🎉', $e->details()['body']);
        }
    }

    public function testRejectsTooLongBodyAndTitle(): void
    {
        $this->expectException(DomainError::class);
        MessageText::assertFits(str_repeat('가', 1001), null); // 2002바이트
    }

    public function testRejectsTooLongTitle(): void
    {
        $this->expectException(DomainError::class);
        MessageText::assertFits('짧은 본문', str_repeat('가', 23)); // 46바이트
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/MessageTextTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\MessageText" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/MessageText.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;

/**
 * 알리고 문자 API 는 EUC-KR 을 쓴다. SMS/LMS 경계인 90바이트도 EUC-KR 기준이므로
 * 여기서만 길이를 재고, 변환할 수 없는 글자는 깨진 문자를 내보내는 대신 거절한다.
 */
final class MessageText
{
    public const SMS_BYTES = 90;
    public const LMS_BYTES = 2000;
    public const TITLE_BYTES = 44;

    public static function toEucKr(string $utf8): string
    {
        $unsupported = self::unsupported($utf8);
        if ($unsupported !== []) {
            throw DomainError::validation(['body' =>
                '문자로 보낼 수 없는 글자가 있습니다: ' . implode(' ', $unsupported) . '. 지우고 다시 시도해 주세요.']);
        }

        return (string) mb_convert_encoding($utf8, 'EUC-KR', 'UTF-8');
    }

    /** @return list<string> EUC-KR 로 옮길 수 없는 글자들 */
    public static function unsupported(string $utf8): array
    {
        $found = [];
        $previous = mb_substitute_character();
        mb_substitute_character(0x3F); // '?'
        foreach (preg_split('//u', $utf8, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $encoded = (string) mb_convert_encoding($char, 'EUC-KR', 'UTF-8');
            $back = (string) mb_convert_encoding($encoded, 'UTF-8', 'EUC-KR');
            if ($back !== $char && !in_array($char, $found, true)) {
                $found[] = $char;
            }
        }
        mb_substitute_character($previous);

        return $found;
    }

    public static function byteLength(string $utf8): int
    {
        return strlen((string) mb_convert_encoding($utf8, 'EUC-KR', 'UTF-8'));
    }

    public static function channelFor(string $body): string
    {
        return self::byteLength($body) <= self::SMS_BYTES ? 'sms' : 'lms';
    }

    public static function assertFits(string $body, ?string $title): void
    {
        self::toEucKr($body);
        if (self::byteLength($body) > self::LMS_BYTES) {
            throw DomainError::validation(['body' =>
                '본문이 너무 깁니다. 한글 기준 1,000자(2,000바이트)까지 보낼 수 있습니다.']);
        }
        if ($title !== null && $title !== '' && self::byteLength($title) > self::TITLE_BYTES) {
            throw DomainError::validation(['title' =>
                '제목이 너무 깁니다. 한글 기준 22자(44바이트)까지 넣을 수 있습니다.']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/MessageTextTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/MessageText.php tests/Aligo/MessageTextTest.php
git commit -m "feat: measure SMS length in EUC-KR bytes and refuse unsendable characters"
```

---

### Task 4: Variables — `#{...}` 추출과 치환

**Files:**
- Create: `src/Aligo/Variables.php`
- Test: `tests/Aligo/VariablesTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - `Variables::names(string $body): list<string>` — 본문에 쓰인 변수 이름을 순서대로, 중복 없이
  - `Variables::apply(string $body, array $values): string` — 값이 없는 변수가 있으면 `DomainError::validation(['vars' => ...])`
  - `Variables::missing(string $body, array $values): list<string>` — 예외 없이 비어 있는 변수 이름

- [ ] **Step 1: Write the failing test**

`tests/Aligo/VariablesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\Variables;
use GnuCms\Error\DomainError;
use PHPUnit\Framework\TestCase;

final class VariablesTest extends TestCase
{
    public function testFindsNamesInOrderWithoutDuplicates(): void
    {
        self::assertSame(['이름', '주문번호'], Variables::names('#{이름}님 #{주문번호} 건, #{이름}님 감사합니다'));
        self::assertSame([], Variables::names('변수 없는 본문'));
    }

    public function testReplacesEveryOccurrence(): void
    {
        self::assertSame(
            '홍길동님 A-1 건, 홍길동님 감사합니다',
            Variables::apply('#{이름}님 #{주문번호} 건, #{이름}님 감사합니다', ['이름' => '홍길동', '주문번호' => 'A-1'])
        );
    }

    public function testRefusesWhenAValueIsMissingOrBlank(): void
    {
        self::assertSame(['주문번호'], Variables::missing('#{이름} #{주문번호}', ['이름' => '홍길동']));
        self::assertSame(['주문번호'], Variables::missing('#{이름} #{주문번호}', ['이름' => '홍길동', '주문번호' => '  ']));

        try {
            Variables::apply('#{이름} #{주문번호}', ['이름' => '홍길동']);
            self::fail('빈 변수는 거절해야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('주문번호', $e->details()['vars']);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/VariablesTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\Variables" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/Variables.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;

/**
 * 본문의 #{변수}를 다룬다. 빈 값으로 보내면 알림톡은 템플릿 불일치(rslt: U)로 떨어지고
 * 문자는 뜻이 깨진 채로 나가므로, 값이 없으면 보내기 전에 거절한다.
 */
final class Variables
{
    private const PATTERN = '/#\{([^}\r\n]{1,50})\}/u';

    /** @return list<string> */
    public static function names(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);
        $names = [];
        foreach ($matches[1] as $name) {
            $name = trim($name);
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return list<string> */
    public static function missing(string $body, array $values): array
    {
        $missing = [];
        foreach (self::names($body) as $name) {
            $value = $values[$name] ?? null;
            if ($value === null || trim((string) $value) === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public static function apply(string $body, array $values): string
    {
        $missing = self::missing($body, $values);
        if ($missing !== []) {
            throw DomainError::validation(['vars' =>
                '값이 비어 있는 변수가 있습니다: ' . implode(', ', $missing)]);
        }

        return (string) preg_replace_callback(
            self::PATTERN,
            static fn (array $m): string => (string) $values[trim($m[1])],
            $body
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/VariablesTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/Variables.php tests/Aligo/VariablesTest.php
git commit -m "feat: substitute template variables and refuse blank values"
```

---

### Task 5: ResultCodes — 알리고 코드를 읽을 수 있는 사유로

**Files:**
- Create: `src/Aligo/ResultCodes.php`
- Test: `tests/Aligo/ResultCodesTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - `ResultCodes::alimtalkReason(string $code, string $message): string`
  - `ResultCodes::smsReason(string $code, string $message): string`
  - `ResultCodes::deliveryReason(string $rslt, string $message): string` — 수신자별 결과(`rslt`)
  - 모두 아는 코드면 해결 방법까지 붙은 한국어, 모르는 코드면 `'(코드 N) 원문'` 형태

- [ ] **Step 1: Write the failing test**

`tests/Aligo/ResultCodesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\ResultCodes;
use PHPUnit\Framework\TestCase;

final class ResultCodesTest extends TestCase
{
    public function testExplainsCommonFailuresWithAFix(): void
    {
        self::assertStringContainsString('발신번호', ResultCodes::smsReason('-101', ''));
        self::assertStringContainsString('잔여', ResultCodes::smsReason('-99', ''));
        self::assertStringContainsString('발신프로필', ResultCodes::alimtalkReason('509', ''));
        self::assertStringContainsString('템플릿', ResultCodes::deliveryReason('U', ''));
    }

    public function testUnknownCodeKeepsTheOriginalMessage(): void
    {
        $reason = ResultCodes::smsReason('-12345', '알 수 없는 오류');
        self::assertStringContainsString('-12345', $reason);
        self::assertStringContainsString('알 수 없는 오류', $reason);
    }

    public function testUnknownCodeWithoutMessageStillReads(): void
    {
        self::assertStringContainsString('-12345', ResultCodes::smsReason('-12345', ''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/ResultCodesTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\ResultCodes" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/ResultCodes.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

/**
 * 알리고가 돌려주는 코드를 관리자가 읽고 조치할 수 있는 문장으로 바꾼다.
 * 응답 원문을 그대로 화면에 싣지 않기 위한 유일한 통로다.
 */
final class ResultCodes
{
    private const SMS = [
        '-99' => '잔여 건수가 부족합니다. 알리고에서 충전한 뒤 다시 보내 주세요.',
        '-101' => '등록되지 않은 발신번호입니다. 알리고에서 발신번호를 사전 등록한 뒤 설정에 같은 번호를 저장해 주세요.',
        '-102' => '수신번호 형식이 올바르지 않습니다.',
        '-103' => '본문이 비어 있거나 허용 길이를 넘었습니다.',
        '-104' => '인증에 실패했습니다. API 키와 사용자 ID를 확인해 주세요.',
        '-201' => '등록되지 않은 IP에서 요청했습니다. 알리고에서 서버 IP를 등록해 주세요.',
        '-804' => '발송 5분 전까지만 취소할 수 있습니다.',
    ];

    private const ALIMTALK = [
        '-99' => '잔여 건수가 부족합니다. 알리고에서 충전한 뒤 다시 보내 주세요.',
        '501' => '인증에 실패했습니다. API 키와 사용자 ID를 확인해 주세요.',
        '509' => '발신프로필을 확인할 수 없습니다. 카카오채널 관리자 알림 설정과 발신프로필키를 확인해 주세요.',
        '510' => '승인되지 않은 템플릿입니다. 카카오 검수가 끝난 템플릿만 보낼 수 있습니다.',
        '520' => '발신프로필이 차단되었거나 삭제되었습니다. 알리고에서 채널 상태를 확인해 주세요.',
    ];

    private const DELIVERY = [
        'U' => '승인된 템플릿 본문과 맞지 않습니다. 템플릿을 다시 가져오고 변수값을 확인해 주세요.',
        'K' => '수신자가 카카오톡을 쓰지 않거나 알림톡을 차단했습니다.',
        'M' => '메시지 형식이 올바르지 않습니다.',
        'P' => '발신프로필에 문제가 있습니다.',
        'S' => '정상 처리되었습니다.',
    ];

    public static function smsReason(string $code, string $message): string
    {
        return self::lookup(self::SMS, $code, $message);
    }

    public static function alimtalkReason(string $code, string $message): string
    {
        return self::lookup(self::ALIMTALK, $code, $message);
    }

    public static function deliveryReason(string $rslt, string $message): string
    {
        return self::lookup(self::DELIVERY, $rslt, $message);
    }

    private static function lookup(array $table, string $code, string $message): string
    {
        if (isset($table[$code])) {
            return $table[$code];
        }
        $original = trim($message);

        return '알리고가 처리하지 못했습니다 (코드 ' . $code . ')'
            . ($original !== '' ? '. ' . $original : '.');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/ResultCodesTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/ResultCodes.php tests/Aligo/ResultCodesTest.php
git commit -m "feat: turn Aligo result codes into reasons an admin can act on"
```

---

### Task 6: 스키마 판 23 — 표 세 장과 `users.phone`

**Files:**
- Modify: `src/Db/Schema.php` (`VERSION`, `TABLES`, `INDEXES`, `statements()`, `migrateAll()`)
- Test: `tests/Db/AligoSchemaTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - 표 `message_jobs`, `message_recipients`, `alimtalk_templates`
  - 컬럼 `users.phone`
  - `Schema::migrateAligoMessaging(): void`
  - `Schema::VERSION === '23'`

**Note:** `users.phone`은 계획 2(회원 휴대폰번호)가 쓰지만 마이그레이션이 하나이므로 여기서 함께 만든다.

- [ ] **Step 1: Write the failing test**

`tests/Db/AligoSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Schema;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AligoSchemaTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testFreshInstallHasTheMessagingTables(array $config): void
    {
        $db = $this->freshDatabase($config);
        foreach (['message_jobs', 'message_recipients', 'alimtalk_templates'] as $table) {
            self::assertSame(0, (int) $db->selectOne(
                'SELECT COUNT(*) AS c FROM ' . $db->table($table))['c'], $table);
        }
        self::assertNotNull($db->selectOne('SELECT phone FROM ' . $db->table('users') . ' LIMIT 1')
            ?? ['phone' => null]);
    }

    #[DataProvider('connectionProvider')]
    public function testUpgradingAnOlderInstallCreatesThem(array $config): void
    {
        $db = $this->freshDatabase($config);
        foreach (['message_recipients', 'message_jobs', 'alimtalk_templates'] as $table) {
            $db->execute('DROP TABLE ' . $db->table($table));
        }

        (new Schema($db))->migrateAligoMessaging();

        foreach (['message_jobs', 'message_recipients', 'alimtalk_templates'] as $table) {
            self::assertSame(0, (int) $db->selectOne(
                'SELECT COUNT(*) AS c FROM ' . $db->table($table))['c'], $table);
        }
    }

    public function testSchemaVersionIsTwentyThree(): void
    {
        self::assertSame('23', Schema::VERSION);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Db/AligoSchemaTest.php`
Expected: FAIL — `no such table: message_jobs`

- [ ] **Step 3: Write minimal implementation**

`src/Db/Schema.php`에서 `VERSION`을 `'23'`으로 바꾸고, `TABLES`에 `'message_jobs', 'message_recipients', 'alimtalk_templates'`를, `INDEXES`에 `'ix_message_recipients_job', 'ix_message_recipients_mid', 'ux_alimtalk_templates_code', 'ix_message_jobs_created'`를 더한다. `statements()` 끝에 `...$this->aligoStatements()`를 넣고 아래를 추가한다:

```php
    /** 알리고 발송 작업·수신자·알림톡 템플릿 사본. 기존 설치에는 없으므로 업그레이드할 때 만든다. */
    public function migrateAligoMessaging(): void
    {
        foreach ($this->aligoStatements() as $sql) {
            // 표가 이미 있으면 건너뛴다. 세 표가 한 번에 생기지 않은 설치도 있을 수 있다.
            if (preg_match('/^CREATE TABLE (\w+)/', $sql, $m) === 1 && $this->tableExists($m[1])) {
                continue;
            }
            if (preg_match('/^CREATE (?:UNIQUE )?INDEX/', $sql) === 1 && !$this->tableExists('message_jobs')) {
                continue;
            }
            $this->db->execute($this->expand($sql));
        }
        $this->addColumnIfMissing('users', 'phone', 'VARCHAR(20) NULL');
    }

    private function aligoStatements(): array
    {
        return [
            'CREATE TABLE message_jobs (
                id           {AUTO_PK},
                channel      VARCHAR(8)   NOT NULL,
                tpl_code     VARCHAR(40)  NULL,
                senderkey    VARCHAR(64)  NULL,
                sender       VARCHAR(20)  NOT NULL,
                title        VARCHAR(60)  NULL,
                body         TEXT         NOT NULL,
                failover     SMALLINT     NOT NULL DEFAULT 0,
                event_key    VARCHAR(40)  NULL,
                created_by   VARCHAR(64)  NULL,
                total        INTEGER      NOT NULL DEFAULT 0,
                success      INTEGER      NOT NULL DEFAULT 0,
                failure      INTEGER      NOT NULL DEFAULT 0,
                status       VARCHAR(12)  NOT NULL,
                test_mode    SMALLINT     NOT NULL DEFAULT 0,
                created_at   {DATETIME}   NOT NULL,
                finished_at  {DATETIME}   NULL
            ){SUFFIX}',
            'CREATE TABLE message_recipients (
                id              {AUTO_PK},
                job_id          BIGINT       NOT NULL,
                mid             VARCHAR(32)  NULL,
                msgid           VARCHAR(40)  NULL,
                phone           VARCHAR(20)  NOT NULL,
                name            VARCHAR(60)  NULL,
                user_id         VARCHAR(64)  NULL,
                body            TEXT         NOT NULL,
                status          VARCHAR(12)  NOT NULL,
                rslt            VARCHAR(8)   NULL,
                rslt_message    VARCHAR(200) NULL,
                fallback_body   TEXT         NULL,
                smid            VARCHAR(32)  NULL,
                fallback_status VARCHAR(12)  NULL,
                requested_at    {DATETIME}   NULL,
                sent_at         {DATETIME}   NULL,
                result_at       {DATETIME}   NULL,
                checked_at      {DATETIME}   NULL
            ){SUFFIX}',
            'CREATE TABLE alimtalk_templates (
                id             {AUTO_PK},
                tpl_code       VARCHAR(40)  NOT NULL,
                senderkey      VARCHAR(64)  NOT NULL,
                name           VARCHAR(200) NOT NULL,
                content        TEXT         NOT NULL,
                template_type  VARCHAR(4)   NULL,
                emphasis_type  VARCHAR(8)   NULL,
                status         VARCHAR(4)   NULL,
                insp_status    VARCHAR(4)   NULL,
                buttons        TEXT         NULL,
                enabled        SMALLINT     NOT NULL DEFAULT 0,
                fetched_at     {DATETIME}   NOT NULL
            ){SUFFIX}',
            'CREATE INDEX ix_message_jobs_created ON message_jobs (created_at, id)',
            'CREATE INDEX ix_message_recipients_job ON message_recipients (job_id, id)',
            'CREATE INDEX ix_message_recipients_mid ON message_recipients (mid, status)',
            'CREATE UNIQUE INDEX ux_alimtalk_templates_code ON alimtalk_templates (tpl_code)',
        ];
    }
```

`migrateAll()`의 `migrateExtensionSchemas();` 바로 앞에 `$this->migrateAligoMessaging();`을 넣는다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Db/AligoSchemaTest.php`
Expected: PASS (3 tests)

그다음 전체 DB 테스트: `./vendor/bin/phpunit tests/Db/`
Expected: PASS — 판 문자열이 바뀌어 기존 마이그레이션 테스트가 깨지면 그 테스트의 기대값을 `'23'`으로 맞춘다.

- [ ] **Step 5: Commit**

```bash
git add src/Db/Schema.php tests/Db/AligoSchemaTest.php
git commit -m "feat: add the messaging tables and users.phone in schema version 23"
```

---

### Task 7: Settings — 알리고 계정 저장과 암호화

**Files:**
- Create: `src/Aligo/SettingsRepository.php`
- Create: `src/Aligo/Settings.php`
- Test: `tests/Aligo/SettingsTest.php`

**Interfaces:**
- Consumes: `PhoneNumber::normalizeSender()` (Task 2)
- Produces:
  - `SettingsRepository::__construct(Connection $db)`, `all(): array`, `save(array $settings): void` — `site_settings`의 `aligo.` 접두사
  - `Settings::__construct(SettingsRepository $repository, SecretCipher $cipher)`
  - `Settings::formValues(): array` — 화면용. `api_key`는 빈 문자열, `api_key_set`은 bool
  - `Settings::save(array $input): void`
  - `Settings::runtime(): ?array` — 복호화된 키 포함. 계정이 없으면 `null`
  - `Settings::isEnabled(string $channel): bool` — `'at'` 또는 `'sms'`
  - `Settings::setEnabled(string $channel, bool $on): void`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/SettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends DatabaseTestCase
{
    private function settings(array $config): Settings
    {
        return new Settings(new SettingsRepository($this->freshDatabase($config)), new SecretCipher('test-secret'));
    }

    #[DataProvider('connectionProvider')]
    public function testApiKeyIsEncryptedAndNeverReturnedToTheForm(array $config): void
    {
        $settings = $this->settings($config);
        $settings->save(['user_id' => 'shop', 'api_key' => 'live-key-value',
            'sender' => '02-1234-5678', 'senderkey' => 'SK1', 'channel_name' => '@상점']);

        $form = $settings->formValues();
        self::assertSame('', $form['api_key']);
        self::assertTrue($form['api_key_set']);
        self::assertSame('0212345678', $form['sender']);
        self::assertSame('live-key-value', $settings->runtime()['api_key']);
    }

    #[DataProvider('connectionProvider')]
    public function testBlankKeyKeepsTheStoredOneAndDeleteRemovesIt(array $config): void
    {
        $settings = $this->settings($config);
        $base = ['user_id' => 'shop', 'sender' => '0212345678', 'senderkey' => 'SK1'];
        $settings->save($base + ['api_key' => 'first-key']);
        $settings->save($base + ['api_key' => '']);
        self::assertSame('first-key', $settings->runtime()['api_key']);

        $settings->save($base + ['api_key' => '', 'api_key_delete' => '1']);
        self::assertNull($settings->runtime());
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkKeyFallsBackToTheSharedOne(array $config): void
    {
        $settings = $this->settings($config);
        $settings->save(['user_id' => 'shop', 'api_key' => 'shared', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        self::assertSame('shared', $settings->runtime()['alimtalk_api_key']);

        $settings->save(['user_id' => 'shop', 'api_key' => '', 'alimtalk_api_key' => 'special',
            'sender' => '0212345678', 'senderkey' => 'SK1']);
        self::assertSame('special', $settings->runtime()['alimtalk_api_key']);
        self::assertSame('shared', $settings->runtime()['api_key']);
    }

    #[DataProvider('connectionProvider')]
    public function testSendingIsOffUntilTurnedOnPerChannel(array $config): void
    {
        $settings = $this->settings($config);
        $settings->save(['user_id' => 'shop', 'api_key' => 'k', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        self::assertFalse($settings->isEnabled('sms'));
        self::assertFalse($settings->isEnabled('at'));

        $settings->setEnabled('sms', true);
        self::assertTrue($settings->isEnabled('sms'));
        self::assertFalse($settings->isEnabled('at'));
    }

    #[DataProvider('connectionProvider')]
    public function testRejectsABadSenderNumber(array $config): void
    {
        $this->expectException(DomainError::class);
        $this->settings($config)->save(['user_id' => 'shop', 'api_key' => 'k',
            'sender' => '123', 'senderkey' => 'SK1']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/SettingsTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\SettingsRepository" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/SettingsRepository.php`는 `src/Mail/MailSettingsRepository.php`와 같은 구조로 만들되 `private const PREFIX = 'aligo.';`만 다르다. 그 파일을 열어 `all()`·`save()`를 그대로 옮기고 클래스 이름과 네임스페이스(`GnuCms\Aligo`)를 바꾼다.

`src/Aligo/Settings.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Validation\Validator;

final class Settings
{
    public const CHANNELS = ['at', 'sms'];

    private SettingsRepository $repository;
    private SecretCipher $cipher;

    public function __construct(SettingsRepository $repository, SecretCipher $cipher)
    {
        $this->repository = $repository;
        $this->cipher = $cipher;
    }

    public function formValues(): array
    {
        $stored = $this->repository->all();

        return [
            'user_id' => (string) ($stored['user_id'] ?? ''),
            'sender' => (string) ($stored['sender'] ?? ''),
            'senderkey' => (string) ($stored['senderkey'] ?? ''),
            'channel_name' => (string) ($stored['channel_name'] ?? ''),
            'api_key' => '',
            'api_key_set' => ($stored['api_key'] ?? '') !== '',
            'alimtalk_api_key' => '',
            'alimtalk_api_key_set' => ($stored['alimtalk_api_key'] ?? '') !== '',
            'test_mode' => ($stored['test_mode'] ?? '0') === '1',
            'sms_enabled' => ($stored['sms_enabled'] ?? '0') === '1',
            'alimtalk_enabled' => ($stored['alimtalk_enabled'] ?? '0') === '1',
        ];
    }

    public function save(array $input): void
    {
        $current = $this->repository->all();
        $v = new Validator($input);
        $userId = $v->requiredString('user_id', 60);
        $senderkey = $v->optionalString('senderkey', 64, '') ?? '';
        $channelName = $v->optionalString('channel_name', 60, '') ?? '';
        $testMode = $v->bool('test_mode', false);
        $v->check();

        $sender = PhoneNumber::normalizeSender((string) ($input['sender'] ?? ''));
        if ($senderkey !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $senderkey) !== 1) {
            throw DomainError::validation(['senderkey' => '발신프로필키를 확인해 주세요.']);
        }

        $keys = [];
        foreach (['api_key', 'alimtalk_api_key'] as $field) {
            $keys[$field] = $this->nextSecret($field, $input, $current);
        }
        if ($keys['api_key'] === '' && $keys['alimtalk_api_key'] !== '') {
            throw DomainError::validation(['api_key' => '알림톡 전용 키만 저장할 수는 없습니다. API 키를 먼저 입력해 주세요.']);
        }

        // 계정이 바뀌면 이전 계정으로 확인한 상태를 물려받지 않는다.
        $accountChanged = $userId !== (string) ($current['user_id'] ?? '')
            || $keys['api_key'] !== (string) ($current['api_key'] ?? '');

        $this->repository->save([
            'user_id' => $userId,
            'api_key' => $keys['api_key'],
            'alimtalk_api_key' => $keys['alimtalk_api_key'],
            'sender' => $sender,
            'senderkey' => $senderkey,
            'channel_name' => $channelName,
            'test_mode' => $testMode ? '1' : '0',
            'sms_enabled' => $accountChanged ? '0' : (string) ($current['sms_enabled'] ?? '0'),
            'alimtalk_enabled' => $accountChanged ? '0' : (string) ($current['alimtalk_enabled'] ?? '0'),
        ]);
    }

    /** 빈 입력은 기존 값을 유지하고, 삭제 체크는 지운다. 저장은 암호문으로 한다. */
    private function nextSecret(string $field, array $input, array $current): string
    {
        if (($input[$field . '_delete'] ?? '') === '1') {
            return '';
        }
        $given = trim((string) ($input[$field] ?? ''));
        if ($given === '') {
            return (string) ($current[$field] ?? '');
        }

        return $this->cipher->encrypt($given);
    }

    public function runtime(): ?array
    {
        $stored = $this->repository->all();
        if (($stored['user_id'] ?? '') === '' || ($stored['api_key'] ?? '') === '') {
            return null;
        }
        $apiKey = $this->cipher->decrypt((string) $stored['api_key']);
        $alimtalkKey = ($stored['alimtalk_api_key'] ?? '') !== ''
            ? $this->cipher->decrypt((string) $stored['alimtalk_api_key'])
            : $apiKey;

        return [
            'user_id' => (string) $stored['user_id'],
            'api_key' => $apiKey,
            'alimtalk_api_key' => $alimtalkKey,
            'sender' => (string) ($stored['sender'] ?? ''),
            'senderkey' => (string) ($stored['senderkey'] ?? ''),
            'test_mode' => ($stored['test_mode'] ?? '0') === '1',
        ];
    }

    public function isEnabled(string $channel): bool
    {
        $key = $channel === 'at' ? 'alimtalk_enabled' : 'sms_enabled';

        return ($this->repository->all()[$key] ?? '0') === '1' && $this->runtime() !== null;
    }

    public function setEnabled(string $channel, bool $on): void
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw DomainError::validation(['channel' => '알림톡 또는 문자를 선택해 주세요.']);
        }
        if ($on && $this->runtime() === null) {
            throw DomainError::validation(['api_key' => '계정을 먼저 저장해 주세요.']);
        }
        $this->repository->save([($channel === 'at' ? 'alimtalk_enabled' : 'sms_enabled') => $on ? '1' : '0']);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/SettingsTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/SettingsRepository.php src/Aligo/Settings.php tests/Aligo/SettingsTest.php
git commit -m "feat: store the Aligo account with the API key encrypted"
```

---

### Task 8: AlimtalkApi — 알림톡 호출

**Files:**
- Create: `src/Aligo/AlimtalkApi.php`
- Test: `tests/Aligo/AlimtalkApiTest.php`

**Interfaces:**
- Consumes: `Transport` (Task 1), `ResultCodes::alimtalkReason()` (Task 5), `Settings::runtime()` (Task 7)
- Produces:
  - `AlimtalkApi::__construct(Transport $transport, Settings $settings)`
  - `AlimtalkApi::heartInfo(): array` — `['ALT_CNT' => int, ...]`
  - `AlimtalkApi::profiles(): list<array{senderKey:string,name:string,uuid:string,status:string}>`
  - `AlimtalkApi::templates(string $senderkey): list<array>` — 알리고 원본 필드 그대로
  - `AlimtalkApi::send(array $fields): array` — `['mid' => string, 'scnt' => int, 'fcnt' => int]`
  - `AlimtalkApi::detail(string $mid): list<array>`
  - 실패는 전부 `DomainError::serviceUnavailable(ResultCodes::alimtalkReason(...))`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/AlimtalkApiTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class AlimtalkApiTest extends DatabaseTestCase
{
    private FakeAligoTransport $transport;

    private function api(array $config): AlimtalkApi
    {
        $settings = new Settings(new SettingsRepository($this->freshDatabase($config)), new SecretCipher('s'));
        $settings->save(['user_id' => 'shop', 'api_key' => 'KEY', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        $this->transport = new FakeAligoTransport();

        return new AlimtalkApi($this->transport, $settings);
    }

    #[DataProvider('connectionProvider')]
    public function testSendsCredentialsWithEveryCall(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"code":0,"ALT_CNT":120,"SMS_CNT":50,"LMS_CNT":10}');

        self::assertSame(120, $api->heartInfo()['ALT_CNT']);
        $request = $this->transport->requests[0];
        self::assertStringStartsWith('https://kakaoapi.aligo.in/akv10/heartinfo/', $request['url']);
        self::assertSame('KEY', $request['fields']['apikey']);
        self::assertSame('shop', $request['fields']['userid']);
    }

    #[DataProvider('connectionProvider')]
    public function testSendReturnsMidAndCounts(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"code":0,"info":{"mid":"M1","scnt":2,"fcnt":0}}');

        $result = $api->send(['senderkey' => 'SK1', 'tpl_code' => 'T1', 'sender' => '0212345678',
            'receiver_1' => '01012345678', 'message_1' => '안녕하세요']);

        self::assertSame(['mid' => 'M1', 'scnt' => 2, 'fcnt' => 0], $result);
        self::assertStringContainsString('/akv10/alimtalk/send/', $this->transport->requests[0]['url']);
    }

    #[DataProvider('connectionProvider')]
    public function testFailureBecomesAReadableError(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"code":510,"message":"template not approved"}');

        try {
            $api->send(['tpl_code' => 'T1']);
            self::fail('실패 코드는 예외가 되어야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('승인되지 않은 템플릿', $e->getMessage());
            self::assertStringNotContainsString('KEY', $e->getMessage());
        }
    }

    #[DataProvider('connectionProvider')]
    public function testBrokenJsonIsRejected(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(500, '<html>오류</html>');

        $this->expectException(DomainError::class);
        $api->heartInfo();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/AlimtalkApiTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\AlimtalkApi" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/AlimtalkApi.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;

/** kakaoapi.aligo.in. 인증은 apikey·userid 이고 응답 성공은 code 0 이다. */
final class AlimtalkApi
{
    private const BASE = 'https://kakaoapi.aligo.in';

    private Transport $transport;
    private Settings $settings;

    public function __construct(Transport $transport, Settings $settings)
    {
        $this->transport = $transport;
        $this->settings = $settings;
    }

    public function heartInfo(): array
    {
        return $this->call('/akv10/heartinfo/', []);
    }

    public function profiles(): array
    {
        return $this->call('/akv10/profile/list/', [])['list'] ?? [];
    }

    public function templates(string $senderkey): array
    {
        return $this->call('/akv10/template/list/', ['senderkey' => $senderkey])['list'] ?? [];
    }

    public function send(array $fields): array
    {
        $info = $this->call('/akv10/alimtalk/send/', $fields)['info'] ?? [];

        return [
            'mid' => (string) ($info['mid'] ?? ''),
            'scnt' => (int) ($info['scnt'] ?? 0),
            'fcnt' => (int) ($info['fcnt'] ?? 0),
        ];
    }

    public function detail(string $mid): array
    {
        return $this->call('/akv10/history/detail/', ['mid' => $mid, 'limit' => '500'])['list'] ?? [];
    }

    private function call(string $path, array $fields): array
    {
        $account = $this->settings->runtime();
        if ($account === null) {
            throw DomainError::validation(['api_key' => '알리고 계정을 먼저 저장해 주세요.']);
        }

        $response = $this->transport->post(self::BASE . $path, $fields + [
            'apikey' => $account['alimtalk_api_key'],
            'userid' => $account['user_id'],
        ]);

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw DomainError::serviceUnavailable(
                '알리고 알림톡 응답을 읽지 못했습니다 (HTTP ' . $response['status'] . ').');
        }
        $code = (string) ($decoded['code'] ?? '');
        if ($code !== '0') {
            throw DomainError::serviceUnavailable(
                ResultCodes::alimtalkReason($code, (string) ($decoded['message'] ?? '')));
        }

        return $decoded;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/AlimtalkApiTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/AlimtalkApi.php tests/Aligo/AlimtalkApiTest.php
git commit -m "feat: call the Aligo alimtalk API behind one code check"
```

---

### Task 9: SmsApi — 문자 호출과 EUC-KR

**Files:**
- Create: `src/Aligo/SmsApi.php`
- Test: `tests/Aligo/SmsApiTest.php`

**Interfaces:**
- Consumes: `Transport` (Task 1), `MessageText::toEucKr()` (Task 3), `ResultCodes::smsReason()` (Task 5), `Settings::runtime()` (Task 7)
- Produces:
  - `SmsApi::__construct(Transport $transport, Settings $settings)`
  - `SmsApi::remain(): array` — `['SMS_CNT' => int, 'LMS_CNT' => int, 'MMS_CNT' => int]`
  - `SmsApi::sendMass(array $fields): array` — `['mid' => string, 'scnt' => int, 'fcnt' => int]`. 본문·제목 필드는 이 클래스가 EUC-KR로 바꿔 보낸다
  - `SmsApi::detail(string $mid): list<array>`
  - 실패는 `DomainError::serviceUnavailable(ResultCodes::smsReason(...))`

**Why:** 문자 API는 인증 파라미터 이름(`key`·`user_id`)과 성공 판정(`result_code === 1`)이 알림톡과 다르고, 본문을 EUC-KR로 보내야 한다.

- [ ] **Step 1: Write the failing test**

`tests/Aligo/SmsApiTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Aligo\SmsApi;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class SmsApiTest extends DatabaseTestCase
{
    private FakeAligoTransport $transport;

    private function api(array $config): SmsApi
    {
        $settings = new Settings(new SettingsRepository($this->freshDatabase($config)), new SecretCipher('s'));
        $settings->save(['user_id' => 'shop', 'api_key' => 'KEY', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        $this->transport = new FakeAligoTransport();

        return new SmsApi($this->transport, $settings);
    }

    #[DataProvider('connectionProvider')]
    public function testUsesTheSmsHostAndItsOwnParameterNames(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"result_code":1,"SMS_CNT":500,"LMS_CNT":100,"MMS_CNT":0}');

        self::assertSame(500, $api->remain()['SMS_CNT']);
        $request = $this->transport->requests[0];
        self::assertStringStartsWith('https://apis.aligo.in/remain/', $request['url']);
        self::assertSame('KEY', $request['fields']['key']);
        self::assertSame('shop', $request['fields']['user_id']);
        self::assertArrayNotHasKey('apikey', $request['fields']);
    }

    #[DataProvider('connectionProvider')]
    public function testConvertsBodyAndTitleToEucKr(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"result_code":1,"msg_id":"7788","success_cnt":1,"error_cnt":0}');

        $result = $api->sendMass(['cnt' => '1', 'msg_type' => 'LMS', 'title' => '안내',
            'rec_1' => '01012345678', 'msg_1' => '안녕하세요']);

        self::assertSame('7788', $result['mid']);
        self::assertSame(1, $result['scnt']);
        $fields = $this->transport->requests[0]['fields'];
        self::assertSame(mb_convert_encoding('안녕하세요', 'EUC-KR', 'UTF-8'), $fields['msg_1']);
        self::assertSame(mb_convert_encoding('안내', 'EUC-KR', 'UTF-8'), $fields['title']);
        self::assertSame('01012345678', $fields['rec_1']);
    }

    #[DataProvider('connectionProvider')]
    public function testNegativeResultCodeBecomesAReadableError(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"result_code":-101,"message":"sender not registered"}');

        try {
            $api->sendMass(['cnt' => '1', 'rec_1' => '01012345678', 'msg_1' => '안녕']);
            self::fail('음수 코드는 예외가 되어야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('발신번호', $e->getMessage());
            self::assertStringNotContainsString('KEY', $e->getMessage());
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/SmsApiTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\SmsApi" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/SmsApi.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Error\DomainError;

/**
 * apis.aligo.in. 알림톡과 달리 인증은 key·user_id 이고 성공은 result_code 1 이며
 * 본문·제목은 EUC-KR 로 보낸다.
 */
final class SmsApi
{
    private const BASE = 'https://apis.aligo.in';
    /** EUC-KR 로 바꿔 보내야 하는 필드 이름 앞머리 */
    private const TEXT_FIELDS = ['msg', 'title'];

    private Transport $transport;
    private Settings $settings;

    public function __construct(Transport $transport, Settings $settings)
    {
        $this->transport = $transport;
        $this->settings = $settings;
    }

    public function remain(): array
    {
        return $this->call('/remain/', []);
    }

    public function sendMass(array $fields): array
    {
        $decoded = $this->call('/send_mass/', $fields);

        return [
            'mid' => (string) ($decoded['msg_id'] ?? ''),
            'scnt' => (int) ($decoded['success_cnt'] ?? 0),
            'fcnt' => (int) ($decoded['error_cnt'] ?? 0),
        ];
    }

    public function detail(string $mid): array
    {
        return $this->call('/sms_list/', ['mid' => $mid, 'page_size' => '500'])['list'] ?? [];
    }

    private function call(string $path, array $fields): array
    {
        $account = $this->settings->runtime();
        if ($account === null) {
            throw DomainError::validation(['api_key' => '알리고 계정을 먼저 저장해 주세요.']);
        }

        $response = $this->transport->post(self::BASE . $path, $this->encodeText($fields) + [
            'key' => $account['api_key'],
            'user_id' => $account['user_id'],
        ]);

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw DomainError::serviceUnavailable(
                '알리고 문자 응답을 읽지 못했습니다 (HTTP ' . $response['status'] . ').');
        }
        $code = (string) ($decoded['result_code'] ?? '');
        if ($code !== '1') {
            throw DomainError::serviceUnavailable(
                ResultCodes::smsReason($code, (string) ($decoded['message'] ?? '')));
        }

        return $decoded;
    }

    /** msg_1, msg_2, title 처럼 사람이 읽는 값만 EUC-KR 로 바꾼다. 번호·건수는 그대로다. */
    private function encodeText(array $fields): array
    {
        foreach ($fields as $name => $value) {
            if ($value === null || !is_string($value)) {
                continue;
            }
            foreach (self::TEXT_FIELDS as $prefix) {
                if ($name === $prefix || str_starts_with($name, $prefix . '_')) {
                    $fields[$name] = MessageText::toEucKr($value);
                    break;
                }
            }
        }

        return $fields;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/SmsApiTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/SmsApi.php tests/Aligo/SmsApiTest.php
git commit -m "feat: call the Aligo SMS API with EUC-KR bodies"
```

---

### Task 10: Templates — 알림톡 템플릿 가져오기와 사용 전환

**Files:**
- Create: `src/Aligo/Templates.php`
- Test: `tests/Aligo/TemplatesTest.php`

**Interfaces:**
- Consumes: `AlimtalkApi::templates()` (Task 8), `Settings::runtime()` (Task 7), `Variables::names()` (Task 4)
- Produces:
  - `Templates::__construct(Connection $db, AlimtalkApi $api, Settings $settings)`
  - `Templates::fetch(): array` — `['imported' => int, 'updated' => int, 'disabled' => int]`
  - `Templates::all(): list<array>` — 사본 전체
  - `Templates::find(string $tplCode): ?array`
  - `Templates::usable(): list<array>` — `enabled = 1`인 것만
  - `Templates::setEnabled(string $tplCode, bool $on): void` — 승인·정상이 아니면 `DomainError::validation`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/TemplatesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Aligo\Templates;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class TemplatesTest extends DatabaseTestCase
{
    private FakeAligoTransport $transport;
    private Templates $templates;

    private function boot(array $config): void
    {
        $db = $this->freshDatabase($config);
        $settings = new Settings(new SettingsRepository($db), new SecretCipher('s'));
        $settings->save(['user_id' => 'shop', 'api_key' => 'K', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        $this->transport = new FakeAligoTransport();
        $this->templates = new Templates($db, new AlimtalkApi($this->transport, $settings), $settings);
    }

    private function queueList(array $items): void
    {
        $this->transport->queue(200, (string) json_encode(['code' => 0, 'list' => $items]));
    }

    #[DataProvider('connectionProvider')]
    public function testImportedCopiesStartDisabled(array $config): void
    {
        $this->boot($config);
        $this->queueList([[
            'templtCode' => 'T1', 'templtName' => '주문 안내', 'templtContent' => '#{이름}님 주문 #{주문번호}',
            'templateType' => 'BA', 'templateEmType' => 'NONE', 'status' => 'A', 'inspStatus' => 'APR',
            'buttons' => [['ordering' => 1, 'name' => '주문확인', 'linkType' => 'WL']],
        ]]);

        self::assertSame(['imported' => 1, 'updated' => 0, 'disabled' => 0], $this->templates->fetch());
        $copy = $this->templates->find('T1');
        self::assertSame('주문 안내', $copy['name']);
        self::assertSame(0, (int) $copy['enabled']);
        self::assertSame([], $this->templates->usable());
    }

    #[DataProvider('connectionProvider')]
    public function testOnlyApprovedTemplatesCanBeTurnedOn(array $config): void
    {
        $this->boot($config);
        $this->queueList([
            ['templtCode' => 'OK', 'templtName' => '승인', 'templtContent' => '본문',
                'status' => 'A', 'inspStatus' => 'APR'],
            ['templtCode' => 'WAIT', 'templtName' => '대기', 'templtContent' => '본문',
                'status' => 'R', 'inspStatus' => 'REQ'],
        ]);
        $this->templates->fetch();

        $this->templates->setEnabled('OK', true);
        self::assertCount(1, $this->templates->usable());

        $this->expectException(DomainError::class);
        $this->templates->setEnabled('WAIT', true);
    }

    #[DataProvider('connectionProvider')]
    public function testRefetchDisablesACopyThatLostApproval(array $config): void
    {
        $this->boot($config);
        $this->queueList([['templtCode' => 'T1', 'templtName' => '안내', 'templtContent' => '본문',
            'status' => 'A', 'inspStatus' => 'APR']]);
        $this->templates->fetch();
        $this->templates->setEnabled('T1', true);

        $this->queueList([['templtCode' => 'T1', 'templtName' => '안내', 'templtContent' => '본문',
            'status' => 'S', 'inspStatus' => 'APR']]);
        self::assertSame(['imported' => 0, 'updated' => 1, 'disabled' => 1], $this->templates->fetch());
        self::assertSame([], $this->templates->usable());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/TemplatesTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\Templates" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/Templates.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/**
 * 알리고에서 승인 템플릿을 가져와 사본으로 보관한다. 여기서 템플릿을 만들거나 고치지 않는다.
 * 승인(APR)이고 정상(A)인 사본만 켤 수 있고, 가져오기에서 그 조건을 잃으면 자동으로 꺼진다.
 */
final class Templates
{
    private Connection $db;
    private AlimtalkApi $api;
    private Settings $settings;

    public function __construct(Connection $db, AlimtalkApi $api, Settings $settings)
    {
        $this->db = $db;
        $this->api = $api;
        $this->settings = $settings;
    }

    public function fetch(): array
    {
        $account = $this->settings->runtime();
        if ($account === null || $account['senderkey'] === '') {
            throw DomainError::validation(['senderkey' => '발신프로필키를 먼저 저장해 주세요.']);
        }

        $counts = ['imported' => 0, 'updated' => 0, 'disabled' => 0];
        foreach ($this->api->templates($account['senderkey']) as $item) {
            $code = (string) ($item['templtCode'] ?? '');
            if ($code === '') {
                continue;
            }
            $status = (string) ($item['status'] ?? '');
            $insp = (string) ($item['inspStatus'] ?? '');
            $row = [
                'senderkey' => $account['senderkey'],
                'name' => (string) ($item['templtName'] ?? ''),
                'content' => (string) ($item['templtContent'] ?? ''),
                'template_type' => (string) ($item['templateType'] ?? ''),
                'emphasis_type' => (string) ($item['templateEmType'] ?? ''),
                'status' => $status,
                'insp_status' => $insp,
                'buttons' => (string) json_encode($item['buttons'] ?? [], JSON_UNESCAPED_UNICODE),
                'fetched_at' => Clock::now(),
            ];

            $existing = $this->find($code);
            if ($existing === null) {
                $this->db->insert('alimtalk_templates', $row + ['tpl_code' => $code, 'enabled' => 0]);
                $counts['imported']++;
                continue;
            }

            // 승인·정상을 잃은 사본은 켜져 있었더라도 끈다.
            if ((int) $existing['enabled'] === 1 && !$this->approved($status, $insp)) {
                $row['enabled'] = 0;
                $counts['disabled']++;
            }
            $this->db->update('alimtalk_templates', $row, 'tpl_code = :code', ['code' => $code]);
            $counts['updated']++;
        }

        return $counts;
    }

    public function all(): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('alimtalk_templates') . ' ORDER BY name, id');
    }

    public function usable(): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('alimtalk_templates')
            . ' WHERE enabled = 1 ORDER BY name, id');
    }

    public function find(string $tplCode): ?array
    {
        return $this->db->selectOne('SELECT * FROM ' . $this->db->table('alimtalk_templates')
            . ' WHERE tpl_code = ?', [$tplCode]);
    }

    public function setEnabled(string $tplCode, bool $on): void
    {
        $row = $this->find($tplCode);
        if ($row === null) {
            throw DomainError::validation(['tpl_code' => '먼저 템플릿을 가져와 주세요.']);
        }
        if ($on && !$this->approved((string) $row['status'], (string) $row['insp_status'])) {
            throw DomainError::validation(['tpl_code' =>
                '카카오 승인이 끝나고 정상 상태인 템플릿만 쓸 수 있습니다. 알리고에서 검수를 마친 뒤 다시 가져와 주세요.']);
        }
        $this->db->update('alimtalk_templates', ['enabled' => $on ? 1 : 0], 'tpl_code = :code', ['code' => $tplCode]);
    }

    private function approved(string $status, string $insp): bool
    {
        return $status === 'A' && $insp === 'APR';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/TemplatesTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/Templates.php tests/Aligo/TemplatesTest.php
git commit -m "feat: import approved alimtalk templates and keep copies honest"
```

---

### Task 11: Dispatch — 발송, 500명 분할, 대체발송

**Files:**
- Create: `src/Aligo/Dispatch.php`
- Test: `tests/Aligo/DispatchTest.php`

**Interfaces:**
- Consumes: `AlimtalkApi::send()` (Task 8), `SmsApi::sendMass()` (Task 9), `Templates::find()` (Task 10), `Settings` (Task 7), `MessageText` (Task 3), `Variables` (Task 4), `PhoneNumber` (Task 2)
- Produces:
  - `Dispatch::__construct(Connection $db, AlimtalkApi $alimtalk, SmsApi $sms, Templates $templates, Settings $settings)`
  - `Dispatch::send(array $request): int` — `message_jobs.id` 반환. 요청 모양은 아래 구현의 주석 참고
  - `const CHUNK = 500`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/DispatchTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\Dispatch;
use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Aligo\SmsApi;
use GnuCms\Aligo\Templates;
use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class DispatchTest extends DatabaseTestCase
{
    private FakeAligoTransport $transport;
    private Dispatch $dispatch;
    private Templates $templates;
    private Settings $settings;
    private Connection $db;

    private function boot(array $config): void
    {
        $this->db = $this->freshDatabase($config);
        $this->settings = new Settings(new SettingsRepository($this->db), new SecretCipher('s'));
        $this->settings->save(['user_id' => 'shop', 'api_key' => 'K', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        $this->transport = new FakeAligoTransport();
        $alimtalk = new AlimtalkApi($this->transport, $this->settings);
        $sms = new SmsApi($this->transport, $this->settings);
        $this->templates = new Templates($this->db, $alimtalk, $this->settings);
        $this->dispatch = new Dispatch($this->db, $alimtalk, $sms, $this->templates, $this->settings);
    }

    private function queueSmsOk(int $count): void
    {
        $this->transport->queue(200, (string) json_encode(
            ['result_code' => 1, 'msg_id' => 'M' . $count, 'success_cnt' => $count, 'error_cnt' => 0]));
    }

    #[DataProvider('connectionProvider')]
    public function testRefusesWhenTheChannelIsNotAllowed(array $config): void
    {
        $this->boot($config);
        $this->expectException(DomainError::class);
        $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'recipients' => [['phone' => '01012345678']]]);
    }

    #[DataProvider('connectionProvider')]
    public function testSendsTextAndRecordsEveryRecipient(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(2);

        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '#{이름}님 안녕하세요', 'recipients' => [
            ['phone' => '010-1234-5678', 'name' => '홍길동', 'vars' => ['이름' => '홍길동']],
            ['phone' => '01098765432', 'name' => '김철수', 'vars' => ['이름' => '김철수']],
        ]]);

        $job = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId]);
        self::assertSame('sms', $job['channel']);
        self::assertSame(2, (int) $job['total']);
        self::assertSame(2, (int) $job['success']);
        self::assertSame('sent', $job['status']);

        $rows = $this->db->select('SELECT * FROM ' . $this->db->table('message_recipients')
            . ' WHERE job_id = ? ORDER BY id', [$jobId]);
        self::assertSame('01012345678', $rows[0]['phone']);
        self::assertSame('홍길동님 안녕하세요', $rows[0]['body']);
        self::assertSame('김철수님 안녕하세요', $rows[1]['body']);
        self::assertSame('accepted', $rows[0]['status']);

        $fields = $this->transport->requests[0]['fields'];
        self::assertSame('2', $fields['cnt']);
        self::assertSame('01012345678', $fields['rec_1']);
        self::assertSame('01098765432', $fields['rec_2']);
    }

    #[DataProvider('connectionProvider')]
    public function testSwitchesToLmsWhenTheBodyIsLong(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(1);

        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => str_repeat('가', 46),
            'title' => '안내', 'recipients' => [['phone' => '01012345678']]]);

        self::assertSame('lms', $this->db->selectOne('SELECT channel FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['channel']);
        self::assertSame('LMS', $this->transport->requests[0]['fields']['msg_type']);
    }

    #[DataProvider('connectionProvider')]
    public function testSplitsIntoChunksOfFiveHundred(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(500);
        $this->queueSmsOk(2);

        $recipients = [];
        for ($i = 0; $i < 502; $i++) {
            $recipients[] = ['phone' => '010' . str_pad((string) $i, 8, '0', STR_PAD_LEFT)];
        }
        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요', 'recipients' => $recipients]);

        self::assertCount(2, $this->transport->requests);
        self::assertSame('500', $this->transport->requests[0]['fields']['cnt']);
        self::assertSame('2', $this->transport->requests[1]['fields']['cnt']);
        self::assertSame(502, (int) $this->db->selectOne('SELECT total FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['total']);
        // mid 는 묶음마다 다르므로 수신자 쪽에 적힌다.
        self::assertSame('M500', $this->db->selectOne('SELECT mid FROM '
            . $this->db->table('message_recipients') . ' WHERE job_id = ? ORDER BY id', [$jobId])['mid']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefusesTheWholeJobWhenAVariableIsBlank(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);

        try {
            $this->dispatch->send(['channel' => 'sms', 'body' => '#{이름}님', 'recipients' => [
                ['phone' => '01012345678', 'vars' => ['이름' => '홍길동']],
                ['phone' => '01098765432', 'vars' => []],
            ]]);
            self::fail('빈 변수가 있으면 작업을 만들지 않아야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('이름', $e->details()['vars']);
        }
        self::assertSame(0, (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM '
            . $this->db->table('message_jobs'))['c']);
        self::assertSame([], $this->transport->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testDropsDuplicateNumbers(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(1);

        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요', 'recipients' => [
            ['phone' => '010-1234-5678'], ['phone' => '01012345678'],
        ]]);

        self::assertSame(1, (int) $this->db->selectOne('SELECT total FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['total']);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkNeedsAnEnabledTemplateAndSendsFailoverText(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('at', true);
        $this->transport->queue(200, (string) json_encode(['code' => 0, 'list' => [[
            'templtCode' => 'T1', 'templtName' => '안내', 'templtContent' => '#{이름}님 안녕하세요',
            'status' => 'A', 'inspStatus' => 'APR']]]));
        $this->templates->fetch();
        $this->templates->setEnabled('T1', true);
        $this->transport->queue(200, (string) json_encode(
            ['code' => 0, 'info' => ['mid' => 'A1', 'scnt' => 1, 'fcnt' => 0]]));

        $jobId = $this->dispatch->send(['channel' => 'at', 'tpl_code' => 'T1', 'failover' => true,
            'recipients' => [['phone' => '01012345678', 'vars' => ['이름' => '홍길동']]]]);

        $fields = $this->transport->requests[1]['fields'];
        self::assertSame('T1', $fields['tpl_code']);
        self::assertSame('홍길동님 안녕하세요', $fields['message_1']);
        self::assertSame('Y', $fields['failover']);
        self::assertSame('홍길동님 안녕하세요', $fields['fmessage_1']);
        self::assertSame(1, (int) $this->db->selectOne('SELECT failover FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['failover']);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkRefusesATemplateThatIsNotTurnedOn(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('at', true);
        $this->expectException(DomainError::class);
        $this->dispatch->send(['channel' => 'at', 'tpl_code' => 'NOPE',
            'recipients' => [['phone' => '01012345678']]]);
    }

    #[DataProvider('connectionProvider')]
    public function testAFailedChunkIsRecordedAndNotRetried(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->transport->queueFailure();

        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'recipients' => [['phone' => '01012345678']]]);

        $job = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId]);
        self::assertSame('failed', $job['status']);
        self::assertSame(1, (int) $job['failure']);
        self::assertCount(1, $this->transport->requests, '발송은 재시도하지 않는다');
        $row = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_recipients')
            . ' WHERE job_id = ?', [$jobId]);
        self::assertSame('failed', $row['status']);
        self::assertNotSame('', (string) $row['rslt_message']);
    }

    #[DataProvider('connectionProvider')]
    public function testTestModeIsPassedThroughAndRecorded(array $config): void
    {
        $this->boot($config);
        $this->settings->save(['user_id' => 'shop', 'api_key' => 'K', 'sender' => '0212345678',
            'senderkey' => 'SK1', 'test_mode' => '1']);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(1);

        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'recipients' => [['phone' => '01012345678']]]);

        self::assertSame('Y', $this->transport->requests[0]['fields']['testmode_yn']);
        self::assertSame(1, (int) $this->db->selectOne('SELECT test_mode FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['test_mode']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/DispatchTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\Dispatch" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/Dispatch.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/**
 * 발송 작업 한 건을 만들고 알리고에 보낸다.
 *
 * 요청 모양:
 *   channel    'at' | 'sms'            (문자는 길이로 sms·lms 가 정해진다)
 *   body       본문. 알림톡이면 비우고 tpl_code 의 본문을 쓴다
 *   title      LMS 제목 (선택)
 *   tpl_code   알림톡 템플릿 코드 (알림톡 필수)
 *   failover   알림톡 실패 시 문자 대체발송 (선택)
 *   event_key  이 발송을 일으킨 알림 이벤트 (선택)
 *   created_by 요청한 관리자 표시명 (선택)
 *   recipients [['phone' =>, 'name' =>, 'user_id' =>, 'vars' => []], ...]
 */
final class Dispatch
{
    public const CHUNK = 500;

    private Connection $db;
    private AlimtalkApi $alimtalk;
    private SmsApi $sms;
    private Templates $templates;
    private Settings $settings;

    public function __construct(Connection $db, AlimtalkApi $alimtalk, SmsApi $sms,
        Templates $templates, Settings $settings)
    {
        $this->db = $db;
        $this->alimtalk = $alimtalk;
        $this->sms = $sms;
        $this->templates = $templates;
        $this->settings = $settings;
    }

    public function send(array $request): int
    {
        $channel = (string) ($request['channel'] ?? 'sms');
        if (!in_array($channel, Settings::CHANNELS, true)) {
            throw DomainError::validation(['channel' => '알림톡 또는 문자를 선택해 주세요.']);
        }
        if (!$this->settings->isEnabled($channel)) {
            throw DomainError::validation(['channel' =>
                ($channel === 'at' ? '알림톡' : '문자') . ' 발송이 허용되어 있지 않습니다. 설정에서 허용해 주세요.']);
        }
        $account = $this->settings->runtime();

        [$body, $tplCode] = $this->resolveBody($channel, $request);
        $title = trim((string) ($request['title'] ?? '')) ?: null;
        $stored = $channel === 'at' ? 'at' : MessageText::channelFor($body);
        if ($stored !== 'at') {
            MessageText::assertFits($body, $title);
        }

        $prepared = $this->prepare($body, $request['recipients'] ?? []);
        if ($prepared === []) {
            throw DomainError::validation(['recipients' => '보낼 수 있는 수신번호가 없습니다.']);
        }

        $jobId = (int) $this->db->insert('message_jobs', [
            'channel' => $stored,
            'tpl_code' => $tplCode,
            'senderkey' => $channel === 'at' ? $account['senderkey'] : null,
            'sender' => $account['sender'],
            'title' => $stored === 'lms' ? $title : null,
            'body' => $body,
            'failover' => !empty($request['failover']) && $channel === 'at' ? 1 : 0,
            'event_key' => $request['event_key'] ?? null,
            'created_by' => $request['created_by'] ?? null,
            'total' => count($prepared),
            'success' => 0, 'failure' => 0,
            'status' => 'sending',
            'test_mode' => $account['test_mode'] ? 1 : 0,
            'created_at' => Clock::now(),
        ]);

        foreach ($prepared as $index => $one) {
            $prepared[$index]['id'] = (int) $this->db->insert('message_recipients', [
                'job_id' => $jobId,
                'phone' => $one['phone'],
                'name' => $one['name'],
                'user_id' => $one['user_id'],
                'body' => $one['body'],
                'status' => 'queued',
                'fallback_body' => !empty($request['failover']) && $channel === 'at' ? $one['body'] : null,
                'requested_at' => Clock::now(),
            ]);
        }

        $success = 0;
        $failure = 0;
        foreach (array_chunk($prepared, self::CHUNK) as $chunk) {
            try {
                $result = $channel === 'at'
                    ? $this->alimtalk->send($this->alimtalkFields($chunk, $request, $account))
                    : $this->sms->sendMass($this->smsFields($chunk, $stored, $title, $account));
                $this->markChunk($chunk, 'accepted', $result['mid'], null);
                $success += count($chunk);
            } catch (DomainError | TransportFailure $e) {
                // 발송은 재시도하지 않는다. 응답을 못 받은 채 다시 보내면 중복 발송이 된다.
                $this->markChunk($chunk, 'failed', null, $e->getMessage());
                $failure += count($chunk);
            }
        }

        $this->db->update('message_jobs', [
            'success' => $success, 'failure' => $failure,
            'status' => $failure === 0 ? 'sent' : ($success === 0 ? 'failed' : 'sent'),
            'finished_at' => Clock::now(),
        ], 'id = :id', ['id' => $jobId]);

        return $jobId;
    }

    /** @return array{0:string,1:?string} 본문과 템플릿 코드 */
    private function resolveBody(string $channel, array $request): array
    {
        if ($channel !== 'at') {
            $body = trim((string) ($request['body'] ?? ''));
            if ($body === '') {
                throw DomainError::validation(['body' => '본문을 입력해 주세요.']);
            }

            return [$body, null];
        }

        $code = trim((string) ($request['tpl_code'] ?? ''));
        $template = $code === '' ? null : $this->templates->find($code);
        if ($template === null || (int) $template['enabled'] !== 1) {
            throw DomainError::validation(['tpl_code' => '사용 중인 승인 템플릿을 골라 주세요.']);
        }

        return [(string) $template['content'], $code];
    }

    /** 번호를 정규화하고 중복을 없애며 변수를 치환한다. 하나라도 어긋나면 작업을 만들지 않는다. */
    private function prepare(string $body, array $recipients): array
    {
        $prepared = [];
        $seen = [];
        foreach ($recipients as $one) {
            $phone = PhoneNumber::normalize((string) ($one['phone'] ?? ''));
            if (isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $prepared[] = [
                'phone' => $phone,
                'name' => ($one['name'] ?? '') !== '' ? (string) $one['name'] : null,
                'user_id' => ($one['user_id'] ?? '') !== '' ? (string) $one['user_id'] : null,
                'body' => Variables::apply($body, (array) ($one['vars'] ?? [])),
            ];
        }

        return $prepared;
    }

    private function alimtalkFields(array $chunk, array $request, array $account): array
    {
        $fields = [
            'senderkey' => $account['senderkey'],
            'tpl_code' => (string) $request['tpl_code'],
            'sender' => $account['sender'],
            'testMode' => $account['test_mode'] ? 'Y' : 'N',
        ];
        if (!empty($request['failover'])) {
            $fields['failover'] = 'Y';
        }
        $n = 0;
        foreach ($chunk as $one) {
            $n++;
            $fields['receiver_' . $n] = $one['phone'];
            $fields['message_' . $n] = $one['body'];
            $fields['subject_' . $n] = mb_substr($one['body'], 0, 20);
            if ($one['name'] !== null) {
                $fields['recvname_' . $n] = $one['name'];
            }
            if (!empty($request['failover'])) {
                $fields['fmessage_' . $n] = $one['body'];
            }
        }

        return $fields;
    }

    private function smsFields(array $chunk, string $stored, ?string $title, array $account): array
    {
        $fields = [
            'sender' => $account['sender'],
            'cnt' => (string) count($chunk),
            'msg_type' => strtoupper($stored),
            'testmode_yn' => $account['test_mode'] ? 'Y' : 'N',
        ];
        if ($stored === 'lms' && $title !== null) {
            $fields['title'] = $title;
        }
        $n = 0;
        foreach ($chunk as $one) {
            $n++;
            $fields['rec_' . $n] = $one['phone'];
            $fields['msg_' . $n] = $one['body'];
        }

        return $fields;
    }

    private function markChunk(array $chunk, string $status, ?string $mid, ?string $reason): void
    {
        foreach ($chunk as $one) {
            $this->db->update('message_recipients', [
                'status' => $status,
                'mid' => $mid,
                'rslt_message' => $reason === null ? null : mb_substr($reason, 0, 200),
                'sent_at' => $status === 'accepted' ? Clock::now() : null,
            ], 'id = :id', ['id' => $one['id']]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/DispatchTest.php`
Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/Dispatch.php tests/Aligo/DispatchTest.php
git commit -m "feat: send alimtalk and text in chunks of five hundred without retrying"
```

---

### Task 12: History — 결과 갱신과 이력 조회

**Files:**
- Create: `src/Aligo/History.php`
- Test: `tests/Aligo/HistoryTest.php`

**Interfaces:**
- Consumes: `AlimtalkApi::detail()` (Task 8), `SmsApi::detail()` (Task 9), `ResultCodes::deliveryReason()` (Task 5)
- Produces:
  - `History::__construct(Connection $db, AlimtalkApi $alimtalk, SmsApi $sms)`
  - `History::refresh(int $limit = 5): int` — 갱신한 `mid` 수
  - `History::jobs(int $page = 1, int $perPage = 20): array` — `['items' => list<array>, 'total' => int]`
  - `History::job(int $id): ?array` — 작업과 `recipients`
  - `History::pendingCount(): int`
  - `const RECHECK_SECONDS = 60`, `const GIVE_UP_DAYS = 7`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/HistoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\History;
use GnuCms\Aligo\Settings;
use GnuCms\Aligo\SettingsRepository;
use GnuCms\Aligo\SmsApi;
use GnuCms\Db\Connection;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class HistoryTest extends DatabaseTestCase
{
    private FakeAligoTransport $transport;
    private History $history;
    private Connection $db;

    private function boot(array $config): void
    {
        $this->db = $this->freshDatabase($config);
        $settings = new Settings(new SettingsRepository($this->db), new SecretCipher('s'));
        $settings->save(['user_id' => 'shop', 'api_key' => 'K', 'sender' => '0212345678', 'senderkey' => 'SK1']);
        $this->transport = new FakeAligoTransport();
        $this->history = new History($this->db,
            new AlimtalkApi($this->transport, $settings), new SmsApi($this->transport, $settings));
    }

    private function seed(string $channel, string $mid, string $sentAt): int
    {
        $jobId = (int) $this->db->insert('message_jobs', [
            'channel' => $channel, 'sender' => '0212345678', 'body' => '본문', 'failover' => 0,
            'total' => 1, 'success' => 1, 'failure' => 0, 'status' => 'sent', 'test_mode' => 0,
            'created_at' => $sentAt,
        ]);
        $this->db->insert('message_recipients', [
            'job_id' => $jobId, 'mid' => $mid, 'phone' => '01012345678', 'body' => '본문',
            'status' => 'accepted', 'requested_at' => $sentAt, 'sent_at' => $sentAt,
        ]);

        return $jobId;
    }

    #[DataProvider('connectionProvider')]
    public function testWritesDeliveryResultBackToTheRecipient(array $config): void
    {
        $this->boot($config);
        $jobId = $this->seed('sms', 'M1', Clock::now());
        $this->transport->queue(200, (string) json_encode(['result_code' => 1, 'list' => [
            ['mdid' => 'D1', 'receiver' => '01012345678', 'sms_state' => '전송성공',
             'send_date' => '2026-09-17 10:00:00'],
        ]]));

        self::assertSame(1, $this->history->refresh());

        $row = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_recipients')
            . ' WHERE job_id = ?', [$jobId]);
        self::assertSame('sent', $row['status']);
        self::assertSame('D1', $row['msgid']);
        self::assertNotNull($row['result_at']);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkFailureKeepsAReadableReason(array $config): void
    {
        $this->boot($config);
        $jobId = $this->seed('at', 'A1', Clock::now());
        $this->transport->queue(200, (string) json_encode(['code' => 0, 'list' => [
            ['msgid' => 'X1', 'phone' => '01012345678', 'rslt' => 'U', 'rslt_message' => 'mismatch'],
        ]]));

        $this->history->refresh();

        $row = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_recipients')
            . ' WHERE job_id = ?', [$jobId]);
        self::assertSame('failed', $row['status']);
        self::assertStringContainsString('템플릿', (string) $row['rslt_message']);
    }

    #[DataProvider('connectionProvider')]
    public function testDoesNotAskAgainWithinTheRecheckWindow(array $config): void
    {
        $this->boot($config);
        $this->seed('sms', 'M1', Clock::now());
        $this->transport->queue(200, (string) json_encode(['result_code' => 1, 'list' => []]));

        self::assertSame(1, $this->history->refresh());
        self::assertSame(0, $this->history->refresh(), '60초 안에는 다시 묻지 않는다');
        self::assertCount(1, $this->transport->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testStopsAskingAfterSevenDaysAndMarksUnknown(array $config): void
    {
        $this->boot($config);
        $old = date('Y-m-d H:i:s', strtotime('-8 days'));
        $jobId = $this->seed('sms', 'M1', $old);

        self::assertSame(0, $this->history->refresh());
        self::assertSame([], $this->transport->requests);
        self::assertSame('unknown', $this->db->selectOne('SELECT status FROM '
            . $this->db->table('message_recipients') . ' WHERE job_id = ?', [$jobId])['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testPendingCountAndJobListing(array $config): void
    {
        $this->boot($config);
        $this->seed('sms', 'M1', Clock::now());
        self::assertSame(1, $this->history->pendingCount());

        $listing = $this->history->jobs();
        self::assertSame(1, $listing['total']);
        self::assertSame('sms', $listing['items'][0]['channel']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/HistoryTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\History" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/History.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/**
 * 알리고는 결과 웹훅이 없어 조회로만 결과를 안다. 호스팅 cron 을 요구하지 않으므로
 * 관리자가 이력 화면을 열 때 조금씩 갱신한다. 접속이 없으면 늦어지며 그 사실을 화면에 적는다.
 */
final class History
{
    public const RECHECK_SECONDS = 60;
    public const GIVE_UP_DAYS = 7;
    private const BATCH = 5;

    private Connection $db;
    private AlimtalkApi $alimtalk;
    private SmsApi $sms;

    public function __construct(Connection $db, AlimtalkApi $alimtalk, SmsApi $sms)
    {
        $this->db = $db;
        $this->alimtalk = $alimtalk;
        $this->sms = $sms;
    }

    public function refresh(int $limit = self::BATCH): int
    {
        $this->giveUpOnStaleRows();

        $cutoff = date('Y-m-d H:i:s', time() - self::RECHECK_SECONDS);
        $rows = $this->db->select(
            'SELECT r.mid AS mid, j.channel AS channel FROM ' . $this->db->table('message_recipients') . ' r'
            . ' JOIN ' . $this->db->table('message_jobs') . ' j ON j.id = r.job_id'
            . ' WHERE r.status = ? AND r.mid IS NOT NULL AND (r.checked_at IS NULL OR r.checked_at < ?)'
            . ' GROUP BY r.mid, j.channel ORDER BY MIN(r.id) LIMIT ' . max(1, $limit),
            ['accepted', $cutoff]
        );

        $done = 0;
        foreach ($rows as $row) {
            $mid = (string) $row['mid'];
            // 같은 mid 를 다른 관리자가 동시에 묻지 않도록 먼저 표시를 찍는다.
            $claimed = $this->db->update('message_recipients', ['checked_at' => Clock::now()],
                'mid = :mid AND status = :status AND (checked_at IS NULL OR checked_at < :cutoff)',
                ['mid' => $mid, 'status' => 'accepted', 'cutoff' => $cutoff]);
            if ($claimed === 0) {
                continue;
            }
            try {
                $this->apply($mid, (string) $row['channel']);
            } catch (DomainError | TransportFailure $e) {
                // 조회 실패는 다음 방문에 다시 시도한다. 결과를 잃지 않는다.
                continue;
            }
            $done++;
        }

        return $done;
    }

    private function apply(string $mid, string $channel): void
    {
        $isAlimtalk = $channel === 'at';
        $list = $isAlimtalk ? $this->alimtalk->detail($mid) : $this->sms->detail($mid);
        foreach ($list as $item) {
            $phone = PhoneNumber::digits((string) ($item['phone'] ?? $item['receiver'] ?? ''));
            if ($phone === '') {
                continue;
            }
            if ($isAlimtalk) {
                $rslt = (string) ($item['rslt'] ?? '');
                $ok = $rslt === 'S' || $rslt === '';
                $reason = $ok ? null : ResultCodes::deliveryReason($rslt, (string) ($item['rslt_message'] ?? ''));
                $msgid = (string) ($item['msgid'] ?? '');
                $smid = ($item['smid'] ?? '') !== '' ? (string) $item['smid'] : null;
            } else {
                $state = (string) ($item['sms_state'] ?? '');
                $ok = str_contains($state, '성공');
                $rslt = $ok ? 'S' : 'F';
                $reason = $ok ? null : ($state !== '' ? $state : '전송에 실패했습니다.');
                $msgid = (string) ($item['mdid'] ?? '');
                $smid = null;
            }

            $this->db->update('message_recipients', [
                'msgid' => $msgid !== '' ? $msgid : null,
                'status' => $ok ? 'sent' : 'failed',
                'rslt' => $rslt !== '' ? $rslt : null,
                'rslt_message' => $reason === null ? null : mb_substr($reason, 0, 200),
                'smid' => $smid,
                'fallback_status' => $smid !== null ? 'accepted' : null,
                'result_at' => Clock::now(),
            ], 'mid = :mid AND phone = :phone', ['mid' => $mid, 'phone' => $phone]);
        }
    }

    /** 오래된 건은 조회를 멈춘다. 무한히 묻지 않는다. */
    private function giveUpOnStaleRows(): void
    {
        $limit = date('Y-m-d H:i:s', strtotime('-' . self::GIVE_UP_DAYS . ' days'));
        $this->db->update('message_recipients', ['status' => 'unknown'],
            'status = :status AND requested_at < :limit', ['status' => 'accepted', 'limit' => $limit]);
    }

    public function pendingCount(): int
    {
        return (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM '
            . $this->db->table('message_recipients') . ' WHERE status = ?', ['accepted'])['c'];
    }

    public function jobs(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $total = (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM '
            . $this->db->table('message_jobs'))['c'];
        $items = $this->db->select('SELECT * FROM ' . $this->db->table('message_jobs')
            . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage));

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function job(int $id): ?array
    {
        $job = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_jobs') . ' WHERE id = ?', [$id]);
        if ($job === null) {
            return null;
        }
        $job['recipients'] = $this->db->select('SELECT * FROM ' . $this->db->table('message_recipients')
            . ' WHERE job_id = ? ORDER BY id', [$id]);

        return $job;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/HistoryTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/History.php tests/Aligo/HistoryTest.php
git commit -m "feat: refresh delivery results on visits instead of requiring cron"
```

---

### Task 13: AligoService와 App 배선

**Files:**
- Create: `src/Aligo/AligoService.php`
- Modify: `src/App.php` (서비스 게터 추가)
- Test: `tests/Aligo/AligoServiceTest.php`

**Interfaces:**
- Consumes: Task 7~12 전부
- Produces:
  - `AligoService::__construct(Connection $db, Transport $transport, SecretCipher $cipher)`
  - 공개 속성 `settings`, `templates`, `dispatch`, `history`, `alimtalkApi`, `smsApi`
  - `AligoService::send(array $request): int` — 확장이 쓰는 입구. `Dispatch::send()`로 넘긴다
  - `AligoService::status(): array` — `['configured' => bool, 'sms_enabled' => bool, 'alimtalk_enabled' => bool, 'test_mode' => bool, 'pending' => int]`
  - `AligoService::verify(): array` — 연결 확인. `['ALT_CNT' => int, 'SMS_CNT' => int, 'LMS_CNT' => int]`
  - `App::aligo(): AligoService`

- [ ] **Step 1: Write the failing test**

`tests/Aligo/AligoServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\AligoService;
use GnuCms\Mail\SecretCipher;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class AligoServiceTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testStatusReportsWhatIsConfigured(array $config): void
    {
        $transport = new FakeAligoTransport();
        $service = new AligoService($this->freshDatabase($config), $transport, new SecretCipher('s'));

        self::assertFalse($service->status()['configured']);

        $service->settings->save(['user_id' => 'shop', 'api_key' => 'K',
            'sender' => '0212345678', 'senderkey' => 'SK1']);
        $service->settings->setEnabled('sms', true);

        $status = $service->status();
        self::assertTrue($status['configured']);
        self::assertTrue($status['sms_enabled']);
        self::assertFalse($status['alimtalk_enabled']);
        self::assertSame(0, $status['pending']);
    }

    #[DataProvider('connectionProvider')]
    public function testVerifyAsksBothServicesForRemainingCounts(array $config): void
    {
        $transport = new FakeAligoTransport();
        $service = new AligoService($this->freshDatabase($config), $transport, new SecretCipher('s'));
        $service->settings->save(['user_id' => 'shop', 'api_key' => 'K',
            'sender' => '0212345678', 'senderkey' => 'SK1']);

        $transport->queue(200, '{"code":0,"ALT_CNT":120}');
        $transport->queue(200, '{"result_code":1,"SMS_CNT":500,"LMS_CNT":100,"MMS_CNT":0}');

        self::assertSame(['ALT_CNT' => 120, 'SMS_CNT' => 500, 'LMS_CNT' => 100], $service->verify());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/AligoServiceTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\AligoService" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/AligoService.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Aligo;

use GnuCms\Db\Connection;
use GnuCms\Mail\SecretCipher;

/** 알리고 기능의 유일한 입구. 확장과 컨트롤러는 이 클래스만 안다. */
final class AligoService
{
    public Settings $settings;
    public Templates $templates;
    public Dispatch $dispatch;
    public History $history;
    public AlimtalkApi $alimtalkApi;
    public SmsApi $smsApi;

    public function __construct(Connection $db, Transport $transport, SecretCipher $cipher)
    {
        $this->settings = new Settings(new SettingsRepository($db), $cipher);
        $this->alimtalkApi = new AlimtalkApi($transport, $this->settings);
        $this->smsApi = new SmsApi($transport, $this->settings);
        $this->templates = new Templates($db, $this->alimtalkApi, $this->settings);
        $this->dispatch = new Dispatch($db, $this->alimtalkApi, $this->smsApi, $this->templates, $this->settings);
        $this->history = new History($db, $this->alimtalkApi, $this->smsApi);
    }

    public function send(array $request): int
    {
        return $this->dispatch->send($request);
    }

    public function status(): array
    {
        $runtime = $this->settings->runtime();

        return [
            'configured' => $runtime !== null,
            'sms_enabled' => $this->settings->isEnabled('sms'),
            'alimtalk_enabled' => $this->settings->isEnabled('at'),
            'test_mode' => $runtime !== null && $runtime['test_mode'],
            'pending' => $this->history->pendingCount(),
        ];
    }

    public function verify(): array
    {
        $alimtalk = $this->alimtalkApi->heartInfo();
        $sms = $this->smsApi->remain();

        return [
            'ALT_CNT' => (int) ($alimtalk['ALT_CNT'] ?? 0),
            'SMS_CNT' => (int) ($sms['SMS_CNT'] ?? 0),
            'LMS_CNT' => (int) ($sms['LMS_CNT'] ?? 0),
        ];
    }
}
```

`src/App.php`에 `mailSettingsService()` 옆 같은 모양으로 더한다:

```php
    private ?AligoService $aligoService = null;

    public function aligo(): AligoService
    {
        if ($this->aligoService === null) {
            $this->aligoService = new AligoService(
                $this->db(),
                new StreamTransport(),
                new SecretCipher((string) $this->config('auth.secret', ''))
            );
        }

        return $this->aligoService;
    }
```

`use GnuCms\Aligo\AligoService;`와 `use GnuCms\Aligo\StreamTransport;`를 파일 위쪽 `use` 목록에 더한다. `$this->db()`는 기존 커넥션 게터 이름을 그대로 쓴다 — `src/App.php`에서 `mailSettingsService()`가 `MailSettingsRepository`에 넘기는 인자와 같은 것을 쓰면 된다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/AligoServiceTest.php`
Expected: PASS (2 tests)

그다음 전체: `./vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/AligoService.php src/App.php tests/Aligo/AligoServiceTest.php
git commit -m "feat: expose the Aligo service as the single entry point"
```

---

### Task 14: 설정 화면 — 계정·발신프로필·연결 확인

**Files:**
- Create: `src/Web/Controller/AdminAligoController.php`
- Create: `templates/default/admin/aligo_settings.php`
- Modify: `src/Web/Routes.php`
- Modify: `templates/default/admin/_settings_tabs.php`
- Test: `tests/Web/AligoSettingsTest.php`

**Interfaces:**
- Consumes: `App::aligo()` (Task 13)
- Produces:
  - 라우트 이름 `admin.aligo`(GET·POST), `admin.aligo.verify`(POST), `admin.aligo.profiles`(POST), `admin.aligo.toggle`(POST)
  - 템플릿 변수: `values`(Settings::formValues), `errors`, `status`(AligoService::status), `verified`(?array), `profiles`(list), `notice`(?string)

- [ ] **Step 1: Write the failing test**

`tests/Web/AligoSettingsTest.php` — Global Constraints의 **Web 테스트 규약**을 따른다.

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AligoSettingsTest extends WebTestCase
{
    // adminApp() 은 Global Constraints 의 Web 테스트 규약에 있는 헬퍼를 그대로 둔다.

    #[DataProvider('connectionProvider')]
    public function testFormNeverEchoesTheStoredKey(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $app->aligo()->settings->save(['user_id' => 'shop', 'api_key' => 'SECRET-KEY',
            'sender' => '0212345678', 'senderkey' => 'SK1']);

        $html = $this->body($this->get($app, '/admin/aligo'));

        self::assertStringNotContainsString('SECRET-KEY', $html);
        self::assertStringContainsString('저장됨', $html);
        self::assertStringContainsString('02-1234-5678', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testSavingRejectsABadSenderAndKeepsTheInput(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $response = $this->post($app, '/admin/aligo', ['csrf_token' => $_SESSION['csrf_token'],
            'user_id' => 'shop', 'api_key' => 'K', 'sender' => '123', 'senderkey' => 'SK1']);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('발신번호', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testTurningSendingOnRequiresAnAccount(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $response = $this->post($app, '/admin/aligo/toggle', ['csrf_token' => $_SESSION['csrf_token'],
            'channel' => 'sms', 'action' => 'enable']);

        self::assertSame(422, $response->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenTheSettings(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect($this->get($app, '/admin/aligo'), '/admin/aligo');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/AligoSettingsTest.php`
Expected: FAIL — 404 (라우트 없음)

- [ ] **Step 3: Write minimal implementation**

`src/Web/Controller/AdminAligoController.php`를 `AdminCmsController::mailForm()`·`mail()` 패턴 그대로 만든다. 핵심만:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\Error\DomainError;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminAligoController
{
    // 생성자·input()·assertCsrf()·redirect() 는 AdminCmsController 와 같은 방식으로 둔다.

    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->render($request, $response, null, [], null, []);
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $this->app->guestAcl()->assertGlobalAdmin();
        try {
            $this->app->aligo()->settings->save($input);
        } catch (DomainError $e) {
            if ($e->status() !== 422) {
                throw $e;
            }
            return $this->render($request, $response->withStatus(422), null, $e->details(), null, []);
        }

        return $this->redirect($request, $response, 'admin.aligo', ['saved' => '1']);
    }

    public function verify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertCsrf($this->input($request));
        $this->app->guestAcl()->assertGlobalAdmin();
        try {
            $verified = $this->app->aligo()->verify();
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status() === 422 ? 422 : 502),
                null, $e->details(), $e->getMessage(), []);
        }

        return $this->render($request, $response, $verified, [], null, []);
    }

    public function profiles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->assertCsrf($this->input($request));
        $this->app->guestAcl()->assertGlobalAdmin();
        try {
            $profiles = $this->app->aligo()->alimtalkApi->profiles();
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus(502), null, [], $e->getMessage(), []);
        }

        return $this->render($request, $response, null, [], null, $profiles);
    }

    public function toggle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $this->app->guestAcl()->assertGlobalAdmin();
        try {
            $this->app->aligo()->settings->setEnabled(
                (string) ($input['channel'] ?? ''), ($input['action'] ?? '') === 'enable');
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus(422), null, $e->details(), null, []);
        }

        return $this->redirect($request, $response, 'admin.aligo', ['saved' => '1']);
    }

    private function render(ServerRequestInterface $request, ResponseInterface $response,
        ?array $verified, array $errors, ?string $error, array $profiles): ResponseInterface
    {
        return View::fromRequest($request)->render($response, 'admin/aligo_settings', [
            'values' => $this->app->aligo()->settings->formValues(),
            'status' => $this->app->aligo()->status(),
            'errors' => $errors, 'error' => $error, 'verified' => $verified, 'profiles' => $profiles,
            'query' => $request->getQueryParams(),
        ]);
    }
}
```

`src/Web/Routes.php`에 `/admin/mail` 줄 아래로 더한다:

```php
        $slim->get('/admin/aligo', [$aligo, 'form'])->setName('admin.aligo');
        $slim->post('/admin/aligo', [$aligo, 'save']);
        $slim->post('/admin/aligo/verify', [$aligo, 'verify'])->setName('admin.aligo.verify');
        $slim->post('/admin/aligo/profiles', [$aligo, 'profiles'])->setName('admin.aligo.profiles');
        $slim->post('/admin/aligo/toggle', [$aligo, 'toggle'])->setName('admin.aligo.toggle');
```

`$aligo = new AdminAligoController($app);`를 다른 컨트롤러를 만드는 곳과 같은 자리에 둔다.

`templates/default/admin/_settings_tabs.php`의 메일 `<a>` 다음에 넣는다:

```php
  <a class="tab<?= $active === 'aligo' ? ' tab-active' : '' ?>"<?= $active === 'aligo' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.aligo') ?>">알림톡·문자</a>
```

`templates/default/admin/aligo_settings.php`는 `templates/default/admin/mail.php`의 구조(제목·탭·폼·오류 표시)를 그대로 따르고 항목만 바꾼다. 반드시 담을 것:

- `$this->e()`로 모든 출력 이스케이프
- API 키 칸은 `value=""`에 `placeholder`로 `저장됨`(`$values['api_key_set']`일 때)
- 발신번호는 `PhoneNumber::format()`으로 보여준다
- **채널 불러오기** 버튼(`admin.aligo.profiles`)과 `$profiles` 목록에서 `senderkey`를 고르는 라디오
- **연결 확인** 버튼(`admin.aligo.verify`)과 `$verified`가 있으면 `알림톡 N건 · SMS N건 · LMS N건 남았습니다`
- 채널별 발송 허용 토글 두 개(`admin.aligo.toggle`)
- 모든 폼에 `csrf_token` 히든

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Web/AligoSettingsTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Web/Controller/AdminAligoController.php templates/default/admin/aligo_settings.php \
  templates/default/admin/_settings_tabs.php src/Web/Routes.php tests/Web/AligoSettingsTest.php
git commit -m "feat: add the Aligo account settings screen"
```

---

### Task 15: 운영 화면 — 템플릿 탭

**Files:**
- Create: `src/Web/Controller/AdminMessageController.php`
- Create: `templates/default/admin/message/_tabs.php`
- Create: `templates/default/admin/message/templates.php`
- Modify: `src/Web/Routes.php`
- Modify: `templates/default/admin/_sidebar.php`
- Test: `tests/Web/MessageTemplatesTest.php`

**Interfaces:**
- Consumes: `App::aligo()->templates` (Task 10)
- Produces:
  - 라우트 `admin.messages.templates`(GET), `admin.messages.templates.fetch`(POST), `admin.messages.templates.toggle`(POST)
  - `templates/default/admin/message/_tabs.php` — `$active`가 `'send'|'templates'|'history'`

- [ ] **Step 1: Write the failing test**

`tests/Web/MessageTemplatesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MessageTemplatesTest extends WebTestCase
{
    // adminApp() 은 Global Constraints 의 Web 테스트 규약에 있는 헬퍼를 그대로 둔다.

    #[DataProvider('connectionProvider')]
    public function testListShowsCopiesAndTheirApprovalState(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $db = $app->db();
        $db->insert('alimtalk_templates', ['tpl_code' => 'T1', 'senderkey' => 'SK1', 'name' => '주문 안내',
            'content' => '#{이름}님', 'status' => 'A', 'insp_status' => 'APR', 'enabled' => 0,
            'fetched_at' => '2026-09-17 10:00:00']);

        $html = $this->body($this->get($app, '/admin/messages/templates'));

        self::assertStringContainsString('주문 안내', $html);
        self::assertStringContainsString('T1', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testTurningOnATemplateThatIsNotApprovedFails(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $app->db()->insert('alimtalk_templates', ['tpl_code' => 'W1', 'senderkey' => 'SK1', 'name' => '대기',
            'content' => '본문', 'status' => 'R', 'insp_status' => 'REQ', 'enabled' => 0,
            'fetched_at' => '2026-09-17 10:00:00']);

        $response = $this->post($app, '/admin/messages/templates/toggle',
            ['csrf_token' => $_SESSION['csrf_token'], 'tpl_code' => 'W1', 'action' => 'enable']);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('승인', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenTheTemplateTab(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect(
            $this->get($app, '/admin/messages/templates'), '/admin/messages/templates');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/MessageTemplatesTest.php`
Expected: FAIL — 404

- [ ] **Step 3: Write minimal implementation**

`src/Web/Controller/AdminMessageController.php`에 `templates()`·`fetchTemplates()`·`toggleTemplate()` 세 메서드를 Task 14와 같은 방식으로 만든다. `fetchTemplates()`는 `$this->app->aligo()->templates->fetch()` 결과를 `가져오기 N건, 갱신 N건, 사용 중지 N건` 알림으로 넘긴다.

라우트:

```php
        $slim->get('/admin/messages/templates', [$msg, 'templates'])->setName('admin.messages.templates');
        $slim->post('/admin/messages/templates/fetch', [$msg, 'fetchTemplates'])
            ->setName('admin.messages.templates.fetch');
        $slim->post('/admin/messages/templates/toggle', [$msg, 'toggleTemplate'])
            ->setName('admin.messages.templates.toggle');
```

`templates/default/admin/_sidebar.php`의 `운영` 절 `로그인 기록` 다음에 넣는다:

```php
    <li><a href="<?= $this->url('admin.messages.send') ?>"<?php if ($section === 'messages'): ?> class="menu-active" aria-current="page"<?php endif ?> title="알림톡·문자 발송"><?= $this->icon('bell', 18) ?><span class="menu-text">알림톡·문자 발송</span></a></li>
```

`$this->icon('bell', 18)`의 `bell`이 없으면 `templates/default/` 아이콘 목록에서 있는 이름으로 바꾼다.

`templates/default/admin/message/_tabs.php`는 `_settings_tabs.php`와 같은 모양으로 발송·템플릿·이력 세 탭을 만든다.

`templates/default/admin/message/templates.php`는 목록 표(코드·이름·유형·상태·승인상태·사용)와 **가져오기** 버튼, 행마다 사용 토글, 본문·버튼을 보는 `<dialog>` 상세를 담는다. 모든 출력은 `$this->e()`를 거친다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Web/MessageTemplatesTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Web/Controller/AdminMessageController.php templates/default/admin/message/ \
  templates/default/admin/_sidebar.php src/Web/Routes.php tests/Web/MessageTemplatesTest.php
git commit -m "feat: add the alimtalk template tab to the admin"
```

---

### Task 16: 운영 화면 — 발송 탭

**Files:**
- Modify: `src/Web/Controller/AdminMessageController.php` (`send()`, `preview()`, `dispatch()`)
- Create: `templates/default/admin/message/send.php`
- Modify: `src/Web/Routes.php`
- Test: `tests/Web/MessageSendTest.php`

**Interfaces:**
- Consumes: `App::aligo()->send()` (Task 13), `Templates::usable()` (Task 10)
- Produces:
  - 라우트 `admin.messages.send`(GET), `admin.messages.send.preview`(POST), `admin.messages.send.dispatch`(POST)
  - 수신자 입력: `members[]`(회원 ID 체크) + `numbers`(여러 줄 텍스트). 둘을 합쳐 보낸다

- [ ] **Step 1: Write the failing test**

`tests/Web/MessageSendTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MessageSendTest extends WebTestCase
{
    // adminApp() 은 Global Constraints 의 Web 테스트 규약에 있는 헬퍼를 그대로 둔다.

    /** 발송이 가능한 상태까지 만든 앱 */
    private function ready(array $dbConfig): App
    {
        $app = $this->adminApp($dbConfig);
        $app->aligo()->settings->save(['user_id' => 'shop', 'api_key' => 'K',
            'sender' => '0212345678', 'senderkey' => 'SK1']);
        $app->aligo()->settings->setEnabled('sms', true);

        return $app;
    }

    private function member(App $app, string $email, string $name, ?string $phone): string
    {
        $id = $app->users()->create($email, password_hash('member-password-123', PASSWORD_DEFAULT), $name);
        if ($phone !== null) {
            $app->db()->update('users', ['phone' => $phone], 'id = :id', ['id' => $id]);
        }

        return (string) $id;
    }

    #[DataProvider('connectionProvider')]
    public function testPreviewShowsTheFirstRecipientBodyWithoutSending(array $dbConfig): void
    {
        $app = $this->ready($dbConfig);

        $html = $this->body($this->post($app, '/admin/messages/send/preview', [
            'csrf_token' => $_SESSION['csrf_token'],
            'channel' => 'sms', 'body' => '#{이름}님 안녕하세요',
            'numbers' => "010-1111-2222\n010-3333-4444", 'var_이름' => '홍길동',
        ]));

        self::assertStringContainsString('홍길동님 안녕하세요', $html);
        self::assertStringContainsString('2명', $html);
        self::assertSame(0, (int) $app->db()->selectOne('SELECT COUNT(*) AS c FROM '
            . $app->db()->table('message_jobs'))['c'], '미리보기는 보내지 않는다');
    }

    #[DataProvider('connectionProvider')]
    public function testDispatchIsRefusedWhenTheChannelIsOff(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $app->aligo()->settings->save(['user_id' => 'shop', 'api_key' => 'K',
            'sender' => '0212345678', 'senderkey' => 'SK1']);

        $response = $this->post($app, '/admin/messages/send/dispatch', [
            'csrf_token' => $_SESSION['csrf_token'],
            'channel' => 'sms', 'body' => '안녕하세요', 'numbers' => '010-1111-2222',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('허용', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testMembersWithoutAPhoneAreReportedAsSkipped(array $dbConfig): void
    {
        $app = $this->ready($dbConfig);
        $withPhone = $this->member($app, 'a@example.com', '있음', '01011112222');
        $withoutPhone = $this->member($app, 'b@example.com', '없음', null);

        $html = $this->body($this->post($app, '/admin/messages/send/preview', [
            'csrf_token' => $_SESSION['csrf_token'],
            'channel' => 'sms', 'body' => '안녕하세요',
            'members' => [$withPhone, $withoutPhone],
        ]));

        self::assertStringContainsString('1명', $html);
        self::assertStringContainsString('번호가 없어 제외', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenTheSendTab(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect($this->get($app, '/admin/messages/send'), '/admin/messages/send');
    }
}
```

`$app->users()->create()`의 인자 순서는 `tests/Web/AdminPageTest.php`에 쓰인 것과 같다(이메일, 해시, 표시이름, 관리자 여부).

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/MessageSendTest.php`
Expected: FAIL — 404

- [ ] **Step 3: Write minimal implementation**

컨트롤러에 더한다:

```php
    /** 화면 입력을 Dispatch 가 받는 모양으로 바꾼다. 미리보기와 발송이 같은 것을 쓴다. */
    private function collect(array $input): array
    {
        $vars = [];
        foreach ($input as $name => $value) {
            if (str_starts_with((string) $name, 'var_')) {
                $vars[substr((string) $name, 4)] = (string) $value;
            }
        }

        $recipients = [];
        $skipped = 0;
        foreach ((array) ($input['members'] ?? []) as $userId) {
            $row = $this->app->db()->selectOne('SELECT id, display_name, phone FROM '
                . $this->app->db()->table('users') . ' WHERE id = ?', [(string) $userId]);
            if ($row === null || ($row['phone'] ?? '') === '') {
                $skipped++;
                continue;
            }
            $recipients[] = ['phone' => (string) $row['phone'], 'name' => (string) $row['display_name'],
                'user_id' => (string) $row['id'], 'vars' => $vars];
        }
        foreach (preg_split('/[\r\n,]+/', (string) ($input['numbers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $recipients[] = ['phone' => $line, 'vars' => $vars];
            }
        }

        return [
            'request' => [
                'channel' => (string) ($input['channel'] ?? 'sms'),
                'body' => (string) ($input['body'] ?? ''),
                'title' => (string) ($input['title'] ?? ''),
                'tpl_code' => (string) ($input['tpl_code'] ?? ''),
                'failover' => ($input['failover'] ?? '') === '1',
                'created_by' => $this->app->guestAcl()->displayName(),
                'recipients' => $recipients,
            ],
            'skipped' => $skipped,
        ];
    }
```

`preview()`는 `collect()` 뒤 첫 수신자의 본문만 `Variables::apply()`로 만들어 화면에 넘기고 **보내지 않는다.** `dispatch()`는 `$this->app->aligo()->send($collected['request'])`를 부르고 성공하면 이력 상세로 보낸다. `DomainError` 422는 입력을 유지한 채 다시 렌더한다.

`$this->app->guestAcl()->displayName()`가 없으면 `Acl`에서 관리자 표시명을 얻는 기존 방법을 쓴다.

라우트:

```php
        $slim->get('/admin/messages/send', [$msg, 'send'])->setName('admin.messages.send');
        $slim->post('/admin/messages/send/preview', [$msg, 'preview'])->setName('admin.messages.send.preview');
        $slim->post('/admin/messages/send/dispatch', [$msg, 'dispatch'])->setName('admin.messages.send.dispatch');
```

`templates/default/admin/message/send.php`에 담을 것:

- 채널 라디오(알림톡·문자). 알림톡을 고르면 `Templates::usable()` 목록에서 템플릿 선택, 문자를 고르면 본문 textarea와 제목 칸
- 본문 아래 `현재 N바이트 · SMS/LMS` 표시 (JS 없이 미리보기 결과로 보여줘도 된다)
- 회원 검색 입력과 결과 체크박스(`members[]`), 번호 붙여넣기 textarea(`numbers`)
- 변수 칸(`var_이름` 형태). 알림톡은 고른 템플릿의 `Variables::names()`로, 문자는 본문에서 뽑아 만든다
- 대체발송 체크(`failover`, 알림톡일 때만)
- **미리보기** 버튼 → 결과 영역에 첫 수신자 본문·수신 인원·제외 인원 → **발송** 버튼
- 모든 폼에 `csrf_token`

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Web/MessageSendTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Web/Controller/AdminMessageController.php templates/default/admin/message/send.php \
  src/Web/Routes.php tests/Web/MessageSendTest.php
git commit -m "feat: add the admin send screen with preview and member picking"
```

---

### Task 17: 운영 화면 — 이력 탭

**Files:**
- Modify: `src/Web/Controller/AdminMessageController.php` (`history()`, `historyDetail()`, `refresh()`)
- Create: `templates/default/admin/message/history.php`
- Create: `templates/default/admin/message/history_detail.php`
- Modify: `src/Web/Routes.php`
- Test: `tests/Web/MessageHistoryTest.php`

**Interfaces:**
- Consumes: `History::jobs()`, `History::job()`, `History::refresh()`, `History::pendingCount()` (Task 12), `PhoneNumber::mask()` (Task 2)
- Produces: 라우트 `admin.messages.history`(GET), `admin.messages.history.detail`(GET `/{id}`), `admin.messages.history.refresh`(POST)

- [ ] **Step 1: Write the failing test**

`tests/Web/MessageHistoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MessageHistoryTest extends WebTestCase
{
    // adminApp() 은 Global Constraints 의 Web 테스트 규약에 있는 헬퍼를 그대로 둔다.

    private function seed(App $app): int
    {
        $db = $app->db();
        $jobId = (int) $db->insert('message_jobs', ['channel' => 'sms', 'sender' => '0212345678',
            'body' => '안녕하세요', 'failover' => 0, 'total' => 1, 'success' => 1, 'failure' => 0,
            'status' => 'sent', 'test_mode' => 0, 'created_at' => '2026-09-17 10:00:00']);
        $db->insert('message_recipients', ['job_id' => $jobId, 'mid' => 'M1', 'phone' => '01012345678',
            'body' => '안녕하세요', 'status' => 'accepted', 'requested_at' => '2026-09-17 10:00:00']);

        return $jobId;
    }

    #[DataProvider('connectionProvider')]
    public function testListMasksTheMiddleOfEveryNumber(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $this->seed($app);

        $html = $this->body($this->get($app, '/admin/messages/history'));

        self::assertStringNotContainsString('010-1234-5678', $html);
        self::assertStringContainsString('결과를 기다리는 중', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testDetailShowsTheWholeNumber(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $jobId = $this->seed($app);

        $html = $this->body($this->get($app, '/admin/messages/history/' . $jobId));

        self::assertStringContainsString('010-1234-5678', $html);
        self::assertStringContainsString('안녕하세요', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenTheHistory(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect($this->get($app, '/admin/messages/history'), '/admin/messages/history');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/MessageHistoryTest.php`
Expected: FAIL — 404

- [ ] **Step 3: Write minimal implementation**

`history()`는 화면을 그리기 전에 `refresh()`를 조용히 한 번 부른다 — 결과 조회가 실패해도 목록은 보여야 하므로 `DomainError`·`TransportFailure`를 잡아 무시한다:

```php
    public function history(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->app->aligo()->history->refresh();
        } catch (DomainError | TransportFailure $e) {
            // 조회에 실패해도 이력 목록은 보여준다. 다음 방문에 다시 시도한다.
        }
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));

        return View::fromRequest($request)->render($response, 'admin/message/history', [
            'listing' => $this->app->aligo()->history->jobs($page),
            'pending' => $this->app->aligo()->history->pendingCount(),
            'query' => $request->getQueryParams(),
        ]);
    }
```

라우트:

```php
        $slim->get('/admin/messages/history', [$msg, 'history'])->setName('admin.messages.history');
        $slim->post('/admin/messages/history/refresh', [$msg, 'refresh'])
            ->setName('admin.messages.history.refresh');
        $slim->get('/admin/messages/history/{id:[0-9]+}', [$msg, 'historyDetail'])
            ->setName('admin.messages.history.detail');
```

`{id}` 라우트를 `/refresh` **뒤에** 두어야 `refresh`가 id로 잡히지 않는다.

`history.php`: 작업 목록 표(요청 시각·채널·템플릿·총·성공·실패·상태·테스트 여부), 각 행에서 상세로 가는 링크, `$pending > 0`이면 `아직 결과를 기다리는 중인 발송이 N건 있습니다. 접속이 없으면 갱신이 늦어질 수 있습니다.`와 **갱신** 버튼.

`history_detail.php`: 작업 요약과 수신자 표(번호는 `PhoneNumber::format()`으로 전체, 상태·사유·전송·결과 시각, 대체발송이 있으면 그 결과).

목록 화면의 번호는 `PhoneNumber::mask()`를 쓴다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Web/MessageHistoryTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Web/Controller/AdminMessageController.php templates/default/admin/message/history.php \
  templates/default/admin/message/history_detail.php src/Web/Routes.php tests/Web/MessageHistoryTest.php
git commit -m "feat: add the send history screen with visit-driven result refresh"
```

---

### Task 18: 선택적 CLI와 문서

**Files:**
- Create: `bin/messages.php`
- Create: `docs/messaging.md`
- Modify: `AGENTS.md` (기능 지도)
- Modify: `README.md` (기능 목록)
- Test: `tests/Aligo/MessagesCliTest.php`

**Interfaces:**
- Consumes: `History::refresh()` (Task 12)
- Produces: `php bin/messages.php refresh [건수]` — 종료 코드 0, 갱신 건수를 출력

- [ ] **Step 1: Write the failing test**

`tests/Aligo/MessagesCliTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use PHPUnit\Framework\TestCase;

final class MessagesCliTest extends TestCase
{
    public function testRefreshCommandRunsAndReports(): void
    {
        $output = [];
        $status = 0;
        exec('php ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/messages.php') . ' refresh 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString('갱신', implode("\n", $output));
    }

    public function testUnknownCommandExplainsUsage(): void
    {
        $output = [];
        $status = 0;
        exec('php ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/messages.php') . ' nope 2>&1', $output, $status);

        self::assertSame(1, $status);
        self::assertStringContainsString('refresh', implode("\n", $output));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/MessagesCliTest.php`
Expected: FAIL — `Could not open input file: bin/messages.php`

- [ ] **Step 3: Write minimal implementation**

`bin/messages.php`는 `bin/migrate.php`의 부팅 방식(자동 로더와 설정을 읽어 `App`을 만드는 부분)을 그대로 따른다. 그 파일을 열어 같은 방식으로 앱을 얻은 뒤:

```php
$command = $argv[1] ?? '';
if ($command !== 'refresh') {
    fwrite(STDERR, "사용법: php bin/messages.php refresh [건수]\n"
        . "발송 결과를 알리고에 물어 갱신합니다. cron 없이도 관리자 화면 방문으로 갱신되므로 선택 사항입니다.\n");
    exit(1);
}

$limit = (int) ($argv[2] ?? 20);
$done = $app->aligo()->history->refresh(max(1, $limit));
fwrite(STDOUT, $done . '건의 발송 결과를 갱신했습니다.' . PHP_EOL);
exit(0);
```

`docs/messaging.md`에 담을 것: 알리고 계정 준비 절차(발신번호 사전 등록, 카카오채널·템플릿 검수), 설정 화면 사용법, 알림톡과 문자의 차이, EUC-KR 제약(이모지 불가), 결과가 방문 기반으로 갱신된다는 점과 CLI가 선택 사항이라는 점, 확장이 쓰는 `$app->aligo()->send()` 예제.

`AGENTS.md`의 `현재 기능 지도`와 `README.md` 기능 목록에 알림톡·문자 발송을 한 줄씩 더한다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/MessagesCliTest.php`
Expected: PASS (2 tests)

그다음 전체: `./vendor/bin/phpunit`
Expected: PASS (기존 851개 + 이번에 더한 것 전부)

- [ ] **Step 5: Commit**

```bash
git add bin/messages.php docs/messaging.md AGENTS.md README.md tests/Aligo/MessagesCliTest.php
git commit -m "feat: add an optional result refresh CLI and document the messaging engine"
```

---

## 완료 확인

계획 1이 끝나면 아래가 모두 참이어야 한다.

- `./vendor/bin/phpunit` 전체 통과
- 관리자가 설정 → 알림톡·문자에서 계정을 저장하고 **연결 확인**으로 잔여 건수를 본다
- 운영 → 알림톡·문자 발송에서 문자를 여러 명에게 보내고 이력에서 결과를 본다
- 승인 템플릿을 가져와 켠 뒤 알림톡을 보낸다
- `$app->aligo()->send([...])`로 확장이 일괄 발송한다
- `users.phone` 컬럼이 있다 (계획 2가 채운다)
