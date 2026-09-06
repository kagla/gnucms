# apiboard 설계

- 작성일: 2026-08-26
- 상태: 승인 대기

## 1. 배경과 목표

여러 프로젝트에 반복해서 붙일 수 있는 게시판을 만든다. 특정 프레임워크나 특정
DB에 묶이지 않고, 저가형 공유 PHP 호스팅에 FTP로 폴더째 올리면 동작해야 한다.

목표:

1. **DB 무관** — SQLite, MySQL/MariaDB에서 동일하게 동작한다. 말이 아니라 테스트로 증명한다.
2. **무한 게시판** — 게시판을 몇 개 만들든 게시글 테이블은 하나다. 테이블이 늘어나지 않는다.
3. **무한 댓글/대댓글** — 깊이 제한이 없다.
4. **API 우선** — JSON API가 본체다. 관리자 화면은 그 API의 첫 클라이언트다.
5. **의존성 0** — 런타임에 Composer 패키지를 쓰지 않는다.

## 2. 제약

- PHP 7.4 이상. 7.4에서 동작해야 하므로 enum, readonly, 생성자 프로퍼티 승격을 쓰지 않는다.
- 런타임 의존성 없음. 배포물에 `vendor/` 가 없다.
- CLI 접근을 가정하지 않는다. 설치를 웹으로 할 수 있어야 한다.
- `mod_rewrite` 를 가정하지 않는다. 없으면 쿼리스트링 라우팅으로 폴백한다.
- PDO 드라이버는 `pdo_sqlite`, `pdo_mysql` 중 쓰는 것만 있으면 된다.
- MySQL 5.7 을 지원한다. 따라서 재귀 CTE(`WITH RECURSIVE`)와 `JSON` 타입 함수를 쓰지 않는다.

## 3. 아키텍처

### 3.1 디렉터리

```
apiboard/
  public/
    index.php      프론트 컨트롤러 (API 전부)
    admin.php      관리자 화면 (단일 HTML + vanilla JS)
    install.php    스키마 생성 (설치 후 삭제)
    .htaccess
  storage/
    uploads/       첨부 저장소 (문서 루트 밖)
  src/
    Db/            Connection, Dialect/{SqliteDialect,MysqlDialect}, Schema
    Auth/          TokenVerifier, TokenIssuer, Identity, Acl
    Repository/    BoardRepository, PostRepository, CommentRepository
    Service/       BoardService, PostService, CommentService, AttachmentService
    Http/          Router, Request, Response, ApiError
    autoload.php
  config/
    config.php         실제 설정 (git 제외)
    config.sample.php  샘플
  tests/
  docs/
```

첨부 저장소를 `public/` 밖에 두는 이유: `.htaccess` 로 PHP 실행을 막는 방식은 nginx 호스팅에서
통하지 않는다. 문서 루트 밖에 두고 다운로드 엔드포인트로만 흘려보내면 서버 종류와 무관하게 안전하고,
비밀글 첨부에 권한 검사를 걸 수 있다는 이점이 따라온다.

### 3.2 계층

```
public/index.php
      -> Http\Router          경로 -> 핸들러
      -> Auth\TokenVerifier   토큰 -> Identity
      -> Service\*            권한 판정, 검증, 업무 규칙
      -> Repository\*         SQL. 3개 DB 공통 문법만 사용
      -> Db\Connection        PDO 래퍼
      -> Db\Dialect\*         방언 차이 흡수 (DDL, 인용, lastInsertId)
```

Repository 는 배열을 주고받고 도메인 객체를 만들지 않는다. Service 는 SQL 을 모른다.
이 경계 덕분에 Service 는 SQLite 인메모리로 빠르게 테스트하고, Repository 는 지원 DB 에
반복 실행하는 식으로 테스트 비용을 나눌 수 있다.

### 3.3 라우팅

`public/index.php` 가 `PATH_INFO` 를 먼저 읽고, 비어 있으면 `?p=/boards/free/posts` 를 읽는다.
`.htaccess` 는 있으면 좋은 것이지 필수가 아니다.

## 4. 데이터 모델

테이블은 3개다: `boards`, `posts`, `comments`. 첨부는 `posts.attachments` 에 JSON 문자열로 넣는다.
첨부는 항상 글과 함께 읽고 함께 지우므로 조인할 이유가 없다.

### 4.1 방언 치환자

DDL 에서 다음 세 자리만 방언별로 치환한다.

