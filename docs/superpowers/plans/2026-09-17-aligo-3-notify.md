# 알림 채널 배선 구현 계획 (3/3)

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 코어 알림을 메일·알림톡·문자·알림함으로 나눠 보내고, 어느 이벤트를 어느 채널로 보낼지 관리자가 정하게 한다.

**Architecture:** `src/Notify/`에 `Notifier`를 두고 코어는 이벤트만 알린다. 채널은 `ChannelInterface` 구현 넷(`MailChannel`·`AlimtalkChannel`·`SmsChannel`·`InboxChannel`)이고, 발송은 계획 1의 `AligoService`를, 알림함은 기존 `Service\NotificationService`를, 메일은 기존 `MailerInterface`를 쓴다. 지금 `$this->mailer->send()`를 직접 부르는 다섯 곳이 `Notifier`를 지나도록 바뀐다.

**Tech Stack:** PHP 8.1+, Slim 4, PHPUnit 10.5.

**Spec:** [docs/superpowers/specs/2026-09-17-aligo-messaging-design.md](../specs/2026-09-17-aligo-messaging-design.md) §6

**선행:** 계획 1 [2026-09-17-aligo-1-engine.md](2026-09-17-aligo-1-engine.md) 전체와 계획 2 [2026-09-17-aligo-2-member-phone.md](2026-09-17-aligo-2-member-phone.md) 전체.

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다.
- 모든 PHP 파일은 `declare(strict_types=1);`, 클래스는 `final` (인터페이스 제외).
- 네임스페이스는 `GnuCms\Notify`, 테스트는 `GnuCms\Tests\Notify`.
- **한 채널이 실패해도 나머지는 보낸다. 시도한 채널이 모두 실패하면 예외를 올린다.**
- **번호가 없으면 알림톡·문자 채널은 조용히 건너뛴다. 실패로 세지 않는다.**
- 기존 메일 제목·본문 문구를 바꾸지 않는다. 옮기기만 한다.
- 이벤트 목록은 코어가 정한 고정 목록이다. 확장이 이벤트를 더하는 API는 범위 밖이다.
- 화면 문구는 한국어, 커밋 제목은 영어 conventional commit.
- 관련 테스트를 먼저 돌리고, 마지막에 `./vendor/bin/phpunit` 전체도 돌린다.

---

### Task 1: Events와 Recipient

**Files:**
- Create: `src/Notify/Events.php`
- Create: `src/Notify/Recipient.php`
- Test: `tests/Notify/EventsTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - `Events::ALL` — 이벤트 키 => `['label' => string, 'vars' => list<string>, 'phone' => bool]`
  - `Events::labels(): array<string,string>`
  - `Events::variables(string $key): list<string>`
  - `Events::phoneCapable(string $key): bool`
  - `Events::exists(string $key): bool`
  - `Recipient::forUser(array $user): self` — `id`·`display_name`·`email`·`phone` 칸을 읽는다
  - `Recipient::forEmail(string $email, string $name = ''): self`
  - 공개 읽기 전용 속성 `email`(?string), `phone`(?string), `userId`(?string), `name`(string)

- [ ] **Step 1: Write the failing test**

`tests/Notify/EventsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Notify;

use GnuCms\Notify\Events;
use GnuCms\Notify\Recipient;
use PHPUnit\Framework\TestCase;

final class EventsTest extends TestCase
{
    public function testCarriesTheSevenCoreEvents(): void
    {
        self::assertSame([
            'password_reset', 'password_changed', 'welcome', 'comment_new',
            'email_verify', 'signup_attempt', 'social_email_verify',
        ], array_keys(Events::ALL));
    }

    public function testOnlyFourEventsCanGoToAPhone(): void
    {
        foreach (['password_reset', 'password_changed', 'welcome', 'comment_new'] as $key) {
            self::assertTrue(Events::phoneCapable($key), $key);
        }
        foreach (['email_verify', 'signup_attempt', 'social_email_verify'] as $key) {
            self::assertFalse(Events::phoneCapable($key), $key);
        }
    }

    public function testEveryEventDeclaresItsVariables(): void
    {
        foreach (Events::ALL as $key => $event) {
            self::assertNotSame('', $event['label'], $key);
            self::assertContains('사이트명', $event['vars'], $key);
        }
        self::assertContains('링크', Events::variables('password_reset'));
    }

    public function testRecipientReadsWhatTheUserRowHas(): void
    {
        $to = Recipient::forUser(['id' => '7', 'display_name' => '홍길동',
            'email' => 'a@example.com', 'phone' => '01012345678']);

        self::assertSame('7', $to->userId);
        self::assertSame('홍길동', $to->name);
        self::assertSame('a@example.com', $to->email);
        self::assertSame('01012345678', $to->phone);
    }

