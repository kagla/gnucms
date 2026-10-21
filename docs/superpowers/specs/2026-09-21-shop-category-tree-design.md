# 쇼핑몰 분류: 무한 단계 트리와 슬러그 주소 — 설계

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

> 현재 DB 지원 범위 (2026-10-21): GNUCMS는 **MySQL과 MariaDB를 지원**하며 SQLite·PostgreSQL은 지원하지 않습니다. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 아래 SQLite 코드·시험 예시는 현재 설치·개발 절차에 적용하지 않습니다. 현재 기준은 [데이터베이스 지원 정책](../../database-support.md)을 따릅니다.

2026-09-21. 사용자 요청: "5단계 2자 코드 방식 말고 흔한 방식으로 무한 분류", "주소는 id 대신 슬러그".

## 1. 목표와 범위

영카트5에서 가져온 분류 코드 규칙(단계당 2자, `10` → `1010` → `101010`, 최대 5단계)을 버리고,
부모 id 로 이어지는 흔한 트리로 바꾼다. 분류는 이름과 상위 분류만 골라 만들고, 단계 제한은 두지
않는다(화면과 검증은 10단계까지). 공개 주소는 슬러그(`/shop/c/셔츠`)다. 상위 분류를 옮길 수 있다.
기존 사이트의 분류와 상품 연결, 옛 주소는 그대로 살아 있어야 한다.

범위 밖: 슬러그 변경 이력(옛 슬러그 자동 넘김), 끌어서 정렬, 분류 이미지, 상품 슬러그 규칙 변경.

## 2. 데이터

### 2.1 `yc_categories` (29판)

| 칸 | 내용 |
|---|---|
| `id`, `parent_id`(NULL = 최상위), `name`, `sort_order`, `active`, `no_coupon`, `head_html`, `tail_html`, `list_columns`, `list_rows`, `image_width`, `image_height`, `extra`, `created_at`, `updated_at` | 그대로 |
| `depth` | 그대로. 최상위 1. `path` 의 id 개수와 같다 |
| `slug VARCHAR(200) NOT NULL UNIQUE`(바이너리 정렬) | 공개 주소. 상품 슬러그와 같은 규칙(`Input::slug()`): 공백과 `/ ? # % " ' \` < > \\` 를 `-` 로, 연속 `-` 는 하나로, 앞뒤 `-` 제거, 190자 이내. 한글·대소문자는 그대로. 비우면 이름에서 만들고, 겹치면 `-2`, `-3` 을 붙인다 |
| `path VARCHAR(255) NOT NULL` | 조상부터 자기까지의 id 를 `/1/5/12/` 처럼 이은 값. 하위 전체 조회는 `path LIKE '/1/5/%'` 한 번이다. 인덱스 `yc_cat_path` |
| `legacy_code VARCHAR(10) NULL` | 옛 2자 코드. 29판 이전에 만든 분류만 값이 있고 새 분류는 NULL. 옛 주소 `/shop/list?ca=코드` 를 새 주소로 넘길 때만 쓴다. 고유 인덱스 `yc_cat_legacy_code` |
| `code` | 없앤다 |

인덱스: 기존 `yc_cat_parent`, `yc_cat_order` 유지 + `yc_cat_path`, `yc_cat_legacy_code`. 상품 연결(`yc_product_categories.category_id`)은 id 라서 바뀌지 않는다.

### 2.2 29판 이전(기존 사이트)

`Shop\Schema::migrate()` 가 `yc_categories` 에 `slug` 칸이 없으면 다음을 한 트랜잭션으로 한다.

1. 옛 행을 모두 읽어 PHP 에서 `path`(부모 사슬), `depth`, `slug`(이름 → `Input::slug()`, 겹치면 `-2`…, 그래도 비면 `c` + id), `legacy_code`(= `code`) 를 정한다.
2. 표를 새 정의로 다시 만든다. SQLite 는 `ALTER COLUMN`·UNIQUE 칸 `DROP` 을 못 하므로 새 표를 만들어 옮기고 이름을 바꾼다(`CREATE yc_categories_new` → `INSERT … SELECT` → `DROP yc_categories` → `RENAME`). MySQL 은 `ALTER TABLE … DROP COLUMN code, ADD COLUMN slug …, ADD COLUMN path …, ADD COLUMN legacy_code …` 로 한다.
3. 인덱스를 다시 만든다.

새 설치는 CREATE 문에 새 정의가 그대로 들어간다. `migrate()` 는 멱등이다(`slug` 칸이 있으면 이 단계를 건너뛴다). 코어 `Db\Schema::VERSION` 은 `'29'`. `TABLES` 수는 그대로(32).

## 3. 서비스 `Shop\Catalog\Categories`

- `save(array $input, ?int $id = null): int` — 입력: `name`(필수), `slug`(선택), `parent_id`('' 또는 없음 = 최상위), 나머지 설정은 지금과 같다. 검증: 상위 분류가 없으면 422 `parent_id`; 자기 자신이나 자기 하위 분류를 상위로 고르면 422 "자기 하위 분류 아래로 옮길 수 없습니다"; 옮긴 뒤 하위 어느 분류든 10단계를 넘으면 422 "분류는 10단계까지입니다"; 슬러그가 다른 분류와 겹치면 422 `slug`.
  - 새 분류: 삽입 뒤 `path = 부모.path + id + '/'`, `depth = 부모.depth + 1`(최상위는 `/id/`, 1)로 갱신.
  - 상위 변경(이동): 자기와 하위 전체의 `path` 앞부분을 바꾸고 `depth` 를 차이만큼 더한다(`UPDATE … SET path = REPLACE(path, ?, ?), depth = depth + ? WHERE path LIKE ?`).
  - `apply_children` 은 `path LIKE '자기.path%' AND id <> 자기` 로 하위에 적용한다.
  - `suggestCode()` 는 없앤다. `code` 를 보내면 무시한다.