| 치환자 | SQLite | MySQL |
|---|---|---|
| `{AUTO_PK}` | `INTEGER PRIMARY KEY AUTOINCREMENT` | `BIGINT AUTO_INCREMENT PRIMARY KEY` |
| `{DATETIME}` | `TEXT` | `DATETIME` |
| `{TEXT}` | `TEXT` | `LONGTEXT` |

그 외 방언 차이는 세 가지뿐이다.

- 식별자 인용: SQLite 는 `"x"`, MySQL 은 `` `x` ``
- `CREATE TABLE` 뒤에 붙는 문자열: MySQL 만 엔진/문자셋이 필요하다 (`ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`)
- 접속 직후 세션 설정: 시간대를 UTC 로 맞추고, MySQL 은 잘림을 오류로 만든다 (`STRICT_ALL_TABLES`)

날짜는 항상 UTC `Y-m-d H:i:s` 문자열로 저장한다. 지원 DB 모두 이 형식을 사전순 정렬해도
시간순과 일치하므로 `ORDER BY created_at` 이 안전하다.

### 4.2 boards

```sql
CREATE TABLE boards (
  id            {AUTO_PK},
  board_key     VARCHAR(50)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  description   {TEXT}       NULL,
  categories    {TEXT}       NULL,      -- JSON 배열
  managers      {TEXT}       NULL,      -- JSON 배열 (게시판 관리자 author_id)
  perm_read     VARCHAR(10)  NOT NULL DEFAULT 'guest',
  perm_write    VARCHAR(10)  NOT NULL DEFAULT 'member',
  perm_comment  VARCHAR(10)  NOT NULL DEFAULT 'member',
  use_secret    SMALLINT     NOT NULL DEFAULT 0,
  use_file      SMALLINT     NOT NULL DEFAULT 0,
  use_category  SMALLINT     NOT NULL DEFAULT 0,
  per_page      INTEGER      NOT NULL DEFAULT 20,
  sort_order    INTEGER      NOT NULL DEFAULT 0,
  created_at    {DATETIME}   NOT NULL,
  updated_at    {DATETIME}   NOT NULL
);
CREATE UNIQUE INDEX ux_boards_key ON boards (board_key);
```

`perm_*` 는 `guest` | `member` | `admin` 세 값을 가진다. ENUM 을 쓰지 않는 이유는 문법이 지원 DB마다
다르고, 값을 늘릴 때 DDL 변경이 필요하기 때문이다. `VARCHAR` 에 애플리케이션 검증으로 둔다.

분류는 별도 테이블이 아니라 `boards.categories` JSON 배열이고, 글은 `posts.category` 에
이름 문자열을 그대로 갖는다. 테이블로 빼면 이름 변경이 글에 자동으로 전파되지만, 이
프로젝트에는 마이그레이션 장치가 없어서(`Schema::create()` 는 설치 때 한 번만 돈다)
이미 설치된 곳에 4번째 테이블을 올릴 방법이 없다. 그 유일한 실익은 아래의 명시적
이름 변경으로 대신한다.

**분류 이름 변경.** `PATCH /boards/{key}` 는 `categories` 와 함께 `category_renames`
(`{"옛 이름": "새 이름"}`)를 받는다. 짝이 주어진 분류만 그 게시판의 글에서 함께 바뀌며,
설정 변경과 글 이동은 한 트랜잭션이다. 서버가 옛 목록과 새 목록을 비교해 짐작하지
않는 이유는, `["질문","잡담"]` 이 `["문의","잡담"]` 이 되었을 때 이름을 고친 것인지
지우고 새로 만든 것인지 목록만으로는 구분할 수 없기 때문이다. 짐작이 틀리면 남의 글이
조용히 다른 분류로 옮겨 간다. 짝이 없으면 지금까지처럼 목록만 갈리고 글의 분류
문자열은 그대로 남는다.

검증 규칙은 셋이다. 옛 이름은 현재 목록에 있어야 하고, 새 목록에서는 빠져 있어야 하며
(남아 있으면 이름 변경이 아니다), 새 이름은 새 목록에 있어야 한다. 여럿을 한 이름으로
보내는 병합은 허용한다.

`categories`, `managers` 를 JSON 문자열로 두는 이유: MySQL 5.7 의 JSON 함수에 의존하지 않기 위해
DB 는 이 값을 문자열로만 취급하고, 파싱과 검증은 전적으로 PHP 가 한다.

