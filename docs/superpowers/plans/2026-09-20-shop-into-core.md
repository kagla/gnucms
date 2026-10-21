# 쇼핑몰을 코어로 옮기기 — 구현 계획

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `modules/youngcart`(켜고 끌 수 있는 모듈)를 코어 기능으로 옮긴다 — 코드는 `src/Shop`, 템플릿은 `templates/default/shop`, 표는 코어 스키마 27판이 만들고, 라우트·메뉴·사이드바는 코어가 그리며, 모듈은 사라진다.

**Architecture:** 설계 변경 없는 이사다. 1) 코드·템플릿·테스트를 옮기고 이름공간을 바꾸며 라우트를 코어에 등록한다(이 단계에서는 아직 모듈식 표 설치가 남아 있다). 2) 코어 스키마가 표를 만들고 모듈 시절 표를 넘겨받으며, "데이터 설치" 흐름을 없앤다. 3) 확장 매니저가 옛 키를 퇴역시키고, 설치기의 내장 켜기를 지우고, 상단 탭·사이드바·공개 여부 설정을 붙인다. 4) 문서.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim, PHPUnit 10, SQLite(기본)·MySQL(선택). 새 Composer 의존성 금지.

**Spec:** `docs/superpowers/specs/2026-09-20-shop-into-core-design.md`

## Global Constraints

- 브랜치 `feat/core-commerce`(통합 브랜치, 라이브 체크아웃). `main`에 직접 커밋하지 않는다.
- 바꾸지 않는 것: 표 이름 `yc_*`, 설정 표 `yc_settings`, 업로드 폴더 `uploads/youngcart/`, 캐시 폴더 `storage/cache/youngcart/`, 테마 자산 `youngcart*.css`·`youngcart*.js`, CSS 접두사 `yc-`.
- 이름공간 `GnuCms\Modules\YoungCart\…` → `GnuCms\Shop\…`(하위 `Catalog`·`Commerce`·`Web`·`Admin` 그대로). 테스트 이름공간 `GnuCms\Tests\YoungCart` → `GnuCms\Tests\Shop`.
- 공개 주소 `/shop…`, 관리자 주소 `/admin/shop…`, 외부 콜백 `/shop/pay/callback`은 그대로다.
- 관리자 라우트는 `assertGlobalAdmin()`을, 모든 POST 는 `GnuCms\Web\Csrf::assert()`를 지난다(모듈 컨텍스트가 하던 것과 같다).
- `templates/default/shop/**`의 상대 템플릿 이름(`_header`, `admin/_nav`, `layout('layout')`)은 그대로 동작해야 한다(`View::forShop()`).
- 새 Composer 의존성 금지. 이니톡 결합 금지.
- 커밋은 `refactor:`/`feat:`/`fix:`/`docs:` 형식, 본문 끝에 Co-Authored-By 서명.
- 테스트는 `vendor/bin/phpunit --no-coverage <경로 하나>`. 전체 스위트는 작업 4 끝에서 한 번.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Shop/**` (이동) | 쇼핑몰 도메인·컨트롤러. `Service`가 `PUBLIC_PREFIX`·`ADMIN_PREFIX` 상수를 갖는다 |
| `src/Shop/Routes.php` (신규) | 공개·관리자·외부 콜백 라우트 등록(옛 `bootstrap.php` 의 자리) |
| `src/Shop/Schema.php` (이동·재작성) | `migrate(Connection $db)` 정적 함수 — 표·결제 칸·인덱스 |
| `templates/default/shop/**` (이동) | 공개 화면과 `admin/**` 관리자 화면 |
| `src/View/PhpView.php`, `src/View/View.php` (수정) | `forShop()` |
| `src/App.php` (수정) | `shop()` |
| `src/Web/Routes.php` (수정) | `Shop\Routes::register()` 호출 |
| `src/Db/Schema.php` (수정) | 27판, `TABLES`, `migrateShop()` |
| `src/Extension/Manager.php` (수정) | 퇴역 키 `modules/youngcart` |
| `src/Install/Installer.php` (수정) | 내장 켜기 제거 |
| `src/Web/Kernel.php`, `templates/default/layout.php`, `templates/default/admin/_sidebar.php` (수정) | 상단 탭·사이드바 |
| `tests/Shop/**`, `tests/Web/Shop*.php`, `tests/Browser/Shop*` (이동), `tests/Db/*`, `tests/Extension/ManagerTest.php`, `tests/Install/InstallerTest.php` | 테스트 |
| `docs/shop.md`(이동·재작성), `AGENTS.md`, `docs/extensions.md`, 결제 스펙 | 문서 |

---

### Task 1: 코드·템플릿·테스트를 코어 자리로 옮기고 라우트를 코어에 등록한다

**Files:**
- Move: `modules/youngcart/src/**` → `src/Shop/**`; `modules/youngcart/templates/**` → `templates/default/shop/**`; `tests/YoungCart/**` → `tests/Shop/**`; `tests/Web/YoungCart{Admin,Commerce,Public}Test.php` → `tests/Web/Shop{Admin,Commerce,Public}Test.php`; `tests/Browser/YoungCart*` → `tests/Browser/Shop*`
- Delete: `modules/youngcart/bootstrap.php`, `modules/youngcart/extension.json`, `modules/youngcart/autoload.php`, `modules/youngcart/README.md`
- Create: `src/Shop/Routes.php`
- Modify: `src/Shop/Service.php`, `src/Shop/Web/ShopController.php:32`, `src/Shop/Web/CommerceController.php:31`, `src/Shop/Web/PayController.php:29`, `src/Shop/Admin/AdminBase.php:39` (뷰), `src/View/PhpView.php`, `src/View/View.php`, `src/App.php`, `src/Web/Routes.php` (끝부분), `templates/default/shop/**` (섹션 표식 22곳 + `admin/_extension_header.php`), 테스트 파일들

**Interfaces:**
- Produces: `App::shop(): \GnuCms\Shop\Service`; `Shop\Service::PUBLIC_PREFIX = '/shop'`, `Shop\Service::ADMIN_PREFIX = '/admin/shop'`; `Shop\Routes::register(SlimApp $slim, App $app): void`; 이름 붙은 라우트 `shop.index`(GET `/shop`)와 `admin.shop`(GET `/admin/shop`); `View::forShop(ServerRequestInterface $request): PhpView`, `PhpView::forShop(): self`; 템플릿 블록 값 `nav_section` = `shop`, `admin_section` = `shop`. 테스트 기반 클래스 `GnuCms\Tests\Shop\ShopTestCase` (`setupShop()`, `product()`, `category()`).
- 이 작업 뒤에도 `Service::ready()`·`install()`·`schema()`(PackageSchema, 키 `modules/youngcart`)는 남아 있고 테스트는 `install()`을 부른다. 작업 2가 없앤다.

- [ ] **Step 1: 이동**

```bash
cd /home/kagla/gnucms
git mv modules/youngcart/src src/Shop
git mv modules/youngcart/templates templates/default/shop
git rm -q modules/youngcart/bootstrap.php modules/youngcart/extension.json modules/youngcart/autoload.php modules/youngcart/README.md
rmdir modules/youngcart 2>/dev/null; ls modules
git mv tests/YoungCart tests/Shop
git mv tests/Shop/YoungCartTestCase.php tests/Shop/ShopTestCase.php
for n in Admin Commerce Public; do git mv tests/Web/YoungCart${n}Test.php tests/Web/Shop${n}Test.php; done
for f in tests/Browser/YoungCart*; do git mv "$f" "${f/YoungCart/Shop}"; done
git status --short | wc -l
```
Expected: `modules/`에 `demo-reservation`만 남는다.

- [ ] **Step 2: 이름공간과 클래스 이름**

```bash
grep -rl 'GnuCms\\Modules\\YoungCart' src tests templates | xargs sed -i 's/GnuCms\\Modules\\YoungCart/GnuCms\\Shop/g'
grep -rl 'GnuCms\\Tests\\YoungCart' tests | xargs sed -i 's/GnuCms\\Tests\\YoungCart/GnuCms\\Tests\\Shop/g'
sed -i 's/class YoungCartTestCase/class ShopTestCase/' tests/Shop/ShopTestCase.php
grep -rl 'YoungCartTestCase' tests | xargs sed -i 's/YoungCartTestCase/ShopTestCase/g'
for n in Admin Commerce Public; do sed -i "s/class YoungCart${n}Test/class Shop${n}Test/" tests/Web/Shop${n}Test.php; done
grep -rl "YoungCart" tests/Browser | xargs sed -i 's/YoungCart\([A-Za-z]*Fixture\)/Shop\1/g; s#tests/Browser/YoungCart#tests/Browser/Shop#g'
grep -rn "require_once.*modules/youngcart/autoload.php" tests | cut -d: -f1 | sort -u | xargs -r sed -i "/modules\/youngcart\/autoload.php/d"
grep -rn "YoungCart\|Modules\\\\YoungCart\|modules/youngcart/autoload" src tests templates | grep -v "^tests/Browser/.*console.log" | head
```
Expected: 마지막 grep 은 브라우저 테스트의 콘솔 문구(선택)를 빼면 0건. `tests/Browser/*Fixture.php`가 `modules/youngcart/autoload.php`를 require 했다면 그 줄도 위 sed 가 지운다.

- [ ] **Step 3: 모듈 켜기 제거 (테스트)**

`tests/Web/ShopCommerceTest.php`, `tests/Web/ShopAdminTest.php`, `tests/Web/ShopPublicTest.php`, `tests/Browser/Shop*Fixture.php`에서 다음을 지운다: `(new Manager(new Catalog(dirname(__DIR__, 2)), new StateStore($this->root . '/extensions')))->setEnabledMany(['modules/youngcart' => true]);` 줄(변수를 거치는 형태면 그 두 줄), 그리고 더 이상 쓰지 않는 `use GnuCms\Extension\{Catalog, Manager, StateStore};`. `$this->shop = new Service($this->app); $this->shop->install();`은 그대로 둔다(작업 2가 손댄다).

- [ ] **Step 4: 실패하는 테스트 — 코어 라우트와 뷰**

`tests/Web/ShopPublicTest.php`에 추가(파일의 `setupShop()`·`get()`·`body()` 헬퍼 사용):

```php
    /** 쇼핑몰은 모듈이 아니라 코어다: 확장 상태 파일 없이도 /shop 과 /admin/shop 이 열리고, 이름 붙은 라우트가 있다. */
    #[DataProvider('connectionProvider')]
    public function testShopRoutesAreCoreRoutes(array $config): void
    {
        $this->setupShop($config);
        self::assertSame(200, $this->get($this->app, '/shop')->getStatusCode());
        self::assertFileDoesNotExist($this->root . '/extensions/enabled.json');
        $parser = \GnuCms\Web\Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '')->getRouteCollector()->getRouteParser();
        self::assertSame('/shop', $parser->urlFor('shop.index'));
        self::assertSame('/admin/shop', $parser->urlFor('admin.shop'));
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop'), '/admin/shop');
    }
