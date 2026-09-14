# 이니톡 결제 2단계 — 결제 요청·알림톡·결제 페이지·운영 화면 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 관리자가 결제 요청을 만들면 비즈뿌리오 알림톡으로 `/pay/{token}` 링크를 보내고, 고객이 이니시스 카드결제로 결제하며, 관리자가 통합조회·대시보드·매출/정산에서 현황을 보고 취소·환불하는 "이니톡 결제" 기능을 코어에 추가한다.

**Architecture:** `src/Initalk/`가 도메인(요청·상태 전이·발송·결제·원장·매출·CSV)을 소유하고, 1단계가 코어에 넣은 `App::messaging()`(알림톡 발송)과 `App::inicisGateway()`(이니시스 `Gateway` 계약)를 호출한다. 관리자 화면은 `InitalkAdminController` + `templates/default/admin/initalk/*`, 공개 결제 페이지는 `PayController` + `templates/default/pay/*`, 이니시스 인증 결과 콜백은 기존 `ExternalRequests` 미들웨어에 `/pay/callback`을 추가한다. QR은 외부 의존성 없는 `Support\QrCode`가 만든다.

**Tech Stack:** PHP 8.2, Slim 4, PHP 템플릿(`PhpView`), SQLite/MySQL(`Connection`·`Schema`), PHPUnit 10. 새 Composer 의존성 없음.

**Spec:** `docs/superpowers/specs/2026-09-14-initalk-design.md` (§2 `initalk_*`·`site_settings`, §3 상태 흐름, §4 `src/Initalk`·`Support\QrCode`, §5 관리자 화면, §6 공개 결제 페이지, §7 알림톡 템플릿, §8 CSV, §9 QR, §10 매출·정산, §11 보안, §12 오류 처리, §14 문서, §15 검증, §16 5~8단계). 1단계 결과는 `docs/superpowers/plans/2026-09-14-initalk-1-core-migration.md`로 구현되어 브랜치 `feat/initalk`(HEAD `7399f11`, 776 tests OK)에 있다.

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다. `vendor/`는 완성본을 묶어 배포한다. QR 인코더·CSV 파서는 직접 구현한다.
- 작업 브랜치는 `feat/initalk`이며 `/home/kagla/gnucms`(Apache가 서비스하는 라이브 체크아웃)에 체크아웃돼 있다. 워크트리를 만들지 않고 `git checkout`으로 브랜치를 바꾸지 않는다.
- DB 변경은 SQLite와 MySQL/MariaDB에서 같은 의미여야 한다. 새 설치용 DDL(`Schema::statements()`)과 기존 설치용 멱등 마이그레이션(`migrateAll()`)을 함께 고친다. 이 계획은 `Schema::VERSION`을 `'24'`로 한 번 올린다(스펙 §2는 23이라 적었지만 1단계가 23을 썼으므로 구조 변경 규칙에 따라 24).
- 식별자: 네임스페이스 `GnuCms\Initalk`, 테이블 `initalk_requests`·`initalk_events`·`initalk_batches`·`initalk_ledger`, 주문번호 `IT-YYYYMMDD-NNNN`(Asia/Seoul 날짜, 일별 순번), 관리자 경로 `/admin/initalk/...`, 라우트 이름 `admin.initalk.*`, 공개 경로 `/pay/{token}`, 메뉴명 **이니톡 결제**, `site_settings` 키 `initalk.*`.
- 상태값은 정확히 `created`·`waiting`·`paid`·`expired`·`cancelled`·`refunded`. 전이는 항상 `UPDATE … WHERE id = ? AND status IN (…)` 조건부 갱신이며 변경 행이 0이면 `DomainError::validation(['status' => '상태가 변경되었습니다. 새로고침 후 확인해 주세요.'])`.
- 휴대폰 번호는 `SecretCipher`(auth.secret)로 암호화 저장, 검색은 `phone_hash = hash_hmac('sha256', 'initalk:phone:' . 숫자, auth.secret)`, 목록은 `phone_mask`(`010-****-1234`). 관리자 화면 외에는 번호를 출력하지 않는다. 번호·본문·토큰을 로그·오류 화면에 남기지 않는다.
- 관리자 화면은 전체 관리자만(`$app->guestAcl()->assertGlobalAdmin()`), 모든 POST는 `GnuCms\Web\Csrf::assert($request)`. 관리자·결제 페이지 응답은 `Cache-Control: no-store`, `Referrer-Policy: no-referrer`. 출력은 `$this->e()`로 이스케이프한다.
- 결제 링크 토큰은 `Base64Url::encode(random_bytes(20))`(27자). 이니시스 `oid`는 요청 `id`(32자 hex). 콜백 `state`는 `CallbackToken::create($app, $order)`이며 `$order`는 `Checkout::order()`가 만든다.
- 알림톡 멱등키는 `'initalk:' . $id . ':' . $n`(n = dispatch_count + 1). 환불 요청 키는 `'initalk-' . $id . '-' . $refundKey`(폼의 `refund_key` 32자 hex).
- 테스트 환경 결제 페이지도 링크가 있으면 누구나 결제할 수 있다(관리자 제한 없음).
- 커밋은 한 가지 논리 변경, `feat:`/`fix:`/`test:`/`docs:` 형식, 트레일러 `Co-Authored-By: Claude <작성 모델> <noreply@anthropic.com>`.
- 각 작업 끝에 관련 테스트를, 코어 공통 코드·DB·라우팅을 건드린 작업 끝에는 전체 테스트(`./vendor/bin/phpunit`, 시작 기준선 776 tests OK)를 돌린다. MySQL 검증은 10번 작업에서 1회용 MariaDB(`mysql -uroot -h127.0.0.1`)로 돌린다.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Initalk/Status.php` | 상태 상수·라벨·허용 전이 |
| `src/Initalk/Phone.php` | 휴대폰 정규화·해시·마스킹·표시 서식 |
| `src/Initalk/Settings.php` | `site_settings`의 `initalk.*` 읽기·검증·저장, 알림톡 템플릿 선택 검증 |
| `src/Initalk/RequestNumber.php` | 일별 주문번호 채번 |
| `src/Initalk/Events.php` | `initalk_events` 기록·조회 |
| `src/Initalk/Requests.php` | 요청 생성·조회·검색·카운트·상태 전이·만료·정리·고객 요약 |
| `src/Initalk/Notifier.php` | 알림톡 변수 구성·발송·재발송·기한 연장 |
| `src/Initalk/Ledger.php` | `initalk_ledger` 확정 원장 |
| `src/Initalk/Checkout.php` | 결제 시작·콜백 승인·상태 조회 반영·환불 |
| `src/Initalk/Sales.php` | 기간별 매출 집계·정산 캘린더·CSV 행 |
| `src/Initalk/CsvImport.php` | CSV 파싱·검증·미리보기·확정 |
| `src/Support/QrCode.php` | QR 인코더(바이트 모드, EC M, 버전 1~10, SVG) |
| `src/Web/Controller/InitalkAdminController.php` | 관리자 화면 전부 |
| `src/Web/Controller/PayController.php` | 공개 결제 페이지·시작·복귀·콜백 처리기 |
| `templates/default/admin/initalk/{_layout_head,dashboard,requests,request_new,request,import,sales,settings}.php` | 관리자 화면 |
| `templates/default/pay/{layout,show,start,done}.php` | 공개 결제 페이지 |
| `www/themes/default/initalk.css`, `www/themes/default/initalk.js`, `www/themes/default/pay.css` | 화면 자산(내용 해시 버전은 `ThemeManager`가 붙임) |
| `bin/initalk.php` | CLI `expire`·`sync`·`purge` |
| `src/Db/Schema.php` | v24: 4개 테이블·인덱스, 멱등 마이그레이션 |
| `src/App.php` | `initalk(): Initalk\Service`(도메인 객체 조립) |
| `docs/initalk.md`, `AGENTS.md`, `README.md` | 문서 |

`GnuCms\Initalk\Service`는 조립만 한다: `public readonly Settings $settings; Requests $requests; Events $events; Ledger $ledger; Notifier $notifier; Checkout $checkout; Sales $sales; CsvImport $import;` — 1번 작업에서 만들고 이후 작업이 속성을 채운다.

---

### Task 1: 스키마 v24, `Status`·`Phone`·`Settings`, `Service` 조립

**Files:**
- Modify: `src/Db/Schema.php` (TABLES, INDEXES, VERSION, `statements()`, `migrateAll()`), `src/App.php`, `tests/Db/SchemaTest.php`(테이블 수 단언)
- Create: `src/Initalk/Status.php`, `src/Initalk/Phone.php`, `src/Initalk/Settings.php`, `src/Initalk/Service.php`
- Test: `tests/Initalk/StatusTest.php`, `tests/Initalk/PhoneTest.php`, `tests/Initalk/SettingsTest.php`

**Interfaces:**
- Consumes: `GnuCms\Cms\CmsRepository::settings(): array` / `saveSettings(array): void` (`App::cms()`), `GnuCms\Messaging\MessagingService::templateAction('get', ['id' => …])` → `['id','environment','enabled','revision','variables' => list<string>,'buttons' => list<array{name,url_mobile,url_pc?}>, …]`, `GnuCms\Messaging\Input::phone()`.
- Produces: `Status` 상수와 `Status::LABELS`, `Status::canSend/canCancel/canPay/canRefund(string): bool`, `Status::OPEN = ['created','waiting']`; `Phone::normalize(mixed): string`(숫자만, 검증 실패 시 validation), `Phone::hash(string $digits, string $secret): string`, `Phone::mask(string $digits): string`, `Phone::format(string $digits): string`; `Settings::read(): array{store_name,support_phone,expiry_hours,environment,template:array{test,live},settlement_days}`, `Settings::save(array $input): void`, `Settings::templateId(string $env): string`, `Settings::VARIABLES`; `App::initalk(): Initalk\Service`; 테이블 4개.

- [ ] **Step 1: 단위 테스트를 쓴다**

`tests/Initalk/StatusTest.php`:

```php
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
```

`tests/Initalk/PhoneTest.php`:

```php
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
```

`tests/Initalk/SettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Settings;
use GnuCms\Messaging\MessagingService;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-settings-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'is' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        $this->app->setMessaging(new MessagingService($this->app, new FakeTransport()));
        $this->app->cms()->saveSettings(['site_name' => '테스트 상점']);
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function template(array $overrides = []): array
    {
        $messaging = $this->app->messaging();
        $messaging->settings->save('test', ['account' => 'initalk-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        return $messaging->templates->save('test', $overrides + ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.\n금액: #{금액}원\n결제기한: #{결제기한}\n고객센터: #{고객센터}",
            'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}']]]);
    }

    #[DataProvider('connectionProvider')]
    public function testDefaultsValidationAndTemplateRules(array $config): void
    {
        $this->setupApp($config);
        $settings = new Settings($this->app);
        $defaults = $settings->read();
        self::assertSame('테스트 상점', $defaults['store_name']);
        self::assertSame('', $defaults['support_phone']);
        self::assertSame(48, $defaults['expiry_hours']);
        self::assertSame('test', $defaults['environment']);
        self::assertSame(['test' => '', 'live' => ''], $defaults['template']);
        self::assertSame(3, $defaults['settlement_days']);
        $template = $this->template();
        $settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-1234', 'expiry_hours' => '72', 'environment' => 'test',
            'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '5']);
        $saved = $settings->read();
        self::assertSame('이니 상점', $saved['store_name']);
        self::assertSame('1588-1234', $saved['support_phone']);
        self::assertSame(72, $saved['expiry_hours']);
        self::assertSame(5, $saved['settlement_days']);
        self::assertSame($template['id'], $settings->templateId('test'));
        self::assertSame('', $settings->templateId('live'));
        foreach ([
            ['store_name' => ''], ['expiry_hours' => '0'], ['expiry_hours' => '721'], ['environment' => 'prod'], ['settlement_days' => '-1'],
            ['template_test' => 'not-an-id'], ['template_live' => $template['id']],
        ] as $bad) {
            try {
                $settings->save($bad + ['store_name' => '이니 상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => '', 'template_live' => '']);
                self::fail('rejected: ' . json_encode($bad));
            } catch (DomainError $e) {
                self::assertSame(422, $e->status());
            }
        }
        $noToken = $this->template(['code' => 'no_token', 'buttons' => [['name' => '보기', 'url_mobile' => 'https://shop.example.test/view/#{주문번호}']]]);
        $this->expectException(DomainError::class);
        $settings->save(['store_name' => '이니 상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => $noToken['id'], 'template_live' => '']);
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownVariableInTemplateIsRejected(array $config): void
    {
        $this->setupApp($config);
        $template = $this->template(['code' => 'extra_var', 'message' => '#{구매자명}님 #{없는변수}']);
        $this->expectException(DomainError::class);
        (new Settings($this->app))->save(['store_name' => '상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => $template['id'], 'template_live' => '']);
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
cd /home/kagla/gnucms && ./vendor/bin/phpunit tests/Initalk 2>&1 | tail -5
```

Expected: `Class "GnuCms\Initalk\Status" not found` 류.

- [ ] **Step 3: `Status`·`Phone`을 쓴다**

`src/Initalk/Status.php`:

```php
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
```

`src/Initalk/Phone.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Messaging\Input;

/** 구매자 휴대폰 번호의 정규화·해시·마스킹. 원문은 암호화해서만 저장한다. */
final class Phone
{
    public static function normalize(mixed $value): string
    {
        return Input::phone($value);
    }

    public static function hash(string $digits, string $secret): string
    {
        return hash_hmac('sha256', 'initalk:phone:' . $digits, $secret);
    }

    public static function mask(string $digits): string
    {
        if ($digits === '') return '';
        $parts = self::parts($digits);
        return $parts[0] . '-' . str_repeat('*', strlen($parts[1])) . '-' . $parts[2];
    }

    public static function format(string $digits): string
    {
        if ($digits === '') return '';
        return implode('-', self::parts($digits));
    }

    /** @return array{string,string,string} */
    private static function parts(string $digits): array
    {
        $middle = strlen($digits) === 10 ? 3 : 4;
        return [substr($digits, 0, 3), substr($digits, 3, $middle), substr($digits, 3 + $middle)];
    }
}
```

- [ ] **Step 4: `Settings`와 `Service`를 쓴다**

`src/Initalk/Settings.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;

/** site_settings의 initalk.* 키. 알림톡 템플릿 선택은 저장 시 변수·버튼을 검증한다. */
final class Settings
{
    /** 알림톡 템플릿에 제공하는 변수 이름(스펙 §7). */
    public const VARIABLES = ['상점명', '구매자명', '요청일', '상품명', '금액', '결제기한', '고객센터', '결제토큰', '주문번호'];
    public const TOKEN_VARIABLE = '#{결제토큰}';

    public function __construct(private App $app)
    {
    }

    /** @return array{store_name:string,support_phone:string,expiry_hours:int,environment:string,template:array{test:string,live:string},settlement_days:int} */
    public function read(): array
    {
        $all = $this->app->cms()->settings();
        $hours = (int) ($all['initalk.expiry_hours'] ?? 48);
        $days = (int) ($all['initalk.settlement_days'] ?? 3);
        return [
            'store_name' => (string) ($all['initalk.store_name'] ?? ($all['site_name'] ?? 'GNUCMS')),
            'support_phone' => (string) ($all['initalk.support_phone'] ?? ''),
            'expiry_hours' => $hours >= 1 && $hours <= 720 ? $hours : 48,
            'environment' => in_array($all['initalk.environment'] ?? 'test', ['test', 'live'], true) ? (string) $all['initalk.environment'] : 'test',
            'template' => ['test' => (string) ($all['initalk.template.test'] ?? ''), 'live' => (string) ($all['initalk.template.live'] ?? '')],
            'settlement_days' => $days >= 0 && $days <= 60 ? $days : 3,
        ];
    }

    public function templateId(string $environment): string
    {
        return $this->read()['template'][$environment] ?? '';
    }

    public function save(array $input): void
    {
        $storeName = trim((string) ($input['store_name'] ?? ''));
        if ($storeName === '' || mb_strlen($storeName) > 40 || preg_match('/[\r\n]/', $storeName)) throw DomainError::validation(['store_name' => '상점명을 1~40자로 입력해 주세요.']);
        $support = trim((string) ($input['support_phone'] ?? ''));
        if ($support !== '' && !preg_match('/^[0-9-]{7,20}$/D', $support)) throw DomainError::validation(['support_phone' => '고객센터 번호는 숫자와 하이픈으로 입력해 주세요.']);
        $hours = $input['expiry_hours'] ?? '';
        if (!is_scalar($hours) || !preg_match('/^\d{1,3}$/D', (string) $hours) || (int) $hours < 1 || (int) $hours > 720) throw DomainError::validation(['expiry_hours' => '기본 결제기한은 1~720시간입니다.']);
        $environment = $input['environment'] ?? '';
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '테스트 또는 운영 환경을 선택해 주세요.']);
        $days = $input['settlement_days'] ?? '';
        if (!is_scalar($days) || !preg_match('/^\d{1,2}$/D', (string) $days) || (int) $days > 60) throw DomainError::validation(['settlement_days' => '정산 주기는 0~60일입니다.']);
        $templates = [];
        foreach (['test', 'live'] as $env) {
            $id = trim((string) ($input['template_' . $env] ?? ''));
            if ($id !== '') $this->assertTemplate($env, $id);
            $templates[$env] = $id;
        }
        $this->app->cms()->saveSettings([
            'initalk.store_name' => $storeName, 'initalk.support_phone' => $support, 'initalk.expiry_hours' => (string) (int) $hours,
            'initalk.environment' => $environment, 'initalk.template.test' => $templates['test'], 'initalk.template.live' => $templates['live'],
            'initalk.settlement_days' => (string) (int) $days,
        ]);
    }

    /** 템플릿이 이 환경의 사용 가능한 템플릿이고, 변수가 제공 집합 안이며, #{결제토큰}이 든 웹링크 버튼이 있어야 한다. */
    public function assertTemplate(string $environment, string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw DomainError::validation(['template_' . $environment => '알림톡 템플릿을 선택해 주세요.']);
        try {
            $template = $this->app->messaging()->templateAction('get', ['id' => $id]);
        } catch (DomainError $e) {
            throw DomainError::validation(['template_' . $environment => '알림톡 템플릿을 찾을 수 없습니다.']);
        }
        if ($template['environment'] !== $environment) throw DomainError::validation(['template_' . $environment => '선택한 환경의 템플릿이 아닙니다.']);
        if (!$template['enabled']) throw DomainError::validation(['template_' . $environment => '사용 중지된 템플릿입니다.']);
        $unknown = array_diff($template['variables'], self::VARIABLES);
        if ($unknown !== []) throw DomainError::validation(['template_' . $environment => '이니톡 결제가 제공하지 않는 변수가 있습니다: ' . implode(', ', $unknown)]);
        $hasToken = false;
        foreach ($template['buttons'] as $button) {
            foreach (['url_mobile', 'url_pc'] as $field) {
                if (str_contains((string) ($button[$field] ?? ''), self::TOKEN_VARIABLE)) $hasToken = true;
            }
        }
        if (!$hasToken) throw DomainError::validation(['template_' . $environment => '#{결제토큰}이 들어간 웹링크 버튼이 필요합니다.']);
        return $template;
    }
}
```

`src/Initalk/Service.php` (이후 작업이 속성을 추가한다):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;

/** 이니톡 결제 도메인 객체 조립. 생성자에서 쓰기·외부 통신을 하지 않는다. */
final class Service
{
    public readonly Settings $settings;

    public function __construct(public readonly App $app)
    {
        $this->settings = new Settings($app);
    }
}
```

`src/App.php`: 속성 `private ?\GnuCms\Initalk\Service $initalk = null;`와 `setMessaging()` 아래에

```php
    public function initalk(): \GnuCms\Initalk\Service
    {
        return $this->initalk ??= new \GnuCms\Initalk\Service($this);
    }
```

- [ ] **Step 5: 스키마 v24**

`src/Db/Schema.php`:

1. `TABLES`의 `'bp_settings', … 'bp_receipts',` 줄 뒤에 `'initalk_requests', 'initalk_events', 'initalk_batches', 'initalk_ledger',` 추가.
2. `INDEXES`의 `'bp_list', 'bp_tries', 'bp_results',` 뒤에 `'ux_initalk_number', 'ux_initalk_token', 'ix_initalk_status', 'ix_initalk_created', 'ix_initalk_phone', 'ix_initalk_expires', 'ix_initalk_batch', 'ix_initalk_events', 'ix_initalk_ledger_at', 'ix_initalk_ledger_request',` 추가.
3. `VERSION = '23'` → `'24'`.
4. `statements()`의 `$this->messagingStatements()` 뒤에 `, $this->initalkStatements()` 추가.
5. `migrateAll()`의 `$this->migrateMessaging();` 다음 줄에 `$this->migrateInitalk();` 추가.
6. `migrateMessaging()` 아래에 추가:

```php
    private function initalkStatements(): array
    {
        return [
            'CREATE TABLE initalk_requests (
                id VARCHAR(32) PRIMARY KEY, number VARCHAR(20) NOT NULL, url_token VARCHAR(40) NOT NULL,
                environment VARCHAR(8) NOT NULL, status VARCHAR(16) NOT NULL,
                product_name VARCHAR(30) NOT NULL, product_detail VARCHAR(150) NOT NULL DEFAULT \'\',
                buyer_name VARCHAR(30) NOT NULL, phone {TEXT} NOT NULL, phone_hash VARCHAR(64) NOT NULL, phone_mask VARCHAR(16) NOT NULL,
                amount INTEGER NOT NULL, expires_at BIGINT NOT NULL, batch_id VARCHAR(32) NULL,
                dispatch_count INTEGER NOT NULL DEFAULT 0, last_dispatch_id VARCHAR(32) NULL, last_dispatched_at BIGINT NULL,
                checkout_started_at BIGINT NULL, config_revision VARCHAR(32) NOT NULL DEFAULT \'\',
                paid_at BIGINT NULL, transaction_id VARCHAR(40) NOT NULL DEFAULT \'\', refunded_amount INTEGER NOT NULL DEFAULT 0,
                needs_review SMALLINT NOT NULL DEFAULT 0, created_by INTEGER NOT NULL DEFAULT 0,
                created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, status_changed_at BIGINT NOT NULL
            ){SUFFIX}',
            'CREATE UNIQUE INDEX ux_initalk_number ON initalk_requests (number)',
            'CREATE UNIQUE INDEX ux_initalk_token ON initalk_requests (url_token)',
            'CREATE INDEX ix_initalk_status ON initalk_requests (status)',
            'CREATE INDEX ix_initalk_created ON initalk_requests (created_at)',
            'CREATE INDEX ix_initalk_phone ON initalk_requests (phone_hash)',
            'CREATE INDEX ix_initalk_expires ON initalk_requests (expires_at)',
            'CREATE INDEX ix_initalk_batch ON initalk_requests (batch_id)',
            'CREATE TABLE initalk_events (
                id {AUTO_PK}, request_id VARCHAR(32) NOT NULL, type VARCHAR(32) NOT NULL,
                actor VARCHAR(100) NOT NULL, note {TEXT} NOT NULL, created_at BIGINT NOT NULL
            ){SUFFIX}',
            'CREATE INDEX ix_initalk_events ON initalk_events (request_id, id)',
            'CREATE TABLE initalk_batches (
                id VARCHAR(32) PRIMARY KEY, filename VARCHAR(200) NOT NULL, total INTEGER NOT NULL,
                created INTEGER NOT NULL, failed INTEGER NOT NULL, errors {TEXT} NOT NULL,
                created_by INTEGER NOT NULL DEFAULT 0, created_at BIGINT NOT NULL
            ){SUFFIX}',
            'CREATE TABLE initalk_ledger (
                id VARCHAR(64) PRIMARY KEY, request_id VARCHAR(32) NOT NULL, kind VARCHAR(16) NOT NULL,
                amount INTEGER NOT NULL, at BIGINT NOT NULL, reference VARCHAR(100) NOT NULL, created_at BIGINT NOT NULL
            ){SUFFIX}',
            'CREATE INDEX ix_initalk_ledger_at ON initalk_ledger (at)',
            'CREATE INDEX ix_initalk_ledger_request ON initalk_ledger (request_id)',
        ];
    }

    /** 이니톡 결제 테이블. 없는 것만 만들고 인덱스는 존재 검사 후 만든다. */
    public function migrateInitalk(): void
    {
        foreach ($this->initalkStatements() as $sql) {
            if (preg_match('/^CREATE TABLE (\w+)/', $sql, $m)) {
                if (!$this->tableExists($m[1])) $this->db->execute($this->expand($sql));
            } elseif (preg_match('/^CREATE (?:UNIQUE )?INDEX (\w+)/', $sql, $m)) {
                $this->createIndexIfMissing($m[1], $sql);
            }
        }
    }
```

`tests/Db/SchemaTest.php`에서 테이블 수를 단언하는 값을 22 → 26으로 고친다.

- [ ] **Step 6: 테스트**

```bash
for f in src/Initalk/*.php src/Db/Schema.php src/App.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Initalk tests/Db
./vendor/bin/phpunit
```

Expected: 모두 `OK`.

- [ ] **Step 7: 커밋**

```bash
git add src/Initalk src/Db/Schema.php src/App.php tests/Initalk tests/Db/SchemaTest.php
git commit -m "feat: add INITalk request tables, status rules and settings

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 2: `Requests`·`RequestNumber`·`Events` — 결제 요청 도메인

**Files:**
- Create: `src/Initalk/Events.php`, `src/Initalk/RequestNumber.php`, `src/Initalk/Requests.php`
- Modify: `src/Initalk/Service.php`
- Test: `tests/Initalk/RequestsTest.php`

**Interfaces:**
- Consumes: `Status`, `Phone`, `GnuCms\Support\Base64Url::encode()`, `GnuCms\Mail\SecretCipher`, `GnuCms\Support\Clock`.
- Produces: `Events::record(string $requestId, string $type, string $actor, string $note = ''): void`, `Events::forRequest(string $requestId): array`; `RequestNumber::next(Connection $db, int $timestamp): string`; `Requests::normalize(array $input, int $defaultHours): array{product_name,product_detail,buyer_name,phone,amount,expiry_hours}` (static), `Requests::create(array $input, string $environment, int $defaultHours, int $actorId, string $actor, ?string $batchId = null): array`, `find(string $id): array`(없으면 notFound; `phone`은 복호화된 숫자, `status_label` 포함; `search`/`recent` 항목에는 `phone`·`phone_hash`가 없고 `phone_mask`만 있다), `findByToken(string $token): ?array`, `search(array $filter, int $page = 1): array{items,total,page,per_page}`, `counts(string $environment): array<string,int>`, `recent(string $environment, int $limit): array`, `customerSummary(string $phoneDigits, string $environment): array{count,total,last_paid_at}`, `cancel(string $id, string $actor): array`, `markPaid(string $id, int $paidAt, string $transactionId, string $actor): array`, `applyRefund(string $id, int $refundedTotal, string $actor, string $note): array`, `extend(string $id, int $hours, string $actor): array`, `recordDispatch(string $id, string $dispatchId, int $sequence, string $submission, string $actor): array`, `touchCheckout(string $id, string $configRevision): void`, `setReview(string $id, bool $review, string $actor, string $note): void`, `expire(int $limit = 200): int`, `expireOne(string $id): bool`, `purge(int $limit = 100): int`; `Service->requests`, `Service->events`.

- [ ] **Step 1: 테스트를 쓴다**

`tests/Initalk/RequestsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Requests;
use GnuCms\Initalk\Status;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RequestsTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private Requests $requests;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-req-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ir' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $this->requests = $this->app->initalk()->requests;
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function input(array $overrides = []): array
    {
        return $overrides + ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니',
            'phone' => '010-2345-7891', 'amount' => '15,800'];
    }

    private function rejected(callable $work, string $field): void
    {
        try { $work(); self::fail('rejected: ' . $field); } catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey($field, $e->details()); }
    }

    #[DataProvider('connectionProvider')]
    public function testCreateNumbersTokensEncryptionAndLookups(array $config): void
    {
        $this->setupApp($config);
        $first = $this->requests->create($this->input(), 'test', 48, 7, '운영자');
        $second = $this->requests->create($this->input(['buyer_name' => '홍길동', 'phone' => '01011112222', 'expiry_hours' => '2']), 'test', 48, 7, '운영자');
        self::assertSame('IT-20260915-0001', $first['number']);
        self::assertSame('IT-20260915-0002', $second['number']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $first['id']);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{27}$/D', $first['url_token']);
        self::assertSame(Status::CREATED, $first['status']);
        self::assertSame('결제생성', $first['status_label']);
        self::assertSame(15800, $first['amount']);
        self::assertSame('01023457891', $first['phone']);
        self::assertSame('010-****-7891', $first['phone_mask']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $first['expires_at']);
        self::assertSame(Clock::timestamp() + 2 * 3600, $second['expires_at']);
        self::assertSame(7, $first['created_by']);
        $raw = $this->app->db()->selectOne('SELECT phone, phone_hash FROM ' . $this->app->db()->table('initalk_requests') . ' WHERE id = ?', [$first['id']]);
        self::assertStringNotContainsString('01023457891', $raw['phone']);
        self::assertSame(64, strlen($raw['phone_hash']));
        self::assertSame($first['id'], $this->requests->findByToken($first['url_token'])['id']);
        self::assertNull($this->requests->findByToken('short'));
        self::assertNull($this->requests->findByToken(str_repeat('a', 27)));
        self::assertSame('created', $this->app->initalk()->events->forRequest($first['id'])[0]['type']);
        try { $this->requests->find(str_repeat('0', 32)); self::fail('not found'); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testValidationRules(array $config): void
    {
        $this->setupApp($config);
        $create = fn (array $o) => $this->requests->create($this->input($o), 'test', 48, 1, '운영자');
        $this->rejected(fn () => $create(['product_name' => '']), 'product_name');
        $this->rejected(fn () => $create(['product_name' => str_repeat('가', 31)]), 'product_name');
        $this->rejected(fn () => $create(['product_detail' => str_repeat('a', 151)]), 'product_detail');
        $this->rejected(fn () => $create(['buyer_name' => "김\n이니"]), 'buyer_name');
        $this->rejected(fn () => $create(['phone' => '02-123-4567']), 'phone');
        $this->rejected(fn () => $create(['amount' => '99']), 'amount');
        $this->rejected(fn () => $create(['amount' => '100000000']), 'amount');
        $this->rejected(fn () => $create(['amount' => '1e3']), 'amount');
        $this->rejected(fn () => $create(['expiry_hours' => '721']), 'expiry_hours');
        $this->rejected(fn () => $create(['expiry_hours' => '0']), 'expiry_hours');
        self::assertSame(100, $create(['amount' => '100', 'product_detail' => ''])['amount']);
        self::assertSame(0, $this->requests->search(['environment' => 'live'], 1)['total']);
    }

    #[DataProvider('connectionProvider')]
    public function testSearchCountsRecentAndCustomerSummary(array $config): void
    {
        $this->setupApp($config);
        $a = $this->requests->create($this->input(), 'test', 48, 1, '운영자');
        $b = $this->requests->create($this->input(['buyer_name' => '홍길동', 'phone' => '01011112222', 'amount' => '5000', 'product_name' => '수강료']), 'test', 48, 1, '운영자');
        $c = $this->requests->create($this->input(['phone' => '01023457891', 'amount' => '30000']), 'live', 48, 1, '운영자');
        $this->requests->markPaid($a['id'], Clock::timestamp() + 60, 'StdpayCARD0001', 'customer');
        $this->requests->cancel($b['id'], '운영자');
        self::assertSame(['created' => 0, 'waiting' => 0, 'paid' => 1, 'expired' => 0, 'cancelled' => 1, 'refunded' => 0], $this->requests->counts('test'));
        self::assertSame(['created' => 1, 'waiting' => 0, 'paid' => 0, 'expired' => 0, 'cancelled' => 0, 'refunded' => 0], $this->requests->counts('live'));
        $all = $this->requests->search(['environment' => 'test'], 1);
        self::assertSame(2, $all['total']);
        self::assertSame([$b['id'], $a['id']], array_column($all['items'], 'id'));
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'phone' => '010-1111-2222'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'buyer_name' => '길동'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'product_name' => '핸드크림'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'number' => $a['number']], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'amount' => '5000'], 1)['total']);
        self::assertSame(1, $this->requests->search(['environment' => 'test', 'status' => 'paid'], 1)['total']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'sendable' => '1'], 1)['total']);
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'sendable' => '0'], 1)['total']);
        self::assertSame(2, $this->requests->search(['environment' => 'test', 'from' => '2026-09-15', 'until' => '2026-09-15'], 1)['total']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'from' => '2026-09-16'], 1)['total']);
        self::assertSame([$c['id']], array_column($this->requests->recent('live', 10), 'id'));
        $summary = $this->requests->customerSummary('01023457891', 'test');
        self::assertSame(['count' => 1, 'total' => 15800, 'last_paid_at' => Clock::timestamp() + 60], $summary);
        self::assertSame(['count' => 0, 'total' => 0, 'last_paid_at' => null], $this->requests->customerSummary('01099998888', 'test'));
        self::assertStringNotContainsString('01023457891', json_encode($all['items']));
    }

    #[DataProvider('connectionProvider')]
    public function testTransitionsExpiryProtectionRefundAndPurge(array $config): void
    {
        $this->setupApp($config);
        $r = $this->requests->create($this->input(['expiry_hours' => '1']), 'test', 48, 1, '운영자');
        $this->requests->recordDispatch($r['id'], str_repeat('d', 32), 1, 'accepted', '운영자');
        $after = $this->requests->find($r['id']);
        self::assertSame(Status::WAITING, $after['status']);
        self::assertSame(1, $after['dispatch_count']);
        self::assertSame(str_repeat('d', 32), $after['last_dispatch_id']);
        $this->requests->recordDispatch($r['id'], str_repeat('e', 32), 2, 'rejected', '운영자');
        self::assertSame(Status::WAITING, $this->requests->find($r['id'])['status']);
        self::assertSame('dispatch_failed', end($this->app->initalk()->events->forRequest($r['id']))['type']);
        // 만료: 결제창을 연 지 30분이 안 된 건은 보호한다.
        Clock::freeze('2026-09-15 04:30:00');
        $this->requests->touchCheckout($r['id'], bin2hex(random_bytes(16)));
        self::assertSame(0, $this->requests->expire());
        Clock::freeze('2026-09-15 05:10:00');
        self::assertSame(1, $this->requests->expire());
        self::assertSame(Status::EXPIRED, $this->requests->find($r['id'])['status']);
        self::assertFalse($this->requests->expireOne($r['id']));
        $this->rejected(fn () => $this->requests->markPaid(str_repeat('0', 32), 1, 'x', 'customer'), 'status');
        // 만료 → 연장 → 결제 → 부분 환불 → 전액 환불
        $extended = $this->requests->extend($r['id'], 24, '운영자');
        self::assertSame(Status::CREATED, $extended['status']);
        self::assertSame(Clock::timestamp() + 24 * 3600, $extended['expires_at']);
        $paid = $this->requests->markPaid($r['id'], Clock::timestamp(), 'StdpayCARD0002', 'customer');
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame('StdpayCARD0002', $paid['transaction_id']);
        $this->rejected(fn () => $this->requests->cancel($r['id'], '운영자'), 'status');
        $partial = $this->requests->applyRefund($r['id'], 5800, '운영자', '부분 환불');
        self::assertSame(Status::PAID, $partial['status']);
        self::assertSame(5800, $partial['refunded_amount']);
        $full = $this->requests->applyRefund($r['id'], 15800, '운영자', '전액 환불');
        self::assertSame(Status::REFUNDED, $full['status']);
        $this->requests->setReview($r['id'], true, 'system', '조회 불일치');
        self::assertSame(1, $this->requests->find($r['id'])['needs_review']);
        // 개인정보 정리: 90일 지난 종료 건만
        self::assertSame(0, $this->requests->purge());
        Clock::freeze('2026-12-20 00:00:00');
        self::assertSame(1, $this->requests->purge());
        $purged = $this->requests->find($r['id']);
        self::assertSame('', $purged['phone']);
        self::assertSame('', $purged['phone_mask']);
        self::assertSame('', $purged['buyer_name']);
        self::assertSame('플로럴 핸드크림 30ml', $purged['product_name']);
        self::assertSame(0, $this->requests->search(['environment' => 'test', 'phone' => '01023457891'], 1)['total']);
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Initalk/RequestsTest.php 2>&1 | tail -5
```

Expected: `Undefined property … $requests` 또는 클래스 부재.

- [ ] **Step 3: `Events`와 `RequestNumber`**

`src/Initalk/Events.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;

/** 요청별 상태·발송·결제·환불 이력. 상세 화면의 타임라인이다. */
final class Events
{
    public function __construct(private Connection $db)
    {
    }

    public function record(string $requestId, string $type, string $actor, string $note = ''): void
    {
        $this->db->insert('initalk_events', ['request_id' => $requestId, 'type' => $type, 'actor' => mb_substr($actor, 0, 100),
            'note' => mb_substr($note, 0, 1000), 'created_at' => Clock::timestamp()]);
    }

    public function forRequest(string $requestId): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('initalk_events') . ' WHERE request_id = ? ORDER BY id', [$requestId]);
    }
}
```

`src/Initalk/RequestNumber.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;