    public function testRecipientWithoutAPhoneIsNull(): void
    {
        $to = Recipient::forUser(['id' => '7', 'display_name' => '홍길동',
            'email' => 'a@example.com', 'phone' => '']);
        self::assertNull($to->phone);

        $guest = Recipient::forEmail('b@example.com');
        self::assertNull($guest->phone);
        self::assertNull($guest->userId);
        self::assertSame('b@example.com', $guest->email);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Notify/EventsTest.php`
Expected: FAIL — `Class "GnuCms\Notify\Events" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Notify/Events.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

/**
 * 코어가 보내는 알림의 고정 목록. phone 이 false 인 것은 수신자가 이메일로만 식별되거나
 * 이메일 확인 자체가 목적이라 알림톡·문자로 보낼 수 없다.
 */
final class Events
{
    public const ALL = [
        'password_reset' => ['label' => '비밀번호 재설정',
            'vars' => ['사이트명', '이름', '링크', '유효시간'], 'phone' => true],
        'password_changed' => ['label' => '비밀번호 변경 안내',
            'vars' => ['사이트명', '이름', '일시'], 'phone' => true],
        'welcome' => ['label' => '가입 완료 안내',
            'vars' => ['사이트명', '이름'], 'phone' => true],
        'comment_new' => ['label' => '새 댓글·답글',
            'vars' => ['사이트명', '이름', '글제목', '작성자', '링크'], 'phone' => true],
        'email_verify' => ['label' => '이메일 인증',
            'vars' => ['사이트명', '이름', '링크'], 'phone' => false],
        'signup_attempt' => ['label' => '가입 시도 안내',
            'vars' => ['사이트명', '링크'], 'phone' => false],
        'social_email_verify' => ['label' => '소셜 로그인 이메일 확인',
            'vars' => ['사이트명', '링크'], 'phone' => false],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    public static function labels(): array
    {
        return array_map(static fn (array $event): string => $event['label'], self::ALL);
    }

    public static function variables(string $key): array
    {
        return self::ALL[$key]['vars'] ?? [];
    }

    public static function phoneCapable(string $key): bool
    {
        return (bool) (self::ALL[$key]['phone'] ?? false);
    }
}
```

`src/Notify/Recipient.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

final class Recipient
{
    public ?string $email;
    public ?string $phone;
    public ?string $userId;
    public string $name;

    private function __construct(?string $email, ?string $phone, ?string $userId, string $name)
    {
        $this->email = $email;
        $this->phone = $phone;
        $this->userId = $userId;
        $this->name = $name;
    }

    public static function forUser(array $user): self
    {
        return new self(
            self::orNull((string) ($user['email'] ?? '')),
            self::orNull((string) ($user['phone'] ?? '')),
            self::orNull((string) ($user['id'] ?? '')),
            (string) ($user['display_name'] ?? '')
        );
    }

    public static function forEmail(string $email, string $name = ''): self
    {
        return new self(self::orNull($email), null, null, $name);
    }

    private static function orNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Notify/EventsTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Notify/Events.php src/Notify/Recipient.php tests/Notify/EventsTest.php
git commit -m "feat: define the core notification events and their recipients"
```

---

### Task 2: NotifySettings — 이벤트 × 채널과 매핑

**Files:**
- Create: `src/Notify/SettingsRepository.php`
- Create: `src/Notify/NotifySettings.php`
- Test: `tests/Notify/NotifySettingsTest.php`

**Interfaces:**
- Consumes: `Events` (Task 1), `Variables::names()` (계획 1 Task 4), `Templates::find()` (계획 1 Task 10)
- Produces:
  - `SettingsRepository::__construct(Connection $db)`, `all()`, `save(array)` — `site_settings`의 `notify.` 접두사
  - `NotifySettings::__construct(SettingsRepository $repository, Templates $templates)`
  - `NotifySettings::channelsFor(string $event): list<string>` — 켜진 채널 키
  - `NotifySettings::isOn(string $event, string $channel): bool`
  - `NotifySettings::templateFor(string $event): ?array` — `['tpl_code' => string, 'var_map' => array]`
  - `NotifySettings::smsBody(string $event): string`
  - `NotifySettings::save(string $event, array $input): void` — 매핑이 빠지면 `DomainError::validation`
  - `NotifySettings::formValues(): array` — 화면용 이벤트별 묶음

- [ ] **Step 1: Write the failing test**

`tests/Notify/NotifySettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Notify;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\Settings as AligoSettings;
use GnuCms\Aligo\SettingsRepository as AligoSettingsRepository;
use GnuCms\Aligo\Templates;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Notify\NotifySettings;
use GnuCms\Notify\SettingsRepository;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotifySettingsTest extends DatabaseTestCase
{
    private function boot(array $config): NotifySettings
    {
        $db = $this->freshDatabase($config);
        $aligo = new AligoSettings(new AligoSettingsRepository($db), new SecretCipher('s'));
        $templates = new Templates($db, new AlimtalkApi(new FakeAligoTransport(), $aligo), $aligo);
        $db->insert('alimtalk_templates', ['tpl_code' => 'T1', 'senderkey' => 'SK1', 'name' => '재설정',
            'content' => '#{고객명}님 #{주소} 에서 재설정하세요', 'status' => 'A', 'insp_status' => 'APR',
            'enabled' => 1, 'fetched_at' => '2026-09-17 10:00:00']);

        return new NotifySettings(new SettingsRepository($db), $templates);
    }

    #[DataProvider('connectionProvider')]
    public function testMailIsTheOnlyChannelOnByDefault(array $config): void
    {
        $settings = $this->boot($config);
        self::assertSame(['mail'], $settings->channelsFor('password_reset'));
        self::assertSame(['inbox'], $settings->channelsFor('comment_new'));
    }

    #[DataProvider('connectionProvider')]
    public function testTextBodyIsStoredAndRead(array $config): void
    {
        $settings = $this->boot($config);
        $settings->save('password_reset', ['mail' => '1', 'sms' => '1',
            'sms_body' => '#{이름}님 #{링크} 에서 재설정하세요']);

        self::assertSame(['mail', 'sms'], $settings->channelsFor('password_reset'));
        self::assertSame('#{이름}님 #{링크} 에서 재설정하세요', $settings->smsBody('password_reset'));
    }

    #[DataProvider('connectionProvider')]
    public function testTextBodyMayOnlyUseVariablesTheEventProvides(array $config): void
    {
        $settings = $this->boot($config);

        try {
            $settings->save('password_reset', ['sms' => '1', 'sms_body' => '#{주문번호} 안내']);
            self::fail('없는 변수는 거절해야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('주문번호', $e->details()['sms_body']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkCannotBeTurnedOnUntilEveryVariableIsMapped(array $config): void
    {
        $settings = $this->boot($config);

        try {
            $settings->save('password_reset', ['alimtalk' => '1', 'tpl_code' => 'T1',
                'var_map' => ['고객명' => '이름']]);
            self::fail('매핑이 빠지면 거절해야 한다');
        } catch (DomainError $e) {
            self::assertStringContainsString('주소', $e->details()['var_map']);
        }

        $settings->save('password_reset', ['alimtalk' => '1', 'tpl_code' => 'T1',
            'var_map' => ['고객명' => '이름', '주소' => '링크']]);

        self::assertContains('alimtalk', $settings->channelsFor('password_reset'));
        self::assertSame(['고객명' => '이름', '주소' => '링크'],
            $settings->templateFor('password_reset')['var_map']);
    }

    #[DataProvider('connectionProvider')]
    public function testEventsThatCannotUseAPhoneRefuseThoseChannels(array $config): void
    {
        $settings = $this->boot($config);

        $this->expectException(DomainError::class);
        $settings->save('email_verify', ['sms' => '1', 'sms_body' => '#{링크}']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Notify/NotifySettingsTest.php`
Expected: FAIL — `Class "GnuCms\Notify\SettingsRepository" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Notify/SettingsRepository.php`는 `src/Mail/MailSettingsRepository.php`를 그대로 옮기고 `PREFIX`만 `'notify.'`로 바꾼다.

`src/Notify/NotifySettings.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

use GnuCms\Aligo\Templates;
use GnuCms\Aligo\Variables;
use GnuCms\Error\DomainError;

final class NotifySettings
{
    public const CHANNELS = ['mail', 'alimtalk', 'sms', 'inbox'];
    /** 설정하기 전의 동작. 지금 코어가 하는 일을 그대로 둔다. */
    private const DEFAULTS = [
        'password_reset' => ['mail'], 'password_changed' => ['mail'], 'welcome' => [],
        'comment_new' => ['inbox'], 'email_verify' => ['mail'],
        'signup_attempt' => ['mail'], 'social_email_verify' => ['mail'],
    ];

    private SettingsRepository $repository;
    private Templates $templates;

    public function __construct(SettingsRepository $repository, Templates $templates)
    {
        $this->repository = $repository;
        $this->templates = $templates;
    }

    public function channelsFor(string $event): array
    {
        $stored = $this->repository->all();
        if (!isset($stored[$event . '.configured'])) {
            return self::DEFAULTS[$event] ?? [];
        }

        return array_values(array_filter(self::CHANNELS,
            fn (string $channel): bool => ($stored[$event . '.' . $channel] ?? '0') === '1'));
    }

    public function isOn(string $event, string $channel): bool
    {
        return in_array($channel, $this->channelsFor($event), true);
    }

    public function templateFor(string $event): ?array
    {
        $stored = $this->repository->all();
        $code = (string) ($stored[$event . '.tpl_code'] ?? '');
        if ($code === '') {
            return null;
        }
        $map = json_decode((string) ($stored[$event . '.var_map'] ?? '[]'), true);

        return ['tpl_code' => $code, 'var_map' => is_array($map) ? $map : []];
    }

    public function smsBody(string $event): string
    {
        return (string) ($this->repository->all()[$event . '.sms_body'] ?? '');
    }

    public function save(string $event, array $input): void
    {
        if (!Events::exists($event)) {
            throw DomainError::validation(['event' => '알 수 없는 알림입니다.']);
        }
        $allowed = Events::variables($event);
        $saved = [$event . '.configured' => '1'];

        foreach (self::CHANNELS as $channel) {
            $on = ($input[$channel] ?? '') === '1';
            if ($on && in_array($channel, ['alimtalk', 'sms'], true) && !Events::phoneCapable($event)) {
                throw DomainError::validation([$channel =>
                    '이 알림은 이메일로만 보낼 수 있습니다. 받는 사람이 이메일로만 확인됩니다.']);
            }
            $saved[$event . '.' . $channel] = $on ? '1' : '0';
        }

        if ($saved[$event . '.sms'] === '1') {
            $body = trim((string) ($input['sms_body'] ?? ''));
            if ($body === '') {
                throw DomainError::validation(['sms_body' => '문자로 보낼 본문을 입력해 주세요.']);
            }
            $unknown = array_diff(Variables::names($body), $allowed);
            if ($unknown !== []) {
                throw DomainError::validation(['sms_body' =>
                    '이 알림이 제공하지 않는 변수가 있습니다: ' . implode(', ', $unknown)
                    . '. 쓸 수 있는 변수는 ' . implode(', ', $allowed) . ' 입니다.']);
            }
            $saved[$event . '.sms_body'] = $body;
        }

        if ($saved[$event . '.alimtalk'] === '1') {
            $saved += $this->alimtalkSettings($event, $input, $allowed);
        }

        $this->repository->save($saved);
    }

    /** 승인 템플릿의 변수명은 사이트마다 다르다. 모든 변수가 코어 변수와 이어져야 켤 수 있다. */
    private function alimtalkSettings(string $event, array $input, array $allowed): array
    {
        $code = trim((string) ($input['tpl_code'] ?? ''));
        $template = $code === '' ? null : $this->templates->find($code);
        if ($template === null || (int) $template['enabled'] !== 1) {
            throw DomainError::validation(['tpl_code' => '사용 중인 승인 템플릿을 골라 주세요.']);
        }

        $map = [];
        $unmapped = [];
        foreach (Variables::names((string) $template['content']) as $name) {
            $core = trim((string) (($input['var_map'] ?? [])[$name] ?? ''));
            if ($core === '' || !in_array($core, $allowed, true)) {
                $unmapped[] = $name;
                continue;
            }
            $map[$name] = $core;
        }
        if ($unmapped !== []) {
            throw DomainError::validation(['var_map' =>
                '템플릿 변수에 넣을 값을 모두 골라 주세요. 남은 변수: ' . implode(', ', $unmapped)]);
        }

        return [
            $event . '.tpl_code' => $code,
            $event . '.var_map' => (string) json_encode($map, JSON_UNESCAPED_UNICODE),
        ];
    }

    public function formValues(): array
    {
        $values = [];
        foreach (Events::ALL as $key => $event) {
            $values[$key] = [
                'label' => $event['label'],
                'vars' => $event['vars'],
                'phone' => $event['phone'],
                'channels' => $this->channelsFor($key),
                'template' => $this->templateFor($key),
                'sms_body' => $this->smsBody($key),
            ];
        }

        return $values;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Notify/NotifySettingsTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Notify/SettingsRepository.php src/Notify/NotifySettings.php tests/Notify/NotifySettingsTest.php
git commit -m "feat: map events to channels and refuse half-mapped alimtalk templates"
```

---

### Task 3: 채널 넷

**Files:**
- Create: `src/Notify/ChannelInterface.php`
- Create: `src/Notify/MailBodies.php`
- Create: `src/Notify/MailChannel.php`
- Create: `src/Notify/AlimtalkChannel.php`
- Create: `src/Notify/SmsChannel.php`
- Create: `src/Notify/InboxChannel.php`
- Test: `tests/Notify/ChannelsTest.php`

**Interfaces:**
- Consumes: `Recipient`·`Events` (Task 1), `NotifySettings` (Task 2), `AligoService::send()` (계획 1 Task 13), `MailerInterface`, `Service\NotificationService`
- Produces:
  - `interface ChannelInterface { public function key(): string; public function available(string $event, Recipient $to): bool; public function send(string $event, Recipient $to, array $vars): void; }`
  - `MailBodies::render(string $event, array $vars): array{subject:string,body:string}` — 지금 `AccountService`에 있는 제목·본문을 그대로 옮긴 것
  - 네 채널 구현

**Note:** `MailBodies`로 옮길 때 **기존 문구를 한 글자도 바꾸지 않는다.** `src/Account/AccountService.php`와 `src/Account/SocialAuthService.php`의 `send()` 호출을 열어 제목·본문을 그대로 복사한다.

- [ ] **Step 1: Write the failing test**

`tests/Notify/ChannelsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Notify;

use GnuCms\Notify\AlimtalkChannel;
use GnuCms\Notify\Recipient;
use GnuCms\Notify\SmsChannel;
use PHPUnit\Framework\TestCase;

final class ChannelsTest extends TestCase
{
    public function testPhoneChannelsAreUnavailableWithoutANumber(): void
    {
        $withPhone = Recipient::forUser(['id' => '1', 'display_name' => '홍', 'phone' => '01012345678']);
        $without = Recipient::forUser(['id' => '2', 'display_name' => '김', 'phone' => '']);

        $sms = $this->smsChannel();
        self::assertTrue($sms->available('password_reset', $withPhone));
        self::assertFalse($sms->available('password_reset', $without));

        $alimtalk = $this->alimtalkChannel();
        self::assertFalse($alimtalk->available('password_reset', $without));
    }

    public function testTextChannelSubstitutesTheConfiguredBody(): void
    {
        [$channel, $sent] = $this->smsChannelRecording('#{이름}님 #{링크}');
        $channel->send('password_reset', Recipient::forUser(
            ['id' => '1', 'display_name' => '홍길동', 'phone' => '01012345678']),
            ['이름' => '홍길동', '링크' => 'https://example.com/r']);

        self::assertSame('sms', $sent[0]['channel']);
        self::assertSame('홍길동님 https://example.com/r', $sent[0]['body']);
        self::assertSame('01012345678', $sent[0]['recipients'][0]['phone']);
        self::assertSame('password_reset', $sent[0]['event_key']);
    }

    public function testAlimtalkChannelRenamesVariablesThroughTheMap(): void
    {
        // 템플릿은 #{고객명}·#{주소}, 코어는 이름·링크. 매핑이 이어 준다.
        [$channel, $sent] = $this->alimtalkChannelRecording('T1', ['고객명' => '이름', '주소' => '링크']);
        $channel->send('password_reset', Recipient::forUser(
            ['id' => '1', 'display_name' => '홍길동', 'phone' => '01012345678']),
            ['이름' => '홍길동', '링크' => 'https://example.com/r']);

        self::assertSame('at', $sent[0]['channel']);
        self::assertSame('T1', $sent[0]['tpl_code']);
        self::assertSame(['고객명' => '홍길동', '주소' => 'https://example.com/r'],
            $sent[0]['recipients'][0]['vars']);
    }
}
```

`smsChannel()`·`alimtalkChannel()`·`smsChannelRecording()`·`alimtalkChannelRecording()`는 이 파일의 private 헬퍼다. `AligoService` 대신 `send(array $request): int`만 가진 익명 클래스를 넘겨 호출 내용을 `$sent`에 기록한다. `NotifySettings`도 필요한 값만 돌려주는 익명 클래스로 대신한다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Notify/ChannelsTest.php`
Expected: FAIL — `Class "GnuCms\Notify\SmsChannel" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Notify/ChannelInterface.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

interface ChannelInterface
{
    public function key(): string;

    /** 보낼 수 없으면 false. 실패가 아니라 건너뛰기다. */
    public function available(string $event, Recipient $to): bool;

    public function send(string $event, Recipient $to, array $vars): void;
}
```

`src/Notify/SmsChannel.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

use GnuCms\Aligo\AligoService;

final class SmsChannel implements ChannelInterface
{
    private AligoService $aligo;
    private NotifySettings $settings;

    public function __construct(AligoService $aligo, NotifySettings $settings)
    {
        $this->aligo = $aligo;
        $this->settings = $settings;
    }

    public function key(): string
    {
        return 'sms';
    }

    public function available(string $event, Recipient $to): bool
    {
        return $to->phone !== null
            && Events::phoneCapable($event)
            && $this->settings->smsBody($event) !== ''
            && $this->aligo->settings->isEnabled('sms');
    }

    public function send(string $event, Recipient $to, array $vars): void
    {
        $this->aligo->send([
            'channel' => 'sms',
            'body' => $this->settings->smsBody($event),
            'event_key' => $event,
            'recipients' => [['phone' => $to->phone, 'name' => $to->name,
                'user_id' => $to->userId, 'vars' => $vars]],
        ]);
    }
}
```

`src/Notify/AlimtalkChannel.php`는 같은 모양이되 `available()`이 `templateFor($event) !== null`과 `isEnabled('at')`을 더 보고, `send()`가 매핑으로 변수 이름을 바꾼다:

```php
    public function send(string $event, Recipient $to, array $vars): void
    {
        $template = $this->settings->templateFor($event);
        $mapped = [];
        // 템플릿 변수명 => 코어 변수명. 값은 코어 쪽 이름으로 들어온다.
        foreach ($template['var_map'] as $templateName => $coreName) {
            $mapped[$templateName] = (string) ($vars[$coreName] ?? '');
        }

        $this->aligo->send([
            'channel' => 'at',
            'tpl_code' => $template['tpl_code'],
            'event_key' => $event,
            'recipients' => [['phone' => $to->phone, 'name' => $to->name,
                'user_id' => $to->userId, 'vars' => $mapped]],
        ]);
    }
```

`src/Notify/MailChannel.php`는 `available()`이 `$to->email !== null`이면 참이고, `send()`가 `MailBodies::render()` 결과를 기존 `MailerInterface::send($to, $subject, $body)`로 넘긴다.

`src/Notify/InboxChannel.php`는 `available()`이 `$to->userId !== null && $event === 'comment_new'`이고, `send()`가 기존 `NotificationService`에 알림함 기록을 맡긴다. `comment_new` 외에는 알림함에 넣지 않는다.

`src/Notify/MailBodies.php`는 이벤트별 `subject`·`body`를 돌려준다. 문구는 `AccountService`·`SocialAuthService`에서 그대로 옮긴다. `welcome`은 코어에 없던 알림이므로 새로 쓴다:

```php
            'welcome' => [
                'subject' => '[' . $vars['사이트명'] . '] 가입을 환영합니다',
                'body' => $vars['이름'] . "님, " . $vars['사이트명'] . " 가입이 끝났습니다.\n\n"
                    . "이제 로그인해서 이용하실 수 있습니다.",
            ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Notify/ChannelsTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Notify/ChannelInterface.php src/Notify/MailBodies.php src/Notify/MailChannel.php \
  src/Notify/AlimtalkChannel.php src/Notify/SmsChannel.php src/Notify/InboxChannel.php \
  tests/Notify/ChannelsTest.php
git commit -m "feat: add mail, alimtalk, text and inbox notification channels"
```

---

### Task 4: Notifier — 팬아웃과 실패 처리

**Files:**
- Create: `src/Notify/Notifier.php`
- Modify: `src/App.php` (`notifier()` 게터)
- Test: `tests/Notify/NotifierTest.php`

**Interfaces:**
- Consumes: Task 1~3 전부
- Produces:
  - `Notifier::__construct(NotifySettings $settings, array $channels)` — `$channels`는 `ChannelInterface` 목록
  - `Notifier::notify(string $event, Recipient $to, array $vars): void`
  - `App::notifier(): Notifier`

- [ ] **Step 1: Write the failing test**

`tests/Notify/NotifierTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Notify;

use GnuCms\Aligo\AlimtalkApi;
use GnuCms\Aligo\Settings as AligoSettings;
use GnuCms\Aligo\SettingsRepository as AligoSettingsRepository;
use GnuCms\Aligo\Templates;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Notify\ChannelInterface;
use GnuCms\Notify\Notifier;
use GnuCms\Notify\NotifySettings;
use GnuCms\Notify\Recipient;
use GnuCms\Notify\SettingsRepository;
use GnuCms\Tests\Support\DatabaseTestCase;
use GnuCms\Tests\Support\FakeAligoTransport;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotifierTest extends DatabaseTestCase
{
    private function channel(string $key, bool $available, bool $throws = false): ChannelInterface
    {
        return new class ($key, $available, $throws) implements ChannelInterface {
            public array $sent = [];
            public function __construct(private string $k, private bool $ok, private bool $boom) {}
            public function key(): string { return $this->k; }
            public function available(string $event, Recipient $to): bool { return $this->ok; }
            public function send(string $event, Recipient $to, array $vars): void
            {
                if ($this->boom) {
                    throw DomainError::serviceUnavailable('테스트 실패');
                }
                $this->sent[] = $event;
            }
        };
    }

    /**
     * Notifier 는 진짜 NotifySettings 를 받는다. 여기서는 SQLite 로 한 벌 만들고
     * 원하는 채널만 켜 둔다 — 익명 클래스로 흉내 내면 타입이 어긋난다.
     */
    private function notifier(array $dbConfig, array $channels, array $on): Notifier
    {
        $db = $this->freshDatabase($dbConfig);
        $aligo = new AligoSettings(new AligoSettingsRepository($db), new SecretCipher('s'));
        $settings = new NotifySettings(
            new SettingsRepository($db),
            new Templates($db, new AlimtalkApi(new FakeAligoTransport(), $aligo), $aligo)
        );
        $input = ['mail' => '0', 'alimtalk' => '0', 'sms' => '0', 'inbox' => '0'];
        foreach ($on as $channel) {
            $input[$channel] = '1';
        }
        // welcome 은 문자 본문이 있어야 sms 를 켤 수 있다.
        $input['sms_body'] = '#{이름}님 가입을 환영합니다';
        $settings->save('welcome', $input);

        return new Notifier($settings, $channels);
    }

    private function to(): Recipient
    {
        return Recipient::forUser(['id' => '1', 'display_name' => '홍', 'email' => 'a@example.com']);
    }

    #[DataProvider('connectionProvider')]
    public function testSendsThroughEveryChannelThatIsOn(array $dbConfig): void
    {
        $mail = $this->channel('mail', true);
        $sms = $this->channel('sms', true);
        $this->notifier($dbConfig, [$mail, $sms], ['mail', 'sms'])->notify('welcome', $this->to(), []);

        self::assertSame(['welcome'], $mail->sent);
        self::assertSame(['welcome'], $sms->sent);
    }

    #[DataProvider('connectionProvider')]
    public function testChannelsThatAreOffAreNotUsed(array $dbConfig): void
    {
        $mail = $this->channel('mail', true);
        $sms = $this->channel('sms', true);
        $this->notifier($dbConfig, [$mail, $sms], ['mail'])->notify('welcome', $this->to(), []);

        self::assertSame([], $sms->sent);
    }

    #[DataProvider('connectionProvider')]
    public function testOneFailingChannelDoesNotStopTheOthers(array $dbConfig): void
    {
        $mail = $this->channel('mail', true);
        $sms = $this->channel('sms', true, true);
        $this->notifier($dbConfig, [$mail, $sms], ['mail', 'sms'])->notify('welcome', $this->to(), []);

        self::assertSame(['welcome'], $mail->sent, '문자가 실패해도 메일은 나가야 한다');
    }

    #[DataProvider('connectionProvider')]
    public function testEveryChannelFailingRaises(array $dbConfig): void
    {
        $mail = $this->channel('mail', true, true);
        $this->expectException(DomainError::class);
        $this->notifier($dbConfig, [$mail], ['mail'])->notify('welcome', $this->to(), []);
    }

    #[DataProvider('connectionProvider')]
    public function testUnavailableChannelsAreSkippedQuietly(array $dbConfig): void
    {
        // 번호가 없어 문자가 불가능한 경우. 실패가 아니므로 예외가 없어야 한다.
        $sms = $this->channel('sms', false);
        $this->notifier($dbConfig, [$sms], ['sms'])->notify('welcome', $this->to(), []);

        self::assertSame([], $sms->sent);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Notify/NotifierTest.php`
Expected: FAIL — `Class "GnuCms\Notify\Notifier" not found`

- [ ] **Step 3: Write minimal implementation**

`src/Notify/Notifier.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Notify;

use GnuCms\Error\DomainError;

/**
 * 코어 알림의 유일한 출구. 어느 채널로 나갈지는 설정이 정한다.
 *
 * 보낼 수 없는 채널(번호 없는 회원의 문자 등)은 건너뛰고 실패로 세지 않는다.
 * 시도한 채널이 하나도 성공하지 못하면 예외를 올린다 — 아무 데도 안 갔는데
 * 화면이 "보냈습니다"라고 말하면 안 되기 때문이다.
 */
final class Notifier
{
    private NotifySettings $settings;
    /** @var list<ChannelInterface> */
    private array $channels;

    public function __construct(NotifySettings $settings, array $channels)
    {
        $this->settings = $settings;
        $this->channels = $channels;
    }

    public function notify(string $event, Recipient $to, array $vars): void
    {
        $wanted = $this->settings->channelsFor($event);
        $attempted = 0;
        $sent = 0;
        $reasons = [];

        foreach ($this->channels as $channel) {
            if (!in_array($channel->key(), $wanted, true) || !$channel->available($event, $to)) {
                continue;
            }
            $attempted++;
            try {
                $channel->send($event, $to, $vars);
                $sent++;
            } catch (\Throwable $e) {
                $reasons[] = $channel->key() . ': ' . $e->getMessage();
            }
        }

        if ($attempted > 0 && $sent === 0) {
            throw DomainError::serviceUnavailable(
                '알림을 보내지 못했습니다. ' . implode(' / ', $reasons));
        }
    }
}
```

`src/App.php`에 `notifier()` 게터를 `aligo()` 옆에 더한다. `NotifySettings`, 채널 넷을 만들어 `Notifier`에 넘긴다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Notify/NotifierTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Notify/Notifier.php src/App.php tests/Notify/NotifierTest.php
git commit -m "feat: fan notifications out to channels and fail only when all do"
```

---

### Task 5: 코어 알림을 Notifier로 옮기기

**Files:**
- Modify: `src/Account/AccountService.php` (메일 호출 네 곳)
- Modify: `src/Account/SocialAuthService.php` (메일 호출 한 곳)
- Modify: `src/App.php` (두 서비스에 `Notifier` 주입)
- Test: `tests/Account/NotificationRoutingTest.php`

**Interfaces:**
- Consumes: `Notifier::notify()` (Task 4)
- Produces: 다섯 알림이 전부 `Notifier`를 지난다. 메일 문구와 수신자는 그대로다

**Note:** 기존 `tests/Account/`에 메일 발송을 확인하는 테스트가 있으면 그 테스트가 계속 통과해야 한다. 통과하지 않으면 문구나 수신자가 바뀐 것이므로 구현을 고친다 — 테스트를 고치지 않는다.

- [ ] **Step 1: Write the failing test**

`tests/Account/NotificationRoutingTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Account;

use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotificationRoutingTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testPasswordResetGoesThroughTheNotifier(array $config): void
    {
        [$service, $notifier] = $this->bootWithSpyNotifier($config);
        $this->createMember($service, 'a@example.com');

        $service->requestPasswordReset('a@example.com');

        self::assertSame('password_reset', $notifier->calls[0]['event']);
        self::assertSame('a@example.com', $notifier->calls[0]['to']->email);
        self::assertArrayHasKey('링크', $notifier->calls[0]['vars']);
        self::assertArrayHasKey('사이트명', $notifier->calls[0]['vars']);
    }

    #[DataProvider('connectionProvider')]
    public function testSignupSendsTheWelcomeNotification(array $config): void
    {
        [$service, $notifier] = $this->bootWithSpyNotifier($config);
        $service->register($this->signup(['email' => 'new@example.com']));

        $events = array_column($notifier->calls, 'event');
        self::assertContains('welcome', $events);
    }

    #[DataProvider('connectionProvider')]
    public function testPasswordChangeNotifiesTheOwner(array $config): void
    {
        [$service, $notifier] = $this->bootWithSpyNotifier($config);
        $userId = $this->createMember($service, 'a@example.com');

        $service->notifyPasswordChanged($userId);

        self::assertSame('password_changed', $notifier->calls[0]['event']);
        self::assertArrayHasKey('일시', $notifier->calls[0]['vars']);
    }
}
```

`bootWithSpyNotifier()`는 `notify()` 호출을 `$calls`에 쌓는 익명 클래스를 `AccountService`에 넣어 돌려준다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Account/NotificationRoutingTest.php`
Expected: FAIL — `AccountService`가 `Notifier`를 받지 않는다

- [ ] **Step 3: Write minimal implementation**

`AccountService`의 생성자에 `Notifier $notifier`를 더하고 `$this->mailer->send(...)` 네 곳을 바꾼다. 예를 들어 비밀번호 재설정은:

```php
        $this->notifier->notify('password_reset', Recipient::forUser($user), [
            '사이트명' => $this->siteName(),
            '이름' => (string) $user['display_name'],
            '링크' => $resetUrl,
            '유효시간' => '30분',
        ]);
```

기존 제목·본문은 Task 3의 `MailBodies`가 들고 있으므로 여기서는 지운다. `$resetUrl`·`$user` 같은 지역 변수 이름은 지금 코드에 있는 것을 그대로 쓴다.

`register()`가 회원을 만든 뒤 `welcome`을 보낸다. 이메일 인증을 쓰는 사이트라면 인증이 끝난 시점에 보낸다 — `verifyEmail()` 안에 두고, 인증을 쓰지 않는 사이트는 `register()` 끝에서 보낸다. 지금 코드가 인증 필요 여부를 어떻게 판단하는지 확인해 그 갈래를 따른다.

`SocialAuthService`도 같은 방식으로 한 곳을 바꾼다.

`src/App.php`에서 두 서비스를 만드는 곳에 `$this->notifier()`를 넘긴다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Account/ tests/Notify/`
Expected: PASS — 기존 계정 테스트도 전부 통과해야 한다

- [ ] **Step 5: Commit**

```bash
git add src/Account/AccountService.php src/Account/SocialAuthService.php src/App.php \
  tests/Account/NotificationRoutingTest.php
git commit -m "feat: route account notifications through the notifier"
```

---

### Task 6: 댓글 알림을 Notifier로 옮기기

**Files:**
- Modify: `src/Service/NotificationService.php` (`notifyComment()`)
- Modify: `src/App.php`
- Test: `tests/Service/CommentNotificationRoutingTest.php`

**Interfaces:**
- Consumes: `Notifier::notify()` (Task 4), `InboxChannel` (Task 3)
- Produces: 댓글 알림이 `comment_new` 이벤트로 나가고, 알림함은 `InboxChannel`을 통해 기록된다

**Note:** 순환 의존을 조심한다. `InboxChannel`이 `NotificationService`를 부르고 `NotificationService`가 `Notifier`를 부르면 서로를 참조한다. 알림함에 실제로 쓰는 코드를 `NotificationService::recordInbox()`로 떼어 `InboxChannel`이 그것만 부르게 하고, `notifyComment()`는 `Notifier`만 부른다.

- [ ] **Step 1: Write the failing test**

`tests/Service/CommentNotificationRoutingTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Service;

use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CommentNotificationRoutingTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testCommentRaisesTheEventWithPostVariables(array $config): void
    {
        [$service, $notifier] = $this->bootWithSpyNotifier($config);
        [$postId, $commentId] = $this->seedPostWithComment($service);

        $service->notifyComment($postId, $commentId);

        self::assertSame('comment_new', $notifier->calls[0]['event']);
        self::assertArrayHasKey('글제목', $notifier->calls[0]['vars']);
        self::assertArrayHasKey('작성자', $notifier->calls[0]['vars']);
        self::assertArrayHasKey('링크', $notifier->calls[0]['vars']);
    }

    #[DataProvider('connectionProvider')]
    public function testTheInboxStillGetsTheNotification(array $config): void
    {
        // 알림함은 InboxChannel 이 recordInbox() 로 넣는다. 기존 동작이 유지되어야 한다.
        [$service, , $db] = $this->bootWithRealNotifier($config);
        [$postId, $commentId] = $this->seedPostWithComment($service);

        $service->notifyComment($postId, $commentId);

        self::assertSame(1, (int) $db->selectOne('SELECT COUNT(*) AS c FROM '
            . $db->table('notifications'))['c']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Service/CommentNotificationRoutingTest.php`
Expected: FAIL — `NotificationService`가 `Notifier`를 받지 않는다

- [ ] **Step 3: Write minimal implementation**

`NotificationService`에서 알림함에 넣는 부분을 `recordInbox(string $userId, string $kind, int $postId, ?int $commentId, string $actorName, string $subject): void`로 떼어낸다 — 지금 `notifyComment()` 안에서 `notifications` 표에 `insert`하는 코드를 그대로 옮긴다.

`notifyComment()`는 대상 회원을 찾은 뒤 이렇게 바뀐다:

```php
        $this->notifier->notify('comment_new', Recipient::forUser($owner), [
            '사이트명' => $siteName,
            '이름' => (string) $owner['display_name'],
            '글제목' => (string) $post['title'],
            '작성자' => $actor,
            '링크' => $postUrl,
        ]);
```

`$owner`·`$post`·`$actor`는 지금 코드에 있는 변수 이름을 그대로 쓴다. `$postUrl`은 지금 알림함 링크를 만드는 방식을 그대로 쓴다.

`InboxChannel::send()`가 `recordInbox()`를 부른다. 알림함이 필요로 하는 `post_id`·`comment_id`는 이벤트 변수에 없으므로, `InboxChannel`이 쓸 수 있도록 `notify()`의 `$vars`에 `_post_id`·`_comment_id`를 담아 넘기고 **밑줄로 시작하는 키는 본문 치환에서 제외**한다. `Variables::names()`는 본문에 쓰인 이름만 보므로 치환에는 영향이 없고, `NotifySettings::save()`의 검증 대상은 `Events::variables()`뿐이라 그대로다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Service/ tests/Comment/`
Expected: PASS — 기존 댓글·알림함 테스트도 전부 통과

- [ ] **Step 5: Commit**

```bash
git add src/Service/NotificationService.php src/Notify/InboxChannel.php src/App.php \
  tests/Service/CommentNotificationRoutingTest.php
git commit -m "feat: raise a comment event and keep the inbox as one of its channels"
```

---

### Task 7: 알림 설정 화면

**Files:**
- Modify: `src/Web/Controller/AdminAligoController.php` (`notifications()`, `saveNotifications()`)
- Create: `templates/default/admin/notify_settings.php`
- Modify: `src/Web/Routes.php`
- Modify: `templates/default/admin/_settings_tabs.php`
- Test: `tests/Web/NotifySettingsScreenTest.php`

**Interfaces:**
- Consumes: `NotifySettings::formValues()`·`save()` (Task 2), `Templates::usable()` (계획 1 Task 10)
- Produces: 라우트 `admin.settings.notifications`(GET), `admin.settings.notifications.save`(POST, 이벤트 하나씩 저장)

- [ ] **Step 1: Write the failing test**

`tests/Web/NotifySettingsScreenTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotifySettingsScreenTest extends WebTestCase
{
    // adminApp() 은 계획 1 Global Constraints 의 Web 테스트 규약에 있는 헬퍼를 그대로 둔다.

    #[DataProvider('connectionProvider')]
    public function testShowsEveryEventAndLocksPhoneColumnsWhereNotPossible(array $dbConfig): void
    {
        $html = $this->body($this->get($this->adminApp($dbConfig), '/admin/settings/notifications'));

        self::assertStringContainsString('비밀번호 재설정', $html);
        self::assertStringContainsString('이메일 인증', $html);
        self::assertStringContainsString('이메일로만', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testSavingATextBodyWithAnUnknownVariableFails(array $dbConfig): void
    {
        $app = $this->adminApp($dbConfig);
        $response = $this->post($app, '/admin/settings/notifications/save', [
            'csrf_token' => $_SESSION['csrf_token'],
            'event' => 'password_reset', 'mail' => '1', 'sms' => '1', 'sms_body' => '#{주문번호}',
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('주문번호', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testCommentVolumeIsCalledOut(array $dbConfig): void
    {
        $html = $this->body($this->get($this->adminApp($dbConfig), '/admin/settings/notifications'));
        self::assertStringContainsString('발송량', $html);
    }

    #[DataProvider('connectionProvider')]
    public function testGuestCannotOpenTheNotificationSettings(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $this->assertLoginRedirect(
            $this->get($app, '/admin/settings/notifications'), '/admin/settings/notifications');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Web/NotifySettingsScreenTest.php`
Expected: FAIL — 404

- [ ] **Step 3: Write minimal implementation**

컨트롤러에 두 메서드를 Task 14(계획 1)와 같은 방식으로 더한다. `saveNotifications()`는 `$input['event']` 하나만 저장하고 `DomainError` 422면 입력을 유지한 채 다시 렌더한다.

라우트:

```php
        $slim->get('/admin/settings/notifications', [$aligo, 'notifications'])
            ->setName('admin.settings.notifications');
        $slim->post('/admin/settings/notifications/save', [$aligo, 'saveNotifications'])
            ->setName('admin.settings.notifications.save');
```

`_settings_tabs.php`에 `알림` 탭을 `알림톡·문자` 다음에 더한다.

`templates/default/admin/notify_settings.php`에 담을 것:

- 이벤트마다 한 묶음(`<details>` 또는 카드). 제목은 라벨, 그 안에 채널 체크 넷
- `phone`이 false인 이벤트는 알림톡·문자 체크를 `disabled`로 두고 `이메일로만 보낼 수 있습니다`를 적는다
- 문자를 켜면 본문 textarea와 `쓸 수 있는 변수: 사이트명, 이름, 링크 …` 안내
- 알림톡을 켜면 `Templates::usable()` 선택과, 고른 템플릿의 변수마다 코어 변수를 고르는 `<select name="var_map[템플릿변수]">`
- `comment_new` 묶음에 `활동이 많은 사이트는 발송량이 빠르게 늘 수 있습니다.`
- 묶음마다 저장 버튼과 `csrf_token`, `<input type="hidden" name="event" value="...">`

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Web/NotifySettingsScreenTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Web/Controller/AdminAligoController.php templates/default/admin/notify_settings.php \
  templates/default/admin/_settings_tabs.php src/Web/Routes.php tests/Web/NotifySettingsScreenTest.php
git commit -m "feat: add the event-by-channel notification settings screen"
```

---

### Task 8: 문서와 마무리

**Files:**
- Modify: `docs/messaging.md` (계획 1 Task 18에서 만든 것)
- Modify: `AGENTS.md`, `README.md`, `CHANGELOG.md`
- Test: 없음 (문서)

- [ ] **Step 1: 전체 테스트를 먼저 돌려 상태를 확인한다**

Run: `./vendor/bin/phpunit`
Expected: PASS — 실패가 있으면 문서를 쓰기 전에 고친다

- [ ] **Step 2: `docs/messaging.md`에 알림 배선 절을 더한다**

담을 것: 이벤트 일곱 개 표와 각 이벤트가 주는 변수, 알림톡 변수 매핑이 왜 필요한지, 번호 없는 회원은 조용히 건너뛴다는 것, 모든 채널이 실패해야 오류가 난다는 것, `comment_new`의 발송량 주의.

- [ ] **Step 3: 기능 지도와 변경 기록을 맞춘다**

`AGENTS.md`의 기능 지도에 알림 채널 배선을 한 줄 더한다. `README.md` 기능 목록에도 더한다. `CHANGELOG.md`에 이번 기능을 적는다 — 기존 항목의 형식을 그대로 따른다.

- [ ] **Step 4: 전체 테스트를 다시 돌린다**

Run: `./vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add docs/messaging.md AGENTS.md README.md CHANGELOG.md
git commit -m "docs: describe the notification channels and their settings"
```

---

## 완료 확인

- `./vendor/bin/phpunit` 전체 통과
- 설정 → 알림에서 이벤트별로 메일·알림톡·문자·알림함을 켜고 끈다
- 문자를 켜면 본문을 직접 쓰고, 알림톡을 켜면 템플릿과 변수 매핑을 고른다
- 비밀번호 재설정·변경, 가입 완료, 새 댓글이 켠 채널로 나간다
- 번호가 없는 회원에게는 알림톡·문자가 조용히 건너뛰어지고 메일은 그대로 간다
- 기존 메일 제목·본문과 수신자가 바뀌지 않았다