### 4.3 posts

```sql
CREATE TABLE posts (
  id             {AUTO_PK},
  board_id       BIGINT       NOT NULL,
  category       VARCHAR(50)  NULL,
  title          VARCHAR(200) NOT NULL,
  content        {TEXT}       NOT NULL,
  author_id      VARCHAR(64)  NULL,     -- NULL 이면 비회원 글
  author_name    VARCHAR(100) NOT NULL,
  guest_password VARCHAR(255) NULL,     -- password_hash(), 비회원 글만
  is_notice      SMALLINT     NOT NULL DEFAULT 0,
  is_secret      SMALLINT     NOT NULL DEFAULT 0,
  view_count     INTEGER      NOT NULL DEFAULT 0,
  comment_count  INTEGER      NOT NULL DEFAULT 0,
  attachments    {TEXT}       NULL,     -- JSON 배열
  created_at     {DATETIME}   NOT NULL,
  updated_at     {DATETIME}   NOT NULL,
  deleted_at     {DATETIME}   NULL
);
CREATE INDEX ix_posts_list ON posts (board_id, deleted_at, is_notice, id);
CREATE INDEX ix_posts_category ON posts (board_id, category);
```

`author_id` 를 정수가 아닌 문자열로 두는 것이 중요하다. 호스트 앱의 사용자 ID 가 정수일지
UUID 일지 이메일일지 모르므로 게시판은 불투명한 문자열로만 취급한다. 외래키 제약도 걸지 않는다 —
걸 대상 테이블이 같은 DB 에 없을 수 있다.

`comment_count` 는 비정규화 값이다. 목록에서 글마다 댓글을 세는 쿼리를 없애기 위해 둔다.
댓글 생성/삭제 시 같은 트랜잭션에서 갱신한다.

### 4.4 comments

```sql
CREATE TABLE comments (
  id             {AUTO_PK},
  board_id       BIGINT       NOT NULL,
  post_id        BIGINT       NOT NULL,
  parent_id      BIGINT       NULL,
  depth          SMALLINT     NOT NULL DEFAULT 0,
  content        {TEXT}       NOT NULL,
  author_id      VARCHAR(64)  NULL,
  author_name    VARCHAR(100) NOT NULL,
  guest_password VARCHAR(255) NULL,
  is_secret      SMALLINT     NOT NULL DEFAULT 0,
  created_at     {DATETIME}   NOT NULL,
  updated_at     {DATETIME}   NOT NULL,
  deleted_at     {DATETIME}   NULL
);
CREATE INDEX ix_comments_post ON comments (post_id, id);
```

트리는 `parent_id` 만으로 표현한다. materialized path 를 쓰지 않는 이유:

- `path` 는 파생 데이터라 댓글 이동/하드 삭제/복구 때마다 하위 전체를 재계산해야 하고,
  재계산이 실패하면 트리가 조용히 깨진다.
- 읽기 성능은 사실상 동일하다. 둘 다 `WHERE post_id = ?` 한 번이고 인덱스도 하나다.
  `path` 가 이기는 구간은 글 하나에 댓글이 1000개를 넘어 댓글 자체를 페이징할 때뿐이다.
- 쓰기는 `parent_id` 가 낫다. INSERT 한 번이면 끝난다. `path` 는 DB 왕복이 두 번이다.
- 깊이 제한이 없다. `path` 는 MySQL 인덱스 길이 제약 때문에 사실상 25단계쯤에서 막힌다.

나중에 댓글 폭주가 실제로 발생하면 `path` 컬럼을 추가하고 한 번 채우는 마이그레이션으로 전환할 수 있다.

`depth` 는 트리 구조가 아니라 렌더링 편의용 캐시다. 삽입 시 부모의 `depth + 1` 로 한 번 정한 뒤
바뀌지 않는다.

## 5. 인증

호스트 앱이 공유 시크릿으로 HS256 JWT 를 발급하고, 게시판은 `hash_hmac` + `hash_equals` 로
검증만 한다. 라이브러리 없이 구현할 수 있고, 호스트 쪽은 어떤 언어의 JWT 라이브러리든 쓸 수 있다.

```json
{ "sub": "user-123", "name": "홍길동", "admin": true, "iat": 1756... , "exp": 1756... }
```

