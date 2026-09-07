# 비즈뿌리오 알림톡 플러그인

GNUCMS 안에서 재사용하는 알림톡 발송 기능이다. API 2 확장 기반을 요구하며 기본값은
미사용이다. 국내 휴대폰 대상 기본 텍스트형 알림톡과 웹링크(WL) 버튼 최대 5개를 지원한다.
승인된 템플릿을 로컬에 등록해 사용하며 KAPI 원격 승인·동기화, 이미지/강조/아이템리스트,
문자 대체, 대량·예약·업무 자동 발송은 포함하지 않는다.

## 사용 준비

1. 비즈뿌리오에서 API 계정·알림톡 사용 승인과 서버의 외부 접속 IP 등록을 완료한다.
2. 카카오톡 채널·발신프로필과 사용할 템플릿의 검수를 완료한다.
3. GNUCMS **플러그인 → 비즈뿌리오 알림톡**을 켜고 저장한다.
4. 바로가기 `/plugins/bizppurio/settings`에서 **데이터 설치·갱신**을 실행한다.
5. 검수/운영 환경과 계정 유형(모듈 연동/웹발송)을 선택해 계정 ID·API 연동용 모듈 비밀번호·발신프로필·발신번호·테스트 번호를 저장한다.
6. **인증 연결 확인**을 실행하고 **결과 수신 경로 표시**의 경로를 HTTPS 도메인 뒤에
   붙여 비즈뿌리오에 등록한다. 하위 경로 설치는 생성된 경로에 이미 반영된다.
7. 운영 화면이 필요한 경우 `feat/bizppurio-messaging` 브랜치의 **비즈뿌리오 알림톡 운영** 모듈을 사용한다. `main`에는 이 모듈을 포함하지 않는다. 연동 모듈에서 승인 템플릿을 등록하고 발송 전 이 환경의 발송을 허용한다.
8. 지정 테스트 번호로 실제 수신과 웹훅 결과를 확인한 뒤 필요한 경우 발송 대상 범위를 넓힌다.

검수 환경도 실제 수신·과금 여부는 계정 계약을 따른다. 코드의 모의 API 테스트는 실계정
인증·과금·카카오 수신 검증을 대신하지 않는다. 해당 계정의 웹훅 요청·ACK 조건도 확인한다.

## 웹발송 계정과 GNUCMS 웹발송

별도 브랜치의 GNUCMS 운영 모듈에서 제공하는 **웹발송**은 관리자가 브라우저에서 번호·템플릿·변수를 입력하고
서버가 REST API로 전송하는 기능이다. 설정의 **계정 유형**에는 비즈뿌리오에 등록된
모듈 연동 계정 또는 웹발송 계정을 선택한다. 유형 선택 자체가 업체의 계정 권한을 바꾸지는 않는다.
환경마다 계정 한 개를 설정하며, 여러 계정을 동시에 운영하는 기능은 포함하지 않는다.

웹발송 계정은 **인증 연결 확인 → 이 환경의 발송 허용** 순서로 진행한다. 인증 확인은
기존 캐시를 재사용하지 않고 현재 설정으로 토큰을 다시 발급받는다. 확인을 시작하면 발송을
정지하며 실패 시 정지 상태를 유지한다. 인증 확인값은 설정 변경 또는 복원 시 해제된다.
기존 계정 유형 필드가 없는 설정은 모듈 연동 계정으로 읽는다.

인증 성공은 알림톡 사용 승인·템플릿 승인·실제 수신 성공까지 보장하지 않는다.
공개 개발자 문서에서 별도의 웹발송 전용 연동 API는 확인되지 않았으며, 웹발송 계정의
REST API 사용 가능 여부는 업체 확인과 실계정 테스트가 필요하다. 모의 테스트는 웹발송
유형 설정, 인증 성공/거부, 발송 및 결과 수신 경로를 검증하며 업체의 계정 정책을 검증하지 않는다.

설정과 웹발송 화면의 **비즈뿌리오 사이트에서 웹발송** 링크는 공식 사이트를 새 창으로 연다.
웹전용 계정은 해당 사이트에 직접 로그인해 메시지전송 메뉴를 이용할 수 있다.
GNUCMS는 이 링크에 계정·비밀번호·수신정보를 전달하거나 자동 로그인하지 않는다.
사이트 직접 발송과 그 이력의 GNUCMS 자동 동기화는 구현하지 않았다.

