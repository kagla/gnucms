# 게시판 파일 첨부 구현 계획

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `use_file` 게시판의 글 쓰기/수정 화면에 즉시 업로드·드래그 순서 조정이 되는 첨부 UI 를 붙이고, 용량·개수를 사이트 설정으로 정한다.

**Architecture:** 백엔드는 기존 것을 재사용한다 — 서명 디스크립터 `AttachmentService`(업로드·검증), `posts.attachments` JSON 칸, `FileController` 다운로드, `posts/show.php` 첨부 목록. 새로 만드는 것은 웹 업로드 라우트 하나(`POST /boards/{key}/files`), 폼 조각(`posts/_attachments.php`, 외부 라이브러리 없는 HTML5 드래그), 사이트 설정 두 칸, 그리고 "버려진 파일 정리" 단추다.

**Tech Stack:** PHP 8.1+/Slim 4/PDO(SQLite·MySQL/MariaDB), PHPUnit 10, daisyUI 5 CDN. 서버에 composer·npm·컴파일 없음 — 자산은 빌드 없이 넣는다.

스펙: `docs/superpowers/specs/2026-08-31-post-attachments-design.md`

## Global Constraints

- 사이트 설정 키: `attach_max_mb`(기본 `5`, 1~1024), `attach_limit`(기본 `5`, 0~999, **0 = 무제한**). site_settings 값은 문자열로 저장한다(기존 관례).
- 게시판별 허용은 기존 `use_file`(기본 0 = 불가). 꺼진 게시판은 첨부 UI 를 그리지 않고, 서버(`AttachmentService::upload`, `PostService::verifyAttachments`)는 기존대로 거부한다.
- 업로드 라우트의 CSRF 는 편집기 이미지와 같은 방식: **쿼리 문자열** `?csrf_token=` (`EditorImageController` 참조). AJAX 오류는 413·422 만 JSON `{error: 문구}` 로, 그 밖은 그대로 던진다(편집기와 같은 규칙).
- 순서: 폼의 hidden input `attachments[N][…]` 순서 = 저장 순서 = 글 화면 표시 순서.
- "고아 파일"이라는 말을 쓰지 않는다. 부르는 이름은 **"버려진 파일"**. 정리는 mtime 24시간 안 된 파일을 건너뛴다.
- 문구는 한국어(짧은 문장체). 템플릿 출력은 전부 `$this->e()`(예외: `url/asset/html/icon/json/insert/block` 결과). 커밋 메시지는 한국어 접두어 + 끝에 `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.
- 기존 359개 테스트는 그대로 통과해야 한다. `./vendor/bin/phpunit`.
- 이 체크아웃은 라이브 사이트다: `config/config.php`·`storage/` 를 건드리지 않는다. 실제 화면 확인은 스크래치 복사본(`php -S`)에서 한다.

## File Structure

| 파일 | 책임 |
|---|---|
| `src/Cms/CmsService.php` (수정) | 설정 두 키의 기본값·정수화·검증, `settings()` 메모이즈 |
| `templates/default/admin/settings.php` (수정) | "파일 첨부" 절 두 칸 + 서버 한계 힌트, "버려진 파일 정리" 단추 |
| `src/Service/AttachmentService.php` (수정) | `withSignature()`, `serverMaxMb()`, 정리의 24시간 가드 |
| `src/App.php` (수정) | `attachments()` 에 용량 설정 반영, `postService()` 에 개수 설정 주입 |
| `src/Service/PostService.php` (수정) | `setAttachmentLimit()`, 개수 검사 |
| `src/Web/Controller/FileController.php` (수정) | `upload()` — 웹 업로드 JSON 엔드포인트 |
| `src/Web/Controller/AdminCmsController.php` (수정) | `uploadsGc()` |
| `src/Web/Routes.php` (수정) | `POST /boards/{key}/files`, `POST /admin/uploads/gc` |
| `src/Web/Controller/PostController.php` (수정) | 쓰기/수정 폼에 첨부 값 전달(수정은 서명 재부착) |
| `templates/default/posts/_attachments.php` (신규) | 첨부 UI 조각(업로드·목록·드래그·hidden input) |
| `templates/default/posts/{create,edit}.php` (수정) | 조각 삽입 |
| `public/themes/default/theme.css` (수정) | `.attach-*` 스타일 |

---

### Task 1: 사이트 설정 두 칸 (attach_max_mb / attach_limit)

**Files:**
- Modify: `src/Cms/CmsService.php` (DEFAULT_SETTINGS, `settings()`, `saveSettings()`)
- Modify: `src/Service/AttachmentService.php` (`serverMaxMb()` 만)
- Modify: `templates/default/admin/settings.php` ("표시와 가입" 절 뒤)
- Test: `tests/Web/AdminPageTest.php` (메서드 추가), `tests/Service/AttachmentServiceTest.php` (신규, serverMaxMb 만)

**Interfaces:**
- Consumes: `Validator::int(string $field, int $default, int $min, int $max): int`, `CmsRepository::saveSettings(array<string,string>)`.
- Produces: `CmsService::settings()['attach_max_mb']: int`, `['attach_limit']: int` (0 = 무제한); `AttachmentService::serverMaxMb(): int` (static, `upload_max_filesize`·`post_max_size` 중 작은 값을 MB 로); 설정 화면 name=`attach_max_mb`, `attach_limit`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AdminPageTest.php` 클래스 끝에:

```php
    #[DataProvider('connectionProvider')]
    public function testSettingsPageSavesAttachmentLimits(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);

        $page = $this->body($this->get($app, '/admin/settings'));
        self::assertStringContainsString('파일 첨부', $page);
        self::assertStringContainsString('name="attach_max_mb"', $page);
        self::assertStringContainsString('name="attach_limit"', $page);
        self::assertStringContainsString('0 = 무제한', $page);

        $base = [
            'csrf_token' => $_SESSION['csrf_token'],
            'site_name' => '사이트', 'site_tagline' => '소개', 'home_title' => '홈', 'home_intro' => '소개',
            'registration_enabled' => '1', 'theme' => 'default',
        ];
        $saved = $this->post($app, '/admin/settings', $base + ['attach_max_mb' => '20', 'attach_limit' => '0']);
        self::assertSame(303, $saved->getStatusCode());
        $settings = $app->cmsService()->settings();
        self::assertSame(20, $settings['attach_max_mb']);
        self::assertSame(0, $settings['attach_limit']);

        // 범위를 벗어나면 422 로 폼을 다시 그린다. Validator::int 는 스칼라가 아니거나
        // 숫자가 아니면 기본값으로 떨어지므로, 범위 초과만 검사할 수 있다.
        $bad = $this->post($app, '/admin/settings', $base + ['attach_max_mb' => '2000', 'attach_limit' => '5']);
        self::assertSame(422, $bad->getStatusCode());
    }
```

`Validator::int()` 의 실제 동작(범위 밖이면 fail 인지 잘라내기인지)을 먼저 읽어라: `src/Validation/Validator.php:141` 부근. **잘라내기(clamp)라면** 422 대신 저장 뒤 값이 1024 로 잘렸는지 단언으로 바꾼다 — 두 동작 중 실제에 맞추되, 테스트가 실제 동작을 못박아야 한다.

`tests/Service/AttachmentServiceTest.php` (신규):

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Service;

use GnuCms\Service\AttachmentService;
use PHPUnit\Framework\TestCase;

final class AttachmentServiceTest extends TestCase
{
    public function testServerMaxMbIsPositive(): void
    {
        // php.ini 값에 따라 다르지만 항상 1 이상의 정수여야 한다.
        self::assertGreaterThanOrEqual(1, AttachmentService::serverMaxMb());
    }

