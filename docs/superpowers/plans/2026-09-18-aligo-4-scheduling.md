# 알리고 예약 발송과 취소 구현 계획 (4/4)

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 관리자와 확장이 알림톡·문자를 원하는 시각에 보내도록 예약하고, 나가기 전에 취소할 수 있게 한다.

**Architecture:** 알리고는 예약을 발송 요청의 파라미터 하나로 받는다 — 알림톡은 `senddate`, 문자는 `rdate`+`rtime`. 예약을 거는 순간 메시지는 알리고로 넘어가고, 우리가 다시 손댈 수 있는 유일한 방법은 취소 호출뿐이다. 그래서 이 계획의 규칙은 전부 "이미 남의 손에 있는 것을 어떻게 정직하게 다루는가"에서 나온다.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim 4, PDO(SQLite·MySQL), PHPUnit 10.5. 새 Composer 의존성 없음.

**Spec:** [docs/superpowers/specs/2026-09-17-aligo-messaging-design.md](../specs/2026-09-17-aligo-messaging-design.md) §5-1

**선행:** 계획 1 [2026-09-17-aligo-1-engine.md](2026-09-17-aligo-1-engine.md) 전체. 이 계획은 그 위에 얹는다.

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다. 호스팅 cron을 요구하지 않는다.
- 모든 PHP 파일은 `declare(strict_types=1);`, 클래스는 `final`.
- 네임스페이스 `GnuCms\Aligo`, 테스트 `GnuCms\Tests\Aligo`·`GnuCms\Tests\Web`.
- 주석과 화면 문구는 한국어, 커밋 제목은 영어 conventional commit.
- 표 이름은 반드시 `$db->table('이름')`을 거친다. 오류는 `GnuCms\Error\DomainError`.
- **발송은 재시도하지 않는다.** 예약도 마찬가지다 — 예약 요청이 실패했는지 성공했는지 모를 때 다시 보내면 같은 예약이 두 번 걸린다.
- **예약도 채널 발송 허용 스위치를 지난다.** 확장이 부르는 경로도 같다.
- 시각은 전부 `GnuCms\Support\Clock` 기준 UTC로 저장하고, 알리고에 보낼 때만 사이트 시간대 문자열로 만든다. `Clock::now()`는 `gmdate`다.
- 모든 화면은 전역 관리자만, 모든 POST는 CSRF를 먼저 확인한다. 모든 출력은 `$this->e()`를 거친다.
- 테스트는 실제 알리고를 부르지 않는다. `tests/Support/FakeAligoTransport.php`를 끼운다.
- `phpunit.xml`이 `failOnWarning`·`failOnRisky`를 켠다 — 출력이 깨끗해야 한다.
- 관련 테스트를 먼저 돌리고, 마지막에 `./vendor/bin/phpunit` 전체도 돌린다.

### 알리고 규격 (확인된 값)

| | 알림톡 | 문자 |
|---|---|---|
| 예약 파라미터 | `senddate` = `YYYYMMDDHHMMSS` | `rdate` = `YYYYMMDD`, `rtime` = `HHII` |
| 취소 경로 | `/akv10/cancel/` | `/cancel/` |
| 취소 파라미터 | `mid` | `mid` |
| 취소 시한 | 발송 5분 전까지 | 발송 5분 전까지 |
| 예약 하한 | 문서에 없음 | 현재 +10분 이후 |

두 채널에 **같은 하한(+10분)** 을 적용한다. 규칙이 하나면 화면도 하나로 설명된다. 상한 **30일**은 우리가 정한다.

---

### Task 1: 취소 API 호출

**Files:**
- Modify: `src/Aligo/AlimtalkApi.php`
- Modify: `src/Aligo/SmsApi.php`
- Test: `tests/Aligo/AlimtalkApiTest.php`, `tests/Aligo/SmsApiTest.php`

**Interfaces:**
- Consumes: 기존 `call()` 사설 메서드
- Produces:
  - `AlimtalkApi::cancel(string $mid): void` — `/akv10/cancel/`
  - `SmsApi::cancel(string $mid): void` — `/cancel/`
  - 둘 다 실패는 기존과 같이 `DomainError::serviceUnavailable(ResultCodes::…)`

**Note:** 발송 쪽은 손대지 않는다. 두 send 메서드는 이미 `array $fields`를 그대로 넘기므로, 예약 파라미터는 Task 3이 만들어 넣으면 된다.

- [ ] **Step 1: Write the failing test**

각 테스트 파일에 더한다. `AlimtalkApiTest`:

