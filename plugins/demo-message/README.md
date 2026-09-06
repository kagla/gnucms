# 메시지 형식 플러그인 데모

여러 모듈이 사용하는 작은 기능을 별도 패키지로 제공하는 예제다.
입력한 제목과 내용을 `[데모 알림 · 제목]` 형식의 **일반 텍스트**로 만든다.
실제 알림톡·문자·이메일 발송과 데이터 저장은 하지 않는다.

## 실행

1. GNUCMS 전체 관리자로 로그인한다.
2. **플러그인** 메뉴에서 **데모 · 메시지 형식** 토글을 켜고 목록 위·아래의 **저장**을 누른다.
3. `/extensions/plugins/demo-message/preview`에 접속한다.
4. 제목과 내용을 입력하고 **미리보기 생성**을 누른다.

사이트가 `/cms`에 설치되어 있으면 `/cms/extensions/plugins/demo-message/preview`를 사용한다.
메뉴에 데모가 자동으로 표시되지만 실행 화면 바로가기 등록 API는 아직 없어 위 주소로 접속한다.
처음에는 비활성 상태이며, 끄면 미리보기 주소도 404가 된다.

## 파일별 역할

| 파일 | 역할 |
| --- | --- |
| `extension.json` | ID·종류·버전·의존성 선언 |
| `bootstrap.php` | 클래스 로딩, 서비스 공개, GET/POST 라우트 등록 |
| `src/MessageFormatter.php` | 길이와 제목 형식을 검증하고 일반 텍스트 생성 |
| `src/PreviewController.php` | 요청 입력 검증, 오류 응답, 패키지 전용 화면 렌더링 |
| `templates/preview.php` | CSRF 토큰을 포함한 폼과 이스케이프된 결과 출력 |
| `templates/_style.php` | 외부 라이브러리·빌드 없이 사용하는 화면 스타일 |

화면은 패키지 내부 템플릿과 GNUCMS의 `PhpView`를 사용한다.
`templates/default/`나 사용자 테마를 수정하지 않으며 관리자 화면의 테마를 상속하지 않는다.
자산·HTML 렌더러 콜백은 이 텍스트 전용 예제의 제한된 용도에 맞췄다.

## 다른 모듈에서 사용

모듈의 `extension.json`에 선택 의존성을 선언한다.

```json
"optional": ["plugins/demo-message"]
```

모듈의 진입점에서 공개 서비스를 가져온다.

```php
$format = $context->service('plugins/demo-message', 'format');
$message = $format instanceof Closure
    ? $format('예약 안내', '예약 정보 예시입니다.')
    : '예약 정보 예시입니다.';
```

공개 서비스 `format`의 계약은 `Closure(string $title, string $body): string`이다.
제목은 한 줄·최대 60자, 내용은 최대 1,000자이며 둘 다 필수다.
잘못된 입력에는 `DomainError`(422)를 던진다. 결과에는 HTML 이스케이프를 하지 않는다.
**화면 출력 시 템플릿의 `$this->e()`로 이스케이프한다.**

모듈은 이 패키지의 클래스명이나 내부 파일 경로를 알 필요가 없다.
연동 동작은 `modules/demo-reservation/`에서 확인할 수 있다.

## 새 플러그인으로 복사할 때

- 폴더명과 설명 파일의 ID, PHP 네임스페이스를 함께 바꾼다.
- 컨트롤러의 `form_url`에 사용하는 패키지 경로도 바꾼다.
- 서비스 이름과 입력·출력·예외 계약을 먼저 정의한다.
- 진입점에서는 등록만 하고, 외부 호출이나 데이터 변경은 요청 처리 단계에서 수행한다.
- 예제의 모든 라우트는 `admin: true`다. 공개 기능을 만들 때 필요한 사용자 권한을 별도로 검사한다.
- POST는 공통 확장 기반이 CSRF 토큰을 검사한다. 외부 업체 웹훅은 이 예제를 그대로 복사하지 말고 별도의 인증 설계를 한다.
- 추가 의존성은 개발 환경에서 준비해 패키지에 포함한다. 운영 서버에서 Composer를 실행하지 않는다.

회귀 테스트: `./vendor/bin/phpunit tests/Web/DemoExtensionsTest.php`