    public function testIniShorthandIsParsed(): void
    {
        self::assertSame(8, AttachmentService::iniToMb('8M'));
        self::assertSame(1024, AttachmentService::iniToMb('1G'));
        self::assertSame(1, AttachmentService::iniToMb('1536K'));
        self::assertSame(2, AttachmentService::iniToMb('2097152'));
        self::assertSame(PHP_INT_MAX, AttachmentService::iniToMb('0'), '0 은 무제한이라는 뜻이다');
        self::assertSame(PHP_INT_MAX, AttachmentService::iniToMb('-1'));
    }
}
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit --filter 'testSettingsPageSavesAttachmentLimits|AttachmentServiceTest'`
Expected: FAIL — '파일 첨부' 없음, `serverMaxMb` 없음.

- [ ] **Step 3: CmsService**

`DEFAULT_SETTINGS` 에 두 줄 추가:

```php
        'attach_max_mb' => '5',
        'attach_limit' => '5',
```

`settings()` 의 `return` 앞에:

```php
        $settings['attach_max_mb'] = max(1, (int) $settings['attach_max_mb']);
        $settings['attach_limit'] = max(0, (int) $settings['attach_limit']);
```

`saveSettings()` 의 `$settings` 배열에 두 항목 추가(‘theme’ 뒤):

```php
            'attach_max_mb' => (string) $v->int('attach_max_mb', 5, 1, 1024),
            'attach_limit' => (string) $v->int('attach_limit', 5, 0, 999),
```

(`Validator::int` 가 범위 밖에서 fail 을 낸다면 그대로, 잘라낸다면 그대로 둔다 — Step 1 에서 확인한 실제 동작에 맞는 테스트가 이미 있다.)

- [ ] **Step 4: AttachmentService 에 serverMaxMb/iniToMb**

`AttachmentService` 클래스 안(생성자 위)에:

```php
    /** 서버 PHP 가 실제로 받아 주는 파일당 최대 크기(MB). 설정 화면의 힌트에 쓴다. */
    public static function serverMaxMb(): int
    {
        return max(1, min(
            self::iniToMb((string) ini_get('upload_max_filesize')),
            self::iniToMb((string) ini_get('post_max_size'))
        ));
    }

    /** php.ini 의 8M·1G 같은 축약 표기를 MB 정수로. 0·음수는 무제한이라는 뜻이다. */
    public static function iniToMb(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return PHP_INT_MAX;
        }
        $unit = strtoupper(substr($value, -1));
        $number = (float) $value;
        $bytes = match ($unit) {
            'G' => $number * 1073741824,
            'M' => $number * 1048576,
            'K' => $number * 1024,
            default => $number,
        };
        if ($bytes <= 0) {
            return PHP_INT_MAX;
        }

        return max(1, (int) floor($bytes / 1048576));
    }
```

- [ ] **Step 5: 설정 화면**

`templates/default/admin/settings.php` 의 "표시와 가입" `</div>` 뒤(폼 안, form-actions 앞)에:

```php
      <div class="form-section">
        <h2 class="form-section-title">파일 첨부</h2>
        <fieldset class="fieldset<?php if (array_key_exists('attach_max_mb', $errors)): ?> is-invalid<?php endif ?>">
          <legend class="fieldset-legend">파일당 최대 용량 (MB)</legend>
          <input class="input input-bordered input-block" type="number" name="attach_max_mb" min="1" max="1024" value="<?= $this->e((string) ($values['attach_max_mb'] ?? 5)) ?>" required>
          <?php if (array_key_exists('attach_max_mb', $errors)): ?><p class="validator-hint"><?= $this->icon('warning', 14) ?> <?= $this->e($errors['attach_max_mb']) ?></p><?php endif ?>
          <p class="fieldset-label">서버 PHP 한계는 <?= $this->e((string) \GnuCms\Service\AttachmentService::serverMaxMb()) ?> MB 입니다. 그보다 크게 적어도 거기까지만 받습니다.</p>
        </fieldset>
        <fieldset class="fieldset<?php if (array_key_exists('attach_limit', $errors)): ?> is-invalid<?php endif ?>">
          <legend class="fieldset-legend">글당 첨부 개수</legend>
          <input class="input input-bordered input-block" type="number" name="attach_limit" min="0" max="999" value="<?= $this->e((string) ($values['attach_limit'] ?? 5)) ?>" required>
          <?php if (array_key_exists('attach_limit', $errors)): ?><p class="validator-hint"><?= $this->icon('warning', 14) ?> <?= $this->e($errors['attach_limit']) ?></p><?php endif ?>
          <p class="fieldset-label">0 = 무제한. 파일 첨부는 게시판 설정에서 게시판마다 켭니다.</p>
        </fieldset>
      </div>
```

템플릿에서 클래스 FQCN 호출은 이 파일이 처음이다 — 다른 곳처럼 값을 컨트롤러에서 넘기는 편이 결이 맞다면 `AdminCmsController::settingsForm()/settings()` 의 렌더 데이터에 `'server_max_mb' => AttachmentService::serverMaxMb(),` 를 더하고 템플릿은 `$this->e((string) $server_max_mb)` 를 쓴다. **컨트롤러 방식을 택하라** (템플릿은 데이터만 받는다는 기존 규칙).

- [ ] **Step 6: 통과 확인**

Run: `./vendor/bin/phpunit --filter 'AdminPageTest|AttachmentServiceTest'`
Expected: OK.

- [ ] **Step 7: 커밋**

```bash
git add src/Cms/CmsService.php src/Service/AttachmentService.php src/Web/Controller/AdminCmsController.php templates/default/admin/settings.php tests/Web/AdminPageTest.php tests/Service/AttachmentServiceTest.php
git commit -m "feat: 첨부 용량·개수를 사이트 설정으로 정한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: 한도 배선 (App → AttachmentService·PostService)

**Files:**
- Modify: `src/App.php` (`attachments()`, `postService()`)
- Modify: `src/Service/PostService.php` (`setAttachmentLimit()`, `verifyAttachments()`)
- Modify: `src/Cms/CmsService.php` (`settings()` 메모이즈)
- Test: `tests/Service/PostAttachmentLimitTest.php` (신규)

**Interfaces:**
- Consumes: Task 1 의 `settings()['attach_max_mb'|'attach_limit']`.
- Produces: `PostService::setAttachmentLimit(int $limit): void` (0 = 무제한). `App::attachments()` 가 만드는 서비스의 `max_bytes` 는 `attach_max_mb * 1048576`.

- [ ] **Step 1: 실패하는 테스트**

