# 글쓴이 모달과 목록 코드 합치기 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 전체 글과 게시판 목록이 표·페이저 코드를 한 벌만 쓰게 하고, 회원 글쓴이 이름을 누르면 그 사람의 글·댓글로 가는 모달이 열리게 한다.

**Architecture:** 표와 페이저를 `posts/_table.php`·`posts/_pager.php` 조각으로 뽑아 두 화면이 함께 쓴다. 모달은 페이지당 `<dialog>` 하나를 두고 눌린 이름의 회원 번호를 스크립트가 채운다. 글 목록은 기존 전체 글 화면에 `?author=` 를 더해 재사용하고, 댓글 목록만 새 화면을 만든다.

**Tech Stack:** PHP 8.4 / Slim 4 / PDO(SQLite·MySQL/MariaDB), PHPUnit 10, daisyUI 5 CDN, PHP 파일 템플릿(`PhpTemplate`).

스펙: `docs/superpowers/specs/2026-08-31-author-modal-design.md`

## Global Constraints

- 모달 대상은 **회원 글쓴이뿐**(`author_id` 가 있는 행). 비회원은 지금처럼 글자로만 보인다.
- `<dialog>` 는 **페이지당 하나**. 행마다 만들지 않는다.
- 읽을 수 있는 게시판만, 지운 글·댓글 제외, 비밀글은 기존 목록 규칙 그대로.
- `author` 질의값은 정수만 받는다. 숫자가 아니거나 없는 회원이면 조건을 걸지 않고 평소 목록을 낸다.
- 템플릿 출력은 전부 `$this->e()`(예외: `url/asset/html/icon/json/insert/block`). 문구는 한국어.
- 기존 417개 테스트는 그대로 통과해야 한다. `./vendor/bin/phpunit`.
- 이 체크아웃은 라이브 사이트다: `config/config.php`·`storage/` 를 건드리지 않는다. 화면 확인은 스크래치 복사본에서 한다.
- **작업 트리에 사용자가 편집 중인 파일이 있다**: `public/themes/default/theme.css`, `templates/default/home/index.php`, `templates/default/boards/index.php`, `templates/default/layout.php`. 이 파일들을 통째로 `git add` 하지 말고, 내가 더한 CSS 는 파일 끝에만 붙인 뒤 그 훅만 골라 스테이징한다(`git diff <file> | awk ... > patch; git apply --cached patch`).
- 커밋 메시지는 한국어 접두어 + 끝에 `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

## File Structure

| 파일 | 책임 |
|---|---|
| `templates/default/posts/_table.php` (신규) | 목록 표 한 벌(게시판·분류 칸 선택, compact, 글쓴이 단추) |
| `templates/default/posts/_pager.php` (신규) | 페이지 번호 한 벌 |
| `templates/default/posts/_author_modal.php` (신규) | `<dialog>` 하나 + 채우는 스크립트 |
| `templates/default/posts/all.php` (수정) | 조각 사용, `?author=` 제목 |
| `templates/default/posts/_list_list.php` (수정) | 조각 사용 |
| `templates/default/posts/index.php` (수정) | 페이저 조각 사용 |
| `templates/default/posts/comments_by_author.php` (신규) | 회원 댓글 목록 화면 |
| `src/Repository/PostRepository.php` (수정) | `paginateAll()` 에 글쓴이 조건 |
| `src/Service/PostService.php` (수정) | `listRecentPosts()` 가 `author` 를 읽고 결과에 실어 줌 |
| `src/Repository/CommentRepository.php` (수정) | `paginateByAuthor()` |
| `src/Service/CommentService.php` (수정) | `listByAuthor()` |
| `src/Web/Controller/CommentController.php` (수정) | `byAuthor()` |
| `src/Web/Routes.php` (수정) | `GET /comments` |
| `public/themes/default/theme.css` (수정, 끝에만) | `.link-author`, `.author-modal` |

---

### Task 1: 표·페이저 조각으로 합치기

**Files:**
- Create: `templates/default/posts/_table.php`, `templates/default/posts/_pager.php`
- Modify: `templates/default/posts/all.php`, `templates/default/posts/_list_list.php`, `templates/default/posts/index.php`
- Test: `tests/Web/PostListTest.php` (메서드 추가)

**Interfaces:**
- Produces: 조각 `posts/_table` — 받는 값 `list`(배열, `data` 키), `show_board`(bool, 기본 false), `show_category`(bool, 기본 false), `compact`(bool, 기본 false), `empty_text`(string, 기본 `'아직 글이 없습니다.'`). 조각 `posts/_pager` — 받는 값 `list`(`page`,`total_pages`), `page_url`(클로저 `fn (int $page): string`).
- Consumes: 기존 `posts/_count` 조각, 헬퍼 `compactDate()`, `truncate()`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/PostListTest.php` 클래스 끝에:

```php
    #[DataProvider('connectionProvider')]
    public function testAllPostsAndBoardListShareTheSameTablePartial(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'free', ['title' => '첫 글', 'content' => '본문입니다']);

        $all = $this->body($this->get($app, '/posts'));
        $board = $this->body($this->get($app, '/boards/free'));

        // 한 조각이 두 화면을 그린다: 표 클래스가 같고, 각자 자기 칸을 낸다.
        self::assertStringContainsString('class="table table-zebra posts-table"', $all);
        self::assertStringContainsString('class="table table-zebra posts-table"', $board);
        self::assertStringContainsString('<th class="post-col-board">게시판</th>', $all);
        self::assertStringNotContainsString('<th class="post-col-board">게시판</th>', $board);
    }
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit --filter testAllPostsAndBoardListShareTheSameTablePartial`
Expected: FAIL — `posts-table` 클래스 없음(지금은 `all-posts-table`·`posts-list-table` 로 갈려 있다).

- [ ] **Step 3: 표 조각을 만든다**

`templates/default/posts/_table.php`:

```php
<?php
// 목록 표 한 벌. 전체 글과 게시판 목록형이 함께 쓴다.
//   list           목록 배열(data)
//   show_board     게시판 칸을 낸다 (여러 게시판이 섞이는 화면)
//   show_category  분류 칸을 낸다 (분류를 쓰는 게시판)
//   compact        좁은 칸용. 날짜를 줄이고 이름을 8자에서 자른다
//   empty_text     한 줄도 없을 때 보일 말
$show_board = $show_board ?? false;
$show_category = $show_category ?? false;
$compact = $compact ?? false;
$empty_text = $empty_text ?? '아직 글이 없습니다.';
$columns = 4 + ($show_board ? 1 : 0) + ($show_category ? 1 : 0);
?>
<section class="card">
  <div class="table-wrap">
    <table class="table table-zebra posts-table">
      <thead>
        <tr>
          <?php if ($show_board): ?><th class="post-col-board">게시판</th><?php endif ?>
          <?php if ($show_category): ?><th class="post-col-category">분류</th><?php endif ?>
          <th class="post-col-title">제목</th>
          <th class="post-col-author">글쓴이</th>
          <th class="post-col-date">날짜</th>
          <th class="post-col-views right">조회</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($list['data'] === []): ?>
        <tr class="table-empty"><td colspan="<?= $this->e((string) $columns) ?>"><?= $this->e($empty_text) ?></td></tr>
      <?php else: foreach ($list['data'] as $post): ?>
        <tr>
          <?php if ($show_board): ?>
            <td data-label="게시판" class="post-col-board"><a class="badge badge-ghost badge-sm" href="<?= $this->url('posts.index', ['key' => $post['board_key']]) ?>"><?= $this->e($post['board_name']) ?></a></td>
          <?php endif ?>
          <?php if ($show_category): ?>
            <td data-label="분류" class="post-col-category"><?php if ($post['category']): ?><span class="badge badge-ghost badge-sm"><?= $this->e($post['category']) ?></span><?php else: ?><span class="cell-sub">—</span><?php endif ?></td>
          <?php endif ?>
          <td data-label="제목" class="post-col-title">
            <div class="post-title-line">
              <?php if ($post['is_notice']): ?><span class="badge badge-primary badge-soft badge-sm">공지</span><?php endif ?>
              <?php if ($post['is_secret']): ?><span class="post-row-lock" title="비밀글" aria-label="비밀글"><?= $this->icon('lock', 16) ?></span><?php endif ?>
              <a class="cell-title link link-hover" href="<?= $this->url('posts.show', ['id' => $post['id']]) ?>" title="<?= $this->e($post['title']) ?>"><?= $this->e($post['title']) ?> <?php $this->insert('posts/_count', ['post' => $post]) ?></a>
              <?php if ($post['file_count'] > 0): ?><span class="post-row-clip" title="첨부파일 있음" aria-label="첨부파일 있음"><?= $this->icon('clip', 16) ?></span><?php endif ?>
            </div>
          </td>
          <td data-label="글쓴이" class="cell-author post-col-author"><?php $this->insert('posts/_author', ['post' => $post, 'compact' => $compact]) ?></td>
          <td data-label="날짜" class="post-col-date"><time datetime="<?= $this->e($post['created_at']) ?>"><?= $compact ? $this->compactDate($post['created_at']) : $this->date($post['created_at'], 'Y.m.d') ?></time></td>
          <td data-label="조회" class="post-col-views right"><?= $this->e($post['view_count']) ?></td>
        </tr>
      <?php endforeach; endif ?>
      </tbody>
    </table>
  </div>
</section>
```

이 조각은 `posts/_author` 를 부른다. **Task 2 에서 만든다.** 지금은 Task 1 을 끝내기 위해 최소 형태로 함께 만든다 — `templates/default/posts/_author.php`:

```php
<?php // 목록의 글쓴이 칸. 회원 글쓴이는 Task 2 에서 모달 단추가 된다.
$compact = $compact ?? false;
$author_name = (string) $post['author_name'];
?>
<span class="post-list-author" title="<?= $this->e($author_name) ?>"><?= $this->e($compact ? $this->truncate($author_name, 8) : $author_name) ?></span>
```

- [ ] **Step 4: 페이저 조각을 만든다**

`templates/default/posts/_pager.php`:

```php
<?php
// 페이지 번호 한 벌. page_url 은 쪽 번호를 받아 주소를 돌려주는 클로저다.
if (($list['total_pages'] ?? 0) <= 1) {
    return;
}
$window = 3;
$start = max(1, $list['page'] - $window);
$end = min($list['total_pages'], $list['page'] + $window);
?>
<nav class="pager" aria-label="페이지 이동">
  <div class="join">
    <?php if ($list['page'] > 1): ?><a class="join-item btn btn-sm" rel="prev" href="<?= $this->e($page_url($list['page'] - 1)) ?>" aria-label="이전 페이지"><?= $this->icon('chevron-left', 15) ?></a><?php endif ?>
    <?php if ($start > 1): ?>
      <a class="join-item btn btn-sm" href="<?= $this->e($page_url(1)) ?>" aria-label="1 페이지">1</a>
      <?php if ($start > 2): ?><span class="join-item btn btn-sm btn-disabled" aria-hidden="true">…</span><?php endif ?>
    <?php endif ?>
    <?php for ($p = $start; $p <= $end; $p++): ?>
      <?php if ($p === $list['page']): ?>
        <span class="join-item btn btn-sm btn-active" aria-current="page"><?= $this->e((string) $p) ?></span>
      <?php else: ?>
        <a class="join-item btn btn-sm" href="<?= $this->e($page_url($p)) ?>" aria-label="<?= $this->e((string) $p) ?> 페이지"><?= $this->e((string) $p) ?></a>
      <?php endif ?>
    <?php endfor ?>
    <?php if ($end < $list['total_pages']): ?>
      <?php if ($end < $list['total_pages'] - 1): ?><span class="join-item btn btn-sm btn-disabled" aria-hidden="true">…</span><?php endif ?>
      <a class="join-item btn btn-sm" href="<?= $this->e($page_url($list['total_pages'])) ?>" aria-label="<?= $this->e((string) $list['total_pages']) ?> 페이지"><?= $this->e((string) $list['total_pages']) ?></a>
    <?php endif ?>
    <?php if ($list['page'] < $list['total_pages']): ?><a class="join-item btn btn-sm" rel="next" href="<?= $this->e($page_url($list['page'] + 1)) ?>" aria-label="다음 페이지"><?= $this->icon('chevron-right', 15) ?></a><?php endif ?>
  </div>
</nav>
```