/** 사람이 보는 주문번호 IT-YYYYMMDD-NNNN. 날짜는 Asia/Seoul, 순번은 하루 단위다. */
final class RequestNumber
{
    public static function next(Connection $db, int $timestamp): string
    {
        $prefix = 'IT-' . (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Ymd') . '-';
        $row = $db->selectOne('SELECT MAX(number) AS last FROM ' . $db->table('initalk_requests') . ' WHERE number LIKE ?', [$prefix . '%']);
        $sequence = is_string($row['last'] ?? null) && $row['last'] !== '' ? (int) substr($row['last'], -4) + 1 : 1;
        if ($sequence > 9999) throw DomainError::serviceUnavailable('오늘 만들 수 있는 결제 요청 번호를 모두 사용했습니다.');
        return $prefix . sprintf('%04d', $sequence);
    }
}
```

- [ ] **Step 4: `Requests`**

`src/Initalk/Requests.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\Base64Url;
use GnuCms\Support\Clock;

/** 결제 요청의 생성·조회·검색·상태 전이. 상태 전이는 모두 조건부 UPDATE다. */
final class Requests
{
    public const PER_PAGE = 20;
    public const CHECKOUT_GRACE = 1800;
    public const RETENTION = 90 * 86400;
    private const EXTRA_COLUMNS = ['paid_at', 'transaction_id', 'refunded_amount', 'expires_at', 'dispatch_count', 'last_dispatch_id', 'last_dispatched_at'];
    private SecretCipher $cipher;
    private string $secret;

    public function __construct(private App $app, private Events $events)
    {
        $this->secret = (string) $app->config('auth.secret', '');
        $this->cipher = new SecretCipher($this->secret);
    }

    private function db(): Connection { return $this->app->db(); }

    /** 단건 생성과 CSV 행이 같은 규칙을 쓴다. */
    public static function normalize(array $input, int $defaultHours): array
    {
        $data = [
            'product_name' => self::text($input['product_name'] ?? '', 'product_name', '상품명', 1, 30),
            'product_detail' => self::text($input['product_detail'] ?? '', 'product_detail', '상품 상세', 0, 150),
            'buyer_name' => self::text($input['buyer_name'] ?? '', 'buyer_name', '구매자명', 1, 30),
            'phone' => Phone::normalize($input['phone'] ?? null),
        ];
        $amount = str_replace([',', ' ', '원'], '', is_scalar($input['amount'] ?? null) ? (string) $input['amount'] : '');
        if (!preg_match('/^\d{1,8}$/D', $amount) || (int) $amount < 100) throw DomainError::validation(['amount' => '금액은 100원 이상 99,999,999원 이하의 숫자로 입력해 주세요.']);
        $data['amount'] = (int) $amount;
        $hours = is_scalar($input['expiry_hours'] ?? null) ? trim((string) $input['expiry_hours']) : '';
        if ($hours === '') $hours = (string) $defaultHours;
        if (!preg_match('/^\d{1,3}$/D', $hours) || (int) $hours < 1 || (int) $hours > 720) throw DomainError::validation(['expiry_hours' => '결제기한은 1~720시간입니다.']);
        $data['expiry_hours'] = (int) $hours;
        return $data;
    }

    private static function text(mixed $value, string $field, string $label, int $min, int $max): string
    {
        $value = is_scalar($value) ? trim((string) $value) : null;
        if ($value === null || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $value)) throw DomainError::validation([$field => $label . ' 입력값을 확인해 주세요.']);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) throw DomainError::validation([$field => $label . '은(는) ' . ($min === 0 ? '최대 ' : $min . '~') . $max . '자까지 입력할 수 있습니다.']);
        return $value;
    }

    public function create(array $input, string $environment, int $defaultHours, int $actorId, string $actor, ?string $batchId = null): array
    {
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '환경을 확인해 주세요.']);
        $data = self::normalize($input, $defaultHours);
        $now = Clock::timestamp();
        $row = ['id' => bin2hex(random_bytes(16)), 'url_token' => Base64Url::encode(random_bytes(20)), 'environment' => $environment,
            'status' => Status::CREATED, 'product_name' => $data['product_name'], 'product_detail' => $data['product_detail'],
            'buyer_name' => $data['buyer_name'], 'phone' => $this->cipher->encrypt($data['phone']), 'phone_hash' => Phone::hash($data['phone'], $this->secret),
            'phone_mask' => Phone::mask($data['phone']), 'amount' => $data['amount'], 'expires_at' => $now + $data['expiry_hours'] * 3600,
            'batch_id' => $batchId, 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now, 'status_changed_at' => $now];
        for ($attempt = 1; ; $attempt++) {
            $row['number'] = RequestNumber::next($this->db(), $now);
            try {
                $this->db()->insert('initalk_requests', $row);
                break;
            } catch (DomainError $e) {
                // 같은 순간 두 관리자가 만들면 유일 제약에 걸린다. 번호만 다시 뽑는다.
                if ($attempt >= 5 || !preg_match('/UNIQUE|Duplicate/i', $e->getMessage())) throw $e;
            }
        }
        $this->events->record($row['id'], 'created', $actor, '주문번호 ' . $row['number']);
        return $this->find($row['id']);
    }

    public function find(string $id): array
    {
        $row = preg_match('/^[a-f0-9]{32}$/D', $id) ? $this->db()->selectOne('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE id = ?', [$id]) : null;
        if ($row === null) throw DomainError::notFound('결제 요청을 찾을 수 없습니다.');
        return $this->decode($row);
    }

    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{20,40}$/D', $token)) return null;
        $row = $this->db()->selectOne('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE url_token = ?', [$token]);
        return $row === null ? null : $this->decode($row);
    }

    /** 목록(search/recent)은 번호 원문 없이, 단건(find)은 복호화된 숫자 번호와 함께 돌려준다. */
    private function decode(array $row, bool $withPhone = true): array
    {
        foreach (['amount', 'expires_at', 'dispatch_count', 'refunded_amount', 'needs_review', 'created_by', 'created_at', 'updated_at', 'status_changed_at'] as $key) $row[$key] = (int) $row[$key];
        foreach (['last_dispatched_at', 'checkout_started_at', 'paid_at'] as $key) $row[$key] = $row[$key] === null ? null : (int) $row[$key];
        unset($row['phone_hash']);
        if ($withPhone) $row['phone'] = $row['phone'] === '' ? '' : $this->cipher->decrypt($row['phone']);
        else unset($row['phone']);
        $row['status_label'] = Status::label($row['status']);
        return $row;
    }

    /** @return array{items:list<array>,total:int,page:int,per_page:int} */
    public function search(array $filter, int $page = 1): array
    {
        $where = [];
        $params = [];
        if (in_array($filter['environment'] ?? '', ['test', 'live'], true)) { $where[] = 'environment = ?'; $params[] = $filter['environment']; }
        foreach (['from' => '>=', 'until' => '<='] as $key => $op) {
            $value = $filter[$key] ?? '';
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
                $day = new \DateTimeImmutable($value . ($op === '>=' ? ' 00:00:00' : ' 23:59:59'), new \DateTimeZone('Asia/Seoul'));
                $where[] = 'created_at ' . $op . ' ?'; $params[] = $day->getTimestamp();
            }
        }
        $phone = is_string($filter['phone'] ?? null) ? preg_replace('/\D/', '', $filter['phone']) : '';
        if ($phone !== '') { $where[] = 'phone_hash = ?'; $params[] = Phone::hash($phone, $this->secret); }
        foreach (['buyer_name', 'product_name'] as $key) {
            $value = is_string($filter[$key] ?? null) ? trim($filter[$key]) : '';
            if ($value !== '') { $where[] = $key . ' LIKE ?'; $params[] = '%' . addcslashes($value, '%_\\') . '%'; }
        }
        $number = is_string($filter['number'] ?? null) ? trim($filter['number']) : '';
        if ($number !== '') { $where[] = 'number LIKE ?'; $params[] = addcslashes($number, '%_\\') . '%'; }
        $amount = is_scalar($filter['amount'] ?? null) ? str_replace(',', '', (string) $filter['amount']) : '';
        if (preg_match('/^\d{1,9}$/D', $amount)) { $where[] = 'amount = ?'; $params[] = (int) $amount; }
        if (isset(Status::LABELS[$filter['status'] ?? ''])) { $where[] = 'status = ?'; $params[] = $filter['status']; }
        $batch = is_string($filter['batch'] ?? null) ? $filter['batch'] : '';
        if (preg_match('/^[a-f0-9]{32}$/D', $batch)) { $where[] = 'batch_id = ?'; $params[] = $batch; }
        if (($filter['sendable'] ?? '') === '1') $where[] = "status IN ('created','waiting','expired')";
        if (($filter['sendable'] ?? '') === '0') $where[] = "status NOT IN ('created','waiting','expired')";
        $sql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $table = $this->db()->table('initalk_requests');
        $total = (int) ($this->db()->selectOne('SELECT COUNT(*) AS c FROM ' . $table . $sql, $params)['c'] ?? 0);
        $page = max(1, $page);
        $rows = $this->db()->select('SELECT * FROM ' . $table . $sql . ' ORDER BY created_at DESC, id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        return ['items' => array_map(fn (array $row): array => $this->decode($row, false), $rows), 'total' => $total, 'page' => $page, 'per_page' => self::PER_PAGE];
    }

    public function counts(string $environment): array
    {
        $counts = array_fill_keys(array_keys(Status::LABELS), 0);
        foreach ($this->db()->select('SELECT status, COUNT(*) AS c FROM ' . $this->db()->table('initalk_requests') . ' WHERE environment = ? GROUP BY status', [$environment]) as $row) {
            if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    public function recent(string $environment, int $limit): array
    {
        $rows = $this->db()->select('SELECT * FROM ' . $this->db()->table('initalk_requests') . ' WHERE environment = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(100, $limit)), [$environment]);
        return array_map(fn (array $row): array => $this->decode($row, false), $rows);
    }

    /** 같은 번호의 결제 완료 이력: 거래횟수·총 거래금액·최근 결제일. */
    public function customerSummary(string $phoneDigits, string $environment): array
    {
        $row = $this->db()->selectOne('SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total, MAX(paid_at) AS last FROM ' . $this->db()->table('initalk_requests')
            . " WHERE environment = ? AND phone_hash = ? AND status IN ('paid','refunded')", [$environment, Phone::hash($phoneDigits, $this->secret)]);
        return ['count' => (int) ($row['c'] ?? 0), 'total' => (int) ($row['total'] ?? 0), 'last_paid_at' => isset($row['last']) && $row['last'] !== null ? (int) $row['last'] : null];
    }

    /** @param list<string> $from */
    private function transition(string $id, array $from, string $to, array $extra, string $actor, string $type, string $note = ''): array
    {
        foreach (array_keys($extra) as $column) if (!in_array($column, self::EXTRA_COLUMNS, true)) throw DomainError::internal('허용되지 않은 컬럼 갱신입니다.');
        $now = Clock::timestamp();
        $params = ['id' => $id];
        $list = [];
        foreach (array_values($from) as $i => $status) { $params['from' . $i] = $status; $list[] = ':from' . $i; }
        $changed = $this->db()->update('initalk_requests', ['status' => $to, 'status_changed_at' => $now, 'updated_at' => $now] + $extra,
            'id = :id AND status IN (' . implode(', ', $list) . ')', $params);
        if ($changed !== 1) throw DomainError::validation(['status' => '상태가 변경되었습니다. 새로고침 후 확인해 주세요.']);
        $this->events->record($id, $type, $actor, $note);
        return $this->find($id);
    }

    public function cancel(string $id, string $actor): array
    {
        return $this->transition($id, [Status::CREATED, Status::WAITING, Status::EXPIRED], Status::CANCELLED, [], $actor, 'cancelled', '결제 전 취소');
    }

    public function markPaid(string $id, int $paidAt, string $transactionId, string $actor): array
    {
        return $this->transition($id, [Status::CREATED, Status::WAITING, Status::EXPIRED], Status::PAID,
            ['paid_at' => $paidAt, 'transaction_id' => mb_substr($transactionId, 0, 40)], $actor, 'paid', '승인 ' . $transactionId);
    }

    public function applyRefund(string $id, int $refundedTotal, string $actor, string $note): array
    {
        $request = $this->find($id);
        if (!Status::canRefund($request['status'])) throw DomainError::validation(['status' => '결제 완료 상태에서만 환불할 수 있습니다.']);
        if ($refundedTotal < $request['refunded_amount'] || $refundedTotal > $request['amount']) throw DomainError::validation(['refund' => '환불 누적액을 확인해 주세요.']);
        if ($refundedTotal === $request['amount']) return $this->transition($id, [Status::PAID], Status::REFUNDED, ['refunded_amount' => $refundedTotal], $actor, 'refund', $note);
        $this->db()->update('initalk_requests', ['refunded_amount' => $refundedTotal, 'updated_at' => Clock::timestamp()], 'id = :id AND status = :status', ['id' => $id, 'status' => Status::PAID]);
        $this->events->record($id, 'refund', $actor, $note);
        return $this->find($id);
    }

    public function extend(string $id, int $hours, string $actor): array
    {
        $request = $this->find($id);
        $expiresAt = Clock::timestamp() + max(1, min(720, $hours)) * 3600;
        if ($request['status'] === Status::EXPIRED) {
            return $this->transition($id, [Status::EXPIRED], Status::CREATED, ['expires_at' => $expiresAt], $actor, 'extended', '결제기한 연장');
        }
        if (!Status::canPay($request['status'])) throw DomainError::validation(['status' => '기한을 연장할 수 없는 상태입니다.']);
        $this->db()->update('initalk_requests', ['expires_at' => $expiresAt, 'updated_at' => Clock::timestamp()], 'id = :id', ['id' => $id]);
        $this->events->record($id, 'extended', $actor, '결제기한 연장');
        return $this->find($id);
    }

    public function recordDispatch(string $id, string $dispatchId, int $sequence, string $submission, string $actor): array
    {
        $now = Clock::timestamp();
        $this->db()->update('initalk_requests', ['dispatch_count' => $sequence, 'last_dispatch_id' => $dispatchId, 'last_dispatched_at' => $now, 'updated_at' => $now], 'id = :id', ['id' => $id]);
        $request = $this->find($id);
        if ($submission === 'accepted') {
            if ($request['status'] === Status::CREATED) return $this->transition($id, [Status::CREATED], Status::WAITING, [], $actor, 'dispatched', '알림톡 접수 ' . $sequence . '회');
            $this->events->record($id, 'dispatched', $actor, '알림톡 접수 ' . $sequence . '회');
        } else {
            $this->events->record($id, 'dispatch_failed', $actor, '알림톡 접수 실패(' . $submission . ') ' . $sequence . '회');
        }
        return $this->find($id);
    }

    public function touchCheckout(string $id, string $configRevision): void
    {
        $now = Clock::timestamp();
        $this->db()->update('initalk_requests', ['checkout_started_at' => $now, 'config_revision' => $configRevision, 'updated_at' => $now], 'id = :id', ['id' => $id]);
        $this->events->record($id, 'checkout', 'customer', '결제창 열기');
    }

    public function setReview(string $id, bool $review, string $actor, string $note): void
    {
        $this->db()->update('initalk_requests', ['needs_review' => $review ? 1 : 0, 'updated_at' => Clock::timestamp()], 'id = :id', ['id' => $id]);
        $this->events->record($id, $review ? 'review' : 'review_cleared', $actor, $note);
    }

    public function expire(int $limit = 200): int
    {
        $now = Clock::timestamp();
        $rows = $this->db()->select('SELECT id FROM ' . $this->db()->table('initalk_requests')
            . " WHERE status IN ('created','waiting') AND expires_at < ? AND (checkout_started_at IS NULL OR checkout_started_at < ?) ORDER BY expires_at LIMIT " . max(1, min(1000, $limit)),
            [$now, $now - self::CHECKOUT_GRACE]);
        $count = 0;
        foreach ($rows as $row) if ($this->expireOne($row['id'])) $count++;
        return $count;
    }

    /** 만료 조건을 다시 검사한 뒤 만료시킨다. 이미 다른 상태면 false. */
    public function expireOne(string $id): bool
    {
        $request = $this->find($id);
        $now = Clock::timestamp();
        if (!Status::canPay($request['status']) || $request['expires_at'] >= $now || ($request['checkout_started_at'] !== null && $request['checkout_started_at'] >= $now - self::CHECKOUT_GRACE)) return false;
        try {
            $this->transition($id, Status::OPEN, Status::EXPIRED, [], 'system', 'expired', '결제기한 경과');
            return true;
        } catch (DomainError $e) {
            return false;
        }
    }

    /** 종료된 지 90일이 지난 요청의 구매자명·번호를 지운다. 최대 100건. */
    public function purge(int $limit = 100): int
    {
        $rows = $this->db()->select('SELECT id FROM ' . $this->db()->table('initalk_requests')
            . " WHERE status IN ('paid','refunded','expired','cancelled') AND status_changed_at < ? AND phone <> '' LIMIT " . max(1, min(1000, $limit)), [Clock::timestamp() - self::RETENTION]);
        foreach ($rows as $row) {
            $this->db()->update('initalk_requests', ['phone' => '', 'phone_hash' => '', 'phone_mask' => '', 'buyer_name' => '', 'updated_at' => Clock::timestamp()], 'id = :id', ['id' => $row['id']]);
            $this->events->record($row['id'], 'purged', 'system', '보관 기간 만료 개인정보 정리');
        }
        return count($rows);
    }
}
```

`src/Initalk/Service.php`에 속성과 조립을 추가한다:

```php
    public readonly Events $events;
    public readonly Requests $requests;
    // 생성자 안:
        $this->events = new Events($app->db());
        $this->requests = new Requests($app, $this->events);
```

- [ ] **Step 5: 테스트**

```bash
for f in src/Initalk/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Initalk
```

Expected: `OK`. MySQL에서 `MAX(number)`·`LIMIT n OFFSET m` 리터럴은 그대로 동작한다.

- [ ] **Step 6: 커밋**

```bash
git add src/Initalk tests/Initalk/RequestsTest.php
git commit -m "feat: add INITalk payment request domain with numbering, transitions and expiry

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 3: `Notifier` — 알림톡 발송·재발송·기한 연장

**Files:**
- Create: `src/Initalk/Notifier.php`
- Modify: `src/Initalk/Service.php`
- Test: `tests/Initalk/NotifierTest.php`

**Interfaces:**
- Consumes: `MessagingService::status(env)['enabled']`, `templateAction('get', ['id'])` (`variables`, `revision`), `send([...])` → `['id', 'submission', 'delivery', …]`; `Requests::find/extend/recordDispatch`; `Settings::read()`.
- Produces: `Notifier::variables(array $request, array $config, array $wanted): array<string,string>` (static), `Notifier::send(string $id, string $actor): array`(발송 상세), `Notifier::sendMany(array $ids, string $actor): array{sent:int,failed:int,errors:array<string,string>}`; `Service->notifier`.

- [ ] **Step 1: 테스트를 쓴다**

`tests/Initalk/NotifierTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Notifier;
use GnuCms\Initalk\Status;
use GnuCms\Messaging\MessagingService;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NotifierTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;

    private function setupApp(array $config): array
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-notify-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'in' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $this->http = new FakeTransport();
        $messaging = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($messaging);
        $messaging->settings->save('test', ['account' => 'initalk-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01023457891']);
        $messaging->settings->setEnabled('test', true);
        $template = $messaging->templates->save('test', ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.\n■ 상품명: #{상품명}\n■ 금액: #{금액}원\n■ 결제기한: #{결제기한}\n* 고객센터: #{고객센터}",
            'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}', 'url_pc' => 'https://shop.example.test/pay/#{결제토큰}']]]);
        $this->app->initalk()->settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-4954', 'expiry_hours' => '48',
            'environment' => 'test', 'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '3']);
        return $template;
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function request(array $overrides = []): array
    {
        return $this->app->initalk()->requests->create($overrides + ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    public function testVariablesAreFormattedForTheTemplate(): void
    {
        $request = ['buyer_name' => '김이니', 'created_at' => 1789441200, 'product_name' => '크림', 'amount' => 15800, 'expires_at' => 1789441200 + 48 * 3600,
            'url_token' => 'tok_abc', 'number' => 'IT-20260915-0001'];
        $config = ['store_name' => '이니 상점', 'support_phone' => '1588-4954'];
        $variables = Notifier::variables($request, $config, ['상점명', '구매자명', '요청일', '상품명', '금액', '결제기한', '고객센터', '결제토큰', '주문번호']);
        self::assertSame(['상점명' => '이니 상점', '구매자명' => '김이니', '요청일' => '9월 15일', '상품명' => '크림', '금액' => '15,800',
            '결제기한' => '2026년 09월 17일 12:00', '고객센터' => '1588-4954', '결제토큰' => 'tok_abc', '주문번호' => 'IT-20260915-0001'], $variables);
        self::assertSame(['상점명' => '이니 상점'], Notifier::variables($request, $config, ['상점명']));
        self::assertSame('상점 문의', Notifier::variables($request, ['store_name' => 'x', 'support_phone' => ''], ['고객센터'])['고객센터']);
        $this->expectException(DomainError::class);
        Notifier::variables($request, $config, ['없는변수']);
    }

    #[DataProvider('connectionProvider')]
    public function testSendResendExtendAndGuards(array $config): void
    {
        $this->setupApp($config);
        $notifier = $this->app->initalk()->notifier;
        $request = $this->request();
        $result = $notifier->send($request['id'], '운영자');
        self::assertSame('accepted', $result['submission']);
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('01023457891', $body['to']);
        self::assertStringContainsString('[이니 상점] 김이니님, 9월 15일 요청하신', $body['content']['at']['message']);
        self::assertStringContainsString('15,800원', $body['content']['at']['message']);
        self::assertSame('https://shop.example.test/pay/' . $request['url_token'], $body['content']['at']['button'][0]['url_mobile']);
        $after = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::WAITING, $after['status']);
        self::assertSame(1, $after['dispatch_count']);
        self::assertSame($result['id'], $after['last_dispatch_id']);
        // 재발송은 새 멱등키로 두 번째 발송을 만든다.
        $second = $notifier->send($request['id'], '운영자');
        self::assertNotSame($result['id'], $second['id']);
        self::assertSame(2, $this->http->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->find($request['id'])['dispatch_count']);
        // 만료 건은 기한을 연장한 뒤 보낸다.
        Clock::freeze('2026-09-18 00:00:00');
        self::assertSame(1, $this->app->initalk()->requests->expire());
        $third = $notifier->send($request['id'], '운영자');
        self::assertSame('accepted', $third['submission']);
        $extended = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::WAITING, $extended['status']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $extended['expires_at']);
        // 취소 건·발송 정지·템플릿 없음은 거부한다.
        $cancelled = $this->request();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        try { $notifier->send($cancelled['id'], '운영자'); self::fail('cancelled'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $this->app->messaging()->settings->setEnabled('test', false);
        try { $notifier->send($request['id'], '운영자'); self::fail('stopped'); } catch (DomainError $e) { self::assertArrayHasKey('messaging', $e->details()); }
        $this->app->messaging()->settings->setEnabled('test', true);
        $this->app->cms()->saveSettings(['initalk.template.test' => '']);
        try { $notifier->send($request['id'], '운영자'); self::fail('no template'); } catch (DomainError $e) { self::assertArrayHasKey('template', $e->details()); }
        self::assertSame(3, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testSendManyReportsPerRequestResults(array $config): void
    {
        $this->setupApp($config);
        $a = $this->request();
        $b = $this->request(['phone' => '01011112222']);
        $c = $this->request();
        $this->app->initalk()->requests->cancel($c['id'], '운영자');
        $summary = $this->app->initalk()->notifier->sendMany([$a['id'], $b['id'], $c['id'], str_repeat('0', 32)], '운영자');
        self::assertSame(1, $summary['sent']);
        self::assertSame(3, $summary['failed']);
        self::assertArrayHasKey($b['id'], $summary['errors']);
        self::assertArrayHasKey($c['id'], $summary['errors']);
        self::assertStringNotContainsString('01011112222', json_encode($summary));
    }
}
```

`$b`는 테스트 환경의 지정 수신번호가 아니므로 `Dispatch`가 거부한다(발송 실패로 집계).

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Initalk/NotifierTest.php 2>&1 | tail -5
```

- [ ] **Step 3: `Notifier`**

`src/Initalk/Notifier.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/** 결제 요청을 알림톡 변수로 바꿔 MessagingService로 보낸다. 업무 저장이 끝난 뒤 호출한다. */
final class Notifier
{
    public function __construct(private App $app, private Settings $settings, private Requests $requests)
    {
    }

    /** 템플릿이 쓰는 변수만 돌려준다. 제공하지 않는 변수를 요구하면 거부한다. */
    public static function variables(array $request, array $config, array $wanted): array
    {
        $seoul = new \DateTimeZone('Asia/Seoul');
        $support = trim((string) ($config['support_phone'] ?? ''));
        $all = [
            '상점명' => (string) $config['store_name'],
            '구매자명' => (string) $request['buyer_name'],
            '요청일' => (new \DateTimeImmutable('@' . (int) $request['created_at']))->setTimezone($seoul)->format('n월 j일'),
            '상품명' => (string) $request['product_name'],
            '금액' => number_format((int) $request['amount']),
            '결제기한' => (new \DateTimeImmutable('@' . (int) $request['expires_at']))->setTimezone($seoul)->format('Y년 m월 d일 H:i'),
            '고객센터' => $support !== '' ? $support : '상점 문의',
            '결제토큰' => (string) $request['url_token'],
            '주문번호' => (string) $request['number'],
        ];
        $variables = [];
        foreach ($wanted as $name) {
            if (!array_key_exists($name, $all)) throw DomainError::validation(['template' => '이니톡 결제가 제공하지 않는 변수입니다: ' . $name]);
            $variables[$name] = $all[$name];
        }
        return $variables;
    }

    public function send(string $id, string $actor): array
    {
        $request = $this->requests->find($id);
        if (!Status::canSend($request['status'])) throw DomainError::validation(['status' => '이 상태에서는 알림톡을 보낼 수 없습니다.']);
        if ($request['phone'] === '') throw DomainError::validation(['phone' => '개인정보가 정리된 요청에는 발송할 수 없습니다.']);
        $config = $this->settings->read();
        $templateId = $config['template'][$request['environment']];
        if ($templateId === '') throw DomainError::validation(['template' => '이 환경의 알림톡 템플릿을 이니톡 결제 설정에서 선택해 주세요.']);
        $messaging = $this->app->messaging();
        if (empty($messaging->status($request['environment'])['enabled'])) {
            throw DomainError::validation(['messaging' => '알림톡 발송이 정지되어 있습니다. 설정 → 알림톡·문자에서 발송을 허용해 주세요.']);
        }
        if ($request['status'] === Status::EXPIRED || $request['expires_at'] <= Clock::timestamp()) {
            $request = $this->requests->extend($id, $config['expiry_hours'], $actor);
        }
        $template = $messaging->templateAction('get', ['id' => $templateId]);
        $variables = self::variables($request, $config, $template['variables']);
        $sequence = $request['dispatch_count'] + 1;
        $result = $messaging->send(['environment' => $request['environment'], 'template_id' => $templateId, 'revision' => $template['revision'],
            'idempotency_key' => 'initalk:' . $id . ':' . $sequence, 'phone' => $request['phone'], 'variables' => $variables, 'reference' => $request['number']]);
        $this->requests->recordDispatch($id, $result['id'], $sequence, (string) $result['submission'], $actor);
        return $result;
    }

    /** 통합조회의 선택 발송. 건별 오류는 메시지만 모은다(수신정보 없음). */
    public function sendMany(array $ids, string $actor): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'errors' => []];
        foreach (array_slice(array_values(array_unique(array_filter($ids, 'is_string'))), 0, 100) as $id) {
            try {
                $result = $this->send($id, $actor);
                if ($result['submission'] === 'accepted') $summary['sent']++;
                else { $summary['failed']++; $summary['errors'][$id] = '접수 실패(' . $result['submission'] . ')'; }
            } catch (DomainError $e) {
                $summary['failed']++;
                $summary['errors'][$id] = $e->status() >= 500 ? '발송 요청을 완료하지 못했습니다.' : implode(' ', $e->details() ?: [$e->getMessage()]);
            }
        }
        return $summary;
    }
}
```

`src/Initalk/Service.php`에 `public readonly Notifier $notifier;`와 생성자 안 `$this->notifier = new Notifier($app, $this->settings, $this->requests);`를 추가한다.

- [ ] **Step 4: 테스트·커밋**

```bash
php -l src/Initalk/Notifier.php && ./vendor/bin/phpunit tests/Initalk
git add src/Initalk tests/Initalk/NotifierTest.php
git commit -m "feat: send INITalk payment links through Bizppurio Alimtalk

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 4: `Ledger`·`Checkout` — 결제 시작·승인·조회·환불

