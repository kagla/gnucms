# 회원 휴대폰번호 구현 계획 (2/3)

> 현재 PHP 지원 기준 (2026-10-21): **PHP 8.2 이상 지원, PHP 8.4 권장**. 이 문서는 과거 설계·작업 계획을 보관한 자료입니다. 초기의 하위 버전 문법 제약과 실행 환경 기록은 현재 설치 조건이 아닙니다. [현재 PHP 지원 기준](../../php-support.md)을 따릅니다.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 회원에게 알림톡·문자를 보낼 수 있도록 `users.phone`을 가입·회원정보 수정·관리자 회원 관리에서 받고 고칠 수 있게 한다.

**Architecture:** 컬럼과 마이그레이션은 계획 1의 Task 6에서 이미 만들었다. 여기서는 그 칸을 화면에 연결하고, 가입 때 번호를 받을지를 "회원·글쓰기" 설정에서 고르게 한다. 번호 형식 처리는 계획 1의 `GnuCms\Aligo\PhoneNumber` 한 곳만 쓴다.

**Tech Stack:** PHP 8.2 이상 지원, PHP 8.4 권장, Slim 4, PHPUnit 10.5.

**Spec:** [docs/superpowers/specs/2026-09-17-aligo-messaging-design.md](../specs/2026-09-17-aligo-messaging-design.md) §7

**선행:** 계획 1 [2026-09-17-aligo-1-engine.md](2026-09-17-aligo-1-engine.md) Task 2(PhoneNumber)와 Task 6(스키마)이 끝나 있어야 한다.

## Global Constraints

- 새 Composer 의존성을 추가하지 않는다.
- 모든 PHP 파일은 `declare(strict_types=1);`, 클래스는 `final`.
- 번호는 하이픈을 뗀 숫자로만 저장하고, 화면에 보일 때만 `PhoneNumber::format()`으로 넣는다.
- **본인확인(인증번호)은 하지 않는다.** 형식만 검증한다.
- 기본값은 `안 받음`이다. 기존 사이트의 가입 흐름을 말없이 바꾸지 않는다.
- 화면 문구는 한국어, 커밋 제목은 영어 conventional commit.
- 관련 테스트를 먼저 돌리고, 마지막에 `./vendor/bin/phpunit` 전체도 돌린다.

---

### Task 1: 가입 시 휴대폰번호 정책 설정

**Files:**
- Modify: `src/Cms/CmsService.php` (기본값 목록과 저장·읽기)
- Modify: `templates/default/admin/writing_settings.php`
- Test: `tests/Cms/PhonePolicySettingTest.php`

**Interfaces:**
- Consumes: 없음
- Produces:
  - 설정 키 `signup_phone`, 값은 `'off'`(기본) / `'optional'` / `'required'`
  - `CmsService::settings()['signup_phone']`로 읽는다

- [ ] **Step 1: Write the failing test**

`tests/Cms/PhonePolicySettingTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Cms;

use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PhonePolicySettingTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testDefaultsToNotAskingForAPhone(array $config): void
    {
        $service = $this->cmsService($config);
        self::assertSame('off', $service->settings()['signup_phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testStoresTheChosenPolicy(array $config): void
    {
        $service = $this->cmsService($config);
        $service->saveWritingSettings($this->adminAcl(), $this->writingInput(['signup_phone' => 'required']));
        self::assertSame('required', $service->settings()['signup_phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownValueFallsBackToOff(array $config): void
    {
        $service = $this->cmsService($config);
        $service->saveWritingSettings($this->adminAcl(), $this->writingInput(['signup_phone' => 'nonsense']));
        self::assertSame('off', $service->settings()['signup_phone']);
    }
}
```

