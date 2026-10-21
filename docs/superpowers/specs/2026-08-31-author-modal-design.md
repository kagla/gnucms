# 글쓴이 모달과 목록 코드 합치기 — 설계

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

2026-08-31. 브랜치 main.

## 목표

1. 전체 글과 게시판 목록이 각자 갖고 있는 **표·페이지 번호 코드를 한 벌로 합친다.**
2. 목록의 **회원 글쓴이 이름을 누르면 모달**이 열리고, 그 사람의 **글 목록**과
   **댓글 목록**으로 가는 링크 둘을 준다.

## 1. 중복 제거 (선행)

지금 `templates/default/posts/all.php` 는 표와 페이저를 자기 안에 갖고 있고,
`posts/_list_list.php`(게시판 목록형)와 `posts/index.php`(페이저)에 같은 코드가
한 벌씩 더 있다. 행 내용은 사실상 같고 다른 점은 셋뿐이다:
전체 글엔 **게시판** 칸, 게시판엔 **분류** 칸, 그리고 날짜·이름을 줄이는지 여부.

- **`posts/_table.php`** (신규) — 목록 표 한 벌.
  받는 값: `list`(data 배열), `show_board`(bool, 기본 false), `show_category`(bool, 기본 false),
  `compact`(bool, 기본 false — true 면 `compactDate()`·`truncate(author, 8)`),
  `empty_text`(string, 기본 '아직 글이 없습니다.').
- **`posts/_pager.php`** (신규) — 페이지 번호 한 벌.
  받는 값: `list`(page·total_pages), `page_url`(클로저 `fn (int $page): string`).
- `all.php` 는 `show_board=true`, `_list_list.php` 는 `show_category=$board['use_category']`,
  `compact=true` 로 이 조각을 부른다. `index.php` 의 페이저도 `_pager.php` 로 바꾼다.

표 머리(`<thead>`)와 각 칸은 조각 안에서 위 깃발에 따라 그린다.

## 2. 글쓴이 모달

- 대상은 **회원 글쓴이뿐**이다(`author_id` 가 있는 행). 비회원은 이름만 남아 아무나
  같은 이름을 적을 수 있으므로 지금처럼 글자로만 보인다.
- 목록 요약(`PostService::summary()`)에 `author_id` 를 실어 보낸다(이미 있는 값).
- 표의 글쓴이 칸: 회원이면
  `<button class="link-author" data-author-id="3" data-author-name="홍길동">홍길동</button>`,
  비회원이면 지금 그대로.
- 페이지에 `<dialog class="modal" id="author-modal">` **하나**만 둔다(행마다 만들면
  100행에 100개가 된다). 스크립트가 눌린 단추의 이름·번호를 읽어 제목과 두 링크의
  주소를 채우고 `showModal()` 한다. 스크립트가 없으면 단추는 아무 일도 하지 않는다
  (링크가 아니므로 잘못된 이동이 없다).
- 모달 내용: 이름 한 줄 + 링크 둘 — **이 사람의 글**(`/posts?author={id}`),
  **이 사람의 댓글**(`/comments?author={id}`) + 닫기.

## 3. 글쓴이로 거르기

### 글 — 기존 전체 글 화면 재사용
- `PostRepository::paginateAll()` 에 `?int $authorId = null` 인자를 더한다
  (`author_id = :author_id` 조건). 위치는 마지막.
- `PostService::listRecentPosts()` 가 `author` 질의값(정수)을 읽어 넘기고,
  결과에 `author`(회원 번호)와 `author_name`(그 회원의 표시 이름, 없으면 null)을 실어 준다.
  없는 회원이면 조건을 걸지 않고 평소 목록을 낸다.
- `all.php` 는 `author_name` 이 있으면 제목을 "○○ 님의 글" 로 바꾸고 '거르기 지우기' 링크를 낸다.

### 댓글 — 새 화면
- `CommentRepository::paginateByAuthor(int $authorId, array $boardIds, int $page, int $perPage): array`
  — 지운 댓글 제외, `board_id IN (...)`, 최신순. 반환 `['rows' => …, 'total' => int]`.
- `CommentService::listByAuthor(Acl $acl, array $query): array` — 읽을 수 있는 게시판만
  (`BoardService::listBoards($acl)` 의 id), 페이지 20개. 각 줄에 `post_id`, `post_title`,
  글 제목은 `PostRepository` 에서 한 번에 읽어 붙인다(N+1 을 피해 `id IN (...)`).
  비밀 댓글은 본문 대신 '비밀 댓글' 로 바꾼다.
- `GET /comments` (`comments.byAuthor`) → `CommentController::byAuthor()`
  → `posts/comments_by_author.php`: 글 제목 + 댓글 한 줄(요약 80자) + 날짜,
  각 줄은 `/posts/{post_id}#comment-{id}`.
  `author` 가 없거나 없는 회원이면 404 대신 빈 목록과 안내를 낸다.

## 4. 규칙

- 읽을 수 있는 게시판만(기존 권한 규칙 그대로). 지운 글·댓글 제외.
- 비밀글은 지금 목록 규칙 그대로(요약·썸네일이 비어 온다).
- `author` 는 정수만 받는다. 숫자가 아니면 무시한다.
- 표시 이름은 `UserRepository::findById()` 로 읽고, 없으면 거르기를 하지 않는다.

## 5. 검증

- 저장소·서비스 테스트: 글쓴이 필터가 그 사람 글만 내는지, 권한 밖 게시판이 빠지는지,
  없는 회원이면 평소 목록인지, 댓글 목록이 지운 댓글을 빼고 글 제목을 붙이는지.
- 웹 테스트: 회원 행에만 `data-author-id` 가 있고 비회원 행엔 없는지,
  `/posts?author=` 제목이 바뀌는지, `/comments?author=` 가 200 과 댓글을 내는지.
- 실제 브라우저(CDP)로 이름을 눌러 모달이 열리고 두 링크 주소가 맞는지 확인.

## 범위 밖

- 비회원 글쓴이 모으기, 회원 프로필 화면, 활동 통계, 모달 안에서 목록 미리 보기
  (링크 둘만 둔다), 글 상세 화면의 글쓴이 모달.
