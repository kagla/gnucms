# 영카트 모듈 1단계: 기반과 카탈로그 — 설계

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

2026-09-07. 브랜치 `feat/youngcart-module`.

## 1. 배경과 목표

영카트5의 모든 기능을 GNUCMS 확장 모듈로 새로 만든다. 기존 작은 쇼핑몰(`modules/shop`)은
손대지 않고, 새 모듈은 사용자 주소 `/shop`, 관리자 주소 `/admin/shop`에서 동작한다.
작업은 여덟 개 하위 프로젝트로 나누고 이 문서는 첫 번째인 **기반과 카탈로그**만 다룬다.

1단계가 끝나면:

- 관리자가 분류·상품·옵션·관련상품·이미지를 등록하고 재고를 관리할 수 있다.
- 방문자가 `/shop`에서 메인·분류 목록·유형별 목록·검색·상품 상세를 볼 수 있다.
- 이후 단계(장바구니·주문·적립금·쿠폰·결제수단·후기·통계)가 이 데이터 모델 위에 붙는다.

전체 순서: ① 기반과 카탈로그 ② 장바구니와 주문 ③ 적립금과 회원등급 ④ 쿠폰·이벤트·배너
⑤ 결제수단 확장 ⑥ 고객 참여(문의·후기·위시리스트) ⑦ 개인결제와 운영 도구
⑧ 통계와 마이페이지.

## 2. 제약과 방침

- **참조 원본**은 `/home/kagla/gnuboard5`(그누보드 5.6.26에 포함된 영카트5, LGPL)이다.
  동작과 데이터 의미만 참고하고 PHP·SQL·스킨 코드는 옮기지 않는다(클린룸 재작성). GNUCMS는 MIT다.
- **이관 대비.** 영카트5 데이터를 나중에 가져올 수 있도록 상품 코드(`it_id`)·분류 코드(`ca_id`)를
  담는 컬럼을 두고 옵션 가격 의미를 유지한다. 테이블 구조 자체를 같게 만들지는 않는다.
- **확장 런타임 제약.** 확장 라우트는 GET/POST의 고정 경로만 등록할 수 있다. 식별자는
  쿼리 문자열로 받는다. 관리자 라우트(`admin: true`)에는 런타임이 전역 관리자 검사와
  CSRF 검사를 자동으로 붙인다. 운영 서버에서 Composer·npm·빌드를 하지 않으므로 새 PHP
  의존성과 빌드 도구를 쓰지 않는다.
- **DB.** SQLite와 MySQL/MariaDB에서 같은 의미로 동작해야 한다. 테이블 접두사는 `yc_`다.
  `shop_`은 작은 쇼핑몰이 쓰고 있다.
- **화면.** 기본 테마 하나로 PC·모바일을 반응형으로 처리한다. 영카트5의 스킨·모바일 전용
  화면·PHP include 경로는 옮기지 않는다. 테마는 `extensions/youngcart/`로 조각을 재정의한다.
- **주소.** `/shop`, `/admin/shop`만 응답한다. 런타임이 자동으로 붙이는 `/modules/youngcart/…`
  별칭은 이 모듈에서 끈다(3.2절).
- **폴더 이름.** 작은 쇼핑몰이 `modules/shop`을 쓰는 동안 `modules/youngcart`를 쓴다.
  패키지 키·네임스페이스·테이블 접두사는 폴더 이름과 독립적이므로 나중에 폴더만 옮긴다.

## 3. 아키텍처

### 3.1 패키지 배치

```
modules/youngcart/
  extension.json
  bootstrap.php            라우트 등록만 한다
  autoload.php             GnuCms\Modules\YoungCart\ → src/
  README.md
  src/
    Service.php            Store·Settings·Images·Catalog\* 조립
    Schema.php             PackageSchema 등록, 1판 DDL, 멱등 마이그레이션
    Store.php              접두사·식별자·트랜잭션·재고 원장 도우미
    Settings.php           yc_settings 페이로드와 기본값·검증
    Input.php              요청값 정규화·검증 도우미
    Images.php             상품 이미지 저장·삭제·크기별 응답
    ProductInfo.php        상품정보고시 35개 군 정적 데이터
    Catalog/Categories.php 분류 트리·코드 생성·저장·삭제 보호·하위 적용
    Catalog/Products.php   상품 저장 트랜잭션·복사·삭제·일괄 편집
    Catalog/Options.php    옵션 조합 생성·upsert·재고·가격 계산·페이지용 JSON
    Catalog/Listing.php    목록·유형·검색·메인 블록 조회(정렬·페이징)
    Catalog/Pricing.php    표시 가격·포인트 계산 한 곳(3단계가 등급 할인을 끼운다)
    Web/ShopController.php 공개 화면
    Admin/DashboardController.php, SettingsController.php,
    Admin/CategoryController.php, ProductController.php, StockController.php
  templates/
    layout 은 사이트 layout 을 확장한다 (공개), admin/extension 을 확장한다 (관리자)
    index.php list.php type.php search.php item.php
    _product_card.php _pager.php _options.php _breadcrumb.php _category_menu.php
    admin/dashboard.php settings.php categories.php category_form.php
    admin/products.php product_form.php product_types.php product_stock.php option_stock.php
  (정적 자산은 www/themes/default/youngcart.css, youngcart.js, youngcart-admin.js 에 둔다 — 테마 자산 규칙)
```

