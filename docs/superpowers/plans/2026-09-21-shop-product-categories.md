# 상품의 분류 제한 없이, 목록에서 분류 일괄 넣기·빼기, 분류 메뉴 숨김 — 구현 계획

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 상품에 붙는 분류를 대표 하나 + 추가 2개에서 대표 하나 + 추가 제한 없음(20개)으로 바꾸고, 상품 목록에서 선택한 상품을 분류에 넣거나 빼며, 분류에 "메뉴에서 숨김" 스위치를 둔다(이벤트성 분류).

**Architecture:** 연결 표 `yc_product_categories(product_id, category_id, slot)` 는 그대로다. slot 1 이 대표, 2 이상이 순서다. 폼은 `category_id`(대표) + `extra_category_ids[]`(추가 목록, JS 로 줄 추가) 로 바뀌고 검증이 목록을 받는다. 목록 일괄 작업은 같은 선택 폼에 `categorize`/`uncategorize` 동작을 더한다. 분류 표에 `menu_hidden` 칸(30판)을 더하고 메뉴·바로가기·하위 칩만 이를 거른다.

**Tech Stack:** PHP 8.4, Slim, PHPUnit 10, SQLite·MariaDB. 새 의존성 없음.

**Spec:** 사용자와 채팅에서 확정한 설계(2026-09-21): (1) 상품 폼 대표 분류 + "분류 추가" 목록, 상한 20; (2) 상품 목록 일괄 "분류에 추가 / 분류에서 제거"; (3) 분류 "메뉴에서 숨김" 스위치. 이 문서의 Global Constraints 가 그 요약이다.

## Global Constraints

