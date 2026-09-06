# 게시판 공지와 전체 공지 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 관리자가 글쓰기·수정 화면에서 글을 공지로 올리고, 그 공지가 이 게시판에만 붙는지 모든 게시판에 붙는지 고를 수 있게 한다.

**Architecture:** `posts.is_notice`(있음)는 그대로 두고 `notice_scope`(`board`|`global`) 한 칸을 더한다. 목록 위 공지 줄은 그 게시판 공지와 전체 공지를 함께 뽑되, 전체 공지는 그 글이 사는 게시판을 읽을 수 있는 사람에게만 보인다. 지정은 글쓰기·수정 폼의 라디오 하나로 하고 서버가 관리자 권한을 확인한다.

**Tech Stack:** PHP 8.4 / Slim 4 / PDO(SQLite·MySQL/MariaDB), PHPUnit 10, PHP 파일 템플릿(`PhpTemplate`), daisyUI 5 CDN.

스펙: `docs/superpowers/specs/2026-08-31-notice-scope-design.md`

## Global Constraints

- 저장: `posts.notice_scope VARCHAR(10) NOT NULL DEFAULT 'board'` — `board` | `global`. `is_notice = 1` 일 때만 뜻이 있다. Schema `VERSION` 을 `12` 로 올리고 멱등 마이그레이션 + 새 설치 DDL 둘 다 손본다.
- 폼 이름은 `notice`, 값은 `none` | `board` | `global`. 그 밖의 값은 `none` 으로 본다. 옛 `is_notice` 입력도 계속 받아들이되 **`notice` 가 없을 때만** 본다.
- 공지 지정은 그 게시판의 관리자만(`Acl::isAdminFor($board)`). 권한 없이 보내면 기존처럼 `assertAdminFor()` 가 막는다.
- **전체 공지는 그 글이 사는 게시판을 읽을 수 있는 사람에게만 보인다.** 목록 정렬은 전체 공지 먼저, 그다음 게시판 공지, 각각 최신순. 정렬식은 지원 DB 공통 문법(`CASE WHEN … THEN 0 ELSE 1 END`)으로 적는다. 방언별 SQL 금지, 한 문장에서 이름·위치 파라미터 혼용 금지(이 프로젝트는 `ATTR_EMULATE_PREPARES=false`).
- 홈, 전체 글(`/posts`), 관리 콘솔은 손대지 않는다. 이름이 "공지사항"인 게시판은 이 기능과 무관하다.
- 템플릿 출력은 전부 `$this->e()`(예외: `url/asset/html/icon/json/insert/block`). 문구는 한국어.
- 기존 445개 테스트는 그대로 통과해야 한다. `./vendor/bin/phpunit`.
- 이 체크아웃은 **라이브 사이트**다: `config/config.php`·`storage/` 를 건드리지 않는다. 화면 확인은 스크래치 복사본에서 한다. 스키마는 다음 요청에서 `SchemaUpgrader` 가 백업을 뜨고 스스로 옮긴다.
- 커밋 메시지는 한국어 접두어 + 끝에 `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

## File Structure

| 파일 | 책임 |
|---|---|
| `src/Db/Schema.php` (수정) | `VERSION` 12, `posts` DDL 에 `notice_scope`, `migrateBoards()` 안에 멱등 추가 |
| `src/Repository/PostRepository.php` (수정) | `COLUMNS` 에 `notice_scope`, `hydrate()` 기본값, `notices($boardId, $readableBoardIds)` |
| `src/Service/PostService.php` (수정) | `noticeFrom()` 입력 해석, `create()`/`update()` 저장, `summary()` 에 `notice_scope`, `listPosts()` 가 읽을 수 있는 게시판 목록을 넘김 |
| `src/Web/Controller/PostController.php` (수정) | 폼에 `can_manage_board` 전달 |
| `templates/default/posts/create.php`, `edit.php` (수정) | 공지 라디오 |
| `templates/default/posts/index.php` (수정) | 전체 공지 뱃지 |
| `public/themes/default/theme.css` (수정, 끝에만) | `.notice-scope` 뱃지 여백 |
| 테스트 | `tests/Db/SchemaTest.php`(칸 존재), `tests/Service/NoticeScopeTest.php`(신규), `tests/Web/NoticeFormTest.php`(신규) |

---

### Task 1: 저장 칸 (notice_scope)

**Files:**
- Modify: `src/Db/Schema.php`
- Modify: `src/Repository/PostRepository.php` (`COLUMNS`, `hydrate()`, `create()` 기본값)
- Test: `tests/Db/SchemaTest.php` (메서드 추가)

**Interfaces:**
- Produces: `posts.notice_scope` 칸(`VARCHAR(10) NOT NULL DEFAULT 'board'`); `PostRepository` 가 읽어 오는 행에 `notice_scope` 키가 항상 있다(기본 `'board'`).

- [ ] **Step 1: 실패하는 테스트**

`tests/Db/SchemaTest.php` 클래스 끝에:

```php
    #[DataProvider('connectionProvider')]
    public function testPostsHaveNoticeScope(array $config): void
    {
        $db = $this->freshDatabase($config);

        $db->execute(
            'INSERT INTO ' . $db->q('posts')
            . ' (board_id, title, content, author_name, is_notice, is_secret, view_count, comment_count, created_at, updated_at)'
            . ' VALUES (1, ?, ?, ?, 1, 0, 0, 0, ?, ?)',
            ['공지', '본문', '관리자', '2026-08-31 00:00:00', '2026-08-31 00:00:00']
        );

        $row = $db->selectOne('SELECT notice_scope FROM ' . $db->q('posts'));
        self::assertSame('board', $row['notice_scope'], '기본값은 이 게시판 공지다');
    }