`extension.json`:

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

결제 플러그인은 2단계에서 `optional`에 추가한다.

### 3.2 코어 변경: `aliases` 필드

- `Extension\Catalog`가 설명 파일의 선택 필드 `aliases`(불리언, 기본 `true`)를 읽어 패키지
  배열에 넣는다. 불리언이 아니면 설명 파일 오류로 표시한다.
- `Extension\Manager`가 `Context` 생성 시 이 값을 넘긴다.
- `Context::paths()`는 `aliases`가 `false`면 `'/' . key . path` 항목을 만들지 않는다. 같은 조건에서 `route()`의
  관리자 라우트(`admin: true`)는 `admin_route_prefix` 아래에만 등록하고 `route_prefix` 아래의 사본을 만들지 않는다.
  `route_prefix`가 없는 패키지는 `aliases: false`를 선언할 수 없다(설명 파일 오류).
- `docs/extensions.md` 설명 파일 절에 필드를 추가한다.
- 테스트: `aliases: false`인 패키지의 `/modules/{id}/…`가 404이고 `route_prefix` 주소는 동작한다.
  `route_prefix` 없이 선언하면 오류로 표시된다.

### 3.3 요청 흐름

- `bootstrap.php`는 `Context`로 GET/POST 라우트를 등록하고 컨트롤러에 `routePrefix`·
  `adminRoutePrefix`를 넘긴다. 서비스 조립은 `Service` 한 곳에서 한다(`Store`, `Settings`,
  `Images`, `Catalog\*`).
- 공개 컨트롤러는 `View::forExtension($request, 'youngcart', __DIR__ . '/templates')`로 렌더링하고,
  템플릿은 사이트 `layout`을 확장한다. 관리자 템플릿은 `admin/extension` 레이아웃을 쓰고
  `admin_section`은 `modules`다.
- 오류는 `DomainError`로 던진다. 404는 `notFound`, 입력 오류는 `validation`(422),
  동시 수정 충돌은 `validation(['version' => …])`로 422를 돌려 폼 값을 유지한다.
- 데이터 설치는 관리자 현황 화면의 **데이터 설치/갱신** POST에서만 실행한다. GET은 설치 상태만
  보여 준다. 설치 전에는 공개 화면이 "준비 중" 안내를, 관리자 화면이 설치 안내를 표시한다.

## 4. 데이터 모델 (스키마 1판)

`Schema::KEY = 'modules/youngcart'`, `Schema::VERSION = 1`. `PackageSchema::install()`에
테이블 목록을 등록해 멱등 실행과 백업 포함을 보장한다. 모든 DDL은 `CREATE TABLE IF NOT EXISTS`,
인덱스는 존재 검사 후 생성한다. 시각은 `BIGINT` 유닉스 초다. `{AUTO_PK}`·`{TEXT}`는 방언 typeMap을 쓴다.

### yc_settings

| 컬럼 | 형 | 뜻 |
|---|---|---|
| id | VARCHAR(32) PK | `'settings'` 한 행 |
| payload | {TEXT} | JSON. 5절 참조 |

### yc_categories

| 컬럼 | 형 | 뜻 |
|---|---|---|
| id | {AUTO_PK} | |
| code | VARCHAR(10) NOT NULL UNIQUE | 영카트 호환 분류 코드. 단계당 2자, 최대 5단계, `[0-9a-z]` |
| parent_id | BIGINT NULL | 상위 분류. 1단계는 NULL |
| depth | SMALLINT NOT NULL | 1~5 |
| name | VARCHAR(100) NOT NULL | |
| sort_order | INTEGER NOT NULL DEFAULT 0 | 형제 사이 순서. 정렬은 `sort_order, code` |
| active | SMALLINT NOT NULL DEFAULT 1 | 판매가능. 0이면 목록·검색·상세에서 숨긴다 |
| no_coupon | SMALLINT NOT NULL DEFAULT 0 | 쿠폰 대상 제외(4단계에서 사용) |
| head_html / tail_html | {TEXT} NOT NULL | 목록 위·아래 HTML. 정화해서 저장 |
| list_columns / list_rows | SMALLINT NOT NULL | 목록 한 행 상품 수, 페이지당 행 수. 1~12 / 1~50 |
| image_width / image_height | INTEGER NOT NULL | 목록 이미지 크기. 높이 0은 비율 유지 |
| extra | {TEXT} NOT NULL | 여분필드 JSON `[{"label":…,"value":…}×10]` |
| created_at / updated_at | BIGINT NOT NULL | |