```php
    #[DataProvider('connectionProvider')]
    public function testCancelSendsTheMidToTheCancelPath(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"code":0}');

        $api->cancel('M77');

        $request = $this->transport->requests[0];
        self::assertStringContainsString('/akv10/cancel/', $request['url']);
        self::assertSame('M77', $request['fields']['mid']);
        self::assertSame('KEY', $request['fields']['apikey']);
    }

    #[DataProvider('connectionProvider')]
    public function testCancelTooLateBecomesAReadableError(array $config): void
    {
        $api = $this->api($config);
        $this->transport->queue(200, '{"code":-804,"message":"too late"}');

        $this->expectException(DomainError::class);
        $api->cancel('M77');
    }
```

`SmsApiTest`에는 같은 모양으로, 경로는 `/cancel/`, 성공 응답은 `{"result_code":1,"cancel_date":"2026-09-18 10:00:00"}`, 실패 응답은 `{"result_code":-804,"message":"too late"}`로 쓴다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/AlimtalkApiTest.php tests/Aligo/SmsApiTest.php`
Expected: FAIL — `Call to undefined method … ::cancel()`

- [ ] **Step 3: Write minimal implementation**

`AlimtalkApi`에 더한다:

```php
    /** 예약 발송 취소. 알리고는 발송 5분 전까지만 받아 준다. */
    public function cancel(string $mid): void
    {
        $this->call('/akv10/cancel/', ['mid' => $mid]);
    }
```

`SmsApi`에 같은 모양으로 `/cancel/`을 더한다. 둘 다 반환값을 쓰지 않는다 — 성공 여부는 예외로만 갈린다.

`ResultCodes`의 두 표에 취소 관련 코드가 없으면 더한다: `-804`는 이미 SMS 표에 "발송 5분 전까지만 취소할 수 있습니다."로 있다. 알림톡 표에도 같은 뜻의 항목이 필요한지 확인하고, 알리고 문서에서 확인되지 않는 코드는 **지어내지 말고** 기본 처리에 맡긴다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/AlimtalkApiTest.php tests/Aligo/SmsApiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/AlimtalkApi.php src/Aligo/SmsApi.php tests/Aligo/AlimtalkApiTest.php tests/Aligo/SmsApiTest.php
git commit -m "feat: cancel a scheduled Aligo send through either API"
```

---

### Task 2: 스키마 판 24와 JobStatus 확장

**Files:**
- Modify: `src/Db/Schema.php`
- Modify: `src/Aligo/JobStatus.php`
- Test: `tests/Db/AligoSchemaTest.php`, `tests/Aligo/JobStatusTest.php` (없으면 만든다)

**Interfaces:**
- Produces:
  - `message_jobs.scheduled_at` `{DATETIME}` NULL, `message_jobs.cancelled_at` `{DATETIME}` NULL
  - 인덱스 `ix_message_jobs_scheduled` on `(scheduled_at, status)`
  - `JobStatus::VALUES`에 `scheduled`·`cancelled` 추가 (일곱 개)
  - `JobStatus::of()`는 그대로 — 예약·취소는 집계가 아니라 작업 자체의 사실이므로 호출부가 앞서 판단한다
  - `Schema::VERSION === '24'`

**Why 판을 올리는가:** 라이브 사이트가 이미 판 23을 적용했다. 표를 다시 만드는 것이 아니라 `addColumnIfMissing()`으로 칸만 붙여야 한다.

- [ ] **Step 1: Write the failing test**

`tests/Db/AligoSchemaTest.php`에 더한다:

```php
    #[DataProvider('connectionProvider')]
    public function testScheduleColumnsExistOnAFreshInstall(array $config): void
    {
        $db = $this->freshDatabase($config);
        self::assertSame([], $db->select('SELECT scheduled_at, cancelled_at FROM '
            . $db->table('message_jobs')));
    }

    #[DataProvider('connectionProvider')]
    public function testUpgradingAVersion23InstallAddsTheScheduleColumns(array $config): void
    {
        $db = $this->freshDatabase($config);
        $db->execute('ALTER TABLE ' . $db->table('message_jobs') . ' DROP COLUMN scheduled_at');
        $db->execute('ALTER TABLE ' . $db->table('message_jobs') . ' DROP COLUMN cancelled_at');

        (new Schema($db))->migrateAligoMessaging();

        self::assertSame([], $db->select('SELECT scheduled_at, cancelled_at FROM '
            . $db->table('message_jobs')));
    }

    public function testSchemaVersionIsTwentyFour(): void
    {
        self::assertSame('24', Schema::VERSION);
    }
```