```

Run: `vendor/bin/phpunit --no-coverage --filter ShopRoutesAreCoreRoutes tests/Web/ShopPublicTest.php`
Expected: FAIL — `/shop`이 404 (모듈 부트스트랩이 사라졌고 코어 라우트는 아직 없다).

- [ ] **Step 5: 뷰·App·상수**

`src/View/PhpView.php`의 `forExtension()` 뒤에:
```php
    /**
     * 쇼핑몰 화면. 테마 경로마다 /shop 을 앞에 둔 뷰라 템플릿의 상대 이름(_header, admin/_nav)이
     * 그대로 동작하고, 다른 테마는 templates/<테마>/shop/ 으로 덮어쓴다.
     */
    public function forShop(): self
    {
        $view = clone $this;
        $view->paths = [...array_map(static fn (string $path): string => $path . '/shop', $this->paths), ...$this->paths];
        $view->icons = null;
        return $view;
    }
```
`src/View/View.php`의 `forExtension()` 뒤에:
```php
    public static function forShop(ServerRequestInterface $request): PhpView
    {
        $view = self::fromRequest($request);
        if (!$view instanceof PhpView) {
            throw new RuntimeException('쇼핑몰 화면에는 PHP 템플릿 뷰가 필요합니다.');
        }
        return $view->forShop();
    }