- 브랜치 `feat/core-commerce`(라이브 체크아웃). 커밋 `feat:`/`docs:` + `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- 연결 표·표 이름은 그대로. slot 1 = 대표 분류(`yc_products.category_id` 와 같다), slot 2… = 추가 분류의 순서. 한 상품에 같은 분류는 한 번만.
- 폼 필드: `category_id`(대표, 필수), `extra_category_ids[]`(추가, 선택, 빈값 무시, 대표와 겹치면 무시, 중복 제거, 최대 `Products::MAX_EXTRA_CATEGORIES = 20`, 없는 분류는 422 `extra_category_ids`). `category2_id`·`category3_id` 는 없어진다.
- 목록 일괄 작업: 선택 폼(`yc-product-delete` — id 는 그대로 두고 버튼이 동작을 정한다) 의 `action` = `delete` | `categorize` | `uncategorize`, `category` = 분류 id. 넣기는 이미 있으면 건너뛰고 없으면 `slot = MAX(slot)+1`. 빼기는 slot 1(대표)은 건드리지 않는다. 결과는 `?op=add|remove&changed=n&skipped=m` 로 넘겨 안내문을 만든다.
- 분류 `menu_hidden`(0/1, 기본 0): 켜면 상단 메뉴·메인 바로가기·상위 분류 페이지의 하위 칩에서 빠진다. `/shop/c/슬러그` 주소·빵부스러기·검색·상품 연결은 그대로다. 코어 `Db\Schema::VERSION = '30'`.
- 인라인 JS 핸들러 금지(`onsubmit=` 도 없앤다); `data-yc-*` 속성과 `youngcart-admin.js` 의 위임 핸들러를 쓴다. 데이터를 JS 문자열에 끼워 넣지 않는다.
- 테스트는 `vendor/bin/phpunit --no-coverage <경로>`. 종료 코드를 확인하고 커밋한다(출력 grep 만 보고 커밋하지 않는다). 전체 스위트는 작업 2 끝에서 한 번.

---

## 파일 구조

| 파일 | 책임 |
|---|---|
| `src/Shop/Catalog/Products.php` | `categoryIds()` 목록 검증, `MAX_EXTRA_CATEGORIES`, `addToCategory()`, `removeFromCategory()` |
| `src/Shop/Admin/ProductFormController.php` | 폼 값 `extra_category_ids` |
| `src/Shop/Admin/ProductController.php` | `categorize`/`uncategorize` 동작과 안내문 |
| `templates/default/shop/admin/product_form.php` | 대표 분류 + 추가 분류 목록 + 템플릿 행 |
| `templates/default/shop/admin/products.php` | 선택 작업 막대(분류 선택 + 넣기/빼기/삭제) |
| `www/themes/default/youngcart-admin.js`, `youngcart-admin.css` | 줄 추가·제거, 삭제 확인, 막대 배치 |
| `src/Shop/Schema.php`, `src/Db/Schema.php` | `menu_hidden` 칸(30판) |
| `src/Shop/Catalog/Categories.php` | `menu_hidden` 저장, `children(…, bool $menuOnly)` |
| `src/Shop/Web/ShopController.php`, `CommerceController.php` | 메뉴·하위 칩에 `menuOnly` |
| `templates/default/shop/admin/category_form.php`, `categories.php` | 스위치와 표시 |
| 테스트: `tests/Shop/ProductsTest.php`, `ListingTest.php`, `CategoriesTest.php`, `SchemaTest.php`, `tests/Web/ShopAdminTest.php`, `ShopPublicTest.php`, `tests/Db/*` | |
| `docs/shop.md` | 문서 |

---

### Task 1: 상품의 추가 분류 제한 없이 + 목록에서 분류 넣기·빼기

**Files:**
- Modify: `src/Shop/Catalog/Products.php`(`categoryIds()` ~166-186, `bulkDelete()` 근처에 새 메서드), `src/Shop/Admin/ProductFormController.php`(60, 80-81), `src/Shop/Admin/ProductController.php`(29-33, 안내문 자리 ~56), `templates/default/shop/admin/product_form.php`(22-26 `$catSelect`, 37), `templates/default/shop/admin/products.php`(37-38), `www/themes/default/youngcart-admin.js`(폼 클릭 위임 74-96, 목록 스크립트), `www/themes/default/youngcart-admin.css`
- Test: `tests/Shop/ProductsTest.php`, `tests/Shop/ListingTest.php`, `tests/Web/ShopAdminTest.php`

**Interfaces:**
- Produces: `Products::MAX_EXTRA_CATEGORIES = 20`; `Products::save()` 입력 `category_id` + `extra_category_ids[]`; `Products::addToCategory(array $ids, int $categoryId): array{changed:int, skipped:int}`; `Products::removeFromCategory(array $ids, int $categoryId): array{changed:int, skipped:int}`; 폼 값 `extra_category_ids`(list<string>); 목록 POST `action=categorize|uncategorize`, `ids[]`, `category`.
- `hydrate()` 의 `categories`(slot => 분류 행), `copy()`, `bulk()` 의 대표 분류 바꾸기는 그대로 동작한다.

- [ ] **Step 1: 실패하는 테스트 — 서비스**

`tests/Shop/ProductsTest.php`: 33행·70행의 `'category2_id' => …` 를 `'extra_category_ids' => [ … ]` 로 바꾼다(70행의 오류 표는 `'extra_category_ids' => ['extra_category_ids' => ['999999']]` 처럼 없는 분류로). 다음 테스트를 더한다.

```php
    /** 추가 분류는 개수 제한 없이(20개까지) 순서대로 slot 2… 에 붙는다. 빈값·대표와 겹침·중복은 조용히 빠지고, 없는 분류와 21개 초과는 거절한다. */
    #[DataProvider('connectionProvider')]
    public function testExtraCategoriesAreUnlimitedAndOrdered(array $config): void
    {
        $this->setupShop($config);
        $primary = $this->category('의류');
        $extras = [];
        for ($i = 1; $i <= 4; $i++) $extras[] = (int) $this->category('추가' . $i)['id'];
        $id = $this->shop->products->save($this->fullInput((int) $primary['id'], ['extra_category_ids' => ['', (string) $extras[2], (string) $primary['id'], (string) $extras[0], (string) $extras[2], (string) $extras[3]]]), []);
        $slots = array_map(static fn (array $c): int => (int) $c['id'], $this->shop->products->get($id)['categories']);
        self::assertSame([1 => (int) $primary['id'], 2 => $extras[2], 3 => $extras[0], 4 => $extras[3]], $slots);
        foreach ([['extra_category_ids' => ['999999']], ['extra_category_ids' => array_fill(0, 21, (string) $extras[1])]] as $bad) {
            try { $this->shop->products->save($this->fullInput((int) $primary['id'], ['code' => 'X' . count($bad['extra_category_ids'])] + $bad), []); self::fail('거절해야 한다'); }
            catch (DomainError $e) { self::assertSame(422, $e->status()); self::assertArrayHasKey('extra_category_ids', $e->details()); }
        }
    }

    /** 목록 일괄 작업: 분류에 넣기는 이미 있으면 건너뛰고 slot 을 이어 붙이며, 빼기는 대표(slot 1)를 건드리지 않는다. */
    #[DataProvider('connectionProvider')]
    public function testAddToAndRemoveFromCategory(array $config): void
    {
        $this->setupShop($config);
        $primary = $this->category('의류'); $event = $this->category('봄 세일'); $other = $this->category('기타');
        $a = $this->shop->products->save($this->fullInput((int) $primary['id'], ['code' => 'A', 'extra_category_ids' => [(string) $other['id']]]), []);
        $b = $this->shop->products->save($this->fullInput((int) $event['id'], ['code' => 'B']), []);
        self::assertSame(['changed' => 1, 'skipped' => 1], $this->shop->products->addToCategory([(string) $a, (string) $b], (int) $event['id']));
        self::assertSame([1 => (int) $primary['id'], 2 => (int) $other['id'], 3 => (int) $event['id']], array_map(static fn (array $c): int => (int) $c['id'], $this->shop->products->get($a)['categories']));
        self::assertSame(['changed' => 0, 'skipped' => 2], $this->shop->products->addToCategory([(string) $a, (string) $b], (int) $event['id']));
        self::assertSame(['changed' => 1, 'skipped' => 1], $this->shop->products->removeFromCategory([(string) $a, (string) $b], (int) $event['id']));
        self::assertSame([1 => (int) $primary['id'], 2 => (int) $other['id']], array_map(static fn (array $c): int => (int) $c['id'], $this->shop->products->get($a)['categories']));
        self::assertSame((int) $event['id'], (int) $this->shop->products->get($b)['categories'][1]['id'], '대표 분류는 빼지 않는다');
        try { $this->shop->products->addToCategory([(string) $a], 999999); self::fail(); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
    }
```
`tests/Shop/ListingTest.php:17`: `'category2_id' => (string) $top['id']` → `'extra_category_ids' => [(string) $top['id']]`.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/ProductsTest.php` → FAIL(추가 분류가 붙지 않음, 메서드 없음).

- [ ] **Step 2: 서비스**

`src/Shop/Catalog/Products.php`:
```php
    public const MAX_EXTRA_CATEGORIES = 20;

    /** 대표 분류(slot 1) + 추가 분류(slot 2…). 빈값·대표와 겹침·중복은 빼고, 없는 분류와 상한 초과는 거절한다. */
    private function categoryIds(array $input): array
    {
        $primary = Input::optionalId($input['category_id'] ?? '') ?? throw DomainError::validation(['category_id' => '대표 분류를 선택해 주세요.']);
        if ($this->store->find('yc_categories', $primary) === null) throw DomainError::validation(['category_id' => '분류를 찾을 수 없습니다.']);
        $ids = [1 => $primary];
        $extras = [];
        foreach (is_array($input['extra_category_ids'] ?? null) ? $input['extra_category_ids'] : [] as $raw) {
            if (!is_string($raw) && !is_int($raw)) continue;
            $extra = Input::optionalId((string) $raw);
            if ($extra === null || $extra === $primary || in_array($extra, $extras, true)) continue;
            $extras[] = $extra;
        }
        if (count($extras) > self::MAX_EXTRA_CATEGORIES) throw DomainError::validation(['extra_category_ids' => '추가 분류는 ' . self::MAX_EXTRA_CATEGORIES . '개까지입니다.']);
        foreach ($extras as $extra) {
            if ($this->store->find('yc_categories', $extra) === null) throw DomainError::validation(['extra_category_ids' => '분류를 찾을 수 없습니다.']);
            $ids[] = $extra; // 2, 3, …
        }
        return $ids;
    }

    /** 목록 일괄: 선택 상품을 분류에 넣는다. 이미 있으면 건너뛴다. */
    public function addToCategory(array $ids, int $categoryId): array
    {
        if ($this->store->find('yc_categories', $categoryId) === null) throw DomainError::validation(['category' => '분류를 찾을 수 없습니다.']);
        $changed = 0; $skipped = 0;
        $this->store->transaction(function () use ($ids, $categoryId, &$changed, &$skipped): void {
            foreach ($ids as $raw) {
                $id = Input::id($raw);
                $this->store->get('yc_products', $id);
                if ($this->store->selectOne('SELECT 1 AS x FROM ' . $this->store->table('yc_product_categories') . ' WHERE product_id = ? AND category_id = ?', [$id, $categoryId]) !== null) { $skipped++; continue; }
                $slot = (int) $this->store->selectOne('SELECT COALESCE(MAX(slot), 0) AS s FROM ' . $this->store->table('yc_product_categories') . ' WHERE product_id = ?', [$id])['s'] + 1;
                $this->store->insert('yc_product_categories', ['product_id' => $id, 'category_id' => $categoryId, 'slot' => max(2, $slot)]);
                $changed++;
            }
        });
        return ['changed' => $changed, 'skipped' => $skipped];
    }

    /** 목록 일괄: 선택 상품을 분류에서 뺀다. 대표 분류(slot 1)는 건드리지 않는다. */
    public function removeFromCategory(array $ids, int $categoryId): array
    {
        $changed = 0; $skipped = 0;
        $this->store->transaction(function () use ($ids, $categoryId, &$changed, &$skipped): void {
            foreach ($ids as $raw) {
                $id = Input::id($raw);
                $this->store->get('yc_products', $id);
                $deleted = $this->store->delete('yc_product_categories', 'product_id = ? AND category_id = ? AND slot <> 1', [$id, $categoryId]);
                $deleted > 0 ? $changed++ : $skipped++;
            }
        });
        return ['changed' => $changed, 'skipped' => $skipped];
    }
```
`Store::delete()` 가 지운 행 수를 돌려주지 않으면(확인) `selectOne` 로 먼저 있는지 보고 지운다. `save()` 의 연결 삽입(72행 `foreach ($categoryIds as $slot => $categoryId)`)은 그대로다.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/ProductsTest.php`, `tests/Shop/ListingTest.php` → PASS.

- [ ] **Step 3: 실패하는 테스트 — 관리자 화면**

`tests/Web/ShopAdminTest.php`:
- 상품 폼 테스트(`/admin/shop/products/new` GET, ~573행)에 `self::assertStringContainsString('data-yc-add-category', $form); self::assertStringNotContainsString('name="category2_id"', $form); self::assertStringContainsString('<template data-yc-category-row>', $form);` 를 더한다. 저장 POST 의 `productForm()` 에 `'extra_category_ids' => [(string) $seed['child']['id']]` 를 넘기고(적당한 기존 저장 호출 하나), 저장 뒤 수정 폼 GET 이 `name="extra_category_ids[]"` 셀렉트에 그 분류를 `selected` 로 보이는지 단언한다.
- 새 테스트:
```php
    /** 상품 목록에서 선택한 상품을 분류에 넣고 뺀다. 안내문에 바뀐 수와 건너뛴 수가 나온다. */
    #[DataProvider('connectionProvider')]
    public function testListCategorizeAndUncategorize(array $config): void
    {
        $this->setupShop($config);
        $seed = $this->seedProducts();   // 이 파일의 헬퍼(488행). 돌려주는 배열의 키를 보고 상품 id 둘($a, $b)과 대표 분류를 꺼낸다.
        $this->signIn(true);
        $event = $this->shop->categories->save(['name' => '봄 세일', 'parent_id' => '', 'active' => '1', 'list_columns' => '4', 'list_rows' => '5', 'image_width' => '200', 'image_height' => '0']);
        $a = (int) $seed['a']; $b = (int) $seed['b']; // seedProducts() 가 다른 키를 쓰면 그 키로
        $list = $this->body($this->get($this->app, '/admin/shop/products'));
        self::assertStringContainsString('name="category" form="yc-product-delete"', $list);
        self::assertStringContainsString('value="categorize"', $list); self::assertStringContainsString('value="uncategorize"', $list);
        self::assertStringNotContainsString('onsubmit=', $list);
        $response = $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'categorize', 'ids' => [(string) $a, (string) $b], 'category' => (string) $event]));
        self::assertSame('/admin/shop/products?op=add&changed=2&skipped=0', $response->getHeaderLine('Location'));
        self::assertStringContainsString('2개 상품을 분류에 넣었습니다', $this->body($this->get($this->app, '/admin/shop/products', ['op' => 'add', 'changed' => '2', 'skipped' => '0'])));
        self::assertSame((int) $event, (int) $this->shop->products->get($a)['categories'][2]['id']);
        $response = $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'uncategorize', 'ids' => [(string) $a], 'category' => (string) $event]));
        self::assertSame('/admin/shop/products?op=remove&changed=1&skipped=0', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey(2, $this->shop->products->get($a)['categories']);
        self::assertSame(422, $this->post($this->app, '/admin/shop/products', $this->csrf(['action' => 'categorize', 'ids' => [(string) $a], 'category' => '']))->getStatusCode());
    }