인덱스: `yc_cat_parent (parent_id)`, `yc_cat_order (sort_order)`.

### yc_products

| 컬럼 | 형 | 뜻 |
|---|---|---|
| id | {AUTO_PK} | |
| code | VARCHAR(20) NOT NULL UNIQUE | 영카트 호환 상품 코드 `[A-Za-z0-9_-]{1,20}`. 생성 후 불변 |
| slug | VARCHAR(200) NOT NULL UNIQUE | 주소용 이름. 이름에서 생성, 중복 시 `-2` 접미 |
| category_id | BIGINT NOT NULL | 대표 분류 |
| name | VARCHAR(250) NOT NULL | HTML 제거 |
| summary | {TEXT} NOT NULL | 요약 설명. 정화한 HTML |
| description | {TEXT} NOT NULL | 상세 설명. 정화한 HTML, 에디터 이미지는 코어 콘텐츠 이미지 |
| description_text | {TEXT} NOT NULL | 검색용 평문. 저장 시 태그 제거·공백 정리 |
| list_price | BIGINT NOT NULL DEFAULT 0 | 시중가격. 0이면 표시하지 않음 |
| price | BIGINT NOT NULL | 판매가격 ≥ 0 |
| point_type | SMALLINT NOT NULL DEFAULT 0 | 0 정액, 1 판매가 %, 2 구매가 %(선택옵션 차액 포함) |
| point | INTEGER NOT NULL DEFAULT 0 | 정액 점수 또는 0~99 % |
| supply_point | INTEGER NOT NULL DEFAULT 0 | 추가옵션 정액 점수 |
| tax_free | SMALLINT NOT NULL DEFAULT 0 | 1 비과세 |
| seller_email | VARCHAR(191) NOT NULL DEFAULT '' | 판매자 메일(2단계 주문 메일) |
| active | SMALLINT NOT NULL DEFAULT 1 | 판매가능 |
| no_coupon | SMALLINT NOT NULL DEFAULT 0 | |
| sold_out | SMALLINT NOT NULL DEFAULT 0 | 수동 품절 |
| stock | INTEGER NOT NULL DEFAULT 0 | 선택옵션이 없는 상품의 재고 |
| stock_alert | INTEGER NOT NULL DEFAULT 0 | 재고 통보 기준 |
| buy_min / buy_max | INTEGER NOT NULL DEFAULT 0 | 1회 최소·최대 구매수량. 0은 제한 없음(2단계 검증) |
| phone_inquiry | SMALLINT NOT NULL DEFAULT 0 | 전화문의. 가격 대신 문구, 구매 불가 |
| shipping_type | SMALLINT NOT NULL DEFAULT 0 | 0 상점 기본, 1 무료, 2 조건부 무료, 3 유료, 4 수량별 |
| shipping_method | SMALLINT NOT NULL DEFAULT 0 | 0 선불, 1 착불, 2 구매자 선택 |
| shipping_fee / shipping_free_minimum / shipping_per_qty | BIGINT/BIGINT/INTEGER NOT NULL DEFAULT 0 | 2단계에서 계산 |
| head_html / tail_html | {TEXT} NOT NULL | 상세 위·아래 HTML |
| info_group | VARCHAR(50) NOT NULL DEFAULT '' | 상품정보고시 군 키 |
| info_values | {TEXT} NOT NULL | 고시 항목 JSON `{항목키: 값}` |
| memo | {TEXT} NOT NULL | 관리자 메모. 공개 화면에 내지 않음 |
| hit | INTEGER NOT NULL DEFAULT 0 | 조회수 |
| sold_qty | INTEGER NOT NULL DEFAULT 0 | 누적 판매수량(2단계가 갱신) |
| review_count / review_avg | INTEGER / DECIMAL(2,1) NOT NULL DEFAULT 0 | 6단계가 갱신 |
| is_hit / is_recommended / is_new / is_popular / is_discount | SMALLINT NOT NULL DEFAULT 0 | 영카트 it_type1~5 |
| sort_order | INTEGER NOT NULL DEFAULT 0 | 기본 정렬 `sort_order ASC, id DESC` |
| extra | {TEXT} NOT NULL | 여분필드 JSON ×10 |
| version | INTEGER NOT NULL DEFAULT 0 | 낙관적 잠금 |
| created_at / updated_at | BIGINT NOT NULL | |