### 인증 오류와 IP 정책

GNUCMS의 인증 연결 확인은 선택한 계정 유형과 관계없이 `/v1/token`을 호출한다.
이때 저장된 계정 ID와 **API 연동용 모듈 비밀번호**로 Basic 인증한다. 홈페이지 로그인
비밀번호와는 별도이며 발신프로필 키는 토큰 인증에 사용하지 않는다. 발송에 사용할 계정으로
비즈뿌리오에 로그인한 뒤 **비즈라운지 → 모듈연동 환경설정 → 모듈 비밀번호 변경하기**에서
설정하고 GNUCMS에 저장한다. 입력만 바꾸고 인증 연결 확인을 누르면 이전에 저장된 값이
사용되므로 먼저 저장한다. 별도로 받은 `API KEY`가 이 비밀번호인지 이름만으로 단정하지 않는다.
모듈연동 메뉴가 없는 웹발송 계정은 REST API 사용 권한과 인증 정보 발급 방법을 업체에 확인한다.
근거: [3007 오류와 모듈 비밀번호 안내](https://bizmessage.zendesk.com/hc/ko/articles/8091755754127),
[모듈 비밀번호 변경 방법](https://bizmessage.zendesk.com/hc/ko/articles/7290014712335).

GNUCMS가 이 요청에 별도의 발신 IP 검사를 수행하는 것은 아니며, API 접속 허용 정책은
비즈뿌리오 서버가 적용한다. 설정의 결과 송신 IP 제한은 웹훅 수신용으로, API 발신 IP 등록과 다르다.

인증 실패 화면에는 선택 환경, HTTP 상태와 업체의 숫자 응답 코드를 표시한다.
`3000`은 API 접속 허용 IP 미등록, `3010`은 허용 IP 불일치, `3007`은 모듈 비밀번호 오류,
`3006`은 계정 부재다. 업체 description·자격 증명·토큰 원문은 표시하지 않는다.
코드가 없는 통신·응답 오류를 IP 등록 오류로 단정하지 않는다.

웹발송 계정 신청 화면에서 IP 등록이 비활성화된 경우 계정의 REST API 사용 가능 여부와
등록 절차를 업체에 확인한다. 이 화면 상태만으로 API의 IP 검사 예외를 가정하지 않는다.
근거: [비즈뿌리오 API IP 허용 정책](https://bizppurio.github.io/guides/operations/).

## 서버 조건과 저장

기존 GNUCMS PHP/PDO/OpenSSL 환경에 HTTPS stream과 `allow_url_fopen`이 필요하다.
별도의 Composer·npm·Java 설치나 cron은 필요하지 않다. 외부 요청은 검수/운영 업체
호스트로만 나가며 TLS 검증을 사용하고 리다이렉트를 따라가지 않는다.

설정과 메시지 내용은 `auth.secret` 기반으로 암호화한다. 키를 바꾸면 기존 암호화 데이터를
읽을 수 없으므로 DB와 설정 파일을 함께 보관한다. 비밀번호와 토큰을 Git·대화·운영 로그에
기록하지 않는다. 인증 결과 화면은 토큰을 표시하지 않는다.

API 연동용 모듈 비밀번호 칸의 눈 아이콘으로 입력한 값 또는 저장된 비밀번호를 확인할 수 있다.
저장된 값은 기본 HTML에 포함하지 않으며, 전체 관리자의 CSRF 검사를 통과한 POST에서
현재 계정·설정 판을 확인한 뒤 캐시하지 않는 JSON으로 전달한다. 다시 숨기거나 화면을
떠나거나 저장할 때 조회한 값은 입력칸에서 지운다. 직접 수정한 새 비밀번호는 유지한다.
발신프로필 키 입력칸도 기본으로 마스킹하고 눈 아이콘으로 표시·숨김을 전환한다.
발신프로필 키를 가리는 것은 화면 표시만 바꾸며 저장할 실제 값은 유지한다.

- DB: `bp_settings`, `bp_templates`, `bp_dispatches`, `bp_attempts`, `bp_receipts`.
- 패키지 구조 판과 테이블 소유권: 코어 `extension_schemas` 레지스트리.
- 임시 토큰·잠금: `storage/extensions-runtime/bizppurio/`.
- 실행 허용: `storage/extensions-runtime/permits/` (백업 제외).
- 영속 활성 상태: `storage/extensions/enabled.json` (전체 백업 v2에 포함).

SQLite, MySQL/MariaDB와 DB prefix를 지원한다. 설치·갱신은 관리자 POST에서
실행하고 bootstrap·GET·웹훅에서는 실행하지 않는다. SQLite 갱신 전 자동 스냅숏과
패키지 전체 백업/복구 규약은 [확장 안내](../../docs/extensions.md)를 따른다.
패키지 폴더에는 런타임 파일을 쓰지 않는다.

## 웹훅 보안과 운영

`POST /plugins/bizppurio/result?environment=...&token=...`은 세션 없이 별도 난수 토큰을
검증한다. 업체가 제공한 송신 IP를 추가 제한할 수 있다. 앱은 `REMOTE_ADDR`만 신뢰한다.
프록시/CDN 환경에서 IP 제한을 사용할 때는 서버가 신뢰 프록시만 검증해 실제 주소를
전달하도록 설정해야 한다. 모든 요청을 프록시 IP로 허용하는 설정은 송신자 확인이 아니다.

결과 URL은 인증값을 포함하므로 비즈뿌리오 등록용으로만 사용한다. 웹서버·프록시·CDN의
접근 로그에서 이 endpoint의 쿼리 문자열을 제외하거나 해당 경로의 접근 로그를 끈다.
예를 들어 nginx에서는 이 endpoint에 `$request_uri`·`$request`를 기록하는 기본 로그 대신
쿼리가 없는 `$uri`를 기록하는 전용 형식을 사용한다. 애플리케이션은 콜백에서 HTML·분석·
세션 쿠키를 생성하지 않으며 오류 응답에도 URL·원문을 포함하지 않는다.

JSON 객체 최대 64 KiB를 수신한다. 결과를 DB에 커밋한 뒤 HTTP 200 JSON
`{"accepted":true}`로 응답한다. 오류는 비 200이며 저장 실패에는 ACK를 보내지 않는다.
현재 계정이 별도 ACK 본문을 요구한다면 실운영 개방 전에 수신 계약을 맞춰야 한다.
중복·역순 수신은 신규 발송을 만들지 않는다. 미매칭 결과는 전화번호·본문 없이 최대
1,000건으로 제한한다. 한 계정을 여러 발송 시스템이 공유한다면 결과 URL의 전달 범위를
업체와 확인한다. 미매칭이 계속 누적되면 수신 설정을 점검한다.

## 발송 상태·재시도

접수 상태와 카카오 도달 상태를 별도로 저장한다. 접수 성공 `1000`을 도달 성공으로
표시하지 않는다. 카카오 성공 불확실·지연은 영구 실패로 단정하지 않는다.
본문은 치환 후 1,000자까지 제한한다. 긴 본문이나 다른 템플릿 유형은 자동 변형하지 않는다.

같은 환경의 동일 멱등키·동일 입력은 기존 결과를 반환한다. 같은 키의 다른 입력은
거부한다. 업체 `refkey`를 로컬 멱등키와 별도로 생성하며 업체의 중복 제거를 가정하지 않는다.
외부 호출 전 발송 의도를 저장하고, 시간 초과·5xx·이상 응답·접수 후 DB 실패는 불명확으로
분류한다. 2분 이상 남은 준비/처리 상태도 화면에서 불명확으로 표시하며 자동 재발송하지 않는다.

명확한 인증 만료 응답은 토큰 갱신 후 한 번 재시도한다. 인증 준비 실패 또는 호출 제한으로
거절된 건은 30초 후 관리자 POST로 재시도할 수 있으며 발송당 시도 상한은 3회다.
설정·템플릿이 변경되거나 보관 내용이 삭제되면 기존 건의 재시도를 차단한다.
`RateLimit-Reset` 단위가 문서에서 모호해 초기 구현은 고정 대기 간격을 사용한다.

**도달 결과 재요청**은 `/v2/report`로 결과 웹훅을 다시 요청한다. 새 메시지를 보내거나
동기 응답에서 도달 결과를 가져오는 기능이 아니다. 35일 내 메시지 키가 있는 건에 제공하며
키가 없는 불명확 건은 관리자에게 업체 발송 이력 확인을 안내한다.

**발송 정지**는 신규/재시도를 차단하며 결과 수신은 유지한다. 플러그인 OFF는 결과 경로도
중단한다. 이미 외부에 접수된 메시지는 OFF로 취소되지 않는다.

90일이 지난 발송의 수신정보·본문은 운영 모듈의 개인정보 정리 POST로 최대 100건씩
제거할 수 있다. 멱등키·결과 식별자는 남겨 오래된 요청을 다시 보내지 않는다. 자동 정리는
제공하지 않으므로 운영자가 정기적으로 실행한다. 스냅숏·백업의 보관은 별도로 관리한다.

## 다른 모듈에서 사용

필수 의존성으로 `requires: ["plugins/bizppurio"]`를 선언한다. 부가 알림이라면 선택
의존성을 쓰고 서비스 부재·실패를 업무 모듈에서 처리한다. 플러그인의 클래스를 직접 로드하지 않는다.

| 서비스 | 계약 |
| --- | --- |
| `ready.v1` | `Closure(): bool` |
| `status.v1` | `Closure(string $environment): array`; configured/enabled/api_verified/account_type, 설정된 경우 account/test_only/test_phone. 비밀번호·토큰 제외 |
| `templates.v1` | `Closure(string $action, array $input): array`; action은 list/get/save |
| `preview.v1` | `Closure(array $input): array`; template_id, variables, 선택 revision |
| `send.v1` | `Closure(array $input): array`; environment, template_id, revision, idempotency_key, phone, variables; 선택 config_revision/reference |
| `history.v1` | `Closure(array $filter): array`; environment, page, status, from/until, template_id |
| `detail.v1` | `Closure(string $id): array` |
| `retry.v1` | `Closure(string $id): array` |
| `refresh-result.v1` | `Closure(string $id): void` |
| `purge.v1` | `Closure(): int`; 제거한 발송 수 |

```php
$send = $context->service('plugins/bizppurio', 'send.v1');
// 업무 저장 트랜잭션을 커밋한 뒤 호출한다.
$result = $send([
    'environment' => 'test',
    'template_id' => $template['id'],
    'revision' => $template['revision'],
    'idempotency_key' => 'reservation:' . $reservationId . ':confirmed',
    'phone' => $recipientPhone,
    'variables' => ['이름' => $recipientName],
]);
```

입력은 서버에서 재검증하며 `DomainError`로 거부한다. 수신정보가 포함된 반환값을 그대로
로그에 쓰지 않는다. `send` 결과의 `submission`·`delivery`를 구분하고 모든 템플릿 출력은
이스케이프한다. 호출 권한과 수신번호 출처는 소비 모듈의 책임이다. 현재 파일 잠금은 단일
서버 또는 같은 저장소를 공유하는 배치를 전제로 한다. 자동 업무 큐의 신뢰성까지 제공하지 않는다.

## 검증과 문서

관련 테스트: `./vendor/bin/phpunit tests/Bizppurio`,
`./vendor/bin/phpunit tests/Web/BizppurioTest.php`, `./vendor/bin/phpunit tests/Extension`.
네트워크 테스트는 주입한 `HttpTransport`로 수행하며 실제 계정에 발송하지 않는다.

비밀번호 눈 아이콘의 실제 브라우저 회귀 검증은 개발 환경에 Chrome과 puppeteer-core가
있는 경우 `node tests/Browser/AlimtalkPasswordToggle.cjs`로 실행한다. 별도 설치 경로는
`PUPPETEER_MODULE`·`CHROME_BIN`으로 지정한다. 설정 HTML을 직접 렌더링하고 모든 HTTP
요청을 모의 처리해 검수/운영 환경·하위 경로의 조회 주소와 표시·숨김을 확인한다.

업체 규격: [발송 API](https://bizppurio.github.io/bizapi/),
[운영 가이드](https://bizppurio.github.io/guides/operations/),
[결과 코드](https://bizppurio.github.io/response-codes/).
후속 범위는 [개발 계획](../../docs/bizppurio-alimtalk-plan.md)에 정리했다.

## 관리자 화면과 테마

설정 화면은 관리자 공통 메뉴·글꼴·폼·버튼·다크 모드를 사용한다. 검수/운영 환경은 탭으로 선택한다. 테마의 `extensions/bizppurio/settings.php` 또는 개별 조각으로 재정의하며, 비밀번호 표시 동작과 CSRF 필드를 유지한다.