**Files:**
- Create: `src/Initalk/Ledger.php`, `src/Initalk/Checkout.php`
- Modify: `src/Initalk/Service.php`
- Test: `tests/Initalk/CheckoutTest.php`

**Interfaces:**
- Consumes: `App::inicisGateway()` (`Gateway::checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device)`, `complete(array $order, array $callback)`, `fetch(array $order): array{status,valid,transaction_id,paid_at,cancelled,cancellations,open_cancellations}`, `cancel(array $order, int $amount, int $remaining, string $reason, string $key): array{id,amount,at,reason}`), `App::paymentSettings()->requireEnabled/summary`, `GnuCms\Payment\CallbackToken::create(App, array $order)`.
- Produces: `Ledger::record(string $kind, string $requestId, int $amount, int $at, string $reference): bool`, `Ledger::forRequest(string $requestId): array`, `Ledger::between(string $environment, int $from, int $until): array`(요청의 `number`,`product_name`,`buyer_name`,`phone_mask` 포함); `Checkout::order(array $request): array`, `Checkout::device(string $userAgent): string`(`mobile`|`web`), `Checkout::start(string $id, string $device, string $returnUrl, string $callbackBase): array`(게이트웨이 결제창 정보), `Checkout::complete(string $id, array $callback): void`, `Checkout::sync(string $id, string $actor): array`, `Checkout::refund(string $id, int $amount, string $reason, string $refundKey, string $actor): array`; `Service->ledger`, `Service->checkout`.

- [ ] **Step 1: 테스트를 쓴다**

`tests/Initalk/CheckoutTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Payment\FakeTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CheckoutTest extends DatabaseTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-checkout-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ic' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))],
            'app' => ['url' => 'https://shop.example.test']]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $this->merchant = Fixtures::config('inicis');
        $this->app->paymentSettings()->save('test', $this->merchant);
        $this->app->paymentSettings()->enable('test', true);
        $this->http = new FakeTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->http));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function request(): array
    {
        return $this->app->initalk()->requests->create(['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    private function callback(array $request): array
    {
        return ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $request['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
    }

    private function respond(array $body): void { $this->http->responses[] = ['status' => 200, 'body' => $body]; }

    private function approval(array $request, string $tid, int $price = 15800): array
    {
        return ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $request['id'], 'TotPrice' => (string) $price, 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON'];
    }

    private function inquiry(array $request, string $tid, string $status = 'APPROVAL', int $price = 15800, array $partials = []): array
    {
        $cancelled = array_sum(array_column($partials, 'requestPrice'));
        return ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $request['id'], 'price' => (string) $price, 'tid' => $tid,
            'transactionStatus' => $status, 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120500', 'cardInfo' => ['currencyCode' => 'WON'],
            'availablePartCancelPrice' => (string) ($price - $cancelled), 'partCancelTransInfo' => $partials, 'cancelDate' => '20260916', 'cancelTime' => '090000'];
    }

    #[DataProvider('connectionProvider')]
    public function testStartCompleteSyncAndRefunds(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $form = $checkout->start($request['id'], 'web', 'https://shop.example.test/pay/' . $request['url_token'] . '/return', 'https://shop.example.test/pay/callback');
        self::assertSame('inicis', $form['kind']);
        self::assertSame($request['id'], $form['fields']['oid']);
        self::assertSame('15800', $form['fields']['price']);
        self::assertStringStartsWith('https://shop.example.test/pay/callback?id=' . $request['id'] . '&state=', $form['fields']['returnUrl']);
        $started = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Clock::timestamp(), $started['checkout_started_at']);
        self::assertSame($this->app->paymentSettings()->summary('test')['revision'], $started['config_revision']);
        try { $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('too soon'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-15 03:00:20');
        $mobile = $checkout->start($request['id'], 'mobile', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        self::assertSame('form', $mobile['kind']);
        self::assertSame('EUC-KR', $mobile['charset']);
        self::assertSame($request['id'], $mobile['fields']['P_OID']);
        self::assertSame([], $this->http->calls);
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid));
        $checkout->complete($request['id'], $this->callback($request));
        $paid = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame($tid, $paid['transaction_id']);
        self::assertSame((new \DateTimeImmutable('2026-09-15 12:05:00', new \DateTimeZone('Asia/Seoul')))->getTimestamp(), $paid['paid_at']);
        $ledger = $this->app->initalk()->ledger->forRequest($request['id']);
        self::assertSame([['approve', 15800, $tid]], array_map(static fn (array $row): array => [$row['kind'], (int) $row['amount'], $row['reference']], $ledger));
        // 콜백 재전송은 새 승인을 만들지 않는다.
        $this->respond($this->inquiry($request, $tid));
        $checkout->complete($request['id'], $this->callback($request));
        self::assertCount(1, $this->app->initalk()->ledger->forRequest($request['id']));
        // 부분 환불
        $key = bin2hex(random_bytes(16));
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '5800', 'prtcRemains' => '10000', 'prtcTid' => $tid . 'P1']);
        $partial = $checkout->refund($request['id'], 5800, '일부 반품', $key, '운영자');
        self::assertSame(Status::PAID, $partial['status']);
        self::assertSame(5800, $partial['refunded_amount']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($request['id']));
        // 같은 키로 다시 요청하면 결제사에 다시 보내지 않고 조회로 대조한다.
        $calls = count($this->http->calls);
        $this->respond($this->inquiry($request, $tid, 'PART_CANCEL', 15800, [['tid' => $tid . 'P1', 'requestDate' => '20260916', 'requestTime' => '100000', 'requestPrice' => '5800']]));
        $again = $checkout->refund($request['id'], 5800, '일부 반품', $key, '운영자');
        self::assertSame(5800, $again['refunded_amount']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($request['id']));
        self::assertSame($calls + 1, count($this->http->calls));
        try { $checkout->refund($request['id'], 10001, '초과', bin2hex(random_bytes(16)), '운영자'); self::fail('over'); } catch (DomainError $e) { self::assertArrayHasKey('amount', $e->details()); }
        // 남은 금액 전액 환불. 총액(15,800)보다 작은 금액이므로 게이트웨이는 부분취소 API를 쓴다.
        $this->respond(['resultCode' => '00', 'prtcDate' => '20260917', 'prtcTime' => '110000', 'prtcPrice' => '10000', 'prtcRemains' => '0', 'prtcTid' => $tid . 'P2']);
        $full = $checkout->refund($request['id'], 10000, '전체 반품', bin2hex(random_bytes(16)), '운영자');
        self::assertSame(Status::REFUNDED, $full['status']);
        self::assertSame(15800, $full['refunded_amount']);
        self::assertCount(3, $this->app->initalk()->ledger->forRequest($request['id']));
        $between = $this->app->initalk()->ledger->between('test', 0, Clock::timestamp() + 86400 * 10);
        self::assertSame(3, count($between));
        self::assertSame($request['number'], $between[0]['number']);
        self::assertSame('010-****-7891', $between[0]['phone_mask']);
    }

    #[DataProvider('connectionProvider')]
    public function testMismatchedInquiryFlagsReviewInsteadOfPaying(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $request = $this->request();
        $checkout->start($request['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback');
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->respond($this->approval($request, $tid));
        $this->respond($this->inquiry($request, $tid, 'APPROVAL', 15000));
        $checkout->complete($request['id'], $this->callback($request));
        $after = $this->app->initalk()->requests->find($request['id']);
        self::assertSame(Status::CREATED, $after['status']);
        self::assertSame(1, $after['needs_review']);
        self::assertNull($after['paid_at']);
        self::assertSame([], $this->app->initalk()->ledger->forRequest($request['id']));
        self::assertSame($after, $checkout->sync($this->request()['id'], '운영자') === [] ? null : $after);
    }

    #[DataProvider('connectionProvider')]
    public function testStartRejectsClosedOrExpiredRequestsAndStoppedApi(array $config): void
    {
        $this->setupApp($config);
        $checkout = $this->app->initalk()->checkout;
        $cancelled = $this->request();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        try { $checkout->start($cancelled['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('cancelled'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $late = $this->request();
        Clock::freeze('2026-09-18 00:00:00');
        try { $checkout->start($late['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('expired'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-15 03:00:00');
        $this->app->paymentSettings()->enable('test', false);
        $open = $this->request();
        try { $checkout->start($open['id'], 'web', 'https://shop.example.test/r', 'https://shop.example.test/pay/callback'); self::fail('stopped'); } catch (DomainError $e) { self::assertSame(503, $e->status()); }
        self::assertSame('mobile', Checkout::device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'));
        self::assertSame('web', Checkout::device('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
        self::assertSame([], $this->http->calls);
    }
}
```

`testMismatchedInquiryFlagsReviewInsteadOfPaying`의 마지막 줄은 결제창을 연 적 없는 요청의 `sync()`가 게이트웨이를 호출하지 않고 요청을 그대로 돌려주는지 확인한다 — 구현 시 다음으로 바꾼다: `$fresh = $this->request(); self::assertSame($fresh['status'], $checkout->sync($fresh['id'], '운영자')['status']); self::assertCount(2, $this->http->calls);`

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Initalk/CheckoutTest.php 2>&1 | tail -5
```

- [ ] **Step 3: `Ledger`**

`src/Initalk/Ledger.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\Db\Connection;
use GnuCms\Support\Clock;

/** 결제사 조회·환불 응답으로 확인된 승인·환불만 기록하는 확정 원장. 같은 참조는 한 번만 넣는다. */
final class Ledger
{
    public function __construct(private Connection $db)
    {
    }

    public function record(string $kind, string $requestId, int $amount, int $at, string $reference): bool
    {
        $id = substr(hash('sha256', $kind . ':' . $requestId . ':' . $reference), 0, 64);
        if ($this->db->selectOne('SELECT id FROM ' . $this->db->table('initalk_ledger') . ' WHERE id = ?', [$id]) !== null) return false;
        $this->db->insert('initalk_ledger', ['id' => $id, 'request_id' => $requestId, 'kind' => $kind, 'amount' => $amount, 'at' => $at,
            'reference' => mb_substr($reference, 0, 100), 'created_at' => Clock::timestamp()]);
        return true;
    }

    public function forRequest(string $requestId): array
    {
        return $this->db->select('SELECT * FROM ' . $this->db->table('initalk_ledger') . ' WHERE request_id = ? ORDER BY at, id', [$requestId]);
    }

    /** 기간 안의 원장 행에 요청의 주문번호·상품명·구매자명·마스킹 번호를 붙인다. */
    public function between(string $environment, int $from, int $until): array
    {
        $l = $this->db->table('initalk_ledger');
        $r = $this->db->table('initalk_requests');
        return $this->db->select('SELECT l.*, r.number, r.product_name, r.buyer_name, r.phone_mask FROM ' . $l . ' l JOIN ' . $r . ' r ON r.id = l.request_id'
            . ' WHERE r.environment = ? AND l.at >= ? AND l.at <= ? ORDER BY l.at, l.id', [$environment, $from, $until]);
    }
}
```

- [ ] **Step 4: `Checkout`**

`src/Initalk/Checkout.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Payment\CallbackToken;
use GnuCms\Support\Clock;

/** 이니시스 Gateway 계약 위에서 결제 요청의 결제창·승인·조회·환불을 처리한다. */
final class Checkout
{
    public const RESTART_GRACE = 10;

    public function __construct(private App $app, private Requests $requests, private Ledger $ledger)
    {
    }

    /** Gateway가 받는 주문 배열. oid는 요청 id다. */
    public static function order(array $request): array
    {
        return ['id' => $request['id'], 'provider' => 'inicis', 'environment' => $request['environment'], 'config_revision' => $request['config_revision'],
            'total' => (int) $request['amount'], 'order_name' => $request['product_name'],
            'transaction_id' => $request['transaction_id'] !== '' ? $request['transaction_id'] : null, 'created_at' => (int) $request['created_at']];
    }

    public static function device(string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent) ? 'mobile' : 'web';
    }

    public function start(string $id, string $device, string $returnUrl, string $callbackBase): array
    {
        $request = $this->requests->find($id);
        $now = Clock::timestamp();
        if (!Status::canPay($request['status']) || $request['expires_at'] <= $now) throw DomainError::validation(['payment' => '결제할 수 없는 요청입니다.']);
        if ($request['checkout_started_at'] !== null && $now - $request['checkout_started_at'] < self::RESTART_GRACE) {
            throw DomainError::validation(['payment' => '결제창을 여는 중입니다. 잠시 후 다시 시도해 주세요.']);
        }
        $settings = $this->app->paymentSettings();
        $settings->requireEnabled($request['environment']);
        $revision = (string) $settings->summary($request['environment'])['revision'];
        $this->requests->touchCheckout($id, $revision);
        $request['config_revision'] = $revision;
        $order = self::order($request);
        $callbackUrl = $callbackBase . '?id=' . $id . '&state=' . CallbackToken::create($this->app, $order);
        return $this->app->inicisGateway()->checkout($order, ['name' => $request['buyer_name'], 'phone' => $request['phone'], 'email' => ''], $returnUrl, $callbackUrl, $device === 'mobile' ? 'mobile' : 'web');
    }

    /** 인증 결과 콜백. 승인 후 조회로 확정한다. 호출자가 ExecutionLock 안에서 부른다. */
    public function complete(string $id, array $callback): void
    {
        $request = $this->requests->find($id);
        $this->app->inicisGateway()->complete(self::order($request), $callback);
        $this->sync($id, 'customer');
    }

    /** 결제사 조회 결과를 요청과 원장에 반영한다. 결제창을 연 적 없으면 조회하지 않는다. */
    public function sync(string $id, string $actor): array
    {
        $request = $this->requests->find($id);
        if ($request['config_revision'] === '') return $request;
        $payment = $this->app->inicisGateway()->fetch(self::order($request));
        if (!in_array($payment['status'] ?? 'NOT_FOUND', ['PAID', 'PARTIAL_CANCELLED', 'CANCELLED'], true)) return $request;
        if (!($payment['valid'] ?? false) || ($request['transaction_id'] !== '' && $request['transaction_id'] !== ($payment['transaction_id'] ?? ''))) {
            if (!$request['needs_review']) $this->requests->setReview($id, true, $actor, '결제사 조회 결과가 요청의 상점·금액·거래번호와 일치하지 않습니다.');
            return $this->requests->find($id);
        }
        if ($request['paid_at'] === null) {
            $request = $this->requests->markPaid($id, (int) $payment['paid_at'], (string) $payment['transaction_id'], $actor);
            $this->ledger->record('approve', $id, $request['amount'], (int) $payment['paid_at'], (string) $payment['transaction_id']);
        }
        foreach ($payment['cancellations'] as $cancel) $this->ledger->record('refund', $id, (int) $cancel['amount'], (int) $cancel['at'], (string) $cancel['id']);
        $cancelled = (int) ($payment['cancelled'] ?? 0);
        if ($cancelled > $request['refunded_amount'] && Status::canRefund($request['status'])) {
            $request = $this->requests->applyRefund($id, $cancelled, $actor, '결제사 조회로 확인한 취소 반영');
        }
        if (($payment['open_cancellations'] ?? 0) > 0 && !$request['needs_review']) $this->requests->setReview($id, true, $actor, '보류 중인 환불 요청이 있습니다.');
        return $this->requests->find($id);
    }

    public function refund(string $id, int $amount, string $reason, string $refundKey, string $actor): array
    {
        $request = $this->requests->find($id);
        if (!Status::canRefund($request['status'])) throw DomainError::validation(['status' => '결제 완료 상태에서만 환불할 수 있습니다.']);
        $remaining = $request['amount'] - $request['refunded_amount'];
        if ($amount < 1 || $amount > $remaining) throw DomainError::validation(['amount' => '환불 금액은 1원 이상 남은 금액(' . number_format($remaining) . '원) 이하여야 합니다.']);
        if (!preg_match('/^[a-f0-9]{32}$/D', $refundKey)) throw DomainError::validation(['refund_key' => '환불 요청 키를 확인해 주세요. 화면을 새로 연 뒤 다시 시도해 주세요.']);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 80 || preg_match('/[\r\n]/', $reason)) throw DomainError::validation(['reason' => '환불 사유를 1~80자로 입력해 주세요.']);
        $result = $this->app->inicisGateway()->cancel(self::order($request), $amount, $remaining, $reason, 'initalk-' . $id . '-' . $refundKey);
        if (!$this->ledger->record('refund', $id, (int) $result['amount'], (int) $result['at'], (string) $result['id'])) {
            // 같은 요청 키의 재제출: 결제사는 저장된 결과를 돌려준다. 조회로 대조만 한다.
            return $this->sync($id, $actor);
        }
        return $this->requests->applyRefund($id, $request['refunded_amount'] + (int) $result['amount'], $actor, '환불 ' . number_format((int) $result['amount']) . '원: ' . $reason);
    }
}
```

`src/Initalk/Service.php`에 `public readonly Ledger $ledger; public readonly Checkout $checkout;`와 생성자 안 `$this->ledger = new Ledger($app->db()); $this->checkout = new Checkout($app, $this->requests, $this->ledger);`를 추가한다.

- [ ] **Step 5: 테스트·커밋**

```bash
for f in src/Initalk/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Initalk tests/Payment
git add src/Initalk tests/Initalk/CheckoutTest.php
git commit -m "feat: drive INICIS checkout, approval sync and refunds for INITalk requests

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 5: 관리자 화면 1 — 설정·단건 생성·상세·통합조회·액션

**Files:**
- Create: `src/Web/Controller/InitalkAdminController.php`, `templates/default/admin/initalk/_nav.php`, `templates/default/admin/initalk/requests.php`, `templates/default/admin/initalk/request_new.php`, `templates/default/admin/initalk/request.php`, `templates/default/admin/initalk/settings.php`, `www/themes/default/initalk.css`, `www/themes/default/initalk.js`
- Modify: `src/Web/Routes.php`, `templates/default/admin/_sidebar.php`
- Test: `tests/Web/InitalkAdminTest.php`

**Interfaces:**
- Consumes: `App::initalk()`(`settings`, `requests`, `events`, `ledger`, `notifier`, `checkout`), `App::messaging()->templateAction('list', ['environment' => …])`, `GnuCms\Payment\ExecutionLock::run(string $storageDir, callable)`, `Csrf::assert()`, `View::fromRequest()`.
- Produces: 라우트 `admin.initalk`(GET `/admin/initalk`, 이 작업에서는 통합조회로 303 — 9번 작업이 대시보드로 교체), `admin.initalk.requests`(GET), `admin.initalk.requests.bulk`(POST `action=send|cancel`, `ids[]`), `admin.initalk.requests.new`(GET/POST), `admin.initalk.customer`(POST JSON `{count,total,last_paid_at}`), `admin.initalk.request`(GET `/admin/initalk/requests/{id}`), `admin.initalk.request.send|cancel|sync|refund`(POST), `admin.initalk.settings`(GET/POST), `admin.initalk.purge`(POST). 컨트롤러 공개 메서드: `index`, `requests`, `bulk`, `newForm`, `create`, `customer`, `show`, `act(string $action, …)`, `settingsForm`, `saveSettings`, `purge`. 템플릿 공통 변수: `config`(설정), `status_labels`, `time`(타임스탬프 → `Y-m-d H:i` KST), `errors`, `notice`, `query`.

- [ ] **Step 1: 웹 테스트를 쓴다**

`tests/Web/InitalkAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Status;
use GnuCms\Messaging\MessagingService;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport as MessagingTransport;
use GnuCms\Tests\Payment\FakeTransport as PaymentTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class InitalkAdminTest extends WebTestCase
{
    private App $app;
    private string $root;
    private MessagingTransport $messagingHttp;
    private PaymentTransport $paymentHttp;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-admin-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ia' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']]);
        Clock::freeze('2026-09-15 03:00:00');
        $this->messagingHttp = new MessagingTransport();
        $messaging = new MessagingService($this->app, $this->messagingHttp);
        $this->app->setMessaging($messaging);
        $messaging->settings->save('test', ['account' => 'initalk-web', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01023457891']);
        $messaging->settings->setEnabled('test', true);
        $template = $messaging->templates->save('test', ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님 #{금액}원 결제기한 #{결제기한}", 'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}']]]);
        $this->app->initalk()->settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-4954', 'expiry_hours' => '48', 'environment' => 'test',
            'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '3']);
        $this->merchant = Fixtures::config('inicis');
        $this->app->paymentSettings()->save('test', $this->merchant);
        $this->app->paymentSettings()->enable('test', true);
        $this->paymentHttp = new PaymentTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->paymentHttp));
    }

    private function signIn(bool $admin): void
    {
        $id = $this->app->users()->create(($admin ? 'admin' : 'member') . '@example.com', '', $admin ? '운영자' : '일반회원', $admin);
        $this->get($this->app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function csrf(): string { return $_SESSION['csrf_token']; }

    #[DataProvider('connectionProvider')]
    public function testScreensRequireAdminAndCsrfAndCreateSendCancelFlowWorks(array $config): void
    {
        $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/initalk'), '/admin/initalk');
        $this->signIn(false);
        foreach (['/admin/initalk', '/admin/initalk/requests', '/admin/initalk/requests/new', '/admin/initalk/settings'] as $path) self::assertSame(403, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(true);
        self::assertSame(303, $this->get($this->app, '/admin/initalk')->getStatusCode());
        self::assertStringContainsString('이니톡 결제', $this->body($this->get($this->app, '/admin')));
        $list = $this->get($this->app, '/admin/initalk/requests');
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('조회된 결제 요청이 없습니다', $this->body($list));
        self::assertSame('no-store', $list->getHeaderLine('Cache-Control'));
        $form = ['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니', 'phone' => '010-2345-7891', 'amount' => '15,800', 'expiry_hours' => '', 'send_now' => '1'];
        self::assertSame(403, $this->post($this->app, '/admin/initalk/requests/new', $form)->getStatusCode());
        $invalid = $this->post($this->app, '/admin/initalk/requests/new', ['csrf_token' => $this->csrf()] + array_replace($form, ['amount' => '50']));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('금액은 100원 이상', $this->body($invalid));
        self::assertStringContainsString('value="플로럴 핸드크림 30ml"', $this->body($invalid));
        $created = $this->post($this->app, '/admin/initalk/requests/new', ['csrf_token' => $this->csrf()] + $form);
        self::assertSame(303, $created->getStatusCode());
        self::assertMatchesRegularExpression('~^/admin/initalk/requests/[a-f0-9]{32}\?created=1&sent=1$~', $created->getHeaderLine('Location'));
        self::assertSame(1, $this->messagingHttp->count('/v3/message'));
        $path = (string) parse_url($created->getHeaderLine('Location'), PHP_URL_PATH);
        $id = substr($path, -32);
        $detail = $this->body($this->get($this->app, $path, ['created' => '1', 'sent' => '1']));
        self::assertStringContainsString('IT-20260915-0001', $detail);
        self::assertStringContainsString('결제대기중', $detail);
        self::assertStringContainsString('010-2345-7891', $detail);
        self::assertStringContainsString('https://shop.example.test/pay/', $detail);
        self::assertStringContainsString('알림톡을 발송했습니다', $detail);
        $request = $this->app->initalk()->requests->find($id);
        self::assertSame(Status::WAITING, $request['status']);
        // 통합조회: 검색·카운트·마스킹
        $list = $this->body($this->get($this->app, '/admin/initalk/requests', ['phone' => '010-2345-7891']));
        self::assertStringContainsString('IT-20260915-0001', $list);
        self::assertStringContainsString('010-****-7891', $list);
        self::assertStringNotContainsString('010-2345-7891', $list);
        self::assertStringContainsString('name="ids[]"', $list);
        self::assertStringContainsString('조회된 결제 요청이 없습니다', $this->body($this->get($this->app, '/admin/initalk/requests', ['status' => 'paid'])));
        // 고객 확인 JSON
        $customer = $this->post($this->app, '/admin/initalk/customer', ['csrf_token' => $this->csrf(), 'phone' => '010-2345-7891']);
        self::assertSame('application/json; charset=utf-8', $customer->getHeaderLine('Content-Type'));
        self::assertSame(['count' => 0, 'total' => 0, 'last_paid_at' => ''], json_decode($this->body($customer), true));
        // 재발송·취소
        $resent = $this->post($this->app, $path . '/send', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $resent->getStatusCode());
        self::assertSame(2, $this->messagingHttp->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->find($id)['dispatch_count']);
        self::assertSame(403, $this->post($this->app, $path . '/cancel', [])->getStatusCode());
        self::assertSame(303, $this->post($this->app, $path . '/cancel', ['csrf_token' => $this->csrf()])->getStatusCode());
        self::assertSame(Status::CANCELLED, $this->app->initalk()->requests->find($id)['status']);
        $rejected = $this->post($this->app, $path . '/send', ['csrf_token' => $this->csrf()]);
        self::assertSame(422, $rejected->getStatusCode());
        self::assertStringContainsString('이 상태에서는 알림톡을 보낼 수 없습니다', $this->body($rejected));
        self::assertSame(404, $this->get($this->app, '/admin/initalk/requests/' . str_repeat('0', 32))->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testBulkActionsAndSettingsAndPurge(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $ids = [];
        foreach (['01023457891', '01023457891', '01011112222'] as $phone) {
            $ids[] = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => $phone, 'amount' => '50000'], 'test', 48, 1, '운영자')['id'];
        }
        $bulk = $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'send', 'ids' => $ids, 'environment' => 'test']);
        self::assertSame(303, $bulk->getStatusCode());
        parse_str((string) parse_url($bulk->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertSame(['environment' => 'test', 'sent' => '2', 'failed' => '1'], $query);
        self::assertStringContainsString('알림톡 2건을 발송했습니다', $this->body($this->get($this->app, '/admin/initalk/requests', $query)));
        $cancel = $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'cancel', 'ids' => [$ids[2]], 'environment' => 'test']);
        self::assertSame(303, $cancel->getStatusCode());
        self::assertSame(Status::CANCELLED, $this->app->initalk()->requests->find($ids[2])['status']);
        self::assertSame(303, $this->post($this->app, '/admin/initalk/requests/bulk', ['csrf_token' => $this->csrf(), 'action' => 'send', 'environment' => 'test'])->getStatusCode());
        // 설정 화면
        $settings = $this->body($this->get($this->app, '/admin/initalk/settings'));
        self::assertStringContainsString('value="이니 상점"', $settings);
        self::assertStringContainsString('결제 안내', $settings);
        $saved = $this->post($this->app, '/admin/initalk/settings', ['csrf_token' => $this->csrf(), 'store_name' => '새 상점', 'support_phone' => '', 'expiry_hours' => '24', 'environment' => 'test',
            'template_test' => $this->app->initalk()->settings->templateId('test'), 'template_live' => '', 'settlement_days' => '2']);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame('새 상점', $this->app->initalk()->settings->read()['store_name']);
        $bad = $this->post($this->app, '/admin/initalk/settings', ['csrf_token' => $this->csrf(), 'store_name' => '', 'support_phone' => '', 'expiry_hours' => '24', 'environment' => 'test', 'template_test' => '', 'template_live' => '', 'settlement_days' => '2']);
        self::assertSame(422, $bad->getStatusCode());
        self::assertStringContainsString('상점명을 1~40자로', $this->body($bad));
        // 개인정보 정리
        Clock::freeze('2026-12-20 00:00:00');
        $purged = $this->post($this->app, '/admin/initalk/purge', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $purged->getStatusCode());
        self::assertStringContainsString('purged=1', $purged->getHeaderLine('Location'));
        self::assertSame('', $this->app->initalk()->requests->find($ids[2])['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testRefundAndSyncActionsUseTheGateway(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $id = $request['id'];
        $this->app->initalk()->requests->touchCheckout($id, $this->app->paymentSettings()->summary('test')['revision']);
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->app->initalk()->requests->markPaid($id, Clock::timestamp(), $tid, 'customer');
        $this->app->initalk()->ledger->record('approve', $id, 50000, Clock::timestamp(), $tid);
        $detail = $this->body($this->get($this->app, '/admin/initalk/requests/' . $id));
        self::assertStringContainsString('결제완료', $detail);
        self::assertStringContainsString($tid, $detail);
        preg_match('/name="refund_key" value="([a-f0-9]{32})"/', $detail, $m);
        self::assertSame(403, $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['amount' => '10000', 'reason' => '일부', 'refund_key' => $m[1]])->getStatusCode());
        $this->paymentHttp->responses[] = ['status' => 200, 'body' => ['resultCode' => '00', 'prtcDate' => '20260916', 'prtcTime' => '100000', 'prtcPrice' => '10000', 'prtcRemains' => '40000', 'prtcTid' => $tid . 'P1']];
        $refunded = $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['csrf_token' => $this->csrf(), 'amount' => '10,000', 'reason' => '일부 환불', 'refund_key' => $m[1]]);
        self::assertSame(303, $refunded->getStatusCode());
        self::assertSame(10000, $this->app->initalk()->requests->find($id)['refunded_amount']);
        $detail = $this->body($this->get($this->app, '/admin/initalk/requests/' . $id, ['refunded' => '1']));
        self::assertStringContainsString('환불을 처리했습니다', $detail);
        self::assertStringContainsString('40,000', $detail);
        $over = $this->post($this->app, '/admin/initalk/requests/' . $id . '/refund', ['csrf_token' => $this->csrf(), 'amount' => '40001', 'reason' => '초과', 'refund_key' => bin2hex(random_bytes(16))]);
        self::assertSame(422, $over->getStatusCode());
        $this->paymentHttp->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $id, 'price' => '50000', 'tid' => $tid,
            'transactionStatus' => 'PART_CANCEL', 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120000', 'cardInfo' => ['currencyCode' => 'WON'],
            'availablePartCancelPrice' => '40000', 'partCancelTransInfo' => [['tid' => $tid . 'P1', 'requestDate' => '20260916', 'requestTime' => '100000', 'requestPrice' => '10000']]]];
        $synced = $this->post($this->app, '/admin/initalk/requests/' . $id . '/sync', ['csrf_token' => $this->csrf()]);
        self::assertSame(303, $synced->getStatusCode());
        self::assertSame(Status::PAID, $this->app->initalk()->requests->find($id)['status']);
        self::assertCount(2, $this->app->initalk()->ledger->forRequest($id));
    }
}
```

