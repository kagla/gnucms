# 영카트 모듈 1단계(기반과 카탈로그) Implementation Plan

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `modules/youngcart` 확장 모듈을 새로 만들어 관리자가 분류·상품·옵션·이미지·재고를 관리하고 방문자가 `/shop`에서 메인·분류 목록·유형별 목록·검색·상세를 볼 수 있게 한다.

**Architecture:** GNUCMS 확장 런타임(`src/Extension`) 위의 모듈 하나. `PackageSchema`로 `yc_` 테이블 9개를 설치하고, 서비스 클래스(`Catalog\Categories`·`Products`·`Options`·`Listing`)가 DB를 다루며, 얇은 컨트롤러가 `View::forExtension()`으로 템플릿을 그린다. 코어는 설명 파일의 `aliases: false` 한 필드만 추가한다.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim 4, PDO(SQLite·MySQL), PHPUnit 10, 빌드 없는 정적 CSS/JS, CKEditor4(코어 에디터 조각 재사용).

**Spec:** `docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md`

## Global Constraints

- PHP 8.2 이상 지원, PHP 8.4 권장, 새 Composer 의존성 금지, 운영 서버 빌드 금지(정적 자산은 그대로 배포).
- SQLite와 MySQL/MariaDB에서 같은 의미로 동작. DB 테스트는 `connectionProvider`를 쓴다.
- 테이블 접두사 `yc_`, 패키지 키 `modules/youngcart`, 네임스페이스 `GnuCms\Modules\YoungCart\`.
- 사용자 주소 `/shop`, 관리자 주소 `/admin/shop`. `/modules/youngcart/…` 별칭 없음.
- `modules/shop`(작은 쇼핑몰)은 수정하지 않는다. 영카트5 코드·SQL·스킨을 복사하지 않는다(클린룸).
- 모든 입력은 서버에서 검증하고 HTML 필드는 `$app->htmlSanitizer()->clean()`을 거친다. 출력은 템플릿의 `$this->e()`.
- 관리자 라우트는 `Context::route(..., admin: true)`로 등록해 런타임의 전역 관리자·CSRF 검사를 쓴다.
- 커밋 메시지는 `feat:`·`fix:`·`docs:`·`test:` 형식, 한 커밋 한 논리 변경. 완료 전 `./vendor/bin/phpunit` 전체 통과.
- 이 계획은 worktree `/home/kagla/gnucms/.claude/worktrees/youngcart`(브랜치 `feat/youngcart-module`)에서 실행한다. 모든 명령은 그 디렉터리에서 실행한다.

## 파일 구조

| 경로 | 책임 |
|---|---|
| `src/Extension/Catalog.php`, `Manager.php`, `Context.php` | `aliases` 필드 파싱·전달·별칭 경로 생략 |
| `modules/youngcart/extension.json`, `bootstrap.php`, `autoload.php`, `README.md` | 패키지 선언, 라우트 등록, 하위 네임스페이스 오토로드 |
| `modules/youngcart/src/Service.php` | Store·Settings·Images·Catalog 서비스 조립, 설치·준비 상태 |
| `modules/youngcart/src/Schema.php` | 1판 DDL·인덱스·멱등 설치 |
| `modules/youngcart/src/Store.php` | 접두사 테이블명, 정수 PK CRUD, 트랜잭션, 재고 원장 |
| `modules/youngcart/src/Settings.php` | 설정 기본값·검증·저장 |
| `modules/youngcart/src/Input.php` | 문자열·정수·코드·불리언·HTML 검증 도우미 |
| `modules/youngcart/src/ProductInfo.php` | 상품정보고시 35개 군 정적 데이터 |
| `modules/youngcart/src/Images.php` | 상품 이미지 저장·삭제·복사·크기별 응답 |
| `modules/youngcart/src/Catalog/Categories.php` | 분류 코드 제안, 저장, 트리, 삭제 보호, 하위 적용, 일괄 편집 |
| `modules/youngcart/src/Catalog/Options.php` | 조합 생성, 옵션 검증·upsert, 품절 판정, 페이지용 JSON, 옵션 재고 목록 |
| `modules/youngcart/src/Catalog/Pricing.php` | 표시 가격·포인트 계산 |
| `modules/youngcart/src/Catalog/Products.php` | 상품 저장 트랜잭션, 조회, 복사, 삭제, 일괄 편집, 유형·재고 편집, 분류/전체 적용 |
| `modules/youngcart/src/Catalog/Listing.php` | 분류·유형·검색·메인 블록 조회, 정렬·페이징, 이전·다음, 관련상품 |
| `modules/youngcart/src/Web/ShopController.php` | 공개 화면 |
| `modules/youngcart/src/Admin/AdminController.php` | 현황·설치·설정 |
| `modules/youngcart/src/Admin/CategoryController.php` | 분류 관리 |
| `modules/youngcart/src/Admin/ProductController.php` | 상품 목록·폼·복사·유형·재고·검색 JSON |
| `modules/youngcart/templates/*.php`, `templates/admin/*.php` | 화면 |
| `www/themes/default/youngcart.css`, `youngcart.js`, `youngcart-admin.js` | 정적 자산 |
| `tests/YoungCart/*Test.php`, `tests/Web/YoungCartPublicTest.php`, `tests/Web/YoungCartAdminTest.php` | 테스트 |
| `docs/youngcart.md`, `docs/extensions.md`, `AGENTS.md` | 문서 |

공통 테스트 준비 코드는 Task 3에서 만드는 `tests/YoungCart/YoungCartTestCase.php`에 두고 이후 테스트가 상속한다.

---

### Task 1: 코어 `aliases` 필드

**Files:**
- Modify: `src/Extension/Catalog.php:37-38`, `src/Extension/Catalog.php:80-85`
- Modify: `src/Extension/Manager.php:137`, `src/Extension/Manager.php:190`
- Modify: `src/Extension/Context.php:22-46`, `src/Extension/Context.php:62-79`
- Modify: `docs/extensions.md` (설명 파일 절)
- Modify: `docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md` (3.2절)
- Test: `tests/Extension/ManagerTest.php`

**Interfaces:**
- Produces: 패키지 배열 키 `aliases`(bool, 기본 true). `Context::__construct(App $app, string $key, array $availableServices, ?string $routePrefix = null, ?string $adminRoutePrefix = null, bool $aliases = true)`. `aliases`가 false면 공개 라우트는 `route_prefix` 아래에만, 관리자 라우트는 `admin_route_prefix` 아래에만 등록된다.

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/Extension/ManagerTest.php`의 클래스 끝(마지막 `}` 앞)에 추가:

```php
    public function testAliasesOffRegistersOnlyPrefixedPaths(): void
    {
        $this->package('modules/store', ['route_prefix' => '/store', 'admin_route_prefix' => '/admin/store', 'aliases' => false], <<<'PHP'
<?php
return static function ($context): void {
    $context->route('GET', '/', static function ($request, $response) { $response->getBody()->write('home'); return $response; });
    $context->route('GET', '/products', static fn ($request, $response) => $response, admin: true);
};
PHP);
        $this->manager->setEnabled('modules/store', true);
        $slim = AppFactory::create();
        $this->manager->boot(new App([]), $slim);
        $patterns = array_map(static fn ($route) => $route->getPattern(), $slim->getRouteCollector()->getRoutes());
        sort($patterns);
        self::assertSame(['/admin/store/products', '/store', '/store/'], $patterns);
        $factory = new ServerRequestFactory();
        self::assertSame('home', (string) $slim->handle($factory->createServerRequest('GET', '/store'))->getBody());
        self::assertSame(404, $slim->handle($factory->createServerRequest('GET', '/modules/store/'))->getStatusCode());
    }

    public function testAliasesRequiresRoutePrefixAndBoolean(): void
    {
        $this->package('modules/store', ['aliases' => false]);
        self::assertStringContainsString('aliases', (string) $this->manager->packages()['modules/store']['error']);
        $this->package('modules/store', ['route_prefix' => '/store', 'aliases' => 'no']);
        self::assertStringContainsString('aliases', (string) $this->manager->packages()['modules/store']['error']);
        $this->package('modules/store', ['route_prefix' => '/store']);
        self::assertNull($this->manager->packages()['modules/store']['error']);
        self::assertTrue($this->manager->packages()['modules/store']['aliases']);
    }
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Extension/ManagerTest.php --filter Aliases`
Expected: FAIL — 첫 테스트는 패턴에 `/modules/store/`·`/store/products`가 섞여 있고, 둘째는 `error`가 null이며 `aliases` 키가 없다.

- [ ] **Step 3: Catalog에 필드 파싱 추가**

`src/Extension/Catalog.php` 37행의 기본 배열에 `'aliases' => true,`를 추가한다:

```php
                    'entry_path' => null, 'public_path' => null, 'route_prefix' => null, 'admin_route_prefix' => null, 'admin_test' => false,
                    'aliases' => true,
```

`$package['admin_route_prefix'] = $adminPrefix;` 바로 뒤(85행)에 추가:

```php
                    $aliases = $manifest['aliases'] ?? true;
                    if (!is_bool($aliases) || (!$aliases && $prefix === null)) {
                        throw new RuntimeException('aliases 는 true/false 이며, false 는 route_prefix 가 있을 때만 지정할 수 있습니다.');
                    }
                    $package['aliases'] = $aliases;
```

- [ ] **Step 4: Context에 별칭 생략 구현**

`src/Extension/Context.php` 생성자 시그니처를 바꾼다:

```php
    public function __construct(
        public readonly App $app,
        public readonly string $key,
        private array $availableServices,
        ?string $routePrefix = null,
        public readonly ?string $adminRoutePrefix = null,
        public readonly bool $aliases = true
    ) {
```

`paths()`를 교체:

```php
    private function paths(string $path): array
    {
        $paths = [$this->path($path)];
        if ($this->aliases) $paths[] = '/' . $this->key . $path;
        if ($path === '/') $paths[] = $this->routePrefix . '/';
        return array_values(array_unique($paths));
    }
```

`route()` 안의 관리자 분기를 교체:

```php
        $paths = $this->paths($legacyPath ?? $path);
        if ($admin && $this->adminRoutePrefix !== null) {
            if (!$this->aliases) $paths = [];
            array_unshift($paths, RoutePrefix::path($this->adminRoutePrefix, $path));
            if ($path === '/') $paths[] = $this->adminRoutePrefix . '/';
        }
```

- [ ] **Step 5: Manager가 값을 넘기도록 수정**

`src/Extension/Manager.php`의 두 `new Context(...)` 호출(137행, 190행)을 모두 다음처럼 바꾼다:

```php
                $context = new Context($app, $key, $services, $package['route_prefix'], $package['admin_route_prefix'], $package['aliases']);
```

```php
            $context = new Context($app, $key, $this->services, $package['route_prefix'], $package['admin_route_prefix'], $package['aliases']);
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Extension/ManagerTest.php tests/Web/ExtensionAdminPageTest.php tests/Web/DemoExtensionsTest.php tests/Web/ShopTest.php`
Expected: PASS (기존 패키지는 `aliases` 기본 true라 동작이 같다)

- [ ] **Step 7: 문서 갱신**

`docs/extensions.md`의 "패키지 설명 파일" 절, `admin_route_prefix` 항목 뒤에 추가:

```markdown
- 선택 필드 `aliases`의 기본값은 `true`다. `false`로 두면 `/{종류}/{id}/...` 별칭 주소를 만들지 않아 공개 라우트는 `route_prefix` 아래에만, 관리자 라우트(`admin: true`)는 `admin_route_prefix` 아래에만 등록된다. `route_prefix`가 없는 패키지는 `false`를 지정할 수 없다. 이전 주소를 보존할 필요가 없는 새 패키지에 쓴다.
```

스펙 `docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md` 3.2절의 세 번째 항목을 다음으로 교체:

```markdown
- `Context::paths()`는 `aliases`가 `false`면 `'/' . key . path` 항목을 만들지 않는다. 같은 조건에서 `route()`의
  관리자 라우트(`admin: true`)는 `admin_route_prefix` 아래에만 등록하고 `route_prefix` 아래의 사본을 만들지 않는다.
  `route_prefix`가 없는 패키지는 `aliases: false`를 선언할 수 없다(설명 파일 오류).
```

- [ ] **Step 8: 커밋**

```bash
git add src/Extension/Catalog.php src/Extension/Context.php src/Extension/Manager.php tests/Extension/ManagerTest.php docs/extensions.md docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md
git commit -m "feat: let extensions opt out of legacy alias paths with aliases: false"
```

---

### Task 2: 패키지 골격과 준비 중 화면

**Files:**
- Create: `modules/youngcart/extension.json`, `modules/youngcart/autoload.php`, `modules/youngcart/bootstrap.php`, `modules/youngcart/README.md`
- Create: `modules/youngcart/src/Service.php`(임시 최소본, Task 3에서 완성), `modules/youngcart/templates/notready.php`
- Test: `tests/Web/YoungCartPublicTest.php`

**Interfaces:**
- Produces: `GnuCms\Modules\YoungCart\Service::__construct(App $app)`, `Service::ready(): bool`. 오토로더는 `GnuCms\Modules\YoungCart\Sub\Name` → `src/Sub/Name.php`.

- [ ] **Step 1: 실패하는 웹 테스트 작성**

`tests/Web/YoungCartPublicTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/modules/youngcart/autoload.php';

final class YoungCartPublicTest extends WebTestCase
{
    private App $app;
    private string $root;

    private function setupModule(array $config): void
    {
        session_name(GNUCMS_ID . '_session');
        session_start(); $_SESSION = []; session_write_close();
        $this->root = sys_get_temp_dir() . '/gnucms-yc-web-' . bin2hex(random_bytes(8));
        $config['prefix'] = 'yw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'uploads' => ['dir' => $this->root . '/uploads'],
            'app' => ['url' => 'https://shop.example.test']]);
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabledMany(['modules/youngcart' => true]);
    }

    #[DataProvider('connectionProvider')]
    public function testNotInstalledShowsPreparingPageAndNoAliasPaths(array $config): void
    {
        $this->setupModule($config);
        $response = $this->get($this->app, '/shop');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($response));
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($this->get($this->app, '/shop/list', ['ca' => '10'])));
        self::assertSame(404, $this->get($this->app, '/modules/youngcart')->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/modules/youngcart/')->getStatusCode());
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop'), '/admin/shop');
        self::assertSame(404, $this->get($this->app, '/shop/admin')->getStatusCode());
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartPublicTest.php`
Expected: FAIL — `require_once`가 `modules/youngcart/autoload.php`를 찾지 못한다.

- [ ] **Step 3: 설명 파일·오토로더·README 작성**

`modules/youngcart/extension.json`:

```json
{
  "id": "youngcart",
  "type": "module",
  "name": "쇼핑몰",
  "description": "영카트5의 기능을 GNUCMS 방식으로 다시 만든 쇼핑몰입니다. 1단계는 분류·상품·옵션·목록·검색·상세를 제공합니다.",
  "version": "0.1.0",
  "api": 2,
  "aliases": false,
  "entry_path": "/",
  "public_path": "/",
  "route_prefix": "/shop",
  "admin_route_prefix": "/admin/shop",
  "admin_test": false,
  "requires": [],
  "optional": []
}
```

`modules/youngcart/autoload.php`:

```php
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'GnuCms\\Modules\\YoungCart\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*(\\\\[A-Za-z][A-Za-z0-9]*)*$/D', $relative)) return;
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require_once $file;
});
```

`modules/youngcart/README.md`:

```markdown
# 쇼핑몰 (영카트 모듈)

영카트5의 기능을 GNUCMS 확장 모듈로 다시 만든 쇼핑몰이다. 사용자 주소 `/shop`, 관리자 주소 `/admin/shop`.

- 1단계: 분류·상품·옵션·이미지·재고 관리, 메인·분류 목록·유형별 목록·검색·상세.
- 설치: 관리자 → 모듈에서 활성화한 뒤 `/admin/shop`에서 **데이터 설치/갱신**을 실행한다.
- 설계 문서: `docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md`, 운영 안내: `docs/youngcart.md`.
- 패키지 폴더는 작은 쇼핑몰이 `modules/shop`을 쓰는 동안 `modules/youngcart`를 유지한다.
```

- [ ] **Step 4: 최소 Service와 준비 중 템플릿, bootstrap 작성**

`modules/youngcart/src/Service.php`(Task 3에서 확장):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\App;
use GnuCms\Extension\PackageSchema;

final class Service
{
    public function __construct(public readonly App $app) {}

    public function ready(): bool
    {
        return $this->schema()->current(Schema::KEY, Schema::VERSION);
    }

    public function schema(): PackageSchema
    {
        return new PackageSchema($this->app->db(), $this->app->storageDir());
    }
}
```

`modules/youngcart/src/Schema.php`(Task 3에서 DDL을 채운다):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

final class Schema
{
    public const KEY = 'modules/youngcart';
    public const VERSION = 1;
}
```

`modules/youngcart/templates/notready.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?>쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><?php $this->stop() ?>
<?php $this->start('body') ?>
<section class="card"><div class="card-body">
  <h1 class="card-title">쇼핑몰을 준비 중입니다</h1>
  <p class="muted">상품이 등록되면 이곳에서 볼 수 있습니다.</p>
  <?php if ($admin): ?><p><a class="btn btn-sm" href="<?= $this->e($admin_url) ?>">쇼핑몰 데이터 설치</a></p><?php endif ?>
</div></section>
<?php $this->stop() ?>
```

`modules/youngcart/bootstrap.php`:

```php
<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\View\View;
use Slim\Routing\RouteContext;

require_once __DIR__ . '/autoload.php';

return static function (Context $context): void {
    $service = new Service($context->app);
    $notReady = static function ($request, $response) use ($context, $service) {
        $base = RouteContext::fromRequest($request)->getBasePath();
        $view = View::forExtension($request, 'youngcart', __DIR__ . '/templates');
        return $view->render($response, 'notready', [
            'admin' => $service->app->guestAcl()->identity()->isAdmin(),
            'admin_url' => $base . ($context->adminRoutePrefix ?? '/admin/shop'),
        ]);
    };
    foreach (['/', '/list', '/type', '/search', '/item', '/image'] as $path) {
        $context->route('GET', $path, $notReady);
    }
    $context->route('GET', '/', static fn ($request, $response) => $response, admin: true);
};
```

주의: 공개 `/`와 관리자 `/`는 다른 주소(`/shop`, `/admin/shop`)에 등록되므로 중복이 아니다.

- [ ] **Step 5: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartPublicTest.php`
Expected: PASS

- [ ] **Step 6: 커밋**

```bash
git add modules/youngcart tests/Web/YoungCartPublicTest.php
git commit -m "feat: scaffold the youngcart shop module at /shop"
```

---

### Task 3: 스키마 1판, Store, 설치

**Files:**
- Modify: `modules/youngcart/src/Schema.php`, `modules/youngcart/src/Service.php`
- Create: `modules/youngcart/src/Store.php`
- Create: `tests/YoungCart/YoungCartTestCase.php`, `tests/YoungCart/SchemaTest.php`

**Interfaces:**
- Produces: `Schema::TABLES` (9개), `Schema::install(PackageSchema $schema): void`, `Service::install(): void`, `Service::requireReady(): void`, `Service::$store`.
- `Store`: `__construct(Connection $db)`, `table(string $logical): string`, `insert(string $table, array $row): int`, `find(string $table, int $id): ?array`, `get(string $table, int $id): array`(없으면 `DomainError::notFound`), `update(string $table, int $id, array $data): int`, `delete(string $table, string $where, array $params = []): int`, `select(string $sql, array $params = []): array`, `selectOne(string $sql, array $params = []): ?array`, `execute(string $sql, array $params = []): int`, `transaction(callable $fn): mixed`, `logStock(int $productId, ?int $optionId, int $delta, string $kind, string $reference, string $actor): void`.

- [ ] **Step 1: 공통 테스트 베이스와 실패하는 스키마 테스트 작성**

`tests/YoungCart/YoungCartTestCase.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\App;
use GnuCms\Db\Schema as CoreSchema;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Tests\Support\DatabaseTestCase;

require_once dirname(__DIR__, 2) . '/modules/youngcart/autoload.php';

abstract class YoungCartTestCase extends DatabaseTestCase
{
    protected App $app;
    protected Service $shop;
    protected string $root;

    protected function setupShop(array $config, bool $install = true): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-yc-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        if ($config['dsn'] === 'sqlite::memory:') $config['dsn'] = 'sqlite:' . $this->root . '/yc.sqlite';
        $config['prefix'] = 'yc' . bin2hex(random_bytes(3)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'uploads' => ['dir' => $this->root . '/uploads'],
            'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new CoreSchema($this->app->db()))->create();
        $this->shop = new Service($this->app);
        if ($install) $this->shop->install();
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && $this->app->db()->dialect()->name() === 'mysql') {
            foreach (\GnuCms\Modules\YoungCart\Schema::TABLES as $table) {
                $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            }
            $this->app->db()->execute('DELETE FROM ' . $this->app->db()->table('extension_schemas'));
        }
        if (isset($this->root) && is_dir($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
    }

    /** 최소 필드로 분류 하나를 만든다. */
    protected function category(string $name = '의류', ?string $parent = null, array $extra = []): array
    {
        $code = $this->shop->categories->suggestCode($parent);
        $id = $this->shop->categories->save($extra + ['code' => $code, 'name' => $name, 'active' => '1',
            'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']);
        return $this->shop->categories->get($id);
    }

    /** 최소 필드로 상품 하나를 만든다. */
    protected function product(array $overrides = []): array
    {
        $category = $overrides['category_id'] ?? $this->category()['id'];
        $input = $overrides + ['code' => 'P' . bin2hex(random_bytes(4)), 'name' => '기본 상품', 'category_id' => (string) $category,
            'price' => '10000', 'stock' => '5', 'active' => '1', 'summary' => '', 'description' => ''];
        $id = $this->shop->products->save($input, []);
        return $this->shop->products->get($id);
    }
}
```

`tests/YoungCart/SchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Modules\YoungCart\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

final class SchemaTest extends YoungCartTestCase
{
    #[DataProvider('connectionProvider')]
    public function testInstallIsIdempotentAndRegistersTables(array $config): void
    {
        $this->setupShop($config, false);
        self::assertFalse($this->shop->ready());
        $this->shop->install();
        self::assertTrue($this->shop->ready());
        $this->shop->install();
        self::assertTrue($this->shop->ready());
        $schema = $this->shop->schema();
        foreach (Schema::TABLES as $table) self::assertTrue($schema->exists($table), $table);
        self::assertSame(9, count(Schema::TABLES));
        self::assertSame([], array_diff(Schema::TABLES, $schema->backupTables()));
        $status = $schema->status(Schema::KEY);
        self::assertSame('ready', $status['state']);
        self::assertSame(1, (int) $status['schema_version']);
        $id = $this->shop->store->insert('yc_categories', ['code' => '10', 'parent_id' => null, 'depth' => 1, 'name' => '의류', 'sort_order' => 0,
            'active' => 1, 'no_coupon' => 0, 'head_html' => '', 'tail_html' => '', 'list_columns' => 3, 'list_rows' => 5,
            'image_width' => 200, 'image_height' => 0, 'extra' => '[]', 'created_at' => 1, 'updated_at' => 1]);
        self::assertSame('의류', $this->shop->store->get('yc_categories', $id)['name']);
        self::assertNull($this->shop->store->find('yc_categories', $id + 1));
        $this->shop->store->logStock(1, null, -2, 'admin', 'test', 'tester');
        self::assertSame(-2, (int) $this->shop->store->selectOne('SELECT delta FROM ' . $this->shop->store->table('yc_stock_log'))['delta']);
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/SchemaTest.php`
Expected: FAIL — `Service::install()`이 없다.

- [ ] **Step 3: Store 작성**

`modules/youngcart/src/Store.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;

final class Store
{
    public function __construct(public readonly Connection $db) {}

    public function table(string $logical): string { return $this->db->table($logical); }

    public function insert(string $table, array $row): int { return (int) $this->db->insert($table, $row); }

    public function find(string $table, int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM ' . $this->db->table($table) . ' WHERE id = ?', [$id]);
    }

    public function get(string $table, int $id): array
    {
        return $this->find($table, $id) ?? throw DomainError::notFound('항목을 찾을 수 없습니다.');
    }

    public function update(string $table, int $id, array $data): int
    {
        return $this->db->update($table, $data, 'id = :id', ['id' => $id]);
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->db->delete($table, $where, $params);
    }

    public function select(string $sql, array $params = []): array { return $this->db->select($sql, $params); }
    public function selectOne(string $sql, array $params = []): ?array { return $this->db->selectOne($sql, $params); }
    public function execute(string $sql, array $params = []): int { return $this->db->execute($sql, $params); }
    public function transaction(callable $fn): mixed { return $this->db->transaction($fn); }

    public function logStock(int $productId, ?int $optionId, int $delta, string $kind, string $reference, string $actor): void
    {
        $this->insert('yc_stock_log', ['product_id' => $productId, 'option_id' => $optionId, 'delta' => $delta, 'kind' => $kind,
            'reference' => mb_substr($reference, 0, 100), 'actor' => mb_substr($actor, 0, 100), 'created_at' => Clock::timestamp()]);
    }
}
```

- [ ] **Step 4: Schema DDL 작성**

`modules/youngcart/src/Schema.php` 전체를 교체:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\Db\Connection;
use GnuCms\Extension\PackageSchema;

final class Schema
{
    public const KEY = 'modules/youngcart';
    public const VERSION = 1;
    public const TABLES = ['yc_settings', 'yc_categories', 'yc_products', 'yc_product_categories', 'yc_product_images',
        'yc_option_groups', 'yc_options', 'yc_product_relations', 'yc_stock_log'];

    public static function install(PackageSchema $schema): void
    {
        $schema->install(self::KEY, self::VERSION, self::TABLES, static function (Connection $db): void {
            $bin = $db->dialect()->name() === 'mysql' ? ' COLLATE utf8mb4_bin' : '';
            $definitions = [
                'yc_settings' => 'id VARCHAR(32) PRIMARY KEY, payload {TEXT} NOT NULL',
                'yc_categories' => 'id {AUTO_PK}, code VARCHAR(10) NOT NULL UNIQUE, parent_id BIGINT NULL, depth SMALLINT NOT NULL,
                    name VARCHAR(100) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, active SMALLINT NOT NULL DEFAULT 1,
                    no_coupon SMALLINT NOT NULL DEFAULT 0, head_html {TEXT} NOT NULL, tail_html {TEXT} NOT NULL,
                    list_columns SMALLINT NOT NULL, list_rows SMALLINT NOT NULL, image_width INTEGER NOT NULL, image_height INTEGER NOT NULL,
                    extra {TEXT} NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL',
                'yc_products' => 'id {AUTO_PK}, code VARCHAR(20) NOT NULL UNIQUE, slug VARCHAR(200) NOT NULL UNIQUE, category_id BIGINT NOT NULL,
                    name VARCHAR(250) NOT NULL, summary {TEXT} NOT NULL,
                    description {TEXT} NOT NULL, description_text {TEXT} NOT NULL, list_price BIGINT NOT NULL DEFAULT 0, price BIGINT NOT NULL,
                    point_type SMALLINT NOT NULL DEFAULT 0, point INTEGER NOT NULL DEFAULT 0, supply_point INTEGER NOT NULL DEFAULT 0,
                    tax_free SMALLINT NOT NULL DEFAULT 0, seller_email VARCHAR(191) NOT NULL DEFAULT \'\', active SMALLINT NOT NULL DEFAULT 1,
                    no_coupon SMALLINT NOT NULL DEFAULT 0, sold_out SMALLINT NOT NULL DEFAULT 0, stock INTEGER NOT NULL DEFAULT 0,
                    stock_alert INTEGER NOT NULL DEFAULT 0, buy_min INTEGER NOT NULL DEFAULT 0,
                    buy_max INTEGER NOT NULL DEFAULT 0, phone_inquiry SMALLINT NOT NULL DEFAULT 0, shipping_type SMALLINT NOT NULL DEFAULT 0,
                    shipping_method SMALLINT NOT NULL DEFAULT 0, shipping_fee BIGINT NOT NULL DEFAULT 0, shipping_free_minimum BIGINT NOT NULL DEFAULT 0,
                    shipping_per_qty INTEGER NOT NULL DEFAULT 0, head_html {TEXT} NOT NULL, tail_html {TEXT} NOT NULL,
                    info_group VARCHAR(50) NOT NULL DEFAULT \'\', info_values {TEXT} NOT NULL, memo {TEXT} NOT NULL, hit INTEGER NOT NULL DEFAULT 0,
                    sold_qty INTEGER NOT NULL DEFAULT 0, review_count INTEGER NOT NULL DEFAULT 0, review_avg DECIMAL(2,1) NOT NULL DEFAULT 0,
                    is_hit SMALLINT NOT NULL DEFAULT 0, is_recommended SMALLINT NOT NULL DEFAULT 0, is_new SMALLINT NOT NULL DEFAULT 0,
                    is_popular SMALLINT NOT NULL DEFAULT 0, is_discount SMALLINT NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0,
                    extra {TEXT} NOT NULL, version INTEGER NOT NULL DEFAULT 0, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL',
                'yc_product_categories' => 'product_id BIGINT NOT NULL, category_id BIGINT NOT NULL, slot SMALLINT NOT NULL,
                    PRIMARY KEY (product_id, slot), UNIQUE (product_id, category_id)',
                'yc_product_images' => 'id {AUTO_PK}, product_id BIGINT NOT NULL, filename VARCHAR(100) NOT NULL, sort_order SMALLINT NOT NULL DEFAULT 0',
                'yc_option_groups' => 'product_id BIGINT NOT NULL, kind VARCHAR(8) NOT NULL, position SMALLINT NOT NULL, name VARCHAR(100) NOT NULL,
                    PRIMARY KEY (product_id, kind, position)',
                'yc_options' => 'id {AUTO_PK}, product_id BIGINT NOT NULL, kind VARCHAR(8) NOT NULL,
                    value1 VARCHAR(100)' . $bin . ' NOT NULL DEFAULT \'\', value2 VARCHAR(100)' . $bin . ' NOT NULL DEFAULT \'\',
                    value3 VARCHAR(100)' . $bin . ' NOT NULL DEFAULT \'\', price BIGINT NOT NULL DEFAULT 0, stock INTEGER NOT NULL DEFAULT 0,
                    stock_alert INTEGER NOT NULL DEFAULT 0, active SMALLINT NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
                    UNIQUE (product_id, kind, value1, value2, value3)',
                'yc_product_relations' => 'product_id BIGINT NOT NULL, related_id BIGINT NOT NULL, sort_order SMALLINT NOT NULL DEFAULT 0,
                    PRIMARY KEY (product_id, related_id)',
                'yc_stock_log' => 'id {AUTO_PK}, product_id BIGINT NOT NULL, option_id BIGINT NULL, delta INTEGER NOT NULL, kind VARCHAR(20) NOT NULL,
                    reference VARCHAR(100) NOT NULL, actor VARCHAR(100) NOT NULL, created_at BIGINT NOT NULL',
            ];
            foreach ($definitions as $table => $definition) {
                $db->execute('CREATE TABLE IF NOT EXISTS ' . $db->table($table) . ' (' . strtr($definition, $db->dialect()->typeMap()) . ')' . $db->dialect()->tableSuffix());
            }
            $indexes = ['yc_cat_parent' => ['yc_categories', 'parent_id'], 'yc_cat_order' => ['yc_categories', 'sort_order'],
                'yc_prod_category' => ['yc_products', 'category_id'], 'yc_prod_name' => ['yc_products', 'name'],
                'yc_prod_order' => ['yc_products', 'sort_order'], 'yc_prod_updated' => ['yc_products', 'updated_at'],
                'yc_prod_price' => ['yc_products', 'price'], 'yc_pc_category' => ['yc_product_categories', 'category_id'],
                'yc_img_product' => ['yc_product_images', 'product_id'], 'yc_opt_product' => ['yc_options', 'product_id'],
                'yc_rel_related' => ['yc_product_relations', 'related_id'], 'yc_stock_product' => ['yc_stock_log', 'product_id']];
            foreach ($indexes as $index => [$table, $column]) {
                $physical = $db->prefix() . $index;
                $exists = match ($db->dialect()->name()) {
                    'sqlite' => $db->selectOne("SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?", [$physical]),
                    'mysql' => $db->selectOne('SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?', [$db->tableName($table), $physical]),
                };
                if ($exists === null) $db->execute('CREATE INDEX ' . $db->index($index) . ' ON ' . $db->table($table) . ' (' . $db->q($column) . ')');
            }
        });
    }
}
```

- [ ] **Step 5: Service 확장**

`modules/youngcart/src/Service.php` 전체 교체(이후 Task에서 `settings`·`images`·`categories`·`products`·`options`·`listing` 속성을 추가한다):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Extension\PackageSchema;

final class Service
{
    public readonly Store $store;

    public function __construct(public readonly App $app)
    {
        $this->store = new Store($app->db());
    }

    public function ready(): bool { return $this->schema()->current(Schema::KEY, Schema::VERSION); }
    public function install(): void { Schema::install($this->schema()); }
    public function schema(): PackageSchema { return new PackageSchema($this->app->db(), $this->app->storageDir()); }
    public function requireReady(): void { if (!$this->ready()) throw DomainError::serviceUnavailable('쇼핑몰 데이터를 먼저 설치해 주세요.'); }
}
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/SchemaTest.php tests/Web/YoungCartPublicTest.php`
Expected: PASS

- [ ] **Step 7: 커밋**

```bash
git add modules/youngcart/src tests/YoungCart
git commit -m "feat: add youngcart schema v1, store and installer"
```

---

### Task 4: 설정

**Files:**
- Create: `modules/youngcart/src/Settings.php`
- Modify: `modules/youngcart/src/Service.php` (`$settings` 속성)
- Test: `tests/YoungCart/SettingsTest.php`

**Interfaces:**
- Produces: `Settings::__construct(Store $store, HtmlSanitizer $sanitizer)`, `Settings::defaults(): array`, `Settings::all(): array`(저장값 + 기본값 병합), `Settings::save(array $input): array`(검증 후 저장, 저장된 배열 반환), `Settings::block(string $name): array`(`main.hit` 같은 블록의 `use,columns,rows,image_width,image_height`), `Settings::TYPES = ['hit','new','recommend','discount','popular']`, `Settings::TYPE_LABELS`.
- 폼 입력 키는 점 대신 밑줄: `main_hit_use`, `main_hit_columns`, …, `category_columns`, `type_rows`, `search_image_width`, `related_use`, `detail_image_width`, `show_tax`, `shipping_content`, `exchange_content`.

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/SettingsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends YoungCartTestCase
{
    #[DataProvider('connectionProvider')]
    public function testDefaultsValidationAndRoundTrip(array $config): void
    {
        $this->setupShop($config);
        $settings = $this->shop->settings->all();
        self::assertTrue($settings['main']['hit']['use']);
        self::assertFalse($settings['main']['popular']['use']);
        self::assertSame(['use' => true, 'columns' => 4, 'rows' => 1, 'image_width' => 200, 'image_height' => 0], $settings['main']['new']);
        self::assertSame(['columns' => 3, 'rows' => 5, 'image_width' => 200, 'image_height' => 0], $settings['category']);
        self::assertSame(400, $settings['detail']['image_width']);
        self::assertFalse($settings['show_tax']);
        $saved = $this->shop->settings->save(['main_hit_use' => '0', 'main_popular_use' => '1', 'main_popular_columns' => '2', 'main_popular_rows' => '2',
            'main_popular_image_width' => '300', 'main_popular_image_height' => '300', 'category_columns' => '4', 'category_rows' => '6',
            'category_image_width' => '250', 'category_image_height' => '0', 'type_columns' => '4', 'type_rows' => '5', 'type_image_width' => '200', 'type_image_height' => '0',
            'search_columns' => '4', 'search_rows' => '5', 'search_image_width' => '200', 'search_image_height' => '0',
            'related_use' => '1', 'related_columns' => '5', 'related_image_width' => '120', 'related_image_height' => '0',
            'detail_image_width' => '500', 'detail_image_height' => '0', 'show_tax' => '1',
            'shipping_content' => '<p>배송 안내</p><script>x</script>', 'exchange_content' => '']);
        self::assertFalse($saved['main']['hit']['use']);
        self::assertSame(['use' => true, 'columns' => 2, 'rows' => 2, 'image_width' => 300, 'image_height' => 300], $saved['main']['popular']);
        self::assertSame(4, $this->shop->settings->all()['category']['columns']);
        self::assertTrue($this->shop->settings->all()['show_tax']);
        self::assertSame('<p>배송 안내</p>', $this->shop->settings->all()['shipping']['content']);
        self::assertSame(['use' => true, 'columns' => 2, 'rows' => 2, 'image_width' => 300, 'image_height' => 300], $this->shop->settings->block('main.popular'));
        try {
            $this->shop->settings->save(['category_columns' => '13'] + $this->flat($saved));
            self::fail('열 수 13은 거절해야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertArrayHasKey('category_columns', $e->details());
        }
        self::assertSame(4, $this->shop->settings->all()['category']['columns']);
    }

    private function flat(array $settings): array
    {
        $flat = [];
        foreach (['hit', 'new', 'recommend', 'discount', 'popular'] as $type) {
            foreach ($settings['main'][$type] as $key => $value) $flat['main_' . $type . '_' . $key] = $key === 'use' ? ($value ? '1' : '0') : (string) $value;
        }
        foreach (['category', 'type', 'search', 'related', 'detail'] as $section) {
            foreach ($settings[$section] as $key => $value) $flat[$section . '_' . $key] = $key === 'use' ? ($value ? '1' : '0') : (string) $value;
        }
        $flat['show_tax'] = $settings['show_tax'] ? '1' : '0';
        $flat['shipping_content'] = $settings['shipping']['content'];
        $flat['exchange_content'] = $settings['exchange']['content'];
        return $flat;
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/SettingsTest.php`
Expected: FAIL — `Service::$settings`가 없다.

- [ ] **Step 3: Settings 작성**

`modules/youngcart/src/Settings.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;

final class Settings
{
    public const TYPES = ['hit', 'new', 'recommend', 'discount', 'popular'];
    public const TYPE_LABELS = ['hit' => '히트상품', 'new' => '최신상품', 'recommend' => '추천상품', 'discount' => '할인상품', 'popular' => '인기상품'];
    public const TYPE_COLUMNS = ['hit' => 'is_hit', 'new' => 'is_new', 'recommend' => 'is_recommended', 'discount' => 'is_discount', 'popular' => 'is_popular'];

    private ?array $cache = null;

    public function __construct(private Store $store, private HtmlSanitizer $sanitizer) {}

    public static function defaults(): array
    {
        $block = ['columns' => 4, 'rows' => 1, 'image_width' => 200, 'image_height' => 0];
        $main = [];
        foreach (self::TYPES as $type) $main[$type] = ['use' => $type !== 'popular'] + $block;
        return [
            'main' => $main,
            'category' => ['columns' => 3, 'rows' => 5, 'image_width' => 200, 'image_height' => 0],
            'type' => ['columns' => 4, 'rows' => 5, 'image_width' => 200, 'image_height' => 0],
            'search' => ['columns' => 4, 'rows' => 5, 'image_width' => 200, 'image_height' => 0],
            'related' => ['use' => true, 'columns' => 4, 'image_width' => 100, 'image_height' => 0],
            'detail' => ['image_width' => 400, 'image_height' => 0],
            'show_tax' => false,
            'shipping' => ['content' => ''],
            'exchange' => ['content' => ''],
        ];
    }

    public function all(): array
    {
        if ($this->cache !== null) return $this->cache;
        $row = $this->store->selectOne('SELECT payload FROM ' . $this->store->table('yc_settings') . " WHERE id = 'settings'");
        $saved = $row === null ? [] : json_decode((string) $row['payload'], true, 8, JSON_THROW_ON_ERROR);
        return $this->cache = array_replace_recursive(self::defaults(), is_array($saved) ? $saved : []);
    }

    public function block(string $name): array
    {
        $all = $this->all();
        [$section, $type] = array_pad(explode('.', $name, 2), 2, null);
        return $type === null ? $all[$section] : $all[$section][$type];
    }

    public function save(array $input): array
    {
        $errors = [];
        $int = static function (string $key, int $min, int $max) use ($input, &$errors): int {
            $value = $input[$key] ?? null;
            if (!is_string($value) && !is_int($value) || !preg_match('/^(0|[1-9][0-9]{0,6})$/D', (string) $value) || (int) $value < $min || (int) $value > $max) {
                $errors[$key] = $min . '~' . $max . ' 사이의 정수를 입력해 주세요.';
                return $min;
            }
            return (int) $value;
        };
        $bool = static fn (string $key): bool => ($input[$key] ?? '') === '1';
        $settings = ['main' => []];
        foreach (self::TYPES as $type) {
            $settings['main'][$type] = ['use' => $bool('main_' . $type . '_use'), 'columns' => $int('main_' . $type . '_columns', 1, 12),
                'rows' => $int('main_' . $type . '_rows', 1, 50), 'image_width' => $int('main_' . $type . '_image_width', 0, 2000),
                'image_height' => $int('main_' . $type . '_image_height', 0, 2000)];
        }
        foreach (['category', 'type', 'search'] as $section) {
            $settings[$section] = ['columns' => $int($section . '_columns', 1, 12), 'rows' => $int($section . '_rows', 1, 50),
                'image_width' => $int($section . '_image_width', 0, 2000), 'image_height' => $int($section . '_image_height', 0, 2000)];
        }
        $settings['related'] = ['use' => $bool('related_use'), 'columns' => $int('related_columns', 1, 12),
            'image_width' => $int('related_image_width', 0, 2000), 'image_height' => $int('related_image_height', 0, 2000)];
        $settings['detail'] = ['image_width' => $int('detail_image_width', 0, 2000), 'image_height' => $int('detail_image_height', 0, 2000)];
        $settings['show_tax'] = $bool('show_tax');
        foreach (['shipping', 'exchange'] as $key) {
            $content = $input[$key . '_content'] ?? '';
            if (!is_string($content) || strlen($content) > 60000) { $errors[$key . '_content'] = '내용이 너무 깁니다.'; $content = ''; }
            $settings[$key] = ['content' => $this->sanitizer->clean($content)];
        }
        if ($errors !== []) throw DomainError::validation($errors);
        $payload = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->store->transaction(function () use ($payload): void {
            if ($this->store->selectOne('SELECT id FROM ' . $this->store->table('yc_settings') . " WHERE id = 'settings'") === null) {
                $this->store->insert('yc_settings', ['id' => 'settings', 'payload' => $payload]);
            } else {
                $this->store->db->update('yc_settings', ['payload' => $payload], 'id = :id', ['id' => 'settings']);
            }
        });
        $this->cache = null;
        return $this->all();
    }
}
```

`Service::__construct`에 다음을 추가하고 `public readonly Settings $settings;` 속성을 선언한다:

```php
        $this->settings = new Settings($this->store, $app->htmlSanitizer());
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/SettingsTest.php`
Expected: PASS

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Settings.php modules/youngcart/src/Service.php tests/YoungCart/SettingsTest.php
git commit -m "feat: add youngcart settings with defaults and validation"
```

---

### Task 5: 입력 도우미, 상품정보고시, 가격 계산

**Files:**
- Create: `modules/youngcart/src/Input.php`, `modules/youngcart/src/ProductInfo.php`, `modules/youngcart/src/Catalog/Pricing.php`
- Test: `tests/YoungCart/HelpersTest.php`

**Interfaces:**
- `Input::text(mixed $v, string $field, int $max, bool $optional = true): string`(trim, 제어문자 거절, `DomainError::validation([$field => …])`)
- `Input::int(mixed $v, string $field, int $min, int $max, ?int $default = null): int`(빈값이면 default, default가 null이면 오류)
- `Input::bool(mixed $v): int`(`'1'`·`1`·`true`만 1)
- `Input::id(mixed $v, string $field = 'id'): int`(양의 정수, 아니면 `notFound`)
- `Input::optionalId(mixed $v): ?int`
- `Input::code(mixed $v, string $field, string $pattern, string $message): string`
- `Input::html(mixed $v, string $field, HtmlSanitizer $sanitizer, int $max = 60000): string`
- `Input::plain(string $html): string`(태그 제거·공백 정리)
- `Input::slug(string $name, string $fallback): string`
- `Input::extra(array $input): string`(`extra_label[1..10]`, `extra_value[1..10]` → JSON)
- `Input::csv(mixed $v, int $maxItems, int $maxLength, string $field): array`
- `ProductInfo::GROUPS: array<string, array{label:string, articles:list<string>}>`, `ProductInfo::labels(): array`, `ProductInfo::articles(string $group): array`, `ProductInfo::normalize(string $group, array $values): string`(JSON), `ProductInfo::decode(string $json): array`
- `Pricing::display(array $product): ?int`(전화문의면 null), `Pricing::point(array $product, int $optionDelta = 0): int`, `Pricing::format(int $amount): string`, `Pricing::pointLabel(array $product): string`

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/HelpersTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Catalog\Pricing;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\ProductInfo;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/modules/youngcart/autoload.php';

final class HelpersTest extends TestCase
{
    public function testInputHelpers(): void
    {
        self::assertSame('셔츠', Input::text(" 셔츠 ", 'name', 10));
        self::assertSame('', Input::text(null, 'memo', 10));
        try { Input::text('', 'name', 10, false); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('name', $e->details()); }
        try { Input::text("a\x00b", 'name', 10); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        try { Input::text(str_repeat('가', 11), 'name', 10); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        self::assertSame(7, Input::int('7', 'n', 0, 10));
        self::assertSame(3, Input::int('', 'n', 0, 10, 3));
        try { Input::int('11', 'n', 0, 10); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('n', $e->details()); }
        try { Input::int('-1', 'n', 0, 10); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('n', $e->details()); }
        self::assertSame(1, Input::bool('1')); self::assertSame(0, Input::bool('on')); self::assertSame(0, Input::bool(null));
        self::assertSame(12, Input::id('12'));
        try { Input::id('0'); self::fail(); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
        self::assertNull(Input::optionalId('')); self::assertSame(3, Input::optionalId('3'));
        self::assertSame('AB_1-x', Input::code('AB_1-x', 'code', '/^[A-Za-z0-9_-]{1,20}$/D', '코드 형식'));
        try { Input::code('a b', 'code', '/^[A-Za-z0-9_-]{1,20}$/D', '코드 형식'); self::fail(); } catch (DomainError $e) { self::assertSame('코드 형식', $e->details()['code']); }
        self::assertSame('<p>본문</p>', Input::html('<p>본문</p><script>1</script>', 'html', new HtmlSanitizer()));
        self::assertSame('제목 본문 끝', Input::plain("<h1>제목</h1>\n<p>본문   끝</p>"));
        self::assertSame('여름-셔츠-2', Input::slug('  여름 셔츠 / 2  ', 'P1'));
        self::assertSame('P1', Input::slug('///', 'P1'));
        $extra = json_decode(Input::extra(['extra_label' => [1 => '색상', 3 => '크기'], 'extra_value' => [1 => '빨강', 3 => 'L']]), true);
        self::assertCount(10, $extra);
        self::assertSame(['label' => '색상', 'value' => '빨강'], $extra[0]);
        self::assertSame(['label' => '', 'value' => ''], $extra[1]);
        self::assertSame(['빨강', '파랑'], Input::csv(' 빨강, 파랑 ,,빨강', 20, 100, 'values'));
        try { Input::csv(implode(',', range(1, 21)), 20, 100, 'values'); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('values', $e->details()); }
    }

    public function testProductInfoGroupsAndPricing(): void
    {
        self::assertCount(35, ProductInfo::GROUPS);
        self::assertSame('의류', ProductInfo::labels()['wear']);
        self::assertContains('제품 소재', ProductInfo::articles('wear'));
        $json = ProductInfo::normalize('wear', [0 => '면 100%', 99 => '무시']);
        $decoded = ProductInfo::decode($json);
        self::assertSame('면 100%', $decoded[0]);
        self::assertSame('상품페이지 참고', $decoded[1]);
        self::assertArrayNotHasKey(99, $decoded);
        self::assertSame('', ProductInfo::normalize('', []));
        try { ProductInfo::normalize('nope', []); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('info_group', $e->details()); }
        $product = ['price' => 12000, 'phone_inquiry' => 0, 'point_type' => 1, 'point' => 5];
        self::assertSame(12000, Pricing::display($product));
        self::assertNull(Pricing::display(['price' => 12000, 'phone_inquiry' => 1]));
        self::assertSame(600, Pricing::point($product));
        self::assertSame(600, Pricing::point(['price' => 12000, 'point_type' => 2, 'point' => 5], 0));
        self::assertSame(700, Pricing::point(['price' => 12000, 'point_type' => 2, 'point' => 5], 2000));
        self::assertSame(300, Pricing::point(['price' => 12000, 'point_type' => 0, 'point' => 300]));
        self::assertSame(0, Pricing::point(['price' => 12000, 'point_type' => 0, 'point' => -5]));
        self::assertSame('12,000원', Pricing::format(12000));
        self::assertSame('구매금액(추가옵션 제외)의 5%', Pricing::pointLabel(['price' => 12000, 'point_type' => 2, 'point' => 5]));
        self::assertSame('600점', Pricing::pointLabel($product));
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/HelpersTest.php`
Expected: FAIL — 클래스가 없다.

- [ ] **Step 3: Input 작성**

`modules/youngcart/src/Input.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;

final class Input
{
    public static function text(mixed $value, string $field, int $max, bool $optional = true): string
    {
        if ($value === null) $value = '';
        if (!is_string($value) && !is_int($value)) throw DomainError::validation([$field => '입력값을 확인해 주세요.']);
        $value = trim((string) $value);
        if (!$optional && $value === '') throw DomainError::validation([$field => '필수 항목입니다.']);
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value) || !preg_match('//u', $value)) {
            throw DomainError::validation([$field => '사용할 수 없는 문자가 있습니다.']);
        }
        if (mb_strlen($value, 'UTF-8') > $max) throw DomainError::validation([$field => $max . '자 이내로 입력해 주세요.']);
        return $value;
    }

    public static function int(mixed $value, string $field, int $min, int $max, ?int $default = null): int
    {
        if ($value === null || $value === '') {
            if ($default === null) throw DomainError::validation([$field => '숫자를 입력해 주세요.']);
            return $default;
        }
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^-?(0|[1-9][0-9]{0,11})$/D', (string) $value)
            || (int) $value < $min || (int) $value > $max) {
            throw DomainError::validation([$field => $min . '~' . $max . ' 범위의 정수를 입력해 주세요.']);
        }
        return (int) $value;
    }

    public static function bool(mixed $value): int
    {
        return $value === '1' || $value === 1 || $value === true ? 1 : 0;
    }

    public static function id(mixed $value, string $field = 'id'): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,15}$/D', (string) $value)) {
            throw DomainError::notFound('항목을 찾을 수 없습니다.');
        }
        return (int) $value;
    }

    public static function optionalId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : self::id($value);
    }

    public static function code(mixed $value, string $field, string $pattern, string $message): string
    {
        if (!is_string($value) || !preg_match($pattern, $value)) throw DomainError::validation([$field => $message]);
        return $value;
    }

    public static function html(mixed $value, string $field, HtmlSanitizer $sanitizer, int $max = 60000): string
    {
        if ($value === null) $value = '';
        if (!is_string($value) || strlen($value) > $max) throw DomainError::validation([$field => '내용이 너무 깁니다.']);
        return $sanitizer->clean($value);
    }

    public static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    public static function slug(string $name, string $fallback): string
    {
        $slug = preg_replace('~[\s/?#%"\'`<>\\\\]+~u', '-', trim($name)) ?? '';
        $slug = trim(preg_replace('/-{2,}/', '-', $slug) ?? '', '-');
        $slug = mb_substr($slug, 0, 190, 'UTF-8');
        return $slug === '' ? $fallback : $slug;
    }

    public static function extra(array $input): string
    {
        $labels = is_array($input['extra_label'] ?? null) ? $input['extra_label'] : [];
        $values = is_array($input['extra_value'] ?? null) ? $input['extra_value'] : [];
        $extra = [];
        for ($i = 1; $i <= 10; $i++) {
            $extra[] = ['label' => self::text($labels[$i] ?? '', 'extra_label', 100), 'value' => self::text($values[$i] ?? '', 'extra_value', 1000)];
        }
        return json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** 쉼표로 나눈 값. 공백 제거, 빈값·중복 제외, 순서 유지. */
    public static function csv(mixed $value, int $maxItems, int $maxLength, string $field): array
    {
        $items = [];
        foreach (explode(',', self::text($value, $field, $maxItems * ($maxLength + 1))) as $item) {
            $item = trim($item);
            if ($item === '' || in_array($item, $items, true)) continue;
            if (mb_strlen($item, 'UTF-8') > $maxLength || preg_match('/[<>"\']/', $item)) {
                throw DomainError::validation([$field => '값은 ' . $maxLength . '자 이내이며 <>"\' 문자를 쓸 수 없습니다.']);
            }
            $items[] = $item;
        }
        if (count($items) > $maxItems) throw DomainError::validation([$field => '값은 ' . $maxItems . '개까지 입력할 수 있습니다.']);
        return $items;
    }
}
```

- [ ] **Step 4: ProductInfo 작성**

`modules/youngcart/src/ProductInfo.php`(전자상거래 상품정보제공고시의 35개 품목군과 항목):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\Error\DomainError;

final class ProductInfo
{
    public const DEFAULT_VALUE = '상품페이지 참고';

    public const GROUPS = [
        'wear' => ['label' => '의류', 'articles' => ['제품 소재', '색상', '치수', '제조자/수입자', '제조국', '세탁방법 및 취급시 주의사항', '제조연월', '품질보증기준', 'A/S 책임자와 전화번호']],
        'shoes' => ['label' => '구두/신발', 'articles' => ['제품 주소재', '색상', '치수', '제조자/수입자', '제조국', '취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'bag' => ['label' => '가방', 'articles' => ['종류', '소재', '색상', '크기', '제조자/수입자', '제조국', '취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'fashion' => ['label' => '패션잡화(모자/벨트/액세서리)', 'articles' => ['종류', '소재', '치수', '제조자/수입자', '제조국', '취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'bedding' => ['label' => '침구류/커튼', 'articles' => ['제품 소재', '색상', '치수', '제품구성', '제조자/수입자', '제조국', '세탁방법 및 취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'furniture' => ['label' => '가구(침대/소파/싱크대/DIY제품)', 'articles' => ['품명', 'KC 인증 필 유무', '색상', '구성품', '주요 소재', '제조자/수입자', '제조국', '크기', '배송·설치비용', '품질보증기준', 'A/S 책임자와 전화번호']],
        'video' => ['label' => '영상가전(TV류)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력/에너지소비효율등급', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기', '화면사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'home_appliance' => ['label' => '가정용 전기제품(냉장고/세탁기/식기세척기/전자레인지)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력/에너지소비효율등급', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기', '품질보증기준', 'A/S 책임자와 전화번호']],
        'season' => ['label' => '계절가전(에어컨/온풍기)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력/에너지소비효율등급', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기', '냉난방면적', '추가설치비용', '품질보증기준', 'A/S 책임자와 전화번호']],
        'office' => ['label' => '사무용기기(컴퓨터/노트북/프린터)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력/에너지소비효율등급', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기·무게', '주요 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'optical' => ['label' => '광학기기(디지털카메라/캠코더)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기·무게', '주요 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'small_electronics' => ['label' => '소형전자(MP3/전자사전 등)', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기·무게', '주요 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'mobile' => ['label' => '휴대폰', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기·무게', '이동통신사', '가입절차', '소비자의 추가적인 부담사항', '주요 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'navigation' => ['label' => '네비게이션', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '정격전압/소비전력', '동일모델의 출시년월', '제조자/수입자', '제조국', '크기·무게', '주요 사양', '맵 업데이트 비용 및 무상기간', '품질보증기준', 'A/S 책임자와 전화번호']],
        'car' => ['label' => '자동차용품(자동차부품/기타 자동차용품)', 'articles' => ['품명 및 모델명', '동일모델의 출시년월', 'KC 인증 필 유무', '제조자/수입자', '제조국', '크기', '적용차종', '제품사용으로 인한 위험 및 유의사항', '검사합격증 번호', '품질보증기준', 'A/S 책임자와 전화번호']],
        'medical' => ['label' => '의료기기', 'articles' => ['품명 및 모델명', '의료기기법상 허가·신고 번호 및 광고사전심의필 유무', '정격전압/소비전력', '동일모델의 출시년월', '제조자/수입자', '제조국', '제품의 사용목적 및 사용방법', '취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'kitchen' => ['label' => '주방용품', 'articles' => ['품명 및 모델명', '재질', '구성품', '크기', '동일모델의 출시년월', '제조자/수입자', '제조국', '수입식품안전관리특별법에 따른 수입신고 여부', '품질보증기준', 'A/S 책임자와 전화번호']],
        'cosmetics' => ['label' => '화장품', 'articles' => ['용량 및 중량', '제품 주요 사양', '사용기한 또는 개봉 후 사용기간', '사용방법', '제조업자 및 제조판매업자', '제조국', '화장품법에 따라 기재해야 하는 모든 성분', '기능성 화장품 심사필 유무', '사용할 때 주의사항', '품질보증기준', '소비자상담관련 전화번호']],
        'jewelry' => ['label' => '귀금속/보석/시계류', 'articles' => ['소재/순도/밴드재질', '중량', '제조자/수입자', '제조국', '치수', '착용 시 주의사항', '주요 사양', '보증서 제공여부', '품질보증기준', 'A/S 책임자와 전화번호']],
        'food' => ['label' => '식품(농수산물)', 'articles' => ['포장단위별 용량·수량·크기', '생산자', '원산지', '제조연월일', '관련법상 표시사항', '상품구성', '보관방법 또는 취급방법', '소비자안전을 위한 주의사항', '소비자상담관련 전화번호']],
        'processed_food' => ['label' => '가공식품', 'articles' => ['식품의 유형', '생산자 및 소재지', '제조연월일', '포장단위별 내용물의 용량·수량', '원재료명 및 함량', '영양성분', '유전자변형식품에 해당하는 경우의 표시', '소비자안전을 위한 주의사항', '수입식품 문구', '소비자상담관련 전화번호']],
        'health_food' => ['label' => '건강기능식품', 'articles' => ['식품의 유형', '제조업소의 명칭과 소재지', '제조연월일', '포장단위별 내용물의 용량·수량', '원재료명 및 함량', '영양정보', '기능정보', '섭취량·섭취방법 및 섭취 시 주의사항', '질병의 예방 및 치료를 위한 의약품이 아니라는 문구', '유전자변형건강기능식품 표시', '표시광고 사전심의필', '수입식품 문구', '소비자상담관련 전화번호']],
        'kids' => ['label' => '영유아용품', 'articles' => ['품명 및 모델명', 'KC 인증 필 유무', '크기·중량', '색상', '재질', '사용연령 또는 체중범위', '동일모델의 출시년월', '제조자/수입자', '제조국', '취급방법 및 취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
        'instrument' => ['label' => '악기', 'articles' => ['품명 및 모델명', '크기', '색상', '재질', '제품 구성', '동일모델의 출시년월', '제조자/수입자', '제조국', '상품별 세부 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'sports' => ['label' => '스포츠용품', 'articles' => ['품명 및 모델명', '크기·중량', '색상', '재질', '제품 구성', '동일모델의 출시년월', '제조자/수입자', '제조국', '상품별 세부 사양', '품질보증기준', 'A/S 책임자와 전화번호']],
        'book' => ['label' => '서적', 'articles' => ['도서명', '저자/출판사', '크기', '쪽수', '제품 구성', '출간일', '목차 또는 책소개']],
        'hotel' => ['label' => '호텔/펜션 예약', 'articles' => ['국가 또는 지역명', '숙소형태', '등급/객실타입', '사용가능 인원/인원 추가 시 비용', '부대시설/제공 서비스', '취소 규정', '예약담당 연락처']],
        'travel' => ['label' => '여행패키지', 'articles' => ['여행사', '이용항공편', '여행기간 및 일정', '총 예정 인원/출발 확정 인원', '숙박정보', '여행상품 가격', '선택경비 유무', '취소 규정', '해외여행의 경우 외교부 여행경보단계', '예약담당 연락처']],
        'flight' => ['label' => '항공권', 'articles' => ['요금조건/왕복·편도 여부', '유효기간', '제한사항', '티켓수령방법', '좌석종류', '추가 마일리지', '취소 규정', '예약담당 연락처']],
        'rentcar' => ['label' => '렌터카', 'articles' => ['차종', '소유권 이전 조건', '추가 선택 시 비용', '차량 반환 시 연료 비용', '차량의 고장·훼손 시 소비자 책임', '예약 취소·중도 해약 시 환불 기준', '예약담당 연락처']],
        'rental_appliance' => ['label' => '물품대여(정수기/비데/안마의자)', 'articles' => ['품명 및 모델명', '소유권 이전 조건', '유지보수 조건', '상품의 고장·분실·훼손 시 소비자 책임', '중도 해약 시 환불 기준', '제품 사양', '소비자상담관련 전화번호']],
        'rental_goods' => ['label' => '물품대여(서적/유아용품/행사용품)', 'articles' => ['품명 및 모델명', '소유권 이전 조건', '상품의 고장·분실·훼손 시 소비자 책임', '중도 해약 시 환불 기준', '소비자상담관련 전화번호']],
        'digital' => ['label' => '디지털 콘텐츠(음원/게임/인터넷강의)', 'articles' => ['제작자 또는 공급자', '이용조건·이용기간', '상품 제공 방식', '최소 시스템 사양·필수 소프트웨어', '청약철회 또는 계약의 해제·해지에 따른 효과', '소비자상담관련 전화번호']],
        'voucher' => ['label' => '상품권/쿠폰', 'articles' => ['발행자', '유효기간·이용조건', '이용 가능 매장', '잔액 환급 조건', '소비자상담관련 전화번호']],
        'etc' => ['label' => '기타 재화', 'articles' => ['품명 및 모델명', '인증·허가 사항', '제조자/수입자', '제조국', '제품 사용 목적 및 방법', '취급시 주의사항', '품질보증기준', 'A/S 책임자와 전화번호']],
    ];

    public static function labels(): array
    {
        return array_map(static fn (array $group): string => $group['label'], self::GROUPS);
    }

    public static function articles(string $group): array
    {
        return self::GROUPS[$group]['articles'] ?? [];
    }

    /** 군의 항목 순서대로 값을 채운 JSON. 빈 값은 기본 문구로 채운다. 군이 비면 빈 문자열. */
    public static function normalize(string $group, array $values): string
    {
        if ($group === '') return '';
        if (!isset(self::GROUPS[$group])) throw DomainError::validation(['info_group' => '상품정보고시 군을 확인해 주세요.']);
        $result = [];
        foreach (self::GROUPS[$group]['articles'] as $index => $article) {
            $value = Input::text($values[$index] ?? '', 'info_' . $index, 500);
            $result[$index] = $value === '' ? self::DEFAULT_VALUE : $value;
        }
        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function decode(string $json): array
    {
        if ($json === '') return [];
        $values = json_decode($json, true);
        return is_array($values) ? $values : [];
    }
}
```

- [ ] **Step 5: Pricing 작성**

`modules/youngcart/src/Catalog/Pricing.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Catalog;

/** 표시 가격과 포인트를 한 곳에서 계산한다. 3단계의 등급 할인은 display()에 끼운다. */
final class Pricing
{
    public static function display(array $product): ?int
    {
        return (int) ($product['phone_inquiry'] ?? 0) === 1 ? null : (int) $product['price'];
    }

    /** 선택옵션 차액은 point_type 2에서만 더한다. 10점 단위로 내린다. */
    public static function point(array $product, int $optionDelta = 0): int
    {
        $type = (int) ($product['point_type'] ?? 0);
        $point = (int) ($product['point'] ?? 0);
        if ($type === 0) return max(0, $point);
        $base = (int) $product['price'] + ($type === 2 ? $optionDelta : 0);
        return max(0, (int) floor($base * $point / 100 / 10) * 10);
    }

    public static function format(int $amount): string
    {
        return number_format($amount) . '원';
    }

    public static function pointLabel(array $product): string
    {
        if ((int) ($product['point_type'] ?? 0) === 2) return '구매금액(추가옵션 제외)의 ' . (int) $product['point'] . '%';
        return number_format(self::point($product)) . '점';
    }
}
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/HelpersTest.php`
Expected: PASS

- [ ] **Step 7: 커밋**

```bash
git add modules/youngcart/src/Input.php modules/youngcart/src/ProductInfo.php modules/youngcart/src/Catalog/Pricing.php tests/YoungCart/HelpersTest.php
git commit -m "feat: add youngcart input helpers, product info groups and pricing"
```

---

### Task 6: 분류 서비스

**Files:**
- Create: `modules/youngcart/src/Catalog/Categories.php`
- Modify: `modules/youngcart/src/Service.php` (`$categories`)
- Test: `tests/YoungCart/CategoriesTest.php`

**Interfaces:**
- `Categories::__construct(Store $store, HtmlSanitizer $sanitizer, Settings $settings)`
- `suggestCode(?string $parentCode): ?string`
- `save(array $input, ?int $id = null): int` — 입력 키: `code`(생성 시), `name`, `sort_order`, `active`, `no_coupon`, `head_html`, `tail_html`, `list_columns`, `list_rows`, `image_width`, `image_height`, `extra_label[]`, `extra_value[]`, `apply_children`(수정 시 `'1'`)
- `get(int $id): array`(`extra` 디코드 포함), `byCode(string $code): ?array`, `tree(): array`(DFS 순서, 각 행에 `product_count`), `options(): array`(`id => 경로 이름`), `path(string $code): array`(1단계부터 자기 자신까지), `children(string $code, bool $activeOnly): array`, `delete(int $id): void`, `bulk(array $rows): void`(`rows[id] = [name, sort_order, active, list_columns, list_rows, image_width, image_height]`), `count(): int`

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/CategoriesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use PHPUnit\Framework\Attributes\DataProvider;

final class CategoriesTest extends YoungCartTestCase
{
    #[DataProvider('connectionProvider')]
    public function testCodeSuggestionValidationAndTree(array $config): void
    {
        $this->setupShop($config);
        self::assertSame('10', $this->shop->categories->suggestCode(null));
        $top = $this->category('의류');
        self::assertSame('10', $top['code']); self::assertSame(1, (int) $top['depth']); self::assertNull($top['parent_id']);
        self::assertSame('20', $this->shop->categories->suggestCode(null));
        self::assertSame('1010', $this->shop->categories->suggestCode('10'));
        $child = $this->category('셔츠', '10');
        self::assertSame((int) $top['id'], (int) $child['parent_id']); self::assertSame(2, (int) $child['depth']);
        self::assertSame('1020', $this->shop->categories->suggestCode('10'));
        $this->shop->store->insert('yc_categories', ['code' => 'z0', 'parent_id' => null, 'depth' => 1, 'name' => '끝', 'sort_order' => 0, 'active' => 1,
            'no_coupon' => 0, 'head_html' => '', 'tail_html' => '', 'list_columns' => 3, 'list_rows' => 5, 'image_width' => 200, 'image_height' => 0, 'extra' => '[]', 'created_at' => 1, 'updated_at' => 1]);
        self::assertNull($this->shop->categories->suggestCode(null));
        foreach (['1', '101', '10-1', '9910', '가나', ''] as $bad) {
            try {
                $this->shop->categories->save(['code' => $bad, 'name' => 'x', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']);
                self::fail('코드 ' . $bad . '는 거절해야 한다');
            } catch (DomainError $e) {
                self::assertSame(422, $e->status(), $bad);
            }
        }
        $id = $this->shop->categories->save(['code' => '1A', 'name' => '소문자화', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']);
        self::assertSame('1a', $this->shop->categories->get($id)['code']);
        $deep = '10';
        foreach (['셔츠2', '3단', '4단', '5단'] as $name) { $deep = $this->category($name, $deep)['code']; }
        self::assertSame(10, strlen($deep));
        try { $this->shop->categories->suggestCode($deep); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $tree = $this->shop->categories->tree();
        self::assertSame(['10', '1010', '1020', '102010', '10201010', '1020101010', '1a', '20', 'z0'], array_column($tree, 'code'));
        self::assertSame(0, (int) $tree[0]['product_count']);
        self::assertSame(['10', '1020', '102010'], array_column($this->shop->categories->path('102010'), 'code'));
        self::assertSame(['1010', '1020'], array_column($this->shop->categories->children('10', true), 'code'));
        self::assertStringStartsWith('의류 > ', $this->shop->categories->options()[(int) $child['id']]);
    }

    #[DataProvider('connectionProvider')]
    public function testUpdateApplyChildrenDeleteGuardsAndBulk(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류'); $child = $this->category('셔츠', '10'); $grand = $this->category('반팔', '1010');
        $this->shop->categories->save(['code' => 'ignored', 'name' => '의류(수정)', 'active' => '0', 'no_coupon' => '1', 'list_columns' => '4', 'list_rows' => '2',
            'image_width' => '150', 'image_height' => '150', 'head_html' => '<p>위</p><script>1</script>', 'sort_order' => '5', 'apply_children' => '1',
            'extra_label' => [1 => '라벨'], 'extra_value' => [1 => '값']], (int) $top['id']);
        $top = $this->shop->categories->get((int) $top['id']);
        self::assertSame('10', $top['code']); self::assertSame('의류(수정)', $top['name']); self::assertSame('<p>위</p>', $top['head_html']);
        self::assertSame('라벨', $top['extra'][0]['label']);
        $grand = $this->shop->categories->get((int) $grand['id']);
        self::assertSame(0, (int) $grand['active']); self::assertSame(4, (int) $grand['list_columns']); self::assertSame(150, (int) $grand['image_height']);
        self::assertSame('반팔', $grand['name']);
        try { $this->shop->categories->delete((int) $top['id']); self::fail(); } catch (DomainError $e) { self::assertStringContainsString('하위 분류', $e->details()['category']); }
        $this->product(['category_id' => (string) $grand['id']]);
        try { $this->shop->categories->delete((int) $grand['id']); self::fail(); } catch (DomainError $e) { self::assertStringContainsString('1개 상품', $e->details()['category']); }
        $leaf = $this->category('빈 분류', '1010');
        $this->shop->categories->delete((int) $leaf['id']);
        self::assertNull($this->shop->categories->byCode($leaf['code']));
        $this->shop->categories->bulk([(int) $child['id'] => ['name' => '셔츠(일괄)', 'sort_order' => '3', 'active' => '1', 'list_columns' => '2', 'list_rows' => '2', 'image_width' => '100', 'image_height' => '0']]);
        self::assertSame('셔츠(일괄)', $this->shop->categories->get((int) $child['id'])['name']);
        try {
            $this->shop->categories->bulk([(int) $child['id'] => ['name' => '', 'sort_order' => '3', 'active' => '1', 'list_columns' => '2', 'list_rows' => '2', 'image_width' => '100', 'image_height' => '0'],
                (int) $grand['id'] => ['name' => '유지', 'sort_order' => '0', 'active' => '1', 'list_columns' => '2', 'list_rows' => '2', 'image_width' => '100', 'image_height' => '0']]);
            self::fail();
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
        }
        self::assertSame('반팔', $this->shop->categories->get((int) $grand['id'])['name']);
        self::assertSame(3, $this->shop->categories->count());
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/CategoriesTest.php`
Expected: FAIL — `Service::$categories`가 없다.

- [ ] **Step 3: Categories 작성**

`modules/youngcart/src/Catalog/Categories.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Catalog;

use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\Settings;
use GnuCms\Modules\YoungCart\Store;
use GnuCms\Support\Clock;

final class Categories
{
    public const MAX_DEPTH = 5;

    public function __construct(private Store $store, private HtmlSanitizer $sanitizer, private Settings $settings) {}

    /** 형제의 마지막 두 자리 최댓값(36진수)에 36을 더한다. 첫 형제는 '10'. 'zz'를 넘으면 null. */
    public function suggestCode(?string $parentCode): ?string
    {
        $prefix = $parentCode ?? '';
        if ($prefix !== '' && $this->byCode($prefix) === null) throw DomainError::notFound('상위 분류를 찾을 수 없습니다.');
        if (strlen($prefix) >= self::MAX_DEPTH * 2) throw DomainError::validation(['code' => self::MAX_DEPTH . '단계 아래에는 분류를 만들 수 없습니다.']);
        $rows = $this->store->select('SELECT code FROM ' . $this->store->table('yc_categories') . ' WHERE LENGTH(code) = ? AND code LIKE ?', [strlen($prefix) + 2, $prefix . '%']);
        $max = -1;
        foreach ($rows as $row) $max = max($max, (int) base_convert(substr($row['code'], -2), 36, 10));
        $next = $max < 0 ? 36 : $max + 36;
        if ($next > 1295) return null;
        return $prefix . str_pad(base_convert((string) $next, 10, 36), 2, '0', STR_PAD_LEFT);
    }

    public function save(array $input, ?int $id = null): int
    {
        $defaults = $this->settings->block('category');
        $row = [
            'name' => Input::text($input['name'] ?? '', 'name', 100, false),
            'sort_order' => Input::int($input['sort_order'] ?? '', 'sort_order', -999999, 999999, 0),
            'active' => Input::bool($input['active'] ?? '0'),
            'no_coupon' => Input::bool($input['no_coupon'] ?? '0'),
            'head_html' => Input::html($input['head_html'] ?? '', 'head_html', $this->sanitizer),
            'tail_html' => Input::html($input['tail_html'] ?? '', 'tail_html', $this->sanitizer),
            'list_columns' => Input::int($input['list_columns'] ?? '', 'list_columns', 1, 12, $defaults['columns']),
            'list_rows' => Input::int($input['list_rows'] ?? '', 'list_rows', 1, 50, $defaults['rows']),
            'image_width' => Input::int($input['image_width'] ?? '', 'image_width', 0, 2000, $defaults['image_width']),
            'image_height' => Input::int($input['image_height'] ?? '', 'image_height', 0, 2000, $defaults['image_height']),
            'extra' => Input::extra($input),
            'updated_at' => Clock::timestamp(),
        ];
        return $this->store->transaction(function () use ($input, $id, $row): int {
            if ($id !== null) {
                $existing = $this->store->get('yc_categories', $id);
                $this->store->update('yc_categories', $id, $row);
                if (($input['apply_children'] ?? '') === '1') {
                    $this->store->db->update('yc_categories', array_intersect_key($row, array_flip(['active', 'no_coupon', 'list_columns', 'list_rows', 'image_width', 'image_height', 'updated_at'])),
                        'code LIKE :prefix AND id <> :id', ['prefix' => $existing['code'] . '%', 'id' => $id]);
                }
                return $id;
            }
            $code = strtolower(Input::code($input['code'] ?? '', 'code', '/^[0-9A-Za-z]{2,10}$/D', '분류 코드는 단계당 2자, 최대 10자의 영문 소문자·숫자입니다.'));
            if (strlen($code) % 2 !== 0) throw DomainError::validation(['code' => '분류 코드는 단계당 2자입니다.']);
            $parent = null;
            if (strlen($code) > 2) {
                $parent = $this->byCode(substr($code, 0, -2)) ?? throw DomainError::validation(['code' => '상위 분류 코드가 없습니다.']);
            }
            if ($this->byCode($code) !== null) throw DomainError::validation(['code' => '이미 사용 중인 분류 코드입니다.']);
            $row += ['code' => $code, 'parent_id' => $parent === null ? null : (int) $parent['id'], 'depth' => intdiv(strlen($code), 2), 'created_at' => Clock::timestamp()];
            return $this->store->insert('yc_categories', $row);
        });
    }

    public function get(int $id): array
    {
        return $this->decode($this->store->get('yc_categories', $id));
    }

    public function byCode(string $code): ?array
    {
        $row = $this->store->selectOne('SELECT * FROM ' . $this->store->table('yc_categories') . ' WHERE code = ?', [$code]);
        return $row === null ? null : $this->decode($row);
    }

    /** 부모 우선 DFS. 형제는 sort_order, code 순. 각 행에 product_count. */
    public function tree(): array
    {
        $rows = $this->store->select('SELECT c.*, (SELECT COUNT(*) FROM ' . $this->store->table('yc_product_categories') . ' pc WHERE pc.category_id = c.id) AS product_count FROM '
            . $this->store->table('yc_categories') . ' c ORDER BY c.sort_order, c.code');
        $byParent = [];
        foreach ($rows as $row) $byParent[$row['parent_id'] === null ? 0 : (int) $row['parent_id']][] = $this->decode($row);
        $result = [];
        $walk = function (int $parent) use (&$walk, &$result, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $row) {
                $result[] = $row;
                $walk((int) $row['id']);
            }
        };
        $walk(0);
        return $result;
    }

    /** 선택 상자용 `id => 경로 이름`. */
    public function options(): array
    {
        $names = [];
        $options = [];
        foreach ($this->tree() as $row) {
            $names[(int) $row['id']] = ($row['parent_id'] === null ? '' : $names[(int) $row['parent_id']] . ' > ') . $row['name'];
            $options[(int) $row['id']] = $names[(int) $row['id']];
        }
        return $options;
    }

    public function path(string $code): array
    {
        $codes = [];
        for ($length = 2; $length <= strlen($code); $length += 2) $codes[] = substr($code, 0, $length);
        $rows = [];
        foreach ($codes as $c) { $row = $this->byCode($c); if ($row !== null) $rows[] = $row; }
        return $rows;
    }

    public function children(string $code, bool $activeOnly): array
    {
        $rows = $this->store->select('SELECT * FROM ' . $this->store->table('yc_categories') . ' WHERE LENGTH(code) = ? AND code LIKE ?'
            . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, code', [strlen($code) + 2, $code . '%']);
        return array_map($this->decode(...), $rows);
    }

    public function delete(int $id): void
    {
        $category = $this->store->get('yc_categories', $id);
        if ($this->store->selectOne('SELECT id FROM ' . $this->store->table('yc_categories') . ' WHERE parent_id = ?', [$id]) !== null) {
            throw DomainError::validation(['category' => '하위 분류가 있어 삭제할 수 없습니다. 하위 분류를 먼저 삭제해 주세요.']);
        }
        $count = (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->store->table('yc_product_categories') . ' WHERE category_id = ?', [$id])['c'];
        if ($count > 0) throw DomainError::validation(['category' => $count . '개 상품이 연결되어 있어 삭제할 수 없습니다. 상품의 분류를 먼저 옮겨 주세요.']);
        $this->store->delete('yc_categories', 'id = ?', [(int) $category['id']]);
    }

    /** @param array<int, array<string, mixed>> $rows 한 행이라도 틀리면 전체를 취소한다. */
    public function bulk(array $rows): void
    {
        $this->store->transaction(function () use ($rows): void {
            foreach ($rows as $id => $input) {
                $id = Input::id($id);
                $this->store->get('yc_categories', $id);
                try {
                    $this->store->update('yc_categories', $id, [
                        'name' => Input::text($input['name'] ?? '', 'name', 100, false),
                        'sort_order' => Input::int($input['sort_order'] ?? '', 'sort_order', -999999, 999999, 0),
                        'active' => Input::bool($input['active'] ?? '0'),
                        'list_columns' => Input::int($input['list_columns'] ?? '', 'list_columns', 1, 12),
                        'list_rows' => Input::int($input['list_rows'] ?? '', 'list_rows', 1, 50),
                        'image_width' => Input::int($input['image_width'] ?? '', 'image_width', 0, 2000),
                        'image_height' => Input::int($input['image_height'] ?? '', 'image_height', 0, 2000),
                        'updated_at' => Clock::timestamp(),
                    ]);
                } catch (DomainError $e) {
                    throw DomainError::validation(['row_' . $id => implode(' ', $e->details())]);
                }
            }
        });
    }

    public function count(): int
    {
        return (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->store->table('yc_categories'))['c'];
    }

    private function decode(array $row): array
    {
        $extra = json_decode((string) $row['extra'], true);
        $row['extra'] = is_array($extra) ? $extra : [];
        return $row;
    }
}
```

`Service`에 `public readonly Catalog\Categories $categories;`를 선언하고 생성자에서 `$this->categories = new Catalog\Categories($this->store, $app->htmlSanitizer(), $this->settings);`를 추가한다(`$settings` 다음 줄).

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/CategoriesTest.php --filter testCodeSuggestionValidationAndTree`
Expected: PASS. 둘째 테스트는 `product()`가 `Service::$products`를 요구해 Task 9에서 통과한다.

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Catalog/Categories.php modules/youngcart/src/Service.php tests/YoungCart/CategoriesTest.php
git commit -m "feat: add youngcart category service with code generation and guards"
```

---

### Task 7: 옵션 서비스

**Files:**
- Create: `modules/youngcart/src/Catalog/Options.php`
- Modify: `modules/youngcart/src/Service.php` (`$options`)
- Test: `tests/YoungCart/OptionsTest.php`

**Interfaces:**
- 상수 `MAX_GROUPS = 3`, `MAX_VALUES = 20`, `MAX_COMBOS = 1000`, `MAX_EXTRA_GROUPS = 10`, `MAX_EXTRA_ITEMS = 20`, `DEFAULT_STOCK = 9999`, `DEFAULT_ALERT = 100`
- `Options::combine(array $groupValues): array` — `[['빨강','S',''], …]`
- `Options::draft(array $input, array $existing): array` — 폼의 `option_group[1..3]`·`option_values[1..3]`에서 조합 표를 만든다. 반환 `['groups' => [names], 'rows' => [[value1,value2,value3,price,stock,stock_alert,active]]]`. 기존 조합의 값은 유지.
- `Options::rows(mixed $raw): array` — `options[i][field]` 형식 정규화
- `Options::validate(int $productPrice, array $groups, array $rows, array $extraRows): array` — `['select_groups'=>…, 'select'=>…, 'extra_groups'=>…, 'extra'=>…]`
- `Options::replace(int $productId, array $normalized, string $actor): void` — 트랜잭션 안에서 호출
- `Options::load(int $productId): array` — 같은 구조 + 각 행에 `id`
- `Options::soldOut(array $product, array $selectRows): bool`
- `Options::pageJson(array $product, array $loaded): array`
- `Options::stockList(string $q, int $page, int $perPage): array`, `Options::updateStock(array $rows, string $actor): void`(`rows[optionId] = [stock, stock_alert, active]`)

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/OptionsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Catalog\Options;
use PHPUnit\Framework\Attributes\DataProvider;

final class OptionsTest extends YoungCartTestCase
{
    public function testCombineDraftAndValidation(): void
    {
        self::assertSame([['빨강', 'S', ''], ['빨강', 'M', ''], ['파랑', 'S', ''], ['파랑', 'M', '']], Options::combine([['빨강', '파랑'], ['S', 'M']]));
        self::assertSame([['빨강', '', '']], Options::combine([['빨강']]));
        self::assertSame([], Options::combine([]));
        $draft = Options::draft(['option_group' => [1 => '색상', 2 => '크기'], 'option_values' => [1 => '빨강,파랑', 2 => 'S']],
            [['value1' => '빨강', 'value2' => 'S', 'value3' => '', 'price' => 500, 'stock' => 3, 'stock_alert' => 1, 'active' => 0]]);
        self::assertSame(['색상', '크기'], $draft['groups']);
        self::assertSame(['value1' => '빨강', 'value2' => 'S', 'value3' => '', 'price' => 500, 'stock' => 3, 'stock_alert' => 1, 'active' => 0], $draft['rows'][0]);
        self::assertSame(['value1' => '파랑', 'value2' => 'S', 'value3' => '', 'price' => 0, 'stock' => 9999, 'stock_alert' => 100, 'active' => 1], $draft['rows'][1]);
        try { Options::draft(['option_group' => [1 => '색상'], 'option_values' => [1 => implode(',', range(1, 21))]], []); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        try { Options::draft(['option_group' => [1 => '', 2 => '크기'], 'option_values' => [1 => '', 2 => 'S']], []); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('option_group', $e->details()); }
        $rows = Options::rows(['0' => ['value1' => '빨강', 'value2' => 'S', 'price' => '500', 'stock' => '3', 'stock_alert' => '1', 'active' => '1'], 'x' => 'junk']);
        self::assertCount(1, $rows);
        $normalized = (new Options($this->stubStore()))->validate(10000, ['색상', '크기'], $rows, [['value1' => '포장', 'value2' => '선물포장', 'price' => '2000', 'stock' => '10', 'stock_alert' => '0', 'active' => '1']]);
        self::assertSame(['색상', '크기'], $normalized['select_groups']);
        self::assertSame(['포장'], $normalized['extra_groups']);
        self::assertSame(2000, $normalized['extra'][0]['price']);
        try { (new Options($this->stubStore()))->validate(10000, ['색상'], [['value1' => '빨강', 'price' => '-10001']], []); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('options', $e->details()); }
        try { (new Options($this->stubStore()))->validate(10000, ['색상'], [['value1' => '빨강'], ['value1' => '빨강']], []); self::fail(); } catch (DomainError $e) { self::assertStringContainsString('중복', $e->details()['options']); }
        try { (new Options($this->stubStore()))->validate(10000, [], [], [['value1' => '포장', 'value2' => '선물', 'price' => '-1']]); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('extras', $e->details()); }
        try { (new Options($this->stubStore()))->validate(10000, ['색상'], [['value1' => '<b>']], []); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('options', $e->details()); }
    }

    private function stubStore(): \GnuCms\Modules\YoungCart\Store
    {
        return new \GnuCms\Modules\YoungCart\Store(\GnuCms\Db\Connection::create(['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null]));
    }

    #[DataProvider('connectionProvider')]
    public function testReplaceKeepsStockSoldOutAndPageJson(array $config): void
    {
        $this->setupShop($config);
        $productId = $this->shop->store->insert('yc_products', $this->minimalProductRow('P1'));
        $options = $this->shop->options;
        $first = $options->validate(10000, ['색상'], [['value1' => '빨강', 'price' => '0', 'stock' => '5', 'stock_alert' => '1', 'active' => '1'], ['value1' => '파랑', 'price' => '1000', 'stock' => '0', 'stock_alert' => '1', 'active' => '1']],
            [['value1' => '포장', 'value2' => '선물포장', 'price' => '2000', 'stock' => '10', 'stock_alert' => '0', 'active' => '1']]);
        $this->shop->store->transaction(fn () => $options->replace($productId, $first, 'tester'));
        $loaded = $options->load($productId);
        self::assertSame(['색상'], $loaded['select_groups']); self::assertCount(2, $loaded['select']); self::assertSame(['포장'], $loaded['extra_groups']);
        $redId = (int) $loaded['select'][0]['id'];
        self::assertSame(5, (int) $this->shop->store->selectOne('SELECT SUM(delta) AS d FROM ' . $this->shop->store->table('yc_stock_log') . ' WHERE option_id = ?', [$redId])['d']);
        $second = $options->validate(10000, ['색상'], [['value1' => '빨강', 'price' => '100', 'stock' => '7', 'stock_alert' => '1', 'active' => '1'], ['value1' => '노랑', 'price' => '0', 'stock' => '1', 'stock_alert' => '0', 'active' => '1']], []);
        $this->shop->store->transaction(fn () => $options->replace($productId, $second, 'tester'));
        $loaded = $options->load($productId);
        self::assertSame($redId, (int) $loaded['select'][0]['id']);
        self::assertSame(100, (int) $loaded['select'][0]['price']); self::assertSame(7, (int) $loaded['select'][0]['stock']);
        self::assertSame(['빨강', '노랑'], array_column($loaded['select'], 'value1'));
        self::assertSame([], $loaded['extra']);
        self::assertSame(7, (int) $this->shop->store->selectOne('SELECT SUM(delta) AS d FROM ' . $this->shop->store->table('yc_stock_log') . ' WHERE option_id = ?', [$redId])['d']);
        $product = ['sold_out' => 0, 'stock' => 0];
        self::assertFalse(Options::soldOut($product, $loaded['select']));
        self::assertTrue(Options::soldOut(['sold_out' => 1, 'stock' => 9], $loaded['select']));
        self::assertTrue(Options::soldOut($product, [['stock' => 0, 'active' => 1], ['stock' => 5, 'active' => 0]]));
        self::assertTrue(Options::soldOut($product, []));
        self::assertFalse(Options::soldOut(['sold_out' => 0, 'stock' => 1], []));
        $json = Options::pageJson(['price' => 10000], $loaded);
        self::assertSame(['색상'], $json['select']['groups']);
        self::assertSame(['v' => ['빨강'], 'price' => 100, 'stock' => 7], $json['select']['items'][0]);
        self::assertSame([], $json['extra']['groups']);
        $list = $options->stockList('', 1, 20);
        self::assertSame(2, $list['total']);
        $options->updateStock([$redId => ['stock' => '2', 'stock_alert' => '3', 'active' => '0']], 'tester');
        self::assertSame(2, (int) $options->load($productId)['select'][0]['stock']);
        self::assertSame(-5, (int) $this->shop->store->selectOne('SELECT delta FROM ' . $this->shop->store->table('yc_stock_log') . ' WHERE option_id = ? ORDER BY id DESC LIMIT 1', [$redId])['delta']);
    }

    private function minimalProductRow(string $code): array
    {
        return ['code' => $code, 'slug' => $code, 'category_id' => 1, 'name' => $code, 'summary' => '', 'description' => '', 'description_text' => '', 'price' => 10000,
            'head_html' => '', 'tail_html' => '', 'info_values' => '', 'memo' => '', 'extra' => '[]', 'created_at' => 1, 'updated_at' => 1];
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/OptionsTest.php`
Expected: FAIL — 클래스가 없다.

- [ ] **Step 3: Options 작성**

`modules/youngcart/src/Catalog/Options.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Catalog;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\Store;

final class Options
{
    public const MAX_GROUPS = 3;
    public const MAX_VALUES = 20;
    public const MAX_COMBOS = 1000;
    public const MAX_EXTRA_GROUPS = 10;
    public const MAX_EXTRA_ITEMS = 20;
    public const DEFAULT_STOCK = 9999;
    public const DEFAULT_ALERT = 100;

    public function __construct(private Store $store) {}

    /** @param list<list<string>> $groupValues @return list<array{0:string,1:string,2:string}> */
    public static function combine(array $groupValues): array
    {
        $groupValues = array_values(array_filter($groupValues, static fn (array $values): bool => $values !== []));
        if ($groupValues === []) return [];
        $rows = [[]];
        foreach ($groupValues as $values) {
            $next = [];
            foreach ($rows as $row) foreach ($values as $value) $next[] = [...$row, $value];
            $rows = $next;
        }
        return array_map(static fn (array $row): array => array_pad($row, 3, ''), $rows);
    }

    public static function draft(array $input, array $existing): array
    {
        $groups = [];
        $values = [];
        for ($i = 1; $i <= self::MAX_GROUPS; $i++) {
            $name = Input::text($input['option_group'][$i] ?? '', 'option_group', 100);
            $list = Input::csv($input['option_values'][$i] ?? '', self::MAX_VALUES, 100, 'option_values');
            if ($name === '' && $list === []) continue;
            if ($name === '' || $list === []) throw DomainError::validation(['option_group' => '옵션 이름과 값을 함께 입력해 주세요.']);
            if (count($groups) !== $i - 1) throw DomainError::validation(['option_group' => '옵션 그룹은 순서대로 채워 주세요.']);
            $groups[] = $name;
            $values[] = $list;
        }
        $combos = self::combine($values);
        if (count($combos) > self::MAX_COMBOS) throw DomainError::validation(['option_values' => '옵션 조합은 ' . self::MAX_COMBOS . '개까지 만들 수 있습니다.']);
        $known = [];
        foreach ($existing as $row) $known[self::key($row)] = $row;
        $rows = [];
        foreach ($combos as [$v1, $v2, $v3]) {
            $row = ['value1' => $v1, 'value2' => $v2, 'value3' => $v3];
            $old = $known[self::key($row)] ?? null;
            $rows[] = $row + ['price' => (int) ($old['price'] ?? 0), 'stock' => (int) ($old['stock'] ?? self::DEFAULT_STOCK),
                'stock_alert' => (int) ($old['stock_alert'] ?? self::DEFAULT_ALERT), 'active' => (int) ($old['active'] ?? 1)];
        }
        return ['groups' => $groups, 'rows' => $rows];
    }

    /** 폼의 `options[i][field]`·`extras[i][field]` 배열을 문자열 필드만 남겨 정규화한다. */
    public static function rows(mixed $raw): array
    {
        if (!is_array($raw)) return [];
        $rows = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $clean = [];
            foreach (['value1', 'value2', 'value3', 'price', 'stock', 'stock_alert', 'active'] as $field) {
                $value = $row[$field] ?? '';
                $clean[$field] = is_string($value) || is_int($value) ? (string) $value : '';
            }
            $rows[] = $clean;
        }
        return $rows;
    }

    public function validate(int $productPrice, array $groups, array $rows, array $extraRows): array
    {
        $groups = array_values(array_filter(array_map(static fn ($g) => Input::text($g, 'option_group', 100), $groups), static fn (string $g): bool => $g !== ''));
        if (count($groups) > self::MAX_GROUPS) throw DomainError::validation(['option_group' => '선택옵션 그룹은 ' . self::MAX_GROUPS . '개까지입니다.']);
        $select = [];
        $seen = [];
        foreach ($rows as $row) {
            $values = [];
            for ($i = 1; $i <= 3; $i++) $values[] = Input::text($row['value' . $i] ?? '', 'options', 100);
            if ($values[0] === '') continue;
            foreach ($values as $value) if (preg_match('/[<>"\']/', $value)) throw DomainError::validation(['options' => '옵션 값에 <>"\' 문자를 쓸 수 없습니다.']);
            $filled = count(array_filter($values, static fn (string $v): bool => $v !== ''));
            if ($groups === [] || $filled !== count($groups)) throw DomainError::validation(['options' => '옵션 그룹 수와 조합 값의 수가 맞지 않습니다.']);
            $price = Input::int($row['price'] ?? '', 'options', -1000000000, 1000000000, 0);
            if ($productPrice + $price < 0) throw DomainError::validation(['options' => '판매가와 옵션 차액의 합은 0원 이상이어야 합니다.']);
            $normalized = ['value1' => $values[0], 'value2' => $values[1], 'value3' => $values[2], 'price' => $price,
                'stock' => Input::int($row['stock'] ?? '', 'options', 0, 1000000, self::DEFAULT_STOCK),
                'stock_alert' => Input::int($row['stock_alert'] ?? '', 'options', 0, 1000000, self::DEFAULT_ALERT),
                'active' => Input::bool($row['active'] ?? '1'), 'sort_order' => count($select)];
            $key = self::key($normalized);
            if (isset($seen[$key])) throw DomainError::validation(['options' => '중복된 옵션 조합이 있습니다: ' . implode('/', array_filter($values))]);
            $seen[$key] = true;
            $select[] = $normalized;
        }
        if ($groups !== [] && $select === []) throw DomainError::validation(['options' => '옵션 그룹을 지정했으면 조합을 하나 이상 만들어 주세요.']);
        if (count($select) > self::MAX_COMBOS) throw DomainError::validation(['options' => '옵션 조합은 ' . self::MAX_COMBOS . '개까지입니다.']);
        $extra = [];
        $extraGroups = [];
        $extraSeen = [];
        foreach ($extraRows as $row) {
            $group = Input::text($row['value1'] ?? '', 'extras', 100);
            $name = Input::text($row['value2'] ?? '', 'extras', 100);
            if ($group === '' && $name === '') continue;
            if ($group === '' || $name === '' || preg_match('/[<>"\']/', $group . $name)) throw DomainError::validation(['extras' => '추가옵션은 그룹명과 항목명을 함께 입력하고 <>"\' 문자를 쓸 수 없습니다.']);
            if (!in_array($group, $extraGroups, true)) $extraGroups[] = $group;
            $key = $group . "\x1e" . $name;
            if (isset($extraSeen[$key])) throw DomainError::validation(['extras' => '중복된 추가옵션이 있습니다: ' . $group . ' ' . $name]);
            $extraSeen[$key] = true;
            $extra[] = ['value1' => $group, 'value2' => $name, 'value3' => '', 'price' => Input::int($row['price'] ?? '', 'extras', 0, 1000000000, 0),
                'stock' => Input::int($row['stock'] ?? '', 'extras', 0, 1000000, self::DEFAULT_STOCK),
                'stock_alert' => Input::int($row['stock_alert'] ?? '', 'extras', 0, 1000000, self::DEFAULT_ALERT),
                'active' => Input::bool($row['active'] ?? '1'), 'sort_order' => count($extra)];
        }
        if (count($extraGroups) > self::MAX_EXTRA_GROUPS) throw DomainError::validation(['extras' => '추가옵션 그룹은 ' . self::MAX_EXTRA_GROUPS . '개까지입니다.']);
        foreach ($extraGroups as $group) {
            if (count(array_filter($extra, static fn (array $r): bool => $r['value1'] === $group)) > self::MAX_EXTRA_ITEMS) {
                throw DomainError::validation(['extras' => '추가옵션 그룹당 항목은 ' . self::MAX_EXTRA_ITEMS . '개까지입니다.']);
            }
        }
        return ['select_groups' => $groups, 'select' => $select, 'extra_groups' => $extraGroups, 'extra' => $extra];
    }

    /** 조합 키 기준 upsert. 제출되지 않은 기존 옵션은 삭제한다. 재고 차이는 원장에 기록한다. 트랜잭션 안에서 호출한다. */
    public function replace(int $productId, array $normalized, string $actor): void
    {
        foreach (['select', 'extra'] as $kind) {
            $this->store->delete('yc_option_groups', 'product_id = ? AND kind = ?', [$productId, $kind]);
            foreach ($normalized[$kind . '_groups'] as $position => $name) {
                $this->store->insert('yc_option_groups', ['product_id' => $productId, 'kind' => $kind, 'position' => $position + 1, 'name' => $name]);
            }
            $existing = [];
            foreach ($this->store->select('SELECT * FROM ' . $this->store->table('yc_options') . ' WHERE product_id = ? AND kind = ?', [$productId, $kind]) as $row) {
                $existing[self::key($row)] = $row;
            }
            foreach ($normalized[$kind] as $row) {
                $key = self::key($row);
                if (isset($existing[$key])) {
                    $old = $existing[$key];
                    unset($existing[$key]);
                    $this->store->update('yc_options', (int) $old['id'], ['price' => $row['price'], 'stock' => $row['stock'], 'stock_alert' => $row['stock_alert'], 'active' => $row['active'], 'sort_order' => $row['sort_order']]);
                    if ((int) $old['stock'] !== $row['stock']) $this->store->logStock($productId, (int) $old['id'], $row['stock'] - (int) $old['stock'], 'admin', 'option', $actor);
                } else {
                    $id = $this->store->insert('yc_options', ['product_id' => $productId, 'kind' => $kind] + $row);
                    if ($row['stock'] !== 0) $this->store->logStock($productId, $id, $row['stock'], 'admin', 'option', $actor);
                }
            }
            foreach ($existing as $old) {
                if ((int) $old['stock'] !== 0) $this->store->logStock($productId, (int) $old['id'], -(int) $old['stock'], 'admin', 'option-removed', $actor);
                $this->store->delete('yc_options', 'id = ?', [(int) $old['id']]);
            }
        }
    }

    public function load(int $productId): array
    {
        $result = ['select_groups' => [], 'select' => [], 'extra_groups' => [], 'extra' => []];
        foreach ($this->store->select('SELECT * FROM ' . $this->store->table('yc_option_groups') . ' WHERE product_id = ? ORDER BY kind, position', [$productId]) as $row) {
            $result[$row['kind'] . '_groups'][] = $row['name'];
        }
        foreach ($this->store->select('SELECT * FROM ' . $this->store->table('yc_options') . ' WHERE product_id = ? ORDER BY kind, sort_order, id', [$productId]) as $row) {
            $result[$row['kind']][] = $row;
        }
        return $result;
    }

    public static function soldOut(array $product, array $selectRows): bool
    {
        if ((int) ($product['sold_out'] ?? 0) === 1) return true;
        if ($selectRows === []) return (int) ($product['stock'] ?? 0) <= 0;
        foreach ($selectRows as $row) {
            if ((int) ($row['active'] ?? 1) === 1 && (int) $row['stock'] > 0) return false;
        }
        return true;
    }

    public static function pageJson(array $product, array $loaded): array
    {
        $items = [];
        foreach ($loaded['select'] as $row) {
            $items[] = ['v' => array_values(array_filter([$row['value1'], $row['value2'], $row['value3']], static fn ($v) => $v !== '')),
                'price' => (int) $row['price'], 'stock' => (int) $row['active'] === 1 ? (int) $row['stock'] : 0];
        }
        $extraGroups = [];
        foreach ($loaded['extra_groups'] as $group) {
            $groupItems = [];
            foreach ($loaded['extra'] as $row) {
                if ($row['value1'] === $group) $groupItems[] = ['name' => $row['value2'], 'price' => (int) $row['price'], 'stock' => (int) $row['active'] === 1 ? (int) $row['stock'] : 0];
            }
            $extraGroups[] = ['name' => $group, 'items' => $groupItems];
        }
        return ['price' => (int) $product['price'], 'select' => ['groups' => $loaded['select_groups'], 'items' => $items], 'extra' => ['groups' => $extraGroups]];
    }

    public function stockList(string $q, int $page, int $perPage): array
    {
        $where = ''; $params = [];
        if ($q !== '') { $where = ' WHERE (p.name LIKE ? OR p.code LIKE ?)'; $params = ['%' . $q . '%', '%' . $q . '%']; }
        $total = (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->store->table('yc_options') . ' o JOIN ' . $this->store->table('yc_products') . ' p ON p.id = o.product_id' . $where, $params)['c'];
        $rows = $this->store->select('SELECT o.*, p.name AS product_name, p.code AS product_code FROM ' . $this->store->table('yc_options') . ' o JOIN ' . $this->store->table('yc_products')
            . ' p ON p.id = o.product_id' . $where . ' ORDER BY o.stock ASC, o.id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'total_pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function updateStock(array $rows, string $actor): void
    {
        $this->store->transaction(function () use ($rows, $actor): void {
            foreach ($rows as $id => $input) {
                $id = Input::id($id);
                $old = $this->store->get('yc_options', $id);
                $stock = Input::int($input['stock'] ?? '', 'stock', 0, 1000000);
                $this->store->update('yc_options', $id, ['stock' => $stock, 'stock_alert' => Input::int($input['stock_alert'] ?? '', 'stock_alert', 0, 1000000, 0), 'active' => Input::bool($input['active'] ?? '0')]);
                if ($stock !== (int) $old['stock']) $this->store->logStock((int) $old['product_id'], $id, $stock - (int) $old['stock'], 'admin', 'option-stock', $actor);
            }
        });
    }

    private static function key(array $row): string
    {
        return ($row['value1'] ?? '') . "\x1e" . ($row['value2'] ?? '') . "\x1e" . ($row['value3'] ?? '');
    }
}
```

`Service`에 `public readonly Catalog\Options $options;`를 선언하고 생성자에 `$this->options = new Catalog\Options($this->store);`를 추가한다.

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/OptionsTest.php`
Expected: PASS

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Catalog/Options.php modules/youngcart/src/Service.php tests/YoungCart/OptionsTest.php
git commit -m "feat: add youngcart option combinations with stock-preserving upsert"
```

---

### Task 8: 상품 이미지

**Files:**
- Create: `modules/youngcart/src/Images.php`
- Modify: `modules/youngcart/src/Service.php` (`$images`)
- Test: `tests/YoungCart/ImagesTest.php`

**Interfaces:**
- `Images::__construct(App $app, Settings $settings)`, `Images::MAX = 10`, `Images::SIZES = ['main','list','type','search','related','detail','thumb','original']`
- `directory(int $productId): string`, `save(int $productId, UploadedFileInterface $upload): string`(파일명), `delete(int $productId, string $filename): void`, `deleteAll(int $productId): void`, `copy(int $from, int $to, string $filename): string`, `response(int $productId, string $filename, string $size, ResponseInterface $response, ?int $width = null): ResponseInterface`, `width(string $size, ?array $category = null): int`, `static url(string $publicUrl, int $productId, string $filename, string $size): string`, `static validName(string $filename): bool`

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/ImagesTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Images;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;
use Slim\Psr7\UploadedFile;

final class ImagesTest extends YoungCartTestCase
{
    #[DataProvider('connectionProvider')]
    public function testSaveResizeCopyAndDelete(array $config): void
    {
        $this->setupShop($config);
        $images = $this->shop->images;
        $name = $images->save(7, self::png(800, 400));
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $name);
        self::assertFileExists($images->directory(7) . '/' . $name);
        self::assertSame(200, $images->width('list'));
        self::assertSame(150, $images->width('list', ['image_width' => 150]));
        self::assertSame(70, $images->width('thumb'));
        self::assertSame(0, $images->width('original'));
        $response = $images->response(7, $name, 'list', new Response());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('max-age=86400', $response->getHeaderLine('Cache-Control'));
        $response->getBody()->rewind();
        [$width] = getimagesizefromstring((string) $response->getBody());
        self::assertSame(200, $width);
        $response = $images->response(7, $name, 'original', new Response());
        $response->getBody()->rewind();
        self::assertSame(800, getimagesizefromstring((string) $response->getBody())[0]);
        self::assertSame('/shop/image?p=7&f=' . $name . '&s=thumb', Images::url('/shop', 7, $name, 'thumb'));
        $copy = $images->copy(7, 8, $name);
        self::assertFileExists($images->directory(8) . '/' . $copy);
        self::assertNotSame($name, $copy);
        try { $images->response(7, '../x.png', 'list', new Response()); self::fail(); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
        try { $images->response(7, $name, 'huge', new Response()); self::fail(); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
        try { $images->save(7, new UploadedFile((new StreamFactory())->createStream('not an image'), 'x.png', 'image/png', 12, UPLOAD_ERR_OK)); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $images->delete(7, $name);
        self::assertFileDoesNotExist($images->directory(7) . '/' . $name);
        $images->deleteAll(8);
        self::assertDirectoryDoesNotExist($images->directory(8));
    }

    public static function png(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 200, 30, 30));
        ob_start(); imagepng($image); $bytes = (string) ob_get_clean();
        return new UploadedFile((new StreamFactory())->createStream($bytes), 'photo.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK);
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/ImagesTest.php`
Expected: FAIL — `Service::$images`가 없다.

- [ ] **Step 3: Images 작성**

`modules/youngcart/src/Images.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart;

use GnuCms\App;
use GnuCms\Cms\ImageResizer;
use GnuCms\Error\DomainError;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;

final class Images
{
    public const MAX = 10;
    public const SIZES = ['main', 'list', 'type', 'search', 'related', 'detail', 'thumb', 'original'];
    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    private string $root;
    private string $cache;

    public function __construct(private App $app, private Settings $settings)
    {
        $this->root = rtrim((string) $app->config('uploads.dir', $app->storageDir() . '/uploads'), '/') . '/youngcart';
        $this->cache = rtrim($app->storageDir(), '/') . '/cache/youngcart';
    }

    public function directory(int $productId): string { return $this->root . '/' . $productId; }

    public function save(int $productId, UploadedFileInterface $upload): string
    {
        $maxBytes = max(1, (int) $this->app->cmsService()->settings()['attach_max_mb']) * 1048576;
        if ($upload->getError() !== UPLOAD_ERR_OK || ($upload->getSize() ?? 0) < 1 || ($upload->getSize() ?? 0) > $maxBytes) {
            throw DomainError::validation(['images' => ($maxBytes >> 20) . 'MB 이하의 JPG·PNG·WebP·GIF 이미지를 선택해 주세요.']);
        }
        $stream = $upload->getStream();
        if ($stream->isSeekable()) $stream->rewind();
        $bytes = $stream->read($maxBytes + 1);
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > $maxBytes || $info === false || !isset(self::TYPES[$info[2]]) || $info[0] > 8000 || $info[1] > 8000) {
            throw DomainError::validation(['images' => '가로·세로 8,000px 이하의 JPG·PNG·WebP·GIF 이미지를 선택해 주세요.']);
        }
        $extension = strtolower(pathinfo($upload->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if ($extension === 'jpeg') $extension = 'jpg';
        if ($extension !== '' && $extension !== self::TYPES[$info[2]]) throw DomainError::validation(['images' => '파일 확장자와 이미지 형식이 다릅니다.']);
        $directory = $this->directory($productId);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw DomainError::serviceUnavailable('이미지 폴더를 만들지 못했습니다.');
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$info[2]];
        if (file_put_contents($directory . '/' . $name, $bytes, LOCK_EX) !== strlen($bytes)) {
            @unlink($directory . '/' . $name);
            throw DomainError::serviceUnavailable('이미지를 저장하지 못했습니다.');
        }
        return $name;
    }

    public function delete(int $productId, string $filename): void
    {
        if (!self::validName($filename)) return;
        @unlink($this->directory($productId) . '/' . $filename);
        foreach (glob($this->cache . '/' . $productId . '/*-' . $filename) ?: [] as $cached) @unlink($cached);
    }

    public function deleteAll(int $productId): void
    {
        foreach ([$this->directory($productId), $this->cache . '/' . $productId] as $directory) {
            if (!is_dir($directory)) continue;
            foreach (glob($directory . '/*') ?: [] as $file) @unlink($file);
            @rmdir($directory);
        }
    }

    public function copy(int $from, int $to, string $filename): string
    {
        if (!self::validName($filename) || !is_file($this->directory($from) . '/' . $filename)) throw DomainError::notFound('이미지를 찾을 수 없습니다.');
        $directory = $this->directory($to);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw DomainError::serviceUnavailable('이미지 폴더를 만들지 못했습니다.');
        $name = bin2hex(random_bytes(16)) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        if (!copy($this->directory($from) . '/' . $filename, $directory . '/' . $name)) throw DomainError::serviceUnavailable('이미지를 복사하지 못했습니다.');
        return $name;
    }

    /** 크기별 최대 너비. 0은 원본. list는 분류 값이 있으면 그것을 쓴다. */
    public function width(string $size, ?array $category = null): int
    {
        $all = $this->settings->all();
        return match ($size) {
            'main' => max(array_column($all['main'], 'image_width') ?: [0]),
            'list' => $category === null ? (int) $all['category']['image_width'] : (int) $category['image_width'],
            'type' => (int) $all['type']['image_width'],
            'search' => (int) $all['search']['image_width'],
            'related' => (int) $all['related']['image_width'],
            'detail' => (int) $all['detail']['image_width'],
            'thumb' => 70,
            'original' => 0,
            default => throw DomainError::notFound('이미지 크기를 찾을 수 없습니다.'),
        };
    }

    public function response(int $productId, string $filename, string $size, ResponseInterface $response, ?int $width = null): ResponseInterface
    {
        $source = $this->directory($productId) . '/' . $filename;
        if (!self::validName($filename) || !in_array($size, self::SIZES, true) || !is_file($source)) throw DomainError::notFound('이미지를 찾을 수 없습니다.');
        $width ??= $this->width($size);
        $file = $source;
        if ($width > 0) {
            $target = $this->cache . '/' . $productId . '/' . $size . '-' . $filename;
            if (!is_dir(dirname($target))) @mkdir(dirname($target), 0755, true);
            if ((new ImageResizer())->ensure($source, $target, $width)) $file = $target;
        }
        $response->getBody()->write((string) file_get_contents($file));
        return $response->withHeader('Content-Type', self::MIME[pathinfo($filename, PATHINFO_EXTENSION)])
            ->withHeader('X-Content-Type-Options', 'nosniff')->withHeader('Cache-Control', 'public, max-age=86400');
    }

    public static function url(string $publicUrl, int $productId, string $filename, string $size): string
    {
        return $publicUrl . '/image?p=' . $productId . '&f=' . rawurlencode($filename) . '&s=' . $size;
    }

    public static function validName(string $filename): bool
    {
        return preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/D', $filename) === 1;
    }
}
```

`Service`에 `public readonly Images $images;`를 선언하고 생성자에 `$this->images = new Images($app, $this->settings);`를 추가한다.

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/ImagesTest.php`
Expected: PASS

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Images.php modules/youngcart/src/Service.php tests/YoungCart/ImagesTest.php
git commit -m "feat: add youngcart product image storage with sized responses"
```

---

### Task 9: 상품 서비스

**Files:**
- Create: `modules/youngcart/src/Catalog/Products.php`
- Modify: `modules/youngcart/src/Service.php` (`$products`)
- Test: `tests/YoungCart/ProductsTest.php`, `tests/YoungCart/CategoriesTest.php`(둘째 테스트가 이제 통과)

**Interfaces:**
- `Products::__construct(Store $store, HtmlSanitizer $sanitizer, ContentImageService $contentImages, Images $images, Options $options, Categories $categories)`
- `Products::CODE_PATTERN`, `Products::TYPES = ['is_hit','is_recommended','is_new','is_popular','is_discount']`, `Products::APPLY_FIELDS`(적용 그룹 → 컬럼 목록), `Products::SEARCH_FIELDS`, `Products::SORTS`
- `save(array $input, array $files, ?int $id = null, string $actor = 'admin'): int`
  - 입력 키: `code`(생성), `version`(수정), `name`, `category_id`, `category2_id`, `category3_id`, `summary`, `description`, `image_key`, `list_price`, `price`, `point_type`, `point`, `supply_point`, `tax_free`, `seller_email`, `active`, `no_coupon`, `sold_out`, `stock`, `stock_alert`, `buy_min`, `buy_max`, `phone_inquiry`, `shipping_type`, `shipping_method`, `shipping_fee`, `shipping_free_minimum`, `shipping_per_qty`, `head_html`, `tail_html`, `info_group`, `info[]`, `memo`, `is_hit`…`is_discount`, `sort_order`, `extra_label[]`, `extra_value[]`, `option_group[1..3]`, `options[i][…]`, `extras[i][…]`, `relations`(쉼표 id), `image_delete[]`, `image_order`(쉼표 id), `apply_scope`(`category|all`), `apply_fields[]`
  - `$files`는 `UploadedFileInterface` 목록(`images[]`)
- `get(int $id): array` — 행 + `categories`(slot => 분류 행), `images`, `options`(`Options::load` 구조), `relations`(`[id, code, name]`), `info`(디코드), `extra`(디코드), `sold_out_computed`
- `find(int $id): ?array`(행만), `byCode(string $code): ?array`, `bySlug(string $slug): ?array`(둘 다 `get` 구조)
- `copy(int $id, string $newCode, string $actor): int`, `delete(int $id): void`
- `list(array $filters, int $page, int $perPage = 20): array` — `q`, `field`, `ca`, `sort`, `dir`; 반환 `items`(분류 이름 `category_name` 포함), `total`, `page`, `total_pages`
- `bulk(array $rows, string $actor): void`(`rows[id] = [category_id, name, list_price, price, stock, active, sold_out, sort_order]`), `bulkDelete(array $ids): void`
- `setTypes(array $rows): void`(`rows[id] = [is_hit, …]`), `stockList(string $q, int $page, int $perPage): array`, `updateStock(array $rows, string $actor): void`(`rows[id] = [stock, stock_alert, active, sold_out]`)
- `search(string $q, string $ca, ?int $exclude, int $limit = 30): array`(`[id, code, name, price, category_name]`), `stats(): array`(`products, active, sold_out, categories`), `lowStock(int $limit = 20): array`(`['products' => …, 'options' => …]`)

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/ProductsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use GnuCms\Error\DomainError;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProductsTest extends YoungCartTestCase
{
    private function fullInput(int $category, array $overrides = []): array
    {
        return $overrides + ['code' => 'SHIRT-01', 'name' => '<b>여름 셔츠</b>', 'category_id' => (string) $category,
            'summary' => '<p>요약</p><script>x</script>', 'description' => '<h2>설명</h2><p>본문 내용</p>', 'list_price' => '15000', 'price' => '10000',
            'point_type' => '1', 'point' => '5', 'supply_point' => '100', 'tax_free' => '0', 'seller_email' => 'seller@example.test', 'active' => '1', 'no_coupon' => '0',
            'sold_out' => '0', 'stock' => '3', 'stock_alert' => '1', 'buy_min' => '1', 'buy_max' => '5', 'phone_inquiry' => '0',
            'shipping_type' => '2', 'shipping_method' => '0', 'shipping_fee' => '3000', 'shipping_free_minimum' => '50000', 'shipping_per_qty' => '0',
            'head_html' => '<p>위</p>', 'tail_html' => '<p>아래</p>', 'info_group' => 'wear', 'info' => [0 => '면 100%'], 'memo' => '메모', 'is_hit' => '1', 'is_new' => '1', 'sort_order' => '2',
            'extra_label' => [1 => '라벨'], 'extra_value' => [1 => '값'],
            'option_group' => [1 => '색상', 2 => '크기'], 'options' => [
                ['value1' => '빨강', 'value2' => 'S', 'price' => '0', 'stock' => '2', 'stock_alert' => '1', 'active' => '1'],
                ['value1' => '빨강', 'value2' => 'M', 'price' => '500', 'stock' => '0', 'stock_alert' => '1', 'active' => '1']],
            'extras' => [['value1' => '포장', 'value2' => '선물포장', 'price' => '2000', 'stock' => '10', 'stock_alert' => '0', 'active' => '1']]];
    }

    #[DataProvider('connectionProvider')]
    public function testSaveStoresEverythingAndGuardsEdits(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류'); $child = $this->category('셔츠', '10'); $other = $this->category('잡화');
        $related = $this->product(['category_id' => (string) $other['id'], 'code' => 'REL-1', 'name' => '관련상품']);
        $id = $this->shop->products->save($this->fullInput((int) $child['id'], ['category2_id' => (string) $other['id'], 'relations' => $related['id'] . ',']), []);
        $product = $this->shop->products->get($id);
        self::assertSame('SHIRT-01', $product['code']); self::assertSame('여름 셔츠', $product['name']); self::assertSame('여름-셔츠', $product['slug']);
        self::assertSame('<p>요약</p>', $product['summary']); self::assertSame('설명 본문 내용', $product['description_text']);
        self::assertSame((int) $child['id'], (int) $product['category_id']);
        self::assertSame([1 => (int) $child['id'], 2 => (int) $other['id']], array_map(static fn ($c) => (int) $c['id'], $product['categories']));
        self::assertSame(['색상', '크기'], $product['options']['select_groups']); self::assertCount(2, $product['options']['select']); self::assertCount(1, $product['options']['extra']);
        self::assertSame([(int) $related['id']], array_map(static fn ($r) => (int) $r['id'], $product['relations']));
        self::assertSame('면 100%', $product['info'][0]); self::assertSame('상품페이지 참고', $product['info'][1]);
        self::assertSame('라벨', $product['extra'][0]['label']);
        self::assertSame(1, (int) $product['is_hit']); self::assertSame(0, (int) $product['is_popular']);
        self::assertFalse($product['sold_out_computed']);
        self::assertSame(3, (int) $this->shop->store->selectOne('SELECT SUM(delta) AS d FROM ' . $this->shop->store->table('yc_stock_log') . ' WHERE product_id = ? AND option_id IS NULL', [$id])['d']);
        self::assertSame($id, (int) $this->shop->products->byCode('SHIRT-01')['id']);
        self::assertSame($id, (int) $this->shop->products->bySlug('여름-셔츠')['id']);
        $second = $this->shop->products->save($this->fullInput((int) $child['id'], ['code' => 'SHIRT-02']), []);
        self::assertSame('여름-셔츠-2', $this->shop->products->get($second)['slug']);
        $edit = $this->fullInput((int) $child['id'], ['code' => 'IGNORED', 'version' => (string) $product['version'], 'name' => '가을 셔츠', 'stock' => '1',
            'options' => [['value1' => '빨강', 'value2' => 'S', 'price' => '100', 'stock' => '9', 'stock_alert' => '1', 'active' => '1']], 'extras' => []]);
        self::assertSame($id, $this->shop->products->save($edit, [], $id));
        $product = $this->shop->products->get($id);
        self::assertSame('SHIRT-01', $product['code']); self::assertSame('가을-셔츠', $product['slug']); self::assertSame(1, (int) $product['stock']);
        self::assertCount(1, $product['options']['select']); self::assertSame(9, (int) $product['options']['select'][0]['stock']); self::assertSame([], $product['options']['extra']);
        self::assertSame(1, (int) $product['version']);
        try { $this->shop->products->save($edit, [], $id); self::fail('오래된 version은 거절해야 한다'); } catch (DomainError $e) { self::assertArrayHasKey('version', $e->details()); }
        self::assertSame('가을 셔츠', $this->shop->products->get($id)['name']);
    }

    #[DataProvider('connectionProvider')]
    public function testValidationErrors(array $config): void
    {
        $this->setupShop($config);
        $category = $this->category();
        $cases = [
            'code' => ['code' => 'bad code'], 'category_id' => ['category_id' => '999'], 'price' => ['price' => '-1'], 'point' => ['point_type' => '1', 'point' => '100'],
            'shipping_free_minimum' => ['shipping_type' => '2', 'shipping_free_minimum' => '0'], 'shipping_per_qty' => ['shipping_type' => '4', 'shipping_per_qty' => '0'],
            'buy_max' => ['buy_min' => '5', 'buy_max' => '2'], 'seller_email' => ['seller_email' => 'not-mail'], 'relations' => ['relations' => '999'],
            'name' => ['name' => ''], 'category2_id' => ['category2_id' => (string) $category['id']], 'info_group' => ['info_group' => 'nope'],
        ];
        foreach ($cases as $field => $override) {
            try {
                $this->shop->products->save($this->fullInput((int) $category['id'], $override + ['options' => [], 'extras' => [], 'option_group' => []]), []);
                self::fail($field . ' 오류를 거절해야 한다');
            } catch (DomainError $e) {
                self::assertSame(422, $e->status(), $field);
                self::assertArrayHasKey($field, $e->details(), $field . ': ' . json_encode($e->details(), JSON_UNESCAPED_UNICODE));
            }
        }
        $this->shop->products->save($this->fullInput((int) $category['id'], ['options' => [], 'extras' => [], 'option_group' => []]), []);
        try { $this->shop->products->save($this->fullInput((int) $category['id'], ['options' => [], 'extras' => [], 'option_group' => []]), []); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('code', $e->details()); }
        self::assertSame(0, (int) $this->shop->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->shop->store->table('yc_products') . " WHERE code = 'bad code'")['c']);
    }

    #[DataProvider('connectionProvider')]
    public function testImagesCopyDeleteBulkTypesStockAndApply(array $config): void
    {
        $this->setupShop($config);
        $category = $this->category(); $other = $this->category('잡화');
        $base = $this->fullInput((int) $category['id'], ['options' => [], 'extras' => [], 'option_group' => []]);
        $id = $this->shop->products->save($base, [ImagesTest::png(300, 300), ImagesTest::png(400, 200)]);
        $product = $this->shop->products->get($id);
        self::assertCount(2, $product['images']);
        [$first, $second] = $product['images'];
        $this->shop->products->save($base + ['version' => (string) $product['version'], 'image_order' => $second['id'] . ',' . $first['id']], [], $id);
        self::assertSame([(int) $second['id'], (int) $first['id']], array_map(static fn ($i) => (int) $i['id'], $this->shop->products->get($id)['images']));
        $this->shop->products->save($base + ['version' => '1', 'image_delete' => [(string) $second['id']]], [ImagesTest::png(100, 100)], $id);
        $images = $this->shop->products->get($id)['images'];
        self::assertSame([(int) $first['id']], [(int) $images[0]['id']]); self::assertCount(2, $images);
        self::assertFileDoesNotExist($this->shop->images->directory($id) . '/' . $second['filename']);
        try { $this->shop->products->save($base + ['version' => '2'], array_fill(0, 9, ImagesTest::png(50, 50)), $id); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('images', $e->details()); }
        self::assertCount(2, $this->shop->products->get($id)['images']);
        self::assertCount(2, glob($this->shop->images->directory($id) . '/*'));

        $copyId = $this->shop->products->copy($id, 'COPY-1', 'tester');
        $copy = $this->shop->products->get($copyId);
        self::assertSame('COPY-1', $copy['code']); self::assertSame(0, (int) $copy['hit']); self::assertCount(2, $copy['images']);
        self::assertNotSame($images[0]['filename'], $copy['images'][0]['filename']);
        self::assertSame((int) $category['id'], (int) $copy['categories'][1]['id']);
        try { $this->shop->products->copy($id, 'COPY-1', 'tester'); self::fail(); } catch (DomainError $e) { self::assertArrayHasKey('code', $e->details()); }

        $this->shop->products->bulk([$copyId => ['category_id' => (string) $other['id'], 'name' => '일괄', 'list_price' => '0', 'price' => '900', 'stock' => '7', 'active' => '0', 'sold_out' => '1', 'sort_order' => '9']], 'tester');
        $copy = $this->shop->products->get($copyId);
        self::assertSame('일괄', $copy['name']); self::assertSame((int) $other['id'], (int) $copy['categories'][1]['id']); self::assertSame(7, (int) $copy['stock']);
        self::assertSame(4, (int) $this->shop->store->selectOne('SELECT delta FROM ' . $this->shop->store->table('yc_stock_log') . ' WHERE product_id = ? ORDER BY id DESC LIMIT 1', [$copyId])['delta']);
        $this->shop->products->setTypes([$copyId => ['is_hit' => '0', 'is_popular' => '1']]);
        $copy = $this->shop->products->get($copyId);
        self::assertSame(0, (int) $copy['is_hit']); self::assertSame(1, (int) $copy['is_popular']);
        $this->shop->products->updateStock([$copyId => ['stock' => '0', 'stock_alert' => '2', 'active' => '1', 'sold_out' => '0']], 'tester');
        self::assertSame(0, (int) $this->shop->products->get($copyId)['stock']);
        $list = $this->shop->products->stockList('', 1, 20);
        self::assertSame($copyId, (int) $list['items'][0]['id']);
        self::assertSame(1, count($this->shop->products->lowStock()['products']));

        $this->shop->products->save($base + ['version' => '2', 'is_discount' => '1', 'apply_scope' => 'category', 'apply_fields' => ['types']], [], $id);
        self::assertSame(0, (int) $this->shop->products->get($copyId)['is_discount']);
        $this->shop->products->save($base + ['version' => '3', 'is_discount' => '1', 'apply_scope' => 'all', 'apply_fields' => ['types', 'shipping']], [], $id);
        $copy = $this->shop->products->get($copyId);
        self::assertSame(1, (int) $copy['is_discount']); self::assertSame(2, (int) $copy['shipping_type']); self::assertSame('일괄', $copy['name']);

        $stats = $this->shop->products->stats();
        self::assertSame(['products' => 2, 'active' => 2, 'sold_out' => 0, 'categories' => 2], $stats);
        self::assertSame([$copyId], array_map(static fn ($r) => (int) $r['id'], $this->shop->products->search('일괄', '', $id)));
        $this->shop->products->delete($copyId);
        self::assertNull($this->shop->products->find($copyId));
        self::assertDirectoryDoesNotExist($this->shop->images->directory($copyId));
        self::assertSame(0, (int) $this->shop->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->shop->store->table('yc_product_categories') . ' WHERE product_id = ?', [$copyId])['c']);
        $this->shop->products->bulkDelete([$id]);
        self::assertSame(0, $this->shop->products->stats()['products']);
    }

    #[DataProvider('connectionProvider')]
    public function testAdminListFiltersAndSorts(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류'); $child = $this->category('셔츠', '10'); $other = $this->category('잡화');
        $a = $this->product(['category_id' => (string) $child['id'], 'code' => 'A1', 'name' => '파란 셔츠', 'price' => '300']);
        $b = $this->product(['category_id' => (string) $other['id'], 'code' => 'B1', 'name' => '가방', 'price' => '100']);
        $c = $this->product(['category_id' => (string) $top['id'], 'code' => 'C1', 'name' => '빨간 셔츠', 'price' => '200']);
        $all = $this->shop->products->list([], 1);
        self::assertSame(['C1', 'B1', 'A1'], array_column($all['items'], 'code'));
        self::assertSame('셔츠', $all['items'][2]['category_name']);
        self::assertSame(['C1', 'A1'], array_column($this->shop->products->list(['q' => '셔츠'], 1)['items'], 'code'));
        self::assertSame(['A1'], array_column($this->shop->products->list(['q' => 'A1', 'field' => 'code'], 1)['items'], 'code'));
        self::assertSame(['C1', 'A1'], array_column($this->shop->products->list(['ca' => '10'], 1)['items'], 'code'));
        self::assertSame(['B1', 'C1', 'A1'], array_column($this->shop->products->list(['sort' => 'price', 'dir' => 'asc'], 1)['items'], 'code'));
        self::assertSame(['C1', 'B1', 'A1'], array_column($this->shop->products->list(['sort' => 'nope', 'dir' => 'sideways'], 1)['items'], 'code'));
        $page = $this->shop->products->list([], 2, 2);
        self::assertSame(['A1'], array_column($page['items'], 'code')); self::assertSame(2, $page['total_pages']); self::assertSame(3, $page['total']);
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/ProductsTest.php`
Expected: FAIL — `Service::$products`가 없다.

- [ ] **Step 3: Products 작성**

`modules/youngcart/src/Catalog/Products.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Catalog;

use GnuCms\Cms\ContentImageService;
use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Images;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\ProductInfo;
use GnuCms\Modules\YoungCart\Store;
use GnuCms\Support\Clock;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

final class Products
{
    public const CODE_PATTERN = '/^[A-Za-z0-9_-]{1,20}$/D';
    public const TYPES = ['is_hit', 'is_recommended', 'is_new', 'is_popular', 'is_discount'];
    public const APPLY_FIELDS = [
        'types' => self::TYPES, 'active' => ['active'], 'no_coupon' => ['no_coupon'], 'point' => ['point_type', 'point', 'supply_point'],
        'tax_free' => ['tax_free'], 'shipping' => ['shipping_type', 'shipping_method', 'shipping_fee', 'shipping_free_minimum', 'shipping_per_qty'],
        'buy' => ['buy_min', 'buy_max'], 'html' => ['head_html', 'tail_html'], 'seller_email' => ['seller_email'], 'phone_inquiry' => ['phone_inquiry'],
    ];
    public const SEARCH_FIELDS = ['name', 'code'];
    public const SORTS = ['code', 'name', 'sort_order', 'active', 'sold_out', 'hit', 'price', 'list_price', 'point', 'stock'];
    private const MAX_RELATIONS = 50;

    public function __construct(private Store $store, private HtmlSanitizer $sanitizer, private ContentImageService $contentImages,
        private Images $images, private Options $options, private Categories $categories) {}

    public function save(array $input, array $files, ?int $id = null, string $actor = 'admin'): int
    {
        $existing = $id === null ? null : $this->store->get('yc_products', $id);
        $row = $this->validate($input, $existing);
        $categoryIds = $this->categoryIds($input);
        $row['category_id'] = $categoryIds[1];
        $groups = [];
        for ($i = 1; $i <= Options::MAX_GROUPS; $i++) $groups[] = is_string($input['option_group'][$i] ?? null) ? $input['option_group'][$i] : '';
        $options = $this->options->validate($row['price'], $groups, Options::rows($input['options'] ?? []), Options::rows($input['extras'] ?? []));
        $relations = $this->relationIds($input['relations'] ?? '', $id);
        $uploads = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) $uploads[] = $file;
        }
        $deleteIds = [];
        foreach (is_array($input['image_delete'] ?? null) ? $input['image_delete'] : [] as $value) if ($value !== '') $deleteIds[] = Input::id($value, 'image_delete');
        $orderIds = [];
        foreach (array_filter(explode(',', is_string($input['image_order'] ?? null) ? $input['image_order'] : '')) as $value) $orderIds[] = Input::id(trim($value), 'image_order');
        $imageKey = is_string($input['image_key'] ?? null) && preg_match('/^[a-f0-9]{32}$/D', $input['image_key']) ? $input['image_key'] : null;
        $version = $existing === null ? 0 : Input::int($input['version'] ?? '', 'version', 0, PHP_INT_MAX, -1);
        $saved = [];
        $removed = [];
        try {
            $productId = $this->store->transaction(function () use ($existing, $id, $row, $version, $categoryIds, $options, $relations, $uploads, $deleteIds, $orderIds, $actor, &$saved, &$removed): int {
                $now = Clock::timestamp();
                if ($existing !== null) {
                    if ($this->store->execute('UPDATE ' . $this->store->table('yc_products') . ' SET version = version + 1 WHERE id = ? AND version = ?', [$id, $version]) !== 1) {
                        throw DomainError::validation(['version' => '다른 관리자가 먼저 저장했습니다. 새로고침 후 다시 입력해 주세요.']);
                    }
                    $this->store->update('yc_products', $id, $row + ['updated_at' => $now]);
                    $productId = $id;
                    if ((int) $existing['stock'] !== $row['stock']) $this->store->logStock($productId, null, $row['stock'] - (int) $existing['stock'], 'admin', 'product', $actor);
                } else {
                    $productId = $this->store->insert('yc_products', $row + ['created_at' => $now, 'updated_at' => $now]);
                    if ($row['stock'] !== 0) $this->store->logStock($productId, null, $row['stock'], 'admin', 'product', $actor);
                }
                $this->store->delete('yc_product_categories', 'product_id = ?', [$productId]);
                foreach ($categoryIds as $slot => $categoryId) $this->store->insert('yc_product_categories', ['product_id' => $productId, 'category_id' => $categoryId, 'slot' => $slot]);
                $this->options->replace($productId, $options, $actor);
                $this->store->delete('yc_product_relations', 'product_id = ?', [$productId]);
                foreach ($relations as $index => $relatedId) $this->store->insert('yc_product_relations', ['product_id' => $productId, 'related_id' => $relatedId, 'sort_order' => $index]);
                $images = $this->store->select('SELECT * FROM ' . $this->store->table('yc_product_images') . ' WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
                $kept = [];
                foreach ($images as $image) {
                    if (in_array((int) $image['id'], $deleteIds, true)) {
                        $this->store->delete('yc_product_images', 'id = ?', [(int) $image['id']]);
                        $removed[] = [$productId, $image['filename']];
                    } else $kept[(int) $image['id']] = $image;
                }
                $ordered = [];
                foreach ($orderIds as $imageId) if (isset($kept[$imageId])) { $ordered[] = $kept[$imageId]; unset($kept[$imageId]); }
                $ordered = [...$ordered, ...array_values($kept)];
                if (count($ordered) + count($uploads) > Images::MAX) throw DomainError::validation(['images' => '상품 이미지는 ' . Images::MAX . '장까지 등록할 수 있습니다.']);
                foreach ($uploads as $upload) {
                    $name = $this->images->save($productId, $upload);
                    $saved[] = [$productId, $name];
                    $ordered[] = ['id' => $this->store->insert('yc_product_images', ['product_id' => $productId, 'filename' => $name, 'sort_order' => 0])];
                }
                foreach ($ordered as $position => $image) $this->store->update('yc_product_images', (int) $image['id'], ['sort_order' => $position]);
                return $productId;
            });
        } catch (Throwable $e) {
            foreach ($saved as [$pid, $name]) $this->images->delete($pid, $name);
            throw $e;
        }
        foreach ($removed as [$pid, $name]) $this->images->delete($pid, $name);
        if ($imageKey !== null) $this->contentImages->sync($imageKey, $row['description']);
        $this->applyScope($input, $row, $productId);
        return $productId;
    }

    private function validate(array $input, ?array $existing): array
    {
        $row = ['name' => Input::text(strip_tags((string) ($input['name'] ?? '')), 'name', 250, false)];
        if ($existing === null) {
            $row['code'] = Input::code($input['code'] ?? '', 'code', self::CODE_PATTERN, '상품 코드는 영문·숫자·-·_ 1~20자입니다.');
            if ($this->store->selectOne('SELECT id FROM ' . $this->store->table('yc_products') . ' WHERE code = ?', [$row['code']]) !== null) {
                throw DomainError::validation(['code' => '이미 사용 중인 상품 코드입니다.']);
            }
        }
        $row['slug'] = $this->uniqueSlug(Input::slug($row['name'], $row['code'] ?? $existing['code']), $existing === null ? null : (int) $existing['id']);
        $row['summary'] = Input::html($input['summary'] ?? '', 'summary', $this->sanitizer, 20000);
        $row['description'] = Input::html($input['description'] ?? '', 'description', $this->sanitizer, 500000);
        $row['description_text'] = Input::plain($row['description']);
        $row['list_price'] = Input::int($input['list_price'] ?? '', 'list_price', 0, 10000000000, 0);
        $row['price'] = Input::int($input['price'] ?? '', 'price', 0, 10000000000);
        $row['point_type'] = Input::int($input['point_type'] ?? '', 'point_type', 0, 2, 0);
        $row['point'] = Input::int($input['point'] ?? '', 'point', 0, $row['point_type'] === 0 ? 10000000 : 99, 0);
        $row['supply_point'] = Input::int($input['supply_point'] ?? '', 'supply_point', 0, 10000000, 0);
        foreach (['tax_free', 'active', 'no_coupon', 'sold_out', 'phone_inquiry', ...self::TYPES] as $field) $row[$field] = Input::bool($input[$field] ?? '0');
        $row['seller_email'] = Input::text($input['seller_email'] ?? '', 'seller_email', 191);
        if ($row['seller_email'] !== '' && filter_var($row['seller_email'], FILTER_VALIDATE_EMAIL) === false) throw DomainError::validation(['seller_email' => '판매자 이메일을 확인해 주세요.']);
        $row['stock'] = Input::int($input['stock'] ?? '', 'stock', 0, 1000000, 0);
        $row['stock_alert'] = Input::int($input['stock_alert'] ?? '', 'stock_alert', 0, 1000000, 0);
        $row['buy_min'] = Input::int($input['buy_min'] ?? '', 'buy_min', 0, 9999, 0);
        $row['buy_max'] = Input::int($input['buy_max'] ?? '', 'buy_max', 0, 9999, 0);
        if ($row['buy_max'] !== 0 && $row['buy_max'] < $row['buy_min']) throw DomainError::validation(['buy_max' => '최대 구매수량은 최소 구매수량 이상이어야 합니다.']);
        $row['shipping_type'] = Input::int($input['shipping_type'] ?? '', 'shipping_type', 0, 4, 0);
        $row['shipping_method'] = Input::int($input['shipping_method'] ?? '', 'shipping_method', 0, 2, 0);
        $row['shipping_fee'] = Input::int($input['shipping_fee'] ?? '', 'shipping_fee', 0, 100000000, 0);
        $row['shipping_free_minimum'] = Input::int($input['shipping_free_minimum'] ?? '', 'shipping_free_minimum', 0, 10000000000, 0);
        $row['shipping_per_qty'] = Input::int($input['shipping_per_qty'] ?? '', 'shipping_per_qty', 0, 9999, 0);
        if ($row['shipping_type'] >= 2 && $row['shipping_fee'] < 1) throw DomainError::validation(['shipping_fee' => '배송비를 입력해 주세요.']);
        if ($row['shipping_type'] === 2 && $row['shipping_free_minimum'] < 1) throw DomainError::validation(['shipping_free_minimum' => '무료배송 기준 금액을 입력해 주세요.']);
        if ($row['shipping_type'] === 4 && $row['shipping_per_qty'] < 1) throw DomainError::validation(['shipping_per_qty' => '배송비를 부과할 수량 단위를 입력해 주세요.']);
        $row['head_html'] = Input::html($input['head_html'] ?? '', 'head_html', $this->sanitizer);
        $row['tail_html'] = Input::html($input['tail_html'] ?? '', 'tail_html', $this->sanitizer);
        $row['info_group'] = Input::text($input['info_group'] ?? '', 'info_group', 50);
        $row['info_values'] = ProductInfo::normalize($row['info_group'], is_array($input['info'] ?? null) ? $input['info'] : []);
        $row['memo'] = Input::text($input['memo'] ?? '', 'memo', 5000);
        $row['sort_order'] = Input::int($input['sort_order'] ?? '', 'sort_order', -999999, 999999, 0);
        $row['extra'] = Input::extra($input);
        return $row;
    }

    private function uniqueSlug(string $base, ?int $excludeId): string
    {
        $taken = array_column($this->store->select('SELECT slug FROM ' . $this->store->table('yc_products') . ' WHERE slug LIKE ? ESCAPE \'!\'' . ($excludeId === null ? '' : ' AND id <> ' . $excludeId),
            [str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $base) . '%']), 'slug');
        if (!in_array($base, $taken, true)) return $base;
        for ($n = 2; ; $n++) if (!in_array($base . '-' . $n, $taken, true)) return $base . '-' . $n;
    }

    /** @return array<int,int> slot => category id */
    private function categoryIds(array $input): array
    {
        $ids = [1 => Input::optionalId($input['category_id'] ?? '') ?? throw DomainError::validation(['category_id' => '대표 분류를 선택해 주세요.'])];
        foreach ([2 => 'category2_id', 3 => 'category3_id'] as $slot => $field) {
            $id = Input::optionalId($input[$field] ?? '');
            if ($id !== null) $ids[$slot] = $id;
        }
        foreach ($ids as $slot => $id) {
            $field = $slot === 1 ? 'category_id' : 'category' . $slot . '_id';
            if ($this->store->find('yc_categories', $id) === null) throw DomainError::validation([$field => '분류를 찾을 수 없습니다.']);
        }
        foreach ([2, 3] as $slot) {
            $earlier = array_filter($ids, static fn (int $s): bool => $s < $slot, ARRAY_FILTER_USE_KEY);
            if (isset($ids[$slot]) && in_array($ids[$slot], $earlier, true)) {
                throw DomainError::validation(['category' . $slot . '_id' => '같은 분류를 두 번 지정할 수 없습니다.']);
            }
        }
        return $ids;
    }

    /** @return list<int> */
    private function relationIds(mixed $raw, ?int $selfId): array
    {
        $ids = [];
        foreach (Input::csv($raw, self::MAX_RELATIONS, 20, 'relations') as $value) {
            $id = Input::optionalId($value) ?? throw DomainError::validation(['relations' => '관련상품 지정을 확인해 주세요.']);
            if ($id === $selfId) throw DomainError::validation(['relations' => '자기 자신을 관련상품으로 지정할 수 없습니다.']);
            if ($this->store->find('yc_products', $id) === null) throw DomainError::validation(['relations' => '관련상품을 찾을 수 없습니다.']);
            if (!in_array($id, $ids, true)) $ids[] = $id;
        }
        return $ids;
    }

    private function applyScope(array $input, array $row, int $productId): void
    {
        $scope = $input['apply_scope'] ?? '';
        if (!in_array($scope, ['category', 'all'], true)) return;
        $columns = [];
        foreach (is_array($input['apply_fields'] ?? null) ? $input['apply_fields'] : [] as $group) {
            foreach (self::APPLY_FIELDS[$group] ?? [] as $column) $columns[$column] = $row[$column];
        }
        if ($columns === []) return;
        $columns['updated_at'] = Clock::timestamp();
        if ($scope === 'category') $this->store->db->update('yc_products', $columns, 'category_id = :category', ['category' => $row['category_id']]);
        else $this->store->db->update('yc_products', $columns, 'id <> :none', ['none' => 0]);
    }

    public function find(int $id): ?array { return $this->store->find('yc_products', $id); }

    public function get(int $id): array { return $this->hydrate($this->store->get('yc_products', $id)); }

    public function byCode(string $code): ?array
    {
        $row = $this->store->selectOne('SELECT * FROM ' . $this->store->table('yc_products') . ' WHERE code = ?', [$code]);
        return $row === null ? null : $this->hydrate($row);
    }

    public function bySlug(string $slug): ?array
    {
        $row = $this->store->selectOne('SELECT * FROM ' . $this->store->table('yc_products') . ' WHERE slug = ?', [$slug]);
        return $row === null ? null : $this->hydrate($row);
    }

    private function hydrate(array $row): array
    {
        $id = (int) $row['id'];
        $row['categories'] = [];
        foreach ($this->store->select('SELECT pc.slot, c.* FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories') . ' c ON c.id = pc.category_id WHERE pc.product_id = ? ORDER BY pc.slot', [$id]) as $category) {
            $row['categories'][(int) $category['slot']] = $category;
        }
        $row['images'] = $this->store->select('SELECT * FROM ' . $this->store->table('yc_product_images') . ' WHERE product_id = ? ORDER BY sort_order, id', [$id]);
        $row['options'] = $this->options->load($id);
        $row['relations'] = $this->store->select('SELECT p.id, p.code, p.name FROM ' . $this->store->table('yc_product_relations') . ' r JOIN ' . $this->store->table('yc_products') . ' p ON p.id = r.related_id WHERE r.product_id = ? ORDER BY r.sort_order, p.id', [$id]);
        $row['info'] = ProductInfo::decode((string) $row['info_values']);
        $extra = json_decode((string) $row['extra'], true);
        $row['extra'] = is_array($extra) ? $extra : [];
        $row['sold_out_computed'] = Options::soldOut($row, $row['options']['select']);
        return $row;
    }

    public function copy(int $id, string $newCode, string $actor): int
    {
        $source = $this->get($id);
        $code = Input::code($newCode, 'code', self::CODE_PATTERN, '상품 코드는 영문·숫자·-·_ 1~20자입니다.');
        if ($this->store->selectOne('SELECT id FROM ' . $this->store->table('yc_products') . ' WHERE code = ?', [$code]) !== null) throw DomainError::validation(['code' => '이미 사용 중인 상품 코드입니다.']);
        $copied = [];
        try {
            return $this->store->transaction(function () use ($source, $code, $actor, &$copied): int {
                $row = $source;
                unset($row['id'], $row['categories'], $row['images'], $row['options'], $row['relations'], $row['info'], $row['sold_out_computed']);
                $row['extra'] = $source['extra'] === [] ? '[]' : json_encode($source['extra'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $row['code'] = $code;
                $row['slug'] = $this->uniqueSlug(Input::slug($source['name'], $code), null);
                $row['hit'] = 0; $row['sold_qty'] = 0; $row['review_count'] = 0; $row['review_avg'] = 0; $row['version'] = 0;
                $row['created_at'] = $row['updated_at'] = Clock::timestamp();
                $newId = $this->store->insert('yc_products', $row);
                if ((int) $row['stock'] !== 0) $this->store->logStock($newId, null, (int) $row['stock'], 'admin', 'copy', $actor);
                foreach ($source['categories'] as $slot => $category) $this->store->insert('yc_product_categories', ['product_id' => $newId, 'category_id' => (int) $category['id'], 'slot' => $slot]);
                $options = $source['options'];
                foreach (['select', 'extra'] as $kind) {
                    foreach ($options[$kind] as &$option) {
                        unset($option['id'], $option['product_id'], $option['kind']);
                        foreach (['price', 'stock', 'stock_alert', 'active', 'sort_order'] as $column) $option[$column] = (int) $option[$column];
                    }
                    unset($option);
                }
                $this->options->replace($newId, $options, $actor);
                foreach ($source['relations'] as $index => $related) $this->store->insert('yc_product_relations', ['product_id' => $newId, 'related_id' => (int) $related['id'], 'sort_order' => $index]);
                foreach ($source['images'] as $position => $image) {
                    $name = $this->images->copy((int) $source['id'], $newId, $image['filename']);
                    $copied[] = [$newId, $name];
                    $this->store->insert('yc_product_images', ['product_id' => $newId, 'filename' => $name, 'sort_order' => $position]);
                }
                return $newId;
            });
        } catch (Throwable $e) {
            foreach ($copied as [$pid, $name]) $this->images->delete($pid, $name);
            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $this->store->get('yc_products', $id);
        $this->store->transaction(function () use ($id): void {
            foreach (['yc_option_groups', 'yc_options', 'yc_product_categories', 'yc_product_images', 'yc_stock_log'] as $table) $this->store->delete($table, 'product_id = ?', [$id]);
            $this->store->delete('yc_product_relations', 'product_id = ? OR related_id = ?', [$id, $id]);
            $this->store->delete('yc_products', 'id = ?', [$id]);
        });
        $this->images->deleteAll($id);
    }

    public function bulkDelete(array $ids): void
    {
        foreach ($ids as $id) $this->delete(Input::id($id));
    }

    public function list(array $filters, int $page, int $perPage = 20): array
    {
        $where = ['1 = 1']; $params = [];
        $q = Input::text($filters['q'] ?? '', 'q', 100);
        if ($q !== '') {
            $field = in_array($filters['field'] ?? '', self::SEARCH_FIELDS, true) ? $filters['field'] : 'name';
            $where[] = 'p.' . $field . ' LIKE ? ESCAPE \'!\''; $params[] = '%' . self::like($q) . '%';
        }
        $ca = Input::text($filters['ca'] ?? '', 'ca', 10);
        if ($ca !== '' && preg_match('/^[0-9a-z]{2,10}$/D', $ca)) {
            $where[] = 'EXISTS (SELECT 1 FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories') . ' c2 ON c2.id = pc.category_id WHERE pc.product_id = p.id AND c2.code LIKE ?)';
            $params[] = $ca . '%';
        }
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? 'p.' . $filters['sort'] : 'p.id';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $from = ' FROM ' . $this->store->table('yc_products') . ' p LEFT JOIN ' . $this->store->table('yc_categories') . ' c ON c.id = p.category_id WHERE ' . implode(' AND ', $where);
        $total = (int) $this->store->selectOne('SELECT COUNT(*) AS c' . $from, $params)['c'];
        $items = $this->store->select('SELECT p.*, c.name AS category_name' . $from . ' ORDER BY ' . $sort . ' ' . $dir . ', p.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'total_pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function bulk(array $rows, string $actor): void
    {
        $this->store->transaction(function () use ($rows, $actor): void {
            foreach ($rows as $id => $input) {
                $id = Input::id($id);
                $old = $this->store->get('yc_products', $id);
                try {
                    $categoryId = Input::id($input['category_id'] ?? '', 'category_id');
                    if ($this->store->find('yc_categories', $categoryId) === null) throw DomainError::validation(['category_id' => '분류를 찾을 수 없습니다.']);
                    $data = ['category_id' => $categoryId, 'name' => Input::text(strip_tags((string) ($input['name'] ?? '')), 'name', 250, false),
                        'list_price' => Input::int($input['list_price'] ?? '', 'list_price', 0, 10000000000, 0), 'price' => Input::int($input['price'] ?? '', 'price', 0, 10000000000),
                        'stock' => Input::int($input['stock'] ?? '', 'stock', 0, 1000000, 0), 'active' => Input::bool($input['active'] ?? '0'), 'sold_out' => Input::bool($input['sold_out'] ?? '0'),
                        'sort_order' => Input::int($input['sort_order'] ?? '', 'sort_order', -999999, 999999, 0), 'updated_at' => Clock::timestamp()];
                } catch (DomainError $e) {
                    throw DomainError::validation(['row_' . $id => implode(' ', $e->details())]);
                }
                if ($data['name'] !== $old['name']) $data['slug'] = $this->uniqueSlug(Input::slug($data['name'], $old['code']), $id);
                $this->store->update('yc_products', $id, $data);
                if ((int) $old['category_id'] !== $categoryId) {
                    $this->store->delete('yc_product_categories', 'product_id = ? AND (slot = 1 OR category_id = ?)', [$id, $categoryId]);
                    $this->store->insert('yc_product_categories', ['product_id' => $id, 'category_id' => $categoryId, 'slot' => 1]);
                }
                if ((int) $old['stock'] !== $data['stock']) $this->store->logStock($id, null, $data['stock'] - (int) $old['stock'], 'admin', 'bulk', $actor);
            }
        });
    }

    public function setTypes(array $rows): void
    {
        $this->store->transaction(function () use ($rows): void {
            foreach ($rows as $id => $input) {
                $id = Input::id($id);
                $this->store->get('yc_products', $id);
                $data = ['updated_at' => Clock::timestamp()];
                foreach (self::TYPES as $type) $data[$type] = Input::bool($input[$type] ?? '0');
                $this->store->update('yc_products', $id, $data);
            }
        });
    }

    public function stockList(string $q, int $page, int $perPage): array
    {
        $where = ''; $params = [];
        if ($q !== '') { $where = ' WHERE (p.name LIKE ? OR p.code LIKE ?)'; $params = ['%' . $q . '%', '%' . $q . '%']; }
        $total = (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->store->table('yc_products') . ' p' . $where, $params)['c'];
        $items = $this->store->select('SELECT p.id, p.code, p.name, p.stock, p.stock_alert, p.active, p.sold_out FROM ' . $this->store->table('yc_products') . ' p' . $where
            . ' ORDER BY p.stock ASC, p.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'total_pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function updateStock(array $rows, string $actor): void
    {
        $this->store->transaction(function () use ($rows, $actor): void {
            foreach ($rows as $id => $input) {
                $id = Input::id($id);
                $old = $this->store->get('yc_products', $id);
                $stock = Input::int($input['stock'] ?? '', 'stock', 0, 1000000);
                $this->store->update('yc_products', $id, ['stock' => $stock, 'stock_alert' => Input::int($input['stock_alert'] ?? '', 'stock_alert', 0, 1000000, 0),
                    'active' => Input::bool($input['active'] ?? '0'), 'sold_out' => Input::bool($input['sold_out'] ?? '0'), 'updated_at' => Clock::timestamp()]);
                if ($stock !== (int) $old['stock']) $this->store->logStock($id, null, $stock - (int) $old['stock'], 'admin', 'stock', $actor);
            }
        });
    }

    public function search(string $q, string $ca, ?int $exclude, int $limit = 30): array
    {
        $where = ['p.id <> ?']; $params = [$exclude ?? 0];
        if ($q !== '') { $where[] = '(p.name LIKE ? ESCAPE \'!\' OR p.code LIKE ? ESCAPE \'!\')'; $params[] = '%' . self::like($q) . '%'; $params[] = '%' . self::like($q) . '%'; }
        if ($ca !== '' && preg_match('/^[0-9a-z]{2,10}$/D', $ca)) { $where[] = 'c.code LIKE ?'; $params[] = $ca . '%'; }
        return $this->store->select('SELECT p.id, p.code, p.name, p.price, c.name AS category_name FROM ' . $this->store->table('yc_products') . ' p LEFT JOIN ' . $this->store->table('yc_categories')
            . ' c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name, p.id LIMIT ' . $limit, $params);
    }

    public function stats(): array
    {
        $p = $this->store->table('yc_products');
        return ['products' => (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $p)['c'],
            'active' => (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $p . ' WHERE active = 1')['c'],
            'sold_out' => (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $p . ' WHERE sold_out = 1')['c'],
            'categories' => $this->categories->count()];
    }

    public function lowStock(int $limit = 20): array
    {
        return ['products' => $this->store->select('SELECT id, code, name, stock, stock_alert FROM ' . $this->store->table('yc_products') . ' WHERE stock_alert > 0 AND stock <= stock_alert ORDER BY stock ASC, id DESC LIMIT ' . $limit),
            'options' => $this->store->select('SELECT o.id, o.product_id, o.value1, o.value2, o.value3, o.stock, o.stock_alert, p.name FROM ' . $this->store->table('yc_options') . ' o JOIN ' . $this->store->table('yc_products')
                . ' p ON p.id = o.product_id WHERE o.stock_alert > 0 AND o.stock <= o.stock_alert ORDER BY o.stock ASC, o.id DESC LIMIT ' . $limit)];
    }

    public static function like(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
```

`Service`에 `public readonly Catalog\Products $products;`를 선언하고 생성자 마지막에 추가한다(`$options`·`$images`·`$categories` 뒤):

```php
        $this->products = new Catalog\Products($this->store, $app->htmlSanitizer(), $app->contentImages(), $this->images, $this->options, $this->categories);
```

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/ProductsTest.php tests/YoungCart/CategoriesTest.php`
Expected: PASS

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Catalog/Products.php modules/youngcart/src/Service.php tests/YoungCart/ProductsTest.php
git commit -m "feat: add youngcart product service with transactional save, copy and bulk edits"
```

---

### Task 10: 목록·검색·메인 조회

**Files:**
- Create: `modules/youngcart/src/Catalog/Listing.php`
- Modify: `modules/youngcart/src/Service.php` (`$listing`)
- Test: `tests/YoungCart/ListingTest.php`

**Interfaces:**
- `Listing::__construct(Store $store, Settings $settings, Options $options)`
- `Listing::SORTS = ['name' => 'p.name', 'sold' => 'p.sold_qty', 'price' => 'p.price', 'rating' => 'p.review_avg', 'reviews' => 'p.review_count', 'recent' => 'p.updated_at']`, `Listing::SORT_LABELS`
- `category(array $category, string $sort, string $dir, int $page): array`
- `type(string $type, string $sort, string $dir, int $page): array`(`type`은 `Settings::TYPES` 키)
- `search(string $q, string $ca, int $min, int $max, string $sort, string $dir, int $page): array`(+ `facets`, `words`)
- `main(): array`(`type => items`, `use`인 블록만)
- `related(int $productId): array`, `adjacent(array $product): array`(`['prev' => ?row, 'next' => ?row]`)
- 반환 목록 구조: `['items' => [...], 'page' => int, 'total' => int, 'total_pages' => int, 'per_page' => int, 'columns' => int]`. 각 item에는 행 + `image`(대표 파일명 또는 null) + `sold_out`(bool).

- [ ] **Step 1: 실패하는 테스트 작성**

`tests/YoungCart/ListingTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\YoungCart;

use PHPUnit\Framework\Attributes\DataProvider;

final class ListingTest extends YoungCartTestCase
{
    #[DataProvider('connectionProvider')]
    public function testCategoryPrefixSlotsVisibilitySortAndPaging(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류'); $child = $this->category('셔츠', '10'); $hidden = $this->category('숨김', '10', ['active' => '0']); $other = $this->category('잡화');
        $a = $this->product(['category_id' => (string) $child['id'], 'code' => 'A', 'name' => '가', 'price' => '300', 'sort_order' => '2']);
        $b = $this->product(['category_id' => (string) $other['id'], 'category2_id' => (string) $top['id'], 'code' => 'B', 'name' => '나', 'price' => '100', 'sort_order' => '1']);
        $c = $this->product(['category_id' => (string) $hidden['id'], 'code' => 'C', 'name' => '다', 'price' => '200']);
        $d = $this->product(['category_id' => (string) $child['id'], 'code' => 'D', 'name' => '라', 'price' => '50', 'active' => '0']);
        $e = $this->product(['category_id' => (string) $other['id'], 'code' => 'E', 'name' => '마', 'price' => '400', 'stock' => '0']);
        $list = $this->shop->listing->category($top, '', '', 1);
        self::assertSame(['B', 'A'], array_column($list['items'], 'code'));
        self::assertSame(15, $list['per_page']); self::assertSame(3, $list['columns']); self::assertSame(1, $list['total_pages']);
        self::assertSame(['A', 'B'], array_column($this->shop->listing->category($top, 'price', 'desc', 1)['items'], 'code'));
        self::assertSame(['B', 'A'], array_column($this->shop->listing->category($top, 'bogus', 'up', 1)['items'], 'code'));
        self::assertSame([], $this->shop->listing->category($top, '', '', 2)['items']);
        $small = $this->shop->categories->get((int) $other['id']);
        $this->shop->categories->save(['name' => '잡화', 'active' => '1', 'list_columns' => '1', 'list_rows' => '1', 'image_width' => '200', 'image_height' => '0'], (int) $other['id']);
        $small = $this->shop->categories->get((int) $other['id']);
        $page1 = $this->shop->listing->category($small, 'price', 'asc', 1); $page2 = $this->shop->listing->category($small, 'price', 'asc', 2);
        self::assertSame(['B'], array_column($page1['items'], 'code')); self::assertSame(['E'], array_column($page2['items'], 'code'));
        self::assertSame(2, $page1['total_pages']); self::assertSame(2, $page1['total']);
        self::assertTrue($page2['items'][0]['sold_out']); self::assertFalse($page1['items'][0]['sold_out']);
        self::assertNull($page1['items'][0]['image']);
    }

    #[DataProvider('connectionProvider')]
    public function testTypeSearchFacetsMainRelatedAndAdjacent(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류'); $other = $this->category('잡화');
        $a = $this->product(['category_id' => (string) $top['id'], 'code' => 'A', 'name' => '파란 셔츠', 'summary' => '여름 상품', 'price' => '300', 'is_hit' => '1', 'is_popular' => '1', 'sort_order' => '1']);
        $b = $this->product(['category_id' => (string) $top['id'], 'code' => 'B', 'name' => '빨간 셔츠', 'description' => '<p>겨울 상품</p>', 'price' => '100', 'is_hit' => '1', 'sort_order' => '2', 'relations' => (string) $a['id']]);
        $c = $this->product(['category_id' => (string) $other['id'], 'code' => 'C', 'name' => '가방', 'price' => '200', 'is_new' => '1', 'sort_order' => '3']);
        self::assertSame(['A', 'B'], array_column($this->shop->listing->type('hit', '', '', 1)['items'], 'code'));
        self::assertSame(['C'], array_column($this->shop->listing->type('new', '', '', 1)['items'], 'code'));
        self::assertSame(['A'], array_column($this->shop->listing->type('popular', '', '', 1)['items'], 'code'));
        $search = $this->shop->listing->search('셔츠 상품', '', 0, 0, '', '', 1);
        self::assertSame(['A', 'B'], array_column($search['items'], 'code'));
        self::assertSame(['셔츠', '상품'], $search['words']);
        self::assertSame([['code' => '10', 'name' => '의류', 'count' => 2]], $search['facets']);
        self::assertSame(['B'], array_column($this->shop->listing->search('셔츠', '', 0, 150, '', '', 1)['items'], 'code'));
        self::assertSame(['C'], array_column($this->shop->listing->search('가방', '20', 0, 0, '', '', 1)['items'], 'code'));
        self::assertSame([], $this->shop->listing->search('100%', '', 0, 0, '', '', 1)['items']);
        self::assertSame(['B', 'A'], array_column($this->shop->listing->search('셔츠', '', 0, 0, 'price', 'asc', 1)['items'], 'code'));
        $main = $this->shop->listing->main();
        self::assertSame(['hit', 'new', 'recommend', 'discount'], array_keys($main));
        self::assertSame(['A', 'B'], array_column($main['hit'], 'code')); self::assertSame([], $main['recommend']);
        self::assertSame(['A'], array_column($this->shop->listing->related((int) $b['id']), 'code'));
        self::assertSame(['B'], array_column($this->shop->listing->related((int) $a['id']), 'code'));
        $adjacent = $this->shop->listing->adjacent($this->shop->products->get((int) $b['id']));
        self::assertSame('A', $adjacent['prev']['code']); self::assertNull($adjacent['next']);
        $adjacent = $this->shop->listing->adjacent($this->shop->products->get((int) $a['id']));
        self::assertNull($adjacent['prev']); self::assertSame('B', $adjacent['next']['code']);
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/YoungCart/ListingTest.php`
Expected: FAIL — `Service::$listing`이 없다.

- [ ] **Step 3: Listing 작성**

`modules/youngcart/src/Catalog/Listing.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Catalog;

use GnuCms\Modules\YoungCart\Settings;
use GnuCms\Modules\YoungCart\Store;

final class Listing
{
    public const SORTS = ['name' => 'p.name', 'sold' => 'p.sold_qty', 'price' => 'p.price', 'rating' => 'p.review_avg', 'reviews' => 'p.review_count', 'recent' => 'p.updated_at'];
    public const SORT_LABELS = ['sold_desc' => '판매많은순', 'price_asc' => '낮은가격순', 'price_desc' => '높은가격순', 'rating_desc' => '평점높은순', 'reviews_desc' => '후기많은순', 'recent_desc' => '최근등록순', 'name_asc' => '이름순'];
    private const DEFAULT_ORDER = 'p.sort_order ASC, p.id DESC';

    public function __construct(private Store $store, private Settings $settings, private Options $options) {}

    public function category(array $category, string $sort, string $dir, int $page): array
    {
        $where = 'p.active = 1 AND EXISTS (SELECT 1 FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories')
            . ' c ON c.id = pc.category_id WHERE pc.product_id = p.id AND c.active = 1 AND c.code LIKE ?)';
        return $this->paginate($where, [$category['code'] . '%'], $this->order($sort, $dir), $page, (int) $category['list_columns'], (int) $category['list_rows']);
    }

    public function type(string $type, string $sort, string $dir, int $page): array
    {
        $column = Settings::TYPE_COLUMNS[$type] ?? throw \GnuCms\Error\DomainError::notFound('상품 유형을 찾을 수 없습니다.');
        $block = $this->settings->block('type');
        return $this->paginate($this->visible() . ' AND p.' . $column . ' = 1', [], $this->order($sort, $dir), $page, (int) $block['columns'], (int) $block['rows']);
    }

    public function search(string $q, string $ca, int $min, int $max, string $sort, string $dir, int $page): array
    {
        $words = array_values(array_unique(array_filter(preg_split('/\s+/u', mb_substr(trim($q), 0, 50, 'UTF-8')) ?: [], static fn (string $w): bool => $w !== '')));
        $block = $this->settings->block('search');
        if ($words === []) return ['items' => [], 'page' => 1, 'total' => 0, 'total_pages' => 1, 'per_page' => $block['columns'] * $block['rows'], 'columns' => $block['columns'], 'facets' => [], 'words' => []];
        $where = [$this->visible()]; $params = [];
        foreach ($words as $word) {
            $like = '%' . Products::like($word) . '%';
            $where[] = '(p.name LIKE ? ESCAPE \'!\' OR p.code LIKE ? ESCAPE \'!\' OR p.summary LIKE ? ESCAPE \'!\' OR p.description_text LIKE ? ESCAPE \'!\')';
            array_push($params, $like, $like, $like, $like);
        }
        if ($min > 0) { $where[] = 'p.price >= ?'; $params[] = $min; }
        if ($max > 0) { $where[] = 'p.price <= ?'; $params[] = $max; }
        $facets = $this->store->select('SELECT c.code, c.name, COUNT(*) AS count FROM ' . $this->store->table('yc_products') . ' p JOIN ' . $this->store->table('yc_categories')
            . ' c ON c.id = p.category_id WHERE ' . implode(' AND ', $where) . ' GROUP BY c.code, c.name ORDER BY c.code', $params);
        if ($ca !== '' && preg_match('/^[0-9a-z]{2,10}$/D', $ca)) { $where[] = 'EXISTS (SELECT 1 FROM ' . $this->store->table('yc_categories') . ' cc WHERE cc.id = p.category_id AND cc.code LIKE ?)'; $params[] = $ca . '%'; }
        $result = $this->paginate(implode(' AND ', $where), $params, $this->order($sort, $dir), $page, (int) $block['columns'], (int) $block['rows']);
        $result['facets'] = array_map(static fn (array $f): array => ['code' => $f['code'], 'name' => $f['name'], 'count' => (int) $f['count']], $facets);
        $result['words'] = $words;
        return $result;
    }

    public function main(): array
    {
        $blocks = [];
        foreach ($this->settings->all()['main'] as $type => $block) {
            if (!$block['use']) continue;
            $blocks[$type] = $this->paginate($this->visible() . ' AND p.' . Settings::TYPE_COLUMNS[$type] . ' = 1', [], self::DEFAULT_ORDER, 1, (int) $block['columns'], (int) $block['rows'])['items'];
        }
        return $blocks;
    }

    public function related(int $productId): array
    {
        $rows = $this->store->select('SELECT p.*, r.sort_order AS relation_order FROM ' . $this->store->table('yc_product_relations') . ' r JOIN ' . $this->store->table('yc_products')
            . ' p ON (r.product_id = ? AND p.id = r.related_id) OR (r.related_id = ? AND p.id = r.product_id) WHERE ' . $this->visible() . ' ORDER BY r.sort_order, p.id', [$productId, $productId]);
        $unique = [];
        foreach ($rows as $row) $unique[(int) $row['id']] ??= $row;
        return $this->decorate(array_values($unique));
    }

    public function adjacent(array $product): array
    {
        $base = 'SELECT p.id, p.code, p.slug, p.name FROM ' . $this->store->table('yc_products') . ' p WHERE p.active = 1 AND p.category_id = ? AND ';
        $params = [(int) $product['category_id'], (int) $product['sort_order'], (int) $product['sort_order'], (int) $product['id']];
        return ['prev' => $this->store->selectOne($base . '(p.sort_order < ? OR (p.sort_order = ? AND p.id > ?)) ORDER BY p.sort_order DESC, p.id ASC LIMIT 1', $params),
            'next' => $this->store->selectOne($base . '(p.sort_order > ? OR (p.sort_order = ? AND p.id < ?)) ORDER BY p.sort_order ASC, p.id DESC LIMIT 1', $params)];
    }

    /** 활성 상품이고 대표 분류가 활성인 조건. */
    private function visible(): string
    {
        return 'p.active = 1 AND EXISTS (SELECT 1 FROM ' . $this->store->table('yc_categories') . ' c1 WHERE c1.id = p.category_id AND c1.active = 1)';
    }

    private function order(string $sort, string $dir): string
    {
        if (!isset(self::SORTS[$sort])) return self::DEFAULT_ORDER;
        return self::SORTS[$sort] . ' ' . ($dir === 'asc' ? 'ASC' : 'DESC') . ', ' . self::DEFAULT_ORDER;
    }

    private function paginate(string $where, array $params, string $order, int $page, int $columns, int $rows): array
    {
        $perPage = max(1, $columns) * max(1, $rows);
        $page = max(1, $page);
        $total = (int) $this->store->selectOne('SELECT COUNT(*) AS c FROM ' . $this->store->table('yc_products') . ' p WHERE ' . $where, $params)['c'];
        $totalPages = max(1, (int) ceil($total / $perPage));
        $items = $page > $totalPages ? [] : $this->store->select('SELECT p.* FROM ' . $this->store->table('yc_products') . ' p WHERE ' . $where . ' ORDER BY ' . $order
            . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return ['items' => $this->decorate($items), 'page' => $page, 'total' => $total, 'total_pages' => $totalPages, 'per_page' => $perPage, 'columns' => max(1, $columns)];
    }

    /** 대표 이미지와 품절 여부를 한 번의 조회로 붙인다. */
    private function decorate(array $items): array
    {
        if ($items === []) return [];
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $images = [];
        foreach ($this->store->select('SELECT product_id, filename FROM ' . $this->store->table('yc_product_images') . ' WHERE product_id IN (' . $marks . ') ORDER BY sort_order, id', $ids) as $image) {
            $images[(int) $image['product_id']] ??= $image['filename'];
        }
        $options = [];
        foreach ($this->store->select('SELECT product_id, stock, active FROM ' . $this->store->table('yc_options') . " WHERE kind = 'select' AND product_id IN (" . $marks . ')', $ids) as $option) {
            $options[(int) $option['product_id']][] = $option;
        }
        foreach ($items as &$item) {
            $item['image'] = $images[(int) $item['id']] ?? null;
            $item['sold_out'] = Options::soldOut($item, $options[(int) $item['id']] ?? []);
        }
        return $items;
    }
}
```

`Service`에 `public readonly Catalog\Listing $listing;`를 선언하고 생성자에 `$this->listing = new Catalog\Listing($this->store, $this->settings, $this->options);`를 추가한다.

- [ ] **Step 4: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/YoungCart`
Expected: PASS(전체 YoungCart 단위 테스트)

- [ ] **Step 5: 커밋**

```bash
git add modules/youngcart/src/Catalog/Listing.php modules/youngcart/src/Service.php tests/YoungCart/ListingTest.php
git commit -m "feat: add youngcart listing queries for categories, types, search and main blocks"
```

---

### Task 11: 공개 화면

**Files:**
- Create: `modules/youngcart/src/Web/ShopController.php`
- Create: `modules/youngcart/templates/index.php`, `list.php`, `type.php`, `search.php`, `item.php`, `_header.php`, `_grid.php`, `_product_card.php`, `_pager.php`, `_breadcrumb.php`, `_options.php`
- Create: `www/themes/default/youngcart.css`, `www/themes/default/youngcart.js`
- Modify: `modules/youngcart/bootstrap.php`, `modules/youngcart/templates/notready.php`(그대로 사용)
- Test: `tests/Web/YoungCartPublicTest.php`

**Interfaces:**
- `ShopController::__construct(Service $service, string $routePrefix, ?string $adminRoutePrefix)`, `handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface` — `$page ∈ index|list|type|search|item|image`
- 템플릿 공통 변수: `$url`(공개 기본 주소), `$admin_url`, `$base`, `$admin`(bool), `$query`, `$settings`, `$menu`(1단계 활성 분류), `$img`(클로저 `(int $productId, ?string $file, string $size): ?string`), `$type_labels`, `$sort_labels`

- [ ] **Step 1: 실패하는 웹 테스트 작성**

`tests/Web/YoungCartPublicTest.php`를 아래 전체로 교체한다:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Tests\YoungCart\ImagesTest;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once dirname(__DIR__, 2) . '/modules/youngcart/autoload.php';

final class YoungCartPublicTest extends WebTestCase
{
    private App $app;
    private Service $shop;
    private string $root;

    private function setupModule(array $config, bool $install = true): void
    {
        session_name(GNUCMS_ID . '_session');
        session_start(); $_SESSION = []; session_write_close();
        $this->root = sys_get_temp_dir() . '/gnucms-yc-web-' . bin2hex(random_bytes(8));
        $config['prefix'] = 'yw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'uploads' => ['dir' => $this->root . '/uploads'],
            'app' => ['url' => 'https://shop.example.test']]);
        $manager = new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions'));
        $manager->setEnabledMany(['modules/youngcart' => true]);
        $this->shop = new Service($this->app);
        if ($install) $this->shop->install();
    }

    private function seed(): array
    {
        $top = $this->shop->categories->get($this->shop->categories->save(['code' => '10', 'name' => '의류', 'active' => '1', 'list_columns' => '1', 'list_rows' => '1', 'image_width' => '200', 'image_height' => '0', 'head_html' => '<p>분류 안내</p>']));
        $child = $this->shop->categories->get($this->shop->categories->save(['code' => '1010', 'name' => '셔츠', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        $ids = [];
        foreach ([['A', '<b>파란</b> 셔츠', '300', '1'], ['B', '빨간 셔츠', '100', '1'], ['C', '숨은 셔츠', '200', '0']] as [$code, $name, $price, $active]) {
            $ids[$code] = $this->shop->products->save(['code' => $code, 'name' => $name, 'category_id' => (string) $child['id'], 'price' => $price, 'list_price' => '500', 'stock' => $code === 'B' ? '0' : '3',
                'active' => $active, 'is_hit' => '1', 'summary' => '요약 ' . $code, 'description' => '<p>설명 ' . $code . '</p>', 'info_group' => 'wear', 'sort_order' => $code === 'A' ? '1' : '2',
                'option_group' => $code === 'A' ? [1 => '색상'] : [], 'options' => $code === 'A' ? [['value1' => '빨강', 'price' => '100', 'stock' => '2']] : [],
                'relations' => $code === 'B' ? (string) $ids['A'] : ''], $code === 'A' ? [ImagesTest::png(300, 300)] : []);
        }
        return ['top' => $top, 'child' => $child, 'ids' => $ids];
    }

    #[DataProvider('connectionProvider')]
    public function testNotInstalledShowsPreparingPageAndNoAliasPaths(array $config): void
    {
        $this->setupModule($config, false);
        $response = $this->get($this->app, '/shop');
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($response));
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($this->get($this->app, '/shop/list', ['ca' => '10'])));
        self::assertSame(404, $this->get($this->app, '/shop/image', ['p' => '1', 'f' => 'x.png', 's' => 'list'])->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/modules/youngcart')->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/modules/youngcart/')->getStatusCode());
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop'), '/admin/shop');
        self::assertSame(404, $this->get($this->app, '/shop/admin')->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testMainListTypeAndSearchPages(array $config): void
    {
        $this->setupModule($config);
        $seed = $this->seed();
        $home = $this->body($this->get($this->app, '/shop'));
        self::assertStringContainsString('히트상품', $home);
        self::assertStringContainsString('&lt;b&gt;파란&lt;/b&gt; 셔츠', $home);
        self::assertStringNotContainsString('숨은 셔츠', $home);
        self::assertStringContainsString('href="/shop/item?id=A"', $home);
        self::assertStringContainsString('href="/shop/list?ca=10"', $home);
        self::assertStringContainsString('youngcart.css', $home);
        $list = $this->body($this->get($this->app, '/shop/list', ['ca' => '10']));
        self::assertStringContainsString('<p>분류 안내</p>', $list);
        self::assertStringContainsString('href="/shop/list?ca=1010"', $list);
        self::assertStringContainsString('300원', $list);
        self::assertStringContainsString('<del', $list);
        self::assertStringContainsString('page=2', $list);
        self::assertStringNotContainsString('빨간 셔츠', $list);
        $page2 = $this->body($this->get($this->app, '/shop/list', ['ca' => '10', 'page' => '2']));
        self::assertStringContainsString('빨간 셔츠', $page2);
        self::assertStringContainsString('SOLD OUT', $page2);
        self::assertStringContainsString('빨간 셔츠', $this->body($this->get($this->app, '/shop/list', ['ca' => '10', 'sort' => 'price', 'dir' => 'asc'])));
        self::assertSame(404, $this->get($this->app, '/shop/list', ['ca' => '99'])->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/shop/list')->getStatusCode());
        $type = $this->body($this->get($this->app, '/shop/type', ['t' => 'hit']));
        self::assertStringContainsString('히트상품', $type);
        self::assertStringContainsString('빨간 셔츠', $type);
        self::assertSame(404, $this->get($this->app, '/shop/type', ['t' => 'nope'])->getStatusCode());
        $search = $this->body($this->get($this->app, '/shop/search', ['q' => '셔츠']));
        self::assertStringContainsString('2개', $search);
        self::assertStringContainsString('의류', $search);
        self::assertStringContainsString('value="셔츠"', $search);
        self::assertStringContainsString('검색어를 입력', $this->body($this->get($this->app, '/shop/search')));
        self::assertStringContainsString('검색 결과가 없습니다', $this->body($this->get($this->app, '/shop/search', ['q' => '<script>'])));
    }

    #[DataProvider('connectionProvider')]
    public function testItemPageImageAndAdminPreview(array $config): void
    {
        $this->setupModule($config);
        $seed = $this->seed();
        $response = $this->get($this->app, '/shop/item', ['id' => 'A']);
        $item = $this->body($response);
        self::assertStringContainsString('&lt;b&gt;파란&lt;/b&gt; 셔츠', $item);
        self::assertStringContainsString('<p>설명 A</p>', $item);
        self::assertStringContainsString('data-yc-options', $item);
        self::assertStringContainsString('&quot;빨강&quot;', $item);
        self::assertStringContainsString('상품페이지 참고', $item);
        self::assertStringContainsString('제품 소재', $item);
        self::assertStringContainsString('/shop/image?p=' . $seed['ids']['A'] . '&amp;f=', $item);
        self::assertStringContainsString('href="/shop/item?id=B"', $item);
        self::assertStringContainsString('yc_hit_' . $seed['ids']['A'] . '=1', $response->getHeaderLine('Set-Cookie'));
        self::assertSame(1, (int) $this->shop->products->find($seed['ids']['A'])['hit']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/shop/item?id=A')->withCookieParams(['yc_hit_' . $seed['ids']['A'] => '1']);
        Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '')->handle($request);
        self::assertSame(1, (int) $this->shop->products->find($seed['ids']['A'])['hit']);
        self::assertStringContainsString('&lt;b&gt;파란&lt;/b&gt; 셔츠', $this->body($this->get($this->app, '/shop/item', ['slug' => '파란-셔츠'])));
        $b = $this->body($this->get($this->app, '/shop/item', ['id' => 'B']));
        self::assertStringContainsString('품절', $b);
        self::assertStringContainsString('관련상품', $b);
        self::assertSame(404, $this->get($this->app, '/shop/item', ['id' => 'C'])->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/shop/item', ['id' => 'ZZ'])->getStatusCode());
        $file = $this->shop->products->get($seed['ids']['A'])['images'][0]['filename'];
        $image = $this->get($this->app, '/shop/image', ['p' => (string) $seed['ids']['A'], 'f' => $file, 's' => 'list']);
        self::assertSame('image/png', $image->getHeaderLine('Content-Type'));
        self::assertSame(404, $this->get($this->app, '/shop/image', ['p' => (string) $seed['ids']['A'], 'f' => 'nope.png', 's' => 'list'])->getStatusCode());
        $adminId = $this->app->users()->create('owner@example.test', '', '관리자', true);
        $this->get($this->app, '/login');
        session_start(); $_SESSION['user_id'] = $adminId; $_SESSION['session_epoch'] = 0; session_write_close();
        $preview = $this->body($this->get($this->app, '/shop/item', ['id' => 'C']));
        self::assertStringContainsString('숨은 셔츠', $preview);
        self::assertStringContainsString('미리보기', $preview);
        self::assertStringContainsString('/admin/shop/products/edit?id=' . $seed['ids']['C'], $preview);
    }

    #[DataProvider('connectionProvider')]
    public function testSubdirectoryInstallationLinks(array $config): void
    {
        $this->setupModule($config);
        $this->seed();
        $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle((new ServerRequestFactory())->createServerRequest('GET', '/cms/shop/list?ca=10'));
        $body = $this->body($response);
        self::assertStringContainsString('href="/cms/shop/item?id=A"', $body);
        self::assertStringContainsString('/cms/shop/image?p=', $body);
        self::assertStringContainsString('action="/cms/shop/search"', $body);
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartPublicTest.php`
Expected: 첫 테스트만 PASS, 나머지 FAIL(준비 중 화면만 나온다).

- [ ] **Step 3: ShopController 작성**

`modules/youngcart/src/Web/ShopController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Web;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Catalog\Listing;
use GnuCms\Modules\YoungCart\Catalog\Options;
use GnuCms\Modules\YoungCart\Catalog\Pricing;
use GnuCms\Modules\YoungCart\Images;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\ProductInfo;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Modules\YoungCart\Settings;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class ShopController
{
    public function __construct(private Service $service, private string $routePrefix, private ?string $adminRoutePrefix) {}

    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $base = RouteContext::fromRequest($request)->getBasePath();
        $url = $base . $this->routePrefix;
        $admin = $this->service->app->guestAcl()->identity()->isAdmin();
        $query = [];
        foreach ($request->getQueryParams() as $key => $value) if (is_string($value)) $query[$key] = $value;
        $view = View::forExtension($request, 'youngcart', dirname(__DIR__, 2) . '/templates');
        $data = ['url' => $url, 'admin_url' => $base . ($this->adminRoutePrefix ?? '/admin/shop'), 'base' => $base, 'admin' => $admin, 'query' => $query, 'page' => $page,
            'img' => static fn (int $productId, ?string $file, string $size): ?string => $file === null ? null : Images::url($url, $productId, $file, $size),
            'type_labels' => Settings::TYPE_LABELS, 'sort_labels' => Listing::SORT_LABELS];
        if (!$this->service->ready()) {
            if ($page === 'image') throw DomainError::notFound('이미지를 찾을 수 없습니다.');
            return $view->render($response, 'notready', $data);
        }
        $data['settings'] = $this->service->settings->all();
        $data['menu'] = $this->service->categories->children('', true);
        $sort = $query['sort'] ?? '';
        $dir = ($query['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $pageNo = preg_match('/^[1-9][0-9]{0,5}$/D', $query['page'] ?? '') ? (int) $query['page'] : 1;
        $data += ['sort' => $sort, 'dir' => $dir];
        $response = $response->withHeader('Cache-Control', 'no-store');
        switch ($page) {
            case 'index':
                $data['blocks'] = $this->service->listing->main();
                return $view->render($response, 'index', $data);
            case 'list':
                $category = $this->service->categories->byCode($query['ca'] ?? '');
                if ($category === null || (int) $category['active'] !== 1) throw DomainError::notFound('분류를 찾을 수 없습니다.');
                $data['category'] = $category;
                $data['path'] = $this->service->categories->path($category['code']);
                $data['children'] = $this->service->categories->children($category['code'], true);
                $data['list'] = $this->service->listing->category($category, $sort, $dir, $pageNo);
                return $view->render($response, 'list', $data);
            case 'type':
                $type = $query['t'] ?? '';
                if (!isset(Settings::TYPE_LABELS[$type])) throw DomainError::notFound('상품 유형을 찾을 수 없습니다.');
                $data['type'] = $type;
                $data['list'] = $this->service->listing->type($type, $sort, $dir, $pageNo);
                return $view->render($response, 'type', $data);
            case 'search':
                $q = mb_substr(trim($query['q'] ?? ''), 0, 50, 'UTF-8');
                $ca = preg_match('/^[0-9a-z]{2,10}$/D', $query['ca'] ?? '') ? $query['ca'] : '';
                $min = preg_match('/^[0-9]{1,10}$/D', $query['min'] ?? '') ? (int) $query['min'] : 0;
                $max = preg_match('/^[0-9]{1,10}$/D', $query['max'] ?? '') ? (int) $query['max'] : 0;
                $data += ['q' => $q, 'ca' => $ca, 'min' => $min, 'max' => $max];
                $data['list'] = $this->service->listing->search($q, $ca, $min, $max, $sort, $dir, $pageNo);
                return $view->render($response, 'search', $data);
            case 'item':
                $product = ($query['id'] ?? '') !== '' ? $this->service->products->byCode($query['id'])
                    : (($query['slug'] ?? '') !== '' ? $this->service->products->bySlug($query['slug']) : null);
                if ($product === null) throw DomainError::notFound('상품을 찾을 수 없습니다.');
                $visible = (int) $product['active'] === 1 && (int) ($product['categories'][1]['active'] ?? 0) === 1;
                if (!$visible && !$admin) throw DomainError::notFound('상품을 찾을 수 없습니다.');
                $cookie = 'yc_hit_' . $product['id'];
                if ($visible && !isset($request->getCookieParams()[$cookie])) {
                    $this->service->store->execute('UPDATE ' . $this->service->store->table('yc_products') . ' SET hit = hit + 1 WHERE id = ?', [(int) $product['id']]);
                    $product['hit'] = (int) $product['hit'] + 1;
                    $response = $response->withAddedHeader('Set-Cookie', $cookie . '=1; Max-Age=3600; Path=' . ($base === '' ? '/' : $base) . '; SameSite=Lax; HttpOnly');
                }
                $data['product'] = $product;
                $data['preview'] = !$visible;
                $data['path'] = isset($product['categories'][1]) ? $this->service->categories->path($product['categories'][1]['code']) : [];
                $data['adjacent'] = $this->service->listing->adjacent($product);
                $data['related'] = $data['settings']['related']['use'] ? $this->service->listing->related((int) $product['id']) : [];
                $data['options_json'] = Options::pageJson($product, $product['options']);
                $data['sold_out'] = $product['sold_out_computed'];
                $data['display_price'] = Pricing::display($product);
                $data['point_label'] = Pricing::pointLabel($product);
                $data['info_label'] = ProductInfo::labels()[$product['info_group']] ?? '';
                $data['info_articles'] = ProductInfo::articles($product['info_group']);
                return $view->render($response, 'item', $data);
            case 'image':
                $productId = Input::id($query['p'] ?? '');
                $product = $this->service->products->find($productId) ?? throw DomainError::notFound('이미지를 찾을 수 없습니다.');
                $size = $query['s'] ?? 'list';
                $width = $size === 'list' ? $this->service->images->width('list', $this->service->store->find('yc_categories', (int) $product['category_id'])) : null;
                return $this->service->images->response($productId, $query['f'] ?? '', $size, $response->withoutHeader('Cache-Control'), $width);
        }
        throw DomainError::notFound('페이지를 찾을 수 없습니다.');
    }
}
```

- [ ] **Step 4: bootstrap의 공개 라우트 교체**

`modules/youngcart/bootstrap.php`를 아래로 교체한다(관리자 `/` 임시 라우트는 유지; Task 12에서 바꾼다):

```php
<?php

declare(strict_types=1);

use GnuCms\Extension\Context;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Modules\YoungCart\Web\ShopController;

require_once __DIR__ . '/autoload.php';

return static function (Context $context): void {
    $service = new Service($context->app);
    $shop = new ShopController($service, $context->routePrefix, $context->adminRoutePrefix);
    $context->route('GET', '/', static fn ($request, $response) => $shop->handle('index', $request, $response));
    foreach (['list', 'type', 'search', 'item', 'image'] as $page) {
        $context->route('GET', '/' . $page, static fn ($request, $response) => $shop->handle($page, $request, $response));
    }
    $context->route('GET', '/', static fn ($request, $response) => $response, admin: true);
};
```

- [ ] **Step 5: 공통 조각 템플릿 작성**

`modules/youngcart/templates/_header.php`(모든 공개 페이지 본문 맨 위):

```php
<?php // 상점 머리: 이름, 1단계 분류, 유형 링크, 검색 폼. $url·$menu·$type_labels·$settings·$admin·$admin_url 을 쓴다. ?>
<header class="yc-header">
  <a class="yc-brand" href="<?= $this->e($url) ?>">쇼핑몰</a>
  <nav class="yc-nav" aria-label="상품 분류">
    <?php foreach ($menu as $category): ?>
      <a href="<?= $this->e($url) ?>/list?ca=<?= $this->e($category['code']) ?>"<?= isset($path) && ($path[0]['code'] ?? '') === $category['code'] ? ' aria-current="page"' : '' ?>><?= $this->e($category['name']) ?></a>
    <?php endforeach ?>
    <?php foreach ($type_labels as $key => $label): if (!($settings['main'][$key]['use'] ?? false)) continue; ?>
      <a class="yc-nav-type" href="<?= $this->e($url) ?>/type?t=<?= $this->e($key) ?>"><?= $this->e($label) ?></a>
    <?php endforeach ?>
  </nav>
  <form class="yc-search" method="get" action="<?= $this->e($url) ?>/search" role="search">
    <input class="input input-bordered input-sm" type="search" name="q" value="<?= $this->e($q ?? '') ?>" maxlength="50" placeholder="상품 검색" aria-label="상품 검색">
    <button class="btn btn-sm" type="submit">검색</button>
  </form>
  <?php if ($admin): ?><a class="btn btn-sm btn-outline" href="<?= $this->e($admin_url) ?>">쇼핑몰 관리</a><?php endif ?>
</header>
```

`modules/youngcart/templates/_breadcrumb.php`(`$path` 사용):

```php
<div class="breadcrumbs"><ul>
  <li><a href="<?= $this->e($url) ?>">쇼핑몰</a></li>
  <?php foreach ($path as $index => $crumb): ?>
    <li<?= $index === count($path) - 1 ? ' aria-current="page"' : '' ?>><a href="<?= $this->e($url) ?>/list?ca=<?= $this->e($crumb['code']) ?>"><?= $this->e($crumb['name']) ?></a></li>
  <?php endforeach ?>
</ul></div>
```

`modules/youngcart/templates/_product_card.php`(`$item`, `$size` 사용):

```php
<?php $price = \GnuCms\Modules\YoungCart\Catalog\Pricing::display($item); $image = $img((int) $item['id'], $item['image'], $size); ?>
<article class="yc-card<?= $item['sold_out'] ? ' yc-card-soldout' : '' ?>">
  <a class="yc-card-image" href="<?= $this->e($url) ?>/item?id=<?= $this->e(rawurlencode($item['code'])) ?>">
    <?php if ($image !== null): ?><img src="<?= $this->e($image) ?>" alt="<?= $this->e($item['name']) ?>" loading="lazy"><?php else: ?><span class="yc-noimage" aria-hidden="true">이미지 없음</span><?php endif ?>
    <?php if ($item['sold_out']): ?><span class="yc-soldout">SOLD OUT</span><?php endif ?>
  </a>
  <div class="yc-card-body">
    <a class="yc-card-name" href="<?= $this->e($url) ?>/item?id=<?= $this->e(rawurlencode($item['code'])) ?>"><?= $this->e($item['name']) ?></a>
    <?php if ($item['summary'] !== ''): ?><div class="yc-card-summary"><?= $this->html($item['summary']) ?></div><?php endif ?>
    <div class="yc-card-price">
      <?php if ($price === null): ?><span class="yc-inquiry">전화문의</span>
      <?php else: ?>
        <?php if ((int) $item['list_price'] > 0): ?><del class="yc-list-price"><?= $this->e(number_format((int) $item['list_price'])) ?>원</del><?php endif ?>
        <strong><?= $this->e(number_format($price)) ?>원</strong>
      <?php endif ?>
    </div>
    <div class="yc-card-icons">
      <?php foreach (['is_hit' => '히트', 'is_recommended' => '추천', 'is_new' => '최신', 'is_popular' => '인기', 'is_discount' => '할인'] as $flag => $label): if ((int) $item[$flag] === 1): ?><span class="badge badge-sm"><?= $label ?></span><?php endif; endforeach ?>
      <?php if ((int) $item['review_count'] > 0): ?><span class="yc-stars" aria-label="평점 <?= $this->e((string) $item['review_avg']) ?>">★ <?= $this->e((string) $item['review_avg']) ?> (<?= (int) $item['review_count'] ?>)</span><?php endif ?>
    </div>
  </div>
</article>
```

`modules/youngcart/templates/_grid.php`(`$items`, `$columns`, `$size` 사용):

```php
<?php if ($items === []): ?>
  <p class="muted yc-empty">등록된 상품이 없습니다.</p>
<?php else: ?>
  <div class="yc-grid" style="--yc-columns: <?= (int) $columns ?>">
    <?php foreach ($items as $item) $this->insert('_product_card', ['item' => $item, 'size' => $size]) ?>
  </div>
<?php endif ?>
```

`modules/youngcart/templates/_pager.php`(`$list`, `$page_url` 사용):

```php
<?php
if (($list['total_pages'] ?? 0) <= 1) return;
$window = 3;
$start = max(1, $list['page'] - $window);
$end = min($list['total_pages'], $list['page'] + $window);
?>
<nav class="pager" aria-label="페이지 이동"><div class="join">
  <?php if ($list['page'] > 1): ?><a class="join-item btn btn-sm" rel="prev" href="<?= $this->e($page_url($list['page'] - 1)) ?>" aria-label="이전 페이지"><?= $this->icon('chevron-left', 15) ?></a><?php endif ?>
  <?php if ($start > 1): ?><a class="join-item btn btn-sm" href="<?= $this->e($page_url(1)) ?>">1</a><?php if ($start > 2): ?><span class="join-item btn btn-sm btn-disabled" aria-hidden="true">…</span><?php endif ?><?php endif ?>
  <?php for ($p = $start; $p <= $end; $p++): ?>
    <?php if ($p === $list['page']): ?><span class="join-item btn btn-sm btn-active" aria-current="page"><?= $p ?></span>
    <?php else: ?><a class="join-item btn btn-sm" href="<?= $this->e($page_url($p)) ?>" aria-label="<?= $p ?> 페이지"><?= $p ?></a><?php endif ?>
  <?php endfor ?>
  <?php if ($end < $list['total_pages']): ?><?php if ($end < $list['total_pages'] - 1): ?><span class="join-item btn btn-sm btn-disabled" aria-hidden="true">…</span><?php endif ?><a class="join-item btn btn-sm" href="<?= $this->e($page_url($list['total_pages'])) ?>"><?= $list['total_pages'] ?></a><?php endif ?>
  <?php if ($list['page'] < $list['total_pages']): ?><a class="join-item btn btn-sm" rel="next" href="<?= $this->e($page_url($list['page'] + 1)) ?>" aria-label="다음 페이지"><?= $this->icon('chevron-right', 15) ?></a><?php endif ?>
</div></nav>
```

`modules/youngcart/templates/_sort.php`(`$action`, `$hidden`(배열), `$sort`, `$dir` 사용):

```php
<form class="yc-sort" method="get" action="<?= $this->e($action) ?>">
  <?php foreach ($hidden as $name => $value): ?><input type="hidden" name="<?= $this->e($name) ?>" value="<?= $this->e($value) ?>"><?php endforeach ?>
  <label class="sr-only" for="yc-sort">정렬</label>
  <select class="select select-bordered select-sm" id="yc-sort" name="sortdir" onchange="var v=this.value.split('_');this.form.sort.value=v[0];this.form.dir.value=v[1];this.form.submit()">
    <option value="_"<?= $sort === '' ? ' selected' : '' ?>>기본순</option>
    <?php foreach ($sort_labels as $key => $label): ?><option value="<?= $this->e($key) ?>"<?= $key === $sort . '_' . $dir ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach ?>
  </select>
  <input type="hidden" name="sort" value="<?= $this->e($sort) ?>"><input type="hidden" name="dir" value="<?= $this->e($dir) ?>">
  <noscript><button class="btn btn-sm" type="submit">정렬</button></noscript>
</form>
```

- [ ] **Step 6: 페이지 템플릿 작성**

`modules/youngcart/templates/index.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?>쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop">
  <?php $this->insert('_header') ?>
  <?php foreach ($blocks as $type => $items): ?>
    <section class="yc-block">
      <h2 class="yc-block-title"><a href="<?= $this->e($url) ?>/type?t=<?= $this->e($type) ?>"><?= $this->e($type_labels[$type]) ?></a></h2>
      <?php $this->insert('_grid', ['items' => $items, 'columns' => $settings['main'][$type]['columns'], 'size' => 'main']) ?>
    </section>
  <?php endforeach ?>
</div>
<?php $this->stop() ?>
```

`modules/youngcart/templates/list.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?><?= $this->e($category['name']) ?> · 쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop">
  <?php $this->insert('_header') ?>
  <?php $this->insert('_breadcrumb') ?>
  <?php if ($category['head_html'] !== ''): ?><div class="yc-html"><?= $this->html($category['head_html']) ?></div><?php endif ?>
  <?php if ($children !== []): ?><nav class="yc-children" aria-label="하위 분류"><?php foreach ($children as $child): ?><a class="btn btn-sm btn-outline" href="<?= $this->e($url) ?>/list?ca=<?= $this->e($child['code']) ?>"><?= $this->e($child['name']) ?></a><?php endforeach ?></nav><?php endif ?>
  <div class="yc-toolbar"><span class="muted"><?= $list['total'] ?>개</span><?php $this->insert('_sort', ['action' => $url . '/list', 'hidden' => ['ca' => $category['code']]]) ?></div>
  <?php $this->insert('_grid', ['items' => $list['items'], 'columns' => $list['columns'], 'size' => 'list']) ?>
  <?php $this->insert('_pager', ['page_url' => fn (int $p): string => $url . '/list?' . http_build_query(['ca' => $category['code'], 'sort' => $sort, 'dir' => $dir, 'page' => $p])]) ?>
  <?php if ($category['tail_html'] !== ''): ?><div class="yc-html"><?= $this->html($category['tail_html']) ?></div><?php endif ?>
</div>
<?php $this->stop() ?>
```

`modules/youngcart/templates/type.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?><?= $this->e($type_labels[$type]) ?> · 쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop">
  <?php $this->insert('_header') ?>
  <h1 class="yc-title"><?= $this->e($type_labels[$type]) ?></h1>
  <div class="yc-toolbar"><span class="muted"><?= $list['total'] ?>개</span><?php $this->insert('_sort', ['action' => $url . '/type', 'hidden' => ['t' => $type]]) ?></div>
  <?php $this->insert('_grid', ['items' => $list['items'], 'columns' => $list['columns'], 'size' => 'type']) ?>
  <?php $this->insert('_pager', ['page_url' => fn (int $p): string => $url . '/type?' . http_build_query(['t' => $type, 'sort' => $sort, 'dir' => $dir, 'page' => $p])]) ?>
</div>
<?php $this->stop() ?>
```

`modules/youngcart/templates/search.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?>상품 검색 · 쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,follow"><link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>"><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop">
  <?php $this->insert('_header') ?>
  <h1 class="yc-title">상품 검색</h1>
  <form class="yc-search-form" method="get" action="<?= $this->e($url) ?>/search">
    <input class="input input-bordered" type="search" name="q" value="<?= $this->e($q) ?>" maxlength="50" placeholder="검색어" aria-label="검색어">
    <input class="input input-bordered input-sm" type="number" name="min" value="<?= $min > 0 ? $min : '' ?>" min="0" placeholder="최저가" aria-label="최저 가격">
    <input class="input input-bordered input-sm" type="number" name="max" value="<?= $max > 0 ? $max : '' ?>" min="0" placeholder="최고가" aria-label="최고 가격">
    <input type="hidden" name="ca" value="<?= $this->e($ca) ?>">
    <button class="btn btn-sm" type="submit">검색</button>
  </form>
  <?php if ($q === ''): ?>
    <p class="muted">검색어를 입력해 주세요.</p>
  <?php else: ?>
    <?php if ($list['facets'] !== []): ?>
      <nav class="yc-facets" aria-label="분류별 결과">
        <a class="btn btn-sm<?= $ca === '' ? ' btn-active' : ' btn-outline' ?>" href="<?= $this->e($url) ?>/search?<?= $this->e(http_build_query(['q' => $q, 'min' => $min ?: '', 'max' => $max ?: ''])) ?>">전체</a>
        <?php foreach ($list['facets'] as $facet): ?><a class="btn btn-sm<?= $ca === $facet['code'] ? ' btn-active' : ' btn-outline' ?>" href="<?= $this->e($url) ?>/search?<?= $this->e(http_build_query(['q' => $q, 'ca' => $facet['code'], 'min' => $min ?: '', 'max' => $max ?: ''])) ?>"><?= $this->e($facet['name']) ?> (<?= $facet['count'] ?>)</a><?php endforeach ?>
      </nav>
    <?php endif ?>
    <div class="yc-toolbar"><span class="muted"><?= $list['total'] ?>개</span><?php $this->insert('_sort', ['action' => $url . '/search', 'hidden' => ['q' => $q, 'ca' => $ca, 'min' => $min ?: '', 'max' => $max ?: '']]) ?></div>
    <?php if ($list['items'] === []): ?><p class="muted">검색 결과가 없습니다.</p><?php else: ?>
      <?php $this->insert('_grid', ['items' => $list['items'], 'columns' => $list['columns'], 'size' => 'search']) ?>
      <?php $this->insert('_pager', ['page_url' => fn (int $p): string => $url . '/search?' . http_build_query(['q' => $q, 'ca' => $ca, 'min' => $min ?: '', 'max' => $max ?: '', 'sort' => $sort, 'dir' => $dir, 'page' => $p])]) ?>
    <?php endif ?>
  <?php endif ?>
</div>
<?php $this->stop() ?>
```

`modules/youngcart/templates/_options.php`(`$product`, `$options_json`, `$sold_out` 사용; JS 없이도 선택 상자가 보인다):

```php
<?php $opts = $product['options']; ?>
<div class="yc-options" data-yc-options="<?= $this->e(json_encode($options_json, JSON_UNESCAPED_UNICODE)) ?>">
  <?php foreach ($opts['select_groups'] as $index => $group): $values = array_values(array_unique(array_column($opts['select'], 'value' . ($index + 1)))); ?>
    <label class="yc-option-row"><span><?= $this->e($group) ?></span>
      <select class="select select-bordered select-sm" data-yc-select="<?= $index ?>" name="option<?= $index + 1 ?>">
        <option value=""><?= $this->e($group) ?> 선택</option>
        <?php foreach ($values as $value): if ($value === '') continue; ?><option value="<?= $this->e($value) ?>"><?= $this->e($value) ?></option><?php endforeach ?>
      </select>
    </label>
  <?php endforeach ?>
  <?php foreach ($options_json['extra']['groups'] as $group): ?>
    <label class="yc-option-row"><span><?= $this->e($group['name']) ?> <small class="muted">(추가옵션)</small></span>
      <select class="select select-bordered select-sm" data-yc-extra="<?= $this->e($group['name']) ?>">
        <option value="">선택 안 함</option>
        <?php foreach ($group['items'] as $item): ?><option value="<?= $this->e($item['name']) ?>" data-price="<?= (int) $item['price'] ?>" data-stock="<?= (int) $item['stock'] ?>"<?= $item['stock'] < 1 ? ' disabled' : '' ?>><?= $this->e($item['name']) ?> (+<?= number_format($item['price']) ?>원)<?= $item['stock'] < 1 ? ' [품절]' : '' ?></option><?php endforeach ?>
      </select>
    </label>
  <?php endforeach ?>
  <?php if ($opts['select_groups'] === []): ?>
    <label class="yc-option-row"><span>수량</span><input class="input input-bordered input-sm" type="number" name="quantity" value="1" min="1" max="9999" data-yc-quantity></label>
  <?php endif ?>
  <ul class="yc-selected" data-yc-selected aria-live="polite"></ul>
  <p class="yc-total">합계 <strong data-yc-total><?= $sold_out ? '품절' : number_format((int) $product['price']) . '원' ?></strong></p>
</div>
```

`modules/youngcart/templates/item.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?><?= $this->e($product['name']) ?> · 쇼핑몰 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/youngcart<?php $this->stop() ?>
<?php $this->start('body_class') ?>yc-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?>
<link rel="stylesheet" href="<?= $this->asset('youngcart.css') ?>">
<?php if ($preview): ?><meta name="robots" content="noindex,nofollow"><?php endif ?>
<meta property="og:title" content="<?= $this->e($product['name']) ?>">
<?php if ($product['images'] !== []): ?><meta property="og:image" content="<?= $this->e($site_url . $img((int) $product['id'], $product['images'][0]['filename'], 'detail')) ?>"><?php endif ?>
<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="yc-shop yc-item">
  <?php $this->insert('_header') ?>
  <?php $this->insert('_breadcrumb') ?>
  <?php if ($preview): ?><div class="alert alert-warning">판매하지 않는 상품의 관리자 미리보기입니다. <a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $product['id'] ?>">상품 수정</a></div><?php endif ?>
  <?php if ($product['head_html'] !== ''): ?><div class="yc-html"><?= $this->html($product['head_html']) ?></div><?php endif ?>
  <div class="yc-item-top">
    <div class="yc-gallery" data-yc-gallery>
      <?php if ($product['images'] === []): ?><div class="yc-noimage yc-noimage-large" aria-hidden="true">이미지 없음</div>
      <?php else: ?>
        <a class="yc-gallery-main" href="<?= $this->e($img((int) $product['id'], $product['images'][0]['filename'], 'original')) ?>" target="_blank" rel="noopener"><img src="<?= $this->e($img((int) $product['id'], $product['images'][0]['filename'], 'detail')) ?>" alt="<?= $this->e($product['name']) ?>" data-yc-main></a>
        <?php if (count($product['images']) > 1): ?><div class="yc-thumbs"><?php foreach ($product['images'] as $index => $image): ?>
          <button type="button" class="yc-thumb" data-yc-thumb="<?= $this->e($img((int) $product['id'], $image['filename'], 'detail')) ?>" data-yc-large="<?= $this->e($img((int) $product['id'], $image['filename'], 'original')) ?>" aria-label="<?= $index + 1 ?>번째 이미지"><img src="<?= $this->e($img((int) $product['id'], $image['filename'], 'thumb')) ?>" alt=""></button>
        <?php endforeach ?></div><?php endif ?>
      <?php endif ?>
    </div>
    <div class="yc-item-info">
      <h1 class="yc-item-name"><?= $this->e($product['name']) ?></h1>
      <?php if ($product['summary'] !== ''): ?><div class="yc-item-summary"><?= $this->html($product['summary']) ?></div><?php endif ?>
      <table class="table table-sm yc-item-table"><tbody>
        <?php if ((int) $product['list_price'] > 0 && $display_price !== null): ?><tr><th scope="row">시중가격</th><td><del><?= number_format((int) $product['list_price']) ?>원</del></td></tr><?php endif ?>
        <tr><th scope="row">판매가격</th><td class="yc-item-price"><?php if (!(int) $product['active']): ?>판매중지<?php elseif ($display_price === null): ?>전화문의<?php else: ?><strong><?= number_format($display_price) ?>원</strong><?php if ($settings['show_tax']): ?> <small class="muted"><?= (int) $product['tax_free'] === 1 ? '비과세' : '부가세 포함' ?></small><?php endif ?><?php endif ?></td></tr>
        <?php if ($display_price !== null && (int) $product['point'] > 0): ?><tr><th scope="row">포인트</th><td><?= $this->e($point_label) ?></td></tr><?php endif ?>
        <tr><th scope="row">배송비</th><td><?= [0 => '상점 기본 배송비', 1 => '무료배송', 2 => number_format((int) $product['shipping_free_minimum']) . '원 이상 무료, 미만 ' . number_format((int) $product['shipping_fee']) . '원', 3 => number_format((int) $product['shipping_fee']) . '원', 4 => (int) $product['shipping_per_qty'] . '개마다 ' . number_format((int) $product['shipping_fee']) . '원'][(int) $product['shipping_type']] ?><?= (int) $product['shipping_method'] === 1 ? ' (착불)' : ((int) $product['shipping_method'] === 2 ? ' (선불·착불 선택)' : '') ?></td></tr>
        <?php if ((int) $product['buy_min'] > 0 || (int) $product['buy_max'] > 0): ?><tr><th scope="row">구매수량</th><td><?= (int) $product['buy_min'] > 0 ? '최소 ' . (int) $product['buy_min'] . '개' : '' ?> <?= (int) $product['buy_max'] > 0 ? '최대 ' . (int) $product['buy_max'] . '개' : '' ?></td></tr><?php endif ?>
      </tbody></table>
      <?php if ($sold_out): ?><p class="yc-soldout-notice"><strong>품절</strong>된 상품입니다.</p>
      <?php elseif ($display_price !== null && (int) $product['active'] === 1): ?><?php $this->insert('_options') ?><?php endif ?>
      <div class="yc-item-actions muted">장바구니와 주문은 다음 단계에서 제공됩니다.</div>
    </div>
  </div>
  <nav class="tabs tabs-border yc-tabs" aria-label="상품 정보 탭">
    <a class="tab tab-active" href="#yc-description">상품정보</a>
    <?php if ($settings['shipping']['content'] !== ''): ?><a class="tab" href="#yc-shipping">배송정보</a><?php endif ?>
    <?php if ($settings['exchange']['content'] !== ''): ?><a class="tab" href="#yc-exchange">교환정보</a><?php endif ?>
  </nav>
  <section id="yc-description" class="yc-section"><div class="editor-content"><?= $this->html($product['description']) ?></div>
    <?php if ($info_articles !== []): ?><h2 class="yc-section-title">상품정보고시 <small class="muted"><?= $this->e($info_label) ?></small></h2>
      <table class="table table-sm yc-info-table"><tbody><?php foreach ($info_articles as $index => $article): ?><tr><th scope="row"><?= $this->e($article) ?></th><td><?= $this->e($product['info'][$index] ?? '') ?></td></tr><?php endforeach ?></tbody></table><?php endif ?>
    <?php $extra = array_filter($product['extra'], static fn ($f) => ($f['label'] ?? '') !== ''); if ($extra !== []): ?><table class="table table-sm"><tbody><?php foreach ($extra as $field): ?><tr><th scope="row"><?= $this->e($field['label']) ?></th><td><?= $this->e($field['value']) ?></td></tr><?php endforeach ?></tbody></table><?php endif ?>
  </section>
  <?php if ($settings['shipping']['content'] !== ''): ?><section id="yc-shipping" class="yc-section"><h2 class="yc-section-title">배송정보</h2><div class="editor-content"><?= $this->html($settings['shipping']['content']) ?></div></section><?php endif ?>
  <?php if ($settings['exchange']['content'] !== ''): ?><section id="yc-exchange" class="yc-section"><h2 class="yc-section-title">교환정보</h2><div class="editor-content"><?= $this->html($settings['exchange']['content']) ?></div></section><?php endif ?>
  <?php if ($related !== []): ?><section class="yc-section"><h2 class="yc-section-title">관련상품</h2><?php $this->insert('_grid', ['items' => $related, 'columns' => $settings['related']['columns'], 'size' => 'related']) ?></section><?php endif ?>
  <nav class="yc-adjacent" aria-label="이전·다음 상품">
    <?php if ($adjacent['prev'] !== null): ?><a rel="prev" href="<?= $this->e($url) ?>/item?id=<?= $this->e(rawurlencode($adjacent['prev']['code'])) ?>">← 이전 상품: <?= $this->e($adjacent['prev']['name']) ?></a><?php endif ?>
    <?php if ($adjacent['next'] !== null): ?><a rel="next" href="<?= $this->e($url) ?>/item?id=<?= $this->e(rawurlencode($adjacent['next']['code'])) ?>">다음 상품: <?= $this->e($adjacent['next']['name']) ?> →</a><?php endif ?>
  </nav>
  <?php if ($product['tail_html'] !== ''): ?><div class="yc-html"><?= $this->html($product['tail_html']) ?></div><?php endif ?>
</div>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><script src="<?= $this->asset('youngcart.js') ?>" defer></script><?php $this->stop() ?>
```

- [ ] **Step 7: 정적 자산 작성**

`www/themes/default/youngcart.css`:

```css
.yc-shop{display:flex;flex-direction:column;gap:1rem}
.yc-header{display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border,#e5e7eb)}
.yc-brand{font-weight:700;font-size:1.25rem}
.yc-nav{display:flex;flex-wrap:wrap;gap:.5rem}
.yc-nav a[aria-current=page]{font-weight:700;text-decoration:underline}
.yc-nav-type{opacity:.8}
.yc-search{display:flex;gap:.25rem;margin-left:auto}
.yc-grid{display:grid;grid-template-columns:repeat(var(--yc-columns,4),minmax(0,1fr));gap:1rem}
@media (max-width:768px){.yc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.yc-card{display:flex;flex-direction:column;border:1px solid var(--border,#e5e7eb);border-radius:.5rem;overflow:hidden}
.yc-card-image{position:relative;display:block;aspect-ratio:1/1;background:#f3f4f6}
.yc-card-image img{width:100%;height:100%;object-fit:cover}
.yc-noimage{display:flex;align-items:center;justify-content:center;width:100%;height:100%;color:#9ca3af;font-size:.875rem}
.yc-noimage-large{aspect-ratio:1/1;background:#f3f4f6;border-radius:.5rem}
.yc-soldout{position:absolute;inset:auto 0 0 0;padding:.25rem;text-align:center;background:rgba(0,0,0,.6);color:#fff;font-weight:700;letter-spacing:.1em}
.yc-card-soldout .yc-card-image img{opacity:.6}
.yc-card-body{display:flex;flex-direction:column;gap:.25rem;padding:.75rem}
.yc-card-name{font-weight:600}
.yc-card-summary{font-size:.875rem;opacity:.8}
.yc-list-price{opacity:.6;margin-right:.5rem}
.yc-card-icons{display:flex;flex-wrap:wrap;gap:.25rem;font-size:.75rem}
.yc-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.yc-children{display:flex;flex-wrap:wrap;gap:.5rem}
.yc-item-top{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1.5rem}
@media (max-width:768px){.yc-item-top{grid-template-columns:1fr}}
.yc-gallery-main img{width:100%;border-radius:.5rem}
.yc-thumbs{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem}
.yc-thumb{padding:0;border:2px solid transparent;border-radius:.25rem;background:none;cursor:pointer}
.yc-thumb[aria-current=true]{border-color:currentColor}
.yc-thumb img{display:block;width:70px;height:70px;object-fit:cover;border-radius:.2rem}
.yc-item-table th{width:7rem;text-align:left;font-weight:600}
.yc-item-price strong{font-size:1.25rem}
.yc-options{display:flex;flex-direction:column;gap:.5rem;margin:1rem 0}
.yc-option-row{display:flex;align-items:center;gap:.5rem}
.yc-option-row>span{min-width:6rem}
.yc-selected{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.25rem}
.yc-selected li{display:flex;align-items:center;gap:.5rem;font-size:.875rem}
.yc-selected input{width:4.5rem}
.yc-total{text-align:right;font-size:1.125rem}
.yc-section{margin-top:1rem}
.yc-section-title{font-size:1.125rem;font-weight:700;margin:1rem 0 .5rem}
.yc-adjacent{display:flex;justify-content:space-between;gap:1rem;margin-top:1rem}
.yc-facets{display:flex;flex-wrap:wrap;gap:.5rem}
.yc-search-form{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.yc-soldout-notice{padding:.75rem;border-radius:.5rem;background:#fef2f2}
```

`www/themes/default/youngcart.js`(선택옵션 단계별 선택, 추가옵션, 수량, 합계, 갤러리):

```js
(function(){
  'use strict';
  var root=document.querySelector('[data-yc-options]');
  if(root){setupOptions(root);}
  var gallery=document.querySelector('[data-yc-gallery]');
  if(gallery){setupGallery(gallery);}

  function setupOptions(root){
    var data;try{data=JSON.parse(root.getAttribute('data-yc-options'));}catch(e){return;}
    var selects=[].slice.call(root.querySelectorAll('[data-yc-select]'));
    var extras=[].slice.call(root.querySelectorAll('[data-yc-extra]'));
    var list=root.querySelector('[data-yc-selected]');
    var total=root.querySelector('[data-yc-total]');
    var qty=root.querySelector('[data-yc-quantity]');
    var chosen=[];
    function key(values){return values.join('');}
    function fill(level){
      var select=selects[level];if(!select){return;}
      var prefix=[];for(var i=0;i<level;i++){prefix.push(selects[i].value);}
      var seen={},options=[];
      data.select.items.forEach(function(item){
        for(var i=0;i<level;i++){if(item.v[i]!==prefix[i]){return;}}
        var value=item.v[level];if(seen[value]){return;}seen[value]=true;
        var last=level===data.select.groups.length-1;
        options.push({value:value,label:value+(last?(item.price?(' ('+(item.price>0?'+':'')+item.price.toLocaleString()+'원)'):''):'')+(last&&item.stock<1?' [품절]':''),disabled:last&&item.stock<1});
      });
      select.innerHTML='';var head=document.createElement('option');head.value='';head.textContent=data.select.groups[level]+' 선택';select.appendChild(head);
      options.forEach(function(o){var el=document.createElement('option');el.value=o.value;el.textContent=o.label;el.disabled=o.disabled;select.appendChild(el);});
      select.disabled=false;
      for(var j=level+1;j<selects.length;j++){selects[j].innerHTML='';selects[j].disabled=true;}
    }
    selects.forEach(function(select,level){
      select.addEventListener('change',function(){
        if(!select.value){for(var j=level+1;j<selects.length;j++){selects[j].innerHTML='';selects[j].disabled=true;}return;}
        if(level<selects.length-1){fill(level+1);return;}
        var values=selects.map(function(s){return s.value;});
        var item=data.select.items.filter(function(it){return key(it.v)===key(values);})[0];
        if(!item||item.stock<1){return;}
        addLine('select',values.join(' / '),data.price+item.price,item.stock);
        selects.forEach(function(s,i){if(i===0){s.value='';}});fill(0);
      });
    });
    if(selects.length){fill(0);}
    extras.forEach(function(select){
      select.addEventListener('change',function(){
        var option=select.options[select.selectedIndex];if(!option||!option.value){return;}
        addLine('extra',select.getAttribute('data-yc-extra')+': '+option.value,parseInt(option.getAttribute('data-price'),10)||0,parseInt(option.getAttribute('data-stock'),10)||0);
        select.value='';
      });
    });
    if(qty){qty.addEventListener('input',render);}
    function addLine(kind,label,price,stock){
      var existing=chosen.filter(function(c){return c.label===label;})[0];
      if(existing){existing.qty=Math.min(stock,existing.qty+1);}else{chosen.push({kind:kind,label:label,price:price,stock:stock,qty:1});}
      render();
    }
    function render(){
      if(list){list.innerHTML='';chosen.forEach(function(line,index){
        var li=document.createElement('li');
        var span=document.createElement('span');span.textContent=line.label+' ';li.appendChild(span);
        var input=document.createElement('input');input.type='number';input.min='1';input.max=String(Math.min(9999,line.stock));input.value=String(line.qty);input.className='input input-bordered input-xs';
        input.addEventListener('input',function(){var v=parseInt(input.value,10)||1;line.qty=Math.max(1,Math.min(line.stock,v));input.value=String(line.qty);render();});
        li.appendChild(input);
        var remove=document.createElement('button');remove.type='button';remove.className='btn btn-xs';remove.textContent='삭제';
        remove.addEventListener('click',function(){chosen.splice(index,1);render();});
        li.appendChild(remove);
        var sum=document.createElement('strong');sum.textContent=(line.price*line.qty).toLocaleString()+'원';li.appendChild(sum);
        list.appendChild(li);
      });}
      var amount=0;
      if(chosen.length){chosen.forEach(function(line){amount+=line.price*line.qty;});}
      else if(qty){amount=data.price*Math.max(1,Math.min(9999,parseInt(qty.value,10)||1));}
      else{amount=data.price;}
      if(total){total.textContent=amount.toLocaleString()+'원';}
    }
    render();
  }

  function setupGallery(gallery){
    var main=gallery.querySelector('[data-yc-main]');var link=main&&main.parentNode;
    [].slice.call(gallery.querySelectorAll('[data-yc-thumb]')).forEach(function(button,index){
      if(index===0){button.setAttribute('aria-current','true');}
      button.addEventListener('click',function(){
        if(main){main.src=button.getAttribute('data-yc-thumb');}
        if(link&&link.tagName==='A'){link.href=button.getAttribute('data-yc-large');}
        [].slice.call(gallery.querySelectorAll('[data-yc-thumb]')).forEach(function(b){b.removeAttribute('aria-current');});
        button.setAttribute('aria-current','true');
      });
    });
  }
})();
```

- [ ] **Step 8: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartPublicTest.php`
Expected: PASS. 실패하면 템플릿의 문구·링크 형식을 테스트 단언에 맞춘다(단언을 완화하지 않는다).

- [ ] **Step 9: 커밋**

```bash
git add modules/youngcart www/themes/default/youngcart.css www/themes/default/youngcart.js tests/Web/YoungCartPublicTest.php
git commit -m "feat: add youngcart storefront pages for main, category, type, search and item"
```

---

### Task 12: 관리자 기반, 현황·설치·설정

**Files:**
- Create: `modules/youngcart/src/Admin/AdminBase.php`, `modules/youngcart/src/Admin/AdminController.php`
- Create: `modules/youngcart/templates/admin/_nav.php`, `admin/_errors.php`, `admin/dashboard.php`, `admin/settings.php`
- Modify: `modules/youngcart/bootstrap.php`
- Test: `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- `abstract class AdminBase { __construct(protected Service $service, protected string $routePrefix, protected ?string $adminRoutePrefix) }`
  - `protected function context(ServerRequestInterface $request, string $page): array` — `base`, `admin_url`, `public_url`, `csrf_token`, `page`, `ready`, `errors` = [], `notice` = '', `input`, `actor`
  - `protected function input(ServerRequestInterface $request): array`(POST면 본문, 아니면 쿼리; 배열 값 허용)
  - `protected function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface`
  - `protected function redirect(ResponseInterface $response, string $url): ResponseInterface`
  - `protected function requireReady(ResponseInterface $response, array $context): ?ResponseInterface`(미설치면 현황으로 303)
  - `protected function page(mixed $value): int`
- `AdminController::handle(string $page, …)` — `dashboard`, `settings`
- 관리자 템플릿 공통 변수: 위 context + `title`. 레이아웃 `admin/extension`, `admin_section` = `modules`.

- [ ] **Step 1: 실패하는 웹 테스트 작성**

`tests/Web/YoungCartAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Extension\Catalog;
use GnuCms\Extension\Manager;
use GnuCms\Extension\StateStore;
use GnuCms\Modules\YoungCart\Service;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Tests\YoungCart\ImagesTest;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/modules/youngcart/autoload.php';

final class YoungCartAdminTest extends WebTestCase
{
    private App $app;
    private Service $shop;
    private string $root;

    private function setupModule(array $config, bool $install = true): void
    {
        session_name(GNUCMS_ID . '_session');
        session_start(); $_SESSION = []; session_write_close();
        $this->root = sys_get_temp_dir() . '/gnucms-yc-admin-' . bin2hex(random_bytes(8));
        $config['prefix'] = 'ya' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'uploads' => ['dir' => $this->root . '/uploads'], 'app' => ['url' => 'https://shop.example.test']]);
        (new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions')))->setEnabledMany(['modules/youngcart' => true]);
        $this->shop = new Service($this->app);
        if ($install) $this->shop->install();
    }

    private function signIn(bool $admin): int
    {
        $id = $this->app->users()->create(bin2hex(random_bytes(4)) . '@example.test', '', ($admin ? '운영자' : '회원') . bin2hex(random_bytes(3)), $admin);
        $this->get($this->app, '/login');
        session_start(); $_SESSION['user_id'] = $id; $_SESSION['session_epoch'] = 0; session_write_close();
        return (int) $id;
    }

    private function csrf(array $body = []): array { return $body + ['csrf_token' => $_SESSION['csrf_token']]; }

    #[DataProvider('connectionProvider')]
    public function testGuardsInstallAndSettings(array $config): void
    {
        $this->setupModule($config, false);
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop'), '/admin/shop');
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop/settings'), '/admin/shop/settings');
        $this->assertLoginRedirect($this->post($this->app, '/admin/shop', ['action' => 'install']));
        $this->signIn(false);
        self::assertSame(403, $this->get($this->app, '/admin/shop')->getStatusCode());
        $this->signIn(true);
        $dashboard = $this->body($this->get($this->app, '/admin/shop'));
        self::assertStringContainsString('데이터 설치', $dashboard);
        self::assertStringContainsString('설치되지 않았습니다', $dashboard);
        self::assertSame(403, $this->post($this->app, '/admin/shop', ['action' => 'install'])->getStatusCode());
        self::assertFalse($this->shop->ready());
        $response = $this->post($this->app, '/admin/shop', $this->csrf(['action' => 'install']));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/shop?installed=1', $response->getHeaderLine('Location'));
        self::assertTrue($this->shop->ready());
        $dashboard = $this->body($this->get($this->app, '/admin/shop', ['installed' => '1']));
        self::assertStringContainsString('설치했습니다', $dashboard);
        self::assertStringContainsString('상품 0', $dashboard);
        $settings = $this->body($this->get($this->app, '/admin/shop/settings'));
        self::assertStringContainsString('name="main_hit_use"', $settings);
        self::assertStringContainsString('name="category_columns" value="3"', $settings);
        $response = $this->post($this->app, '/admin/shop/settings', $this->csrf($this->settingsForm(['category_columns' => '13'])));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('1~12', $this->body($response));
        self::assertSame(3, $this->shop->settings->all()['category']['columns']);
        $response = $this->post($this->app, '/admin/shop/settings', $this->csrf($this->settingsForm(['category_columns' => '4', 'show_tax' => '1'])));
        self::assertSame('/admin/shop/settings?saved=1', $response->getHeaderLine('Location'));
        self::assertSame(4, $this->shop->settings->all()['category']['columns']);
        self::assertTrue($this->shop->settings->all()['show_tax']);
    }

    private function settingsForm(array $overrides): array
    {
        $form = [];
        foreach (['hit', 'new', 'recommend', 'discount', 'popular'] as $type) {
            $form += ['main_' . $type . '_use' => '1', 'main_' . $type . '_columns' => '4', 'main_' . $type . '_rows' => '1', 'main_' . $type . '_image_width' => '200', 'main_' . $type . '_image_height' => '0'];
        }
        foreach (['category', 'type', 'search'] as $section) {
            $form += [$section . '_columns' => '3', $section . '_rows' => '5', $section . '_image_width' => '200', $section . '_image_height' => '0'];
        }
        return $overrides + $form + ['related_use' => '1', 'related_columns' => '4', 'related_image_width' => '100', 'related_image_height' => '0',
            'detail_image_width' => '400', 'detail_image_height' => '0', 'shipping_content' => '', 'exchange_content' => ''];
    }

    #[DataProvider('connectionProvider')]
    public function testNotInstalledAdminPagesRedirectToDashboard(array $config): void
    {
        $this->setupModule($config, false);
        $this->signIn(true);
        $response = $this->get($this->app, '/admin/shop/settings');
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/admin/shop?install=1', $response->getHeaderLine('Location'));
    }
}
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php`
Expected: FAIL — `/admin/shop`이 빈 응답, `/admin/shop/settings`가 404.

- [ ] **Step 3: AdminBase와 AdminController 작성**

`modules/youngcart/src/Admin/AdminBase.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Admin;

use GnuCms\Modules\YoungCart\Service;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

abstract class AdminBase
{
    public function __construct(protected Service $service, protected string $routePrefix, protected ?string $adminRoutePrefix) {}

    protected function context(ServerRequestInterface $request, string $page): array
    {
        $base = RouteContext::fromRequest($request)->getBasePath();
        $identity = $this->service->app->guestAcl()->identity();
        return ['base' => $base, 'admin_url' => $base . ($this->adminRoutePrefix ?? '/admin/shop'), 'public_url' => $base . $this->routePrefix,
            'csrf_token' => $_SESSION['csrf_token'] ?? '', 'page' => $page, 'ready' => $this->service->ready(), 'errors' => [], 'notice' => '',
            'input' => $this->input($request), 'actor' => (string) ($identity->displayName() ?? $identity->sub() ?? 'admin')];
    }

    /** POST는 본문, GET은 쿼리. 배열 값은 그대로 두고 문자열·정수 외의 스칼라는 빈 문자열로 만든다. */
    protected function input(ServerRequestInterface $request): array
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        if (!is_array($input)) return [];
        foreach ($input as $key => $value) {
            if (!is_array($value) && !is_string($value) && !is_int($value)) $input[$key] = '';
        }
        return $input;
    }

    protected function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $view = View::forExtension($request, 'youngcart', dirname(__DIR__, 2) . '/templates');
        return $view->render($response->withHeader('Cache-Control', 'no-store'), 'admin/' . $template, $data);
    }

    protected function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Location', $url);
    }

    protected function requireReady(ResponseInterface $response, array $context): ?ResponseInterface
    {
        return $context['ready'] ? null : $this->redirect($response, $context['admin_url'] . '?install=1');
    }

    protected function page(mixed $value): int
    {
        return is_string($value) && preg_match('/^[1-9][0-9]{0,5}$/D', $value) ? (int) $value : 1;
    }
}
```

`modules/youngcart/src/Admin/AdminController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Admin;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Schema;
use GnuCms\Modules\YoungCart\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminController extends AdminBase
{
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->context($request, $page);
        if ($page === 'dashboard') return $this->dashboard($request, $response, $data);
        if ($page === 'settings') return $this->settings($request, $response, $data);
        throw DomainError::notFound('페이지를 찾을 수 없습니다.');
    }

    private function dashboard(ServerRequestInterface $request, ResponseInterface $response, array $data): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            if (($data['input']['action'] ?? '') !== 'install') throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
            $this->service->install();
            return $this->redirect($response, $data['admin_url'] . '?installed=1');
        }
        $data['status'] = $this->service->schema()->status(Schema::KEY);
        $data['version'] = Schema::VERSION;
        $data['stats'] = $data['ready'] ? $this->service->products->stats() : null;
        $data['low_stock'] = $data['ready'] ? $this->service->products->lowStock() : ['products' => [], 'options' => []];
        if (($data['input']['installed'] ?? '') === '1') $data['notice'] = '쇼핑몰 데이터를 설치했습니다.';
        if (($data['input']['install'] ?? '') === '1') $data['notice'] = '먼저 쇼핑몰 데이터를 설치해 주세요.';
        return $this->render($request, $response, 'dashboard', $data);
    }

    private function settings(ServerRequestInterface $request, ResponseInterface $response, array $data): ResponseInterface
    {
        if ($redirect = $this->requireReady($response, $data)) return $redirect;
        $data['types'] = Settings::TYPE_LABELS;
        if ($request->getMethod() === 'POST') {
            try {
                $this->service->settings->save($data['input']);
                return $this->redirect($response, $data['admin_url'] . '/settings?saved=1');
            } catch (DomainError $e) {
                $response = $response->withStatus($e->status());
                $data['errors'] = $e->details() ?: [$e->getMessage()];
                $data['values'] = $data['input'];
                return $this->render($request, $response, 'settings', $data);
            }
        }
        if (($data['input']['saved'] ?? '') === '1') $data['notice'] = '설정을 저장했습니다.';
        $data['values'] = $this->flatten($this->service->settings->all());
        return $this->render($request, $response, 'settings', $data);
    }

    /** 저장 구조를 폼 입력 이름으로 편다. */
    private function flatten(array $settings): array
    {
        $flat = [];
        foreach ($settings['main'] as $type => $block) foreach ($block as $key => $value) $flat['main_' . $type . '_' . $key] = $key === 'use' ? ($value ? '1' : '0') : (string) $value;
        foreach (['category', 'type', 'search', 'related', 'detail'] as $section) foreach ($settings[$section] as $key => $value) $flat[$section . '_' . $key] = $key === 'use' ? ($value ? '1' : '0') : (string) $value;
        $flat['show_tax'] = $settings['show_tax'] ? '1' : '0';
        $flat['shipping_content'] = $settings['shipping']['content'];
        $flat['exchange_content'] = $settings['exchange']['content'];
        return $flat;
    }
}
```

- [ ] **Step 4: bootstrap에 관리자 라우트 등록**

`modules/youngcart/bootstrap.php`의 마지막 줄(`$context->route('GET', '/', …, admin: true);`)을 아래로 교체한다:

```php
    $admin = new AdminController($service, $context->routePrefix, $context->adminRoutePrefix);
    $context->route('GET', '/', static fn ($request, $response) => $admin->handle('dashboard', $request, $response), admin: true);
    $context->route('POST', '/', static fn ($request, $response) => $admin->handle('dashboard', $request, $response), admin: true);
    $context->route('GET', '/settings', static fn ($request, $response) => $admin->handle('settings', $request, $response), admin: true);
    $context->route('POST', '/settings', static fn ($request, $response) => $admin->handle('settings', $request, $response), admin: true);
```

파일 상단 `use` 목록에 `use GnuCms\Modules\YoungCart\Admin\AdminController;`를 추가한다.

- [ ] **Step 5: 관리자 공통 조각과 화면 작성**

`modules/youngcart/templates/admin/_nav.php`(`$page`, `$admin_url`, `$public_url` 사용):

```php
<?php
$tabs = ['dashboard' => ['', '현황'], 'categories' => ['/categories', '분류'], 'products' => ['/products', '상품'], 'settings' => ['/settings', '설정']];
$current = str_starts_with($page, 'categories') ? 'categories' : (str_starts_with($page, 'products') ? 'products' : $page);
?>
<div class="extension-toolbar">
  <nav class="tabs tabs-border settings-tabs" aria-label="쇼핑몰 관리">
    <?php foreach ($tabs as $key => [$path, $label]): ?><a class="tab<?= $current === $key ? ' tab-active' : '' ?>" href="<?= $this->e($admin_url . $path) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach ?>
  </nav>
  <div class="row-actions"><a class="btn btn-sm" href="<?= $this->e($public_url) ?>" target="_blank" rel="noopener"><?= $this->icon('external', 15) ?> 쇼핑몰 보기</a></div>
</div>
<?php if ($current === 'products'): $sub = ['products' => ['/products', '목록'], 'products/new' => ['/products/new', '등록'], 'products/types' => ['/products/types', '유형'], 'products/stock' => ['/products/stock', '재고'], 'products/option-stock' => ['/products/option-stock', '옵션 재고']]; ?>
  <nav class="tabs tabs-sm yc-subtabs" aria-label="상품 관리"><?php foreach ($sub as $key => [$path, $label]): ?><a class="tab<?= $page === $key ? ' tab-active' : '' ?>" href="<?= $this->e($admin_url . $path) ?>"><?= $label ?></a><?php endforeach ?></nav>
<?php endif ?>
```

`modules/youngcart/templates/admin/_errors.php`(`$errors`, `$notice` 사용):

```php
<?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= $this->e($notice) ?></div><?php endif ?>
<?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $field => $message): ?><li><?= is_string($field) ? '<code>' . $this->e($field) . '</code> ' : '' ?><?= $this->e($message) ?></li><?php endforeach ?></ul></div><?php endif ?>
```

`modules/youngcart/templates/admin/dashboard.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>쇼핑몰 현황 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '쇼핑몰', 'description' => '영카트 모듈 현황', 'actions' => []]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<section class="card"><div class="card-body">
  <h2 class="card-title">데이터</h2>
  <?php if ($ready): ?>
    <p>스키마 <?= (int) $status['schema_version'] ?>판이 설치되어 있습니다.</p>
  <?php else: ?>
    <p class="muted">쇼핑몰 데이터가 설치되지 않았습니다. 설치하면 <code>yc_</code> 테이블 9개를 만듭니다. 상태: <?= $this->e($status['state'] ?? '없음') ?></p>
  <?php endif ?>
  <form method="post" action="<?= $this->e($admin_url) ?>">
    <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="action" value="install">
    <button class="btn btn-sm btn-primary" type="submit"><?= $ready ? '데이터 갱신' : '데이터 설치' ?></button>
  </form>
</div></section>
<?php if ($stats !== null): ?>
<section class="card"><div class="card-body">
  <h2 class="card-title">요약</h2>
  <ul class="yc-stats">
    <li>상품 <?= $stats['products'] ?></li><li>판매중 <?= $stats['active'] ?></li><li>품절 <?= $stats['sold_out'] ?></li><li>분류 <?= $stats['categories'] ?></li>
  </ul>
</div></section>
<section class="card"><div class="card-body">
  <h2 class="card-title">재고 부족</h2>
  <?php if ($low_stock['products'] === [] && $low_stock['options'] === []): ?><p class="muted">통보 기준 이하의 재고가 없습니다.</p><?php else: ?>
    <table class="table table-sm"><thead><tr><th>상품</th><th>옵션</th><th>재고</th><th>통보 기준</th></tr></thead><tbody>
      <?php foreach ($low_stock['products'] as $row): ?><tr><td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['id'] ?>"><?= $this->e($row['name']) ?></a></td><td>—</td><td><?= (int) $row['stock'] ?></td><td><?= (int) $row['stock_alert'] ?></td></tr><?php endforeach ?>
      <?php foreach ($low_stock['options'] as $row): ?><tr><td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['product_id'] ?>"><?= $this->e($row['name']) ?></a></td><td><?= $this->e(implode(' / ', array_filter([$row['value1'], $row['value2'], $row['value3']]))) ?></td><td><?= (int) $row['stock'] ?></td><td><?= (int) $row['stock_alert'] ?></td></tr><?php endforeach ?>
    </tbody></table>
  <?php endif ?>
</div></section>
<?php endif ?>
<?php $this->stop() ?>
```

`modules/youngcart/templates/admin/settings.php`(`$values`(평탄한 입력 이름 배열), `$types`):

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>쇼핑몰 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '쇼핑몰 설정', 'description' => '메인·목록·검색·상세 화면의 크기와 안내문', 'actions' => []]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php $num = function (string $name, string $label, int $min, int $max) use ($values, $errors): void { ?>
  <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><?= $this->e($label) ?></legend>
    <input class="input input-bordered input-sm" type="number" name="<?= $this->e($name) ?>" value="<?= $this->e((string) ($values[$name] ?? '')) ?>" min="<?= $min ?>" max="<?= $max ?>" required>
    <?php if (isset($errors[$name])): ?><p class="validator-hint"><?= $this->e($errors[$name]) ?></p><?php endif ?></fieldset>
<?php }; ?>
<form method="post" action="<?= $this->e($admin_url) ?>/settings">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <section class="card"><div class="card-body"><h2 class="card-title">메인 화면 블록</h2>
    <?php foreach ($types as $type => $label): ?>
      <div class="form-section"><h3 class="form-section-title"><?= $this->e($label) ?></h3>
        <label class="label cursor-pointer"><input type="hidden" name="main_<?= $type ?>_use" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="main_<?= $type ?>_use" value="1"<?= ($values['main_' . $type . '_use'] ?? '') === '1' ? ' checked' : '' ?>> 메인에 표시</label>
        <div class="yc-fields"><?php $num('main_' . $type . '_columns', '한 행 상품 수', 1, 12); $num('main_' . $type . '_rows', '행 수', 1, 50); $num('main_' . $type . '_image_width', '이미지 너비', 0, 2000); $num('main_' . $type . '_image_height', '이미지 높이(0 = 비율)', 0, 2000); ?></div>
      </div>
    <?php endforeach ?>
  </div></section>
  <?php foreach (['category' => '분류 목록 기본값(새 분류에 적용)', 'type' => '유형별 목록', 'search' => '검색 결과'] as $section => $label): ?>
    <section class="card"><div class="card-body"><h2 class="card-title"><?= $label ?></h2>
      <div class="yc-fields"><?php $num($section . '_columns', '한 행 상품 수', 1, 12); $num($section . '_rows', '행 수', 1, 50); $num($section . '_image_width', '이미지 너비', 0, 2000); $num($section . '_image_height', '이미지 높이(0 = 비율)', 0, 2000); ?></div>
    </div></section>
  <?php endforeach ?>
  <section class="card"><div class="card-body"><h2 class="card-title">관련상품과 상세</h2>
    <label class="label cursor-pointer"><input type="hidden" name="related_use" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="related_use" value="1"<?= ($values['related_use'] ?? '') === '1' ? ' checked' : '' ?>> 상세에 관련상품 표시</label>
    <div class="yc-fields"><?php $num('related_columns', '관련상품 한 행 수', 1, 12); $num('related_image_width', '관련상품 이미지 너비', 0, 2000); $num('related_image_height', '관련상품 이미지 높이', 0, 2000); $num('detail_image_width', '상세 대표 이미지 너비', 0, 2000); $num('detail_image_height', '상세 대표 이미지 높이', 0, 2000); ?></div>
    <label class="label cursor-pointer"><input type="hidden" name="show_tax" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="show_tax" value="1"<?= ($values['show_tax'] ?? '') === '1' ? ' checked' : '' ?>> 가격 옆에 부가세 포함 표시</label>
  </div></section>
  <section class="card"><div class="card-body"><h2 class="card-title">안내문</h2>
    <fieldset class="fieldset"><legend class="fieldset-legend">배송정보 탭</legend><textarea class="textarea textarea-bordered textarea-block" name="shipping_content" rows="6"><?= $this->e((string) ($values['shipping_content'] ?? '')) ?></textarea></fieldset>
    <fieldset class="fieldset"><legend class="fieldset-legend">교환정보 탭</legend><textarea class="textarea textarea-bordered textarea-block" name="exchange_content" rows="6"><?= $this->e((string) ($values['exchange_content'] ?? '')) ?></textarea></fieldset>
  </div></section>
  <div class="form-actions"><button class="btn btn-primary" type="submit">저장</button></div>
</form>
<?php $this->stop() ?>
```

체크박스는 hidden `0` 뒤에 checkbox `1`을 두어 JS 없이도 꺼진 값이 전달된다. `www/themes/default/youngcart.css` 끝에 관리자용 규칙을 추가한다:

```css
.yc-fields{display:grid;grid-template-columns:repeat(auto-fill,minmax(12rem,1fr));gap:.5rem 1rem}
.yc-stats{display:flex;flex-wrap:wrap;gap:1rem;list-style:none;padding:0;margin:0}
.yc-subtabs{margin-bottom:.75rem}
.yc-table-input{width:6rem}
.yc-combo-table td input{width:6.5rem}
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php tests/Web/YoungCartPublicTest.php`
Expected: PASS

- [ ] **Step 7: 커밋**

```bash
git add modules/youngcart www/themes/default/youngcart.css tests/Web/YoungCartAdminTest.php
git commit -m "feat: add youngcart admin dashboard, installer and settings screens"
```

---

### Task 13: 분류 관리 화면

**Files:**
- Create: `modules/youngcart/src/Admin/CategoryController.php`
- Create: `modules/youngcart/templates/admin/categories.php`, `admin/category_form.php`
- Modify: `modules/youngcart/bootstrap.php`
- Test: `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- `CategoryController::handle(string $page, …)` — `categories`(GET 목록, POST `action=bulk|delete`), `categories/new`(GET `?parent=코드`, POST), `categories/edit`(GET `?id=`, POST)
- 폼 필드 이름은 `Categories::save()`의 입력 키와 같다. 목록 일괄 편집은 `rows[id][field]`, 삭제는 `id`.

- [ ] **Step 1: 실패하는 웹 테스트 추가**

`tests/Web/YoungCartAdminTest.php` 클래스 끝에 추가:

```php
    #[DataProvider('connectionProvider')]
    public function testCategoryScreens(array $config): void
    {
        $this->setupModule($config);
        $this->signIn(true);
        $form = $this->body($this->get($this->app, '/admin/shop/categories/new'));
        self::assertStringContainsString('name="code" value="10"', $form);
        $response = $this->post($this->app, '/admin/shop/categories/new', $this->csrf(['code' => '10', 'name' => '의류', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        self::assertSame(303, $response->getStatusCode());
        $top = $this->shop->categories->byCode('10');
        self::assertSame('/admin/shop/categories/edit?id=' . $top['id'] . '&saved=1', $response->getHeaderLine('Location'));
        $response = $this->post($this->app, '/admin/shop/categories/new', $this->csrf(['code' => '10', 'name' => '중복', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('이미 사용 중인 분류 코드', $this->body($response));
        self::assertStringContainsString('value="중복"', $this->body($response));
        self::assertStringContainsString('name="code" value="1010"', $this->body($this->get($this->app, '/admin/shop/categories/new', ['parent' => '10'])));
        $this->post($this->app, '/admin/shop/categories/new', $this->csrf(['code' => '1010', 'name' => '셔츠', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        $list = $this->body($this->get($this->app, '/admin/shop/categories'));
        self::assertStringContainsString('의류', $list); self::assertStringContainsString('셔츠', $list);
        self::assertStringContainsString('name="rows[' . $top['id'] . '][name]"', $list);
        $edit = $this->body($this->get($this->app, '/admin/shop/categories/edit', ['id' => (string) $top['id']]));
        self::assertStringContainsString('value="의류"', $edit); self::assertStringContainsString('apply_children', $edit);
        $response = $this->post($this->app, '/admin/shop/categories/edit', $this->csrf(['id' => (string) $top['id'], 'name' => '의류(수정)', 'active' => '0', 'list_columns' => '4', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0', 'apply_children' => '1']));
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(0, (int) $this->shop->categories->byCode('1010')['active']);
        $child = $this->shop->categories->byCode('1010');
        $response = $this->post($this->app, '/admin/shop/categories', $this->csrf(['action' => 'bulk', 'rows' => [$child['id'] => ['name' => '셔츠(일괄)', 'sort_order' => '1', 'active' => '1', 'list_columns' => '2', 'list_rows' => '2', 'image_width' => '100', 'image_height' => '0']]]));
        self::assertSame('/admin/shop/categories?saved=1', $response->getHeaderLine('Location'));
        self::assertSame('셔츠(일괄)', $this->shop->categories->byCode('1010')['name']);
        $response = $this->post($this->app, '/admin/shop/categories', $this->csrf(['action' => 'delete', 'id' => (string) $top['id']]));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('하위 분류가 있어', $this->body($response));
        $response = $this->post($this->app, '/admin/shop/categories', $this->csrf(['action' => 'delete', 'id' => (string) $child['id']]));
        self::assertSame(303, $response->getStatusCode());
        self::assertNull($this->shop->categories->byCode('1010'));
        self::assertSame(404, $this->get($this->app, '/admin/shop/categories/edit', ['id' => '999'])->getStatusCode());
        self::assertSame(403, $this->post($this->app, '/admin/shop/categories', ['action' => 'delete', 'id' => (string) $top['id']])->getStatusCode());
    }
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php --filter testCategoryScreens`
Expected: FAIL — 404.

- [ ] **Step 3: CategoryController 작성**

`modules/youngcart/src/Admin/CategoryController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Admin;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Input;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CategoryController extends AdminBase
{
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->context($request, $page);
        if ($redirect = $this->requireReady($response, $data)) return $redirect;
        $input = $data['input'];
        $categories = $this->service->categories;
        try {
            if ($page === 'categories') {
                if ($request->getMethod() === 'POST') {
                    $action = $input['action'] ?? '';
                    if ($action === 'bulk') $categories->bulk(is_array($input['rows'] ?? null) ? $input['rows'] : []);
                    elseif ($action === 'delete') $categories->delete(Input::id($input['id'] ?? ''));
                    else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                    return $this->redirect($response, $data['admin_url'] . '/categories?saved=1');
                }
                if (($input['saved'] ?? '') === '1') $data['notice'] = '분류를 저장했습니다.';
                return $this->list($request, $response, $data);
            }
            if ($page === 'categories/new' || $page === 'categories/edit') {
                $id = $page === 'categories/edit' ? Input::id($input['id'] ?? '') : null;
                if ($request->getMethod() === 'POST') {
                    $saved = $categories->save($input, $id);
                    return $this->redirect($response, $data['admin_url'] . '/categories/edit?id=' . $saved . '&saved=1');
                }
                $values = $id === null ? $this->defaults($input) : $categories->get($id);
                if (($input['saved'] ?? '') === '1') $data['notice'] = '분류를 저장했습니다.';
                return $this->form($request, $response, $data, $values, $id);
            }
        } catch (DomainError $e) {
            if ($e->status() === 404) throw $e;
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->details() ?: [$e->getMessage()];
            if ($page === 'categories') return $this->list($request, $response, $data);
            $id = $page === 'categories/edit' ? Input::id($input['id'] ?? '') : null;
            return $this->form($request, $response, $data, $input + ($id === null ? [] : ['code' => $categories->get($id)['code']]), $id);
        }
        throw DomainError::notFound('페이지를 찾을 수 없습니다.');
    }

    private function defaults(array $input): array
    {
        $parent = is_string($input['parent'] ?? null) && preg_match('/^[0-9a-z]{2,10}$/D', $input['parent']) ? $input['parent'] : null;
        $block = $this->service->settings->block('category');
        return ['code' => (string) $this->service->categories->suggestCode($parent), 'name' => '', 'sort_order' => '0', 'active' => '1', 'no_coupon' => '0',
            'head_html' => '', 'tail_html' => '', 'list_columns' => (string) $block['columns'], 'list_rows' => (string) $block['rows'],
            'image_width' => (string) $block['image_width'], 'image_height' => (string) $block['image_height'], 'extra' => []];
    }

    private function list(ServerRequestInterface $request, ResponseInterface $response, array $data): ResponseInterface
    {
        $data['tree'] = $this->service->categories->tree();
        return $this->render($request, $response, 'categories', $data);
    }

    private function form(ServerRequestInterface $request, ResponseInterface $response, array $data, array $values, ?int $id): ResponseInterface
    {
        $data['values'] = $values;
        $data['id'] = $id;
        $data['extra'] = [];
        for ($i = 1; $i <= 10; $i++) {
            $data['extra'][$i] = ['label' => (string) ($values['extra_label'][$i] ?? $values['extra'][$i - 1]['label'] ?? ''), 'value' => (string) ($values['extra_value'][$i] ?? $values['extra'][$i - 1]['value'] ?? '')];
        }
        return $this->render($request, $response, 'category_form', $data);
    }
}
```

- [ ] **Step 4: bootstrap에 라우트 추가**

`modules/youngcart/bootstrap.php`의 `$admin` 라우트 뒤에 추가하고 `use GnuCms\Modules\YoungCart\Admin\CategoryController;`를 상단에 넣는다:

```php
    $category = new CategoryController($service, $context->routePrefix, $context->adminRoutePrefix);
    foreach (['categories', 'categories/new', 'categories/edit'] as $page) {
        foreach (['GET', 'POST'] as $method) {
            $context->route($method, '/' . $page, static fn ($request, $response) => $category->handle($page, $request, $response), admin: true);
        }
    }
```

- [ ] **Step 5: 템플릿 작성**

`modules/youngcart/templates/admin/categories.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>분류 관리 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '분류 관리', 'description' => '분류 코드는 단계당 2자, 최대 5단계입니다.', 'actions' => [['url' => $admin_url . '/categories/new', 'label' => '1단계 분류 추가']]]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php if ($tree === []): ?><p class="muted">분류가 없습니다. 먼저 1단계 분류를 추가해 주세요.</p><?php else: ?>
<form method="post" action="<?= $this->e($admin_url) ?>/categories">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="action" value="bulk">
  <div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th>코드</th><th>분류명</th><th>순서</th><th>판매</th><th>열</th><th>행</th><th>이미지 폭</th><th>이미지 높이</th><th>상품</th><th></th></tr></thead><tbody>
    <?php foreach ($tree as $row): $n = 'rows[' . (int) $row['id'] . ']'; ?>
      <tr>
        <td><code><?= $this->e($row['code']) ?></code></td>
        <td style="padding-left:<?= ((int) $row['depth'] - 1) * 1.25 ?>rem"><input class="input input-bordered input-xs" type="text" name="<?= $n ?>[name]" value="<?= $this->e($row['name']) ?>" maxlength="100" required></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[sort_order]" value="<?= (int) $row['sort_order'] ?>"></td>
        <td><input type="hidden" name="<?= $n ?>[active]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[active]" value="1"<?= (int) $row['active'] === 1 ? ' checked' : '' ?>></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[list_columns]" value="<?= (int) $row['list_columns'] ?>" min="1" max="12"></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[list_rows]" value="<?= (int) $row['list_rows'] ?>" min="1" max="50"></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[image_width]" value="<?= (int) $row['image_width'] ?>" min="0" max="2000"></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[image_height]" value="<?= (int) $row['image_height'] ?>" min="0" max="2000"></td>
        <td><?= (int) $row['product_count'] ?></td>
        <td class="row-actions">
          <a class="btn btn-xs" href="<?= $this->e($admin_url) ?>/categories/edit?id=<?= (int) $row['id'] ?>">수정</a>
          <?php if ((int) $row['depth'] < 5): ?><a class="btn btn-xs" href="<?= $this->e($admin_url) ?>/categories/new?parent=<?= $this->e($row['code']) ?>">하위 추가</a><?php endif ?>
          <a class="btn btn-xs btn-outline" href="<?= $this->e($public_url) ?>/list?ca=<?= $this->e($row['code']) ?>" target="_blank" rel="noopener">보기</a>
        </td>
      </tr>
    <?php endforeach ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">선택 내용 일괄 저장</button></div>
</form>
<h2 class="card-title">삭제</h2>
<p class="muted">하위 분류나 연결된 상품이 있으면 삭제할 수 없습니다.</p>
<?php foreach ($tree as $row): ?>
  <form method="post" action="<?= $this->e($admin_url) ?>/categories" class="inline" onsubmit="return confirm('<?= $this->e($row['name']) ?> 분류를 삭제할까요?')">
    <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <button class="btn btn-xs btn-error btn-outline" type="submit"><?= $this->e($row['code']) ?> <?= $this->e($row['name']) ?> 삭제</button>
  </form>
<?php endforeach ?>
<?php endif ?>
<?php $this->stop() ?>
```

`modules/youngcart/templates/admin/category_form.php`(`$values`, `$id`, `$extra`):

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?><?= $id === null ? '분류 추가' : '분류 수정' ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => $id === null ? '분류 추가' : '분류 수정', 'description' => '', 'actions' => [['url' => $admin_url . '/categories', 'label' => '분류 목록']]]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php $v = static fn (string $key): string => (string) ($values[$key] ?? ''); ?>
<form method="post" action="<?= $this->e($admin_url) ?>/categories/<?= $id === null ? 'new' : 'edit' ?>">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif ?>
  <section class="card"><div class="card-body">
    <fieldset class="fieldset<?= isset($errors['code']) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend">분류 코드 <span class="legend-hint">단계당 2자, 영문 소문자·숫자</span></legend>
      <?php if ($id === null): ?><input class="input input-bordered" type="text" name="code" value="<?= $this->e($v('code')) ?>" maxlength="10" pattern="[0-9a-zA-Z]{2,10}" required>
      <?php else: ?><input class="input input-bordered" type="text" value="<?= $this->e($v('code')) ?>" readonly><?php endif ?>
      <?php if (isset($errors['code'])): ?><p class="validator-hint"><?= $this->e($errors['code']) ?></p><?php endif ?></fieldset>
    <fieldset class="fieldset<?= isset($errors['name']) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend">분류명</legend><input class="input input-bordered input-block" type="text" name="name" value="<?= $this->e($v('name')) ?>" maxlength="100" required></fieldset>
    <div class="yc-fields">
      <fieldset class="fieldset"><legend class="fieldset-legend">순서</legend><input class="input input-bordered input-sm" type="number" name="sort_order" value="<?= $this->e($v('sort_order')) ?>"></fieldset>
      <fieldset class="fieldset"><legend class="fieldset-legend">한 행 상품 수</legend><input class="input input-bordered input-sm" type="number" name="list_columns" value="<?= $this->e($v('list_columns')) ?>" min="1" max="12" required></fieldset>
      <fieldset class="fieldset"><legend class="fieldset-legend">행 수</legend><input class="input input-bordered input-sm" type="number" name="list_rows" value="<?= $this->e($v('list_rows')) ?>" min="1" max="50" required></fieldset>
      <fieldset class="fieldset"><legend class="fieldset-legend">이미지 너비</legend><input class="input input-bordered input-sm" type="number" name="image_width" value="<?= $this->e($v('image_width')) ?>" min="0" max="2000" required></fieldset>
      <fieldset class="fieldset"><legend class="fieldset-legend">이미지 높이(0 = 비율)</legend><input class="input input-bordered input-sm" type="number" name="image_height" value="<?= $this->e($v('image_height')) ?>" min="0" max="2000" required></fieldset>
    </div>
    <label class="label cursor-pointer"><input type="hidden" name="active" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="active" value="1"<?= $v('active') === '1' ? ' checked' : '' ?>> 판매가능</label>
    <label class="label cursor-pointer"><input type="hidden" name="no_coupon" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="no_coupon" value="1"<?= $v('no_coupon') === '1' ? ' checked' : '' ?>> 쿠폰 대상에서 제외</label>
    <?php if ($id !== null): ?><label class="label cursor-pointer"><input class="checkbox checkbox-sm" type="checkbox" name="apply_children" value="1"> 판매·쿠폰·목록·이미지 설정을 하위 분류에도 적용</label><?php endif ?>
  </div></section>
  <section class="card"><div class="card-body"><h2 class="card-title">목록 위·아래 HTML</h2>
    <fieldset class="fieldset"><legend class="fieldset-legend">목록 위</legend><textarea class="textarea textarea-bordered textarea-block" name="head_html" rows="4"><?= $this->e($v('head_html')) ?></textarea></fieldset>
    <fieldset class="fieldset"><legend class="fieldset-legend">목록 아래</legend><textarea class="textarea textarea-bordered textarea-block" name="tail_html" rows="4"><?= $this->e($v('tail_html')) ?></textarea></fieldset>
  </div></section>
  <section class="card"><div class="card-body"><h2 class="card-title">여분필드</h2>
    <div class="yc-fields"><?php foreach ($extra as $i => $field): ?><fieldset class="fieldset"><legend class="fieldset-legend">여분 <?= $i ?></legend>
      <input class="input input-bordered input-sm" type="text" name="extra_label[<?= $i ?>]" value="<?= $this->e($field['label']) ?>" maxlength="100" placeholder="라벨">
      <input class="input input-bordered input-sm" type="text" name="extra_value[<?= $i ?>]" value="<?= $this->e($field['value']) ?>" maxlength="1000" placeholder="값"></fieldset><?php endforeach ?></div>
  </div></section>
  <div class="form-actions"><button class="btn btn-primary" type="submit">저장</button></div>
</form>
<?php $this->stop() ?>
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php`
Expected: PASS

- [ ] **Step 7: 커밋**

```bash
git add modules/youngcart tests/Web/YoungCartAdminTest.php
git commit -m "feat: add youngcart category admin screens"
```

---

### Task 14: 상품 목록·복사·유형·재고·검색 관리 화면

**Files:**
- Create: `modules/youngcart/src/Admin/ProductController.php`
- Create: `modules/youngcart/templates/admin/products.php`, `admin/product_types.php`, `admin/product_stock.php`, `admin/option_stock.php`
- Modify: `modules/youngcart/bootstrap.php`
- Test: `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- `ProductController::handle(string $page, …)` — `products`(GET 목록, POST `action=bulk|delete`), `products/copy`(POST `id`, `code`), `products/types`(GET/POST `rows[id][is_*]`), `products/stock`(GET/POST `rows[id][…]`), `products/option-stock`(GET/POST `rows[optionId][…]`), `products/search`(GET JSON `{"items":[…]}`)
- 목록 필터 쿼리: `q`, `field`, `ca`, `sort`, `dir`, `page`

- [ ] **Step 1: 실패하는 웹 테스트 추가**

`tests/Web/YoungCartAdminTest.php` 클래스 끝에 추가:

```php
    private function seedProducts(): array
    {
        $top = $this->shop->categories->get($this->shop->categories->save(['code' => '10', 'name' => '의류', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        $other = $this->shop->categories->get($this->shop->categories->save(['code' => '20', 'name' => '잡화', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']));
        $a = $this->shop->products->save(['code' => 'A1', 'name' => '파란 셔츠', 'category_id' => (string) $top['id'], 'price' => '300', 'stock' => '3', 'active' => '1', 'stock_alert' => '5',
            'option_group' => [1 => '색상'], 'options' => [['value1' => '빨강', 'price' => '0', 'stock' => '1', 'stock_alert' => '2', 'active' => '1']]], []);
        $b = $this->shop->products->save(['code' => 'B1', 'name' => '가방', 'category_id' => (string) $other['id'], 'price' => '100', 'stock' => '0', 'active' => '1'], []);
        return ['top' => $top, 'other' => $other, 'a' => $a, 'b' => $b];
    }

    #[DataProvider('connectionProvider')]
    public function testProductListBulkCopyTypesStockAndSearch(array $config): void
    {
        $this->setupModule($config);
        $seed = $this->seedProducts();
        $this->signIn(true);
        $list = $this->body($this->get($this->app, '/admin/shop/products'));
        self::assertStringContainsString('파란 셔츠', $list); self::assertStringContainsString('가방', $list);
        self::assertStringContainsString('name="rows[' . $seed['a'] . '][price]"', $list);
        self::assertStringNotContainsString('가방', $this->body($this->get($this->app, '/admin/shop/products', ['q' => '셔츠'])));
        self::assertStringNotContainsString('파란 셔츠', $this->body($this->get($this->app, '/admin/shop/products', ['ca' => '20'])));
        $response = $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'bulk', 'rows' => [$seed['b'] => ['category_id' => (string) $seed['top']['id'], 'name' => '가방(일괄)', 'list_price' => '0', 'price' => '150', 'stock' => '2', 'active' => '1', 'sold_out' => '0', 'sort_order' => '1']]]));
        self::assertSame('/admin/shop/products?saved=1', $response->getHeaderLine('Location'));
        self::assertSame('가방(일괄)', $this->shop->products->find($seed['b'])['name']);
        $response = $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'bulk', 'rows' => [$seed['b'] => ['category_id' => '999', 'name' => 'x', 'price' => '1']]]));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('분류를 찾을 수 없습니다', $this->body($response));
        $response = $this->post($this->app, '/admin/shop/products/copy', $this->csrf(['id' => (string) $seed['a'], 'code' => 'A2']));
        $copy = $this->shop->products->byCode('A2');
        self::assertNotNull($copy);
        self::assertSame('/admin/shop/products/edit?id=' . $copy['id'] . '&saved=1', $response->getHeaderLine('Location'));
        self::assertCount(1, $copy['options']['select']);
        self::assertSame(422, $this->post($this->app, '/admin/shop/products/copy', $this->csrf(['id' => (string) $seed['a'], 'code' => 'A2']))->getStatusCode());
        $types = $this->body($this->get($this->app, '/admin/shop/products/types'));
        self::assertStringContainsString('name="rows[' . $seed['a'] . '][is_hit]"', $types);
        $this->post($this->app, '/admin/shop/products/types', $this->csrf(['rows' => [$seed['a'] => ['is_hit' => '1', 'is_new' => '1']]]));
        self::assertSame(1, (int) $this->shop->products->find($seed['a'])['is_new']);
        $stock = $this->body($this->get($this->app, '/admin/shop/products/stock'));
        self::assertStringContainsString('name="rows[' . $seed['a'] . '][stock]"', $stock);
        $this->post($this->app, '/admin/shop/products/stock', $this->csrf(['rows' => [$seed['a'] => ['stock' => '9', 'stock_alert' => '1', 'active' => '1', 'sold_out' => '0']]]));
        self::assertSame(9, (int) $this->shop->products->find($seed['a'])['stock']);
        $optionId = (int) $this->shop->products->get($seed['a'])['options']['select'][0]['id'];
        $optionStock = $this->body($this->get($this->app, '/admin/shop/products/option-stock'));
        self::assertStringContainsString('name="rows[' . $optionId . '][stock]"', $optionStock);
        self::assertStringContainsString('빨강', $optionStock);
        $this->post($this->app, '/admin/shop/products/option-stock', $this->csrf(['rows' => [$optionId => ['stock' => '4', 'stock_alert' => '0', 'active' => '1']]]));
        self::assertSame(4, (int) $this->shop->products->get($seed['a'])['options']['select'][0]['stock']);
        $json = $this->get($this->app, '/admin/shop/products/search', ['q' => '가방', 'exclude' => (string) $seed['a']]);
        self::assertSame('application/json; charset=utf-8', $json->getHeaderLine('Content-Type'));
        $decoded = json_decode($this->body($json), true);
        self::assertSame('B1', $decoded['items'][0]['code']); self::assertSame('의류', $decoded['items'][0]['category_name']);
        self::assertSame([], json_decode($this->body($this->get($this->app, '/admin/shop/products/search', ['q' => '셔츠', 'exclude' => (string) $seed['a'], 'ca' => '20'])), true)['items']);
        $response = $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'delete', 'ids' => [(string) $copy['id']]]));
        self::assertSame(303, $response->getStatusCode());
        self::assertNull($this->shop->products->find((int) $copy['id']));
        session_start(); $_SESSION = []; session_write_close();
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop/products/search', ['q' => 'x']), '/admin/shop/products/search?q=x');
    }
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php --filter testProductListBulkCopyTypesStockAndSearch`
Expected: FAIL — 404.

- [ ] **Step 3: ProductController 작성**

`modules/youngcart/src/Admin/ProductController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Admin;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Catalog\Products;
use GnuCms\Modules\YoungCart\Input;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController extends AdminBase
{
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->context($request, $page);
        if ($page === 'products/search') {
            if ($redirect = $this->requireReady($response, $data)) return $redirect;
            return $this->searchJson($response, $data['input']);
        }
        if ($redirect = $this->requireReady($response, $data)) return $redirect;
        $input = $data['input'];
        $products = $this->service->products;
        $post = $request->getMethod() === 'POST';
        $rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];
        try {
            if ($post) {
                switch ($page) {
                    case 'products':
                        $action = $input['action'] ?? '';
                        if ($action === 'bulk') $products->bulk($rows, $data['actor']);
                        elseif ($action === 'delete') $products->bulkDelete(is_array($input['ids'] ?? null) ? $input['ids'] : []);
                        else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                        return $this->redirect($response, $data['admin_url'] . '/products?saved=1');
                    case 'products/copy':
                        $id = $products->copy(Input::id($input['id'] ?? ''), is_string($input['code'] ?? null) ? $input['code'] : '', $data['actor']);
                        return $this->redirect($response, $data['admin_url'] . '/products/edit?id=' . $id . '&saved=1');
                    case 'products/types':
                        $products->setTypes($rows);
                        return $this->redirect($response, $data['admin_url'] . '/products/types?saved=1');
                    case 'products/stock':
                        $products->updateStock($rows, $data['actor']);
                        return $this->redirect($response, $data['admin_url'] . '/products/stock?saved=1');
                    case 'products/option-stock':
                        $this->service->options->updateStock($rows, $data['actor']);
                        return $this->redirect($response, $data['admin_url'] . '/products/option-stock?saved=1');
                }
                throw DomainError::notFound('페이지를 찾을 수 없습니다.');
            }
        } catch (DomainError $e) {
            if ($e->status() === 404) throw $e;
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->details() ?: [$e->getMessage()];
            $page = $page === 'products/copy' ? 'products' : $page;
            $data['page'] = $page;
        }
        if (($input['saved'] ?? '') === '1') $data['notice'] = '저장했습니다.';
        $q = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100, 'UTF-8') : '';
        switch ($page) {
            case 'products':
            case 'products/types':
                $filters = ['q' => $q, 'field' => is_string($input['field'] ?? null) ? $input['field'] : 'name', 'ca' => is_string($input['ca'] ?? null) ? $input['ca'] : '',
                    'sort' => is_string($input['sort'] ?? null) ? $input['sort'] : '', 'dir' => is_string($input['dir'] ?? null) ? $input['dir'] : 'desc'];
                $data['filters'] = $filters;
                $data['list'] = $products->list($filters, $this->page($input['page'] ?? ''));
                $data['categories'] = $this->service->categories->options();
                $data['category_codes'] = array_column($this->service->categories->tree(), 'name', 'code');
                $data['fields'] = Products::SEARCH_FIELDS;
                $data['sorts'] = Products::SORTS;
                $data['types'] = Products::TYPES;
                return $this->render($request, $response, $page === 'products' ? 'products' : 'product_types', $data);
            case 'products/stock':
                $data['q'] = $q;
                $data['list'] = $products->stockList($q, $this->page($input['page'] ?? ''), 30);
                return $this->render($request, $response, 'product_stock', $data);
            case 'products/option-stock':
                $data['q'] = $q;
                $data['list'] = $this->service->options->stockList($q, $this->page($input['page'] ?? ''), 30);
                return $this->render($request, $response, 'option_stock', $data);
        }
        throw DomainError::notFound('페이지를 찾을 수 없습니다.');
    }

    private function searchJson(ResponseInterface $response, array $input): ResponseInterface
    {
        $q = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100, 'UTF-8') : '';
        $ca = is_string($input['ca'] ?? null) ? $input['ca'] : '';
        $items = $this->service->products->search($q, $ca, Input::optionalId($input['exclude'] ?? ''));
        $response->getBody()->write((string) json_encode(['items' => array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'price' => (int) $r['price'], 'category_name' => $r['category_name'] ?? ''], $items)], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }
}
```

- [ ] **Step 4: bootstrap에 라우트 추가**

`modules/youngcart/bootstrap.php`의 `$category` 라우트 뒤에 추가하고 `use GnuCms\Modules\YoungCart\Admin\ProductController;`를 상단에 넣는다:

```php
    $product = new ProductController($service, $context->routePrefix, $context->adminRoutePrefix);
    foreach (['products', 'products/types', 'products/stock', 'products/option-stock'] as $page) {
        foreach (['GET', 'POST'] as $method) {
            $context->route($method, '/' . $page, static fn ($request, $response) => $product->handle($page, $request, $response), admin: true);
        }
    }
    $context->route('POST', '/products/copy', static fn ($request, $response) => $product->handle('products/copy', $request, $response), admin: true);
    $context->route('GET', '/products/search', static fn ($request, $response) => $product->handle('products/search', $request, $response), admin: true);
```

- [ ] **Step 5: 템플릿 작성**

`modules/youngcart/templates/admin/_filters.php`(`$filters`, `$category_codes`, `$fields`, `$action` 사용):

```php
<form class="yc-filter" method="get" action="<?= $this->e($action) ?>">
  <select class="select select-bordered select-sm" name="field" aria-label="검색 필드"><?php foreach ($fields as $field): ?><option value="<?= $field ?>"<?= $filters['field'] === $field ? ' selected' : '' ?>><?= ['name' => '상품명', 'code' => '코드'][$field] ?></option><?php endforeach ?></select>
  <input class="input input-bordered input-sm" type="search" name="q" value="<?= $this->e($filters['q']) ?>" maxlength="100" placeholder="검색어" aria-label="검색어">
  <select class="select select-bordered select-sm" name="ca" aria-label="분류"><option value="">전체 분류</option><?php foreach ($category_codes as $code => $name): ?><option value="<?= $this->e($code) ?>"<?= $filters['ca'] === $code ? ' selected' : '' ?>><?= $this->e(str_repeat('· ', intdiv(strlen($code), 2) - 1) . $name) ?></option><?php endforeach ?></select>
  <button class="btn btn-sm" type="submit">검색</button>
</form>
```

`modules/youngcart/templates/admin/products.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>상품 목록 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '상품 목록', 'description' => '총 ' . $list['total'] . '개', 'actions' => [['url' => $admin_url . '/products/new', 'label' => '상품 등록']]]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php $this->insert('admin/_filters', ['action' => $admin_url . '/products']) ?>
<?php $sortLink = fn (string $key): string => $admin_url . '/products?' . http_build_query(['q' => $filters['q'], 'field' => $filters['field'], 'ca' => $filters['ca'], 'sort' => $key, 'dir' => $filters['sort'] === $key && $filters['dir'] === 'asc' ? 'desc' : 'asc']); ?>
<form method="post" action="<?= $this->e($admin_url) ?>/products" id="yc-product-list">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="action" value="bulk">
  <div class="overflow-x-auto"><table class="table table-sm"><thead><tr>
    <th><input class="checkbox checkbox-xs" type="checkbox" data-yc-check-all aria-label="전체 선택"></th>
    <?php foreach (['code' => '코드', 'name' => '상품명', 'price' => '판매가', 'list_price' => '시중가', 'stock' => '재고', 'active' => '판매', 'sold_out' => '품절', 'sort_order' => '순서', 'hit' => '조회'] as $key => $label): ?><th><a href="<?= $this->e($sortLink($key)) ?>"><?= $label ?><?= $filters['sort'] === $key ? ($filters['dir'] === 'asc' ? ' ▲' : ' ▼') : '' ?></a></th><?php endforeach ?>
    <th>분류</th><th></th></tr></thead><tbody>
    <?php foreach ($list['items'] as $row): $n = 'rows[' . (int) $row['id'] . ']'; ?>
      <tr>
        <td><input class="checkbox checkbox-xs" type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" form="yc-product-delete" aria-label="선택"></td>
        <td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['id'] ?>"><code><?= $this->e($row['code']) ?></code></a></td>
        <td><input class="input input-bordered input-xs" type="text" name="<?= $n ?>[name]" value="<?= $this->e($row['name']) ?>" maxlength="250" required></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[price]" value="<?= (int) $row['price'] ?>" min="0" required></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[list_price]" value="<?= (int) $row['list_price'] ?>" min="0"></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[stock]" value="<?= (int) $row['stock'] ?>" min="0"></td>
        <td><input type="hidden" name="<?= $n ?>[active]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[active]" value="1"<?= (int) $row['active'] === 1 ? ' checked' : '' ?>></td>
        <td><input type="hidden" name="<?= $n ?>[sold_out]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[sold_out]" value="1"<?= (int) $row['sold_out'] === 1 ? ' checked' : '' ?>></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[sort_order]" value="<?= (int) $row['sort_order'] ?>"></td>
        <td><?= (int) $row['hit'] ?></td>
        <td><select class="select select-bordered select-xs" name="<?= $n ?>[category_id]"><?php foreach ($categories as $id => $label): ?><option value="<?= $id ?>"<?= (int) $row['category_id'] === $id ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach ?></select></td>
        <td class="row-actions"><a class="btn btn-xs" href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['id'] ?>">수정</a><a class="btn btn-xs btn-outline" href="<?= $this->e($public_url) ?>/item?id=<?= $this->e(rawurlencode($row['code'])) ?>" target="_blank" rel="noopener">보기</a></td>
      </tr>
    <?php endforeach ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">선택 내용 일괄 저장</button></div>
</form>
<form method="post" action="<?= $this->e($admin_url) ?>/products" id="yc-product-delete" onsubmit="return confirm('선택한 상품을 삭제할까요? 이미지·옵션도 함께 지워집니다.')">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="action" value="delete">
  <button class="btn btn-sm btn-error btn-outline" type="submit">선택 삭제</button>
</form>
<form method="post" action="<?= $this->e($admin_url) ?>/products/copy" class="yc-copy-form">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <label>복사할 상품 <select class="select select-bordered select-sm" name="id"><?php foreach ($list['items'] as $row): ?><option value="<?= (int) $row['id'] ?>"><?= $this->e($row['code'] . ' ' . $row['name']) ?></option><?php endforeach ?></select></label>
  <label>새 코드 <input class="input input-bordered input-sm" type="text" name="code" value="<?= time() ?>" maxlength="20" pattern="[A-Za-z0-9_-]{1,20}" required></label>
  <button class="btn btn-sm" type="submit">상품 복사</button>
</form>
<?php $this->insert('_pager', ['page_url' => fn (int $p): string => $admin_url . '/products?' . http_build_query($filters + ['page' => $p])]) ?>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><script src="<?= $this->asset('youngcart-admin.js') ?>" defer></script><?php $this->stop() ?>
```

`modules/youngcart/templates/admin/product_types.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>상품 유형 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '상품 유형', 'description' => '히트·추천·최신·인기·할인 표시를 한 번에 바꿉니다.', 'actions' => []]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php $this->insert('admin/_filters', ['action' => $admin_url . '/products/types']) ?>
<form method="post" action="<?= $this->e($admin_url) ?>/products/types">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th>코드</th><th>상품명</th><?php foreach (['is_hit' => '히트', 'is_recommended' => '추천', 'is_new' => '최신', 'is_popular' => '인기', 'is_discount' => '할인'] as $type => $label): ?><th><?= $label ?></th><?php endforeach ?></tr></thead><tbody>
    <?php foreach ($list['items'] as $row): $n = 'rows[' . (int) $row['id'] . ']'; ?>
      <tr><td><code><?= $this->e($row['code']) ?></code></td><td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['id'] ?>"><?= $this->e($row['name']) ?></a></td>
        <?php foreach ($types as $type): ?><td><input type="hidden" name="<?= $n ?>[<?= $type ?>]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[<?= $type ?>]" value="1"<?= (int) $row[$type] === 1 ? ' checked' : '' ?>></td><?php endforeach ?></tr>
    <?php endforeach ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">저장</button></div>
</form>
<?php $this->insert('_pager', ['page_url' => fn (int $p): string => $admin_url . '/products/types?' . http_build_query($filters + ['page' => $p])]) ?>
<?php $this->stop() ?>
```

`modules/youngcart/templates/admin/product_stock.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>상품 재고 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '상품 재고', 'description' => '선택옵션이 없는 상품의 재고입니다. 옵션 재고는 별도 화면에서 관리합니다.', 'actions' => []]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<form class="yc-filter" method="get" action="<?= $this->e($admin_url) ?>/products/stock"><input class="input input-bordered input-sm" type="search" name="q" value="<?= $this->e($q) ?>" placeholder="상품명·코드" aria-label="검색어"><button class="btn btn-sm" type="submit">검색</button></form>
<form method="post" action="<?= $this->e($admin_url) ?>/products/stock">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th>코드</th><th>상품명</th><th>재고</th><th>통보 기준</th><th>판매</th><th>품절</th></tr></thead><tbody>
    <?php foreach ($list['items'] as $row): $n = 'rows[' . (int) $row['id'] . ']'; ?>
      <tr><td><code><?= $this->e($row['code']) ?></code></td><td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['id'] ?>"><?= $this->e($row['name']) ?></a></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[stock]" value="<?= (int) $row['stock'] ?>" min="0" required></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[stock_alert]" value="<?= (int) $row['stock_alert'] ?>" min="0"></td>
        <?php foreach (['active', 'sold_out'] as $flag): ?><td><input type="hidden" name="<?= $n ?>[<?= $flag ?>]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[<?= $flag ?>]" value="1"<?= (int) $row[$flag] === 1 ? ' checked' : '' ?>></td><?php endforeach ?></tr>
    <?php endforeach ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">저장</button></div>
</form>
<?php $this->insert('_pager', ['page_url' => fn (int $p): string => $admin_url . '/products/stock?' . http_build_query(['q' => $q, 'page' => $p])]) ?>
<?php $this->stop() ?>
```

`modules/youngcart/templates/admin/option_stock.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>옵션 재고 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '옵션 재고', 'description' => '선택옵션과 추가옵션의 재고입니다.', 'actions' => []]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<form class="yc-filter" method="get" action="<?= $this->e($admin_url) ?>/products/option-stock"><input class="input input-bordered input-sm" type="search" name="q" value="<?= $this->e($q) ?>" placeholder="상품명·코드" aria-label="검색어"><button class="btn btn-sm" type="submit">검색</button></form>
<form method="post" action="<?= $this->e($admin_url) ?>/products/option-stock">
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <div class="overflow-x-auto"><table class="table table-sm"><thead><tr><th>상품</th><th>종류</th><th>옵션</th><th>가격</th><th>재고</th><th>통보 기준</th><th>사용</th></tr></thead><tbody>
    <?php foreach ($list['items'] as $row): $n = 'rows[' . (int) $row['id'] . ']'; ?>
      <tr><td><a href="<?= $this->e($admin_url) ?>/products/edit?id=<?= (int) $row['product_id'] ?>"><?= $this->e($row['product_name']) ?></a> <code><?= $this->e($row['product_code']) ?></code></td>
        <td><?= $row['kind'] === 'select' ? '선택' : '추가' ?></td><td><?= $this->e(implode(' / ', array_filter([$row['value1'], $row['value2'], $row['value3']]))) ?></td><td><?= number_format((int) $row['price']) ?></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[stock]" value="<?= (int) $row['stock'] ?>" min="0" required></td>
        <td><input class="input input-bordered input-xs yc-table-input" type="number" name="<?= $n ?>[stock_alert]" value="<?= (int) $row['stock_alert'] ?>" min="0"></td>
        <td><input type="hidden" name="<?= $n ?>[active]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="<?= $n ?>[active]" value="1"<?= (int) $row['active'] === 1 ? ' checked' : '' ?>></td></tr>
    <?php endforeach ?>
  </tbody></table></div>
  <div class="form-actions"><button class="btn btn-sm btn-primary" type="submit">저장</button></div>
</form>
<?php $this->insert('_pager', ['page_url' => fn (int $p): string => $admin_url . '/products/option-stock?' . http_build_query(['q' => $q, 'page' => $p])]) ?>
<?php $this->stop() ?>
```

`www/themes/default/youngcart-admin.js`의 첫 부분(전체 선택; Task 15에서 나머지를 덧붙인다):

```js
(function(){
  'use strict';
  var checkAll=document.querySelector('[data-yc-check-all]');
  if(checkAll){checkAll.addEventListener('change',function(){[].slice.call(document.querySelectorAll('input[name="ids[]"]')).forEach(function(box){box.checked=checkAll.checked;});});}
})();
```

- [ ] **Step 6: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php`
Expected: PASS(`products/new`·`edit`는 아직 없지만 이 테스트는 호출하지 않는다)

- [ ] **Step 7: 커밋**

```bash
git add modules/youngcart www/themes/default/youngcart-admin.js tests/Web/YoungCartAdminTest.php
git commit -m "feat: add youngcart product list, copy, type and stock admin screens"
```

---

### Task 15: 상품 등록·수정 폼

**Files:**
- Create: `modules/youngcart/src/Admin/ProductFormController.php`
- Create: `modules/youngcart/templates/admin/product_form.php`
- Modify: `modules/youngcart/bootstrap.php`, `www/themes/default/youngcart-admin.js`
- Test: `tests/Web/YoungCartAdminTest.php`

**Interfaces:**
- `ProductFormController::handle(string $page, …)` — `products/new`, `products/edit`(GET `?id=`); POST `action=save|combine`
- 폼 값 배열 `$values`는 `Products::save()`의 입력 키와 같은 이름을 쓴다. 옵션 조합 표는 `$options_rows`(`value1,value2,value3,price,stock,stock_alert,active`), 추가옵션 표는 `$extras_rows`, 그룹 이름은 `$option_groups[1..3]`, 이미지 목록은 `$images`, 관련상품은 `$relations`(`[id, code, name]`), 상품정보고시 전체 군은 `$info_groups`(JSON으로 페이지에 삽입).
- 저장 성공 시 쿠키 `yc_last_category`(31일)를 놓고, 새 상품 폼이 이를 기본값으로 쓴다.

- [ ] **Step 1: 실패하는 웹 테스트 추가**

`tests/Web/YoungCartAdminTest.php` 클래스 끝에 추가:

```php
    private function productForm(int $category, array $overrides = []): array
    {
        return $overrides + ['action' => 'save', 'code' => 'F1', 'name' => '폼 상품', 'category_id' => (string) $category, 'price' => '12000', 'list_price' => '0', 'point_type' => '0', 'point' => '0', 'supply_point' => '0',
            'stock' => '4', 'stock_alert' => '0', 'buy_min' => '0', 'buy_max' => '0', 'active' => '1', 'shipping_type' => '0', 'shipping_method' => '0', 'shipping_fee' => '0', 'shipping_free_minimum' => '0', 'shipping_per_qty' => '0',
            'summary' => '요약', 'description' => '<p>본문</p>', 'info_group' => '', 'memo' => '', 'sort_order' => '0',
            'option_group' => [1 => '색상', 2 => '', 3 => ''], 'option_values' => [1 => '', 2 => '', 3 => ''],
            'options' => [['value1' => '빨강', 'value2' => '', 'value3' => '', 'price' => '0', 'stock' => '2', 'stock_alert' => '1', 'active' => '1']], 'extras' => [], 'relations' => ''];
    }

    #[DataProvider('connectionProvider')]
    public function testProductFormCombineSaveEditImagesAndConflicts(array $config): void
    {
        $this->setupModule($config);
        $seed = $this->seedProducts();
        $this->signIn(true);
        $form = $this->body($this->get($this->app, '/admin/shop/products/new'));
        self::assertMatchesRegularExpression('/name="code" value="[0-9]{10}"/', $form);
        self::assertStringContainsString('의류', $form); self::assertStringContainsString('data-yc-info-groups', $form); self::assertStringContainsString('data-cms-editor', $form);
        $combine = $this->post($this->app, '/admin/shop/products/new', $this->csrf($this->productForm((int) $seed['top']['id'], ['action' => 'combine', 'option_values' => [1 => '빨강,파랑', 2 => 'S,M', 3 => ''], 'option_group' => [1 => '색상', 2 => '크기', 3 => ''], 'options' => []])));
        self::assertSame(200, $combine->getStatusCode());
        $body = $this->body($combine);
        self::assertStringContainsString('name="options[3][value2]" value="M"', $body);
        self::assertStringContainsString('value="폼 상품"', $body);
        self::assertSame(0, $this->shop->products->stats()['products'] - 2);
        $response = $this->postWithFiles($this->app, '/admin/shop/products/new', $this->csrf($this->productForm((int) $seed['top']['id'])), ['images' => [ImagesTest::png(120, 120)]]);
        self::assertSame(303, $response->getStatusCode(), $this->body($response));
        $product = $this->shop->products->byCode('F1');
        self::assertSame('/admin/shop/products/edit?id=' . $product['id'] . '&saved=1', $response->getHeaderLine('Location'));
        self::assertStringContainsString('yc_last_category=', implode(';', $response->getHeader('Set-Cookie')));
        self::assertCount(1, $product['images']); self::assertSame(['색상'], $product['options']['select_groups']);
        $response = $this->post($this->app, '/admin/shop/products/new', $this->csrf($this->productForm((int) $seed['top']['id'], ['code' => 'F1'])));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('이미 사용 중인 상품 코드', $this->body($response));
        self::assertStringContainsString('name="options[0][value1]" value="빨강"', $this->body($response));
        $edit = $this->body($this->get($this->app, '/admin/shop/products/edit', ['id' => (string) $product['id']]));
        self::assertStringContainsString('value="F1"', $edit); self::assertStringContainsString('name="version" value="0"', $edit);
        self::assertStringContainsString('image_delete[]', $edit); self::assertStringContainsString($product['images'][0]['filename'], $edit);
        $response = $this->post($this->app, '/admin/shop/products/edit', $this->csrf($this->productForm((int) $seed['top']['id'], ['id' => (string) $product['id'], 'version' => '0', 'name' => '수정됨', 'image_delete' => [(string) $product['images'][0]['id']]])));
        self::assertSame(303, $response->getStatusCode(), $this->body($response));
        $product = $this->shop->products->get((int) $product['id']);
        self::assertSame('수정됨', $product['name']); self::assertSame([], $product['images']);
        $response = $this->post($this->app, '/admin/shop/products/edit', $this->csrf($this->productForm((int) $seed['top']['id'], ['id' => (string) $product['id'], 'version' => '0', 'name' => '충돌'])));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('다른 관리자가 먼저 저장', $this->body($response));
        self::assertSame('수정됨', $this->shop->products->find((int) $product['id'])['name']);
        self::assertSame(404, $this->get($this->app, '/admin/shop/products/edit', ['id' => '999'])->getStatusCode());
        self::assertSame(403, $this->post($this->app, '/admin/shop/products/edit', $this->productForm((int) $seed['top']['id'], ['id' => (string) $product['id'], 'version' => '1']))->getStatusCode());
    }
```

- [ ] **Step 2: 테스트 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php --filter testProductFormCombineSaveEditImagesAndConflicts`
Expected: FAIL — 404.

- [ ] **Step 3: ProductFormController 작성**

`modules/youngcart/src/Admin/ProductFormController.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Modules\YoungCart\Admin;

use GnuCms\Error\DomainError;
use GnuCms\Modules\YoungCart\Catalog\Options;
use GnuCms\Modules\YoungCart\Catalog\Products;
use GnuCms\Modules\YoungCart\Input;
use GnuCms\Modules\YoungCart\ProductInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class ProductFormController extends AdminBase
{
    private const COOKIES = ['category_id' => 'yc_last_category'];

    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->context($request, $page);
        if ($redirect = $this->requireReady($response, $data)) return $redirect;
        $input = $data['input'];
        $id = $page === 'products/edit' ? Input::id($input['id'] ?? '') : null;
        $product = $id === null ? null : $this->service->products->get($id);
        if ($request->getMethod() !== 'POST') {
            if (($input['saved'] ?? '') === '1') $data['notice'] = '상품을 저장했습니다.';
            return $this->form($request, $response, $data, $product === null ? $this->defaults($request) : $this->values($product), $product);
        }
        $action = $input['action'] ?? '';
        try {
            if ($action === 'combine') {
                $draft = Options::draft($input, array_merge(Options::rows($input['options'] ?? []), $product['options']['select'] ?? []));
                $values = $input;
                for ($i = 1; $i <= Options::MAX_GROUPS; $i++) $values['option_group'][$i] = $draft['groups'][$i - 1] ?? '';
                $values['options'] = $draft['rows'];
                $data['notice'] = count($draft['rows']) . '개 조합을 만들었습니다. 가격·재고를 확인한 뒤 상품 저장을 눌러 주세요.';
                return $this->form($request, $response, $data, $values, $product);
            }
            if ($action !== 'save') throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
            $files = $request->getUploadedFiles()['images'] ?? [];
            $files = is_array($files) ? array_values($files) : [$files];
            $saved = $this->service->products->save($input, array_filter($files, static fn ($f) => $f instanceof UploadedFileInterface), $id, $data['actor']);
            $response = $this->redirect($response, $data['admin_url'] . '/products/edit?id=' . $saved . '&saved=1');
            foreach (self::COOKIES as $field => $cookie) {
                $value = is_string($input[$field] ?? null) ? mb_substr($input[$field], 0, 100, 'UTF-8') : '';
                if ($value !== '') $response = $response->withAddedHeader('Set-Cookie', $cookie . '=' . rawurlencode($value) . '; Max-Age=2678400; Path=' . ($data['base'] === '' ? '/' : $data['base']) . '; SameSite=Lax; HttpOnly');
            }
            return $response;
        } catch (DomainError $e) {
            if ($e->status() === 404) throw $e;
            $data['errors'] = $e->details() ?: [$e->getMessage()];
            return $this->form($request, $response->withStatus($e->status()), $data, $input, $product);
        }
    }

    private function defaults(ServerRequestInterface $request): array
    {
        $cookies = $request->getCookieParams();
        $values = ['code' => (string) time(), 'name' => '', 'category_id' => '', 'category2_id' => '', 'category3_id' => '',
            'summary' => '', 'description' => '', 'list_price' => '0', 'price' => '', 'point_type' => '0', 'point' => '0', 'supply_point' => '0', 'tax_free' => '0', 'seller_email' => '',
            'active' => '1', 'no_coupon' => '0', 'sold_out' => '0', 'stock' => '0', 'stock_alert' => '0', 'buy_min' => '0', 'buy_max' => '0', 'phone_inquiry' => '0',
            'shipping_type' => '0', 'shipping_method' => '0', 'shipping_fee' => '0', 'shipping_free_minimum' => '0', 'shipping_per_qty' => '0', 'head_html' => '', 'tail_html' => '',
            'info_group' => '', 'info' => [], 'memo' => '', 'sort_order' => '0', 'option_group' => [1 => '', 2 => '', 3 => ''], 'option_values' => [1 => '', 2 => '', 3 => ''],
            'options' => [], 'extras' => [], 'relations' => '', 'extra_label' => [], 'extra_value' => [], 'version' => '0'];
        foreach (Products::TYPES as $type) $values[$type] = '0';
        foreach (self::COOKIES as $field => $cookie) if (is_string($cookies[$cookie] ?? null)) $values[$field] = mb_substr($cookies[$cookie], 0, 100, 'UTF-8');
        return $values;
    }

    /** 저장된 상품을 폼 입력 이름으로 편다. */
    private function values(array $product): array
    {
        $values = [];
        foreach ($product as $key => $value) if (is_scalar($value) || $value === null) $values[$key] = (string) $value;
        $values['category_id'] = (string) ($product['categories'][1]['id'] ?? '');
        $values['category2_id'] = (string) ($product['categories'][2]['id'] ?? '');
        $values['category3_id'] = (string) ($product['categories'][3]['id'] ?? '');
        $values['info'] = $product['info'];
        $values['option_group'] = [1 => $product['options']['select_groups'][0] ?? '', 2 => $product['options']['select_groups'][1] ?? '', 3 => $product['options']['select_groups'][2] ?? ''];
        $values['option_values'] = [1 => '', 2 => '', 3 => ''];
        $values['options'] = $product['options']['select'];
        $values['extras'] = $product['options']['extra'];
        $values['relations'] = implode(',', array_column($product['relations'], 'id'));
        foreach ($product['extra'] as $index => $field) { $values['extra_label'][$index + 1] = $field['label']; $values['extra_value'][$index + 1] = $field['value']; }
        return $values;
    }

    private function form(ServerRequestInterface $request, ResponseInterface $response, array $data, array $values, ?array $product): ResponseInterface
    {
        $data['id'] = $product === null ? null : (int) $product['id'];
        $data['product'] = $product;
        $data['values'] = $values;
        $data['options_rows'] = Options::rows($values['options'] ?? []);
        $data['extras_rows'] = Options::rows($values['extras'] ?? []);
        $data['images'] = $product['images'] ?? [];
        $data['categories'] = $this->service->categories->options();
        $data['info_groups'] = ProductInfo::GROUPS;
        $data['types'] = ['is_hit' => '히트', 'is_recommended' => '추천', 'is_new' => '최신', 'is_popular' => '인기', 'is_discount' => '할인'];
        $data['apply_fields'] = ['types' => '유형', 'active' => '판매가능', 'no_coupon' => '쿠폰제외', 'point' => '포인트', 'tax_free' => '과세', 'shipping' => '배송비', 'buy' => '구매수량', 'html' => '상세 위·아래 HTML', 'seller_email' => '판매자 메일', 'phone_inquiry' => '전화문의'];
        $relationIds = array_filter(array_map('intval', explode(',', (string) ($values['relations'] ?? ''))));
        $data['relations'] = [];
        foreach ($relationIds as $relatedId) { $row = $this->service->products->find($relatedId); if ($row !== null) $data['relations'][] = ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $row['name']]; }
        if (!preg_match('/^[a-f0-9]{32}$/D', (string) ($values['image_key'] ?? ''))) $data['values']['image_key'] = bin2hex(random_bytes(16));
        return $this->render($request, $response, 'product_form', $data);
    }
}
```

- [ ] **Step 4: bootstrap에 라우트 추가**

`modules/youngcart/bootstrap.php`의 `$product` 라우트 뒤에 추가하고 `use GnuCms\Modules\YoungCart\Admin\ProductFormController;`를 상단에 넣는다:

```php
    $productForm = new ProductFormController($service, $context->routePrefix, $context->adminRoutePrefix);
    foreach (['products/new', 'products/edit'] as $page) {
        foreach (['GET', 'POST'] as $method) {
            $context->route($method, '/' . $page, static fn ($request, $response) => $productForm->handle($page, $request, $response), admin: true);
        }
    }
```

- [ ] **Step 5: 폼 템플릿 작성**

`modules/youngcart/templates/admin/product_form.php`:

```php
<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?><?= $id === null ? '상품 등록' : '상품 수정' ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => $id === null ? '상품 등록' : '상품 수정', 'description' => $id === null ? '' : ($values['code'] ?? ''), 'actions' => array_merge([['url' => $admin_url . '/products', 'label' => '상품 목록']], $id === null ? [] : [['url' => $public_url . '/item?id=' . rawurlencode((string) $values['code']), 'label' => '상품 보기']])]) ?>
<?php $this->insert('admin/_nav') ?>
<?php $this->insert('admin/_errors') ?>
<?php
$v = static fn (string $key): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : '';
$field = function (string $name, string $label, string $type = 'text', array $attrs = []) use ($v, $errors): void {
    $extra = ''; foreach ($attrs as $k => $val) $extra .= ' ' . $k . '="' . $this->e((string) $val) . '"'; ?>
  <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><?= $this->e($label) ?></legend>
    <input class="input input-bordered input-sm input-block" type="<?= $type ?>" name="<?= $name ?>" value="<?= $this->e($v($name)) ?>"<?= $extra ?>>
    <?php if (isset($errors[$name])): ?><p class="validator-hint"><?= $this->e($errors[$name]) ?></p><?php endif ?></fieldset>
<?php };
$check = function (string $name, string $label) use ($v): void { ?>
  <label class="label cursor-pointer"><input type="hidden" name="<?= $name ?>" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="<?= $name ?>" value="1"<?= $v($name) === '1' ? ' checked' : '' ?>> <?= $this->e($label) ?></label>
<?php };
$apply = function (string $group) use ($apply_fields): void { ?>
  <label class="label cursor-pointer yc-apply"><input class="checkbox checkbox-xs" type="checkbox" name="apply_fields[]" value="<?= $group ?>"> <?= $this->e($apply_fields[$group]) ?> 일괄 적용</label>
<?php };
$catSelect = function (string $name, bool $required) use ($v, $categories, $errors): void { ?>
  <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><?= $name === 'category_id' ? '대표 분류' : '추가 분류' ?></legend>
    <select class="select select-bordered select-sm" name="<?= $name ?>"<?= $required ? ' required' : '' ?>><option value="">선택</option><?php foreach ($categories as $cid => $label): ?><option value="<?= $cid ?>"<?= $v($name) === (string) $cid ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach ?></select>
    <?php if (isset($errors[$name])): ?><p class="validator-hint"><?= $this->e($errors[$name]) ?></p><?php endif ?></fieldset>
<?php };
?>
<form method="post" action="<?= $this->e($admin_url) ?>/products/<?= $id === null ? 'new' : 'edit' ?>" enctype="multipart/form-data" data-yc-product-form>
  <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
  <input type="hidden" name="action" value="save" data-yc-action>
  <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="version" value="<?= $this->e($v('version')) ?>"><?php endif ?>
  <input type="hidden" name="image_key" value="<?= $this->e($v('image_key')) ?>">
  <section class="card" id="section-category"><div class="card-body"><h2 class="card-title">분류</h2>
    <div class="yc-fields"><?php $catSelect('category_id', true); $catSelect('category2_id', false); $catSelect('category3_id', false); ?></div>
  </div></section>
  <section class="card" id="section-basic"><div class="card-body"><h2 class="card-title">기본정보</h2>
    <?php if ($id === null): ?><?php $field('code', '상품 코드 (영문·숫자·-·_ 1~20자)', 'text', ['maxlength' => 20, 'pattern' => '[A-Za-z0-9_-]{1,20}', 'required' => 'required']) ?>
    <?php else: ?><fieldset class="fieldset"><legend class="fieldset-legend">상품 코드</legend><input class="input input-bordered input-sm" type="text" value="<?= $this->e($v('code')) ?>" readonly></fieldset><?php endif ?>
    <?php $field('name', '상품명', 'text', ['maxlength' => 250, 'required' => 'required']) ?>
    <div class="yc-fields"><?php $field('sort_order', '순서', 'number'); $field('seller_email', '판매자 메일', 'email', ['maxlength' => 191]); ?></div>
    <div class="yc-checks"><?php foreach ($types as $type => $label) $check($type, $label); ?><?php $apply('types') ?></div>
    <div class="yc-checks"><?php $check('active', '판매가능'); $apply('active'); $check('no_coupon', '쿠폰 대상 제외'); $apply('no_coupon'); $check('phone_inquiry', '전화문의(가격 숨김)'); $apply('phone_inquiry'); ?></div>
    <fieldset class="fieldset"><legend class="fieldset-legend">요약 설명</legend><textarea class="textarea textarea-bordered textarea-block" name="summary" rows="3" maxlength="20000"><?= $this->e($v('summary')) ?></textarea></fieldset>
    <fieldset class="fieldset"><legend class="fieldset-legend">상세 설명</legend><textarea class="textarea textarea-bordered textarea-block" id="yc-description" name="description" rows="10" data-cms-editor><?= $this->e($v('description')) ?></textarea><input type="hidden" name="uploaded_images" value="" data-uploaded-images></fieldset>
    <fieldset class="fieldset"><legend class="fieldset-legend">관리자 메모 (공개되지 않음)</legend><textarea class="textarea textarea-bordered textarea-block" name="memo" rows="2" maxlength="5000"><?= $this->e($v('memo')) ?></textarea></fieldset>
  </div></section>
  <section class="card" id="section-info"><div class="card-body"><h2 class="card-title">상품정보고시</h2>
    <select class="select select-bordered select-sm" name="info_group" data-yc-info-select><option value="">사용 안 함</option><?php foreach ($info_groups as $key => $group): ?><option value="<?= $key ?>"<?= $v('info_group') === $key ? ' selected' : '' ?>><?= $this->e($group['label']) ?></option><?php endforeach ?></select>
    <noscript><p class="muted">군을 바꾼 뒤 조합 생성 버튼을 누르면 항목 입력칸이 갱신됩니다.</p></noscript>
    <div class="yc-fields" data-yc-info-fields data-yc-info-groups="<?= $this->e(json_encode(array_map(static fn ($g) => $g['articles'], $info_groups), JSON_UNESCAPED_UNICODE)) ?>">
      <?php foreach ($info_groups[$v('info_group')]['articles'] ?? [] as $index => $article): ?><fieldset class="fieldset"><legend class="fieldset-legend"><?= $this->e($article) ?></legend><input class="input input-bordered input-sm" type="text" name="info[<?= $index ?>]" value="<?= $this->e((string) ($values['info'][$index] ?? '')) ?>" maxlength="500" placeholder="상품페이지 참고"></fieldset><?php endforeach ?>
    </div>
  </div></section>
  <section class="card" id="section-price"><div class="card-body"><h2 class="card-title">가격·포인트·재고</h2>
    <div class="yc-fields"><?php $field('price', '판매가격', 'number', ['min' => 0, 'required' => 'required']); $field('list_price', '시중가격 (0이면 표시 안 함)', 'number', ['min' => 0]); ?>
      <fieldset class="fieldset"><legend class="fieldset-legend">포인트 방식</legend><select class="select select-bordered select-sm" name="point_type" data-yc-point-type><?php foreach ([0 => '설정 금액', 1 => '판매가 기준 %', 2 => '구매가 기준 %'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('point_type') === (string) $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></fieldset>
      <?php $field('point', '포인트 (정액 또는 0~99%)', 'number', ['min' => 0]); $field('supply_point', '추가옵션 포인트', 'number', ['min' => 0]); ?></div>
    <div class="yc-checks"><?php $check('tax_free', '비과세'); $apply('tax_free'); $apply('point'); ?></div>
    <div class="yc-fields"><?php $field('stock', '재고 (선택옵션 없을 때)', 'number', ['min' => 0]); $field('stock_alert', '재고 통보 기준', 'number', ['min' => 0]); $field('buy_min', '최소 구매수량 (0 = 제한 없음)', 'number', ['min' => 0, 'max' => 9999]); $field('buy_max', '최대 구매수량 (0 = 제한 없음)', 'number', ['min' => 0, 'max' => 9999]); ?></div>
    <div class="yc-checks"><?php $check('sold_out', '품절 표시'); $apply('buy'); ?></div>
  </div></section>
  <section class="card" id="section-options"><div class="card-body"><h2 class="card-title">선택옵션</h2>
    <p class="muted">그룹 이름과 쉼표로 구분한 값을 입력하고 <strong>조합 생성</strong>을 누르면 조합 표가 만들어집니다. 가격은 판매가에 더하는 차액입니다.</p>
    <div class="yc-fields"><?php for ($i = 1; $i <= 3; $i++): ?>
      <fieldset class="fieldset<?= isset($errors['option_group']) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend">옵션 <?= $i ?></legend>
        <input class="input input-bordered input-sm" type="text" name="option_group[<?= $i ?>]" value="<?= $this->e((string) ($values['option_group'][$i] ?? '')) ?>" maxlength="100" placeholder="그룹 이름 (예: 색상)">
        <input class="input input-bordered input-sm" type="text" name="option_values[<?= $i ?>]" value="<?= $this->e((string) ($values['option_values'][$i] ?? '')) ?>" placeholder="값 (예: 빨강,파랑)"></fieldset>
    <?php endfor ?></div>
    <button class="btn btn-sm" type="submit" name="action" value="combine" formnovalidate>조합 생성</button>
    <?php if (isset($errors['options']) || isset($errors['option_values'])): ?><p class="validator-hint"><?= $this->e($errors['options'] ?? $errors['option_values']) ?></p><?php endif ?>
    <?php if ($options_rows !== []): ?>
      <div class="overflow-x-auto"><table class="table table-sm yc-combo-table" data-yc-combos><thead><tr><th>조합</th><th>차액</th><th>재고</th><th>통보</th><th>사용</th></tr></thead><tbody>
        <?php foreach ($options_rows as $i => $row): ?><tr>
          <td><?= $this->e(implode(' / ', array_filter([$row['value1'], $row['value2'], $row['value3']], static fn ($x) => $x !== ''))) ?><?php for ($k = 1; $k <= 3; $k++): ?><input type="hidden" name="options[<?= $i ?>][value<?= $k ?>]" value="<?= $this->e($row['value' . $k]) ?>"><?php endfor ?></td>
          <td><input class="input input-bordered input-xs" type="number" name="options[<?= $i ?>][price]" value="<?= $this->e($row['price']) ?>"><button class="btn btn-xs" type="button" data-yc-copy-down="price" title="아래 모두 같은 값">↓</button></td>
          <td><input class="input input-bordered input-xs" type="number" name="options[<?= $i ?>][stock]" value="<?= $this->e($row['stock']) ?>" min="0"><button class="btn btn-xs" type="button" data-yc-copy-down="stock" title="아래 모두 같은 값">↓</button></td>
          <td><input class="input input-bordered input-xs" type="number" name="options[<?= $i ?>][stock_alert]" value="<?= $this->e($row['stock_alert']) ?>" min="0"></td>
          <td><input type="hidden" name="options[<?= $i ?>][active]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="options[<?= $i ?>][active]" value="1"<?= (string) $row['active'] === '1' ? ' checked' : '' ?>></td>
        </tr><?php endforeach ?>
      </tbody></table></div>
    <?php endif ?>
  </div></section>
  <section class="card" id="section-extras"><div class="card-body"><h2 class="card-title">추가옵션</h2>
    <p class="muted">그룹명·항목명·절대가·재고를 행으로 입력합니다. 비어 있는 행은 무시합니다.</p>
    <?php if (isset($errors['extras'])): ?><p class="validator-hint"><?= $this->e($errors['extras']) ?></p><?php endif ?>
    <div class="overflow-x-auto"><table class="table table-sm" data-yc-extras><thead><tr><th>그룹명</th><th>항목명</th><th>가격</th><th>재고</th><th>통보</th><th>사용</th></tr></thead><tbody>
      <?php $extraRows = array_merge($extras_rows, array_fill(0, 3, ['value1' => '', 'value2' => '', 'value3' => '', 'price' => '0', 'stock' => '9999', 'stock_alert' => '100', 'active' => '1'])); foreach ($extraRows as $i => $row): ?><tr>
        <td><input class="input input-bordered input-xs" type="text" name="extras[<?= $i ?>][value1]" value="<?= $this->e($row['value1']) ?>" maxlength="100"></td>
        <td><input class="input input-bordered input-xs" type="text" name="extras[<?= $i ?>][value2]" value="<?= $this->e($row['value2']) ?>" maxlength="100"></td>
        <td><input class="input input-bordered input-xs" type="number" name="extras[<?= $i ?>][price]" value="<?= $this->e($row['price']) ?>" min="0"></td>
        <td><input class="input input-bordered input-xs" type="number" name="extras[<?= $i ?>][stock]" value="<?= $this->e($row['stock']) ?>" min="0"></td>
        <td><input class="input input-bordered input-xs" type="number" name="extras[<?= $i ?>][stock_alert]" value="<?= $this->e($row['stock_alert']) ?>" min="0"></td>
        <td><input type="hidden" name="extras[<?= $i ?>][active]" value="0"><input class="checkbox checkbox-xs" type="checkbox" name="extras[<?= $i ?>][active]" value="1"<?= (string) $row['active'] === '1' ? ' checked' : '' ?>></td>
      </tr><?php endforeach ?>
    </tbody></table></div>
    <button class="btn btn-xs" type="button" data-yc-add-extra>행 추가</button>
  </div></section>
  <section class="card" id="section-shipping"><div class="card-body"><h2 class="card-title">배송비</h2>
    <div class="yc-fields">
      <fieldset class="fieldset"><legend class="fieldset-legend">배송비 유형</legend><select class="select select-bordered select-sm" name="shipping_type" data-yc-shipping-type><?php foreach ([0 => '상점 기본', 1 => '무료', 2 => '조건부 무료', 3 => '유료', 4 => '수량별 부과'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('shipping_type') === (string) $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></fieldset>
      <fieldset class="fieldset"><legend class="fieldset-legend">결제 방법</legend><select class="select select-bordered select-sm" name="shipping_method"><?php foreach ([0 => '선불', 1 => '착불', 2 => '구매자 선택'] as $k => $l): ?><option value="<?= $k ?>"<?= $v('shipping_method') === (string) $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></fieldset>
      <?php $field('shipping_fee', '배송비', 'number', ['min' => 0]); $field('shipping_free_minimum', '무료배송 기준 금액', 'number', ['min' => 0]); $field('shipping_per_qty', '배송비 부과 수량 단위', 'number', ['min' => 0]); ?>
    </div>
    <?php $apply('shipping') ?>
  </div></section>
  <section class="card" id="section-images"><div class="card-body"><h2 class="card-title">상품 이미지 (최대 <?= \GnuCms\Modules\YoungCart\Images::MAX ?>장)</h2>
    <?php if (isset($errors['images'])): ?><p class="validator-hint"><?= $this->e($errors['images']) ?></p><?php endif ?>
    <?php if ($images !== []): ?>
      <ol class="yc-image-list" data-yc-image-list>
        <?php foreach ($images as $image): ?><li data-yc-image="<?= (int) $image['id'] ?>">
          <img src="<?= $this->e($public_url . '/image?p=' . $id . '&f=' . rawurlencode($image['filename']) . '&s=thumb') ?>" alt="" width="70" height="70">
          <code><?= $this->e($image['filename']) ?></code>
          <button class="btn btn-xs" type="button" data-yc-move="up" aria-label="위로">↑</button><button class="btn btn-xs" type="button" data-yc-move="down" aria-label="아래로">↓</button>
          <label><input class="checkbox checkbox-xs" type="checkbox" name="image_delete[]" value="<?= (int) $image['id'] ?>"> 삭제</label>
        </li><?php endforeach ?>
      </ol>
      <input type="hidden" name="image_order" value="<?= $this->e(implode(',', array_column($images, 'id'))) ?>" data-yc-image-order>
    <?php endif ?>
    <input class="file-input file-input-bordered file-input-sm" type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
  </div></section>
  <section class="card" id="section-relations"><div class="card-body"><h2 class="card-title">관련상품</h2>
    <?php if (isset($errors['relations'])): ?><p class="validator-hint"><?= $this->e($errors['relations']) ?></p><?php endif ?>
    <div class="yc-relation-search"><input class="input input-bordered input-sm" type="search" placeholder="상품명·코드 검색" data-yc-relation-search data-yc-search-url="<?= $this->e($admin_url . '/products/search') ?>" data-yc-exclude="<?= (int) $id ?>"><ul class="menu" data-yc-relation-results></ul></div>
    <ul class="yc-relations" data-yc-relations><?php foreach ($relations as $r): ?><li data-yc-relation="<?= (int) $r['id'] ?>"><code><?= $this->e($r['code']) ?></code> <?= $this->e($r['name']) ?> <button class="btn btn-xs" type="button" data-yc-remove-relation>제거</button></li><?php endforeach ?></ul>
    <input class="input input-bordered input-sm input-block" type="text" name="relations" value="<?= $this->e($v('relations')) ?>" data-yc-relation-ids aria-label="관련상품 ID(쉼표)">
  </div></section>
  <section class="card" id="section-html"><div class="card-body"><h2 class="card-title">상세 위·아래 HTML</h2>
    <fieldset class="fieldset"><legend class="fieldset-legend">상세 위</legend><textarea class="textarea textarea-bordered textarea-block" name="head_html" rows="3"><?= $this->e($v('head_html')) ?></textarea></fieldset>
    <fieldset class="fieldset"><legend class="fieldset-legend">상세 아래</legend><textarea class="textarea textarea-bordered textarea-block" name="tail_html" rows="3"><?= $this->e($v('tail_html')) ?></textarea></fieldset>
    <?php $apply('html'); $apply('seller_email'); ?>
  </div></section>
  <section class="card" id="section-extra"><div class="card-body"><h2 class="card-title">여분필드</h2>
    <div class="yc-fields"><?php for ($i = 1; $i <= 10; $i++): ?><fieldset class="fieldset"><legend class="fieldset-legend">여분 <?= $i ?></legend>
      <input class="input input-bordered input-sm" type="text" name="extra_label[<?= $i ?>]" value="<?= $this->e((string) ($values['extra_label'][$i] ?? '')) ?>" maxlength="100" placeholder="라벨">
      <input class="input input-bordered input-sm" type="text" name="extra_value[<?= $i ?>]" value="<?= $this->e((string) ($values['extra_value'][$i] ?? '')) ?>" maxlength="1000" placeholder="값"></fieldset><?php endfor ?></div>
  </div></section>
  <section class="card"><div class="card-body"><h2 class="card-title">일괄 적용 범위</h2>
    <p class="muted">위에서 체크한 "일괄 적용" 항목을 저장 값으로 함께 반영합니다.</p>
    <label class="label cursor-pointer"><input class="radio radio-sm" type="radio" name="apply_scope" value="" checked> 이 상품만</label>
    <label class="label cursor-pointer"><input class="radio radio-sm" type="radio" name="apply_scope" value="category"> 같은 대표 분류의 모든 상품</label>
    <label class="label cursor-pointer"><input class="radio radio-sm" type="radio" name="apply_scope" value="all"> 전체 상품</label>
  </div></section>
  <div class="form-actions"><button class="btn btn-primary" type="submit" name="action" value="save">상품 저장</button></div>
</form>
<?php $this->insert('admin/_editor', ['values' => ['image_key' => $values['image_key']], 'editor_required' => false, 'editor_height' => 300]) ?>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><script src="<?= $this->asset('youngcart-admin.js') ?>" defer></script><?php $this->stop() ?>
```

`$this->insert('admin/_editor', …)`는 코어 에디터 조각이며 `[data-cms-editor]` 텍스트영역과 `[data-uploaded-images]` 입력을 찾아 CKEditor를 붙인다. 이 조각이 `scripts` 블록을 요구하지 않는지 실행하며 확인하고, 필요하면 `admin/_editor` 삽입을 `scripts` 블록 안으로 옮긴다.

- [ ] **Step 6: 관리자 JS 완성**

`www/themes/default/youngcart-admin.js`를 아래 전체로 교체한다:

```js
(function(){
  'use strict';
  var checkAll=document.querySelector('[data-yc-check-all]');
  if(checkAll){checkAll.addEventListener('change',function(){[].slice.call(document.querySelectorAll('input[name="ids[]"]')).forEach(function(box){box.checked=checkAll.checked;});});}
  var form=document.querySelector('[data-yc-product-form]');
  if(!form){return;}
  // 조합 표: 같은 열의 아래 행에 값 복사
  form.addEventListener('click',function(event){
    var button=event.target.closest('[data-yc-copy-down]');
    if(button){
      var field=button.getAttribute('data-yc-copy-down'),row=button.closest('tr'),value=row.querySelector('input[name$="['+field+']"]').value,next=row.nextElementSibling;
      while(next){var input=next.querySelector('input[name$="['+field+']"]');if(input){input.value=value;}next=next.nextElementSibling;}
    }
    var move=event.target.closest('[data-yc-move]');
    if(move){
      var li=move.closest('li'),list=li.parentNode;
      if(move.getAttribute('data-yc-move')==='up'&&li.previousElementSibling){list.insertBefore(li,li.previousElementSibling);}
      if(move.getAttribute('data-yc-move')==='down'&&li.nextElementSibling){list.insertBefore(li.nextElementSibling,li);}
      var order=[].slice.call(list.querySelectorAll('[data-yc-image]')).map(function(el){return el.getAttribute('data-yc-image');});
      var orderInput=form.querySelector('[data-yc-image-order]');if(orderInput){orderInput.value=order.join(',');}
    }
    var removeRelation=event.target.closest('[data-yc-remove-relation]');
    if(removeRelation){removeRelation.closest('li').remove();syncRelations();}
    var addExtra=event.target.closest('[data-yc-add-extra]');
    if(addExtra){
      var body=form.querySelector('[data-yc-extras] tbody'),rows=body.querySelectorAll('tr'),index=rows.length,template=rows[rows.length-1].cloneNode(true);
      [].slice.call(template.querySelectorAll('input')).forEach(function(input){input.name=input.name.replace(/extras\[\d+\]/,'extras['+index+']');if(input.type==='text'){input.value='';}});
      body.appendChild(template);
    }
  });
  // 상품정보고시 군 전환
  var infoSelect=form.querySelector('[data-yc-info-select]'),infoFields=form.querySelector('[data-yc-info-fields]');
  if(infoSelect&&infoFields){
    var groups={};try{groups=JSON.parse(infoFields.getAttribute('data-yc-info-groups'));}catch(e){}
    infoSelect.addEventListener('change',function(){
      var articles=groups[infoSelect.value]||[];infoFields.innerHTML='';
      articles.forEach(function(article,index){
        var fieldset=document.createElement('fieldset');fieldset.className='fieldset';
        var legend=document.createElement('legend');legend.className='fieldset-legend';legend.textContent=article;fieldset.appendChild(legend);
        var input=document.createElement('input');input.className='input input-bordered input-sm';input.type='text';input.name='info['+index+']';input.maxLength=500;input.placeholder='상품페이지 참고';fieldset.appendChild(input);
        infoFields.appendChild(fieldset);
      });
    });
  }
  // 배송비 유형에 따라 입력칸 표시
  var shippingType=form.querySelector('[data-yc-shipping-type]');
  function toggleShipping(){
    if(!shippingType){return;}var type=shippingType.value;
    var fee=form.querySelector('[name=shipping_fee]'),min=form.querySelector('[name=shipping_free_minimum]'),qty=form.querySelector('[name=shipping_per_qty]');
    if(fee){fee.closest('fieldset').hidden=type==='0'||type==='1';}
    if(min){min.closest('fieldset').hidden=type!=='2';}
    if(qty){qty.closest('fieldset').hidden=type!=='4';}
  }
  if(shippingType){shippingType.addEventListener('change',toggleShipping);toggleShipping();}
  // 관련상품 검색
  var search=form.querySelector('[data-yc-relation-search]'),results=form.querySelector('[data-yc-relation-results]'),relations=form.querySelector('[data-yc-relations]'),ids=form.querySelector('[data-yc-relation-ids]');
  function syncRelations(){if(!relations||!ids){return;}ids.value=[].slice.call(relations.querySelectorAll('[data-yc-relation]')).map(function(el){return el.getAttribute('data-yc-relation');}).join(',');}
  if(search&&results){
    var timer=null;
    search.addEventListener('input',function(){
      clearTimeout(timer);var q=search.value.trim();if(q.length<1){results.innerHTML='';return;}
      timer=setTimeout(function(){
        fetch(search.getAttribute('data-yc-search-url')+'?q='+encodeURIComponent(q)+'&exclude='+encodeURIComponent(search.getAttribute('data-yc-exclude')||''),{credentials:'same-origin',headers:{'Accept':'application/json'}})
          .then(function(r){return r.json();}).then(function(data){
            results.innerHTML='';
            (data.items||[]).forEach(function(item){
              var li=document.createElement('li');var button=document.createElement('button');button.type='button';button.className='btn btn-xs btn-ghost';button.textContent=item.code+' '+item.name+' ('+item.category_name+')';
              button.addEventListener('click',function(){
                if(relations.querySelector('[data-yc-relation="'+item.id+'"]')){return;}
                var row=document.createElement('li');row.setAttribute('data-yc-relation',String(item.id));
                row.innerHTML='<code></code> <span></span> <button class="btn btn-xs" type="button" data-yc-remove-relation>제거</button>';
                row.querySelector('code').textContent=item.code;row.querySelector('span').textContent=item.name;
                relations.appendChild(row);syncRelations();results.innerHTML='';search.value='';
              });
              li.appendChild(button);results.appendChild(li);
            });
          }).catch(function(){results.innerHTML='';});
      },250);
    });
  }
})();
```

`www/themes/default/youngcart.css` 끝에 추가:

```css
.yc-checks{display:flex;flex-wrap:wrap;gap:.25rem 1rem;align-items:center}
.yc-apply{font-size:.8rem;opacity:.8}
.yc-image-list{list-style:none;padding:0;margin:0 0 .5rem;display:flex;flex-direction:column;gap:.25rem}
.yc-image-list li{display:flex;align-items:center;gap:.5rem}
.yc-image-list img{object-fit:cover;border-radius:.25rem}
.yc-relations{list-style:none;padding:0;margin:.5rem 0;display:flex;flex-direction:column;gap:.25rem}
.yc-relation-search{position:relative}
.yc-relation-search .menu{max-height:12rem;overflow:auto}
.yc-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-bottom:.75rem}
.yc-copy-form{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:1rem 0}
```

- [ ] **Step 7: 테스트 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/YoungCartAdminTest.php tests/Web/YoungCartPublicTest.php tests/YoungCart`
Expected: PASS

- [ ] **Step 8: 커밋**

```bash
git add modules/youngcart www/themes/default/youngcart-admin.js www/themes/default/youngcart.css tests/Web/YoungCartAdminTest.php
git commit -m "feat: add youngcart product registration and edit form"
```

---

### Task 16: 문서, 기능 지도, 최종 검증

**Files:**
- Create: `docs/youngcart.md`
- Modify: `AGENTS.md`(현재 기능 지도), `docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md`(3.1절·10절 자산 위치)
- Test: 전체 스위트

- [ ] **Step 1: 운영 안내 작성**

`docs/youngcart.md`:

```markdown
# 쇼핑몰(영카트 모듈) 운영 안내

영카트5의 기능을 GNUCMS 확장 모듈로 다시 만든 쇼핑몰이다. 1단계는 분류·상품·옵션·이미지·재고 관리와
메인·분류 목록·유형별 목록·검색·상세 화면을 제공한다. 장바구니·주문·결제는 2단계부터 붙는다.

## 주소와 패키지

| 항목 | 값 |
|---|---|
| 패키지 | `modules/youngcart` (작은 쇼핑몰이 `modules/shop`을 쓰는 동안 이 폴더를 유지한다) |
| 사용자 주소 | `/shop` — 메인 `/shop`, 분류 `/shop/list?ca=코드`, 유형 `/shop/type?t=hit|recommend|new|popular|discount`, 검색 `/shop/search?q=`, 상세 `/shop/item?id=상품코드` 또는 `?slug=`, 이미지 `/shop/image` |
| 관리자 주소 | `/admin/shop` 현황, `/admin/shop/settings`, `/admin/shop/categories`, `/admin/shop/products`와 `new`·`edit`·`types`·`stock`·`option-stock` |
| 별칭 | 없음. 설명 파일의 `aliases: false`로 `/modules/youngcart/…` 주소를 만들지 않는다 |
| 테이블 | `yc_settings`, `yc_categories`, `yc_products`, `yc_product_categories`, `yc_product_images`, `yc_option_groups`, `yc_options`, `yc_product_relations`, `yc_stock_log` (스키마 1판) |

작은 쇼핑몰(`modules/shop`)도 `/shop`을 기본 주소로 쓰므로 둘 중 하나만 켤 수 있다. 둘 다 켜면
나중에 등록되는 패키지가 "기본 주소가 다른 경로와 겹칩니다" 오류로 실행되지 않는다.

## 설치

1. 관리자 → 모듈에서 **쇼핑몰**을 켜고 저장한다.
2. `/admin/shop`에서 **데이터 설치**를 누른다. GET 조회만으로는 테이블을 만들지 않는다.
   SQLite는 설치 전에 `storage/backups/extensions/`에 자동 백업을 남긴다.
3. `/admin/shop/settings`에서 메인 블록·목록 크기·이미지 크기·배송/교환 안내문을 정한다.
4. 분류를 만들고 상품을 등록한다. 사이트 상단 메뉴에 **쇼핑몰**이 자동으로 나타난다.

설치 전에는 모든 공개 주소가 "쇼핑몰을 준비 중입니다" 안내를 보여 준다.

## 분류

- 코드는 단계당 2자, 최대 5단계다. 새 분류 폼이 형제 코드의 다음 값(`10`, `20`, … `z0`)을 제안하며
  직접 입력할 수도 있다. 생성 후에는 바꿀 수 없다.
- 목록은 요청한 분류 코드로 시작하는 모든 활성 하위 분류의 상품을 보여 준다. 상품의 대표 분류와
  추가 분류(최대 2개) 어느 쪽에 연결되어도 나온다.
- 판매가능을 끄면 목록·검색·상세에서 숨겨진다. 관리자는 상세를 미리보기로 열 수 있다.
- 하위 분류나 연결된 상품이 있으면 삭제할 수 없다.
- 수정 화면의 **하위 분류에 적용**은 판매·쿠폰·목록 크기·이미지 크기를 하위 분류에 함께 반영한다.

## 상품

- 코드는 영문·숫자·`-`·`_` 1~20자이며 생성 후 바꿀 수 없다. 폼은 현재 시각 10자리를 제안한다.
- 판매가격이 표시 가격이다. 시중가격은 0이 아닐 때만 취소선으로 보인다. 전화문의를 켜면 가격 대신
  문구를 보여 주고 구매 UI를 숨긴다.
- 포인트는 정액, 판매가 %, 구매가 %(선택옵션 차액 포함) 중 하나이며 % 방식은 0~99다.
- 배송비 유형은 상점 기본·무료·조건부 무료·유료·수량별이다. 실제 계산은 2단계에서 한다.
- 품절은 수동 품절 표시이거나, 선택옵션이 있으면 사용 중인 조합의 재고가 모두 0일 때, 없으면 상품 재고가 0일 때다.
- 두 관리자가 같은 상품을 동시에 수정하면 나중 저장이 "다른 관리자가 먼저 저장했습니다" 오류로 거절된다.
  새로고침 후 다시 입력한다.
- 저장 폼의 **일괄 적용** 체크와 적용 범위를 함께 쓰면 유형·판매·포인트·배송비 등을 같은 대표 분류
  또는 전체 상품에 반영한다.

### 옵션

- 선택옵션은 그룹 최대 3개, 그룹당 값 20개, 조합 1,000개까지다. 그룹 이름과 쉼표 구분 값을 입력하고
  **조합 생성**을 누른 뒤 조합별 차액·재고를 입력한다. 차액은 판매가에 더하는 값이며 합은 0원 이상이어야 한다.
- 추가옵션은 그룹명·항목명·절대가·재고를 행으로 입력한다.
- 저장은 조합 키 기준으로 갱신하므로 기존 조합의 재고가 유지된다. 제출하지 않은 조합은 삭제된다.
- 재고 변경은 `yc_stock_log`에 남는다.

### 이미지

- 상품당 10장. JPG·PNG·WebP·GIF, 8,000px 이하, 용량은 사이트 설정의 첨부 용량을 따른다.
- 파일은 `uploads.dir/youngcart/{상품 id}/`에, 축소본은 `storage/cache/youngcart/`에 저장한다.
- 첫 이미지가 대표 이미지다. 수정 화면에서 순서와 삭제를 지정한다.

## 화면 재정의

테마의 `extensions/youngcart/` 아래에 같은 이름의 템플릿을 두면 조각 단위로 재정의된다.
정적 자산은 `www/themes/{테마}/youngcart.css`, `youngcart.js`, `youngcart-admin.js`이며 테마에 없으면 기본 테마 파일을 쓴다.

## 영카트5 데이터 대응표

| 영카트5 | 이 모듈 |
|---|---|
| `ca_id` | `yc_categories.code` |
| `it_id` | `yc_products.code` |
| `ca_id`, `ca_id2`, `ca_id3` | `yc_product_categories` slot 1~3 |
| `it_type1~5` | `is_hit`, `is_recommended`, `is_new`, `is_popular`, `is_discount` |
| `io_type 0/1`, `io_id` | `yc_options.kind`(`select`/`extra`), `value1~3` |
| `it_option_subject`, `it_supply_subject` | `yc_option_groups` |
| `it_img1~10` | `yc_product_images` |
| `it_info_gubun`, `it_info_value` | `info_group`, `info_values` |
| `ca_1~10`, `it_1~10` | `extra` JSON |

## 이번 범위 밖

장바구니·주문·결제·배송비 계산(2단계), 적립금·회원등급(3단계), 쿠폰·이벤트·배너(4단계), 결제수단 확장(5단계),
후기·문의·위시리스트(6단계), 엑셀 등록·개인결제(7단계), 통계·마이페이지(8단계),
분류별 부관리자와 본인확인·성인인증 제한, 영카트5 가져오기 도구.
```

- [ ] **Step 2: AGENTS.md 기능 지도 갱신**

`AGENTS.md`의 "현재 기능 지도" 목록 마지막 항목(화면 확장) 앞에 추가:

```markdown
- 쇼핑몰(영카트 모듈): `modules/youngcart`가 `/shop`·`/admin/shop`에서 분류·상품·옵션·이미지·재고 관리와 메인·분류 목록·유형별 목록·검색·상세 화면을 제공한다. 작은 쇼핑몰(`modules/shop`)과 같은 주소를 쓰므로 둘 중 하나만 켠다. 장바구니·주문·결제는 이후 단계다.
```

- [ ] **Step 3: 스펙의 자산 위치 정정**

`docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md` 3.1절 트리의 `assets/` 두 줄을 아래로 교체하고, 10절 첫 항목을 같은 내용으로 고친다:

```
  (정적 자산은 www/themes/default/youngcart.css, youngcart.js, youngcart-admin.js 에 둔다 — 테마 자산 규칙)
```

```markdown
- `www/themes/default/youngcart.css`, `youngcart.js`, `youngcart-admin.js`는 테마 자산 규칙에 따라 해시 URL로 불러온다.
  선택 테마의 같은 경로에 파일이 있으면 그것을 쓴다.
```

- [ ] **Step 4: 전체 검증**

Run:

```bash
for f in $(git ls-files modules/youngcart | grep '\.php$'); do php -l "$f" | grep -v 'No syntax errors'; done
./vendor/bin/phpunit
git diff --check
git status --short
```

Expected: 문법 오류 없음, 전체 테스트 PASS(기존 774개 + 새 테스트), `git diff --check` 통과, 추적되지 않은 파일 없음(`storage/`·`vendor/` 제외).

- [ ] **Step 5: 커밋**

```bash
git add docs/youngcart.md AGENTS.md docs/superpowers/specs/2026-09-07-youngcart-catalog-design.md
git commit -m "docs: add youngcart module operations guide and feature map entry"
```

---

## 완료 확인 목록

- 스펙 4절의 테이블 9개가 `Schema::TABLES`에 있고 인덱스 12개가 생성된다.
- `/modules/youngcart/…`가 404이고 `/shop`·`/admin/shop`만 응답한다.
- 작은 쇼핑몰 파일(`modules/shop`, `tests/Shop`, `tests/Web/ShopTest.php`)을 수정하지 않았다.
- 새 Composer 의존성이 없다(`composer.json` 변경 없음).
- 전체 테스트가 SQLite에서 통과하고, `TEST_MYSQL_DSN`이 있으면 MySQL에서도 통과한다.
