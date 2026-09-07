# gnucms.com 템플릿 개발 안내

화면은 전부 **PHP 파일 템플릿**으로 그린다. 템플릿 엔진 라이브러리는 없다. 헬퍼 표와
규칙의 정본은 [`templates/default/README.txt`](../templates/default/README.txt) 이고, 이
문서는 테마를 만들고 고치는 흐름을 설명한다.

## 1. 핵심 구조

```
templates/{테마}/theme.php          테마의 표식. 이 파일이 있어야 테마다
templates/{테마}/layout.php         공개 화면 셸 (머리글·탭·바닥글·스크립트)
templates/{테마}/admin/layout.php   관리 콘솔 셸. 안에서 layout.php 를 다시 감싼다
templates/{테마}/_icons.php         아이콘 이름 -> SVG 요소 배열
templates/{테마}/{영역}/{화면}.php    home/ boards/ posts/ pages/ auth/ notifications/ admin/
www/themes/{테마}/theme.css      정적 파일
```

- 컨트롤러는 `View::fromRequest($request)->render($response, 'posts/index', $data)` 처럼
  **확장자 없는 논리 이름**만 넘긴다. `PhpView` 가 `.php` 를 붙여 선택한 테마 폴더에서 찾는다.
- `src/View/PhpTemplate.php` 가 헬퍼(`$this->e()`, `layout()`, `start()/stop()`, `insert()`,
  `url()`, `asset()`, `html()`, `icon()`, `def()` …)를 준다.
- 전역값은 `src/Web/Kernel.php` 와 `SessionGuard` 가 `addGlobal()` 로 넣는다 (아래 6절).
- 테마 선택값은 `site_settings` 의 `theme` 키다. 없는 테마 이름이면 `default` 로 떨어진다.

## 2. 새 테마 만들기

**테마 간 폴백이 없다.** 테마는 화면 전부를 가져야 하므로 `default/` 를 통째로 복사해서
시작한다.

```bash
cp -r templates/default templates/mytheme
cp -r www/themes/default www/themes/mytheme
```

`templates/mytheme/theme.php` 의 `label` 을 바꾸고, 관리 콘솔 > 사이트 설정에서 고른다.
그 다음 바꾸고 싶은 화면만 고친다. 정적 파일은 선택한 테마에 없으면 `www/themes/default/`
의 것이 쓰이므로 CSS 만 바꿔도 된다.

테마 이름은 영문 소문자·숫자·`_`·`-` 만 쓴다.

## 3. 화면 하나 고치기

```php
<?php $this->layout('layout') ?>
<?php $this->start('title') ?><?= $this->e($board['name']) ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>board<?php $this->stop() ?>
<?php $this->start('body') ?>
  <h1><?= $this->e($board['name']) ?></h1>
  <?php foreach ($list['items'] as $post): ?>
    <?php $this->insert('posts/_meta', ['post' => $post]) ?>
  <?php endforeach ?>
<?php $this->stop() ?>
```

- `layout.php` 가 내는 블록: `title`, `meta_description`, `body_class`, `nav_section`, `chrome`,
  `site_header`, `header_search`, `extra_tabs`, `subnav`, `body`, `site_footer`, `scripts`.
  관리 화면은 `admin/layout.php` 를 감싸고 `admin_section` 을 더 낸다(사이드바 활성 표시).
- 조각(`_meta`, `_thumb`, `_count`, `_comments`, `_editor` …)에 **루프 변수는 자동으로 안 넘어간다.**
  `insert('posts/_thumb', ['post' => $post, 'board' => $board])` 처럼 명시로 넘긴다.
- 출력은 전부 `$this->e()` 를 거친다. 예외는 `html()`·`icon()`·`json()`·`insert()`·`block()`·
  `url()`·`asset()` 의 결과뿐이다.

## 4. CSS·이미지·JavaScript

`www/themes/{테마}/` 에 두고 `$this->asset('theme.css')` 로 연결한다. 주소에는 내용 해시가
붙어(`?v=…`) 파일을 고치면 브라우저 캐시가 갱신된다. 선택한 테마에 파일이 없으면
`www/themes/default/` 의 주소가 나간다.

daisyUI 는 CDN 으로 읽는다(`layout.php` 의 `<link>`). 이 서버는 composer·npm·컴파일을 쓸 수
없으므로 빌드가 필요한 자산은 넣지 않는다.

## 5. 아이콘

`_icons.php` 가 `['home' => '<path …/>', …]` 를 돌려주고 `$this->icon('home', 18, 'cls')` 가
`<svg …>` 로 감싼다. 새 아이콘은 배열에 요소 문자열을 더한다. 모르는 이름은 원(`<circle>`)을 낸다.

## 6. 공통 값 (전역)

| 이름 | 내용 |
|---|---|
| `site` | `site_name`, `site_tagline`, `home_title`, `home_intro`, `theme`, `password_login_enabled`, `social_login_enabled`, `registration_enabled`, `social_registration_enabled`, `attach_max_mb`, `attach_limit` |
| `current_user` | `is_guest`, `id`, `display_name`, `is_admin` |
| `csrf_token` | 모든 POST 폼의 hidden 값 |
| `unread_notifications` | 머리글 알림 배지 수 |
| `site_menu` | 상단 메뉴에 올린 공개 내용 목록 (`slug`, `title`) |
| `legal_pages` | 하단에 늘어놓을 공개 약관 전부 |
| `consent_documents` | 회원가입 화면에 붙는 동의 항목 (`id`, `title`, `slug`, `required`) |
| `legal_documents`, `registration_available` | 가입 가능 여부 |
| `oauth_providers` | 설정된 소셜 로그인 (`key`, `label`) |
| `turnstile_enabled`, `turnstile_configured`, `turnstile_site_key` | Cloudflare Turnstile 표시 상태와 공개 사이트 키 |
| `active_theme`, `available_themes` | 사이트 설정의 테마 선택 |
| `base_path`, `GNUCMS`, `GNUCMS_ID` | 기준 경로와 이름 상수 |