```

`tests/Db/SchemaTest.php` 가 `#[DataProvider]` 특성을 쓰는지(파일 위 `use PHPUnit\Framework\Attributes\DataProvider;`) 확인하고, 옛 `/** @dataProvider */` 주석 방식이면 그 방식에 맞춘다. `posts` DDL 의 NOT NULL 칸이 위 INSERT 에 다 들어갔는지 `src/Db/Schema.php` 에서 확인하고 빠진 것이 있으면 채운다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit --filter testPostsHaveNoticeScope`
Expected: FAIL — `notice_scope` 칸이 없다.

- [ ] **Step 3: 새 설치 DDL 과 판 번호**

`src/Db/Schema.php`:

- `public const VERSION = '11';` → `'12'`.
- `posts` DDL 의 `is_notice` 줄 다음에 칸을 더한다:

```
                notice_scope   VARCHAR(10)  NOT NULL DEFAULT 'board',
```

(같은 DDL 안의 다른 칸들과 자리를 맞춰 적는다.)

- [ ] **Step 4: 멱등 마이그레이션**

`migrateBoards()` 안(다른 `addColumnIfMissing('posts', …)` 호출들 옆)에:

```php
        // 공지가 이 게시판만인지 전체인지. 옛 공지는 전부 이 게시판 공지로 본다.
        $this->addColumnIfMissing('posts', 'notice_scope', "VARCHAR(10) NOT NULL DEFAULT 'board'");
```

`migrateBoards()` 에 `posts` 칸을 더하는 자리가 없으면, `addColumnIfMissing('posts', 'image_key', …)` 를 부르는 메서드를 찾아 그 옆에 둔다(`migrateEditorImages()` 일 수 있다). 어느 쪽이든 `migrateAll()` 이 부르는 메서드 안이어야 한다.

- [ ] **Step 5: 저장소가 칸을 읽고 쓰게 한다**

`src/Repository/PostRepository.php`:

- `COLUMNS` 상수의 `is_notice,` 뒤에 `notice_scope,` 를 더한다(문자열이 여러 줄로 이어져 있으니 SQL 이 성한지 확인).
- 기본값 배열(파일 위 `'is_notice' => 0,` 가 있는 배열)에 `'notice_scope' => 'board',` 를 더한다.
- `hydrate()` 가 정수로 바꾸는 칸 목록(`['id', 'board_id', 'is_notice', …]`)에는 넣지 않는다. 대신 값이 없을 때를 대비해 `hydrate()` 끝에 다음을 더한다:

```php
        $row['notice_scope'] = ($row['notice_scope'] ?? '') === 'global' ? 'global' : 'board';
```

- [ ] **Step 6: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit --filter 'SchemaTest|PostRepositoryTest' && ./vendor/bin/phpunit`
Expected: 둘 다 OK.

- [ ] **Step 7: 커밋**

```bash
git add src/Db/Schema.php src/Repository/PostRepository.php tests/Db/SchemaTest.php
git commit -m "feat: 공지의 범위를 담을 칸을 더한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: 입력 해석과 저장 (서비스)

**Files:**
- Modify: `src/Service/PostService.php`
- Test: `tests/Service/NoticeScopeTest.php` (신규)

**Interfaces:**
- Consumes: Task 1 의 `posts.notice_scope`.
- Produces: `PostService::create()`/`update()` 가 입력 `notice`(`none|board|global`)를 읽어 `is_notice`·`notice_scope` 를 저장한다. `summary()` 결과에 `'notice_scope' => 'board'|'global'` 이 실린다.

- [ ] **Step 1: 실패하는 테스트**

`tests/Service/NoticeScopeTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Service;

use GnuCms\Auth\Acl;
use GnuCms\Auth\Identity;
use GnuCms\Error\DomainError;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NoticeScopeTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testAdminCanPinToThisBoardOrEveryBoard(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);

        $plain = $app->postService()->create($acl, 'free', [
            'title' => '보통 글', 'content' => '본문입니다', 'notice' => 'none',
        ]);
        $board = $app->postService()->create($acl, 'free', [
            'title' => '게시판 공지', 'content' => '본문입니다', 'notice' => 'board',
        ]);
        $global = $app->postService()->create($acl, 'free', [
            'title' => '전체 공지', 'content' => '본문입니다', 'notice' => 'global',
        ]);

        self::assertFalse($plain['is_notice']);
        self::assertTrue($board['is_notice']);
        self::assertSame('board', $board['notice_scope']);
        self::assertTrue($global['is_notice']);
        self::assertSame('global', $global['notice_scope']);
    }

    #[DataProvider('connectionProvider')]
    public function testUpdateCanRaiseAndLowerANotice(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $post = $app->postService()->create($acl, 'free', ['title' => '글', 'content' => '본문입니다']);

        $raised = $app->postService()->update($acl, $post['id'], [
            'title' => '글', 'content' => '본문입니다', 'notice' => 'global',
        ]);
        self::assertTrue($raised['is_notice']);
        self::assertSame('global', $raised['notice_scope']);

        $lowered = $app->postService()->update($acl, $post['id'], [
            'title' => '글', 'content' => '본문입니다', 'notice' => 'none',
        ]);
        self::assertFalse($lowered['is_notice']);
        self::assertSame('board', $lowered['notice_scope'], '공지를 내리면 범위도 기본으로 돌아간다');
    }

    #[DataProvider('connectionProvider')]
    public function testMemberCannotPinANotice(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), [
            'board_key' => 'free', 'name' => '자유', 'perm_write' => 'member',
        ]);
        $member = new Acl(Identity::user('7', '회원사람', false));

        try {
            $app->postService()->create($member, 'free', [
                'title' => '몰래 공지', 'content' => '본문입니다', 'notice' => 'global',
            ]);
            self::fail('관리자가 아니면 공지를 올릴 수 없어야 한다');
        } catch (DomainError $e) {
            self::assertContains($e->status(), [401, 403]);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownNoticeValueIsTreatedAsNotANotice(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);

        $post = $app->postService()->create($acl, 'free', [
            'title' => '글', 'content' => '본문입니다', 'notice' => '엉뚱한값',
        ]);

        self::assertFalse($post['is_notice']);
    }

    #[DataProvider('connectionProvider')]
    public function testOldIsNoticeInputStillWorks(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);

        // notice 가 없으면 옛 입력을 본다. 옛 폼과 테스트가 깨지지 않게.
        $post = $app->postService()->create($acl, 'free', [
            'title' => '옛 방식 공지', 'content' => '본문입니다', 'is_notice' => '1',
        ]);

        self::assertTrue($post['is_notice']);
        self::assertSame('board', $post['notice_scope']);
    }
}
```

