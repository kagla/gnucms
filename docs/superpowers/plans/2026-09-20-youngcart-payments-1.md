# 영카트 결제 3.1 구현 계획

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 쇼핑몰(`modules/youngcart`) 주문에 결제를 붙인다 — 이니시스 결제 계층 이식, 주문의 결제 수단·결제 완료 상태·기한, 카드 결제창과 콜백, 무통장입금과 관리자 입금 확인, 환불, 미결제 만료.

**Architecture:** `feat/initalk`의 `src/Payment`(Gateway 계약 + 이니시스 구현 + 암호화 설정·원장)를 코어로 옮기고, 영카트 모듈 안에 `Commerce\Payments`(수단 목록, 게이트웨이 주문 변환, 승인·환불 기록)와 `Web\PayController`(결제 페이지, 세션 없는 콜백)를 둔다. 주문 상태 흐름에 `paid`가 들어가고, 결제 칸은 모듈 스키마 3판이 더한다. 결제 원장·자격증명 판·콜백 HMAC 은 결제 계층이 이미 보장하므로 모듈은 주문과 원장 키를 잇기만 한다.

**Tech Stack:** PHP 8.4, Slim, PHPUnit 10, SQLite(기본)·MySQL(선택), daisyUI 5 템플릿. 새 Composer 의존성 금지.

**Spec:** `docs/superpowers/specs/2026-09-20-youngcart-payments-design.md` (§1 범위 표의 3.1 행, §2·§3·§4 주문서·결제사 수단(카드)·무통장·기한, §5, §7, §8, §9).

## Global Constraints

- 브랜치는 `feat/core-commerce`(통합 브랜치, 라이브 체크아웃). `main`에 직접 커밋하지 않는다.
- 새 Composer 의존성을 추가하지 않는다. `vendor/`는 손대지 않는다.
- 이니톡 결제와 결합하지 않는다. 이식하는 파일 어디에도 `Initalk`·`initalk`·`이니톡` 참조를 남기지 않는다.
- 결제 원장 키(`payment_id`)는 `random_bytes(16)`의 32자리 16진수. 결제 계층(`Journal::read`)이 이 형식을 요구한다.
- 결제창의 returnUrl·callbackUrl 은 공개 HTTPS 여야 한다(`DirectGateway::prepare()`가 검사). 사이트 주소는 `app.url` 설정에서 만든다.
- 카드번호·인증 토큰·PG 응답 원문은 저장하지 않는다. `payment_detail`에는 표시용 값(`tid`, `label`, 계좌 안내, 입금자명)만 둔다.
- 주문 상태 표(`Orders::STATUSES`·`NEXT`)는 상수로 유지한다. `paid` 전이는 `markPaid()`·`confirmDeposit()`로만 일어나고 일반 상태 변경 폼으로는 못 바꾼다.
- 결제된 결제사 주문의 취소는 환불이 먼저다. 무통장은 환불 기록만 남기고 취소한다.
- 기한이 지난 미결제 주문의 만료는 cron 없이 관리자 주문 화면과 주문서(접수 직전)에서 처리하며, 결제 원장이 `pending`·`confirmed`(승인 진행·완료)인 주문은 만료로 취소하지 않는다.
- 커밋은 `feat:`/`fix:`/`docs:`/`test:` 형식, 본문 끝에 `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- 테스트는 `vendor/bin/phpunit --no-coverage <path>` (PHPUnit 10 은 경로 인자를 하나만 받는다 — 여러 파일은 따로 실행). 전체 스위트는 작업 9 끝에서 한 번. MySQL 레그는 `TEST_MYSQL_DSN=mysql:host=127.0.0.1;dbname=<임시>;charset=utf8mb4 TEST_MYSQL_USER=root`로 임시 DB를 만들어 돌린 뒤 지운다(선택).
- 화면 문구는 한국어, 기존 영카트 문체(존댓말, "~해 주세요")를 따른다.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Payment/*` (11개, 이식) | Gateway 계약, 이니시스 구현, 암호화 설정(환경별 자격증명 판·실행 허용), 결제 원장, 콜백 HMAC, 잠금, 전송 |
| `src/Web/Csrf.php` (이식) | 정적 CSRF 검사. 결제 설정 컨트롤러가 쓴다 |
| `templates/default/admin/payment_settings.php` (이식) | 설정 → 결제 화면 |
| `src/Db/Schema.php` (수정) | 26판: `pay_inicis_settings`·`pay_inicis_transactions` |
| `src/App.php`, `src/Web/Routes.php`, `templates/default/admin/_settings_tabs.php` (수정) | 배선 |
| `src/Payment/DirectGateway.php` (수정, 작업 9) | `approvalState()` — 원장의 승인 단계를 읽기 전용으로 노출 |
| `modules/youngcart/src/Schema.php` (수정) | 3판: 결제 칸, `pay_by` 인덱스, 칸 추가 헬퍼 |
| `modules/youngcart/src/Commerce/Orders.php` (수정) | 상태 표, 접수 시 결제 칸, `markPaid`·`confirmDeposit`·`recordRefund`·`expire`·`byPaymentId` |
| `modules/youngcart/src/Commerce/Payments.php` (신규) | 수단 목록, 접수용 결제 정보, 게이트웨이 주문 변환, 결제창·승인·조회·환불, 만료 |
| `modules/youngcart/src/Settings.php` (수정) | `payment` 설정(환경, 무통장 계좌, 기한) |
| `modules/youngcart/src/Service.php` (수정) | `$payments` |
| `modules/youngcart/src/Web/CommerceController.php` (수정) | 주문서 수단 선택, 접수 뒤 분기, 주문 화면의 결제 상태 |
| `modules/youngcart/src/Web/PayController.php` (신규) | `/shop/pay` 결제 페이지, `/shop/pay/callback` 외부 콜백 |
| `modules/youngcart/src/Admin/OrderController.php`, `AdminController.php` (수정) | 입금 확인·결제 조회·환불, 설정 폼 값 |
| `modules/youngcart/bootstrap.php` (수정) | 라우트 |
| `modules/youngcart/templates/checkout.php`, `order.php`, `pay.php`(신규), `admin/order.php`, `admin/orders.php`, `admin/settings.php`, `admin/_refund_form.php`(신규) | 화면 |
| `tests/Payment/*`, `tests/Web/PaymentSettingsTest.php` (이식), `tests/Db/SchemaTest.php`, `tests/YoungCart/SchemaTest.php`, `tests/YoungCart/PaymentsTest.php`(신규), `tests/YoungCart/SettingsTest.php`, `tests/YoungCart/CommerceTest.php`, `tests/Web/YoungCartCommerceTest.php`, `tests/Web/YoungCartAdminTest.php` | 테스트 |
| `docs/payments.md`(신규), `docs/youngcart.md` | 문서 |

---

### Task 1: 결제 계층 이식

**Files:**
- Create (git checkout from `feat/initalk`): `src/Payment/CallbackToken.php`, `src/Payment/DirectGateway.php`, `src/Payment/ExecutionLock.php`, `src/Payment/Gateway.php`, `src/Payment/InicisGateway.php`, `src/Payment/Journal.php`, `src/Payment/ProviderConfig.php`, `src/Payment/Settings.php`, `src/Payment/SettingsController.php`, `src/Payment/StreamTransport.php`, `src/Payment/Transport.php`, `src/Web/Csrf.php`, `templates/default/admin/payment_settings.php`, `tests/Payment/CallbackTest.php`, `tests/Payment/FakeTransport.php`, `tests/Payment/Fixtures.php`, `tests/Payment/GatewayTest.php`, `tests/Web/PaymentSettingsTest.php`
- Modify: `src/Payment/Settings.php`(use 문), `tests/Payment/GatewayTest.php`(use 문), `templates/default/admin/payment_settings.php`(문구), `src/Db/Schema.php:64` (VERSION), `src/Db/Schema.php:14-23` (TABLES), `src/Db/Schema.php:134-138` (migrateAll), `src/Db/Schema.php:655-660` (create 문장 목록), `src/App.php`, `src/Web/Routes.php:121-126`, `templates/default/admin/_settings_tabs.php`, `tests/Db/SchemaTest.php:22`
- Create: `docs/payments.md`

**Interfaces:**
- Produces: `App::paymentSettings(): \GnuCms\Payment\Settings`, `App::inicisGateway(): \GnuCms\Payment\InicisGateway`, `App::setInicisGateway(InicisGateway $gateway): void`. `Gateway::checkout(array $order, array $customer, string $returnUrl, string $callbackUrl, string $device = 'web'): array` (반환 `['kind' => 'inicis'|'form', 'script'|'action', 'fields' => [...], 'charset'?]`), `complete(array $order, array $callback): void`, `fetch(array $order): array` (`status` ∈ `PAID`·`PARTIAL_CANCELLED`·`CANCELLED`·`NOT_FOUND`, `valid`, `transaction_id`, `paid_at`, `cancelled`, `cancellations`), `cancel(array $order, int $amount, int $remaining, string $reason, string $key): array` (`id`, `amount`, `at`, `reason`). `Payment\Settings::available(string $env): bool`, `summary(string $env): array{configured,enabled,merchant_id,client_ip,revision,environment}`, `save(string $env, array $input)`, `enable(string $env, bool)`. `CallbackToken::create(App, array $order): string`, `verify(App, array $order, mixed $token): bool`. `ExecutionLock::run(string $storage, callable): mixed`. 테스트용 `Tests\Payment\Fixtures::config(): array`, `Tests\Payment\FakeTransport` (`$responses[]`, `$calls[]`). 라우트 `admin.settings.payment`.

- [ ] **Step 1: 파일 가져오기**

```bash
cd /home/kagla/gnucms
git checkout feat/initalk -- src/Payment src/Web/Csrf.php templates/default/admin/payment_settings.php \
  tests/Payment tests/Web/PaymentSettingsTest.php
git status --short
```
Expected: 위 파일들이 `A`(added)로 스테이징된다. `src/Payment/`에 11개 파일.

- [ ] **Step 2: 이름공간과 문구 고치기**

`RuntimePermit`은 이 브랜치에서 `GnuCms\Extension` 이름공간에 있다(이니톡 브랜치는 `GnuCms\Support`). 두 파일을 고친다.

```bash
sed -i 's/^use GnuCms\\Support\\RuntimePermit;/use GnuCms\\Extension\\RuntimePermit;/' src/Payment/Settings.php tests/Payment/GatewayTest.php
grep -rn "이니톡\|initalk\|Initalk" src/Payment src/Web/Csrf.php templates/default/admin/payment_settings.php tests/Payment tests/Web/PaymentSettingsTest.php
```
`payment_settings.php`의 `card-sub` 문장 안 `이니톡 결제의 카드결제에 사용합니다.`를 `쇼핑몰 주문의 온라인 결제에 사용합니다.`로 바꾼다. grep 결과에 다른 이니톡 참조가 있으면 문장을 "쇼핑몰"로 바꾸거나 지운다. 다시 grep 해서 0건이어야 한다.

- [ ] **Step 3: 실패하는 테스트 — 코어 스키마 표 수**

`tests/Db/SchemaTest.php:22`의 `self::assertCount(18, Schema::TABLES);`를 `20`으로 바꾼다.

Run: `vendor/bin/phpunit --no-coverage tests/Db/SchemaTest.php`
Expected: FAIL — `Failed asserting that actual size 18 matches expected size 20`.

- [ ] **Step 4: 코어 스키마 26판**

`src/Db/Schema.php`:
1. `public const VERSION = '25';` → `'26'`.
2. `TABLES` 배열의 `'message_jobs', 'message_recipients', 'alimtalk_templates',` 다음 줄에 `'pay_inicis_settings', 'pay_inicis_transactions',`를 넣는다.
3. `migrateAll()`에서 `$this->migrateAligoMessaging();` 다음 줄에 `$this->migratePayments();`를 넣는다.
4. `create()`의 문장 목록(655~660행, `$this->writeRateLimitStatements(), $this->extensionSchemaStatements(),`가 있는 줄) 그 줄 끝에 `$this->paymentStatements(),`를 더한다.
5. `aligoStatements()` 메서드 바로 앞에 다음을 넣는다.

```php
    /** 쇼핑몰 결제(docs/payments.md). 설정과 원장은 암호문이라 칸이 둘뿐이다. */
    private function paymentStatements(): array
    {
        return [
            'CREATE TABLE pay_inicis_settings (id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL){SUFFIX}',
            'CREATE TABLE pay_inicis_transactions (id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL){SUFFIX}',
        ];
    }

    /** 26판. 기존 설치에는 없으므로 업그레이드할 때 만든다. */
    public function migratePayments(): void
    {
        foreach ($this->paymentStatements() as $sql) {
            preg_match('/^CREATE TABLE (\w+)/', $sql, $m);
            if (!$this->tableExists($m[1])) $this->db->execute($this->expand($sql));
        }
    }
```

Run: `vendor/bin/phpunit --no-coverage tests/Db/SchemaTest.php`
Expected: PASS.

- [ ] **Step 5: App·라우트·설정 탭 배선**

`src/App.php`: 다른 `private ?…Service $… = null;` 필드들 곁에
```php
    private ?\GnuCms\Payment\Settings $paymentSettings = null;
    private ?\GnuCms\Payment\InicisGateway $inicisGateway = null;
```
`aligo()` 메서드 뒤에
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
`src/Web/Routes.php`: `$slim->post('/admin/aligo/key', …)` 줄 다음에
```php
        $payment = new \GnuCms\Payment\SettingsController($app->paymentSettings());
        $slim->get('/admin/settings/payment', [$payment, 'handle'])->setName('admin.settings.payment');
        $slim->post('/admin/settings/payment', [$payment, 'handle']);
```
`templates/default/admin/_settings_tabs.php`: `알림` 탭 줄 다음에
```php
  <a class="tab<?= $active === 'payment' ? ' tab-active' : '' ?>"<?= $active === 'payment' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.payment') ?>">결제</a>
```

- [ ] **Step 6: 이식한 테스트 통과 확인**

Run:
```bash
vendor/bin/phpunit --no-coverage tests/Payment
vendor/bin/phpunit --no-coverage tests/Web/PaymentSettingsTest.php
vendor/bin/phpunit --no-coverage tests/Db
php -l src/Payment/Settings.php && php -l src/App.php && php -l src/Web/Routes.php
```
Expected: 모두 OK. `PaymentSettingsTest`가 화면 문구를 검사해 실패하면 Step 2에서 바꾼 문장에 맞춰 테스트의 기대 문자열을 고친다(이니톡 문구를 되살리지 않는다).

- [ ] **Step 7: docs/payments.md**