**주의**: 기존 `all.php` 의 페이저는 주소에 `&amp;` 를 직접 넣어 만든 문자열을 그대로 출력했다. 새 조각은 `$this->e()` 를 거치므로 클로저는 **이스케이프하지 않은 순수 주소**(`&` 그대로)를 돌려줘야 한다. 아래 두 화면의 클로저를 그렇게 고친다.

- [ ] **Step 5: all.php 가 조각을 쓰게 한다**

`templates/default/posts/all.php` 에서 파일 맨 위 `$allUrl` 클로저를 다음으로 바꾼다(`&amp;` → `&`):

```php
<?php // 전체 글 주소 만들기. 이 파일 안에서만 쓰는 클로저다. 출력할 때 템플릿이 이스케이프한다.
$allUrl = function ($q, $page): string {
    $params = [];
    if ($q !== null && $q !== '') { $params[] = 'q=' . rawurlencode((string) $q); }
    if ($page && $page > 1) { $params[] = 'page=' . (int) $page; }
    return $this->url('posts.all') . ($params !== [] ? '?' . implode('&', $params) : '');
}; ?>
```

`<section class="card">` 부터 `</section>` 까지(표 전체)를 다음으로 바꾼다:

```php
<?php $this->insert('posts/_table', [
  'list' => $list,
  'show_board' => true,
  'empty_text' => ($query['q'] !== null && $query['q'] !== '') ? '조건에 맞는 글이 없습니다.' : '아직 글이 없습니다.',
]) ?>
```

파일 끝의 `<?php if ($list['total_pages'] > 1): ?>` 부터 `<?php endif ?>` 까지(페이저 전체)를 다음으로 바꾼다:

```php
<?php $this->insert('posts/_pager', ['list' => $list, 'page_url' => fn (int $page): string => $allUrl($query['q'], $page)]) ?>
```

`$this->url()` 은 이미 이스케이프된 문자열을 돌려주므로 `$allUrl` 결과를 `_pager` 가 다시 `e()` 하면 `&amp;` 가 `&amp;amp;` 가 되지 않는지 확인하라 — `url()` 은 경로만 만들고 `&` 는 이 클로저가 붙이므로 이중 이스케이프는 나지 않는다. Step 7 의 테스트가 이를 잡는다.

- [ ] **Step 6: _list_list.php 와 index.php 를 바꾼다**

`templates/default/posts/_list_list.php` 전체를 다음으로 바꾼다:

```php
<?php // 목록형: 표. 정보 밀도가 가장 높다. 좁은 화면에서는 카드로 접힌다. ?>
<?php $this->insert('posts/_table', [
  'list' => $list,
  'show_category' => (bool) $board['use_category'],
  'compact' => true,
]) ?>
```

`templates/default/posts/index.php` 의 페이저 블록(`<?php if ($list['total_pages'] > 1): ?>` 부터 그 `<?php endif ?>` 까지)을 다음으로 바꾼다:

```php
<?php $this->insert('posts/_pager', [
  'list' => $list,
  'page_url' => fn (int $page): string => $listUrl($board, $query['q'], $query['category'], $page, $view_param),
]) ?>
```

`$listUrl` 클로저도 `&amp;` 를 쓰고 있으면 `&` 로 바꾼다(파일 위쪽에서 확인).

- [ ] **Step 7: 통과 확인 + 이중 이스케이프 검사**

Run: `./vendor/bin/phpunit --filter 'PostListTest|BoardListViewTest|AllPostsTest'`
Expected: OK.

주소가 성한지 눈으로도 본다:

```bash
grep -o 'href="[^"]*posts?[^"]*"' /dev/null; ./vendor/bin/phpunit --filter testAllPostsAndBoardListShareTheSameTablePartial
```

그리고 다음 단언을 같은 테스트에 덧붙여 `&amp;amp;` 가 없음을 못박는다:

```php
        self::assertStringNotContainsString('&amp;amp;', $all);
```

- [ ] **Step 8: 전체 스위트**

Run: `./vendor/bin/phpunit`
Expected: OK. 실패하면 옛 표 클래스(`all-posts-table`·`posts-list-table`)를 단언하던 테스트다 — 새 클래스(`posts-table`)로 고친다. CSS 에도 그 두 이름이 있으면 `posts-table` 로 합친다(`public/themes/default/theme.css`, 파일 끝이 아니라 기존 규칙 자리에서 이름만 바꾼다).

- [ ] **Step 9: 커밋**

