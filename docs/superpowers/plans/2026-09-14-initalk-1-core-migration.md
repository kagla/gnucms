# 이니톡 결제 1단계 — 알림톡·결제 코어 내장 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `feat/bizppurio-messaging`의 비즈뿌리오 플러그인·알림톡/문자 운영 모듈과 `feat/direct-pg-payments`의 이니시스 결제 코드를 GNUCMS 코어(`src/`)로 옮겨, 관리자 설정 탭·운영 메뉴·코어 스키마·백업에 통합된 상태로 만든다. 이니톡 결제 도메인(2단계 계획)이 이 위에 올라간다.

**Architecture:** 플러그인의 서비스 클로저(`send.v1` 등)는 `GnuCms\Messaging\MessagingService` 메서드로, 패키지 DB 설치(`PackageSchema`)는 코어 `Schema` 마이그레이션(v23)으로, 패키지 토글은 설정 저장 + 실행 허용 스위치로 바뀐다. 결제 엔진은 `GnuCms\Payment` 네임스페이스를 유지하되 이니시스만 남기고, 설정 화면은 `/admin/settings/payment`가 된다. 세션 없는 외부 POST(비즈뿌리오 웹훅, 이후 이니시스 콜백)는 코어로 옮긴 `ExternalRequests` 미들웨어가 처리한다.

**Tech Stack:** PHP 8.2+, Slim 4, PHP 템플릿(`PhpView`), SQLite/MySQL(`Connection`·`Schema`), PHPUnit 10. 새 Composer 의존성 없음.

**Spec:** `docs/superpowers/specs/2026-09-14-initalk-design.md` (§1, §2의 `bp_*`·`pay_inicis_*`, §4 `src/Messaging`·`src/Payment`·`MessagingController`, §11, §13, §14, §15, §16의 1~4단계)

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다. `vendor/`는 완성본을 묶어 배포한다.
- 작업 브랜치는 `feat/initalk`이며 `/home/kagla/gnucms`에 체크아웃돼 있다(Apache가 서비스 중인 라이브 체크아웃). 워크트리를 만들지 않는다. `git checkout`으로 브랜치를 바꾸지 않는다.
- 원본 코드는 브랜치에서 `git show <branch>:<path>`로 가져온다. 브랜치를 병합하지 않는다: 비즈뿌리오 = `feat/bizppurio-messaging`, 결제 = `feat/direct-pg-payments`.
- DB 변경은 SQLite와 MySQL/MariaDB에서 같은 의미여야 한다. 테이블·컬럼을 바꾸면 새 설치용 DDL(`Schema::statements()`)과 기존 설치용 멱등 마이그레이션(`migrate*()`)을 함께 고친다. `Schema::VERSION`은 이 계획에서 `'23'`으로 한 번만 올린다.
- 기존 플러그인 테이블 이름(`bp_settings`, `bp_templates`, `bp_dispatches`, `bp_attempts`, `bp_receipts`, `pay_inicis_settings`, `pay_inicis_transactions`)과 컬럼은 그대로 둔다. 데이터를 승계한다.
- 비밀번호·API 키·토큰·휴대폰 번호를 코드·테스트 fixture·로그·화면 오류에 남기지 않는다. 화면 출력은 `$this->e()`로 이스케이프한다.
- 관리자 화면은 전체 관리자만(`$app->guestAcl()->assertGlobalAdmin()`), POST는 세션 CSRF(`GnuCms\Web\Csrf::assert($request)`)를 검사한다.
- 실행 허용값(`RuntimePermit`)의 물리 경로는 `storage/extensions-runtime/permits/`를 그대로 쓴다(스펙 §13의 `storage/runtime/permits/`는 8번 작업에서 스펙을 이 경로로 고친다).
- 커밋은 한 가지 논리 변경, 형식 `feat:`/`refactor:`/`test:`/`docs:`. 커밋 메시지 끝에 `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- 각 작업 끝에 관련 테스트를, 코어 공통 코드·DB·라우팅을 건드린 작업 끝에는 전체 테스트(`./vendor/bin/phpunit`)를 돌린다. 시작 기준선: 689 tests OK.
- MySQL 검증은 로컬 MariaDB에 1회용 DB를 만들어 `TEST_MYSQL_DSN='mysql:host=127.0.0.1;dbname=<db>;charset=utf8mb4' TEST_MYSQL_USER=root ./vendor/bin/phpunit`으로 돌리고 끝나면 DB를 지운다(8번 작업).

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Web/Csrf.php` (신규) | 세션 CSRF 토큰 검사 한 곳. 코어 컨트롤러와 `Extension\Context`가 같이 쓴다 |
| `src/Web/Middleware/ExternalRequests.php` (이동) | 세션·HTML 없이 등록된 외부 POST만 처리하는 미들웨어 |
| `src/Support/RuntimePermit.php` (이동) | 백업에서 제외되는 실행 허용값(발송 허용·API 실행 허용) |
| `src/Payment/{Gateway,DirectGateway,InicisGateway,Journal,CallbackToken,ExecutionLock,Transport,StreamTransport,ProviderConfig,Settings,SettingsController}.php` | 이니시스 결제 엔진과 설정 화면 컨트롤러 |
| `templates/default/admin/payment_settings.php` | 설정 → 결제(이니시스) 화면 |
| `src/Messaging/*.php` | 비즈뿌리오 발송 엔진(`MessagingService`가 조립·공개 API) |
| `src/Messaging/SettingsController.php` | 설정 → 알림톡·문자 화면 컨트롤러 |
| `src/Web/Controller/MessagingController.php` | 운영 → 메시지 발송(알림톡 템플릿·웹발송·이력·상세, 문자 발송·이력·상세) |
| `templates/default/admin/messaging_settings.php`, `templates/default/admin/messaging/*.php`, `templates/default/admin/_password_toggle.php`, `templates/default/admin/_phone_input.php` | 메시징 화면과 공용 조각 |
| `src/Db/Schema.php` | v23: 결제·메시징 테이블 DDL, 멱등 마이그레이션, 플러그인 등록 승계 |
| `src/Db/SchemaUpgrader.php` | 마이그레이션 후 `enabled.json`에서 흡수한 패키지 ID 제거 |
| `src/App.php` | `paymentSettings()`, `inicisGateway()`, `messaging()` 접근자 |
| `src/Web/Routes.php` | 설정·운영 라우트와 웹훅 미들웨어 등록 |
| `tests/Payment/*`, `tests/Messaging/*`, `tests/Web/{PaymentSettingsTest,MessagingSettingsTest,MessagingTest}.php`, `tests/Db/LegacyPackageAdoptionTest.php` | 회귀 테스트 |

---

### Task 1: CSRF 도우미와 공통 클래스 이동

**Files:**
- Create: `src/Web/Csrf.php`
- Move: `src/Extension/ExternalRequests.php` → `src/Web/Middleware/ExternalRequests.php`
- Move: `src/Extension/RuntimePermit.php` → `src/Support/RuntimePermit.php`
- Modify: `src/Extension/Context.php:125-133`, `src/Extension/Manager.php:151`, `src/Maintenance/BackupManager.php:202`
- Modify: `tests/Extension/ExternalRequestsTest.php:7`, `tests/Maintenance/BackupManagerTest.php:220`

**Interfaces:**
- Produces: `GnuCms\Web\Csrf::assert(ServerRequestInterface $request): void` (실패 시 `DomainError::forbidden`), `GnuCms\Web\Middleware\ExternalRequests::__construct(array $routes, string $basePath = '')` (`$routes = ['/path' => [callable $authenticate, callable $handler, int $maxBytes, string $contentType]]`), `GnuCms\Support\RuntimePermit::__construct(string $storageDir)` with `allowed(string $key, string $revision): bool`, `set(string $key, ?string $revision): void`, `revokeAll(): void`, `generation(): string`.

- [ ] **Step 1: `Csrf` 클래스를 만든다**

`src/Web/Csrf.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\Error\DomainError;
use Psr\Http\Message\ServerRequestInterface;

/** 관리자·회원 POST의 세션 CSRF 토큰 검사. 코어 컨트롤러와 확장 라우트가 함께 쓴다. */
final class Csrf
{
    public static function assert(ServerRequestInterface $request): void
    {
        $input = $request->getParsedBody();
        $given = is_array($input) ? ($input['csrf_token'] ?? null) : null;
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($given) || !is_string($expected) || $expected === '' || !hash_equals($expected, $given)) {
            throw DomainError::forbidden('요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
    }
}
```

- [ ] **Step 2: `Context::assertCsrf`가 `Csrf`에 위임하게 바꾼다**

`src/Extension/Context.php`의 `assertCsrf` 본문(현재 125~133행)을 다음으로 교체한다. 파일 상단 `use` 목록에 `use GnuCms\Web\Csrf;`를 추가한다.

```php
    public static function assertCsrf(ServerRequestInterface $request): void
    {
        Csrf::assert($request);
    }
```

- [ ] **Step 3: 두 클래스를 옮기고 네임스페이스를 바꾼다**

```bash
cd /home/kagla/gnucms
git mv src/Extension/ExternalRequests.php src/Web/Middleware/ExternalRequests.php
git mv src/Extension/RuntimePermit.php src/Support/RuntimePermit.php
perl -pi -e 's/^namespace GnuCms\\Extension;/namespace GnuCms\\Web\\Middleware;/' src/Web/Middleware/ExternalRequests.php
perl -pi -e 's/^namespace GnuCms\\Extension;/namespace GnuCms\\Support;/' src/Support/RuntimePermit.php
perl -pi -e 's/\\GnuCms\\Extension\\RuntimePermit/\\GnuCms\\Support\\RuntimePermit/g' src/Maintenance/BackupManager.php tests/Maintenance/BackupManagerTest.php
perl -pi -e 's/use GnuCms\\Extension\\ExternalRequests;/use GnuCms\\Web\\Middleware\\ExternalRequests;/' tests/Extension/ExternalRequestsTest.php
grep -n "ExternalRequests" src/Extension/Manager.php
```

`src/Extension/Manager.php`는 같은 네임스페이스라 `use`가 없었다. 파일 상단 `use` 목록에 `use GnuCms\Web\Middleware\ExternalRequests;`를 추가한다. `src/Extension/PackageSchema.php`는 `RuntimePermit`을 쓰지 않으므로 손대지 않는다.

- [ ] **Step 4: 남은 참조가 없는지 확인하고 테스트한다**

```bash
grep -rn "Extension\\\\ExternalRequests\|Extension\\\\RuntimePermit" src tests; echo "(no output above = OK)"
php -l src/Web/Csrf.php && php -l src/Extension/Context.php && php -l src/Extension/Manager.php
./vendor/bin/phpunit tests/Extension tests/Maintenance
```

Expected: `OK` (기존 테스트 수 그대로).

- [ ] **Step 5: 커밋**

```bash
git add -A src/Web/Csrf.php src/Web/Middleware/ExternalRequests.php src/Support/RuntimePermit.php src/Extension tests/Extension/ExternalRequestsTest.php src/Maintenance/BackupManager.php tests/Maintenance/BackupManagerTest.php
git commit -m "refactor: move external request middleware, runtime permits and CSRF check to core

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 결제 엔진 코어화 (`src/Payment`, 스키마 v23 결제 테이블, 단위 테스트)

**Files:**
- Create (브랜치에서 가져옴): `src/Payment/Gateway.php`, `DirectGateway.php`, `InicisGateway.php`, `Journal.php`, `CallbackToken.php`, `ExecutionLock.php`, `Transport.php`, `StreamTransport.php`
- Create (새로 씀): `src/Payment/Settings.php`, `src/Payment/ProviderConfig.php`
- Modify: `src/Db/Schema.php` (TABLES, VERSION, statements, migrateAll), `src/App.php`
- Test: `tests/Payment/FakeTransport.php`, `tests/Payment/Fixtures.php`, `tests/Payment/CallbackTest.php`, `tests/Payment/GatewayTest.php`

**Interfaces:**
- Consumes: `GnuCms\Support\RuntimePermit` (Task 1)
- Produces: `GnuCms\Payment\Settings::__construct(App $app, string $provider = 'inicis')`, `key(): string` (= `payment-inicis`), `current(string $env): ?array`, `revision(string $revision): array`, `credentials(string $revision): array`, `available(string $env): bool`, `requireEnabled(string $env): void`, `summary(string $env): array{configured,enabled,merchant_id,client_ip,revision,environment}`, `save(string $env, array $input): void`, `enable(string $env, bool $enabled): void`; `GnuCms\Payment\Gateway` 인터페이스(`checkout`, `complete`, `fetch`, `cancel`)는 브랜치와 동일; `App::paymentSettings(): Settings`, `App::inicisGateway(): InicisGateway`, `App::setInicisGateway(InicisGateway): void`; 코어 테이블 `pay_inicis_settings`, `pay_inicis_transactions`.

- [ ] **Step 1: 테스트를 먼저 가져와 이니시스만 남긴다**

```bash
cd /home/kagla/gnucms
mkdir -p tests/Payment
for f in FakeTransport.php CallbackTest.php GatewayTest.php; do git show feat/direct-pg-payments:tests/Payment/$f > tests/Payment/$f; done
```

`tests/Payment/Fixtures.php`는 새로 쓴다:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Payment;

final class Fixtures
{
    /** 이니시스 상점 설정 입력값. 실제 계정과 무관한 난수다. */
    public static function config(string $provider = 'inicis'): array
    {
        if ($provider !== 'inicis') throw new \InvalidArgumentException('inicis fixture only');
        return ['merchant_id' => 'gnu' . substr(bin2hex(random_bytes(4)), 0, 7), 'sign_key' => bin2hex(random_bytes(32)),
            'hash_key' => bin2hex(random_bytes(16)), 'api_key' => bin2hex(random_bytes(16)), 'client_ip' => '192.0.2.10'];
    }
}
```

`tests/Payment/GatewayTest.php`를 다음과 같이 고친다.

1. 11행 `use GnuCms\Payment\{InicisGateway, KcpGateway, KspayGateway, Journal, Settings, StreamTransport};` → `use GnuCms\Payment\{InicisGateway, Journal, Settings, StreamTransport};`
2. 10행 `use GnuCms\Extension\RuntimePermit;` → `use GnuCms\Support\RuntimePermit;`
3. 15행 `require_once dirname(__DIR__, 2) . '/modules/shop/autoload.php';` 삭제.
4. 26행 `private bool $shopInstalled = false;` 삭제.
5. `setupGateway()`에서 `$this->settings = new Settings($this->app, 'inicis'); $this->settings->install(); $this->settings->install();` → `$this->settings = new Settings($this->app, 'inicis');`
6. `tearDown()`의 `if ($this->shopInstalled) foreach (...)` 한 줄과 `foreach (array_keys(Settings::PROVIDERS) as $id) foreach (['transactions', 'settings'] as $table) ...` 한 줄을 삭제한다(코어 `Schema::drop()`이 테이블을 지운다).
7. `testEncryptedMerchantSettingsRotationAndRestorePermit()`에서 `foreach (['kcp' => KcpGateway::class, 'kspay' => KspayGateway::class] as $id => $class) { ... }` 블록(5줄)을 삭제한다.
8. 메서드 `testMerchantInputPreservesPrivateKeyPasswordAndRejectsMismatchedCertificate`, `testVersionOneUpgradeAddsJournalAndRequiresFreshMerchantConfiguration`, `testDirectGatewayApprovalAndRefundUpdateShopMoneyAndStockExactlyOnce`를 `#[DataProvider]` 속성 줄까지 통째로 삭제한다.
9. `testTransportOnlyAllowsDocumentedPgHttpsDestinations()` 본문을 다음으로 교체한다.