```markdown
# 결제 설정 (설정 → 결제)

쇼핑몰(`modules/youngcart`) 주문의 온라인 결제는 코어의 결제 계층(`src/Payment`)이 KG이니시스와
연동한다. 관리자는 **설정 → 결제**(`/admin/settings/payment`)에서 테스트·운영 환경마다 상점
정보를 저장하고 실행을 허용한다.

## 준비물 (이니시스 상점관리자에서 발급)

| 항목 | 설명 |
|---|---|
| 상점 아이디 (MID) | 영문·숫자 10자 |
| 웹표준 결제 SignKey | PC 결제창 서명 |
| 모바일 금액 위변조 Hash Key | 모바일 결제창 서명 |
| INIAPI Key | 승인 조회·환불 API |
| 결제 요청 서버 IPv4 주소 | 이니시스에 등록한 이 서버의 공인 IP |

인증키는 암호화해 저장하고 화면에 다시 보여주지 않는다. 저장할 때마다 설정 판(revision)이
새로 생기고, 그 판으로 연 주문은 나중에도 같은 판으로 승인·조회·환불한다. 같은 상점의
인증키만 바뀐 경우에는 최신 키를 쓴다.

## 실행 허용

저장만으로는 결제창이 열리지 않는다. 환경마다 **실행 허용**을 눌러야 그 환경의 결제가
켜지고, 쇼핑몰 설정의 결제 환경이 그 환경을 가리켜야 주문서에 카드 결제가 나타난다.
백업·복원 중에는 결제 설정을 바꿀 수 없다.

## 표

`pay_inicis_settings`(환경·판별 설정, 암호문), `pay_inicis_transactions`(결제 원장 — 주문의
결제 원장 키마다 승인 단계·승인 결과·환불 기록, 암호문). 코어 스키마 26판이 만든다.
```

- [ ] **Step 8: 커밋**

```bash
git add src/Payment src/Web/Csrf.php templates/default/admin/payment_settings.php tests/Payment tests/Web/PaymentSettingsTest.php \
  src/Db/Schema.php tests/Db/SchemaTest.php src/App.php src/Web/Routes.php templates/default/admin/_settings_tabs.php docs/payments.md
git commit -m "feat: bring the Inicis payment layer into the core

Ported from feat/initalk as code only: the Gateway contract, the Inicis
implementation, encrypted per-environment settings, the payment journal,
the callback token and the settings screen. Core schema 26 creates the
two tables. Nothing here references INITalk.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 모듈 스키마 3판과 주문 상태 `paid`

**Files:**
- Modify: `modules/youngcart/src/Schema.php` (VERSION, TABLES 정의의 `yc_orders`, 인덱스, 칸 추가 헬퍼), `modules/youngcart/src/Commerce/Orders.php:16-17` (상태 표), `Orders.php:71-77` (`get()`), `Orders.php:125-128` (`transition()` 앞부분), `modules/youngcart/templates/admin/order.php` (`$next`에서 `paid` 제외 — 한 줄)
- Test: `tests/YoungCart/SchemaTest.php`, `tests/YoungCart/CommerceTest.php`

**Interfaces:**
- Produces: `Schema::VERSION = 3`, `Schema::PAYMENT_COLUMNS`. `Orders::STATUSES`에 `paid`, `Orders::NEXT`, `Orders::PG_METHODS = ['card', 'easy_pay', 'bank_transfer', 'virtual_account']`. `Orders::get()`가 `$order['payment']`(배열, `payment_detail` JSON 해독)을 싣는다. `transition()`은 `$to === 'paid'`를 거절한다.

- [ ] **Step 1: 실패하는 테스트 — 3판 칸과 업그레이드**

`tests/YoungCart/SchemaTest.php`에 추가(기존 `use` 목록에 `GnuCms\Modules\YoungCart\Schema`가 이미 있다):

```php
    /** 3판은 주문에 결제 칸을 더한다. 2판 설치에도 칸이 생겨야 한다. */
    #[DataProvider('connectionProvider')]
    public function testVersionThreeAddsPaymentColumnsToExistingOrders(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        self::assertSame(3, (int) $this->shop->schema()->status(Schema::KEY)['schema_version']);
        foreach (array_keys(Schema::PAYMENT_COLUMNS) as $column) {
            $db->execute('ALTER TABLE ' . $db->table('yc_orders') . ' DROP COLUMN ' . $column);
        }
        $db->update('extension_schemas', ['schema_version' => 2], 'package_key = :key', ['key' => Schema::KEY]);
        Schema::install($this->shop->schema());
        self::assertSame(3, (int) $this->shop->schema()->status(Schema::KEY)['schema_version']);
        $db->execute('INSERT INTO ' . $db->table('yc_orders') . ' (number, checkout_key, owner_key, user_id, guest_password, status, buyer_name, email, phone, recipient, recipient_phone, postcode, address, address_detail, delivery_note, subtotal, shipping_fee, cod_fee, total, shipping_detail, order_notice, carrier, tracking_number, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 1, ?, ?, ?, ?, 1, 1)',
            ['N1', str_repeat('a', 64), str_repeat('b', 64), '', 'pending', '이름', 'a@b.c', '010', '받는분', '010', '04524', '주소', '', '', '[]', '', '', '']);
        $row = $db->selectOne('SELECT payment_method, payment_id, pay_by, paid_at, refunded_amount, payment_detail FROM ' . $db->table('yc_orders') . " WHERE number = 'N1'");
        self::assertSame(['', '', 0, 0, 0, ''], [$row['payment_method'], $row['payment_id'], (int) $row['pay_by'], (int) $row['paid_at'], (int) $row['refunded_amount'], $row['payment_detail']]);
    }
```
SchemaTest 상단의 기대 인덱스 목록(12행 주석 아래 배열)에 `'yc_order_pay_by'`를 더한다. 기존 `testInstallIsIdempotentAndRegistersTables`의 `assertSame(2, …schema_version)` 두 곳을 `3`으로 바꾼다.

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/SchemaTest.php`
Expected: FAIL — `Undefined constant Schema::PAYMENT_COLUMNS` 또는 version 2≠3.

- [ ] **Step 2: 스키마 3판**

`modules/youngcart/src/Schema.php`:
1. `use GnuCms\Error\DomainError;`를 추가한다.
2. `public const VERSION = 2;` → `3`.
3. `TABLES` 상수 뒤에
```php
    /** 3판이 yc_orders 에 더한 결제 칸. 새 설치는 CREATE 문에, 기존 설치는 addColumn() 이 넣는다. */
    public const PAYMENT_COLUMNS = [
        'payment_method' => 'VARCHAR(20) NOT NULL DEFAULT \'\'',
        'payment_id' => 'VARCHAR(32) NOT NULL DEFAULT \'\'',
        'payment_environment' => 'VARCHAR(8) NOT NULL DEFAULT \'\'',
        'payment_revision' => 'VARCHAR(32) NOT NULL DEFAULT \'\'',
        'paid_at' => 'BIGINT NOT NULL DEFAULT 0',
        'paid_amount' => 'BIGINT NOT NULL DEFAULT 0',
        'refunded_amount' => 'BIGINT NOT NULL DEFAULT 0',
        'payment_detail' => 'VARCHAR(2000) NOT NULL DEFAULT \'\'',
        'pay_by' => 'BIGINT NOT NULL DEFAULT 0',
    ];
```
4. `yc_orders` 정의 문자열의 끝 `created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL'` 를 `created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, ' . self::paymentColumnSql()` 로 바꾼다(정의는 `$definitions` 배열 안 문자열이므로 문자열 연결로 붙인다).
5. `$indexes` 배열에 `'yc_order_pay_by' => ['yc_orders', 'pay_by'],`를 더한다.
6. 인덱스 생성 `foreach` 앞(표 생성 `foreach` 뒤)에
```php
            foreach (self::PAYMENT_COLUMNS as $column => $definition) self::addColumn($db, 'yc_orders', $column, $definition);
```
7. 클래스 끝에
```php
    private static function paymentColumnSql(): string
    {
        $parts = [];
        foreach (self::PAYMENT_COLUMNS as $column => $definition) $parts[] = $column . ' ' . $definition;
        return implode(', ', $parts);
    }

    /** 기존 표에 칸을 더한다. 이미 있으면 아무것도 하지 않는다 — 코어 Schema::addColumnIfMissing() 과 같은 방식. */
    private static function addColumn(Connection $db, string $table, string $column, string $definition): void
    {
        try {
            $db->selectOne('SELECT ' . $column . ' FROM ' . $db->table($table) . ' LIMIT 1');
            return;
        } catch (DomainError) {
        }
        $db->execute('ALTER TABLE ' . $db->table($table) . ' ADD COLUMN ' . $column . ' ' . strtr($definition, $db->dialect()->typeMap()));
    }
```
`{TEXT}` 치환은 `typeMap()`이 하지만 여기서는 쓰지 않는다(MySQL 의 TEXT 기본값 제약 때문에 `VARCHAR(2000)`).

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/SchemaTest.php`
Expected: PASS.

- [ ] **Step 3: 실패하는 테스트 — 상태 표와 `paid` 거절**

`tests/YoungCart/CommerceTest.php`에 추가:

```php
    /** 결제 완료는 상태 흐름에 들어 있되 일반 전이로는 못 간다 — 결제 확인만이 그 자리를 채운다. */
    #[DataProvider('connectionProvider')]
    public function testPaidIsAStatusThatOnlyPaymentConfirmationCanReach(array $config): void
    {
        $this->setupShop($config);
        self::assertSame(['pending', 'paid', 'confirmed', 'shipped', 'completed', 'cancelled'], array_keys(Orders::STATUSES));
        self::assertSame(['paid', 'cancelled'], Orders::NEXT['pending']);
        self::assertSame(['confirmed', 'cancelled'], Orders::NEXT['paid']);
        $order = $this->place($this->cart($this->product()));
        self::assertSame([], $order['payment']);
        $this->reject(fn () => $this->shop->orders->transition((int) $order['id'], 'pending', 'paid', 'admin'), '결제 확인');
        $this->reject(fn () => $this->shop->orders->transition((int) $order['id'], 'pending', 'confirmed', 'admin'));
    }
```
`use GnuCms\Modules\YoungCart\Commerce\Orders;`가 없으면 추가한다.

Run: `vendor/bin/phpunit --no-coverage --filter PaidIsAStatus tests/YoungCart/CommerceTest.php`
Expected: FAIL — 상태 키 배열 불일치.

- [ ] **Step 4: 상태 표·`get()`·`transition()`**

`Orders.php` 16~17행을 바꾼다:
```php
    public const STATUSES = ['pending' => '주문 접수', 'paid' => '결제 완료', 'confirmed' => '상품 준비', 'shipped' => '배송 중', 'completed' => '배송 완료', 'cancelled' => '주문 취소'];
    public const NEXT = ['pending' => ['paid', 'cancelled'], 'paid' => ['confirmed', 'cancelled'], 'confirmed' => ['shipped', 'cancelled'], 'shipped' => ['completed'], 'completed' => [], 'cancelled' => []];
    /** 결제사(이니시스)를 거치는 수단. 무통장은 관리자가 입금을 확인한다. */
    public const PG_METHODS = ['card', 'easy_pay', 'bank_transfer', 'virtual_account'];
```
`get()`의 `$order['shipping'] = …` 다음 줄에
```php
        $decoded = ($order['payment_detail'] ?? '') === '' ? [] : json_decode((string) $order['payment_detail'], true, 8);
        $order['payment'] = is_array($decoded) ? $decoded : [];
```
`transition()` 첫 `if` 앞에
```php
        if ($to === 'paid') throw DomainError::validation(['status' => '결제 완료는 결제 확인으로만 바뀝니다.']);
```
`templates/admin/order.php`: `<?php if ($next !== []): ?>` 앞에 `<?php $next = array_values(array_diff($next, ['paid'])); ?>` 를 넣는다(관리자가 셀렉트로 결제 완료를 고르지 못하게 — 작업 7 이 컨트롤러로 옮긴다).

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart` 그리고 `vendor/bin/phpunit --no-coverage tests/Web/YoungCartAdminTest.php` 그리고 `vendor/bin/phpunit --no-coverage tests/Web/YoungCartCommerceTest.php`
Expected: 모두 PASS.

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Schema.php modules/youngcart/src/Commerce/Orders.php tests/YoungCart/SchemaTest.php tests/YoungCart/CommerceTest.php modules/youngcart/templates/admin/order.php
git commit -m "feat: give YoungCart orders payment columns and a paid status

Module schema 3 adds the payment method, journal key, environment and
revision, paid/refunded amounts, display detail and the pay-by deadline,
also to existing version-2 installs. 'paid' joins the status table but
only payment confirmation may enter it.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: 쇼핑몰 결제 설정

**Files:**
- Modify: `modules/youngcart/src/Settings.php` (`defaults()`, `save()`), `modules/youngcart/src/Admin/AdminController.php:75-88` (`flatten()`), `modules/youngcart/templates/admin/settings.php`
- Test: `tests/YoungCart/SettingsTest.php`, `tests/Web/YoungCartAdminTest.php`(필요하면 설정 저장 입력값 보강)

**Interfaces:**
- Produces: `Settings::all()['payment'] = ['environment' => 'test'|'live', 'manual' => ['enabled' => bool, 'bank' => string, 'account' => string, 'holder' => string], 'deadline_hours' => ['card' => int, 'virtual_account' => int, 'manual_transfer' => int]]`. 폼 이름: `payment_environment`, `payment_manual_enabled`, `payment_manual_bank`, `payment_manual_account`, `payment_manual_holder`, `payment_deadline_card`, `payment_deadline_virtual_account`, `payment_deadline_manual_transfer`.

- [ ] **Step 1: 실패하는 테스트**

`tests/YoungCart/SettingsTest.php`에 추가. 이 파일이 `save()`에 넘기는 "필수 폼 값 전부" 배열을 만드는 헬퍼가 있으면 그것을 `settingsInput()`으로 쓴다. 없으면 파일 안의 기존 테스트가 쓰는 입력 배열을 `private function settingsInput(): array`로 뽑아낸다.

```php
    /** 결제 설정: 환경, 무통장 계좌, 수단별 기한. 폼에 없는 값은 이전 값을 지킨다. */
    #[DataProvider('connectionProvider')]
    public function testPaymentSettingsSaveAndKeepPreviousValuesWhenAbsent(array $config): void
    {
        $this->setupShop($config);
        $defaults = $this->shop->settings->all()['payment'];
        self::assertSame('live', $defaults['environment']);
        self::assertFalse($defaults['manual']['enabled']);
        self::assertSame(['card' => 1, 'virtual_account' => 72, 'manual_transfer' => 72], $defaults['deadline_hours']);

        $this->shop->settings->save($this->settingsInput() + ['payment_environment' => 'test', 'payment_manual_enabled' => '1',
            'payment_manual_bank' => '국민은행', 'payment_manual_account' => '123456-01-234567', 'payment_manual_holder' => '홍길동',
            'payment_deadline_card' => '2', 'payment_deadline_manual_transfer' => '48']);
        $saved = $this->shop->settings->all()['payment'];
        self::assertSame('test', $saved['environment']);
        self::assertSame(['enabled' => true, 'bank' => '국민은행', 'account' => '123456-01-234567', 'holder' => '홍길동'], $saved['manual']);
        self::assertSame(['card' => 2, 'virtual_account' => 72, 'manual_transfer' => 48], $saved['deadline_hours']);

        $this->shop->settings->save($this->settingsInput());
        self::assertSame($saved, $this->shop->settings->all()['payment']);

        try {
            $this->shop->settings->save($this->settingsInput() + ['payment_deadline_card' => '0']);
            self::fail('0시간은 거절해야 합니다.');
        } catch (DomainError $e) {
            self::assertArrayHasKey('payment_deadline_card', $e->details());
        }
    }
```
`use GnuCms\Error\DomainError;` 확인.

