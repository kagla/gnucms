# 공통 결제 계층 (설정 → 결제)

GNUCMS가 PG와 직접 연동한다. 포트원 계정이나 API를 거치지 않는다. 쇼핑몰은 공통
`Gateway` 계약으로 결제창·승인·조회·전액/부분 환불·미확정 환불 대조를 호출하며,
각 PG의 전문과 인증은 해당 어댑터가 처리한다.

상품별 면세 여부는 주문 시점에 주문 상품으로 복사하고, 결제 총액을 과세금액·공급가액·부가세·
면세금액으로 나눠 함께 저장한다. 선불 배송비는 과세금액에 포함한다. 쇼핑몰과 결제 계층은
`TaxAmounts`의 표준 명세만 사용하며 이니시스·KCP·토스·나이스의 서로 다른 요청 필드명은
`TaxAdapter` 한 곳에서 변환한다. 면세금액이 있는 결제는 PG 계약도 면세 또는 복합과세 상점으로
설정되어 있어야 한다.

### 결제수단 정책

운영을 단순하게 유지하기 위해 새 PG 주문에는 **일반 신용카드 결제만** 제공한다. KG이니시스,
NHN KCP 기존 방식(TCP/IP), 토스페이먼츠, 나이스페이먼츠는 카드 결제만 주문서에 노출한다.
NHN KCP REST API 방식은 당분간 새 결제사 선택 목록과 신규 주문에서 제외한다.
실시간 계좌이체·가상계좌·휴대폰 결제와 에스크로 결제는 새 주문에서 사용할 수 없다.
토스 카드 결제창에서 간편결제사를 선택해도 결제액 전부가 카드로 청구되고
계좌·포인트 사용액이 0원인 경우에는 카드 결제로 확인한다.
PG를 거치지 않는 상점 무통장입금은 별도 수단으로 계속 제공할 수 있고, 관리자가 입금 여부를 확인한다.