`tests/Aligo/JobStatusTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\JobStatus;
use PHPUnit\Framework\TestCase;

final class JobStatusTest extends TestCase
{
    public function testSevenValuesIncludingScheduleAndCancellation(): void
    {
        self::assertSame(
            ['scheduled', 'sending', 'sent', 'failed', 'partial', 'unknown', 'cancelled'],
            JobStatus::VALUES
        );
    }

    public function testTallyRulesAreUnchanged(): void
    {
        // 예약·취소는 수신자 집계가 아니라 작업 자체의 사실이므로 of() 는 그대로다.
        self::assertSame('sending', JobStatus::of(0, 0, 1, 0));
        self::assertSame('partial', JobStatus::of(1, 1, 0, 0));
        self::assertSame('unknown', JobStatus::of(1, 0, 0, 1));
        self::assertSame('sent', JobStatus::of(2, 0, 0, 0));
        self::assertSame('failed', JobStatus::of(0, 2, 0, 0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Db/AligoSchemaTest.php tests/Aligo/JobStatusTest.php`
Expected: FAIL — `no such column: scheduled_at`

- [ ] **Step 3: Write minimal implementation**

`Schema::VERSION`을 `'24'`로 바꾼다. `aligoStatements()`의 `message_jobs` 정의에 `test_mode` 아래로 두 칸을 더한다:

```
                scheduled_at {DATETIME}   NULL,
                cancelled_at {DATETIME}   NULL,
```

`INDEXES`에 `'ix_message_jobs_scheduled'`를 더하고 `aligoStatements()` 끝에:

```php
            'CREATE INDEX ix_message_jobs_scheduled ON message_jobs (scheduled_at, status)',
```

`migrateAligoMessaging()` 끝, `users.phone` 줄 옆에 더한다:

```php
        // 판 23 을 이미 적용한 설치에는 표가 있으므로 칸만 붙인다.
        $this->addColumnIfMissing('message_jobs', 'scheduled_at', $this->expand('{DATETIME} NULL'));
        $this->addColumnIfMissing('message_jobs', 'cancelled_at', $this->expand('{DATETIME} NULL'));
```

`expand()`가 사설이면 그 자리에서 쓸 수 있는 형태로 맞춘다 — `addColumnIfMissing()`이 다른 곳에서 어떤 형식의 타입 문자열을 받는지 먼저 보고 같은 방식을 쓴다.

`JobStatus::VALUES`를 일곱 개로 바꾸고, 클래스 docblock에 예약·취소가 집계에서 나오지 않는 이유를 한 줄 적는다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Db/ tests/Aligo/JobStatusTest.php`
Expected: PASS. 판 문자열을 기대하는 기존 테스트가 있으면 `'24'`로 맞추고 보고서에 적는다.

- [ ] **Step 5: Commit**

```bash
git add src/Db/Schema.php src/Aligo/JobStatus.php tests/Db/AligoSchemaTest.php tests/Aligo/JobStatusTest.php
git commit -m "feat: add the schedule columns in schema version 24"
```

---

### Task 3: 예약 발송

**Files:**
- Modify: `src/Aligo/Dispatch.php`
- Create: `src/Aligo/SendTime.php`
- Test: `tests/Aligo/SendTimeTest.php`, `tests/Aligo/DispatchTest.php`

**Interfaces:**
- Consumes: `Clock::timestamp()`, `Clock::now()`
- Produces:
  - `SendTime::parse(mixed $value): ?string` — 화면·API가 준 값을 검증해 UTC `Y-m-d H:i:s`로. 비어 있으면 `null`(즉시). 하한·상한을 벗어나면 `DomainError::validation(['scheduled_at' => …])`
  - `SendTime::MIN_MINUTES = 10`, `SendTime::MAX_DAYS = 30`
  - `SendTime::alimtalk(string $utc): string` — `YYYYMMDDHHMMSS`
  - `SendTime::sms(string $utc): array` — `['rdate' => 'YYYYMMDD', 'rtime' => 'HHII']`
  - `Dispatch::send()`가 `scheduled_at`을 받아 저장하고 알리고 파라미터로 넘긴다

**시간대 주의:** 저장은 UTC, 알리고에 보내는 문자열은 **사이트가 실제로 쓰는 시간대**여야 한다. 알리고는 한국 서비스이므로 `Asia/Seoul`이 맞다. `SendTime`의 변환 메서드에서 한 번만 바꾸고, 어느 쪽이 UTC이고 어느 쪽이 KST인지 주석으로 못 박는다. 이 한 곳이 틀리면 9시간 어긋난 시각에 발송된다.

- [ ] **Step 1: Write the failing test**

`tests/Aligo/SendTimeTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Aligo;

use GnuCms\Aligo\SendTime;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use PHPUnit\Framework\TestCase;