Run: `vendor/bin/phpunit --no-coverage --filter PaymentSettings tests/YoungCart/SettingsTest.php`
Expected: FAIL — `Undefined array key "payment"`.

- [ ] **Step 2: 기본값과 저장**

`Settings::defaults()` 배열의 `'exchange' => ['content' => ''],` 다음에
```php
            'payment' => ['environment' => 'live',
                'manual' => ['enabled' => false, 'bank' => '', 'account' => '', 'holder' => ''],
                'deadline_hours' => ['card' => 1, 'virtual_account' => 72, 'manual_transfer' => 72]],
```
`save()`에서 `$settings['order_notice'] = …` 줄 다음, `if ($errors !== []) throw …` 앞에
```php
        // 결제: 폼에 없는 값은 이전 값을 지킨다(다른 테마의 옛 폼과 같은 규칙).
        $payment = $previous['payment'];
        $environment = $input['payment_environment'] ?? null;
        if (in_array($environment, ['test', 'live'], true)) $payment['environment'] = $environment;
        if (array_key_exists('payment_manual_enabled', $input) || array_key_exists('payment_manual_account', $input)) {
            $payment['manual'] = ['enabled' => $bool('payment_manual_enabled'),
                'bank' => Input::text($input['payment_manual_bank'] ?? '', 'payment_manual_bank', 50),
                'account' => Input::text($input['payment_manual_account'] ?? '', 'payment_manual_account', 50),
                'holder' => Input::text($input['payment_manual_holder'] ?? '', 'payment_manual_holder', 50)];
            if ($payment['manual']['enabled'] && $payment['manual']['account'] === '') $errors['payment_manual_account'] = '무통장입금을 켜려면 계좌번호를 입력해 주세요.';
        }
        foreach (['card' => 72, 'virtual_account' => 720, 'manual_transfer' => 720] as $key => $max) {
            if (array_key_exists('payment_deadline_' . $key, $input)) $payment['deadline_hours'][$key] = $int('payment_deadline_' . $key, 1, $max);
        }
        $settings['payment'] = $payment;
```
(`$int`·`$bool`·`$errors`·`$previous`는 같은 메서드 안에 이미 있다.)

`AdminController::flatten()`의 `$flat['exchange_content'] = …` 다음에
```php
        $flat['payment_environment'] = $settings['payment']['environment'];
        $flat['payment_manual_enabled'] = $settings['payment']['manual']['enabled'] ? '1' : '0';
        foreach (['bank', 'account', 'holder'] as $key) $flat['payment_manual_' . $key] = $settings['payment']['manual'][$key];
        foreach ($settings['payment']['deadline_hours'] as $key => $hours) $flat['payment_deadline_' . $key] = (string) $hours;
```

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/SettingsTest.php`
Expected: PASS.

- [ ] **Step 3: 설정 화면**

`templates/admin/settings.php`의 `_form_nav` 섹션 배열에 `'settings-payment' => '결제'`를 `'settings-shipping' => '배송·주문'` 다음에 넣고, `id="settings-shipping"` 섹션 뒤에 새 섹션을 넣는다.

```php
  <section class="card" id="settings-payment"><div class="card-body"><h2 class="card-title">결제</h2><p class="muted">카드 결제는 설정 → 결제에서 저장하고 실행을 허용한 이니시스 환경을 씁니다. 무통장입금은 아래 계좌를 안내하고 관리자가 입금을 확인합니다.</p>
    <fieldset class="fieldset"><legend class="fieldset-legend">결제 환경</legend>
      <?php foreach (['live' => '운영', 'test' => '테스트'] as $env => $label): ?><label class="label"><input class="radio radio-sm" type="radio" name="payment_environment" value="<?= $env ?>"<?= ($values['payment_environment'] ?? 'live') === $env ? ' checked' : '' ?>> <?= $label ?></label><?php endforeach ?>
      <span class="muted">주문서의 카드 결제는 이 환경이 결제 설정에서 허용돼 있을 때만 보입니다.</span></fieldset>
    <label class="label"><input class="checkbox checkbox-sm" type="checkbox" name="payment_manual_enabled" value="1"<?= ($values['payment_manual_enabled'] ?? '0') === '1' ? ' checked' : '' ?>> 무통장입금 사용</label>
    <div class="yc-fields">
      <?php foreach (['bank' => '은행', 'account' => '계좌번호', 'holder' => '예금주'] as $key => $label): $name = 'payment_manual_' . $key; ?>
      <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><label for="yc-setting-<?= $name ?>"><?= $label ?></label></legend>
        <input class="input input-bordered input-sm" type="text" id="yc-setting-<?= $name ?>" name="<?= $name ?>" maxlength="50" value="<?= $this->e((string) ($values[$name] ?? '')) ?>">
        <?php if (isset($errors[$name])): ?><p class="validator-hint"><?= $this->e($errors[$name]) ?></p><?php endif ?></fieldset>
      <?php endforeach ?>
    </div>
    <div class="yc-fields"><?php $num('payment_deadline_card', '카드 결제 기한 (시간)', 1, 72); $num('payment_deadline_manual_transfer', '무통장 입금 기한 (시간)', 1, 720); $num('payment_deadline_virtual_account', '가상계좌 입금 기한 (시간)', 1, 720); ?></div>
    <p class="muted">기한이 지난 미결제 주문은 자동으로 취소되고 재고가 돌아갑니다.</p>
  </div></section>
```

Run: `vendor/bin/phpunit --no-coverage tests/Web/YoungCartAdminTest.php` 와 `php -l modules/youngcart/templates/admin/settings.php`
Expected: PASS. `testGuardsInstallAndSettings`가 설정 저장 폼을 전부 보내면 새 `$num` 셋이 `required`라 422 가 날 수 있다 — 그 테스트가 보내는 배열에 `'payment_deadline_card' => '1', 'payment_deadline_manual_transfer' => '72', 'payment_deadline_virtual_account' => '72'`를 더한다.

- [ ] **Step 4: 커밋**

```bash
git add modules/youngcart/src/Settings.php modules/youngcart/src/Admin/AdminController.php modules/youngcart/templates/admin/settings.php tests/YoungCart/SettingsTest.php tests/Web/YoungCartAdminTest.php
git commit -m "feat: add payment environment, manual-transfer account and deadlines to shop settings

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: Payments 서비스와 주문의 결제 기록

**Files:**
- Create: `modules/youngcart/src/Commerce/Payments.php`
- Modify: `modules/youngcart/src/Commerce/Orders.php` (`place()` 서명·insert, `transition()` 취소 규칙, 새 메서드), `modules/youngcart/src/Service.php`
- Test: `tests/YoungCart/PaymentsTest.php` (신규)

**Interfaces:**
- Consumes: 작업 1의 `App::paymentSettings()`·`inicisGateway()`, `CallbackToken`, `ExecutionLock`; 작업 2의 `Orders::PG_METHODS`; 작업 3의 `Settings::all()['payment']`.
- Produces:
  - `Orders::place(array $lines, array $input, string $key, string $owner, ?int $userId, string $fingerprint, array $shipping = [], array $payment = []): array` — `$payment = ['method', 'pay_by', 'detail', 'id'?, 'environment'?, 'revision'?]`.
  - `Orders::markPaid(int $id, string $actor, int $amount, array $detail, int $paidAt, string $note): array`, `Orders::confirmDeposit(int $id, string $actor): array`, `Orders::recordRefund(int $id, int $amount, string $actor, string $reason): array`, `Orders::byPaymentId(string $paymentId): ?array`, `Orders::expire(int $now, callable $inProgress, int $limit = 50): int`.
  - `Payments::METHODS`, `Payments::methods(): array<string,string>`, `Payments::forPlacing(array $input): array`, `Payments::gatewayOrder(array $order): array` (static), `Payments::isPgOrder(array $order): bool`, `Payments::checkout(array $order, string $device, string $returnUrl, string $callbackBase): array`, `Payments::callbackUrl(array $order, string $callbackBase): string`, `Payments::complete(array $order, array $callback): array`, `Payments::sync(array $order): array`, `Payments::refund(array $order, int $amount, string $reason, string $key, string $actor): array`, `Payments::inProgress(array $order): bool`, `Payments::expireOverdue(): int`.
  - `Service::$payments`.

- [ ] **Step 1: 실패하는 테스트 파일**