인덱스: `yc_prod_category (category_id)`, `yc_prod_name (name)`, `yc_prod_order (sort_order)`,
`yc_prod_updated (updated_at)`, `yc_prod_price (price)`.

### yc_product_categories

`product_id BIGINT, category_id BIGINT, slot SMALLINT` — PK `(product_id, slot)`, UNIQUE
`(product_id, category_id)`, 인덱스 `yc_pc_category (category_id)`. slot 1은 대표 분류와 같다.
저장 시 대표 분류를 slot 1로 항상 기록하고 2·3은 선택이다.

### yc_product_images

`id {AUTO_PK}, product_id BIGINT, filename VARCHAR(100), sort_order SMALLINT` — 인덱스
`yc_img_product (product_id)`. 상품당 최대 10장. 첫 장이 대표 이미지다.

### yc_options

| 컬럼 | 형 | 뜻 |
|---|---|---|
| id | {AUTO_PK} | |
| product_id | BIGINT NOT NULL | |
| kind | VARCHAR(8) NOT NULL | `select` 선택옵션, `extra` 추가옵션 |
| value1 / value2 / value3 | VARCHAR(100) NOT NULL DEFAULT '' | 선택옵션: 1~3단계 값. 추가옵션: value1 그룹명, value2 항목명 |
| price | BIGINT NOT NULL DEFAULT 0 | 선택옵션은 판매가에 더하는 차액(음수 가능, 합은 ≥ 0). 추가옵션은 절대가 ≥ 0 |
| stock | INTEGER NOT NULL DEFAULT 0 | |
| stock_alert | INTEGER NOT NULL DEFAULT 0 | |
| active | SMALLINT NOT NULL DEFAULT 1 | |
| sort_order | INTEGER NOT NULL DEFAULT 0 | |

UNIQUE `(product_id, kind, value1, value2, value3)`(MySQL은 `utf8mb4_bin`), 인덱스 `yc_opt_product (product_id)`.
선택옵션의 그룹 이름은 `yc_products`가 아니라 **`yc_option_groups`**에 둔다:

`yc_option_groups`: `product_id BIGINT, kind VARCHAR(8), position SMALLINT, name VARCHAR(100)` —
PK `(product_id, kind, position)`. 선택옵션은 position 1~3, 추가옵션은 그룹 순서다.
영카트의 `it_option_subject`·`it_supply_subject`(쉼표 연결 문자열)에 해당한다.

### yc_product_relations

`product_id BIGINT, related_id BIGINT, sort_order SMALLINT` — PK `(product_id, related_id)`,
인덱스 `yc_rel_related (related_id)`. 한 방향만 저장하고 조회는 양방향이다.

### yc_stock_log

`id {AUTO_PK}, product_id BIGINT, option_id BIGINT NULL, delta INTEGER, kind VARCHAR(20),
reference VARCHAR(100), actor VARCHAR(100), created_at BIGINT` — 인덱스 `yc_stock_product (product_id)`.
1단계에서는 `kind = 'admin'`(상품 저장·재고 일괄 편집)만 기록한다.

### 이관 대응표

| 영카트5 | 이 모듈 |
|---|---|
| `ca_id` | `yc_categories.code` |
| `it_id` | `yc_products.code` |
| `ca_id, ca_id2, ca_id3` | `yc_product_categories` slot 1~3 |
| `it_type1~5` | `is_hit, is_recommended, is_new, is_popular, is_discount` |
| `io_type 0/1`, `io_id`(chr(30) 결합) | `kind select/extra`, `value1~3` |
| `it_option_subject / it_supply_subject` | `yc_option_groups` |
| `it_img1~10` | `yc_product_images` |
| `it_info_gubun / it_info_value` | `info_group / info_values` |
| `ca_1~10, it_1~10` | `extra` JSON |

## 5. 설정 (`yc_settings.payload`)

| 키 | 기본 | 뜻 |
|---|---|---|
| `main.{hit,new,recommend,discount,popular}.use` | hit·new·recommend·discount `true`, popular `false` | 메인 블록 표시 |
| `main.*.columns` / `rows` | 4 / 1 | 블록 크기 |
| `main.*.image_width` / `image_height` | 200 / 0 | |
| `category.columns` / `rows` / `image_width` / `image_height` | 3 / 5 / 200 / 0 | 새 분류 기본값 |
| `type.columns` / `rows` / `image_width` / `image_height` | 4 / 5 / 200 / 0 | 유형별 목록 |
| `search.columns` / `rows` / `image_width` / `image_height` | 4 / 5 / 200 / 0 | 검색 결과 |
| `related.use` / `columns` / `image_width` / `image_height` | true / 4 / 100 / 0 | 관련상품. 행 제한 없음 |
| `detail.image_width` / `image_height` | 400 / 0 | 상세 대표 이미지 |
| `show_tax` | false | 가격 옆 "부가세 포함" 표시 |
| `shipping.content` / `exchange.content` | '' | 상세 배송·교환 탭 본문(정화한 HTML). 비어 있으면 탭 숨김 |

