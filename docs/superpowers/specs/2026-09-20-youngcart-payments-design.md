# 영카트 결제 설계 (2026-09-20)

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

쇼핑몰(`src/Shop`)의 주문에 결제를 붙인다. 카드·간편결제·실시간 계좌이체·
가상계좌·무통장입금 다섯 수단을 모두 다루되, 실행은 세 묶음으로 나눈다. 결제사 연동은
`feat/initalk` 브랜치에 있던 이니시스 결제 계층(`src/Payment`)을 통합 브랜치 `feat/core-commerce`로
옮겨 쓴다. 그 계층은 이니시스 코드가 놓여 있는 자리일 뿐이며, 이 설계는 이니톡 결제와 무관하다.

## 1. 범위

| 묶음 | 내용 |
|---|---|
| 3.1 | 결제 계층 이식, 주문 모델(결제 수단·결제 완료 상태·기한), 주문서의 수단 선택, 결제 페이지와 콜백, **카드**(간편결제 포함 결제창)와 **무통장입금**, 관리자 결제 구역, 미결제 만료 |
| 3.2 | **실시간 계좌이체**와 **간편결제 직접 호출**: 게이트웨이의 카드 고정 해제, 수단별 응답 검증, 환불계좌 |
| 3.3 | **가상계좌**: 계좌 발급 응답, 입금통보 수신, 입금 기한 만료 |

범위 밖: 주문·결제 알림(4단계, 알림 이벤트 카탈로그), 반품·교환(별도 단계 — 아래 §9의 요구만
남긴다), 에스크로, 휴대폰 결제, 현금영수증 발행 화면(결제창이 제공하는 것을 그대로 쓴다), 이니톡.

## 2. 결제 계층 이식

`feat/initalk`에서 다음을 그대로 가져온다. 어느 파일도 이니톡이나 비즈뿌리오에 의존하지 않는다.

- `src/Payment/` 11개: `Gateway`(계약), `DirectGateway`(원장 위의 공통 흐름), `InicisGateway`,
  `Settings`(환경별 자격증명·허용), `Journal`(결제 원장), `ProviderConfig`, `CallbackToken`,
  `ExecutionLock`, `Transport`·`StreamTransport`, `SettingsController`.
- `templates/default/admin/payment_settings.php`, `tests/Payment/*`, `tests/Web/PaymentSettingsTest.php`.
- App 배선: `paymentSettings()`, `inicisGateway()`, `setInicisGateway()`. 라우트
  `/admin/settings/payment`(GET·POST, 이름 `admin.settings.payment`). 설정 탭에 "결제" 항목.
- 표 `pay_inicis_settings`, `pay_inicis_transactions`(둘 다 `id VARCHAR(32)`, `payload`). 우리 코어
  스키마는 25판이고 이니톡 브랜치의 판 번호와 갈라져 있으므로, **26판**에서 멱등한
  `migratePayments()`로 만든다. 원장은 `id`를 32자리 16진수로 요구한다.

게이트웨이 계약은 그대로 둔다: `checkout(order, customer, returnUrl, callbackUrl, device)` →
결제창 정의, `complete(order, callback)` → 승인, `fetch(order)` → 조회, `cancel(order, amount,
remaining, reason, key)` → 환불. `order` 배열은 `id`(원장 키), `provider`, `environment`,
`config_revision`, `total`, `order_name`, `transaction_id`를 싣는다. 3.2에서 `method`가 더해진다(§6).

## 3. 주문 모델 (모듈 스키마 3판)

`yc_orders`에 칸을 더한다. 기존 2판 설치는 `addColumnIfMissing` 방식으로 올린다.