```php
    public function testTransportOnlyAllowsDocumentedPgHttpsDestinations(): void
    {
        self::assertTrue(StreamTransport::allowed('https://iniapi.inicis.com/v2/pg/inquiry'));
        self::assertTrue(StreamTransport::allowed('https://stginiapi.inicis.com/v2/pg/partialRefund'));
        self::assertTrue(StreamTransport::allowed('https://fcstdpay.inicis.com/api/payAuth'));
        self::assertTrue(StreamTransport::allowed('https://stgmobile.inicis.com/smart/payReq.ini'));
        self::assertFalse(StreamTransport::allowed('http://iniapi.inicis.com/v2/pg/inquiry'));
        self::assertFalse(StreamTransport::allowed('https://iniapi.inicis.com/v2/pg/inquiry', 'GET'));
        self::assertFalse(StreamTransport::allowed('https://spl.kcp.co.kr/gw/mod/v1/cancel'));
        self::assertFalse(StreamTransport::allowed('https://api.tosspayments.com/v1/payments/confirm'));
        self::assertFalse(StreamTransport::allowed('https://evil.example/iniapi.inicis.com/v2/pg/inquiry'));
    }
```

- [ ] **Step 2: 테스트가 클래스 부재로 실패하는지 확인한다**

```bash
./vendor/bin/phpunit tests/Payment 2>&1 | tail -5
```

Expected: `Error: Class "GnuCms\Payment\Settings" not found` 류의 오류.

- [ ] **Step 3: 엔진 파일을 가져온다**

```bash
mkdir -p src/Payment
for f in Gateway DirectGateway InicisGateway Journal CallbackToken ExecutionLock Transport StreamTransport; do git show feat/direct-pg-payments:src/Payment/$f.php > src/Payment/$f.php; done
```

`src/Payment/StreamTransport.php`의 `allowed()`를 이니시스 전용으로 교체한다:

```php
    public static function allowed(string $url, string $method = 'POST'): bool
    {
        if ($method !== 'POST') return false;
        return (bool) preg_match('~^https://(?:(?:stg)?iniapi\.inicis\.com/v2/pg/(?:inquiry|refund|partialRefund)|(?:fc|ks|stg)stdpay\.inicis\.com/api/[A-Za-z0-9]+|(?:fc|ks|stg)mobile\.inicis\.com/smart/(?:payReq|payNetCancel)\.ini)$~D', $url);
    }
```

- [ ] **Step 4: `Settings`와 `ProviderConfig`를 코어용으로 새로 쓴다**

`src/Payment/Settings.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;
use GnuCms\Support\RuntimePermit;

/** 이니시스 결제 설정. 이전 주문의 조회·환불에 필요한 암호화 설정 판을 보존한다. */
final class Settings
{
    public const PROVIDERS = ['inicis' => 'KG이니시스'];
    private SecretCipher $cipher;
    private string $table;

    public function __construct(public readonly App $app, public readonly string $provider = 'inicis')
    {
        if (!isset(self::PROVIDERS[$provider])) throw DomainError::internal('결제사를 확인해 주세요.');
        $this->table = 'pay_' . $provider . '_settings';
        $this->cipher = new SecretCipher((string) $app->config('auth.secret'));
    }

    /** 실행 허용값의 키. 플러그인 시절 키(plugins/payment-inicis)와 다르므로 업그레이드 후 다시 허용해야 한다. */
    public function key(): string { return 'payment-' . $this->provider; }

    public static function environment(string $environment): string
    {
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '결제 환경을 확인해 주세요.']);
        return $environment;
    }

    private function row(string $id): ?array
    {
        $row = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table($this->table) . ' WHERE id = ?', [$id]);
        return $row === null ? null : json_decode($this->cipher->decrypt($row['payload']), true, 16, JSON_THROW_ON_ERROR);
    }

    public function current(string $environment): ?array
    {
        $row = $this->row(self::environment($environment));
        return ($row['integration'] ?? '') === 'direct-v1' ? $row : null;
    }

    public function revision(string $revision): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $revision)) throw DomainError::validation(['revision' => '결제 설정 판을 확인해 주세요.']);
        $row = $this->row($revision);
        if (($row['integration'] ?? '') !== 'direct-v1') throw DomainError::serviceUnavailable('직접 연동 설정으로 생성한 주문이 아닙니다.');
        return $row;
    }

    /** 주문의 상점은 보존하고 같은 상점의 인증키·서버 주소 변경을 과거 주문에 적용한다. */
    public function credentials(string $revision): array
    {
        $row = $this->revision($revision);
        $current = $this->current($row['environment']);
        if ($current !== null && $current['merchant_id'] === $row['merchant_id']) {
            foreach (ProviderConfig::fields($this->provider) as $key => $field) {
                if ($field['secret'] || $key === 'client_ip') $row[$key] = $current[$key];
            }
        }
        return $row;
    }

    public function available(string $environment): bool
    {
        $row = $this->current($environment);
        return $row !== null && (new RuntimePermit($this->app->storageDir()))->allowed($this->key() . '/' . $environment, $row['revision']);
    }

    public function requireEnabled(string $environment): void
    {
        if (!$this->available($environment)) throw DomainError::serviceUnavailable('결제 설정에서 API 실행을 허용해 주세요.');
    }

    public function summary(string $environment): array
    {
        $row = $this->current($environment);
        return ['configured' => $row !== null, 'enabled' => $this->available($environment),
            'merchant_id' => $row['merchant_id'] ?? '', 'client_ip' => $row['client_ip'] ?? '',
            'revision' => $row['revision'] ?? '', 'environment' => $environment];
    }

    public function save(string $environment, array $input): void
    {
        ExecutionLock::settings($this->app->storageDir(), fn () => $this->saveUnlocked($environment, $input));
    }

    private function saveUnlocked(string $environment, array $input): void
    {
        self::environment($environment);
        $before = $this->row($environment);
        $data = ProviderConfig::validate($this->provider, $input, $this->current($environment) ?? [], $environment)
            + ['integration' => 'direct-v1', 'environment' => $environment, 'revision' => bin2hex(random_bytes(16))];
        (new RuntimePermit($this->app->storageDir()))->set($this->key() . '/' . $environment, null);
        $payload = $this->cipher->encrypt(json_encode($data, JSON_THROW_ON_ERROR));
        $db = $this->app->db();
        $db->transaction(function () use ($db, $data, $environment, $before, $payload): void {
            $db->execute('INSERT INTO ' . $db->table($this->table) . ' (id, payload) VALUES (?, ?)', [$data['revision'], $payload]);
            if ($before === null) $db->execute('INSERT INTO ' . $db->table($this->table) . ' (id, payload) VALUES (?, ?)', [$environment, $payload]);
            else $db->update($this->table, ['payload' => $payload], 'id = :id', ['id' => $environment]);
        });
    }

    public function enable(string $environment, bool $enabled): void
    {
        ExecutionLock::settings($this->app->storageDir(), function () use ($environment, $enabled): void {
            $row = $this->current($environment);
            if ($enabled && $row === null) throw DomainError::validation(['settings' => '설정을 먼저 저장해 주세요.']);
            (new RuntimePermit($this->app->storageDir()))->set($this->key() . '/' . $environment, $enabled ? $row['revision'] : null);
        });
    }
}
```

`src/Payment/ProviderConfig.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;

/** 이니시스 상점 설정 항목과 검증. 리셀러 코드는 가맹점 등록용이며 결제 설정·전문에 넣지 않는다. */
final class ProviderConfig
{
    public static function fields(string $provider): array
    {
        if ($provider !== 'inicis') throw DomainError::internal('결제사를 확인해 주세요.');
        $names = ['merchant_id' => '상점 아이디 (MID)', 'sign_key' => '웹표준 결제 SignKey', 'hash_key' => '모바일 금액 위변조 Hash Key',
            'api_key' => 'INIAPI Key', 'client_ip' => '결제 요청 서버 IPv4 주소'];
        $fields = [];
        foreach ($names as $key => $label) {
            $fields[$key] = ['label' => $label, 'secret' => !in_array($key, ['merchant_id', 'client_ip'], true), 'multiline' => false];
        }
        return $fields;
    }

    public static function manual(string $provider): string
    {
        self::fields($provider);
        return 'https://manual.inicis.com/pay/';
    }

    public static function validate(string $provider, array $input, array $before, string $environment = 'test'): array
    {
        Settings::environment($environment);
        $data = [];
        foreach (self::fields($provider) as $key => $field) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || strlen($value) > 16384 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value)) {
                throw DomainError::validation([$key => '결제 연동 값을 확인해 주세요.']);
            }
            $value = trim($value);
            if ($value === '' && $field['secret'] && ($data['merchant_id'] ?? '') === ($before['merchant_id'] ?? null)) $value = $before[$key] ?? '';
            if ($value === '') throw DomainError::validation([$key => $field['label'] . '을 입력해 주세요.']);
            if (preg_match('/[\r\n]/', $value)) throw DomainError::validation([$key => '한 줄로 입력해 주세요.']);
            $data[$key] = $value;
        }
        if (!preg_match('/^[A-Za-z0-9]{10}$/D', $data['merchant_id'])) throw DomainError::validation(['merchant_id' => 'PG에서 발급한 상점 코드를 확인해 주세요.']);
        if (!filter_var($data['client_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw DomainError::validation(['client_ip' => '서버의 IPv4 주소를 입력해 주세요.']);
        foreach (['sign_key', 'hash_key', 'api_key'] as $key) {
            if (strlen($data[$key]) < 16 || preg_match('/\s/', $data[$key])) throw DomainError::validation([$key => '발급받은 인증키를 확인해 주세요.']);
        }
        return $data;
    }
}
```

- [ ] **Step 5: 코어 스키마에 결제 테이블을 넣는다**

`src/Db/Schema.php`:

1. `TABLES` 배열 마지막 `'extension_schemas',` 뒤에 한 줄 추가: `'pay_inicis_settings', 'pay_inicis_transactions',`
2. `public const VERSION = '22';` → `public const VERSION = '23';`
3. `migrateAll()`에서 `$this->migrateExtensionSchemas();` 바로 다음 줄에 `$this->migratePayment();` 추가.
4. `statements()`의 `array_merge(..., $this->writeRateLimitStatements(), $this->extensionSchemaStatements());` → `..., $this->extensionSchemaStatements(), $this->paymentStatements());`
5. `extensionSchemaStatements()` 메서드 바로 아래에 추가:

```php
    private function paymentStatements(): array
    {
        return [
            'CREATE TABLE pay_inicis_settings (id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL){SUFFIX}',
            'CREATE TABLE pay_inicis_transactions (id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL){SUFFIX}',
        ];
    }

    /** 결제 플러그인 판 2와 같은 구조. 플러그인으로 설치한 테이블은 그대로 승계한다. */
    public function migratePayment(): void
    {
        foreach ($this->paymentStatements() as $sql) {
            preg_match('/^CREATE TABLE (\w+)/', $sql, $m);
            if (!$this->tableExists($m[1])) $this->db->execute($this->expand($sql));
        }
        $this->adoptExtensionTables('plugins/payment-inicis');
    }

    /** 플러그인 시절 extension_schemas에 등록된 테이블을 코어 소유로 옮긴다. 데이터는 그대로다. */
    private function adoptExtensionTables(string $packageKey): void
    {
        if (!$this->tableExists('extension_schemas')) return;
        $this->db->delete('extension_schemas', 'package_key = :key', ['key' => $packageKey]);
    }
```

- [ ] **Step 6: `App` 접근자를 추가한다**

`src/App.php`의 속성 선언부(예: `private ?AdminService $adminService = null;` 아래)에 추가:

```php
    private ?\GnuCms\Payment\Settings $paymentSettings = null;
    private ?\GnuCms\Payment\InicisGateway $inicisGateway = null;
```

`mailSettingsService()` 메서드 아래에 추가:

```php
    public function paymentSettings(): \GnuCms\Payment\Settings
    {
        return $this->paymentSettings ??= new \GnuCms\Payment\Settings($this, 'inicis');
    }

    public function inicisGateway(): \GnuCms\Payment\InicisGateway
    {
        return $this->inicisGateway ??= new \GnuCms\Payment\InicisGateway($this->paymentSettings());
    }

    /** 테스트에서 모의 전송기를 가진 게이트웨이로 바꾼다. */
    public function setInicisGateway(\GnuCms\Payment\InicisGateway $gateway): void
    {
        $this->inicisGateway = $gateway;
    }
```

- [ ] **Step 7: 테스트를 돌린다**

```bash
for f in src/Payment/*.php src/Db/Schema.php src/App.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Payment tests/Db
```

Expected: `OK`. `tests/Db`의 기존 스키마 테스트가 `VERSION` 문자열이나 테이블 수를 단언한다면 23과 새 테이블에 맞춰 그 단언만 고친다(실패 메시지가 가리키는 줄).

- [ ] **Step 8: 커밋**