`tests/Service/PostAttachmentLimitTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Service;

use GnuCms\Error\DomainError;
use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PostAttachmentLimitTest extends WebTestCase
{
    #[DataProvider('connectionProvider')]
    public function testAttachmentCountIsLimitedBySetting(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['attach_limit' => '2']);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);

        $files = [];
        for ($i = 0; $i < 3; $i++) {
            $files[] = $app->attachments()->upload($acl, 'free', $this->fakeUpload('파일' . $i . '.txt', '내용' . $i));
        }

        $post = $app->postService()->create($acl, 'free', [
            'title' => '두 개는 된다', 'content' => '본문', 'attachments' => [$files[0], $files[1]],
        ]);
        self::assertCount(2, $post['attachments']);

        try {
            $app->postService()->create($acl, 'free', [
                'title' => '세 개는 안 된다', 'content' => '본문', 'attachments' => $files,
            ]);
            self::fail('422 가 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(422, $e->status());
            self::assertSame('첨부는 2개까지입니다.', $e->details()['attachments']);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testZeroMeansUnlimited(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['attach_limit' => '0']);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);

        $attachments = [];
        for ($i = 0; $i < 7; $i++) {
            $attachments[] = $app->attachments()->upload($acl, 'free', $this->fakeUpload('파일' . $i . '.txt', 'x'));
        }
        $post = $app->postService()->create($acl, 'free', [
            'title' => '무제한', 'content' => '본문', 'attachments' => $attachments,
        ]);

        self::assertCount(7, $post['attachments']);
    }

    #[DataProvider('connectionProvider')]
    public function testOrderIsPreservedAsSubmitted(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $first = $app->attachments()->upload($acl, 'free', $this->fakeUpload('가.txt', '1'));
        $second = $app->attachments()->upload($acl, 'free', $this->fakeUpload('나.txt', '2'));

        $post = $app->postService()->create($acl, 'free', [
            'title' => '순서', 'content' => '본문', 'attachments' => [$second, $first],
        ]);

        self::assertSame(['나.txt', '가.txt'], array_column($post['attachments'], 'name'));
    }

    #[DataProvider('connectionProvider')]
    public function testUploadSizeFollowsSiteSetting(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $app->cms()->saveSettings(['attach_max_mb' => '1']);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);

        try {
            $app->attachments()->upload($acl, 'free', $this->fakeUpload('큰파일.txt', str_repeat('a', 1048577)));
            self::fail('413 이 나와야 한다');
        } catch (DomainError $e) {
            self::assertSame(413, $e->status());
        }
    }
}
```

`fakeUpload()` 헬퍼가 `tests/Support/WebTestCase.php` 에 이미 있다(이름·내용으로 임시 파일을 만든다). 없다면 `AttachmentDownloadTest` 가 쓰는 그 헬퍼를 찾아 같은 것을 쓴다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Service/PostAttachmentLimitTest.php`
Expected: `testAttachmentCountIsLimitedBySetting`·`testUploadSizeFollowsSiteSetting` FAIL (지금은 한도 미적용, config 5MB 고정), 나머지 둘은 PASS 일 수 있다.

- [ ] **Step 3: CmsService::settings() 메모이즈**

요청 하나에서 `settings()` 가 Kernel·App 조립에서 여러 번 불리게 되므로 캐시한다.
`private ?array $settingsCache = null;` 프로퍼티를 더하고:

```php
    public function settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }
        try {
            $stored = $this->cms->settings();
        } catch (DomainError $e) {
            // 아직 CMS 마이그레이션 전인 기존 설치도 오류 화면과 설치 진입점을
            // 정상적으로 열 수 있어야 한다. 실제 요청의 DB 오류는 각 기능에서 처리한다.
            $stored = [];
        }
        $settings = array_merge(self::DEFAULT_SETTINGS, $stored);
        $settings['registration_enabled'] = $settings['registration_enabled'] === '1';
        $settings['attach_max_mb'] = max(1, (int) $settings['attach_max_mb']);
        $settings['attach_limit'] = max(0, (int) $settings['attach_limit']);

        return $this->settingsCache = $settings;
    }
```

`saveSettings()` 끝(`$this->cms->saveSettings($settings);` 뒤)에 `$this->settingsCache = null;` 를 더한다. **주의**: 마이그레이션 전 `$stored = []` 로 떨어진 결과도 캐시되면 설치 화면 뒤 같은 요청에서 갱신을 못 본다 — 실패(빈) 결과는 캐시하지 않는다: `catch` 경로에서는 캐시에 넣지 말고 바로 계산해 반환하도록 분기한다.

- [ ] **Step 4: PostService 개수 검사**

프로퍼티·세터(클래스 상단, `$attachments` 옆):

```php
    /** 글당 첨부 개수 한도. 0 = 무제한. App 이 사이트 설정에서 넣는다. */
    private int $attachmentLimit = 0;

    public function setAttachmentLimit(int $limit): void
    {
        $this->attachmentLimit = max(0, $limit);
    }
```

`verifyAttachments()` 의 `use_file` 검사 다음에:

```php
        if ($this->attachmentLimit > 0 && count($input) > $this->attachmentLimit) {
            throw DomainError::validation(['attachments' => '첨부는 ' . $this->attachmentLimit . '개까지입니다.']);
        }
```

- [ ] **Step 5: App 배선**

`App::attachments()` 의 config 인자를 사이트 설정으로 덮는다:

```php
            $uploads = (array) $this->config('uploads', []);
            $uploads['max_bytes'] = $this->cmsService()->settings()['attach_max_mb'] * 1048576;
            $this->attachmentService = new AttachmentService(
                $this->boardService(),
                $this->postService(),
                $this->posts(),
                $uploads,
                (string) $this->config('auth.secret', '')
            );
            $this->postService()->setAttachmentLimit($this->cmsService()->settings()['attach_limit']);
            $this->postService()->setAttachmentService($this->attachmentService);
```

(개수 한도는 첨부를 실제로 쓰는 요청에서만 필요하므로 `attachments()` 조립 시점에 넣는 것으로 충분하다 — `verifyAttachments()` 는 `$this->attachments` 가 연결된 뒤에만 도달한다.)

- [ ] **Step 6: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Service/PostAttachmentLimitTest.php && ./vendor/bin/phpunit`
Expected: 둘 다 OK.

- [ ] **Step 7: 커밋**

```bash
git add src/App.php src/Service/PostService.php src/Cms/CmsService.php tests/Service/PostAttachmentLimitTest.php
git commit -m "feat: 첨부 용량·개수 설정을 업로드와 글 저장 검사에 잇는다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: withSignature() 와 정리의 24시간 가드

**Files:**
- Modify: `src/Service/AttachmentService.php`
- Test: `tests/Service/AttachmentServiceTest.php` (Task 1 파일에 추가)

**Interfaces:**
- Produces: `AttachmentService::withSignature(array $stored): array` — 저장된 디스크립터(`id,name,size,mime,path`, `index` 등 여분 키 무시)에 `sig` 를 다시 붙인 배열. `verify()` 를 통과한다. `AttachmentService::GC_MIN_AGE_SECONDS = 86400`; `collectGarbage()` 는 mtime 이 그보다 새 파일을 건너뛴다.

- [ ] **Step 1: 실패하는 테스트** (`tests/Service/AttachmentServiceTest.php` 에 추가 — 이 테스트들은 DB 가 필요하므로 클래스를 `WebTestCase` 상속으로 바꾸고 `connectionProvider` 를 쓴다. Task 1 에서 만든 두 테스트(serverMaxMb·iniToMb)는 provider 없이 그대로 둔다.)

```php
    #[DataProvider('connectionProvider')]
    public function testWithSignatureRoundTripsThroughVerify(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $descriptor = $app->attachments()->upload($acl, 'free', $this->fakeUpload('문서.txt', '내용'));
        $post = $app->postService()->create($acl, 'free', [
            'title' => '글', 'content' => '본문', 'attachments' => [$descriptor],
        ]);

        // 저장된 디스크립터에는 서명이 없다. 수정 화면이 다시 서명해 폼에 싣는다.
        $stored = $post['attachments'][0];
        self::assertArrayNotHasKey('sig', $stored);
        $signed = $app->attachments()->withSignature($stored);
        self::assertSame($stored['name'], $app->attachments()->verify($signed)['name']);
    }

    #[DataProvider('connectionProvider')]
    public function testCollectGarbageSkipsFreshFiles(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $fresh = $app->attachments()->upload($acl, 'free', $this->fakeUpload('방금.txt', '1'));
        $old = $app->attachments()->upload($acl, 'free', $this->fakeUpload('어제.txt', '2'));
        touch($old['path'], time() - 90000);

        $result = $app->attachments()->collectGarbage($acl);

        self::assertFileExists($fresh['path'], '24시간이 안 된 파일은 작성 중일 수 있으니 남긴다');
        self::assertFileDoesNotExist($old['path']);
        self::assertSame(1, $result['deleted']);
    }

    #[DataProvider('connectionProvider')]
    public function testCollectGarbageKeepsReferencedFiles(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $kept = $app->attachments()->upload($acl, 'free', $this->fakeUpload('붙음.txt', '1'));
        $app->postService()->create($acl, 'free', ['title' => '글', 'content' => '본문', 'attachments' => [$kept]]);
        touch($kept['path'], time() - 90000);

        $result = $app->attachments()->collectGarbage($acl);

        self::assertFileExists($kept['path']);
        self::assertSame(0, $result['deleted']);
    }