```
Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php` → FAIL.

- [ ] **Step 4: 폼·목록·컨트롤러·JS**

- `ProductFormController::defaults()`: `'category2_id' => '', 'category3_id' => ''` 를 `'extra_category_ids' => []` 로; 수정 값(80-81행) 을
```php
        $values['extra_category_ids'] = array_values(array_map(static fn (array $c): string => (string) $c['id'], array_filter($product['categories'], static fn (int $slot): bool => $slot >= 2, ARRAY_FILTER_USE_KEY)));
```
로. 오류 재표시 때 `$input['extra_category_ids']` 가 배열이면 그대로 쓴다(문자열이면 `[]`).
- `product_form.php` 37행을 다음으로(`$catSelect` 는 `category_id` 용으로만 남기고 두 번째 인자 제거해도 된다):
```php
    <div class="yc-fields"><?php $catSelect('category_id', true); ?></div>
    <fieldset class="fieldset<?= isset($errors['extra_category_ids']) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend">추가 분류 <span class="legend-hint">이벤트·기획전 분류에도 함께 보이게 합니다. <?= \GnuCms\Shop\Catalog\Products::MAX_EXTRA_CATEGORIES ?>개까지</span></legend>
      <div class="yc-category-rows" data-yc-categories>
        <?php foreach (is_array($values['extra_category_ids'] ?? null) ? $values['extra_category_ids'] : [] as $extraId): ?><?php $extraRow((string) $extraId) ?><?php endforeach ?>
      </div>
      <template data-yc-category-row><?php $extraRow('') ?></template>
      <button class="btn btn-xs" type="button" data-yc-add-category><?= $this->icon('plus', 14) ?> 분류 추가</button>
      <?php if (isset($errors['extra_category_ids'])): ?><p class="validator-hint"><?= $this->e($errors['extra_category_ids']) ?></p><?php endif ?></fieldset>
```
`$extraRow` 는 `$catSelect` 옆에 정의한다:
```php
$extraRow = function (string $selected) use ($categories): void { ?>
  <div class="yc-category-row" data-yc-category-row-item><select class="select select-bordered select-sm" name="extra_category_ids[]"><option value="">선택</option><?php foreach ($categories as $cid => $option): ?><option value="<?= $cid ?>" title="<?= $this->e($option['title']) ?>"<?= $selected === (string) $cid ? ' selected' : '' ?>><?= $this->e($option['text']) ?></option><?php endforeach ?></select><button class="btn btn-xs" type="button" data-yc-remove-category aria-label="이 추가 분류 제거">제거</button></div>
<?php };
```
- `youngcart-admin.js` 폼 클릭 위임(74행 `form.addEventListener('click', …)`) 안에
```js
    var addCategory=event.target.closest('[data-yc-add-category]');
    if(addCategory){
      var rows=form.querySelector('[data-yc-categories]'),tpl=form.querySelector('template[data-yc-category-row]');
      if(rows&&tpl){rows.appendChild(tpl.content.firstElementChild.cloneNode(true));rows.lastElementChild.querySelector('select').focus();}
    }
    var removeCategory=event.target.closest('[data-yc-remove-category]');
    if(removeCategory){removeCategory.closest('[data-yc-category-row-item]').remove();}
```
- `youngcart-admin.css`: `.yc-admin-page .yc-category-rows{display:grid;gap:.5rem;margin-bottom:.6rem}.yc-admin-page .yc-category-row{display:flex;gap:.5rem;align-items:center}.yc-admin-page .yc-category-row .select{flex:1;max-width:28rem}`.
- `products.php` 37-38행: 삭제 폼에서 `onsubmit` 과 hidden `action` 을 없애고 `data-yc-selection-form` 을 붙인다. 작업 막대의 `row-actions` 를
```php
<div class="row-actions"><label class="yc-list-category"><span class="sr-only">분류</span><select class="select select-bordered select-sm" name="category" form="yc-product-delete"><option value="">분류 선택</option><?php foreach ($categories as $cid => $label): ?><option value="<?= (int) $cid ?>"><?= $this->e($label) ?></option><?php endforeach ?></select></label><button class="btn btn-sm" type="submit" form="yc-product-delete" name="action" value="categorize">분류에 넣기</button><button class="btn btn-sm" type="submit" form="yc-product-delete" name="action" value="uncategorize">분류에서 빼기</button><button class="btn btn-sm btn-error btn-outline" type="submit" form="yc-product-delete" name="action" value="delete" data-yc-delete-selected>선택 삭제</button><button class="btn btn-sm btn-primary" type="submit" form="yc-product-list">목록 변경사항 저장</button></div>
```
로(`$categories` 는 `ProductController` 가 목록 화면에 이미 넘기는 `id => 이름` 목록. 65행 확인). 삭제 확인은 JS 로: `youngcart-admin.js` 의 목록 스크립트에 `var selection=document.querySelector('[data-yc-selection-form]'); if(selection){selection.addEventListener('submit',function(event){var action=event.submitter&&event.submitter.value; if(action==='delete'&&!confirm('선택한 상품을 삭제할까요? 이미지·옵션도 함께 지워집니다.')){event.preventDefault();return;} if((action==='categorize'||action==='uncategorize')&&!selection.querySelector('select[name=category]').value){event.preventDefault();alert('분류를 먼저 고르세요.');}});}` — 기존 `data-yc-delete-selected` 처리(선택 0개면 막기 등)가 있으면 그 안에서 `action` 을 함께 본다.
- `ProductController` 29-33행:
```php
                        if ($action === 'bulk') $products->bulk($rows, $data['actor']);
                        elseif ($action === 'delete') $products->bulkDelete(is_array($input['ids'] ?? null) ? $input['ids'] : []);
                        elseif ($action === 'categorize' || $action === 'uncategorize') {
                            $categoryId = Input::optionalId($input['category'] ?? '') ?? throw DomainError::validation(['category' => '분류를 고르세요.']);
                            $ids = is_array($input['ids'] ?? null) ? $input['ids'] : [];
                            if ($ids === []) throw DomainError::validation(['ids' => '상품을 선택하세요.']);
                            $result = $action === 'categorize' ? $products->addToCategory($ids, $categoryId) : $products->removeFromCategory($ids, $categoryId);
                            return $this->redirect($response, $data['admin_url'] . '/products?' . http_build_query(['op' => $action === 'categorize' ? 'add' : 'remove'] + $result));
                        }
                        else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
```
안내문(56행 `saved=1` 옆): `if (in_array($input['op'] ?? '', ['add', 'remove'], true)) { $changed = (int) ($input['changed'] ?? 0); $skipped = (int) ($input['skipped'] ?? 0); $data['notice'] = $changed . '개 상품을 분류에' . ($input['op'] === 'add' ? ' 넣었습니다.' : '서 뺐습니다.') . ($skipped > 0 ? ' ' . $skipped . '개는 ' . ($input['op'] === 'add' ? '이미 있어' : '대표 분류이거나 없어') . ' 건너뛰었습니다.' : ''); }`.