`postService()->create()`/`update()` 가 돌려주는 배열에 `is_notice`·`notice_scope` 가 있는지 확인하라 — `detail()` 을 거쳐 나온다. `detail()` 에 `notice_scope` 가 없으면 Step 3 에서 더한다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Service/NoticeScopeTest.php`
Expected: FAIL — `notice` 입력을 아직 읽지 않는다.

- [ ] **Step 3: 서비스 구현**

`src/Service/PostService.php` 에 헬퍼를 더한다(다른 private 헬퍼들 옆):

```php
    /**
     * 폼의 공지 선택을 읽는다. none|board|global 이며 그 밖의 값은 공지 아님으로 본다.
     * notice 가 아예 없을 때만 옛 is_notice 입력을 본다(옛 폼 호환).
     *
     * @return array{is_notice: int, notice_scope: string}
     */
    private function noticeFrom(Validator $v, array $input): array
    {
        if (array_key_exists('notice', $input)) {
            $choice = $v->inList('notice', ['none', 'board', 'global'], 'none');
        } else {
            $choice = $v->bool('is_notice', false) ? 'board' : 'none';
        }

        return $choice === 'none'
            ? ['is_notice' => 0, 'notice_scope' => 'board']
            : ['is_notice' => 1, 'notice_scope' => $choice];
    }
```

`Validator::inList(string $field, array $allowed, string $default): string` 가 있는지 확인하고(있다), 없으면 직접 비교로 적는다.

`create()` 의 공지 처리 블록

```php
        $data['is_notice'] = 0;

        if (array_key_exists('is_notice', $input) && $v->bool('is_notice', false)) {
            $acl->assertAdminFor($board);
            $data['is_notice'] = 1;
        }
```

을 다음으로 바꾼다:

```php
        $notice = $this->noticeFrom($v, $input);
        $data['is_notice'] = $notice['is_notice'];
        $data['notice_scope'] = $notice['notice_scope'];
        if ($data['is_notice'] === 1) {
            // 공지는 그 게시판의 관리자만 올릴 수 있다.
            $acl->assertAdminFor($board);
        }
```

`update()` 의

```php
        if (array_key_exists('is_notice', $input)) {
            $acl->assertAdminFor($board);
            $data['is_notice'] = $v->bool('is_notice', false) ? 1 : 0;
        }
```

를 다음으로 바꾼다:

```php
        if (array_key_exists('notice', $input) || array_key_exists('is_notice', $input)) {
            $acl->assertAdminFor($board);
            $notice = $this->noticeFrom($v, $input);
            $data['is_notice'] = $notice['is_notice'];
            $data['notice_scope'] = $notice['notice_scope'];
        }
```

(수정에서는 공지를 내릴 때도 관리자 확인이 필요하므로 값과 무관하게 먼저 확인한다.)

`summary()` 의 `'is_notice' => (bool) $row['is_notice'],` 다음 줄에:

```php
            'notice_scope'  => ($row['notice_scope'] ?? 'board') === 'global' ? 'global' : 'board',
```

`detail()` 에도 같은 키가 실려 있지 않으면 같은 줄을 더한다(테스트가 `create()` 결과에서 읽는다).

- [ ] **Step 4: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Service/NoticeScopeTest.php && ./vendor/bin/phpunit`
Expected: 둘 다 OK. 기존 테스트 중 `is_notice` 를 보내는 것이 있으면 옛 경로가 살아 있으므로 그대로 통과해야 한다.