`testRefundAndSyncActionsUseTheGateway`의 `sync`는 게이트웨이 `fetch()`가 `journal` 상태 `pending|confirmed`일 때만 조회한다. 이 테스트는 `markPaid`로 결제를 흉내 냈으므로 `fetch()`가 `NOT_FOUND`를 돌려주고 원장은 2건(승인 + 환불) 그대로다 — 단언은 그 사실을 확인한다(조회 응답은 소비되지 않아도 된다).

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Web/InitalkAdminTest.php 2>&1 | tail -5
```

Expected: 첫 단언(404)에서 실패.

- [ ] **Step 3: 컨트롤러**

`src/Web/Controller/InitalkAdminController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Phone;
use GnuCms\Initalk\Status;
use GnuCms\Payment\ExecutionLock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/** 운영 → 이니톡 결제. 전체 관리자 전용, POST는 세션 CSRF. */
final class InitalkAdminController
{
    public function __construct(private App $app)
    {
    }

    private function guard(ServerRequestInterface $request): void
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
    }

    private function actor(): string { return (string) ($this->app->guestAcl()->identity()->displayName() ?? '관리자'); }
    private function actorId(): int { return (int) ($this->app->guestAcl()->identity()->sub() ?? 0); }

    private function input(ServerRequestInterface $request): array
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        return is_array($input) ? $input : [];
    }

    private function environment(array $input): string
    {
        return in_array($input['environment'] ?? '', ['test', 'live'], true) ? $input['environment'] : $this->app->initalk()->settings->read()['environment'];
    }

    private function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $time = static fn ($timestamp): string => $timestamp === null || (int) $timestamp === 0 ? ''
            : (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i');
        return View::fromRequest($request)->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/initalk/' . $template, $data + ['config' => $this->app->initalk()->settings->read(), 'status_labels' => Status::LABELS, 'time' => $time,
                'errors' => [], 'notice' => '', 'query' => $request->getQueryParams()]);
    }

    private function redirect(ServerRequestInterface $request, ResponseInterface $response, string $route, array $params = [], array $query = []): ResponseInterface
    {
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor($route, $params, $query);
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $url);
    }

    private function errors(DomainError $e): array
    {
        return $e->status() >= 500 ? ['작업을 완료하지 못했습니다. 결제사·알림톡 연결과 설정을 확인해 주세요.'] : array_values($e->details() ?: [$e->getMessage()]);
    }

    private function payUrl(array $request): string
    {
        return rtrim((string) $this->app->config('app.url', GNUCMS_URL), '/') . '/pay/' . $request['url_token'];
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        return $this->redirect($request, $response, 'admin.initalk.requests');
    }

    public function requests(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $service = $this->app->initalk();
        $service->requests->expire();
        $filter = $request->getQueryParams();
        $filter['environment'] = $this->environment($filter);
        if (in_array($filter['months'] ?? '', ['1', '2', '3'], true)) {
            $today = (new \DateTimeImmutable('@' . \GnuCms\Support\Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'));
            $filter['until'] = $today->format('Y-m-d');
            $filter['from'] = $today->modify('-' . $filter['months'] . ' months')->format('Y-m-d');
        }
        $result = $service->requests->search($filter, max(1, (int) ($filter['page'] ?? 1)));
        return $this->render($request, $response, 'requests', ['filter' => $filter, 'result' => $result, 'counts' => $service->requests->counts($filter['environment'])]);
    }

    public function bulk(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $environment = $this->environment($input);
        $ids = is_array($input['ids'] ?? null) ? array_values(array_filter($input['ids'], static fn ($id): bool => is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1)) : [];
        if ($ids === []) return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'none' => '1']);
        $service = $this->app->initalk();
        $action = $input['action'] ?? '';
        if ($action === 'send') {
            $summary = ExecutionLock::run($this->app->storageDir(), fn (): array => $service->notifier->sendMany($ids, $this->actor()));
            return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'sent' => $summary['sent'], 'failed' => $summary['failed']]);
        }
        if ($action === 'cancel') {
            $done = 0;
            $failed = 0;
            foreach ($ids as $id) {
                try { $service->requests->cancel($id, $this->actor()); $done++; } catch (DomainError $e) { $failed++; }
            }
            return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'cancelled' => $done, 'failed' => $failed]);
        }
        throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
    }

    public function newForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        return $this->render($request, $response, 'request_new', ['values' => []]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $service = $this->app->initalk();
        $config = $service->settings->read();
        try {
            $created = $service->requests->create($input, $config['environment'], $config['expiry_hours'], $this->actorId(), $this->actor());
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status()), 'request_new', ['values' => $input, 'errors' => $this->errors($e)]);
        }
        $query = ['created' => '1'];
        if (($input['send_now'] ?? '') === '1') {
            try {
                $result = ExecutionLock::run($this->app->storageDir(), fn (): array => $service->notifier->send($created['id'], $this->actor()));
                $query['sent'] = $result['submission'] === 'accepted' ? '1' : '0';
            } catch (DomainError $e) {
                $query['send_error'] = '1';
            }
        }
        return $this->redirect($request, $response, 'admin.initalk.request', ['id' => $created['id']], $query);
    }

    /** 휴대폰 번호의 결제 이력 요약(JSON). 번호 자체는 돌려주지 않는다. */
    public function customer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $summary = ['count' => 0, 'total' => 0, 'last_paid_at' => ''];
        try {
            $found = $this->app->initalk()->requests->customerSummary(Phone::normalize($input['phone'] ?? null), $this->app->initalk()->settings->read()['environment']);
            $summary = ['count' => $found['count'], 'total' => $found['total'],
                'last_paid_at' => $found['last_paid_at'] === null ? '' : (new \DateTimeImmutable('@' . $found['last_paid_at']))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d')];
        } catch (DomainError $e) {
            // 형식이 틀린 번호는 이력 없음으로 답한다.
        }
        $response->getBody()->write(json_encode($summary, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'no-store')->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->guard($request);
        return $this->detail($request, $response, (string) $args['id'], []);
    }

    private function detail(ServerRequestInterface $request, ResponseInterface $response, string $id, array $errors): ResponseInterface
    {
        $service = $this->app->initalk();
        $found = $service->requests->find($id);
        if (Status::canPay($found['status']) && $found['expires_at'] <= \GnuCms\Support\Clock::timestamp()) {
            $service->requests->expireOne($id);
            $found = $service->requests->find($id);
        }
        return $this->render($request, $response, 'request', ['request' => $found, 'events' => $service->events->forRequest($id), 'ledger' => $service->ledger->forRequest($id),
            'refund_key' => bin2hex(random_bytes(16)), 'pay_url' => $this->payUrl($found), 'errors' => $errors]);
    }

    /** @param 'send'|'cancel'|'sync'|'refund' $action */
    public function act(string $action, ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->guard($request);
        $id = (string) $args['id'];
        $service = $this->app->initalk();
        $service->requests->find($id);
        $input = $this->input($request);
        try {
            $query = ExecutionLock::run($this->app->storageDir(), function () use ($service, $action, $id, $input): array {
                switch ($action) {
                    case 'send':
                        $result = $service->notifier->send($id, $this->actor());
                        return ['sent' => $result['submission'] === 'accepted' ? '1' : '0'];
                    case 'cancel':
                        $service->requests->cancel($id, $this->actor());
                        return ['cancelled' => '1'];
                    case 'sync':
                        $service->checkout->sync($id, $this->actor());
                        return ['synced' => '1'];
                    case 'refund':
                        $amount = preg_replace('/[^0-9]/', '', is_scalar($input['amount'] ?? null) ? (string) $input['amount'] : '');
                        $service->checkout->refund($id, $amount === '' ? 0 : (int) $amount, is_string($input['reason'] ?? null) ? $input['reason'] : '',
                            is_string($input['refund_key'] ?? null) ? $input['refund_key'] : '', $this->actor());
                        return ['refunded' => '1'];
                }
                throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
            });
        } catch (DomainError $e) {
            if ($e->status() === 404) throw $e;
            return $this->detail($request, $response->withStatus($e->status()), $id, $this->errors($e));
        }
        return $this->redirect($request, $response, 'admin.initalk.request', ['id' => $id], $query);
    }

    public function settingsForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $config = $this->app->initalk()->settings->read();
        return $this->render($request, $response, 'settings', ['values' => $this->settingsValues($config), 'templates' => $this->templateOptions()]);
    }

    public function saveSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        try {
            $this->app->initalk()->settings->save($input);
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status()), 'settings', ['values' => $input, 'templates' => $this->templateOptions(), 'errors' => $this->errors($e)]);
        }
        return $this->redirect($request, $response, 'admin.initalk.settings', [], ['saved' => '1']);
    }

    public function purge(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $count = $this->app->initalk()->requests->purge();
        return $this->redirect($request, $response, 'admin.initalk.settings', [], ['purged' => (string) $count]);
    }

    private function settingsValues(array $config): array
    {
        return ['store_name' => $config['store_name'], 'support_phone' => $config['support_phone'], 'expiry_hours' => (string) $config['expiry_hours'],
            'environment' => $config['environment'], 'template_test' => $config['template']['test'], 'template_live' => $config['template']['live'],
            'settlement_days' => (string) $config['settlement_days']];
    }

    /** @return array{test:list<array>,live:list<array>} */
    private function templateOptions(): array
    {
        $options = ['test' => [], 'live' => []];
        foreach (['test', 'live'] as $environment) {
            try {
                foreach ($this->app->messaging()->templateAction('list', ['environment' => $environment]) as $template) {
                    if ($template['enabled']) $options[$environment][] = ['id' => $template['id'], 'name' => $template['name'], 'code' => $template['code']];
                }
            } catch (\Throwable $e) {
                // 알림톡 설정 전에는 목록이 비어 있다.
            }
        }
        return $options;
    }
}
```

- [ ] **Step 4: 템플릿**

모든 `admin/initalk/*.php` 화면은 같은 머리 블록으로 시작한다(아래 `HEAD` 블록을 각 파일 첫 줄에 그대로 둔다; `title`의 화면 이름만 바꾼다):

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
```

`templates/default/admin/initalk/_nav.php` (머리글·탭; `$active`를 받는다. 8·9번 작업이 탭을 추가한다):

```php
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li aria-current="page">이니톡 결제</li></ul></div>
<div class="page-head"><div><h1>이니톡 결제</h1><p class="muted">알림톡으로 결제 링크를 보내고 카드결제 현황을 관리합니다. 현재 환경: <strong><?= $config['environment'] === 'live' ? '운영' : '테스트' ?></strong></p></div>
<div class="row-actions"><a class="btn btn-sm btn-primary" href="<?= $this->url('admin.initalk.requests.new') ?>"><?= $this->icon('plus', 14) ?> 결제 생성</a></div></div>
<?php $tabs = [['requests', 'admin.initalk.requests', '통합조회'], ['new', 'admin.initalk.requests.new', '결제 생성'], ['settings', 'admin.initalk.settings', '설정']]; ?>
<nav class="tabs tabs-border settings-tabs" aria-label="이니톡 결제 메뉴"><?php foreach ($tabs as [$key, $route, $label]): ?><a class="tab<?= $active === $key ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
```

`templates/default/admin/initalk/requests.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>통합조회 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php
$notice = match (true) {
    isset($query['sent']) => '알림톡 ' . (int) $query['sent'] . '건을 발송했습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 실패 ' . (int) $query['failed'] . '건은 상세에서 확인해 주세요.' : ''),
    isset($query['cancelled']) => (int) $query['cancelled'] . '건을 결제 전 취소했습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 취소할 수 없는 ' . (int) $query['failed'] . '건은 건너뛰었습니다.' : ''),
    isset($query['none']) => '선택한 결제 요청이 없습니다.',
    default => '',
};
$value = static fn (string $key): string => is_string($filter[$key] ?? null) ? $filter[$key] : '';
?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'requests', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<div class="stats stats-grid initalk-counts"><?php foreach ($status_labels as $key => $label): if ($key === 'refunded') continue; ?><div class="stat"><div class="stat-title"><?= $this->e($label) ?></div><div class="stat-value"><?= (int) $counts[$key] ?></div></div><?php endforeach ?></div>
<section class="card card-body extension-panel"><h2 class="card-title">검색</h2>
<form method="get" action="<?= $this->url('admin.initalk.requests') ?>" class="initalk-filters">
<label class="extension-label">환경<select class="select select-bordered select-block" name="environment"><option value="test"<?= $filter['environment'] === 'test' ? ' selected' : '' ?>>테스트</option><option value="live"<?= $filter['environment'] === 'live' ? ' selected' : '' ?>>운영</option></select></label>
<label class="extension-label">거래등록일 시작<input class="input input-bordered input-block" type="date" name="from" value="<?= $this->e($value('from')) ?>"></label>
<label class="extension-label">거래등록일 끝<input class="input input-bordered input-block" type="date" name="until" value="<?= $this->e($value('until')) ?>"></label>
<label class="extension-label">휴대폰번호<input class="input input-bordered input-block" type="tel" name="phone" data-phone-format="mobile" value="<?= $this->e($value('phone')) ?>" placeholder="- 없이 숫자만"></label>
<label class="extension-label">구매자명<input class="input input-bordered input-block" name="buyer_name" value="<?= $this->e($value('buyer_name')) ?>"></label>
<label class="extension-label">상품명<input class="input input-bordered input-block" name="product_name" value="<?= $this->e($value('product_name')) ?>"></label>
<label class="extension-label">주문번호<input class="input input-bordered input-block" name="number" value="<?= $this->e($value('number')) ?>" placeholder="IT-"></label>
<label class="extension-label">금액<input class="input input-bordered input-block" name="amount" inputmode="numeric" value="<?= $this->e($value('amount')) ?>"></label>
<label class="extension-label">결제상태<select class="select select-bordered select-block" name="status"><option value="">전체</option><?php foreach ($status_labels as $key => $label): ?><option value="<?= $this->e($key) ?>"<?= $value('status') === $key ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach ?></select></label>
<fieldset class="extension-label"><legend>알림톡</legend><label><input type="radio" name="sendable" value=""<?= $value('sendable') === '' ? ' checked' : '' ?>> 전체</label> <label><input type="radio" name="sendable" value="1"<?= $value('sendable') === '1' ? ' checked' : '' ?>> 발송 가능</label> <label><input type="radio" name="sendable" value="0"<?= $value('sendable') === '0' ? ' checked' : '' ?>> 발송 불가</label></fieldset>
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">조회</button><?php foreach (['1' => '1개월', '2' => '2개월', '3' => '3개월'] as $months => $label): ?><button class="btn btn-outline btn-sm" type="submit" name="months" value="<?= $months ?>"><?= $label ?></button><?php endforeach ?><a class="btn btn-ghost btn-sm" href="<?= $this->url('admin.initalk.requests') ?>">조건 초기화</a></div>
</form></section>
<section class="card card-body extension-panel"><h2 class="card-title">결제 요청 <small><?= (int) $result['total'] ?>건</small></h2>
<form method="post" action="<?= $this->url('admin.initalk.requests.bulk') ?>" data-initalk-bulk>
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($filter['environment']) ?>">
<div class="card-actions"><button class="btn btn-primary btn-sm" name="action" value="send">선택 건 알림톡 발송</button><button class="btn btn-outline btn-sm" name="action" value="cancel">선택 건 결제전 취소</button></div>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th><input type="checkbox" data-initalk-check-all aria-label="전체 선택"></th><th>주문번호</th><th>구매자</th><th>휴대폰</th><th>상품명</th><th class="num">금액</th><th>알림톡</th><th>상태</th><th>등록</th><th>상태 변경</th></tr></thead><tbody>
<?php foreach ($result['items'] as $item): ?><tr>
<td><input type="checkbox" name="ids[]" value="<?= $this->e($item['id']) ?>" aria-label="선택"></td>
<td><a class="link" href="<?= $this->url('admin.initalk.request', ['id' => $item['id']]) ?>"><?= $this->e($item['number']) ?></a></td>
<td><?= $this->e($item['buyer_name'] !== '' ? $item['buyer_name'] : '보관 만료') ?></td>
<td><?= $this->e($item['phone_mask'] !== '' ? $item['phone_mask'] : '보관 만료') ?></td>
<td><?= $this->e($item['product_name']) ?></td>
<td class="num"><?= number_format($item['amount']) ?>원</td>
<td><?= $item['dispatch_count'] > 0 ? $item['dispatch_count'] . '회' : '미발송' ?></td>
<td><span class="badge badge-soft initalk-status-<?= $this->e($item['status']) ?>"><?= $this->e($item['status_label']) ?></span><?php if ($item['needs_review']): ?> <span class="badge badge-warning">확인 필요</span><?php endif ?></td>
<td><?= $this->e($time($item['created_at'])) ?></td>
<td><?= $this->e($time($item['status_changed_at'])) ?></td>
</tr><?php endforeach ?>
<?php if ($result['items'] === []): ?><tr><td colspan="10">조회된 결제 요청이 없습니다.</td></tr><?php endif ?>
</tbody></table></div></form>
<?php $pageQuery = array_filter($filter, static fn ($v, $k): bool => is_string($v) && $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH); $pages = (int) ceil($result['total'] / $result['per_page']); ?>
<nav class="initalk-pager"><?php if ($result['page'] > 1): ?><a class="link" href="<?= $this->url('admin.initalk.requests', [], $pageQuery + ['page' => $result['page'] - 1]) ?>">이전</a><?php endif ?> <?= $result['page'] ?> / <?= max(1, $pages) ?> <?php if ($result['page'] < $pages): ?><a class="link" href="<?= $this->url('admin.initalk.requests', [], $pageQuery + ['page' => $result['page'] + 1]) ?>">다음</a><?php endif ?></nav>
</section>
<p class="muted">목록의 번호는 마스킹됩니다. 전체 번호는 상세에서 전체 관리자만 볼 수 있습니다.</p>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><?php $this->insert('admin/_phone_input') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
```

`templates/default/admin/initalk/request_new.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>결제 생성 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $v = static fn (string $key, string $default = ''): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : $default; ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'new', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<section class="card card-body extension-panel"><h2 class="card-title">결제 생성 <small><?= $config['environment'] === 'live' ? '운영' : '테스트' ?> 환경</small></h2>
<p>결제 URL이 포함된 알림톡을 고객 휴대전화로 전송합니다. 알림톡의 링크는 결제기한(기본 <?= (int) $config['expiry_hours'] ?>시간) 동안 유효합니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.requests.new') ?>" autocomplete="off" data-initalk-new>
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="product_name">상품명 (최대 30자)</label><input class="input input-bordered input-block" id="product_name" name="product_name" maxlength="30" required value="<?= $this->e($v('product_name')) ?>">
<label class="extension-label" for="product_detail">상품 상세 (선택, 최대 150자)</label><textarea class="textarea textarea-bordered textarea-block" id="product_detail" name="product_detail" maxlength="150" rows="3"><?= $this->e($v('product_detail')) ?></textarea>
<label class="extension-label" for="buyer_name">구매자명</label><input class="input input-bordered input-block" id="buyer_name" name="buyer_name" maxlength="30" required value="<?= $this->e($v('buyer_name')) ?>">
<label class="extension-label" for="phone">휴대폰번호</label><input class="input input-bordered input-block" type="tel" id="phone" name="phone" data-phone-format="mobile" inputmode="tel" maxlength="20" required placeholder="010-1234-5678" value="<?= $this->e($v('phone')) ?>" data-customer-url="<?= $this->url('admin.initalk.customer') ?>">
<p id="initalk-customer" class="muted" aria-live="polite"></p>
<label class="extension-label" for="amount">금액 (원)</label><input class="input input-bordered input-block" id="amount" name="amount" inputmode="numeric" maxlength="12" required value="<?= $this->e($v('amount')) ?>">
<p id="initalk-amount-words" class="muted" aria-live="polite"></p>
<label class="extension-label" for="expiry_hours">결제기한 (시간, 비우면 기본 <?= (int) $config['expiry_hours'] ?>시간)</label><input class="input input-bordered input-block" id="expiry_hours" name="expiry_hours" inputmode="numeric" maxlength="3" value="<?= $this->e($v('expiry_hours')) ?>">
<label class="extension-label"><input class="checkbox" type="checkbox" name="send_now" value="1"<?= $values === [] || $v('send_now') === '1' ? ' checked' : '' ?>> 결제 알림톡 즉시 전송하기</label>
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">거래등록</button></div>
</form></section>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><?php $this->insert('admin/_phone_input') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
```

`templates/default/admin/initalk/request.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>결제 요청 상세 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php
$notice = match (true) {
    isset($query['created']) && isset($query['sent']) && $query['sent'] === '1' => '결제 요청을 만들고 알림톡을 발송했습니다.',
    isset($query['created']) && isset($query['send_error']) => '결제 요청을 만들었지만 알림톡 발송에 실패했습니다. 아래에서 다시 보낼 수 있습니다.',
    isset($query['created']) => '결제 요청을 만들었습니다.',
    isset($query['sent']) => $query['sent'] === '1' ? '알림톡을 발송했습니다.' : '알림톡 접수에 실패했습니다. 발송 상세를 확인해 주세요.',
    isset($query['cancelled']) => '결제 전 취소했습니다.',
    isset($query['synced']) => '결제사 조회 결과를 반영했습니다.',
    isset($query['refunded']) => '환불을 처리했습니다.',
    default => '',
};
$r = $request; $remaining = $r['amount'] - $r['refunded_amount'];
?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'requests', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<?php if ($r['needs_review']): ?><p class="alert alert-warning" role="alert">결제사 조회 결과와 요청이 일치하지 않거나 보류 중인 환불이 있습니다. 결제 상태 조회로 대조해 주세요.</p><?php endif ?>
<div class="cols initalk-cols">
<section class="card card-body extension-panel"><h2 class="card-title"><?= $this->e($r['number']) ?> <span class="badge badge-soft initalk-status-<?= $this->e($r['status']) ?>"><?= $this->e($r['status_label']) ?></span></h2>
<dl class="initalk-dl">
<dt>구매자명</dt><dd><?= $this->e($r['buyer_name'] !== '' ? $r['buyer_name'] : '보관 만료') ?></dd>
<dt>휴대폰번호</dt><dd><?= $this->e($r['phone'] !== '' ? \GnuCms\Initalk\Phone::format($r['phone']) : '보관 만료') ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?></dd>
<dt>상품 상세</dt><dd><?= $this->e($r['product_detail']) ?></dd>
<dt>금액</dt><dd><?= number_format($r['amount']) ?>원<?php if ($r['refunded_amount'] > 0): ?> (환불 <?= number_format($r['refunded_amount']) ?>원 · 남은 금액 <?= number_format($remaining) ?>원)<?php endif ?></dd>
<dt>결제기한</dt><dd><?= $this->e($time($r['expires_at'])) ?></dd>
<dt>환경</dt><dd><?= $r['environment'] === 'live' ? '운영' : '테스트' ?></dd>
<dt>거래 등록</dt><dd><?= $this->e($time($r['created_at'])) ?></dd>
<dt>최종 상태 변경</dt><dd><?= $this->e($time($r['status_changed_at'])) ?></dd>
<dt>알림톡</dt><dd><?= $r['dispatch_count'] > 0 ? $r['dispatch_count'] . '회 발송 · 마지막 ' . $this->e($time($r['last_dispatched_at'])) : '미발송' ?><?php if ($r['last_dispatch_id'] !== null): ?> · <a class="link" href="<?= $this->url('admin.messaging.detail', ['id' => $r['last_dispatch_id']], ['environment' => $r['environment']]) ?>">발송 상세</a><?php endif ?></dd>
<dt>결제일시</dt><dd><?= $this->e($time($r['paid_at'])) ?></dd>
<dt>TID</dt><dd><?= $this->e($r['transaction_id']) ?></dd>
</dl>
<label class="extension-label" for="initalk-pay-url">결제 링크</label><div class="initalk-link"><input class="input input-bordered input-block" id="initalk-pay-url" readonly value="<?= $this->e($pay_url) ?>"><button type="button" class="btn btn-sm" data-copy="initalk-pay-url">복사</button></div>
</section>
<section class="card card-body extension-panel"><h2 class="card-title">작업</h2>
<?php if (\GnuCms\Initalk\Status::canSend($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.send', ['id' => $r['id']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-primary"><?= $r['status'] === 'expired' ? '기한 연장 후 알림톡 발송' : ($r['dispatch_count'] > 0 ? '알림톡 재발송' : '알림톡 발송') ?></button></form><?php endif ?>
<?php if (\GnuCms\Initalk\Status::canCancel($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.cancel', ['id' => $r['id']]) ?>" data-confirm="cancel"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">결제전 취소</button></form><?php endif ?>
<?php if ($r['config_revision'] !== ''): ?><form method="post" action="<?= $this->url('admin.initalk.request.sync', ['id' => $r['id']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">결제 상태 조회</button></form><?php endif ?>
<?php if (\GnuCms\Initalk\Status::canRefund($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.refund', ['id' => $r['id']]) ?>" data-confirm="refund"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="refund_key" value="<?= $this->e($refund_key) ?>">
<label class="extension-label" for="refund-amount">환불 금액 (남은 금액 <?= number_format($remaining) ?>원)</label><input class="input input-bordered input-block" id="refund-amount" name="amount" inputmode="numeric" required value="<?= $remaining ?>">
<label class="extension-label" for="refund-reason">환불 사유</label><input class="input input-bordered input-block" id="refund-reason" name="reason" maxlength="80" required>
<button class="btn btn-error">환불</button></form><?php endif ?>
</section>
</div>
<section class="card card-body extension-panel"><h2 class="card-title">확정 원장</h2><div class="table-wrap"><table class="table"><thead><tr><th>일시</th><th>구분</th><th class="num">금액</th><th>거래·취소 ID</th></tr></thead><tbody>
<?php foreach ($ledger as $row): ?><tr><td><?= $this->e($time($row['at'])) ?></td><td><?= $row['kind'] === 'approve' ? '승인' : '환불' ?></td><td class="num"><?= number_format((int) $row['amount']) ?>원</td><td><?= $this->e($row['reference']) ?></td></tr><?php endforeach ?>
<?php if ($ledger === []): ?><tr><td colspan="4">확정된 결제가 없습니다.</td></tr><?php endif ?></tbody></table></div></section>
<section class="card card-body extension-panel"><h2 class="card-title">이력</h2><ol class="initalk-timeline"><?php foreach ($events as $event): ?><li><small><?= $this->e($time($event['created_at'])) ?></small> <strong><?= $this->e($event['type']) ?></strong> <?= $this->e($event['note']) ?> <small>(<?= $this->e($event['actor']) ?>)</small></li><?php endforeach ?></ol></section>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
```

`templates/default/admin/initalk/settings.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>설정 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $notice = isset($query['saved']) ? '설정을 저장했습니다.' : (isset($query['purged']) ? '보관 기간이 지난 ' . (int) $query['purged'] . '건의 구매자명·번호를 정리했습니다.' : '');
$v = static fn (string $key): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : ''; ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'settings', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<section class="card card-body extension-panel"><h2 class="card-title">이니톡 결제 설정</h2>
<p>알림톡 계정은 <a class="link" href="<?= $this->url('admin.settings.messaging') ?>">설정 → 알림톡·문자</a>, 이니시스 상점은 <a class="link" href="<?= $this->url('admin.settings.payment') ?>">설정 → 결제</a>에서 먼저 준비합니다. 템플릿은 <a class="link" href="<?= $this->url('admin.messaging.templates') ?>">메시지 발송 → 템플릿</a>에서 가져온 것 중 고릅니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.settings') ?>" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="store_name">상점명 (알림톡·결제 페이지 표시)</label><input class="input input-bordered input-block" id="store_name" name="store_name" maxlength="40" required value="<?= $this->e($v('store_name')) ?>">
<label class="extension-label" for="support_phone">고객센터 번호 (선택)</label><input class="input input-bordered input-block" id="support_phone" name="support_phone" maxlength="20" value="<?= $this->e($v('support_phone')) ?>" placeholder="1588-1234">
<label class="extension-label" for="expiry_hours">기본 결제기한 (시간, 1~720)</label><input class="input input-bordered input-block" id="expiry_hours" name="expiry_hours" inputmode="numeric" maxlength="3" required value="<?= $this->e($v('expiry_hours')) ?>">
<label class="extension-label" for="environment">사용 환경</label><select class="select select-bordered select-block" id="environment" name="environment"><option value="test"<?= $v('environment') === 'test' ? ' selected' : '' ?>>테스트 (알림톡 테스트 계정 · 이니시스 테스트 상점)</option><option value="live"<?= $v('environment') === 'live' ? ' selected' : '' ?>>운영</option></select>
<?php foreach (['test' => '테스트', 'live' => '운영'] as $env => $label): ?>
<label class="extension-label" for="template_<?= $env ?>"><?= $label ?> 환경 알림톡 템플릿</label><select class="select select-bordered select-block" id="template_<?= $env ?>" name="template_<?= $env ?>"><option value="">선택 안 함</option><?php foreach ($templates[$env] as $option): ?><option value="<?= $this->e($option['id']) ?>"<?= $v('template_' . $env) === $option['id'] ? ' selected' : '' ?>><?= $this->e($option['name']) ?> (<?= $this->e($option['code']) ?>)</option><?php endforeach ?></select>
<?php endforeach ?>
<p><small>템플릿 본문에는 #{상점명} #{구매자명} #{요청일} #{상품명} #{금액} #{결제기한} #{고객센터} #{주문번호} 변수를, 웹링크 버튼에는 결제 링크 <?= $this->e(rtrim((string) $site_url, '/')) ?>/pay/#{결제토큰}을 쓸 수 있습니다.</small></p>
<label class="extension-label" for="settlement_days">정산 주기 (승인일 + N일, 0~60)</label><input class="input input-bordered input-block" id="settlement_days" name="settlement_days" inputmode="numeric" maxlength="2" required value="<?= $this->e($v('settlement_days')) ?>">
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">설정 저장</button></div></form></section>
<section class="card card-body extension-panel"><h2 class="card-title">보관 만료 개인정보 정리</h2><p>결제 완료·환불·만료·취소된 지 90일이 지난 요청의 구매자명과 휴대폰번호를 최대 100건씩 지웁니다. 주문번호·금액·원장은 남습니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.purge') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">개인정보 정리 실행</button></form></section>
<?php $this->stop() ?>
```

- [ ] **Step 5: 자산·라우트·사이드바**

`www/themes/default/initalk.css`:

```css
/* 이니톡 결제 관리자 화면. 관리 콘솔 컴포넌트 위에 배치만 보탠다. */
.initalk-admin .initalk-counts{margin-bottom:1.25rem}
.initalk-admin .initalk-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.25rem 1rem}
.initalk-admin .initalk-filters .form-actions{grid-column:1/-1;flex-wrap:wrap}
.initalk-admin .initalk-table .num{text-align:right;white-space:nowrap}
.initalk-admin .initalk-cols{display:grid;grid-template-columns:2fr 1fr;gap:1rem;align-items:start}
@media (max-width:900px){.initalk-admin .initalk-cols{grid-template-columns:1fr}}
.initalk-admin .initalk-dl{display:grid;grid-template-columns:8rem 1fr;gap:.35rem .75rem;margin:0}
.initalk-admin .initalk-dl dt{color:var(--bc-soft);font-size:13px}
.initalk-admin .initalk-dl dd{margin:0;overflow-wrap:anywhere}
.initalk-admin .initalk-link{display:flex;gap:.5rem;align-items:center}
.initalk-admin .initalk-timeline{padding-left:1.25rem}
.initalk-admin .initalk-pager{display:flex;gap:1rem;justify-content:center;margin-top:1rem}
.initalk-admin .initalk-status-paid{color:var(--color-success)}
.initalk-admin .initalk-status-expired,.initalk-admin .initalk-status-cancelled{color:var(--bc-soft)}
.initalk-admin .initalk-status-refunded{color:var(--color-error)}
.initalk-admin .card-body form + form{margin-top:.75rem}
```

`www/themes/default/initalk.js` (데이터를 스크립트에 끼워 넣지 않고 DOM 속성만 읽는다):

```js
(() => {
  'use strict';
  const units = ['', '만', '억'];
  const digits = ['', '일', '이', '삼', '사', '오', '육', '칠', '팔', '구'];
  const smallUnits = ['', '십', '백', '천'];
  function koreanWords(value) {
    const n = Number(String(value).replace(/[^0-9]/g, ''));
    if (!n) return '';
    let result = '';
    let group = 0;
    let rest = n;
    while (rest > 0) {
      const part = rest % 10000;
      if (part > 0) {
        let text = '';
        String(part).split('').reverse().forEach((d, i) => { if (d !== '0') text = (d === '1' && i > 0 ? '' : digits[Number(d)]) + smallUnits[i] + text; });
        result = text + units[group] + result;
      }
      rest = Math.floor(rest / 10000);
      group++;
    }
    return result + '원';
  }
  const amount = document.getElementById('amount');
  const words = document.getElementById('initalk-amount-words');
  if (amount && words) {
    const update = () => { words.textContent = koreanWords(amount.value); };
    amount.addEventListener('input', update);
    update();
  }
  const phone = document.getElementById('phone');
  const customer = document.getElementById('initalk-customer');
  const form = document.querySelector('form[data-initalk-new]');
  if (phone && customer && form && phone.dataset.customerUrl) {
    phone.addEventListener('change', async () => {
      const value = phone.value.replace(/[^0-9]/g, '');
      if (value.length < 10) { customer.textContent = ''; return; }
      try {
        const body = new URLSearchParams({ phone: value, csrf_token: form.elements.csrf_token.value });
        const response = await fetch(phone.dataset.customerUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' }, body: body.toString() });
        if (!response.ok) throw new Error('request failed');
        const data = await response.json();
        customer.textContent = data.count > 0
          ? '거래횟수 ' + data.count + '회 · 총 거래금액 ' + Number(data.total).toLocaleString('ko-KR') + '원 · 최근거래일 ' + data.last_paid_at
          : '이 번호의 결제 이력이 없습니다.';
      } catch (e) { customer.textContent = '고객 이력을 확인하지 못했습니다.'; }
    });
  }
  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const field = document.getElementById(button.dataset.copy);
      if (!field) return;
      try { await navigator.clipboard.writeText(field.value); button.textContent = '복사됨'; }
      catch (e) { field.select(); button.textContent = '선택됨'; }
    });
  });
  const bulk = document.querySelector('form[data-initalk-bulk]');
  if (bulk) {
    const all = bulk.querySelector('[data-initalk-check-all]');
    if (all) all.addEventListener('change', () => { bulk.querySelectorAll('input[name="ids[]"]').forEach((box) => { box.checked = all.checked; }); });
    bulk.addEventListener('submit', (event) => {
      if (!bulk.querySelector('input[name="ids[]"]:checked')) { event.preventDefault(); window.alert('결제 요청을 먼저 선택해 주세요.'); return; }
      const action = event.submitter && event.submitter.value;
      if (action === 'cancel' && !window.confirm('선택한 결제 요청을 결제 전 취소할까요?')) event.preventDefault();
    });
  }
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (event) => {
      const message = f.dataset.confirm === 'refund' ? '입력한 금액을 결제사에 환불 요청합니다. 계속할까요?' : '이 결제 요청을 결제 전 취소할까요?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
})();
```

`src/Web/Routes.php`: 메시지 운영 라우트 `foreach` 블록 바로 다음에 추가한다(정적 경로를 `{id}` 라우트보다 먼저 등록한다):

```php
        $initalk = new \GnuCms\Web\Controller\InitalkAdminController($app);
        $slim->get('/admin/initalk', [$initalk, 'index'])->setName('admin.initalk');
        $slim->get('/admin/initalk/requests', [$initalk, 'requests'])->setName('admin.initalk.requests');
        $slim->post('/admin/initalk/requests/bulk', [$initalk, 'bulk'])->setName('admin.initalk.requests.bulk');
        $slim->get('/admin/initalk/requests/new', [$initalk, 'newForm'])->setName('admin.initalk.requests.new');
        $slim->post('/admin/initalk/requests/new', [$initalk, 'create']);
        $slim->post('/admin/initalk/customer', [$initalk, 'customer'])->setName('admin.initalk.customer');
        $slim->get('/admin/initalk/settings', [$initalk, 'settingsForm'])->setName('admin.initalk.settings');
        $slim->post('/admin/initalk/settings', [$initalk, 'saveSettings']);
        $slim->post('/admin/initalk/purge', [$initalk, 'purge'])->setName('admin.initalk.purge');
        $slim->get('/admin/initalk/requests/{id:[a-f0-9]{32}}', [$initalk, 'show'])->setName('admin.initalk.request');
        foreach (['send', 'cancel', 'sync', 'refund'] as $action) {
            $slim->post('/admin/initalk/requests/{id:[a-f0-9]{32}}/' . $action, static fn ($request, $response, array $args) => $initalk->act($action, $request, $response, $args))
                ->setName('admin.initalk.request.' . $action);
        }
