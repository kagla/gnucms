# 게시판 공지와 전체 공지 — 설계

2026-08-31. 브랜치 main.

## 목표

관리자가 글을 **공지로 올릴 수 있게** 하고, 그 공지가 **이 게시판에만** 붙는지
**모든 게시판에** 붙는지 고를 수 있게 한다.

지금 상태: 서버는 게시판별 공지를 이미 지원한다(`posts.is_notice`,
`PostRepository::notices($boardId)`, `paginate()` 가 일반 목록에서 제외,
`posts/index.php` 가 목록 위에 따로 그림). **없는 것은 공지로 지정하는 화면과
전체 공지라는 개념**이다. 관리 콘솔의 글 목록(`/admin/posts`)은 사이드바에서
빠져 있으므로 지정은 글쓰기·수정 화면에서 한다.

## 1. 저장 방식 (Schema VERSION 12)

`posts` 에 칸 하나를 더한다.

| 칸 | 형 | 뜻 |
|---|---|---|
| `notice_scope` | `VARCHAR(10) NOT NULL DEFAULT 'board'` | `board` = 이 게시판 공지, `global` = 전체 공지. `is_notice = 1` 일 때만 뜻이 있다 |

- `is_notice` 는 그대로 0/1 이다. 두 칸을 함께 본다.
- 멱등 마이그레이션: `addColumnIfMissing('posts', 'notice_scope', "VARCHAR(10) NOT NULL DEFAULT 'board'")`
  + 새 설치 DDL 에도 같은 칸. 기존 공지는 전부 `board` 로 시작한다(지금 동작 유지).

## 2. 지정 화면

`posts/create.php`·`posts/edit.php` 의 비밀글 토글 옆에, **그 게시판의 관리자에게만**
보이는 선택 하나:

```
공지  ( ) 공지 아님   ( ) 이 게시판 공지   ( ) 전체 게시판 공지
```

- 이름은 `notice`, 값은 `none` | `board` | `global`. 기본은 `none`(수정 화면은 현재 값).
- 컨트롤러는 `can_manage_board`(= `Acl::isAdminFor($board)`)를 넘겨 이 블록을 그릴지 정한다.
- 안내 한 줄: “전체 공지도 이 게시판을 읽을 수 있는 사람에게만 보입니다.”

### 서버 검증

`PostService::create()`/`update()` 는 `notice` 값을 읽는다.

- `none` → `is_notice = 0`, `notice_scope = 'board'`
- `board` → `is_notice = 1`, `notice_scope = 'board'` (관리자 확인)
- `global` → `is_notice = 1`, `notice_scope = 'global'` (관리자 확인)
- 그 밖의 값 → `none` 과 같이 본다.
- 관리자가 아닌데 `board`·`global` 을 보내면 지금처럼 `assertAdminFor()` 가 막는다.
- 기존 `is_notice` 입력(체크박스 형태)도 계속 받아들인다 — 옛 폼·테스트가 깨지지 않게
  `notice` 가 없을 때만 본다.

## 3. 보이는 곳

`PostRepository::notices(int $boardId, array $readableBoardIds): array` 로 바꾼다.

```sql
WHERE deleted_at IS NULL AND is_notice = 1
  AND ( board_id = :board_id
        OR (notice_scope = 'global' AND board_id IN (:readable…)) )
ORDER BY (notice_scope = 'global') DESC, id DESC
```

- 전체 공지가 먼저, 그다음 게시판 공지. 각각 최신순.
- **전체 공지는 그 글이 사는 게시판을 읽을 수 있는 사람에게만 보인다** — 관리자 전용
  게시판에 쓴 공지의 제목이 새지 않게. `readableBoardIds` 는 `BoardService::listBoards($acl)`
  에서 온다.
- 지금 게시판의 전체 공지는 두 조건에 모두 맞지만 한 행이므로 중복되지 않는다.
- `ORDER BY` 의 불리언 식은 방언마다 다르므로 `CASE WHEN notice_scope = 'global' THEN 0 ELSE 1 END`
  로 적는다(지원 DB 공통).
- 요약(`summary()`)에 `notice_scope` 를 실어 화면이 뱃지를 가른다.

`posts/index.php` 의 공지 줄: 전체 공지면 **“전체 공지”** 뱃지, 아니면 지금의 “공지” 뱃지.

`paginate()` 는 지금처럼 `is_notice = 0` 만 세므로 공지가 일반 줄에 겹치지 않는다.

## 4. 건드리지 않는 것

홈, 전체 글(`/posts`) — 공지도 최신순으로 섞여 나오는 지금 방식 그대로.
이름이 “공지사항”인 게시판은 이 기능과 무관하다. 관리 콘솔의 글 목록은 손대지 않는다.

## 5. 검증

- 저장소: 게시판 공지만, 전체 공지만, 둘 다인 경우의 정렬과 중복 없음.
  권한 밖 게시판의 전체 공지는 빠진다.
- 서비스: `notice` 값별 저장, 관리자가 아니면 거부, 옛 `is_notice` 입력 호환.
- 웹: 관리자에게만 폼에 공지 칸이 보인다. 두 게시판을 만들어 A 에 쓴 전체 공지가
  B 목록 맨 위에 “전체 공지” 뱃지로 보인다. 게시판 공지는 자기 게시판에만 보인다.
- 실제 화면(스크래치)으로 뱃지와 순서를 확인한다.

## 범위 밖

공지 기간(며칠 뒤 자동 내림), 공지 순서 손으로 바꾸기, 홈·전체 글의 공지 고정,
관리 콘솔에서의 공지 지정.