`tests/YoungCart/PaymentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Commerce\Orders;
use GnuCms\Modules\YoungCart\Commerce\Payments;
use GnuCms\Payment\InicisGateway;
use GnuCms\Support\Clock;
use GnuCms\Tests\Payment\FakeTransport;
use GnuCms\Tests\Payment\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;

final class PaymentsTest extends YoungCartTestCase
{
    private FakeTransport $http;
    private array $config;

    /** 이니시스 테스트 환경을 저장·허용하고 쇼핑몰이 그 환경을 쓰게 한다. */
    private function setupPayments(array $config, bool $manual = true): void
    {
        $this->setupShop($config);
        $this->config = Fixtures::config();
        $this->app->paymentSettings()->save('test', $this->config);
        $this->app->paymentSettings()->enable('test', true);
        $this->http = new FakeTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->http));
        $this->savePayment(['environment' => 'test', 'manual' => ['enabled' => $manual, 'bank' => '국민은행', 'account' => '123-45', 'holder' => '상점'],
            'deadline_hours' => ['card' => 1, 'virtual_account' => 72, 'manual_transfer' => 72]]);
    }

    /** 설정 폼을 거치지 않고 payment 블록만 바꾼다(다른 설정은 그대로). */
    private function savePayment(array $payment): void
    {
        $settings = $this->shop->settings->all();
        $settings['payment'] = $payment;
        $this->app->db()->update('yc_settings', ['payload' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)], 'id = :id', ['id' => 'settings']);
    }

    private function buyer(array $extra = []): array
    {
        return $extra + ['buyer_name' => '테스트 구매자', 'email' => 'buyer@example.test', 'phone' => '010-0000-0000',
            'recipient' => '받는 사람', 'recipient_phone' => '010-0000-0000', 'postcode' => '04524', 'address' => '테스트 배송지',
            'address_detail' => '', 'delivery_note' => '', 'password' => bin2hex(random_bytes(12)), 'agree' => '1'];
    }

    private function place(string $method, array $extra = []): array
    {
        $cart = $this->shop->cart->add([], ['product_id' => $this->product(['price' => '12000'])['id'], 'quantity' => 1]);
        $input = $this->buyer($extra + ['payment_method' => $method]);
        return $this->shop->orders->place($cart, $input, bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), null,
            $this->shop->cart->quote($cart, [], true)['fingerprint'], [], $this->shop->payments->forPlacing($input));
    }

    private function callback(array $order): array
    {
        return ['resultCode' => '0000', 'mid' => $this->config['merchant_id'], 'orderNumber' => $order['payment_id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
    }

    private function queueApproval(array $order, string $tid): void
    {
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->config['merchant_id'], 'MOID' => $order['payment_id'],
            'TotPrice' => (string) $order['total'], 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $this->queueInquiry($order, $tid);
    }

    private function queueInquiry(array $order, string $tid, string $status = 'APPROVAL'): void
    {
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'transactionStatus' => $status, 'mid' => $this->config['merchant_id'],
            'oid' => $order['payment_id'], 'price' => (string) $order['total'], 'tid' => $tid, 'paymethod' => 'Card', 'approvedDate' => '20260920', 'approvedTime' => '120000',
            'cardInfo' => ['currencyCode' => 'WON'], 'partCancelTransInfo' => []]];
    }

    #[DataProvider('connectionProvider')]
    public function testMethodsFollowThePaymentSettingsAndTheManualAccount(array $config): void
    {
        $this->setupPayments($config);
        self::assertSame(['card' => '카드 결제', 'manual_transfer' => '무통장입금'], $this->shop->payments->methods());
        $this->app->paymentSettings()->enable('test', false);
        self::assertSame(['manual_transfer' => '무통장입금'], $this->shop->payments->methods());
        $this->savePayment(['environment' => 'test', 'manual' => ['enabled' => false, 'bank' => '', 'account' => '', 'holder' => ''],
            'deadline_hours' => ['card' => 1, 'virtual_account' => 72, 'manual_transfer' => 72]]);
        self::assertSame([], $this->shop->payments->methods());
        self::assertSame([], $this->shop->payments->forPlacing(['payment_method' => 'card']), '수단이 하나도 없으면 접수 전용이다');
    }

    #[DataProvider('connectionProvider')]
    public function testPlacingACardOrderRecordsTheJournalKeyEnvironmentRevisionAndDeadline(array $config): void
    {
        $this->setupPayments($config);
        $order = $this->place('card');
        self::assertSame('card', $order['payment_method']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $order['payment_id']);
        self::assertSame('test', $order['payment_environment']);
        self::assertSame($this->app->paymentSettings()->summary('test')['revision'], $order['payment_revision']);
        self::assertEqualsWithDelta(Clock::timestamp() + 3600, (int) $order['pay_by'], 5);
        self::assertSame('pending', $order['status']);
        self::assertTrue($this->shop->payments->isPgOrder($order));
        self::assertSame($order['number'], $this->shop->orders->byPaymentId($order['payment_id'])['number']);
        self::assertNull($this->shop->orders->byPaymentId(str_repeat('0', 32)));
        self::assertNull($this->shop->orders->byPaymentId('not-hex'));
    }

    #[DataProvider('connectionProvider')]
    public function testPlacingAManualTransferOrderKeepsTheAccountAndDepositor(array $config): void
    {
        $this->setupPayments($config);
        $order = $this->place('manual_transfer', ['depositor' => '홍길동']);
        self::assertSame('manual_transfer', $order['payment_method']);
        self::assertSame('', $order['payment_id']);
        self::assertSame(['depositor' => '홍길동', 'bank' => '국민은행', 'account' => '123-45', 'holder' => '상점'], $order['payment']);
        self::assertEqualsWithDelta(Clock::timestamp() + 72 * 3600, (int) $order['pay_by'], 5);
        self::assertFalse($this->shop->payments->isPgOrder($order));
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownMethodIsRejected(array $config): void
    {
        $this->setupPayments($config);
        try { $this->shop->payments->forPlacing(['payment_method' => 'virtual_account']); self::fail('아직 없는 수단'); }
        catch (DomainError $e) { self::assertArrayHasKey('payment_method', $e->details()); }
    }

    #[DataProvider('connectionProvider')]
    public function testCheckoutOpensTheInicisWindowWithTheOrderTotalAndCallbackState(array $config): void
    {
        $this->setupPayments($config);
        $order = $this->place('card');
        $window = $this->shop->payments->checkout($order, 'web', 'https://shop.example.test/shop/order?number=' . $order['number'], 'https://shop.example.test/shop/pay/callback');
        self::assertSame('inicis', $window['kind']);
        self::assertSame((string) $order['total'], $window['fields']['price']);
        self::assertSame($order['payment_id'], $window['fields']['oid']);
        self::assertStringStartsWith('https://shop.example.test/shop/pay/callback?order=' . $order['payment_id'] . '&state=', $window['fields']['returnUrl']);
        self::assertSame('테스트 구매자', $window['fields']['buyername']);
        self::assertSame([], $this->http->calls);
    }

    #[DataProvider('connectionProvider')]
    public function testCompleteApprovesAndMarksTheOrderPaidOnce(array $config): void
    {
        $this->setupPayments($config);
        $order = $this->place('card');
        $this->shop->payments->checkout($order, 'web', 'https://shop.example.test/shop/order', 'https://shop.example.test/shop/pay/callback');
        $tid = bin2hex(random_bytes(20));
        $this->queueApproval($order, $tid);
        $paid = $this->shop->payments->complete($order, $this->callback($order));
        self::assertSame('paid', $paid['status']);
        self::assertSame((int) $order['total'], (int) $paid['paid_amount']);
        self::assertSame($tid, $paid['payment']['tid']);
        self::assertSame('카드 결제', $paid['payment']['label']);
        self::assertGreaterThan(0, (int) $paid['paid_at']);
        self::assertSame('paid', end($paid['history'])['status']);
        // 콜백이 다시 와도(재전송) 두 번 승인하지 않고 두 번 기록하지 않는다. 조회만 한 번 더 한다.
        $this->queueInquiry($order, $tid);
        $again = $this->shop->payments->complete($this->shop->orders->get((int) $order['id']), $this->callback($order));
        self::assertSame('paid', $again['status']);
        self::assertCount(2, $again['history']);
    }

    #[DataProvider('connectionProvider')]
    public function testConfirmDepositOnlyForManualTransferAndOnlyOnce(array $config): void
    {
        $this->setupPayments($config);
        $card = $this->place('card');
        try { $this->shop->orders->confirmDeposit((int) $card['id'], 'admin'); self::fail('카드 주문은 입금 확인이 없다'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $manual = $this->place('manual_transfer');
        $paid = $this->shop->orders->confirmDeposit((int) $manual['id'], 'admin');
        self::assertSame('paid', $paid['status']);
        self::assertSame((int) $manual['total'], (int) $paid['paid_amount']);
        try { $this->shop->orders->confirmDeposit((int) $manual['id'], 'admin'); self::fail('두 번 확인할 수 없다'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testCustomersCannotCancelAfterPaymentAndAdminsNeedARefundFirst(array $config): void
    {
        $this->setupPayments($config);
        $order = $this->place('manual_transfer');
        $this->shop->orders->confirmDeposit((int) $order['id'], 'admin');
        try { $this->shop->orders->transition((int) $order['id'], 'paid', 'cancelled', 'guest', [], true); self::fail('고객은 결제 뒤 취소 못 한다'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $card = $this->place('card');
        $this->shop->payments->checkout($card, 'web', 'https://shop.example.test/shop/order', 'https://shop.example.test/shop/pay/callback');
        $this->queueApproval($card, bin2hex(random_bytes(20)));
        $this->shop->payments->complete($card, $this->callback($card));
        try { $this->shop->orders->transition((int) $card['id'], 'paid', 'cancelled', 'admin'); self::fail('결제된 카드 주문은 환불이 먼저다'); }
        catch (DomainError $e) { self::assertArrayHasKey('refund', $e->details()); }
    }
}
```

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/PaymentsTest.php`
Expected: FAIL — `Class "GnuCms\Modules\YoungCart\Commerce\Payments" not found` (또는 `Undefined property $payments`).

- [ ] **Step 2: Orders — 접수 시 결제 칸과 새 메서드**

`Orders::place()` 서명을 바꾼다: `…, array $shipping = [], array $payment = []): array`. 트랜잭션 클로저의 `use (…)`에 `$payment`를 더하고, `insert('yc_orders', $buyer + [ … ])` 배열의 `'carrier' => '', 'tracking_number' => '',` 다음에
```php
                    'payment_method' => (string) ($payment['method'] ?? ''), 'payment_id' => (string) ($payment['id'] ?? ''),
                    'payment_environment' => (string) ($payment['environment'] ?? ''), 'payment_revision' => (string) ($payment['revision'] ?? ''),
                    'paid_at' => 0, 'paid_amount' => 0, 'refunded_amount' => 0, 'pay_by' => (int) ($payment['pay_by'] ?? 0),
                    'payment_detail' => $payment === [] ? '' : json_encode($payment['detail'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
```
`transition()`의 `$carrier = …` 줄 앞에 (결제 뒤 취소 규칙):
```php
        if ($to === 'cancelled' && $from !== 'pending') {
            $current = $this->get($id);
            if (in_array($current['payment_method'], self::PG_METHODS, true) && (int) $current['refunded_amount'] < (int) $current['paid_amount']) {
                throw DomainError::validation(['refund' => '결제된 주문은 환불을 먼저 처리해 주세요.']);
            }
        }
```
`history()` 앞에 새 메서드들:
```php
    public function byPaymentId(string $paymentId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $paymentId)) return null;
        $row = $this->store->selectOne('SELECT id FROM ' . $this->store->table('yc_orders') . ' WHERE payment_id = ?', [$paymentId]);
        return $row === null ? null : $this->get((int) $row['id']);
    }

    /**
     * 결제 완료. 콜백 재전송이나 통보 중복으로 두 번 불려도 한 번만 기록한다. 금액은 주문
     * 총액과 같아야 한다 — 결제 계층도 검사하지만 무통장 입금 확인은 여기만 지난다.
     */
    public function markPaid(int $id, string $actor, int $amount, array $detail, int $paidAt, string $note): array
    {
        $this->store->transaction(function () use ($id, $actor, $amount, $detail, $paidAt, $note): void {
            $order = $this->get($id);
            if ($order['status'] === 'paid' && (int) $order['paid_amount'] === $amount) return;
            if ($order['status'] !== 'pending') throw DomainError::validation(['status' => '결제 대기 중인 주문이 아닙니다.']);
            if ($amount !== (int) $order['total']) throw DomainError::validation(['amount' => '결제 금액이 주문 금액과 다릅니다.']);
            $changed = $this->store->execute('UPDATE ' . $this->store->table('yc_orders') . ' SET status = ?, paid_at = ?, paid_amount = ?, payment_detail = ?, updated_at = ? WHERE id = ? AND status = ?',
                ['paid', $paidAt, $amount, json_encode($detail + $order['payment'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), Clock::timestamp(), $id, 'pending']);
            if ($changed !== 1) throw DomainError::validation(['status' => '주문 상태가 변경되었습니다. 새로고침 후 확인해 주세요.']);
            $this->history($id, 'paid', $actor, $note);
        });
        return $this->get($id);
    }

    /** 무통장입금의 입금 확인. 관리자만 부른다. */
    public function confirmDeposit(int $id, string $actor): array
    {
        $order = $this->get($id);
        if ($order['payment_method'] !== 'manual_transfer') throw DomainError::validation(['payment' => '무통장입금 주문만 입금을 확인합니다.']);
        return $this->markPaid($id, $actor, (int) $order['total'], ['confirmed_by' => $actor], Clock::timestamp(), '입금을 확인했습니다.');
    }

    /** 환불 누계와 이력. 결제사 환불은 Payments 가 먼저 성공시키고 부른다. 상태는 바꾸지 않는다. */
    public function recordRefund(int $id, int $amount, string $actor, string $reason): array
    {
        $this->store->transaction(function () use ($id, $amount, $actor, $reason): void {
            $order = $this->get($id);
            $remaining = (int) $order['paid_amount'] - (int) $order['refunded_amount'];
            if ($amount < 1 || $amount > $remaining) throw DomainError::validation(['refund' => '환불 금액을 확인해 주세요.']);
            $this->store->execute('UPDATE ' . $this->store->table('yc_orders') . ' SET refunded_amount = refunded_amount + ?, updated_at = ? WHERE id = ?', [$amount, Clock::timestamp(), $id]);
            $this->history($id, $order['status'], $actor, '환불 ' . number_format($amount) . '원: ' . $reason);
        });
        return $this->get($id);
    }

    /**
     * 결제 기한이 지난 미결제 주문을 취소하고 재고를 돌려놓는다. cron 없이 관리자 주문 화면과
     * 주문서(접수 직전)에서 부른다. $inProgress 가 참인 주문(결제사 승인이 진행·완료됨)은
     * 건드리지 않는다 — 돈이 움직였을 수 있으므로 관리자의 결제 조회가 먼저다.
     * @param callable(array):bool $inProgress
     */
    public function expire(int $now, callable $inProgress, int $limit = 50): int
    {
        $rows = $this->store->select('SELECT id FROM ' . $this->store->table('yc_orders') . ' WHERE status = ? AND pay_by > 0 AND pay_by < ? ORDER BY pay_by LIMIT ' . $limit, ['pending', $now]);
        $count = 0;
        foreach ($rows as $row) {
            $order = $this->get((int) $row['id']);
            if ($inProgress($order)) continue;
            try {
                $this->transition((int) $row['id'], 'pending', 'cancelled', 'system', ['note' => '결제 기한이 지나 자동으로 취소했습니다.']);
                $count++;
            } catch (DomainError) {
                // 경합으로 이미 바뀐 주문은 건너뛴다.
            }
        }
        return $count;
    }
```
`Orders.php` 상단에 `use GnuCms\Support\Clock;`가 이미 있는지 확인한다(있다 — `place()`가 쓴다).

- [ ] **Step 3: Payments 서비스**

`modules/youngcart/src/Commerce/Payments.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Commerce;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\Settings;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\ExecutionLock;
use GnuCms\Support\Clock;

/**
 * 주문과 코어 결제 계층을 잇는다. 결제 원장·자격증명 판·콜백 HMAC 은 결제 계층이 보장하고,
 * 여기서는 주문의 결제 칸(원장 키·환경·판)을 게이트웨이의 주문 배열로 바꾸고 결과를 주문에 적는다.
 */
final class Payments
{
    public const PROVIDER = 'inicis';
    public const METHODS = ['card' => '카드 결제', 'easy_pay' => '간편결제', 'bank_transfer' => '실시간 계좌이체', 'virtual_account' => '가상계좌', 'manual_transfer' => '무통장입금'];

    public function __construct(private App $app, private Settings $settings, private Orders $orders) {}

    /** 주문서에 보일 수단. 3.1 은 카드와 무통장만 안다. 켜져 있지 않은 수단은 목록에 없다. */
    public function methods(): array
    {
        $payment = $this->settings->all()['payment'];
        $methods = [];
        if ($this->app->paymentSettings()->available($payment['environment'])) $methods['card'] = self::METHODS['card'];
        if ($payment['manual']['enabled'] && $payment['manual']['account'] !== '') $methods['manual_transfer'] = self::METHODS['manual_transfer'];
        return $methods;
    }

    /** 주문 접수 때 Orders::place() 에 넘길 결제 정보. 수단이 하나도 없으면(접수 전용) 빈 배열. */
    public function forPlacing(array $input): array
    {
        $methods = $this->methods();
        if ($methods === []) return [];
        $method = Input::text($input['payment_method'] ?? '', 'payment_method', 20);
        if (!isset($methods[$method])) throw DomainError::validation(['payment_method' => '결제 수단을 선택해 주세요.']);
        $payment = $this->settings->all()['payment'];
        $hours = (int) $payment['deadline_hours'][self::deadlineKey($method)];
        $spec = ['method' => $method, 'pay_by' => Clock::timestamp() + $hours * 3600, 'detail' => []];
        if ($method === 'manual_transfer') {
            $spec['detail'] = ['depositor' => Input::text($input['depositor'] ?? '', 'depositor', 100),
                'bank' => $payment['manual']['bank'], 'account' => $payment['manual']['account'], 'holder' => $payment['manual']['holder']];
            return $spec;
        }
        $summary = $this->app->paymentSettings()->summary($payment['environment']);
        return $spec + ['id' => bin2hex(random_bytes(16)), 'environment' => $payment['environment'], 'revision' => $summary['revision']];
    }

    /** 카드·간편결제·계좌이체는 카드 기한을, 가상계좌·무통장은 제 기한을 쓴다. */
    private static function deadlineKey(string $method): string
    {
        return in_array($method, ['virtual_account', 'manual_transfer'], true) ? $method : 'card';
    }

    public function isPgOrder(array $order): bool
    {
        return in_array($order['payment_method'], Orders::PG_METHODS, true) && $order['payment_id'] !== '';
    }

    /** 게이트웨이 계약이 받는 주문 배열. id 는 결제 원장 키다. */
    public static function gatewayOrder(array $order): array
    {
        $items = $order['items'] ?? [];
        $first = (string) ($items[0]['product_name'] ?? '주문');
        $name = count($items) > 1 ? $first . ' 외 ' . (count($items) - 1) . '건' : $first;
        return ['id' => (string) $order['payment_id'], 'provider' => self::PROVIDER, 'environment' => (string) $order['payment_environment'],
            'config_revision' => (string) $order['payment_revision'], 'total' => (int) $order['total'], 'order_name' => $name,
            'transaction_id' => (string) ($order['payment']['tid'] ?? ''), 'created_at' => (int) $order['created_at']];
    }

    /** 이니시스가 인증 결과를 보낼 주소. 주문의 원장 키와 그 주문·결제사·설정 판의 HMAC 을 싣는다. */
    public function callbackUrl(array $order, string $callbackBase): string
    {
        return $callbackBase . '?order=' . $order['payment_id'] . '&state=' . CallbackToken::create($this->app, self::gatewayOrder($order));
    }

    /** 결제창 정의. 결제 대기이고 기한 안인 결제사 주문만. */
    public function checkout(array $order, string $device, string $returnUrl, string $callbackBase): array
    {
        if ($order['status'] !== 'pending' || !$this->isPgOrder($order)) throw DomainError::validation(['payment' => '결제할 수 있는 주문이 아닙니다.']);
        if ((int) $order['pay_by'] > 0 && (int) $order['pay_by'] < Clock::timestamp()) throw DomainError::validation(['payment' => '결제 기한이 지났습니다. 주문을 다시 접수해 주세요.']);
        $gateway = $this->app->inicisGateway();
        $customer = ['name' => $order['buyer_name'], 'phone' => preg_replace('/\D/', '', $order['phone']) ?? '', 'email' => $order['email']];
        $callbackUrl = $this->callbackUrl($order, $callbackBase);
        return ExecutionLock::run($this->app->storageDir(), static fn (): array => $gateway->checkout(self::gatewayOrder($order), $customer, $returnUrl, $callbackUrl, $device));
    }

    /**
     * 콜백 처리: 승인한 뒤 조회로 결과를 확인하고 결제 완료로 적는다. 승인 실패·검증 실패는
     * 예외로 올리고 주문은 그대로 둔다 — 원장이 pending 으로 잠가 재승인을 막는다.
     */
    public function complete(array $order, array $callback): array
    {
        $gateway = $this->app->inicisGateway();
        $gw = self::gatewayOrder($order);
        ExecutionLock::run($this->app->storageDir(), static fn () => $gateway->complete($gw, $callback));
        return $this->applyFetched($order, $gateway->fetch($gw), '결제가 승인되었습니다.');
    }

    /** 관리자 결제 조회. 승인됐는데 주문이 아직 결제 대기면 결제 완료로 맞춘다. */
    public function sync(array $order): array
    {
        if (!$this->isPgOrder($order)) throw DomainError::validation(['payment' => '결제사 결제가 아닌 주문입니다.']);
        $payment = $this->app->inicisGateway()->fetch(self::gatewayOrder($order));
        if (($payment['status'] ?? '') === 'NOT_FOUND') return $order;
        if (!($payment['valid'] ?? false)) throw DomainError::serviceUnavailable('결제사 조회 결과가 주문과 맞지 않습니다. PG 관리자 화면에서 확인해 주세요.');
        if ($order['status'] === 'pending' && $payment['status'] === 'PAID') return $this->applyFetched($order, $payment, '결제 조회로 승인을 확인했습니다.');
        return $this->orders->get((int) $order['id']);
    }

    private function applyFetched(array $order, array $payment, string $note): array
    {
        if (($payment['status'] ?? '') !== 'PAID' || !($payment['valid'] ?? false)) throw DomainError::serviceUnavailable('승인 결과를 확인하지 못했습니다. 관리자에게 문의해 주세요.');
        return $this->orders->markPaid((int) $order['id'], 'pg:' . self::PROVIDER, (int) $order['total'],
            ['tid' => (string) $payment['transaction_id'], 'label' => self::METHODS[$order['payment_method']] ?? $order['payment_method']], (int) $payment['paid_at'], $note);
    }

    /** 환불. 결제사 주문은 PG 환불이 먼저 성공해야 기록하고, 무통장은 밖에서 돌려준 돈을 기록만 한다. */
    public function refund(array $order, int $amount, string $reason, string $key, string $actor): array
    {
        if (!in_array($order['status'], ['paid', 'confirmed'], true)) throw DomainError::validation(['refund' => '결제 완료 상태의 주문만 환불할 수 있습니다.']);
        $remaining = (int) $order['paid_amount'] - (int) $order['refunded_amount'];
        if ($amount < 1 || $amount > $remaining) throw DomainError::validation(['refund' => '환불 금액을 확인해 주세요.']);
        if ($this->isPgOrder($order)) {
            $gateway = $this->app->inicisGateway();
            $gw = self::gatewayOrder($order);
            ExecutionLock::run($this->app->storageDir(), static fn (): array => $gateway->cancel($gw, $amount, $remaining, $reason, $key));
        }
        return $this->orders->recordRefund((int) $order['id'], $amount, $actor, $reason);
    }

    /** 결제사 승인이 진행 중이거나 끝난 주문인지. 만료 취소가 이런 주문을 건너뛴다. 작업 9 가 원장 상태를 읽게 한다. */
    public function inProgress(array $order): bool
    {
        return false;
    }

    public function expireOverdue(): int
    {
        return $this->orders->expire(Clock::timestamp(), fn (array $order): bool => $this->inProgress($order));
    }
}
```

`Service.php`: 속성 `public readonly Commerce\Payments $payments;` 를 `$orders` 아래에 선언하고 생성자 끝에
```php
        $this->payments = new Commerce\Payments($app, $this->settings, $this->orders);
```

- [ ] **Step 4: 테스트 통과**

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/PaymentsTest.php` 그리고 `vendor/bin/phpunit --no-coverage tests/YoungCart`
Expected: PASS. `testCompleteApprovesAndMarksTheOrderPaidOnce`의 두 번째 `complete()`가 `승인 결과를 확인하지 못했습니다`로 실패하면 큐에 넣은 응답 수(승인 1 + 조회 1, 재전송 때 조회 1)를 다시 센다.

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Commerce/Payments.php modules/youngcart/src/Commerce/Orders.php modules/youngcart/src/Service.php tests/YoungCart/PaymentsTest.php
git commit -m "feat: connect YoungCart orders to the payment layer

Payments lists the methods the shop can offer, prepares the payment
fields an order is placed with, turns an order into the gateway's order
array, and records approvals, deposit confirmations and refunds. Paid
orders cannot be cancelled by customers, and card orders need a refund
before an admin cancels them.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5: 주문서의 결제 수단 선택과 접수

**Files:**
- Modify: `modules/youngcart/src/Web/CommerceController.php` (`checkout()`, `order` 페이지), `modules/youngcart/templates/checkout.php`, `modules/youngcart/templates/order.php`
- Test: `tests/Web/YoungCartCommerceTest.php`

**Interfaces:**
- Consumes: `Payments::methods()`, `forPlacing()`, `isPgOrder()`, `expireOverdue()`, `Payments::METHODS`; `Orders::place(…, $payment)`.
- Produces: 주문서 폼 필드 `payment_method`, `depositor`. 접수 뒤 결제사 수단은 `/shop/pay?number=…`로, 무통장·접수 전용은 `/shop/order?number=…`로 간다. 주문 화면 변수 `pay_url`(문자열|null), `pay_state`(`''`·`closed`·`failed`), `method_labels`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/YoungCartCommerceTest.php`에 헬퍼와 테스트를 더한다. 파일 상단 `use`에 `GnuCms\Payment\InicisGateway`, `GnuCms\Tests\Payment\FakeTransport`, `GnuCms\Tests\Payment\Fixtures`를 추가.

```php
    private FakeTransport $http;
    private array $payConfig;

    /** 이니시스 테스트 환경을 켜고 쇼핑몰이 그 환경을 쓰게 한다. 무통장 계좌도 켠다. */
    private function enablePayments(): void
    {
        $this->payConfig = Fixtures::config();
        $this->app->paymentSettings()->save('test', $this->payConfig);
        $this->app->paymentSettings()->enable('test', true);
        $this->http = new FakeTransport();
        $this->app->setInicisGateway(new InicisGateway($this->app->paymentSettings(), $this->http));
        $settings = $this->shop->settings->all();
        $settings['payment'] = ['environment' => 'test', 'manual' => ['enabled' => true, 'bank' => '국민은행', 'account' => '123-45', 'holder' => '상점'],
            'deadline_hours' => ['card' => 1, 'virtual_account' => 72, 'manual_transfer' => 72]];
        $this->app->db()->update('yc_settings', ['payload' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)], 'id = :id', ['id' => 'settings']);
    }

    /** 303 Location 의 number= 값. */
    private function numberFrom(\Psr\Http\Message\ResponseInterface $response): string
    {
        $location = $response->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        return (string) ($query['number'] ?? '');
    }

    #[DataProvider('connectionProvider')]
    public function testCheckoutStaysReceiptOnlyWhenNoMethodIsEnabled(array $config): void
    {
        $this->setupShop($config);
        $this->add();
        $html = $this->body($this->get($this->app, '/shop/checkout'));
        self::assertStringNotContainsString('name="payment_method"', $html);
        self::assertStringContainsString('주문 접수 안내', $html);
        $response = $this->post($this->app, '/shop/checkout', $this->checkout());
        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/shop/order?number=', $response->getHeaderLine('Location'));
    }

    #[DataProvider('connectionProvider')]
    public function testCheckoutOffersMethodsAndSendsCardOrdersToThePayPage(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $this->add();
        $html = $this->body($this->get($this->app, '/shop/checkout'));
        self::assertStringContainsString('name="payment_method" value="card"', $html);
        self::assertStringContainsString('name="payment_method" value="manual_transfer"', $html);
        self::assertStringContainsString('국민은행 123-45', $html);
        self::assertStringNotContainsString('이 화면에서는 결제되지 않습니다', $html);

        $response = $this->post($this->app, '/shop/checkout', $this->checkout(['payment_method' => 'card']));
        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('/shop/pay?number=', $response->getHeaderLine('Location'));
        $number = $this->numberFrom($response);
        $order = $this->shop->orders->get((int) $this->app->db()->selectOne('SELECT id FROM ' . $this->app->db()->table('yc_orders') . ' WHERE number = ?', [$number])['id']);
        self::assertSame('card', $order['payment_method']);
        self::assertSame('pending', $order['status']);

        $page = $this->body($this->get($this->app, '/shop/order', ['number' => $number, 'pay' => 'closed']));
        self::assertStringContainsString('결제창이 닫혔습니다', $page);
        self::assertStringContainsString('/shop/pay?number=' . rawurlencode($number), $page);
    }

    #[DataProvider('connectionProvider')]
    public function testCheckoutRejectsAMissingMethodAndKeepsTheForm(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $this->add();
        $response = $this->post($this->app, '/shop/checkout', $this->checkout(['payment_method' => '']));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('결제 수단을 선택해 주세요', $this->body($response));
    }

    #[DataProvider('connectionProvider')]
    public function testManualTransferOrderShowsTheAccountAndDeadline(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $this->add();
        $response = $this->post($this->app, '/shop/checkout', $this->checkout(['payment_method' => 'manual_transfer', 'depositor' => '홍길동']));
        self::assertStringContainsString('/shop/order?number=', $response->getHeaderLine('Location'));
        $page = $this->body($this->get($this->app, '/shop/order', ['number' => $this->numberFrom($response)]));
        self::assertStringContainsString('입금 안내', $page);
        self::assertStringContainsString('국민은행 123-45 (예금주 상점)', $page);
        self::assertStringContainsString('홍길동', $page);
        self::assertStringNotContainsString('결제하기', $page);
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter "ReceiptOnly|OffersMethods|MissingMethod|ManualTransferOrder" tests/Web/YoungCartCommerceTest.php`
Expected: 첫 테스트는 PASS 일 수 있고(접수 전용은 지금 동작이다) 나머지 셋은 FAIL.

- [ ] **Step 2: 컨트롤러**

`CommerceController::checkout()`:
1. `$quote = $this->service->cart->quote($cart, $choices, true);` 다음 줄에
```php
        $methods = $this->service->payments->methods();
```
2. 접수 `try` 블록 안, `$order = $this->service->orders->place(…)` 앞에
```php
                $this->service->payments->expireOverdue();
                $payment = $this->service->payments->forPlacing($input);
```
그리고 `place(...)` 호출 끝 인자에 `, $payment`를 더한다: `place($cart, $input, $token, $_SESSION['yc_owner'], $userId, $issued['fingerprint'], $choices, $payment)`.
3. 접수 뒤 `return $this->redirect($response, $data['url'] . '/order?number=' …)` 를
```php
                $next = $payment !== [] && $this->service->payments->isPgOrder($order) ? '/pay' : '/order';
                return $this->redirect($response, $data['url'] . $next . '?number=' . rawurlencode($order['number']));
```
4. `$data += ['flow' => $flow, 'checkout_token' => $token, 'quote' => $quote];` 를
```php
        $data += ['flow' => $flow, 'checkout_token' => $token, 'quote' => $quote, 'payment_methods' => $methods, 'payment' => $this->service->settings->all()['payment']];
```

`handle()`의 `order` 페이지: `$data['order'] = $order;` 다음에
```php
            $payState = $input['pay'] ?? '';
            $data['pay_state'] = in_array($payState, ['closed', 'failed'], true) ? $payState : '';
            $data['pay_url'] = $order['status'] === 'pending' && $this->service->payments->isPgOrder($order) && ((int) $order['pay_by'] === 0 || (int) $order['pay_by'] > \GnuCms\Support\Clock::timestamp())
                ? $url . '/pay?number=' . rawurlencode($number) : null;
            $data['method_labels'] = \GnuCms\Modules\YoungCart\Commerce\Payments::METHODS;
```

- [ ] **Step 3: 템플릿**

`checkout.php`: 배송비 섹션(`<section class="yc-panel"><h2>배송비</h2>…</section>`) 다음, `</div>`(yc-checkout-sections 닫힘) 앞에
```php
<?php if ($payment_methods !== []): ?>
<section class="yc-panel" id="yc-payment"><h2>결제 수단</h2><div class="yc-form-stack">
<?php $picked = $input['payment_method'] ?? array_key_first($payment_methods); foreach ($payment_methods as $key => $label): ?>
<label class="yc-choice"><input class="radio radio-sm" type="radio" name="payment_method" value="<?= $this->e($key) ?>" required<?= $picked === $key ? ' checked' : '' ?>><span><?= $this->e($label) ?></span></label>
<?php endforeach ?>
<?php if (isset($payment_methods['manual_transfer'])): ?><div class="yc-manual-transfer"><p class="yc-help">무통장입금 계좌: <?= $this->e($payment['manual']['bank'] . ' ' . $payment['manual']['account']) ?> (예금주 <?= $this->e($payment['manual']['holder']) ?>). 접수 후 <?= (int) $payment['deadline_hours']['manual_transfer'] ?>시간 안에 입금해 주세요.</p>
<label class="yc-field" for="yc-depositor"><span>입금자명 <small class="muted">선택</small></span><input class="input input-bordered" id="yc-depositor" name="depositor" maxlength="100" value="<?= $this->e($input['depositor'] ?? '') ?>"></label></div><?php endif ?>
</div></section>
<?php endif ?>
```
주문 접수 안내 블록(`<div class="yc-order-notice"><strong>주문 접수 안내</strong>…</div>`)을 `<?php if ($payment_methods === []): ?> … <?php endif ?>`로 감싼다. 접수 버튼 문구 `원 주문 접수`를 `원 <?= $payment_methods === [] ? '주문 접수' : '주문하고 결제하기' ?>`로 바꾼다.

`order.php`: `<?php if ($order['status'] === 'pending'): ?><details class="yc-cancel">` 앞에
```php
<?php if ($pay_url !== null): ?><div class="yc-order-notice"><strong>결제 대기</strong><p><?= $pay_state === 'closed' ? '결제창이 닫혔습니다. ' : ($pay_state === 'failed' ? '결제 결과를 확인하지 못했습니다. 다시 시도하거나 상점에 문의해 주세요. ' : '') ?>결제 기한 <?= $this->e(date('Y-m-d H:i', (int) $order['pay_by'])) ?></p><a class="yc-button yc-button-primary yc-button-block" href="<?= $this->e($pay_url) ?>">결제하기</a></div><?php endif ?>
<?php if ($order['payment_method'] === 'manual_transfer' && $order['status'] === 'pending'): ?><div class="yc-order-notice"><strong>입금 안내</strong><p><?= $this->e(($order['payment']['bank'] ?? '') . ' ' . ($order['payment']['account'] ?? '') . ' (예금주 ' . ($order['payment']['holder'] ?? '') . ')') ?></p><p>입금자명 <?= $this->e(($order['payment']['depositor'] ?? '') !== '' ? $order['payment']['depositor'] : $order['buyer_name']) ?> · 입금 기한 <?= $this->e(date('Y-m-d H:i', (int) $order['pay_by'])) ?></p></div><?php endif ?>
<?php if ((int) $order['paid_at'] > 0): ?><div class="yc-order-notice"><strong>결제 완료</strong><p><?= $this->e($method_labels[$order['payment_method']] ?? $order['payment_method']) ?> · <?= number_format((int) $order['paid_amount']) ?>원 · <?= $this->e(date('Y-m-d H:i', (int) $order['paid_at'])) ?><?= (int) $order['refunded_amount'] > 0 ? ' · 환불 ' . number_format((int) $order['refunded_amount']) . '원' : '' ?></p></div><?php endif ?>
```
고객 취소 `<details class="yc-cancel">` 조건은 그대로 `pending`이다(결제 뒤에는 나오지 않는다).

Run: `vendor/bin/phpunit --no-coverage tests/Web/YoungCartCommerceTest.php` 와 `php -l` 두 템플릿
Expected: PASS.

- [ ] **Step 4: 커밋**

```bash
git add modules/youngcart/src/Web/CommerceController.php modules/youngcart/templates/checkout.php modules/youngcart/templates/order.php tests/Web/YoungCartCommerceTest.php
git commit -m "feat: let the checkout pick a payment method and route card orders to the pay page

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6: 결제 페이지와 콜백

**Files:**
- Create: `modules/youngcart/src/Web/PayController.php`, `modules/youngcart/templates/pay.php`
- Modify: `modules/youngcart/bootstrap.php`
- Test: `tests/Web/YoungCartCommerceTest.php`

**Interfaces:**
- Consumes: `Payments::checkout()`, `complete()`, `gatewayOrder()`; `Orders::byPaymentId()`, `owned()`; `CallbackToken::verify()`; `Context::externalPost()`.
- Produces: `GET /shop/pay?number=…`(주문 주인만), `POST /shop/pay/callback?order=<payment_id>&state=<hmac>`(세션 없음, 폼 본문). 콜백 뒤에는 `app.url` 기준 절대 주소로 `/shop/order?number=…`(실패면 `&pay=failed`)로 303. `ExternalRequests`는 인증기를 본문보다 먼저 부르므로 인증은 쿼리만으로 한다.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/YoungCartCommerceTest.php`에 추가(`use Slim\Psr7\Factory\ServerRequestFactory;`와 `GnuCms\Web\Kernel`은 파일에 이미 있다):

```php
    private function placeCardOrder(): array
    {
        $this->add();
        $response = $this->post($this->app, '/shop/checkout', $this->checkout(['payment_method' => 'card']));
        $number = $this->numberFrom($response);
        return $this->shop->orders->get((int) $this->app->db()->selectOne('SELECT id FROM ' . $this->app->db()->table('yc_orders') . ' WHERE number = ?', [$number])['id']);
    }

    private function callbackFor(array $order): array
    {
        return ['resultCode' => '0000', 'mid' => $this->payConfig['merchant_id'], 'orderNumber' => $order['payment_id'], 'idc_name' => 'stg',
            'authToken' => bin2hex(random_bytes(32)), 'authUrl' => 'https://stgstdpay.inicis.com/api/payAuth', 'netCancelUrl' => 'https://stgstdpay.inicis.com/api/netCancel'];
    }

    private function queueApproval(array $order, string $tid): void
    {
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '0000', 'mid' => $this->payConfig['merchant_id'], 'MOID' => $order['payment_id'],
            'TotPrice' => (string) $order['total'], 'payMethod' => 'Card', 'tid' => $tid, 'currency' => 'WON']];
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => 'SUCCESS', 'transactionStatus' => 'APPROVAL', 'mid' => $this->payConfig['merchant_id'],
            'oid' => $order['payment_id'], 'price' => (string) $order['total'], 'tid' => $tid, 'paymethod' => 'Card', 'approvedDate' => '20260920', 'approvedTime' => '120000',
            'cardInfo' => ['currencyCode' => 'WON'], 'partCancelTransInfo' => []]];
    }

    /** 세션 없는 외부 요청. 폼 본문과 쿼리를 그대로 싣는다. */
    private function externalPost(string $path, array $query, array $body): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path . '?' . http_build_query($query))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody((new \Slim\Psr7\Factory\StreamFactory())->createStream(http_build_query($body)));
        return (new Kernel($this->app))->handle($request);
    }

    #[DataProvider('connectionProvider')]
    public function testPayPageOpensTheInicisWindowForTheOrderOwnerOnly(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $order = $this->placeCardOrder();
        $html = $this->body($this->get($this->app, '/shop/pay', ['number' => $order['number']]));
        self::assertStringContainsString('INIStdPay.js', $html);
        self::assertStringContainsString('name="oid" value="' . $order['payment_id'] . '"', $html);
        self::assertStringContainsString('name="price" value="' . $order['total'] . '"', $html);
        self::assertStringContainsString('name="returnUrl" value="https://shop.example.test/shop/pay/callback?order=' . $order['payment_id'], $html);
        self::assertStringContainsString('name="closeUrl" value="https://shop.example.test/shop/order?number=' . rawurlencode($order['number']) . '&amp;pay=closed"', $html);

        session_start(); $_SESSION['yc_guest_orders'] = []; session_write_close();
        self::assertSame(404, $this->get($this->app, '/shop/pay', ['number' => $order['number']])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testCallbackNeedsTheOrderStateAndMarksTheOrderPaid(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $order = $this->placeCardOrder();
        $this->get($this->app, '/shop/pay', ['number' => $order['number']]);
        $state = \GnuCms\Payment\CallbackToken::create($this->app, \GnuCms\Modules\YoungCart\Commerce\Payments::gatewayOrder($order));

        self::assertSame(403, $this->externalPost('/shop/pay/callback', ['order' => $order['payment_id'], 'state' => str_repeat('0', 64)], $this->callbackFor($order))->getStatusCode());
        self::assertSame(403, $this->externalPost('/shop/pay/callback', ['order' => str_repeat('a', 32), 'state' => $state], $this->callbackFor($order))->getStatusCode());
        self::assertSame('pending', $this->shop->orders->get((int) $order['id'])['status']);

        $tid = bin2hex(random_bytes(20));
        $this->queueApproval($order, $tid);
        $response = $this->externalPost('/shop/pay/callback', ['order' => $order['payment_id'], 'state' => $state], $this->callbackFor($order));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('https://shop.example.test/shop/order?number=' . rawurlencode($order['number']), $response->getHeaderLine('Location'));
        $paid = $this->shop->orders->get((int) $order['id']);
        self::assertSame('paid', $paid['status']);
        self::assertSame($tid, $paid['payment']['tid']);

        $page = $this->body($this->get($this->app, '/shop/order', ['number' => $order['number']]));
        self::assertStringContainsString('결제 완료', $page);
        self::assertStringNotContainsString('주문 취소', $page);
    }

    #[DataProvider('connectionProvider')]
    public function testFailedApprovalSendsTheCustomerBackWithAFailureNote(array $config): void
    {
        $this->setupShop($config); $this->enablePayments();
        $order = $this->placeCardOrder();
        $this->get($this->app, '/shop/pay', ['number' => $order['number']]);
        $state = \GnuCms\Payment\CallbackToken::create($this->app, \GnuCms\Modules\YoungCart\Commerce\Payments::gatewayOrder($order));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '9999', 'resultMsg' => '거절']];
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '00']]; // 망취소 응답
        $response = $this->externalPost('/shop/pay/callback', ['order' => $order['payment_id'], 'state' => $state], $this->callbackFor($order));
        self::assertSame(303, $response->getStatusCode());
        self::assertStringEndsWith('&pay=failed', $response->getHeaderLine('Location'));
        self::assertSame('pending', $this->shop->orders->get((int) $order['id'])['status']);
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter "PayPage|CallbackNeeds|FailedApproval" tests/Web/YoungCartCommerceTest.php`
Expected: FAIL — `/shop/pay`가 404.

- [ ] **Step 2: PayController**

`modules/youngcart/src/Web/PayController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Web;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Commerce\Payments;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Payment\CallbackToken;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * 결제 페이지(주문 주인만)와 이니시스가 부르는 콜백(세션 없음). 콜백은 ExternalRequests 가
 * 인증기를 본문보다 먼저 부르므로 인증은 쿼리(order=원장 키, state=HMAC)만으로 한다.
 */
