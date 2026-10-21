# 쇼핑몰 진열 유형 정리: 자동 묶음 + 분류 블록 — 구현 계획

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 상품의 다섯 깃발(히트·추천·최신·인기·할인)과 진열 유형 화면을 없애고, 신상품·베스트·인기·할인을 규칙으로 계산(묶음마다 수동 분류 전환 가능)하며, 메인 화면에 관리자가 고른 분류 블록을 놓는다. 기존 깃발은 숨김 분류로 옮긴다(31판).

**Architecture:** 1) 설정·목록·스키마·공개 화면: `Settings` 의 묶음 정의가 바뀌고 `Listing` 이 규칙 SQL(또는 수동 분류)로 묶음을 만든다. 31판 이전이 깃발을 분류로 옮기고 칸을 지운다. 유형 페이지·메인·상단 메뉴가 새 묶음을 쓴다. 2) 관리자 화면: 설정의 메인 진열 섹션(기준·기간·분류 블록), 상품 폼·진열 유형 화면 정리. 3) 문서와 전체 검증.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim, PHPUnit 10, SQLite·MariaDB. 새 의존성 없음.

**Spec:** `docs/superpowers/specs/2026-09-21-shop-collections-design.md`

## Global Constraints

- 브랜치 `feat/core-commerce`(라이브 체크아웃). 커밋 `feat:`/`docs:` + `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- 묶음 키 `Settings::TYPES = ['new', 'best', 'popular', 'discount']`, `TYPE_LABELS` = 신상품·베스트·인기상품·할인상품, `TYPE_COLUMNS` 삭제, `Products::TYPES` 삭제. 규칙: 신상품 `created_at >= now - new_days*86400` 등록 내림차순; 베스트 최근 `best_days` 일 `paid|confirmed|shipped|completed` 주문의 `yc_order_items.quantity` 합 내림차순(판매 없는 상품 제외, 동률은 `p.id DESC`); 인기 `hit > 0` 을 `hit DESC, id DESC`; 할인 `list_price > price` 를 할인율 내림차순. 모두 `p.active = 1`. 정렬 메뉴가 있으면 그것이 우선.
- 설정: `auto = {new_days: 30, best_days: 30}`(1~365); `main.<키> = {use, columns, rows, image_width, image_height, source: 'auto'|'category', source_category_id: int|null}`; `main.categories = [{id, columns, rows}]` 최대 10개, 순서 유지. `main.popular` 는 옛 크기 설정을 이어받는다. 옛 `main.hit`·`main.recommend` 는 버린다.
- 수동 전환: `source = 'category'` 이고 분류가 있으면 그 분류 하위(`Categories::subtreeWhere`)의 활성 상품을 `sort_order, id DESC` 로. 분류가 없어졌으면 자동 규칙.
- 공개: `/shop/type?t=new|best|popular|discount`; `t=hit`·`recommend` 는 슬러그 `히트상품`·`추천상품` 분류가 있으면 `/shop/c/…` 301, 없으면 404; 그 밖의 값 404. 상단 메뉴는 `main.<키>.use` 인 묶음만(순서 베스트·신상품·인기상품·할인상품).
- 31판 이전(멱등, `yc_products.is_hit` 칸이 있을 때만): 깃발별로 상품이 하나라도 있으면 메뉴 숨김 최상위 분류(이름·슬러그 `히트상품`/`추천상품`/`인기상품`, 슬러그 겹치면 `-2`)를 만들어 그 상품들을 다음 slot 으로 연결(이미 있으면 건너뜀), 다섯 칸 삭제. 코어 `Db\Schema::VERSION = '31'`.
- 인라인 JS 핸들러 금지, 데이터를 JS 문자열에 끼워 넣지 않는다. 테스트는 종료 코드로 확인하고 커밋한다. 전체 스위트는 작업 3 에서 한 번.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Shop/Settings.php` | 묶음 정의, `auto`, `main` 새 모양, 저장·검증 |
| `src/Shop/Catalog/Listing.php` | `collection()`(규칙/수동), `main()`(자동 블록 + 분류 블록), `categoryBlock()` |
| `src/Shop/Catalog/Products.php` | 깃발 제거(검증·복사·일괄 적용·`setTypes` 삭제) |
| `src/Shop/Schema.php`, `src/Db/Schema.php` | 상품 정의에서 깃발 제거, 31판 이전 |
| `src/Shop/Web/ShopController.php`, `src/Shop/HomeBanner.php`, `src/Shop/Routes.php` | 유형 페이지·넘김, 배너 기본 링크, 진열 유형 라우트 삭제 |
| `templates/default/shop/{_header,index,type}.php` | 묶음 이름·링크·블록 |
| `src/Shop/Admin/{AdminController,ProductController,ProductFormController}.php` | 설정 flatten, 진열 유형 화면 삭제, 상품 폼 |
| `templates/default/shop/admin/{settings,product_form,_nav}.php`, `product_types.php`(삭제) | 관리자 화면 |
| `www/themes/default/youngcart-admin.js`, `youngcart-admin.css` | 설정의 분류 블록 줄 추가·제거 |
| 테스트 전반, `docs/shop.md`, `AGENTS.md` | |

---

### Task 1: 설정·목록·스키마·공개 화면