| 칸 | 값 |
|---|---|
| `payment_method` | `card` · `easy_pay` · `bank_transfer` · `virtual_account` · `manual_transfer` |
| `payment_id` | 결제 원장 키. 주문 생성 때 `random_bytes(16)`의 16진수. 무통장은 비운다 |
| `payment_environment`, `payment_revision` | 결제창을 연 시점의 결제 환경(test·live)과 자격증명 판. 승인·조회·환불은 이 판으로 한다 |
| `paid_at`, `paid_amount` | 결제 완료 시각과 금액 |
| `refunded_amount` | 환불 누계. 원장의 환불 기록 합과 같아야 한다 |
| `payment_detail` | 수단별 표시 정보 JSON(아래) |
| `pay_by` | 결제 기한(UTC 초). 지나면 미결제 주문을 취소한다 |

`payment_detail`:
- 카드·간편결제·계좌이체: `{"tid": …, "label": "카드" \| "카카오페이" …, "bank": …}` — 승인 응답에서
  표시용으로 남기는 값만. 카드번호·인증 토큰·응답 원문은 저장하지 않는다(결제 계층의 규칙).
- 가상계좌: `{"bank_code", "bank_name", "account", "holder", "deadline"}`. 승인 응답의
  `VACT_BankCode`·`vactBankName`·`VACT_Num`·`VACT_Name`·`VACT_Date`+`VACT_Time`.
- 무통장: `{"depositor": 입금자명, "account": 매장 계좌 안내문}`.

**주문 상태**에 `paid`(결제 완료)가 들어간다.

| 키 | 이름 | 다음 상태 |
|---|---|---|
| `pending` | 주문 접수(결제 대기) | `paid`, `cancelled` |
| `paid` | 결제 완료 | `confirmed`, `cancelled` |
| `confirmed` | 상품 준비 | `shipped`, `cancelled` |
| `shipped` | 배송 중 | `completed` |
| `completed` | 배송 완료 | — |
| `cancelled` | 주문 취소 | — |

- 고객은 `pending`에서만 스스로 취소한다(지금과 같다). `paid` 이후 취소는 관리자가 하며,
  결제사 결제는 환불이 먼저 성공해야 `cancelled`로 넘어간다. 무통장은 환불을 밖에서 하므로
  환불 금액·방법을 적는 메모만 받고 넘어간다.
- `cancelled`는 배송 전 취소다. 배송 뒤의 반품은 다른 상태로 남겨 둔다(§9).
- 상태 표(`Orders::STATUSES`·`NEXT`)는 상수라 상태를 더할 때 표만 늘린다.
- 재고는 지금처럼 주문 접수 때 차감하고, 취소 때 돌려놓는다. 판매량(`sold_qty`)은 배송 완료 때
  더한다(지금과 같다).

## 4. 결제 흐름

### 주문서
결제 수단 라디오가 생긴다. 켜진 수단만 보인다: 카드·간편결제·계좌이체·가상계좌는 결제 설정에서
그 환경의 이니시스가 허용돼 있을 때, 무통장은 쇼핑몰 설정에 계좌 안내가 있을 때. 아무 수단도
켜져 있지 않으면 주문서는 지금처럼 "판매자가 결제를 안내한다"는 접수 전용으로 동작한다(하위
호환). 주문은 지금과 같은 `Orders::place()`로 접수되며 여기서 `payment_method`·`payment_id`·
`pay_by`를 정한다.

### 결제사 수단(카드·간편결제·계좌이체·가상계좌)
1. 접수 뒤 `/shop/pay?number=…`로 보낸다. 이 페이지는 주문 주인(회원 또는 비회원 주문 키)만
   열 수 있고, `Gateway::checkout()`이 돌려준 정의로 결제창을 띄운다. PC는 `INIStdPay.js`, 모바일은
   폼 전송. 기기 판별은 결제 계층의 `device` 인자를 그대로 쓴다.
2. `returnUrl`(닫힘)은 `/shop/order?number=…`, `callbackUrl`은 `/shop/pay/callback`이다. 콜백은
   세션이 없는 외부 요청이므로 `Extension\ExternalRequests` 경로로 받고, 이니톡과 같은 방식으로
   `CallbackToken`(주문·결제사·설정 판의 HMAC)을 `state`로 실어 검증한다.