```bash
git add src/Payment src/Db/Schema.php src/App.php tests/Payment
git commit -m "feat: add INICIS payment engine to the core schema

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: 결제 설정 화면·라우트·웹 테스트

**Files:**
- Create: `src/Payment/SettingsController.php`, `templates/default/admin/payment_settings.php`
- Modify: `src/Web/Routes.php` (관리자 설정 라우트 블록), `templates/default/admin/_settings_tabs.php`
- Test: `tests/Web/PaymentSettingsTest.php`

**Interfaces:**
- Consumes: `App::paymentSettings()`, `Csrf::assert()`, `View::fromRequest($request)->render($response, 'admin/payment_settings', array $data)`
- Produces: 라우트 이름 `admin.settings.payment` (GET/POST `/admin/settings/payment`, 쿼리·본문 `environment=test|live`, POST `action=save|enable|disable`)

- [ ] **Step 1: 웹 테스트를 쓴다**

`tests/Web/PaymentSettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Tests\Payment\Fixtures;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PaymentSettingsTest extends WebTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-pay-web-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'pw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
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
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsPageRequiresAdminAndCsrfAndSavesEncryptedMerchantConfiguration(array $config): void
    {
        $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/settings/payment'), '/admin/settings/payment');
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/admin/settings/payment')->getStatusCode());
        $this->signIn(true);
        $page = $this->get($this->app, '/admin/settings/payment');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('상점 아이디 (MID)', $this->body($page));
        self::assertStringContainsString('href="/admin/settings/payment"', $this->body($page));
        self::assertSame('no-store', $page->getHeaderLine('Cache-Control'));
        $merchant = Fixtures::config('inicis');
        self::assertSame(403, $this->post($this->app, '/admin/settings/payment', ['action' => 'save', 'environment' => 'test'] + $merchant)->getStatusCode());
        $saved = $this->post($this->app, '/admin/settings/payment', ['action' => 'save', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']] + $merchant);
        self::assertSame(200, $saved->getStatusCode());
        self::assertStringContainsString('설정을 저장했습니다', $this->body($saved));
        self::assertStringNotContainsString($merchant['api_key'], $this->body($saved));
        self::assertStringContainsString('value="enable"', $this->body($saved));
        $settings = $this->app->paymentSettings();
        self::assertTrue($settings->summary('test')['configured']);
        self::assertFalse($settings->available('test'));
        $raw = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('pay_inicis_settings') . " WHERE id = 'test'")['payload'];
        self::assertStringNotContainsString($merchant['api_key'], $raw);
        $enabled = $this->post($this->app, '/admin/settings/payment', ['action' => 'enable', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $enabled->getStatusCode());
        self::assertStringContainsString('value="disable"', $this->body($enabled));
        self::assertTrue($settings->available('test'));
        self::assertFalse($settings->available('live'));
        $invalid = $this->post($this->app, '/admin/settings/payment', ['action' => 'save', 'environment' => 'live', 'csrf_token' => $_SESSION['csrf_token']] + array_replace($merchant, ['merchant_id' => 'bad']));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('상점 코드를 확인해 주세요', $this->body($invalid));
        self::assertFalse($settings->summary('live')['configured']);
        self::assertSame(422, $this->post($this->app, '/admin/settings/payment', ['action' => 'unknown', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Web/PaymentSettingsTest.php 2>&1 | tail -5
```

Expected: 첫 단언 실패(`/admin/settings/payment`가 404).

- [ ] **Step 3: 컨트롤러를 쓴다**

`src/Payment/SettingsController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Payment;

use GnuCms\Error\DomainError;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** 설정 → 결제(이니시스). 전체 관리자 전용이며 POST는 세션 CSRF를 검사한다. */
final class SettingsController
{
    public function __construct(private Settings $settings)
    {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->settings->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        foreach ($input as $value) if (!is_string($value) && !is_int($value)) throw DomainError::validation(['input' => '단일 입력값을 사용해 주세요.']);
        $environment = is_string($input['environment'] ?? null) ? Settings::environment($input['environment']) : 'test';
        $notice = '';
        $errors = [];
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'save') {
                    $this->settings->save($environment, $input);
                    $notice = '설정을 저장했습니다. 상점 코드와 환경을 확인한 뒤 API 실행을 허용해 주세요.';
                } elseif (in_array($action, ['enable', 'disable'], true)) {
                    $this->settings->enable($environment, $action === 'enable');
                    $notice = $action === 'enable' ? 'API 실행을 허용했습니다.' : 'API 실행을 정지했습니다.';
                } else {
                    throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                }
            }
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $errors = $e->status() >= 500 ? ['설정 저장에 실패했습니다. 암호화 키와 저장소 상태를 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]);
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/payment_settings',
            ['fields' => ProviderConfig::fields($this->settings->provider), 'manual' => ProviderConfig::manual($this->settings->provider),
                'label' => Settings::PROVIDERS[$this->settings->provider], 'environment' => $environment,
                'settings' => $this->settings->summary($environment), 'notice' => $notice, 'errors' => $errors]
        );
    }
}
```

- [ ] **Step 4: 템플릿을 쓴다**

`templates/default/admin/payment_settings.php`:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('title') ?><?= $this->e($label) ?> 결제 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>site<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li><a href="<?= $this->url('admin.settings') ?>">설정</a></li><li aria-current="page">결제</li></ul></div>
<?php $this->insert('admin/_settings_tabs', ['active' => 'payment']) ?>
<section class="card settings-card">
  <div class="card-body">
    <h1 class="card-title"><?= $this->icon('shield', 19) ?> <?= $this->e($label) ?> 결제 설정</h1>
    <p class="card-sub">상점 코드와 인증 정보를 등록하고 환경별 결제 실행을 관리합니다. 이니톡 결제의 카드결제에 사용합니다. 인증 정보는 암호화해서 저장합니다.</p>
    <nav class="tabs tabs-border settings-tabs" aria-label="결제 환경">
      <?php foreach (['test' => '테스트 환경', 'live' => '운영 환경'] as $env => $envLabel): ?><a class="tab<?= $environment === $env ? ' tab-active' : '' ?>" href="<?= $this->url('admin.settings.payment') ?>?environment=<?= $env ?>"<?= $environment === $env ? ' aria-current="page"' : '' ?>><?= $this->e($envLabel) ?></a><?php endforeach ?>
    </nav>
    <?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><span><?= $this->e($error) ?></span></div><?php endforeach ?>
    <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><span><?= $this->e($notice) ?></span></div><?php endif ?>
    <form method="post" action="<?= $this->url('admin.settings.payment') ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
      <?php foreach ($fields as $name => $field): ?>
      <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><label for="payment-<?= $this->e($name) ?>"><?= $this->e($field['label']) ?></label></legend>
        <input class="input input-bordered input-block" id="payment-<?= $this->e($name) ?>" type="<?= $field['secret'] ? 'password' : 'text' ?>" name="<?= $this->e($name) ?>" value="<?= $field['secret'] ? '' : $this->e($settings[$name] ?? '') ?>" autocomplete="<?= $field['secret'] ? 'new-password' : 'off' ?>"<?= !$field['secret'] ? ' required' : '' ?> placeholder="<?= $field['secret'] && $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '' ?>">
      </fieldset>
      <?php endforeach ?>
      <div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="save">설정 저장</button></div>
    </form>
  </div>
</section>
<section class="card settings-card">
  <div class="card-body">
    <h2 class="card-title">결제 실행 상태 <span class="badge badge-soft<?= $settings['enabled'] ? ' badge-success' : '' ?>"><?= $settings['enabled'] ? '허용됨' : '정지됨' ?></span></h2>
    <p class="card-sub">PG에서 발급받은 상점 코드가 선택한 환경용인지 확인해 주세요. 테스트 결제는 운영 정산에 포함되지 않습니다. 백업을 복원하면 실행 허용이 해제됩니다.</p>
    <form method="post" action="<?= $this->url('admin.settings.payment') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><div class="card-actions form-actions"><button class="btn<?= $settings['enabled'] ? '' : ' btn-primary' ?>" name="action" value="<?= $settings['enabled'] ? 'disable' : 'enable' ?>"<?= !$settings['configured'] ? ' disabled' : '' ?>><?= $settings['enabled'] ? 'API 실행 정지' : 'API 실행 허용' ?></button></div></form>
  </div>
</section>
<p class="muted"><a class="link" href="<?= $this->e($manual) ?>" target="_blank" rel="noopener noreferrer">PG 공식 연동 문서 <?= $this->icon('external', 14) ?></a></p>
<?php $this->stop() ?>
```

- [ ] **Step 5: 라우트와 설정 탭을 추가한다**

`src/Web/Routes.php`에서 `$slim->get('/admin/settings/maintenance', ...)` 줄 바로 앞에 추가:

```php
        $payment = new \GnuCms\Payment\SettingsController($app->paymentSettings());
        $slim->get('/admin/settings/payment', [$payment, 'handle'])->setName('admin.settings.payment');
        $slim->post('/admin/settings/payment', [$payment, 'handle']);
```

`templates/default/admin/_settings_tabs.php`의 `메일` 탭 줄 다음에 추가:

```php
  <a class="tab<?= $active === 'payment' ? ' tab-active' : '' ?>"<?= $active === 'payment' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.payment') ?>">결제</a>
```

- [ ] **Step 6: 테스트와 전체 스위트를 돌린다**

```bash
./vendor/bin/phpunit tests/Web/PaymentSettingsTest.php
./vendor/bin/phpunit
```

Expected: 둘 다 `OK`.

- [ ] **Step 7: 커밋**

```bash
git add src/Payment/SettingsController.php templates/default/admin/payment_settings.php templates/default/admin/_settings_tabs.php src/Web/Routes.php tests/Web/PaymentSettingsTest.php
git commit -m "feat: add INICIS payment settings screen to site settings

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: 메시징 엔진 코어화 (`src/Messaging`, 스키마 메시징 테이블, 단위 테스트)

**Files:**
- Create (브랜치에서 가져와 네임스페이스 변경): `src/Messaging/{Api,Dispatch,HttpTransport,Input,KapiTemplates,Locks,ResultCodes,Results,Settings,Store,StreamTransport,Templates,TextMessage,TransportFailure}.php`
- Create (새로 씀): `src/Messaging/MessagingService.php`
- Modify: `src/Db/Schema.php` (TABLES, INDEXES, statements, migrateAll), `src/App.php`
- Test: `tests/Messaging/{FakeTransport,TextFixtures,TextMessageTest,ServiceTest,TextServiceTest,KapiTemplatesTest}.php`

**Interfaces:**
- Consumes: `GnuCms\Support\RuntimePermit`
- Produces: `GnuCms\Messaging\MessagingService::__construct(App $app, ?HttpTransport $transport = null)` with public readonly `settings`(`Settings`), `templates`(`Templates`), `dispatch`(`Dispatch`), `results`(`Results`), `api`(`Api`), `remoteTemplates`(`KapiTemplates`); 메서드 `ready(): bool`, `requireReady(): void`, `connect(string $env): void`, `status(string $env): array`, `preview(array $input): array`, `send(array $input): array`, `history(array $filter): array`, `detail(string $id): array`, `retry(string $id): array`, `refresh(string $id): void`, `purge(): int`, `previewText(array): array`, `sendText(array): array`, `textHistory(array): array`, `textDetail(string): array`, `retryText(string): array`, `refreshText(string): void`, `purgeText(): int`, `templateAction(string $action, array $input): array`. `send()` 입력: `environment, template_id, revision, idempotency_key, phone, variables[, config_revision, reference]`; 반환에 `id`, `submission`, `delivery`, `attempts_detail`. `App::messaging(): MessagingService`, `App::setMessaging(MessagingService): void`. 코어 테이블 `bp_*` 5개, 인덱스 `bp_list`, `bp_tries`, `bp_results`. 실행 허용 키 `messaging/{env}`, `messaging/{env}/api-verified`.

- [ ] **Step 1: 단위 테스트를 먼저 옮긴다**

```bash
cd /home/kagla/gnucms
mkdir -p tests/Messaging
for f in FakeTransport TextFixtures TextMessageTest ServiceTest TextServiceTest KapiTemplatesTest; do git show feat/bizppurio-messaging:tests/Bizppurio/$f.php > tests/Messaging/$f.php; done
perl -pi -e 's/^namespace GnuCms\\Tests\\Bizppurio;/namespace GnuCms\\Tests\\Messaging;/; s/GnuCms\\Tests\\Bizppurio\\/GnuCms\\Tests\\Messaging\\/g; s/GnuCms\\Plugins\\Bizppurio\\Service\b/GnuCms\\Messaging\\MessagingService/g; s/GnuCms\\Plugins\\Bizppurio\\/GnuCms\\Messaging\\/g; s/\bprivate Service \$service;/private MessagingService \$service;/; s/new Service\(/new MessagingService(/g' tests/Messaging/*.php
perl -ni -e 'print unless /require_once .*plugins\/bizppurio\/autoload\.php/' tests/Messaging/*.php
perl -ni -e 'print unless /\$this->service->install\(\);/ || /self::assert(True|False)\(\$this->service->ready\(\)\);/ || /array_reverse\(\\GnuCms\\Messaging\\Schema::TABLES\)/' tests/Messaging/*.php
grep -n "install()\|ready()\|Schema::TABLES\|Bizppurio" tests/Messaging/*.php; echo "(no output above = OK)"
```

`tests/Messaging/TextServiceTest.php`의 `testV1MigrationPreservesAlimtalkAndSeparatesTextHistoryAndRetention` 메서드는 플러그인 판 1→2 설치 경로(`extension_schemas` 조작·`install()`)를 검증하므로 `#[DataProvider('connectionProvider')]` 속성 줄부터 닫는 중괄호까지 통째로 삭제한다. 판 1 승계는 7번 작업의 `LegacyPackageAdoptionTest`가 검증한다.

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Messaging 2>&1 | tail -5
```

Expected: `Class "GnuCms\Messaging\MessagingService" not found` 류.

- [ ] **Step 3: 엔진 파일을 가져와 네임스페이스와 참조를 바꾼다**

```bash
mkdir -p src/Messaging
for f in Api Dispatch HttpTransport Input KapiTemplates Locks ResultCodes Results Settings Store StreamTransport Templates TextMessage TransportFailure; do git show feat/bizppurio-messaging:plugins/bizppurio/src/$f.php > src/Messaging/$f.php; done
perl -pi -e 's/^namespace GnuCms\\Plugins\\Bizppurio;/namespace GnuCms\\Messaging;/; s/\\GnuCms\\Extension\\RuntimePermit/\\GnuCms\\Support\\RuntimePermit/g; s/^use GnuCms\\Extension\\RuntimePermit;/use GnuCms\\Support\\RuntimePermit;/' src/Messaging/*.php
grep -n "Extension\\\\\|Plugins\\\\\|Schema::KEY\|PackageSchema" src/Messaging/*.php
```

마지막 `grep`이 가리키는 곳을 고친다. `src/Messaging/Settings.php`의 `permitKey()`를 다음으로 교체한다(`Schema::KEY` 참조 제거):

```php
    private function permitKey(string $environment): string
    {
        return 'messaging/' . Input::environment($environment);
    }
```

- [ ] **Step 4: `MessagingService`를 쓴다**

`src/Messaging/MessagingService.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Mail\SecretCipher;

/** 비즈뿌리오 발송 엔진의 조립과 공개 API. 생성자에서 쓰기·외부 통신을 하지 않는다. */
final class MessagingService
{
    public readonly Settings $settings;
    public readonly Templates $templates;
    public readonly Dispatch $dispatch;
    public readonly Results $results;
    public readonly Api $api;
    public readonly KapiTemplates $remoteTemplates;

    public function __construct(App $app, ?HttpTransport $transport = null)
    {
        $db = $app->db();
        $storage = $app->storageDir();
        $secret = (string) $app->config('auth.secret', '');
        $cipher = new SecretCipher($secret);
        $store = new Store($db);
        $this->settings = new Settings($store, $cipher, $storage);
        $this->templates = new Templates($store, $this->settings);
        $transport ??= new StreamTransport();
        $this->api = new Api($transport, $cipher, $storage);
        $this->remoteTemplates = new KapiTemplates($transport, $this->settings, $this->templates);
        $this->dispatch = new Dispatch($store, $this->settings, $this->templates, $this->api, $cipher, $secret, $storage);
        $this->results = new Results($store, $this->settings, $this->dispatch, $storage);
    }

    /** 테이블은 코어 스키마가 보장한다. 플러그인 시절의 "데이터 설치" 단계는 없다. */
    public function ready(): bool { return true; }
    public function requireReady(): void {}

    public function connect(string $environment): void { $this->settings->checkConnection($environment, $this->api); }

    public function status(string $environment): array
    {
        Input::environment($environment);
        return array_intersect_key($this->settings->summary($environment), array_flip([
            'configured', 'enabled', 'api_verified', 'account_type', 'account', 'test_only', 'test_phone', 'kapi_configured',
        ]));
    }

    public function preview(array $input): array
    {
        if (!is_array($input['variables'] ?? [])) throw DomainError::validation(['variables' => '변수 입력을 확인해 주세요.']);
        return $this->templates->preview(Input::id($input['template_id'] ?? null), $input['variables'] ?? [], isset($input['revision']) ? Input::id($input['revision']) : null);
    }

    public function send(array $input): array { return $this->dispatch->send($input); }
    public function history(array $input): array { return $this->dispatch->history($input); }
    public function detail(string $id): array { return $this->channelDetail($id, false); }
    public function retry(string $id): array { $this->detail($id); return $this->dispatch->retry($id); }
    public function refresh(string $id): void { $this->detail($id); $this->dispatch->refreshResult($id); }
    public function purge(): int { return $this->dispatch->purge(); }

    public function previewText(array $input): array
    {
        $environment = Input::environment($input['environment'] ?? null);
        $phone = Input::phone($input['phone'] ?? null);
        $text = TextMessage::normalize($input);
        $settings = $this->settings->read($environment);
        if ($settings === null) throw DomainError::validation(['settings' => '알림톡·문자 설정에 계정과 발신번호를 먼저 저장해 주세요.']);
        if ($settings['test_only'] && $phone !== $settings['test_phone']) throw DomainError::validation(['phone' => '현재 지정된 테스트 번호로만 발송할 수 있습니다.']);
        return $text + ['environment' => $environment, 'phone' => $phone, 'from' => $settings['from'], 'config_revision' => $settings['revision']];
    }

    public function sendText(array $input): array { return $this->dispatch->sendText($input); }
    public function textHistory(array $input): array { return $this->dispatch->history($input, true); }
    public function textDetail(string $id): array { return $this->channelDetail($id, true); }
    public function retryText(string $id): array { $this->textDetail($id); return $this->dispatch->retry($id); }
    public function refreshText(string $id): void { $this->textDetail($id); $this->dispatch->refreshResult($id); }
    public function purgeText(): int { return $this->dispatch->purge(true); }

    private function channelDetail(string $id, bool $text): array
    {
        $detail = $this->dispatch->detail($id, $text);
        if (!in_array($detail['channel'], $text ? ['sms', 'lms'] : ['at'], true)) throw DomainError::notFound('이 채널의 발송 이력이 아닙니다.');
        return $detail;
    }

    public function templateAction(string $action, array $input): array
    {
        return match ($action) {
            'list' => $this->templates->all(Input::environment($input['environment'] ?? null)),
            'get' => $this->templates->get(Input::id($input['id'] ?? null)),
            'save' => $this->templates->save(Input::environment($input['environment'] ?? null), $input),
            'remote-list' => $this->remoteTemplates->listing(Input::environment($input['environment'] ?? null), $input),
            'remote-detail' => $this->remoteTemplates->detail(Input::environment($input['environment'] ?? null), $input),
            'remote-import' => $this->remoteTemplates->import(Input::environment($input['environment'] ?? null), $input),
            default => throw DomainError::validation(['action' => '템플릿 작업을 확인해 주세요.']),
        };
    }
}
```

- [ ] **Step 5: 코어 스키마에 메시징 테이블을 넣는다**

`src/Db/Schema.php`:

1. `TABLES`의 `'pay_inicis_settings', 'pay_inicis_transactions',` 뒤에 `'bp_settings', 'bp_templates', 'bp_dispatches', 'bp_attempts', 'bp_receipts',` 추가.
2. `INDEXES` 마지막 `'ux_write_rate_limits',` 뒤에 `'bp_list', 'bp_tries', 'bp_results',` 추가.
3. `statements()`의 `$this->paymentStatements()` 뒤에 `, $this->messagingStatements()` 추가.
4. `migrateAll()`의 `$this->migratePayment();` 다음 줄에 `$this->migrateMessaging();` 추가.
5. `migratePayment()` 아래에 추가:

```php
    private function messagingStatements(): array
    {
        return [
            'CREATE TABLE bp_settings (environment VARCHAR(8) PRIMARY KEY, revision VARCHAR(32) NOT NULL, payload {TEXT} NOT NULL){SUFFIX}',
            'CREATE TABLE bp_templates (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, code VARCHAR(30) NOT NULL,
                revision VARCHAR(32) NOT NULL, payload {TEXT} NOT NULL, enabled SMALLINT NOT NULL, UNIQUE (environment, code)){SUFFIX}',
            'CREATE TABLE bp_dispatches (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, config_revision VARCHAR(32) NOT NULL,
                idempotency_key VARCHAR(64) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL, template_id VARCHAR(32) NOT NULL,
                template_name VARCHAR(100) NOT NULL, channel VARCHAR(8) NOT NULL DEFAULT \'at\', payload {TEXT} NOT NULL,
                phone_mask VARCHAR(30) NOT NULL, phone_hash VARCHAR(64) NOT NULL, submission VARCHAR(16) NOT NULL, delivery VARCHAR(16) NOT NULL,
                created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, retry_at BIGINT NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0){SUFFIX}',
            'CREATE TABLE bp_attempts (id VARCHAR(32) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, sequence_no INTEGER NOT NULL,
                refkey VARCHAR(32) NOT NULL UNIQUE, messagekey VARCHAR(128) NOT NULL, submission VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL,
                http_status INTEGER NOT NULL, created_at BIGINT NOT NULL){SUFFIX}',
            'CREATE TABLE bp_receipts (id VARCHAR(64) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, attempt_id VARCHAR(32) NOT NULL,
                message_id VARCHAR(128) NOT NULL, message_key VARCHAR(128) NOT NULL, media VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL,
                event_at BIGINT NOT NULL, received_at BIGINT NOT NULL, matched SMALLINT NOT NULL){SUFFIX}',
            'CREATE INDEX bp_list ON bp_dispatches (created_at)',
            'CREATE INDEX bp_tries ON bp_attempts (dispatch_id)',
            'CREATE INDEX bp_results ON bp_receipts (dispatch_id)',
        ];
    }

    /** 비즈뿌리오 플러그인 판 2와 같은 구조. 판 1 테이블에는 channel 컬럼과 인덱스를 보충한다. */
    public function migrateMessaging(): void
    {
        if (!$this->tableExists('bp_settings')) {
            foreach ($this->messagingStatements() as $sql) $this->db->execute($this->expand($sql));
        } else {
            $this->addColumnIfMissing('bp_dispatches', 'channel', "VARCHAR(8) NOT NULL DEFAULT 'at'");
            foreach (['bp_list' => 'CREATE INDEX bp_list ON bp_dispatches (created_at)', 'bp_tries' => 'CREATE INDEX bp_tries ON bp_attempts (dispatch_id)',
                'bp_results' => 'CREATE INDEX bp_results ON bp_receipts (dispatch_id)'] as $index => $sql) {
                $this->createIndexIfMissing($index, $sql);
            }
        }
        $this->adoptExtensionTables('plugins/bizppurio');
    }

    private function createIndexIfMissing(string $index, string $sql): void
    {
        $physical = $this->db->prefix() . $index;
        $exists = $this->db->dialect()->name() === 'sqlite'
            ? $this->db->selectOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$physical])
            : $this->db->selectOne('SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name = ?', [$physical]);
        if ($exists === null) $this->db->execute($this->expand($sql));
    }
