# 플러그인과 모듈

플러그인은 알림톡 같은 재사용 기능이며, 모듈은 예약 시스템처럼 독립적으로
동작하거나 플러그인을 조합하는 업무 프로그램이다. 독립 동작은 GNUCMS 안에서
다른 플러그인 없이 실행되는 것을 의미한다.

## 실행 가능한 참고 데모

기본 배포본에는 처음에 꺼져 있는 데모 패키지가 하나씩 포함된다.

| 종류 | 패키지와 개발 안내 | 활성화 후 관리자 실행 주소 |
| --- | --- | --- |
| 플러그인 | [메시지 형식](../plugins/demo-message/README.md) | `/plugins/demo-message/preview` |
| 모듈 | [예약 안내문](../modules/demo-reservation/README.md) | `/modules/demo-reservation/preview` |

모듈 목록의 **관리자 테스트**로 미사용 상태에서 확인하거나, 모듈을 켜고 **바로가기**로 실행할 수 있다.
플러그인을 켜면 메시지 형식 연동을 비교할 수 있다.
둘 다 관리자 전용 미리보기이며 실제 예약 저장·외부 발송은 하지 않는다.
하위 경로에 설치했다면 실행 주소 앞에 설치 경로를 붙인다.
기존 `/extensions/modules/…`, `/extensions/plugins/…` 주소를 사용하는 링크와 폼은
각각 `/modules/…`, `/plugins/…`로 변경해야 한다. 이전 주소는 404를 반환한다.

## 관리와 배포

- 관리자 메뉴를 `/admin/plugins`, `/admin/modules`로 분리한다. 전체 관리자만 접근한다.
- 패키지를 루트의 `plugins/{id}/`, `modules/{id}/`에 배포하면 목록에 나타난다.
- 최초 상태는 사용 안 함이다. 패키지별 사용 상태 토글을 변경하고 목록 위·아래의 **저장**을 누르면 해당 목록의 변경분이 한 번에 반영된다.
- 상태와 사용 여부는 **사용 상태** 한 열에 표시한다. 실행 오류도 같은 열에서 확인한다.
- 저장으로 실제 사용 상태가 바뀐 패키지가 최근 순서대로 상단에 표시된다. ON/OFF 모두 포함하며,
  동일한 값을 다시 저장해도 순서는 바뀌지 않는다. 한 번에 여러 항목을 바꾸면 마지막으로 토글한
  항목을 먼저 표시한다. JavaScript를 사용하지 않으면 같은 저장 안의 항목은 제출 순서로 정렬한다.
- 의존성을 최종 선택 상태로 검증한 후 한 번에 저장한다. 오류가 있으면 상태·정렬 순서 모두
  저장하지 않고 선택값을 화면에 유지한다. 다른 목록이나 화면에서 바꾼 미수정 항목은 덮어쓰지 않는다.
- OFF는 일반 요청의 패키지 실행과 라우트 등록을 중단한다. 명시적으로 지원하는 관리자 테스트는 별도 주소에서 가능하다. 파일·업무 데이터는 삭제하지 않는다.
  이미 진행 중인 요청이나 외부에 접수된 발송을 취소하지는 않는다.
- 상태는 `storage/extensions/enabled.json`에 저장한다. 동시 변경에는 공통 잠금을 사용하고
  임시 파일을 원자적으로 교체한다. 패키지 폴더에는 런타임 쓰기를 하지 않는다.
  상태 파일 형식은 `{"version":2,"enabled":[…],"recent":[…]}`이며 `recent`는 최근 변경 ID부터 나열한다.
  기존 ID 배열 형식은 그대로 읽고 다음 상태 변경 저장 때 이력을 포함한 형식으로 전환한다.
  이전 코드로 되돌릴 때는 상태 파일도 해당 버전의 백업으로 복원해야 한다.
- 의존성과 설명 파일을 검증한 후 사용 설정을 저장한다. 실제 진입점 실행이 실패하면
  사용 설정은 유지하되 화면에 **실행 불가**를 표시한다. 수정하거나 토글을 꺼서 복구한다.
