# 쇼핑몰 분류: 무한 단계 트리와 슬러그 주소 — 구현 계획

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 2자 코드 계층(최대 5단계)을 부모 id 트리(`path` 열, 10단계 상한)로 바꾸고, 공개 주소를 `/shop/c/{slug}` 로 옮기며, 옛 주소와 기존 데이터를 살린다.

**Architecture:** 1) 스키마 29판이 `yc_categories` 를 `slug`·`path`·`legacy_code` 모양으로 바꾸고(SQLite 는 표 재생성, MySQL 은 ALTER) 기존 행을 채운다. 분류 서비스는 코드 대신 `parent_id`·`path` 로 동작하고, 하위 전체를 거는 SQL 은 `Categories::subtreeWhere()` 한 곳에서 나온다. 공개 화면은 슬러그 주소를 쓰고 옛 주소는 301 로 넘긴다. 2) 관리자 분류 화면에서 코드 칸이 사라지고 상위 분류 선택(이동)과 슬러그가 들어간다. 3) 문서와 전체 검증.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim, PHPUnit 10, SQLite(기본)·MariaDB(선택). 새 의존성 금지.

**Spec:** `docs/superpowers/specs/2026-09-21-shop-category-tree-design.md`

## Global Constraints

- 브랜치 `feat/core-commerce`(라이브 체크아웃). `main`에 직접 커밋하지 않는다.
- 표 이름·상품 연결 표(`yc_product_categories`, id 기준)는 그대로. `code` 칸은 없어지고 `legacy_code` 로만 남는다.
- 슬러그 규칙은 상품과 같다: `Input::slug()`(공백과 `/ ? # % " ' \` < > \\` 를 `-` 로, 연속 `-` 하나로, 앞뒤 `-` 제거, 190자). 한글·대소문자는 그대로. 전체에서 하나.
- `path` 는 `/1/5/12/` 처럼 조상부터 자기까지의 id 를 `/` 로 감싼 값. `depth` = id 개수. 상한 `Categories::MAX_DEPTH = 10`.
- 공개 분류 주소 `/shop/c/{slug}`(라우트 이름 `shop.category`). 옛 `/shop/list?ca=값` 은 `legacy_code` → 슬러그 순으로 찾아 301, 없으면 404. 링크는 `rawurlencode(slug)`.
- 하위 전체 조건은 `Categories::subtreeWhere($category, $alias)` 만 쓴다(`c.code LIKE` 를 남기지 않는다).
- 모든 새·수정 코드에 한국어 주석은 기존 파일과 같은 말투(짧은 평서문).
- 테스트는 `vendor/bin/phpunit --no-coverage <경로 하나>`. 전체 스위트는 작업 3 에서 한 번. 커밋은 `feat:`/`docs:` + `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Shop/Schema.php` | 새 `yc_categories` 정의, 29판 이전(`migrateCategoryTree`·`fillCategoryTree`), 인덱스 3개 추가 |
| `src/Db/Schema.php` | `VERSION = '29'` |
| `src/Shop/Catalog/Categories.php` | 트리 서비스(재작성): `save`(생성·이동), `bySlug`, `byLegacyCode`, `find`, `tree`, `options`, `optionsExcluding`, `ancestors`, `children`, `subtreeWhere` |
| `src/Shop/Catalog/Listing.php`, `src/Shop/Catalog/Products.php` | 하위 조건을 `subtreeWhere` 로; 검색 facet 은 슬러그; 관리자 필터는 분류 id |
| `src/Shop/Web/ShopController.php`, `src/Shop/Routes.php`, `src/Shop/HomeBanner.php` | `category` 페이지, 옛 `list` 넘김, 메뉴·경로·링크 |
| `templates/default/shop/{_header,_breadcrumb,index,list,search}.php` | 슬러그 링크 |
| `src/Shop/Admin/CategoryController.php`, `src/Shop/Admin/ProductController.php` | 상위 분류 선택·슬러그, 상품 필터 id |
| `templates/default/shop/admin/{categories,category_form,_filters}.php` | 코드 칸 제거, 상위 분류·슬러그 |
| `tests/Shop/ShopTestCase.php`, `tests/Shop/*`, `tests/Web/Shop*`, `tests/Db/*` | 테스트 |
| `docs/shop.md`, `AGENTS.md` | 문서 |

---

### Task 1: 스키마 29판, 분류 트리 서비스, 공개 화면

