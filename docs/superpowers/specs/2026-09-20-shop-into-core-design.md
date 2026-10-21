# 쇼핑몰을 코어로 옮기기 (2026-09-20)

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

영카트 계열 쇼핑몰은 지금 `modules/youngcart`에 있는 켜고 끌 수 있는 모듈이다. 사용자가 뜻한 "코어에
포함"은 게시판·회원처럼 처음부터 있는 것이다: 켜는 스위치도, 데이터 설치 클릭도 없고, 표는 코어
스키마가 만들고, 관리자 메뉴와 `/shop` 주소가 항상 있다. 이 문서는 그 이사를 정한다. 설계 변경은
없고, 코드가 코어 자리로 옮겨 가며 코어와 맞닿는 지점만 바뀐다. 오늘 만든 결제 코드(3.1)는 그대로
따라간다.

## 1. 자리

| 지금 | 뒤 |
|---|---|
| `modules/youngcart/src/**`, 이름공간 `GnuCms\Modules\YoungCart\` | `src/Shop/**`, 이름공간 `GnuCms\Shop\` (하위 `Catalog`·`Commerce`·`Web`·`Admin` 그대로). 코어 PSR-4 로 자동 로드되므로 `autoload.php` 는 없어진다 |
| `modules/youngcart/templates/**` (공개 화면과 `admin/**`) | `templates/default/shop/**` (같은 트리. `shop/admin/**` 포함) |
| `modules/youngcart/{bootstrap.php, extension.json, README.md}` | 없어진다. 라우트는 `src/Web/Routes.php` 가 등록한다 |
| `tests/YoungCart/**`, `tests/Web/YoungCart*.php`, `tests/Browser/YoungCart*` | `tests/Shop/**`(이름공간 `GnuCms\Tests\Shop`), `tests/Web/Shop*.php`, `tests/Browser/Shop*` |
| `docs/youngcart.md` | `docs/shop.md` |

바꾸지 않는 것: 표 이름 `yc_*`, 설정 표 `yc_settings`, 업로드 폴더 `uploads/youngcart/`, 캐시 폴더
`storage/cache/youngcart/`, 테마 자산 `youngcart*.css`·`youngcart*.js`, CSS 접두사 `yc-`. 기존 데이터와
테마가 그대로 살아 있어야 하기 때문이다.

## 2. 코어와 맞닿는 지점

### 스키마
- `src/Shop/Schema.php` 는 `PackageSchema` 를 쓰지 않고 `public static function migrate(Connection $db): void` 하나가 된다: 12개 표 `CREATE TABLE IF NOT EXISTS`, `yc_orders` 결제 칸 `addColumn`(기존 2판 설치), 인덱스(없으면 생성). 지금 모듈 스키마의 클로저 본문 그대로다.
- 코어 `Db\Schema` 27판: `TABLES` 에 12개 `yc_*` 표가 들어가고(테스트의 표 수 20 → 32), `create()` 와 `migrateAll()` 이 `migrateShop()` 을 부르며, `migrateShop()` 은 `Shop\Schema::migrate()` 를 부른 뒤 `extension_schemas` 에서 `modules/youngcart` 행을 지운다(모듈 시절 설치를 코어가 넘겨받는다).
- `Service::ready()`·`install()`·`requireReady()`·`schema()` 와 관리자의 데이터 설치 동작·안내는 없어진다. 표는 항상 있다.

### 실행과 라우트
- `App::shop(): Shop\Service` (다른 서비스처럼 지연 생성).
- `src/Web/Routes.php` 가 공개 `/shop…`, 관리자 `/admin/shop…`, 외부 콜백 `/shop/pay/callback` 을 등록한다. 관리자 라우트는 모듈 컨텍스트가 하던 것과 같이 `assertGlobalAdmin()` 과 POST 의 `Csrf::assert()` 를 지나는 래퍼로 감싼다(`Context::route()` 의 래퍼를 옮긴다). 외부 콜백은 이니톡 브랜치의 코어 방식과 같이 `ExternalRequests` 미들웨어 하나로 등록한다.
- 컨트롤러의 `routePrefix`·`adminRoutePrefix` 는 상수 `/shop`·`/admin/shop` 이 된다(생성자 인자 제거).
- 모듈이 `/shop` 을 쓰던 자리를 코어가 차지하므로, `/shop` 을 선언하는 제3자 모듈은 지금처럼 "기본 주소가 다른 경로와 겹칩니다" 로 거절된다.

### 화면
- 뷰: `View::forShop(ServerRequestInterface $request): PhpView` — 테마 경로마다 `/shop` 을 앞에 붙인 뷰(`forExtension()` 과 같은 방식, `extensions/` 층은 없다). 그래서 템플릿의 상대 이름(`_header`, `admin/_nav`, `layout('layout')`)이 그대로 동작하고, 다른 테마는 `templates/<테마>/shop/` 로 덮어쓴다.
- 사이트 상단 메뉴: 코어 `layout.php` 가 "쇼핑몰" 탭을 그린다(`nav_section` 값 `shop`). 확장 메뉴(`public_extensions`) 자리에 의존하지 않는다.
- 관리자 사이드바: 운영 묶음에 "쇼핑몰"(`admin_section` 값 `shop`, `/admin/shop`). 쇼핑몰 관리자 화면의 `admin/extension.php` 레이아웃은 `shop/admin/layout.php` 로 남아 코어 `admin/layout` 을 확장한다(모듈 전용 CSS·body class 그대로).

### 공개 여부 설정
판매를 하지 않는 사이트를 위해 쇼핑몰 설정에 **쇼핑몰 공개**(`visible`, 기본 켜짐) 하나를 둔다. 끄면
상단 메뉴의 탭이 사라지고 공개 `/shop…` 화면은 "쇼핑몰을 준비 중입니다" 안내(`closed.php`, 지금의
`notready.php`)를 보인다. 관리자 화면과 `/shop/pay/callback` 은 영향을 받지 않는다(진행 중인 결제가
있을 수 있다).

### 모듈의 퇴역
- `modules/youngcart/` 디렉터리는 삭제한다.
- 기존 사이트의 `storage/extensions/enabled.json` 에 남은 `modules/youngcart` 는 `Extension\Manager` 가 **퇴역 키**로 알아본다: 목록에 오류 카드로 보이지 않고, 다음 저장 때 조용히 빠진다.
- `Install\Installer` 의 `BUNDLED_ENABLED` 와 그 테스트는 없어진다(켤 것이 없다).

## 3. 테스트
- 옮긴 테스트는 모듈 켜기(`Manager…setEnabledMany`)와 `install()` 호출 없이 돈다. `ShopTestCase::setupShop()` 은 코어 `Schema::create()` 뒤에 `Service` 만 만든다.
- 새로 더하는 것: 코어 `SchemaTest` 표 수, `migrateShop()` 이 모듈 시절 표를 넘겨받고 `extension_schemas` 행을 지우는 것, `Manager` 의 퇴역 키 처리, 공개 여부 설정(탭·`closed` 화면·콜백은 그대로), 관리자 라우트 래퍼(손님 403·CSRF).
- 전체 스위트를 한 번 돌린다.

## 4. 문서
`docs/shop.md`(패키지·설치 절을 "코어 기능" 으로 다시 씀: 업그레이드 때 코어 스키마 27판이 표를
넘겨받음, 공개 여부 설정), AGENTS.md(34·68행), `docs/extensions.md` 의 내장 모듈 문단,
`docs/superpowers/specs/2026-09-20-youngcart-payments-design.md` 의 경로 언급.