final class PayController
{
    public function __construct(private Service $service, private string $routePrefix, private ?string $adminRoutePrefix) {}

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $base = RouteContext::fromRequest($request)->getBasePath();
        $url = $base . $this->routePrefix;
        $view = View::forExtension($request, 'youngcart', dirname(__DIR__, 2) . '/templates');
        $response = $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
        $this->service->requireReady();
        $identity = $this->service->app->guestAcl()->identity();
        $userId = $identity->isGuest() ? null : (int) $identity->sub();
        $_SESSION['yc_guest_orders'] ??= [];
        $number = Input::text($request->getQueryParams()['number'] ?? '', 'number', 32, false);
        $order = $this->service->orders->owned($number, $userId, $_SESSION['yc_guest_orders']);
        $site = $this->siteUrl();
        try {
            $payment = $this->service->payments->checkout($order, self::device($request->getHeaderLine('User-Agent')),
                $site . $this->routePrefix . '/order?number=' . rawurlencode($number) . '&pay=closed', $site . $this->routePrefix . '/pay/callback');
        } catch (DomainError $e) {
            if ($e->status() >= 500) throw $e;
            return $response->withStatus(303)->withHeader('Location', $url . '/order?number=' . rawurlencode($number) . '&pay=failed');
        }
        return $view->render($response, 'pay', ['url' => $url, 'base' => $base, 'order' => $order, 'payment' => $payment,
            'method_label' => Payments::METHODS[$order['payment_method']] ?? $order['payment_method']]);
    }

    /** ExternalRequests 인증기: 쿼리의 원장 키로 주문을 찾고 state 가 그 주문·결제사·설정 판의 HMAC 과 맞아야 한다. */
    public function callbackAuthenticate(ServerRequestInterface $request): bool
    {
        $order = $this->orderFromQuery($request);
        return $order !== null && CallbackToken::verify($this->service->app, Payments::gatewayOrder($order), $request->getQueryParams()['state'] ?? null);
    }

    /** ExternalRequests 처리기: 승인·조회 뒤 주문 화면으로 보낸다. 실패는 pay=failed 로 알린다. */
    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $order = $this->orderFromQuery($request);
        if ($order === null) throw DomainError::forbidden('주문을 확인할 수 없습니다.');
        $body = $request->getParsedBody();
        $suffix = '';
        try {
            $this->service->payments->complete($order, is_array($body) ? $body : []);
        } catch (DomainError) {
            $suffix = '&pay=failed';
        }
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Location', $this->siteUrl() . $this->routePrefix . '/order?number=' . rawurlencode($order['number']) . $suffix);
    }

    public static function device(string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent) ? 'mobile' : 'web';
    }

    private function orderFromQuery(ServerRequestInterface $request): ?array
    {
        $id = $request->getQueryParams()['order'] ?? '';
        return is_string($id) ? $this->service->orders->byPaymentId($id) : null;
    }

    private function siteUrl(): string
    {
        return rtrim((string) $this->service->app->config('app.url', GNUCMS_URL), '/');
    }
}
```

- [ ] **Step 3: 템플릿과 라우트**

`modules/youngcart/templates/pay.php`:
```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?>결제 · 쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop"><div class="yc-page-heading"><div><p class="yc-kicker"><?= $this->e($order['number']) ?></p><h1 class="yc-title"><?= $this->e($method_label) ?> 결제창으로 이동합니다</h1></div></div>
<section class="yc-panel"><p class="yc-help"><?= number_format((int) $order['total']) ?>원. 결제창이 열리지 않으면 아래 버튼을 눌러 주세요.</p>
<form id="yc-pay-form" method="post" action="<?= $this->e($payment['action'] ?? '') ?>" accept-charset="<?= $this->e($payment['charset'] ?? 'UTF-8') ?>">
<?php foreach ($payment['fields'] as $field => $value): ?><input type="hidden" name="<?= $this->e($field) ?>" value="<?= $this->e((string) $value) ?>"><?php endforeach ?>
<button class="yc-button yc-button-primary" id="yc-pay-button" type="<?= $payment['kind'] === 'inicis' ? 'button' : 'submit' ?>">결제창 열기</button>
</form>
<p id="yc-pay-message" class="yc-help" role="status"></p>
<p><a class="yc-more" href="<?= $this->e($url) ?>/order?number=<?= rawurlencode($order['number']) ?>">주문 상세로 돌아가기</a></p></section></div>
<?php $this->stop() ?>
<?php $this->start('scripts') ?>
<?php if ($payment['kind'] === 'inicis'): ?>
<script src="<?= $this->e($payment['script']) ?>"></script>
<script>(()=>{'use strict';const button=document.getElementById('yc-pay-button'),message=document.getElementById('yc-pay-message');const open=()=>{if(!window.INIStdPay){message.textContent='결제창 연결을 확인해 주세요. 잠시 후 다시 시도해 주세요.';return}window.INIStdPay.pay('yc-pay-form')};button.addEventListener('click',open);window.setTimeout(open,300);})();</script>
<?php else: ?>
<script>(()=>{'use strict';window.setTimeout(()=>{document.getElementById('yc-pay-form').submit();},300);})();</script>
<?php endif ?>
<?php $this->stop() ?>
```
`bootstrap.php`: `$orders = new …OrderController(…)` 줄 앞에
```php
    $pay = new \GnuCms\Modules\YoungCart\Web\PayController($service, $context->routePrefix, $context->adminRoutePrefix);
    $context->route('GET', '/pay', static fn ($request, $response) => $pay->show($request, $response));
    $context->externalPost('/pay/callback', [$pay, 'callbackAuthenticate'], [$pay, 'callback'], 65536, 'application/x-www-form-urlencoded');