- `sub` — 호스트의 사용자 ID. `posts.author_id` 에 그대로 들어간다.
- `name` — 표시용 이름. `posts.author_name` 의 기본값.
- `admin` — 전역 관리자 여부. 클레임 이름을 `adm` 으로 줄이지 않는다. 아끼는 것은 2바이트인데
  연동하는 사람이 의미를 의심하는 비용이 그보다 크다.

전달은 `Authorization: Bearer <token>`. 토큰이 없으면 게스트다.

`exp` 는 필수이며 검증한다. 시계 오차 허용은 60초.

### 5.1 CORS

브라우저가 호스트 앱 도메인에서 게시판 API 를 직접 호출하므로 교차 출처 요청이 되고,
`Authorization` 헤더 때문에 프리플라이트(`OPTIONS`)가 발생한다. 허용 출처는
`config.cors.allowed_origins` 에 정확히 일치하는 문자열로 화이트리스트한다.
와일드카드(`*`)는 지원하지 않는다 — 토큰을 헤더로 받는 API 에서 모든 출처를 허용할 이유가 없다.
허용되지 않은 출처의 프리플라이트에는 403 을 준다.

## 6. 권한

### 6.1 판정 순서

Acl 은 아래 순서로 단락 평가한다.

1. `identity.admin === true` → **전역 관리자**. 모든 권한. 게시판 생성/삭제 포함.
2. `identity.sub` 가 `board.managers` 에 있음 → **게시판 관리자**. 그 게시판의 글/댓글에 대해
   수정, 삭제, 복구, 공지 지정. 게시판 설정 변경과 다른 게시판은 불가.
3. `resource.author_id === identity.sub` (둘 다 non-null) → **본인**.
4. `resource.author_id IS NULL` 이고 요청에 담긴 비밀번호가 `guest_password` 와 일치 → **비회원 본인**.
5. 그 외 → `board.perm_*` 설정에 따른다.

3번과 4번이 같은 자리를 차지하는 것이 설계의 핵심이다. 소유 증명 수단만 다를 뿐 같은 권한이다.

### 6.2 행위별 요구 권한

| 행위 | 요구 |
|---|---|
| 게시판 목록/조회 | 누구나 |
| 게시판 생성/수정/삭제 | 전역 관리자 |
| 게시판 관리자 지정/해제 | 전역 관리자 |
| 글 목록/조회 | `perm_read` |
| 글 작성 | `perm_write` |
| 글 수정/삭제 | 본인 또는 비회원 본인 또는 관리자 |
| 글 복구 | 관리자 |
| 공지 지정/해제 | 관리자 |
| 댓글 작성 | `perm_comment` |
| 댓글 수정/삭제 | 본인 또는 비회원 본인 또는 관리자 |
| 비밀글 열람 | 작성자 또는 비회원 본인 또는 관리자 |

`perm_read = guest` 이면 토큰 없이 읽을 수 있고, `member` 면 유효한 토큰이 필요하며,
`admin` 이면 관리자만 읽는다.

### 6.3 부트스트랩 관리자

호스트 앱이 아직 없거나 게시판만 단독으로 띄울 때를 위해 `config.php` 에 관리자 하나를 둔다.

```php
'bootstrap_admin' => [
    'id'            => 'root',
    'password_hash' => '$2y$10$...',   // password_hash() 로 생성
],
```

`POST /auth/login` 에 이 자격을 제출하면 `admin: true` 인 토큰을 발급한다. 호스트 앱을 붙인
뒤에는 `bootstrap_admin` 을 `null` 로 두어 이 경로를 완전히 닫는다.

관리자 진입점이 두 개가 되는 것이 아니다. 진입점은 서명된 토큰 하나이고, 그 토큰을 만드는
방법이 두 가지일 뿐이다. 권한 판정 코드는 토큰의 출처를 알지 못한다.

## 7. API

모든 응답은 `application/json; charset=utf-8`.

```
POST   /auth/login                  부트스트랩 관리자 로그인 -> 토큰

GET    /boards                      게시판 목록
POST   /boards                      생성                (전역 관리자)
GET    /boards/{key}
PATCH  /boards/{key}                설정 변경            (전역 관리자)
DELETE /boards/{key}                                    (전역 관리자)

GET    /boards/{key}/posts          ?page&per_page&q&category&include_deleted
POST   /boards/{key}/posts
GET    /posts/{id}                  본문 + 첨부 목록
PATCH  /posts/{id}
DELETE /posts/{id}                  소프트 삭제
POST   /posts/{id}/restore                              (관리자)
GET    /posts/{id}/files/{index}    첨부 다운로드 (권한 검사 후 스트리밍)

GET    /posts/{id}/comments         전체를 트리로 반환
POST   /posts/{id}/comments         body: { parent_id, content, ... }
PATCH  /comments/{id}
DELETE /comments/{id}

POST   /uploads                     multipart -> 서명된 첨부 디스크립터
POST   /maintenance/gc              고아 첨부 정리      (전역 관리자)
```