정수 범위: columns 1~12, rows 1~50, 이미지 0~2000. 저장은 전체 페이로드를 검증한 뒤 한 번에 쓴다.

## 6. 분류 규칙

- **코드 생성.** 새 분류 폼은 형제들의 마지막 2자 중 최댓값을 36진수로 읽어 36을 더한 값을 제안한다
  (`10, 20, …, z0`). 1단계 첫 분류는 `10`. 제안값이 `zz`를 넘으면 빈칸으로 두고 직접 입력을 요구한다.
  입력 코드는 상위 코드 + 2자 `[0-9a-z]`여야 하고 저장 시 소문자로 정규화, 중복 거절. 생성 후 변경 불가.
- **트리.** `parent_id`·`depth`는 코드에서 유도해 저장한다. 5단계 아래에는 하위를 만들 수 없다.
- **표시 가능.** 목록 화면은 요청한 분류가 `active = 1`일 때만 연다(아니면 404). 목록에는
  요청 코드로 시작하는 **활성** 분류에 slot 1~3으로 연결된 활성 상품이 나온다.
  검색은 대표 분류가 활성인 상품만 찾는다. 상세는 상품이 활성이고 대표 분류가 활성일 때 열리며
  관리자는 미리보기 표시와 함께 항상 열 수 있다.
- **삭제 보호.** 하위 분류가 있으면 거절. 어느 슬롯이든 연결된 상품이 있으면 개수와 함께 거절.
- **하위 적용.** 수정 화면의 "하위 분류에 적용"을 켜면 active·list_*·image_*·no_coupon을 코드 접두사가
  같은 모든 하위 분류에 함께 반영한다.
- **일괄 편집.** 목록 화면에서 name, sort_order, active, list_columns, list_rows, image_width, image_height를
  여러 행 한 번에 저장한다. 행 단위 검증 실패는 전체를 취소하고 오류 행을 표시한다.

## 7. 상품 규칙

- **코드.** 새 상품 폼은 현재 유닉스 시각 10자리를 제안한다. `[A-Za-z0-9_-]{1,20}`, 중복 거절, 생성 후 불변.
- **slug.** 이름의 앞뒤 공백 제거 후 공백·`/`·`?`·`#`·`%`·따옴표를 `-`로 바꾸고 연속 `-`를 합친다.
  한글은 유지한다. 비면 코드를 쓴다. 중복이면 `-2`, `-3`… 을 붙인다. 이름을 바꾸면 slug도 다시 만든다.
- **가격.** `price ≥ 0`, `list_price ≥ 0`. `point_type ∈ {1,2}`면 `point` 0~99. 판매 표시 가격은 항상 `price`다.
  회원등급 할인은 3단계에서 계산 함수를 바꿔 넣을 수 있도록 `Listing`·상세가 `Pricing::display()` 한 곳을 거친다.
- **전화문의.** `phone_inquiry = 1`이면 목록·상세에 가격 대신 "전화문의"를 표시하고 구매 UI를 숨긴다.
- **품절.** `sold_out = 1`이거나, 활성 선택옵션이 있으면 전부 재고 ≤ 0일 때, 없으면 `stock ≤ 0`일 때 품절이다.
  목록 카드에 SOLD OUT 표시. 1단계에서 가용 재고 = 재고(2단계에서 주문 대기분을 뺀다).
- **재고 변경.** 상품 저장·재고 일괄 편집에서 재고가 바뀌면 `yc_stock_log`에 차이를 기록한다.
- **조회수.** 상세를 열 때 `yc_hit_{id}` 쿠키가 없으면 +1 하고 1시간 쿠키를 놓는다.
- **관련상품.** 폼에서 검색해 선택한 순서로 저장. 조회는 양방향이며 `sort_order, id` 순.
- **여분필드.** 라벨·값 10쌍을 JSON으로 저장하고 폼에만 표시한다. 테마가 상세에서 쓸 수 있도록 뷰에 전달한다.
- **분류 적용·전체 적용.** 폼의 각 필드군 옆 체크로 저장 값을 같은 대표 분류의 모든 상품 또는 전체 상품에
  일괄 반영한다. 대상 필드: 유형 플래그, active, no_coupon, point_type/point/supply_point, tax_free,
  배송비 5개, buy_min/buy_max, head_html/tail_html, seller_email, phone_inquiry.
- **최근 입력 기억.** 저장 시 대표 분류를 31일 쿠키에 담아 새 상품 폼에 미리 채운다.