```

- [ ] **Step 6: `App` 접근자를 추가한다**

`src/App.php` 속성 선언에 `private ?\GnuCms\Messaging\MessagingService $messaging = null;` 추가, `inicisGateway()` 아래에:

```php
    public function messaging(): \GnuCms\Messaging\MessagingService
    {
        return $this->messaging ??= new \GnuCms\Messaging\MessagingService($this);
    }

    /** 테스트에서 모의 HTTP 전송기를 가진 서비스로 바꾼다. */
    public function setMessaging(\GnuCms\Messaging\MessagingService $service): void
    {
        $this->messaging = $service;
    }
```

- [ ] **Step 7: 테스트를 돌린다**

```bash
for f in src/Messaging/*.php src/Db/Schema.php src/App.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Messaging tests/Db tests/Payment
```

Expected: `OK`. 실패가 나면 원인은 대개 (a) 테스트에 남은 `install()`/`ready()` 단언, (b) `Settings::permitKey` 변경 → 테스트가 `RuntimePermit` 키 문자열을 직접 단언하는 경우 `messaging/test`로 고친다.

- [ ] **Step 8: 커밋**

```bash
git add src/Messaging src/Db/Schema.php src/App.php tests/Messaging
git commit -m "feat: add Bizppurio messaging engine to the core schema

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: 알림톡·문자 설정 화면, 웹훅 라우트, 설정 웹 테스트

**Files:**
- Create: `src/Messaging/SettingsController.php`, `templates/default/admin/messaging_settings.php`, `templates/default/admin/_password_toggle.php`, `templates/default/admin/_phone_input.php`
- Modify: `src/Web/Routes.php`, `templates/default/admin/_settings_tabs.php`
- Test: `tests/Web/MessagingSettingsTest.php`

**Interfaces:**
- Consumes: `App::messaging()`, `Csrf::assert()`, `ExternalRequests`
- Produces: 라우트 `admin.settings.messaging` (GET/POST `/admin/settings/messaging`, POST `action=save|enable|disable|connect|webhook|reveal-password`, `environment=test|live`); 외부 POST `/messaging/bizppurio/result?environment=&token=`와 이전 주소 `/plugins/bizppurio/result` (JSON, 64KB); 라우트 이름 `admin.messaging.templates`, `admin.messaging.send`, `admin.messaging.sms.send`는 6번 작업에서 등록되므로 이 작업의 템플릿 링크는 그 이름을 미리 쓴다 — 6번 작업 전까지 화면을 열면 링크 생성에서 예외가 나므로, 이 작업의 테스트는 라우트 등록(아래 Step 5)에서 임시로 같은 이름의 GET 라우트를 함께 등록해 통과시킨다.

- [ ] **Step 1: 설정 웹 테스트를 쓴다**

`tests/Web/MessagingSettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Messaging\MessagingService;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MessagingSettingsTest extends WebTestCase
{
    private string $root;
    private App $app;
    private FakeTransport $http;

    private function setupApp(array $config): MessagingService
    {
        $this->root = sys_get_temp_dir() . '/gnucms-msg-settings-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'ms' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $this->http = new FakeTransport();
        $service = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($service);
        return $service;
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
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsRequireAdminAndCsrfAndKapiKeyStaysHidden(array $config): void
    {
        $service = $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/settings/messaging'), '/admin/settings/messaging');
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/admin/settings/messaging')->getStatusCode());
        $this->signIn(true);
        $page = $this->get($this->app, '/admin/settings/messaging');
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('알림톡·문자 설정', $this->body($page));
        self::assertStringContainsString('href="/admin/settings/messaging"', $this->body($page));
        self::assertStringNotContainsString('데이터 설치·갱신', $this->body($page));
        self::assertSame('no-store', $page->getHeaderLine('Cache-Control'));
        $key = bin2hex(random_bytes(24));
        $settings = ['action' => 'save', 'environment' => 'test', 'account' => 'kapi-web-test',
            'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'kapi_key' => $key];
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $settings)->getStatusCode());
        $saved = $this->post($this->app, '/admin/settings/messaging', $settings + ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $saved->getStatusCode());
        self::assertStringNotContainsString($key, $this->body($saved));
        self::assertStringContainsString('name="clear_kapi_key"', $this->body($saved));
        self::assertTrue($service->status('test')['kapi_configured']);
        $failed = $this->post($this->app, '/admin/settings/messaging', array_replace($settings, ['from' => 'invalid']) + ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(422, $failed->getStatusCode());
        self::assertStringNotContainsString($key, $this->body($failed));
        foreach (['/admin/settings/messaging'] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $path);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('action="/cms' . $path . '"', $this->body($response));
        }
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testWebAccountMustVerifyBeforeEnablingAndEnvironmentPolicyIsShown(array $config): void
    {
        $service = $this->setupApp($config);
        $this->signIn(true);
        $response = $this->post($this->app, '/admin/settings/messaging', ['action' => 'save', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'account_type' => 'web', 'account' => 'web-account', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('value="web" selected', $this->body($response));
        self::assertStringContainsString('value="enable" disabled', $this->body($response));
        self::assertSame('web', $service->status('test')['account_type']);
        self::assertSame(422, $this->post($this->app, '/admin/settings/messaging', ['action' => 'enable', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $service->connect('test');
        $enabled = $this->post($this->app, '/admin/settings/messaging', ['action' => 'enable', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(200, $enabled->getStatusCode());
        self::assertTrue($service->status('test')['enabled']);
        $service->settings->save('live', ['account' => 'live-account', 'password' => bin2hex(random_bytes(20)), 'from' => '0212345678']);
        foreach (['test', 'live'] as $environment) {
            $html = $this->body($this->get($this->app, '/admin/settings/messaging', ['environment' => $environment]));
            self::assertStringContainsString('테스트 환경', $html);
            self::assertStringNotContainsString('검수 환경', $html);
            self::assertStringNotContainsString('name="test_only"', $html);
            if ($environment === 'test') self::assertStringContainsString('name="test_phone"', $html);
            else { self::assertStringNotContainsString('name="test_phone"', $html); self::assertStringContainsString('발송 대상: 입력한 국내 휴대폰 번호', $html); }
        }
        self::assertSame(1, $this->http->count('/v1/token'));
    }

    #[DataProvider('connectionProvider')]
    public function testSavedPasswordRevealRequiresAdminCsrfAndCurrentAccountSettings(array $config): void
    {
        $service = $this->setupApp($config);
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'test-account', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $settings = $service->settings->summary('test');
        $input = ['action' => 'reveal-password', 'environment' => 'test', 'account' => $settings['account'], 'revision' => $settings['revision']];
        $this->assertLoginRedirect($this->post($this->app, '/admin/settings/messaging', $input));
        $this->signIn(false);
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $input + ['csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/admin/settings/messaging', ['action' => 'reveal-password']));
        self::assertStringNotContainsString($password, $page);
        self::assertStringContainsString('aria-controls="password"', $page);
        self::assertSame(403, $this->post($this->app, '/admin/settings/messaging', $input)->getStatusCode());
        $input['csrf_token'] = $_SESSION['csrf_token'];
        foreach (['revision' => bin2hex(random_bytes(16)), 'account' => 'another-account', 'environment' => 'live'] as $key => $value) {
            $response = $this->post($this->app, '/admin/settings/messaging', array_replace($input, [$key => $value]));
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString($password, $this->body($response));
        }
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms/admin/settings/messaging')->withParsedBody($input);
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($password, json_decode($this->body($response), true, 8, JSON_THROW_ON_ERROR)['password']);
        self::assertSame($settings, $service->settings->summary('test'));
    }

    #[DataProvider('connectionProvider')]
    public function testAuthenticationFailureShowsCodeWithoutSecrets(array $config): void
    {
        $service = $this->setupApp($config);
        $password = bin2hex(random_bytes(20));
        $service->settings->save('test', ['account' => 'web-account', 'account_type' => 'web', 'password' => $password,
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->signIn(true);
        foreach ([3007 => 'API 연동용 모듈 비밀번호가 유효하지 않습니다.', 3010 => '해당 계정의 REST API 사용 가능 여부'] as $code => $expected) {
            $this->http->respond = static fn (): array => ['status' => 400, 'body' => ['code' => $code, 'description' => $password]];
            $response = $this->post($this->app, '/admin/settings/messaging', ['action' => 'connect', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
            self::assertSame(503, $response->getStatusCode());
            self::assertStringContainsString('코드 ' . $code, $this->body($response));
            self::assertStringContainsString($expected, $this->body($response));
            self::assertStringNotContainsString($password, $this->body($response));
            self::assertFalse($service->status('test')['api_verified']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testWebhookUsesTokenNotSessionAndAcceptsLegacyPath(array $config): void
    {
        $service = $this->setupApp($config);
        $service->settings->save('test', ['account' => 'test-account', 'password' => bin2hex(random_bytes(20)), 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'webhook_ips' => '127.0.0.1']);
        $settings = $service->settings->read('test');
        $this->signIn(true);
        $shown = $this->post($this->app, '/admin/settings/messaging', ['action' => 'webhook', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertStringContainsString('/messaging/bizppurio/result?environment=test&amp;token=', $this->body($shown));
        $send = function (string $path, string $token, string $ip) use ($settings): \Psr\Http\Message\ResponseInterface {
            $request = (new ServerRequestFactory())->createServerRequest('POST', '/cms' . $path . '?'
                . http_build_query(['environment' => 'test', 'token' => $token]), ['REMOTE_ADDR' => $ip])->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode(['MEDIA' => 'AT', 'MSGID' => 'unmatched', 'CMSGID' => 'missing', 'PHONE' => '01000000000',
                'RESULT' => '7000', 'UNIXTIME' => (string) time()], JSON_THROW_ON_ERROR));
            return Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
        };
        self::assertSame(403, $send('/messaging/bizppurio/result', 'invalid', '127.0.0.1')->getStatusCode());
        self::assertSame(403, $send('/messaging/bizppurio/result', $settings['webhook_token'], '192.0.2.1')->getStatusCode());
        foreach (['/messaging/bizppurio/result', '/plugins/bizppurio/result'] as $path) {
            $response = $send($path, $settings['webhook_token'], '127.0.0.1');
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertSame('', $response->getHeaderLine('Set-Cookie'));
            self::assertSame('{"accepted":true}', (string) $response->getBody());
        }
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Web/MessagingSettingsTest.php 2>&1 | tail -5
```

Expected: 404로 첫 단언 실패.

- [ ] **Step 3: 공용 조각과 템플릿을 가져온다**

```bash
git show feat/bizppurio-messaging:plugins/bizppurio/templates/_password_toggle.php > templates/default/admin/_password_toggle.php
git show feat/bizppurio-messaging:src/Extension/templates/admin/_phone_input.php > templates/default/admin/_phone_input.php
git show feat/bizppurio-messaging:plugins/bizppurio/templates/settings.php > templates/default/admin/messaging_settings.php
```

`templates/default/admin/messaging_settings.php`를 다음 순서로 고친다.

1. 첫 4줄(`layout`, `title`, `admin_section`, `extension_body` 시작)을 다음으로 교체:

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>알림톡·문자 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>site<?php $this->stop() ?>
<?php $this->start('body') ?>
```

2. `<?php $this->insert('admin/_extension_header', [...]) ?>` 한 줄을 다음으로 교체:

```php
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li><a href="<?= $this->url('admin.settings') ?>">설정</a></li><li aria-current="page">알림톡·문자</li></ul></div>
<?php $this->insert('admin/_settings_tabs', ['active' => 'messaging']) ?>
<div class="page-head"><div><h1>알림톡·문자 설정</h1><p class="muted">비즈뿌리오 계정과 환경별 발송 허용을 관리합니다. 이니톡 결제 알림톡과 메시지 발송 화면이 이 설정을 사용합니다.</p></div>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.messaging.templates') ?>?environment=<?= $this->e($environment) ?>">알림톡 운영</a><a class="btn btn-sm" href="<?= $this->url('admin.messaging.sms.send') ?>?environment=<?= $this->e($environment) ?>">문자 발송</a></div></div>
```

3. `<?php if (!$ready): ?>`로 시작하는 줄부터 `<?php else: ?>` 줄까지(데이터 설치 카드) 삭제하고, `<?php endif ?><section class="card card-body extension-panel"><h2 class="card-title">비즈뿌리오 사이트 웹발송</h2>`의 앞 `<?php endif ?>`를 지운다.
4. 치환:

```bash
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/plugins/bizppurio/settings#<?= \$this->url(\x27admin.settings.messaging\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/send\?environment=#<?= \$this->url(\x27admin.messaging.send\x27) ?>?environment=#g; s#<\?= \$this->e\(\$base\) \?>/modules/sms/send\?environment=#<?= \$this->url(\x27admin.messaging.sms.send\x27) ?>?environment=#g; s#\$this->insert\(\x27_password_toggle\x27\)#\$this->insert(\x27admin/_password_toggle\x27)#; s#\$this->insert\(\x27_phone_input\x27\)#\$this->insert(\x27admin/_phone_input\x27)#' templates/default/admin/messaging_settings.php
grep -n '\$base\|plugins/bizppurio\|modules/alimtalk\|modules/sms\|\$ready' templates/default/admin/messaging_settings.php; echo "(no output above = OK)"
```

`$ready`가 남아 있으면 그 조건문을 제거한다(항상 준비 상태).

- [ ] **Step 4: 컨트롤러를 쓴다**

`src/Messaging/SettingsController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Messaging;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/** 설정 → 알림톡·문자. 전체 관리자 전용이며 POST는 세션 CSRF를 검사한다. */
final class SettingsController
{
    public function __construct(private App $app)
    {
    }