Run: `vendor/bin/phpunit --no-coverage tests/Web/ShopAdminTest.php`(종료 코드 확인), `tests/Shop` → PASS. 화면 확인: 상품 등록 폼(추가 분류 줄 없음 + 분류 추가 단추)과 상품 목록 작업 막대를 렌더링해 본다.

- [ ] **Step 5: 커밋**

```bash
git add src/Shop templates/default/shop/admin www/themes/default/youngcart-admin.js www/themes/default/youngcart-admin.css tests/Shop tests/Web/ShopAdminTest.php
git commit -m "feat: give products any number of extra categories and add or remove them from the list

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2: 분류 "메뉴에서 숨김"(30판), 문서, 전체 검증

**Files:**
- Modify: `src/Shop/Schema.php`(`categoriesDefinition()`, `migrate()` 칸 추가), `src/Db/Schema.php`(`VERSION`), `src/Shop/Catalog/Categories.php`(`save()` 51행 근처, `children()`), `src/Shop/Web/ShopController.php`(43, 62), `src/Shop/Web/CommerceController.php`(40), `templates/default/shop/admin/category_form.php`(35-38 체크 묶음), `templates/default/shop/admin/categories.php`(16 이름 칸), `docs/shop.md`
- Test: `tests/Shop/SchemaTest.php`, `tests/Shop/CategoriesTest.php`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopAdminTest.php`, `tests/Db/AligoSchemaTest.php`, `tests/Db/SchemaTest.php`