```

Run: `vendor/bin/phpunit --no-coverage tests/Web/YoungCartCommerceTest.php`
Expected: PASS. `INIStdPay.pay('yc-pay-form')`는 이니시스 스크립트가 폼 id 로 결제창을 연다(이니톡 브랜치의 `pay/start.php`와 같은 호출).

- [ ] **Step 4: 커밋**

```bash
git add modules/youngcart/src/Web/PayController.php modules/youngcart/templates/pay.php modules/youngcart/bootstrap.php tests/Web/YoungCartCommerceTest.php
git commit -m "feat: open the Inicis window from the shop and take its callback without a session

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7: 관리자 — 입금 확인·결제 조회·결제 구역·주문 목록

**Files:**
- Modify: `modules/youngcart/src/Admin/OrderController.php`, `modules/youngcart/templates/admin/order.php`, `modules/youngcart/templates/admin/orders.php`
- Create: `modules/youngcart/templates/admin/_refund_form.php` (이 작업에서는 주석 한 줄, 작업 8이 채운다)
- Test: `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- Consumes: `Orders::confirmDeposit()`, `Payments::sync()`, `Payments::expireOverdue()`, `Payments::METHODS`, `Payments::isPgOrder()`.
- Produces: `POST /admin/shop/orders/detail`의 `action` ∈ `''`(상태 변경, 지금과 같다)·`confirm-deposit`·`sync`·`refund`(작업 8). 화면 변수 `payment_methods`(라벨 표), `is_pg`, `refund_key`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/YoungCartAdminTest.php`에 추가. `enablePayments()`는 작업 5의 `YoungCartCommerceTest`와 같은 코드를 이 파일에도 둔다(두 파일이 서로를 참조하지 않는다). `use` 에 `GnuCms\Payment\InicisGateway`, `GnuCms\Tests\Payment\FakeTransport`, `GnuCms\Tests\Payment\Fixtures` 추가.