    public function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $service = $this->app->messaging();
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        $environment = Input::environment($input['environment'] ?? 'test');
        $notice = '';
        $errors = [];
        $webhook = null;
        try {
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($action === 'reveal-password') {
                    $settings = $service->settings->read($environment);
                    if ($settings === null || ($input['revision'] ?? null) !== $settings['revision'] || ($input['account'] ?? null) !== $settings['account']) {
                        throw DomainError::validation(['password' => '계정 설정이 변경되었습니다. 화면을 새로 연 뒤 확인해 주세요.']);
                    }
                    $response->getBody()->write(json_encode(['password' => $settings['password']], JSON_THROW_ON_ERROR));
                    return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
                        ->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
                        ->withHeader('X-Content-Type-Options', 'nosniff');
                } elseif ($action === 'save') {
                    $service->settings->save($environment, $input);
                    $notice = '설정을 저장했습니다. 발송은 정지 상태입니다.';
                } elseif (in_array($action, ['enable', 'disable'], true)) {
                    $service->settings->setEnabled($environment, $action === 'enable');
                    $notice = $action === 'enable' ? '발송을 허용했습니다.' : '발송을 정지했습니다. 결과 수신은 계속됩니다.';
                } elseif ($action === 'connect') {
                    $service->connect($environment);
                    $notice = 'API 인증을 확인했습니다. 발송을 허용한 뒤 발송 화면에서 수신을 확인해 주세요. 메시지는 발송하지 않았습니다.';
                } elseif ($action === 'webhook') {
                    $settings = $service->settings->read($environment);
                    if ($settings === null) throw DomainError::validation(['settings' => '계정을 먼저 저장해 주세요.']);
                    $webhook = RouteContext::fromRequest($request)->getBasePath() . '/messaging/bizppurio/result?'
                        . http_build_query(['environment' => $environment, 'token' => $settings['webhook_token']]);
                    $notice = '아래 경로 앞에 사이트의 HTTPS 도메인을 붙여 결과 수신 URL로 등록해 주세요.';
                } else {
                    throw DomainError::validation(['action' => '설정 작업을 확인해 주세요.']);
                }
            }
        } catch (DomainError $e) {
            $errors = $e->code() === 'BIZPPURIO_AUTH' ? [$e->getMessage()]
                : ($e->status() >= 500 ? ['작업을 완료하지 못했습니다. 서버 연결·저장소 상태를 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]));
            $response = $response->withStatus($e->status());
        } catch (Throwable $e) {
            $errors = ['작업을 완료하지 못했습니다. 서버 연결·저장소 상태를 확인해 주세요.'];
            $response = $response->withStatus(503);
        }
        try {
            $settings = $service->settings->summary($environment);
        } catch (Throwable $e) {
            $settings = ['configured' => false, 'enabled' => false];
            $errors = ['저장된 설정을 읽지 못했습니다. 암호화 키와 DB 복구 상태를 확인해 주세요.'];
            $response = $response->withStatus(503);
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/messaging_settings',
            ['base' => RouteContext::fromRequest($request)->getBasePath(), 'environment' => $environment, 'ready' => true,
                'settings' => $settings, 'notice' => $notice, 'errors' => $errors, 'webhook' => $webhook]
        );
    }
}
```

- [ ] **Step 5: 라우트·웹훅 미들웨어·설정 탭을 등록한다**

`src/Web/Routes.php` 상단 `use` 목록에 `use GnuCms\Web\Middleware\ExternalRequests;` 추가. `$slim->post('/admin/settings/payment', ...)` 다음 줄에 추가:

```php
        $messagingSettings = new \GnuCms\Messaging\SettingsController($app);
        $slim->get('/admin/settings/messaging', [$messagingSettings, 'handle'])->setName('admin.settings.messaging');
        $slim->post('/admin/settings/messaging', [$messagingSettings, 'handle']);
```

6번 작업 전까지 템플릿 링크가 깨지지 않도록 같은 자리에 **임시** 라우트를 등록한다(6번 작업 Step 4에서 실제 라우트로 교체):

```php
        // 임시 라우트: 6번 작업에서 실제 운영 화면 라우트로 교체한다
        foreach (['admin.messaging.templates' => '/admin/messaging/templates', 'admin.messaging.send' => '/admin/messaging/send',
            'admin.messaging.sms.send' => '/admin/messaging/sms/send'] as $name => $path) {
            $slim->get($path, static fn ($request, $response) => $response->withStatus(404))->setName($name);
        }
```

`Routes::register()` 끝의 `\GnuCms\Extension\AdminRoutes::register($slim, $app);` 바로 앞에 웹훅 미들웨어를 추가한다:

```php
        // 비즈뿌리오 결과 웹훅. 세션·CSRF·HTML 없이 토큰(과 선택적 IP)으로만 인증한다. 이전 플러그인 주소도 받는다.
        $messaging = $app->messaging();
        $webhook = [
            static fn (ServerRequestInterface $request): bool => $messaging->results->authenticate($request),
            static function (ServerRequestInterface $request, ResponseInterface $response) use ($messaging): ResponseInterface {
                $messaging->results->receive($request->getQueryParams()['environment'], $request->getParsedBody());
                $response->getBody()->write('{"accepted":true}');
                return $response;
            },
            65536, 'application/json',
        ];
        $slim->add(new ExternalRequests(['/messaging/bizppurio/result' => $webhook, '/plugins/bizppurio/result' => $webhook], $slim->getBasePath()));
```

`templates/default/admin/_settings_tabs.php`의 `결제` 탭 줄 앞에 추가:

```php
  <a class="tab<?= $active === 'messaging' ? ' tab-active' : '' ?>"<?= $active === 'messaging' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.messaging') ?>">알림톡·문자</a>
```

- [ ] **Step 6: 테스트와 전체 스위트를 돌린다**

```bash
php -l src/Web/Routes.php && php -l src/Messaging/SettingsController.php
./vendor/bin/phpunit tests/Web/MessagingSettingsTest.php
./vendor/bin/phpunit
```

Expected: `OK`. `tests/Extension`의 확장 관리 테스트가 코어 예약 경로 목록을 단언한다면 `/messaging/bizppurio/result`는 Slim 라우트가 아니므로 영향이 없다.

- [ ] **Step 7: 커밋**

```bash
git add src/Messaging/SettingsController.php templates/default/admin/messaging_settings.php templates/default/admin/_password_toggle.php templates/default/admin/_phone_input.php templates/default/admin/_settings_tabs.php src/Web/Routes.php tests/Web/MessagingSettingsTest.php
git commit -m "feat: add Bizppurio messaging settings screen and result webhook

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: 알림톡·문자 운영 화면 이관

**Files:**
- Create: `src/Web/Controller/MessagingController.php`, `templates/default/admin/messaging/{page,templates,_remote_templates,send,history,detail,sms_page,sms_send,sms_history,sms_detail,_phone}.php`
- Modify: `src/Web/Routes.php` (임시 라우트 교체), `templates/default/admin/_sidebar.php`
- Test: `tests/Web/MessagingTest.php`

**Interfaces:**
- Consumes: `App::messaging()`
- Produces: 라우트 `admin.messaging` (GET `/admin/messaging` → 템플릿 화면으로 303), `admin.messaging.templates` (`/admin/messaging/templates`), `admin.messaging.send`, `admin.messaging.history`, `admin.messaging.detail` (`/admin/messaging/history/{id}`), `admin.messaging.sms.send`, `admin.messaging.sms.history`, `admin.messaging.sms.detail` (`/admin/messaging/sms/history/{id}`). 모두 GET/POST, 쿼리 `environment=`, POST `action=`(templates: `save|remote-list|remote-detail|remote-import`, send: `preview|send`, detail: `retry|refresh-result`, history: `purge`).

- [ ] **Step 1: 운영 웹 테스트를 쓴다**

`tests/Web/MessagingTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Messaging\MessagingService;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Messaging\TextFixtures;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MessagingTest extends WebTestCase
{
    private string $root;
    private App $app;
    private FakeTransport $http;

    private function setupApp(array $config): MessagingService
    {
        $this->root = sys_get_temp_dir() . '/gnucms-msg-web-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'mw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $this->http = new FakeTransport();
        $service = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($service);
        return $service;
    }

    private function configure(MessagingService $service, bool $senderKey = true): void
    {
        $service->settings->save('test', ['account' => 'ops-web-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => $senderKey ? bin2hex(random_bytes(20)) : '', 'from' => '0212345678', 'test_phone' => '01000000000']);
        $service->settings->setEnabled('test', true);
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

    #[DataProvider('connectionProvider')]
    public function testOperationsScreensRequireAdminAndCsrfAndWorkInsideSubdirectory(array $config): void
    {
        $service = $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/messaging/templates'), '/admin/messaging/templates');
        $this->signIn(false);
        foreach (['/admin/messaging', '/admin/messaging/send', '/admin/messaging/sms/send'] as $path) self::assertSame(403, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(true);
        self::assertSame(303, $this->get($this->app, '/admin/messaging')->getStatusCode());
        self::assertSame('/admin/messaging/templates', $this->get($this->app, '/admin/messaging')->getHeaderLine('Location'));
        $this->configure($service);
        foreach (['/admin/messaging/send' => ['preview', 'send'], '/admin/messaging/history' => ['purge'], '/admin/messaging/sms/send' => ['preview', 'send']] as $path => $actions) {
            foreach ($actions as $action) self::assertSame(403, $this->post($this->app, $path, ['action' => $action, 'environment' => 'test'])->getStatusCode());
        }
        foreach (['/admin/messaging/templates', '/admin/messaging/send', '/admin/messaging/history', '/admin/messaging/sms/send', '/admin/messaging/sms/history'] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $path);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('action="/cms' . $path . '"', $this->body($response));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
        $sidebar = $this->body($this->get($this->app, '/admin'));
        self::assertStringContainsString('메시지 발송', $sidebar);
        self::assertSame(404, $this->get($this->app, '/modules/alimtalk/home')->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/plugins/bizppurio/settings')->getStatusCode());
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkPreviewIsEscapedAndSendUsesOnlyTheConfirmedSnapshot(array $config): void
    {
        $service = $this->setupApp($config);
        $this->configure($service);
        $template = $service->templates->save('test', ['code' => 'hello', 'name' => '<b>안내</b>', 'message' => '#{이름}님 안내입니다.']);
        $this->signIn(true);
        $preview = $this->post($this->app, '/admin/messaging/send', ['action' => 'preview', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'template_id' => $template['id'], 'revision' => $template['revision'], 'phone' => '01000000000', 'variables' => ['이름' => '<script>alert(1)</script>']]);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;', $this->body($preview));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($preview));
        self::assertStringContainsString('name="confirmation"', $this->body($preview));
        self::assertSame(0, $service->history(['environment' => 'test'])['total']);
        self::assertSame(422, $this->post($this->app, '/admin/messaging/send', ['action' => 'send', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'], 'confirmation' => 'not-a-confirmation'])->getStatusCode());
        session_start();
        $token = array_key_last($_SESSION['alimtalk_previews']);
        session_write_close();
        $sent = $this->post($this->app, '/admin/messaging/send', ['action' => 'send', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'confirmation' => $token, 'phone' => '01011111111', 'variables' => ['이름' => '변조']]);
        self::assertSame(303, $sent->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/history/', $sent->getHeaderLine('Location'));
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('01000000000', $body['to']);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;님 안내입니다.', $this->body($preview));
        $detail = $this->get($this->app, parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH), ['environment' => 'test']);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('발송 상세', $this->body($detail));
        self::assertSame(404, $this->get($this->app, parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH), ['environment' => 'live'])->getStatusCode());
        $history = $this->get($this->app, '/admin/messaging/history', ['environment' => 'test']);
        self::assertStringContainsString('010-****-0000', $this->body($history));
        self::assertStringNotContainsString('01000000000', $this->body($history));
    }

    #[DataProvider('connectionProvider')]
    public function testRemoteTemplateScreenListsEscapesAndImports(array $config): void
    {
        $service = $this->setupApp($config);
        $service->settings->save('test', ['account' => 'kapi-view', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'kapi_key' => bin2hex(random_bytes(24)),
            'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->signIn(true);
        $remote = ['senderKey' => $service->settings->read('test')['senderkey'], 'senderKeyType' => 'S',
            'templateCode' => 'notice', 'templateName' => '<b>예약 안내</b>', 'templateContent' => '#{이름}님 <script>alert(1)</script>',
            'templateMessageType' => 'BA', 'templateEmphasizeType' => 'NONE', 'inspectionStatus' => 'APR',
            'status' => 'A', 'serviceStatus' => 'ACT', 'block' => false, 'dormant' => false, 'buttons' => []];
        $this->http->respond = static fn ($environment, $path) => ['status' => 200, 'body' => $path === '/v3/kakao/template/list'
            ? ['code' => '200', 'totalCount' => 21, 'totalPage' => 2, 'currentPage' => 1, 'data' => ['list' => [$remote]]]
            : ['code' => '200', 'data' => $remote]];
        $post = fn (array $input) => $this->post($this->app, '/admin/messaging/templates', $input + ['environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        $list = $post(['action' => 'remote-list']);
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;예약 안내&lt;/b&gt;', $this->body($list));
        self::assertStringContainsString('name="remote_page" value="2"', $this->body($list));
        $detail = $post(['action' => 'remote-detail', 'remote_code' => 'notice']);
        self::assertStringContainsString('GNUCMS로 가져오기', $this->body($detail));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($detail));
        $imported = $post(['action' => 'remote-import', 'remote_code' => 'notice', 'local_revision' => '',
            'config_revision' => $service->settings->read('test')['revision'], 'message' => '변조']);
        self::assertSame(303, $imported->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/templates?environment=test&id=', $imported->getHeaderLine('Location'));
        parse_str((string) parse_url($imported->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $page = $this->get($this->app, '/admin/messaging/templates', $query);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('비즈뿌리오 최신 상태 확인', $this->body($page));
        self::assertStringContainsString('id="message" name="message" readonly', $this->body($page));
        self::assertSame($remote['templateContent'], $service->templates->all('test')[0]['message']);
        $this->http->respond = static fn () => ['status' => 403, 'body' => ['code' => '403', 'message' => 'provider-internal-diagnostic']];
        $failure = $post(['action' => 'remote-list']);
        self::assertSame(503, $failure->getStatusCode());
        self::assertStringContainsString('HTTP 403', $this->body($failure));
        self::assertStringNotContainsString('provider-internal-diagnostic', $this->body($failure));
        self::assertSame(0, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testSmsConfirmedSnapshotCannotBeTamperedWithAndExpires(array $config): void
    {
        $service = $this->setupApp($config);
        $this->configure($service, false);
        $this->signIn(true);
        Clock::freeze('2026-09-07 01:00:00');
        $input = ['action' => 'preview', 'environment' => 'test', 'type' => 'lms', 'phone' => '010-0000-0000',
            'subject' => '<b>제목 ʕ</b>', 'message' => TextFixtures::ART . "\n<script>alert(1)</script>", 'csrf_token' => $_SESSION['csrf_token']];
        $preview = $this->post($this->app, '/admin/messaging/sms/send', $input);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->body($preview));
        self::assertStringContainsString('본문 길이: 업체 확인 필요', $this->body($preview));
        self::assertSame(0, $this->http->count('/v3/message'));
        session_start();
        $token = array_key_last($_SESSION['sms_previews']);
        session_write_close();
        self::assertSame(422, $this->post($this->app, '/admin/messaging/sms/send', ['action' => 'send', 'confirmation' => $token, 'environment' => 'live', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $sendInput = ['action' => 'send', 'confirmation' => $token, 'environment' => 'test', 'message' => '변조', 'phone' => '01011111111', 'type' => 'sms', 'csrf_token' => $_SESSION['csrf_token']];
        $sent = $this->post($this->app, '/admin/messaging/sms/send', $sendInput);
        self::assertSame(303, $sent->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/sms/history/', $sent->getHeaderLine('Location'));
        self::assertSame($sent->getHeaderLine('Location'), $this->post($this->app, '/admin/messaging/sms/send', $sendInput)->getHeaderLine('Location'));
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('lms', $body['type']);
        self::assertSame('01000000000', $body['to']);
        self::assertSame($input['message'], $body['content']['lms']['message']);
        $path = (string) parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH);
        $detail = $this->get($this->app, $path, ['environment' => 'test']);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;제목 ʕ&lt;/b&gt;', $this->body($detail));
        self::assertStringContainsString('010-0000-0000', $this->body($detail));
        foreach (['retry', 'refresh-result'] as $action) {
            self::assertSame(404, $this->post($this->app, $path, ['action' => $action, 'environment' => 'live', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        }
        $history = $this->get($this->app, '/admin/messaging/sms/history', ['environment' => 'test', 'type' => 'lms']);
        self::assertStringContainsString('LMS', $this->body($history));
        self::assertStringContainsString('010-****-0000', $this->body($history));
        self::assertStringNotContainsString($input['message'], $this->body($history));
        $this->post($this->app, '/admin/messaging/sms/send', $input);
        session_start();
        $next = array_key_last($_SESSION['sms_previews']);
        session_write_close();
        Clock::freeze('2026-09-07 01:11:00');
        self::assertSame(422, $this->post($this->app, '/admin/messaging/sms/send', ['action' => 'send', 'confirmation' => $next, 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        self::assertSame(1, $this->http->count('/v3/message'));
        Clock::freeze('2027-01-01 00:00:00');
        $purged = $this->post($this->app, '/admin/messaging/sms/history', ['action' => 'purge', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertStringContainsString('1건', $this->body($purged));
        self::assertStringContainsString('보관 만료', $this->body($this->get($this->app, $path, ['environment' => 'test'])));
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Web/MessagingTest.php 2>&1 | tail -5
```