```bash
git add templates/default/posts/_table.php templates/default/posts/_pager.php templates/default/posts/_author.php templates/default/posts/all.php templates/default/posts/_list_list.php templates/default/posts/index.php tests/Web/PostListTest.php
git commit -m "refactor: 목록 표와 페이지 번호를 조각 하나로 합친다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

CSS 이름을 고쳤다면 그 훅만 골라 함께 스테이징한다(Global Constraints 참고).

---

### Task 2: 글쓴이 모달

**Files:**
- Modify: `templates/default/posts/_author.php`
- Create: `templates/default/posts/_author_modal.php`
- Modify: `templates/default/posts/all.php`, `templates/default/posts/index.php` (모달 조각 한 줄 삽입)
- Modify: `public/themes/default/theme.css` (파일 끝에만)
- Test: `tests/Web/AuthorModalTest.php` (신규)

**Interfaces:**
- Consumes: Task 1 의 `posts/_table` → `posts/_author` 호출, 목록 요약의 `author_id`(이미 `PostService::summary()` 가 실어 준다).
- Produces: 회원 행의 글쓴이 칸이 `<button class="link-author" data-author-id="3" data-author-name="홍길동">`. 조각 `posts/_author_modal` (받는 값 없음).

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AuthorModalTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** 목록의 글쓴이 모달: 회원만 누를 수 있고, dialog 는 페이지에 하나뿐이다. */
final class AuthorModalTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testMemberAuthorIsButtonAndGuestAuthorIsNot(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['guest_write_enabled' => '1']);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'perm_write' => 'guest']);
        // 회원 글: adminAcl 의 신원이 글쓴이가 된다.
        $app->postService()->create($acl, 'free', ['title' => '회원 글', 'content' => '본문입니다']);
        // 비회원 글
        $this->get($app, '/boards/free/new');
        $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '손님 글', 'content' => '본문입니다',
            'author_name' => '지나가던손님', 'password' => 'guest-pass-123',
        ]);

        $body = $this->body($this->get($app, '/posts'));

        self::assertStringContainsString('data-author-name="관리자"', $body);
        self::assertStringNotContainsString('data-author-name="지나가던손님"', $body);
        self::assertStringContainsString('지나가던손님', $body, '비회원 이름은 글자로는 보인다');
        // dialog 는 페이지에 하나뿐이다.
        self::assertSame(1, substr_count($body, 'id="author-modal"'));
    }

    #[DataProvider('connectionProvider')]
    public function testBoardListAlsoGetsTheModal(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'free', ['title' => '회원 글', 'content' => '본문입니다']);

        $body = $this->body($this->get($app, '/boards/free'));

        self::assertStringContainsString('data-author-id=', $body);
        self::assertSame(1, substr_count($body, 'id="author-modal"'));
    }
}
```

`adminAcl()` 이 만드는 신원의 표시 이름은 `tests/Support/WebTestCase.php` 에서 확인하라(현재 `'관리자'`). 다르면 단언 문자열을 실제 값으로 맞춘다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/AuthorModalTest.php`
Expected: FAIL — `data-author-name` 없음.

- [ ] **Step 3: 글쓴이 조각을 모달 단추로**

`templates/default/posts/_author.php` 전체:

```php
<?php
// 목록의 글쓴이 칸. 회원이면 모달을 여는 단추, 비회원이면 글자.
// 비회원은 이름만 남아 동명이인을 가릴 수 없으므로 누를 수 없다.
$compact = $compact ?? false;
$author_name = (string) $post['author_name'];
$shown = $compact ? $this->truncate($author_name, 8) : $author_name;
$author_id = $post['author_id'] ?? null;
?>
<?php if ($author_id !== null && (string) $author_id !== ''): ?>
  <button type="button" class="link-author" data-author-id="<?= $this->e((string) $author_id) ?>" data-author-name="<?= $this->e($author_name) ?>" title="<?= $this->e($author_name) ?>"><?= $this->e($shown) ?></button>
<?php else: ?>
  <span class="post-list-author" title="<?= $this->e($author_name) ?>"><?= $this->e($shown) ?></span>
<?php endif ?>
```

- [ ] **Step 4: 모달 조각**

`templates/default/posts/_author_modal.php`:

```php
<?php // 목록 페이지에 하나만 둔다. 눌린 이름의 회원 번호를 스크립트가 링크에 채운다. ?>
<dialog class="modal author-modal" id="author-modal">
  <div class="modal-box">
    <h3 class="author-modal-title" data-author-modal-name></h3>
    <p class="author-modal-sub">이 회원이 남긴 것을 모아 봅니다.</p>
    <div class="author-modal-links">
      <a class="btn btn-outline btn-block" href="#" data-author-modal-posts><?= $this->icon('board', 16) ?> 이 사람의 글</a>
      <a class="btn btn-outline btn-block" href="#" data-author-modal-comments><?= $this->icon('comment', 16) ?> 이 사람의 댓글</a>
    </div>
    <form method="dialog" class="modal-action"><button class="btn btn-ghost">닫기</button></form>
  </div>
  <form method="dialog" class="modal-backdrop"><button aria-label="닫기">닫기</button></form>
</dialog>
<script>
(function () {
  var modal = document.getElementById('author-modal');
  if (!modal || typeof modal.showModal !== 'function') { return; }
  var title = modal.querySelector('[data-author-modal-name]');
  var postsLink = modal.querySelector('[data-author-modal-posts]');
  var commentsLink = modal.querySelector('[data-author-modal-comments]');
  var postsBase = <?= $this->json($this->url('posts.all')) ?>;
  var commentsBase = <?= $this->json($this->url('comments.byAuthor')) ?>;
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.link-author');
    if (!button) { return; }
    var id = button.dataset.authorId;
    if (!id) { return; }
    title.textContent = button.dataset.authorName || '';
    postsLink.href = postsBase + '?author=' + encodeURIComponent(id);
    commentsLink.href = commentsBase + '?author=' + encodeURIComponent(id);
    modal.showModal();
  });
})();
</script>
```

`comments.byAuthor` 라우트는 **Task 4** 에서 만든다. Task 2 만 적용한 상태로 테스트를 돌리면 `urlFor()` 가 없는 라우트라 예외가 난다. 그래서 **Task 2 에서는 라우트와 빈 컨트롤러까지 함께 만든다**: `src/Web/Routes.php` 에 `$slim->get('/comments', [$comments, 'byAuthor'])->setName('comments.byAuthor');` 를 `$comments = new CommentController($app);` 뒤에 더하고, `CommentController` 에 다음을 더한다(내용은 Task 4 에서 채운다):

```php
    public function byAuthor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return View::fromRequest($request)->render($response, 'posts/comments_by_author', [
            'list' => ['data' => [], 'page' => 1, 'total' => 0, 'total_pages' => 0],
            'author' => null,
        ]);
    }