```

주의: `makeApp` 은 `uploads.dir` 를 `sys_get_temp_dir().'/gnucms-test-uploads'` 로 공유한다. 이전 실행이 남긴 파일이 `collectGarbage` 의 개수 단언을 흔들 수 있으니, 두 GC 테스트 첫 줄에서 업로드 폴더를 비운다:

```php
        $this->purgeTestUploads(); // WebTestCase 에 protected 헬퍼로 추가: uploads 테스트 폴더의 파일 전부 삭제
```

`purgeTestUploads()` 는 `tests/Support/WebTestCase.php` 에 추가한다:

```php
    /** 공유 임시 업로드 폴더를 비운다. collectGarbage 의 개수 단언이 이전 실행에 흔들리지 않게. */
    protected function purgeTestUploads(): void
    {
        $root = sys_get_temp_dir() . '/' . GNUCMS_ID . '-test-uploads';
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
```

(`GNUCMS_ID` 상수와 실제 업로드 폴더 이름은 `makeApp()` 의 `uploads.dir` 값과 일치시켜라 — 파일을 열어 확인.)

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Service/AttachmentServiceTest.php`
Expected: `withSignature` 없음 오류, `testCollectGarbageSkipsFreshFiles` 는 fresh 파일까지 지워져 FAIL.

- [ ] **Step 3: 구현**

`AttachmentService` 에:

```php
    /** 정리가 건너뛰는 나이. 이보다 새 파일은 작성 중인 폼의 것일 수 있다. */
    public const GC_MIN_AGE_SECONDS = 86400;

    /**
     * 저장된 디스크립터에 서명을 다시 붙인다. 수정 화면이 기존 첨부를
     * 폼의 hidden input 으로 되실을 때 쓴다. index 같은 여분 키는 버린다.
     */
    public function withSignature(array $stored): array
    {
        $descriptor = [
            'id'   => (string) ($stored['id'] ?? ''),
            'name' => (string) ($stored['name'] ?? ''),
            'size' => (int) ($stored['size'] ?? 0),
            'mime' => (string) ($stored['mime'] ?? ''),
            'path' => (string) ($stored['path'] ?? ''),
        ];
        $descriptor['sig'] = $this->sign($descriptor);

        return $descriptor;
    }
```

`collectGarbage()` 루프의 `if (isset($referenced[$path])) { continue; }` 다음에:

```php
            // 방금 올라온 파일은 아직 글을 저장하지 않은 폼의 것일 수 있다.
            if ($item->getMTime() > time() - self::GC_MIN_AGE_SECONDS) {
                continue;
            }
```

- [ ] **Step 4: 통과 확인**

Run: `./vendor/bin/phpunit tests/Service/AttachmentServiceTest.php`
Expected: OK.

- [ ] **Step 5: 커밋**

```bash
git add src/Service/AttachmentService.php tests/Service/AttachmentServiceTest.php tests/Support/WebTestCase.php
git commit -m "feat: 저장된 첨부에 서명을 다시 붙이고 정리는 하루 안 된 파일을 건너뛴다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: 웹 업로드 라우트 POST /boards/{key}/files

**Files:**
- Modify: `src/Web/Controller/FileController.php`
- Modify: `src/Web/Routes.php` (편집기 이미지 라우트 근처, `/boards/{key}/editor/images` 위)
- Test: `tests/Web/AttachmentUploadTest.php` (신규)

**Interfaces:**
- Consumes: `AttachmentService::upload(Acl, string $boardKey, array $file)` — `$_FILES` 모양 배열(`name,size,tmp_name,error,type`).
- Produces: `POST /boards/{key}/files?csrf_token=…` (`boards.files.upload`) → 200 JSON `{id,name,size,mime,path,sig,size_label}`. 413/422 → JSON `{error: 문구}`. CSRF 실패 403(HTML), 권한 없음 401/403(HTML) — 편집기 이미지와 같은 규칙.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AttachmentUploadTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\UploadedFile;

final class AttachmentUploadTest extends WebTestCase
{
    /** @return array{0: \GnuCms\App} 관리자 로그인이 끝난 앱 */
    private function loggedInApp(array $dbConfig, bool $useFile = true): \GnuCms\App
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유', 'use_file' => $useFile]);

        return $app;
    }

    private function tmpFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'att-upload-');
        file_put_contents($path, $content);

        return $path;
    }

    #[DataProvider('connectionProvider')]
    public function testUploadReturnsSignedDescriptor(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig);
        $file = new UploadedFile($this->tmpFile('안녕'), '메모.txt', 'text/plain', 6);

        $response = $this->upload($app, '/boards/free/files?csrf_token=' . urlencode($_SESSION['csrf_token']), ['file' => $file]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode($this->body($response), true);
        self::assertSame('메모.txt', $data['name']);
        self::assertNotSame('', (string) $data['sig']);
        self::assertFileExists($data['path']);
        self::assertArrayHasKey('size_label', $data);
    }

    #[DataProvider('connectionProvider')]
    public function testBoardWithoutFilesRejectsAsJson(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig, false);
        $file = new UploadedFile($this->tmpFile('x'), 'a.txt', 'text/plain', 1);

        $response = $this->upload($app, '/boards/free/files?csrf_token=' . urlencode($_SESSION['csrf_token']), ['file' => $file]);

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode($this->body($response), true);
        self::assertStringContainsString('첨부를 쓰지 않습니다', $data['error']);
    }

    #[DataProvider('connectionProvider')]
    public function testOversizeIsRejectedAsJson413(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig);
        $app->cms()->saveSettings(['attach_max_mb' => '1']);
        $file = new UploadedFile($this->tmpFile(str_repeat('a', 1048577)), 'big.txt', 'text/plain', 1048577);

        $response = $this->upload($app, '/boards/free/files?csrf_token=' . urlencode($_SESSION['csrf_token']), ['file' => $file]);

        self::assertSame(413, $response->getStatusCode());
        self::assertArrayHasKey('error', json_decode($this->body($response), true));
    }

    #[DataProvider('connectionProvider')]
    public function testMissingCsrfIsForbidden(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig);
        $file = new UploadedFile($this->tmpFile('x'), 'a.txt', 'text/plain', 1);

        self::assertSame(403, $this->upload($app, '/boards/free/files', ['file' => $file])->getStatusCode());
    }

    #[DataProvider('connectionProvider')]
    public function testMissingFileIsRejected(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig);

        $response = $this->upload($app, '/boards/free/files?csrf_token=' . urlencode($_SESSION['csrf_token']), []);

        self::assertSame(422, $response->getStatusCode());
    }
}
```

주의: `makeApp` 의 `attach_max_mb` 설정은 `App::attachments()` 가 **처음 불릴 때** 읽힌다. `testOversize…` 는 업로드 요청 전에 설정을 저장하므로 문제없지만, 같은 앱 인스턴스로 설정을 바꾼 뒤 다시 업로드하는 테스트는 쓰지 마라(서비스가 이미 조립됨).

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/AttachmentUploadTest.php`
Expected: 404 (라우트 없음) 계열 FAIL.

- [ ] **Step 3: FileController::upload**

`FileController` 에 (use 문에 `Psr\Http\Message\UploadedFileInterface` 추가):

```php
    /**
     * 글쓰기 폼이 파일을 고르는 즉시 부르는 업로드. 서명된 디스크립터를 돌려주고,
     * 글이 저장될 때 그 서명으로 되검증하므로 임시 표가 필요 없다.
     */
    public function upload(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->assertCsrf($request->getQueryParams());
        $uploaded = $request->getUploadedFiles()['file'] ?? null;
        if (!$uploaded instanceof UploadedFileInterface) {
            return $this->json($response->withStatus(422), ['error' => '파일이 없습니다.']);
        }

        try {
            $descriptor = $this->app->attachments()->upload(
                $this->app->guestAcl(),
                (string) $args['key'],
                $this->toFilesArray($uploaded)
            );
        } catch (DomainError $e) {
            // 용량·형식 오류는 폼이 문구로 보여 준다. 권한 같은 판단은 그대로 내보낸다.
            if (!in_array($e->status(), [413, 422], true)) {
                throw $e;
            }
            $message = $e->details() !== [] ? implode(' ', $e->details()) : $e->getMessage();

            return $this->json($response->withStatus($e->status()), ['error' => $message]);
        }

        $descriptor['size_label'] = $this->sizeLabel((int) $descriptor['size']);

        return $this->json($response, $descriptor);
    }

    /** PSR-7 업로드 파일을 AttachmentService 가 받는 $_FILES 모양으로 바꾼다. */
    private function toFilesArray(UploadedFileInterface $uploaded): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gnucms-att-');
        if ($tmp === false) {
            throw DomainError::internal('임시 파일을 만들 수 없습니다.');
        }
        if ((int) $uploaded->getError() === UPLOAD_ERR_OK) {
            // moveTo() 는 실제 SAPI 에서는 move_uploaded_file 을 쓰고, 테스트에서는 rename 한다.
            $uploaded->moveTo($tmp);
        }

        return [
            'name'     => (string) $uploaded->getClientFilename(),
            'size'     => (int) $uploaded->getSize(),
            'tmp_name' => $tmp,
            'error'    => (int) $uploaded->getError(),
            'type'     => (string) $uploaded->getClientMediaType(),
        ];
    }

    private function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    private function json(ResponseInterface $response, array $payload): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function assertCsrf(array $input): void
    {
        $expected = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        $given = isset($input['csrf_token']) && is_scalar($input['csrf_token']) ? (string) $input['csrf_token'] : '';
        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            throw DomainError::forbidden('요청을 확인할 수 없습니다. 다시 시도해 주세요.');
        }
    }