Expected: 임시 라우트가 404를 돌려주거나 라우트 부재로 실패.

- [ ] **Step 3: 컨트롤러를 쓴다**

`src/Web/Controller/MessagingController.php` (두 모듈 컨트롤러를 채널 인자로 합친 것):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Messaging\Input;
use GnuCms\Support\Clock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/** 운영 → 메시지 발송. 알림톡(templates/send/history/detail)과 문자(sms-send/sms-history/sms-detail) 화면. */
final class MessagingController
{
    public const STATUS_LABELS = ['prepared' => '준비', 'sending' => '접수 확인 중', 'accepted' => '접수됨', 'rejected' => '접수 거절',
        'unknown' => '접수 불명확', 'pending' => '결과 대기', 'delivered' => '도달 성공', 'failed' => '도달 실패', 'uncertain' => '도달 불확실'];

    public function __construct(private App $app)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.messaging.templates');
        return $response->withStatus(303)->withHeader('Location', $url);
    }

    /** @param string $page templates|send|history|detail|sms-send|sms-history|sms-detail */
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $service = $this->app->messaging();
        $text = str_starts_with($page, 'sms-');
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        if (isset($args['id'])) $input['id'] = $args['id'];
        $environment = $input['environment'] ?? 'test';
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '환경을 확인해 주세요.']);
        $input['environment'] = $environment;
        $routes = RouteContext::fromRequest($request)->getRouteParser();
        $base = RouteContext::fromRequest($request)->getBasePath();
        $data = ['page' => $page, 'base' => $base, 'environment' => $environment, 'ready' => true,
            'errors' => [], 'notice' => '', 'templates' => [], 'selected' => null, 'preview' => null,
            'confirmation' => null, 'detail' => null, 'remote_list' => null, 'remote_detail' => null,
            'history' => ['items' => [], 'page' => 1, 'total' => 0],
            'account_status' => ['configured' => false, 'enabled' => false, 'api_verified' => false, 'account_type' => 'module'],
            'values' => $input, 'csrf_token' => $_SESSION['csrf_token'] ?? '', 'status_labels' => self::STATUS_LABELS,
            'time' => static fn ($timestamp): string => (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i:s')];
        try {
            $data['account_status'] = $service->status($environment);
            if (!$text) {
                $data['templates'] = $service->templateAction('list', ['environment' => $environment]);
                $selectedId = $input[$page === 'templates' ? 'id' : 'template_id'] ?? '';
                if (is_string($selectedId) && $selectedId !== '') {
                    $selected = $service->templateAction('get', ['id' => $selectedId]);
                    if ($selected['environment'] !== $environment) throw DomainError::notFound('이 환경의 템플릿이 아닙니다.');
                    $data['selected'] = $selected;
                }
            }
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($page === 'templates' && in_array($action, ['remote-list', 'remote-detail', 'remote-import'], true)) {
                    $result = $service->templateAction($action, $input);
                    if ($action === 'remote-import') return $this->redirect($response, $routes->urlFor('admin.messaging.templates') . '?environment=' . $environment . '&id=' . $result['id']);
                    $data[$action === 'remote-list' ? 'remote_list' : 'remote_detail'] = $result;
                } elseif ($page === 'templates' && $action === 'save') {
                    if (isset($input['buttons']) && is_array($input['buttons'])) {
                        $input['buttons'] = array_values(array_filter($input['buttons'], static fn ($row): bool => !is_array($row)
                            || ($row['name'] ?? '') !== '' || ($row['url_mobile'] ?? '') !== '' || ($row['url_pc'] ?? '') !== ''));
                    }
                    $saved = $service->templateAction('save', $input);
                    return $this->redirect($response, $routes->urlFor('admin.messaging.templates') . '?environment=' . $environment . '&id=' . $saved['id']);
                } elseif ($page === 'send' && $action === 'preview') {
                    $preview = $service->preview($input);
                    if ($preview['environment'] !== $environment) throw DomainError::validation(['environment' => '선택한 환경의 템플릿을 사용해 주세요.']);
                    $phone = $this->string($input, 'phone');
                    if (!preg_match('/^(?:010\d{8}|01[16789]\d{7,8})$/D', str_replace(['-', ' '], '', $phone))) throw DomainError::validation(['phone' => '국내 휴대폰 번호를 입력해 주세요.']);
                    $token = $this->remember('alimtalk_previews', ['environment' => $environment, 'template_id' => $preview['template_id'], 'revision' => $preview['revision'],
                        'config_revision' => $preview['config_revision'], 'phone' => $phone, 'variables' => $input['variables'] ?? []]);
                    $data['preview'] = $preview;
                    $data['confirmation'] = $token;
                } elseif ($page === 'sms-send' && $action === 'preview') {
                    $preview = $service->previewText($input);
                    $token = $this->remember('sms_previews', ['environment' => $environment, 'type' => $preview['type'], 'phone' => $preview['phone'],
                        'subject' => $preview['subject'], 'message' => $preview['message'], 'config_revision' => $preview['config_revision']]);
                    $data['preview'] = $preview;
                    $data['confirmation'] = $token;
                } elseif (in_array($page, ['send', 'sms-send'], true) && $action === 'send') {
                    $pending = $this->confirmed($text ? 'sms_previews' : 'alimtalk_previews', $this->string($input, 'confirmation'), $environment);
                    $sent = $text ? $service->sendText($pending) : $service->send($pending);
                    return $this->redirect($response, $routes->urlFor($text ? 'admin.messaging.sms.detail' : 'admin.messaging.detail', ['id' => $sent['id']]) . '?environment=' . $environment);
                } elseif (in_array($page, ['detail', 'sms-detail'], true) && in_array($action, ['retry', 'refresh-result'], true)) {
                    $id = $this->string($input, 'id');
                    $this->detail($service, $id, $environment, $text);
                    if ($action === 'retry') { $text ? $service->retryText($id) : $service->retry($id); }
                    else { $text ? $service->refreshText($id) : $service->refresh($id); }
                    $data['notice'] = $action === 'retry' ? '재시도 결과를 확인해 주세요.' : '결과 재요청을 접수했습니다. 웹훅 수신 후 상태가 갱신됩니다.';
                } elseif (in_array($page, ['history', 'sms-history'], true) && $action === 'purge') {
                    $count = $text ? $service->purgeText() : $service->purge();
                    $data['notice'] = '90일이 지난 ' . ($text ? '문자' : '발송') . ' ' . $count . '건의 수신정보·내용을 삭제했습니다.';
                } else {
                    throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                }
            }
            if (in_array($page, ['history', 'sms-history'], true)) $data['history'] = $text ? $service->textHistory($input) : $service->history($input);
            if (in_array($page, ['detail', 'sms-detail'], true)) $data['detail'] = $this->detail($service, $this->string($input, 'id'), $environment, $text);
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->code() === 'BIZPPURIO_KAPI' ? [$e->getMessage()]
                : ($e->status() >= 500 ? ['작업을 완료하지 못했습니다. 알림톡·문자 설정과 서버 연결을 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]));
        } catch (Throwable $e) {
            $response = $response->withStatus(503);
            $data['errors'] = ['작업을 완료하지 못했습니다. 알림톡·문자 설정과 서버 연결을 확인해 주세요.'];
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            $text ? 'admin/messaging/sms_page' : 'admin/messaging/page', $data
        );
    }

    private function detail(\GnuCms\Messaging\MessagingService $service, string $id, string $environment, bool $text): array
    {
        $detail = $text ? $service->textDetail($id) : $service->detail($id);
        if ($detail['environment'] !== $environment) throw DomainError::notFound('이 환경의 발송 이력이 아닙니다.');
        return $detail;
    }

    /** 미리보기 확인값을 세션에 10분 보관한다(최근 10개). 발송은 이 값만 신뢰한다. */
    private function remember(string $bucket, array $input): string
    {
        $token = bin2hex(random_bytes(16));
        $pending = is_array($_SESSION[$bucket] ?? null) ? $_SESSION[$bucket] : [];
        foreach ($pending as $key => $entry) if (($entry['expires'] ?? 0) < Clock::timestamp()) unset($pending[$key]);
        if (count($pending) >= 10) array_shift($pending);
        $pending[$token] = ['expires' => Clock::timestamp() + 600, 'input' => $input + ['idempotency_key' => $token]];
        $_SESSION[$bucket] = $pending;
        return $token;
    }

    private function confirmed(string $bucket, string $token, string $environment): array
    {
        $pending = $_SESSION[$bucket][$token] ?? null;
        if (!is_array($pending) || $pending['expires'] < Clock::timestamp() || $pending['input']['environment'] !== $environment) {
            throw DomainError::validation(['preview' => '미리보기가 만료되었거나 환경이 다릅니다. 내용을 다시 확인해 주세요.']);
        }
        return $pending['input'];
    }

    private function string(array $input, string $key): string
    {
        if (!is_string($input[$key] ?? null)) throw DomainError::validation([$key => '입력값을 확인해 주세요.']);
        return $input[$key];
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $url);
    }
}
```

- [ ] **Step 4: 라우트와 사이드바를 등록한다**

`src/Web/Routes.php`: 5번 작업의 `// 임시 라우트` 블록(foreach 3줄 포함)을 삭제하고 그 자리에:

```php
        $messagingOps = new \GnuCms\Web\Controller\MessagingController($app);
        $slim->get('/admin/messaging', [$messagingOps, 'index'])->setName('admin.messaging');
        foreach (['admin.messaging.templates' => ['/admin/messaging/templates', 'templates'], 'admin.messaging.send' => ['/admin/messaging/send', 'send'],
            'admin.messaging.history' => ['/admin/messaging/history', 'history'], 'admin.messaging.detail' => ['/admin/messaging/history/{id:[a-f0-9]{32}}', 'detail'],
            'admin.messaging.sms.send' => ['/admin/messaging/sms/send', 'sms-send'], 'admin.messaging.sms.history' => ['/admin/messaging/sms/history', 'sms-history'],
            'admin.messaging.sms.detail' => ['/admin/messaging/sms/history/{id:[a-f0-9]{32}}', 'sms-detail']] as $name => [$path, $page]) {
            $slim->map(['GET', 'POST'], $path, static fn ($request, $response, array $args) => $messagingOps->handle($page, $request, $response, $args))->setName($name);
        }
```

`use GnuCms\Web\Controller\MessagingController;`를 상단 `use`에 추가하고 위 코드의 FQCN을 짧게 써도 된다.

`templates/default/admin/_sidebar.php`의 `로그인 기록` `<li>` 다음 줄에 추가:

```php
    <li><a href="<?= $this->url('admin.messaging') ?>"<?php if ($section === 'messaging'): ?> class="menu-active" aria-current="page"<?php endif ?> title="메시지 발송"><?= $this->icon('megaphone', 18) ?><span class="menu-text">메시지 발송</span></a></li>
```

- [ ] **Step 5: 템플릿을 가져와 고친다**