**Files:**
- Modify: `src/Shop/Settings.php`(12-14 상수, 18-34 기본값, 68-80 저장), `src/Shop/Catalog/Listing.php`(26-31 `type()`, 59-67 `main()`), `src/Shop/Catalog/Products.php`(21-23 `TYPES`·`APPLY_GROUPS`, 132 검증, 411~ `setTypes`, 복사·`applyScope` 의 types), `src/Shop/Schema.php`(73 상품 정의, `migrate()`), `src/Db/Schema.php`(`VERSION`), `src/Shop/Web/ShopController.php`(`case 'type'`), `src/Shop/HomeBanner.php`(~119 버튼 링크), `src/Shop/Routes.php`(73 `products/types`), `src/Shop/Admin/ProductController.php`(44-46, 72-81 `products/types` 분기 — 삭제만; 나머지 관리자 화면은 작업 2), `templates/default/shop/_header.php`, `templates/default/shop/index.php`, `templates/default/shop/type.php`
- Test: `tests/Shop/SettingsTest.php`, `tests/Shop/ListingTest.php`, `tests/Shop/HomeBannerTest.php`, `tests/Shop/ProductsTest.php`, `tests/Shop/SchemaTest.php`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php`, `tests/Browser/ShopBannerFixture.php`, `tests/Browser/ShopCartFixture.php`, `tests/Db/AligoSchemaTest.php`, `tests/Db/SchemaTest.php`

**Interfaces:**
- Produces: `Settings::TYPES`, `TYPE_LABELS`(위 제약), `Settings::all()['auto']`, `['main'][키]` 의 `source`·`source_category_id`, `['main']['categories']`; 저장 입력 `auto_new_days`, `auto_best_days`, `main_<키>_use|columns|rows|image_width|image_height|source|source_category_id`, `main_categories[]` = `[['id' => …, 'columns' => …, 'rows' => …], …]`(작업 2 의 폼이 그대로 보낸다); `Listing::collection(string $key, string $sort, string $dir, int $page): array`(옛 `type()` 대체); `Listing::main(): array` — `['new' => [...items], 'best' => …, 'categories' => [['category' => 행, 'items' => [...]], …]]`(use 가 꺼진 자동 블록은 키가 없다); `Listing::collectionWhere(string $key, array $block): array{0: string, 1: array, 2: string}` = [where, params, defaultOrder]. `Products::APPLY_GROUPS` 에서 `types` 제거.
- 관리자 화면(설정 템플릿·flatten·상품 폼)은 작업 2 가 맞춘다. 이 작업 뒤 `tests/Web/ShopAdminTest.php` 는 그 부분만 실패해도 된다(브리프에 적힌 대로).

- [ ] **Step 1: 실패하는 테스트 — 설정과 목록**

`tests/Shop/SettingsTest.php`: 기본값 단언에서 `main` 의 키를 `new`·`best`·`popular`·`discount` 로(각 `source => 'auto'`, `source_category_id => null`), `main.hit` 등 옛 키를 쓰는 곳은 새 키로, `auto` 기본 `['new_days' => 30, 'best_days' => 30]`, `main.categories` 기본 `[]`. 새 테스트:
```php
    /** 자동 묶음 기간, 묶음 기준(자동/분류), 메인 분류 블록을 저장하고 검증한다. 옛 hit·recommend 설정은 버리고 popular 의 크기는 잇는다. */
    #[DataProvider('connectionProvider')]
    public function testCollectionSettings(array $config): void
    {
        $this->setupShop($config);
        $cat = $this->category('기획전'); $sub = $this->category('여름', (int) $cat['id']);
        $form = $this->settingsInput() + ['auto_new_days' => '14', 'auto_best_days' => '90', 'main_best_source' => 'category', 'main_best_source_category_id' => (string) $cat['id'],
            'main_categories' => [['id' => (string) $sub['id'], 'columns' => '3', 'rows' => '1'], ['id' => (string) $cat['id'], 'columns' => '4', 'rows' => '2']]];
        $this->shop->settings->save($form);
        $all = $this->shop->settings->all();
        self::assertSame(['new_days' => 14, 'best_days' => 90], $all['auto']);
        self::assertSame(['category', (int) $cat['id']], [$all['main']['best']['source'], $all['main']['best']['source_category_id']]);
        self::assertSame('auto', $all['main']['new']['source']);
        self::assertSame([['id' => (int) $sub['id'], 'columns' => 3, 'rows' => 1], ['id' => (int) $cat['id'], 'columns' => 4, 'rows' => 2]], $all['main']['categories']);
        foreach ([['main_categories' => [['id' => '999999', 'columns' => '3', 'rows' => '1']]], ['main_categories' => array_fill(0, 11, ['id' => (string) $cat['id'], 'columns' => '3', 'rows' => '1'])], ['auto_new_days' => '0'], ['main_best_source' => 'category', 'main_best_source_category_id' => '999999']] as $bad) {
            try { $this->shop->settings->save($this->settingsInput() + $bad); self::fail('거절해야 한다'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        // 옛 저장값: hit·recommend 는 사라지고 popular 의 크기는 남는다.
        $legacy = $all; $legacy['main'] = ['hit' => ['use' => true, 'columns' => 2, 'rows' => 2, 'image_width' => 100, 'image_height' => 0], 'popular' => ['use' => true, 'columns' => 6, 'rows' => 1, 'image_width' => 150, 'image_height' => 0]];
        $this->app->db()->update('yc_settings', ['payload' => json_encode($legacy, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => 'settings']);
        $all = $this->shop->settings->all();
        self::assertArrayNotHasKey('hit', $all['main']);
        self::assertSame([true, 6, 'auto'], [$all['main']['popular']['use'], $all['main']['popular']['columns'], $all['main']['popular']['source']]);
        self::assertSame(['new', 'best', 'popular', 'discount', 'categories'], array_keys($all['main']));
    }
```
(`settingsInput()` 이 `main_hit_*` 같은 옛 키를 담고 있으면 새 키로 고친다. `yc_settings` 행이 없으면 insert 로 만든다 — 파일의 다른 테스트가 하는 방식.)

`tests/Shop/ListingTest.php`: `listing->type('hit', …)` 꼴을 새 묶음으로 바꾸고 `is_hit` 등 깃발 입력을 없앤다. 새 테스트:
```php
    /** 자동 묶음: 신상품은 기간, 베스트는 판매량(결제 뒤 상태의 주문만), 인기는 조회수, 할인은 시중가보다 싼 상품. 수동 전환은 분류 하위를 보인다. */
    #[DataProvider('connectionProvider')]
    public function testCollectionsFollowTheirRules(array $config): void
    {
        $this->setupShop($config);
        $cat = $this->category('의류'); $event = $this->category('기획전', null, ['menu_hidden' => '1']);
        $now = \GnuCms\Support\Clock::timestamp();
        $fresh = $this->product(['category_id' => (string) $cat['id'], 'code' => 'F', 'name' => '새것', 'price' => '100']);
        $old = $this->product(['category_id' => (string) $cat['id'], 'code' => 'O', 'name' => '옛것', 'price' => '100']);
        $this->shop->store->update('yc_products', (int) $old['id'], ['created_at' => $now - 40 * 86400]);
        $sale = $this->product(['category_id' => (string) $cat['id'], 'code' => 'S', 'name' => '할인', 'price' => '80', 'list_price' => '100']);
        $this->shop->store->update('yc_products', (int) $sale['id'], ['hit' => 5]);
        $this->shop->store->update('yc_products', (int) $fresh['id'], ['hit' => 9]);
        // 판매: fresh 2개(결제 완료), old 5개(주문 접수 — 세지 않음), sale 1개(배송 완료)
        $order = function (int $productId, int $qty, string $status) use ($now): void {
            $id = $this->shop->store->insert('yc_orders', ['number' => 'N' . $productId . $status, 'checkout_key' => str_repeat('a', 64), 'owner_key' => bin2hex(random_bytes(32)), 'user_id' => null, 'guest_password' => '', 'status' => $status,
                'buyer_name' => 'x', 'email' => 'a@b.c', 'phone' => '010', 'recipient' => 'x', 'recipient_phone' => '010', 'postcode' => '04524', 'address' => 'x', 'address_detail' => '', 'memo' => '', 'items_json' => '[]',
                'total' => 0, 'shipping_fee' => 0, 'created_at' => $now, 'updated_at' => $now] + $this->orderDefaults());
            $this->shop->store->insert('yc_order_items', ['order_id' => (int) $id, 'product_id' => $productId, 'option_id' => null, 'name' => 'x', 'option_name' => '', 'price' => 100, 'quantity' => $qty, 'created_at' => $now] + $this->orderItemDefaults());
        };
        $order((int) $fresh['id'], 2, 'paid'); $order((int) $old['id'], 5, 'pending'); $order((int) $sale['id'], 1, 'completed');
        $codes = fn (array $r): array => array_column($r['items'], 'code');
        self::assertSame(['S', 'F'], $codes($this->shop->listing->collection('new', '', '', 1)));      // 등록 내림차순, 옛것 제외
        self::assertSame(['F', 'S'], $codes($this->shop->listing->collection('best', '', '', 1)));     // 2개 > 1개, 접수 주문 제외
        self::assertSame(['F', 'S'], $codes($this->shop->listing->collection('popular', '', '', 1)));  // 9 > 5, 0 제외
        self::assertSame(['S'], $codes($this->shop->listing->collection('discount', '', '', 1)));
        self::assertSame(['F', 'O', 'S'], $codes($this->shop->listing->collection('best', 'name', 'asc', 1)) === ['F', 'O', 'S'] ? ['F', 'O', 'S'] : $codes($this->shop->listing->collection('best', 'name', 'asc', 1)), '정렬 메뉴는 규칙보다 우선하되 판매 없는 상품은 여전히 빠진다');
        // 수동 전환: 베스트를 기획전 분류로
        $this->shop->products->addToCategory([(string) $old['id']], (int) $event['id']);
        $settings = $this->shop->settings->all(); $settings['main']['best'] = ['source' => 'category', 'source_category_id' => (int) $event['id']] + $settings['main']['best'];
        $this->saveSettingsRow($settings);
        self::assertSame(['O'], $codes($this->shop->listing->collection('best', '', '', 1)));
        $this->shop->categories->delete((int) $event['id']);
        self::assertSame(['F', 'S'], $codes($this->shop->listing->collection('best', '', '', 1)), '분류가 없어지면 자동 규칙으로');
        try { $this->shop->listing->collection('hit', '', '', 1); self::fail(); } catch (DomainError $e) { self::assertSame(404, $e->status()); }
    }
```
위 테스트의 `orderDefaults()`·`orderItemDefaults()`·`saveSettingsRow()` 는 파일에 없으면 만든다: `yc_orders`·`yc_order_items` 의 NOT NULL 칸을 `src/Shop/Schema.php` 정의에서 읽어 빈값으로 채우는 헬퍼(`tests/Shop/CommerceTest.php` 나 `tests/Shop/SchemaTest.php` 의 주문 삽입 예를 참고), `saveSettingsRow(array)` 는 `yc_settings` 의 payload 를 덮는 헬퍼. 정렬 우선 단언은 결과 하나로 단순화해도 된다(`assertSame(['F', 'S'], …)` 가 아닌 이름순 `['F', 'S']` → 새것·할인 이름 기준으로 계산해 적는다). `삭제`가 상품 연결 때문에 거절되면 `removeFromCategory` 로 먼저 빼고 지운다.

`Listing::main()` 테스트(같은 파일):
```php
    #[DataProvider('connectionProvider')]
    public function testMainBlocksIncludeChosenCategories(array $config): void
    {
        $this->setupShop($config);
        $cat = $this->category('의류'); $sub = $this->category('셔츠', (int) $cat['id']);
        $a = $this->product(['category_id' => (string) $sub['id'], 'code' => 'A']); $b = $this->product(['category_id' => (string) $cat['id'], 'code' => 'B', 'price' => '50', 'list_price' => '100']);
        $settings = $this->shop->settings->all();
        $settings['main']['popular']['use'] = false; $settings['main']['best']['use'] = false;
        $settings['main']['categories'] = [['id' => (int) $cat['id'], 'columns' => 4, 'rows' => 1], ['id' => 999999, 'columns' => 4, 'rows' => 1]];
        $this->saveSettingsRow($settings);
        $blocks = $this->shop->listing->main();
        self::assertSame(['new', 'discount', 'categories'], array_keys($blocks));
        self::assertSame(['B'], array_column($blocks['discount'], 'code'));
        self::assertCount(1, $blocks['categories'], '없어진 분류는 건너뛴다');
        self::assertSame('의류', $blocks['categories'][0]['category']['name']);
        self::assertSame(['B', 'A'], array_column($blocks['categories'][0]['items'], 'code'), '하위 분류 상품까지, sort_order·id 역순');
    }
```
Run: `vendor/bin/phpunit --no-coverage tests/Shop/SettingsTest.php` → FAIL; `tests/Shop/ListingTest.php` → FAIL.

- [ ] **Step 2: 설정**

`src/Shop/Settings.php`:
```php
    public const TYPES = ['new', 'best', 'popular', 'discount'];
    public const TYPE_LABELS = ['new' => '신상품', 'best' => '베스트', 'popular' => '인기상품', 'discount' => '할인상품'];
    public const SOURCES = ['auto', 'category'];
    public const MAX_MAIN_CATEGORIES = 10;
```
`defaults()`: `$main[$type] = ['use' => $type !== 'popular', 'source' => 'auto', 'source_category_id' => null] + $block;` 뒤에 `$main['categories'] = [];`, 그리고 `'auto' => ['new_days' => 30, 'best_days' => 30],`. `all()` 이 저장값을 기본값 위에 얹을 때 `main` 은 키별로 합치되 `TYPES` 와 `categories` 에 없는 키(옛 `hit`·`recommend`)는 버린다(현재 병합 방식을 보고 `main` 만 명시적으로 걸러 낸다; 저장값의 `main.popular` 에 `source` 가 없으면 기본 `auto`). `save()`:
```php
        $settings['auto'] = ['new_days' => $int('auto_new_days', 1, 365), 'best_days' => $int('auto_best_days', 1, 365)];
        foreach (self::TYPES as $type) {
            $source = in_array($input['main_' . $type . '_source'] ?? 'auto', self::SOURCES, true) ? $input['main_' . $type . '_source'] : 'auto';
            $categoryId = $source === 'category' ? Input::optionalId($input['main_' . $type . '_source_category_id'] ?? '') : null;
            if ($source === 'category' && ($categoryId === null || $this->store->find('yc_categories', $categoryId) === null)) $errors['main_' . $type . '_source_category_id'] = '기준으로 쓸 분류를 고르세요.';
            $settings['main'][$type] = ['use' => …, 'columns' => …, 'rows' => …, 'image_width' => …, 'image_height' => …, 'source' => $source, 'source_category_id' => $categoryId];
        }
        $settings['main']['categories'] = [];
        foreach (is_array($input['main_categories'] ?? null) ? array_values($input['main_categories']) : [] as $i => $row) {
            if (!is_array($row)) continue;
            $id = Input::optionalId($row['id'] ?? '');
            if ($id === null) continue;                       // 빈 줄은 건너뛴다
            if ($this->store->find('yc_categories', $id) === null) { $errors['main_categories'] = '없는 분류가 있습니다.'; continue; }
            $settings['main']['categories'][] = ['id' => $id, 'columns' => Input::int($row['columns'] ?? '', 'main_categories', 1, 12, 4), 'rows' => Input::int($row['rows'] ?? '', 'main_categories', 1, 50, 1)];
        }
        if (count($settings['main']['categories']) > self::MAX_MAIN_CATEGORIES) $errors['main_categories'] = '메인 분류 블록은 ' . self::MAX_MAIN_CATEGORIES . '개까지입니다.';
```
(`$int()` 는 오류를 `$errors` 에 모은다 — 기존 방식대로 `save()` 끝에서 `$errors !== []` 이면 422. `Store::find` 가 필요하면 `Settings` 에 `Store` 가 이미 있다(`$this->store`). `auto_*` 가 폼에 없으면(옛 테마 폼) 이전 값을 지키는 규칙은 다른 필드와 같게 `array_key_exists` 로 처리한다.)

Run: `vendor/bin/phpunit --no-coverage tests/Shop/SettingsTest.php` → PASS.

- [ ] **Step 3: 목록**

`src/Shop/Catalog/Listing.php`: `type()` 을 지우고
```php
    /** 자동 묶음(신상품·베스트·인기·할인) 또는 수동 전환된 분류. [where, params, 기본 정렬]. */
    public function collectionWhere(string $key, array $block): array
    {
        if (!isset(Settings::TYPE_LABELS[$key])) throw \GnuCms\Error\DomainError::notFound('상품 묶음을 찾을 수 없습니다.');
        if (($block['source'] ?? 'auto') === 'category' && ($block['source_category_id'] ?? null) !== null) {
            $category = $this->store->find('yc_categories', (int) $block['source_category_id']);
            if ($category !== null) {
                [$sub, $params] = Categories::subtreeWhere($category, 'c');
                return [$this->visible() . ' AND EXISTS (SELECT 1 FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories')
                    . ' c ON c.id = pc.category_id WHERE pc.product_id = p.id AND c.active = 1 AND ' . $sub . ')', $params, self::DEFAULT_ORDER];
            }
        }
        $auto = $this->settings->all()['auto'];
        $now = Clock::timestamp();
        $paidStatuses = "('paid', 'confirmed', 'shipped', 'completed')";
        return match ($key) {
            'new' => [$this->visible() . ' AND p.created_at >= ?', [$now - (int) $auto['new_days'] * 86400], 'p.created_at DESC, p.id DESC'],
            'best' => [$this->visible() . ' AND (SELECT COALESCE(SUM(oi.quantity), 0) FROM ' . $this->store->table('yc_order_items') . ' oi JOIN ' . $this->store->table('yc_orders')
                . ' o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status IN ' . $paidStatuses . ' AND o.created_at >= ?) > 0', [$now - (int) $auto['best_days'] * 86400],
                '(SELECT COALESCE(SUM(oi.quantity), 0) FROM ' . $this->store->table('yc_order_items') . ' oi JOIN ' . $this->store->table('yc_orders') . ' o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status IN ' . $paidStatuses . ' AND o.created_at >= ' . ($now - (int) $auto['best_days'] * 86400) . ') DESC, p.id DESC'],
            'popular' => [$this->visible() . ' AND p.hit > 0', [], 'p.hit DESC, p.id DESC'],
            'discount' => [$this->visible() . ' AND p.list_price > p.price', [], '(1.0 * p.price / p.list_price) ASC, p.id DESC'],
        };
    }

    public function collection(string $key, string $sort, string $dir, int $page): array
    {
        $blocks = $this->settings->all()['main'];
        [$where, $params, $order] = $this->collectionWhere($key, $blocks[$key] ?? []);
        $size = $this->settings->block('type');
        return $this->paginate($where, $params, $sort === '' ? $order : $this->order($sort, $dir), $page, (int) $size['columns'], (int) $size['rows']);
    }

    /** 메인: use 가 켜진 자동 묶음(키별) + 고른 분류 블록(`categories`). */
    public function main(): array
    {
        $settings = $this->settings->all();
        $blocks = [];
        foreach (Settings::TYPES as $key) {
            $block = $settings['main'][$key];
            if (!$block['use']) continue;
            [$where, $params, $order] = $this->collectionWhere($key, $block);
            $blocks[$key] = $this->paginate($where, $params, $order, 1, (int) $block['columns'], (int) $block['rows'])['items'];
        }
        $blocks['categories'] = [];
        foreach ($settings['main']['categories'] as $entry) {
            $category = $this->store->find('yc_categories', (int) $entry['id']);
            if ($category === null || (int) $category['active'] !== 1) continue;
            [$sub, $params] = Categories::subtreeWhere($category, 'c');
            $where = $this->visible() . ' AND EXISTS (SELECT 1 FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories') . ' c ON c.id = pc.category_id WHERE pc.product_id = p.id AND c.active = 1 AND ' . $sub . ')';
            $blocks['categories'][] = ['category' => $category, 'items' => $this->paginate($where, $params, self::DEFAULT_ORDER, 1, (int) $entry['columns'], (int) $entry['rows'])['items']];
        }
        return $blocks;
    }
```
(`use GnuCms\Support\Clock;` 추가. 베스트 정렬식에 숫자를 직접 넣는 대신 `paginate()` 가 정렬 매개변수를 받을 수 없으면 정렬식의 기준 시각을 SQL 안에 정수로 넣는 위 방식으로 둔다 — `(int)` 캐스트라 주입 여지가 없다. `order()` 가 `$sort === ''` 를 기본 정렬로 돌려주면 위 삼항은 그대로 두어도 된다.) 다른 곳에서 `Listing::type()` 을 부르는 곳(`ShopController`)은 Step 5 에서 바꾼다. `Images.php:88` 의 `'main' => max(array_column($all['main'], 'image_width') …)` 는 `categories` 항목 때문에 깨지므로 `array_column(array_intersect_key($all['main'], array_flip(Settings::TYPES)), 'image_width')` 로.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/ListingTest.php` → PASS.

- [ ] **Step 4: 실패하는 테스트 — 스키마·상품**

`tests/Shop/SchemaTest.php`:
```php
    /** 31판: 깃발 다섯 개를 없앤다. 히트·추천·인기가 켜진 상품은 메뉴 숨김 분류로 옮기고, 상품이 없는 깃발은 분류를 만들지 않는다. 두 번 돌려도 같다. */
    #[DataProvider('connectionProvider')]
    public function testMigrateMovesDisplayFlagsIntoHiddenCategories(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        foreach (['is_hit', 'is_recommended', 'is_new', 'is_popular', 'is_discount'] as $c) $db->execute('ALTER TABLE ' . $db->table('yc_products') . ' ADD COLUMN ' . $c . ' SMALLINT NOT NULL DEFAULT 0');
        $cat = $this->category('의류');
        $a = $this->product(['category_id' => (string) $cat['id'], 'code' => 'A']); $b = $this->product(['category_id' => (string) $cat['id'], 'code' => 'B']);
        $db->execute('UPDATE ' . $db->table('yc_products') . ' SET is_hit = 1, is_new = 1 WHERE id = ?', [(int) $a['id']]);
        $db->execute('UPDATE ' . $db->table('yc_products') . ' SET is_hit = 1, is_popular = 1 WHERE id = ?', [(int) $b['id']]);
        $this->category('히트상품'); // 슬러그가 이미 쓰이는 경우 → 히트상품-2
        Schema::migrate($db); Schema::migrate($db);
        self::assertArrayNotHasKey('is_hit', $this->shop->products->get((int) $a['id']));
        $hit = $this->shop->categories->bySlug('히트상품-2'); $popular = $this->shop->categories->bySlug('인기상품');
        self::assertNotNull($hit); self::assertSame(1, (int) $hit['menu_hidden']); self::assertNull($hit['parent_id']);
        self::assertNull($this->shop->categories->bySlug('추천상품'), '켜진 상품이 없는 깃발은 분류를 만들지 않는다');
        self::assertSame([(int) $cat['id'], (int) $hit['id']], array_map(static fn (array $c): int => (int) $c['id'], $this->shop->products->get((int) $a['id'])['categories']));
        self::assertSame([(int) $cat['id'], (int) $hit['id'], (int) $popular['id']], array_map(static fn (array $c): int => (int) $c['id'], $this->shop->products->get((int) $b['id'])['categories']));
    }
```
`tests/Db/AligoSchemaTest.php`·`tests/Db/SchemaTest.php`: `'30'`·`Thirty` → `'31'`·`ThirtyOne`. `tests/Shop/ProductsTest.php`: `is_hit` 등 입력·단언과 `apply_fields` 의 `types` 를 없앤다(`testImagesCopyDeleteBulkTypesStockAndApply` 안의 `setTypes` 부분 삭제; 복사 뒤 깃발 단언 삭제). `tests/Shop/HomeBannerTest.php`: `is_hit => '1'` 로 블록을 채우던 곳은 `list_price > price`(할인) 나 `created_at`(신상품)으로 바꾼다 — 배너 자동 표시는 이제 신상품 블록의 첫 상품이 된다.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/SchemaTest.php` → FAIL.

- [ ] **Step 5: 스키마·상품·공개 화면**

- `src/Shop/Schema.php`: 상품 정의(73행)에서 `is_hit SMALLINT …, is_recommended …, is_new …, is_popular …, is_discount …` 다섯 칸을 지운다. `migrate()` 의 `migrateCategoryTree()`·`CATEGORY_COLUMNS` 루프 뒤에 `self::migrateDisplayFlags($db);`:
```php
    /** 31판: 진열 깃발을 없앤다. 히트·추천·인기 깃발이 켜진 상품은 메뉴 숨김 분류로 옮겨 보존하고, 신상품·할인은 규칙이 대신한다. is_hit 칸이 없으면 끝난 것이다. */
    private static function migrateDisplayFlags(Connection $db): void
    {
        if (!self::columnExists($db, 'yc_products', 'is_hit')) return;
        $products = $db->table('yc_products'); $categories = $db->table('yc_categories'); $links = $db->table('yc_product_categories');
        $db->transaction(function () use ($db, $products, $categories, $links): void {
            $now = time();
            foreach (['is_hit' => '히트상품', 'is_recommended' => '추천상품', 'is_popular' => '인기상품'] as $flag => $name) {
                $ids = array_map('intval', array_column($db->select('SELECT id FROM ' . $products . ' WHERE ' . $flag . ' = 1 ORDER BY id', []), 'id'));
                if ($ids === []) continue;
                $slug = $name; for ($n = 2; $db->selectOne('SELECT id FROM ' . $categories . ' WHERE slug = ?', [$slug]) !== null; $n++) $slug = $name . '-' . $n;
                $categoryId = (int) $db->insert('yc_categories', ['parent_id' => null, 'depth' => 1, 'name' => $name, 'slug' => $slug, 'path' => '', 'legacy_code' => null, 'sort_order' => 0, 'active' => 1, 'no_coupon' => 0, 'menu_hidden' => 1,
                    'head_html' => '', 'tail_html' => '', 'list_columns' => 4, 'list_rows' => 5, 'image_width' => 200, 'image_height' => 0, 'extra' => '[]', 'created_at' => $now, 'updated_at' => $now]);
                $db->update('yc_categories', ['path' => '/' . $categoryId . '/'], 'id = :id', ['id' => $categoryId]);
                foreach ($ids as $productId) {
                    if ($db->selectOne('SELECT 1 AS x FROM ' . $links . ' WHERE product_id = ? AND category_id = ?', [$productId, $categoryId]) !== null) continue;
                    $slot = (int) $db->selectOne('SELECT COALESCE(MAX(slot), 0) AS s FROM ' . $links . ' WHERE product_id = ?', [$productId])['s'] + 1;
                    $db->insert('yc_product_categories', ['product_id' => $productId, 'category_id' => $categoryId, 'slot' => max(2, $slot)]);
                }
            }
        });
        foreach (['is_hit', 'is_recommended', 'is_new', 'is_popular', 'is_discount'] as $column) self::dropColumn($db, 'yc_products', $column);
    }
```
(`columnExists()` 가 없으면 `addColumn()` 이 쓰는 SELECT 검사와 같은 방식으로 만든다. `$db->update()` 시그니처는 `update(table, data, where, params)` — `Connection::update()` 를 확인해 맞춘다. SQLite 는 DDL 을 트랜잭션 밖에서 실행하므로 칸 삭제는 트랜잭션 뒤에 둔다 — MySQL 도 마찬가지.)
- `src/Db/Schema.php`: `VERSION = '31'`, `migrateShop()` 주석에 `31판: 진열 깃발 → 자동 묶음·숨김 분류.` 잇기.
- `src/Shop/Catalog/Products.php`: `TYPES` 상수와 `APPLY_GROUPS` 의 `'types' => self::TYPES` 삭제, 132행의 `...self::TYPES` 제거, `setTypes()` 삭제, 복사·`applyScope` 에서 깃발 항목 제거(`grep -n "TYPES" src/Shop/Catalog/Products.php` 가 0건).
- `src/Shop/Routes.php:73` 의 `'products/types'` 제거; `src/Shop/Admin/ProductController.php` 의 `case 'products/types'` 두 곳과 `$data['types'] = Products::TYPES;` 제거(화면 파일 삭제는 작업 2).
- `src/Shop/Web/ShopController.php` `case 'type'`:
```php
            case 'type':
                $type = $query['t'] ?? '';
                // 옛 유형: popular 는 그대로, hit·recommend 는 이전 때 만든 숨김 분류가 있으면 그리로.
                if (in_array($type, ['hit', 'recommend'], true)) {
                    $moved = $this->service->categories->bySlug($type === 'hit' ? '히트상품' : '추천상품');
                    if ($moved === null) throw DomainError::notFound('상품 묶음을 찾을 수 없습니다.');
                    return $response->withStatus(301)->withHeader('Location', $url . '/c/' . rawurlencode($moved['slug']));
                }
                if (!isset(Settings::TYPE_LABELS[$type])) throw DomainError::notFound('상품 묶음을 찾을 수 없습니다.');
                $data['type'] = $type;
                $data['list'] = $this->service->listing->collection($type, $sort, $dir, $pageNo);
                return $view->render($response, 'type', $data);
```
- `src/Shop/HomeBanner.php` `view()`: `$blocks` 순회를 `foreach ($blocks as $key => $rows) { if ($key === 'categories') { foreach ($rows as $entry) if ($entry['items'] !== []) { $product = $entry['items'][0]; $buttonUrl = $url . '/c/' . rawurlencode($entry['category']['slug']); break 2; } continue; } if ($rows !== []) { $product = $rows[0]; $type = $key; break; } }` 꼴로 — 자동 블록이 먼저, 없으면 분류 블록. `randomProduct($blocks)` 도 `categories` 항목을 건너뛰거나 그 items 를 합친다(파일을 보고 맞춘다).
- 템플릿: `_header.php` 의 링크 레이블 배열을 `['best' => '베스트', 'new' => '신상품', 'popular' => '인기상품', 'discount' => '할인상품']` 순서로 돌린다(`Settings::TYPES` 순서가 아니라 이 순서; `foreach (['best','new','popular','discount'] as $key)`). `index.php`: `$captions` 를 새 키 넷으로(`best` => ['지금 가장 많이 팔리는 상품', …], `new`, `popular` => ['많이 본 상품', …], `discount`), 블록 루프에서 `$type === 'categories'` 는 건너뛰고, 자동 블록 뒤에 분류 블록 루프를 더한다(제목 = 분류 이름, 더 보기 = `/c/슬러그`, 같은 `_grid` 삽입에 `'size' => 'list'`, 이미지 크기는 그 분류 것). `type.php` 는 `$type_labels[$type]` 그대로 동작한다.
- `tests/Web/ShopPublicTest.php`·`ShopCommerceTest.php`·`tests/Browser/Shop*Fixture.php`: `is_hit => '1'` 등 입력 삭제; `type?t=hit` 단언을 `t=best`(판매 없으면 빈 목록이므로 `t=new` 로 바꾸는 편이 낫다)로; 새 단언: `t=hit` → 404, 이전 분류를 만든 뒤 → 301 `/shop/c/%ED%9E%88%ED%8A%B8%EC%83%81%ED%92%88`; 상단 메뉴에 `type?t=best` 링크와 `t=popular` 없음(기본 use false); 메인에 분류 블록(설정 `main.categories` 를 심고 분류 이름 제목과 `/shop/c/` 더 보기 링크).

Run(각각): `tests/Shop`, `tests/Db`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php` → PASS. `tests/Web/ShopAdminTest.php` 는 설정·상품 폼·진열 유형 화면 관련 실패만 남아야 한다(작업 2).

- [ ] **Step 6: 커밋**

```bash
git add src/Shop src/Db/Schema.php templates/default/shop/_header.php templates/default/shop/index.php templates/default/shop/type.php tests/Shop tests/Db tests/Web/ShopPublicTest.php tests/Web/ShopCommerceTest.php tests/Browser
git commit -m "feat: compute shop collections from rules and show chosen category blocks on the home page

Schema 31 drops the five display flags; products flagged 히트·추천·인기 move
into hidden categories. 신상품·베스트·인기·할인 come from created_at, paid
order quantities, hit counts and list_price > price, each switchable to a
category, and the home page can feature any categories.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 관리자 화면 — 설정의 메인 진열, 상품 폼, 진열 유형 화면 삭제

**Files:**
- Modify: `templates/default/shop/admin/settings.php`(45-52 메인 블록 섹션), `src/Shop/Admin/AdminController.php`(`flatten()`·설정 저장), `src/Shop/Admin/ProductFormController.php`(66, 106-107), `templates/default/shop/admin/product_form.php`(유형 체크 줄), `templates/default/shop/admin/_nav.php`(12 하위 탭), `www/themes/default/youngcart-admin.js`, `www/themes/default/youngcart-admin.css`
- Delete: `templates/default/shop/admin/product_types.php`
- Test: `tests/Web/ShopAdminTest.php`

**Interfaces:**
- Consumes 작업 1 의 설정 입력 이름(`auto_new_days`, `auto_best_days`, `main_<키>_source`, `main_<키>_source_category_id`, `main_categories[i][id|columns|rows]`), `Settings::TYPES`·`TYPE_LABELS`·`SOURCES`·`MAX_MAIN_CATEGORIES`, `Categories::optionDetails()`.
- Produces: 설정 화면 마크업 — 자동 블록마다 `main_<키>_use`·크기·`main_<키>_source`(select `auto`/`category`)·`main_<키>_source_category_id`(select, 분류 optionDetails); 기간 `auto_new_days`·`auto_best_days`; 분류 블록 목록 `<div data-yc-main-categories>` 의 줄마다 `main_categories[i][id]`(select)·`[columns]`·`[rows]` + 제거, `<template data-yc-main-category-row>` + `data-yc-add-main-category` 단추(줄 번호는 JS 가 붙여 넣을 때 `i` 를 현재 줄 수로 바꾼다). `flatten()` 은 `main_categories` 를 `[['id' => '..', 'columns' => '..', 'rows' => '..'], …]` 로, `auto_*`·`source*` 를 문자열로 낸다.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/ShopAdminTest.php`: 
- 설정 화면 GET 에 `name="auto_new_days"`, `name="main_best_source"`, `data-yc-add-main-category`, `<template data-yc-main-category-row>` 가 있고 `main_hit_use` 가 없다. 설정 POST(`settingsForm()` + `['auto_best_days' => '60', 'main_best_source' => 'category', 'main_best_source_category_id' => <id>, 'main_categories' => [['id' => <id>, 'columns' => '3', 'rows' => '1']]]`) → 303, `settings->all()` 반영, 다시 연 화면에 `value="<id>" selected` 와 `name="main_categories[0][id]"`.
- 상품 폼: `name="is_hit"` 없음, `value="types"` 칩 없음. `GET /admin/shop/products/types` → 404. 상품 하위 탭에 `진열 유형` 없음.
- `settingsForm()`·`productForm()` 헬퍼에서 옛 키(`main_hit_*`, `is_hit`)를 지운다.

Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php` → FAIL.

- [ ] **Step 2: 구현**

- `AdminController::flatten()`: `main` 순회에서 `categories` 는 `$flat['main_categories'] = array_map(fn ($e) => ['id' => (string) $e['id'], 'columns' => (string) $e['columns'], 'rows' => (string) $e['rows']], $settings['main']['categories'])`, 나머지 키는 지금처럼(`source_category_id` 가 null 이면 ''), `auto_new_days`·`auto_best_days` 추가. 설정 화면 데이터에 `$data['categories'] = $this->service->categories->optionDetails();`.
- `settings.php` 메인 블록 섹션: 소제목 "자동 묶음" 아래 기간 두 칸(`$num('auto_new_days', '신상품 기간(일)', 1, 365)`, `$num('auto_best_days', '베스트 집계 기간(일)', 1, 365)`), 그 아래 `Settings::TYPES` 순으로 블록 카드(사용 체크, 기준 select `auto`=자동 규칙/`category`=분류 선택, 분류 select(`category` 일 때만 의미), 열·행·이미지 크기 — 지금 마크업 유지). 그 아래 "메인 분류 블록" 소제목: `<div class="yc-main-category-rows" data-yc-main-categories>` 안에 저장된 항목마다 줄(분류 select `main_categories[i][id]`, `[columns]` number, `[rows]` number, 제거 단추 `data-yc-remove-main-category`), `<template data-yc-main-category-row>` 에 빈 줄(`main_categories[__i__][…]`), `<button type="button" data-yc-add-main-category>블록 추가</button>`, 안내문 "메뉴 숨김 분류도 고를 수 있어 기획전을 메인에 올릴 때 씁니다. 최대 10개."
- `youngcart-admin.js`: 설정 폼에도 동작하도록 문서 수준 위임: `document.addEventListener('click', …)` 에서 `[data-yc-add-main-category]` → 템플릿 복제, `__i__` 를 현재 줄 수로 치환(`innerHTML` 을 문자열로 다루지 말고 복제한 요소의 `name` 속성을 바꾼다), 추가; `[data-yc-remove-main-category]` → 줄 제거. 상품 폼의 `data-yc-add-category` 처리와 같은 방식.
- CSS: `.yc-admin-page .yc-main-category-row{display:grid;grid-template-columns:minmax(0,1fr) 6rem 6rem auto;gap:.5rem;align-items:center}` 와 목록 간격.
- `ProductFormController`: 66행 `foreach (Products::TYPES …)` 삭제, 106행 `$data['types']` 삭제, 107행 `apply_fields` 에서 `'types' => '유형'` 삭제. `product_form.php`: 유형 체크 줄(`foreach ($types …)` + `$apply('types', '유형')`) 삭제. `_nav.php`: 하위 탭에서 `products/types` 항목 삭제. `product_types.php` 삭제.

Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php` → PASS(종료 코드). 화면 확인: 쇼핑몰 설정의 메인 진열 섹션과 상품 폼을 렌더링해 본다.

- [ ] **Step 3: 커밋**

```bash
git add src/Shop/Admin templates/default/shop/admin www/themes/default/youngcart-admin.js www/themes/default/youngcart-admin.css tests/Web/ShopAdminTest.php
git rm -q templates/default/shop/admin/product_types.php
git commit -m "feat: configure shop collections and home category blocks in the admin, drop the display type screen

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: 문서, 전체 스위트, MySQL

**Files:**
- Modify: `docs/shop.md`(14행 주소 표의 유형 주소, "진열 유형" 절 → "자동 묶음과 메인 블록", 상품 절의 유형 체크 문장, 186행 배너 자동 표시 설명, 296행 대응표 `it_type1~5`), `AGENTS.md:34`

- [ ] **Step 1: 문서**

- 주소 표: `유형 `/shop/type?t=new|best|popular|discount`(옛 `hit`·`recommend` 는 옮겨진 분류로 넘긴다)`.
- 새 절:
```markdown
## 자동 묶음과 메인 블록

진열 유형 깃발은 없다. 네 묶음은 규칙으로 정해진다: **신상품**은 최근 N일(기본 30) 안에 등록한 상품,
**베스트**는 최근 N일(기본 30) 동안 결제 완료 이후 상태의 주문에서 팔린 수량이 많은 상품, **인기상품**은
누적 조회수가 많은 상품, **할인상품**은 시중가보다 판매가가 낮은 상품이다. 쇼핑몰 설정 → 메인 진열에서
기간과 각 묶음의 사용 여부·진열 크기를 정하고, 묶음마다 **기준**을 "분류 선택"으로 바꾸면 그 분류(하위 포함,
메뉴 숨김 분류 가능)의 상품이 대신 나온다 — 손으로 고른 베스트가 필요할 때 쓴다.

메인 화면에는 자동 묶음 아래에 **메인 분류 블록**을 최대 10개 놓을 수 있다. 각 블록은 그 분류와 하위 분류의
상품을 분류의 이미지 크기로 보이고 제목이 분류 페이지로 이어진다. 기획전은 메뉴 숨김 분류를 만들어 상품을
넣고 여기에 올리면 된다.

31판으로 올라갈 때 히트·추천·인기가 켜져 있던 상품은 같은 이름의 메뉴 숨김 분류(`히트상품`, `추천상품`,
`인기상품`)로 옮겨진다. 신상품·할인은 규칙이 대신하므로 옮기지 않는다.
```
- 상품 절에서 "히트·추천·최신·인기·할인" 체크 설명 삭제; 배너 자동 표시: "사용 중인 자동 묶음(베스트·신상품·인기·할인 순서)과 분류 블록을 차례로 보고 첫 상품…"; 대응표 `| `it_type1~5` | 없음 — 히트·추천·인기는 이전 때 숨김 분류로, 최신·할인은 규칙 |`. AGENTS.md 34행의 "유형별 목록" → "자동 묶음(신상품·베스트·인기·할인)".

- [ ] **Step 2: 전체 스위트, MySQL, 커밋**

```bash
vendor/bin/phpunit --no-coverage; echo exit=$?
DB=gnucms_col_$(date +%s); mysql -uroot -e "CREATE DATABASE $DB CHARACTER SET utf8mb4;"
for p in tests/Db tests/Shop/SchemaTest.php tests/Shop/ListingTest.php tests/Shop/SettingsTest.php tests/Web/ShopPublicTest.php; do TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=$DB;charset=utf8mb4" TEST_MYSQL_USER=root vendor/bin/phpunit --no-coverage $p | tail -1; done
mysql -uroot -e "DROP DATABASE $DB;"
git add docs/shop.md AGENTS.md
git commit -m "docs: describe the shop's automatic collections and home category blocks

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```
Expected: 전체 OK, MySQL 다섯 스위트 OK(베스트의 서브쿼리 정렬과 할인율 나눗셈이 MariaDB 에서도 도는지 여기서 본다).

---

## 자체 검토

- **스펙 대조.** §2 규칙·수동 전환 → 작업 1 Step 1-3. §3 메인 → Step 3·5(템플릿). §4 주소·메뉴 → Step 5. §5 상품·관리자 → Step 5(상품 서비스·라우트) + 작업 2. §6 이전 → Step 4-5. §7 테스트 → 각 작업. §8 문서 → 작업 3. §9 → 이전 코드와 MySQL 검증.
- **자리표시자.** 작업 1 Step 1 의 주문 삽입 헬퍼는 기존 테스트의 삽입 예를 참고하라고 적었고, 정렬 우선 단언은 단순화 지시가 있다. Step 5 의 `HomeBanner::randomProduct` 는 파일을 보고 맞춘다.
- **이름 일치.** `Settings::TYPES/TYPE_LABELS/SOURCES/MAX_MAIN_CATEGORIES`, `auto.new_days/best_days`, `main.<키>.source/source_category_id`, `main.categories[{id,columns,rows}]`, 입력 `auto_*`, `main_<키>_source*`, `main_categories[i][…]`, `Listing::collection/collectionWhere/main`, 템플릿 속성 `data-yc-main-categories`, `data-yc-main-category-row`, `data-yc-add-main-category`, `data-yc-remove-main-category` — 작업 간 동일하다.