```

`templates/default/admin/_sidebar.php`: `메시지 발송` `<li>` 다음 줄에

```php
    <li><a href="<?= $this->url('admin.initalk') ?>"<?php if ($section === 'initalk'): ?> class="menu-active" aria-current="page"<?php endif ?> title="이니톡 결제"><?= $this->icon('tag', 18) ?><span class="menu-text">이니톡 결제</span></a></li>
```

- [ ] **Step 6: 테스트·전체 스위트·커밋**

```bash
php -l src/Web/Controller/InitalkAdminController.php && php -l src/Web/Routes.php
for f in templates/default/admin/initalk/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
node --check www/themes/default/initalk.js
./vendor/bin/phpunit tests/Web/InitalkAdminTest.php
./vendor/bin/phpunit
git add src/Web/Controller/InitalkAdminController.php src/Web/Routes.php templates/default/admin/_sidebar.php templates/default/admin/initalk www/themes/default/initalk.css www/themes/default/initalk.js tests/Web/InitalkAdminTest.php
git commit -m "feat: add INITalk admin screens for requests, actions and settings

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 6: 공개 결제 페이지·결제 시작·복귀·이니시스 콜백

**Files:**
- Create: `src/Web/Controller/PayController.php`, `templates/default/pay/layout.php`, `templates/default/pay/show.php`, `templates/default/pay/start.php`, `www/themes/default/pay.css`
- Modify: `src/Web/Routes.php` (공개 라우트 + `ExternalRequests` 경로 추가)
- Test: `tests/Web/PayTest.php`

**Interfaces:**
- Consumes: `App::initalk()->requests->findByToken/expireOne/setReview`, `->checkout->start/complete`, `Checkout::order/device`, `App::paymentSettings()->available(env)`, `CallbackToken::verify(App, array $order, mixed $state)`, `ExecutionLock::run`, `Csrf::assert`.
- Produces: 라우트 `pay.show`(GET `/pay/{token}`), `pay.start`(POST `/pay/{token}/start`), `pay.return`(GET `/pay/{token}/return`), 외부 POST `/pay/callback?id=&state=`(폼, 64KB). `PayController` 공개 메서드 `show`, `start`, `back`, `callbackAuthenticate(ServerRequestInterface): bool`, `callback(ServerRequestInterface, ResponseInterface): ResponseInterface`. 템플릿 `pay/show`는 `state`(`open|unavailable|done|expired|cancelled`)에 따라 화면을 고른다.

- [ ] **Step 1: 웹 테스트를 쓴다**

`tests/Web/PayTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Payment\FakeTransport;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class PayTest extends WebTestCase
{
    private App $app;
    private string $root;
    private FakeTransport $http;
    private array $merchant;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-pay-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'py' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test/cms']]);
        Clock::freeze('2026-09-15 03:00:00');
        $this->app->cms()->saveSettings(['initalk.store_name' => '이니 상점', 'initalk.environment' => 'test', 'initalk.support_phone' => '1588-4954']);
        $this->merchant = Fixtures::config('inicis');
        $this->app->paymentSettings()->save('test', $this->merchant);
        $this->app->paymentSettings()->enable('test', true);
        $this->http = new FakeTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->http));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function request(): array
    {
        return $this->app->initalk()->requests->create(['product_name' => '플로럴 핸드크림 30ml', 'product_detail' => '향기 좋은 크림', 'buyer_name' => '김이니',
            'phone' => '01023457891', 'amount' => '15800'], 'test', 48, 1, '운영자');
    }

    /** 하위 경로(/cms) 설치를 흉내 낸다. 모든 요청은 /cms 기준으로 보낸다. */
    private function handle(string $method, string $path, array $body = [], array $server = [], ?string $rawBody = null, string $contentType = ''): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/cms' . $path, $server);
        if ($rawBody !== null) { $request->getBody()->write($rawBody); $request = $request->withHeader('Content-Type', $contentType); }
        elseif ($body !== []) $request = $request->withParsedBody($body);
        return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
    }

    #[DataProvider('connectionProvider')]
    public function testPaymentPageStartCallbackAndCompletion(array $config): void
    {
        $this->setupApp($config);
        $r = $this->request();
        $path = '/pay/' . $r['url_token'];
        self::assertSame(404, $this->handle('GET', '/pay/' . str_repeat('x', 27))->getStatusCode());
        $page = $this->handle('GET', $path);
        self::assertSame(200, $page->getStatusCode());
        self::assertSame('no-store', $page->getHeaderLine('Cache-Control'));
        self::assertSame('no-referrer', $page->getHeaderLine('Referrer-Policy'));
        $html = $this->body($page);
        self::assertStringContainsString('김이니 님', $html);
        self::assertStringContainsString('이니 상점', $html);
        self::assertStringContainsString('플로럴 핸드크림 30ml', $html);
        self::assertStringContainsString('15,800', $html);
        self::assertStringContainsString('action="/cms' . $path . '/start"', $html);
        self::assertStringNotContainsString('01023457891', $html);
        self::assertStringNotContainsString('daisyui', $html);
        self::assertSame(403, $this->handle('POST', $path . '/start', ['x' => '1'])->getStatusCode());
        session_start();
        $csrf = $_SESSION['csrf_token'];
        session_write_close();
        $desktop = $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)']);
        self::assertSame(200, $desktop->getStatusCode());
        self::assertStringContainsString('INIStdPay.js', $this->body($desktop));
        self::assertStringContainsString('name="oid" value="' . $r['id'] . '"', $this->body($desktop));
        self::assertStringContainsString('https://shop.example.test/cms/pay/callback?id=' . $r['id'] . '&amp;state=', $this->body($desktop));
        Clock::freeze('2026-09-15 03:00:30');
        $mobile = $this->handle('POST', $path . '/start', ['csrf_token' => $csrf], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)']);
        self::assertSame(200, $mobile->getStatusCode());
        self::assertStringContainsString('accept-charset="EUC-KR"', $this->body($mobile));
        self::assertStringContainsString('stgmobile.inicis.com/smart/payment/', $this->body($mobile));
        self::assertStringContainsString('name="P_OID" value="' . $r['id'] . '"', $this->body($mobile));
        self::assertSame([], $this->http->calls);
        // 복귀(닫기)는 아직 미결제이므로 실패 안내와 함께 결제 페이지로 돌아간다.
        $back = $this->handle('GET', $path . '/return');
        self::assertSame(303, $back->getStatusCode());
        self::assertSame('/cms' . $path . '?failed=1', $back->getHeaderLine('Location'));
        self::assertStringContainsString('결제가 완료되지 않았습니다', $this->body($this->handle('GET', $path . '?failed=1')));
        // 콜백: 잘못된 state → 403, 올바른 state → 승인·조회 후 완료 화면
        $stored = $this->app->initalk()->requests->find($r['id']);
        $state = CallbackToken::create($this->app, Checkout::order($stored));
        $callback = ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'orderNumber' => $r['id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
        $post = fn (string $stateValue) => $this->handle('POST', '/pay/callback?' . http_build_query(['id' => $r['id'], 'state' => $stateValue]), [], [], http_build_query($callback), 'application/x-www-form-urlencoded');
        self::assertSame(403, $post(bin2hex(random_bytes(32)))->getStatusCode());
        $tid = 'StdpayCARD' . bin2hex(random_bytes(10));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->merchant['merchant_id'], 'MOID' => $r['id'], 'TotPrice' => '15800', 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'mid' => $this->merchant['merchant_id'], 'oid' => $r['id'], 'price' => '15800', 'tid' => $tid,
            'transactionStatus' => 'APPROVAL', 'paymethod' => 'Card', 'approvedDate' => '20260915', 'approvedTime' => '120500', 'cardInfo' => ['currencyCode' => 'WON']]];
        $done = $post($state);
        self::assertSame(303, $done->getStatusCode());
        self::assertSame('/cms' . $path, $done->getHeaderLine('Location'));
        self::assertSame('', $done->getHeaderLine('Set-Cookie'));
        $paid = $this->app->initalk()->requests->find($r['id']);
        self::assertSame(Status::PAID, $paid['status']);
        self::assertSame($tid, $paid['transaction_id']);
        $final = $this->body($this->handle('GET', $path));
        self::assertStringContainsString('결제가 완료되었습니다', $final);
        self::assertStringContainsString($r['number'], $final);
        self::assertStringNotContainsString('action="/cms' . $path . '/start"', $final);
        // 이미 결제된 요청의 결제 시작은 거부한다.
        self::assertSame(422, $this->handle('POST', $path . '/start', ['csrf_token' => $csrf])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testClosedRequestsAndStoppedApiShowGuidanceInsteadOfForms(array $config): void
    {
        $this->setupApp($config);
        $expired = $this->request();
        $cancelled = $this->request();
        $this->app->initalk()->requests->cancel($cancelled['id'], '운영자');
        Clock::freeze('2026-09-18 00:00:00');
        $html = $this->body($this->handle('GET', '/pay/' . $expired['url_token']));
        self::assertStringContainsString('결제 기한이 지났습니다', $html);
        self::assertStringContainsString('1588-4954', $html);
        self::assertStringNotContainsString('/start"', $html);
        self::assertSame(Status::EXPIRED, $this->app->initalk()->requests->find($expired['id'])['status']);
        $html = $this->body($this->handle('GET', '/pay/' . $cancelled['url_token']));
        self::assertStringContainsString('취소된 결제 요청입니다', $html);
        Clock::freeze('2026-09-15 03:00:00');
        $open = $this->request();
        $this->app->paymentSettings()->enable('test', false);
        $html = $this->body($this->handle('GET', '/pay/' . $open['url_token']));
        self::assertStringContainsString('지금은 결제할 수 없습니다', $html);
        self::assertStringNotContainsString('/start"', $html);
        self::assertSame([], $this->http->calls);
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Web/PayTest.php 2>&1 | tail -5
```

- [ ] **Step 3: 컨트롤러**

`src/Web/Controller/PayController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\ExecutionLock;
use GnuCms\Support\Clock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** 고객이 알림톡 링크로 여는 결제 페이지. 회원·로그인과 무관하며 토큰으로만 요청을 찾는다. */
final class PayController
{
    public function __construct(private App $app)
    {
    }

    private function find(string $token): array
    {
        $requests = $this->app->initalk()->requests;
        $found = $requests->findByToken($token);
        if ($found === null) throw DomainError::notFound('결제 요청을 찾을 수 없습니다.');
        if (Status::canPay($found['status']) && $found['expires_at'] <= Clock::timestamp() && $requests->expireOne($found['id'])) $found = $requests->find($found['id']);
        return $found;
    }

    private function siteUrl(): string { return rtrim((string) $this->app->config('app.url', GNUCMS_URL), '/'); }
    private function basePath(): string { return rtrim((string) parse_url($this->siteUrl(), PHP_URL_PATH), '/'); }

    private function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $config = $this->app->initalk()->settings->read();
        return View::fromRequest($request)->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'pay/' . $template,
            $data + ['store_name' => $config['store_name'], 'support_phone' => $config['support_phone'], 'errors' => [],
                'time' => static fn ($timestamp): string => $timestamp === null ? '' : (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y년 m월 d일 H:i')]);
    }

    /** 화면에 넘길 요청 정보. 휴대폰 번호는 제외한다. */
    private function safe(array $found): array
    {
        unset($found['phone'], $found['phone_hash'], $found['phone_mask']);
        return $found;
    }

    private function state(array $found): string
    {
        return match (true) {
            in_array($found['status'], [Status::PAID, Status::REFUNDED], true) => 'done',
            $found['status'] === Status::EXPIRED => 'expired',
            $found['status'] === Status::CANCELLED => 'cancelled',
            !$this->app->paymentSettings()->available($found['environment']) => 'unavailable',
            default => 'open',
        };
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $found = $this->find((string) $args['token']);
        $query = $request->getQueryParams();
        return $this->render($request, $response, 'show', ['request' => $this->safe($found), 'state' => $this->state($found),
            'failed' => ($query['failed'] ?? '') === '1', 'errors' => []]);
    }

    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        Csrf::assert($request);
        $found = $this->find((string) $args['token']);
        $token = (string) $args['token'];
        try {
            $payment = ExecutionLock::run($this->app->storageDir(), fn (): array => $this->app->initalk()->checkout->start(
                $found['id'], Checkout::device($request->getHeaderLine('User-Agent')),
                $this->siteUrl() . '/pay/' . $token . '/return', $this->siteUrl() . '/pay/callback'));
        } catch (DomainError $e) {
            $message = $e->status() >= 500 ? '지금은 결제할 수 없습니다. 잠시 후 다시 시도해 주세요.' : implode(' ', array_values($e->details() ?: [$e->getMessage()]));
            return $this->render($request, $response->withStatus($e->status()), 'show', ['request' => $this->safe($found), 'state' => $this->state($found), 'failed' => false, 'errors' => [$message]]);
        }
        return $this->render($request, $response, 'start', ['request' => $this->safe($found), 'payment' => $payment, 'token' => $token]);
    }

    /** 결제창 닫기·실패 복귀. 아직 미결제면 안내 표시와 함께 결제 페이지로. */
    public function back(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $found = $this->find((string) $args['token']);
        $suffix = Status::canPay($found['status']) ? '?failed=1' : '';
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $this->basePath() . '/pay/' . $args['token'] . $suffix);
    }

    /** ExternalRequests 인증기: 요청이 있고 state가 그 요청·결제사·설정 판의 HMAC과 맞아야 한다. */
    public function callbackAuthenticate(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        $id = $query['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) return false;
        try {
            $found = $this->app->initalk()->requests->find($id);
        } catch (DomainError $e) {
            return false;
        }
        return $found['config_revision'] !== '' && CallbackToken::verify($this->app, Checkout::order($found), $query['state'] ?? null);
    }

    /** ExternalRequests 처리기: 승인·조회 후 결제 페이지로 보낸다. 실패는 확인 필요로 표시한다. */
    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = (string) $request->getQueryParams()['id'];
        $service = $this->app->initalk();
        $found = $service->requests->find($id);
        ExecutionLock::run($this->app->storageDir(), function () use ($service, $id, $request): void {
            try {
                $service->checkout->complete($id, is_array($request->getParsedBody()) ? $request->getParsedBody() : []);
            } catch (DomainError $e) {
                $service->requests->setReview($id, true, 'system', $e->status() >= 500 ? '승인·조회 결과를 확인해 주세요.' : '인증 결과 검증 실패: ' . implode(' ', array_values($e->details() ?: [$e->getMessage()])));
            }
        });
        return $response->withStatus(303)->withHeader('Location', $this->basePath() . '/pay/' . $found['url_token']);
    }
}
```

- [ ] **Step 4: 템플릿과 CSS**

`templates/default/pay/layout.php`:

```php
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="light dark">
<title><?= $this->block('title') ?></title>
<link rel="stylesheet" href="<?= $this->asset('pay.css') ?>">
</head>
<body class="pay-body">
<main class="pay-card" id="main">
<header class="pay-head"><span class="pay-store"><?= $this->e($store_name) ?></span><span class="pay-brand">이니톡 결제</span></header>
<?= $this->block('body') ?>
<footer class="pay-foot"><?php if ($support_phone !== ''): ?>고객센터 <a href="tel:<?= $this->e(preg_replace('/[^0-9]/', '', $support_phone)) ?>"><?= $this->e($support_phone) ?></a> · <?php endif ?><?= $this->e($site['site_name']) ?></footer>
</main>
<?= $this->block('scripts') ?>
</body>
</html>
```

`templates/default/pay/show.php`:

```php
<?php $this->layout('pay/layout') ?>
<?php $this->start('title') ?>결제 · <?= $this->e($store_name) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $r = $request; ?>
<?php foreach ($errors as $error): ?><p class="pay-alert" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($failed && $state === 'open'): ?><p class="pay-alert" role="alert">결제가 완료되지 않았습니다. 다시 시도해 주세요.</p><?php endif ?>
<?php if ($state === 'open' || $state === 'unavailable'): ?>
<h1 class="pay-title"><?= $this->e($r['buyer_name'] !== '' ? $r['buyer_name'] . ' 님' : '고객') ?>, 결제할 내역을 확인해 주세요</h1>
<dl class="pay-summary">
<dt>상점명</dt><dd><?= $this->e($store_name) ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?><?php if ($r['product_detail'] !== ''): ?><br><small><?= $this->e($r['product_detail']) ?></small><?php endif ?></dd>
<dt>결제금액</dt><dd class="pay-amount"><?= number_format($r['amount']) ?>원</dd>
<dt>결제기한</dt><dd><?= $this->e($time($r['expires_at'])) ?></dd>
<dt>주문번호</dt><dd><?= $this->e($r['number']) ?></dd>
</dl>
<?php if ($state === 'open'): ?>
<p class="pay-hint">결제 내용에 동의하시면 다음 버튼을 눌러 주세요. 신용카드·간편결제로 결제됩니다.</p>
<form method="post" action="<?= $this->url('pay.start', ['token' => $r['url_token']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="pay-button" type="submit">다음</button></form>
<?php else: ?><p class="pay-alert" role="alert">지금은 결제할 수 없습니다. 상점에 문의해 주세요.</p><?php endif ?>
<?php elseif ($state === 'done'): ?>
<h1 class="pay-title">결제가 완료되었습니다</h1>
<dl class="pay-summary">
<dt>주문번호</dt><dd><?= $this->e($r['number']) ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?></dd>
<dt>결제금액</dt><dd class="pay-amount"><?= number_format($r['amount']) ?>원</dd>
<dt>결제일시</dt><dd><?= $this->e($time($r['paid_at'])) ?></dd>
<?php if ($r['refunded_amount'] > 0): ?><dt>환불</dt><dd><?= number_format($r['refunded_amount']) ?>원<?= $r['status'] === 'refunded' ? ' (전액 환불)' : '' ?></dd><?php endif ?>
</dl>
<p class="pay-hint">승인 내역은 카드사 앱과 문자로도 확인할 수 있습니다.</p>
<?php elseif ($state === 'expired'): ?>
<h1 class="pay-title">결제 기한이 지났습니다</h1>
<p class="pay-hint"><?= $this->e($r['product_name']) ?> (<?= number_format($r['amount']) ?>원)의 결제 기한 <?= $this->e($time($r['expires_at'])) ?>이 지났습니다. 상점에 문의해 주세요.</p>
<?php else: ?>
<h1 class="pay-title">취소된 결제 요청입니다</h1>
<p class="pay-hint">이 결제 요청은 상점에서 취소했습니다. 문의는 상점으로 해 주세요.</p>
<?php endif ?>
<?php $this->stop() ?>
```

`templates/default/pay/start.php`:

```php
<?php $this->layout('pay/layout') ?>
<?php $this->start('title') ?>결제창 연결 · <?= $this->e($store_name) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<h1 class="pay-title">카드 결제창으로 이동합니다</h1>
<p class="pay-hint"><?= $this->e($request['product_name']) ?> · <?= number_format($request['amount']) ?>원. 결제창이 열리지 않으면 아래 버튼을 눌러 주세요.</p>
<form id="pay-form" method="post" action="<?= $this->e($payment['action'] ?? '') ?>" accept-charset="<?= $this->e($payment['charset'] ?? 'UTF-8') ?>">
<?php foreach ($payment['fields'] as $field => $value): ?><input type="hidden" name="<?= $this->e($field) ?>" value="<?= $this->e((string) $value) ?>"><?php endforeach ?>
<button class="pay-button" id="pay-button" type="<?= $payment['kind'] === 'inicis' ? 'button' : 'submit' ?>">카드 결제창 열기</button>
</form>
<p id="pay-message" class="pay-hint" role="status"></p>
<p><a class="pay-link" href="<?= $this->url('pay.show', ['token' => $token]) ?>">결제 내역으로 돌아가기</a></p>
<?php $this->stop() ?>
<?php $this->start('scripts') ?>
<?php if ($payment['kind'] === 'inicis'): ?>
<script src="<?= $this->e($payment['script']) ?>"></script>
<script>(()=>{'use strict';const button=document.getElementById('pay-button'),message=document.getElementById('pay-message');const open=()=>{if(!window.INIStdPay){message.textContent='결제창 연결을 확인해 주세요.';return;}window.INIStdPay.pay('pay-form');};button.addEventListener('click',open);window.setTimeout(open,300);})();</script>
<?php else: ?>
<script>(()=>{'use strict';window.setTimeout(()=>{document.getElementById('pay-form').submit();},300);})();</script>
<?php endif ?>
<?php $this->stop() ?>
```