```bash
mkdir -p templates/default/admin/messaging
B=feat/bizppurio-messaging
for f in page templates _remote_templates send history detail; do git show $B:modules/alimtalk/templates/$f.php > templates/default/admin/messaging/$f.php; done
for f in page send history detail; do git show $B:modules/sms/templates/$f.php > templates/default/admin/messaging/sms_$f.php; done
git show $B:modules/sms/templates/_phone.php > templates/default/admin/messaging/_phone.php
```

`page.php`와 `sms_page.php`의 첫 4줄(`layout`~`extension_body` 시작)을 다음으로 교체한다(제목은 각각 `알림톡 발송 · `, `문자 발송 · `):

```php
<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>알림톡 발송 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>messaging<?php $this->stop() ?>
<?php $this->start('body') ?>
```

`page.php`의 `<?php $this->insert('admin/_extension_header', [...]) ?>` 줄을 다음으로 교체(`sms_page.php`는 제목을 `문자 발송`, 설명을 `SMS·LMS 문자를 작성하고 발송 결과를 확인합니다.`로):

```php
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li aria-current="page">메시지 발송</li></ul></div>
<div class="page-head"><div><h1>알림톡 발송</h1><p class="muted">승인 템플릿으로 발송하고 접수·도달 결과를 확인합니다.</p></div>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.messaging.sms.send') ?>?environment=<?= $this->e($environment) ?>">문자 발송</a><a class="btn btn-sm" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">알림톡·문자 설정</a></div></div>
```

(`sms_page.php`의 `row-actions`에는 `알림톡 발송`(`admin.messaging.send`)과 설정 링크를 둔다.)

두 `page.php`의 탭 `nav`(`foreach ([...] as $path => $label)`)를 라우트 이름 기반으로 교체한다. `page.php`:

```php
<nav class="tabs tabs-border settings-tabs" aria-label="알림톡 운영 메뉴"><?php foreach (['templates' => ['admin.messaging.templates', '템플릿'], 'send' => ['admin.messaging.send', '웹발송'], 'history' => ['admin.messaging.history', '발송 이력']] as $key => [$route, $label]): $active = $page === $key || ($page === 'detail' && $key === 'history'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>?environment=<?= $this->e($environment) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
```

`sms_page.php`:

```php
<nav class="tabs tabs-border settings-tabs" aria-label="문자 운영 메뉴"><?php foreach (['sms-send' => ['admin.messaging.sms.send', '문자 발송'], 'sms-history' => ['admin.messaging.sms.history', '발송 이력']] as $key => [$route, $label]): $active = $page === $key || ($page === 'sms-detail' && $key === 'sms-history'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>?environment=<?= $this->e($environment) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
```

`page.php`에서 `home` 페이지 분기(`in_array($page, ['home', 'send'], true)` → `$page === 'send'`; `<?php if ($page === 'home'): ?>...<?php endif ?>` 블록 삭제)와 환경 선택 폼의 `action`(`/modules/alimtalk/<page>` → `$this->url($page === 'detail' ? 'admin.messaging.history' : 'admin.messaging.' . $page)`)을 고친다. `sms_page.php`의 환경 선택 폼 `action`은 `$this->url($page === 'sms-detail' ? 'admin.messaging.sms.history' : ($page === 'sms-send' ? 'admin.messaging.sms.send' : 'admin.messaging.sms.history'))`로. 두 파일에서 `<?php if (!$ready): ?>...<?php elseif` 분기의 `!$ready` 카드 부분은 삭제하고 `elseif`를 `if`로 바꾼다. 분기 이름은 `sms_page.php`에서 `'send'`→`'sms-send'`, `'detail'`→`'sms-detail'`로 바꾸고, `insert` 대상은 `admin/messaging/sms_send` 등으로 바꾼다.

나머지 파일은 일괄 치환한다:

```bash
cd templates/default/admin/messaging
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/templates#<?= \$this->url(\x27admin.messaging.templates\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/send#<?= \$this->url(\x27admin.messaging.send\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/history#<?= \$this->url(\x27admin.messaging.history\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/plugins/bizppurio/settings#<?= \$this->url(\x27admin.settings.messaging\x27) ?>#g' page.php templates.php _remote_templates.php send.php history.php detail.php
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/sms/send#<?= \$this->url(\x27admin.messaging.sms.send\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/modules/sms/history#<?= \$this->url(\x27admin.messaging.sms.history\x27) ?>#g; s#<\?= \$this->e\(\$base\) \?>/plugins/bizppurio/settings#<?= \$this->url(\x27admin.settings.messaging\x27) ?>#g' sms_page.php sms_send.php sms_history.php sms_detail.php
# 상세 링크: detail?environment=X&id=Y → history/{id}?environment=X
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/detail\?environment=<\?= \$this->e\(\$environment\) \?>&amp;id=<\?= \$this->e\(\$item\[\x27id\x27\]\) \?>#<?= \$this->url(\x27admin.messaging.detail\x27, [\x27id\x27 => \$item[\x27id\x27]]) ?>?environment=<?= \$this->e(\$environment) ?>#g' history.php
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/sms/detail\?environment=<\?= \$this->e\(\$environment\) \?>&amp;id=<\?= \$this->e\(\$item\[\x27id\x27\]\) \?>#<?= \$this->url(\x27admin.messaging.sms.detail\x27, [\x27id\x27 => \$item[\x27id\x27]]) ?>?environment=<?= \$this->e(\$environment) ?>#g' sms_history.php
# 상세 화면의 POST 폼은 자기 주소로: /modules/alimtalk/detail → history/{id}
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/alimtalk/detail#<?= \$this->url(\x27admin.messaging.detail\x27, [\x27id\x27 => \$detail[\x27id\x27]]) ?>#g' detail.php
perl -pi -e 's#<\?= \$this->e\(\$base\) \?>/modules/sms/detail#<?= \$this->url(\x27admin.messaging.sms.detail\x27, [\x27id\x27 => \$detail[\x27id\x27]]) ?>#g' sms_detail.php
# 형제 템플릿 insert 경로와 조각
perl -pi -e 's#\$this->insert\(\x27(_remote_templates|templates|send|history|detail)\x27\)#\$this->insert(\x27admin/messaging/$1\x27)#g' page.php templates.php
perl -pi -e 's#\$this->insert\(\x27(send|history|detail)\x27\)#\$this->insert(\x27admin/messaging/sms_$1\x27)#g; s#\$this->insert\(\x27_phone\x27#\$this->insert(\x27admin/messaging/_phone\x27#g; s#\$this->fetch\(\x27_phone\x27#\$this->fetch(\x27admin/messaging/_phone\x27#g' sms_page.php sms_send.php sms_history.php sms_detail.php
perl -pi -e 's#\$this->insert\(\x27_phone_input\x27\)#\$this->insert(\x27admin/_phone_input\x27)#g' page.php sms_page.php
# 상세 화면 폼의 hidden id 입력은 라우트 인자로 대체됐지만 남겨도 무해하다.
cd /home/kagla/gnucms
grep -n '\$base\|/modules/\|/plugins/\|admin/extension\|extension_body\|_extension_header\|!\$ready' templates/default/admin/messaging/*.php; echo "(no output above = OK)"
```

`sms_history.php`와 `history.php`의 `<?php if ($page === 'history'): ?>` 정리(purge) 폼 조건은 각각 `$page === 'sms-history'`, `$page === 'history'`로 둔다. `sms_detail.php`·`sms_send.php`·`sms_history.php` 안의 `$page === 'send'` 류 비교가 있으면 `sms-` 접두사로 바꾼다.

- [ ] **Step 6: 테스트와 전체 스위트를 돌린다**

```bash
php -l src/Web/Controller/MessagingController.php && php -l src/Web/Routes.php
for f in templates/default/admin/messaging/*.php; do php -l "$f" >/dev/null || echo "SYNTAX $f"; done
./vendor/bin/phpunit tests/Web/MessagingTest.php tests/Web/MessagingSettingsTest.php
./vendor/bin/phpunit
```

Expected: `OK`. 템플릿 변수 부재로 500이 나면 `MessagingController::handle()`의 `$data` 키와 템플릿이 쓰는 변수를 대조한다(모듈 컨트롤러와 같은 키를 모두 넘긴다).

- [ ] **Step 7: 커밋**