### 7.1 목록 응답

```json
{
  "data": [ { "id": 12, "title": "...", "author_name": "홍길동",
              "comment_count": 3, "is_notice": false, "created_at": "2026-08-26 01:02:03" } ],
  "page": 1, "per_page": 20, "total": 137, "total_pages": 7
}
```

공지는 페이지와 무관하게 항상 목록 상단에 별도로 붙는다. `total` 에는 포함하지 않는다.

목록은 기본적으로 삭제된 글을 뺀다. 관리자가 `include_deleted=1` 을 주면 포함하며, 항목에
`deleted: true` 가 붙는다. 관리자 화면의 복구 기능이 이것에 의존한다 — 삭제한 글을 다시 볼
방법이 없으면 복구 엔드포인트에 도달할 수단이 없다. 관리 권한이 없는 요청자가 이 값을 주면
오류가 아니라 조용히 무시한다.

검색 `q` 는 제목과 본문에 대한 `LIKE '%...%'` 다. MySQL `MATCH AGAINST` 는
이식되지 않고 저가 호스팅에서 인덱스를 만들 권한도 불확실하므로 쓰지 않는다. `%` 와 `_` 는 이스케이프한다.

### 7.2 댓글 트리 응답

`WHERE post_id = ? ORDER BY id` 한 번으로 전부 읽고 PHP 가 중첩 구조로 조립한다.

```json
{ "data": [
  { "id": 1, "content": "...", "author_name": "가", "depth": 0, "children": [
    { "id": 3, "content": "...", "author_name": "나", "depth": 1, "children": [] }
  ] }
] }
```

삭제된 댓글에 살아 있는 자식이 있으면 `"deleted": true` 와 자리표시자 문구로 남긴다.
자식이 없으면 응답에서 제외한다.

### 7.3 첨부

1. `POST /uploads` 로 파일을 올리면 `storage/uploads/YYYY/MM/<32hex>` 에 저장하고,
   메타데이터에 HMAC 서명을 붙인 디스크립터를 돌려준다.
2. 글을 저장할 때 `attachments` 에 그 디스크립터 배열을 넣는다. 서버는 서명을 검증한 뒤
   JSON 으로 `posts.attachments` 에 저장한다.

서명을 쓰는 이유는 임시 업로드를 추적할 테이블을 만들지 않기 위해서다. 서명이 유효하면
그 파일은 이 서버가 방금 받아들인 파일이라는 뜻이므로 상태를 보관할 필요가 없다.

다운로드는 `GET /posts/{id}/files/{index}` 로만 가능하다. 글의 읽기 권한을 검사한 뒤
`Content-Disposition: attachment` 로 스트리밍한다. 파일 경로가 URL 에 노출되지 않고
비밀글 첨부도 보호된다.

허용 확장자와 최대 크기는 `config.php` 에 둔다. 기본값은 화이트리스트 방식이다.

어느 글에도 연결되지 않은 파일(업로드 후 글 저장을 안 한 경우)은 `POST /maintenance/gc` 로
정리한다. 관리자 화면에 버튼으로 노출한다. cron 은 저가 호스팅에서 보장되지 않으므로 쓰지 않는다.

### 7.4 조회수

`GET /posts/{id}` 에서 증가시킨다. 요청자가 작성자 본인이면 증가시키지 않는다.
동일 방문자 중복 집계 방지는 하지 않는다 — 상태 저장이 필요하고 정확도 이득이 작다.

### 7.5 오류

```json
{ "error": { "code": "FORBIDDEN", "message": "권한이 없습니다.", "details": {} } }
```

| code | HTTP |
|---|---|
| `UNAUTHORIZED` | 401 |
| `FORBIDDEN` | 403 |
| `NOT_FOUND` | 404 |
| `VALIDATION_FAILED` | 422 |
| `PAYLOAD_TOO_LARGE` | 413 |
| `INTERNAL` | 500 |

`VALIDATION_FAILED` 의 `details` 는 `{ "title": "필수 항목입니다." }` 형태의 필드별 메시지다.