`www/themes/default/pay.css`:

```css
/* 공개 결제 페이지. 외부 자산 없이 모바일 우선. */
:root{color-scheme:light dark;--pay-bg:#f4f5f8;--pay-card:#fff;--pay-text:#1b1d24;--pay-soft:#6b7280;--pay-line:#e5e7eb;--pay-primary:#4f46e5;--pay-alert:#b91c1c;--pay-alert-bg:#fef2f2}
@media (prefers-color-scheme:dark){:root{--pay-bg:#0f1117;--pay-card:#171a23;--pay-text:#f3f4f6;--pay-soft:#9ca3af;--pay-line:#2a2f3d;--pay-primary:#818cf8;--pay-alert:#fca5a5;--pay-alert-bg:#3b1111}}
*{box-sizing:border-box}
body.pay-body{margin:0;padding:1rem;background:var(--pay-bg);color:var(--pay-text);font:16px/1.5 Pretendard,-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo","Malgun Gothic",system-ui,sans-serif}
.pay-card{max-width:28rem;margin:0 auto;background:var(--pay-card);border:1px solid var(--pay-line);border-radius:16px;padding:1.25rem}
.pay-head{display:flex;justify-content:space-between;align-items:baseline;gap:.5rem;border-bottom:1px solid var(--pay-line);padding-bottom:.75rem;margin-bottom:1rem}
.pay-store{font-weight:700}.pay-brand{font-size:12px;color:var(--pay-soft)}
.pay-title{font-size:1.25rem;margin:.25rem 0 1rem;line-height:1.35}
.pay-summary{display:grid;grid-template-columns:6rem 1fr;gap:.5rem .75rem;margin:0 0 1rem}
.pay-summary dt{color:var(--pay-soft);font-size:14px}.pay-summary dd{margin:0;overflow-wrap:anywhere}
.pay-amount{font-size:1.4rem;font-weight:700}
.pay-hint{color:var(--pay-soft);font-size:14px}
.pay-alert{background:var(--pay-alert-bg);color:var(--pay-alert);border-radius:10px;padding:.75rem 1rem;font-size:14px}
.pay-button{display:block;width:100%;padding:.9rem 1rem;border:0;border-radius:12px;background:var(--pay-primary);color:#fff;font-size:1rem;font-weight:700;cursor:pointer}
.pay-link{color:var(--pay-primary)}
.pay-foot{margin-top:1.25rem;padding-top:.75rem;border-top:1px solid var(--pay-line);font-size:12px;color:var(--pay-soft)}
.pay-foot a{color:inherit}
```

- [ ] **Step 5: 라우트와 콜백 등록**

`src/Web/Routes.php`: 이니톡 관리자 라우트 블록 다음에

```php
        $pay = new \GnuCms\Web\Controller\PayController($app);
        $slim->get('/pay/{token:[A-Za-z0-9_-]{20,40}}', [$pay, 'show'])->setName('pay.show');
        $slim->post('/pay/{token:[A-Za-z0-9_-]{20,40}}/start', [$pay, 'start'])->setName('pay.start');
        $slim->get('/pay/{token:[A-Za-z0-9_-]{20,40}}/return', [$pay, 'back'])->setName('pay.return');
```

기존 `$slim->add(new ExternalRequests([...], $slim->getBasePath()));` 호출을 다음으로 바꾼다(웹훅 두 경로는 그대로, 콜백 경로 추가):

```php
        $slim->add(new ExternalRequests([
            '/messaging/bizppurio/result' => $webhook, '/plugins/bizppurio/result' => $webhook,
            // 이니시스 인증 결과 콜백(폼). 세션 없이 HMAC state로만 인증한다.
            '/pay/callback' => [[$pay, 'callbackAuthenticate'], [$pay, 'callback'], 65536, 'application/x-www-form-urlencoded'],
        ], $slim->getBasePath()));
```

- [ ] **Step 6: 테스트·전체 스위트·커밋**

```bash
php -l src/Web/Controller/PayController.php && php -l src/Web/Routes.php
for f in templates/default/pay/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Web/PayTest.php
./vendor/bin/phpunit
git add src/Web/Controller/PayController.php src/Web/Routes.php templates/default/pay www/themes/default/pay.css tests/Web/PayTest.php
git commit -m "feat: add the public INITalk payment page with INICIS checkout and callback

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 7: `Support\QrCode` 인코더와 상세 QR

**Files:**
- Create: `src/Support/QrCode.php`, `tests/Support/QrCodeTest.php`, `tests/Support/qr_decode.py`
- Modify: `src/Web/Controller/InitalkAdminController.php` (`qr()`), `src/Web/Routes.php`, `templates/default/admin/initalk/request.php`, `tests/Web/InitalkAdminTest.php`

**Interfaces:**
- Produces: `QrCode::matrix(string $text): list<list<bool>>`(행 배열, `true`=어두움), `QrCode::svg(string $text, int $module = 4, int $quiet = 4): string`, `QrCode::png(string $text, int $module = 8, int $quiet = 4): string`(GD 필요; 없으면 `DomainError::internal`), `QrCode::version(string $text): int`, `QrCode::capacity(int $version): int`(바이트), `QrCode::reedSolomon(array $data, int $ecCount): array`, `QrCode::formatBits(int $mask): int`; 라우트 `admin.initalk.request.qr`(GET `/admin/initalk/requests/{id}/qr.svg`, `image/svg+xml`).
- 규격: 바이트 모드, 오류정정 M, 버전 1~10, 8개 마스크 벌점 최소 선택, 조용 영역 4모듈. 입력 최대 213바이트.

- [ ] **Step 1: 테스트를 쓴다**

`tests/Support/qr_decode.py` (선택적 라운드트립 디코더; OpenCV가 없으면 종료 코드 2):

```python
import sys
try:
    import cv2
except ImportError:
    print("SKIP: cv2 not installed", file=sys.stderr)
    sys.exit(2)
image = cv2.imread(sys.argv[1])
data, points, _ = cv2.QRCodeDetector().detectAndDecode(image)
print(data, end="")
```

`tests/Support/QrCodeTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use GnuCms\Error\DomainError;
use GnuCms\Support\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testReedSolomonMatchesTheStandardExample(): void
    {
        // ISO/IEC 18004 1-M 예제("HELLO WORLD")의 데이터 코드워드와 오류정정 코드워드.
        $data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17];
        self::assertSame([196, 35, 39, 119, 235, 215, 231, 226, 93, 23], QrCode::reedSolomon($data, 10));
    }

    public function testFormatInformationIsBchEncodedAndMasked(): void
    {
        self::assertSame(0x5412, QrCode::formatBits(0));
        self::assertSame(0b101000100100101, QrCode::formatBits(1));
        for ($mask = 0; $mask < 8; $mask++) {
            $value = QrCode::formatBits($mask) ^ 0x5412;
            self::assertSame($mask, $value >> 10, 'data bits');
            for ($i = 14; $i >= 10; $i--) if (($value >> $i) & 1) $value ^= 0x537 << ($i - 10);
            self::assertSame(0, $value & 0x3FF, 'BCH remainder for mask ' . $mask);
        }
    }

    public function testVersionSelectionAndCapacity(): void
    {
        self::assertSame([14, 26, 42, 62, 84, 106, 122, 152, 180, 213], array_map(QrCode::capacity(...), range(1, 10)));
        self::assertSame(1, QrCode::version(str_repeat('a', 14)));
        self::assertSame(2, QrCode::version(str_repeat('a', 15)));
        self::assertSame(10, QrCode::version(str_repeat('a', 213)));
        $this->expectException(DomainError::class);
        QrCode::version(str_repeat('a', 214));
    }

    public function testMatrixHasFunctionPatternsAndIsDeterministic(): void
    {
        $text = 'https://shop.example.test/pay/abcdefghijklmnopqrstuvwxyz0';
        $matrix = QrCode::matrix($text);
        $size = count($matrix);
        self::assertSame(17 + 4 * QrCode::version($text), $size);
        foreach ($matrix as $row) self::assertCount($size, $row);
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            self::assertTrue($matrix[$r][$c]);
            self::assertTrue($matrix[$r + 3][$c + 3]);
            self::assertFalse($matrix[$r + 1][$c + 1]);
            self::assertTrue($matrix[$r + 6][$c + 6]);
        }
        self::assertFalse($matrix[7][7]);
        for ($i = 8; $i < $size - 8; $i++) {
            self::assertSame($i % 2 === 0, $matrix[6][$i], 'timing row');
            self::assertSame($i % 2 === 0, $matrix[$i][6], 'timing column');
        }
        self::assertTrue($matrix[4 * QrCode::version($text) + 9][8], 'dark module');
        self::assertSame($matrix, QrCode::matrix($text));
        $svg = QrCode::svg($text);
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        self::assertStringContainsString('<path d="M', $svg);
        $dim = ($size + 8) * 4;
        self::assertStringContainsString('viewBox="0 0 ' . $dim . ' ' . $dim . '"', $svg);
        $this->expectException(DomainError::class);
        QrCode::matrix('');
    }

    public function testVersionSevenAndAboveCarryVersionInformation(): void
    {
        $matrix = QrCode::matrix(str_repeat('https://shop.example.test/pay/', 5)); // 150 bytes → 버전 8
        $size = count($matrix);
        self::assertSame(49, $size);
        // 버전 8 정보 0x085BC: 비트 i는 (행 i/3, 열 size-11+i%3)와 그 전치 위치에 놓인다.
        for ($i = 0; $i < 18; $i++) {
            $bit = ((0x085BC >> $i) & 1) === 1;
            self::assertSame($bit, $matrix[intdiv($i, 3)][$size - 11 + $i % 3], 'version bit ' . $i);
            self::assertSame($bit, $matrix[$size - 11 + $i % 3][intdiv($i, 3)], 'version bit mirror ' . $i);
        }
    }

    public function testPngRoundTripsThroughAnExternalDecoderWhenAvailable(): void
    {
        if (!function_exists('imagecreatetruecolor')) self::markTestSkipped('GD 없음');
        $python = getenv('GNUCMS_QR_PYTHON') ?: 'python3';
        $text = 'https://shop.example.test/pay/Zm9vYmFyYmF6cXV4cXV1eA';
        $file = tempnam(sys_get_temp_dir(), 'gnucms-qr-') . '.png';
        file_put_contents($file, QrCode::png($text));
        try {
            $process = proc_open([$python, __DIR__ . '/qr_decode.py', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) self::markTestSkipped('python3 없음');
            $out = stream_get_contents($pipes[1]);
            fclose($pipes[1]); fclose($pipes[2]);
            $code = proc_close($process);
            if ($code === 2 || $code === 127) self::markTestSkipped('OpenCV 디코더 없음 — 휴대폰 스캔으로 확인');
            self::assertSame(0, $code);
            self::assertSame($text, $out);
        } finally {
            @unlink($file);
        }
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Support/QrCodeTest.php 2>&1 | tail -5
```

- [ ] **Step 3: 인코더**

`src/Support/QrCode.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Support;

use GnuCms\Error\DomainError;

/**
 * 외부 의존성 없는 QR 인코더. 바이트 모드, 오류정정 M, 버전 1~10, ISO/IEC 18004 배치·마스크 규칙.
 * 결제 링크(100자 이내)를 넣는 용도이며 카나·한자 모드 등은 지원하지 않는다.
 */
final class QrCode
{
    private const EC_LEVEL = 0; // M
    /** 버전 => [총 코드워드, 블록당 EC 코드워드, [[블록 수, 블록당 데이터 코드워드], …]] */
    private const BLOCKS = [
        1 => [26, 10, [[1, 16]]], 2 => [44, 16, [[1, 28]]], 3 => [70, 26, [[1, 44]]], 4 => [100, 18, [[2, 32]]], 5 => [134, 24, [[2, 43]]],
        6 => [172, 16, [[4, 27]]], 7 => [196, 18, [[4, 31]]], 8 => [242, 22, [[2, 38], [2, 39]]], 9 => [292, 22, [[3, 36], [2, 37]]], 10 => [346, 26, [[4, 43], [1, 44]]],
    ];
    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    private const VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];
    private static ?array $exp = null;
    private static ?array $log = null;

    public static function capacity(int $version): int
    {
        $data = 0;
        foreach (self::BLOCKS[$version][2] as [$count, $length]) $data += $count * $length;
        return intdiv($data * 8 - 4 - ($version >= 10 ? 16 : 8), 8);
    }

    public static function version(string $text): int
    {
        for ($version = 1; $version <= 10; $version++) if (strlen($text) <= self::capacity($version)) return $version;
        throw DomainError::validation(['qr' => 'QR 코드에 넣기에는 너무 긴 내용입니다(최대 213바이트).']);
    }

    /** @return list<list<bool>> */
    public static function matrix(string $text): array
    {
        if ($text === '') throw DomainError::validation(['qr' => 'QR 코드 내용이 비어 있습니다.']);
        $version = self::version($text);
        $size = 17 + 4 * $version;
        [$modules, $reserved] = self::functionPatterns($version, $size);
        self::placeData($modules, $reserved, self::codewords($text, $version), $size);
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $modules;
            for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) if (!$reserved[$y][$x] && self::maskBit($mask, $y, $x)) $candidate[$y][$x] = !$candidate[$y][$x];
            self::writeFormat($candidate, $mask, $size);
            if ($version >= 7) self::writeVersion($candidate, $version, $size);
            $score = self::penalty($candidate, $size);
            if ($score < $bestScore) { $bestScore = $score; $best = $candidate; }
        }
        return $best;
    }

    public static function svg(string $text, int $module = 4, int $quiet = 4): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $dim = ($size + 2 * $quiet) * $module;
        $path = '';
        foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
            if ($dark) $path .= 'M' . (($x + $quiet) * $module) . ' ' . (($y + $quiet) * $module) . 'h' . $module . 'v' . $module . 'h-' . $module . 'z';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" aria-label="QR 코드">'
            . '<rect width="100%" height="100%" fill="#ffffff"/><path d="' . $path . '" fill="#000000"/></svg>';
    }

    public static function png(string $text, int $module = 8, int $quiet = 4): string
    {
        if (!function_exists('imagecreatetruecolor')) throw DomainError::internal('PNG 생성에는 GD 확장이 필요합니다.');
        $matrix = self::matrix($text);
        $size = count($matrix);
        $dim = ($size + 2 * $quiet) * $module;
        $image = imagecreatetruecolor($dim, $dim);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $dim - 1, $dim - 1, $white);
        foreach ($matrix as $y => $row) foreach ($row as $x => $dark) {
            if ($dark) imagefilledrectangle($image, ($x + $quiet) * $module, ($y + $quiet) * $module, ($x + $quiet + 1) * $module - 1, ($y + $quiet + 1) * $module - 1, $black);
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /** 데이터 비트 → 패딩 → 블록 분할 → RS → 인터리브. @return list<int> */
    private static function codewords(string $text, int $version): array
    {
        [, $ecCount, $blocks] = self::BLOCKS[$version];
        $dataCount = 0;
        foreach ($blocks as [$count, $length]) $dataCount += $count * $length;
        $bits = '0100' . str_pad(decbin(strlen($text)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $bits .= str_repeat('0', min(4, $dataCount * 8 - strlen($bits)));
        if (strlen($bits) % 8 !== 0) $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        $data = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($data) < $dataCount; $pad++) $data[] = $pad % 2 === 0 ? 0xEC : 0x11;
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($blocks as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $length);
                $offset += $length;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecCount);
            }
        }
        $result = [];
        $longest = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $longest; $i++) foreach ($dataBlocks as $block) if (isset($block[$i])) $result[] = $block[$i];
        for ($i = 0; $i < $ecCount; $i++) foreach ($ecBlocks as $block) $result[] = $block[$i];
        return $result;
    }

    private static function tables(): void
    {
        if (self::$exp !== null) return;
        $exp = [];
        $log = [];
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function multiply(int $a, int $b): int
    {
        return $a === 0 || $b === 0 ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** GF(256) 리드-솔로몬 오류정정 코드워드. @param list<int> $data @return list<int> */
    public static function reedSolomon(array $data, int $ecCount): array
    {
        self::tables();
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= self::multiply($coefficient, self::$exp[$i]);
            }
            $generator = $next;
        }
        $remainder = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;
            foreach ($generator as $j => $coefficient) {
                if ($j > 0) $remainder[$j - 1] ^= self::multiply($coefficient, $factor);
            }
        }
        return $remainder;
    }

    /** @return array{0:list<list<bool>>,1:list<list<bool>>} 모듈과 기능 패턴 예약 표 */
    private static function functionPatterns(int $version, int $size): array
    {
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));
        $set = static function (int $y, int $x, bool $dark) use (&$modules, &$reserved, $size): void {
            if ($y < 0 || $x < 0 || $y >= $size || $x >= $size) return;
            $modules[$y][$x] = $dark;
            $reserved[$y][$x] = true;
        };
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
                $inside = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
                $set($row + $r, $col + $c, $inside && max(abs($r - 3), abs($c - 3)) !== 2);
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        $positions = self::ALIGNMENT[$version];
        $last = $positions === [] ? -1 : $positions[count($positions) - 1];
        foreach ($positions as $row) foreach ($positions as $col) {
            if (($row === 6 && $col === 6) || ($row === 6 && $col === $last) || ($row === $last && $col === 6)) continue;
            for ($r = -2; $r <= 2; $r++) for ($c = -2; $c <= 2; $c++) $set($row + $r, $col + $c, max(abs($r), abs($c)) !== 1);
        }
        // 형식 정보 자리(값은 마스크 선택 후 기록) + 어두운 모듈
        for ($i = 0; $i <= 8; $i++) { if ($i === 6) continue; $set(8, $i, false); $set($i, 8, false); } // 6은 타이밍 패턴
        for ($i = 0; $i < 8; $i++) { $set(8, $size - 1 - $i, false); $set($size - 1 - $i, 8, false); }
        $set($size - 8, 8, true);
        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $set(intdiv($i, 3), $size - 11 + $i % 3, false);
                $set($size - 11 + $i % 3, intdiv($i, 3), false);
            }
        }
        return [$modules, $reserved];
    }

    /** 오른쪽 아래부터 두 열씩 지그재그로 데이터 비트를 놓는다. 6열은 건너뛴다. */
    private static function placeData(array &$modules, array $reserved, array $codewords, int $size): void
    {
        $bits = '';
        foreach ($codewords as $codeword) $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        $index = 0;
        $upward = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;
            for ($k = 0; $k < $size; $k++) {
                $row = $upward ? $size - 1 - $k : $k;
                foreach ([$col, $col - 1] as $x) {
                    if ($reserved[$row][$x]) continue;
                    $modules[$row][$x] = $index < strlen($bits) && $bits[$index] === '1';
                    $index++;
                }
            }
            $upward = !$upward;
        }
    }

    private static function maskBit(int $mask, int $i, int $j): bool
    {
        return match ($mask) {
            0 => ($i + $j) % 2 === 0,
            1 => $i % 2 === 0,
            2 => $j % 3 === 0,
            3 => ($i + $j) % 3 === 0,
            4 => (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0,
            5 => ($i * $j) % 2 + ($i * $j) % 3 === 0,
            6 => (($i * $j) % 2 + ($i * $j) % 3) % 2 === 0,
            7 => (($i + $j) % 2 + ($i * $j) % 3) % 2 === 0,
        };
    }

    /** 15비트 형식 정보: (EC 2비트 | 마스크 3비트) + BCH(15,5) 나머지, 0x5412로 XOR. */
    public static function formatBits(int $mask): int
    {
        $data = (self::EC_LEVEL << 3) | $mask;
        $remainder = $data << 10;
        for ($i = 14; $i >= 10; $i--) if (($remainder >> $i) & 1) $remainder ^= 0x537 << ($i - 10);
        return (($data << 10) | $remainder) ^ 0x5412;
    }

    private static function writeFormat(array &$m, int $mask, int $size): void
    {
        $bits = self::formatBits($mask);
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) $m[$i][8] = $bit($i);
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) $m[8][14 - $i] = $bit($i);
        for ($i = 0; $i < 8; $i++) $m[8][$size - 1 - $i] = $bit($i);
        for ($i = 8; $i < 15; $i++) $m[$size - 15 + $i][8] = $bit($i);
        $m[$size - 8][8] = true;
    }

    private static function writeVersion(array &$m, int $version, int $size): void
    {
        $bits = self::VERSION_INFO[$version];
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $m[intdiv($i, 3)][$size - 11 + $i % 3] = $dark;
            $m[$size - 11 + $i % 3][intdiv($i, 3)] = $dark;
        }
    }

    /** ISO 18004 마스크 벌점 4개 규칙(N1=3, N2=3, N3=40, N4=10). */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $lines = [];
        for ($y = 0; $y < $size; $y++) {
            $row = '';
            $col = '';
            for ($x = 0; $x < $size; $x++) {
                $row .= $m[$y][$x] ? '1' : '0';
                $col .= $m[$x][$y] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
        }
        foreach ($lines as $line) {
            preg_match_all('/(0{5,}|1{5,})/', $line, $runs);
            foreach ($runs[0] as $run) $score += 3 + strlen($run) - 5;
            $score += 40 * (substr_count($line, '00001011101') + substr_count($line, '10111010000'));
        }
        for ($y = 0; $y < $size - 1; $y++) for ($x = 0; $x < $size - 1; $x++) {
            if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) $score += 3;
        }
        $dark = 0;
        foreach ($m as $row) foreach ($row as $module) if ($module) $dark++;
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $score + 10 * max(0, $k);
    }
}
```

- [ ] **Step 4: 상세 화면의 QR**

`src/Web/Controller/InitalkAdminController.php`에 추가:

```php
    public function qr(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->guard($request);
        $found = $this->app->initalk()->requests->find((string) $args['id']);
        $response->getBody()->write(\GnuCms\Support\QrCode::svg($this->payUrl($found), 6));
        return $response->withHeader('Content-Type', 'image/svg+xml; charset=utf-8')->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Content-Disposition', 'inline; filename="' . $found['number'] . '.svg"')->withHeader('X-Content-Type-Options', 'nosniff');
    }
```

`src/Web/Routes.php`의 `admin.initalk.request` GET 라우트 다음 줄에:

```php
        $slim->get('/admin/initalk/requests/{id:[a-f0-9]{32}}/qr.svg', [$initalk, 'qr'])->setName('admin.initalk.request.qr');