```
네 군데의 `View::forExtension($request, 'youngcart', dirname(__DIR__, 2) . '/templates')`를 `View::forShop($request)`로 바꾼다(`ShopController`, `CommerceController`, `PayController`, `Admin/AdminBase`).

`src/Shop/Service.php` 클래스 상단에:
```php
    public const PUBLIC_PREFIX = '/shop';
    public const ADMIN_PREFIX = '/admin/shop';
```
`src/App.php`: 다른 서비스 필드 곁에 `private ?\GnuCms\Shop\Service $shop = null;`, `aligo()` 뒤에
```php
    public function shop(): \GnuCms\Shop\Service
    {
        return $this->shop ??= new \GnuCms\Shop\Service($this);
    }
```

- [ ] **Step 6: `src/Shop/Routes.php`**

옛 `bootstrap.php`(git 에서 `git show HEAD:modules/youngcart/bootstrap.php`로 본다)의 등록을 그대로 옮긴다. 관리자 보호와 POST CSRF 는 옛 `Context::route()`의 래퍼를 옮긴 것이다.

```php
<?php

declare(strict_types=1);

namespace GnuCms\Shop;

use GnuCms\App;
use GnuCms\Extension\ExternalRequests;
use GnuCms\Shop\Admin\AdminController;
use GnuCms\Shop\Admin\CategoryController;
use GnuCms\Shop\Admin\OrderController;
use GnuCms\Shop\Admin\ProductController;
use GnuCms\Shop\Admin\ProductFormController;
use GnuCms\Shop\Web\CommerceController;
use GnuCms\Shop\Web\PayController;
use GnuCms\Shop\Web\ShopController;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;

/** 쇼핑몰 라우트. 모듈 시절 bootstrap.php 가 하던 등록을 코어가 한다. */
final class Routes
{
    public static function register(SlimApp $slim, App $app): void
    {
        $service = $app->shop();
        $shop = new ShopController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $commerce = new CommerceController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $pay = new PayController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $orders = new OrderController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $admin = new AdminController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $category = new CategoryController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $product = new ProductController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);
        $productForm = new ProductFormController($service, Service::PUBLIC_PREFIX, Service::ADMIN_PREFIX);

        // 관리자 라우트는 전역 관리자만, POST 는 CSRF 를 지난다 — 모듈 컨텍스트가 하던 래퍼 그대로.
        $map = static function (string $method, string $path, callable $handler, bool $admin = false) use ($slim, $app): \Slim\Interfaces\RouteInterface {
            $wrapped = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($handler, $admin, $method, $app): ResponseInterface {
                if ($admin) $app->guestAcl()->assertGlobalAdmin();
                if ($method === 'POST') Csrf::assert($request);
                return $handler($request, $response, $args);
            };
            $prefix = $admin ? Service::ADMIN_PREFIX : Service::PUBLIC_PREFIX;
            return $slim->map([$method], $prefix . ($path === '/' ? '' : $path), $wrapped);
        };

        $map('GET', '/', static fn ($request, $response) => $shop->handle('index', $request, $response))->setName('shop.index');
        foreach (['list', 'type', 'search', 'item', 'image', 'banner-image'] as $page) {
            $map('GET', '/' . $page, static fn ($request, $response) => $shop->handle($page, $request, $response));
        }
        foreach (['cart', 'checkout', 'orders'] as $page) foreach (['GET', 'POST'] as $method) {
            $map($method, '/' . $page, static fn ($request, $response) => $commerce->handle($page, $request, $response));
        }
        $map('GET', '/order', static fn ($request, $response) => $commerce->handle('order', $request, $response));
        foreach (['cart/add', 'order/cancel'] as $page) {
            $map('POST', '/' . $page, static fn ($request, $response) => $commerce->handle($page, $request, $response));
        }
        $map('GET', '/pay', static fn ($request, $response) => $pay->show($request, $response));

        $map('GET', '/', static fn ($request, $response) => $admin->handle('dashboard', $request, $response), true)->setName('admin.shop');
        $map('POST', '/', static fn ($request, $response) => $admin->handle('dashboard', $request, $response), true);
        $slim->map(['GET'], Service::ADMIN_PREFIX . '/', static fn ($request, $response) => $response->withStatus(301)->withHeader('Location', $slim->getBasePath() . Service::ADMIN_PREFIX));
        foreach (['GET', 'POST'] as $method) {
            $map($method, '/settings', static fn ($request, $response) => $admin->handle('settings', $request, $response), true);
            $map($method, '/orders/detail', static fn ($request, $response) => $orders->handle('orders/detail', $request, $response), true);
            foreach (['categories', 'categories/new', 'categories/edit'] as $page) {
                $map($method, '/' . $page, static fn ($request, $response) => $category->handle($page, $request, $response), true);
            }
            foreach (['products', 'products/types', 'products/stock', 'products/option-stock'] as $page) {
                $map($method, '/' . $page, static fn ($request, $response) => $product->handle($page, $request, $response), true);
            }
            foreach (['products/new', 'products/edit'] as $page) {
                $map($method, '/' . $page, static fn ($request, $response) => $productForm->handle($page, $request, $response), true);
            }
        }
        $map('GET', '/orders', static fn ($request, $response) => $orders->handle('orders', $request, $response), true);
        $map('POST', '/products/copy', static fn ($request, $response) => $product->handle('products/copy', $request, $response), true);
        $map('GET', '/products/search', static fn ($request, $response) => $product->handle('products/search', $request, $response), true);

        // 이니시스 인증 결과 콜백(폼). 세션 없이 쿼리의 HMAC 으로만 인증한다.
        $slim->add(new ExternalRequests([
            Service::PUBLIC_PREFIX . '/pay/callback' => [[$pay, 'callbackAuthenticate'], [$pay, 'callback'], 65536, 'application/x-www-form-urlencoded'],
        ], $slim->getBasePath()));
    }
}
```
클로저 안에서 `$page`를 `foreach` 변수로 잡는 것은 옛 bootstrap 과 같다(`static fn` 은 정의 시점 값을 붙든다).

`src/Web/Routes.php`의 `// 코어 경로가 등록된 뒤 확장 기본 주소의 충돌을 검사한다.` 줄 바로 앞에
```php
        \GnuCms\Shop\Routes::register($slim, $app);
```