```

(`json`/`assertCsrf` 가 `EditorImageController` 와 중복이지만 기존에도 컨트롤러마다 두는 관례다 — 그대로 따른다.)

- [ ] **Step 4: 라우트**

`src/Web/Routes.php` 의 `/boards/{key}/editor/images` 등록 앞에:

```php
        $slim->post('/boards/{key}/files', [$files, 'upload'])->setName('boards.files.upload');
```

(`$files` 변수는 이미 그 위에서 `new FileController($app)` 로 만들어져 있다 — 등록 순서상 변수 정의 뒤에 두어라.)

- [ ] **Step 5: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Web/AttachmentUploadTest.php && ./vendor/bin/phpunit`
Expected: OK.

- [ ] **Step 6: 커밋**

```bash
git add src/Web/Controller/FileController.php src/Web/Routes.php tests/Web/AttachmentUploadTest.php
git commit -m "feat: 글쓰기 폼이 쓰는 첨부 업로드 경로를 연다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: 폼 조각 posts/_attachments.php + 컨트롤러 + CSS

**Files:**
- Create: `templates/default/posts/_attachments.php`
- Modify: `templates/default/posts/create.php`, `templates/default/posts/edit.php` (편집기 조각 아래, 저장 단추 위)
- Modify: `src/Web/Controller/PostController.php` (`editForm()` 첨부 프리로드)
- Modify: `public/themes/default/theme.css` (끝에 추가)
- Test: `tests/Web/AttachmentFormTest.php` (신규)

**Interfaces:**
- Consumes: `boards.files.upload` 라우트(Task 4), `AttachmentService::withSignature()`(Task 3), 전역 `site['attach_max_mb'|'attach_limit']`(Kernel 이 `cmsService()->settings()` 전체를 `site` 로 넘긴다), `csrf_token` 전역.
- Produces: 폼 필드 `attachments[N][id|name|size|mime|path|sig]` (DOM 순서 = 저장 순서). `values['attachments']` 로 재렌더·수정 프리로드.

- [ ] **Step 1: 실패하는 테스트**

`tests/Web/AttachmentFormTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AttachmentFormTest extends WebTestCase
{
    private function loggedInApp(array $dbConfig, bool $useFile): \GnuCms\App
    {
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유', 'use_file' => $useFile]);

        return $app;
    }

    #[DataProvider('connectionProvider')]
    public function testWriteFormShowsAttachmentUiOnlyWhenBoardAllowsFiles(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig, true);
        $body = $this->body($this->get($app, '/boards/free/write'));
        self::assertStringContainsString('data-attachments', $body);
        self::assertStringContainsString('/boards/free/files', $body);

        $app2 = $this->loggedInApp($dbConfig, false);
        self::assertStringNotContainsString('data-attachments', $this->body($this->get($app2, '/boards/free/write')));
    }

    #[DataProvider('connectionProvider')]
    public function testSubmittedOrderIsStoredAndShown(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig, true);
        $acl = $this->adminAcl();
        $first = $app->attachments()->upload($acl, 'free', $this->fakeUpload('가.txt', '1'));
        $second = $app->attachments()->upload($acl, 'free', $this->fakeUpload('나.txt', '2'));

        $created = $this->post($app, '/boards/free/write', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '순서 시험', 'content' => '본문',
            // 드래그로 순서를 바꾼 상태를 흉내 낸다: 나 → 가
            'attachments' => [$second, $first],
        ]);
        self::assertSame(303, $created->getStatusCode());

        $show = $this->body($this->get($app, $created->getHeaderLine('Location')));
        $positionOfSecond = strpos($show, '나.txt');
        $positionOfFirst = strpos($show, '가.txt');
        self::assertNotFalse($positionOfSecond);
        self::assertNotFalse($positionOfFirst);
        self::assertLessThan($positionOfFirst, $positionOfSecond, '제출한 순서대로 보여야 한다');
    }

    #[DataProvider('connectionProvider')]
    public function testEditFormPreloadsSignedAttachments(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig, true);
        $acl = $this->adminAcl();
        $descriptor = $app->attachments()->upload($acl, 'free', $this->fakeUpload('기존.txt', 'x'));
        $post = $app->postService()->create($acl, 'free', [
            'title' => '글', 'content' => '본문', 'attachments' => [$descriptor],
        ]);

        $body = $this->body($this->get($app, '/posts/' . $post['id'] . '/edit'));

        self::assertStringContainsString('기존.txt', $body);
        // 프리로드된 hidden input 에 서명이 실려 있어야 다시 저장할 수 있다.
        self::assertMatchesRegularExpression('/name="attachments\[\d+\]\[sig\]" value="[0-9a-f]/', $body);
    }

    #[DataProvider('connectionProvider')]
    public function testEditCanRemoveAnAttachment(array $dbConfig): void
    {
        $app = $this->loggedInApp($dbConfig, true);
        $acl = $this->adminAcl();
        $keep = $app->attachments()->upload($acl, 'free', $this->fakeUpload('남김.txt', '1'));
        $drop = $app->attachments()->upload($acl, 'free', $this->fakeUpload('뺌.txt', '2'));
        $post = $app->postService()->create($acl, 'free', [
            'title' => '글', 'content' => '본문', 'attachments' => [$keep, $drop],
        ]);

        $keepSigned = $app->attachments()->withSignature($post['attachments'][0]);
        $updated = $this->post($app, '/posts/' . $post['id'] . '/edit', [
            'csrf_token' => $_SESSION['csrf_token'],
            'title' => '글', 'content' => '본문',
            'attachments' => [$keepSigned],
        ]);
        self::assertSame(303, $updated->getStatusCode());

        $show = $this->body($this->get($app, '/posts/' . $post['id']));
        self::assertStringContainsString('남김.txt', $show);
        self::assertStringNotContainsString('뺌.txt', $show);
    }
}
```

주의: `posts.create` 폼의 실제 action 경로(`/boards/free/write`)와 수정 경로(`/posts/{id}/edit`)는 `src/Web/Routes.php` 에서 확인하고 다르면 맞춘다.

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit tests/Web/AttachmentFormTest.php`
Expected: `data-attachments` 없음 FAIL. (순서·수정 테스트는 백엔드가 이미 되므로 UI 단언만 실패할 수 있다 — 어떤 것이 왜 실패하는지 기록.)