```

`templates/default/admin/initalk/request.php`의 결제 링크 블록(`<label class="extension-label" for="initalk-pay-url">…</div>`) 다음에:

```php
<div class="initalk-qr"><img src="<?= $this->url('admin.initalk.request.qr', ['id' => $r['id']]) ?>" alt="결제 링크 QR 코드" width="180" height="180"><p class="muted">대면 결제: 고객이 QR을 찍으면 같은 결제 페이지가 열립니다. <a class="link" href="<?= $this->url('admin.initalk.request.qr', ['id' => $r['id']]) ?>" target="_blank" rel="noopener">새 창에서 크게 보기</a></p></div>
```

`www/themes/default/initalk.css`에 `.initalk-admin .initalk-qr{margin-top:1rem;display:flex;gap:1rem;align-items:center}.initalk-admin .initalk-qr img{background:#fff;border:1px solid var(--bc-line, #e5e7eb);border-radius:8px}` 추가.

`tests/Web/InitalkAdminTest.php`에 테스트 메서드 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testQrEndpointIsAdminOnlyAndRendersTheLink(array $config): void
    {
        $this->setupApp($config);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $this->assertLoginRedirect($this->get($this->app, '/admin/initalk/requests/' . $request['id'] . '/qr.svg'), '/admin/initalk/requests/' . $request['id'] . '/qr.svg');
        $this->signIn(true);
        $svg = $this->get($this->app, '/admin/initalk/requests/' . $request['id'] . '/qr.svg');
        self::assertSame(200, $svg->getStatusCode());
        self::assertSame('image/svg+xml; charset=utf-8', $svg->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('<svg', $this->body($svg));
        self::assertStringContainsString('qr.svg', $this->body($this->get($this->app, '/admin/initalk/requests/' . $request['id'])));
        self::assertSame(404, $this->get($this->app, '/admin/initalk/requests/' . str_repeat('0', 32) . '/qr.svg')->getStatusCode());
    }
```

- [ ] **Step 5: 검증**

```bash
php -l src/Support/QrCode.php
./vendor/bin/phpunit tests/Support/QrCodeTest.php tests/Web/InitalkAdminTest.php
```

라운드트립 테스트는 OpenCV가 없으면 건너뛴다. 네트워크가 되면 저장소 밖에 1회용 가상환경을 만들어 실행해 본다(실패해도 작업을 막지 않는다):

```bash
python3 -m venv /tmp/gnucms-qr-venv && /tmp/gnucms-qr-venv/bin/pip install --quiet opencv-python-headless \
  && GNUCMS_QR_PYTHON=/tmp/gnucms-qr-venv/bin/python3 ./vendor/bin/phpunit tests/Support/QrCodeTest.php --filter RoundTrip
```

가상환경을 만들지 못했으면 `php -r 'require "vendor/autoload.php"; file_put_contents("/tmp/gnucms-qr-sample.png", GnuCms\Support\QrCode::png("https://gnucms.charmgen.com/pay/Zm9vYmFyYmF6cXV4cXV1eA"));'`로 PNG를 만들어 보고서에 경로를 적는다(사용자가 휴대폰으로 스캔해 확인한다).

- [ ] **Step 6: 커밋**

```bash
git add src/Support/QrCode.php tests/Support/QrCodeTest.php tests/Support/qr_decode.py src/Web/Controller/InitalkAdminController.php src/Web/Routes.php templates/default/admin/initalk/request.php www/themes/default/initalk.css tests/Web/InitalkAdminTest.php
git commit -m "feat: add a dependency-free QR encoder and payment link QR for INITalk requests

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 8: CSV 일괄등록

**Files:**
- Create: `src/Initalk/CsvImport.php`, `templates/default/admin/initalk/import.php`
- Modify: `src/Initalk/Service.php`, `src/Web/Controller/InitalkAdminController.php`, `src/Web/Routes.php`, `templates/default/admin/initalk/_nav.php`
- Test: `tests/Initalk/CsvImportTest.php`, `tests/Web/InitalkAdminTest.php`(메서드 추가)

**Interfaces:**
- Consumes: `Requests::normalize/create`, `Notifier::sendMany`, `Settings::read`.
- Produces: `CsvImport::sample(): string`, `CsvImport::parse(string $contents, int $defaultHours): array{rows:list<array>,errors:list<string>,total:int}`(각 row는 `line`(원본 행 번호) + 정규화된 `product_name,product_detail,buyer_name,phone,amount,expiry_hours`), `CsvImport::remember(array $parsed, string $filename, bool $sendNow): string`(세션 토큰, 10분), `CsvImport::pending(string $token): ?array`, `CsvImport::confirm(string $token, string $environment, int $actorId, string $actor): array{batch_id,created,failed,sent,errors:list<string>}`; 라우트 `admin.initalk.import`(GET/POST `/admin/initalk/requests/import`), `admin.initalk.import.confirm`(POST), `admin.initalk.import.sample`(GET, `text/csv`); `Service->import`.
- 한도: 파일 1MB, 500행. 헤더 `상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)`(순서 무관, 모르는 열 무시, `결제기한(시간)` 선택). 인코딩: UTF-8(BOM 허용), 아니면 CP949로 간주.

- [ ] **Step 1: 단위 테스트를 쓴다**

`tests/Initalk/CsvImportTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\CsvImport;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CsvImportTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): CsvImport
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-csv-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'iv' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
        $_SESSION = [];
        return $this->app->initalk()->import;
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        $_SESSION = [];
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    public function testSampleParsesAndHeadersMayBeReorderedOrExtended(): void
    {
        $import = new CsvImport(new App(['auth' => ['secret' => 'x']]), null, null, null);
        $sample = CsvImport::sample();
        self::assertStringStartsWith("\xEF\xBB\xBF상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)\n", $sample);
        $parsed = $import->parse($sample, 48);
        self::assertSame([], $parsed['errors']);
        self::assertCount(2, $parsed['rows']);
        self::assertSame(2, $parsed['rows'][0]['line']);
        $reordered = "금액,휴대폰번호,구매자명,메모,상품명\n\"12,000\",010-1111-2222,홍길동,무시되는 열,\"수강료, 9월\"\n\n";
        $parsed = $import->parse($reordered, 24);
        self::assertSame([], $parsed['errors']);
        self::assertSame(['line' => 2, 'product_name' => '수강료, 9월', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01011112222', 'amount' => 12000, 'expiry_hours' => 24], $parsed['rows'][0]);
        $cp949 = mb_convert_encoding("상품명,구매자명,휴대폰번호,금액\r\n한글 상품,김이니,01023457891,5000\r\n", 'CP949', 'UTF-8');
        $parsed = $import->parse($cp949, 48);
        self::assertSame([], $parsed['errors']);
        self::assertSame('한글 상품', $parsed['rows'][0]['product_name']);
    }

    public function testRowErrorsAreReportedByLineAndLimitsApply(): void
    {
        $import = new CsvImport(new App(['auth' => ['secret' => 'x']]), null, null, null);
        $parsed = $import->parse("상품명,구매자명,휴대폰번호,금액\n좋은 상품,김이니,02-123-4567,5000\n,홍길동,01011112222,5000\n정상,이몽룡,01011113333,50\n정상,성춘향,01011114444,7000\n", 48);
        self::assertCount(1, $parsed['rows']);
        self::assertSame(4, $parsed['total']);
        self::assertCount(3, $parsed['errors']);
        self::assertStringStartsWith('2행: ', $parsed['errors'][0]);
        self::assertStringStartsWith('3행: ', $parsed['errors'][1]);
        self::assertStringStartsWith('4행: ', $parsed['errors'][2]);
        self::assertStringNotContainsString('01011112222', json_encode($parsed['errors']));
        $missing = $import->parse("상품명,구매자명\n상품,이름\n", 48);
        self::assertSame([], $missing['rows']);
        self::assertStringContainsString('휴대폰번호', $missing['errors'][0]);
        $tooMany = "상품명,구매자명,휴대폰번호,금액\n" . str_repeat("상품,이름,01011112222,1000\n", 501);
        self::assertStringContainsString('500행', $import->parse($tooMany, 48)['errors'][0]);
        self::assertStringContainsString('1MB', $import->parse(str_repeat('a', 1048577), 48)['errors'][0]);
        self::assertStringContainsString('비어', $import->parse('', 48)['errors'][0]);
    }

    #[DataProvider('connectionProvider')]
    public function testRememberAndConfirmCreateABatchOfRequests(array $config): void
    {
        $import = $this->setupApp($config);
        $parsed = $import->parse("상품명,구매자명,휴대폰번호,금액,결제기한(시간)\n수강료,홍길동,01011112222,50000,12\n교재비,김이니,01023457891,30000,\n", 48);
        self::assertSame([], $parsed['errors']);
        $token = $import->remember($parsed, '9월_청구.csv', false);
        self::assertSame('9월_청구.csv', $import->pending($token)['filename']);
        self::assertNull($import->pending('missing'));
        $summary = $import->confirm($token, 'test', 7, '운영자');
        self::assertSame(2, $summary['created']);
        self::assertSame(0, $summary['failed']);
        self::assertSame(0, $summary['sent']);
        self::assertNull($import->pending($token));
        $batch = $this->app->db()->selectOne('SELECT * FROM ' . $this->app->db()->table('initalk_batches') . ' WHERE id = ?', [$summary['batch_id']]);
        self::assertSame('9월_청구.csv', $batch['filename']);
        self::assertSame(2, (int) $batch['total']);
        $rows = $this->app->initalk()->requests->search(['environment' => 'test', 'batch' => $summary['batch_id']], 1);
        self::assertSame(2, $rows['total']);
        $byName = array_column($rows['items'], null, 'buyer_name');
        self::assertSame(Clock::timestamp() + 12 * 3600, $byName['홍길동']['expires_at']);
        self::assertSame(Clock::timestamp() + 48 * 3600, $byName['김이니']['expires_at']);
        Clock::freeze('2026-09-15 03:11:00');
        self::assertNull($import->pending($token));
    }
}
```

`CsvImport`의 생성자는 `(App $app, ?Requests $requests, ?Notifier $notifier, ?Settings $settings)`로 두어 파싱만 하는 테스트가 DB 없이 만들 수 있게 한다. `Service`는 세 의존성을 모두 넘긴다.

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Initalk/CsvImportTest.php 2>&1 | tail -5
```

- [ ] **Step 3: `CsvImport`**

`src/Initalk/CsvImport.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

/** CSV 일괄등록: 파싱·검증 → 세션 미리보기 → 확정 생성(+즉시 발송). */
final class CsvImport
{
    public const MAX_BYTES = 1048576;
    public const MAX_ROWS = 500;
    public const TTL = 600;
    public const COLUMNS = ['상품명' => 'product_name', '상품상세' => 'product_detail', '구매자명' => 'buyer_name', '휴대폰번호' => 'phone', '금액' => 'amount', '결제기한(시간)' => 'expiry_hours'];
    private const REQUIRED = ['product_name', 'buyer_name', 'phone', 'amount'];

    public function __construct(private App $app, private ?Requests $requests, private ?Notifier $notifier, private ?Settings $settings)
    {
    }

    public static function sample(): string
    {
        return "\xEF\xBB\xBF상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)\n"
            . "플로럴 핸드크림 30ml,향기 좋은 핸드크림,김이니,010-2345-7891,15800,48\n"
            . "9월 수강료,\"수학, 영어\",홍길동,010-1111-2222,120000,\n";
    }

    /** @return array{rows:list<array>,errors:list<string>,total:int} */
    public function parse(string $contents, int $defaultHours): array
    {
        if ($contents === '') return ['rows' => [], 'errors' => ['파일이 비어 있습니다.'], 'total' => 0];
        if (strlen($contents) > self::MAX_BYTES) return ['rows' => [], 'errors' => ['파일은 1MB 이하여야 합니다.'], 'total' => 0];
        if (str_starts_with($contents, "\xEF\xBB\xBF")) $contents = substr($contents, 3);
        if (!mb_check_encoding($contents, 'UTF-8')) $contents = (string) mb_convert_encoding($contents, 'UTF-8', 'CP949');
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        while ($lines !== [] && trim((string) end($lines)) === '') array_pop($lines);
        if ($lines === []) return ['rows' => [], 'errors' => ['파일이 비어 있습니다.'], 'total' => 0];
        $header = array_map(static fn ($cell): string => trim((string) $cell), str_getcsv((string) array_shift($lines)));
        $columns = [];
        foreach ($header as $index => $name) if (isset(self::COLUMNS[$name])) $columns[self::COLUMNS[$name]] = $index;
        $missing = array_diff(self::REQUIRED, array_keys($columns));
        if ($missing !== []) {
            $labels = array_map(static fn (string $key): string => array_search($key, self::COLUMNS, true), $missing);
            return ['rows' => [], 'errors' => ['필수 열이 없습니다: ' . implode(', ', $labels) . '. 샘플 파일의 머리글을 사용해 주세요.'], 'total' => 0];
        }
        if (count($lines) > self::MAX_ROWS) return ['rows' => [], 'errors' => ['한 번에 최대 500행까지 등록할 수 있습니다.'], 'total' => count($lines)];
        $rows = [];
        $errors = [];
        $total = 0;
        foreach ($lines as $offset => $line) {
            if (trim($line) === '') continue;
            $total++;
            $cells = str_getcsv($line);
            $input = [];
            foreach ($columns as $key => $index) $input[$key] = isset($cells[$index]) ? trim((string) $cells[$index]) : '';
            try {
                $rows[] = ['line' => $offset + 2] + Requests::normalize($input, $defaultHours);
            } catch (DomainError $e) {
                $errors[] = ($offset + 2) . '행: ' . implode(' ', array_values($e->details() ?: [$e->getMessage()]));
            }
        }
        return ['rows' => $rows, 'errors' => $errors, 'total' => $total];
    }

    public function remember(array $parsed, string $filename, bool $sendNow): string
    {
        $token = bin2hex(random_bytes(16));
        $pending = is_array($_SESSION['initalk_imports'] ?? null) ? $_SESSION['initalk_imports'] : [];
        foreach ($pending as $key => $entry) if (($entry['expires'] ?? 0) < Clock::timestamp()) unset($pending[$key]);
        if (count($pending) >= 3) array_shift($pending);
        $pending[$token] = ['expires' => Clock::timestamp() + self::TTL, 'filename' => mb_substr($filename, 0, 200), 'send' => $sendNow, 'rows' => $parsed['rows']];
        $_SESSION['initalk_imports'] = $pending;
        return $token;
    }

    public function pending(string $token): ?array
    {
        $entry = $_SESSION['initalk_imports'][$token] ?? null;
        return is_array($entry) && ($entry['expires'] ?? 0) >= Clock::timestamp() ? $entry : null;
    }

    /** @return array{batch_id:string,created:int,failed:int,sent:int,errors:list<string>} */
    public function confirm(string $token, string $environment, int $actorId, string $actor): array
    {
        $pending = $this->pending($token);
        if ($pending === null) throw DomainError::validation(['import' => '미리보기가 만료되었습니다. 파일을 다시 올려 주세요.']);
        unset($_SESSION['initalk_imports'][$token]);
        $requests = $this->requests ?? throw DomainError::internal('요청 저장소가 없습니다.');
        $config = ($this->settings ?? throw DomainError::internal('설정이 없습니다.'))->read();
        $batchId = bin2hex(random_bytes(16));
        $db = $this->app->db();
        $db->insert('initalk_batches', ['id' => $batchId, 'filename' => $pending['filename'], 'total' => count($pending['rows']), 'created' => 0, 'failed' => 0,
            'errors' => '[]', 'created_by' => $actorId, 'created_at' => Clock::timestamp()]);
        $created = [];
        $errors = [];
        foreach ($pending['rows'] as $row) {
            $input = ['product_name' => $row['product_name'], 'product_detail' => $row['product_detail'], 'buyer_name' => $row['buyer_name'],
                'phone' => $row['phone'], 'amount' => (string) $row['amount'], 'expiry_hours' => (string) $row['expiry_hours']];
            try {
                $created[] = $requests->create($input, $environment, $config['expiry_hours'], $actorId, $actor, $batchId)['id'];
            } catch (DomainError $e) {
                $errors[] = $row['line'] . '행: ' . ($e->status() >= 500 ? '생성하지 못했습니다.' : implode(' ', array_values($e->details() ?: [$e->getMessage()])));
            }
        }
        $sent = 0;
        if ($pending['send'] && $created !== [] && $this->notifier !== null) {
            $summary = $this->notifier->sendMany($created, $actor);
            $sent = $summary['sent'];
            foreach ($summary['errors'] as $id => $message) $errors[] = '발송 실패 ' . substr($id, 0, 8) . '…: ' . $message;
        }
        $db->update('initalk_batches', ['created' => count($created), 'failed' => count($pending['rows']) - count($created), 'errors' => json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            'id = :id', ['id' => $batchId]);
        return ['batch_id' => $batchId, 'created' => count($created), 'failed' => count($pending['rows']) - count($created), 'sent' => $sent, 'errors' => $errors];
    }
}
```

`src/Initalk/Service.php`에 `public readonly CsvImport $import;`와 생성자 안 `$this->import = new CsvImport($app, $this->requests, $this->notifier, $this->settings);`를 추가한다.

- [ ] **Step 4: 화면·라우트**

`src/Web/Controller/InitalkAdminController.php`에 추가:

```php
    public function importForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        return $this->render($request, $response, 'import', ['preview' => null, 'parse_errors' => []]);
    }

    public function import(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $upload = $request->getUploadedFiles()['file'] ?? null;
        $service = $this->app->initalk();
        if (!$upload instanceof \Psr\Http\Message\UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
            return $this->render($request, $response->withStatus(422), 'import', ['preview' => null, 'parse_errors' => [], 'errors' => ['CSV 파일을 선택해 주세요. 파일이 크면 PHP 업로드 용량 제한을 확인해 주세요.']]);
        }
        if (($upload->getSize() ?? 0) > \GnuCms\Initalk\CsvImport::MAX_BYTES) {
            return $this->render($request, $response->withStatus(422), 'import', ['preview' => null, 'parse_errors' => [], 'errors' => ['파일은 1MB 이하여야 합니다.']]);
        }
        $parsed = $service->import->parse((string) $upload->getStream()->getContents(), $service->settings->read()['expiry_hours']);
        if ($parsed['errors'] !== [] || $parsed['rows'] === []) {
            $errors = $parsed['rows'] === [] && $parsed['errors'] === [] ? ['등록할 행이 없습니다.'] : [];
            return $this->render($request, $response->withStatus(422), 'import', ['preview' => null, 'parse_errors' => $parsed['errors'], 'errors' => $errors, 'total' => $parsed['total']]);
        }
        $token = $service->import->remember($parsed, (string) ($upload->getClientFilename() ?? 'upload.csv'), ($input['send_now'] ?? '') === '1');
        return $this->render($request, $response, 'import', ['preview' => ['token' => $token, 'rows' => $parsed['rows'], 'sum' => array_sum(array_column($parsed['rows'], 'amount')),
            'send' => ($input['send_now'] ?? '') === '1', 'filename' => (string) ($upload->getClientFilename() ?? 'upload.csv')], 'parse_errors' => []]);
    }

    public function importConfirm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $service = $this->app->initalk();
        $config = $service->settings->read();
        try {
            $summary = ExecutionLock::run($this->app->storageDir(), fn (): array => $service->import->confirm(is_string($input['token'] ?? null) ? $input['token'] : '', $config['environment'], $this->actorId(), $this->actor()));
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status()), 'import', ['preview' => null, 'parse_errors' => [], 'errors' => $this->errors($e)]);
        }
        return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $config['environment'], 'batch' => $summary['batch_id'],
            'imported' => $summary['created'], 'failed' => $summary['failed'], 'sent' => $summary['sent']]);
    }

    public function importSample(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $response->getBody()->write(\GnuCms\Initalk\CsvImport::sample());
        return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="initalk-sample.csv"')->withHeader('Cache-Control', 'no-store');
    }
```

`templates/default/admin/initalk/requests.php`의 `$notice` `match`에 다음 분기를 `isset($query['sent'])` 앞에 추가한다:

```php
    isset($query['imported']) => 'CSV로 ' . (int) $query['imported'] . '건을 만들었습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 실패 ' . (int) $query['failed'] . '건.' : '') . ((int) ($query['sent'] ?? 0) > 0 ? ' 알림톡 ' . (int) $query['sent'] . '건 발송.' : ''),