- [ ] **Step 5: 커밋**

```bash
git add src/Service/PostService.php tests/Service/NoticeScopeTest.php
git commit -m "feat: 공지 범위를 폼 입력에서 읽어 저장한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: 목록에 전체 공지를 함께 뽑기

**Files:**
- Modify: `src/Repository/PostRepository.php` (`notices()`)
- Modify: `src/Service/PostService.php` (`listPosts()`)
- Modify: `templates/default/posts/index.php` (뱃지)
- Modify: `public/themes/default/theme.css` (끝에만)
- Test: `tests/Web/NoticeListTest.php` (신규)

**Interfaces:**
- Consumes: Task 2 의 `summary()['notice_scope']`, `BoardService::listBoards(Acl): array`(읽을 수 있는 게시판).
- Produces: `PostRepository::notices(int $boardId, array $readableBoardIds = []): array` — 그 게시판 공지 + 읽을 수 있는 게시판의 전체 공지, 전체 공지 먼저·각각 최신순, 중복 없음.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/NoticeListTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NoticeListTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testGlobalNoticeShowsOnEveryBoardAndBoardNoticeOnlyOnItsOwn(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->boardService()->create($acl, ['board_key' => 'qna', 'name' => '질문']);

        $app->postService()->create($acl, 'free', [
            'title' => '점검 안내', 'content' => '본문입니다', 'notice' => 'global',
        ]);
        $app->postService()->create($acl, 'free', [
            'title' => '자유 규칙', 'content' => '본문입니다', 'notice' => 'board',
        ]);

        $free = $this->body($this->get($app, '/boards/free'));
        self::assertStringContainsString('점검 안내', $free);
        self::assertStringContainsString('자유 규칙', $free);
        self::assertStringContainsString('전체 공지', $free);

        $qna = $this->body($this->get($app, '/boards/qna'));
        self::assertStringContainsString('점검 안내', $qna, '전체 공지는 다른 게시판에도 보인다');
        self::assertStringNotContainsString('자유 규칙', $qna, '게시판 공지는 자기 게시판에만');
    }

    #[DataProvider('connectionProvider')]
    public function testGlobalNoticeInAnUnreadableBoardIsHidden(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'staff', 'name' => '내부', 'perm_read' => 'admin',
        ]);
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'staff', [
            'title' => '내부 전용 공지', 'content' => '본문입니다', 'notice' => 'global',
        ]);

        // 손님으로 자유게시판을 본다. 읽을 수 없는 게시판의 공지는 제목도 새면 안 된다.
        $body = $this->body($this->get($app, '/boards/free'));

        self::assertStringNotContainsString('내부 전용 공지', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testNoticeIsNotRepeatedInTheNormalList(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $app->postService()->create($acl, 'free', [
            'title' => '한 번만 보이는 공지', 'content' => '본문입니다', 'notice' => 'global',
        ]);

        $body = $this->body($this->get($app, '/boards/free'));

        self::assertSame(1, substr_count($body, '한 번만 보이는 공지'));
    }
}
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/NoticeListTest.php`
Expected: FAIL — 전체 공지가 다른 게시판에 안 나온다.

- [ ] **Step 3: 저장소**

`src/Repository/PostRepository.php` 의 `notices()` 를 다음으로 바꾼다:

```php
    /**
     * 목록 맨 위에 붙일 공지. 이 게시판의 공지와, 읽을 수 있는 게시판에 올라온
     * 전체 공지를 함께 뽑는다. 전체 공지가 먼저, 각각 최신순이다.
     *
     * @param int[] $readableBoardIds 읽을 수 있는 게시판 번호. 전체 공지는 이 안에서만 온다
     */
    public function notices(int $boardId, array $readableBoardIds = []): array
    {
        $params = ['board_id' => $boardId];
        $globalClause = '';
        if ($readableBoardIds !== []) {
            $marks = [];
            foreach (array_values($readableBoardIds) as $i => $id) {
                $marks[] = ':r' . $i;
                $params['r' . $i] = (int) $id;
            }
            $globalClause = " OR (notice_scope = 'global' AND board_id IN (" . implode(', ', $marks) . '))';
        }

        $rows = $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM ' . $this->db->q('posts')
            . ' WHERE deleted_at IS NULL AND is_notice = 1'
            . ' AND (board_id = :board_id' . $globalClause . ')'
            // 전체 공지를 먼저. 방언마다 불리언 정렬이 달라 CASE 로 적는다.
            . " ORDER BY CASE WHEN notice_scope = 'global' THEN 0 ELSE 1 END, id DESC",
            $params
        );

        return array_map([$this, 'hydrate'], $rows);
    }
```

- [ ] **Step 4: 서비스가 읽을 수 있는 게시판을 넘긴다**

`src/Service/PostService.php` 의 `listPosts()` 에서 공지를 모으는 부분

```php
        $notices = [];
        foreach ($this->posts->notices((int) $board['id']) as $row) {
            $notices[] = $this->summary($row);
        }
```

을 다음으로 바꾼다:

```php
        // 전체 공지는 읽을 수 있는 게시판의 것만 온다. 관리자 전용 게시판의 공지가
        // 제목만이라도 새어 나가지 않게 한다.
        $readableBoardIds = [];
        foreach ($this->boards->listBoards($acl) as $readable) {
            $readableBoardIds[] = (int) $readable['id'];
        }

        $notices = [];
        foreach ($this->posts->notices((int) $board['id'], $readableBoardIds) as $row) {
            $notices[] = $this->summary($row);
        }
```

`$this->boards` 가 `BoardService` 인지 확인한다(`listPosts()` 첫 줄이 `$this->boards->getEntity(...)` 를 부르므로 맞다).

- [ ] **Step 5: 화면 뱃지**

`templates/default/posts/index.php` 의 공지 줄에서

```php
        <span class="badge badge-primary badge-soft badge-sm">공지</span>
```

을 다음으로 바꾼다:

```php
        <?php if (($post['notice_scope'] ?? 'board') === 'global'): ?>
          <span class="badge badge-accent badge-soft badge-sm notice-scope">전체 공지</span>
        <?php else: ?>
          <span class="badge badge-primary badge-soft badge-sm">공지</span>
        <?php endif ?>
```

`public/themes/default/theme.css` 끝에:

```css
/* 전체 공지 뱃지는 게시판 공지와 색으로 갈린다 */
.notice-list .notice-scope{white-space:nowrap}
```

- [ ] **Step 6: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Web/NoticeListTest.php && ./vendor/bin/phpunit`
Expected: 둘 다 OK.

- [ ] **Step 7: 커밋**

```bash
git add src/Repository/PostRepository.php src/Service/PostService.php templates/default/posts/index.php public/themes/default/theme.css tests/Web/NoticeListTest.php
git commit -m "feat: 전체 공지를 모든 게시판 목록 위에 보인다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: 글쓰기·수정 폼의 공지 선택

**Files:**
- Modify: `src/Web/Controller/PostController.php` (`createForm()`, `renderEditForm()`, `editForm()`)
- Modify: `templates/default/posts/create.php`, `templates/default/posts/edit.php`
- Test: `tests/Web/NoticeFormTest.php` (신규)