프론트 컨트롤러가 모든 예외와 PHP 오류를 잡아 `INTERNAL` 로 변환한다. `display_errors` 가 켜진
호스팅에서도 스택 트레이스가 새지 않도록 출력 버퍼를 쓰고, 원문은 설정된 로그 파일에만 남긴다.

### 7.6 검증 규칙

| 필드 | 규칙 |
|---|---|
| `board_key` | 1~50자, `[a-z0-9_-]` 만 허용, 소문자 |
| `title` | 1~200자 |
| `content` | 1자 이상 |
| `author_name` | 1~100자. 토큰이 있으면 `name` 클레임으로 강제하고 요청값을 무시한다 |
| `guest_password` | 비회원 글/댓글에 필수. 4자 이상 |
| `category` | `board.categories` 에 있는 값이거나 NULL |
| `per_page` | 1~100. 미지정 시 `board.per_page` |
| `page` | 1 이상 |

`author_name` 을 토큰 값으로 강제하는 이유: 로그인한 사용자가 임의의 이름으로 글을 쓸 수 있으면
게시판 안에서 사칭이 가능해진다. 비회원 글일 때만 요청값을 받는다.

`guest_password` 는 응답에 절대 포함하지 않는다. Repository 의 SELECT 컬럼 목록에서 기본 제외하고,
권한 검사가 필요할 때만 명시적으로 조회한다.

## 8. 관리자 화면

`public/admin.php` 하나. 빌드 도구 없이 단일 HTML + vanilla JS 이며, API 를 그대로 호출한다.
이 화면이 동작한다는 것이 곧 API 설계가 실제로 쓸 만하다는 검증이 된다.

- 로그인 — 부트스트랩 관리자 계정, 또는 호스트가 발급한 토큰 붙여넣기
- 게시판 관리 — 목록, 생성, 설정 변경(권한/카테고리/페이지당 글 수/비밀글·첨부·분류 사용 여부), 삭제
- 게시판 관리자 지정 — 게시판별 `managers` 에 사용자 ID 추가/제거
- 글·댓글 관리 — 게시판별 목록에서 삭제, 삭제된 항목 복구, 공지 지정/해제
- 유지보수 — 고아 첨부 정리

토큰은 `sessionStorage` 에 둔다. 탭을 닫으면 사라진다.

## 9. 설치

`public/install.php` 를 브라우저로 연다. DB 접속 정보를 입력하면 연결을 시험하고,
방언에 맞는 DDL 로 테이블을 만들고, `config/config.php` 를 쓰고, 부트스트랩 관리자 비밀번호를
받아 해시한다. 완료 후 파일을 지우라고 안내하고, `config.php` 가 이미 있으면 실행을 거부한다.

CLI 가 있는 환경을 위해 같은 로직을 `php public/install.php --cli` 로도 돌릴 수 있게 한다.

## 10. 테스트

PHPUnit 을 개발 전용 의존성으로 쓴다. 배포물에는 포함되지 않는다.

- Service 계층 테스트는 SQLite 인메모리로 전부 돌린다. 빠르고 항상 실행된다.
- Repository 계층 테스트는 DSN 을 파라미터화한다. 기본은 SQLite 이고,
  `TEST_MYSQL_DSN` 환경변수가 있으면 같은 스위트를 그 DB 에도 반복 실행한다.

이식성이 이 프로젝트의 핵심 주장이므로 처음부터 이 구조로 쓴다. 나중에 끼워 넣기 어렵다.

핵심 테스트 대상:

- 지원 DB 에서 동일한 CRUD 결과
- 깊이 10 이상의 댓글 트리 조립
- 삭제된 중간 댓글의 자리표시자 처리
- 권한 판정 6.1 의 다섯 분기 전부
- 만료/변조 토큰 거부
- 비회원 글의 비밀번호 수정·삭제, 틀린 비밀번호 거부
- 공지가 페이징과 무관하게 상단 고정
- 검색어의 `%`, `_` 이스케이프

## 11. 이번 범위 밖

좋아요/추천, 신고, 알림, 리치 에디터, 스팸 차단과 비회원 글 일괄 삭제, 첨부 이미지 리사이즈,
댓글 페이징, 전문 검색, 게시판별 스킨.

이 중 댓글 페이징과 전문 검색은 4.4 와 7.1 에 전환 경로를 적어 두었다.