```

그리고 자리를 채우는 최소 템플릿 `templates/default/posts/comments_by_author.php`:

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?>회원 댓글 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>all<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="page-head"><div><h1>회원 댓글</h1></div></div>
<section class="card"><div class="card-body"><p>준비 중입니다.</p></div></section>
<?php $this->stop() ?>
```

- [ ] **Step 5: 두 화면에 모달을 넣는다**

`templates/default/posts/all.php` 와 `templates/default/posts/index.php` 의 `body` 블록 맨 끝(`<?php $this->stop() ?>` 바로 앞)에 각각:

```php
<?php $this->insert('posts/_author_modal') ?>
```

- [ ] **Step 6: CSS (파일 끝에만 추가)**

`public/themes/default/theme.css` 끝에:

```css
/* 목록의 글쓴이 단추와 모달 */
.link-author{max-width:9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;vertical-align:bottom;padding:0;border:0;background:none;color:inherit;font:inherit;text-align:left;cursor:pointer;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:3px}
.link-author:hover{color:var(--color-primary)}
.author-modal .modal-box{max-width:22rem}
.author-modal-title{margin:0 0 .25rem;font-size:1.05rem;font-weight:800}
.author-modal-sub{margin:0 0 1rem;font-size:.8125rem;color:var(--bc-soft)}
.author-modal-links{display:grid;gap:.5rem}
```

- [ ] **Step 7: 통과 확인**

Run: `./vendor/bin/phpunit tests/Web/AuthorModalTest.php && ./vendor/bin/phpunit`
Expected: 둘 다 OK.

- [ ] **Step 8: 실제 브라우저로 모달 확인**

스크래치 복사본을 최신 코드로 맞추고(`cp -r src templates public $S/`) 내장 서버를 띄운 뒤, CDP 로 `/posts` 에서 `.link-author` 를 클릭해 `#author-modal` 이 `open` 이 되는지, 두 링크의 `href` 가 `?author={id}` 로 채워지는지 확인한다. 스크래치 경로와 서버 띄우는 법은 이 저장소의 이전 작업과 같다:

```bash
S=/tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/attach-run
cp -r src templates public "$S"/ && cd "$S" && (php -S 127.0.0.1:8096 -t public > server.log 2>&1 &)
```

CDP 는 반드시 `type === 'page'` 인 대상에 붙어라(`/json` 의 첫 항목은 백그라운드 페이지일 수 있다). 확인 뒤 서버와 크롬을 끈다(`ps -eo pid,comm,args | awk '$2=="php" && /8096/ {print $1}' | xargs -r kill`).

- [ ] **Step 9: 커밋**

```bash
git add templates/default/posts/_author.php templates/default/posts/_author_modal.php templates/default/posts/all.php templates/default/posts/index.php templates/default/posts/comments_by_author.php src/Web/Controller/CommentController.php src/Web/Routes.php tests/Web/AuthorModalTest.php
git commit -m "feat: 목록의 회원 글쓴이를 누르면 글·댓글로 가는 모달이 열린다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

CSS 는 파일 끝 훅만 골라 스테이징한다:

```bash
SP=/tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad
git diff public/themes/default/theme.css | awk '/^@@/{h=h+1; buf[h]=""} h>0{buf[h]=buf[h] $0 "\n"} END{printf "%s", buf[h]}' > $SP/css.hunk
{ echo "--- a/public/themes/default/theme.css"; echo "+++ b/public/themes/default/theme.css"; cat $SP/css.hunk; } > $SP/css.patch
git apply --cached $SP/css.patch
git commit --amend --no-edit
```

---

### Task 3: 글쓴이로 거른 전체 글 (`/posts?author=`)

**Files:**
- Modify: `src/Repository/PostRepository.php` (`paginateAll()`)
- Modify: `src/Service/PostService.php` (`listRecentPosts()`)
- Modify: `templates/default/posts/all.php` (제목·지우기 링크)
- Test: `tests/Web/AllPostsTest.php` (메서드 추가)

**Interfaces:**
- Consumes: `UserRepository::findById(int $id): ?array` (표시 이름 확인), `BoardService::listBoards(Acl)`.
- Produces: `PostRepository::paginateAll(int $page, int $perPage, ?string $q = null, ?int $boardId = null, bool $includeDeleted = false, ?array $boardIds = null, ?int $authorId = null): array`. `PostService::listRecentPosts()` 결과에 `author`(?int)·`author_name`(?string) 추가.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AllPostsTest.php` 클래스 끝에:

```php
    #[DataProvider('connectionProvider')]
    public function testAuthorFilterShowsOnlyThatMembersPosts(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $memberId = $app->users()->create('writer@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '글쓴사람');
        $app->users()->verifyEmail($memberId);

        $app->postService()->create($acl, 'free', ['title' => '관리자 글', 'content' => '본문입니다']);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'writer@example.com', 'password' => 'member-password-123',
        ]);
        $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'], 'title' => '회원 글', 'content' => '본문입니다',
        ]);

        $body = $this->body($this->get($app, '/posts', ['author' => (string) $memberId]));

        self::assertStringContainsString('회원 글', $body);
        self::assertStringNotContainsString('관리자 글', $body);
        self::assertStringContainsString('글쓴사람 님의 글', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownAuthorFallsBackToTheWholeList(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'free', ['title' => '관리자 글', 'content' => '본문입니다']);

        $body = $this->body($this->get($app, '/posts', ['author' => '99999']));

        self::assertStringContainsString('관리자 글', $body);
        self::assertStringNotContainsString('님의 글', $body);
    }
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/AllPostsTest.php`
Expected: FAIL — 거르지 않아 '관리자 글' 이 함께 나온다.

- [ ] **Step 3: 저장소에 조건 추가**

`src/Repository/PostRepository.php` 의 `paginateAll()` 서명 마지막에 인자를 더한다:

```php
    public function paginateAll(
        int $page,
        int $perPage,
        ?string $q = null,
        ?int $boardId = null,
        bool $includeDeleted = false,
        ?array $boardIds = null,
        ?int $authorId = null
    ): array {
```

`if ($boardId !== null) { … }` 블록 다음에:

```php
        if ($authorId !== null) {
            $where .= ' AND author_id = :author_id';
            $params['author_id'] = (string) $authorId;
        }
```

`author_id` 는 회원 번호를 문자열로 담는 칸(`VARCHAR`)이다 — `src/Db/Schema.php` 의 `posts` DDL 에서 형을 확인하고, 숫자 칸이면 `(int) $authorId` 로 넘겨라.

- [ ] **Step 4: 서비스가 질의값을 읽는다**

`src/Service/PostService.php` 의 `listRecentPosts()` 를 다음으로 바꾼다(생성자에 `UserRepository` 가 없으므로 표시 이름은 새 세터로 받는다):

```php
    /** 전체 글 목록. author 질의값이 있으면 그 회원의 글만 모은다. */
    public function listRecentPosts(Acl $acl, array $query): array
    {
        $v = new Validator($query);
        $page = $v->int('page', 1, 1, 100000);
        $q = $v->optionalString('q', 100);
        $author = $v->int('author', 0, 0, PHP_INT_MAX);
        $v->check();
        $perPage = 20;

        $boards = [];
        foreach ($this->boards->listBoards($acl) as $board) {
            $boards[(int) $board['id']] = $board;
        }

        // 없는 회원이면 거르지 않고 평소 목록을 낸다.
        $authorName = $author > 0 && $this->users !== null ? $this->displayNameOf($author) : null;
        $authorId = $authorName === null ? null : $author;

        $result = $this->posts->paginateAll($page, $perPage, $q, null, false, array_keys($boards), $authorId);

        $rows = [];
        foreach ($result['rows'] as $row) {
            $summary = $this->summary($row);
            $board = $boards[(int) $row['board_id']];
            $summary['board_key'] = $board['board_key'];
            $summary['board_name'] = $board['name'];
            $rows[] = $summary;
        }

        return [
            'data'        => $rows,
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $result['total'],
            'total_pages' => $result['total'] === 0 ? 0 : (int) ceil($result['total'] / $perPage),
            'author'      => $authorId,
            'author_name' => $authorName,
        ];
    }

    /** 회원 번호로 표시 이름을 읽는다. 없는 회원이면 null. */
    private function displayNameOf(int $userId): ?string
    {
        $user = $this->users === null ? null : $this->users->findById($userId);

        return $user === null ? null : (string) $user['display_name'];
    }
```

클래스 위쪽(다른 프로퍼티 옆)에:

```php
    /** 글쓴이 표시 이름을 읽을 때만 쓴다. App 이 넣어 준다. */
    private ?UserRepository $users = null;

    public function setUserRepository(UserRepository $users): void
    {
        $this->users = $users;
    }
```

`use GnuCms\Account\UserRepository;` 를 파일 위 use 목록에 더한다.

`src/App.php` 의 `postService()` 조립부(다른 세터들 옆)에 한 줄:

```php
            $this->postService->setUserRepository($this->users());
```

- [ ] **Step 5: 화면에 제목을 반영**

`templates/default/posts/all.php` 의 `page-head` 블록을 다음으로 바꾼다:

```php
<div class="page-head">
  <div>
    <h1><?php if (($list['author_name'] ?? null) !== null): ?><?= $this->e($list['author_name']) ?> 님의 글<?php else: ?>전체 글<?php endif ?></h1>
    <p class="page-sub"><?php if (($list['author_name'] ?? null) !== null): ?>이 회원이 쓴 글을 최신순으로 모았습니다. 글 <strong><?= $this->e((string) $list['total']) ?></strong>개<?php else: ?>읽을 수 있는 모든 게시판의 글을 최신순으로 모았습니다.<?php if ($query['q'] !== null && $query['q'] !== ''): ?> “<?= $this->e($query['q']) ?>” 검색 결과 <?= $this->e((string) $list['total']) ?>건<?php endif ?><?php endif ?></p>
  </div>
  <?php if (($list['author_name'] ?? null) !== null || ($query['q'] !== null && $query['q'] !== '')): ?>
  <div class="page-head-actions"><a class="btn btn-outline btn-sm" href="<?= $this->url('posts.all') ?>">전체 글 보기</a></div>
  <?php endif ?>
</div>
```

`$allUrl` 클로저가 `author` 를 페이지 이동에 이어 가도록 고친다:

```php
$allUrl = function ($q, $page) use ($list): string {
    $params = [];
    if ($q !== null && $q !== '') { $params[] = 'q=' . rawurlencode((string) $q); }
    if (($list['author'] ?? null) !== null) { $params[] = 'author=' . (int) $list['author']; }
    if ($page && $page > 1) { $params[] = 'page=' . (int) $page; }
    return $this->url('posts.all') . ($params !== [] ? '?' . implode('&', $params) : '');
};
```

`use ($list)` 를 쓰려면 클로저 정의가 `$list` 가 있는 자리(템플릿 본문) 뒤여야 한다 — 파일 맨 위에서 정의하고 있으므로 `$list` 는 이미 지역 변수로 풀려 있다(컨트롤러가 넘긴 값). 그대로 동작한다.

- [ ] **Step 6: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Web/AllPostsTest.php && ./vendor/bin/phpunit`
Expected: 둘 다 OK.

- [ ] **Step 7: 커밋**

```bash
git add src/Repository/PostRepository.php src/Service/PostService.php src/App.php templates/default/posts/all.php tests/Web/AllPostsTest.php
git commit -m "feat: 전체 글을 글쓴이로 거를 수 있게 한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: 회원 댓글 목록 (`/comments?author=`)

**Files:**
- Modify: `src/Repository/CommentRepository.php` (`paginateByAuthor()`)
- Modify: `src/Service/CommentService.php` (`listByAuthor()`)
- Modify: `src/Web/Controller/CommentController.php` (`byAuthor()` 채우기)
- Modify: `templates/default/posts/comments_by_author.php` (자리 템플릿 → 실제 화면)
- Modify: `public/themes/default/theme.css` (파일 끝에만)
- Test: `tests/Web/AuthorCommentsTest.php` (신규)