**Interfaces:**
- Consumes: Task 2 의 입력 이름 `notice`(`none|board|global`), Task 3 의 목록 표시.
- Produces: 폼 데이터 `can_manage_board`(bool). 수정 화면의 `values['notice']` 는 현재 값(`none|board|global`).

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/NoticeFormTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NoticeFormTest extends WebTestCase
{
    private function loginAsAdmin(\GnuCms\App $app): void
    {
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);
    }

    private function loginAsMember(\GnuCms\App $app): void
    {
        $id = $app->users()->create('member@example.com', password_hash('member-password-123', PASSWORD_DEFAULT), '회원사람');
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'member@example.com', 'password' => 'member-password-123',
        ]);
    }

    #[DataProvider('connectionProvider')]
    public function testAdminSeesTheNoticeChoiceAndMemberDoesNot(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유']);

        $this->loginAsAdmin($app);
        $adminForm = $this->body($this->get($app, '/boards/free/new'));
        self::assertStringContainsString('name="notice"', $adminForm);
        self::assertStringContainsString('전체 게시판 공지', $adminForm);

        $app2 = $this->makeApp($dbConfig);
        $app2->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유']);
        $this->loginAsMember($app2);
        self::assertStringNotContainsString('name="notice"', $this->body($this->get($app2, '/boards/free/new')));
    }

    #[DataProvider('connectionProvider')]
    public function testAdminCanPinThroughTheForm(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유']);
        $this->loginAsAdmin($app);

        $created = $this->post($app, '/boards/free/new', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '폼으로 올린 전체 공지', 'content' => '본문입니다', 'notice' => 'global',
        ]);
        self::assertSame(303, $created->getStatusCode());

        $body = $this->body($this->get($app, '/boards/free'));
        self::assertStringContainsString('전체 공지', $body);
        self::assertStringContainsString('폼으로 올린 전체 공지', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testEditFormRemembersTheCurrentScope(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유']);
        $post = $app->postService()->create($acl, 'free', [
            'title' => '전체 공지', 'content' => '본문입니다', 'notice' => 'global',
        ]);
        $this->loginAsAdmin($app);

        $body = $this->body($this->get($app, '/posts/' . $post['id'] . '/edit'));

        self::assertMatchesRegularExpression('/value="global"[^>]*checked/', $body);
    }
}
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/NoticeFormTest.php`
Expected: FAIL — 폼에 `name="notice"` 가 없다.

- [ ] **Step 3: 컨트롤러가 권한과 현재 값을 넘긴다**

`src/Web/Controller/PostController.php`:

`createForm()` 의 렌더 데이터에 한 줄을 더한다:

```php
            'can_manage_board' => $acl->isAdminFor($entity),
```

`renderEditForm()` 의 렌더 데이터에도:

```php
            'can_manage_board' => $acl->isAdminFor($loaded['board']),
```

`editForm()` 이 넘기는 `values` 배열(제목·본문·분류·비밀글이 들어 있는 곳)에 현재 공지 값을 더한다:

```php
            'notice' => $loaded['post']['is_notice']
                ? (($loaded['post']['notice_scope'] ?? 'board') === 'global' ? 'global' : 'board')
                : 'none',
```

`loadForRead()` 가 돌려주는 `post` 행에 `notice_scope` 가 실려 오는지 확인한다(Task 1 에서 `COLUMNS` 에 더했으므로 온다).

- [ ] **Step 4: 폼 마크업**

`templates/default/posts/create.php` 의 비밀글 `<?php endif ?>` 다음(첨부 조각 앞)에:

```php
      <?php if (!empty($can_manage_board)): ?>
        <?php // 공지는 그 게시판의 관리자만 올린다. 회원에게는 이 칸이 아예 없다. ?>
        <fieldset class="fieldset">
          <legend class="fieldset-legend"><?= $this->icon('megaphone', 15) ?> 공지</legend>
          <div class="chip-bar" role="radiogroup" aria-label="공지 범위">
            <?php foreach (['none' => '공지 아님', 'board' => '이 게시판 공지', 'global' => '전체 게시판 공지'] as $value => $label): ?>
              <label class="btn btn-sm chip-radio">
                <input type="radio" name="notice" value="<?= $this->e($value) ?>"<?= $this->def($values['notice'] ?? null, 'none') === $value ? ' checked' : '' ?>>
                <span><?= $this->e($label) ?></span>
              </label>
            <?php endforeach ?>
          </div>
          <p class="fieldset-label">전체 게시판 공지는 이 게시판을 읽을 수 있는 사람에게만 보입니다.</p>
        </fieldset>
      <?php endif ?>