- 필수 확장을 먼저 켜야 한다. 사용 중인 필수 확장을 끄려면 의존하는 확장을 먼저 끈다.
  배포로 의존성이 깨지거나 순환한 경우에는 토글을 꺼서 복구할 수 있다.
- 파일이 사라진 활성 패키지도 관리 목록에 표시하며 끌 수 있다.
- JSON 상태가 손상되면 확장은 실행하지 않으며 파일을 덮어쓰지 않는다. 코어 화면은
  계속 열리고 관리 화면에 오류를 표시한다. 보관한 상태 파일을 복원해 복구한다.

수동 백업 v2는 설치 레지스트리에 등록된 확장 테이블과 `storage/extensions/`의 영속 상태를
포함한다. 잠금·임시 파일, `storage/extensions-runtime/`의 토큰 캐시·실행 허용값은 제외한다.
SQLite 자동 복원은 외부 실행 허용값을 해제하고 토큰 캐시 세대를 갱신한다. 이전 v1 백업도
복원할 수 있지만 확장 활성 상태가 없어 다시 설정해야 한다. 패키지 PHP 파일은 백업에
포함하지 않으므로 같은 버전의 완성 배포본으로 재배치한다. 수동 서버 복제·DB 복구에서도
먼저 발송을 정지하고 계정·미완료 발송을 확인한 뒤 다시 허용한다.
SQLite 자동 복원은 백업과 현재 설정의 DB 테이블 접두사가 같아야 한다.

운영 서버에서는 Composer·npm 설치나 빌드를 하지 않는다. 패키지의 의존성·정적 자산은
개발 환경이나 CI에서 준비한 완성본으로 배포한다. 패키지는 서버 권한으로 실행되는
신뢰하는 PHP 코드이며, 등록 API는 보안 샌드박스가 아니다.

## 코어와의 경계

`src/Web/Routes.php`의 공통 연결점은 `Extension\AdminRoutes::register()` 한 곳이다.
패키지마다 코어 라우트·컨트롤러·App에 메서드를 추가하지 않는다. 이후 패키지 추가는
해당 디렉터리 배포와 관리자 토글만으로 처리한다. 확장 기반 구현은 `src/Extension/`에 있다.

관리 화면은 `admin/extensions/index`를 사용한다. 관리자 화면을 재정의하는 테마는
해당 템플릿과 `admin/_sidebar`의 플러그인·모듈 메뉴를 함께 반영해야 한다.
현재 패키지 전용 템플릿의 테마 재정의 API는 제공하지 않는다.

기본 패키지 루트는 GNUCMS 루트다. 특수 배치·테스트에서는 설정의 `extensions.root`로
`plugins/`, `modules/`를 포함하는 상위 디렉터리의 절대 경로를 지정할 수 있다.

## 패키지 설명 파일

예: `plugins/message/extension.json`

```json
{
  "id": "message",
  "type": "plugin",
  "name": "메시지 기능",
  "description": "여러 모듈에서 사용하는 메시지 기능입니다.",
  "version": "1.0.0",
  "api": 1,
  "requires": [],
  "optional": []
}
```

- ID는 폴더명과 같고, 영문 소문자로 시작하는 1~64자의 영문 소문자·숫자·`_`·`-`만 허용한다.
- `type`은 `plugins/`에서는 `plugin`, `modules/`에서는 `module`이다.
- `api`는 정수 `1` 또는 `2`여야 한다. 외부 콜백·패키지 DB 기반을 쓰는 새 패키지는 `2`를 선언한다. `version`은 표시용 패키지 버전이며 의존 버전 범위는 아직 지원하지 않는다.
- `requires`는 필수 확장의 전체 ID 배열이다. 예: `["plugins/message"]`.
- `optional`은 선택 확장의 전체 ID 배열이다. 활성화된 선택 확장을 먼저 등록하되,
  없거나 실행에 실패하면 해당 서비스 없이 현재 패키지를 실행한다.