- [ ] **Step 7: 템플릿 표식**

```bash
cd templates/default/shop
grep -rl "nav_section') ?>modules/youngcart" . | xargs sed -i "s#nav_section') ?>modules/youngcart#nav_section') ?>shop#"
grep -rl "admin_section') ?>modules" . | xargs sed -i "s#admin_section') ?>modules#admin_section') ?>shop#"
grep -rl "'section' => 'modules'" . | xargs sed -i "s#'section' => 'modules'#'section' => 'shop'#"
grep -rn "modules/youngcart\|'modules'" . | head
```
`admin/_extension_header.php`의 빵부스러기에서 `<li><a href="<?= $this->url('admin.modules') ?>">모듈</a></li>` 줄을 지운다(쇼핑몰은 모듈이 아니다). 남은 grep 은 0건.

- [ ] **Step 8: 테스트 통과**

Run (각각):
```bash
php -l src/Shop/Routes.php && php -l src/View/PhpView.php
vendor/bin/phpunit --no-coverage tests/Shop
vendor/bin/phpunit --no-coverage tests/Web/ShopPublicTest.php
vendor/bin/phpunit --no-coverage tests/Web/ShopCommerceTest.php
vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php
vendor/bin/phpunit --no-coverage tests/Extension
vendor/bin/phpunit --no-coverage tests/Install
```
Expected: 모두 OK. `tests/Extension`은 `modules/demo-reservation`만 보며 통과해야 한다. `tests/Install`은 아직 `modules/youngcart`를 켜는 테스트가 있어 통과한다(디렉터리가 없어도 켜기 목록에 적기만 한다) — 작업 3이 지운다. `ShopPublicTest::testNotInstalledShowsPreparingPageAndNoAliasPaths`는 아직 `install()` 흐름이 있어 통과한다.

- [ ] **Step 9: 커밋 (둘)**

```bash
git add -A modules src/Shop templates/default/shop src/View src/App.php src/Web/Routes.php
git commit -m "refactor: move the shop from modules/youngcart into the core

Code lives in src/Shop (GnuCms\Shop), templates in templates/default/shop,
routes are registered by the core, views come from View::forShop() so the
templates' relative names keep working. Table creation still goes through
the module-era PackageSchema until the next commit.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
git add -A tests
git commit -m "test: follow the shop into the core

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 코어 스키마 27판이 쇼핑몰 표를 만들고 데이터 설치 흐름을 없앤다

**Files:**
- Modify: `src/Shop/Schema.php`(재작성), `src/Db/Schema.php` (VERSION, TABLES, `create()`, `migrateAll()`, `migrateShop()`), `src/Shop/Service.php` (`ready()`·`install()`·`schema()`·`requireReady()` 제거), `src/Shop/Web/ShopController.php:36-39`, `src/Shop/Web/CommerceController.php:37`, `src/Shop/Web/PayController.php` (`requireReady()` 호출), `src/Shop/Admin/AdminBase.php` (`ready`, `requireReady()`), `src/Shop/Admin/AdminController.php` (install 동작·안내), `templates/default/shop/notready.php` → `closed.php`, `templates/default/shop/admin/dashboard.php` (데이터 설치 단추·안내가 있으면 제거)
- Test: `tests/Db/SchemaTest.php`, `tests/Db/AligoSchemaTest.php`, `tests/Shop/SchemaTest.php`, `tests/Shop/ShopTestCase.php`, `tests/Web/Shop*Test.php`, `tests/Browser/Shop*Fixture.php`

**Interfaces:**
- Produces: `GnuCms\Shop\Schema::migrate(Connection $db): void`(멱등), `Shop\Schema::TABLES`(12개), `Shop\Schema::PAYMENT_COLUMNS`; 코어 `Db\Schema::VERSION = '27'`, `Db\Schema::migrateShop(): void`. `Service`에서 `ready()`·`install()`·`schema()`·`requireReady()`가 사라진다. 템플릿 `closed.php`(작업 3이 공개 여부 설정에 쓴다).

- [ ] **Step 1: 실패하는 테스트 — 코어가 표를 만들고 모듈 시절 기록을 넘겨받는다**

`tests/Db/SchemaTest.php`: `assertCount(20, Schema::TABLES)` → `32`. 같은 파일에 추가(파일의 기존 테스트가 `Connection`·`Schema`를 만드는 방식을 그대로 쓴다; `$this->db`나 `$db` 같은 헬퍼 이름은 파일을 보고 맞춘다):

```php
    /** 쇼핑몰 표는 코어가 만든다. 모듈 시절(modules/youngcart)에 만든 표는 그대로 두고 확장 스키마 기록만 지운다. */
    #[DataProvider('connectionProvider')]
    public function testShopTablesAreCoreTablesAndTheModuleRecordIsRetired(array $config): void
    {
        $db = $this->freshDatabase($config);
        $schema = new Schema($db);
        $schema->create();
        self::assertNotNull($db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->table('yc_orders')));
        $db->insert('extension_schemas', ['package_key' => 'modules/youngcart', 'schema_version' => 3, 'table_names' => json_encode(\GnuCms\Shop\Schema::TABLES), 'state' => 'ready']);
        $schema->migrateAll();
        self::assertNull($db->selectOne('SELECT package_key FROM ' . $db->table('extension_schemas') . " WHERE package_key = 'modules/youngcart'"));
        $schema->migrateAll(); // 멱등
        self::assertSame('27', explode('.', $schema->stamp())[0]);
    }
```
`tests/Db/AligoSchemaTest.php`: `testSchemaVersionIsTwentySix` → `testSchemaVersionIsTwentySeven`, 문자열 `'26'` → `'27'`.

Run: `vendor/bin/phpunit --no-coverage tests/Db/SchemaTest.php`
Expected: FAIL — 표 수 20≠32, `yc_orders` 없음.

- [ ] **Step 2: `src/Shop/Schema.php` 재작성**

```php
<?php

declare(strict_types=1);

namespace GnuCms\Shop;

use GnuCms\Db\Connection;
use GnuCms\Error\DomainError;

/**
 * 쇼핑몰 표. 코어 스키마(Db\Schema::migrateShop())가 부른다. 모듈 시절의 표 이름을 그대로 쓰므로
 * 그때 만든 데이터를 넘겨받는다. 멱등이다 — 표는 IF NOT EXISTS, 칸은 없을 때만, 인덱스는 없을 때만.
 */