## 7. 폼과 보안

- 모든 POST 폼에 `<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">`.
- 검증 오류는 컨트롤러가 `errors`(칸 이름 → 문구)와 `values` 를 넘겨 422 로 다시 그린다.
  `array_key_exists('email', $errors)` 로 갈라 `validator-hint` 를 낸다.
- 본문 HTML 은 반드시 `$this->html($content)` 로 낸다(정화 + 사진 축소본).
- 주소는 `$this->url('라우트', [경로 인자], [쿼리])` 로 만든다. 직접 조립하지 않는다.
- 글쓰기·게시판·문서 등록은 `/new` 주소를 사용한다. 글쓰기 링크와 폼 action은 `posts.create`로 만들며 실제 주소는 `/boards/{key}/new`다. 옛 `/boards/{key}/write`와 `/b/{key}/write`는 GET·POST 모두 지원하지 않고 `404`를 반환한다.
- 로그인이 필요한 HTML 요청은 `/login?url=원래주소`로 이동하고, 로그인 후 해당 주소로 돌아간다.
  복귀 주소는 현재 설치 내부의 경로만 허용하며 쿼리도 보존한다. POST 요청은 작업을 재실행하지 않도록 복귀 주소 없이 로그인 화면으로 보낸다. JSON 요청은 기존 `401` 응답을 유지한다.
- `auth/login.php`의 `return_url`은 검증된 복귀 주소다. 테마 재정의에서도 `url` hidden 필드와 소셜 로그인 링크의 `url` 쿼리에 이 값을 이스케이프해 전달한다. 비밀번호 오류 화면에서도 값을 보존하며, 소셜 로그인은 각 OAuth state에 복귀 주소를 묶어 완료 후 사용한다.

## 8. 관리 콘솔

`admin/layout.php` 가 사이드바(`admin/_sidebar.php`)와 상단 띠를 그리고, 각 화면은
`admin_section` 블록으로 사이드바의 활성 항목을 알린다 (`dashboard`, `members`, `boards`,
`content`, `legal`, `site`, `mail`). 화면 파일은 `admin/*.php` 다.

## 9. 게시판 목록 형태 (list_type)

게시판마다 `list`·`gallery`·`news`·`magazine` 중 하나를 고른다. `posts/index.php` 는
`?view=` 쿼리로 덮어쓸 수 있고, `posts/_list_{형태}.php` 조각을 `exists()` 로 확인한 뒤 낸다 —
조각이 빠지면 목록형으로 떨어진다. 홈의 게시판 피드도 같은 규칙으로 `home/_feed_{형태}.php` 를 쓴다.

목록 항목에는 `excerpt`, `thumbnail`, `comment_count`, `view_count` 가 실려 온다. 비밀글은
발췌와 썸네일이 비어 온다.

목록 위의 공지 줄에는 그 게시판의 공지와 전체 공지가 함께 온다. 항목의 `notice_scope`
가 `global` 이면 "전체 공지" 뱃지를 붙인다.

## 10. 본문 편집기와 사진

글·댓글·내용 편집은 CKEditor 4 (`www/vendor/ckeditor4/`)를 `posts/_editor.php`·
`admin/_editor.php` 가 띄운다. 업로드 주소와 버릴 주소를 `$this->json()` 으로 스크립트에 넣는다.
`image_key` 는 폼을 그릴 때 컨트롤러가 만드는 난수이고, 저장 전 올린 사진을 묶는 열쇠다.
본문 사진은 원본과 축소본(`-thumb`, `-view`)으로 저장되고 `$this->html()` 이 축소본 + 원본
링크로 바꿔 낸다.

## 11. 검증

```bash
./vendor/bin/phpunit                        # 전체
./vendor/bin/phpunit --filter PhpViewTest   # 템플릿 엔진
php -l templates/default/posts/index.php    # 문법
```

테마별 마크업을 단언하는 테스트는 `makeApp($db, [], '테마이름')` 으로 테마를 못박는다.
`GNUCMS_TEST_THEME=이름 ./vendor/bin/phpunit` 로 전체 스위트를 다른 테마로 돌릴 수 있다.

## 12. 여러 사람·AI 의 병렬 작업

- 테마 폴더가 경계다. 다른 테마를 만들면 서로 파일이 겹치지 않는다.
- 컨트롤러가 넘기는 데이터 이름을 바꾸면 모든 테마의 그 화면이 같이 바뀌어야 한다.
  데이터를 **더하는** 것은 안전하고, **빼거나 이름을 바꾸는** 것은 테마 전부를 훑는다.
- 스키마를 바꾸면 `Schema::VERSION` 을 올린다. 도장에 파일 해시가 들어가 잊어도 다시 돌지만,
  판 번호는 사람이 읽는 표식이다.
  옮기는 일은 `SchemaUpgrader` 가 첫 요청에서 한다(백업 → 마이그레이션 → 실패 시 503 점검 화면).