**Interfaces:**
- Consumes: Task 2 가 만든 라우트 `comments.byAuthor` 와 빈 `byAuthor()`; `PostRepository::findMany(array $ids): array` 가 없으면 이 태스크에서 만든다.
- Produces: `CommentRepository::paginateByAuthor(int $authorId, array $boardIds, int $page, int $perPage): array{rows: array, total: int}`; `CommentService::listByAuthor(Acl $acl, array $query): array{data: array, page: int, per_page: int, total: int, total_pages: int, author: ?int, author_name: ?string}`; 각 줄은 `['id','post_id','post_title','excerpt','is_secret','created_at']`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AuthorCommentsTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthorCommentsTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testMembersCommentsAreListedWithTheirPostTitles(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $post = $app->postService()->create($acl, 'free', ['title' => '이야기 글', 'content' => '본문입니다']);
        $memberId = $app->users()->create('writer@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '댓쓴사람');
        $app->users()->verifyEmail($memberId);

        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'writer@example.com', 'password' => 'member-password-123',
        ]);
        $this->post($app, '/posts/' . $post['id'] . '/comments', [
            'csrf_token' => $_SESSION['csrf_token'], 'content' => '반가운 댓글입니다',
        ]);

        $body = $this->body($this->get($app, '/comments', ['author' => (string) $memberId]));

        self::assertStringContainsString('댓쓴사람 님의 댓글', $body);
        self::assertStringContainsString('반가운 댓글입니다', $body);
        self::assertStringContainsString('이야기 글', $body);
        self::assertStringContainsString('#comment-', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testDeletedCommentsAndUnknownAuthorAreHandled(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $post = $app->postService()->create($acl, 'free', ['title' => '이야기 글', 'content' => '본문입니다']);
        $memberId = $app->users()->create('writer@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '댓쓴사람');
        $app->users()->verifyEmail($memberId);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'writer@example.com', 'password' => 'member-password-123',
        ]);
        $this->post($app, '/posts/' . $post['id'] . '/comments', [
            'csrf_token' => $_SESSION['csrf_token'], 'content' => '지울 댓글입니다',
        ]);
        $comment = $app->comments()->findByPost($post['id'])[0];
        $app->comments()->softDelete((int) $comment['id']);

        $mine = $this->body($this->get($app, '/comments', ['author' => (string) $memberId]));
        self::assertStringNotContainsString('지울 댓글입니다', $mine);
        self::assertStringContainsString('남긴 댓글이 없습니다', $mine);

        $unknown = $this->get($app, '/comments', ['author' => '99999']);
        self::assertSame(200, $unknown->getStatusCode());
        self::assertStringContainsString('회원을 찾을 수 없습니다', $this->body($unknown));
    }
}
```

`$app->comments()` 가 `CommentRepository` 를 돌려주는지 `src/App.php` 에서 확인하고, 이름이 다르면 맞춘다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/AuthorCommentsTest.php`
Expected: FAIL — 자리 템플릿이라 '준비 중입니다' 만 나온다.

- [ ] **Step 3: 저장소**

`src/Repository/CommentRepository.php` 에:

```php
    /**
     * 한 회원이 남긴 댓글을 최신순으로. 지운 댓글과 읽을 수 없는 게시판은 뺀다.
     *
     * @param int[] $boardIds 읽을 수 있는 게시판 번호. 빈 배열이면 아무것도 없다
     * @return array{rows: array, total: int}
     */
    public function paginateByAuthor(int $authorId, array $boardIds, int $page, int $perPage): array
    {
        if ($boardIds === []) {
            return ['rows' => [], 'total' => 0];
        }

        $params = ['author_id' => (string) $authorId];
        $marks = [];
        foreach (array_values($boardIds) as $i => $id) {
            $marks[] = ':b' . $i;
            $params['b' . $i] = (int) $id;
        }
        $where = 'deleted_at IS NULL AND author_id = :author_id AND board_id IN (' . implode(', ', $marks) . ')';

        $total = (int) $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->q('comments') . ' WHERE ' . $where,
            $params
        )['c'];

        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->q('comments')
            . ' WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }
```

`self::COLUMNS` 가 `hydrate()` 를 거쳐야 하는 형태면 기존 다른 메서드와 같은 방식으로 맞춘다(파일 안의 `findByPost()` 를 본보기로).

`author_id` 칸의 형(문자열/숫자)은 `src/Db/Schema.php` 의 `comments` DDL 에서 확인해 파라미터 형을 맞춘다.

- [ ] **Step 4: 서비스**

`src/Service/CommentService.php` 에:

```php
    /** 한 회원이 남긴 댓글 목록. 글 제목을 함께 붙인다. */
    public function listByAuthor(Acl $acl, array $query): array
    {
        $v = new Validator($query);
        $page = $v->int('page', 1, 1, 100000);
        $author = $v->int('author', 0, 0, PHP_INT_MAX);
        $v->check();
        $perPage = 20;

        $user = $author > 0 && $this->users !== null ? $this->users->findById($author) : null;
        $empty = [
            'data' => [], 'page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 0,
            'author' => null, 'author_name' => null,
        ];
        if ($user === null) {
            return $empty;
        }

        $boardIds = [];
        foreach ($this->boards->listBoards($acl) as $board) {
            $boardIds[] = (int) $board['id'];
        }

        $result = $this->comments->paginateByAuthor($author, $boardIds, $page, $perPage);

        // 글 제목은 한 번에 읽는다. 줄마다 읽으면 스무 번 물어보게 된다.
        $titles = [];
        $postIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['post_id'], $result['rows'])));
        foreach ($postIds as $postId) {
            $post = $this->posts->findWithSecret($postId);
            if ($post !== null) {
                $titles[$postId] = (string) $post['title'];
            }
        }

        $rows = [];
        foreach ($result['rows'] as $row) {
            $secret = (bool) $row['is_secret'];
            $rows[] = [
                'id'         => (int) $row['id'],
                'post_id'    => (int) $row['post_id'],
                'post_title' => $titles[(int) $row['post_id']] ?? '(지워진 글)',
                // 비밀 댓글의 본문은 목록에 흘리지 않는다.
                'excerpt'    => $secret ? '비밀 댓글' : $this->plainExcerpt((string) $row['content'], 80),
                'is_secret'  => $secret,
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'data' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => $result['total'] === 0 ? 0 : (int) ceil($result['total'] / $perPage),
            'author' => $author,
            'author_name' => (string) $user['display_name'],
        ];
    }

    /** 목록에 보일 한 줄. 태그를 걷고 길면 자른다. */
    private function plainExcerpt(string $html, int $length): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }
```