### 7.1 저장 트랜잭션

1. 입력을 검증한다(코드·이름·분류 존재·가격·포인트·옵션·관련상품·이미지 개수).
2. 수정이면 `UPDATE yc_products SET version = version + 1 WHERE id = ? AND version = ?`로 잠근다.
   변경 행이 0이면 422 `version` 오류("다른 관리자가 먼저 저장했습니다. 새로고침 후 다시 입력해 주세요.").
3. 기본 정보 저장. slug 갱신. `description_text` 재생성.
4. 분류 슬롯 교체(slot 1 = 대표).
5. 옵션 그룹 교체, 옵션은 `(kind, value1, value2, value3)` 키로 upsert. 제출되지 않은 기존 옵션은 삭제한다.
   재고 차이는 원장에 기록한다. 조합 중복·값에 제어 문자·`<`·`>`·따옴표가 있으면 거절.
6. 관련상품 교체(자기 자신·중복·없는 상품 거절).
7. 이미지: 삭제 표시된 파일 제거, 새 업로드 저장, 순서 저장. 파일 이동은 트랜잭션 커밋 후에 확정하고
   실패 시 새로 저장한 파일을 지운다.

### 7.2 옵션

- 선택옵션 그룹은 최대 3개, 그룹당 값 최대 20개, 조합 최대 1,000개. 폼에서 그룹 이름과 쉼표 구분 값을
  입력하고 **조합 생성**을 누르면 서버가 카티션 곱으로 조합 표를 만든다(JS가 꺼져 있어도 동작).
  기존 조합의 가격·재고는 유지하고 새 조합은 기본 차액 0, 재고 9999, 통보 100, 사용.
- 추가옵션은 그룹명·항목명·절대가·재고·통보·사용을 행으로 입력한다. 그룹 최대 10개, 항목 최대 20개.
- 일괄 적용: 조합 표의 가격·재고·통보·사용을 한 번에 채운다.
- 상세 화면용 JSON: `{"select":{"groups":[…],"items":[{"v":[…],"price":n,"stock":n}]},"extra":{"groups":[{"name":…,"items":[{"name":…,"price":n,"stock":n}]}]}}`.
  비활성·품절 조합은 `stock 0`으로 내려 "품절" 표시에 쓴다. JS는 단계별 select를 만들고
  선택 행 목록과 합계 `Σ(price + 차액)×수량 + Σ 추가옵션가×수량`을 보여 준다. 수량 1~9999, 재고 초과 불가.
  1단계에서는 장바구니·바로구매 버튼을 렌더링하지 않는다(2단계).

### 7.3 이미지

- 저장 경로 `{uploads.dir}/youngcart/{product_id}/{32hex}.{jpg|png|webp|gif}`. `uploads.dir`가 없으면
  `storage/uploads`. DB에는 파일명만 둔다.
- 허용 MIME `image/jpeg, image/png, image/webp, image/gif`. 확장자와 실제 MIME이 일치해야 한다.
  파일당 최대 용량은 사이트 설정 `attach_max_mb`를 따른다. 상품당 10장.
- 응답 `/shop/image?p={product_id}&f={filename}&s={main|list|type|search|related|detail|thumb|original}`.
  `s`별 최대 너비는 설정값에서 읽고(`list`는 해당 분류 값), `thumb`은 70, `original`은 원본 그대로다. 축소본은 `storage/cache/youngcart/{product_id}/`에
  코어 `ImageResizer::ensure()`로 만들고 `Cache-Control: public, max-age=86400`으로 보낸다.
  파일명 검증 실패·없는 파일은 404.
- 이미지가 없는 상품은 테마의 `no-image` 조각을 표시한다.
- 상품 삭제 시 원본과 캐시 폴더를 지운다. 복사 시 원본 파일을 새 상품 폴더로 복사한다.

## 8. 공개 화면 (`/shop`)

| 주소 | 내용 |
|---|---|
| `GET /shop` | 메인. 설정에 따라 히트·최신·추천·할인·인기 블록. 각 블록은 `is_*` 플래그 상품을 기본 정렬로 블록 크기만큼 |
| `GET /shop/list?ca=코드&sort=&dir=&page=` | 분류 목록. 상단 경로(breadcrumb), 하위 분류 링크, 정렬 선택, 카드 격자, 페이지 이동, head/tail HTML |
| `GET /shop/type?t=hit\|recommend\|new\|popular\|discount&sort=&dir=&page=` | 유형별 목록. `t`가 아니면 404 |
| `GET /shop/search?q=&ca=&min=&max=&sort=&dir=&page=` | 검색 |
| `GET /shop/item?id=코드` 또는 `?slug=` | 상세 |
| `GET /shop/image?…` | 이미지 |