3. 콜백은 `Gateway::complete()`를 부른다. 승인되면 카드·간편결제·계좌이체는 `paid`로 전이하고
   `paid_at`·`paid_amount`·`payment_detail`을 적는다. 가상계좌는 승인 응답이 "계좌 발급"이므로
   `payment_detail`에 계좌와 기한을 적고 `pending`에 머문다.
4. 승인 실패·검증 실패·통신 실패는 원장이 `pending`(승인 중)으로 잠가 재승인을 막는다(결제 계층의
   규칙). 주문 화면은 "결제 결과를 확인하는 중"을 보이고, 관리자 결제 조회가 `fetch()`로 풀어
   준다.
5. 결제 페이지를 닫고 돌아오면 주문 화면이 "아직 결제되지 않음"과 **다시 결제** 링크를 보인다.
   원장이 `ready`인 동안은 결제창을 다시 열 수 있다.

### 무통장입금
접수 직후 주문 화면이 매장 계좌 안내, 입금자명, 입금 기한을 보인다. 관리자가 주문 상세에서
**입금 확인**을 누르면 `paid`가 된다. 입금자명은 주문서에서 받는다(비우면 주문자명).

### 결제 기한과 만료
`pay_by`는 수단별 설정값으로 정한다. 기본은 카드·간편결제·계좌이체 1시간, 가상계좌와 무통장
3일(가상계좌는 결제창에 같은 기한을 `acceptmethod`의 `vbank(YYYYMMDD)`로 넘긴다). 기한이 지난
`pending` 주문은 `cancelled`로 넘기고 재고를 돌려놓는다. cron 없이, 관리자 주문 목록·상세와
주문서(주문 접수 직전)를 열 때 만료분을 처리한다. 결제 원장이 `pending`(승인 중)인 주문은 만료로
취소하지 않는다 — 돈이 움직였을 수 있으므로 관리자의 조회가 먼저다.

## 5. 관리자

- **주문 상세의 결제 구역**: 수단·상태·결제 시각·금액·환불 누계, 가상계좌 정보, 무통장 입금자명.
  단추: 입금 확인(무통장, `pending`→`paid`), 결제 조회(`fetch()`로 원장과 주문을 맞춘다), 환불(금액·
  사유, 계좌이체·가상계좌는 환불계좌 은행·계좌번호·예금주 — 3.2). 환불은 결제 계층의 요청 키로
  중복 제출을 막고, 부분 환불을 허용하며, 전액 환불이면 `cancelled`로 넘길지 묻는다.
- **주문 목록**: 상태 필터에 `paid`가 들어가고, 결제 수단 열이 생긴다.
- **쇼핑몰 설정**: 무통장 계좌 안내문(은행·계좌·예금주), 수단별 결제 기한, 주문서에 보일 수단 순서.
  `order_notice`의 기본 문구("이 화면에서는 결제되지 않습니다")는 결제 수단이 켜져 있으면 쓰지
  않는다.
- 결제사 자격증명과 환경 허용은 기존 결제 설정 화면(`/admin/settings/payment`)이다.

## 6. 게이트웨이 확장 (3.2·3.3)

`checkout()`의 `order`에 `method`가 들어오고, 이니시스 구현이 수단별로 결제창 값과 검증을 바꾼다.