`cmsService()`·`adminAcl()`·`writingInput()`는 이 파일 안의 private 헬퍼로 둔다. `tests/Cms/` 안에 이미 `CmsService`를 만드는 테스트가 있으므로 그 파일을 열어 같은 방식으로 만들고, `writingInput()`는 `saveWritingSettings()`가 요구하는 나머지 필수 입력을 기본값으로 채운 뒤 인자를 덮어쓴다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Cms/PhonePolicySettingTest.php`
Expected: FAIL — `Undefined array key "signup_phone"`

- [ ] **Step 3: Write minimal implementation**

`src/Cms/CmsService.php`의 기본값 배열(`'registration_enabled' => '1',` 이 있는 곳)에 더한다:

```php
        'signup_phone' => 'off',
```

`saveWritingSettings()`의 저장 배열에 더한다 (`registration_enabled` 줄 옆):

```php
            // 가입 화면에서 휴대폰번호를 받을지. 기존 사이트의 가입 흐름을 바꾸지 않도록 기본은 off 다.
            'signup_phone' => $v->inList('signup_phone', ['off', 'optional', 'required'], 'off'),
```

`templates/default/admin/writing_settings.php`의 가입 관련 묶음에 라디오 세 개를 더한다:

```php
<fieldset class="form-control">
  <legend>가입 시 휴대폰번호</legend>
  <?php foreach (['off' => '받지 않음', 'optional' => '선택 입력', 'required' => '필수 입력'] as $value => $label): ?>
    <label class="label"><input class="radio radio-sm" type="radio" name="signup_phone" value="<?= $value ?>"<?= ($settings['signup_phone'] ?? 'off') === $value ? ' checked' : '' ?>> <?= $this->e($label) ?></label>
  <?php endforeach ?>
  <p><small>알림톡·문자를 회원에게 보내려면 번호가 필요합니다. 본인확인은 하지 않고 형식만 확인합니다.</small></p>
</fieldset>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Cms/PhonePolicySettingTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Cms/CmsService.php templates/default/admin/writing_settings.php \
  tests/Cms/PhonePolicySettingTest.php
git commit -m "feat: choose whether signup asks for a phone number"
```

---

### Task 2: 가입에서 휴대폰번호 받기

**Files:**
- Modify: `src/Account/AccountService.php` (`register()`)
- Modify: `src/Repository/UserRepository.php` (생성 시 `phone` 저장)
- Modify: 가입 화면 템플릿 (`templates/default/` 안의 회원가입 폼)
- Test: `tests/Account/SignupPhoneTest.php`

**Interfaces:**
- Consumes: `PhoneNumber::normalize()` (계획 1 Task 2), `signup_phone` 설정 (Task 1)
- Produces: `users.phone`가 채워진 회원

- [ ] **Step 1: Write the failing test**

`tests/Account/SignupPhoneTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Account;