**Interfaces:**
- Produces: `yc_categories.menu_hidden SMALLINT NOT NULL DEFAULT 0`; `Shop\Schema::CATEGORY_COLUMNS = ['menu_hidden' => 'SMALLINT NOT NULL DEFAULT 0']`; `Categories::save()` 입력 `menu_hidden`('1'/'0'); `Categories::children(?int $parentId, bool $activeOnly, bool $menuOnly = false)`; `Db\Schema::VERSION = '30'`.

- [ ] **Step 1: 실패하는 테스트**

- `tests/Shop/SchemaTest.php`: 
```php
    /** 30판: 분류의 메뉴 숨김 칸. 없던 표에는 migrate() 가 넣는다. */
    #[DataProvider('connectionProvider')]
    public function testMigrateAddsTheMenuHiddenColumnToCategories(array $config): void
    {
        $this->setupShop($config);
        $db = $this->app->db();
        $db->execute('ALTER TABLE ' . $db->table('yc_categories') . ' DROP COLUMN menu_hidden');
        Schema::migrate($db); Schema::migrate($db);
        self::assertSame(0, (int) $this->category('의류')['menu_hidden']);
    }
```
- `tests/Shop/CategoriesTest.php`(`testTreeSlugsAndPaths` 끝에):
```php
        // 메뉴 숨김: 메뉴용 children() 에서만 빠지고 나머지는 그대로다.
        $hidden = $this->category('기획전', null, ['menu_hidden' => '1']);
        self::assertSame(1, (int) $hidden['menu_hidden']);
        self::assertContains('기획전', array_column($this->shop->categories->children(null, true), 'name'));
        self::assertNotContains('기획전', array_column($this->shop->categories->children(null, true, true), 'name'));
        self::assertSame('기획전', $this->shop->categories->bySlug('기획전')['name']);
```
- `tests/Web/ShopPublicTest.php`: 새 테스트 — 메뉴 숨김 최상위 분류와 숨김 하위 분류를 만들고 상품을 하나 붙인 뒤: 홈·분류 페이지의 상단 메뉴(`_header` 의 `href="/shop/c/…"`)에 숨김 분류 링크가 없고, 상위 분류 페이지의 하위 칩에도 없으며, `/shop/c/<숨김 슬러그>` 는 200 이고 상품이 보인다.
- `tests/Web/ShopAdminTest.php::testCategoryScreens`: 새 폼에 `name="menu_hidden"` 이 있고, `'menu_hidden' => '1'` 로 저장하면 `get()` 이 1, 목록에 `메뉴 숨김` 표시가 나온다.
- `tests/Db/AligoSchemaTest.php`: `TwentyNine`/`'29'` → `Thirty`/`'30'`; `tests/Db/SchemaTest.php` 의 `'29'` 두 곳 → `'30'`.