- **정렬 화이트리스트** `name, sold, price, rating, reviews, recent` × `asc|desc`. 지정 정렬 뒤에 항상
  `sort_order ASC, id DESC`를 붙인다. 기본은 뒤쪽만.
- **페이징** 페이지 크기 = 열×행(분류는 분류 값, 나머지는 설정값). `page`는 1 이상 정수, 범위 밖이면 빈 목록.
  총 건수는 별도 COUNT. 페이지 이동은 모듈 `_pager.php`가 그리고 테마가 재정의할 수 있다.
- **카드** 대표 이미지, 이름, 요약, 시중가격(취소선, 0이면 생략), 판매가격 또는 전화문의, 유형 아이콘
  (히트·추천·최신·인기·할인), 품절 표시, 별점(6단계 전까지 review_count 0이면 숨김).
- **검색** `q`는 앞뒤 공백 제거 후 50자로 자르고 공백으로 나눈 단어를 모두 포함(AND)해야 한다.
  대상은 `name, code, summary, description_text`의 LIKE. `ca`는 코드 접두사, `min/max`는 가격 범위.
  결과 위에 분류별 건수를 보여 준다. 빈 `q`는 검색하지 않고 폼만 보여 준다. 대소문자 구분은
  DB 기본 collation을 따르며 테스트는 정확 일치로만 검증한다.
- **상세** 이미지 갤러리(대표 + 썸네일), 이름·요약, 가격 또는 전화문의,
  포인트 안내(`point_type 2`는 "구매금액의 N%"), 부가세 표시, 배송비 요약(유형별 문구, 계산은 2단계),
  옵션 UI(7.2), 탭 "상품정보·배송정보·교환정보"(뒤 둘은 설정 본문이 있을 때), 상품정보고시 표,
  관련상품 블록, 이전·다음 상품(같은 대표 분류에서 기본 정렬 기준의 앞·뒤), head/tail HTML.
  후기·문의 탭과 위시리스트·SNS 공유는 6단계에서 붙인다.
- **사이트 메뉴** `public_path`로 상단·모바일 메뉴에 "쇼핑몰"이 연결된다(런타임 기능).
- **미설치** 상태에서는 모든 공개 주소가 "쇼핑몰을 준비 중입니다" 안내를 200으로 보여 준다.

## 9. 관리자 화면 (`/admin/shop`)

| 주소 | 내용 |
|---|---|
| `GET /admin/shop` | 현황: 설치 상태·판번호, 상품·활성·품절·분류 수, 재고 ≤ 통보 기준 상품·옵션 상위 20개, **데이터 설치/갱신** 버튼 |
| `POST /admin/shop` | `action=install` 데이터 설치/갱신 |
| `GET/POST /admin/shop/settings` | 5절 설정 |
| `GET /admin/shop/categories` | 트리 목록(들여쓰기, 상품 수), 인라인 일괄 편집 폼 |
| `POST /admin/shop/categories` | `action=bulk` 일괄 저장, `action=delete` 삭제 |
| `GET/POST /admin/shop/categories/new?parent=코드` | 새 분류 |
| `GET/POST /admin/shop/categories/edit?id=` | 수정(하위 적용 포함) |
| `GET /admin/shop/products` | 검색(필드 화이트리스트 name, code), 분류 접두사 필터, 정렬(code, name, sort_order, active, sold_out, hit, price, list_price, point, stock; 기본 id desc), 페이지 20건, 인라인 일괄 편집 |
| `POST /admin/shop/products` | `action=bulk`(category_id, name, list_price, price, stock, active, sold_out, sort_order), `action=delete` |
| `GET/POST /admin/shop/products/new` | 새 상품. POST `action=save` 저장, `action=combine` 조합 생성(저장 안 함, 폼 값 유지) |
| `GET/POST /admin/shop/products/edit?id=` | 수정. POST 동작은 새 상품과 같다 |
| `POST /admin/shop/products/copy` | `id`, `code`로 복사. 통계값 초기화 |
| `GET/POST /admin/shop/products/types` | 유형 플래그 격자 일괄 편집 |
| `GET/POST /admin/shop/products/stock` | 상품 재고 목록·일괄 편집(stock, stock_alert, active, sold_out) |
| `GET/POST /admin/shop/products/option-stock` | 옵션 재고 목록·일괄 편집(stock, stock_alert, active) |
| `GET /admin/shop/products/search?q=&ca=&exclude=` | 관련상품 검색 JSON `{items:[{id, code, name, price, category}]}` 최대 30건 |