use GnuCms\Error\DomainError;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SignupPhoneTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testPhoneIsIgnoredWhenTheSiteDoesNotAskForIt(array $config): void
    {
        [$service, $db] = $this->boot($config, 'off');
        $service->register($this->signup(['phone' => '010-1234-5678']));

        self::assertNull($db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' ORDER BY id DESC')['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testOptionalPhoneIsStoredWithoutHyphens(array $config): void
    {
        [$service, $db] = $this->boot($config, 'optional');
        $service->register($this->signup(['phone' => '010-1234-5678']));

        self::assertSame('01012345678', $db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' ORDER BY id DESC')['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testOptionalPhoneMayBeLeftBlank(array $config): void
    {
        [$service, $db] = $this->boot($config, 'optional');
        $service->register($this->signup(['phone' => '']));

        self::assertNull($db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' ORDER BY id DESC')['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testRequiredPhoneRefusesABlankOrBadNumber(array $config): void
    {
        [$service] = $this->boot($config, 'required');

        foreach (['', '02-1234-5678', '010-12'] as $value) {
            try {
                $service->register($this->signup(['phone' => $value, 'email' => uniqid() . '@example.com']));
                self::fail($value . ' 는 거절해야 한다');
            } catch (DomainError $e) {
                self::assertArrayHasKey('phone', $e->details());
            }
        }
    }
}
```

`boot(array $config, string $policy): array`는 이 파일의 private 헬퍼로, `freshDatabase()`로 DB를 만들고 `signup_phone` 설정을 `$policy`로 저장한 뒤 `AccountService`와 `Connection`을 돌려준다. `signup(array $override): array`는 `register()`가 요구하는 이메일·비밀번호·표시이름·동의 입력을 채운 배열을 만든다. `tests/Account/` 안의 기존 가입 테스트를 열어 같은 방식으로 만든다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Account/SignupPhoneTest.php`
Expected: FAIL — 번호가 저장되지 않아 `null !== '01012345678'`

- [ ] **Step 3: Write minimal implementation**

`AccountService::register()`의 검증부에 더한다 (`$v->check()` 앞):

```php
        // 번호를 받을지는 사이트 설정이 정한다. 본인확인은 하지 않고 형식만 본다.
        $phone = $this->phoneFromInput($input);
```

그리고 같은 클래스에 더한다:

```php
    /** 가입·프로필 공통. 설정이 off 면 입력을 무시하고, required 면 빈 값을 거절한다. */
    private function phoneFromInput(array $input): ?string
    {
        $policy = $this->signupPhonePolicy();
        if ($policy === 'off') {
            return null;
        }
        $given = trim((string) ($input['phone'] ?? ''));
        if ($given === '') {
            if ($policy === 'required') {
                throw DomainError::validation(['phone' => '휴대폰번호를 입력해 주세요.']);
            }

            return null;
        }

        return PhoneNumber::normalize($given);
    }
```

`AccountService`는 생성자에서 이미 `CmsService $cms`를 받아 `$this->cms`에 들고 있다. 새 의존을 더하지 말고 그것을 쓴다:

```php
    private function signupPhonePolicy(): string
    {
        $policy = (string) ($this->cms->settings()['signup_phone'] ?? 'off');

        return in_array($policy, ['off', 'optional', 'required'], true) ? $policy : 'off';
    }
```

`register()`가 회원을 만드는 호출에 `phone`을 넘기고, `UserRepository`의 생성 메서드가 `phone` 칸을 `INSERT`에 포함하도록 고친다.

가입 화면 템플릿에 `$settings['signup_phone']`가 `off`가 아닐 때만 보이는 입력을 더한다:

```php
<?php if (($settings['signup_phone'] ?? 'off') !== 'off'): ?>
<label class="form-control">
  <span class="label-text">휴대폰번호<?= $settings['signup_phone'] === 'required' ? '' : ' (선택)' ?></span>
  <input class="input input-bordered" type="tel" name="phone" inputmode="numeric" autocomplete="tel"
         value="<?= $this->e($values['phone'] ?? '') ?>" placeholder="010-1234-5678">
  <?php if (isset($errors['phone'])): ?><span class="label-text-alt error"><?= $this->e($errors['phone']) ?></span><?php endif ?>
</label>
<?php endif ?>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Account/SignupPhoneTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Account/AccountService.php src/Repository/UserRepository.php templates/default \
  tests/Account/SignupPhoneTest.php
git commit -m "feat: accept a phone number at signup when the site asks for one"
```

---

### Task 3: 회원정보 수정과 관리자 회원 수정에서 번호 고치기

**Files:**
- Modify: `src/Account/AccountService.php` (`updateProfile()`)
- Modify: `src/Web/Controller/AdminController.php` (`memberUpdate()`)
- Modify: 회원정보 수정 템플릿, `templates/default/admin/member_form.php`
- Test: `tests/Account/ProfilePhoneTest.php`

**Interfaces:**
- Consumes: `PhoneNumber::normalize()` (계획 1 Task 2)
- Produces: 회원 본인과 관리자가 번호를 넣고 지울 수 있다

**Note:** 회원정보 수정과 관리자 수정은 **정책과 무관하게 항상** 번호를 고칠 수 있다. `signup_phone`은 가입 화면에만 적용한다 — 설정을 끈 사이트도 이미 받아 둔 번호를 고칠 수 있어야 한다.

- [ ] **Step 1: Write the failing test**

`tests/Account/ProfilePhoneTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Account;

use GnuCms\Error\DomainError;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProfilePhoneTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testMemberCanSetAndClearTheirNumber(array $config): void
    {
        [$service, $db, $userId] = $this->bootWithMember($config);

        $service->updateProfile($userId, $this->profile(['phone' => '010-1234-5678']));
        self::assertSame('01012345678', $db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' WHERE id = ?', [$userId])['phone']);

        $service->updateProfile($userId, $this->profile(['phone' => '']));
        self::assertNull($db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' WHERE id = ?', [$userId])['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testEditingIsAllowedEvenWhenSignupDoesNotAskForAPhone(array $config): void
    {
        [$service, $db, $userId] = $this->bootWithMember($config, 'off');

        $service->updateProfile($userId, $this->profile(['phone' => '01011112222']));
        self::assertSame('01011112222', $db->selectOne('SELECT phone FROM ' . $db->table('users')
            . ' WHERE id = ?', [$userId])['phone']);
    }

    #[DataProvider('connectionProvider')]
    public function testABadNumberIsRefused(array $config): void
    {
        [$service, , $userId] = $this->bootWithMember($config);

        $this->expectException(DomainError::class);
        $service->updateProfile($userId, $this->profile(['phone' => '02-1234-5678']));
    }
}
```

`bootWithMember()`와 `profile()`은 이 파일의 private 헬퍼다. `tests/Account/` 안의 기존 프로필 수정 테스트를 열어 같은 방식으로 만든다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Account/ProfilePhoneTest.php`
Expected: FAIL — 번호가 저장되지 않는다

- [ ] **Step 3: Write minimal implementation**

`AccountService::updateProfile()`의 검증부에 더한다:

```php
        // 프로필에서는 정책과 무관하게 언제나 고칠 수 있다. 빈 값은 지우는 뜻이다.
        $given = trim((string) ($input['phone'] ?? ''));
        $phone = $given === '' ? null : PhoneNumber::normalize($given);
```

그리고 저장 배열에 `'phone' => $phone`을 더한다.

`AdminController::memberUpdate()`에도 같은 두 줄을 더하고 저장 배열에 넣는다. 관리자 화면은 `Acl::assertGlobalAdmin()`을 이미 지나므로 추가 권한 검사는 필요 없다.

두 템플릿(회원정보 수정, `templates/default/admin/member_form.php`)에 입력을 더한다. 저장된 값은 `PhoneNumber::format()`으로 보여준다:

```php
<label class="form-control">
  <span class="label-text">휴대폰번호</span>
  <input class="input input-bordered" type="tel" name="phone" inputmode="numeric" autocomplete="tel"
         value="<?= $this->e($values['phone'] === null || $values['phone'] === '' ? '' : \GnuCms\Aligo\PhoneNumber::format((string) $values['phone'])) ?>"
         placeholder="010-1234-5678">
  <span class="label-text-alt">비우면 지웁니다. 알림톡·문자를 받을 번호입니다.</span>
  <?php if (isset($errors['phone'])): ?><span class="label-text-alt error"><?= $this->e($errors['phone']) ?></span><?php endif ?>
</label>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Account/ProfilePhoneTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Account/AccountService.php src/Web/Controller/AdminController.php templates/default \
  tests/Account/ProfilePhoneTest.php
git commit -m "feat: let members and admins edit the stored phone number"
```

---

### Task 4: 관리자 회원 검색에 번호 붙이기

**Files:**
- Modify: `src/Repository/UserRepository.php` (검색 조건과 반환 칸)
- Modify: `templates/default/admin/members.php`
- Test: `tests/Repository/MemberPhoneSearchTest.php`

**Interfaces:**
- Consumes: `users.phone` (계획 1 Task 6)
- Produces: 관리자 회원 목록이 번호로 검색되고 목록에 가린 번호가 보인다. 계획 1 Task 16의 발송 화면 회원 선택이 이 검색을 쓴다

- [ ] **Step 1: Write the failing test**

`tests/Repository/MemberPhoneSearchTest.php`:

```php
<?php

declare(strict_types=1);

namespace GnuCms\Tests\Repository;

use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MemberPhoneSearchTest extends DatabaseTestCase
{
    #[DataProvider('connectionProvider')]
    public function testFindsAMemberByDigitsWithOrWithoutHyphens(array $config): void
    {
        [$repository] = $this->bootWith($config, [
            ['name' => '홍길동', 'phone' => '01012345678'],
            ['name' => '김철수', 'phone' => '01098765432'],
            ['name' => '번호없음', 'phone' => null],
        ]);

        self::assertSame(['홍길동'], $this->names($repository->search('010-1234-5678')));
        self::assertSame(['홍길동'], $this->names($repository->search('1234')));
        self::assertCount(3, $repository->search(''));
    }

    #[DataProvider('connectionProvider')]
    public function testSearchStillMatchesNameAndEmail(array $config): void
    {
        [$repository] = $this->bootWith($config, [['name' => '홍길동', 'phone' => '01012345678']]);

        self::assertSame(['홍길동'], $this->names($repository->search('홍길동')));
    }
}
```

`bootWith()`와 `names()`는 이 파일의 private 헬퍼다. `search()`의 실제 메서드 이름은 `src/Repository/UserRepository.php`를 열어 관리자 회원 목록이 쓰는 것으로 맞춘다 — 이름이 다르면 테스트와 구현 양쪽에서 그 이름을 쓴다.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Repository/MemberPhoneSearchTest.php`
Expected: FAIL — 번호로는 찾지 못한다

- [ ] **Step 3: Write minimal implementation**

`UserRepository`의 검색 `WHERE`에 번호 조건을 더한다. 저장된 값에 하이픈이 없으므로 **검색어에서 숫자만 뽑아** 비교한다:

```php
        // 번호는 숫자만 저장하므로 검색어에서도 숫자만 뽑아 비교한다.
        $digits = preg_replace('/\D+/', '', $keyword);
        if ($digits !== '' && strlen($digits) >= 2) {
            $conditions[] = 'phone LIKE ?';
            $params[] = '%' . $digits . '%';
        }
```

`SELECT` 목록에 `phone`을 더한다.

`templates/default/admin/members.php`의 표에 열을 더한다. 목록이므로 가린 번호를 보여준다:

```php
<td><?= $member['phone'] === null || $member['phone'] === '' ? '<span class="muted">—</span>' : $this->e(\GnuCms\Aligo\PhoneNumber::mask((string) $member['phone'])) ?></td>
```

`<th>휴대폰번호</th>`도 같은 자리에 더한다.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Repository/MemberPhoneSearchTest.php`
Expected: PASS (2 tests)

그다음 전체: `./vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/UserRepository.php templates/default/admin/members.php \
  tests/Repository/MemberPhoneSearchTest.php
git commit -m "feat: search members by phone number and show it masked in the list"
```

---

## 완료 확인

- `./vendor/bin/phpunit` 전체 통과
- 설정 → 회원·글쓰기에서 가입 시 휴대폰번호를 `받지 않음`·`선택`·`필수`로 고를 수 있다
- 가입·회원정보 수정·관리자 회원 수정에서 번호를 넣고 지울 수 있다
- 관리자 회원 목록이 번호로 검색되고 가린 번호를 보여준다
- 계획 1의 발송 화면에서 회원을 골라 보낼 수 있다