```bash
git add src/Web/Controller/MessagingController.php src/Web/Routes.php templates/default/admin/_sidebar.php templates/default/admin/messaging tests/Web/MessagingTest.php
git commit -m "feat: add Alimtalk and SMS operations screens to the admin

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: 이전 패키지 승계 — 확장 상태 정리와 마이그레이션 테스트

**Files:**
- Modify: `src/Db/SchemaUpgrader.php` (`run()`)
- Test: `tests/Db/LegacyPackageAdoptionTest.php`

**Interfaces:**
- Consumes: `Schema::migrateAll()`, `Schema::migratePayment()`, `Schema::migrateMessaging()`, `GnuCms\Extension\StateStore`, `GnuCms\Extension\PackageSchema::backupTables()`
- Produces: 업그레이드 후 `storage/extensions/enabled.json`에서 `plugins/bizppurio`, `plugins/payment-inicis`, `modules/alimtalk`, `modules/sms`가 제거된다.

- [ ] **Step 1: 승계 테스트를 쓴다**

`tests/Db/LegacyPackageAdoptionTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Db;

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Db\SchemaUpgrader;
use GnuCms\Extension\PackageSchema;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LegacyPackageAdoptionTest extends DatabaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-adopt-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    /** 플러그인 판 1(channel 컬럼·인덱스 없음)과 결제 플러그인 판 2가 설치된 v22 DB를 흉내 낸다. */
    private function legacyDatabase(array $config): Connection
    {
        $config['prefix'] = 'lg' . bin2hex(random_bytes(3)) . '_';
        $db = $this->freshDatabase($config);
        foreach (['bp_receipts', 'bp_attempts', 'bp_dispatches', 'bp_templates', 'bp_settings', 'pay_inicis_transactions', 'pay_inicis_settings'] as $table) {
            $db->execute('DROP TABLE IF EXISTS ' . $db->table($table));
        }
        $text = $db->dialect()->typeMap()['{TEXT}'];
        $suffix = $db->dialect()->tableSuffix();
        $db->execute('CREATE TABLE ' . $db->table('bp_settings') . ' (environment VARCHAR(8) PRIMARY KEY, revision VARCHAR(32) NOT NULL, payload ' . $text . ' NOT NULL)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_templates') . ' (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, code VARCHAR(30) NOT NULL, revision VARCHAR(32) NOT NULL, payload ' . $text . ' NOT NULL, enabled SMALLINT NOT NULL, UNIQUE (environment, code))' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_dispatches') . ' (id VARCHAR(32) PRIMARY KEY, environment VARCHAR(8) NOT NULL, config_revision VARCHAR(32) NOT NULL, idempotency_key VARCHAR(64) NOT NULL UNIQUE, request_hash VARCHAR(64) NOT NULL, template_id VARCHAR(32) NOT NULL, template_name VARCHAR(100) NOT NULL, payload ' . $text . ' NOT NULL, phone_mask VARCHAR(30) NOT NULL, phone_hash VARCHAR(64) NOT NULL, submission VARCHAR(16) NOT NULL, delivery VARCHAR(16) NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, retry_at BIGINT NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_attempts') . ' (id VARCHAR(32) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, sequence_no INTEGER NOT NULL, refkey VARCHAR(32) NOT NULL UNIQUE, messagekey VARCHAR(128) NOT NULL, submission VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL, http_status INTEGER NOT NULL, created_at BIGINT NOT NULL)' . $suffix);
        $db->execute('CREATE TABLE ' . $db->table('bp_receipts') . ' (id VARCHAR(64) PRIMARY KEY, dispatch_id VARCHAR(32) NOT NULL, attempt_id VARCHAR(32) NOT NULL, message_id VARCHAR(128) NOT NULL, message_key VARCHAR(128) NOT NULL, media VARCHAR(16) NOT NULL, result_code VARCHAR(16) NOT NULL, event_at BIGINT NOT NULL, received_at BIGINT NOT NULL, matched SMALLINT NOT NULL)' . $suffix);
        foreach (['pay_inicis_settings', 'pay_inicis_transactions'] as $table) {
            $db->execute('CREATE TABLE ' . $db->table($table) . ' (id VARCHAR(32) PRIMARY KEY, payload ' . $text . ' NOT NULL)' . $suffix);
        }
        $db->execute('INSERT INTO ' . $db->table('bp_settings') . ' (environment, revision, payload) VALUES (?, ?, ?)', ['test', 'rev1', 'encrypted-settings']);
        $db->execute('INSERT INTO ' . $db->table('bp_dispatches') . ' (id, environment, config_revision, idempotency_key, request_hash, template_id, template_name, payload, phone_mask, phone_hash, submission, delivery, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [str_repeat('a', 32), 'test', 'rev1', 'key-1', 'hash', str_repeat('b', 32), '안내', 'encrypted', '010-****-0000', 'phash', 'accepted', 'pending', 1, 1]);
        $db->execute('INSERT INTO ' . $db->table('pay_inicis_settings') . ' (id, payload) VALUES (?, ?)', ['test', 'encrypted-merchant']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['plugins/bizppurio', 1, json_encode(['bp_settings', 'bp_templates', 'bp_dispatches', 'bp_attempts', 'bp_receipts']), 'ready']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['plugins/payment-inicis', 2, json_encode(['pay_inicis_settings', 'pay_inicis_transactions']), 'ready']);
        $db->execute('INSERT INTO ' . $db->table('extension_schemas') . ' (package_key, schema_version, table_names, state) VALUES (?, ?, ?, ?)',
            ['modules/demo-reservation', 1, json_encode(['demo_reservations']), 'failed']);
        $db->execute('UPDATE ' . $db->table('site_settings') . " SET setting_value = '22.legacy' WHERE setting_key = 'system.schema_version'");
        return $db;
    }

    #[DataProvider('connectionProvider')]
    public function testMigrationAdoptsPluginTablesKeepsRowsAndDropsRegistryEntries(array $config): void
    {
        $db = $this->legacyDatabase($config);
        $schema = new Schema($db);
        $schema->migrateAll();
        $schema->migrateAll();
        self::assertSame('encrypted-settings', $db->selectOne('SELECT payload FROM ' . $db->table('bp_settings') . " WHERE environment = 'test'")['payload']);
        self::assertSame('encrypted-merchant', $db->selectOne('SELECT payload FROM ' . $db->table('pay_inicis_settings') . " WHERE id = 'test'")['payload']);
        self::assertSame('at', $db->selectOne('SELECT channel FROM ' . $db->table('bp_dispatches'))['channel']);
        $keys = array_column($db->select('SELECT package_key FROM ' . $db->table('extension_schemas')), 'package_key');
        self::assertSame(['modules/demo-reservation'], $keys);
        self::assertSame([], (new PackageSchema($db, $this->root))->backupTables());
        self::assertSame($schema->stamp(), $schema->storedStamp());
    }

    #[DataProvider('connectionProvider')]
    public function testUpgraderRetiresAbsorbedPackagesFromEnabledState(array $config): void
    {
        $db = $this->legacyDatabase($config);
        $store = new StateStore($this->root . '/extensions');
        $store->update(static fn (): array => ['plugins/bizppurio', 'modules/alimtalk', 'modules/sms', 'plugins/payment-inicis', 'plugins/demo-message']);
        (new SchemaUpgrader($db, $this->root, null, static function (): void {}))->run();
        self::assertSame(['plugins/demo-message'], $store->read());
    }

    #[DataProvider('connectionProvider')]
    public function testUpgraderDoesNotCreateStateFileWhenNoneExists(array $config): void
    {
        $db = $this->legacyDatabase($config);
        (new SchemaUpgrader($db, $this->root, null, static function (): void {}))->run();
        self::assertFileDoesNotExist($this->root . '/extensions/enabled.json');
    }
}
```

- [ ] **Step 2: 실패를 확인한다**

```bash
./vendor/bin/phpunit tests/Db/LegacyPackageAdoptionTest.php 2>&1 | tail -8
```

Expected: 첫 테스트는 통과할 수 있다(4번 작업의 승계). `testUpgraderRetiresAbsorbedPackagesFromEnabledState`는 `enabled` 배열이 그대로라 실패.

- [ ] **Step 3: `SchemaUpgrader`에 정리 단계를 넣는다**

`src/Db/SchemaUpgrader.php`의 `run()`에서 `($this->migrate)();` 바로 다음 줄에 `$this->retireLegacyPackages();`를 추가하고, `backup()` 메서드 위에 추가:

```php
    /** 코어로 흡수한 패키지의 사용 상태를 지운다. 상태 파일이 없으면 만들지 않는다. */
    private function retireLegacyPackages(): void
    {
        $directory = $this->storageDir . '/extensions';
        if (!is_file($directory . '/enabled.json')) {
            return;
        }
        $legacy = ['plugins/bizppurio', 'plugins/payment-inicis', 'modules/alimtalk', 'modules/sms'];
        try {
            $store = new \GnuCms\Extension\StateStore($directory);
            if (array_intersect($store->read(), $legacy) === []) {
                return;
            }
            $store->update(static fn (array $enabled): array => array_values(array_diff($enabled, $legacy)));
        } catch (DomainError $e) {
            // 손상된 상태 파일은 확장 관리 화면이 안내한다. 스키마 갱신을 막지 않는다.
            ($this->log)('[schema-upgrade] 확장 사용 상태를 정리하지 못했습니다: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 4: 테스트를 돌린다**

```bash
./vendor/bin/phpunit tests/Db tests/Extension tests/Maintenance
./vendor/bin/phpunit
```

Expected: `OK`.

- [ ] **Step 5: 커밋**

```bash
git add src/Db/SchemaUpgrader.php tests/Db/LegacyPackageAdoptionTest.php
git commit -m "feat: adopt legacy messaging and payment package data during schema upgrade

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: 문서·스펙 갱신과 최종 검증

**Files:**
- Create: `docs/messaging.md`
- Modify: `README.md:361-373`, `docs/extensions.md:229-230`, `AGENTS.md` (기능 지도, Git과 릴리스), `docs/superpowers/specs/2026-09-14-initalk-design.md` (§4 경로 표, §13 허용값 경로)

- [ ] **Step 1: `docs/messaging.md`를 만든다**

플러그인 README를 기반으로 만들고 코어 기준으로 고친다:

```bash
git show feat/bizppurio-messaging:plugins/bizppurio/README.md > docs/messaging.md
perl -pi -e 's#/plugins/bizppurio/settings#/admin/settings/messaging#g; s#/plugins/bizppurio/result#/messaging/bizppurio/result#g; s#/modules/alimtalk/home#/admin/messaging#g; s#/modules/alimtalk/#/admin/messaging/#g; s#/modules/sms/#/admin/messaging/sms/#g; s#\[문자메시지 운영 모듈\]\(\.\./\.\./modules/sms/README\.md\)#문자 발송 화면#; s#\[확장 안내\]\(\.\./\.\./docs/extensions\.md\)#[확장 안내](extensions.md)#; s#\[개발 계획\]\(\.\./\.\./docs/bizppurio-alimtalk-plan\.md\)#`feat/bizppurio-messaging` 브랜치의 `docs/bizppurio-alimtalk-plan.md`#' docs/messaging.md
```

그런 다음 손으로 고친다.

1. 제목을 `# 알림톡·문자 발송 (비즈뿌리오)`로, 첫 문단의 "GNUCMS 안에서 재사용하는 알림톡·SMS·LMS 발송 기능이다. API 2 확장 기반을 요구하며 기본값은 미사용이다."를 "GNUCMS 코어에 내장된 알림톡·SMS·LMS 발송 기능이다. 설정 → **알림톡·문자**에서 계정을 저장하고 환경별 발송을 허용하면 사용할 수 있다. 이니톡 결제의 결제 알림톡도 이 기능으로 보낸다."로 바꾼다.
2. "## 알림톡 사용 준비"의 3·4·7번 항목을 다음으로 바꾼다: 3 → "GNUCMS **설정 → 알림톡·문자**를 연다.", 4 → 삭제(데이터 설치 없음), 7 → "**운영 → 메시지 발송 → 템플릿**에서 승인 템플릿을 KAPI로 가져오거나 수동 등록한다. 발송 전 설정에서 이 환경의 발송을 허용한다."
3. "기존 설치는 설정 화면의 **데이터 설치·갱신**이 필요하다. 패키지 구조 판 2는 …" 문단을 "이전에 플러그인으로 사용하던 설치는 코어 스키마 v23 업그레이드가 `bp_*` 테이블과 데이터를 그대로 승계하고 플러그인·모듈의 사용 상태를 해제한다. 배포본에 남은 `plugins/bizppurio/`, `modules/alimtalk/`, `modules/sms/` 폴더는 삭제한다. 발송 허용과 API 인증 확인은 업그레이드 후 다시 실행한다."로 바꾼다.
4. "## 다른 모듈에서 사용" 절의 서비스 표와 예제를 다음으로 바꾼다:

````markdown
## 다른 코드에서 사용

`$app->messaging()`이 `GnuCms\Messaging\MessagingService`를 돌려준다. 업무 저장 트랜잭션을 커밋한 뒤 호출한다.

| 메서드 | 내용 |
| --- | --- |
| `status(string $environment): array` | configured/enabled/api_verified/account_type, 설정된 경우 account/test_only/test_phone/kapi_configured. 비밀번호·토큰 제외 |
| `templateAction(string $action, array $input): array` | action은 list/get/save/remote-list/remote-detail/remote-import |
| `preview(array $input): array` | template_id, variables, 선택 revision |
| `send(array $input): array` | environment, template_id, revision, idempotency_key, phone, variables; 선택 config_revision/reference |
| `history(array $filter): array`, `detail(string $id): array`, `retry(string $id): array`, `refresh(string $id): void`, `purge(): int` | 알림톡 이력·재시도·결과 재요청·개인정보 정리 |
| `previewText`, `sendText`, `textHistory`, `textDetail`, `retryText`, `refreshText`, `purgeText` | 문자(SMS/LMS)용. 입력·반환은 아래 문자 발송 절과 같다 |

```php
$result = $app->messaging()->send([
    'environment' => 'live',
    'template_id' => $template['id'],
    'revision' => $template['revision'],
    'idempotency_key' => 'initalk:' . $requestId . ':1',
    'phone' => $recipientPhone,
    'variables' => ['구매자명' => $buyerName],
]);
```
````

5. "## 문자 발송 서비스" 절의 표에서 `Closure(...)` 계약 표기를 `previewText(array $input): array` 식의 메서드 표기로 바꾸고, `$context->service(...)` 예제를 `$app->messaging()->sendText([...])`로 바꾼다.
6. "## 검증과 문서" 절의 테스트 명령을 `./vendor/bin/phpunit tests/Messaging tests/Web/MessagingTest.php tests/Web/MessagingSettingsTest.php`로 바꾼다.
7. "## 관리자 화면과 테마" 절을 "설정 화면은 `admin/messaging_settings.php`, 운영 화면은 `admin/messaging/*.php`이며 테마에서 같은 경로로 재정의한다. 비밀번호 표시 동작과 CSRF 필드를 유지한다. 번호 입력 서식은 `admin/_phone_input.php`다."로 바꾼다.
8. 남은 `플러그인`·`모듈`·`데이터 설치` 문구를 `grep -n "플러그인\|모듈\|데이터 설치\|extension_schemas\|패키지" docs/messaging.md`로 찾아 코어 기준으로 고친다.

- [ ] **Step 2: README·docs/extensions.md·AGENTS.md를 고친다**

`README.md` 361~373행("## 비즈뿌리오 연동 별도 브랜치"와 "## 쇼핑몰·전자결제 별도 브랜치" 두 절)을 다음으로 교체:

```markdown
## 알림톡·문자 발송과 이니시스 결제

비즈뿌리오 알림톡·SMS·LMS 발송과 KG이니시스 카드결제는 코어 기능이다. 설정 → **알림톡·문자**, 설정 → **결제**에서 계정을 저장하고 환경별 실행을 허용한다. 운영 → **메시지 발송**에서 템플릿·웹발송·이력을 관리한다. 자세한 내용은 [docs/messaging.md](docs/messaging.md)를 본다.

이전 `feat/bizppurio-messaging` 플러그인·모듈과 `feat/direct-pg-payments`의 이니시스 결제 플러그인으로 설치한 사이트는 업그레이드 시 DB 데이터를 그대로 승계한다. 덮어쓰기 배포에서는 이전 `plugins/bizppurio/`, `modules/alimtalk/`, `modules/sms/`, `plugins/payment-inicis/` 폴더를 제거하고, 비즈뿌리오에 등록한 결과 수신 URL을 `/messaging/bizppurio/result?…`로 바꾼다(이전 주소도 당분간 받는다).

## 쇼핑몰·다른 PG 별도 브랜치

작은 쇼핑몰(`modules/shop`)과 KCP·KSPay·토스페이먼츠 결제 플러그인은 `feat/direct-pg-payments` 브랜치에 보관한다. `main` 배포본에는 쇼핑몰 모듈·전용 자산, `/shop`·`/admin/shop` 화면과 해당 결제 플러그인을 포함하지 않으며 사용자의 별도 요청 없이 `main`에 병합하지 않는다. 기존 설치에서 사용했다면 관리자 모듈·플러그인 목록에서 사용을 끄고, 덮어쓰기 배포에서는 이전 `modules/shop/`, `plugins/payment-kcp/`, `plugins/payment-kspay/`, `plugins/payment-toss/` 폴더도 제거한다.
```

`docs/extensions.md` 229~230행("외부 연동의 구현 예시인 비즈뿌리오 플러그인과 …")을 다음으로 교체:

```markdown
외부 연동 구현 예시로는 코어의 알림톡·문자 발송(`src/Messaging/`, [docs/messaging.md](messaging.md))과 결제 콜백(`src/Payment/`)을 참고한다. 세션 없는 외부 POST는 `GnuCms\Web\Middleware\ExternalRequests`, 실행 허용값은 `GnuCms\Support\RuntimePermit`을 쓴다.
```

`docs/extensions.md`에서 `Extension\ExternalRequests`·`Extension\RuntimePermit` 표기가 있으면 새 네임스페이스로 고친다(`grep -n "ExternalRequests\|RuntimePermit" docs/extensions.md`).

`AGENTS.md`:

1. "## 현재 기능 지도"의 "- 운영·보안:" 항목 뒤에 추가:

```markdown
- 알림톡·문자 발송: 비즈뿌리오 계정 설정(환경별·암호화)·API 인증 확인·발송 허용, KAPI 템플릿 조회·가져오기·수동 등록, 관리자 웹발송(미리보기 확인값 기반)·이력·상세·재시도·결과 재요청·개인정보 정리, 결과 웹훅(`/messaging/bizppurio/result`), SMS/LMS 발송을 코어 기능으로 제공한다.
- 결제: KG이니시스 상점 설정(환경별·암호화)과 API 실행 허용, 웹표준 PC·모바일 카드결제 요청, 인증 결과 콜백의 서버 승인·망취소, INIAPI 조회·전체/부분 취소 엔진(`src/Payment/`)을 제공한다. 결제를 쓰는 화면은 이니톡 결제(2단계)가 추가한다.
```

2. "## Git과 릴리스"에서 "비즈뿌리오 플러그인 전체(설정·인증·발송·결과 수신)와 알림톡·문자 운영 모듈은 `feat/bizppurio-messaging` 브랜치에서 함께 관리한다. 사용자의 별도 요청 없이 일부 코드나 설정 화면도 `main`에 병합하거나 다시 포함하지 않는다." 줄을 삭제하고, "작은 쇼핑몰과 전자결제 플러그인(이니시스·KCP·KSPay·토스페이먼츠), 공통 결제 코드는 `feat/direct-pg-payments` 브랜치에 보관한다. …" 줄을 "작은 쇼핑몰(`modules/shop`)과 KCP·KSPay·토스페이먼츠 결제 플러그인은 `feat/direct-pg-payments` 브랜치에 보관한다. 사용자의 별도 요청 없이 해당 코드를 `main`에 병합하거나 다시 포함하지 않는다. 이니시스 결제 엔진과 비즈뿌리오 발송은 코어(`src/Payment/`, `src/Messaging/`)에 있다."로 바꾼다.

- [ ] **Step 3: 스펙을 실제 구현에 맞춘다**

`docs/superpowers/specs/2026-09-14-initalk-design.md`:

1. §4 "`src/Web/Controller/MessagingController.php` (운영 화면 이관)" 표에서 `/admin/messaging/history/{id}/retry`, `…/refresh`, `…/sms/history/{id}/retry`, `…/refresh` 항목을 "`/admin/messaging/history/{id}` POST `action=retry|refresh-result`", "`/admin/messaging/sms/history/{id}` POST `action=retry|refresh-result`"로, `/admin/messaging/purge`·`/admin/messaging/sms/purge`를 "`/admin/messaging/history` POST `action=purge`", "`/admin/messaging/sms/history` POST `action=purge`"로 바꾼다.
2. §1 표의 공통 행과 §13의 "실행 허용값은 `RuntimePermit`을 `src/Support/`로 옮겨 `storage/runtime/permits/`에 두고"를 "`storage/extensions-runtime/permits/`에 두고"로 바꾼다.
3. §4 `src/Messaging/`의 "`Settings`: `ready()`는 코어 테이블 존재로 판단한다"를 "`MessagingService::ready()`는 항상 참이다(코어 스키마가 테이블을 보장)"로 바꾼다.

- [ ] **Step 4: 전체 검증**

```bash
cd /home/kagla/gnucms
./vendor/bin/phpunit
git diff --check
DB="gnucms_test_$(date +%s)"; mysql -uroot -h127.0.0.1 -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4" \
  && TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=$DB;charset=utf8mb4" TEST_MYSQL_USER=root ./vendor/bin/phpunit tests/Db tests/Payment tests/Messaging tests/Web/PaymentSettingsTest.php tests/Web/MessagingSettingsTest.php tests/Web/MessagingTest.php; \
  mysql -uroot -h127.0.0.1 -e "DROP DATABASE \`$DB\`"
grep -rn "plugins/bizppurio\|modules/alimtalk\|modules/sms\|payment-inicis" src templates | grep -v "result'" ; echo "(only the legacy webhook alias and adoption keys should appear above)"
```

Expected: SQLite 전체 `OK`, MySQL 부분 스위트 `OK`, `git diff --check` 무출력. 마지막 grep에는 `Routes.php`의 이전 웹훅 별칭과 `Schema`/`SchemaUpgrader`의 승계 키만 남아야 한다.

- [ ] **Step 5: 커밋**

```bash
git add README.md AGENTS.md docs/extensions.md docs/messaging.md docs/superpowers/specs/2026-09-14-initalk-design.md
git commit -m "docs: describe core messaging and payment features and update branch rules

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

## 완료 확인

- `./vendor/bin/phpunit` 전체 통과(기준 689개 + 이관·신규 테스트), `git diff --check` 통과.
- 관리자에서 설정 → 알림톡·문자 / 결제 탭이 보이고, 운영 → 메시지 발송 메뉴가 열린다(라이브 체크아웃이므로 실제 화면으로 확인 가능: https://gnucms.charmgen.com/admin/settings/messaging).
- 플러그인 시절 데이터가 있는 DB에서 첫 요청 후 `extension_schemas`에 `plugins/bizppurio`·`plugins/payment-inicis` 행이 없고 `enabled.json`에서 네 패키지가 빠진다.
- 2단계 계획(`docs/superpowers/plans/2026-09-14-initalk-2-payment-requests.md`)은 이 계획의 결과(`App::messaging()`, `App::inicisGateway()`, `ExternalRequests`, 코어 스키마 v23)를 전제로 작성한다.