- [ ] **Step 3: PostController 프리로드**

`editForm()` 의 렌더 값에 첨부를 더한다:

```php
        $attachments = [];
        foreach ($loaded['post']['attachments'] as $stored) {
            // 저장본에는 서명이 없다. 폼이 다시 제출할 수 있게 여기서 붙인다.
            $attachments[] = $this->app->attachments()->withSignature($stored);
        }

        return $this->renderEditForm($request, $response, $id, [
            'title'     => $loaded['post']['title'],
            'content'   => $loaded['post']['content'],
            'category'  => $loaded['post']['category'],
            'is_secret' => (bool) $loaded['post']['is_secret'],
            'image_key' => (string) ($loaded['post']['image_key'] ?? '') ?: bin2hex(random_bytes(16)),
            'attachments' => $attachments,
        ], []);
    }
```

(422 재렌더는 `values => $input` 에 `attachments` 배열이 그대로 실려 오므로 손댈 것 없다.)

- [ ] **Step 4: 조각**

`templates/default/posts/_attachments.php`:

```php
<?php
// 첨부 UI. use_file 게시판의 쓰기/수정 폼이 insert 한다.
//   board   게시판 (board_key)
//   values  폼 값. values['attachments'] = [ {id,name,size,mime,path,sig}, ... ]
// 파일은 고르는 즉시 boards.files.upload 로 올라가고, 서명된 디스크립터가
// hidden input 으로 실린다. 목록의 DOM 순서가 곧 저장 순서다.
$attach_rows = [];
foreach (($values['attachments'] ?? []) as $row) {
    if (is_array($row) && isset($row['sig'])) {
        $attach_rows[] = $row;
    }
}
?>
<fieldset class="fieldset attach-box" data-attachments
  data-url="<?= $this->url('boards.files.upload', ['key' => $board['board_key']]) ?>"
  data-limit="<?= $this->e((string) ($site['attach_limit'] ?? 5)) ?>"
  data-max-bytes="<?= $this->e((string) (($site['attach_max_mb'] ?? 5) * 1048576)) ?>">
  <legend class="fieldset-legend"><?= $this->icon('clip', 15) ?> 첨부파일
    <span class="legend-hint">파일당 <?= $this->e((string) ($site['attach_max_mb'] ?? 5)) ?>MB<?php if ((int) ($site['attach_limit'] ?? 5) > 0): ?> · <?= $this->e((string) $site['attach_limit']) ?>개까지<?php endif ?></span>
  </legend>
  <div class="attach-zone" data-attach-zone>
    <p>파일을 여기에 끌어다 놓거나</p>
    <label class="btn btn-sm">파일 선택<input type="file" multiple hidden data-attach-input></label>
  </div>
  <p class="validator-hint attach-error" data-attach-error hidden></p>
  <ul class="attach-list" data-attach-list>
    <?php foreach ($attach_rows as $i => $row): ?>
    <li class="attach-row" draggable="true">
      <span class="attach-grip" aria-hidden="true"><?= $this->icon('menu', 14) ?></span>
      <span class="attach-name"><?= $this->e($row['name']) ?></span>
      <span class="attach-size"><?= $this->e(number_format(((int) $row['size']) / 1024, 1)) ?> KB</span>
      <span class="attach-tools">
        <button type="button" class="btn btn-ghost btn-xs" data-attach-up aria-label="위로">↑</button>
        <button type="button" class="btn btn-ghost btn-xs" data-attach-down aria-label="아래로">↓</button>
        <button type="button" class="btn btn-ghost btn-xs" data-attach-remove aria-label="삭제"><?= $this->icon('close', 13) ?></button>
      </span>
      <?php foreach (['id', 'name', 'size', 'mime', 'path', 'sig'] as $field): ?>
      <input type="hidden" name="attachments[<?= $i ?>][<?= $field ?>]" data-field="<?= $field ?>" value="<?= $this->e((string) ($row[$field] ?? '')) ?>">
      <?php endforeach ?>
    </li>
    <?php endforeach ?>
  </ul>
</fieldset>
<script>
(function () {
  var box = document.querySelector('[data-attachments]');
  if (!box) { return; }
  var form = box.closest('form');
  var csrf = form ? form.querySelector('[name=csrf_token]') : null;
  var url = box.dataset.url + '?csrf_token=' + encodeURIComponent(csrf ? csrf.value : '');
  var limit = parseInt(box.dataset.limit, 10) || 0;      // 0 = 무제한
  var maxBytes = parseInt(box.dataset.maxBytes, 10) || 0;
  var list = box.querySelector('[data-attach-list]');
  var zone = box.querySelector('[data-attach-zone]');
  var input = box.querySelector('[data-attach-input]');
  var errorBox = box.querySelector('[data-attach-error]');

  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
    window.clearTimeout(showError.timer);
    showError.timer = window.setTimeout(function () { errorBox.hidden = true; }, 6000);
  }

  function renumber() {
    Array.prototype.forEach.call(list.children, function (row, index) {
      Array.prototype.forEach.call(row.querySelectorAll('input[type=hidden]'), function (hidden) {
        hidden.name = 'attachments[' + index + '][' + hidden.dataset.field + ']';
      });
    });
  }

  function doneCount() {
    return list.querySelectorAll('li:not(.is-uploading)').length;
  }

  function sizeLabel(bytes) {
    if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + ' MB'; }
    if (bytes >= 1024) { return (bytes / 1024).toFixed(1) + ' KB'; }
    return bytes + ' B';
  }

  function makeRow(name) {
    var row = document.createElement('li');
    row.className = 'attach-row is-uploading';
    row.draggable = true;
    row.innerHTML = '<span class="attach-grip" aria-hidden="true">≡</span>'
      + '<span class="attach-name"></span><span class="attach-size">올리는 중…</span>'
      + '<span class="attach-tools">'
      + '<button type="button" class="btn btn-ghost btn-xs" data-attach-up aria-label="위로">↑</button>'
      + '<button type="button" class="btn btn-ghost btn-xs" data-attach-down aria-label="아래로">↓</button>'
      + '<button type="button" class="btn btn-ghost btn-xs" data-attach-remove aria-label="삭제">✕</button></span>';
    row.querySelector('.attach-name').textContent = name;
    list.appendChild(row);
    return row;
  }

  function addFile(file) {
    if (limit > 0 && doneCount() >= limit) {
      showError('첨부는 ' + limit + '개까지입니다.');
      return;
    }
    if (maxBytes > 0 && file.size > maxBytes) {
      showError('"' + file.name + '" 은 파일당 한도를 넘습니다.');
      return;
    }
    var row = makeRow(file.name);
    var body = new FormData();
    body.append('file', file);
    fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok) { throw new Error(result.data.error || '업로드에 실패했습니다.'); }
        row.classList.remove('is-uploading');
        row.querySelector('.attach-size').textContent = result.data.size_label;
        ['id', 'name', 'size', 'mime', 'path', 'sig'].forEach(function (field) {
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.dataset.field = field;
          hidden.value = result.data[field];
          row.appendChild(hidden);
        });
        renumber();
      })
      .catch(function (error) {
        row.classList.add('is-failed');
        row.classList.remove('is-uploading');
        row.querySelector('.attach-size').textContent = '실패: ' + error.message;
      });
  }

  input.addEventListener('change', function () {
    Array.prototype.forEach.call(input.files, addFile);
    input.value = '';
  });
  ['dragover', 'dragleave', 'drop'].forEach(function (type) {
    zone.addEventListener(type, function (event) {
      // 행 드래그(순서 조정)는 무시하고 밖에서 온 파일만 받는다.
      if (!event.dataTransfer || Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') === -1) { return; }
      event.preventDefault();
      zone.classList.toggle('is-over', type === 'dragover');
      if (type === 'drop') { Array.prototype.forEach.call(event.dataTransfer.files, addFile); }
    });
  });

  list.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) { return; }
    var row = button.closest('li');
    if (button.hasAttribute('data-attach-remove')) {
      row.remove();
    } else if (button.hasAttribute('data-attach-up') && row.previousElementSibling) {
      list.insertBefore(row, row.previousElementSibling);
    } else if (button.hasAttribute('data-attach-down') && row.nextElementSibling) {
      list.insertBefore(row.nextElementSibling, row);
    }
    renumber();
  });

  // HTML5 드래그로 순서 조정. 라이브러리 없이 li 만 옮긴다.
  var dragging = null;
  list.addEventListener('dragstart', function (event) {
    dragging = event.target.closest('li');
    if (dragging) { dragging.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; }
  });
  list.addEventListener('dragend', function () {
    if (dragging) { dragging.classList.remove('is-dragging'); dragging = null; renumber(); }
  });
  list.addEventListener('dragover', function (event) {
    if (!dragging) { return; }
    event.preventDefault();
    var over = event.target.closest('li');
    if (!over || over === dragging) { return; }
    var rect = over.getBoundingClientRect();
    var after = event.clientY > rect.top + rect.height / 2;
    list.insertBefore(dragging, after ? over.nextElementSibling : over);
  });
})();
</script>
```