- 필수 의존성은 순서대로 등록한다. 필수 의존성의 순환·누락·실행 실패는 해당 패키지 실행을 차단한다.
- 폴더, 설명 파일, 진입 파일에 심볼릭 링크를 사용하지 않는다.
- 목록 탐색과 설명 파일 검증은 비활성 패키지의 PHP를 실행하지 않는다.
- 선택 필드 `entry_path`에 `/preview`처럼 패키지의 고정 실행 경로를 지정하면 관리 목록에
  `/{종류}/{id}{entry_path}` 주소가 표시된다. 사용 중에는 주소 링크와 **바로가기**를 제공한다.
  외부 URL·쿼리·동적 경로는 허용하지 않는다.
- 선택 필드 `admin_test`는 기본값이 `false`다. `true`이고 `entry_path`가 있으면 미사용 상태에서
  **관리자 테스트** 버튼을 제공하며, 실행 주소 링크도 같은 테스트 화면으로 연결한다.
  `bootstrap.php`는 해당 경로의 GET과 필요한 POST를 등록해야 한다.

### 관리자 테스트 규약

테스트 주소는 `/admin/{plugins 또는 modules}/{id}/test`다. 서버는 전체 관리자 권한과
POST의 CSRF를 확인한 뒤 대상 패키지를 불러온다. 테스트 시 사용 상태나 최근 변경 순서를
저장하지 않고, 다른 요청에서 비활성 패키지를 실행하지도 않는다.

필수 의존성은 정상적으로 활성화되어 있어야 한다. 테스트 때문에 다른 패키지를 자동으로
켜지 않는다. 선택 의존성은 기존에 활성화된 서비스만 제공한다.

요청 속성 `Context::TEST_ATTRIBUTE`가 `true`이면 패키지 컨트롤러는 폼을 해당 테스트 주소로
제출하고 테스트 표시를 해야 한다. 예약 안내문 데모의 컨트롤러가 예제다.
테스트 응답에는 캐시 방지와 검색 제외 헤더를 붙인다.

이 기능은 별도 DB·외부 서비스 샌드박스를 만들지 않는다. `admin_test: true`를 선언하는
패키지는 테스트 요청의 저장·발송 동작을 직접 제어해야 한다. 예약 안내문 데모는 원래
저장·발송이 없는 미리보기이므로 동일 처리기를 사용한다.

## 진입점과 서비스

`bootstrap.php`는 `Context`를 받는 콜백을 반환한다. 활성 패키지마다 요청당 한 번 호출한다.
여기서는 서비스와 라우트만 등록한다. 발송·예약 생성·DB 마이그레이션 같은 부수 효과를
진입점에서 실행하면 모든 방문마다 반복될 수 있으므로 넣지 않는다.

예: `plugins/message/bootstrap.php`

```php
<?php

use GnuCms\Extension\Context;

return static function (Context $context): void {
    $context->provide('formatter', new class {
        public function format(string $text): string
        {
            return '[알림] ' . $text;
        }
    });
};
```

모듈에서 `optional: ["plugins/message"]`를 선언하고 선택적으로 서비스를 사용한다.
예: `modules/reservation/bootstrap.php` (설명 파일은 `type: "module"`, `id: "reservation"`):

```php
<?php

use GnuCms\Extension\Context;

return static function (Context $context): void {
    $formatter = $context->service('plugins/message', 'formatter');

    $context->route('GET', '/status', static function ($request, $response) use ($formatter) {
        $text = $formatter === null ? '예약 기능 준비' : $formatter->format('예약 기능 준비');
        $response->getBody()->write(json_encode(['message' => $text], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }, admin: true);
};
```

주소는 `/modules/reservation/status`다. 선택 플러그인이 꺼져 있어도 동작한다.
실제 예약·발송 기능은 이 예제에 포함하지 않는다. 패키지의 추가 PHP 파일과 완성된
의존성은 진입점에서 `require_once` 등으로 불러오며 코어 Composer 설정을 수정하지 않는다.

## 라우트와 보안