```

`templates/default/posts/edit.php` 에도 같은 블록을 같은 자리(비밀글 다음)에 넣는다. 두 파일의 폼 구조가 다르면 각 파일을 열어 비밀글 토글 뒤에 맞춰 넣는다.

`chip-radio` 는 이미 분류 선택에서 쓰는 짜임이라 CSS 를 새로 만들 필요가 없다(`.chip-radio` 는 `theme.css` 에 있다).

- [ ] **Step 5: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Web/NoticeFormTest.php && ./vendor/bin/phpunit && php -l templates/default/posts/create.php && php -l templates/default/posts/edit.php`
Expected: 전부 OK.

- [ ] **Step 6: 실제 화면 확인**

스크래치 복사본을 최신 코드로 맞추고 내장 서버를 띄운 뒤(관리자로 로그인해) 글쓰기 화면에 공지 선택이 보이는지, 전체 공지로 쓴 글이 다른 게시판 목록 맨 위에 "전체 공지" 뱃지로 보이는지 스크린샷으로 확인한다:

```bash
S=/tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/attach-run
cp -r src templates public "$S"/ && cd "$S" && (php -S 127.0.0.1:8096 -t public > server.log 2>&1 &)
```

스크래치 DB 의 스키마는 첫 요청에서 `SchemaUpgrader` 가 스스로 옮긴다. 확인 뒤 서버를 끈다(`ps -eo pid,comm,args | awk '$2=="php" && /8096/ {print $1}' | xargs -r kill`).

- [ ] **Step 7: 커밋**

```bash
git add src/Web/Controller/PostController.php templates/default/posts/create.php templates/default/posts/edit.php tests/Web/NoticeFormTest.php
git commit -m "feat: 관리자가 글쓰기 화면에서 공지와 그 범위를 고른다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: 라이브 확인과 문서

**Files:**
- Modify: `docs/template-development.md` (9절 목록 항목 설명에 한 줄)
- 검증: 전체 스위트, 문법, 라이브 스키마 이행 확인

- [ ] **Step 1: 문서**

`docs/template-development.md` 의 "9. 게시판 목록 형태" 절 끝에 한 줄을 더한다:

```markdown
목록 위의 공지 줄에는 그 게시판의 공지와 전체 공지가 함께 온다. 항목의 `notice_scope`
가 `global` 이면 "전체 공지" 뱃지를 붙인다.
```

- [ ] **Step 2: 전체 스위트와 문법**

Run: `./vendor/bin/phpunit && for f in $(git ls-files '*.php'); do php -l $f > /dev/null || echo "SYNTAX $f"; done`
Expected: OK, 문법 오류 없음.

- [ ] **Step 3: 라이브 스키마 이행 확인**

코드가 이미 라이브에 반영돼 있으므로 첫 요청에서 `SchemaUpgrader` 가 백업을 뜨고 칸을 더한다. 확인:

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://gnucms.gnuboard.net/
sqlite3 storage/board.sqlite "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('schema_version','schema_upgraded_at');"
sqlite3 storage/board.sqlite "SELECT COUNT(*) FROM pragma_table_info('posts') WHERE name='notice_scope';"
ls -t storage/backups/ | head -2
```

Expected: 200, 도장이 `12.…`, `notice_scope` 칸 1개, 새 백업 파일. **`storage/` 는 읽기만 한다.**

- [ ] **Step 4: 공개 화면 스모크**

```bash
for u in / /posts /comments /boards/free /login; do printf '%s %s\n' "$(curl -s -o /dev/null -w '%{http_code}' https://gnucms.gnuboard.net$u)" "$u"; done
```

Expected: 전부 200.

- [ ] **Step 5: 커밋**

```bash
git add docs/template-development.md
git commit -m "docs: 공지 범위를 템플릿 안내에 적는다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