- [ ] **Step 5: create.php / edit.php 삽입**

두 파일 모두 편집기(본문) 블록 뒤·저장 단추 앞에(정확한 위치는 파일을 열어 폼 구조에 맞춘다):

```php
<?php if (!empty($board['use_file'])): ?><?php $this->insert('posts/_attachments', ['board' => $board, 'values' => $values]) ?><?php endif ?>
```

`$board['use_file']` 이 present 뷰(`BoardService::get`)에 boolean 으로 실려 오는지 확인(`use_file' => (bool)` — 이미 확인됨). edit.php 의 `$board` 데이터가 어떤 모양으로 오는지(renderEditForm 이 넘기는 값) 열어 보고 키 이름을 맞춘다.

- [ ] **Step 6: CSS**

`public/themes/default/theme.css` 끝에:

```css
/* 글쓰기 폼의 첨부 목록 */
.attach-box { margin-top: 1rem; }
.attach-zone { display: flex; align-items: center; gap: .75rem; padding: .9rem 1rem; border: 1.5px dashed color-mix(in oklch, currentColor 25%, transparent); border-radius: .75rem; }
.attach-zone.is-over { border-color: var(--color-primary, #2f7fe0); background: color-mix(in oklch, var(--color-primary, #2f7fe0) 8%, transparent); }
.attach-zone p { margin: 0; font-size: .875rem; opacity: .7; }
.attach-list { list-style: none; margin: .5rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .25rem; }
.attach-row { display: flex; align-items: center; gap: .5rem; padding: .4rem .6rem; border: 1px solid color-mix(in oklch, currentColor 12%, transparent); border-radius: .5rem; background: color-mix(in oklch, currentColor 3%, transparent); }
.attach-row.is-dragging { opacity: .5; }
.attach-row.is-failed .attach-size { color: var(--color-error, #d92d20); }
.attach-grip { cursor: grab; opacity: .5; }
.attach-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .875rem; }
.attach-size { font-size: .75rem; opacity: .6; white-space: nowrap; }
.attach-tools { display: flex; gap: .1rem; }
.attach-error { color: var(--color-error, #d92d20); }
```

(색 변수 이름은 theme.css 에서 기존에 쓰는 것을 확인해 맞춘다 — daisyUI 5 는 `var(--color-error)` 계열.)

- [ ] **Step 7: 통과 확인 + 전체**

Run: `./vendor/bin/phpunit tests/Web/AttachmentFormTest.php && ./vendor/bin/phpunit && php -l templates/default/posts/_attachments.php`
Expected: OK.

- [ ] **Step 8: 실제 화면 확인 (스크래치 복사본)**

라이브를 건드리지 않도록 저장소를 스크래치에 복사해 내장 서버로 확인한다:

```bash
S=/tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/attach-run
rm -rf "$S" && mkdir -p "$S" && cd /home/kagla/gnucms.com
git ls-files -z | xargs -0 -I{} cp --parents {} "$S"/ && cp -r vendor "$S"/ && mkdir -p "$S"/storage/{uploads,editor,logs,cache}
cd "$S" && php -S 127.0.0.1:8097 -t public > server.log 2>&1 &
```

설치기(다섯 단계)를 curl 로 통과시키고(관리자 owner@example.com), 로그인 → 게시판 만들기(use_file 켬) → 글쓰기 화면을 헤드리스 크롬으로 찍는다:

```bash
/usr/bin/google-chrome --headless=new --disable-gpu --window-size=900,1200 --screenshot="$S"/write.png "http://127.0.0.1:8097/boards/free/write"
```

(로그인 쿠키가 필요하면 CDP 로 로그인 후 찍는다 — 기존 세션들의 CDP 스크립트 방식.) 화면을 Read 로 보고 어긋난 CSS 를 고친다. 확인 후 서버를 죽인다(`ps -eo pid,args | grep 'php -S 127.0.0.1:8097'`).

- [ ] **Step 9: 커밋**