final class SendTimeTest extends TestCase
{
    public function testBlankMeansSendNow(): void
    {
        self::assertNull(SendTime::parse(''));
        self::assertNull(SendTime::parse(null));
    }

    public function testRefusesTooSoonAndTooFar(): void
    {
        $soon = gmdate('Y-m-d\TH:i', Clock::timestamp() + 5 * 60);
        $far = gmdate('Y-m-d\TH:i', Clock::timestamp() + 31 * 86400);

        foreach ([$soon, $far, '어제', '2026-13-45T99:99'] as $value) {
            try {
                SendTime::parse($value);
                self::fail($value . ' 는 거절해야 한다');
            } catch (DomainError $e) {
                self::assertArrayHasKey('scheduled_at', $e->details());
            }
        }
    }

    public function testAcceptsAValidTimeAndStoresItAsUtc(): void
    {
        $at = SendTime::parse(gmdate('Y-m-d\TH:i', Clock::timestamp() + 3600));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $at);
    }

    public function testFormatsForBothApisInKoreanTime(): void
    {
        // 2026-09-18 01:00 UTC 는 한국 시각 10:00 이다. 알리고는 한국 시각으로 받는다.
        self::assertSame('20260918100000', SendTime::alimtalk('2026-09-18 01:00:00'));
        self::assertSame(['rdate' => '20260918', 'rtime' => '1000'],
            SendTime::sms('2026-09-18 01:00:00'));
    }
}
```

`DispatchTest`에 더한다 (기존 헬퍼를 그대로 쓴다):

```php
    #[DataProvider('connectionProvider')]
    public function testSchedulesInsteadOfSendingNow(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(1);

        $at = gmdate('Y-m-d\TH:i', Clock::timestamp() + 3600);
        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'scheduled_at' => $at, 'recipients' => [['phone' => '01012345678']]]);

        $job = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_jobs')
            . ' WHERE id = ?', [$jobId]);
        self::assertSame('scheduled', $job['status']);
        self::assertNotNull($job['scheduled_at']);

        $fields = $this->transport->requests[0]['fields'];
        self::assertArrayHasKey('rdate', $fields);
        self::assertArrayHasKey('rtime', $fields);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkScheduleUsesSenddate(array $config): void
    {
        // 알림톡은 rdate·rtime 이 아니라 senddate 한 칸을 쓴다.
        // (템플릿 준비는 기존 알림톡 테스트와 같은 방식으로 한다.)
    }

    #[DataProvider('connectionProvider')]
    public function testARefusedScheduleCreatesNoJobAndSendsNothing(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);

        try {
            $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
                'scheduled_at' => gmdate('Y-m-d\TH:i', Clock::timestamp() + 60),
                'recipients' => [['phone' => '01012345678']]]);
            self::fail('10분 안쪽 예약은 거절해야 한다');
        } catch (DomainError $e) {
            self::assertArrayHasKey('scheduled_at', $e->details());
        }
        self::assertSame([], $this->transport->requests);
        self::assertSame(0, (int) $this->db->selectOne('SELECT COUNT(*) AS c FROM '
            . $this->db->table('message_jobs'))['c']);
    }

    #[DataProvider('connectionProvider')]
    public function testAScheduledSendStillNeedsTheChannelSwitch(array $config): void
    {
        $this->boot($config);
        $this->expectException(DomainError::class);
        $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'scheduled_at' => gmdate('Y-m-d\TH:i', Clock::timestamp() + 3600),
            'recipients' => [['phone' => '01012345678']]]);
    }
```

`testAlimtalkScheduleUsesSenddate`의 본문은 기존 `testAlimtalkNeedsAnEnabledTemplateAndSendsFailoverText`가 템플릿을 준비하는 방식을 그대로 베껴 채운다. 확인할 것은 요청 필드에 `senddate`가 있고 `rdate`·`rtime`은 없다는 것이다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/SendTimeTest.php tests/Aligo/DispatchTest.php`
Expected: FAIL — `Class "GnuCms\Aligo\SendTime" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Aligo/SendTime.php`를 만든다. 검증은 `DomainError::validation(['scheduled_at' => …])`로 하고, 메시지는 무엇이 잘못됐는지 말한다 — "10분 뒤부터 30일 이내로 정해 주세요." 처럼.

`Dispatch::send()`에서:
- 수신자 준비 전에 `$scheduledAt = SendTime::parse($request['scheduled_at'] ?? null);`
- `message_jobs` insert에 `'scheduled_at' => $scheduledAt`
- 작업 상태: `$scheduledAt !== null`이면 집계와 무관하게 `'scheduled'`. 아니면 지금 규칙 그대로
- `alimtalkFields()`에 `senddate`, `smsFields()`에 `rdate`·`rtime`을 `$scheduledAt !== null`일 때만 더한다
- `finished_at`은 예약이면 `null`