```php
    private function placeManualOrder(): array
    {
        $seed = $this->seedProducts();
        $cart = $this->shop->cart->add([], ['product_id' => $seed['b'], 'quantity' => 1]);
        $input = ['buyer_name' => '입금자', 'email' => 'buyer@example.test', 'phone' => '010-0000-0000', 'recipient' => '받는 분', 'recipient_phone' => '010-0000-0000',
            'postcode' => '04524', 'address' => '주소', 'address_detail' => '', 'delivery_note' => '', 'password' => bin2hex(random_bytes(12)), 'agree' => '1',
            'payment_method' => 'manual_transfer', 'depositor' => '홍길동'];
        return $this->shop->orders->place($cart, $input, bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), null,
            $this->shop->cart->quote($cart, [], true)['fingerprint'], [], $this->shop->payments->forPlacing($input));
    }

    #[DataProvider('connectionProvider')]
    public function testAdminConfirmsADepositAndSeesThePaymentPanel(array $config): void
    {
        $this->setupModule($config); $this->enablePayments();
        $order = $this->placeManualOrder();
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/admin/shop/orders/detail', ['id' => $order['id']]));
        self::assertStringContainsString('무통장입금', $page);
        self::assertStringContainsString('홍길동', $page);
        self::assertStringContainsString('value="confirm-deposit"', $page);
        self::assertStringNotContainsString('<option value="paid"', $page);

        $response = $this->post($this->app, '/admin/shop/orders/detail', $this->csrf(['id' => $order['id'], 'action' => 'confirm-deposit']));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('paid', $this->shop->orders->get((int) $order['id'])['status']);
        $page = $this->body($this->get($this->app, '/admin/shop/orders/detail', ['id' => $order['id'], 'saved' => 'confirm-deposit']));
        self::assertStringContainsString('입금을 확인했습니다', $page);
        self::assertStringContainsString('결제 완료', $page);
        self::assertStringNotContainsString('value="confirm-deposit"', $page);
    }

    #[DataProvider('connectionProvider')]
    public function testOrderListShowsTheMethodAndFiltersPaid(array $config): void
    {
        $this->setupModule($config); $this->enablePayments();
        $order = $this->placeManualOrder();
        $this->shop->orders->confirmDeposit((int) $order['id'], 'admin');
        $this->signIn(true);
        $list = $this->body($this->get($this->app, '/admin/shop/orders', ['status' => 'paid']));
        self::assertStringContainsString($order['number'], $list);
        self::assertStringContainsString('무통장입금', $list);
        self::assertStringNotContainsString('주문 상태는 결제 완료를 의미하지 않습니다', $list);
    }
```
`seedProducts()`는 이 파일에 있다(`'b'`가 재고 0 인 상품이면 `'a'`를 쓴다 — 재고가 있는 쪽을 고른다).

Run: `vendor/bin/phpunit --no-coverage --filter "ConfirmsADeposit|OrderListShows" tests/Web/YoungCartAdminTest.php`
Expected: FAIL — `confirm-deposit` 없음.

- [ ] **Step 2: 컨트롤러**

`OrderController::handle()`을 다음으로 바꾼다(`use GnuCms\Modules\YoungCart\Commerce\Payments;` 추가).

```php
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->context($request, $page);
        if ($redirect = $this->requireReady($response, $data)) return $redirect;
        $data['statuses'] = Orders::STATUSES;
        $data['payment_methods'] = Payments::METHODS;
        $this->service->payments->expireOverdue();
        if ($page === 'orders') {
            $data['status_filter'] = Input::text($data['input']['status'] ?? '', 'status', 20);
            $data['q'] = Input::text($data['input']['q'] ?? '', 'q', 100);
            $data['list'] = $this->service->orders->listing(null, $data['status_filter'], $this->page($data['input']['page'] ?? ''), true, $data['q']);
            return $this->render($request, $response, 'orders', $data);
        }
        $id = Input::id($data['input']['id'] ?? null);
        $action = Input::text($data['input']['action'] ?? '', 'action', 20);
        if ($request->getMethod() === 'POST') {
            try {
                $order = $this->service->orders->get($id);
                match ($action) {
                    'confirm-deposit' => $this->service->orders->confirmDeposit($id, $data['actor']),
                    'sync' => $this->service->payments->sync($order),
                    'refund' => $this->refund($order, $data),
                    default => $this->service->orders->transition($id, Input::text($data['input']['from'] ?? '', 'from', 20),
                        Input::text($data['input']['status'] ?? '', 'status', 20), $data['actor'], $data['input']),
                };
                return $this->redirect($response, $data['admin_url'] . '/orders/detail?id=' . $id . '&saved=' . ($action === '' ? '1' : $action));
            } catch (DomainError $e) { $data['errors'] = $e->details() ?: [$e->getMessage()]; $response = $response->withStatus($e->status()); }
        }
        $data['order'] = $this->service->orders->get($id);
        $data['next'] = array_values(array_diff(Orders::NEXT[$data['order']['status']], ['paid']));
        $data['is_pg'] = $this->service->payments->isPgOrder($data['order']);
        $data['refund_key'] = bin2hex(random_bytes(16));
        $data['notice'] = match ($data['input']['saved'] ?? '') {
            '1' => '주문 상태를 변경했습니다.', 'confirm-deposit' => '입금을 확인했습니다.', 'sync' => '결제 상태를 조회했습니다.', 'refund' => '환불을 처리했습니다.', default => '',
        };
        return $this->render($request, $response, 'order', $data);
    }

    /** 작업 8 이 채운다. */
    private function refund(array $order, array $data): void
    {
        throw DomainError::validation(['refund' => '아직 지원하지 않는 작업입니다.']);
    }
```
작업 2 가 `admin/order.php` 에 넣은 `$next = array_values(array_diff($next, ['paid']));` 한 줄은 이제 컨트롤러가 하므로 지운다.

- [ ] **Step 3: 템플릿**

`admin/order.php`: `<p class="yc-help">온라인 결제 내역이 없는 주문입니다. …</p>` 한 줄을 다음 블록으로 바꾼다.
```php
<section class="yc-panel yc-payment-panel"><h2>결제</h2>
<?php if ($order['payment_method'] === ''): ?><p class="yc-help">온라인 결제 내역이 없는 주문입니다. 결제 안내와 확인은 별도로 진행해 주세요.</p>
<?php else: ?><dl class="yc-detail-list">
<div><dt>수단</dt><dd><?= $this->e($payment_methods[$order['payment_method']] ?? $order['payment_method']) ?></dd></div>
<div><dt>상태</dt><dd><?= (int) $order['paid_at'] > 0 ? '결제 완료 · ' . $this->e(date('Y-m-d H:i', (int) $order['paid_at'])) . ' · ' . number_format((int) $order['paid_amount']) . '원' : '결제 대기 · 기한 ' . $this->e(date('Y-m-d H:i', (int) $order['pay_by'])) ?></dd></div>
<?php if ((int) $order['refunded_amount'] > 0): ?><div><dt>환불</dt><dd><?= number_format((int) $order['refunded_amount']) ?>원</dd></div><?php endif ?>
<?php if ($order['payment_method'] === 'manual_transfer'): ?><div><dt>입금자명</dt><dd><?= $this->e(($order['payment']['depositor'] ?? '') !== '' ? $order['payment']['depositor'] : $order['buyer_name']) ?></dd></div><div><dt>안내 계좌</dt><dd><?= $this->e(($order['payment']['bank'] ?? '') . ' ' . ($order['payment']['account'] ?? '') . ' ' . ($order['payment']['holder'] ?? '')) ?></dd></div><?php endif ?>
<?php if (($order['payment']['tid'] ?? '') !== ''): ?><div><dt>거래번호</dt><dd><?= $this->e($order['payment']['tid']) ?></dd></div><?php endif ?>
</dl>
<?php if ($order['status'] === 'pending' && $order['payment_method'] === 'manual_transfer'): ?><form method="post" action="<?= $this->e($admin_url) ?>/orders/detail"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="confirm-deposit"><button class="btn btn-primary btn-sm" type="submit">입금 확인</button></form><?php endif ?>
<?php if ($is_pg): ?><form method="post" action="<?= $this->e($admin_url) ?>/orders/detail"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="sync"><button class="btn btn-outline btn-sm" type="submit">결제 조회</button></form><?php endif ?>
<?php $this->insert('admin/_refund_form') ?>
<?php endif ?></section>
```
`admin/_refund_form.php`를 `<?php // 작업 8 이 채운다 ?>` 한 줄로 만든다.

`admin/orders.php`: 안내 문구 `주문 상태는 결제 완료를 의미하지 않습니다. 결제 확인 후 상품 준비와 배송을 진행해 주세요.`를 `결제 완료 상태의 주문부터 상품 준비와 배송을 진행해 주세요. 무통장입금은 주문 상세에서 입금을 확인합니다.`로 바꾼다. 표 머리 `<th>주문 상태</th>` 다음에 `<th>결제</th>`를 넣고, 각 행의 주문 상태 셀 다음에
```php
<td data-label="결제"><?= $order['payment_method'] === '' ? '—' : $this->e($payment_methods[$order['payment_method']] ?? $order['payment_method']) . ((int) $order['paid_at'] > 0 ? ' · 완료' : ' · 대기') ?></td>
```
빈 목록 행의 `colspan="5"`를 `6`으로.

Run: `vendor/bin/phpunit --no-coverage tests/Web/YoungCartAdminTest.php`
Expected: PASS.

- [ ] **Step 4: 커밋**

```bash
git add modules/youngcart/src/Admin/OrderController.php modules/youngcart/templates/admin/order.php modules/youngcart/templates/admin/orders.php modules/youngcart/templates/admin/_refund_form.php tests/Web/YoungCartAdminTest.php
git commit -m "feat: show payment details on the admin order screens and confirm manual deposits

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8: 환불과 결제 뒤 취소

**Files:**
- Modify: `modules/youngcart/src/Admin/OrderController.php` (`refund()`), `modules/youngcart/templates/admin/_refund_form.php`
- Test: `tests/YoungCart/PaymentsTest.php`, `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- Consumes: `Payments::refund(array $order, int $amount, string $reason, string $key, string $actor)`, `Orders::transition()`.
- Produces: 폼 필드 `action=refund`, `amount`, `reason`, `refund_key`(32자리 16진수), `cancel_order`(1이면 전액 환불 뒤 취소).

- [ ] **Step 1: 단위 테스트 — 결제사 환불과 무통장 환불**

`tests/YoungCart/PaymentsTest.php`에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testRefundGoesThroughThePgForCardAndIsOnlyRecordedForManualTransfer(array $config): void
    {
        $this->setupPayments($config);
        $card = $this->place('card');
        $this->shop->payments->checkout($card, 'web', 'https://shop.example.test/shop/order', 'https://shop.example.test/shop/pay/callback');
        $tid = bin2hex(random_bytes(20));
        $this->queueApproval($card, $tid);
        $paid = $this->shop->payments->complete($card, $this->callback($card));
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '00', 'prtcDate' => '20260921', 'prtcTime' => '090000', 'prtcPrice' => '2000', 'prtcRemains' => '10000', 'prtcTid' => bin2hex(random_bytes(20))]];
        $after = $this->shop->payments->refund($paid, 2000, '배송비 조정', bin2hex(random_bytes(16)), 'admin');
        self::assertSame(2000, (int) $after['refunded_amount']);
        self::assertSame('paid', $after['status']);
        self::assertSame('https://stginiapi.inicis.com/v2/pg/partialRefund', end($this->http->calls)['url']);
        self::assertStringContainsString('환불 2,000원: 배송비 조정', end($after['history'])['note']);

        $manual = $this->place('manual_transfer');
        $paidManual = $this->shop->orders->confirmDeposit((int) $manual['id'], 'admin');
        $calls = count($this->http->calls);
        $after = $this->shop->payments->refund($paidManual, (int) $paidManual['total'], '고객 요청', bin2hex(random_bytes(16)), 'admin');
        self::assertSame((int) $paidManual['total'], (int) $after['refunded_amount']);
        self::assertCount($calls, $this->http->calls, '무통장 환불은 PG 를 부르지 않는다');
        $cancelled = $this->shop->orders->transition((int) $after['id'], 'paid', 'cancelled', 'admin', ['note' => '환불 완료']);
        self::assertSame('cancelled', $cancelled['status']);
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter RefundGoesThrough tests/YoungCart/PaymentsTest.php`
Expected: PASS — `Payments::refund()`와 `recordRefund()`는 작업 4에서 만들었다. 실패하면 응답 필드명(`prtcDate`·`prtcTime`·`prtcPrice`·`prtcRemains`·`prtcTid`; 전액이면 `cancelDate`·`cancelTime`)이 `InicisGateway::refund()`가 읽는 이름과 맞는지 확인한다. 이 테스트는 회귀 방지용으로 남긴다.

- [ ] **Step 2: 실패하는 화면 테스트**

`tests/Web/YoungCartAdminTest.php`에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testAdminRefundsAndCancelsAPaidManualOrder(array $config): void
    {
        $this->setupModule($config); $this->enablePayments();
        $order = $this->placeManualOrder();
        $this->shop->orders->confirmDeposit((int) $order['id'], 'admin');
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/admin/shop/orders/detail', ['id' => $order['id']]));
        preg_match('/name="refund_key" value="([a-f0-9]{32})"/', $page, $m);
        self::assertArrayHasKey(1, $m, '환불 폼이 있어야 한다');

        $response = $this->post($this->app, '/admin/shop/orders/detail', $this->csrf(['id' => $order['id'], 'action' => 'refund', 'amount' => (string) $order['total'], 'reason' => '고객 요청', 'refund_key' => $m[1], 'cancel_order' => '1']));
        self::assertSame(303, $response->getStatusCode(), $this->body($response));
        $after = $this->shop->orders->get((int) $order['id']);
        self::assertSame((int) $order['total'], (int) $after['refunded_amount']);
        self::assertSame('cancelled', $after['status']);
        self::assertStringContainsString('환불을 처리했습니다', $this->body($this->get($this->app, '/admin/shop/orders/detail', ['id' => $order['id'], 'saved' => 'refund'])));
    }

    #[DataProvider('connectionProvider')]
    public function testRefundFormValidatesAmountAndReason(array $config): void
    {
        $this->setupModule($config); $this->enablePayments();
        $order = $this->placeManualOrder();
        $this->shop->orders->confirmDeposit((int) $order['id'], 'admin');
        $this->signIn(true);
        $response = $this->post($this->app, '/admin/shop/orders/detail', $this->csrf(['id' => $order['id'], 'action' => 'refund', 'amount' => '0', 'reason' => '', 'refund_key' => bin2hex(random_bytes(16))]));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, (int) $this->shop->orders->get((int) $order['id'])['refunded_amount']);
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter "AdminRefunds|RefundFormValidates" tests/Web/YoungCartAdminTest.php`
Expected: FAIL — 환불 폼 없음 / `아직 지원하지 않는 작업`.