Run: `vendor/bin/phpunit --no-coverage tests/Shop/SchemaTest.php` → FAIL(칸 없음).

- [ ] **Step 2: 구현**

- `src/Shop/Schema.php`: `categoriesDefinition()` 의 `no_coupon SMALLINT NOT NULL DEFAULT 0,` 뒤에 `menu_hidden SMALLINT NOT NULL DEFAULT 0,`; 상수 `public const CATEGORY_COLUMNS = ['menu_hidden' => 'SMALLINT NOT NULL DEFAULT 0'];` (주석: 30판, 이벤트성 분류를 메뉴에서 감춘다); `migrate()` 에서 `migrateCategoryTree()` 호출 **뒤에** `foreach (self::CATEGORY_COLUMNS as $column => $definition) self::addColumn($db, 'yc_categories', $column, $definition);`. SQLite 재생성의 `$keep` 목록은 29판 이전 표에 없는 칸이므로 그대로 둔다.
- `src/Db/Schema.php`: `VERSION = '30'`; `migrateShop()` 주석에 `30판: 분류 메뉴 숨김(yc_categories.menu_hidden).` 을 잇는다.
- `Categories::save()`: `'no_coupon' => …` 다음에 `'menu_hidden' => Input::bool($input['menu_hidden'] ?? '0'),`. `apply_children` 의 필드 목록에는 넣지 않는다. `children()`:
```php
    public function children(?int $parentId, bool $activeOnly, bool $menuOnly = false): array
    {
        $rows = $this->store->select('SELECT * FROM ' . $this->store->table('yc_categories') . ' WHERE ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = ?')
            . ($activeOnly ? ' AND active = 1' : '') . ($menuOnly ? ' AND menu_hidden = 0' : '') . ' ORDER BY sort_order, name, id', $parentId === null ? [] : [$parentId]);
        return array_map($this->decode(...), $rows);
    }
```
- `ShopController` 43·62행과 `CommerceController` 40행: `children(null, true, true)`, `children((int) $category['id'], true, true)`.
- `category_form.php` 체크 묶음(37행 `no_coupon` 줄 뒤): `<label class="label cursor-pointer"><input type="hidden" name="menu_hidden" value="0"><input class="checkbox checkbox-sm" type="checkbox" name="menu_hidden" value="1"<?= $v('menu_hidden') === '1' ? ' checked' : '' ?>> 메뉴에서 숨김 <span class="legend-hint">상단 메뉴·바로가기·하위 분류 칩에 안 보이고 주소·링크·배너로만 들어갑니다</span></label>`. `CategoryController::defaults()` 에 `'menu_hidden' => '0'`.
- `categories.php` 이름 칸의 `<small>` 안(슬러그 뒤)에 `<?php if ((int) $row['menu_hidden'] === 1): ?> · <span class="badge badge-ghost badge-xs">메뉴 숨김</span><?php endif ?>`.
- `ShopTestCase::category()` 의 `$extra +` 병합은 이미 `menu_hidden` 을 넘길 수 있다.

