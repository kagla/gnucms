# 이니톡 결제(알림톡 결제 요청)와 알림톡·결제 코어 내장 — 설계

2026-09-14. 브랜치 `feat/initalk`.

## 목표

1. KG이니시스 **이니톡결제**와 같은 흐름을 GNUCMS 기본 기능으로 제공한다. 관리자가 결제 요청(상품·구매자·휴대폰·금액·기한)을 만들면 GNUCMS가 **비즈뿌리오 알림톡**으로 결제 링크를 보내고, 고객은 GNUCMS의 결제 페이지에서 **이니시스 모바일/PC 카드결제**로 결제한다. 관리자는 통합조회·대시보드·매출/정산에서 현황을 보고 취소·환불한다.
2. 이를 위해 `feat/bizppurio-messaging`의 비즈뿌리오 플러그인·알림톡/문자 운영 모듈과 `feat/direct-pg-payments`의 이니시스 결제 코드를 **플러그인·모듈이 아닌 코어(`src/`)** 로 옮긴다. 켜고 끄는 패키지 토글 대신 설정 저장과 허용 스위치로 운영한다.
3. 새 Composer 의존성은 추가하지 않는다. `vendor/`는 지금처럼 완성본을 묶어 배포한다.

## 0. 배경과 결정

- 이니톡결제(https://inicis.com/initalkpay/)는 이니시스가 운영하는 노코드 서비스다. 가맹점이 이니시스 전용 어드민·앱에서 거래를 만들면 **이니시스가 자체 카카오 채널로** 결제 URL이 담긴 알림톡(48시간 유효)을 보내고, 고객은 이니시스 모바일 결제창에서 결제한다. 가맹점 서버가 호출할 공개 API는 없다(FAQ: "웹사이트 구축 및 연동이 전혀 필요 없습니다"). 이니톡 특약은 건당 이용료가 별도다.
- 따라서 "비즈뿌리오 알림톡으로 이니톡 결제"는 이니톡 서비스 연동이 아니라 **GNUCMS가 같은 경험을 직접 제공**하는 것이다. 이니톡 특약 없이 일반 이니시스 온라인결제 가맹점(MID)과 비즈뿌리오 계정·검수 템플릿만 있으면 된다.
- 접근법은 사용자 결정으로 **코어 내장(B)** 이다. 모듈+플러그인 조합(A)보다 확장 경계 규칙과 diff가 크지만, 사용자가 이 기능을 GNUCMS 기본 기능으로 보기 때문이다. `AGENTS.md`의 "두 브랜치를 main에 병합하지 않는다" 규칙은 이 작업으로 폐기한다.
- 이름은 사용자 결정으로 **이니톡 결제**(식별자 `initalk`)다. 이니시스 서비스명과 같으므로 문서·화면에서 "이니톡 특약 서비스가 아니라 GNUCMS가 비즈뿌리오 알림톡과 이니시스 일반결제로 직접 구현한 기능"임을 밝힌다.
- 1차 범위는 이니톡 소개서의 PC 웹·앱 기능 전부(단건·CSV 일괄 생성, 알림톡 즉시/수동/재발송, 결제 페이지, 통합조회, 결제전 취소, 전체·부분 환불, QR, 대시보드, 매출·정산)다. 전용 모바일 앱은 반응형 관리자 화면으로 대신한다.

## 1. 코어 배치

| 기능 | 출처 | 코어 위치 | 관리자 진입 |
|---|---|---|---|
| 비즈뿌리오 발송 엔진 | `plugins/bizppurio/src/*` | `src/Messaging/` (`GnuCms\Messaging\*`) | 설정 → **알림톡·문자** |
| 알림톡·문자 운영 화면 | `modules/alimtalk`, `modules/sms` | `src/Web/Controller/MessagingController.php`, `templates/default/admin/messaging/*` | 운영 → **메시지 발송** |
| 이니시스 결제 엔진 | `src/Payment/*`, `plugins/payment-inicis` | `src/Payment/` (이니시스만. KCP·KSPay·토스·`modules/shop`은 가져오지 않는다) | 설정 → **결제(이니시스)** |
| 이니톡 결제 | 신규 | `src/Initalk/`, `src/Web/Controller/InitalkAdminController.php`, `src/Web/Controller/PayController.php`, `templates/default/admin/initalk/*`, `templates/default/pay/*` | 운영 → **이니톡 결제**, 공개 `/pay/{token}` |
| 공통 | `src/Extension/ExternalRequests.php`, `src/Extension/RuntimePermit.php` | `src/Web/Middleware/ExternalRequests.php`, `src/Support/RuntimePermit.php` (확장 관리자는 새 위치를 사용) | — |
| QR | 신규 | `src/Support/QrCode.php` | — |

- 라우트는 `src/Web/Routes.php`에 코어 라우트로 등록한다. `admin/_sidebar` 운영 절에 **메시지 발송**, **이니톡 결제**를, `admin/_settings_tabs`에 **알림톡·문자**, **결제**를 추가한다.
- 플러그인의 `extension.json`, `bootstrap.php`, 서비스 클로저(`send.v1` 등)는 없앤다. 다른 코드는 `GnuCms\Messaging\MessagingService`, `GnuCms\Payment\InicisGateway`를 직접 쓴다.
- "데이터 설치·갱신" 버튼은 없앤다. 테이블은 코어 스키마 마이그레이션이 만든다.
- 테마 재정의 경로는 `admin/messaging/*`, `admin/initalk/*`, `pay/*`다. 기존 `extensions/bizppurio/*`, `extensions/alimtalk/*` 재정의는 더 이상 읽지 않는다.

## 2. 데이터 모델

`Schema::VERSION`을 23으로 올리고 아래 테이블을 `Schema::TABLES`에 넣는다(코어 마이그레이션·백업·복원 자동 포함). 기존 플러그인 테이블은 이름을 유지해 데이터를 승계한다.

| 테이블 | 출처 | 내용 |
|---|---|---|
| `bp_settings`, `bp_templates`, `bp_dispatches`, `bp_attempts`, `bp_receipts` | 플러그인 그대로 (`plugins/bizppurio/src/Schema.php` 판 2) | 환경별 암호화 설정, 승인 템플릿, 발송 건·시도·도달 결과 |
| `pay_inicis_settings`, `pay_inicis_transactions` | 결제 플러그인 그대로 (판 2) | 환경별 암호화 MID·키, 주문별 승인·조회·환불 원장(`Journal`) |
| `initalk_requests` | 신규 | 아래 컬럼 표 |
| `initalk_events` | 신규 | `id`, `request_id`, `type`, `actor`(관리자 표시명·`system`·`customer`), `note`, `created_at` |
| `initalk_batches` | 신규 | `id`, `filename`, `total`, `created`, `failed`, `errors`(JSON, 행 번호·사유), `created_by`, `created_at` |
| `initalk_ledger` | 신규 | `id`, `request_id`, `kind`(`approve`/`refund`), `amount`, `at`, `reference`(TID/취소 TID), `created_at`. 게이트웨이 조회로 확인된 값만 기록 |

`initalk_requests` 컬럼:

| 컬럼 | 설명 |
|---|---|
| `id` VARCHAR(32) PK | 32자 hex 난수. 이니시스 `oid`로 사용 |
| `number` VARCHAR(20) UNIQUE | 사람이 보는 주문번호 `IT-YYYYMMDD-NNNN`(일별 순번, 유일 제약 충돌 시 최대 5회 재시도) |
| `url_token` VARCHAR(40) UNIQUE | 결제 링크용 난수(160비트, base64url). 주문 id와 별개 |
| `environment` VARCHAR(8) | `test`/`live`. 생성 시점의 `initalk.environment` |
| `status` VARCHAR(16) | `created`/`waiting`/`paid`/`expired`/`cancelled`/`refunded` |
| `product_name` VARCHAR(30), `product_detail` VARCHAR(150) | 상품명·상품상세 |
| `buyer_name` VARCHAR(30) | 구매자명 |
| `phone` TEXT | 암호화된 숫자 문자열. 정리 후 빈 문자열 |
| `phone_hash` VARCHAR(64) | HMAC-SHA256(`auth.secret`, 숫자) — 검색용. 정리 후 빈 문자열 |
| `phone_mask` VARCHAR(16) | `010-****-1234` 표시용 |
| `amount` INTEGER | 원 단위 정수, 100 이상 99,999,999 이하 |
| `expires_at` INTEGER | 결제기한 |
| `batch_id` VARCHAR(32) NULL | CSV 배치 |
| `dispatch_count` INTEGER, `last_dispatch_id` VARCHAR(32) NULL, `last_dispatched_at` INTEGER NULL | 알림톡 발송 이력 요약 |
| `checkout_started_at` INTEGER NULL | 마지막 결제창 시작 시각 |
| `paid_at` INTEGER NULL, `transaction_id` VARCHAR(40) | 승인 시각·TID |
| `refunded_amount` INTEGER | 누적 환불액 |
| `needs_review` INTEGER | 승인·조회 불확정 표시 |
| `created_by` INTEGER, `created_at`, `updated_at`, `status_changed_at` INTEGER | |

인덱스: `status`, `created_at`, `phone_hash`, `expires_at`, `batch_id`.

`site_settings` 키(`initalk.*`):

| 키 | 기본값 | 용도 |
|---|---|---|
| `initalk.store_name` | 사이트 이름 | 알림톡·결제 페이지의 상점명 |
| `initalk.support_phone` | 빈 값 | 알림톡의 고객센터 |
| `initalk.expiry_hours` | 48 | 기본 결제기한(1~720) |
| `initalk.environment` | `test` | 사용 환경. 알림톡·결제 모두 이 환경을 쓴다 |
| `initalk.template.test`, `initalk.template.live` | 빈 값 | 환경별 알림톡 템플릿 ID(`bp_templates.id`) |
| `initalk.settlement_days` | 3 | 지급예정일 = 승인일 + N일 (영업일·공휴일 계산은 하지 않는다) |

## 3. 상태 흐름

```
created ──알림톡 접수 성공──▶ waiting ──고객 결제 승인──▶ paid ──전체 환불──▶ refunded
  │                             │                          └─부분 환불─▶ paid (refunded_amount > 0)
  ├──관리자 결제전 취소─────────┴──▶ cancelled
  └──결제기한 경과──────────────┴──▶ expired ──기한 연장·재발송──▶ waiting
```

- `created`(결제생성): 등록만 됨. 링크·QR은 유효하므로 알림톡 없이도 결제할 수 있다.
- `waiting`(결제대기중): 알림톡이 비즈뿌리오에 접수(`1000`)됨. 도달 실패는 상태를 바꾸지 않고 이벤트로 남기며 재발송 가능으로 표시한다.
- `paid`: 콜백에서 `Gateway::complete()`(서버 승인) 성공 후 `fetch()` 재조회로 상점·주문번호·금액·TID 일치를 확인하면 `paid`, 원장 `approve`. 콜백이 유실되면 상세의 **결제 상태 조회**(`sync`)로 복구한다.
- `expired`: 지연 처리. 관리자 이니톡 결제 화면 진입, 결제 페이지 접근, `bin/initalk.php expire`에서 `expires_at < now`인 `created`/`waiting`을 만료시킨다. 단 `checkout_started_at`이 30분 이내인 건은 건너뛴다(승인 중 결제 보호). 한 번에 최대 200건.
- `cancelled`: 관리자 결제전 취소. `created`/`waiting`/`expired`에서만 가능.
- `refunded`: 환불 누적액이 `amount`와 같아지면. 부분 환불은 `paid` 유지.
- 재발송: `expired`이면 새 기한(`now + expiry_hours`)으로 연장한 뒤 `waiting`. `paid`·`cancelled`·`refunded`는 발송 불가.
- 모든 전이는 `UPDATE … SET status = ? WHERE id = ? AND status = ?` 조건부 갱신이며, 변경 행이 0이면 "상태가 바뀌었습니다" 검증 오류를 낸다. 전이마다 `initalk_events`에 기록한다.
- 결제 페이지 `start`와 콜백은 상태가 `created`/`waiting`일 때만 진행한다. 만료 시각이 지났지만 아직 `expired`로 바뀌지 않은 건도 `start`는 거부한다. 콜백은 `checkout_started_at`이 있으면 만료 여부와 관계없이 승인 결과를 반영한다(고객이 결제창에서 시간을 끈 경우).

**환경**: `initalk.environment`가 알림톡 환경(`bp_*`)과 결제 환경(`pay_inicis_*`)을 함께 정한다. 테스트 환경의 알림톡은 비즈뿌리오 설정의 테스트 수신번호로만 나가고(기존 규칙), 결제 페이지는 환경과 무관하게 **링크를 가진 누구나** 결제할 수 있다. 요청에는 생성 시 환경을 저장하므로 환경을 바꿔도 기존 요청은 자기 환경의 계정으로 조회·환불한다.

## 4. 서비스·클래스

### `src/Messaging/` (플러그인 이관)

`GnuCms\Plugins\Bizppurio\*` → `GnuCms\Messaging\*`로 네임스페이스만 바꾸고 다음을 조정한다.

- `Schema`: DDL을 코어 `Schema::migrateMessaging()`으로 옮긴다. `PackageSchema` 의존을 없앤다.
- `MessagingService::ready()`는 항상 참이다(코어 스키마가 테이블을 보장). 실행 허용은 `GnuCms\Support\RuntimePermit`(`storage/extensions-runtime/permits/messaging-{env}`)을 쓴다.
- `MessagingService`(신규, 기존 `bootstrap.php`의 서비스 클로저를 메서드로): `ready()`, `status(string $env)`, `templates(string $action, array $input)`, `preview(array $input)`, `send(array $input)`, `history(array $filter)`, `detail(string $id)`, `retry(string $id)`, `refreshResult(string $id)`, `purge()`, 문자용 `textPreview`/`textSend`/`textHistory`/`textDetail`/`textRetry`/`textRefreshResult`/`textPurge`. 입력·반환 계약은 플러그인 README의 서비스 표와 같다.
- `SettingsController`: 라우트 `/admin/settings/messaging`(GET/POST), `/admin/settings/messaging/password`(POST JSON), `/admin/settings/messaging/verify`(POST), `/admin/settings/messaging/permit`(POST). 결과 URL 표시는 `/messaging/bizppurio/result?environment=…&token=…`.
- 웹훅: `Routes.php`에서 `ExternalRequests`로 `POST /messaging/bizppurio/result`와 이전 주소 `POST /plugins/bizppurio/result`를 같은 검증기·처리기에 연결한다.

### `src/Web/Controller/MessagingController.php` (운영 화면 이관)

`modules/alimtalk/src/Controller.php`와 `modules/sms/src/Controller.php`를 합친다. 경로 대응:

| 이전 | 새 경로 |
|---|---|
| `/modules/alimtalk/home` | `/admin/messaging` (템플릿 목록으로) |
| `/modules/alimtalk/templates`, `…/templates/remote` | `/admin/messaging/templates`, `/admin/messaging/templates/remote` |
| `/modules/alimtalk/send` | `/admin/messaging/send` |
| `/modules/alimtalk/history`, `…/detail` | `/admin/messaging/history`, `/admin/messaging/history/{id}` |
| `/modules/alimtalk/retry`, `…/refresh`, `…/purge` | `/admin/messaging/history/{id}` POST `action=retry|refresh-result`, `/admin/messaging/history` POST `action=purge` |
| `/modules/sms/*` | `/admin/messaging/sms/send`, `/admin/messaging/sms/history`, `/admin/messaging/sms/history/{id}` POST `action=retry|refresh-result`, `/admin/messaging/sms/history` POST `action=purge` |

템플릿은 `templates/default/admin/messaging/*.php`로 옮기고 `admin/layout`을 직접 쓴다(`admin/extension` 레이아웃 불필요). 세션 미리보기 확인값(10분·10개) 규칙은 그대로다.

### `src/Payment/` (결제 엔진 이관)

- 가져오는 파일: `Gateway`, `DirectGateway`, `InicisGateway`, `Journal`, `CallbackToken`, `ExecutionLock`, `Transport`, `StreamTransport`, `ProviderConfig`, `Settings`, `SettingsController`, `templates/settings.php`(→ `templates/default/admin/payment_settings.php`).
- `Settings::PROVIDERS`는 `['inicis' => 'KG이니시스']`만 남긴다. `Settings`에는 `ready()`가 없다 — 실행 가능 여부는 `available(string $environment)`/`requireEnabled(string $environment)`이 판단하며, 설정이 저장되어 있고 그 판(revision)에 대해 `RuntimePermit`(`storage/extensions-runtime/permits/payment-inicis-{env}`)이 허용됐을 때만 실행한다. 코어 테이블 존재 자체는 실행 조건이 아니다(코어 스키마가 항상 보장).
- `SettingsController` 라우트: `/admin/settings/payment`(GET/POST), `/admin/settings/payment/permit`(POST).
- `Gateway` 계약은 그대로다. 이니톡 결제는 `order = ['id', 'number', 'total', 'order_name', 'environment', 'provider' => 'inicis', 'transaction_id']`, `customer = ['name', 'phone', 'email' => '']` 형태로 호출한다. `Journal`이 `pay_inicis_transactions`에 주문 id별 상태를 보관한다.

### `src/Initalk/`

| 클래스 | 책임 |
|---|---|
| `Settings` | `site_settings`의 `initalk.*` 읽기·검증·저장. 템플릿 선택 검증(§7) |
| `Status` | 상태 상수·라벨·허용 전이표 |
| `RequestNumber` | 일별 순번 채번 |
| `Requests` | 생성(`create(array $input, int $adminId)`), 검색(`search(array $filter, int $page)`), 상태 카운트(`counts()`), 상세(`find`, `findByToken`), 고객 이력(`customerSummary(string $phone)`: 거래횟수·총액·최근거래일), 전이(`cancel`, `expire(int $limit)`, `markPaid`, `applyRefund`), 개인정보 정리(`purge()`) |
| `Notifier` | 요청 → 템플릿 변수 치환 입력 구성 → `MessagingService::send()` 호출(멱등키 `initalk:{id}:{dispatch_count+1}`) → `waiting` 전이·이벤트. 재발송·기한 연장 포함. 선택 건 일괄 발송은 건별 호출 후 결과 요약 |
| `Checkout` | `start(request, device)` → `checkout_started_at` 기록 후 `Gateway::checkout()`; `complete(request, callback)` → `ExecutionLock` 안에서 `complete()`+`fetch()`+`markPaid`+원장; `sync(request)` → `fetch()`로 상태·환불 대조; `refund(request, amount, reason, adminId)` → `Gateway::cancel()`+원장 |
| `CsvImport` | 업로드 파싱(UTF-8·BOM·CP949 자동 변환), 행 검증, 미리보기 토큰(세션, 10분), 확정 시 `Requests::create` 반복 + `initalk_batches` 기록 |
| `Sales` | 기간별 승인·환불·순매출 집계, 월별 합계, 지급예정일별 정산 캘린더, CSV 내보내기 행 생성. 원천은 `initalk_ledger` |
| `Events` | 이벤트 기록·조회 |
| `Ledger` | `initalk_ledger` 기록(`record`)·요청별 조회(`forRequest`)·기간 조회(`between`). 같은 `reference`는 한 번만 기록 |

컨트롤러: `InitalkAdminController`(관리자 전부), `PayController`(공개 결제 페이지·시작·복귀·콜백 처리기). 콜백 검증기는 `CallbackToken::verify()`.

CLI `bin/initalk.php`: `expire`(만료 처리), `sync`(최근 7일 `checkout_started_at`이 있는 미결·`needs_review` 건 조회), `purge`(개인정보 정리). 기존 `bin/*.php`와 같은 인자 규약(`--config=`).

### `src/Support/QrCode.php`

외부 의존성 없는 QR 인코더. 바이트 모드, 오류정정 M, 버전 1~10(최대 213바이트; 결제 링크는 100자 이내), 8개 마스크 중 벌점 최소 선택, `svg(string $text, int $module = 4): string` 출력. 입력이 한도를 넘으면 `DomainError`. 표준 예제 벡터로 Reed–Solomon과 형식 정보(BCH)를 테스트하고, 고정 입력의 행렬 스냅숏을 테스트에 둔다(스냅숏은 구현 시 휴대폰 스캔으로 한 번 확인한다).

## 5. 관리자 화면·라우트 (이니톡 결제)

전부 전체 관리자 + 세션 CSRF. 목록·상세는 `admin/layout`, 부속 자산은 `www/themes/default/initalk.css`(및 필요 시 `initalk.js`, 내용 해시 버전).

| 화면 | 경로 | 내용 |
|---|---|---|
| 대시보드 | `GET /admin/initalk` | 상태 카운트 5개(결제생성·결제대기중·결제완료·기한만료·결제전취소), 이달 승인 건수·금액, 환불 건수·금액, 순매출, 지급예정금액, 최근 요청 10건. 진입 시 만료 처리 |
| 통합조회 | `GET /admin/initalk/requests` | 검색: 등록일 범위(+1/2/3개월 버튼), 휴대폰(숫자만), 구매자명, 상품명, 주문번호, 금액, 상태, 알림톡 발송 가능 여부. 20건 페이지. 체크박스 + **알림톡 발송**·**결제전 취소** → `POST /admin/initalk/requests/bulk` (`action=send|cancel`, 최대 100건, 건별 결과 요약) |
| 단건 생성 | `GET/POST /admin/initalk/requests/new` | 상품명·상품상세·구매자명·휴대폰(하이픈 자동 서식, `_phone_input` 조각 재사용)·금액(한글 금액 표시)·결제기한(시간, 기본 설정값)·☑ 즉시 알림톡 발송. 저장 후 상세로 이동 |
| 고객 확인 | `POST /admin/initalk/customer` (JSON) | 휴대폰으로 거래횟수·총 거래금액·최근거래일 |
| CSV 일괄등록 | `GET /admin/initalk/requests/import`, `POST …/import`(업로드→미리보기), `POST …/import/confirm`, `GET …/import/sample` | §8 |
| 상세 | `GET /admin/initalk/requests/{id}` | 상태·주문번호·구매자·마스킹 해제 번호·상품·금액·기한·환경·결제 링크(복사)·QR·알림톡 발송 요약과 발송 상세 링크·결제일시·TID·환불 내역·타임라인 |
| 액션 | `POST …/{id}/send`(발송·재발송·기한 연장), `…/cancel`, `…/sync`, `…/refund`(`amount`, `reason`), `GET …/{id}/qr.svg` | |
| 매출·정산 | `GET /admin/initalk/sales`, `GET …/sales/export` | 기간(월 선택 또는 시작~끝), 일별 승인·환불·순매출 표와 합계, 지급예정일별 캘린더, CSV(UTF-8 BOM) |
| 설정 | `GET/POST /admin/initalk/settings`, `POST /admin/initalk/purge` | §2의 `initalk.*`, 환경별 템플릿 선택(§7), 개인정보 정리 실행 |

## 6. 공개 결제 페이지

| 경로 | 동작 |
|---|---|
| `GET /pay/{token}` | 토큰으로 요청 조회(없으면 404). 상태별 화면: `created`/`waiting` → 확인 화면("○○ 님, 결제할 내역을 확인해 주세요": 상점명·상품명·상품상세·금액·결제기한, [다음]). `paid`/`refunded` → 완료 화면(결제일시·금액·주문번호, 환불 시 환불 표시). `expired` → "결제 기한이 지났습니다. 상점에 문의해 주세요"(고객센터). `cancelled` → "취소된 결제 요청입니다". 만료 시각이 지난 미처리 건은 이 시점에 만료 처리한다 |
| `POST /pay/{token}/start` | 세션 CSRF(공개 세션) 검사, 상태·기한 재검사, `Checkout::start()`. 기기 판별(UA에 `Mobile`/`Android`/`iPhone`/`iPad` → 모바일): 모바일은 이니시스 모바일 결제 폼(EUC-KR) 자동 제출 화면, PC는 웹표준 결제창(`INIStdPay.js`) 화면. `returnUrl=/pay/{token}/return`, `callbackUrl=/pay/callback?id={id}&state={HMAC}` |
| `POST /pay/callback?id=&state=` | `ExternalRequests`(세션 없음, `application/x-www-form-urlencoded`, 64KB). 검증기: `id` 형식·요청 존재·`CallbackToken::verify()`. 처리기: `Checkout::complete()` → `303 /pay/{token}`. `DomainError` 5xx면 `needs_review` + 이벤트 후 같은 곳으로 이동 |
| `GET /pay/{token}/return` | 결제창 닫기·실패 복귀. "결제가 완료되지 않았습니다" 안내와 함께 `303 /pay/{token}` |

- 레이아웃 `templates/default/pay/layout.php`: 사이트 이름·상점명만 있는 최소 화면, 모바일 우선, 사이트 추적·광고 코드 없음, 외부 자산은 PC 결제창의 PG 스크립트뿐. 응답 헤더 `Cache-Control: no-store`, `Referrer-Policy: no-referrer`.
- 결제 페이지는 로그인·회원과 무관하다. 관리자 로그인 상태에서 열면 상단에 "관리자: 요청 상세 보기" 링크만 추가한다.

## 7. 알림톡 템플릿과 발송

운영자가 비즈뿌리오·카카오에 검수받을 권장 템플릿(`docs/initalk.md`에 수록):

```
[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.

■ 상점명: #{상점명}
■ 상품명: #{상품명}
■ 금액: #{금액}원
■ 결제기한: #{결제기한}

* 기한 내 결제를 완료하지 못한 경우 상점에 문의해 주세요.
* 고객센터: #{고객센터}
```

버튼: 웹링크 **결제하기**, 모바일·PC 링크 모두 `https://<사이트 주소>/pay/#{결제토큰}`.

- 이니톡 결제가 제공하는 변수: `상점명`, `구매자명`, `요청일`(`M월 D일`), `상품명`, `금액`(천 단위 구분), `결제기한`(`YYYY년 MM월 DD일 HH:mm`), `고객센터`, `결제토큰`, `주문번호`. 템플릿은 이 중 일부만 써도 된다.
- 설정에서 템플릿을 저장할 때 서버가 검증한다: 템플릿이 존재하고 사용 가능 상태, 본문·버튼 변수가 제공 변수 집합의 부분집합, `#{결제토큰}`을 URL에 포함한 WL 버튼이 1개 이상. 하나라도 어긋나면 저장을 거부한다.
- 발송은 `Notifier`가 요청 저장 트랜잭션 커밋 후 `MessagingService::send()`를 호출한다. 결과(`submission`/`delivery`)는 `bp_*`가 보관하고 요청에는 `last_dispatch_id`·`dispatch_count`·`last_dispatched_at`만 요약한다. 상세에서 발송 상세(`/admin/messaging/history/{id}`)로 연결한다.
- 발송 정지(메시징 실행 허용 해제)나 템플릿 미설정이면 발송 버튼을 비활성화하고 서버도 거부한다. 생성 자체는 막지 않는다.

## 8. CSV 일괄등록

- 샘플(`GET …/import/sample`): UTF-8 BOM, 헤더 `상품명,상품상세,구매자명,휴대폰번호,금액,결제기한(시간)`. 마지막 열은 선택.
- 업로드: 최대 1MB·500행. 인코딩은 UTF-8 유효성 검사 후 실패하면 CP949로 간주해 변환. 헤더는 이름으로 대응(순서 무관), 모르는 열은 무시.
- 검증은 단건 생성과 같은 규칙. 오류가 있으면 행 번호·열·사유 목록을 보여주고 확정 버튼을 내지 않는다. 전부 통과하면 미리보기(행·합계 금액)와 ☑ 즉시 알림톡 발송, **거래등록** 버튼. 미리보기 내용은 세션 토큰(10분)에 보관하고 확정 POST는 토큰만 받는다(브라우저가 바꾼 값을 믿지 않는다).
- 확정: `initalk_batches` 생성 → 행마다 `Requests::create` → 실패 행은 배치 `errors`에 기록하고 계속 진행 → 즉시 발송을 골랐으면 생성된 건에 `Notifier` 순차 호출(발송 실패는 요청 상태를 바꾸지 않음) → 결과 화면(성공/실패/발송 요약, 통합조회 필터 링크 `batch=`).

## 9. QR 코드

- `GET /admin/initalk/requests/{id}/qr.svg`: 결제 링크 `https://<사이트>/pay/{token}`의 QR SVG. 관리자만. `Cache-Control: private, no-store`.
- 상세 화면에 QR을 표시하고 "새 창에서 크게 보기"·"SVG 저장" 링크를 둔다. 대면 결제는 고객이 QR을 찍어 같은 결제 페이지로 들어오는 것이다.
- 인코더는 §4의 `Support\QrCode`. 취소·만료·완료 건도 QR은 만들 수 있으나 결제 페이지가 상태 안내를 낸다.

## 10. 매출·정산

- 원천은 `initalk_ledger`(승인·환불 확정값). 승인은 `markPaid` 시, 환불은 `applyRefund` 또는 `sync`가 PG 조회에서 확인한 취소를 반영할 때 기록한다. `sync`가 이미 기록된 환불(`reference` 동일)을 다시 넣지 않는다.
- 매출 화면: 기간 선택(월 선택 기본 이번 달, 또는 시작~끝 90일 이내). 일별 행: 승인 건수·금액, 환불 건수·금액, 순매출. 합계 행. 환경 필터(test/live, 기본 live).
- 정산 캘린더: 지급예정일 = 승인일 + `initalk.settlement_days`. 지급예정일별 순매출 합계를 월 달력에 표시한다. 실제 PG 정산과 다를 수 있음을 화면과 문서에 명시한다. 명세서·PG 정산서 자동 수집은 하지 않는다.
- CSV 내보내기: 일별 표 + 건별 원장(주문번호·상품명·구매자명·승인/환불·금액·시각·TID). 휴대폰은 마스킹 값.

## 11. 보안

- 관리자 화면·액션: 전체 관리자만, 기존 `SessionGuard` CSRF. 이니톡 결제·메시징·결제 설정 POST 모두 포함.
- 외부 POST(이니시스 콜백, 비즈뿌리오 웹훅)는 `ExternalRequests`로 세션·CSRF 없이 처리한다. HMAC·난수 토큰은 상수시간 비교, 본문 64KB·Content-Type·JSON 형식 검사, HTML·쿠키·분석 코드 없음, 오류 응답에 URL·원문·번호 없음.
- 비밀정보(비즈뿌리오 비밀번호·KAPI 키·이니시스 SignKey/HashKey/INIAPI Key·구매자 휴대폰)는 `SecretCipher`(`auth.secret`)로 암호화한다. 카드번호·인증 토큰·PG 응답 원문·발송 본문 원문을 로그에 남기지 않는다.
- 결제 링크 토큰은 160비트 난수. 토큰 조회는 `url_token` 인덱스로만 하고 주문 id는 URL에 쓰지 않는다(콜백 `id`는 HMAC `state`와 함께).
- 결제 페이지·콜백 응답 `Cache-Control: no-store`, `Referrer-Policy: no-referrer`. `start`는 같은 요청에 대해 10초 안에 다시 오면 거부한다(중복 결제창).
- 콜백·환불·발송·만료 처리는 `ExecutionLock`(storage 파일 잠금)으로 직렬화하고, 상태 전이는 조건부 `UPDATE`다.
- 서버 접근 로그에서 웹훅 URL의 쿼리(토큰)를 제외하는 설정을 `docs/initalk.md`에 안내한다.
- 이니시스 콜백 IDC·승인 URL 호스트 검증, 망취소, 금액·주문번호·수단 검증은 기존 `InicisGateway` 로직을 그대로 쓴다.

## 12. 오류 처리

- 알림톡: 인증 실패·호출 제한으로 명확히 거절되면 요청 상태를 유지하고 이벤트를 남기며 상세에서 재시도한다. 접수 불명확 건은 자동 재발송하지 않고 결과 재요청을 안내한다. 도달 실패(`7xxx`)는 이벤트 + 재발송 가능 표시.
- 결제 승인: 응답 유실·검증 실패는 게이트웨이가 망취소를 시도하고 예외를 던진다. 콜백 처리기는 5xx `DomainError`면 `needs_review`를 켜고 이벤트를 남긴 뒤 결제 페이지로 보낸다(고객에게는 "결제 확인 중, 상점에 문의"). 관리자는 `sync`로 확정한다.
- 환불: `Journal`의 보류·대조·2시간 미처리 종료 규약을 그대로 쓴다. 불확정 환불은 상세에 표시하고 `sync`로 대조한다.
- CSV: 파싱·인코딩 오류는 파일 단위, 값 오류는 행 단위로 보고한다. 확정 중 생성 실패는 배치 `errors`에 남기고 계속 진행한다.
- 실행 허용 해제(메시징 발송 정지, 결제 API 정지) 상태에서는 해당 버튼을 비활성화하고 서버도 `DomainError::validation`으로 거부한다. 결제 페이지는 결제 API 정지 시 "지금은 결제할 수 없습니다"를 낸다.
- 만료 처리 중 개별 실패는 나머지 건을 계속 처리하고 오류를 로그에 남긴다.

## 13. 설치·업그레이드·백업·복원

- 새 설치: `Schema::create()`가 모든 테이블을 만든다.
- 업그레이드(`Schema::migrateAll()` v23): 새 테이블 생성. `extension_schemas`에 `plugins/bizppurio` 또는 `plugins/payment-inicis` 등록이 있으면 해당 테이블을 그대로 두고(구조가 같음) 등록 행을 삭제한다. `storage/extensions/enabled.json`에 `plugins/bizppurio`, `plugins/payment-inicis`, `modules/alimtalk`, `modules/sms`(= `Catalog::ABSORBED`)가 있으면 제거한다. 배포본에 남은 이전 패키지 폴더는 삭제하도록 문서화한다. 지우지 않고 남아 있어도(또는 지우기 전 재기동해도) 코어 예약 경로 충돌이 아니라 `Catalog::ABSORBED` 검사로 막는다: `Manager::boot()`는 순서 계산·`bootstrap.php` 로딩 전에 그 키를 걸러 실행하지 않고 사용 상태 화면에 흡수 안내를 보여주며, `Manager::setEnabledMany()`도 그 키를 다시 켜는 조작을 `DomainError::validation`으로 거부한다.
- 실행 허용값은 `storage/extensions-runtime/permits/`에 두며 백업에서 제외하고 복원 시 해제한다(`BackupManager`의 제외 목록과 복원 후 처리 갱신). SQLite 자동 복원도 같다.
- 백업 v2는 `Schema::TABLES` 기준이므로 새 테이블이 자동 포함된다. MySQL 덤프 목록도 같은 상수를 쓴다.
- 코어 `Schema::VERSION`은 23으로 올린다. 제품 버전은 Release Please가 정한다(MINOR).

## 14. 문서

- `AGENTS.md`: 기능 지도에 알림톡·문자 발송, 이니시스 결제, 이니톡 결제를 추가하고 "확장 예제" 항목은 유지. "비즈뿌리오 플러그인·알림톡 모듈은 `feat/bizppurio-messaging`", "결제 플러그인·쇼핑몰은 `feat/direct-pg-payments`" 규칙을 삭제하고 쇼핑몰(`modules/shop`)·KCP·KSPay·토스만 해당 브랜치에 남는다고 적는다.
- `docs/initalk.md`(신규): 운영 절차(이니시스 MID 계약 → 결제 설정 → 비즈뿌리오 계정·템플릿 검수 → 알림톡 설정 → 이니톡 결제 설정 → 테스트 → 운영), 권장 템플릿, 웹훅·콜백 URL 등록, 접근 로그 제외, 만료·정리 CLI, 정산 캘린더의 한계, 이니톡과의 차이.
- `docs/messaging.md`(신규): 플러그인·모듈 README를 코어 기준으로 옮긴다. `docs/bizppurio-alimtalk-plan.md`는 가져오지 않는다(이력은 브랜치에 남는다).
- `docs/extensions.md`, `README.md`: 플러그인 예시 문구·기능 목록 정리.
- `config/config.sample.php`: 변경 없음(비밀정보는 DB 암호화).

## 15. 검증

- 이관 테스트: `tests/Bizppurio/*` → `tests/Messaging/*`(네임스페이스·경로만), `tests/Payment/GatewayTest.php`·`CallbackTest.php`·`FakeTransport.php`·`Fixtures.php`(이니시스만), `tests/Web/AlimtalkTest.php`·`SmsTest.php` → `tests/Web/MessagingTest.php`(새 경로).
- 신규 단위 테스트 `tests/Initalk/`: 상태 전이표·조건부 갱신, 만료(30분 보호 포함), 채번 충돌 재시도, 휴대폰 정규화·해시·마스킹, `CsvImport`(BOM·CP949·헤더 순서·오류 행), `Sales` 집계·지급예정일, `QrCode`(RS·형식 정보 벡터·스냅숏), 개인정보 정리.
- 신규 웹 테스트 `tests/Web/InitalkTest.php`: 관리자 권한·CSRF, 생성→즉시 발송(모의 비즈뿌리오)→결제 페이지→`start`(모바일/PC 폼)→콜백(모의 PG 승인·조회)→완료 화면→환불(전체·부분)→매출 반영, 결제전 취소·만료·재발송, 웹훅 이전 주소 호환, `needs_review` 경로.
- 마이그레이션 테스트: 플러그인 판 2 테이블과 `extension_schemas` 등록이 있는 DB에서 v23 이후 데이터 보존과 등록 삭제. SQLite·MySQL 양쪽(`TEST_MYSQL_DSN`).
- 브라우저: `tests/Browser/AlimtalkPasswordToggle.cjs` 경로 수정, 이니톡 결제 상세·결제 페이지 모바일 폭 확인은 수동.
- 완료 전 `./vendor/bin/phpunit` 전체와 `git diff --check`.

## 16. 구현 순서

각 단계는 `feat/initalk` 워크트리에서 독립 커밋 묶음으로 진행한다.

1. 워크트리·브랜치 생성, `vendor/` 복사, 기준 테스트 통과 확인.
2. 공통 이동: `ExternalRequests`·`RuntimePermit` 코어 위치로, 확장 관리자 참조 갱신.
3. 결제 엔진 코어화: `src/Payment`·테스트 가져오기(이니시스만), `Settings` 코어 테이블·허용값 전환, 설정 화면·라우트·설정 탭, 스키마 v23의 `pay_inicis_*` 생성·승계.
4. 메시징 코어화: `src/Messaging` 이관, `MessagingService`, 설정 화면·웹훅 라우트, 운영 화면 이관, 스키마의 `bp_*` 생성·승계, 테스트 이관.
5. 이니톡 결제 도메인: 스키마 테이블, `Settings`·`Status`·`RequestNumber`·`Requests`·`Events`·`Notifier`·`Checkout`·`Ledger`, 단위 테스트.
6. 이니톡 결제 화면 1: 설정, 단건 생성, 상세, 통합조회, 액션(발송·취소·조회·환불), 공개 결제 페이지·콜백·복귀, 웹 테스트.
7. 이니톡 결제 화면 2: 대시보드, CSV 일괄등록, QR, 매출·정산, CLI.
8. 문서·`AGENTS.md`·README, 전체 테스트(SQLite/MySQL), 라이브 적용 절차(웹훅 URL 재등록, 이전 패키지 폴더 삭제) 정리.

## 17. 범위 밖

전용 모바일 앱, 회원 계정과 결제 요청 연결, 다른 PG(토스·KCP·KSPay) 선택, 정기 과금, 가상계좌·계좌이체, 현금영수증, PG 정산서 자동 수집, 알림톡 실패 시 문자 자동 대체, 예약 발송, 다국어. 쇼핑몰(`modules/shop`)과 영카트 모듈의 결제 연동은 별도 작업이다.