- [ ] **Step 3: 컨트롤러와 폼**

`OrderController::refund()`를 채운다.
```php
    /** 환불. 전액 환불이고 cancel_order 가 켜져 있으면 주문도 취소한다(재고 복원은 transition 이 한다). */
    private function refund(array $order, array $data): void
    {
        $amount = Input::int($data['input']['amount'] ?? null, 'amount', 1, 999999999);
        $reason = Input::text($data['input']['reason'] ?? '', 'reason', 200, false);
        $key = Input::text($data['input']['refund_key'] ?? '', 'refund_key', 40, false);
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) throw DomainError::validation(['refund' => '환불 요청을 다시 열어 주세요.']);
        $after = $this->service->payments->refund($order, $amount, $reason, $key, $data['actor']);
        if (($data['input']['cancel_order'] ?? '') === '1' && (int) $after['refunded_amount'] >= (int) $after['paid_amount'] && in_array('cancelled', Orders::NEXT[$after['status']], true)) {
            $this->service->orders->transition((int) $after['id'], $after['status'], 'cancelled', $data['actor'], ['note' => '환불 뒤 주문을 취소했습니다.']);
        }
    }
```
`Input::int($value, $field, $min, $max, ?$default)`가 범위 밖 값을 DomainError 로 거절하는지 `modules/youngcart/src/Input.php:25`에서 확인한다. 기본값을 돌려주는 규약이면 `if ($amount < 1) throw DomainError::validation(['amount' => '환불 금액을 확인해 주세요.']);` 를 덧붙인다.

`admin/_refund_form.php`:
```php
<?php $remaining = (int) $order['paid_amount'] - (int) $order['refunded_amount']; ?>
<?php if ((int) $order['paid_at'] > 0 && $remaining > 0 && in_array($order['status'], ['paid', 'confirmed'], true)): ?>
<details class="yc-refund"><summary>환불</summary>
<form class="yc-form-stack" method="post" action="<?= $this->e($admin_url) ?>/orders/detail">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="refund"><input type="hidden" name="refund_key" value="<?= $this->e($refund_key) ?>">
<label class="yc-field"><span>환불 금액 <small>남은 금액 <?= number_format($remaining) ?>원</small></span><input class="input input-bordered" type="number" name="amount" min="1" max="<?= $remaining ?>" value="<?= $remaining ?>" required></label>
<label class="yc-field"><span>사유</span><input class="input input-bordered" name="reason" maxlength="200" required></label>
<label class="label"><input class="checkbox checkbox-sm" type="checkbox" name="cancel_order" value="1" checked> 전액 환불이면 주문도 취소</label>
<?php if ($is_pg): ?><p class="yc-help">결제사에 환불을 요청한 뒤 기록합니다. 같은 요청을 두 번 보내도 한 번만 처리됩니다.</p><?php else: ?><p class="yc-help">무통장입금은 환불을 계좌로 직접 보낸 뒤 여기 기록합니다.</p><?php endif ?>
<button class="btn btn-outline btn-sm" type="submit">환불 처리</button></form></details>
<?php endif ?>
```

Run: `vendor/bin/phpunit --no-coverage tests/Web/YoungCartAdminTest.php`
Expected: PASS.

- [ ] **Step 4: 커밋**

```bash
git add modules/youngcart/src/Admin/OrderController.php modules/youngcart/templates/admin/_refund_form.php tests/Web/YoungCartAdminTest.php tests/YoungCart/PaymentsTest.php
git commit -m "feat: refund paid orders from the admin order screen

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9: 미결제 만료와 문서

**Files:**
- Modify: `src/Payment/DirectGateway.php` (`approvalState()`), `modules/youngcart/src/Commerce/Payments.php` (`inProgress()`), `docs/youngcart.md`
- Test: `tests/Payment/GatewayTest.php`, `tests/YoungCart/PaymentsTest.php`

**Interfaces:**
- Produces: `DirectGateway::approvalState(array $order): string` — `none`·`ready`·`pending`·`confirmed`. `Payments::inProgress()`가 이를 읽는다.

- [ ] **Step 1: 실패하는 테스트 — 원장 승인 단계**

`tests/Payment/GatewayTest.php`에 추가(`approve()` 헬퍼는 그 파일에 있으며 승인 응답을 큐에 넣고 `complete()`를 부른다):

```php
    #[DataProvider('connectionProvider')]
    public function testApprovalStateReadsTheJournalWithoutCallingThePg(array $db): void
    {
        $this->setupGateway($db);
        self::assertSame('none', $this->gateway->approvalState($this->order));
        $this->checkout();
        self::assertSame('ready', $this->gateway->approvalState($this->order));
        $this->approve();
        self::assertSame('confirmed', $this->gateway->approvalState($this->order));
        self::assertSame([], array_filter($this->http->calls, static fn (array $call): bool => str_contains($call['url'], 'inquiry')));
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter ApprovalState tests/Payment/GatewayTest.php`
Expected: FAIL — `approvalState` 없음.

- [ ] **Step 2: `approvalState()`**

`src/Payment/DirectGateway.php`의 `pendingRefunds()` 앞에
```php
    /**
     * 원장의 승인 단계. none(결제창을 연 적 없음)·ready·pending(승인 요청 중)·confirmed. PG 를
     * 부르지 않는다. 미결제 만료가 pending·confirmed 주문을 건너뛰는 데 쓴다.
     */
    public function approvalState(array $order): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($order['id'] ?? ''))) return 'none';
        $state = $this->journal->read($order['id']);
        return (string) ($state['approval'] ?? 'none');
    }
```

Run: `vendor/bin/phpunit --no-coverage tests/Payment/GatewayTest.php`
Expected: PASS.

- [ ] **Step 3: 실패하는 테스트 — 만료**

`tests/YoungCart/PaymentsTest.php`에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testOverdueUnpaidOrdersExpireAndReturnStockButApprovingOnesAreLeftAlone(array $config): void
    {
        $this->setupPayments($config);
        $manual = $this->place('manual_transfer');
        $card = $this->place('card');
        $approving = $this->place('card');
        $this->shop->payments->checkout($approving, 'web', 'https://shop.example.test/shop/order', 'https://shop.example.test/shop/pay/callback');
        $db = $this->app->db();
        foreach ([$manual, $card, $approving] as $order) $db->update('yc_orders', ['pay_by' => Clock::timestamp() - 60], 'id = :id', ['id' => (int) $order['id']]);
        // 승인 요청 중인 주문: 승인 응답이 없는 채 complete() 가 던지면 원장은 pending 으로 남는다(결제 계층의 규칙).
        $this->http->responses[] = new \RuntimeException('timeout');
        $this->http->responses[] = ['status' => 200, 'body' => ['resultCode' => '00']];
        try { $this->shop->payments->complete($approving, $this->callback($approving)); } catch (\Throwable) {}
        self::assertSame('pending', $this->app->inicisGateway()->approvalState(Payments::gatewayOrder($this->shop->orders->get((int) $approving['id']))));

        $productId = (int) $manual['items'][0]['product_id'];
        $stockBefore = (int) $this->shop->products->get($productId)['stock'];
        self::assertSame(2, $this->shop->payments->expireOverdue());
        self::assertSame('cancelled', $this->shop->orders->get((int) $manual['id'])['status']);
        self::assertSame('cancelled', $this->shop->orders->get((int) $card['id'])['status']);
        self::assertSame('pending', $this->shop->orders->get((int) $approving['id'])['status']);
        self::assertSame($stockBefore + 1, (int) $this->shop->products->get($productId)['stock']);
        self::assertStringContainsString('결제 기한이 지나', end($this->shop->orders->get((int) $card['id'])['history'])['note']);
        self::assertSame(0, $this->shop->payments->expireOverdue(), '두 번째 호출은 할 일이 없다');
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter OverdueUnpaid tests/YoungCart/PaymentsTest.php`
Expected: FAIL — `approving` 주문까지 취소되어 3 ≠ 2.

- [ ] **Step 4: `inProgress()`**

`Payments::inProgress()`를 바꾼다.
```php
    /** 결제사 승인이 진행 중이거나 끝난 주문인지. 만료 취소가 이런 주문을 건너뛴다. */
    public function inProgress(array $order): bool
    {
        if (!$this->isPgOrder($order)) return false;
        return in_array($this->app->inicisGateway()->approvalState(self::gatewayOrder($order)), ['pending', 'confirmed'], true);
    }
```

Run: `vendor/bin/phpunit --no-coverage tests/YoungCart/PaymentsTest.php`
Expected: PASS.

- [ ] **Step 5: docs/youngcart.md**

머리말 문단의 `온라인 결제(PG)는 아직 연결하지 않았다. 주문 접수 후 결제·배송 안내는 판매자가 별도로 진행한다.`를 다음으로 바꾼다.
```
온라인 결제는 코어 결제 계층(설정 → 결제, `docs/payments.md`)의 KG이니시스로 한다. 결제 수단이
하나도 켜져 있지 않으면 예전처럼 접수만 받고 판매자가 결제를 따로 안내한다.
```
표의 사용자 주소 행 끝에 `, 결제 `/shop/pay?number=주문번호`, 결제 콜백 `/shop/pay/callback`(이니시스가 부른다, 세션 없음)`을 더하고, 테이블 행의 `(모듈 스키마 2판)`을 `(모듈 스키마 3판 — 3판이 `yc_orders`에 결제 칸을 더한다)`로 바꾼다. 「설치」 다음에 새 절을 넣는다.

```markdown
## 결제

주문서는 켜진 결제 수단만 보여 준다. 카드 결제는 쇼핑몰 설정의 **결제 환경**(운영·테스트)이
설정 → 결제에서 저장되고 실행이 허용돼 있을 때, 무통장입금은 쇼핑몰 설정에 계좌를 적고 켰을 때
나타난다. 접수는 지금처럼 재고를 차감하며 주문 상태는 **주문 접수(결제 대기)** 다.

| 수단 | 흐름 |
|---|---|
| 카드 | 접수 → `/shop/pay`가 이니시스 결제창을 연다 → 이니시스가 `/shop/pay/callback`을 부른다 → 승인·조회 → **결제 완료** |
| 무통장입금 | 접수 → 주문 화면이 계좌·입금자명·기한을 안내 → 관리자가 주문 상세에서 **입금 확인** → **결제 완료** |

주문 상태 흐름: 주문 접수 → 결제 완료 → 상품 준비 → 배송 중 → 배송 완료, 그리고 취소. 결제
완료는 결제 확인으로만 들어가며 상태 변경 셀렉트에는 없다. 고객은 결제 전에만 스스로 취소한다.
결제된 카드 주문을 취소하려면 관리자가 먼저 **환불**(부분 가능, 결제사 요청 뒤 기록)해야 하고,
무통장은 계좌로 돌려준 뒤 기록만 한다. 환불 폼의 "전액 환불이면 주문도 취소"가 켜져 있으면
전액 환불과 함께 취소되고 재고가 돌아간다.

결제 기한(쇼핑몰 설정, 카드 1시간·무통장 72시간 기본)이 지난 미결제 주문은 관리자 주문 화면과
주문서(접수 직전)를 열 때 자동으로 취소되고 재고가 돌아간다. cron 은 없다. 이니시스 승인이
진행 중이거나 끝난 주문은 만료로 취소하지 않는다 — 관리자의 **결제 조회**가 원장과 주문을
맞춘 뒤 처리한다.

결제창은 사이트 주소(`app.url`)가 공개 HTTPS 여야 열린다. 콜백 주소에는 주문의 결제 원장 키와
그 주문·결제사·설정 판의 HMAC 이 실려 있어, 다른 주문의 콜백으로는 승인되지 않는다. 카드번호와
PG 응답 원문은 저장하지 않으며 주문에는 거래번호와 표시용 정보만 남는다.
```

- [ ] **Step 6: 전체 스위트와 커밋**

Run: `vendor/bin/phpunit --no-coverage` (전체, 한 번)
Expected: OK. 실패하면 고친 뒤 다시 한 번만 돌린다.

```bash
git add src/Payment/DirectGateway.php modules/youngcart/src/Commerce/Payments.php tests/Payment/GatewayTest.php tests/YoungCart/PaymentsTest.php docs/youngcart.md
git commit -m "feat: expire overdue unpaid orders without touching approvals in flight

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

## 자체 검토 (계획 작성자가 확인함)

- **스펙 대조.** §2 이식 → 작업 1. §3 칸·상태·`payment_detail`·`pay_by`·3판 → 작업 2·4. §4 주문서·카드 흐름·무통장·기한 → 작업 3·5·6·9. §5 관리자 → 작업 7·8. §7 오류 경계(원장 잠금, 환경 판, 금액 검증) → 결제 계층 그대로, 작업 6 실패 테스트. §8 테스트 항목 → 각 작업. §9 반품·교환 대비 → 작업 4의 부분 환불·환불 누계·상수 상태 표. §10 문서 → 작업 1·9. 간편결제·계좌이체·가상계좌(§6)는 3.2·3.3 이라 여기 없다.
- **자리표시자 없음.** 모든 코드 단계에 코드가 있다. 작업 7 의 `_refund_form.php` 한 줄 파일은 작업 8 이 채운다고 명시했다.
- **이름 일치.** `Payments::forPlacing/methods/checkout/callbackUrl/complete/sync/refund/inProgress/expireOverdue/gatewayOrder/isPgOrder`, `Orders::markPaid/confirmDeposit/recordRefund/byPaymentId/expire`, `DirectGateway::approvalState`, 폼 이름 `payment_method/depositor/action/amount/reason/refund_key/cancel_order`, 쿼리 `order/state/pay`, 설정 키 `payment.environment/manual/deadline_hours` — 작업 간 동일하다.