`$this->users`·`$this->boards`·`$this->posts` 가 이 클래스에 없으면 프로퍼티와 세터를 더하고 `src/App.php` 의 `commentService()` 조립부에서 넣는다(PostService 의 `setUserRepository()` 와 같은 방식). 이미 있으면 그대로 쓴다 — **파일을 열어 확인한 뒤 필요한 것만 더하라.**

- [ ] **Step 5: 컨트롤러**

`src/Web/Controller/CommentController.php` 의 `byAuthor()` 를 채운다:

```php
    public function byAuthor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $list = $this->app->commentService()->listByAuthor($this->app->guestAcl(), $request->getQueryParams());

        return View::fromRequest($request)->render($response, 'posts/comments_by_author', ['list' => $list]);
    }
```

- [ ] **Step 6: 화면**

`templates/default/posts/comments_by_author.php` 전체:

```php
<?php $this->layout('layout') ?>
<?php
$commentsUrl = function (int $page) use ($list): string {
    $params = ['author=' . (int) ($list['author'] ?? 0)];
    if ($page > 1) { $params[] = 'page=' . $page; }
    return $this->url('comments.byAuthor') . '?' . implode('&', $params);
};
$who = $list['author_name'] ?? null;
?>
<?php $this->start('title') ?><?= $this->e($who !== null ? $who . ' 님의 댓글' : '회원 댓글') ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>all<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="page-head">
  <div>
    <h1><?= $this->e($who !== null ? $who . ' 님의 댓글' : '회원 댓글') ?></h1>
    <p class="page-sub"><?php if ($who !== null): ?>이 회원이 남긴 댓글을 최신순으로 모았습니다. 댓글 <strong><?= $this->e((string) $list['total']) ?></strong>개<?php else: ?>회원을 찾을 수 없습니다.<?php endif ?></p>
  </div>
  <?php if ($who !== null): ?>
  <div class="page-head-actions"><a class="btn btn-outline btn-sm" href="<?= $this->url('posts.all') ?>?author=<?= $this->e((string) $list['author']) ?>">이 사람의 글</a></div>
  <?php endif ?>
</div>

<section class="card">
  <ul class="list author-comments">
    <?php if ($list['data'] === []): ?>
      <li class="list-row author-comment-empty"><?= $this->e($who !== null ? '아직 남긴 댓글이 없습니다.' : '주소의 회원 번호를 확인해 주세요.') ?></li>
    <?php else: foreach ($list['data'] as $row): ?>
      <li class="list-row author-comment">
        <a class="author-comment-body" href="<?= $this->url('posts.show', ['id' => $row['post_id']]) ?>#comment-<?= $this->e((string) $row['id']) ?>">
          <span class="author-comment-text"><?php if ($row['is_secret']): ?><?= $this->icon('lock', 13) ?> <?php endif ?><?= $this->e($row['excerpt']) ?></span>
          <span class="author-comment-post"><?= $this->icon('board', 13) ?> <?= $this->e($row['post_title']) ?></span>
        </a>
        <time class="author-comment-date" datetime="<?= $this->e($row['created_at']) ?>"><?= $this->date($row['created_at'], 'Y.m.d') ?></time>
      </li>
    <?php endforeach; endif ?>
  </ul>
</section>

<?php $this->insert('posts/_pager', ['list' => $list, 'page_url' => $commentsUrl]) ?>
<?php $this->stop() ?>
```

- [ ] **Step 7: CSS (파일 끝에만 추가)**

```css
/* 회원 댓글 목록 */
.author-comments .author-comment{display:flex;align-items:center;gap:.75rem}
.author-comment-body{flex:1;min-width:0;display:grid;gap:.15rem}
.author-comment-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}
.author-comment-post{font-size:.75rem;color:var(--bc-soft);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.author-comment-date{font-size:.75rem;color:var(--bc-soft);white-space:nowrap}
.author-comment-empty{color:var(--bc-soft)}
```

- [ ] **Step 8: 통과 확인 + 전체 + 문법**

Run: `./vendor/bin/phpunit tests/Web/AuthorCommentsTest.php && ./vendor/bin/phpunit && for f in $(git ls-files '*.php'); do php -l $f > /dev/null || echo "SYNTAX $f"; done`
Expected: OK, 문법 오류 없음.

- [ ] **Step 9: 실제 확인**

스크래치 서버에서 `/comments?author={회원번호}` 를 열어 줄이 나오는지, 줄을 누르면 `#comment-` 로 이동하는지 확인하고 화면을 찍어 본다(Task 2 Step 8 과 같은 방법). 라이브는 공개 화면 몇 개만 200 인지 본다:

```bash
for u in / /posts /comments /boards/free; do printf '%s %s\n' "$(curl -s -o /dev/null -w '%{http_code}' https://gnucms.gnuboard.net$u)" "$u"; done
```

- [ ] **Step 10: 커밋**

```bash
git add src/Repository/CommentRepository.php src/Service/CommentService.php src/App.php src/Web/Controller/CommentController.php templates/default/posts/comments_by_author.php tests/Web/AuthorCommentsTest.php
git commit -m "feat: 회원이 남긴 댓글을 모아 보는 화면을 만든다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

CSS 는 파일 끝 훅만 골라 스테이징한 뒤 `git commit --amend --no-edit` 한다(Task 2 Step 9 참고).