```

`templates/default/admin/initalk/_nav.php`의 `$tabs` 배열에서 `['new', …]` 다음에 `['import', 'admin.initalk.import', '일괄등록']`를 추가한다.

`templates/default/admin/initalk/import.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>CSV 일괄등록 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'import', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<?php if ($preview === null): ?>
<section class="card card-body extension-panel"><h2 class="card-title">CSV 일괄등록</h2>
<p>샘플 파일의 머리글(상품명, 상품상세, 구매자명, 휴대폰번호, 금액, 결제기한(시간))을 그대로 쓰면 됩니다. 열 순서는 바꿔도 되고 결제기한(시간)은 비우면 기본값(<?= (int) $config['expiry_hours'] ?>시간)입니다. UTF-8 또는 엑셀 저장(CP949) 파일, 1MB·500행 이하.</p>
<p><a class="btn btn-sm btn-outline" href="<?= $this->url('admin.initalk.import.sample') ?>">업로드 샘플 다운로드</a></p>
<?php if ($parse_errors !== []): ?><div class="alert alert-error" role="alert"><strong>검증에 실패한 행이 있어 등록하지 않았습니다. (<?= count($parse_errors) ?>건)</strong><ul><?php foreach ($parse_errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form method="post" action="<?= $this->url('admin.initalk.import') ?>" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="file">CSV 파일</label><input class="file-input file-input-bordered" type="file" id="file" name="file" accept=".csv,text/csv" required>
<label class="extension-label"><input class="checkbox" type="checkbox" name="send_now" value="1" checked> 결제 알림톡 즉시 전송하기</label>
<div class="card-actions form-actions"><button class="btn btn-primary">검증하고 미리보기</button></div></form></section>
<?php else: ?>
<section class="card card-body extension-panel"><h2 class="card-title">미리보기 <small><?= $this->e($preview['filename']) ?> · <?= count($preview['rows']) ?>건 · 합계 <?= number_format($preview['sum']) ?>원</small></h2>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th>행</th><th>상품명</th><th>상품 상세</th><th>구매자명</th><th>휴대폰</th><th class="num">금액</th><th>기한(시간)</th></tr></thead><tbody>
<?php foreach ($preview['rows'] as $row): ?><tr><td><?= (int) $row['line'] ?></td><td><?= $this->e($row['product_name']) ?></td><td><?= $this->e($row['product_detail']) ?></td><td><?= $this->e($row['buyer_name']) ?></td><td><?= $this->e(\GnuCms\Initalk\Phone::format($row['phone'])) ?></td><td class="num"><?= number_format($row['amount']) ?>원</td><td><?= (int) $row['expiry_hours'] ?></td></tr><?php endforeach ?>
</tbody></table></div>
<p><?= $preview['send'] ? '거래등록 후 각 건에 결제 알림톡을 바로 보냅니다.' : '거래만 등록하고 알림톡은 통합조회에서 따로 보냅니다.' ?> 미리보기는 10분 동안 유효합니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.import.confirm') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="token" value="<?= $this->e($preview['token']) ?>">
<div class="card-actions form-actions"><button class="btn btn-primary">거래등록</button><a class="btn btn-ghost" href="<?= $this->url('admin.initalk.import') ?>">다시 올리기</a></div></form></section>
<?php endif ?>
<?php $this->stop() ?>
```

`src/Web/Routes.php`: `admin.initalk.requests.new` POST 다음에 추가(정적 경로이므로 `{id}` 라우트보다 앞):

```php
        $slim->get('/admin/initalk/requests/import', [$initalk, 'importForm'])->setName('admin.initalk.import');
        $slim->post('/admin/initalk/requests/import', [$initalk, 'import']);
        $slim->post('/admin/initalk/requests/import/confirm', [$initalk, 'importConfirm'])->setName('admin.initalk.import.confirm');
        $slim->get('/admin/initalk/requests/import/sample', [$initalk, 'importSample'])->setName('admin.initalk.import.sample');
```

`tests/Web/InitalkAdminTest.php`에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testCsvImportPreviewsThenCreatesAndSends(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $sample = $this->body($this->get($this->app, '/admin/initalk/requests/import/sample'));
        self::assertStringStartsWith("\xEF\xBB\xBF상품명", $sample);
        $csv = "상품명,구매자명,휴대폰번호,금액\n수강료,홍길동,010-2345-7891,50000\n교재비,김이니,010-2345-7891,\"30,000\"\n";
        $tmp = tempnam(sys_get_temp_dir(), 'gnucms-csv-');
        file_put_contents($tmp, $csv);
        $file = new \Slim\Psr7\UploadedFile($tmp, '9월.csv', 'text/csv', strlen($csv), UPLOAD_ERR_OK);
        $preview = $this->postWithFiles($this->app, '/admin/initalk/requests/import', ['csrf_token' => $this->csrf(), 'send_now' => '1'], ['file' => $file]);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('2건 · 합계 80,000원', $this->body($preview));
        preg_match('/name="token" value="([a-f0-9]{32})"/', $this->body($preview), $m);
        $confirmed = $this->post($this->app, '/admin/initalk/requests/import/confirm', ['csrf_token' => $this->csrf(), 'token' => $m[1]]);
        self::assertSame(303, $confirmed->getStatusCode());
        parse_str((string) parse_url($confirmed->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertSame('2', $query['imported']);
        self::assertSame('2', $query['sent']);
        self::assertSame(2, $this->messagingHttp->count('/v3/message'));
        self::assertSame(2, $this->app->initalk()->requests->search(['environment' => 'test', 'batch' => $query['batch']], 1)['total']);
        self::assertSame(422, $this->post($this->app, '/admin/initalk/requests/import/confirm', ['csrf_token' => $this->csrf(), 'token' => $m[1]])->getStatusCode());
        file_put_contents($tmp, "상품명,구매자명,휴대폰번호,금액\n,홍길동,010-2345-7891,50000\n");
        $bad = $this->postWithFiles($this->app, '/admin/initalk/requests/import', ['csrf_token' => $this->csrf()], ['file' => new \Slim\Psr7\UploadedFile($tmp, 'bad.csv', 'text/csv', 60, UPLOAD_ERR_OK)]);
        self::assertSame(422, $bad->getStatusCode());
        self::assertStringContainsString('2행: 상품명', $this->body($bad));
        @unlink($tmp);
    }
```

- [ ] **Step 5: 검증·커밋**

```bash
php -l src/Initalk/CsvImport.php && php -l src/Web/Controller/InitalkAdminController.php && php -l templates/default/admin/initalk/import.php
./vendor/bin/phpunit tests/Initalk/CsvImportTest.php tests/Web/InitalkAdminTest.php
git add src/Initalk src/Web/Controller/InitalkAdminController.php src/Web/Routes.php templates/default/admin/initalk tests/Initalk/CsvImportTest.php tests/Web/InitalkAdminTest.php
git commit -m "feat: add CSV bulk registration for INITalk payment requests

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 9: 대시보드·매출/정산·CSV 내보내기·CLI

**Files:**
- Create: `src/Initalk/Sales.php`, `templates/default/admin/initalk/dashboard.php`, `templates/default/admin/initalk/sales.php`, `bin/initalk.php`
- Modify: `src/Initalk/Requests.php`(`needingSync`), `src/Initalk/Service.php`, `src/Web/Controller/InitalkAdminController.php`(`index` 교체, `sales`, `salesExport`), `src/Web/Routes.php`, `templates/default/admin/initalk/_nav.php`
- Test: `tests/Initalk/SalesTest.php`, `tests/Initalk/CliTest.php`, `tests/Web/InitalkAdminTest.php`(메서드 추가)

**Interfaces:**
- Consumes: `Ledger::between`, `Requests::counts/recent/expire/purge`, `Checkout::sync`.
- Produces: `Sales::monthRange(int $year, int $month): array{0:int,1:int}`(KST 월 시작·끝 타임스탬프, static), `Sales::daily(string $env, int $from, int $until): list<array{date,approve_count,approve_amount,refund_count,refund_amount,net}>`, `Sales::summary(string $env, int $from, int $until): array{approve_count,approve_amount,refund_count,refund_amount,net}`, `Sales::calendar(string $env, int $year, int $month, int $settlementDays): array<string,int>`(지급예정일 `Y-m-d` → 순액), `Sales::expectedPayout(string $env, int $settlementDays, int $now): int`, `Sales::csv(string $env, int $from, int $until): string`; `Requests::needingSync(int $window): list<string>`; 라우트 `admin.initalk`(대시보드), `admin.initalk.sales`(GET `/admin/initalk/sales`, 쿼리 `environment`, `month=YYYY-MM` 또는 `from`/`until`), `admin.initalk.sales.export`(GET `/admin/initalk/sales/export`, `text/csv`); CLI `php bin/initalk.php expire|sync|purge [--config=경로]`; `Service->sales`.

- [ ] **Step 1: 테스트를 쓴다**

`tests/Initalk/SalesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Sales;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SalesTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-sales-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'il' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-15 03:00:00');
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function kst(string $datetime): int { return (new \DateTimeImmutable($datetime, new \DateTimeZone('Asia/Seoul')))->getTimestamp(); }

    private function paid(string $env, int $amount, int $paidAt, string $tid): string
    {
        $request = $this->app->initalk()->requests->create(['product_name' => '상품', 'product_detail' => '', 'buyer_name' => '구매자', 'phone' => '01023457891', 'amount' => (string) $amount], $env, 48, 1, '운영자');
        $this->app->initalk()->requests->markPaid($request['id'], $paidAt, $tid, 'customer');
        $this->app->initalk()->ledger->record('approve', $request['id'], $amount, $paidAt, $tid);
        return $request['id'];
    }

    public function testMonthRangeUsesSeoulTime(): void
    {
        [$from, $until] = Sales::monthRange(2026, 9);
        self::assertSame($this->kst('2026-09-01 00:00:00'), $from);
        self::assertSame($this->kst('2026-09-30 23:59:59'), $until);
        [$from, $until] = Sales::monthRange(2026, 2);
        self::assertSame($this->kst('2026-02-28 23:59:59'), $until);
    }

    #[DataProvider('connectionProvider')]
    public function testDailySummaryCalendarPayoutAndCsv(array $config): void
    {
        $this->setupApp($config);
        $sales = $this->app->initalk()->sales;
        $a = $this->paid('test', 15800, $this->kst('2026-09-02 10:00:00'), 'TIDA');
        $b = $this->paid('test', 50000, $this->kst('2026-09-02 15:00:00'), 'TIDB');
        $this->paid('test', 7000, $this->kst('2026-09-28 23:30:00'), 'TIDC');
        $this->paid('live', 99000, $this->kst('2026-09-03 09:00:00'), 'TIDL');
        $this->app->initalk()->ledger->record('refund', $b, 20000, $this->kst('2026-09-05 12:00:00'), 'TIDB-P1');
        [$from, $until] = Sales::monthRange(2026, 9);
        $daily = $sales->daily('test', $from, $until);
        self::assertSame(['2026-09-02', '2026-09-05', '2026-09-28'], array_column($daily, 'date'));
        self::assertSame(['date' => '2026-09-02', 'approve_count' => 2, 'approve_amount' => 65800, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 65800], $daily[0]);
        self::assertSame(['date' => '2026-09-05', 'approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 1, 'refund_amount' => 20000, 'net' => -20000], $daily[1]);
        self::assertSame(['approve_count' => 3, 'approve_amount' => 72800, 'refund_count' => 1, 'refund_amount' => 20000, 'net' => 52800], $sales->summary('test', $from, $until));
        self::assertSame([], $sales->daily('test', $this->kst('2026-10-01 00:00:00'), $this->kst('2026-10-31 23:59:59')));
        $calendar = $sales->calendar('test', 2026, 9, 3);
        self::assertSame(['2026-09-05' => 65800, '2026-09-08' => -20000], $calendar);
        self::assertSame(['2026-10-01' => 7000], $sales->calendar('test', 2026, 10, 3));
        Clock::freeze('2026-09-06 00:00:00');
        self::assertSame(-20000 + 7000, $sales->expectedPayout('test', 3, Clock::timestamp()));
        Clock::freeze('2026-09-01 00:00:00');
        self::assertSame(52800, $sales->expectedPayout('test', 3, Clock::timestamp()));
        $csv = $sales->csv('test', $from, $until);
        self::assertStringStartsWith("\xEF\xBB\xBF일시,주문번호,상품명,구매자명,휴대폰,구분,금액,참조\n", $csv);
        self::assertStringContainsString('2026-09-02 10:00,IT-20260915-0001,상품,구매자,010-****-7891,승인,15800,TIDA', $csv);
        self::assertStringContainsString(',환불,-20000,TIDB-P1', $csv);
        self::assertStringNotContainsString('TIDL', $csv);
        self::assertStringNotContainsString('01023457891', $csv);
    }
}
```

`tests/Initalk/CliTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Initalk\Status;
use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-cli-' . bin2hex(random_bytes(5));
        mkdir($this->root . '/storage', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function run(string $command, string $configFile): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/initalk.php', $command, '--config=' . $configFile], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testExpireAndPurgeRunAgainstTheConfiguredDatabase(): void
    {
        $config = ['db' => ['dsn' => 'sqlite:' . $this->root . '/board.sqlite', 'username' => null, 'password' => null], 'storage' => ['dir' => $this->root . '/storage'],
            'auth' => ['secret' => bin2hex(random_bytes(32))], 'app' => ['url' => 'https://shop.example.test']];
        $configFile = $this->root . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
        $app = new App($config);
        (new Schema($app->db()))->create();
        $request = $app->initalk()->requests->create(['product_name' => '상품', 'product_detail' => '', 'buyer_name' => '구매자', 'phone' => '01023457891', 'amount' => '1000'], 'test', 48, 1, '운영자');
        $app->db()->update('initalk_requests', ['expires_at' => time() - 10], 'id = :id', ['id' => $request['id']]);
        [$code, $out] = $this->run('expire', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('expire: 1건', $out);
        self::assertSame(Status::EXPIRED, (new App($config))->initalk()->requests->find($request['id'])['status']);
        $app->db()->update('initalk_requests', ['status_changed_at' => time() - 100 * 86400], 'id = :id', ['id' => $request['id']]);
        [$code, $out] = $this->run('purge', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('purge: 1건', $out);
        [$code, $out] = $this->run('sync', $configFile);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('sync: 0건', $out);
        [$code, , $err] = $this->run('bogus', $configFile);
        self::assertSame(1, $code);
        self::assertStringContainsString('사용법', $err);
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Initalk/SalesTest.php tests/Initalk/CliTest.php 2>&1 | tail -5
```

- [ ] **Step 3: `Sales`와 `Requests::needingSync`**

`src/Initalk/Sales.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Initalk;

/** 확정 원장으로 만드는 매출·정산 집계. 영업일·공휴일 계산은 하지 않는다(문서에 명시). */
final class Sales
{
    private const TZ = 'Asia/Seoul';

    public function __construct(private Ledger $ledger)
    {
    }

    private static function seoul(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(self::TZ));
    }

    /** @return array{0:int,1:int} */
    public static function monthRange(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new \DateTimeZone(self::TZ));
        return [$start->getTimestamp(), $start->modify('last day of this month')->setTime(23, 59, 59)->getTimestamp()];
    }

    public function daily(string $environment, int $from, int $until): array
    {
        $days = [];
        foreach ($this->ledger->between($environment, $from, $until) as $row) {
            $date = self::seoul((int) $row['at'])->format('Y-m-d');
            $days[$date] ??= ['date' => $date, 'approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 0];
            if ($row['kind'] === 'approve') {
                $days[$date]['approve_count']++;
                $days[$date]['approve_amount'] += (int) $row['amount'];
                $days[$date]['net'] += (int) $row['amount'];
            } else {
                $days[$date]['refund_count']++;
                $days[$date]['refund_amount'] += (int) $row['amount'];
                $days[$date]['net'] -= (int) $row['amount'];
            }
        }
        ksort($days);
        return array_values($days);
    }

    public function summary(string $environment, int $from, int $until): array
    {
        $summary = ['approve_count' => 0, 'approve_amount' => 0, 'refund_count' => 0, 'refund_amount' => 0, 'net' => 0];
        foreach ($this->daily($environment, $from, $until) as $day) foreach ($summary as $key => $value) $summary[$key] += $day[$key];
        return $summary;
    }

    /** 지급예정일(승인·환불일 + N일) 기준 순액. 해당 월에 지급예정일이 있는 원장만. */
    public function calendar(string $environment, int $year, int $month, int $settlementDays): array
    {
        [$from, $until] = self::monthRange($year, $month);
        $shift = $settlementDays * 86400;
        $calendar = [];
        foreach ($this->ledger->between($environment, $from - $shift, $until - $shift) as $row) {
            $date = self::seoul((int) $row['at'] + $shift)->format('Y-m-d');
            $calendar[$date] = ($calendar[$date] ?? 0) + ($row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount']);
        }
        ksort($calendar);
        return $calendar;
    }

    /** 아직 지급예정일이 오지 않은 원장의 순액. */
    public function expectedPayout(string $environment, int $settlementDays, int $now): int
    {
        $total = 0;
        foreach ($this->ledger->between($environment, $now - $settlementDays * 86400 + 1, $now + 366 * 86400) as $row) {
            $total += $row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount'];
        }
        return $total;
    }

    public function csv(string $environment, int $from, int $until): string
    {
        $lines = ["\xEF\xBB\xBF일시,주문번호,상품명,구매자명,휴대폰,구분,금액,참조"];
        foreach ($this->ledger->between($environment, $from, $until) as $row) {
            $cells = [self::seoul((int) $row['at'])->format('Y-m-d H:i'), $row['number'], $row['product_name'], $row['buyer_name'] !== '' ? $row['buyer_name'] : '보관 만료',
                $row['phone_mask'] !== '' ? $row['phone_mask'] : '보관 만료', $row['kind'] === 'approve' ? '승인' : '환불',
                (string) ($row['kind'] === 'approve' ? (int) $row['amount'] : -(int) $row['amount']), $row['reference']];
            $lines[] = implode(',', array_map(static fn (string $cell): string => preg_match('/[",\r\n]/', $cell) ? '"' . str_replace('"', '""', $cell) . '"' : $cell, $cells));
        }
        return implode("\n", $lines) . "\n";
    }
}
```

`src/Initalk/Requests.php`에 추가:

```php
    /** 결제창을 연 미결·확인 필요 건. CLI sync 대상. @return list<string> */
    public function needingSync(int $window): array
    {
        $now = Clock::timestamp();
        $rows = $this->db()->select('SELECT id FROM ' . $this->db()->table('initalk_requests')
            . " WHERE config_revision <> '' AND ((status IN ('created','waiting') AND checkout_started_at >= ?) OR needs_review = 1) ORDER BY updated_at LIMIT 500", [$now - $window]);
        return array_column($rows, 'id');
    }
```

`src/Initalk/Service.php`에 `public readonly Sales $sales;`와 `$this->sales = new Sales($this->ledger);`를 추가한다(`$this->ledger` 조립 뒤).

- [ ] **Step 4: 대시보드·매출 화면·CLI**

`src/Web/Controller/InitalkAdminController.php`: `index()`를 다음으로 교체하고 `sales`/`salesExport`를 추가한다.

```php
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $service = $this->app->initalk();
        $config = $service->settings->read();
        $service->requests->expire();
        $now = \GnuCms\Support\Clock::timestamp();
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('Asia/Seoul'));
        [$from, $until] = \GnuCms\Initalk\Sales::monthRange((int) $today->format('Y'), (int) $today->format('n'));
        return $this->render($request, $response, 'dashboard', ['environment' => $config['environment'], 'counts' => $service->requests->counts($config['environment']),
            'month' => $service->sales->summary($config['environment'], $from, $until), 'month_label' => $today->format('Y년 n월'),
            'payout' => $service->sales->expectedPayout($config['environment'], $config['settlement_days'], $now), 'recent' => $service->requests->recent($config['environment'], 10)]);
    }

    /** @return array{0:int,1:int,2:string,3:int,4:int} from, until, label, year, month */
    private function salesRange(array $query): array
    {
        $today = (new \DateTimeImmutable('@' . \GnuCms\Support\Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'));
        $from = is_string($query['from'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $query['from']) ? $query['from'] : null;
        $until = is_string($query['until'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $query['until']) ? $query['until'] : null;
        if ($from !== null && $until !== null) {
            $start = new \DateTimeImmutable($from . ' 00:00:00', new \DateTimeZone('Asia/Seoul'));
            $end = new \DateTimeImmutable($until . ' 23:59:59', new \DateTimeZone('Asia/Seoul'));
            if ($end < $start || $end->getTimestamp() - $start->getTimestamp() > 92 * 86400) throw DomainError::validation(['range' => '조회 기간은 92일 이내로 지정해 주세요.']);
            return [$start->getTimestamp(), $end->getTimestamp(), $from . ' ~ ' . $until, (int) $start->format('Y'), (int) $start->format('n')];
        }
        $month = is_string($query['month'] ?? null) && preg_match('/^\d{4}-\d{2}$/D', $query['month']) ? $query['month'] : $today->format('Y-m');
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        if ($monthNumber < 1 || $monthNumber > 12) throw DomainError::validation(['month' => '월을 확인해 주세요.']);
        [$start, $end] = \GnuCms\Initalk\Sales::monthRange($year, $monthNumber);
        return [$start, $end, $year . '년 ' . $monthNumber . '월', $year, $monthNumber];
    }

    public function sales(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $query = $request->getQueryParams();
        $service = $this->app->initalk();
        $config = $service->settings->read();
        $environment = in_array($query['environment'] ?? '', ['test', 'live'], true) ? $query['environment'] : 'live';
        [$from, $until, $label, $year, $month] = $this->salesRange($query);
        return $this->render($request, $response, 'sales', ['environment' => $environment, 'range_label' => $label, 'from' => $from, 'until' => $until,
            'month_value' => sprintf('%04d-%02d', $year, $month), 'daily' => $service->sales->daily($environment, $from, $until),
            'summary' => $service->sales->summary($environment, $from, $until), 'calendar' => $service->sales->calendar($environment, $year, $month, $config['settlement_days']),
            'calendar_days' => (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone('Asia/Seoul')))->format('t'),
            'calendar_first_weekday' => (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone('Asia/Seoul')))->format('w')]);
    }

    public function salesExport(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $query = $request->getQueryParams();
        $environment = in_array($query['environment'] ?? '', ['test', 'live'], true) ? $query['environment'] : 'live';
        [$from, $until] = $this->salesRange($query);
        $name = 'initalk-sales-' . date('Ymd', $from) . '-' . date('Ymd', $until) . '.csv';
        $response->getBody()->write($this->app->initalk()->sales->csv($environment, $from, $until));
        return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"')->withHeader('Cache-Control', 'no-store');
    }
```

`templates/default/admin/initalk/_nav.php`의 `$tabs`를 `[['dashboard', 'admin.initalk', '대시보드'], ['requests', …], ['new', …], ['import', …], ['sales', 'admin.initalk.sales', '매출·정산'], ['settings', …]]` 순서로 바꾼다.

`templates/default/admin/initalk/dashboard.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>대시보드 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'dashboard', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<div class="stats stats-grid initalk-counts"><?php foreach (['created' => 0, 'waiting' => 1, 'paid' => 2, 'expired' => 3, 'cancelled' => 4] as $key => $tone): ?><a class="stat" href="<?= $this->url('admin.initalk.requests', [], ['environment' => $environment, 'status' => $key]) ?>"><div class="stat-figure" data-tone="<?= $tone ?>" aria-hidden="true"><?= $this->icon('tag', 20) ?></div><div class="stat-title"><?= $this->e($status_labels[$key]) ?></div><div class="stat-value"><?= (int) $counts[$key] ?></div><div class="stat-desc">보기 <?= $this->icon('arrow-right', 12) ?></div></a><?php endforeach ?></div>
<div class="cols initalk-cols">
<section class="card card-body extension-panel"><h2 class="card-title"><?= $this->e($month_label) ?> 매출 현황</h2>
<dl class="initalk-dl"><dt>승인</dt><dd><?= (int) $month['approve_count'] ?>건 · <?= number_format($month['approve_amount']) ?>원</dd><dt>환불</dt><dd><?= (int) $month['refund_count'] ?>건 · <?= number_format($month['refund_amount']) ?>원</dd><dt>합계</dt><dd><strong><?= number_format($month['net']) ?>원</strong></dd></dl>
<p><a class="link" href="<?= $this->url('admin.initalk.sales', [], ['environment' => $environment]) ?>">매출·정산 보기</a></p></section>
<section class="card card-body extension-panel"><h2 class="card-title">지급예정금액</h2><p class="initalk-payout"><strong><?= number_format($payout) ?>원</strong></p><p class="muted">승인일 + <?= (int) $config['settlement_days'] ?>일 기준으로 아직 지급예정일이 오지 않은 순액입니다. 실제 PG 정산과 다를 수 있습니다.</p></section>
</div>
<section class="card card-body extension-panel"><h2 class="card-title">최근 결제 요청</h2><div class="table-wrap"><table class="table initalk-table"><thead><tr><th>주문번호</th><th>구매자</th><th>상품명</th><th class="num">금액</th><th>상태</th><th>등록</th></tr></thead><tbody>
<?php foreach ($recent as $item): ?><tr><td><a class="link" href="<?= $this->url('admin.initalk.request', ['id' => $item['id']]) ?>"><?= $this->e($item['number']) ?></a></td><td><?= $this->e($item['buyer_name'] !== '' ? $item['buyer_name'] : '보관 만료') ?></td><td><?= $this->e($item['product_name']) ?></td><td class="num"><?= number_format($item['amount']) ?>원</td><td><?= $this->e($item['status_label']) ?></td><td><?= $this->e($time($item['created_at'])) ?></td></tr><?php endforeach ?>
<?php if ($recent === []): ?><tr><td colspan="6">아직 결제 요청이 없습니다. <a class="link" href="<?= $this->url('admin.initalk.requests.new') ?>">첫 결제를 만들어 보세요.</a></td></tr><?php endif ?>
</tbody></table></div></section>
<?php $this->stop() ?>
```

`templates/default/admin/initalk/sales.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>매출·정산 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'sales', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<section class="card card-body extension-panel"><h2 class="card-title">매출 조회 <small><?= $this->e($range_label) ?> · <?= $environment === 'live' ? '운영' : '테스트' ?></small></h2>
<form method="get" action="<?= $this->url('admin.initalk.sales') ?>" class="initalk-filters">
<label class="extension-label">환경<select class="select select-bordered select-block" name="environment"><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영</option><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트</option></select></label>
<label class="extension-label">월<input class="input input-bordered input-block" type="month" name="month" value="<?= $this->e($month_value) ?>"></label>
<label class="extension-label">시작일 (직접 지정)<input class="input input-bordered input-block" type="date" name="from" value="<?= $this->e($query['from'] ?? '') ?>"></label>
<label class="extension-label">종료일<input class="input input-bordered input-block" type="date" name="until" value="<?= $this->e($query['until'] ?? '') ?>"></label>
<div class="card-actions form-actions"><button class="btn btn-primary">조회</button><a class="btn btn-outline btn-sm" href="<?= $this->url('admin.initalk.sales.export', [], array_filter(['environment' => $environment, 'month' => $query['month'] ?? '', 'from' => $query['from'] ?? '', 'until' => $query['until'] ?? ''])) ?>">CSV 내보내기</a></div></form>
<div class="stats stats-grid initalk-counts"><div class="stat"><div class="stat-title">승인</div><div class="stat-value"><?= number_format($summary['approve_amount']) ?>원</div><div class="stat-desc"><?= (int) $summary['approve_count'] ?>건</div></div><div class="stat"><div class="stat-title">환불</div><div class="stat-value"><?= number_format($summary['refund_amount']) ?>원</div><div class="stat-desc"><?= (int) $summary['refund_count'] ?>건</div></div><div class="stat"><div class="stat-title">순매출</div><div class="stat-value"><?= number_format($summary['net']) ?>원</div></div></div>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th>일자</th><th class="num">승인 건수</th><th class="num">승인 금액</th><th class="num">환불 건수</th><th class="num">환불 금액</th><th class="num">순매출</th></tr></thead><tbody>
<?php foreach ($daily as $day): ?><tr><td><?= $this->e($day['date']) ?></td><td class="num"><?= (int) $day['approve_count'] ?></td><td class="num"><?= number_format($day['approve_amount']) ?></td><td class="num"><?= (int) $day['refund_count'] ?></td><td class="num"><?= number_format($day['refund_amount']) ?></td><td class="num"><?= number_format($day['net']) ?></td></tr><?php endforeach ?>
<?php if ($daily === []): ?><tr><td colspan="6">이 기간의 확정 결제가 없습니다.</td></tr><?php endif ?></tbody></table></div></section>
<section class="card card-body extension-panel"><h2 class="card-title">정산 캘린더 <small>지급예정일 = 승인·환불일 + <?= (int) $config['settlement_days'] ?>일</small></h2>
<p class="muted">영업일·공휴일은 계산하지 않습니다. 실제 PG 정산서와 다를 수 있습니다.</p>
<table class="table initalk-calendar"><thead><tr><?php foreach (['일', '월', '화', '수', '목', '금', '토'] as $w): ?><th><?= $w ?></th><?php endforeach ?></tr></thead><tbody><tr>
<?php for ($i = 0; $i < $calendar_first_weekday; $i++): ?><td></td><?php endfor ?>
<?php for ($day = 1; $day <= $calendar_days; $day++): $date = $month_value . '-' . sprintf('%02d', $day); ?><td><div class="initalk-cal-day"><?= $day ?></div><?php if (isset($calendar[$date])): ?><div class="initalk-cal-amount<?= $calendar[$date] < 0 ? ' is-negative' : '' ?>"><?= number_format($calendar[$date]) ?></div><?php endif ?></td><?php if (($day + $calendar_first_weekday) % 7 === 0 && $day < $calendar_days): ?></tr><tr><?php endif ?><?php endfor ?>
</tr></tbody></table></section>
<?php $this->stop() ?>
```

`www/themes/default/initalk.css`에 추가:

```css
.initalk-admin .initalk-payout{font-size:1.75rem;margin:.25rem 0}
.initalk-admin .initalk-calendar td{vertical-align:top;height:4rem;width:14.28%}
.initalk-admin .initalk-cal-day{font-size:12px;color:var(--bc-soft)}
.initalk-admin .initalk-cal-amount{font-weight:700;font-size:13px}
.initalk-admin .initalk-cal-amount.is-negative{color:var(--color-error)}
```

`src/Web/Routes.php`: `admin.initalk.purge` 다음에

```php
        $slim->get('/admin/initalk/sales', [$initalk, 'sales'])->setName('admin.initalk.sales');
        $slim->get('/admin/initalk/sales/export', [$initalk, 'salesExport'])->setName('admin.initalk.sales.export');
```

`bin/initalk.php`:

```php
<?php

declare(strict_types=1);

/**
 * 이니톡 결제 유지보수 CLI. 스케줄러(cron)에서 주기적으로 실행한다.
 *
 *   php bin/initalk.php expire   결제기한이 지난 요청을 만료시킨다(결제창을 연 지 30분 이내 건은 보호)
 *   php bin/initalk.php sync     최근 7일 안에 결제창을 연 미결 건과 확인 필요 건을 결제사에 조회한다
 *   php bin/initalk.php purge    종료 90일이 지난 요청의 구매자명·번호를 정리한다
 *   --config=/경로/config.php    다른 설정 파일을 쓴다
 */

use GnuCms\App;
use GnuCms\Payment\ExecutionLock;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("명령줄에서만 실행할 수 있습니다.\n");
}

$configFile = __DIR__ . '/../config/config.php';
$command = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--config=')) $configFile = substr($argument, 9);
    else $command = $argument;
}
if (!in_array($command, ['expire', 'sync', 'purge'], true)) {
    fwrite(STDERR, "사용법: php bin/initalk.php expire|sync|purge [--config=경로]\n");
    exit(1);
}
if (!is_file($configFile)) {
    fwrite(STDERR, "설정 파일을 찾을 수 없습니다: {$configFile}\n");
    exit(1);
}
$config = require $configFile;
if (!is_array($config) || !isset($config['db'])) {
    fwrite(STDERR, "설정 파일에 db 항목이 없습니다: {$configFile}\n");
    exit(1);
}

try {
    $app = new App($config, $configFile);
    $service = $app->initalk();
    if ($command === 'expire') {
        $count = $service->requests->expire(1000);
    } elseif ($command === 'purge') {
        $count = $service->requests->purge(1000);
    } else {
        $count = 0;
        foreach ($service->requests->needingSync(7 * 86400) as $id) {
            try {
                ExecutionLock::run($app->storageDir(), static fn () => $service->checkout->sync($id, 'cli'));
                $count++;
            } catch (Throwable $e) {
                fwrite(STDERR, "  ! " . substr($id, 0, 8) . "…: 조회하지 못했습니다.\n");
            }
        }
    }
    echo "{$command}: {$count}건 처리\n";
} catch (Throwable $e) {
    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    exit(1);
}
```

`tests/Web/InitalkAdminTest.php`에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testDashboardAndSalesScreens(array $config): void
    {
        $this->setupApp($config);
        $this->signIn(true);
        $request = $this->app->initalk()->requests->create(['product_name' => '수강료', 'product_detail' => '', 'buyer_name' => '홍길동', 'phone' => '01023457891', 'amount' => '50000'], 'test', 48, 1, '운영자');
        $this->app->initalk()->requests->markPaid($request['id'], Clock::timestamp(), 'TID1', 'customer');
        $this->app->initalk()->ledger->record('approve', $request['id'], 50000, Clock::timestamp(), 'TID1');
        $dashboard = $this->body($this->get($this->app, '/admin/initalk'));
        self::assertStringContainsString('2026년 9월 매출 현황', $dashboard);
        self::assertStringContainsString('50,000원', $dashboard);
        self::assertStringContainsString($request['number'], $dashboard);
        $sales = $this->body($this->get($this->app, '/admin/initalk/sales', ['environment' => 'test', 'month' => '2026-09']));
        self::assertStringContainsString('2026-09-15', $sales);
        self::assertStringContainsString('정산 캘린더', $sales);
        self::assertStringContainsString('<div class="initalk-cal-amount">50,000</div>', $sales);
        $csv = $this->get($this->app, '/admin/initalk/sales/export', ['environment' => 'test', 'month' => '2026-09']);
        self::assertSame('text/csv; charset=utf-8', $csv->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment; filename="initalk-sales-20260901-20260930.csv"', $csv->getHeaderLine('Content-Disposition'));
        self::assertStringContainsString($request['number'] . ',수강료,홍길동,010-****-7891,승인,50000,TID1', $this->body($csv));
        self::assertSame(422, $this->get($this->app, '/admin/initalk/sales', ['from' => '2026-01-01', 'until' => '2026-06-30'])->getStatusCode());
    }
```

(정산 주기 3일이므로 9월 18일 셀에 50,000이 표시된다.)

- [ ] **Step 5: 검증·커밋**

```bash
php -l src/Initalk/Sales.php && php -l bin/initalk.php && php -l src/Web/Controller/InitalkAdminController.php
for f in templates/default/admin/initalk/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Initalk tests/Web/InitalkAdminTest.php
./vendor/bin/phpunit
git add src/Initalk bin/initalk.php src/Web/Controller/InitalkAdminController.php src/Web/Routes.php templates/default/admin/initalk www/themes/default/initalk.css tests/Initalk tests/Web/InitalkAdminTest.php
git commit -m "feat: add INITalk dashboard, sales and settlement views, CSV export and maintenance CLI

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

### Task 10: 문서·스펙 갱신과 최종 검증

**Files:**
- Create: `docs/initalk.md`
- Modify: `AGENTS.md`(기능 지도), `README.md`(알림톡·결제 절), `docs/messaging.md`(이니톡 결제 연결 문장), `docs/superpowers/specs/2026-09-14-initalk-design.md`(§2 VERSION, §4·§5 라우트 표, §9 QR 검증 방법)

- [ ] **Step 1: `docs/initalk.md`를 쓴다**

```markdown
# 이니톡 결제

GNUCMS 코어에 내장된 "알림톡 결제 링크" 기능이다. 관리자가 결제 요청(상품·구매자·휴대폰·금액·기한)을 만들면
비즈뿌리오 알림톡으로 `/pay/{토큰}` 링크를 보내고, 고객은 그 페이지에서 KG이니시스 카드결제(간편결제 포함)로 결제한다.
KG이니시스의 **이니톡결제 특약 서비스가 아니다**. 이니톡은 이니시스가 자체 채널로 알림톡을 보내는 노코드 서비스이며
가맹점 서버용 공개 API가 없다. GNUCMS는 같은 흐름을 비즈뿌리오 알림톡과 이니시스 일반결제(MID)로 직접 구현한다.
따라서 이니톡 특약·건당 이용료 없이 일반 이니시스 온라인결제 가맹점과 비즈뿌리오 계정만 있으면 된다.

## 준비 절차

1. **이니시스**: 온라인결제 가맹점(MID) 계약 후 상점관리자에서 웹표준 SignKey, 모바일 Hash Key, INIAPI Key를 발급받는다.
   결제 요청 서버 IP를 이니시스에 등록한다. 테스트 상점으로 먼저 검증한다.
2. **설정 → 결제**(`/admin/settings/payment`): 환경(테스트/운영)별로 MID·키·서버 IPv4를 저장하고 **API 실행 허용**을 켠다.
3. **비즈뿌리오**: 계정·발신프로필·알림톡 템플릿 검수를 마친다([알림톡·문자 안내](messaging.md)).
4. **설정 → 알림톡·문자**(`/admin/settings/messaging`): 계정을 저장하고 인증 확인 후 **발송 허용**을 켠다.
5. **운영 → 메시지 발송 → 템플릿**: 검수받은 결제 안내 템플릿을 KAPI로 가져오거나 수동 등록한다(아래 권장 문안).
6. **운영 → 이니톡 결제 → 설정**(`/admin/initalk/settings`): 상점명·고객센터·기본 결제기한·사용 환경·환경별 템플릿·정산 주기를 저장한다.
   템플릿은 본문 변수가 제공 변수 안에 있고 `#{결제토큰}`이 든 웹링크 버튼이 있어야 저장된다.
7. 테스트 환경에서 결제 생성 → 알림톡 수신 → 결제 페이지 → 테스트 승인 → 상세의 결제 상태 조회 → 환불까지 확인한 뒤 운영 환경으로 바꾼다.

업그레이드로 알림톡·결제 설정을 승계한 사이트는 permit 키가 바뀌었으므로 **발송 허용**과 **API 실행 허용**을 다시 켠다.

## 화면

| 화면 | 주소 | 내용 |
|---|---|---|
| 대시보드 | `/admin/initalk` | 상태별 건수, 이달 승인·환불·합계, 지급예정금액, 최근 요청 |
| 통합조회 | `/admin/initalk/requests` | 등록일·휴대폰·구매자명·상품명·주문번호·금액·상태·알림톡 가능 여부 검색, 선택 건 **알림톡 발송**·**결제전 취소** |
| 결제 생성 | `/admin/initalk/requests/new` | 단건 생성. 휴대폰 입력 시 같은 번호의 거래횟수·총액·최근거래일 표시. 즉시 발송 선택 |
| 일괄등록 | `/admin/initalk/requests/import` | CSV(샘플 제공, UTF-8/CP949, 1MB·500행) 검증 → 미리보기 → 거래등록(+즉시 발송) |
| 상세 | `/admin/initalk/requests/{id}` | 전체 번호(전체 관리자), 결제 링크 복사, QR, 알림톡 (재)발송·기한 연장, 결제전 취소, 결제 상태 조회, 전체·부분 환불, 원장, 이력 |
| 매출·정산 | `/admin/initalk/sales` | 월/기간별 일별 승인·환불·순매출, 정산 캘린더(승인일 + N일), CSV 내보내기 |
| 설정 | `/admin/initalk/settings` | 상점명·고객센터·기본 결제기한·환경·템플릿·정산 주기, 보관 만료 개인정보 정리 |
| 결제 페이지(공개) | `/pay/{토큰}` | 내역 확인 → 다음 → 이니시스 결제창. 완료·만료·취소 안내 |

주문번호는 `IT-YYYYMMDD-NNNN`, 결제 링크 토큰은 27자 난수다. 상태는 결제생성 → 결제대기중(알림톡 접수) → 결제완료 → 환불완료이며,
결제 전에는 결제전취소·기한만료가 된다. 만료된 건은 재발송하면 기한을 연장한 뒤 보낸다.

## 알림톡 템플릿 권장 문안

```
[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.

■ 상점명: #{상점명}
■ 상품명: #{상품명}
■ 금액: #{금액}원
■ 결제기한: #{결제기한}

* 기한 내 결제를 완료하지 못한 경우 상점에 문의해 주세요.
* 고객센터: #{고객센터}
```

버튼: 웹링크 **결제하기**, 모바일·PC 링크 `https://<사이트 주소>/pay/#{결제토큰}`.
제공 변수: `상점명`, `구매자명`, `요청일`(n월 j일), `상품명`, `금액`(천 단위 구분), `결제기한`(Y년 m월 d일 H:i), `고객센터`, `결제토큰`, `주문번호`.
템플릿은 이 중 일부만 써도 된다. 카카오 검수는 비즈뿌리오에서 받는다.

## 결제·콜백·환경

- 결제 페이지는 로그인·회원과 무관하고 테스트 환경도 링크가 있으면 누구나 결제할 수 있다. 결제창 시작 후 10초 안의 재시작은 거부한다.
- 이니시스 인증 결과는 `POST /pay/callback?id=…&state=…`로 온다. `state`는 요청·설정 판에 묶인 HMAC이며 세션을 쓰지 않는다.
  콜백은 서버 승인 → 조회 재확인 후에만 결제완료로 바꾸고, 불일치·오류는 요청에 **확인 필요**를 표시한다(상세의 결제 상태 조회로 대조).
- 사이트 주소(`app.url`)는 공개 HTTPS 주소여야 한다. 하위 경로 설치는 그대로 반영된다.
- 설정의 **사용 환경**이 알림톡·결제 환경을 함께 정한다. 요청은 생성 시 환경을 기억하므로 환경을 바꿔도 기존 요청은 자기 환경으로 조회·환불한다.
- 테스트 환경 알림톡은 알림톡·문자 설정의 테스트 수신번호로만 나간다.

## 유지보수 CLI

```
php bin/initalk.php expire   # 결제기한이 지난 요청 만료(결제창을 연 지 30분 이내 건은 보호)
php bin/initalk.php sync     # 최근 7일 결제창을 연 미결 건·확인 필요 건을 결제사에 조회
php bin/initalk.php purge    # 종료 90일 지난 요청의 구매자명·번호 정리
```

만료는 관리자 화면 진입과 결제 페이지 접근 때도 처리하므로 cron은 선택이다. 예: `*/10 * * * * php /경로/bin/initalk.php expire`, 매일 `sync`·`purge`.

## 매출·정산의 한계

원장은 결제사 응답으로 확인된 승인·환불만 기록한다. 지급예정일은 승인일 + 정산 주기(일)이며 영업일·공휴일을 계산하지 않는다.
실제 정산은 이니시스 정산서를 기준으로 한다. PG 정산서 자동 수집은 제공하지 않는다.

## 보안·운영

- 휴대폰 번호는 암호화 저장하고 목록에서는 마스킹한다. 로그·오류 화면·CSV 내보내기에 원문 번호를 쓰지 않는다.
- 카드번호·인증 토큰·PG 응답 원문은 저장하지 않는다. 환불 요청 키는 화면마다 새로 발급돼 중복 제출을 막는다.
- 결제 페이지·콜백·관리자 응답은 캐시하지 않고 리퍼러를 보내지 않는다. 웹훅·콜백 URL의 쿼리는 서버 접근 로그에서 제외한다([알림톡 안내](messaging.md)).
- 백업 복원 후에는 알림톡 발송 허용과 결제 API 실행 허용이 해제된다. 결제사 내역과 대조한 뒤 다시 켠다.

## 검증

`./vendor/bin/phpunit tests/Initalk tests/Support/QrCodeTest.php tests/Web/InitalkAdminTest.php tests/Web/PayTest.php`.
QR 라운드트립 테스트는 OpenCV(`python3` + `opencv-python-headless`)가 있을 때만 실행되며 없으면 건너뛴다.
실제 결제·알림톡 수신은 이니시스 테스트 상점과 비즈뿌리오 테스트 계정으로 별도 확인한다.
```

- [ ] **Step 2: AGENTS.md·README·messaging.md·스펙**

`AGENTS.md` 기능 지도의 `- 결제:` 항목 뒤에 추가:

```markdown
- 이니톡 결제: 관리자가 만든 결제 요청(단건·CSV)을 비즈뿌리오 알림톡으로 보내고 고객이 `/pay/{토큰}`에서 이니시스 카드결제로 결제한다. 통합조회·대시보드·매출/정산 캘린더·CSV, 결제전 취소·전체/부분 환불·결제 상태 조회, QR, 만료·정리·조회 CLI(`bin/initalk.php`)를 제공한다. 이니시스 이니톡 특약 서비스와 무관한 자체 구현이다.
```

`AGENTS.md`의 `- 결제:` 항목 끝 문장 "결제를 쓰는 화면은 이니톡 결제(2단계)가 추가한다."를 "결제 화면은 이니톡 결제가 사용한다."로 바꾼다.

`README.md`의 "## 알림톡·문자 발송과 이니시스 결제" 절 첫 문단 끝에 " 두 기능을 조합한 **이니톡 결제**(운영 → 이니톡 결제, `/admin/initalk`)는 [docs/initalk.md](docs/initalk.md)를 본다."를 덧붙인다.

`docs/messaging.md`의 첫 문단에 "이니톡 결제의 결제 알림톡도 이 기능으로 보낸다."가 이미 있으면 그대로 두고, 없으면 덧붙인다.

스펙 `docs/superpowers/specs/2026-09-14-initalk-design.md`:
1. §2 "`Schema::VERSION`을 23으로 올리고" → "`Schema::VERSION`은 1단계에서 23, 2단계에서 24가 된다(이니톡 테이블 추가)."
2. §5 표의 액션 행 `POST …/{id}/send`, `/cancel`, `/sync`, `/refund`(`amount`, `reason`)와 `GET …/{id}/qr.svg`는 그대로이고, `고객 확인 POST /admin/initalk/customer`, `CSV 일괄등록 GET/POST /admin/initalk/requests/import` 등 구현 경로와 일치하는지 확인해 다른 곳만 고친다(구현이 진실).
3. §9에 "QR 라운드트립은 OpenCV가 있을 때 자동 검증하고, 없으면 PNG를 휴대폰으로 스캔해 확인한다."를 덧붙인다.
4. §14의 "`docs/initalk.md`(신규)" 항목이 실제 목차와 맞는지 확인한다.

- [ ] **Step 3: 최종 검증**

```bash
cd /home/kagla/gnucms
./vendor/bin/phpunit
git diff --check
DB="gnucms_test_$(date +%s)"; mysql -uroot -h127.0.0.1 -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4" \
  && TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=$DB;charset=utf8mb4" TEST_MYSQL_USER=root ./vendor/bin/phpunit tests/Db tests/Initalk tests/Web/InitalkAdminTest.php tests/Web/PayTest.php; \
  mysql -uroot -h127.0.0.1 -e "DROP DATABASE \`$DB\`"
grep -rn "tokpay\|TokPay" src templates tests docs/initalk.md www/themes/default; echo "(no output above = OK)"
php -r 'require "vendor/autoload.php"; file_put_contents("/tmp/gnucms-initalk-qr.png", GnuCms\Support\QrCode::png("https://gnucms.charmgen.com/pay/Zm9vYmFyYmF6cXV4cXV1eA")); echo "QR sample: /tmp/gnucms-initalk-qr.png\n";'
```

Expected: SQLite 전체 `OK`, MySQL 부분 스위트 `OK`, `git diff --check` 무출력, `tokpay` 잔재 없음. QR 샘플 PNG 경로를 보고서에 적는다(사용자가 휴대폰으로 스캔).

- [ ] **Step 4: 커밋**

```bash
git add docs/initalk.md docs/messaging.md AGENTS.md README.md docs/superpowers/specs/2026-09-14-initalk-design.md
git commit -m "docs: describe INITalk payment operations and update the feature map

Co-Authored-By: Claude <model> <noreply@anthropic.com>"
```

---

## 완료 확인

- `./vendor/bin/phpunit` 전체 통과(기준 776 + 신규), MySQL 부분 스위트 통과, `git diff --check` 통과.
- 라이브 사이트에서 운영 → **이니톡 결제** 메뉴: 설정 저장 → 결제 생성 → 알림톡 수신 → `/pay/{토큰}` → 이니시스 테스트 결제 → 상세에서 결제완료·원장 확인 → 환불.
- QR 샘플 PNG를 휴대폰으로 스캔해 링크가 열리는지 확인.