- 관리자 라우트는 런타임의 전역 관리자 검사·CSRF 검사를 쓴다. 비로그인은 로그인 화면으로 이동, 일반 회원은 403.
- 상품 폼 절: 분류(대표 + 추가 2개), 기본정보(코드·이름·정렬·유형·전화문의·판매가능·
  쿠폰제외·판매자메일·메모), 요약·상세 설명(코어 에디터), 상품정보고시(군 선택 → 항목 입력, 전체 군 JSON을 페이지에 넣어
  JS로 전환, JS 없으면 군 선택 후 다시 열기), 가격·포인트·과세, 재고·품절·구매수량 제한,
  선택옵션·추가옵션, 배송비, 이미지(10칸, 미리보기, 삭제 체크, 순서), 관련상품, 상세 위·아래 HTML, 여분필드.
- 폼은 JS 없이 제출·검증·오류 표시가 동작해야 한다. JS는 조합 표 편집·이미지 순서·관련상품 검색만 돕는다.
- 삭제는 이미지 파일·캐시·옵션·그룹·분류 연결·관련상품(양방향)·재고 원장을 함께 지운다. 2단계부터 주문이 있는 상품은
  삭제 대신 비활성화를 안내한다.

## 10. 자산과 테마

- `www/themes/default/youngcart.css`, `youngcart.js`, `youngcart-admin.js`는 테마 자산 규칙에 따라 해시 URL로 불러온다.
  선택 테마의 같은 경로에 파일이 있으면 그것을 쓴다.
- 템플릿 탐색 순서는 런타임 규칙대로 테마 `extensions/youngcart/` → 패키지 `templates/` → 사이트 공통 → 코어 확장 조각이다.
- 다크 모드·모바일 메뉴는 사이트 `layout`과 `admin/extension` 레이아웃을 그대로 쓴다.

## 11. 테스트

connectionProvider로 SQLite와 MySQL 양쪽에서 실행한다.

- `tests/YoungCart/SchemaTest.php` 설치 멱등성(두 번 실행), 테이블·인덱스 존재, 백업 테이블 목록 포함.
- `tests/YoungCart/CategoriesTest.php` 코드 제안(`10`→`20`, `z0` 다음은 빈칸), 코드 검증·소문자화·중복,
  depth·parent 유도, 5단계 제한, 삭제 보호 두 경우, 하위 적용, 일괄 편집 원자성.
- `tests/YoungCart/ProductsTest.php` 코드·slug 규칙과 중복 접미, 검증 오류 목록, 저장 트랜잭션(옵션 upsert로 재고 보존,
  제거 옵션 삭제, 원장 기록), version 충돌, 복사(통계 초기화·옵션·이미지), 삭제 정리, 분류 적용·전체 적용, 일괄 편집.
- `tests/YoungCart/OptionsTest.php` 조합 생성 상한, 차액·절대가 검증, 품절 판정 세 경우, 페이지용 JSON.
- `tests/YoungCart/ListingTest.php` 접두사 일치와 슬롯, 비활성 분류·상품 제외, 정렬 화이트리스트와 보조 정렬,
  페이징 경계, 검색 AND·필드·가격 범위·분류 건수, 메인 블록, 이전·다음.
- `tests/Web/YoungCartPublicTest.php` 메인·목록·유형·검색·상세·이미지 응답, 미설치 안내, 잘못된 파라미터 404,
  조회수 쿠키, 하위 경로 설치(`/cms/shop`) 링크, 이스케이프.
- `tests/Web/YoungCartAdminTest.php` 비로그인 `assertLoginRedirect`, 일반 회원 403, CSRF, 설치 POST, 분류·상품 폼 왕복,
  이미지 업로드·삭제·순서, 관련상품 검색 JSON, 재고 목록 편집.
- `tests/Extension/ManagerTest.php` `aliases: false` 동작과 오류 두 경우.
- 완료 전 전체 테스트를 실행한다.

## 12. 문서

- `docs/youngcart.md` 설치·분류·상품·옵션·이미지·설정 운영 안내와 이관 대응표.
- `modules/youngcart/README.md` 패키지 개요와 주소.
- `docs/extensions.md` `aliases` 필드.
- `AGENTS.md` 기능 지도에 쇼핑몰(영카트) 항목 추가.

## 13. 이번 범위 밖

- 장바구니·주문·결제·배송비 계산·구매수량 검증(2단계). 적립금·회원등급 할인(3단계). 쿠폰·이벤트·배너와
  `no_coupon`의 실제 효과(4단계). 가상계좌·간편결제 등 결제수단(5단계). 후기·문의·위시리스트·SNS 공유(6단계).
  엑셀 등록·개인결제(7단계). 통계·마이페이지(8단계).
- 분류별 부관리자, 본인확인·성인인증 분류 제한: 코어에 해당 권한·인증 모델이 없어 코어가 갖춘 뒤 붙인다.
- 영카트5 데이터 가져오기 도구. 대응표만 유지한다.
- 작은 쇼핑몰 폴더·주소 이동.