- `get(int)`, `bySlug(string)`, `byLegacyCode(string)`.
- `tree()` — 부모 우선 DFS, 형제는 `sort_order`, `name` 순, 각 행에 `product_count`. `options()` — 선택 상자용 `id => '의류 > 셔츠'`. `optionsExcluding(int $id)` — 자기와 하위를 뺀 목록(수정 화면의 상위 분류 선택용).
- `ancestors(array $category): array` — `path` 의 id 순서대로 행을 돌려준다(빵부스러기). `children(?int $parentId, bool $activeOnly)`.
- 하위 전체를 거는 SQL 조각은 한 곳(`Categories::subtreeWhere(array $category, string $alias): array{sql, params}`)에서 만들고 `Listing::category()`, `Listing::search()`, `Products::list()`(관리자 필터), `Products::search()` 가 쓴다.
- `delete()`, `bulk()`, `count()` 는 하는 일이 같다. `bulk()` 는 이름·순서·판매·배치·이미지 크기만 바꾸며 상위는 옮기지 않는다.

## 4. 공개 주소와 화면

| 주소 | 동작 |
|---|---|
| `GET /shop/c/{slug}` | 분류 목록. 이름 붙은 라우트 `shop.category`. 슬러그는 `[^/]+`, URL 디코딩 뒤 `bySlug()`. 없거나 판매 꺼짐이면 404 |
| `GET /shop/list?ca=값` | 옛 주소. `값` 이 `legacy_code` 나 슬러그와 맞으면 `/shop/c/{slug}` 로 301, 아니면 404. 정렬·쪽 매개변수는 그대로 붙여 넘긴다 |
| 검색 `?ca=` | 슬러그. 분류 facet 링크와 하위 필터가 슬러그·`path` 를 쓴다 |

템플릿: 상단 메뉴(`_header`), 메인 바로가기(`index`), 빵부스러기(`_breadcrumb`, `ancestors()`), 하위 분류 칩·정렬·쪽 링크(`list`), 검색 facet(`search`), 메인 배너 기본 링크(`HomeBanner`)가 모두 `/shop/c/{slug}` 를 쓴다. 상품 상세의 경로도 대표 분류의 `ancestors()` 다. 링크는 `rawurlencode(slug)` 로 찍는다.

## 5. 관리자 화면

- 분류 목록(`/admin/shop/categories`): 지금처럼 들여쓴 트리(`depth`). 코드 열 대신 슬러그를 이름 아래 작게 보인다. "하위 추가" 는 `categories/new?parent=<id>`. 일괄 저장은 그대로.
- 분류 폼(`categories/new`, `categories/edit`): 이름, 슬러그(비우면 이름에서 만든다는 안내), 상위 분류(선택 상자, 수정 때는 자기와 하위 제외, `?parent=` 로 미리 고름), 순서, 배치·이미지 크기, 판매/쿠폰/하위 적용, 목록 위·아래 HTML(편집기), 여분필드. 코드 칸은 없다. 상위 분류를 바꾸고 저장하면 옮겨진다.
- 상품 폼의 분류 선택(`options()`)은 그대로다. 상품 목록 필터의 분류 선택은 id 를 값으로 쓰고 하위 전체를 건다.

## 6. 테스트

- 스키마: 새 설치에 `slug`·`path`·`legacy_code` 와 인덱스; 28판 모양(코드 있음)으로 만든 표에 코드 분류 `10`, `1010`, `20` 을 넣고 `migrate()` → `path`·`depth`·`slug`(중복 이름은 `-2`)·`legacy_code` 가 채워지고 `code` 칸이 없으며 상품 연결이 그대로; 두 번 돌려도 같다. SQLite·MySQL 둘 다.
- 서비스: 최상위·하위 생성과 `path`; 슬러그 자동 생성·중복 처리·직접 입력·겹침 거절; 없는 상위 거절; 이동(하위 `path`·`depth` 갱신), 자기 하위로 이동 거절, 10단계 초과 거절; `apply_children`; `ancestors`; `tree` 순서; 삭제 보호는 그대로.
- 공개: `/shop/c/{슬러그}` 가 하위 분류 상품까지 보임, 한글 슬러그, 옛 `?ca=1010` 301, 슬러그 `?ca=` 301, 모르는 값 404, 메뉴·빵부스러기·facet 링크 형식.
- 관리자: 코드 칸 없음, 상위 분류 선택에 자기·하위 없음, 수정으로 이동, 목록에 슬러그, 상품 필터 id.
- 기존 테스트의 `category('셔츠', '10')` 헬퍼는 상위 id 를 받도록 바꾼다. 브라우저 픽스처의 코드도 바꾼다.

## 7. 문서

`docs/shop.md`: 주소 표(`/shop/c/슬러그`, 옛 주소 넘김), 분류 절 전면 수정(코드 설명 삭제, 슬러그·상위 분류·이동·단계 상한), 영카트5 대응표(`ca_id` → `legacy_code`, 주소는 `slug`), 상품 절의 필터 설명. AGENTS.md 34행의 분류 설명.

## 8. 기존 사이트

29판이 첫 요청에서 돈다. SQLite 는 갱신 전 백업이 자동이다. 옛 링크(`/shop/list?ca=1010`)는 `legacy_code` 로 새 주소에 닿는다. 영카트5에서 가져온 코드 체계는 더 이상 새 분류에 쓰이지 않는다.