final class Schema
{
    public const TABLES = ['yc_settings', 'yc_categories', 'yc_products', 'yc_product_categories', 'yc_product_images',
        'yc_option_groups', 'yc_options', 'yc_product_relations', 'yc_stock_log', 'yc_orders', 'yc_order_items', 'yc_order_history'];

    /** 결제 칸. 결제 이전에 만든 yc_orders 에는 addColumn() 이 넣는다. */
    public const PAYMENT_COLUMNS = [ /* 지금 파일의 배열 그대로 */ ];

    public static function migrate(Connection $db): void
    {
        // 지금 파일의 install() 클로저 본문을 그대로 옮긴다: $definitions … CREATE TABLE IF NOT EXISTS …,
        // PAYMENT_COLUMNS 의 addColumn 루프, $indexes 와 존재 검사 루프.
    }

    private static function paymentColumnSql(): string { /* 그대로 */ }
    private static function addColumn(Connection $db, string $table, string $column, string $definition): void { /* 그대로 */ }
}
```
즉 `KEY`·`VERSION`·`PackageSchema` 의존과 `install(PackageSchema $schema)` 서명만 사라지고, 클로저 본문이 `migrate()` 본문이 된다. 주석의 "지금 파일" 은 이 작업 시작 시점의 `src/Shop/Schema.php`다 — 위 뼈대의 주석 자리에 그 코드를 옮겨 적는다.

- [ ] **Step 3: 코어 스키마**

`src/Db/Schema.php`:
- `VERSION` `'26'` → `'27'`.
- `TABLES`의 `'pay_inicis_settings', 'pay_inicis_transactions',` 다음 줄에 `...\GnuCms\Shop\Schema::TABLES,` — 상수 표현식에 전개가 안 되면 12개 이름을 그대로 적는다.
- `create()`의 `foreach ($this->statements() as $sql) …` 뒤에 `\GnuCms\Shop\Schema::migrate($this->db);`.
- `migrateAll()`의 `$this->migrateExtensionSchemas();` 다음 줄에 `$this->migrateShop();`.
- `migratePayments()` 뒤에
```php
    /** 27판: 쇼핑몰 표. 모듈 시절(modules/youngcart)에 만든 표는 그대로 넘겨받고 확장 스키마 기록만 지운다. */
    public function migrateShop(): void
    {
        \GnuCms\Shop\Schema::migrate($this->db);
        if ($this->tableExists('extension_schemas')) {
            $this->db->execute('DELETE FROM ' . $this->db->table('extension_schemas') . ' WHERE package_key = ?', ['modules/youngcart']);
        }
    }
```
`Schema::drop()`이 `TABLES`를 돌며 지우므로 테스트 정리도 함께 된다.

Run: `vendor/bin/phpunit --no-coverage tests/Db`
Expected: PASS.

- [ ] **Step 4: 데이터 설치 흐름 제거**

- `Service.php`: `ready()`·`install()`·`schema()`·`requireReady()`와 `use GnuCms\Extension\PackageSchema;`·`use GnuCms\Error\DomainError;`(다른 데서 안 쓰면) 제거.
- `ShopController::handle()`: `if (!$this->service->ready()) { … 'notready' … }` 블록 제거. `CommerceController::handle()`: `if (!$this->service->ready()) return $view->render($response, 'notready', $data);` 제거. `PayController::show()`: `$this->service->requireReady();` 제거.
- `AdminBase::context()`: `'ready' => $this->service->ready(),` 제거; `requireReady()` 메서드 제거; 각 관리자 컨트롤러의 `if ($redirect = $this->requireReady($response, $data)) return $redirect;` 줄 제거(`grep -rn requireReady src/Shop`).
- `AdminController`: `install` 동작 분기(`action === 'install'` → `$this->service->install()` → `?installed=1` 리다이렉트)와 `?install=1`·`?installed=1` 안내를 제거. `dashboard.php`에 데이터 설치 단추·안내가 있으면 제거.
- `git mv templates/default/shop/notready.php templates/default/shop/closed.php`; 본문에서 관리자용 "쇼핑몰 데이터 설치" 링크 줄을 지우고 문구는 `쇼핑몰을 준비 중입니다` / `상품이 등록되면 이곳에서 볼 수 있습니다.` 그대로 둔다(작업 3이 쓴다).

- [ ] **Step 5: 테스트 정리**

- `tests/Shop/ShopTestCase.php`: `setupShop(array $config, bool $install = true)` → `setupShop(array $config)`; `if ($install) $this->shop->install();` 제거; `tearDown()`의 MySQL 정리(`Schema::TABLES` 루프 + `extension_schemas` DELETE)를 `if (isset($this->app) && $this->app->db()->dialect()->name() === 'mysql') (new CoreSchema($this->app->db()))->drop();`로 바꾼다.
- `tests/Shop/SchemaTest.php`: `install()`·`schema()->status()`·판 번호 검사를 모두 걷어내고 다음 셋으로 다시 쓴다(기대 인덱스 목록은 그대로 둔다):
```php
    #[DataProvider('connectionProvider')]
    public function testMigrateIsIdempotentAndCreatesEveryTableAndIndex(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        Schema::migrate($db); Schema::migrate($db);
        foreach (Schema::TABLES as $table) self::assertNotNull($db->selectOne('SELECT COUNT(*) AS c FROM ' . $db->table($table)));
        self::assertSame(21, count(self::INDEXES));
        $this->assertIndexesExist(); // 파일에 있던 헬퍼(INDEXES 는 인덱스 => 표)
    }

    #[DataProvider('connectionProvider')]
    public function testMigrateAddsThePaymentColumnsToAPrePaymentOrdersTable(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        foreach (['yc_order_pay_by', 'yc_order_payment'] as $index) $db->execute('DROP INDEX ' . $db->index($index) . ($db->dialect()->name() === 'mysql' ? ' ON ' . $db->table('yc_orders') : ''));
        foreach (array_keys(Schema::PAYMENT_COLUMNS) as $column) $db->execute('ALTER TABLE ' . $db->table('yc_orders') . ' DROP COLUMN ' . $column);
        Schema::migrate($db);
        self::assertNotNull($db->selectOne('SELECT payment_method, payment_id, pay_by, refunded_amount FROM ' . $db->table('yc_orders') . ' LIMIT 1') ?? []);
        // 기존 행 삽입·기본값 검사는 지금 파일의 testVersionThreeAddsPaymentColumnsToExistingOrders 를 그대로 옮긴다.
    }