Run(각각): `tests/Shop`, `tests/Web/ShopPublicTest.php`, `tests/Web/ShopAdminTest.php`, `tests/Db` → PASS.

- [ ] **Step 3: 문서, 전체 스위트, MySQL, 커밋**

`docs/shop.md`: 분류 절에 "메뉴에서 숨김" 문단(이벤트·기획전 분류: 메뉴·바로가기·하위 칩에서 빠지고 주소·배너 링크로 들어간다; 상품 목록의 "분류에 넣기"로 상품을 모은다). 상품 절: "추가 분류(최대 2개)" 문구를 "추가 분류는 개수 제한 없이(20개까지)" 로, 상품 목록의 분류 넣기·빼기 설명(대표 분류는 빼지 않는다) 한 문단. 대응표 `ca_id2`, `ca_id3` 행: `slot 2 이상(제한 없음)`.

```bash
vendor/bin/phpunit --no-coverage; echo exit=$?
DB=gnucms_pc_$(date +%s); mysql -uroot -e "CREATE DATABASE $DB CHARACTER SET utf8mb4;"
for p in tests/Db tests/Shop/SchemaTest.php tests/Shop/ProductsTest.php tests/Shop/CategoriesTest.php; do TEST_MYSQL_DSN="mysql:host=127.0.0.1;dbname=$DB;charset=utf8mb4" TEST_MYSQL_USER=root vendor/bin/phpunit --no-coverage $p | tail -1; done
mysql -uroot -e "DROP DATABASE $DB;"
git add src/Shop src/Db/Schema.php templates/default/shop tests docs/shop.md
git commit -m "feat: hide event categories from the shop menu

Schema 30 adds yc_categories.menu_hidden; hidden categories stay reachable by
address, breadcrumb and search but leave the header menu, home shortcuts and
child chips.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```
Expected: 전체 OK, MySQL 네 스위트 OK.

---

## 자체 검토

- **설계 대조.** (1) 폼 제한 없이 → 작업 1 Step 1-4. (2) 목록 일괄 → 작업 1 Step 1-4. (3) 메뉴 숨김 → 작업 2. 문서·검증 → 작업 2 Step 3.
- **자리표시자.** 작업 1 Step 3 의 `seedProducts()` 는 이 파일의 기존 헬퍼이며 없을 때의 대안을 적었다. `Store::delete()` 반환값 확인 지시가 있다.
- **이름 일치.** `extra_category_ids`, `MAX_EXTRA_CATEGORIES`, `addToCategory`/`removeFromCategory`, `categorize`/`uncategorize`, `op/changed/skipped`, `menu_hidden`, `children(?int, bool, bool)`, `CATEGORY_COLUMNS` — 작업 간 동일하다.