| 수단 | PC 결제창 | 승인 응답 검증 |
|---|---|---|
| 카드 | `gopaymethod=Card` (지금과 같다) | `payMethod` ∈ {Card, VCard} |
| 간편결제 | `gopaymethod=onlykakaopay` · `onlynaverpay` · `onlyssp`(삼성페이) · `onlypayco` · `onlytosspay` · `onlylpay` · `onlyssgcard`, `acceptmethod`에 `cardonly` | 카드와 같다. 어느 간편결제였는지는 표시용으로만 남긴다 |
| 계좌이체 | `gopaymethod=DirectBank` | `payMethod=DirectBank`, `ACCT_BankCode`·`ACCT_BankName`·`ACCT_Name` |
| 가상계좌 | `gopaymethod=VBank`, `acceptmethod`에 `vbank(YYYYMMDD)`(입금 기한), `va_receipt`(현금영수증 UI) | `payMethod=VBank`, `VACT_Num`·`VACT_BankCode`·`vactBankName`·`VACT_Name`·`VACT_Date`·`VACT_Time` |

- 주문서가 보이는 간편결제 항목은 쇼핑몰 설정에서 고른다(계약된 것만). 각 항목이 위 값 하나에
  대응한다.
- `query()`는 `paymethod`를 수단에 맞게 검사한다. 가상계좌는 입금 전 조회가 "발급됨"일 수 있으므로
  `PAID`와 구분한 상태를 돌려준다.
- `refund()`는 계좌이체·가상계좌에서 환불계좌(은행코드·계좌번호·예금주)를 요구한다. 이니시스 환불
  API의 해당 인자 이름은 구현 때 매뉴얼로 확정한다.
- **모바일 결제창**의 수단 값(`P_INI_PAYMENT`: `CARD`·`BANK`·`VBANK`와 간편결제 지정 방식, 가상계좌
  기한 옵션, 응답의 `P_VACT_*` 필드)은 매뉴얼 페이지가 정적으로 열리지 않아 이 설계에서 확정하지
  못했다. 3.2·3.3 계획의 첫 작업이 이 값의 확정이다.

### 가상계좌 입금통보 (3.3)
이니시스가 부르는 수신 경로를 영카트 모듈이 `/shop/pay/vbank-notify`로 가진다(`ExternalRequests`).

- 요청: `POST`, `application/x-www-form-urlencoded; charset=euc-kr`. 필드 `no_tid`(입금 거래번호),
  `no_oid`(상점 주문번호 = 결제 원장 키), `id_merchant`, `dt_trans`·`tm_trans`, `cd_bank`, `cd_deal`,
  `no_vacct`, `amt_input`, `type_msg`(`0200` 정상), `nm_inputbank`, `nm_input`, `dt_inputstd`,
  `flg_close`.
- 검증: 송신 IP가 `203.238.37.15`·`183.109.71.153`(결제 설정에 목록으로 두고 기본값으로 넣는다),
  `id_merchant`가 상점 아이디, `no_oid`로 찾은 주문이 가상계좌 주문이고 `no_vacct`가 발급 계좌,
  `amt_input`이 주문 총액과 같다. 금액이 다르면 받지 않고(응답을 `OK`로 하지 않는다) 관리자에게
  보이도록 주문 이력에 남긴다.
- 처리: `pending`→`paid`, `paid_at`은 `dt_trans`+`tm_trans`(KST). 같은 `no_tid`가 다시 오면 아무것도
  바꾸지 않고 `OK`만 낸다(이니시스는 `OK`를 못 받으면 24시간 동안 약 10분마다 다시 보낸다).
- 응답: 본문 `OK` 두 글자.
- 모바일 가상계좌의 통보 형식이 PC와 다를 수 있다(이니시스 안내). 3.3 계획에서 확정한다.
- 기한이 지나 만료된 뒤 뒤늦게 입금통보가 오면: 주문은 이미 `cancelled`이고 재고가 돌아가 있으므로
  `paid`로 되돌리지 않는다. 주문 이력에 "만료 뒤 입금"으로 남기고 관리자에게 환불이 필요함을
  보인다. 이때도 응답은 `OK`다(재전송을 막기 위해).

## 7. 오류와 경계