```bash
git add templates/default/posts/_attachments.php templates/default/posts/create.php templates/default/posts/edit.php src/Web/Controller/PostController.php public/themes/default/theme.css tests/Web/AttachmentFormTest.php
git commit -m "feat: 글쓰기 폼에 즉시 업로드·드래그 순서 조정 첨부 UI

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: 버려진 파일 정리 단추

**Files:**
- Modify: `src/Web/Controller/AdminCmsController.php`
- Modify: `src/Web/Routes.php` (admin 그룹)
- Modify: `templates/default/admin/settings.php` ("데이터 구조" 카드 안)
- Test: `tests/Web/AdminPageTest.php` (메서드 추가)

**Interfaces:**
- Consumes: `AttachmentService::collectGarbage(Acl): array{deleted: int, bytes: int}` (24시간 가드는 Task 3).
- Produces: `POST /admin/uploads/gc` (`admin.uploads.gc`) → 303 `admin.settings?gc={deleted}` — 카드가 알림으로 보여 준다.

- [ ] **Step 1: 실패하는 테스트** (`tests/Web/AdminPageTest.php` 에 추가)

```php
    #[DataProvider('connectionProvider')]
    public function testAbandonedUploadsCanBeCleanedFromSettings(array $dbConfig): void
    {
        $this->purgeTestUploads();
        $app = $this->makeApp($dbConfig);
        $id = $app->users()->create('admin@example.com', password_hash('admin-password-123', PASSWORD_DEFAULT), '관리자', true);
        $app->users()->verifyEmail($id);
        $this->get($app, '/login');
        $this->post($app, '/login', [
            'csrf_token' => $_SESSION['csrf_token'], 'email' => 'admin@example.com', 'password' => 'admin-password-123',
        ]);
        $app->boardService()->create($this->adminAcl(), ['board_key' => 'free', 'name' => '자유', 'use_file' => true]);
        $abandoned = $app->attachments()->upload($this->adminAcl(), 'free', $this->fakeUpload('버려짐.txt', 'x'));
        touch($abandoned['path'], time() - 90000);

        $page = $this->body($this->get($app, '/admin/settings'));
        self::assertStringContainsString('버려진 파일 정리', $page);

        $cleaned = $this->post($app, '/admin/uploads/gc', ['csrf_token' => $_SESSION['csrf_token']]);
        self::assertSame(303, $cleaned->getStatusCode());
        self::assertStringContainsString('gc=1', $cleaned->getHeaderLine('Location'));
        self::assertFileDoesNotExist($abandoned['path']);

        $after = $this->body($this->get($app, '/admin/settings', ['gc' => '1']));
        self::assertStringContainsString('버려진 파일 1개를 정리했습니다', $after);
    }
```

- [ ] **Step 2: 실패 확인**

Run: `./vendor/bin/phpunit --filter testAbandonedUploadsCanBeCleanedFromSettings`
Expected: '버려진 파일 정리' 없음 FAIL.

- [ ] **Step 3: 컨트롤러·라우트**

`AdminCmsController` 에 (`settings()` 뒤, 같은 `input`/`assertCsrf` 헬퍼 사용 — 이 컨트롤러의 기존 헬퍼 이름을 열어 확인):

```php
    public function uploadsGc(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->input($request);
        $this->assertCsrf($input);
        $result = $this->app->attachments()->collectGarbage($this->app->guestAcl());
        $url = RouteContext::fromRequest($request)->getRouteParser()
            ->urlFor('admin.settings', [], ['gc' => (string) $result['deleted']]);

        return $response->withHeader('Location', $url)->withStatus(303);
    }
```

`src/Web/Routes.php` 의 `admin/settings` 등록 옆에:

```php
        $slim->post('/admin/uploads/gc', [$cms, 'uploadsGc'])->setName('admin.uploads.gc');
```

- [ ] **Step 4: 카드 단추**

`templates/default/admin/settings.php` "데이터 구조" 카드의 `card-body` 끝(백업 표 아래)에:

```php
    <?php if (($query['gc'] ?? '') !== ''): ?>
      <div class="alert alert-success"><span aria-hidden="true"><?= $this->icon('check-circle', 18) ?></span><span>버려진 파일 <?= $this->e((string) (int) $query['gc']) ?>개를 정리했습니다.</span></div>
    <?php endif ?>
    <form method="post" action="<?= $this->url('admin.uploads.gc') ?>" class="schema-gc">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <button class="btn btn-sm" type="submit">버려진 파일 정리</button>
      <span class="schema-note">글에 붙지 못하고 하루 넘게 남은 업로드를 지웁니다.</span>
    </form>
```

`$query` 가 이 템플릿에 이미 넘어오는지 확인(설정 저장 알림이 `$query['saved']` 를 쓴다 — 있다). `.schema-gc { display:flex; align-items:center; gap:.6rem; margin-top:.75rem; }` 를 theme.css 에 추가.

- [ ] **Step 5: 통과 확인**

Run: `./vendor/bin/phpunit --filter AdminPageTest`
Expected: OK.

- [ ] **Step 6: 커밋**

```bash
git add src/Web/Controller/AdminCmsController.php src/Web/Routes.php templates/default/admin/settings.php public/themes/default/theme.css tests/Web/AdminPageTest.php
git commit -m "feat: 사이트 설정에서 버려진 업로드를 정리한다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: 전체 검증·문서·실기 확인

**Files:**
- Modify: `docs/template-development.md` (6절 전역 표에 attach 키 언급 한 줄)
- 검증: 전체 스위트·lint·스모크·스크래치 CDP 드래그

- [ ] **Step 1: 문서**

`docs/template-development.md` 6절 전역 표의 `site` 행 설명에 `attach_max_mb`, `attach_limit` 를 덧붙인다(값 나열에 두 키 추가).

- [ ] **Step 2: 전체 스위트 + 문법**

Run: `./vendor/bin/phpunit && for f in $(git ls-files '*.php'); do php -l $f > /dev/null || echo "SYNTAX $f"; done`
Expected: OK(기존 359 + 신규 ≈ 14), 문법 오류 없음.

- [ ] **Step 3: 스모크**

Run: `php /tmp/claude-1001/-home-kagla-gnucms-com/c8416273-8669-48d0-9787-bf01028dc218/scratchpad/smoke.php` (없으면 `curl -s -o /dev/null -w '%{http_code} ' https://gnucms.gnuboard.net/{,posts,login,register,terms/service}`)
Expected: 전부 200.

- [ ] **Step 4: 스크래치에서 CDP 로 실제 드래그**

Task 5 Step 8 의 스크래치 서버를 다시 쓰거나 새로 만들어: CDP(websocket, node 22 전역 WebSocket)로 ① 파일 두 개를 input 으로 첨부(업로드 완료 행 2개 확인) ② `Input.dispatchMouseEvent` 로 첫 행을 둘째 아래로 드래그 ③ hidden input 순서가 바뀌었는지 `Runtime.evaluate` 로 확인 ④ 저장 → 글 화면의 첨부 순서 확인. 스크린샷을 Read 로 본다. (CDP 드래그가 flaky 하면 ↑↓ 단추 클릭으로 순서 변경을 검증하고, 드래그는 dragstart/dragover/drop 이벤트 dispatch 로 대신한다 — 어느 쪽이든 hidden input 순서 변화가 증거다.)

- [ ] **Step 5: 라이브 확인**

라이브는 코드가 이미 반영돼 있다(작업 트리 = 서비스 중). `https://gnucms.gnuboard.net/admin/settings` 는 로그인 없이 401 만 확인하고, 공개 화면(`/`, `/posts`, 게시판 하나)을 200 으로 확인한다. 라이브 게시판의 `use_file` 은 켜지 않는다(사용자 결정).

- [ ] **Step 6: 커밋**

```bash
git add docs/template-development.md
git commit -m "docs: 첨부 설정 키를 템플릿 전역 표에 적는다

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```