```
(마지막 주석 자리에 그 검사 코드를 옮겨 적는다. `INDEXES` 상수와 `assertIndexesExist()` 헬퍼는 그대로 둔다.)
- `tests/Web/Shop*Test.php`·`tests/Browser/Shop*Fixture.php`: `$this->shop->install();` 제거, `setupModule($config, false)`/`setupShop($config, false)` 호출의 두 번째 인자 제거.
- `ShopPublicTest::testNotInstalledShowsPreparingPageAndNoAliasPaths` → 이름 `testNoAliasPaths`, "준비 중" 두 단언을 지우고 `/modules/youngcart/…` 404 단언만 남긴다. `ShopAdminTest::testNotInstalledAdminPagesRedirectToDashboard` 삭제. `ShopAdminTest::testGuardsInstallAndSettings` → `testGuardsSettings`: `action => 'install'` 을 보내는 세 줄과 `installed=1` 단언을 지우고 손님 리다이렉트·회원 403·설정 저장 부분만 남긴다(설정 저장 POST 의 CSRF 403 단언이 있으면 그대로).

Run (각각): `tests/Shop`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php`, `tests/Web/ShopAdminTest.php`, `tests/Db`.
Expected: 모두 OK.

- [ ] **Step 6: 커밋**

```bash
git add -A src/Shop src/Db/Schema.php templates/default/shop tests/Shop tests/Web tests/Browser tests/Db
git commit -m "feat: let the core schema own the shop tables

Core schema 27 creates the yc_ tables, adopts the ones the module era
created, and drops the module's extension_schemas record. The shop no
longer has an install step or a not-ready state.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: 모듈 키 퇴역, 설치기 정리, 상단 탭·사이드바, 공개 여부 설정

**Files:**
- Modify: `src/Extension/Manager.php`, `src/Install/Installer.php`, `src/Web/Kernel.php` (뷰 전역), `templates/default/layout.php:121-124, ~253`, `templates/default/admin/_sidebar.php:21`, `src/Shop/Settings.php`, `src/Shop/Admin/AdminController.php` (`flatten()`), `templates/default/shop/admin/settings.php`, `src/Shop/Web/ShopController.php`, `src/Shop/Web/CommerceController.php`
- Test: `tests/Extension/ManagerTest.php`, `tests/Install/InstallerTest.php`, `tests/Shop/SettingsTest.php`, `tests/Web/ShopPublicTest.php`, `tests/Web/AdminViewFixture`를 쓰는 화면 테스트가 `shop_visible` 전역을 요구하면 `tests/Support/AdminViewFixture.php`에 `'shop_visible' => true` 추가

**Interfaces:**
- Produces: `Extension\Manager::RETIRED = ['modules/youngcart']` (목록에서 숨기고 저장 때 버린다); 뷰 전역 `shop_visible`(bool); `Shop\Settings::all()['visible']`(bool, 기본 true), 폼 이름 `visible` + 동반 hidden `visible_form=1`; 공개 화면은 `visible`이 꺼지면 `closed.php`를 그린다.

- [ ] **Step 1: 실패하는 테스트 — 퇴역 키**

`tests/Extension/ManagerTest.php`(`$this->state`·`$this->manager`·`$this->extensionRoot` 준비를 그대로 쓴다):
```php
    /** 코어로 옮겨간 모듈 키는 오류 카드로 보이지 않고, 다음 저장 때 조용히 빠진다. */
    public function testRetiredModuleKeyIsHiddenAndDroppedOnTheNextSave(): void
    {
        $this->state->update(static fn (array $enabled): array => [...$enabled, 'modules/youngcart']);
        self::assertArrayNotHasKey('modules/youngcart', $this->manager->packages());
        $first = array_key_first($this->manager->packages());
        $this->manager->setEnabledMany([$first => true]);
        self::assertNotContains('modules/youngcart', $this->state->read());
        self::assertContains($first, $this->state->read());
    }
```
Run: `vendor/bin/phpunit --no-coverage --filter RetiredModuleKey tests/Extension/ManagerTest.php`
Expected: FAIL — `modules/youngcart`가 오류 항목으로 목록에 있다.

- [ ] **Step 2: Manager**

`src/Extension/Manager.php`:
```php
    /** 코어로 옮겨 간 옛 모듈 키. 상태 파일에 남아 있어도 목록에 보이지 않고 다음 저장 때 빠진다. */
    private const RETIRED = ['modules/youngcart'];

    private static function live(array $keys): array
    {
        return array_values(array_diff($keys, self::RETIRED));
    }
```
`packages()`의 `$enabled = $snapshot['enabled'];` → `$enabled = self::live($snapshot['enabled']);`. `setEnabledMany()`의 클로저 첫 줄 `$candidate = $active;` → `$active = self::live($active); $candidate = $active;`. `boot()`의 `$active = $this->state->read();`(109행)와 관리자 테스트 실행 메서드의 같은 줄(183행)도 `$active = self::live($this->state->read());`로 바꾼다 — `state->read()`를 부르는 곳은 그 셋뿐이다.

Run: `vendor/bin/phpunit --no-coverage tests/Extension`
Expected: PASS.

- [ ] **Step 3: 설치기**

`src/Install/Installer.php`: `BUNDLED_ENABLED` 상수, `enableBundledPackages()` 메서드와 그 호출, `use GnuCms\Extension\StateStore;` 제거. `tests/Install/InstallerTest.php`: `testFinishEnablesTheBundledShopOnANewInstall`·`testFinishLeavesAnExistingExtensionStateAlone` 삭제, `use GnuCms\Extension\StateStore;` 제거(다른 데서 안 쓰면), `tearDown()`의 `storage/extensions/*` 항목은 두어도 된다.

Run: `vendor/bin/phpunit --no-coverage tests/Install`
Expected: PASS.

- [ ] **Step 4: 실패하는 테스트 — 공개 여부**

`tests/Shop/SettingsTest.php`:
```php
    /** 쇼핑몰 공개: 기본 켜짐, 폼이 보낸 값으로 끄고 켠다. 폼에 없으면 이전 값. */
    #[DataProvider('connectionProvider')]
    public function testVisibilityDefaultsOnAndFollowsTheForm(array $config): void
    {
        $this->setupShop($config);
        self::assertTrue($this->shop->settings->all()['visible']);
        $this->shop->settings->save($this->settingsInput() + ['visible_form' => '1']);
        self::assertFalse($this->shop->settings->all()['visible']);
        $this->shop->settings->save($this->settingsInput());
        self::assertFalse($this->shop->settings->all()['visible'], '폼에 없으면 이전 값');
        $this->shop->settings->save($this->settingsInput() + ['visible_form' => '1', 'visible' => '1']);
        self::assertTrue($this->shop->settings->all()['visible']);
    }