**Files:**
- Modify: `src/Shop/Schema.php`, `src/Db/Schema.php`, `src/Shop/Catalog/Categories.php`(재작성), `src/Shop/Catalog/Listing.php`, `src/Shop/Catalog/Products.php`, `src/Shop/Web/ShopController.php`, `src/Shop/Routes.php`, `src/Shop/HomeBanner.php`, `templates/default/shop/_header.php`, `templates/default/shop/_breadcrumb.php`, `templates/default/shop/index.php`, `templates/default/shop/list.php`, `templates/default/shop/search.php`
- Test: `tests/Shop/ShopTestCase.php`, `tests/Shop/SchemaTest.php`, `tests/Shop/CategoriesTest.php`, `tests/Shop/ListingTest.php`, `tests/Shop/ProductsTest.php`, `tests/Shop/HomeBannerTest.php`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php`, `tests/Db/AligoSchemaTest.php`, `tests/Db/SchemaTest.php`

**Interfaces:**
- Produces (작업 2 가 쓴다): `Categories::MAX_DEPTH = 10`; `save(array $input, ?int $id = null): int` — 입력 `name`, `slug`(선택), `parent_id`(''/없음 = 최상위), 나머지는 지금과 같음, `code` 는 무시; `find(int): ?array`; `bySlug(string): ?array`; `byLegacyCode(string): ?array`; `tree(): array`(각 행 `product_count`, 부모 우선, 형제 `sort_order, name`); `options(): array`(`id => '의류 > 셔츠'`); `optionsExcluding(int $id): array`(자기·하위 제외); `ancestors(array $category): array`; `children(?int $parentId, bool $activeOnly): array`; `static subtreeWhere(array $category, string $alias = 'c'): array{0: string, 1: array}`; 행에는 `slug`, `path`, `legacy_code`(NULL 가능) 가 있고 `code` 는 없다. `Listing::search(string $q, ?array $category, int $min, int $max, string $sort, string $dir, int $page)` — facet 은 `['slug','name','count']`. `Products::list()`·`Products::search()` 의 `ca` 는 분류 id(문자열). `ShopController::handle(string $page, $request, $response, array $args = [])`, 페이지 `category`. 테스트 헬퍼 `ShopTestCase::category(string $name = '의류', ?int $parentId = null, array $extra = []): array`.
- 이 작업 뒤 `tests/Web/ShopAdminTest.php` 의 분류 화면 테스트와 관리자 분류 화면은 작업 2 가 고칠 때까지 깨져 있다(코드 칸·`suggestCode`). 그 외 스위트는 모두 통과해야 한다.

- [ ] **Step 1: 실패하는 테스트 — 스키마**

`tests/Shop/SchemaTest.php`: `INDEXES` 에 `'yc_cat_path' => 'yc_categories', 'yc_cat_slug' => 'yc_categories', 'yc_cat_legacy_code' => 'yc_categories'` 를 더하고 `count(self::INDEXES)` 단언을 21 → 24 로. `testMigrateDropsTheShortLivedImageKeyColumnFromCategories` 를 지우고 다음을 넣는다.

```php
    /** 29판: 2자 코드 계층을 부모 id 트리로. 옛 표를 새 모양으로 바꾸고 slug·path·legacy_code 를 채운다. 두 번 돌려도 같다. */
    #[DataProvider('connectionProvider')]
    public function testMigrateTurnsCodedCategoriesIntoATree(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        // 28판 모양(code 있음, slug·path 없음)의 표를 손으로 만든다.
        foreach (['yc_cat_parent', 'yc_cat_order', 'yc_cat_path', 'yc_cat_slug', 'yc_cat_legacy_code'] as $index) {
            $db->execute('DROP INDEX ' . ($db->dialect()->name() === 'mysql' ? $db->index($index) . ' ON ' . $db->table('yc_categories') : $db->index($index)));
        }
        $db->execute('DROP TABLE ' . $db->table('yc_categories'));
        $bin = $db->dialect()->name() === 'mysql' ? ' COLLATE utf8mb4_bin' : '';
        $db->execute('CREATE TABLE ' . $db->table('yc_categories') . ' (' . strtr('id {AUTO_PK}, code VARCHAR(10)' . $bin . ' NOT NULL UNIQUE, parent_id BIGINT NULL, depth SMALLINT NOT NULL,
            name VARCHAR(100) NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, active SMALLINT NOT NULL DEFAULT 1, no_coupon SMALLINT NOT NULL DEFAULT 0,
            head_html {TEXT} NOT NULL, tail_html {TEXT} NOT NULL, list_columns SMALLINT NOT NULL, list_rows SMALLINT NOT NULL, image_width INTEGER NOT NULL,
            image_height INTEGER NOT NULL, extra {TEXT} NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL', $db->dialect()->typeMap()) . ')' . $db->dialect()->tableSuffix());
        $common = ['sort_order' => 0, 'active' => 1, 'no_coupon' => 0, 'head_html' => '', 'tail_html' => '', 'list_columns' => 3, 'list_rows' => 5, 'image_width' => 200, 'image_height' => 0, 'extra' => '[]', 'created_at' => 1, 'updated_at' => 1];
        $top = $db->insert('yc_categories', ['code' => '10', 'parent_id' => null, 'depth' => 1, 'name' => '의류'] + $common);
        $child = $db->insert('yc_categories', ['code' => '1010', 'parent_id' => $top, 'depth' => 2, 'name' => '셔츠'] + $common);
        $grand = $db->insert('yc_categories', ['code' => '101010', 'parent_id' => $child, 'depth' => 3, 'name' => '반팔 셔츠'] + $common);
        $dup = $db->insert('yc_categories', ['code' => '20', 'parent_id' => null, 'depth' => 1, 'name' => '의류'] + $common);
        Schema::migrate($db);
        Schema::migrate($db);
        $rows = [];
        foreach ($db->select('SELECT * FROM ' . $db->table('yc_categories') . ' ORDER BY id') as $row) $rows[(int) $row['id']] = $row;
        self::assertArrayNotHasKey('code', $rows[(int) $top]);
        self::assertSame(['의류', '/' . $top . '/', 1, '10'], [$rows[(int) $top]['slug'], $rows[(int) $top]['path'], (int) $rows[(int) $top]['depth'], $rows[(int) $top]['legacy_code']]);
        self::assertSame(['셔츠', '/' . $top . '/' . $child . '/', 2, '1010'], [$rows[(int) $child]['slug'], $rows[(int) $child]['path'], (int) $rows[(int) $child]['depth'], $rows[(int) $child]['legacy_code']]);
        self::assertSame(['반팔-셔츠', '/' . $top . '/' . $child . '/' . $grand . '/', 3], [$rows[(int) $grand]['slug'], $rows[(int) $grand]['path'], (int) $rows[(int) $grand]['depth']]);
        self::assertSame('의류-2', $rows[(int) $dup]['slug']);
        $this->assertIndexesExist();
        self::assertNull($this->shop->categories->byLegacyCode('99'));
        self::assertSame((int) $child, (int) $this->shop->categories->byLegacyCode('1010')['id']);
    }
```
(`$db->insert()` 는 새 id 를 문자열로 돌려준다.) `tests/Db/AligoSchemaTest.php`: `testSchemaVersionIsTwentyEight` → `testSchemaVersionIsTwentyNine`, `'28'` → `'29'`. `tests/Db/SchemaTest.php`: `'28'` 두 곳(`explode('.', $schema->stamp())[0]`, `'/^28\.[0-9a-f]{12}$/D'`) → `'29'`.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/SchemaTest.php`
Expected: FAIL — `slug` 칸 없음, 인덱스 없음.

- [ ] **Step 2: 스키마**

`src/Shop/Schema.php`:
- `$definitions` 의 `'yc_categories' => '…'` 항목을 `self::categoriesDefinition($bin)` 호출로 바꾸고 메서드를 만든다(`$bin` 은 `migrate()` 첫머리에서 정하는 MySQL 바이너리 정렬 접미사).
```php
    /** 분류 표. 부모 id 트리: path 는 /1/5/12/ 처럼 조상부터 자기까지의 id, slug 는 공개 주소, legacy_code 는 29판 이전의 2자 코드(새 분류는 NULL). */
    private static function categoriesDefinition(string $bin): string
    {
        return 'id {AUTO_PK}, parent_id BIGINT NULL, depth SMALLINT NOT NULL, name VARCHAR(100) NOT NULL,
            slug VARCHAR(200)' . $bin . ' NOT NULL, path VARCHAR(255) NOT NULL, legacy_code VARCHAR(10)' . $bin . ' NULL,
            sort_order INTEGER NOT NULL DEFAULT 0, active SMALLINT NOT NULL DEFAULT 1, no_coupon SMALLINT NOT NULL DEFAULT 0,
            head_html {TEXT} NOT NULL, tail_html {TEXT} NOT NULL, list_columns SMALLINT NOT NULL, list_rows SMALLINT NOT NULL,
            image_width INTEGER NOT NULL, image_height INTEGER NOT NULL, extra {TEXT} NOT NULL, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL';
    }
```
- `migrate()`: CREATE 루프 뒤, `PAYMENT_COLUMNS` 루프와 `dropColumn(... 'image_key')` 다음에 `self::migrateCategoryTree($db, $bin);` 를 넣는다. `$indexes` 에 `'yc_cat_path' => ['yc_categories', 'path']` 를 더한다. 인덱스 루프 뒤에 고유 인덱스 루프를 더한다(존재 검사는 같은 `match` 를 쓴다):
```php
        $uniqueIndexes = ['yc_cat_slug' => ['yc_categories', 'slug'], 'yc_cat_legacy_code' => ['yc_categories', 'legacy_code']];
        foreach ($uniqueIndexes as $index => [$table, $column]) {
            $physical = $db->prefix() . $index;
            $exists = match ($db->dialect()->name()) { /* 위 루프와 같은 두 질의 */ };
            if ($exists === null) $db->execute('CREATE UNIQUE INDEX ' . $db->index($index) . ' ON ' . $db->table($table) . ' (' . $db->q($column) . ')');
        }
```
(중복을 피하려면 존재 검사를 `private static function indexExists(Connection $db, string $table, string $index): bool` 로 뽑아 두 루프가 같이 쓴다.)
- 새 메서드 둘:
```php
    /** 29판: 2자 코드 계층 → 부모 id 트리. slug 칸이 이미 있으면 끝난 것이다. SQLite 는 칸을 지우거나 NULL 허용을 바꾸지 못해 표를 다시 만든다(코어의 rebuildSqliteUsers() 와 같은 방식). */
    private static function migrateCategoryTree(Connection $db, string $bin): void
    {
        try {
            $db->selectOne('SELECT slug FROM ' . $db->table('yc_categories') . ' LIMIT 1');
            return;
        } catch (DomainError) {
        }
        $table = $db->table('yc_categories');
        $keep = 'id, parent_id, depth, name, sort_order, active, no_coupon, head_html, tail_html, list_columns, list_rows, image_width, image_height, extra, created_at, updated_at';
        $db->transaction(function () use ($db, $table, $keep, $bin): void {
            if ($db->dialect()->name() === 'mysql') {
                $db->execute('ALTER TABLE ' . $table . ' ADD COLUMN slug VARCHAR(200)' . $bin . ' NOT NULL DEFAULT \'\', ADD COLUMN path VARCHAR(255) NOT NULL DEFAULT \'\', ADD COLUMN legacy_code VARCHAR(10)' . $bin . ' NULL');
                $db->execute('UPDATE ' . $table . ' SET legacy_code = code, slug = CONCAT(\'c\', id)');
                $db->execute('ALTER TABLE ' . $table . ' DROP COLUMN code');
            } else {
                $old = $db->table('yc_categories_before_tree');
                $db->execute('ALTER TABLE ' . $table . ' RENAME TO ' . $old);
                $db->execute('CREATE TABLE ' . $table . ' (' . strtr(self::categoriesDefinition($bin), $db->dialect()->typeMap()) . ')' . $db->dialect()->tableSuffix());
                $db->execute('INSERT INTO ' . $table . ' (' . $keep . ", slug, path, legacy_code) SELECT " . $keep . ", 'c' || id, '', code FROM " . $old);
                $db->execute('DROP TABLE ' . $old);
            }
            self::fillCategoryTree($db);
        });
    }

    /** 부모 사슬로 path·depth 를, 이름으로 slug 를 채운다(겹치면 -2, -3…, 이름에서 못 만들면 c<id>). 이전 직후 한 번만 돈다. */
    private static function fillCategoryTree(Connection $db): void
    {
        $table = $db->table('yc_categories');
        $rows = [];
        foreach ($db->select('SELECT id, parent_id, name FROM ' . $table . ' ORDER BY id') as $row) $rows[(int) $row['id']] = $row;
        $paths = [];
        $pathOf = static function (int $id) use (&$pathOf, &$paths, $rows): string {
            if (isset($paths[$id])) return $paths[$id];
            $parent = $rows[$id]['parent_id'];
            $above = $parent === null || !isset($rows[(int) $parent]) ? '/' : $pathOf((int) $parent);
            return $paths[$id] = $above . $id . '/';
        };
        $taken = [];
        foreach ($rows as $id => $row) {
            $base = Input::slug((string) $row['name'], 'c' . $id);
            $slug = $base;
            for ($n = 2; isset($taken[$slug]); $n++) $slug = $base . '-' . $n;
            $taken[$slug] = true;
            $path = $pathOf($id);
            $db->execute('UPDATE ' . $table . ' SET slug = ?, path = ?, depth = ? WHERE id = ?', [$slug, $path, substr_count($path, '/') - 1, $id]);
        }
    }
```
(`use GnuCms\Shop\Input;` 추가. SQLite 의 옛 인덱스 `yc_cat_parent`·`yc_cat_order` 는 표를 지울 때 함께 사라지고 인덱스 루프가 다시 만든다.)
- `src/Db/Schema.php`: `VERSION = '28'` → `'29'`; `migrateShop()` 주석에 `29판: 분류를 부모 id 트리(slug·path·legacy_code)로.` 를 잇는다.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/SchemaTest.php` → 새 테스트 PASS(다른 테스트는 다음 단계에서). `vendor/bin/phpunit --no-coverage tests/Db` → PASS.

- [ ] **Step 3: 실패하는 테스트 — 서비스**

`tests/Shop/ShopTestCase.php`:
```php
    /** 최소 필드로 분류 하나. 상위는 id 로 준다(없으면 최상위). */
    protected function category(string $name = '의류', ?int $parentId = null, array $extra = []): array
    {
        $id = $this->shop->categories->save($extra + ['name' => $name, 'parent_id' => $parentId === null ? '' : (string) $parentId, 'active' => '1',
            'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']);
        return $this->shop->categories->get($id);
    }
```
`tests/Shop/CategoriesTest.php`: `testCodeSuggestionValidationAndTree` 를 지우고 다음 둘을 넣는다(`testEditorImagesLiveInTheCategoryFolder` 의 `'code' => '10'`/`'code' => '20'` 은 지우고 최상위로 둔다; `testUpdateApplyChildrenDeleteGuardsAndBulk` 는 `category('…', '10')` 을 `category('…', (int) $top['id'])` 로, `'code' =>` 입력을 `'parent_id' =>` 로 바꾼다).

```php
    #[DataProvider('connectionProvider')]
    public function testTreeSlugsAndPaths(array $config): void
    {
        $this->setupShop($config);
        $top = $this->category('의류');
        self::assertSame(['의류', '/' . $top['id'] . '/', 1, null], [$top['slug'], $top['path'], (int) $top['depth'], $top['legacy_code']]);
        self::assertNull($top['parent_id']);
        $child = $this->category('셔츠', (int) $top['id']);
        self::assertSame(['셔츠', '/' . $top['id'] . '/' . $child['id'] . '/', 2, (int) $top['id']], [$child['slug'], $child['path'], (int) $child['depth'], (int) $child['parent_id']]);
        // 이름이 같으면 -2, 직접 준 슬러그는 규칙대로 다듬고, 남과 겹치는 직접 슬러그는 거절한다.
        self::assertSame('의류-2', $this->category('의류')['slug']);
        self::assertSame('summer-tees', $this->category('여름', null, ['slug' => ' summer/tees '])['slug']);
        try { $this->category('겹침', null, ['slug' => '셔츠']); self::fail('겹치는 슬러그는 거절해야 한다'); } catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey('slug', $e->details()); }
        // 자기 슬러그를 그대로 두고 저장하는 것은 된다.
        $this->shop->categories->save(['name' => '셔츠', 'slug' => '셔츠', 'parent_id' => (string) $top['id'], 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0'], (int) $child['id']);
        self::assertSame('셔츠', $this->shop->categories->get((int) $child['id'])['slug']);
        try { $this->category('없는 부모', 999999); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey('parent_id', $e->details()); }
        self::assertSame($child['id'], $this->shop->categories->bySlug('셔츠')['id']);
        self::assertNull($this->shop->categories->bySlug('없음'));
        self::assertSame([$top['id'], $child['id']], array_column($this->shop->categories->ancestors($this->shop->categories->get((int) $child['id'])), 'id'));
        self::assertSame(['셔츠'], array_column($this->shop->categories->children((int) $top['id'], true), 'name'));
        self::assertSame(['의류', '의류', '여름'], array_column($this->shop->categories->children(null, true), 'name'));
        $tree = $this->shop->categories->tree();
        self::assertSame(['의류', '셔츠', '의류', '여름'], array_column($tree, 'name'));
        self::assertSame((int) $top['id'] . ' > 셔츠', substr($this->shop->categories->options()[(int) $child['id']], strlen('의류 > ') - strlen('의류 > ')) === '' ? '' : '의류 > 셔츠', $this->shop->categories->options()[(int) $child['id']] === '의류 > 셔츠' ? (int) $top['id'] . ' > 셔츠' : 'x');
        self::assertSame(['c.path LIKE ?', [$top['path'] . '%']], Categories::subtreeWhere($top, 'c'));
    }
```
위 `options()` 단언 줄은 읽기 어려우니 다음 한 줄로 대신한다: `self::assertSame('의류 > 셔츠', $this->shop->categories->options()[(int) $child['id']]);`

```php
    #[DataProvider('connectionProvider')]
    public function testMoveRewritesTheSubtreeAndGuardsCyclesAndDepth(array $config): void
    {
        $this->setupShop($config);
        $a = $this->category('A'); $b = $this->category('B', (int) $a['id']); $c = $this->category('C', (int) $b['id']); $x = $this->category('X');
        $form = ['name' => 'B', 'active' => '1', 'list_columns' => '3', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0'];
        // B(와 그 아래 C)를 X 아래로 옮긴다.
        $this->shop->categories->save($form + ['parent_id' => (string) $x['id']], (int) $b['id']);
        $b2 = $this->shop->categories->get((int) $b['id']); $c2 = $this->shop->categories->get((int) $c['id']);
        self::assertSame(['/' . $x['id'] . '/' . $b['id'] . '/', 2], [$b2['path'], (int) $b2['depth']]);
        self::assertSame(['/' . $x['id'] . '/' . $b['id'] . '/' . $c['id'] . '/', 3], [$c2['path'], (int) $c2['depth']]);
        self::assertSame([], $this->shop->categories->children((int) $a['id'], false));
        self::assertArrayNotHasKey((int) $b['id'], $this->shop->categories->optionsExcluding((int) $b['id']));
        self::assertArrayNotHasKey((int) $c['id'], $this->shop->categories->optionsExcluding((int) $b['id']));
        self::assertArrayHasKey((int) $x['id'], $this->shop->categories->optionsExcluding((int) $b['id']));
        // 자기 자신·자기 하위 아래로는 못 옮긴다.
        foreach ([(int) $b['id'], (int) $c['id']] as $bad) {
            try { $this->shop->categories->save($form + ['parent_id' => (string) $bad], (int) $b['id']); self::fail('순환'); } catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey('parent_id', $e->details()); }
        }
        // 최상위로 되돌리면 path 가 /id/ 가 된다.
        $this->shop->categories->save($form + ['parent_id' => ''], (int) $b['id']);
        self::assertSame(['/' . $b['id'] . '/', 1], [$this->shop->categories->get((int) $b['id'])['path'], (int) $this->shop->categories->get((int) $b['id'])['depth']]);
        // 10단계: 9단계 사슬 아래에 하나는 되고, 그 아래로 두 단계짜리를 옮기는 것은 안 된다.
        $node = $x;
        for ($i = 2; $i <= 9; $i++) $node = $this->category('L' . $i, (int) $node['id']);
        $tenth = $this->category('L10', (int) $node['id']);
        self::assertSame(10, (int) $tenth['depth']);
        try { $this->category('L11', (int) $tenth['id']); self::fail('11단계'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        try { $this->shop->categories->save($form + ['parent_id' => (string) $node['id']], (int) $b['id']); self::fail('B+C 가 11단계'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        // 하위 적용은 path 로 건다.
        $this->shop->categories->save($form + ['parent_id' => '', 'active' => '0', 'apply_children' => '1'], (int) $b['id']);
        self::assertSame(0, (int) $this->shop->categories->get((int) $c['id'])['active']);
        self::assertSame(1, (int) $this->shop->categories->get((int) $x['id'])['active']);
    }
```
`use GnuCms\Shop\Catalog\Categories;` 를 파일에 더한다.

`tests/Shop/ListingTest.php`, `tests/Shop/ProductsTest.php`, `tests/Shop/HomeBannerTest.php`: `category('셔츠', '10')` 꼴을 `category('셔츠', (int) $top['id'])` 로 바꾼다(변수 이름은 파일을 보고 맞춘다). `ListingTest` 의 검색 호출 `listing->search($q, '10', …)` 꼴이 있으면 두 번째 인자를 분류 행(또는 null)으로 바꾸고 facet 단언은 `'slug'` 키로.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/CategoriesTest.php`
Expected: FAIL — `suggestCode`/`code` 기반 저장.

- [ ] **Step 4: 분류 서비스 재작성**

`src/Shop/Catalog/Categories.php` 를 다음 뼈대로 다시 쓴다(`delete`·`bulk`·`count`·`decode` 와 편집기 사진 이동·정리 부분은 지금 코드 그대로).

```php
final class Categories
{
    public const MAX_DEPTH = 10;

    public function __construct(private Store $store, private HtmlSanitizer $sanitizer, private Settings $settings, private ContentImageService $contentImages) {}

    /** 만들기와 고치기. 상위 분류를 바꾸면 자기와 하위 전체가 함께 옮겨진다. */
    public function save(array $input, ?int $id = null): int
    {
        $defaults = $this->settings->block('category');
        $existing = $id === null ? null : $this->get($id);
        $parentId = Input::optionalId($input['parent_id'] ?? '');
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->find($parentId) ?? throw DomainError::validation(['parent_id' => '상위 분류를 찾을 수 없습니다.']);
            if ($existing !== null && str_starts_with($parent['path'], $existing['path'])) throw DomainError::validation(['parent_id' => '자기 자신이나 자기 하위 분류 아래로 옮길 수 없습니다.']);
        }
        $depth = $parent === null ? 1 : (int) $parent['depth'] + 1;
        $below = $existing === null ? 0 : (int) $this->store->selectOne('SELECT MAX(depth) AS d FROM ' . $this->store->table('yc_categories') . ' WHERE path LIKE ?', [$existing['path'] . '%'])['d'] - (int) $existing['depth'];
        if ($depth + $below > self::MAX_DEPTH) throw DomainError::validation(['parent_id' => '분류는 ' . self::MAX_DEPTH . '단계까지입니다.']);
        $row = [
            'name' => Input::text($input['name'] ?? '', 'name', 100, false),
            'slug' => $this->uniqueSlug($input['slug'] ?? '', $input['name'] ?? '', $id),
            'parent_id' => $parentId, 'depth' => $depth,
            /* sort_order, active, no_coupon, head_html, tail_html, list_columns, list_rows, image_width, image_height, extra, updated_at — 지금 코드 그대로 */
        ];
        $tmpKey = /* 지금 코드 그대로 */;
        $saved = $this->store->transaction(function () use ($input, $id, $existing, $parent, $row): int {
            $table = $this->store->table('yc_categories');
            if ($existing !== null) {
                $this->store->update('yc_categories', $id, $row);
                $newPath = ($parent === null ? '/' : $parent['path']) . $id . '/';
                if ($newPath !== $existing['path']) {
                    // 자기와 하위 전체의 path 앞부분을 바꾸고 depth 를 차이만큼 옮긴다. 분류는 많지 않아 행마다 고친다(방언 차이 없음).
                    $delta = (int) $row['depth'] - (int) $existing['depth'];
                    foreach ($this->store->select('SELECT id, path, depth FROM ' . $table . ' WHERE path LIKE ?', [$existing['path'] . '%']) as $node) {
                        $this->store->update('yc_categories', (int) $node['id'], ['path' => $newPath . substr($node['path'], strlen($existing['path'])), 'depth' => (int) $node['depth'] + $delta]);
                    }
                }
                if (($input['apply_children'] ?? '') === '1') {
                    $this->store->db->update('yc_categories', array_intersect_key($row, array_flip(['active', 'no_coupon', 'list_columns', 'list_rows', 'image_width', 'image_height', 'updated_at'])),
                        'path LIKE :prefix AND id <> :id', ['prefix' => $newPath . '%', 'id' => $id]);
                }
                return $id;
            }
            $newId = $this->store->insert('yc_categories', $row + ['path' => '', 'created_at' => Clock::timestamp()]);
            $this->store->update('yc_categories', $newId, ['path' => ($parent === null ? '/' : $parent['path']) . $newId . '/']);
            return $newId;
        });
        /* 편집기 사진 이동·정리 — 지금 코드 그대로 */
        return $saved;
    }

    /** 직접 준 슬러그는 규칙대로 다듬고 겹치면 거절한다. 이름에서 만든 슬러그는 -2, -3 … 을 붙인다. */
    private function uniqueSlug(mixed $given, mixed $name, ?int $excludeId): string
    {
        $typed = is_string($given) && trim($given) !== '';
        $base = Input::slug($typed ? $given : (is_string($name) ? $name : ''), '');
        if ($base === '') throw DomainError::validation(['slug' => '슬러그를 만들 수 없습니다. 영문·숫자·한글이 든 이름이나 슬러그를 입력해 주세요.']);
        $slug = $base;
        for ($n = 2; ($other = $this->bySlug($slug)) !== null && (int) $other['id'] !== $excludeId; $n++) {
            if ($typed) throw DomainError::validation(['slug' => '이미 쓰는 슬러그입니다.']);
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    public function get(int $id): array { return $this->decode($this->store->get('yc_categories', $id)); }
    public function find(int $id): ?array { $row = $this->store->find('yc_categories', $id); return $row === null ? null : $this->decode($row); }
    public function bySlug(string $slug): ?array { /* WHERE slug = ? */ }
    public function byLegacyCode(string $code): ?array { /* WHERE legacy_code = ?; 빈 문자열이면 null */ }

    /** 부모 우선 DFS. 형제는 sort_order, name 순. 각 행에 product_count. */
    public function tree(): array { /* 지금 코드에서 ORDER BY c.sort_order, c.code → c.sort_order, c.name */ }
    public function options(): array { /* 그대로 */ }
    /** 수정 화면의 상위 분류 선택용: 자기와 하위를 뺀 options(). */
    public function optionsExcluding(int $id): array
    {
        $self = $this->find($id);
        $out = [];
        foreach ($this->options() as $optionId => $label) {
            $row = $this->find((int) $optionId);
            if ($self === null || $row === null || !str_starts_with($row['path'], $self['path'])) $out[(int) $optionId] = $label;
        }
        return $out;
    }
    /** 빵부스러기: path 의 id 순서대로. */
    public function ancestors(array $category): array
    {
        $ids = array_values(array_filter(explode('/', (string) $category['path']), static fn (string $s): bool => $s !== ''));
        $rows = [];
        foreach ($ids as $id) { $row = $this->find((int) $id); if ($row !== null) $rows[] = $row; }
        return $rows;
    }
    public function children(?int $parentId, bool $activeOnly): array
    {
        $rows = $this->store->select('SELECT * FROM ' . $this->store->table('yc_categories') . ' WHERE ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = ?')
            . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, name', $parentId === null ? [] : [$parentId]);
        return array_map($this->decode(...), $rows);
    }
    /** 하위 전체(자기 포함)를 거는 조건. [sql, params]. */
    public static function subtreeWhere(array $category, string $alias = 'c'): array
    {
        return [$alias . '.path LIKE ?', [$category['path'] . '%']];
    }
    /* delete, bulk, count, decode — 그대로 */
}
```
`optionsExcluding()` 의 `find()` 반복은 분류 수만큼 질의한다. 더 낫게 하려면 `tree()` 결과의 `path` 로 걸러도 된다(둘 중 하나, 동작은 같다).

Run: `vendor/bin/phpunit --no-coverage tests/Shop/CategoriesTest.php` → PASS. `vendor/bin/phpunit --no-coverage tests/Shop` → `ListingTest`·`ProductsTest`·`HomeBannerTest` 가 아직 `c.code LIKE` 로 깨질 수 있다 — 다음 단계.

- [ ] **Step 5: 목록·상품의 하위 조건과 검색 facet**

- `src/Shop/Catalog/Listing.php` `category()`: `c.code LIKE ?` 와 `[$category['code'] . '%']` 를 `[$sub, $params] = Categories::subtreeWhere($category, 'c');` 로 바꿔 `… AND c.active = 1 AND ' . $sub . ')'`, `$params`. `search(string $q, ?array $category, int $min, int $max, string $sort, string $dir, int $page)`: facet 질의를 `SELECT c.slug, c.name, COUNT(*) AS count … GROUP BY c.slug, c.name ORDER BY c.name` 으로, `$ca` 조건을 `if ($category !== null) { [$sub, $subParams] = Categories::subtreeWhere($category, 'cc'); $where[] = 'EXISTS (SELECT 1 FROM ' . … . ' cc WHERE cc.id = p.category_id AND ' . $sub . ')'; array_push($params, ...$subParams); }` 으로, `facets` 를 `['slug' => $f['slug'], 'name' => …, 'count' => …]` 로. `use GnuCms\Shop\Catalog\Categories;` 는 같은 이름공간이라 필요 없다.
- `src/Shop/Catalog/Products.php` `list()`: `$ca = Input::text(...)` 와 `preg_match` 블록을
```php
        $category = ($caId = Input::optionalId($filters['ca'] ?? '')) === null ? null : $this->categories->find($caId);
        if ($category !== null) {
            [$sub, $subParams] = Categories::subtreeWhere($category, 'c2');
            $where[] = 'EXISTS (SELECT 1 FROM ' . $this->store->table('yc_product_categories') . ' pc JOIN ' . $this->store->table('yc_categories') . ' c2 ON c2.id = pc.category_id WHERE pc.product_id = p.id AND ' . $sub . ')';
            array_push($params, ...$subParams);
        }
```
로. `search()` 의 `if ($ca !== '' && preg_match(...)) { $where[] = 'c.code LIKE ?'; … }` 도 같은 식으로(`$this->categories->find(Input::optionalId($ca))`, 별칭 `c`). `Input::optionalId()` 는 숫자가 아니면 422 를 던지므로 관리자 필터에 옛 코드가 남아 오면 422 — 작업 2 가 화면을 id 로 바꾸므로 그대로 둔다.

Run: `vendor/bin/phpunit --no-coverage tests/Shop` → PASS(전부).

- [ ] **Step 6: 실패하는 테스트 — 공개 화면**

`tests/Web/ShopPublicTest.php`: `seed()` 의 두 `save([...'code' => '10'...])`/`'code' => '1010'` 를 `'parent_id' => ''` / `'parent_id' => (string) $top['id']` 로 바꾼다(`code` 키 삭제). 176~194행 묶음을 다음으로 바꾼다(주변 변수 이름은 파일을 보고 맞춘다).
```php
        self::assertStringContainsString('href="/shop/c/%EC%9D%98%EB%A5%98"', $home);
        $list = $this->body($this->get($this->app, '/shop/c/의류'));
        self::assertStringContainsString('href="/shop/c/%EC%85%94%EC%B8%A0"', $list);
        /* 기존의 쪽·정렬 단언은 '/shop/c/의류' 에 ['page' => '2'] 등 쿼리를 붙여 그대로 */
        self::assertSame(404, $this->get($this->app, '/shop/c/없는-분류')->getStatusCode());
        // 옛 주소는 새 주소로 넘긴다: 코드도, 슬러그도.
        foreach (['10', '의류'] as $ca) {
            $moved = $this->get($this->app, '/shop/list', ['ca' => $ca, 'sort' => 'price', 'dir' => 'asc']);
            self::assertSame(301, $moved->getStatusCode());
            self::assertSame('/shop/c/%EC%9D%98%EB%A5%98?sort=price&dir=asc', $moved->getHeaderLine('Location'));
        }
        self::assertSame(404, $this->get($this->app, '/shop/list', ['ca' => '99'])->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/shop/list')->getStatusCode());
```
옛 코드 넘김을 실제로 보려면 `seed()` 뒤에 `$this->app->db()->update('yc_categories', ['legacy_code' => '10'], 'id = :id', ['id' => $top['id']])` 로 옛 코드를 심는다. 276·282·287행의 `/shop/list?ca=…` 호출은 `/shop/c/<슬러그>` 로 바꾼다(`$category['slug']` 를 `rawurlencode`). 검색 facet 단언이 있으면 `ca=<슬러그>` 로.
`tests/Web/ShopCommerceTest.php:33`: `'code' => '10'` 을 지운다.

Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopPublicTest.php` → FAIL(`/shop/c/…` 404).

- [ ] **Step 7: 라우트·컨트롤러·템플릿**

- `src/Shop/Routes.php`: 공개 GET 루프 뒤에
```php
        $map('GET', '/c/{slug}', static fn ($request, $response, array $args) => $shop->handle('category', $request, $response, $args))->setName('shop.category');
```
- `src/Shop/Web/ShopController.php`: `handle(string $page, ServerRequestInterface $request, ResponseInterface $response, array $args = [])`. `$data['menu'] = $this->service->categories->children(null, true);`. `case 'list'` 를 다음 둘로:
```php
            case 'category':
                $category = $this->service->categories->bySlug((string) ($args['slug'] ?? ''));
                if ($category === null || (int) $category['active'] !== 1) throw DomainError::notFound('분류를 찾을 수 없습니다.');
                $data['category'] = $category;
                $data['path'] = $this->service->categories->ancestors($category);
                $data['children'] = $this->service->categories->children((int) $category['id'], true);
                $data['list'] = $this->service->listing->category($category, $sort, $dir, $pageNo);
                return $view->render($response, 'list', $data);
            case 'list':
                // 옛 주소 /shop/list?ca=코드|슬러그 → /shop/c/슬러그. 나머지 매개변수는 그대로 붙인다.
                $ca = (string) ($query['ca'] ?? '');
                $category = $ca === '' ? null : ($this->service->categories->byLegacyCode($ca) ?? $this->service->categories->bySlug($ca));
                if ($category === null) throw DomainError::notFound('분류를 찾을 수 없습니다.');
                unset($query['ca']);
                return $response->withStatus(301)->withHeader('Location', $url . '/c/' . rawurlencode($category['slug']) . ($query === [] ? '' : '?' . http_build_query($query)));
```
`case 'search'`: `$ca = preg_match(...)` 줄을 `$ca = (string) ($query['ca'] ?? ''); $category = $ca === '' ? null : $this->service->categories->bySlug($ca);` 로, `$data['ca'] = $category['slug'] ?? ''`, `listing->search($q, $category, …)`. `case 'item'`: `$data['path'] = isset($product['categories'][1]) ? $this->service->categories->ancestors($product['categories'][1]) : [];`(`hydrate()` 가 `c.*` 를 고르므로 `path` 가 있다).
- `src/Shop/HomeBanner.php:120`: `$url . '/c/' . rawurlencode($menu[0]['slug'])`.
- 템플릿: `_header.php`·`index.php`·`_breadcrumb.php`·`list.php`(하위 칩) 의 `/list?ca=<?= $this->e($x['code']) ?>` 를 `/c/<?= $this->e(rawurlencode($x['slug'])) ?>` 로. `list.php` 의 `_sort` 삽입을 `['action' => $url . '/c/' . rawurlencode($category['slug']), 'hidden' => []]` 로, `_pager` 의 `page_url` 을 `fn (int $p): string => $url . '/c/' . rawurlencode($category['slug']) . '?' . http_build_query(['sort' => $sort, 'dir' => $dir, 'page' => $p])` 로. `search.php` facet: `$ca === $facet['slug']`, `'ca' => $facet['slug']`.

Run (각각): `tests/Web/ShopPublicTest.php`, `tests/Web/ShopCommerceTest.php`, `tests/Shop`, `tests/Db` → 모두 OK. `tests/Web/ShopAdminTest.php` 는 분류 화면 테스트만 실패해야 한다(다른 실패가 있으면 고친다).

- [ ] **Step 8: 커밋**

```bash
git add src/Shop src/Db/Schema.php templates/default/shop tests/Shop tests/Web/ShopPublicTest.php tests/Web/ShopCommerceTest.php tests/Db
git commit -m "feat: turn shop categories into an unlimited tree with slug addresses

Schema 29 replaces the two-character code hierarchy with parent ids, a
materialized path and a slug (old codes stay as legacy_code); categories
can be moved; /shop/c/{slug} is the category page and /shop/list?ca= redirects.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 관리자 분류 화면 — 코드 칸 제거, 상위 분류 선택, 슬러그

**Files:**
- Modify: `src/Shop/Admin/CategoryController.php`, `src/Shop/Admin/ProductController.php:66`, `templates/default/shop/admin/categories.php`, `templates/default/shop/admin/category_form.php`, `templates/default/shop/admin/_filters.php`
- Test: `tests/Web/ShopAdminTest.php`

**Interfaces:**
- Consumes 작업 1 의 `Categories` API(`options`, `optionsExcluding`, `find`, `save` 의 `parent_id`·`slug`), `Products::list()` 의 `ca` = 분류 id.
- Produces: 분류 폼 필드 `name`, `slug`, `parent_id`(select, '' = 최상위), 나머지 그대로; `categories/new?parent=<id>` 로 상위 미리 고름; 목록의 상품 수 링크 `products?ca=<id>`; 상품 필터 select 값 = 분류 id(뷰 변수 `category_options`).

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/ShopAdminTest.php::testCategoryScreens` 를 새 화면에 맞춘다.
- 새 폼: `self::assertStringContainsString('name="code" value="10"', $form)` 을 `self::assertStringNotContainsString('name="code"', $form); self::assertStringContainsString('name="parent_id"', $form); self::assertStringContainsString('name="slug"', $form);` 로.
- 첫 POST(`/categories/new`)에서 `'code' => '10'` 을 지우고 `'parent_id' => ''` 를 넣는다. `$top = byCode('10')` → `$top = $this->shop->categories->bySlug('의류')`. 중복 POST(`'name' => '중복'`)는 `'slug' => '의류'` 를 함께 보내 422 와 `'이미 쓰는 슬러그'` 문구를 단언한다.
- `?parent=10` 으로 열던 줄은 `['parent' => (string) $top['id']]` 로 열어 `'<option value="' . $top['id'] . '" selected'` 를 단언. 하위 생성 POST 는 `'parent_id' => (string) $top['id']` 로, 이후 `byCode('1010')` 는 `bySlug('셔츠')` 로.
- 목록: `self::assertStringContainsString('셔츠</', $list)` 대신 슬러그 표시 `'<small>셔츠</small>'`(템플릿의 표시 형식과 맞춘다) 와 `'products?ca=' . $top['id']` 를 단언.
- 수정 폼: `name="parent_id"` select 에 자기 자신 `option value="<id>"` 가 없음을 단언(`optionsExcluding`). 수정 POST 에 `'parent_id' => ''` 를 포함(최상위 유지). 이동 확인: 하위 `셔츠` 를 새로 만든 최상위 `가전` 아래로 옮기는 POST 뒤 `path` 가 `/가전id/셔츠id/` 인지.
- 상품 필터: `GET /admin/shop/products?ca=<top id>` 가 200 이고 하위 분류 상품이 보이는지(기존 상품 목록 테스트에 `?ca=` 가 있으면 id 로 바꾼다).

Run: `vendor/bin/phpunit --no-coverage --filter testCategoryScreens tests/Web/ShopAdminTest.php` → FAIL.

- [ ] **Step 2: 컨트롤러·템플릿**

- `CategoryController::defaults()`: `$parent` 를 `Input::optionalId($input['parent'] ?? '')` 로 받아 `'parent_id' => $parent === null ? '' : (string) $parent`, `'slug' => ''` 를 넣고 `'code'`·`suggestCode` 를 지운다. `form()`: `$data['parents'] = $id === null ? $this->service->categories->options() : $this->service->categories->optionsExcluding($id);`. 오류 재표시(`$input + …`)에서 `['code' => …]` 를 붙이던 부분을 지운다.
- `category_form.php`: 코드 fieldset 을 지우고 첫 줄을 `이름`(넓게)·`슬러그`(힌트: 비우면 이름에서 만듭니다. 주소 `/shop/c/슬러그`) 로, 다음 줄에 `상위 분류` select(`<option value="">최상위</option>` + `$parents`, 선택값 `$v('parent_id')`)와 `순서`. 나머지 그대로.
- `categories.php`: 이름 아래 `<small>` 에 슬러그를 보이고, 상품 수 링크를 `products?ca=<?= (int) $row['id'] ?>` 로, "하위 추가" 조건을 `(int) $row['depth'] < Categories::MAX_DEPTH` 로(템플릿 상단에 `use GnuCms\Shop\Catalog\Categories;` 대신 `\GnuCms\Shop\Catalog\Categories::MAX_DEPTH` 로 쓴다), 링크 `categories/new?parent=<?= (int) $row['id'] ?>`.
- `ProductController.php:66`: `$data['category_options'] = $this->service->categories->options();` `_filters.php`: `$category_codes` → `$category_options`, `value="<?= (int) $id ?>"`, 선택 비교 `$filters['ca'] === (string) $id`.

Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php` → OK.

- [ ] **Step 3: 화면 확인과 커밋**

렌더링 확인: 분류 목록·분류 수정 화면을 라이트로 한 번(세션 메모리 `admin-screen-visual-check` 의 WebTestCase 스크래치 방식). 이상 없으면:
```bash
git add src/Shop/Admin templates/default/shop/admin tests/Web/ShopAdminTest.php
git commit -m "feat: pick a parent category and slug instead of a code in the shop admin

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3: 문서와 전체 검증

**Files:**
- Modify: `docs/shop.md`(주소 표 14행, 분류 절 66~75행, 상품 절의 필터 문장, 영카트5 대응표 274~276행), `AGENTS.md:34`

- [ ] **Step 1: 문서**

- `docs/shop.md` 14행: `분류 `/shop/list?ca=코드`` → `분류 `/shop/c/슬러그`(옛 `/shop/list?ca=코드`는 새 주소로 넘긴다)`.
- 분류 절: 코드 규칙 두 문단(69~72행)을 다음으로 바꾼다.
```markdown
- 분류는 상위 분류를 골라 만드는 트리다. 단계 제한은 없고 화면과 저장은 10단계까지 받는다. 수정 화면에서
  상위 분류를 바꾸면 자기와 하위 전체가 함께 옮겨진다. 자기 하위 분류 아래로는 옮길 수 없다.
- 주소는 슬러그다(`/shop/c/셔츠`). 슬러그는 상품과 같은 규칙으로 이름에서 만들고(한글 그대로, 공백은 `-`),
  직접 정할 수도 있으며 전체에서 하나여야 한다. 이름이 같은 분류는 `-2`, `-3`이 붙는다. 슬러그를 바꾸면 주소가
  바뀌므로 옛 주소는 더 이상 열리지 않는다.
- 29판 이전에 쓰던 2자 코드는 `legacy_code`로 남아 옛 링크 `/shop/list?ca=코드`를 새 주소로 넘기는 데만 쓴다.
- 목록은 그 분류와 모든 활성 하위 분류의 상품을 보여 준다. 상품의 대표 분류와 추가 분류(최대 2개) 어느 쪽에
  연결되어도 나온다.
```
- 대응표 274행 `| `ca_id` | `yc_categories.code` |` → `| `ca_id` | `yc_categories.legacy_code`(주소는 `slug`, 계층은 `parent_id`·`path`) |`.
- `AGENTS.md` 34행의 "분류·상품·옵션·…" 문장에 `분류는 무한 단계 트리(슬러그 주소 `/shop/c/슬러그`)` 를 덧붙인다.

- [ ] **Step 2: 전체 스위트와 MySQL 한 번, 커밋**

```bash
vendor/bin/phpunit --no-coverage
DB=gnucms_tree_$(date +%s); mysql -uroot -e "CREATE DATABASE $DB CHARACTER SET utf8mb4;"
for p in tests/Shop tests/Db tests/Web/ShopPublicTest.php tests/Web/ShopAdminTest.php tests/Web/ShopCommerceTest.php; do TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=$DB;charset=utf8mb4" TEST_MYSQL_USER=root vendor/bin/phpunit --no-coverage $p | tail -1; done
mysql -uroot -e "DROP DATABASE $DB;"
git add docs/shop.md AGENTS.md
git commit -m "docs: describe the shop's category tree and slug addresses

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```
Expected: 전체 OK, MySQL 다섯 스위트 OK(스키마 이전의 ALTER 경로는 여기서만 검증된다).

---

## 자체 검토

- **스펙 대조.** §2 데이터·이전 → 작업 1 Step 1-2. §3 서비스 → Step 3-5. §4 공개 주소·화면 → Step 6-7. §5 관리자 → 작업 2. §6 테스트 → 각 작업. §7 문서 → 작업 3. §8 기존 사이트 → 스키마 이전과 MySQL 검증(작업 3).
- **자리표시자.** 작업 1 Step 4 의 "지금 코드 그대로" 는 같은 파일의 현재 본문(`delete`·`bulk`·`count`·`decode`, 편집기 사진 이동·정리, `$row` 의 나머지 항목)을 가리킨다.
- **이름 일치.** `Categories::MAX_DEPTH`, `subtreeWhere`, `optionsExcluding`, `ancestors`, `children(?int,bool)`, `bySlug`, `byLegacyCode`, `find`; `Listing::search(…, ?array $category, …)`; 페이지 `category`, 라우트 `shop.category`; 뷰 변수 `parents`, `category_options`; 테스트 헬퍼 `category(string, ?int, array)` — 작업 간 동일하다.