이 제품 결정의 배경은 에스크로 거래에 필요한 배송등록·구매결정 절차와 택배사 연동을 운영하지
않고, 카드 결제로 범위를 한정하려는 것이다. 전자상거래법은 소비자가 결제대금예치 등을 선택한
선지급식 거래의 제공 의무와 신용카드 거래 예외를 규정한다([전자상거래법 제24조](https://www.law.go.kr/LSW/lsLawLinkInfo.do?chrClsCd=010202&lsId=009318&lsJoLnkSeq=1000527507)).
상점 무통장입금도 현금 선결제이므로 이 PG 수단 제한만으로 별도 법적 의무가 면제되는 것은 아니다.
운영자는 판매 방식과 계약에 맞는 구매안전서비스 의무·고지를 별도로 확인해야 한다.

### 관리자 사용 순서

**쇼핑몰 → 설정 → 결제**(`/admin/shop/settings#settings-payment`)에서 새 주문에 사용할 PG와
운영·테스트 환경을 선택한다. 결제수단은 신용카드만 켜거나 끌 수 있고, 무통장입금은 별도로
켜고 계좌·예금주를 등록한다. 이니시스 테스트 환경은 공용 테스트 정보가 자동 적용된다.
NHN KCP REST API 방식은 신규 선택을 중단했다. 기존 KCP REST API 주문의 조회·환불은 유지한다.
토스페이먼츠 테스트 환경은 [공식 SDK v1 PHP 샘플의 클라이언트 키](https://github.com/tosspayments/tosspayments-sample-v1/blob/main/payment/payment-window/php/index.html)와
[서버 시크릿 키](https://github.com/tosspayments/tosspayments-sample-v1/blob/main/payment/payment-window/php/success.php)를 설정 저장 시 자동 적용한다.
나이스페이먼츠 테스트 환경은 [공식 서버 승인·Basic 인증 샌드박스 샘플](https://github.com/nicepayments/nicepay-manual)의 공개 클라이언트 키와 서버 시크릿 키를 설정 저장 시 자동 적용한다.
영카트5의 `nicepay00m` MID와 상점키는 구형 결제 방식의 값이어서 이 서버 승인 API에 사용할 수 없다.
토스와 나이스페이먼츠 운영 환경에는 상점에 발급된 클라이언트 키와 서버 시크릿 키를 입력한다. NHN KCP 기존 방식
(TCP/IP)은 KCP 브라우저 결제창과 서버 `pp_cli` 승인을 연결한다. KCP에서 발급받은
실행 파일과 `pub.key`가 서버에 설치된 경우에만 주문서에 신용카드로 표시된다.

설정 화면의 결제사 키는 암호화해 저장한다. 결제사 또는 환경을 바꿔도 기존 주문은 결제 당시의
PG·환경·설정 판을 사용한다. 기존 주문의 콜백·조회·환불을 위해 과거 온라인 수단 처리 코드는
남아 있지만, 신규 주문 검증과 PG 결제창 시작은 신용카드만 허용한다.

PG 거절과 승인 확인 보류 기록은 쇼핑몰 관리자 → 주문 → 결제 오류에서 확인한다. 목록은 결제
참조번호·PG 응답 코드로 검색하고 20건씩 페이지를 나눈다. 주문자 정보와 PG 결과 메시지는 결제
원장 안에서 암호화한다. 설정이 없는 환경에는 온라인 결제 수단을 표시하지 않는다. 백업·복원 중에는
결제 실행과 설정 변경을 잠근다.

## 이니시스 준비물

공용 테스트 상점 MID는 `INIpayTest`다. 테스트 환경을 선택해 쇼핑몰 설정을 저장하면
[KG이니시스 PayPro 안내](https://manual.inicis.com/inipaypro/)와
[INIAPI 샘플](https://manual.inicis.com/download/TLS12_test_manual.pdf)의 공개 테스트 값을
서버에서 자동으로 저장한다. 테스트 인증키는 관리자 화면에 표시하거나 입력받지 않는다.
테스트 API의 기본 `clientIp`는 `127.0.0.1`이며 필요하면 서버별
`config/config.php`의 `payment.inicis.client_ip`로 요청 주소를 지정한다.
운영 환경에는 해당 상점에 발급된 아래 값을 입력한다.

| 항목 | 설명 |
|---|---|
| 상점 아이디 (MID) | 영문·숫자 10자 |
| 웹표준 결제 SignKey | 기존 웹표준 결제 건의 승인 호환용. 신규 PayPro 설정만 할 때는 비워 둔다 |
| 모바일 금액 위변조 Hash Key | PayPro 결제창의 금액 위변조 검증 |
| INIAPI Key | 승인 조회·환불 API |
| 기본 요청 서버 IPv4 주소 | 별도 서버 설정이 없을 때 INIAPI 요청의 `clientIp`에 넣는 주소 |

INIAPI 요청 한 건에는 IPv4 주소 하나만 보낸다. 여러 서버가 같은 DB를 사용하면 각 서버의
`config/config.php`에 `payment.inicis.client_ip`를 해당 서버의 요청 IPv4로 지정한다.
이 로컬 설정이 관리자 기본값보다 우선하며, 비어 있으면 기본값을 사용한다. 프록시나 NAT를
사용한다면 서버가 실제로 어떤 주소로 요청하는지 확인해 설정한다. 방문자 IP나
`X-Forwarded-For` 헤더를 이 값으로 사용하지 않는다.

사이트 주소(`app.url`)는 공개 HTTPS 주소여야 한다. 인증키는 암호화해 저장하고 화면에
다시 보여주지 않는다. 설정을 저장할 때마다 새 판(revision)을 보존한다. 이니시스는 같은
상점의 인증키와 서버 IP가 바뀌면 과거 주문에도 최신 값을 사용하고, 상점 ID가 바뀌면
과거 주문의 상점 정보를 보존한다. 다른 PG의 키 교체 규칙은 그 PG의 `credentials()`가 정한다.
신규 카드 결제는 PC·모바일 모두 이니시스 PayPro의 `INIPayPro_v2.js`로 요청한다.
주문서의 구매자 이메일과 연락처는 `P_RESERVED` JSON의 `email`·`phonenum` 항목으로 전달한다.
PayPro JS의 결과 콜백을 사용해 인증 결과를 쇼핑몰의 서명된 콜백 주소로 POST한다.
`P_CLOSE_URL`은 성공 후 이동을 덮어쓸 수 있으므로 새 PayPro 요청에는 넣지 않는다.
인증 실패(`P_STATUS`가 `00`이 아님)는 승인 API를 호출하지 않고 오류 코드만 표시한다.
실패한 임시 주문서는 `declined`로 기록하며, 다시 시도할 때는 새 PG 주문번호를 발급한다.
승인 요청이나 결과 확인에서 실패한 경우에는 결제 여부가 불확실하므로 재결제를 권하지 않는다.
인증 결과에 포함된 IDC 코드로 스테이징·운영 승인 서버를 선택한다. 이전 웹표준 결제창에서
진행 중이던 건은 기존 콜백 형식도 처리한다.
PayPro의 IDC는 주문의 테스트·운영 설정과 별도로 PG가 반환한 `fc`·`ks`·`stg` 중 하나를
사용한다. 반환값을 그대로 호스트명에 넣지 않고 이 세 값만 허용한다. 승인 요청 전후로 실패하면
사용자에게 애플리케이션 오류 코드와 결제 참조번호를 표시해 원장·로그를 대조할 수 있게 한다.
결제 완료 주문에는 거래조회가 제공한 카드사명, 마스킹 카드 끝번호, 일시불·할부 개월,
무이자 여부와 승인번호를 저장해 주문 상세에 표시한다. 원 카드번호는 저장하지 않는다.
개인·법인 구분은 일반 카드 거래조회 응답에 포함되지 않으므로 임의로 표시하지 않는다.
주문 상세의 **카드 매출전표 보기**는 이니시스 영수증 조회로 연결하며, 영수증 페이지에서
구매자명과 결제 금액을 확인해야 한다.

PG 실계정 승인·조회·환불은 별도 검증이 필요하다. 자동 테스트는 외부 요청을 모의하며
실제 카드 결제나 송금을 실행하지 않는다.

## 실행 구조와 파일

```text
쇼핑몰 주문·관리자
  → src/Shop/Commerce/Payments.php
  → App::paymentGateway(주문에 저장된 payment_provider)
  → ProviderRegistry → PG별 Gateway
  → PG API
```

| 위치 | 책임 |
|---|---|
| `src/Payment/Provider.php`, `ProviderRegistry.php` | PG 등록, 설정 항목·검증, 지원 수단·부분 환불 여부, 결제창 템플릿, 게이트웨이 생성 |
| `src/Payment/Gateway.php` | 승인·조회·환불·미확정 처리의 서버 계약 |
| `src/Payment/DirectGateway.php` | 승인/환불 중복 전송 방지, 원장 상태, 환불 대조 공통 구현 |
| `src/Payment/TaxAmounts.php`, `TaxAdapter.php` | 과세·면세 금액 계산·검증과 PG별 결제·부분취소 필드 변환 |
| `src/Payment/Settings.php`, `Journal.php` | PG별 암호화 설정 판·원장 |
| `src/Payment/InicisProvider.php`, `ProviderConfig.php` | 이니시스 설정 항목·검증·키 교체 정책 |
| `src/Payment/InicisGateway.php`, `KcpGateway.php`, `StreamTransport.php` | 이니시스·KCP 전문·통신·허용 API 주소·응답 검증 |
| `templates/default/admin/payment_settings.php` | 모든 등록 PG가 사용하는 설정 화면 |
| `src/Shop/Commerce/CheckoutIntents.php` | PG 승인 전 암호화 임시 주문서와 승인 후 주문 확정·접수 실패 시 취소 |
| `templates/default/shop/checkout.php` | 새 PG 주문의 결제창을 주문서 안에 표시 |
| `templates/default/shop/pay.php` | 이전 결제 대기 주문의 결제 재시도 화면 |
| `templates/default/payment/inicis.php`, `inicis_scripts.php` | 이니시스 PC/모바일 결제창 조각과 실행 스크립트 |
| `templates/default/payment/kcp.php`, `kcp_scripts.php`, `www/themes/default/kcp.js` | KCP PC/모바일 결제창 조각과 콜백 실행 스크립트 |
| `src/Payment/KcpLegacyGateway.php`, `templates/default/payment/kcp_legacy.php`, `kcp_legacy_scripts.php` | KCP 기존 표준결제 인증·TCP/IP 승인·전체 취소 |

결제창 조각도 기존 테마 경로 탐색을 사용한다. `templates/<테마>/payment/inicis.php`,
`inicis_scripts.php`, `kcp.php`, `kcp_scripts.php`, `kcp_legacy.php`, `kcp_legacy_scripts.php`로 재정의할 수 있다. 쇼핑몰 전용 재정의는
`templates/<테마>/shop/payment/`에 둔다. 기존 `shop/pay.php` 전체 재정의도 유지된다.

신규 주문에서 PG가 제공하는 수단은 신용카드뿐이다. 계좌이체·가상계좌·휴대폰 결제와 에스크로는
상점 설정 화면에 나오지 않고, PG 어댑터의 새 결제창 요청도 거절한다. 기존 주문의 상태 조회,
콜백·입금 통보와 환불 처리는 과거 거래를 보존하기 위해 남겨 둔다.

## NHN KCP 기존 방식 (TCP/IP)

쇼핑몰 설정 → 결제에서 **NHN KCP 기존 방식 (TCP/IP)**을 선택한다. 운영 환경은 일반 카드 결제용으로
KCP에서 받은 `site_cd`와 `site_key` 한 쌍을 입력한다. 테스트 환경은 사이트 코드 `T0000`과
공용 테스트 사이트 키가 하단의 설정 저장 시 자동 적용된다. 사이트 설정 → 결제에서도 자격정보를
관리할 수 있으며, 설정은 REST API 방식과 분리해 암호화 저장한다.

배포 ZIP에는 Linux x86-64용 `pp_cli_x64`, Windows용 `pp_cli_exe.exe`, 공통 `pub.key`를
[그누보드 공식 KCP 플러그인의 공개 파일](https://github.com/gnuboard/g7-plugin-sirsoft-pay_nhnkcp/tree/0e02e7dae01523f227688ffefcdda04d9b1d6421/bin)에서 버전과 해시를 고정해 포함한다.
소스만 내려받아 설치할 때는 같은 파일을 아래 경로에 직접 배치한다. Linux 32비트 PHP에서는
별도의 `pp_cli`가 필요하다.

```text
storage/payment/kcp_legacy/bin/pp_cli_x64
storage/payment/kcp_legacy/bin/pp_cli_exe.exe
storage/payment/kcp_legacy/bin/pub.key
```

Linux에서는 PHP 프로세스가 `pp_cli_x64`를 실행할 수 있고 `exec()`가 활성화되어 있어야 한다.
Windows에서는 PHP 프로세스가 `pp_cli_exe.exe`를 실행할 수 있고 `proc_open()`이 활성화되어 있어야 한다.
KCP 표준결제 웹 스크립트가 브라우저 인증을 수행한 뒤 서명된 쇼핑몰 콜백을 보내며, 서버는
`pp_cli`로 TCP/IP 승인 또는 전체 취소를 요청한다. 결제 완료 후 카드사명·마스킹 카드 끝번호·
할부 개월·무이자 여부·승인번호를 저장한다. 카드 번호 원문은 저장하지 않는다. 부분 환불은
지원하지 않는다.

기존 TCP/IP 연동은 KCP pp_cli 원장 조회 API가 없어 GNUCMS가 직접 승인·취소한 거래의 암호화
원장으로만 주문 결제 상태를 표시한다. KCP 상점관리자에서 수동 취소한 거래는 자동으로 동기화되지
않으므로, 이 방식은 주문 관리 화면에서 환불을 실행해야 한다. KCP는 TCP/IP에서 HTTPS로의 전환과
REST API 사용을 안내하고 있다([KCP REST API 전환 가이드](https://developer.kcp.co.kr/guide/rest-api-guide)).
GNUCMS에서는 이 REST API 방식의 신규 선택을 당분간 중단한다.

## NHN KCP REST API 방식 설정

현재 **NHN KCP REST API 방식**은 쇼핑몰 설정의 신규 결제사 선택 목록에서 숨긴다. 기존 연동 정보와 거래 처리 코드는 유지한다.
테스트 환경은 사이트 코드 `T0000`과
KCP 개발자센터가 공개한 `config/payment/kcp/test/splCert.pem`·`splPrikeyPKCS8.pem`과
`settings.php`의 공개 테스트용 개인키 비밀번호를 설정 저장 시 자동 적용한다. PEM을 관리 화면에 입력하지 않는다.
운영 환경은 KCP가 발급한 5자리 사이트 코드와 서비스 인증서 PEM, 개인키 PEM, 발급 시 설정한
개인키 비밀번호를 입력한다. 비밀번호는 REST 요청 본문에 보내는 값이 아니라 서버에서 암호화된
개인키를 열어 서명할 때 사용한다. 사이트 설정 → 결제에서도 같은 설정을 관리할 수 있으며,
저장된 인증서·개인키·비밀번호는 암호화한다. [KCP 인증서 발급 안내](https://developer.kcp.co.kr/reference/key),
[KCP 테스트 파일·서명 안내](https://developer.kcp.co.kr/reference/signdata).

PC는 KCP 표준결제 허브를, 모바일은 [거래등록 API](https://developer.kcp.co.kr/reference/regist)가 반환한 PayUrl과 인증 키를 사용한다.
인증 후에는 서버에서 [결제승인 API](https://developer.kcp.co.kr/reference/pay-approve)를 호출하고, 거래조회 결과로 주문번호·거래번호·수단·금액·잔액을 검증한다.
[공식 거래조회 Guide](https://developer.kcp.co.kr/reference/search)는 조회 서명 문자열로 `site_cd^tno^pay_type`을 안내하지만 API Reference의 서명 파라미터 설명은
`site_cd^tno^mod_type`을 적고 있다. 구현은 Guide에 요청 예시가 있는 `pay_type` 규칙을 사용한다. 실제 KCP 테스트 계정으로
조회·취소를 검증해야 한다. 거래조회 응답에는 취소별 이력이 없어 원장에 저장한 GNUCMS 취소와 PG 잔액 합계가
일치할 때만 조회 결과를 유효하게 처리한다. 전액·부분 취소는 [거래취소 API](https://developer.kcp.co.kr/reference/cancel)를 호출한다.

## 새 PG 추가

신뢰하는 서버 초기화 코드에서 다음처럼 등록한다. 사용자 입력으로 클래스를 생성하거나
템플릿 파일 경로를 지정하지 않는다.

```php
$app->paymentProviders()->register(new YourProvider());
```

`YourProvider`는 `Provider`를 구현한다. 고유 ID(영문 소문자로 시작, 영문 소문자·숫자·밑줄,
최대 32자), 표시명, 설정 항목과 검증, 가맹점별 키 교체 정책, 지원 수단, 부분 환불 여부,
결제창 조각 이름을 선언하고 `gateway(Settings $settings)`에서 PG 게이트웨이를 반환한다.
중복 ID 등록은 거절한다. 쇼핑몰의 새 온라인 주문은 신용카드만 지원하며, 제공자 수단 목록과 카드 사용 설정이
모두 허용될 때 주문서에 표시한다. 새 PG도 다른 온라인 결제수단을 주문서에 추가할 수 없다.
새 PG의 복합과세 요청 필드는 `TaxAdapter`에 추가하며 쇼핑몰의 세금 계산을 게이트웨이에 다시
구현하지 않는다.

게이트웨이는 `DirectGateway`를 상속하고 다음 PG별 부분을 구현한다.

- `checkout()`: `prepare()`로 주문·설정·리턴 주소를 확인한 뒤 결제창 데이터를 만든다.
- `validateCallback()`: 인증 결과와 주문 연결을 확인한다.
- `approve()`, `query()`, `refund()`: PG 규격으로 요청하고 공통 결과로 변환한다.
- `query()`는 `status`, `valid`, `transaction_id`, `paid_at`, `cancelled`, 선택적
  `cancellations`를 돌려준다. `valid`는 PG에서 조회한 상점·주문·금액·통화 등이 모두
  주문과 일치할 때만 참이다. 취소 항목은 `id`, 정수 원 단위 `amount`, Unix 초 `at`이다.
- `refund()`는 확정된 취소의 `id`, `amount`, `at`를 반환한다. 응답이 불확실하면 예외를
  내서 원장의 보류 상태를 유지한다. 승인·환불 타임아웃을 성공이나 미처리로 추측하지 않는다.

현재 `StreamTransport`는 등록된 이니시스·KCP 주소만 허용한다. 새 PG는 `Transport` 구현을 주입해
그 PG의 공식 HTTPS 주소·응답 형식·인증을 검증한다. 임의 콜백 URL로 인증키를 보내거나,
리다이렉트를 따라가거나, TLS 검증을 끄지 않는다. 필요하면 PG별 콜백/웹훅 인증과 경로도
추가한다. 브라우저 인증 콜백과 비동기 입금 통보는 같은 계약이 아니다. 이니시스 가상계좌 통보는
서명된 주문 콜백 URL로 받고, 본문을 결제 완료로 신뢰하지 않고 이니시스 거래 조회 결과를 확인한다.

PG별 프로토콜은 각각 구현해야 한다. 이 구조는 서로 다른 PG를 설정 이름만 바꿔 연결하는
방식이 아니라, 반복되는 주문·설정·원장·관리 화면을 공유하는 방식이다.
`tests/Payment/TestProvider.php`는 실제 연동에 사용하지 않는 모의 어댑터 예다.

## 데이터와 업그레이드

코어 DB 스키마 45판은 주문별 과세금액·공급가액·부가세·면세금액과 주문 상품별 면세 여부를
추가한다. 이전 주문은 당시 면세 스냅샷이 없으므로 전체 결제금액을 과세로 이전한다. MySQL의
기존 데이터는 보존한다. SQLite·PostgreSQL은 지원하지 않는다.

- `pay_settings`: `(provider, id)` 기본키, 암호화 `payload`. ID는 환경 또는 설정 판이다.
- `pay_transactions`: `(provider, id)` 기본키, 암호화 `payload`. ID는 결제 원장 키다.
- `yc_orders.payment_provider`: 주문이 생성될 때 선택된 PG. 무통장·접수 전용은 빈 값이다.

기존 `pay_inicis_settings`, `pay_inicis_transactions`의 암호문은 `provider=inicis`로
복사한다. 과거 온라인 결제 주문에도 이니시스를 기록한다. 원래 설정 판·결제 ID·콜백 HMAC·
미확정 승인/환불 기록을 유지한다. 재실행해도 공통 표의 더 최신 기록을 덮어쓰지 않는다.
이전 두 표는 보관·백업만 하며 실행에서는 읽지 않는다. 새 설치에도 빈 보관 표를 만들어
동일한 백업/복원 표 목록을 유지한다.

`App::paymentSettings()`의 기본 PG와 기존 `inicisGateway()`/`setInicisGateway()` 호출은
호환용으로 남아 있다. 새 코드는 PG ID를 전달하는 `paymentSettings($id)`와
`paymentGateway($id)`를 사용한다.