```
`tests/Web/ShopPublicTest.php`:
```php
    /** 공개를 끄면 상단 탭이 사라지고 /shop 은 준비 중 안내, 관리자와 결제 콜백 경로는 그대로다. */
    #[DataProvider('connectionProvider')]
    public function testHiddenShopShowsTheClosedPageAndNoTab(array $config): void
    {
        $this->setupShop($config);
        self::assertStringContainsString('>쇼핑몰</a>', $this->body($this->get($this->app, '/')));
        $settings = $this->shop->settings->all(); $settings['visible'] = false;
        $this->app->db()->update('yc_settings', ['payload' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)], 'id = :id', ['id' => 'settings']);
        self::assertStringNotContainsString('>쇼핑몰</a>', $this->body($this->get($this->app, '/')));
        $closed = $this->get($this->app, '/shop');
        self::assertSame(200, $closed->getStatusCode());
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($closed));
        self::assertStringContainsString('쇼핑몰을 준비 중입니다', $this->body($this->get($this->app, '/shop/cart')));
        $this->assertLoginRedirect($this->get($this->app, '/admin/shop'), '/admin/shop');
    }
```
(`yc_settings` 행이 없으면 `insert` 로 만든다 — `ShopCommerceTest::enablePayments()`의 insert-or-update 를 참고.)

Run: `vendor/bin/phpunit --no-coverage --filter "Visibility|HiddenShop" tests/Shop/SettingsTest.php` 그리고 같은 필터로 `tests/Web/ShopPublicTest.php`
Expected: FAIL — `visible` 키 없음, 상단에 `>쇼핑몰</a>` 없음.

- [ ] **Step 5: 설정·화면**

- `Shop\Settings::defaults()`에 `'visible' => true,` (첫 항목). `save()`의 `$settings['order_notice'] = …` 줄 다음에
```php
        $settings['visible'] = array_key_exists('visible_form', $input) ? $bool('visible') : $previous['visible'];
```
- `AdminController::flatten()`: `$flat['visible'] = $settings['visible'] ? '1' : '0';`
- `templates/default/shop/admin/settings.php`의 첫 섹션(배송·주문 또는 메인 배너 섹션 위) 맨 앞에
```php
  <section class="card" id="settings-visible"><div class="card-body"><h2 class="card-title">공개</h2>
    <input type="hidden" name="visible_form" value="1">
    <label class="label"><input class="checkbox checkbox-sm" type="checkbox" name="visible" value="1"<?= ($values['visible'] ?? '1') === '1' ? ' checked' : '' ?>> 쇼핑몰 공개</label>
    <p class="muted">끄면 상단 메뉴의 쇼핑몰 탭이 사라지고 쇼핑몰 화면은 "준비 중" 안내를 보입니다. 관리자 화면과 진행 중인 결제는 영향을 받지 않습니다.</p>
  </div></section>
```
`_form_nav` 섹션 배열에 `'settings-visible' => '공개'`를 맨 앞에.
- `ShopController::handle()`: `$data['settings'] = $this->service->settings->all();` 뒤(이미지 경로 `image`·`banner-image`는 제외 — 이전 `ready()` 분기와 같은 자리)에
```php
        if (!$data['settings']['visible']) {
            if (in_array($page, ['image', 'banner-image'], true)) throw DomainError::notFound('이미지를 찾을 수 없습니다.');
            return $view->render($response, 'closed', $data);
        }
```
`CommerceController::handle()`: `$data['settings'] = …` 뒤에 `if (!$data['settings']['visible']) return $view->render($response, 'closed', $data);`. `PayController::show()`와 콜백은 손대지 않는다.
- 뷰 전역: `src/Web/Kernel.php`에서 `$view->addGlobal('site_menu', …)` 곁에 `$view->addGlobal('shop_visible', (bool) $app->shop()->settings->all()['visible']);` — `site_menu`가 DB 를 읽는 같은 자리다. `tests/Support/AdminViewFixture.php`의 전역 목록에 `'shop_visible' => true`.
- `templates/default/layout.php`: 상단 탭에서 `<?php foreach ($public_extensions ?? [] as $key => $extension): …` 바로 앞에
```php
            <?php if ($shop_visible ?? false): $shopActive = trim($this->block('nav_section')) === 'shop'; ?><a class="tab<?= $shopActive ? ' tab-active' : '' ?>" href="<?= $this->url('shop.index') ?>"<?= $shopActive ? ' aria-current="page"' : '' ?>>쇼핑몰</a><?php endif ?>
```
서랍 메뉴(약 253행, `foreach ($public_extensions …)` 의 `<li>` 앞)에 `<?php if ($shop_visible ?? false): ?><li><a href="<?= $this->url('shop.index') ?>"><?= $this->icon('gift', 18) ?> 쇼핑몰</a></li><?php endif ?>`.
- `templates/default/admin/_sidebar.php`: 알림톡·문자 발송 줄 다음에
```php
    <li><a href="<?= $this->url('admin.shop') ?>"<?php if ($section === 'shop'): ?> class="menu-active" aria-current="page"<?php endif ?> title="쇼핑몰"><?= $this->icon('gift', 18) ?><span class="menu-text">쇼핑몰</span></a></li>