- `route('GET' 또는 'POST', '/경로', $handler, admin: false)`로 등록한다.
- 경로 앞에 `/{plugins 또는 modules}/{id}`가 자동으로 붙어 충돌을 방지한다.
- 초기 API는 고정 경로만 지원한다. 경로 세그먼트에는 영문·숫자·`_`·`-`를 사용한다.
  동적 ID는 쿼리나 POST 본문으로 받아 서버에서 검증한다.
- 코어의 세션·오류 처리 미들웨어를 공유하며 모든 POST에 기존 세션 CSRF 토큰 검사를 적용한다.
- `admin: true`는 전체 관리자 권한을 검사한다. 기본값은 공개 라우트다.
- `$context->app`으로 기존 서비스를 사용할 수 있다. 로그인·글 소유권·업무 권한 등
  추가 권한 검사는 각 요청 처리기에서 수행한다. 일반 부팅의 등록 시점에는 사용자 세션이 복원되기 전이다. 관리자 테스트의 등록은 권한 검사 후 수행한다.
- 등록 도중 예외가 발생하면 해당 패키지의 서비스·라우트를 공개하지 않고 필수 의존 모듈도 중단한다.
  예외 원문에는 비밀키가 포함될 수 있으므로 관리 화면에는 일반 오류만 표시한다.
- 요청 처리 중 예외는 공통 오류 처리로 전달된다. 템플릿 출력은 이스케이프하고 입력은 서버에서 검증한다.

## 현재 범위 밖

웹 ZIP 설치·자동 업데이트·의존 버전 범위 검사·패키지 설정/메뉴/템플릿 등록 API·
주기적 작업 실행기는 아직 제공하지 않는다.

주기적 실행에 호스팅 cron을 요구하지 않는다. 후속 방문 기반 실행 플러그인은 접속이
없으면 지연될 수 있어야 하며, 예약 만료 같은 업무상 기한 검증은 모듈 자체에서 수행한다.

## API 2: 외부 콜백과 패키지 데이터

`Context::externalPost($path, $authenticate, $handler, $maxBytes = 65536)`는 고정 JSON POST
콜백을 등록한다. 인증기는 `ServerRequestInterface`를 받아 반드시 `true`를 반환해야 한다.
실제로 등록된 경로만 세션·기존 본문 파서·HTML 오류 처리를 거치지 않는다. 본문 크기 제한과
인증 검사를 먼저 수행하고 JSON 객체를 파싱해 처리기에 전달한다. 다른 관리 POST의 CSRF는
유지된다. 비활성화하면 콜백도 사라진다. 이 API가 업체 인증을 자동 제공하지는 않는다.

`Extension\PackageSchema`는 명시적인 관리자 POST에서 `install($key, $version, $tables,
$migrate)`로 실행한다. `$migrate(Connection $db, int $previousVersion)`는 멱등 DDL을
수행한다. 공통 레지스트리에 테이블 소유권과 성공 판을 기록하며 갱신·백업 잠금을 공유한다.
실패한 설치의 실제 테이블도 백업하고, 성공 상태의 테이블이 누락되면 백업을 중단한다.
SQLite 설치 전 스냅숏은 `storage/backups/extensions/`에 보존하며 관리자 관리 화면 또는
일반 전체 백업 목록과는 별개다. 이 스냅숏은 자동 정리하지 않으므로 운영자가 보관 정책에
따라 관리한다. MySQL/PG는 갱신 전 전체 백업을 별도로 확보한다.

패키지 목록·bootstrap·공개 GET·웹훅에서는 설치를 실행하지 않는다. `Schema::VERSION`은
코어 구조 판이며 각 패키지의 스키마 판과 독립적이다. 운영 서버에서 Composer를 실행할
필요 없이 패키지 파일과 의존성을 완성본으로 배포한다.

실제 구현 예시는 [비즈뿌리오 플러그인](../plugins/bizppurio/README.md)과
[알림톡 운영 모듈](../modules/alimtalk/README.md)을 참고한다.