**거절은 작업을 만들기 전에** 일어나야 한다. 시각이 잘못됐다고 이미 행이 생긴 뒤 던지면 유령 작업이 남는다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/SendTimeTest.php tests/Aligo/DispatchTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/SendTime.php src/Aligo/Dispatch.php tests/Aligo/SendTimeTest.php tests/Aligo/DispatchTest.php
git commit -m "feat: schedule a send instead of firing it immediately"
```

---

### Task 4: 작업 취소와 부분 취소 보고

**Files:**
- Modify: `src/Aligo/Dispatch.php`
- Modify: `src/Aligo/AligoService.php`
- Test: `tests/Aligo/DispatchTest.php`

**Interfaces:**
- Consumes: `AlimtalkApi::cancel()`, `SmsApi::cancel()` (Task 1)
- Produces:
  - `Dispatch::cancel(int $jobId): array` — `['cancelled' => int, 'failed' => int, 'reasons' => list<string>]`
  - `AligoService::cancel(int $jobId): array` — 위임

**왜 배열을 돌려주는가:** 500명이 넘는 작업은 `mid`가 여러 개다. 그중 일부만 취소될 수 있고, **그 사실을 삼키면 관리자는 전부 멈춘 줄 안다.**

- [ ] **Step 1: Write the failing test**

```php
    #[DataProvider('connectionProvider')]
    public function testCancelsEveryMidOfTheJob(array $config): void
    {
        $this->boot($config);
        $this->settings->setEnabled('sms', true);
        $this->queueSmsOk(500);
        $this->queueSmsOk(2);
        $recipients = [];
        for ($i = 0; $i < 502; $i++) {
            $recipients[] = ['phone' => '010' . str_pad((string) $i, 8, '0', STR_PAD_LEFT)];
        }
        $jobId = $this->dispatch->send(['channel' => 'sms', 'body' => '안녕하세요',
            'scheduled_at' => gmdate('Y-m-d\TH:i', Clock::timestamp() + 3600),
            'recipients' => $recipients]);

        $this->transport->queue(200, '{"result_code":1,"cancel_date":"2026-09-18 10:00:00"}');
        $this->transport->queue(200, '{"result_code":1,"cancel_date":"2026-09-18 10:00:00"}');

        $result = $this->dispatch->cancel($jobId);

        self::assertSame(2, $result['cancelled']);
        self::assertSame(0, $result['failed']);
        $job = $this->db->selectOne('SELECT * FROM ' . $this->db->table('message_jobs')
            . ' WHERE id = ?', [$jobId]);
        self::assertSame('cancelled', $job['status']);
        self::assertNotNull($job['cancelled_at']);
    }

    #[DataProvider('connectionProvider')]
    public function testReportsAPartialCancellationInsteadOfHidingIt(array $config): void
    {
        // 두 묶음 중 하나만 취소된다. 작업은 취소되지 않은 것으로 남고, 이유가 그대로 올라온다.
        // (위와 같은 방식으로 502명 예약을 만든 뒤)
        $this->transport->queue(200, '{"result_code":1,"cancel_date":"2026-09-18 10:00:00"}');
        $this->transport->queue(200, '{"result_code":-804,"message":"too late"}');

        $result = $this->dispatch->cancel($jobId);

        self::assertSame(1, $result['cancelled']);
        self::assertSame(1, $result['failed']);
        self::assertNotSame([], $result['reasons']);
        self::assertSame('scheduled', $this->db->selectOne('SELECT status FROM '
            . $this->db->table('message_jobs') . ' WHERE id = ?', [$jobId])['status']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefusesToCancelWhatIsNotScheduled(array $config): void
    {
        // 즉시 발송한 작업은 취소할 수 없다 — 이미 나갔다.
        $this->expectException(DomainError::class);
        $this->dispatch->cancel($immediateJobId);
    }
```

세 번째 테스트의 `$immediateJobId`는 예약 없이 보낸 작업으로 만든다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/DispatchTest.php`
Expected: FAIL — `Call to undefined method … ::cancel()`

- [ ] **Step 3: Write minimal implementation**

`Dispatch::cancel(int $jobId): array`:
- 작업을 읽고, 예약이 아니거나 이미 취소됐으면 `DomainError::validation`
- 그 작업 수신자들의 서로 다른 `mid`를 모은다
- `mid`마다 채널에 맞는 `cancel()`을 부르고, 성공·실패를 센다. **실패 하나가 나머지를 막지 않는다** — 다음 `mid`도 시도한다
- 전부 취소됐을 때만 작업을 `cancelled`로 바꾸고 `cancelled_at`을 찍는다. 하나라도 남았으면 상태를 건드리지 않는다 — 나갈 메시지가 남아 있는데 취소됐다고 적으면 거짓말이다
- 취소된 `mid`의 수신자는 `status = 'cancelled'`로
- **취소는 재시도하지 않는다.** 실패한 `mid`는 이유와 함께 보고하고 끝낸다

`AligoService::cancel()`은 그대로 위임한다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/DispatchTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/Dispatch.php src/Aligo/AligoService.php tests/Aligo/DispatchTest.php
git commit -m "feat: cancel a scheduled job and report what could not be cancelled"
```

---

### Task 5: 결과 폴링을 예약에 맞추기

**Files:**
- Modify: `src/Aligo/History.php`
- Test: `tests/Aligo/HistoryTest.php`

**Interfaces:**
- Produces: 예약 건이 발송 시각 전에는 조회되지 않고, 포기 시한이 발송 시각 기준으로 센다

**두 가지가 지금 틀려 있다:**
1. `refreshPrimary()`·`refreshFallbacks()`는 `status = 'accepted'`만 보고 발송 시각을 안 본다 — 알리고가 **보내지도 않은** 건에 조회 예산을 쓴다.
2. `giveUpOnStaleRows()`는 `requested_at`부터 7일을 센다 — 5일 뒤로 예약한 작업이 **나가기도 전에** `unknown`이 된다.

- [ ] **Step 1: Write the failing test**

```php
    #[DataProvider('connectionProvider')]
    public function testDoesNotPollAJobWhoseSendTimeHasNotCome(array $config): void
    {
        $this->boot($config);
        $future = gmdate('Y-m-d H:i:s', Clock::timestamp() + 3600);
        $this->seedScheduled('sms', 'M1', $future);

        self::assertSame(0, $this->history->refresh());
        self::assertSame([], $this->transport->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testPollsOnceTheSendTimeHasPassed(array $config): void
    {
        $this->boot($config);
        $past = gmdate('Y-m-d H:i:s', Clock::timestamp() - 3600);
        $this->seedScheduled('sms', 'M1', $past);
        $this->transport->queue(200, (string) json_encode(['result_code' => 1, 'list' => [
            ['mdid' => 'D1', 'receiver' => '01012345678', 'sms_state' => '전송성공'],
        ]]));

        self::assertSame(1, $this->history->refresh());
    }

    #[DataProvider('connectionProvider')]
    public function testTheGiveUpClockCountsFromTheScheduledTimeNotTheRequest(array $config): void
    {
        $this->boot($config);
        // 8일 전에 요청했지만 발송은 어제였다 — 아직 포기할 때가 아니다.
        $requested = gmdate('Y-m-d H:i:s', Clock::timestamp() - 8 * 86400);
        $scheduled = gmdate('Y-m-d H:i:s', Clock::timestamp() - 86400);
        $jobId = $this->seedScheduled('sms', 'M1', $scheduled, $requested);
        $this->transport->queue(200, '{"result_code":1,"list":[]}');

        $this->history->refresh();

        self::assertSame('accepted', $this->db->selectOne('SELECT status FROM '
            . $this->db->table('message_recipients') . ' WHERE job_id = ?', [$jobId])['status']);
    }
```

`seedScheduled(string $channel, string $mid, string $scheduledAt, ?string $requestedAt = null): int`는 이 테스트 파일의 private 헬퍼로, 기존 `seed()`와 같은 방식이되 작업에 `scheduled_at`을 넣고 상태를 `scheduled`로 둔다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/HistoryTest.php`
Expected: FAIL — 예약 건이 조회되고, 포기 시한이 요청 기준으로 센다

- [ ] **Step 3: Write minimal implementation**

두 후보 쿼리에 작업을 조인해 `scheduled_at IS NULL OR scheduled_at <= :now` 조건을 더한다. 포기 쓸이 두 곳은 `requested_at` 대신 `COALESCE(scheduled_at, requested_at)` 기준으로 센다 — 작업을 조인해야 하므로 지금의 `DISTINCT job_id` 구조를 그대로 살려 쓴다.

주석으로 이유를 적는다. 다음 사람이 "왜 조인이 붙었지"를 다시 추적하지 않게.

`recomputeJob()`도 봐야 한다: 예약 시각이 아직 안 온 작업은 집계와 무관하게 `scheduled`로 남아야 하고, 취소된 작업은 `cancelled`로 남아야 한다. 둘 다 `JobStatus::of()`가 아니라 작업 자체의 사실이므로 `recomputeJob()`이 먼저 걸러야 한다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/HistoryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/History.php tests/Aligo/HistoryTest.php
git commit -m "feat: keep scheduled jobs out of polling until their time comes"
```

---

### Task 6: 설정이 바뀌면 예약을 자동 취소

**Files:**
- Modify: `src/Aligo/Settings.php`
- Modify: `src/Aligo/Templates.php`
- Modify: `src/Aligo/AligoService.php`
- Test: `tests/Aligo/SettingsTest.php`, `tests/Aligo/TemplatesTest.php`

**Interfaces:**
- Consumes: `Dispatch::cancel()` (Task 4)
- Produces:
  - `Settings::setEnabled($channel, false)`가 그 채널의 예약을 취소 요청하고 결과를 돌려준다
  - `Templates::fetch()`가 승인을 잃은 템플릿의 예약을 취소 요청하고 `fetch()`의 반환에 그 사실을 더한다

**의존 방향 주의:** `Settings`와 `Templates`는 지금 `Dispatch`를 모른다. 거꾸로 `Dispatch`가 둘을 안다. 순환을 만들지 말고, 취소를 실제로 수행하는 조율은 **`AligoService`에 둔다** — 그것이 이미 모든 조각을 쥐고 있는 유일한 곳이다. `Settings::setEnabled()`는 스위치만 바꾸고, `AligoService`가 그 뒤에 취소를 부른다. 이 판단이 맞는지 먼저 확인하고, 더 나은 배치가 보이면 보고서에 적고 그렇게 한다.

- [ ] **Step 1: Write the failing test**

```php
    #[DataProvider('connectionProvider')]
    public function testTurningAChannelOffCancelsItsSchedules(array $config): void
    {
        // 문자 예약을 하나 걸어 두고 채널을 끈다. 예약이 취소 요청되어야 한다.
        $result = $service->setChannelEnabled('sms', false);

        self::assertSame(1, $result['cancelled']);
        self::assertSame('cancelled', /* 그 작업의 상태 */);
    }

    #[DataProvider('connectionProvider')]
    public function testACancellationThatFailsIsReportedNotSwallowed(array $config): void
    {
        // 취소가 -804 로 거절되면 그 사실이 반환값에 남아야 한다.
        self::assertSame(1, $result['failed']);
        self::assertNotSame([], $result['reasons']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefetchCancelsSchedulesOfATemplateThatLostApproval(array $config): void
    {
        // 승인을 잃은 템플릿으로 걸린 예약이 취소 요청되고, fetch() 결과에 건수가 들어간다.
    }
```

세 테스트의 준비 부분은 기존 `SettingsTest`·`TemplatesTest`의 부팅 방식을 그대로 쓴다. 예약 작업은 `Dispatch::send()`에 `scheduled_at`을 주어 만든다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Aligo/SettingsTest.php tests/Aligo/TemplatesTest.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

`AligoService`에 `setChannelEnabled(string $channel, bool $on): array`를 더한다. 끄는 경우에만 그 채널의 `scheduled` 작업을 찾아 `Dispatch::cancel()`을 부르고, 합계를 돌려준다. 켜는 경우는 스위치만 바꾼다.

`Templates::fetch()`는 승인을 잃어 꺼진 사본의 `tpl_code`를 모아 돌려주고, `AligoService`에 `importTemplates(): array`를 두어 그 코드들로 걸린 예약을 취소한 뒤 합계를 더해 돌려준다.

**실패한 취소는 절대 삼키지 않는다.** 스위치는 꺼졌는데 예약은 살아 있는 상태가 가장 위험하다 — 관리자는 막았다고 믿는다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Aligo/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Aligo/ tests/Aligo/
git commit -m "feat: cancel schedules when a channel or template stops allowing them"
```

---

### Task 7: 화면 — 예약 입력과 취소

**Files:**
- Modify: `src/Web/Controller/AdminMessageController.php`
- Modify: `src/Web/Controller/AdminAligoController.php`
- Modify: `templates/default/admin/message/send.php`, `history.php`, `history_detail.php`
- Modify: `src/Web/Routes.php`
- Test: `tests/Web/MessageSendTest.php`, `tests/Web/MessageHistoryTest.php`

**Interfaces:**
- Produces: 라우트 `admin.messages.history.cancel` (POST), 발송 화면의 시각 입력, 이력의 예약 열과 취소 버튼

- [ ] **Step 1: Write the failing test**

```php
    #[DataProvider('connectionProvider')]
    public function testSchedulingFromTheScreenCreatesAScheduledJob(array $dbConfig): void
    {
        // 발송 탭에서 시각을 넣어 보내면 작업이 scheduled 로 생긴다.
    }

    #[DataProvider('connectionProvider')]
    public function testARefusedTimeKeepsWhatTheAdminTyped(array $dbConfig): void
    {
        // 10분 안쪽 시각은 422 로 돌아오고, 본문과 수신자 입력이 화면에 남아 있다.
    }

    #[DataProvider('connectionProvider')]
    public function testHistoryShowsTheScheduleAndOffersCancel(array $dbConfig): void
    {
        // 예약 작업의 이력에 발송 예정 시각과 취소 버튼이 보인다.
    }

    #[DataProvider('connectionProvider')]
    public function testCancellingFromTheScreenReportsPartialFailure(array $dbConfig): void
    {
        // 일부만 취소된 경우 화면이 "N개 취소, N개는 …" 를 그대로 보여준다.
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotCancel(array $dbConfig): void
    {
        // 취소 라우트는 로그인 리다이렉트로 막힌다.
    }
```

기존 Web 테스트 파일의 `adminApp($dbConfig)` 헬퍼와 CSRF 규약을 그대로 쓴다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/MessageSendTest.php tests/Web/MessageHistoryTest.php`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

**발송 탭:** `datetime-local` 입력 하나. 비우면 즉시 발송이라고 라벨에 적는다. 미리보기에 "2026-09-20 14:00에 발송 예정"을 함께 보여준다 — 지금 나가는 것과 나중에 나가는 것은 관리자가 확실히 구분해야 한다. 422로 돌아올 때 입력한 시각이 남아야 한다.

**이력 목록:** 예약 작업은 요청 시각 대신(또는 옆에) 발송 예정 시각을 보여준다. `scheduled`와 `cancelled` 배지를 상태 라벨 표에 더한다 — 표는 `history.php`와 `history_detail.php` 두 곳에 있으니 **둘 다** 고친다.

**취소:** 이력 상세에 취소 버튼. `AligoService::cancel()` 결과를 구조화된 쿼리 파라미터로 넘겨 문장은 서버에서 조립한다(자유 문자열 `notice`를 쓰지 않는 기존 규칙 그대로). 부분 취소는 숨기지 않는다.

**설정 화면:** 채널을 끌 때 취소가 함께 일어나므로, 그 결과를 저장 후 알림에 포함한다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit`
Expected: PASS — 전체 초록

- [ ] **Step 5: Commit**

```bash
git add src/Web/ templates/default/admin/ tests/Web/
git commit -m "feat: schedule and cancel sends from the admin screens"
```

---

### Task 8: 문서

**Files:**
- Modify: `docs/messaging.md`
- Modify: `AGENTS.md`, `README.md`, `CHANGELOG.md`

- [ ] **Step 1: 전체 테스트를 먼저 돌려 상태를 확인한다**

Run: `./vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 2: `docs/messaging.md`에 예약 절을 더한다**

담을 것: 예약을 걸면 메시지가 그 순간 알리고로 넘어간다는 것, 하한 10분·상한 30일과 그 이유, 취소가 발송 5분 전까지만 가능하다는 것, 500명이 넘으면 일부만 취소될 수 있고 그 사실을 화면이 그대로 보여준다는 것, 채널을 끄거나 템플릿이 승인을 잃으면 자동 취소를 시도한다는 것, 확장이 `send(['scheduled_at' => …])`로 예약한다는 것.

**시간대를 명시한다** — 화면과 저장은 어느 기준이고 알리고에는 무엇으로 보내는지.

- [ ] **Step 3: 기능 지도와 변경 기록을 맞춘다**

`AGENTS.md` 기능 지도, `README.md` 기능 목록, `CHANGELOG.md`에 각각 한 줄.

- [ ] **Step 4: 전체 테스트를 다시 돌린다**

Run: `./vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add docs/ AGENTS.md README.md CHANGELOG.md
git commit -m "docs: describe scheduled sending and cancellation"
```

---

## 완료 확인

- `./vendor/bin/phpunit` 전체 통과
- 관리자가 발송 탭에서 시각을 넣어 예약하고, 이력에서 그 예약을 취소한다
- 10분 안쪽·30일 밖 시각은 작업을 만들지 않고 거절된다
- 예약 건은 발송 시각 전까지 결과 조회 대상이 아니고, 포기 시한도 발송 시각부터 센다
- 500명이 넘는 작업의 부분 취소가 화면에 그대로 보인다
- 채널을 끄거나 템플릿이 승인을 잃으면 그 예약이 취소되고, 취소하지 못한 건은 알려진다
- 확장이 `$app->aligo()->send(['scheduled_at' => …])`로 예약한다