```

Run (각각): `tests/Shop`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php`, `tests/Web/ShopAdminTest.php`, `tests/Web/AdminPageTest.php`, `tests/Web/AligoSettingsTest.php`(뷰 전역 때문에 관리자 화면 테스트 한 묶음 더).
Expected: 모두 OK. `$this->url('shop.index')`가 실패하면 작업 1의 라우트 이름이 붙어 있는지 확인한다.

- [ ] **Step 6: 커밋**

```bash
git add -A src/Extension/Manager.php src/Install/Installer.php src/Web/Kernel.php templates/default/layout.php templates/default/admin/_sidebar.php src/Shop templates/default/shop tests
git commit -m "feat: retire the shop module key and show the shop as a core feature

The extension manager hides and drops modules/youngcart from the state
file, the installer no longer has a bundled package to enable, the site
header and the admin sidebar carry a shop entry, and a 쇼핑몰 공개 setting
hides the storefront for sites that do not sell.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4: 문서와 전체 스위트

**Files:**
- Move: `docs/youngcart.md` → `docs/shop.md`
- Modify: `docs/shop.md`, `AGENTS.md:34, 68`, `docs/extensions.md:235-238`, `docs/superpowers/specs/2026-09-20-youngcart-payments-design.md` (경로 언급), `docs/superpowers/specs/2026-09-17-aligo-messaging-design.md`(모듈 언급이 있으면), `docs/messaging.md`(있으면)

- [ ] **Step 1: docs/shop.md**

`git mv docs/youngcart.md docs/shop.md`. 다음을 고친다.
- 제목 `# 쇼핑몰 운영 안내`. 머리말: `영카트5의 기능을 GNUCMS 코어 기능으로 다시 만든 쇼핑몰이다. 게시판·회원처럼 처음부터 있으며 켜고 끄는 스위치나 데이터 설치는 없다.` (이어지는 기능 나열은 그대로.)
- 「주소와 패키지」표: `패키지` 행을 `| 코드 | `src/Shop` (`GnuCms\Shop`), 템플릿 `templates/default/shop/`, 테마 덮어쓰기 `templates/<테마>/shop/` |`로, `별칭` 행 삭제, `테이블` 행 끝을 `(코어 스키마 27판이 만든다)`로.
- 「설치」절을 다음으로 바꾼다:
```markdown
## 설치와 업그레이드

설치할 것이 없다. 코어 스키마가 표를 만들고, 관리자 메뉴의 **쇼핑몰**(`/admin/shop`)이 바로 열린다.
모듈(`modules/youngcart`) 시절에 설치한 사이트는 업그레이드 때 코어 스키마 27판이 그 표를 그대로
넘겨받고 확장 스키마 기록을 지운다. 확장 상태 파일에 남은 `modules/youngcart`는 무시되고 다음
저장 때 빠진다. 업로드(`uploads/youngcart/`)와 설정(`yc_settings`)은 그대로다.

판매를 하지 않는 사이트는 쇼핑몰 설정의 **쇼핑몰 공개**를 끈다. 상단 메뉴의 탭이 사라지고
`/shop` 화면은 "준비 중" 안내를 보인다. 관리자 화면과 진행 중인 결제(`/shop/pay/callback`)는
영향을 받지 않는다.
```
- 「기존 1판 설치에서 갱신」계열 절은 위 문단으로 갈음하고 삭제한다. `extensions/youngcart/` 테마 덮어쓰기 언급(127행 부근)은 `templates/<테마>/shop/`로.
- 문서 안의 `modules/youngcart` 언급을 모두 찾아(`grep -n youngcart docs/shop.md`) 자산·폴더 이름(그대로 두는 것)만 남기고 고친다.

- [ ] **Step 2: 나머지 문서**

- `AGENTS.md` 34행: `쇼핑몰: `src/Shop`이 `/shop`·`/admin/shop`에서 …`(문장의 나머지는 그대로). 68행: `쇼핑몰은 코어 기능이다(`src/Shop`, 2026-09-20 이사). …` 로 시작하도록 고치고 "내장 모듈"·"새 설치에서 켜진 채" 표현을 지운다.
- `docs/extensions.md` 235행 문단: `쇼핑몰은 확장이 아니라 코어 기능이다(`src/Shop`, `docs/shop.md`). `/shop`·`/admin/shop`은 코어 주소이므로 같은 기본 주소를 선언한 모듈은 실행되지 않는다.`
- 결제 스펙 `2026-09-20-youngcart-payments-design.md`: `modules/youngcart` → `src/Shop`(경로 언급 부분만).
- `grep -rn "modules/youngcart\|내장 모듈" docs AGENTS.md README.md | grep -v superpowers/plans` 로 남은 곳을 확인한다(계획 문서는 역사 기록이라 손대지 않는다).

- [ ] **Step 3: 전체 스위트와 커밋**

Run: `vendor/bin/phpunit --no-coverage` (전체, 한 번)
Expected: OK.

```bash
git add -A docs AGENTS.md
git commit -m "docs: describe the shop as a core feature

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

## 자체 검토

- **스펙 대조.** §1 자리 → 작업 1(이동·이름공간·삭제)·4(docs). §2 스키마 → 작업 2. 실행·라우트(관리자 래퍼, 외부 콜백, 상수, `/shop` 충돌) → 작업 1. 화면(`forShop`, 상단 탭, 사이드바, `shop/admin/layout`은 옮긴 `admin/extension.php`가 그 역할) → 작업 1·3. 공개 여부 → 작업 3. 퇴역(디렉터리 삭제, Manager, Installer) → 작업 1·3. §3 테스트 → 각 작업. §4 문서 → 작업 4.
- **자리표시자.** 작업 2 Step 2·5의 "그대로 옮긴다" 지시는 같은 저장소 안의 현재 파일 본문을 가리키며 파일과 함수 이름을 명시했다.
- **이름 일치.** `Service::PUBLIC_PREFIX/ADMIN_PREFIX`, `Shop\Routes::register`, `View::forShop`/`PhpView::forShop`, `App::shop`, `Shop\Schema::migrate/TABLES/PAYMENT_COLUMNS`, `Db\Schema::migrateShop`, 라우트 이름 `shop.index`/`admin.shop`, 전역 `shop_visible`, 설정 `visible`/`visible_form`, `Manager::RETIRED`, 템플릿 `closed.php` — 작업 간 동일하다.