- 결제 환경이 바뀌면(test↔live, 자격증명 판 변경) 그 전에 열린 주문은 자기 판으로 승인·조회·환불한다.
  결제 계층이 `config_revision`으로 이미 보장한다.
- 같은 주문의 결제창을 두 번 열어 두 번 승인되는 일은 원장의 `ready→pending→confirmed` 잠금이 막는다.
- 콜백이 오지 않은 채 결제창이 닫히면 주문은 `pending`에 남고 기한 뒤 만료된다. 돈이 빠져나갔는데
  콜백이 유실된 경우는 원장이 `ready`라 만료 대상이 되는데, 이니시스가 승인 전에는 돈을 확정하지
  않으므로(인증만 된 상태) 안전하다. 승인 요청 도중 실패한 경우는 `pending` 잠금 + 망취소로
  결제 계층이 처리한다.
- 주문 총액과 승인 금액이 다르면 승인하지 않는다(결제 계층의 규칙). 주문서의 견적 지문이 접수
  시점에 잠기므로 결제창의 금액은 주문 총액과 같다.
- 결제 대기를 벗어난 주문(취소·만료)에 결제사 승인이 도착하면 결제 완료로 적지 않고, 주문의
  결제 칸에 거래번호와 `needs_review` 를, 이력에 환불이 필요하다는 줄을 남겨 관리자 화면이
  알린다. 자동 환불은 하지 않는다 — 돈을 되돌리는 일은 사람이 결제사 기록을 보고 결정한다.
  승인이 오가는 중(원장 `pending`·`confirmed`)에는 고객 취소를 막고, 결제창을 여는 순간 기한이
  15분 미만이면 기한을 밀어 애초에 이런 주문이 잘 생기지 않게 한다.
- 환불을 요청하고 결제사 응답을 받지 못하면 결제 계층이 그 요청을 보류로 잠그고 재전송하지
  않는다. 관리자 주문 화면의 **환불 대조**(조회로 확인한 취소 거래번호에 연결)와 **결제사
  미처리로 정리**(2시간 경과 요청 종료)로 풀며, 환불 요청 키가 주문의 환불 누계를 한 번만
  세게 한다.

## 8. 테스트

- 결제 계층: 이식한 `tests/Payment` 그대로. 수단별 확장은 `FakeTransport`로 결제창 값·응답 검증·
  환불 인자를 단위 테스트한다.
- 영카트: 주문서 → 결제 페이지 → 콜백 → `paid` (카드·계좌이체), 가상계좌 발급 → 통보 → `paid`,
  무통장 → 입금 확인, 기한 만료 취소와 재고 복원, 만료 뒤 통보, 원장 `pending` 주문은 만료 제외,
  `paid` 이후 고객 취소 거절, 환불 뒤 취소, 결제 수단이 하나도 없을 때의 접수 전용 동작.
- 통보 수신: IP·상점·금액 검증, 중복 통보의 멱등성, 응답 본문 `OK`.
- 관리자 화면: 결제 구역 렌더링, 입금 확인, 결제 조회, 환불 폼(환불계좌 필수 조건).

## 9. 반품·교환을 위해 남겨 두는 것

이 단계는 반품·교환을 만들지 않지만 다음을 보장한다. 금액을 지정한 부분 환불과 사유·환불계좌
(§5·§6), 주문의 환불 누계와 원장의 환불 기록(§3), 상수 표로 된 상태 흐름과 배송 전 취소로 좁힌
`cancelled`(§3). 영카트5처럼 품목 단위 반품·교환을 하려면 주문 품목에 상태 칸이 필요한데, 그것은
반품·교환 단계에서 모듈 스키마를 올려 더한다.

## 10. 문서

`docs/payments.md`(결제 설정 화면, 이니시스 준비물: 상점 아이디·서명키·API 키·IP 등록, 입금통보
URL 등록), `docs/shop.md`(결제 수단, 주문 상태 `paid`, 관리자 결제 구역, 만료 규칙).
