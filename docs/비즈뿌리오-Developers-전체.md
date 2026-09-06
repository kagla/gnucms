# 비즈뿌리오 Developers — 전체 문서

원본: [https://bizppurio.github.io/](https://bizppurio.github.io/)  \
저장일: 2026-09-06

이 파일은 빠르게 전체를 검색할 때 쓰는 합본입니다. 주제별로 읽거나 수정할 때는 같은 폴더의 개별 Markdown 파일을 사용하세요.


---

# 시작하기

> 원본: [https://bizppurio.github.io/start/](https://bizppurio.github.io/start/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

GET STARTED · 시작하기

# 비즈뿌리오 첫 발송까지

문자 · 카카오 · RCS · 네이버 톡톡 · FAX/PHONE 을 하나의 인터페이스로 보내는 통합 메시징 플랫폼입니다.  
**비교 → 선택 → 사전 준비 → Quickstart** 순서로, 처음 연동에 필요한 길을 안내합니다.

NOTE

서비스·채널이 바뀌면 사전 준비 항목과 구현 방식도 함께 달라집니다.  
먼저 **무엇을 보낼지** 정한 뒤 아래 길찾기를 따라가는 편이 가장 빠릅니다.

## 빠른 시작 — 3단계 길찾기

아래에서 채널과 연동 방식을 고르면, 현재 상황에 맞는 Quickstart 와 레퍼런스로 바로 이동할 수 있습니다.

1무엇을 보내시나요?

## 연동 방식 비교

두 방식 중 환경에 맞는 것을 선택하세요.

| 항목 | 메시지 API | BIZCLIENT |
| --- | --- | --- |
| 인터페이스 | HTTPS REST (POST /v3/message) | DB INSERT (biz\_msg 큐 테이블) |
| 호스팅 | 비즈뿌리오 클라우드 | 고객사 서버 (온프레미스 모듈) |
| 인증 | Bearer 토큰 (24시간 유효) | DB 접근 권한 |
| 결과 수신 | Webhook(URL Push) / Polling | STATUS · CALL\_STATUS 컬럼 |
| 모듈 설치 | 불필요 | 필수 (JDK 1.8+, JDBC) |
| 추천 환경 | 새 프로젝트 · 빠른 시작 · 운영 부담 최소 | 사내망 · 대량 배치 · 레거시 · FAX/PHONE |

## 사전 준비 체크리스트

모든 채널 공통 준비 항목입니다. 연동 방식과 무관하게 먼저 끝내두세요.

1

회원 가입

bizppurio.com 에서 계정을 생성합니다.

2

API 용도 계정 생성

운영용 · 검수용 계정을 각각 발급하는 것을 권장합니다.

3

서비스 사용 승인 요청

전화 1599-9782 또는 이메일 bizppurio@daou.co.kr 로 신청합니다.

4

발신번호 등록

비즈뿌리오 사이트에서 SMS/LMS/MMS/RCS/FAX/PHONE 공통으로 등록합니다.

5

결과 수신 URL 등록 (메시지 API)

Webhook 사용 시. 443·80 외 포트는 방화벽 허용 요청이 필요합니다.

### 채널별 추가 등록

카카오 (AT/BT)

카카오톡 채널 + 발신프로필 + 템플릿 + 브랜드

등록처 · 카카오 비즈니스 + 비즈뿌리오 사이트

RCS

브랜드 + 챗봇 + 메시지베이스 + 발신번호 + 이미지

등록처 · RBC + 비즈뿌리오 사이트

네이버 톡톡

채널 + 파트너 키 + 템플릿

등록처 · 네이버 톡톡 파트너센터 + 비즈뿌리오 사이트


---

# 통합 레퍼런스

> 원본: [https://bizppurio.github.io/docs/](https://bizppurio.github.io/docs/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

GET STARTED · 문서

# 시작하기

## 서비스 소개

**비즈뿌리오** 는 SMS · LMS · MMS · 카카오 알림톡 · 카카오 브랜드메시지 · RCS · 네이버 톡톡 · FAX · PHONE 을 하나의 인터페이스로 발송할 수 있는 통합 메시징 플랫폼입니다. 본 문서는 발송 API 와 자원 관리 API 의 공식 레퍼런스입니다.

### 발송 방식

두 가지 방식 중 환경에 맞는 것을 선택하세요. 두 방식을 혼용해도 됩니다.

| 방식 | 인터페이스 | 호스팅 | 사이드바 위치 |
| --- | --- | --- | --- |
| **메시지 API** | HTTPS REST (`POST /v3/message`), Bearer 24h 토큰 | 비즈뿌리오 클라우드 | **메시지 발송 › 메시지 API** |
| **BIZCLIENT** | DB 큐 테이블 INSERT (`biz_msg`) | 고객사 서버 (온프레미스 모듈) | **메시지 발송 › BIZCLIENT** |

결과 수신: 메시지 API 는 Webhook(URL Push) 또는 Polling, BIZCLIENT 는 `biz_msg.STATUS` / `CALL_STATUS` 컬럼 확인.

### 자원 관리 API

템플릿 · 발신프로필 · 이미지 · 챗봇 · 메시지베이스 등 발송에 사용되는 자원을 프로그래매틱하게 관리합니다. 비즈뿌리오 사이트(GUI)에서 한 번씩 등록하는 대신 자동화하고 싶을 때 사용.

| API | 도메인 | 관리 대상 | 사이드바 위치 |
| --- | --- | --- | --- |
| **KAPI** | `kapi.ppurio.com` | 카카오 비즈메시지 — 알림톡 템플릿 / 비즈메시지 파일 / 발신프로필 / 그룹 / 그룹태그 / 플러그인 콜백 / 브랜드메시지 템플릿·이미지 / 통계 | **관리 API › KAPI** |
| **RAPI** | `rapi.bizppurio.com` | RCS — 브랜드 / 챗봇 / 파일 / 메시지베이스 | **관리 API › RAPI** |
| **NAPI** | `napi.bizppurio.com` | 네이버 톡톡 — 파트너 / 그룹 / 이미지 / 정보성·광고성 템플릿 | **관리 API › NAPI** |

### 연동 규격 (공통)

| 항목 | 값 |
| --- | --- |
| 프로토콜 | HTTPS (443) |
| 인코딩 | UTF-8 |
| 메시지 API Keep-Alive Timeout | 4초 이하 |
| 메시지 API 인증 | `POST /v1/token` (Basic) → `Authorization: Bearer {accesstoken}` (24시간) |
| KAPI 인증 | 요청 본문에 `bizId` + `apiKey` |
| NAPI 인증 | Bearer 토큰 (`refreshToken` → `accessToken`) |

메시지 API 도메인: 운영 `api.bizppurio.com` / 검수 `dev-api.bizppurio.com`.
Rate Limit 초과 시 HTTP 429 + `code: 5002` 응답 — 백오프 후 재시도.

### 다음 단계

처음 연동하시면 사이드바 **시작 › 시작하기** 에서 연동 방식 비교 / 사전 준비 / 5분 Quickstart 를 확인하세요. 구체적인 엔드포인트·페이로드는 **메시지 발송 / 관리 API** 그룹 안의 각 페이지로 들어가면 됩니다.

## 시작하기

**비즈뿌리오 첫 발송까지** — 비교, 선택, 사전 준비, Quickstart

비즈뿌리오를 처음 연동하신다면 아래 순서대로 진행하세요.

1. **연동 방식 비교** — API vs BIZCLIENT 차이 파악
2. **연동 방식 선택** — 트래픽·보안·운영 환경 기준
3. **사전 준비** — 계정·발신번호·채널·브랜드 등록
4. **Quickstart** — cURL 또는 SQL 한 번으로 SMS 발송

---

### 1. 연동 방식 비교 (API vs BIZCLIENT)

| 항목 | 메시지 API | BIZCLIENT |
| --- | --- | --- |
| 인터페이스 | HTTPS REST (`POST /v3/message`) | MySQL / Oracle / MSSQL INSERT |
| 호스팅 | 비즈뿌리오 클라우드 | 고객사 서버 (온프레미스) |
| 인증 | Bearer 토큰 (24시간 유효) | DB 접근 권한 |
| 발송 트리거 | API 호출 즉시 | BIZCLIENT 모듈이 큐 폴링 |
| 결과 수신 | Webhook (URL Push) / Polling | `biz_msg.STATUS` + `CALL_STATUS` |
| 외부 네트워크 의존 | 필요 (HTTPS 443 outbound) | 38300/38400 outbound (모듈 ↔ 비즈뿌리오) |
| 모듈 설치 | 불필요 | 필수 (JDK 1.8+, 4GB RAM, JDBC) |
| 운영 부담 | 낮음 | 모듈 구동·DB 관리·로그 테이블 회전 |
| 미지원 채널 | FAX · PHONE | NTALK |

### 2. 연동 방식 선택 가이드

| 우선 검토 | 이유 |
| --- | --- |
| **메시지 API** | 새 프로젝트, 빠른 시작, 운영 부담 최소화, 멀티테넌트 환경 |
| **BIZCLIENT** | 외부 HTTPS 호출이 제한된 사내망, 대량 배치(DB 동시 처리), 레거시 ERP·CRM 연동, FAX/PHONE 발송 |
| **두 방식 혼용** | 메시지 API 로 트랜잭션성 메시지 + BIZCLIENT 로 야간 대량 배치 |

### 3. 사전 준비 체크리스트

### 공통 (모든 사용자)

| 단계 | 절차 |
| --- | --- |
| 회원 가입 | [www.bizppurio.com](https://www.bizppurio.com) |
| API 용도 계정 생성 | 운영용·검수용 **각각** 발급 권장 |
| 서비스 사용 승인 요청 | 전화 1599-9782 / 이메일 [bizppurio@daou.co.kr](mailto:bizppurio@daou.co.kr) |
| 발신번호 등록 | 비즈뿌리오 사이트 (SMS/LMS/MMS/RCS/FAX/PHONE 공통) |
| (메시지 API) 결과 수신 URL 등록 | Webhook 사용 시. 443·80 외 포트는 방화벽 허용 요청 필요 |

### 채널별 추가 등록

| 채널 | 등록 항목 | 위치 |
| --- | --- | --- |
| **카카오 (AT/BT)** | 카카오톡 채널 + 발신프로필 + (AT) 템플릿 + (BT) 브랜드 | [카카오 비즈니스](https://center-pf.kakao.com) + 비즈뿌리오 사이트 |
| **RCS** | 브랜드 + 챗봇 + 메시지베이스 + 발신번호 + 이미지 | [RBC](https://www.rcsbizcenter.com) + 비즈뿌리오 사이트 |
| **네이버 톡톡** | 채널 + 파트너 키 + 템플릿 | [네이버 톡톡 파트너센터](https://partner.talk.naver.com) + 비즈뿌리오 사이트 |

### 자원을 코드로 관리하고 싶다면

자원 등록을 GUI 대신 API 로 — 좌측 사이드바 **관리 API** 그룹 참고:

- **KAPI** — 카카오 알림톡 템플릿·이미지·발신프로필·브랜드메시지
- **RAPI** — RCS 브랜드·챗봇·파일·메시지베이스
- **NAPI** — 네이버 톡톡 파트너·그룹·이미지·템플릿

### 4. Quickstart

### 4-A. 메시지 API (cURL, 5분)

```
      # 환경 변수
export BP_HOST="dev-api.bizppurio.com"   # 운영: api.bizppurio.com
export BP_ACCOUNT="bizUserId001"
export BP_PASSWORD="mypassword"
export BP_FROM="07000000000"             # 등록된 발신번호
export BP_TO="01012345678"               # 수신 테스트 번호

# 1) 토큰 발급
AUTH=$(printf '%s' "$BP_ACCOUNT:$BP_PASSWORD" | base64)
ACCESS_TOKEN=$(curl -s -X POST "https://$BP_HOST/v1/token" \
  -H "Authorization: Basic $AUTH" \
  -H "Content-type: application/json; charset=utf-8" \
  | python -c "import sys, json; print(json.load(sys.stdin)['accesstoken'])")

# 2) SMS 발송
curl -X POST "https://$BP_HOST/v3/message" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-type: application/json" \
  -d '{
    "account": "'"$BP_ACCOUNT"'",
    "refkey": "test-'"$(date +%s)"'",
    "type": "sms",
    "from": "'"$BP_FROM"'",
    "to": "'"$BP_TO"'",
    "content": { "sms": { "message": "비즈뿌리오 Quickstart 테스트" } }
  }'
```

> ℹ️ 토큰은 **24시간** 유효. 매 요청마다 발급하지 말고 캐싱해서 사용.

### 4-B. BIZCLIENT (SQL, 5분)

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, MSG_BODY
) VALUES (
  0,                                         -- SMS
  CONCAT('TEST', UNIX_TIMESTAMP()),          -- 데이터 ID
  NOW(), NOW(),
  '01012345678',                             -- 수신번호
  '07000000000',                             -- 발신번호 (사전 등록)
  '비즈뿌리오 BIZCLIENT Quickstart 테스트'
);
```

BIZCLIENT 모듈이 큐를 폴링하여 자동 발송. 결과는 `biz_msg.STATUS` / `CALL_STATUS` 로 확인.

---

### 다음 단계

- [`POST /v3/message`](https://bizppurio.github.io/bizapi#post-v3message) — 메시지 API 전체 페이로드 (모든 채널)
- **BIZCLIENT** 사이드바 그룹 — 채널별 INSERT 가이드
- **관리 API** 사이드바 그룹 — 자원 등록·관리

## 선택 가이드

[연동 방식 비교](https://bizppurio.github.io/docs#비교-—-bizapi-vs-bizclient)에서 두 방식의 차이를 확인하셨다면, 아래 의사결정 트리로 빠르게 결정할 수 있습니다.

### 의사결정 트리

1. 사내망에서 외부 HTTPS 호출이 가능한가?아니오 — DMZ · 폐쇄망BIZCLIENT예2. FAX 또는 PHONE 채널이 필요한가?예BIZCLIENT아니오3. 네이버 톡톡(NTALK) 채널이 필요한가?예API — BIZCLIENT 미지원아니오4. 트랜잭션성 발송인가, 대량 배치인가?트랜잭션성 — 사용자 액션 즉시 발송API야간 대량 배치 — 수십만 건 이상BIZCLIENT 권장둘 다혼용 — API + BIZCLIENT계속 검토5. 발송 대상 데이터는 어디서 오는가?DB에 발송 대상이 이미 존재 (SELECT → INSERT)BIZCLIENT외부 이벤트 · 웹훅 트리거API계속 검토6. 모듈 설치 · 운영 부담을 피하고 싶은가?예API — 설치 · 운영 불필요직접 운영 가능BIZCLIENT

### 시나리오별 권장

| 시나리오 | 권장 |
| --- | --- |
| SaaS 신규 개발, 빠른 출시 | **API** |
| 사내망에서 외부 HTTPS 차단 | **BIZCLIENT** |
| ERP / CRM이 이미 DB 통합 환경 | **BIZCLIENT** |
| FAX 발송 (계약서·청구서 등) | **BIZCLIENT** |
| PHONE 발송 (TTS 안내, 설문) | **BIZCLIENT** |
| 네이버 톡톡 발송 | **API** |
| 사용자 액션 직후 즉시 1건 발송 | **API** |

### 결정 후 다음 단계

> **NOTE:** 선택 후 사전 준비 단계는 두 방식 공통입니다. [사전 준비](https://bizppurio.github.io/docs#공통-사전-준비)에서 비즈뿌리오 계정·발신번호·채널 등록을 먼저 마쳐야 발송이 가능합니다.

- **API 선택** → [Quickstart (API)](https://bizppurio.github.io/bizapi#quickstart-bizapi)
- **BIZCLIENT 선택** → [Quickstart (BIZCLIENT)](https://bizppurio.github.io/bizclient#quickstart-bizclient)

## 비교 — BIZAPI vs BIZCLIENT

비즈뿌리오는 두 가지 연동 방식을 제공합니다.

### 한눈에 비교

| 항목 | API 연동 | BIZCLIENT 연동 |
| --- | --- | --- |
| 인터페이스 | HTTPS REST (`/v3/message`) | MySQL/Oracle/MSSQL INSERT |
| 호스팅 | 비즈뿌리오 클라우드 | 고객사 서버 (온프레미스) |
| 인증 | Bearer 토큰 (24시간 유효) | DB 접근 권한 |
| 발송 트리거 | API 호출 즉시 | BIZCLIENT 모듈이 큐 폴링 |
| 결과 수신 | Webhook(URL Push) / Polling | `BIZ_MSG.STATUS` + `CALL_STATUS` |
| 외부 네트워크 의존 | 필요 (HTTPS 443 outbound) | 38300/38400 outbound (모듈 ↔ 비즈뿌리오) |
| 모듈 설치 | 불필요 | 필수 (JDK 1.8+, 4GB RAM, JDBC) |
| 운영 부담 | 낮음 | 모듈 구동·DB 관리·로그 테이블 회전 |
| 미지원 채널 | FAX · PHONE | NTALK |

### 발송 흐름 차이

### API 연동

고객사 앱비즈뿌리오통신사·카카오·RCS→ 단말① POST /v1/token — Basic 인증② accesstoken 발급 (24시간 유효)③ POST /v3/message — Bearer + JSON메시지 전달④ Webhook (URL Push) 또는 Polling

### BIZCLIENT 연동

고객사 앱BIZ\_MSG큐 테이블 (고객사 DB)BIZCLIENT 모듈비즈뿌리오 서버① INSERT (STATUS = 0)② 폴링 — 발송 데이터 인출③ 전송 — 38300/38400 outbound통신사 · 카카오 · RCS → 단말④ 결과 수신⑤ STATUS = 2 · CALL\_STATUS 업데이트완료 → BIZ\_LOG\_YYYYMM 이동

### 어떤 방식을 골라야 할까?

| 우선 검토 | 이유 |
| --- | --- |
| **API**를 우선 검토 | 새 프로젝트, 빠른 시작, 운영 부담 최소화, 멀티테넌트 환경 |
| **BIZCLIENT** 검토 | 외부 HTTPS 호출이 제한된 사내망, 대량 배치(DB 동시 처리), 레거시 ERP·CRM 연동, FAX/PHONE 발송 |
| **두 방식 혼용** | API로 트랜잭션성 메시지 + BIZCLIENT로 야간 대량 배치 |

자세한 결정 흐름은 [연동 방식 선택 가이드](https://bizppurio.github.io/docs#선택-가이드)를 참고하세요.

### 채널 지원 매트릭스

| 채널 | API | BIZCLIENT |
| --- | --- | --- |
| SMS / LMS / MMS | ✓ | ✓ |
| 카카오 알림톡 (AT/AI) | ✓ | ✓ |
| 카카오 브랜드메시지 (BT 외 7종) | ✓ | ✓ |
| RCS | ✓ | ✓ |
| 네이버 톡톡 (NTALK) | ✓ | ✗ |
| FAX | ✗ | ✓ |
| PHONE | ✗ | ✓ |

## 공통 사전 준비

발송 전에 비즈뿌리오 사이트에서 사용할 채널별로 다음 항목을 등록해야 합니다.

### 모든 사용자 공통

### 1. 비즈뿌리오 서비스 계정

| 단계 | 절차 |
| --- | --- |
| 회원 가입 | [www.bizppurio.com](https://www.bizppurio.com) |
| API 용도 계정 생성 | 운영용·검수용 **각각** 발급 권장 |
| 서비스 사용 승인 요청 | 전화 1599-9782 / 이메일 [bizppurio@daou.co.kr](mailto:bizppurio@daou.co.kr) |

### 2. 발신번호 등록

이동통신 메시지(SMS/LMS/MMS)·RCS·BIZCLIENT FAX/PHONE 모두 발신번호 사전 등록이 필수입니다.

비즈뿌리오 사이트에서 직접 등록.

### 3. (API 사용 시) 전송 결과 수신 URL 등록

Webhook(URL Push) 방식 사용 시 결과 수신용 URL(IP/PORT)을 비즈뿌리오에 등록 요청.

> **WARNING:** 443·80 외 포트를 사용하려면 별도로 방화벽 접근 허용 요청이 필요합니다.

### 채널별 추가 등록

### 카카오 비즈메시지 (AT / BT)

| 단계 | 위치 |
| --- | --- |
| 카카오톡 채널 개설 및 비즈니스 채널 신청 | [카카오 비즈니스](https://center-pf.kakao.com) |
| 발신프로필 키 생성 | 비즈뿌리오 사이트 |
| 알림톡(AT) 템플릿 등록·승인 | 비즈뿌리오 사이트 — 알림톡 사용 시 필수 |
| 브랜드메시지(BT) 브랜드 등록 | 비즈뿌리오 사이트 — 브랜드메시지 사용 시 |

### RCS

| 단계 | 위치 |
| --- | --- |
| RCS 브랜드 개설 및 대행사 설정 | [RCS 비즈센터 (RBC)](https://www.rcsbizcenter.com) |
| RCS 브랜드 등록 | 비즈뿌리오 사이트 |
| RCS 발신번호 등록 | 비즈뿌리오 사이트 |
| 챗봇 ID 발급 | RBC |
| 브랜드 키 (`brandkey`) 발급 | RBC |
| 메시지베이스 ID 결정 | [공통 포맷](https://bizppurio.github.io/bizclient#용어) 또는 템플릿 등록 |
| RCS 이미지 등록 | 비즈뿌리오 사이트 [메시지관리] → [RCS 관리] → [RCS 이미지 관리] (365일 유효) |

### 네이버 톡톡 (NTALK, API 전용)

| 단계 | 위치 |
| --- | --- |
| 네이버 톡톡 채널 개설 | [네이버 톡톡 파트너센터](https://partner.talk.naver.com) |
| 파트너 키 (`partnerid` / `partnerkey`) 생성 | 비즈뿌리오 사이트 |
| 템플릿 등록·승인 | 비즈뿌리오 사이트 |
| 이미지 등록 | 비즈뿌리오 사이트 |

### 사용 채널별 체크리스트

| 채널 | 필수 등록 |
| --- | --- |
| **SMS / LMS / MMS** | 비즈뿌리오 계정 + 발신번호 |
| **카카오 알림톡** | + 카카오 채널 + 발신프로필 키 + 템플릿 승인 |
| **카카오 브랜드메시지** | + 카카오 채널 + 발신프로필 키 + 브랜드 등록 + 템플릿 |
| **RCS** | + RBC 브랜드·챗봇·발신번호·메시지베이스 + 브랜드 키 |
| **네이버 톡톡 (API 전용)** | + 네이버 톡톡 채널 + 파트너 키 + 템플릿 |
| **FAX (BIZCLIENT 전용)** | + 발신 팩스번호 |
| **PHONE (BIZCLIENT 전용)** | + 발신번호 |

### 자원을 코드로 관리하고 싶다면

비즈뿌리오 사이트(GUI)에서 한 번씩 등록할 수도 있지만, 자원을 **프로그래매틱하게 관리**하려면 다음 관리 API를 사용하세요.

- [KAPI](https://bizppurio.github.io/kapi#공통-사항) — 카카오 알림톡 템플릿·이미지·발신프로필·브랜드메시지 자원 CRUD
- [RAPI](https://bizppurio.github.io/rapi#토큰) — RCS 브랜드·챗봇·파일·메시지베이스 CRUD

### 다음 단계

준비가 끝나면 Quickstart로 진행하세요.

- [Quickstart (API)](https://bizppurio.github.io/bizapi#quickstart-bizapi)
- [Quickstart (BIZCLIENT)](https://bizppurio.github.io/bizclient#quickstart-bizclient)


---

# 메시지 API (BIZAPI)

> 원본: [https://bizppurio.github.io/bizapi/](https://bizppurio.github.io/bizapi/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

BIZAPIv3.11.2

# 비즈뿌리오 메시지 API (BIZAPI)

## 공통 사항

메시지 API (BIZAPI) — 모든 발송 API 호출에 공통으로 적용되는 사양.

### 연동 규격

| 항목 | 값 |
| --- | --- |
| 프로토콜 | HTTPS (443) |
| 도메인 (운영) | `https://api.bizppurio.com` |
| 도메인 (검수) | `https://dev-api.bizppurio.com` |
| 메서드 | POST |
| 인코딩 | UTF-8 |
| Content-Type | `application/json; charset=utf-8` |
| 인증 | Bearer 토큰 (`Authorization: Bearer {accessToken}`) |
| HTTP Keep-Alive Timeout | **4초 이하** |

> **WARNING:** Keep-Alive Timeout 이 4초를 초과하면 서버가 연결을 종료하여 일부 요청이 실패하거나 재시도가 필요할 수 있습니다.

### 인증 흐름

고객사비즈뿌리오 서버([api.bizppurio.com](http://api.bizppurio.com))① POST /v1/token — Basic Auth: account + apiKey② accessToken + expired (24h)③ 후속 호출 — Authorization: Bearer {accessToken}만료 직전 재발급 — 만료된 토큰은 인증 오류

자세한 토큰 발급은 [인증 API](https://bizppurio.github.io/bizapi#인증) 를 참고하세요.

### 공통 응답 형식

```
{
  "code": 1000,
  "description": "Success",
  "refkey": "test1234",
  "messagekey": "190922175225820#ft002951seXXXXXX"
}
```

| 필드 | 설명 |
| --- | --- |
| `code` | 결과 코드 (`1000` = 성공, 그 외 [전송 결과 코드](https://bizppurio.github.io/response-codes) 참고) |
| `description` | 결과 메시지 |
| `refkey` | 요청 시 보낸 고객사 키 (echo) |
| `messagekey` | 비즈뿌리오 발급 메시지 키 (결과 조회·중복 검사용) |

### Rate Limit

특정 시간 내 호출 가능한 API 요청 횟수 제한입니다. 제한값을 초과하면 **HTTP 429** 가 반환됩니다.

- 모든 API 요청 횟수가 카운트됩니다 (토큰 발급 / 메시지 발송 / 리포트 요청 등)
- 재시도 로직이 있는 경우 재시도 간격을 충분히 늘려야 합니다
- 제한 값 상향은 고객센터 문의

응답 헤더:

| 헤더 | 설명 |
| --- | --- |
| `RateLimit-Limit` | 기준 시간 내 최대 요청 가능 횟수 |
| `RateLimit-Remaining` | 기준 시간 내 남은 요청 가능 횟수 |
| `RateLimit-Reset` | 기준 시간 갱신까지 남은 시간 (ms) |

초과 시 응답 예시:

```
HTTP/1.1 429 Too Many Requests
Content-type: application/json
RateLimit-Limit: 1000
RateLimit-Remaining: 0
RateLimit-Reset: 0.299
```

```
{
  "code": 5002,
  "description": "too many requests",
  "refkey": "test1234"
}
```

## Quickstart BIZAPI

비즈뿌리오 API로 SMS 1건을 보내는 가장 짧은 경로입니다.

### 사전 조건

- 비즈뿌리오 운영 또는 검수 계정 (계정ID·암호)
- 등록된 발신번호 1개
- 본인 휴대폰 번호 (수신 테스트용)

준비가 안 됐다면 [사전 준비](https://bizppurio.github.io/docs#공통-사전-준비)부터 진행하세요.

### 1. 환경 변수 세팅

```
# 검수 환경
export BP_HOST="dev-api.bizppurio.com"
# 운영 환경에서는 export BP_HOST="api.bizppurio.com"

export BP_ACCOUNT="bizUserId001"
export BP_PASSWORD="mypassword"
export BP_FROM="07000000000"      # 등록된 발신번호
export BP_TO="01012345678"        # 수신 테스트 번호
```

### 2. 인증 토큰 발급

```
AUTH=$(printf '%s' "$BP_ACCOUNT:$BP_PASSWORD" | base64)

ACCESS_TOKEN=$(curl -s -X POST "https://$BP_HOST/v1/token" \
  -H "Authorization: Basic $AUTH" \
  -H "Content-type: application/json; charset=utf-8" \
  | python -c "import sys, json; print(json.load(sys.stdin)['accesstoken'])")

echo "$ACCESS_TOKEN"
```

응답 예시:

```
{
  "accesstoken": "eyJ0eXAiOiJKV1QiLC...",
  "type": "Bearer",
  "expired": "20260429185520"
}
```

> **NOTE:** 토큰은 **24시간** 유효합니다. 매 요청마다 발급하지 말고 캐싱해서 사용하세요.

### 3. SMS 메시지 전송

```
curl -X POST "https://$BP_HOST/v3/message" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-type: application/json" \
  -d '{
    "account": "'"$BP_ACCOUNT"'",
    "refkey": "test-'"$(date +%s)"'",
    "type": "sms",
    "from": "'"$BP_FROM"'",
    "to": "'"$BP_TO"'",
    "content": {
      "sms": {
        "message": "비즈뿌리오 API Quickstart 테스트입니다."
      }
    }
  }'
```

성공 응답 예시:

```
{
  "code": 1000,
  "description": "Success",
  "refkey": "test-1735459200",
  "messagekey": "260429185700123#sms027420XXXXXXXX"
}
```

`code: 1000`이면 비즈뿌리오 서버가 메시지를 정상 접수한 것입니다. 잠시 후 등록한 수신번호로 SMS가 도착합니다.

> **WARNING:** **`code`가 1000이 아니면** [BIZAPI 응답 상태 코드](https://bizppurio.github.io/response-codes)를 참고해 원인을 확인하세요. 자주 마주치는 케이스: `3001`(Basic 인증 실패), `3010`(IP 화이트리스트 미등록), `2000`(페이로드 오류).

### 4. 발송 결과 확인

발송 결과는 두 가지 방식으로 받을 수 있습니다.

### Webhook (권장)

비즈뿌리오에 사전 등록한 URL로 결과가 자동 PUSH됩니다.

```
{
  "DEVICE": "SMS",
  "CMSGID": "260429185700123#sms027420XXXXXXXX",
  "MSGID": "0429se_SL46760273836XXXXXXXX",
  "PHONE": "01012345678",
  "MEDIA": "SMS",
  "UNIXTIME": "1735459200",
  "RESULT": "4100",
  "REFKEY": "test-1735459200"
}
```

`RESULT: 4100`이면 단말기 전달 성공입니다. 자세한 키와 코드는 [Webhook](https://bizppurio.github.io/bizapi#전송-결과-조회)·[발송 결과 코드](https://bizppurio.github.io/response-codes) 참고.

### 결과 재요청 (Webhook 누락 시)

```
curl -X POST "https://$BP_HOST/v2/report" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-type: application/json" \
  -d '{
    "account": "'"$BP_ACCOUNT"'",
    "messagekey": "260429185700123#sms027420XXXXXXXX"
  }'
```

### 다음 단계

| 하고 싶은 것 | 진행 |
| --- | --- |
| LMS / MMS / 알림톡·브랜드메시지·RCS·NTALK 발송 | [메시지 채널 카탈로그](https://bizppurio.github.io/bizapi#메시지-전송) |
| 발송 실패 시 다른 채널로 자동 전환 | [대체 발송](https://bizppurio.github.io/bizapi#메시지-전송) |
| 대량 발송 / Rate Limit 다루기 | [Rate Limit 가이드](https://bizppurio.github.io/bizapi#rate-limit) |
| MMS에 이미지 첨부 | [MMS 파일 업로드](https://bizppurio.github.io/bizapi#파일-업로드) |
| Polling 방식 결과 수신 | [전송 결과 조회](https://bizppurio.github.io/bizapi#전송-결과-조회) |

### 트러블슈팅

| 증상 | 원인·해결 |
| --- | --- |
| `3001` | Basic Base64 인코딩 확인 (`계정:암호` 형식) |
| `3010` | 비즈뿌리오에 접속 IP 등록 |
| `2000` | 페이로드 형식 오류 — `content.sms.message` 구조 확인 |
| `5002` (HTTP 429) | Rate Limit 초과 — `RateLimit-Reset` 헤더 참고 후 백오프 |
| `code: 1000`인데 SMS 미수신 | 결과는 `RESULT` 코드 확인 — 4100=성공, 4400~=음영지역, 4430=스팸 등 |

자세한 가이드는 [API errors](https://bizppurio.github.io/response-codes)와 [발송 결과 코드](https://bizppurio.github.io/response-codes)를 참고하세요.

## 인증

액세스 토큰 발급

post/v1/token

### 인증 토큰 발급

비즈뿌리오 계정과 암호를 **Basic 인증** 방식으로 전송하여 액세스 토큰을 발급받습니다.

- `Authorization` 값은 `계정:암호` 문자열을 콜론으로 연결한 뒤 Base64 인코딩
- 토큰 유효 시간 **24시간** — 만료 후 재발급 필요
- 토큰을 캐싱하여 매 요청마다 재발급하지 않도록 운영

```
echo -n "bizUserId001:mypassword" | base64
# bXlhY2NvdW50Om15cGFzc3dvcmQ=
```

cURL

```
curl -X POST "{baseUrl}/v1/token" \
  -H "Authorization: Basic {base64(account:password)}"
```

응답

200토큰 발급 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| accesstoken | string | 필수 | 인증 토큰 (이후 모든 API 호출의 Authorization 헤더에 사용) |
| type | string | 필수 | 항상 "Bearer"  = Bearer |
| expired | string | 필수 | 토큰 만료 시간 (yyyyMMddHHmmss) |

응답 · 200

```
{
  "accesstoken": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "type": "Bearer",
  "expired": "20201110185520"
}
```

400인증 실패. 주요 코드:
- `3001` Basic 인증 정보가 유효하지 않음
- `3006` 계정이 존재하지 않음
- `3007` 계정 암호가 유효하지 않음
- `3009` 계정 중지 상태
- `3010` 등록된 접속 허용 IP와 불일치

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 0,
  "description": "string",
  "refkey": "string"
}
```

## 메시지 전송

모든 채널 공통 발송 엔드포인트 (`/v3/message`). 채널별 페이로드는 `content.oneOf` 안에 모두 정의되어 있으며 본 페이지에서 펼쳐 확인할 수 있습니다.

post/v3/message

### 메시지 전송

모든 채널의 메시지 전송에 사용하는 단일 엔드포인트입니다.  
채널은 `type` 필드로 구분되며, `content` 객체는 `type`에 매칭되는 채널 키 하나만 포함합니다.

### sendtime 동작 규칙

- 비즈뿌리오 서버 시간 기준, 한국 표준시(GMT+9)
- 과거 시간 입력 시 즉시 발송
- 즉시 발송을 원하는 경우 미입력
- **네이버 톡톡은 예약 불가** (`sendtime`에 관계없이 즉시 발송)
- **브랜드메시지는 예약 취소 불가**

### RESEND — 대체 전송

같은 메신저(카카오톡)끼리는 대체 불가. 2차 대체는 1차가 rich 채널(RCS/AT/AI/BT)일 때만 가능합니다.

| 본발송 | 1차 대체 | 2차 대체 |
| --- | --- | --- |
| 문자(`sms`/`lms`/`mms`) | — | — |
| 알림톡(`at`/`ai`) | 문자(SMS/LMS/MMS) 또는 RCS | (1차 RCS) 문자(SMS/LMS/MMS) |
| 브랜드메시지(`ut~ua`) | 문자(SMS/LMS/MMS) 또는 RCS | (1차 RCS) 문자(SMS/LMS/MMS) |
| RCS | 문자(SMS/LMS/MMS) 또는 카카오(알림톡/브랜드메시지) | (1차 카카오) 문자(SMS/LMS/MMS) |
| 네이버 톡톡(`ntalk`) | — | — |

cURL

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200요청 접수 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 결과 코드 (1000 = 성공) |
| description | string | 필수 | — |
| messagekey | string(32) | 필수 | 비즈뿌리오 메시지 키 — 고객 문의 및 리포트 재요청 기준 |
| refkey | string(32) | 필수 | 요청 시 전달한 고객사 키 |

응답 · 200

```
{
  "code": 1000,
  "description": "Success",
  "messagekey": "190922175225820#ft002951seXXXXXX",
  "refkey": "test1234"
}
```

400요청 실패. 주요 응답 코드:
- `2000` 메시지가 유효하지 않음
- `3000~3013` 인증/계정 관련 오류
- `3014` 데이터 포맷 에러

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 2000,
  "description": "invalid message",
  "refkey": "test1234"
}
```

429Rate Limit 초과 (`code: 5002`). `RateLimit-Reset` 헤더 참고하여 백오프 후 재시도.

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 429

```
{
  "code": 5002,
  "description": "too many requests",
  "refkey": "test1234"
}
```

post/v3/messagetype=sms

### SMS

**실제 HTTP endpoint**: `POST /v3/message` — `type: sms` SMS 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = sms |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | SMS 페이로드 (`type: sms`) |
| └sms | object | 필수 | — |
| └message | string | 필수 | 본문 (EUC-KR 기준 최대 90바이트) |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "sms",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "sms": {
      "message": "SMS 전송"
    }
  }
}'
```

post/v3/messagetype=lms

### LMS

**실제 HTTP endpoint**: `POST /v3/message` — `type: lms` LMS 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = lms |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | LMS 페이로드 (`type: lms`) |
| └lms | object | 필수 | — |
| └subject | string | — | 제목 (EUC-KR 기준 최대 64바이트) |
| └message | string | 필수 | 본문 (EUC-KR 기준 최대 2000바이트) |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "lms",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "lms": {
      "subject": "제목",
      "message": "LMS 전송"
    }
  }
}'
```

post/v3/messagetype=mms

### MMS

**실제 HTTP endpoint**: `POST /v3/message` — `type: mms` MMS 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = mms |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | MMS 페이로드 (`type: mms`) — LMS + 이미지 첨부 (최대 3개). 본문은 선택 (이미지만 발송 가능).  이미지는 [POST /v2/file](https://bizppurio.github.io/bizapi#post-v2file)로 사전 업로드한 후 받은 `filekey`를 `file[].key`에 사용. |
| └mms | object | 필수 | — |
| └subject | string | — | 제목 (EUC-KR 기준 최대 64바이트) |
| └message | string | — | 본문 (EUC-KR 기준 최대 2000바이트, 선택) |
| └file | array<object>(~3) | 필수 | 첨부파일 배열 (최대 3개) |
| └type | string | 필수 | 파일 유형 (현재 IMG만 지원)  = IMG |
| └key | string(40) | 필수 | 파일 키 (`/v2/file` 응답의 `filekey`) |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "mms",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "mms": {
      "subject": "제목",
      "message": "MMS 전송",
      "file": [
        {
          "type": "IMG",
          "key": "1585011852_DD7482861185100000001.jpg"
        }
      ]
    }
  }
}'
```

post/v3/messagetype=rcs

### RCS

**실제 HTTP endpoint**: `POST /v3/message` — `type: rcs` RCS 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

---

RCS는 이통 3사의 리치 메시징 채널입니다. **안드로이드 RCS**(채팅+ 지원 단말)와 **통합 RCS**(이통 3사 표준 규격) 모두 `type: rcs` 하나로 발송하며, `messagebaseid` 값으로 메시지 유형이 결정됩니다.

> **사전 준비**: ① RCS 브랜드 개설·대행사 설정 ([RCS 비즈센터](https://www.rcsbizcenter.com)) → ② RCS 브랜드 등록 ([비즈뿌리오](https://www.bizppurio.com)) → ③ 발신번호·템플릿 등록/승인

RCS의 상세 규격 — MESSAGEBASE ID 유형별 표(안드로이드 RCS / 통합 RCS), 슬라이드형 글자수·라인수 정의, 이미지·동영상 첨부(`media`) 규격, 버튼 Action 7종 규격 — 은 [RCS 연동 규격](https://bizppurio.github.io/bizapi#rcs-연동-규격)을 참고하세요. 아래 요청 본문 스키마에서 각 필드의 레벨별 정의와 예시를 확인할 수 있습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = rcs |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | RCS 페이로드 (`type: rcs`) — 안드로이드 RCS / 통합 RCS 모두 지원.  MESSAGEBASE ID 유형별 표·안드로이드 vs 통합 차이는 [RCS 발송 페이지](https://bizppurio.github.io/bizapi#post-v3messagercs) 를 참고하세요. |
| └rcs | object | 필수 | — |
| └messagebaseid | string(40) | 필수 | 메시지 베이스 ID (MESSAGEBASE ID — 유형별 표는 [RCS 발송 페이지](https://bizppurio.github.io/bizapi#post-v3messagercs) 참고) |
| └chatbotid | string(40) | 필수 | RCS 비즈센터에서 생성한 챗봇 ID |
| └brandkey | string(64) | — | 브랜드별 제공되는 특수 키 (2023.08.01 이후 잘못된 값은 실패) |
| └header | string(1) | 필수 | 메시지 상단 식별 문구. `0`=Web 발신 / `1`=광고. **통합 RCS는 `0`만 허용**  = 0 | 1 |
| └footer | string(64) | — | 하단 수신거부 문구 (안드로이드 RCS 전용) |
| └copyallowed | string(1) | — | 복사/공유 메뉴 표시 (안드로이드 RCS 전용, 기본 N)  = Y | N |
| └agencyid | string(20) | — | 대행사 ID (기본: daoutech) |
| └agencykey | string(64) | — | 대행사 Key (2차 대행사인 경우 필수) |
| └groupid | string(20) | — | 캠페인 그룹 ID (통계용) |
| └message | object | — | 메시지 베이스에서 치환할 본문 객체 (`messagebaseid`에 따라 필드 구성·`media` 값 상이) — [RCS 연동 규격 — MESSAGE](https://bizppurio.github.io/bizapi#message) 참조 |
| └button | array<object> | — | 버튼 배열 (캐러셀은 카드별로 객체, 빈 카드는 `{}`로 순서 유지) |
| └suggestions | array<object> | — | 제안(suggestion) 배열 |
| └action | object | 필수 | RCS Action — 7종 중 정확히 1개만 포함. 타입별 필드는 [RCS 연동 규격 — BUTTON](https://bizppurio.github.io/bizapi#button) 참조 |
| └displayText | string | 필수 | 버튼에 출력될 텍스트 |
| └postback | object | — | 챗봇 콜백 데이터 |
| └data | string | — | — |
| resend | object | — | 대체 발송 설정 (본 발송 실패 시 다른 채널로 자동 전환). 채널 조합·규격은 [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |
| recontent | object | — | 대체 채널별 본문 (`resend`와 짝) — [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "rcs",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "rcs": {
      "messagebaseid": "RPLSAXX001",
      "chatbotid": "15880000",
      "header": "0",
      "message": {
        "title": "줄바꿈 없는 14자 권장",
        "description": "(광고)\n안녕하세요! RCS LMS\n무료 수신 거부 080-1234-5678\n"
      },
      "button": [
        {
          "suggestions": [
            {
              "action": {
                "urlAction": {
                  "openUrl": {
                    "url": "https://www.bizppurio.com"
                  }
                }
              },
              "displayText": "비즈뿌리오로 이동"
            }
          ]
        }
      ]
    }
  }
}'
```

post/v3/messagetype=at

### 카카오 알림톡

**실제 HTTP endpoint**: `POST /v3/message` — `type: at` 알림톡 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

---

알림톡은 **사전 등록·승인된 템플릿** 기반으로 발송합니다. `senderkey`(발신 프로필 키)와 `templatecode`로 템플릿을 지정하고, `message`에는 **변수 치환이 끝난 최종 본문**을 입력합니다.

> **사전 준비 — 알림톡·브랜드메시지 공통**: ① 카카오톡 채널 개설·비즈니스 채널 신청 ([카카오 비즈니스](https://center-pf.kakao.com)) → ② 발신 프로필 키 생성 ([비즈뿌리오](https://www.bizppurio.com)) → ③ 템플릿 등록/승인

### 공통 입력 규칙

- **템플릿에 포함된 구성 요소는 필수 입력** — 템플릿에 버튼(`button`)·바로연결(`quickreply`)·강조표기(`title`)가 등록돼 있으면 발송 요청에도 해당 필드를 반드시 전달합니다.
- 본문은 **이모지를 포함한 UTF-8 범위 내 문자열** 사용 가능합니다 (카카오·RCS·네이버 톡톡 공통).

### 구성 요소와 한도

| 필드 | 한도 | 비고 |
| --- | --- | --- |
| `message` | 한글/영문 1,300자 | 필수 — 변수 치환 후 최종 본문 |
| `title` | 50자 | 강조표기형 — 본문 중 강조할 핵심 정보 |
| `header` | 16자 | 아이템리스트형 헤더 |
| `item` | list: title 6자 · description 23자 | 아이템리스트. `summary`는 title 6자 · 가격정보 14자 (통화기호·통화코드·숫자만) |
| `itemhighlight` | title 30자 · description 19자 | 이미지 동반 시 21자 / 13자. title 끝에 `\s` 플래그 포함 시 취소선 적용 |
| `button` | 최대 5개 | 14종 — 타입별 필수 파라미터는 [알림톡 버튼 표](https://bizppurio.github.io/bizapi#spec-at-button) |
| `quickreply` | 최대 10개 | 6종 — [알림톡 바로연결 표](https://bizppurio.github.io/bizapi#spec-at-quickreply) |
| `link` | — | 대표 링크 (URL·앱 스킴) |

> 광고성 메시지가 필요하다면 **브랜드메시지**(UT 등 8종)를 사용하세요. 같은 발신프로필을 사용하지만 발송 조건·버튼 타입이 다릅니다 — [브랜드메시지 선택 가이드](https://bizppurio.github.io/bizapi#브랜드-메시지-선택-가이드) 참고.

> **이미지 알림톡**: 알림톡 템플릿이 이미지 강조 유형이면 동일 페이로드에 `type: ai` 로 발송합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = at |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | 카카오 알림톡 페이로드 (`type: at`) |
| └at | object | 필수 | 페이로드 키는 `type` 값과 일치해야 합니다 — 알림톡은 `at`.  **이미지 강조형은 `type: ai` 로 발송하며 키도 `ai`** 를 사용합니다(하위 구조는 아래와 동일). |
| └senderkey | string(40) | 필수 | 발신 프로필 키 |
| └templatecode | string(32) | 필수 | 템플릿 코드 |
| └message | string(1300) | 필수 | 본문 (한글/영문 최대 1300자, 변수 치환 후) |
| └button | array<object>(~5) | — | 버튼 (최대 5개, 템플릿 포함 시 필수) — 타입·필드 규격은 [카카오 연동 규격 — 알림톡 버튼](https://bizppurio.github.io/bizapi#spec-at-button) 참조 |
| └name | string(28) | 필수 | 버튼 제목 (AC 타입은 '채널 추가' 고정) |
| └type | string | 필수 | 버튼 타입 — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격](https://bizppurio.github.io/bizapi#카카오-연동-규격) 의 "알림톡 버튼" 표 참조  = WL | AL | DS | BK | MD | BC | BT | AC | P1 | P2 | P3 | BF | TN | MP |
| └url\_pc | string | — | PC 환경 이동 URL |
| └url\_mobile | string | — | Mobile 환경 이동 URL (WL 필수) |
| └scheme\_ios | string | — | iOS 앱 Custom Scheme |
| └scheme\_android | string | — | Android 앱 Custom Scheme |
| └chat\_extra | string(50) | — | 상담톡/봇 전환 시 메타정보 |
| └chat\_event | string(50) | — | 봇 전환 시 이벤트명 |
| └plugin\_id | string(24) | — | 플러그인 ID |
| └relay\_id | string | — | 플러그인 실행 시 X-Kakao-Plugin-Relay-Id 헤더 전달 값 |
| └oneclick\_id | string | — | 원클릭 결제 ID |
| └product\_id | string | — | 원클릭 결제 상품 ID |
| └tel\_number | string(14) | — | 전화번호 (TN 전용, 하이픈 포함) |
| └biz\_form\_id | integer | — | 비즈니스폼 ID (BF 전용) |
| └map\_address | string | — | 지도보기 주소 (MP 전용) |
| └map\_coordinates | string | — | 지도보기 위경도 좌표 (MP 전용, map\_address 우선) |
| └quickreply | array<object>(~10) | — | 바로연결 (최대 10개, 템플릿 포함 시 필수) — 타입·필드 규격은 [카카오 연동 규격 — 알림톡 바로연결](https://bizppurio.github.io/bizapi#spec-at-quickreply) 참조 |
| └name | string(14) | 필수 | 바로연결 텍스트 |
| └type | string | 필수 | 바로연결 타입 (WL·AL·BK·BC·BT·BF 6종) — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격](https://bizppurio.github.io/bizapi#카카오-연동-규격) 의 "알림톡 바로연결" 표 참조  = WL | AL | BK | BC | BT | BF |
| └url\_pc | string | — | — |
| └url\_mobile | string | — | — |
| └scheme\_ios | string | — | — |
| └scheme\_android | string | — | — |
| └chat\_extra | string(50) | — | — |
| └chat\_event | string(50) | — | — |
| └title | string(50) | — | 강조 표기할 핵심 정보 |
| └header | string(16) | — | 아이템리스트 헤더 |
| └item | object | — | 알림톡 아이템리스트와 아이템 요약정보 |
| └list | array<object> | 필수 | 아이템 리스트 |
| └title | string(6) | 필수 | 타이틀 |
| └description | string(23) | 필수 | 부가정보 |
| └summary | object | — | 아이템 요약 정보 |
| └title | string(6) | 필수 | 타이틀 |
| └description | string(14) | 필수 | 가격정보 (통화기호/ISO4217/숫자/콤마/소수점 2자리) |
| └itemhighlight | object | — | 아이템 하이라이트 |
| └title | string(30) | 필수 | 타이틀 (이미지 동반 시 21자, 내용 끝 \s 플래그 시 취소선) |
| └description | string(19) | 필수 | 부가정보 (이미지 동반 시 13자) |
| └link | object | — | 대표 링크 |
| └url\_mobile | string | — | Mobile 환경 이동 URL |
| └url\_pc | string | — | PC 환경 이동 URL |
| └scheme\_android | string | — | Android 앱 Custom Scheme |
| └scheme\_ios | string | — | iOS 앱 Custom Scheme |
| resend | object | — | 대체 발송 설정 (본 발송 실패 시 다른 채널로 자동 전환). 채널 조합·규격은 [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |
| recontent | object | — | 대체 채널별 본문 (`resend`와 짝) — [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "at",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "at": {
      "senderkey": "abc123XXXXX",
      "templatecode": "tempXXXX",
      "message": "알림톡 + 버튼(WL)",
      "button": [
        {
          "name": "웹 링크 버튼",
          "type": "WL",
          "url_mobile": "https://www.daou.com"
        }
      ]
    }
  }
}'
```

post/v3/messagetype=ut

### 카카오 브랜드메시지

**실제 HTTP endpoint**: `POST /v3/message` — 카카오 브랜드메시지 8종(UT/UI/UW/UL/UC/UM/UP/UA)을 하나로 안내하는 문서 페이지입니다. `type` 값(`ut`~`ua`)으로 말풍선 형태를 지정하며, **아래 예시 뷰어에서 대상·방식·타입별 전체 64개 조합**을 확인할 수 있습니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

---

브랜드메시지는 **고객사의 광고성 정보 수신 동의 회원** 또는 **카카오 채널 친구** 대상으로 발송하는 광고성 메시지 상품입니다. 8종 타입(UT/UI/UW/UL/UC/UM/UP/UA)이 동일한 페이로드 구조를 사용합니다.

**발송 대상·형태 결정 가이드(4-STEP)·타겟팅(M/N/O)·타입별 구성**은 [카카오 연동 규격 — 브랜드메시지 선택 가이드](https://bizppurio.github.io/bizapi#브랜드-메시지-선택-가이드) 를 참고하세요.

### 발송 조건 (8종 공통)

> **WARNING:** 광고성 상품이므로 **발송 가능 시간은 08:00～20:50 (한국 시간)** 입니다. 해외 전화번호로 카카오톡에 가입한 사용자는 시간 제한이 없습니다.

- 채널을 차단한 사용자에게는 발송되지 않습니다.
- 고객사 회원 대상(`sendtarget: marketing`)은 **사전 발송 권한 신청**이 필요하고 `targeting`(M/N/O)이 필수이며, 카카오톡 25.4.0 이상 사용자에게만 발송됩니다.
- 채널 친구 대상(`sendtarget: friend`)은 발신프로필 등록 후 바로 발송 가능하며 `targeting`을 사용하지 않습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = ut |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | 카카오 브랜드메시지 — TEXT (`type: ut`). 8종 타입(UT/UI/UW/UL/UC/UM/UP/UA)은 동일 구조(`BrandPayload`)를 공유합니다.  타입별 구성·본문 필드·타겟팅은 [카카오 연동 규격 — 브랜드메시지 선택 가이드](https://bizppurio.github.io/bizapi#브랜드-메시지-선택-가이드) 를 참조하세요. 요청 예제 8종을 그대로 복사해 사용할 수 있습니다. |
| └ut | allOf | 필수 | 페이로드 키는 `type` 값과 일치해야 합니다 — 말풍선 형태에 따라  `ut`(TEXT)·`ui`(IMAGE)·`uw`(WIDE)·`ul`(WIDE\_ITEM\_LIST)·`uc`(CAROUSEL\_FEED)·`um`(COMMERCE)·`up`(PREMIUM\_VIDEO)·`ua`(CAROUSEL\_COMMERCE).  8종 모두 아래 동일 구조를 사용합니다. |
| └senderkey | string(40) | 필수 | 발신 프로필 키 |
| └brandmessagetype | string | 필수 | 브랜드메시지 타입 (BASIC=기본형 / FREE=자유형)  = BASIC | FREE |
| └sendtarget | string | 필수 | 발송 대상 타입:   - `marketing` 고객사의 광고성 정보 수신동의 회원 대상 (`targeting` 필수) - `friend` 채널 친구 대상 (`targeting` 미사용)  = marketing | friend |
| └targeting | string | — | **`sendtarget: marketing`일 때 필수, `friend`일 때 미사용** (발송 권한 신청 필요). M/N/O 의미·타겟팅 상세는 [카카오 연동 규격 — 브랜드메시지 타겟팅](https://bizppurio.github.io/bizapi#브랜드-메시지-타겟팅) 참조  = M | N | O |
| └unsubscribephonenumber | string(13) | — | **`sendtarget: marketing`(고객사 회원 대상)일 때만 사용, `friend`(채널 친구 대상)에서는 미사용.**  무료수신거부 전화번호. `unsubscribephonenumber`·`unsubscribeauthnumber` 둘 다 미입력 시  발신프로필에 등록된 무료수신거부 정보로 발송됩니다. (예: 080-1234-1234) |
| └unsubscribeauthnumber | string(10) | — | **`sendtarget: marketing`(고객사 회원 대상)일 때만 사용, `friend`(채널 친구 대상)에서는 미사용.**  무료수신거부 인증번호. 둘 다 미입력 시 발신프로필 정보로 발송되며,  `unsubscribephonenumber` 없이 `unsubscribeauthnumber`만 입력은 불가합니다. (예: 1234) |
| └pushalarm | string(1) | — | 푸시 알람 여부 (기본 Y). `N` 입력 시 수신자 단말에 푸시 알람 없이 발송됩니다.  = Y | N |
| └adult | string(1) | — | 성인용 메시지 (기본 N)  = Y | N |
| └grouptagkey | string(40) | — | 그룹 태그 키 (통계용) |
| └변수 미사용 | object | — | 템플릿 그대로 발송. 변수·본문 필드를 사용하지 않습니다. |
| └brandmessagetype | const | — | = BASIC |
| └templatecode | string(64) | — | 템플릿 코드 (기본형 필수) |
| └변수 사용 (변수 분리 방식) | object | — | 템플릿 변수를 영역별 변수 필드로 분리 전달합니다. |
| └brandmessagetype | const | — | = BASIC |
| └templatecode | string(64) | — | 템플릿 코드 (기본형 필수) |
| └messagevariable | object | — | 메시지 영역 변수 — 변수 분리 방식 (BASIC)에서 사용.  **각 key 는 템플릿에 정의한 변수명(고객사가 직접 지정), 값은 그 변수에 치환할 문자열입니다.**  TEXT/IMAGE/WIDE: 본문 / WIDE\_ITEM\_LIST: 헤더·타이틀·링크 / PREMIUM\_VIDEO: 헤더·본문 / COMMERCE: 부가정보 |
| └buttonvariable | object | — | 버튼 링크 변수. **key 는 템플릿 버튼에 정의한 변수명, 값은 치환할 링크입니다.** |
| └couponvariable | object | — | 쿠폰 링크 변수. **key 는 템플릿 쿠폰에 정의한 변수명, 값은 치환할 값입니다.**  값으로 `"ESCAPE_COUPON"` 전달 시 템플릿에 쿠폰이 있어도 말풍선에서 제외. |
| └imagevariable | array<string> | — | 이미지 변수 (IMAGE/WIDE 1개, WIDE\_ITEM\_LIST는 리스트 개수만큼). 미입력 시 템플릿 이미지 사용 |
| └videovariable | object | — | 비디오 변수 (PREMIUM\_VIDEO 전용) |
| └commercevariable | object | — | 커머스 변수 (COMMERCE 전용). **key 는 변수명, 값은 치환할 값입니다.**  가격 고정변수: `할인가격` · `정상가격` · `할인율` · `정액할인가격` |
| └carouselvariable | array<object> | — | 캐러셀 변수 배열 (CAROUSEL\_FEED/COMMERCE 전용). 인트로 변수는 배열 첫 번째에 위치. 변수 없는 캐러셀은 빈 객체 `{}`로 순서 유지 |
| └messagevariable | object | — | — |
| └buttonvariable | object | — | — |
| └imagevariable | object | — | — |
| └couponvariable | object | — | — |
| └commercevariable | object | — | — |
| └변수 사용 (전문 방식) | object | — | 본문·버튼·첨부를 전문으로 직접 구성합니다. |
| └brandmessagetype | const | — | = BASIC |
| └templatecode | string(64) | — | 템플릿 코드 (기본형 필수) |
| └message | string | — | 본문 — 전문 방식 / 자유형. 타입별 제한:   - TEXT/IMAGE: 1300자 (줄바꿈 99개, URL 형식 입력 가능) - WIDE / PREMIUM\_VIDEO: 76자 (5개) - UL/UC/UM/UA: 사용 안 함 |
| └button | array<object> | — | 버튼. 타입별 개수 제한:   - TEXT/IMAGE: 최대 5개 (쿠폰 적용 시 4개) - WIDE / WIDE\_ITEM\_LIST: 2개 - PREMIUM\_VIDEO: 1개 - COMMERCE: 1~2개 |
| └type | string | 필수 | 브랜드메시지 버튼 타입 — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격 — 브랜드메시지 버튼](https://bizppurio.github.io/bizapi#브랜드-메시지-버튼) 참조  = WL | AL | BK | MD | BC | BT | BF | AC |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └chat\_extra | string | — | 상담톡/봇 전환 시 전달할 메타정보 |
| └chat\_event | string | — | 봇 전환 시 연결할 봇 이벤트명 |
| └biz\_form\_key | integer | — | 비즈니스폼 키 (`BF` 전용) |
| └image | object | — | 이미지 요소 |
| └img\_url | string | — | KAPI 이미지 업로드 API로 사전 등록한 이미지 URL |
| └img\_link | string(1000) | — | 이미지 클릭 시 이동 URL. 미설정 시 카카오톡 내 이미지 뷰어 사용 |
| └header | string(20) | — | WIDE\_ITEM\_LIST 필수 / PREMIUM\_VIDEO 선택 (최대 20자) |
| └item | object | — | 와이드 아이템 리스트 (WIDE\_ITEM\_LIST/UL 전용, list 3~5개) |
| └list | array<object>(3~4) | 필수 | — |
| └title | string | — | 아이템 제목 — 1번째 선택(최대 25자) / 2~5번째 필수(최대 30자), 줄바꿈 1개 |
| └img\_url | string | — | 아이템 이미지 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └carousel | object | — | 캐러셀 (CAROUSEL\_FEED/UC, CAROUSEL\_COMMERCE/UA 필수). 3-레벨: `head` (인트로, UA만) / `list[]` / `tail`.   - UC: list 2～6개 - UA: 인트로 있으면 1～6, 없으면 2～6 |
| └head | object | — | 캐러셀 인트로 (CAROUSEL\_COMMERCE 전용) |
| └header | string(20) | — | 인트로 헤더 (줄바꿈 불가) |
| └content | string(50) | — | 인트로 내용 (줄바꿈 최대 2개) |
| └image\_url | string | — | 인트로 이미지 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └list | array<object>(1~6) | — | — |
| └header | string(20) | — | 캐러셀 리스트 헤더 (줄바꿈 불가) |
| └message | string(180) | — | 캐러셀 리스트 내용 (줄바꿈 최대 10개) |
| └additional\_content | string(34) | — | 부가 정보 (줄바꿈 최대 1개) |
| └attachment | object | — | 캐러셀 아이템 첨부 (버튼·이미지·쿠폰·커머스) |
| └button | array<object> | — | — |
| └type | string | 필수 | 브랜드메시지 버튼 타입 — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격 — 브랜드메시지 버튼](https://bizppurio.github.io/bizapi#브랜드-메시지-버튼) 참조  = WL | AL | BK | MD | BC | BT | BF | AC |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └chat\_extra | string | — | 상담톡/봇 전환 시 전달할 메타정보 |
| └chat\_event | string | — | 봇 전환 시 연결할 봇 이벤트명 |
| └biz\_form\_key | integer | — | 비즈니스폼 키 (`BF` 전용) |
| └image | object | — | 이미지 요소 |
| └img\_url | string | — | KAPI 이미지 업로드 API로 사전 등록한 이미지 URL |
| └img\_link | string(1000) | — | 이미지 클릭 시 이동 URL. 미설정 시 카카오톡 내 이미지 뷰어 사용 |
| └coupon | object | — | 쿠폰 요소. 링크 필수값 — 기본 쿠폰은 `url_mobile` 필수,  채널 쿠폰 URL(`alimtalk=coupon://`) 사용 시 `scheme_android`/`scheme_ios` 중 하나 필수. |
| └title | string | — | 쿠폰 제목 — 5가지 형식만 허용:   - `${숫자}원 할인 쿠폰` (1 ≤ 숫자 ≤ 99,999,999) - `${숫자}% 할인 쿠폰` (1 ≤ 숫자 ≤ 100) - `배송비 할인 쿠폰` - `${7자 이내} 무료 쿠폰` - `${7자 이내} UP 쿠폰` |
| └description | string | — | 쿠폰 설명. WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO: 최대 18자 / 그 외: 최대 12자 (줄바꿈 불가) |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL (기본 쿠폰 필수) |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme (채널 쿠폰 URL 사용 시 `scheme_ios` 와 함께 둘 중 하나 필수) |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └commerce | object | — | 커머스 요소. `discount_price` 있으면 `discount_rate` 또는 `discount_fixed` 중 하나 필수 |
| └title | string(30) | — | 상품 제목 (줄바꿈 불가) |
| └regular\_price | integer(0~99999999) | — | 정상 가격 |
| └discount\_price | integer(0~99999999) | — | 할인 후 가격 |
| └discount\_rate | integer(1~100) | — | 할인율 (1 ~ 100) |
| └discount\_fixed | integer(0~999999) | — | 정액 할인 가격 |
| └commerce | object | — | 커머스 요소. `discount_price` 있으면 `discount_rate` 또는 `discount_fixed` 중 하나 필수 |
| └title | string(30) | — | 상품 제목 (줄바꿈 불가) |
| └regular\_price | integer(0~99999999) | — | 정상 가격 |
| └discount\_price | integer(0~99999999) | — | 할인 후 가격 |
| └discount\_rate | integer(1~100) | — | 할인율 (1 ~ 100) |
| └discount\_fixed | integer(0~999999) | — | 정액 할인 가격 |
| └video | object | — | 비디오 요소 (PREMIUM\_VIDEO/UP 필수). 카카오TV URL 형식:   - `https://tv.kakao.com/v/<id>` - `https://tv.kakao.com/channel/<id>/cliplink/<id>` |
| └video\_url | string(500) | — | 카카오TV 동영상 URL |
| └thumbnail\_url | string(500) | — | 비공개 동영상의 경우 필수 |
| └coupon | object | — | 쿠폰 요소. 링크 필수값 — 기본 쿠폰은 `url_mobile` 필수,  채널 쿠폰 URL(`alimtalk=coupon://`) 사용 시 `scheme_android`/`scheme_ios` 중 하나 필수. |
| └title | string | — | 쿠폰 제목 — 5가지 형식만 허용:   - `${숫자}원 할인 쿠폰` (1 ≤ 숫자 ≤ 99,999,999) - `${숫자}% 할인 쿠폰` (1 ≤ 숫자 ≤ 100) - `배송비 할인 쿠폰` - `${7자 이내} 무료 쿠폰` - `${7자 이내} UP 쿠폰` |
| └description | string | — | 쿠폰 설명. WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO: 최대 18자 / 그 외: 최대 12자 (줄바꿈 불가) |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL (기본 쿠폰 필수) |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme (채널 쿠폰 URL 사용 시 `scheme_ios` 와 함께 둘 중 하나 필수) |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └additionalcontent | string(34) | — | 부가정보 (COMMERCE 최대 34자) |
| └자유형 (템플릿 미사용) | object | — | 템플릿 없이 본문을 직접 작성해 발송합니다(`brandmessagetype: FREE`).  `templatecode`는 사용하지 않습니다. 본문/첨부 필드는 전문 방식과 동일합니다. |
| └brandmessagetype | const | — | = FREE |
| └message | string | — | 본문 — 전문 방식 / 자유형. 타입별 제한:   - TEXT/IMAGE: 1300자 (줄바꿈 99개, URL 형식 입력 가능) - WIDE / PREMIUM\_VIDEO: 76자 (5개) - UL/UC/UM/UA: 사용 안 함 |
| └button | array<object> | — | 버튼. 타입별 개수 제한:   - TEXT/IMAGE: 최대 5개 (쿠폰 적용 시 4개) - WIDE / WIDE\_ITEM\_LIST: 2개 - PREMIUM\_VIDEO: 1개 - COMMERCE: 1~2개 |
| └type | string | 필수 | 브랜드메시지 버튼 타입 — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격 — 브랜드메시지 버튼](https://bizppurio.github.io/bizapi#브랜드-메시지-버튼) 참조  = WL | AL | BK | MD | BC | BT | BF | AC |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └chat\_extra | string | — | 상담톡/봇 전환 시 전달할 메타정보 |
| └chat\_event | string | — | 봇 전환 시 연결할 봇 이벤트명 |
| └biz\_form\_key | integer | — | 비즈니스폼 키 (`BF` 전용) |
| └image | object | — | 이미지 요소 |
| └img\_url | string | — | KAPI 이미지 업로드 API로 사전 등록한 이미지 URL |
| └img\_link | string(1000) | — | 이미지 클릭 시 이동 URL. 미설정 시 카카오톡 내 이미지 뷰어 사용 |
| └header | string(20) | — | WIDE\_ITEM\_LIST 필수 / PREMIUM\_VIDEO 선택 (최대 20자) |
| └item | object | — | 와이드 아이템 리스트 (WIDE\_ITEM\_LIST/UL 전용, list 3~5개) |
| └list | array<object>(3~4) | 필수 | — |
| └title | string | — | 아이템 제목 — 1번째 선택(최대 25자) / 2~5번째 필수(최대 30자), 줄바꿈 1개 |
| └img\_url | string | — | 아이템 이미지 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └carousel | object | — | 캐러셀 (CAROUSEL\_FEED/UC, CAROUSEL\_COMMERCE/UA 필수). 3-레벨: `head` (인트로, UA만) / `list[]` / `tail`.   - UC: list 2～6개 - UA: 인트로 있으면 1～6, 없으면 2～6 |
| └head | object | — | 캐러셀 인트로 (CAROUSEL\_COMMERCE 전용) |
| └header | string(20) | — | 인트로 헤더 (줄바꿈 불가) |
| └content | string(50) | — | 인트로 내용 (줄바꿈 최대 2개) |
| └image\_url | string | — | 인트로 이미지 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └list | array<object>(1~6) | — | — |
| └header | string(20) | — | 캐러셀 리스트 헤더 (줄바꿈 불가) |
| └message | string(180) | — | 캐러셀 리스트 내용 (줄바꿈 최대 10개) |
| └additional\_content | string(34) | — | 부가 정보 (줄바꿈 최대 1개) |
| └attachment | object | — | 캐러셀 아이템 첨부 (버튼·이미지·쿠폰·커머스) |
| └button | array<object> | — | — |
| └type | string | 필수 | 브랜드메시지 버튼 타입 — 타입별 사용 가능/필수 파라미터는 [카카오 연동 규격 — 브랜드메시지 버튼](https://bizppurio.github.io/bizapi#브랜드-메시지-버튼) 참조  = WL | AL | BK | MD | BC | BT | BF | AC |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme |
| └chat\_extra | string | — | 상담톡/봇 전환 시 전달할 메타정보 |
| └chat\_event | string | — | 봇 전환 시 연결할 봇 이벤트명 |
| └biz\_form\_key | integer | — | 비즈니스폼 키 (`BF` 전용) |
| └image | object | — | 이미지 요소 |
| └img\_url | string | — | KAPI 이미지 업로드 API로 사전 등록한 이미지 URL |
| └img\_link | string(1000) | — | 이미지 클릭 시 이동 URL. 미설정 시 카카오톡 내 이미지 뷰어 사용 |
| └coupon | object | — | 쿠폰 요소. 링크 필수값 — 기본 쿠폰은 `url_mobile` 필수,  채널 쿠폰 URL(`alimtalk=coupon://`) 사용 시 `scheme_android`/`scheme_ios` 중 하나 필수. |
| └title | string | — | 쿠폰 제목 — 5가지 형식만 허용:   - `${숫자}원 할인 쿠폰` (1 ≤ 숫자 ≤ 99,999,999) - `${숫자}% 할인 쿠폰` (1 ≤ 숫자 ≤ 100) - `배송비 할인 쿠폰` - `${7자 이내} 무료 쿠폰` - `${7자 이내} UP 쿠폰` |
| └description | string | — | 쿠폰 설명. WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO: 최대 18자 / 그 외: 최대 12자 (줄바꿈 불가) |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL (기본 쿠폰 필수) |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme (채널 쿠폰 URL 사용 시 `scheme_ios` 와 함께 둘 중 하나 필수) |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └commerce | object | — | 커머스 요소. `discount_price` 있으면 `discount_rate` 또는 `discount_fixed` 중 하나 필수 |
| └title | string(30) | — | 상품 제목 (줄바꿈 불가) |
| └regular\_price | integer(0~99999999) | — | 정상 가격 |
| └discount\_price | integer(0~99999999) | — | 할인 후 가격 |
| └discount\_rate | integer(1~100) | — | 할인율 (1 ~ 100) |
| └discount\_fixed | integer(0~999999) | — | 정액 할인 가격 |
| └commerce | object | — | 커머스 요소. `discount_price` 있으면 `discount_rate` 또는 `discount_fixed` 중 하나 필수 |
| └title | string(30) | — | 상품 제목 (줄바꿈 불가) |
| └regular\_price | integer(0~99999999) | — | 정상 가격 |
| └discount\_price | integer(0~99999999) | — | 할인 후 가격 |
| └discount\_rate | integer(1~100) | — | 할인율 (1 ~ 100) |
| └discount\_fixed | integer(0~999999) | — | 정액 할인 가격 |
| └video | object | — | 비디오 요소 (PREMIUM\_VIDEO/UP 필수). 카카오TV URL 형식:   - `https://tv.kakao.com/v/<id>` - `https://tv.kakao.com/channel/<id>/cliplink/<id>` |
| └video\_url | string(500) | — | 카카오TV 동영상 URL |
| └thumbnail\_url | string(500) | — | 비공개 동영상의 경우 필수 |
| └coupon | object | — | 쿠폰 요소. 링크 필수값 — 기본 쿠폰은 `url_mobile` 필수,  채널 쿠폰 URL(`alimtalk=coupon://`) 사용 시 `scheme_android`/`scheme_ios` 중 하나 필수. |
| └title | string | — | 쿠폰 제목 — 5가지 형식만 허용:   - `${숫자}원 할인 쿠폰` (1 ≤ 숫자 ≤ 99,999,999) - `${숫자}% 할인 쿠폰` (1 ≤ 숫자 ≤ 100) - `배송비 할인 쿠폰` - `${7자 이내} 무료 쿠폰` - `${7자 이내} UP 쿠폰` |
| └description | string | — | 쿠폰 설명. WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO: 최대 18자 / 그 외: 최대 12자 (줄바꿈 불가) |
| └url\_mobile | string(1000) | — | MOBILE 환경에서 이동할 URL (기본 쿠폰 필수) |
| └url\_pc | string(1000) | — | PC 환경에서 이동할 URL |
| └scheme\_android | string(1000) | — | ANDROID 환경, Application Custom Scheme (채널 쿠폰 URL 사용 시 `scheme_ios` 와 함께 둘 중 하나 필수) |
| └scheme\_ios | string(1000) | — | iOS 환경, Application Custom Scheme |
| └additionalcontent | string(34) | — | 부가정보 (COMMERCE 최대 34자) |
| resend | object | — | 대체 발송 설정 (본 발송 실패 시 다른 채널로 자동 전환). 채널 조합·규격은 [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |
| recontent | object | — | 대체 채널별 본문 (`resend`와 짝) — [대체 발송](https://bizppurio.github.io/bizapi#대체-발송) 참조 |

말풍선 타입 · TYPE

대상

방식

고객사 회원 › 변수 미사용 › 텍스트brandmessagetype: BASICsendtarget: marketingtargeting: M

message.json· TEXT

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

### 브랜드메시지 예시 — 전체 64개 조합

발송 대상 2 × 방식 4 × 타입 8. 인쇄/PDF 전용 선형 목록입니다.

고객사 회원 › 변수 미사용 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 미사용 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M"
    }
  }
}
```

고객사 회원 › 변수 분리 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "변수": "이름"
      },
      "buttonvariable": {
        "1": "www.bizppurio.com"
      },
      "couponvariable": {
        "1": "www.bizppurio.com",
        "상품명": "상품"
      }
    }
  }
}
```

고객사 회원 › 변수 분리 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "A": "testA"
      },
      "buttonvariable": {
        "B": "testB"
      },
      "couponvariable": {
        "C": "testC",
        "할인금액": "1234"
      },
      "imagevariable": [
        "https://{이미지}"
      ]
    }
  }
}
```

고객사 회원 › 변수 분리 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "내용": "와이드 이미지 메시지는 최대 76자(줄바꿈: 최대 1개)"
      },
      "buttonvariable": {
        "mobile링크": "http://bizppurio.com/",
        "android링크": "kakao://buttons-linkAnd",
        "ios링크": "kakao://buttons-linkIos"
      },
      "couponvariable": {
        "할인금액": "500",
        "쿠폰설명": "쿠폰설명 최대 18자"
      },
      "imagevariable": [
        "{img_url}"
      ]
    }
  }
}
```

고객사 회원 › 변수 분리 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "헤더": "헤헤더더",
        "타이틀": "타타이이틀틀",
        "링크": "www.bizppurio.com",
        "타이틀2": "타타이이틀틀2",
        "링크2": "www.bizppurio.com",
        "타이틀3": "타타이이틀틀3",
        "링크3": "www.bizppurio.com",
        "타이틀4": "타타이이틀틀4",
        "링크4": "www.bizppurio.com"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "couponvariable": {
        "할인금액": "30,000",
        "모바일링크": "www.bizppurio.com"
      },
      "imagevariable": [
        "{img_url1}",
        "{img_url2}",
        "{img_url3}",
        "{img_url4}"
      ]
    }
  }
}
```

고객사 회원 › 변수 분리 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "carouselvariable": [
        {
          "messagevariable": {
            "이름": "이름",
            "상품명": "상품명",
            "가격": "가격"
          },
          "couponvariable": {
            "할인금액": "10,000"
          }
        },
        {
          "messagevariable": {
            "이름": "이름",
            "상품명": "상품명",
            "가격": "가격"
          },
          "buttonvariable": {
            "모바일링크": "www.bizppurio.com"
          }
        }
      ]
    }
  }
}
```

고객사 회원 › 변수 분리 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "이름": "30,000",
        "상품명": "상품",
        "가격": "가격"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "videovariable": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      }
    }
  }
}
```

고객사 회원 › 변수 분리 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "messagevariable": {
        "부가정보": "부가정보"
      },
      "commercevariable": {
        "정상가격": "30000",
        "할인가격": "20000",
        "할인율": "10",
        "정액할인가격": "10"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "couponvariable": {
        "할인금액": "10",
        "모바일링크": "www.bizppurio.com"
      }
    }
  }
}
```

고객사 회원 › 변수 분리 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "carouselvariable": [
        {
          "messagevariable": {
            "헤더": "헤더",
            "내용": "내용",
            "모바일링크": "www.bizppurio.com"
          }
        },
        {
          "messagevariable": {
            "부가정보": "부가정보"
          },
          "commercevariable": {
            "상품": "키키",
            "정상가격": "100",
            "할인가격": "90",
            "할인율": "10"
          },
          "buttonvariable": {
            "모바일링크": "www.bizppurio.com",
            "모바일링크2": "www.bizppurio.com"
          },
          "couponvariable": {
            "할인금액": 20,
            "내용": "내용",
            "모바일링크": "www.bizppurio.com"
          }
        }
      ]
    }
  }
}
```

고객사 회원 › 전문 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "message": "변수\n텍스트_변수_테스트",
      "button": [
        {
          "type": "WL",
          "url_mobile": "http://www.bizppurio.com"
        }
      ],
      "coupon": {
        "title": "상품 무료 쿠폰",
        "url_mobile": "http://www.bizppurio.com"
      }
    }
  }
}
```

고객사 회원 › 전문 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "message": "브랜드메시지 이미지"
    }
  }
}
```

고객사 회원 › 전문 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "message": "와이드 이미지 메시지는 최대 76자(줄바꿈: 최대 1개)"
    }
  }
}
```

고객사 회원 › 전문 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "header": "헤더",
      "item": {
        "list": [
          {
            "title": "최대 25자(줄바꿈: 최대 1개)",
            "img_url": "{img_url1}",
            "url_mobile": "https://www.bizppurio.com",
            "scheme_android": "kakao://mainWideItem-linkAnd",
            "scheme_ios": "kakao://mainWideItem-linkIos"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url2}"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url3}",
            "url_mobile": "https://www.bizppurio.com",
            "scheme_ios": "kakao://subWideItem-linkIos"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url4}",
            "url_mobile": "https://www.bizppurio.com"
          }
        ]
      },
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://bizppurio.com"
        }
      ],
      "coupon": {
        "title": "1원 할인 쿠폰",
        "url_mobile": "http://bizppurio.com"
      }
    }
  }
}
```

고객사 회원 › 전문 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "carousel": {
        "list": [
          {
            "header": "최대 20자(줄바꿈: 불가)",
            "message": "최대 180자(줄바꿈: 최대 2개)",
            "attachment": {
              "coupon": {
                "title": "이모티콘 무료 쿠폰",
                "description": "최대 12자",
                "url_mobile": "https://bizppurio.com"
              },
              "image": {
                "img_url": "{img_url1}",
                "img_link": "https://bizppurio.com"
              }
            }
          },
          {
            "header": "최대 20자(줄바꿈: 불가)",
            "message": "최대 180자(줄바꿈: 최대 2개)",
            "attachment": {
              "image": {
                "img_url": "{img_url2}"
              }
            }
          }
        ]
      }
    }
  }
}
```

고객사 회원 › 전문 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "video": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      },
      "message": "프리미엄 동영상 메시지는 최대 76자 (줄바꿈: 최대 1개)",
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

고객사 회원 › 전문 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "image": {
        "img_url": "{img_url}"
      },
      "commerce": {
        "regular_price": 3000,
        "discount_price": 2000,
        "discount_rate": 33
      },
      "additionalcontent": "부가정보",
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ],
      "coupon": {
        "title": "10원 할인 쿠폰",
        "url_mobile": "https://www.bizppurio.com"
      }
    }
  }
}
```

고객사 회원 › 전문 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "tempXXXX",
      "targeting": "M",
      "carousel": {
        "head": {
          "header": "최대 20자 (줄바꿈: 불가)",
          "content": "최대 50자 (줄바꿈: 최대 2개)"
        },
        "list": [
          {
            "attachment": {
              "commerce": {
                "regular_price": 3000,
                "discount_price": 1000,
                "discount_rate": 67
              },
              "button": [
                {
                  "type": "WL",
                  "url_mobile": "https://www.bizppurio.com"
                }
              ],
              "image": {
                "img_url": "{img_url1}"
              }
            }
          },
          {
            "attachment": {
              "image": {
                "img_url": "{img_url2}",
                "img_link": "https://bizppurio.com"
              }
            }
          },
          {
            "attachment": {
              "button": [
                {
                  "type": "AL",
                  "url_mobile": "https://www.bizppurio.com",
                  "scheme_android": "kakao://buttons-linkAnd",
                  "scheme_ios": "kakao://buttons-linkIos"
                }
              ],
              "image": {
                "img_url": "{img_url3}"
              }
            }
          }
        ]
      }
    }
  }
}
```

고객사 회원 › 자유형 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "message": "변수\n텍스트_변수_테스트",
      "button": [
        {
          "name": "버튼",
          "type": "WL",
          "url_mobile": "http://www.bizppurio.com"
        }
      ]
    }
  }
}
```

고객사 회원 › 자유형 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "message": "브랜드메시지 이미지",
      "image": {
        "img_url": "https://{이미지}",
        "img_link": "https://{이미지링크}"
      }
    }
  }
}
```

고객사 회원 › 자유형 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "message": "메시지",
      "image": {
        "img_url": "{img_url}",
        "img_link": "http://bizppurio.com"
      }
    }
  }
}
```

고객사 회원 › 자유형 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "header": "와이드 리스트 header",
      "item": {
        "list": [
          {
            "title": "1번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "2번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "3번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "4번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          }
        ]
      },
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "http://bizppurio.com/"
        }
      ],
      "coupon": {
        "title": "1원 할인 쿠폰",
        "description": "쿠폰 상세 내용입니다.",
        "url_mobile": "http://bizppurio.com/"
      }
    }
  }
}
```

고객사 회원 › 자유형 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "carousel": {
        "list": [
          {
            "header": "1번 캐러셀 피드 헤더",
            "message": "1번 캐러셀 피드 메시지",
            "attachment": {
              "image": {
                "img_url": "{img_url}"
              },
              "coupon": {
                "description": "쿠폰 상세일까요?",
                "title": "10원 할인 쿠폰",
                "url_mobile": "https://www.bizppurio.com"
              }
            }
          },
          {
            "header": "2번 캐러셀 피드 헤더",
            "message": "2번 캐러셀 피드 메시지",
            "attachment": {
              "image": {
                "img_url": "{img_url}"
              },
              "button": [
                {
                  "name": "필수",
                  "type": "WL",
                  "url_mobile": "https://www.bizppurio.com"
                }
              ]
            }
          }
        ]
      }
    }
  }
}
```

고객사 회원 › 자유형 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "video": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      },
      "header": "헤더입니다.",
      "message": "#{이름} 님 안녕하세요, #{상품명} 할인 판매 중입니다!\n#{가격} 원에 드릴게요.",
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

고객사 회원 › 자유형 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "image": {
        "img_url": "{img_url}"
      },
      "commerce": {
        "title": "상품명입니다.",
        "regular_price": 3000,
        "discount_price": 2000,
        "discount_rate": 33
      },
      "additionalcontent": "부가정보",
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

고객사 회원 › 자유형 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "marketing",
      "targeting": "M",
      "carousel": {
        "head": {
          "header": "인트로 피드 헤더",
          "content": "인트로 피드 컨텐츠",
          "image_url": "{image_url}"
        },
        "list": [
          {
            "additional_content": "",
            "attachment": {
              "image": {
                "img_url": "{image_url}"
              },
              "commerce": {
                "title": "타이틀",
                "regular_price": 3000,
                "discount_fixed": 1000
              },
              "button": [
                {
                  "name": "버튼",
                  "type": "WL",
                  "url_mobile": "https://bizppurio.com"
                },
                {
                  "name": "버튼",
                  "type": "WL",
                  "url_mobile": "https://bizppurio.com",
                  "url_pc": "https://www.bizppurio.com"
                }
              ]
            }
          }
        ]
      }
    }
  }
}
```

채널 친구 › 변수 미사용 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 미사용 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX"
    }
  }
}
```

채널 친구 › 변수 분리 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "변수": "이름"
      },
      "buttonvariable": {
        "1": "www.bizppurio.com"
      },
      "couponvariable": {
        "1": "www.bizppurio.com",
        "상품명": "상품"
      }
    }
  }
}
```

채널 친구 › 변수 분리 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "A": "testA"
      },
      "buttonvariable": {
        "B": "testB"
      },
      "couponvariable": {
        "C": "testC",
        "할인금액": "1234"
      },
      "imagevariable": [
        "https://{이미지}"
      ]
    }
  }
}
```

채널 친구 › 변수 분리 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "내용": "와이드 이미지 메시지는 최대 76자(줄바꿈: 최대 1개)"
      },
      "buttonvariable": {
        "mobile링크": "http://bizppurio.com/",
        "android링크": "kakao://buttons-linkAnd",
        "ios링크": "kakao://buttons-linkIos"
      },
      "couponvariable": {
        "할인금액": "500",
        "쿠폰설명": "쿠폰설명 최대 18자"
      },
      "imagevariable": [
        "{img_url}"
      ]
    }
  }
}
```

채널 친구 › 변수 분리 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "헤더": "헤헤더더",
        "타이틀": "타타이이틀틀",
        "링크": "www.bizppurio.com",
        "타이틀2": "타타이이틀틀2",
        "링크2": "www.bizppurio.com",
        "타이틀3": "타타이이틀틀3",
        "링크3": "www.bizppurio.com",
        "타이틀4": "타타이이틀틀4",
        "링크4": "www.bizppurio.com"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "couponvariable": {
        "할인금액": "30,000",
        "모바일링크": "www.bizppurio.com"
      },
      "imagevariable": [
        "{img_url1}",
        "{img_url2}",
        "{img_url3}",
        "{img_url4}"
      ]
    }
  }
}
```

채널 친구 › 변수 분리 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "carouselvariable": [
        {
          "messagevariable": {
            "이름": "이름",
            "상품명": "상품명",
            "가격": "가격"
          },
          "couponvariable": {
            "할인금액": "10,000"
          }
        },
        {
          "messagevariable": {
            "이름": "이름",
            "상품명": "상품명",
            "가격": "가격"
          },
          "buttonvariable": {
            "모바일링크": "www.bizppurio.com"
          }
        }
      ]
    }
  }
}
```

채널 친구 › 변수 분리 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "이름": "30,000",
        "상품명": "상품",
        "가격": "가격"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "videovariable": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      }
    }
  }
}
```

채널 친구 › 변수 분리 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "messagevariable": {
        "부가정보": "부가정보"
      },
      "commercevariable": {
        "정상가격": "30000",
        "할인가격": "20000",
        "할인율": "10",
        "정액할인가격": "10"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "couponvariable": {
        "할인금액": "10",
        "모바일링크": "www.bizppurio.com"
      }
    }
  }
}
```

채널 친구 › 변수 분리 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "carouselvariable": [
        {
          "messagevariable": {
            "헤더": "헤더",
            "내용": "내용",
            "모바일링크": "www.bizppurio.com"
          }
        },
        {
          "messagevariable": {
            "부가정보": "부가정보"
          },
          "commercevariable": {
            "상품": "키키",
            "정상가격": "100",
            "할인가격": "90",
            "할인율": "10"
          },
          "buttonvariable": {
            "모바일링크": "www.bizppurio.com",
            "모바일링크2": "www.bizppurio.com"
          },
          "couponvariable": {
            "할인금액": 20,
            "내용": "내용",
            "모바일링크": "www.bizppurio.com"
          }
        }
      ]
    }
  }
}
```

채널 친구 › 전문 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "message": "변수\n텍스트_변수_테스트",
      "button": [
        {
          "type": "WL",
          "url_mobile": "http://www.bizppurio.com"
        }
      ],
      "coupon": {
        "title": "상품 무료 쿠폰",
        "url_mobile": "http://www.bizppurio.com"
      }
    }
  }
}
```

채널 친구 › 전문 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "message": "브랜드메시지 이미지"
    }
  }
}
```

채널 친구 › 전문 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "message": "와이드 이미지 메시지는 최대 76자(줄바꿈: 최대 1개)"
    }
  }
}
```

채널 친구 › 전문 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "header": "헤더",
      "item": {
        "list": [
          {
            "title": "최대 25자(줄바꿈: 최대 1개)",
            "img_url": "{img_url1}",
            "url_mobile": "https://www.bizppurio.com",
            "scheme_android": "kakao://mainWideItem-linkAnd",
            "scheme_ios": "kakao://mainWideItem-linkIos"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url2}"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url3}",
            "url_mobile": "https://www.bizppurio.com",
            "scheme_ios": "kakao://subWideItem-linkIos"
          },
          {
            "title": "최대 30자(줄바꿈: 최대 1개)",
            "img_url": "{img_url4}",
            "url_mobile": "https://www.bizppurio.com"
          }
        ]
      },
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://bizppurio.com"
        }
      ],
      "coupon": {
        "title": "1원 할인 쿠폰",
        "url_mobile": "http://bizppurio.com"
      }
    }
  }
}
```

채널 친구 › 전문 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "carousel": {
        "list": [
          {
            "header": "최대 20자(줄바꿈: 불가)",
            "message": "최대 180자(줄바꿈: 최대 2개)",
            "attachment": {
              "coupon": {
                "title": "이모티콘 무료 쿠폰",
                "description": "최대 12자",
                "url_mobile": "https://bizppurio.com"
              },
              "image": {
                "img_url": "{img_url1}",
                "img_link": "https://bizppurio.com"
              }
            }
          },
          {
            "header": "최대 20자(줄바꿈: 불가)",
            "message": "최대 180자(줄바꿈: 최대 2개)",
            "attachment": {
              "image": {
                "img_url": "{img_url2}"
              }
            }
          }
        ]
      }
    }
  }
}
```

채널 친구 › 전문 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "video": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      },
      "message": "프리미엄 동영상 메시지는 최대 76자 (줄바꿈: 최대 1개)",
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

채널 친구 › 전문 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "image": {
        "img_url": "{img_url}"
      },
      "commerce": {
        "regular_price": 3000,
        "discount_price": 2000,
        "discount_rate": 33
      },
      "additionalcontent": "부가정보",
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ],
      "coupon": {
        "title": "10원 할인 쿠폰",
        "url_mobile": "https://www.bizppurio.com"
      }
    }
  }
}
```

채널 친구 › 전문 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "BASIC",
      "sendtarget": "friend",
      "templatecode": "tempXXXX",
      "carousel": {
        "head": {
          "header": "최대 20자 (줄바꿈: 불가)",
          "content": "최대 50자 (줄바꿈: 최대 2개)"
        },
        "list": [
          {
            "attachment": {
              "commerce": {
                "regular_price": 3000,
                "discount_price": 1000,
                "discount_rate": 67
              },
              "button": [
                {
                  "type": "WL",
                  "url_mobile": "https://www.bizppurio.com"
                }
              ],
              "image": {
                "img_url": "{img_url1}"
              }
            }
          },
          {
            "attachment": {
              "image": {
                "img_url": "{img_url2}",
                "img_link": "https://bizppurio.com"
              }
            }
          },
          {
            "attachment": {
              "button": [
                {
                  "type": "AL",
                  "url_mobile": "https://www.bizppurio.com",
                  "scheme_android": "kakao://buttons-linkAnd",
                  "scheme_ios": "kakao://buttons-linkIos"
                }
              ],
              "image": {
                "img_url": "{img_url3}"
              }
            }
          }
        ]
      }
    }
  }
}
```

채널 친구 › 자유형 › 텍스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ut",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ut": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "message": "변수\n텍스트_변수_테스트",
      "button": [
        {
          "name": "버튼",
          "type": "WL",
          "url_mobile": "http://www.bizppurio.com"
        }
      ]
    }
  }
}
```

채널 친구 › 자유형 › 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ui",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ui": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "message": "브랜드메시지 이미지",
      "image": {
        "img_url": "https://{이미지}",
        "img_link": "https://{이미지링크}"
      }
    }
  }
}
```

채널 친구 › 자유형 › 와이드 이미지

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uw",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uw": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "message": "메시지",
      "image": {
        "img_url": "{img_url}",
        "img_link": "http://bizppurio.com"
      }
    }
  }
}
```

채널 친구 › 자유형 › 와이드 리스트

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ul",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ul": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "header": "와이드 리스트 header",
      "item": {
        "list": [
          {
            "title": "1번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "2번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "3번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          },
          {
            "title": "4번 아이템",
            "img_url": "{img_url}",
            "url_mobile": "http://bizppurio.com/"
          }
        ]
      },
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "http://bizppurio.com/"
        }
      ],
      "coupon": {
        "title": "1원 할인 쿠폰",
        "description": "쿠폰 상세 내용입니다.",
        "url_mobile": "http://bizppurio.com/"
      }
    }
  }
}
```

채널 친구 › 자유형 › 캐러셀 피드

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "uc",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "uc": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "carousel": {
        "list": [
          {
            "header": "1번 캐러셀 피드 헤더",
            "message": "1번 캐러셀 피드 메시지",
            "attachment": {
              "image": {
                "img_url": "{img_url}"
              },
              "coupon": {
                "description": "쿠폰 상세일까요?",
                "title": "10원 할인 쿠폰",
                "url_mobile": "https://www.bizppurio.com"
              }
            }
          },
          {
            "header": "2번 캐러셀 피드 헤더",
            "message": "2번 캐러셀 피드 메시지",
            "attachment": {
              "image": {
                "img_url": "{img_url}"
              },
              "button": [
                {
                  "name": "필수",
                  "type": "WL",
                  "url_mobile": "https://www.bizppurio.com"
                }
              ]
            }
          }
        ]
      }
    }
  }
}
```

채널 친구 › 자유형 › 프리미엄 동영상

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "up",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "up": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "video": {
        "video_url": "{video_url}",
        "thumbnail_url": "{thumbnail_url}"
      },
      "header": "헤더입니다.",
      "message": "#{이름} 님 안녕하세요, #{상품명} 할인 판매 중입니다!\n#{가격} 원에 드릴게요.",
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

채널 친구 › 자유형 › 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "image": {
        "img_url": "{img_url}"
      },
      "commerce": {
        "title": "상품명입니다.",
        "regular_price": 3000,
        "discount_price": 2000,
        "discount_rate": 33
      },
      "additionalcontent": "부가정보",
      "button": [
        {
          "name": "버튼명입니다.",
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ]
    }
  }
}
```

채널 친구 › 자유형 › 캐러셀 커머스

```
{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ua",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ua": {
      "senderkey": "abc123XXXXX",
      "brandmessagetype": "FREE",
      "sendtarget": "friend",
      "carousel": {
        "head": {
          "header": "인트로 피드 헤더",
          "content": "인트로 피드 컨텐츠",
          "image_url": "{image_url}"
        },
        "list": [
          {
            "additional_content": "",
            "attachment": {
              "image": {
                "img_url": "{image_url}"
              },
              "commerce": {
                "title": "타이틀",
                "regular_price": 3000,
                "discount_fixed": 1000
              },
              "button": [
                {
                  "name": "버튼",
                  "type": "WL",
                  "url_mobile": "https://bizppurio.com"
                },
                {
                  "name": "버튼",
                  "type": "WL",
                  "url_mobile": "https://bizppurio.com",
                  "url_pc": "https://www.bizppurio.com"
                }
              ]
            }
          }
        ]
      }
    }
  }
}
```

post/v3/messagetype=ntalk

### 네이버 톡톡

**실제 HTTP endpoint**: `POST /v3/message` — `type: ntalk` 네이버 톡톡 페이로드만 보여주는 채널별 문서 페이지입니다.  
통합 endpoint 와 공통 설명은 [POST /v3/message](https://bizppurio.github.io/bizapi#post-v3message) 를 참고하세요.

---

네이버 톡톡은 **사전 검수된 템플릿** 기반으로 발송합니다. 본문 구성 방식은 두 가지입니다.

| 방식 | 사용 파라미터 | 설명 |
| --- | --- | --- |
| 변수 치환 발송 | `templatecode` + `extra.templateParams` | 템플릿의 변수를 키/값 쌍으로 치환 (값 최대 150자) |
| 고정 컨텐츠 발송 | `templatecode` + `message` | 템플릿이 변환되어 발송될 최종 텍스트를 직접 입력 (최대 2,048자) |

> **사전 준비**: 네이버 톡톡 채널 개설 ([파트너센터](https://partner.talk.naver.com)) 후 발송 ID(`partnerid`)·Key(`partnerkey`) 확보
>
> **WARNING:** 네이버 톡톡은 **예약 발송이 불가**합니다 — `sendtime` 값과 무관하게 즉시 발송됩니다.

### 템플릿 타입 (`templatetype`)

| `templatetype` | 상품코드 (`productcode`) | 설명 |
| --- | --- | --- |
| `ID` | `INFORMATION` | 정보 — 기본 (Default) |
| `IG` | `INFORMATION` | 정보 — 선물전달 (Gift). `extra.attachment.gift.coupon` 사용 |
| `IT` | `INFORMATION` | 정보 — 테이블 (Table) |
| `BD` | `BENEFIT` | 혜택 — 기본 (Default) |
| `BM` | `BENEFIT` | 혜택 — LMS (Message) |
| `BC` | `BENEFIT` | 혜택 — 캐러셀 커머스/피드 (Carousel) |
| `BL` | `BENEFIT` | 혜택 — 리스트 커머스/피드 (List) |
| `CT` | `CARDINFO` | 카드 템플릿 |

### 첨부 (`extra.attachment`)

- **버튼 (`buttons`)** — 템플릿에 등록한 버튼 최대 5개. WEB\_LINK 타입은 `pcUrl`/`mobileUrl` 필수, APP\_LINK 타입은 `aOsAppScheme`/`iOsAppScheme` 필수
- **이미지** — `imageUrl`(http로 시작하는 URL) 또는 `imageHashId`(이미지 업로드 API로 발급받은 hashId, 64자)
- **선물 (`gift.coupon`)** — 선물전달 타입(`IG`) 전용. `code`·`endDate`(예: `"2024-04-10"`) 필수, `name` 미입력 시 템플릿 등록 이름 사용

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| type | string | 필수 | 메시지 데이터 타입. 채널 식별자로 사용되며, `content` 객체는 이 값과 매칭되는 키 하나만 포함합니다.  = ntalk |
| from | string(16) | 필수 | 발신 번호 |
| to | string(16) | 필수 | 수신 번호 |
| refkey | string(32) | 필수 | 고객사에서 부여한 키 (UTF-8 기준 최대 32바이트) |
| country | string(5) | — | 국가 코드 (국제 메시지 발송 시) |
| userinfo | string(50) | — | 정산용 부서 코드 |
| resellercode | string | — | 특부가사업자 식별코드 (9자리 숫자) |
| sendtime | string | — | 예약 발송 시각 (unixtime, GMT+9 기준, 최대 30일 이내) |
| content | object | 필수 | 네이버 톡톡 (`type: ntalk`) — 사전 검수된 템플릿 기반 발송. 예약 발송 불가 (즉시 발송).  템플릿 타입 표·첨부 규칙은 [네이버 톡톡 발송 페이지](https://bizppurio.github.io/bizapi#post-v3messagentalk) 를 참고하세요. |
| └ntalk | object | 필수 | — |
| └partnerid | string(40) | 필수 | 네이버 톡톡 발송 ID |
| └partnerkey | string(64) | 필수 | 네이버 톡톡 발송 Key |
| └productcode | string(64) | 필수 | 상품 코드 (`INFORMATION` / `BENEFIT` / `CARDINFO`)  = INFORMATION | BENEFIT | CARDINFO |
| └templatecode | string(64) | 필수 | 템플릿 코드 |
| └templatetype | string(2) | — | 템플릿 타입 (타입 표는 [네이버 톡톡 발송 페이지](https://bizppurio.github.io/bizapi#post-v3messagentalk) 참고)  = ID | IG | IT | BD | BM | BC | BL | CT |
| └username | string(5) | — | 전화번호 소유자 실명 |
| └groupkey | string(30) | — | 발송 그룹 키 (발송 그룹에 포함된 템플릿/파트너로 발송 시 필수) |
| └message | string(2048) | — | 템플릿이 변환되어 발송될 최종 텍스트 (고정 컨텐츠 발송 시) |
| └extra | object | — | 네이버 톡톡 추가 데이터 — 템플릿 치환 변수 + 첨부 |
| └templateParams | object | — | 템플릿에서 치환할 키/값 쌍 (값은 최대 150자) |
| └attachment | object | — | 네이버 톡톡 첨부 데이터 — 이미지 / 버튼 / 선물 |
| └imageUrl | string | — | http로 시작하는 이미지 URL |
| └imageHashId | string(64) | — | 이미지 업로드 API로 업로드한 hashId |
| └buttons | array<object>(~5) | — | 템플릿 등록한 버튼 정보 (최대 5개) |
| └buttonCode | string | 필수 | 등록 시 사용한 버튼 코드 |
| └pcUrl | string | — | PC 환경 이동 링크 (WEB\_LINK 시 필수) |
| └mobileUrl | string | — | Mobile 환경 이동 링크 (WEB\_LINK 시 필수) |
| └aOsAppScheme | string | — | Android 앱 링크 (APP\_LINK 시 필수) |
| └iOsAppScheme | string | — | iOS 앱 링크 (APP\_LINK 시 필수) |
| └gift | object | — | 선물 전달 타입 템플릿(`IG`)에서 사용 |
| └coupon | object | — | — |
| └code | string | 필수 | 쿠폰 코드 |
| └endDate | string | 필수 | 쿠폰 종료일자 (예: "2024-04-10") |
| └name | string | — | 쿠폰 이름. 미입력 시 템플릿 등록 이름 사용 |
| └publisher | string | — | 쿠폰 발급자. 미입력 시 표시되지 않음 |
| └imageUrl | string | — | 쿠폰에 표시될 이미지 URL |

```
curl -X POST "{baseUrl}/v3/message" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "refkey": "test1234",
  "type": "ntalk",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "ntalk": {
      "partnerid": "partnerid",
      "partnerkey": "partnerkey",
      "templatetype": "ID",
      "productcode": "INFORMATION",
      "templatecode": "templateXXXX",
      "extra": {
        "templateParams": {
          "orderNo": "19102387851"
        },
        "attachment": {
          "buttons": [
            {
              "buttonCode": "dailyExpressionGroup",
              "pcUrl": "https://www.yourdomain.com/order/19102387851",
              "mobileUrl": "https://m.yourdomain.com/order/19102387851"
            }
          ]
        }
      }
    }
  }
}'
```

## 파일 업로드

MMS 발송용 이미지 등록 (`/v2/file`)

post/v2/file

### MMS 파일 업로드

MMS 발송 시 첨부할 이미지를 업로드하여 `filekey`를 발급받습니다.

| 항목 | 값 |
| --- | --- |
| 확장자 | `jpg`, `jpeg` |
| 크기 | **300 KB 이하** |
| 1회 업로드 수 | **1개** |

파일은 최대 3개까지 MMS 본문에 첨부할 수 있습니다. 1회 업로드는 1개만 허용되므로 3개를 첨부하려면 업로드를 3번 호출하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | 비즈뿌리오 계정 |
| file | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/jpeg, 300KB 이하) |
| sendtime | string | — | 발송 시간 (unixtime, GMT+9, 발송 +1일 이내). 미입력 시 현재 시간. |

```
curl -X POST "{baseUrl}/v2/file" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "string",
  "file": "{binary}",
  "sendtime": "string"
}'
```

응답

200업로드 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| filekey | string(40) | 필수 | 발급된 파일 키 — MMS 발송 시 `content.mms.file[].key`에 사용 |

응답 · 200

```
{
  "filekey": "0920msg_123912934949595969"
}
```

400요청 실패. 주요 응답 코드:
- `2000` 메시지가 유효하지 않음
- `3000~3013` 인증/계정 관련 오류
- `3014` 데이터 포맷 에러

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 2000,
  "description": "invalid message",
  "refkey": "test1234"
}
```

## 전송 결과 조회

결과 재요청 및 Polling 조회/완료 처리

post/v2/report

### 전송 결과 재요청

특정 메시지의 전송 결과를 다시 요청합니다. Webhook을 받지 못했거나 누락된 경우에 사용합니다.  
결과 자체는 등록된 Webhook URL로 다시 PUSH됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | — |
| messagekey | string(32) | 필수 | 메시지 전송 응답에서 받은 `messagekey` |

```
curl -X POST "{baseUrl}/v2/report" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "messagekey": "190922175225820#ft002951seXXXXXX"
}'
```

응답

200재요청 접수 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | — |
| description | string | 필수 | — |

응답 · 200

```
{
  "code": 1000,
  "description": "Success"
}
```

400요청 실패. 주요 응답 코드:
- `2000` 메시지가 유효하지 않음
- `3000~3013` 인증/계정 관련 오류
- `3014` 데이터 포맷 에러

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 2000,
  "description": "invalid message",
  "refkey": "test1234"
}
```

post/v1/result/request

### 전송 결과 요청 (Polling)

**Polling 사용 사전 신청 필요.** 빈번한 호출은 정책에 따라 차단될 수 있습니다.

### 운영 규칙

- 1회 호출 시 **최대 1,000개** 결과 응답
- 결과 조회 후 반드시 `/v1/result/confirm`을 호출해야 동일 결과가 다시 응답되지 않습니다
- **3일** 동안 조회/완료 처리하지 않으면 결과 데이터는 제거됩니다

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | — |

```
curl -X POST "{baseUrl}/v1/result/request" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001"
}'
```

응답

200Polling 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | — |
| description | string | 필수 | — |
| report | array<object> | 필수 | — |
| └device | string | 필수 | 메시지 유형 |
| └cmsgid | string | 필수 | 메시지 키 |
| └msgid | string | 필수 | 비즈뿌리오 메시지 키 (완료 처리 시 사용) |
| └phone | string | 필수 | — |
| └media | string | 필수 | 실제 발송된 메시지 상세 유형 (Webhook MEDIA 표 참고) |
| └unixtime | string | 필수 | — |
| └result | string | 필수 | 이통사/카카오/RCS 결과 코드 |
| └to\_name | string | — | — |
| └userdata | string | — | — |
| └wapinfo | string | — | SKT/KTF/LGT/KAO |
| └telres | string | — | — |
| └teltime | string | — | — |
| └kaores | string | — | — |
| └kaotime | string | — | — |
| └rcsres | string | — | — |
| └rcstime | string | — | — |
| └retry\_flag | string | — | — |
| └resend\_flag | string | — | — |
| └refkey | string | — | — |

응답 · 200

```
{
  "code": 1000,
  "description": "success",
  "report": [
    {
      "device": "SMS",
      "cmsgid": "201027134355944sms027420XXXXXXXX",
      "msgid": "1027se_SL46760273836XXXXXXXX",
      "phone": "01012345678",
      "media": "SMS",
      "unixtime": "1603773837",
      "result": "4100",
      "userdata": "daoutech",
      "wapinfo": "SKT",
      "refkey": "test1234"
    }
  ]
}
```

400요청 실패. 주요 응답 코드:
- `2000` 메시지가 유효하지 않음
- `3000~3013` 인증/계정 관련 오류
- `3014` 데이터 포맷 에러

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 2000,
  "description": "invalid message",
  "refkey": "test1234"
}
```

post/v1/result/confirm

### 전송 결과 완료 처리 (Polling)

Polling으로 받은 결과를 처리 완료로 표시합니다.  
호출하지 않으면 동일한 결과가 다음 Polling 호출에서 계속 응답됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| account | string(20) | 필수 | — |
| msgid | array<object>(~1000) | 필수 | 비즈뿌리오 메시지 키 배열 (최대 1000개) |
| └msgid | string | 필수 | — |

```
curl -X POST "{baseUrl}/v1/result/confirm" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "account": "bizUserId001",
  "msgid": [
    {
      "msgid": "1027se_SL46760273836XXXXXXXX"
    },
    {
      "msgid": "1027se_SL46760273836XXXXXXXX"
    }
  ]
}'
```

응답

200완료 처리 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | — |
| description | string | 필수 | — |

응답 · 200

```
{
  "code": 1000,
  "description": "Success"
}
```

400요청 실패. 주요 응답 코드:
- `2000` 메시지가 유효하지 않음
- `3000~3013` 인증/계정 관련 오류
- `3014` 데이터 포맷 에러

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | integer | 필수 | 비즈뿌리오 결과 코드 |
| description | string | 필수 | — |
| refkey | string(32) | — | 요청 시 전달한 고객사 키 (가능한 경우) |

응답 · 400

```
{
  "code": 2000,
  "description": "invalid message",
  "refkey": "test1234"
}
```

## 가이드

메시지 API 활용 가이드 모음 — 대체 발송 / Rate Limit / 이미지 업로드 / 국제 / 결과 수신.

## 대체 발송

알림톡·브랜드메시지·RCS는 **수신자 단말 환경·앱 사용 여부**에 따라 도달이 보장되지 않습니다. 비즈뿌리오는 본 발송 실패 시 다른 채널로 자동 전환하는 \*\*대체 발송(RESEND)\*\*을 지원합니다.

### "재발송"의 두 가지 동작

비즈뿌리오에서 "재..." 라는 표현은 **두 가지 다른 동작**을 가리켜 혼동하기 쉽습니다. 먼저 구분합니다.

| 동작 | 키워드 | 무엇이 일어나는가 | 언제 사용 |
| --- | --- | --- | --- |
| **대체 발송 (RESEND)** | `resend.first` / `resend.second` | 본 발송 실패 시 **다른 채널**로 자동 1회 더 발송 | 알림톡/RCS 도달 보장 안 될 때 |
| **결과 재요청** | `POST /v2/report` | 보관된 결과를 **Webhook URL로 다시 PUSH** (새 발송 아님 — [결과 수신](https://bizppurio.github.io/bizapi#결과-수신) 참고) | Webhook 누락·장애 복구 |

---

### 대체 발송 (RESEND)

본 발송이 실패하면 비즈뿌리오 서버가 **자동으로** 다른 채널로 보냅니다 (고객사 코드 불필요, 1·2차까지, 추가 비용 발생).

#### 사용 패턴

| 본 발송 | 1차 대체 | 2차 대체 | 의도 |
| --- | --- | --- | --- |
| AT (알림톡) | SMS | — | 카카오톡 미사용자에게 도달 |
| AT | RCS | SMS | RCS 단말 우선, 미수신 시 SMS |
| BT (브랜드메시지) | RCS | MMS | 풍부한 표현 → 풍부한 표현 → 텍스트 |
| RCS | AT | SMS | 안드로이드 채팅+ 미지원 단말 처리 |
| RCS | BT | SMS | RCS 미지원 + 광고성 메시지 |

#### API 패턴

`resend` 객체로 대체 채널을 명시하고, `recontent`에 각 대체 채널의 페이로드를 둡니다.

#### 1차 대체만

알림톡(AT) 실패 시 SMS로 대체:

```
{
  "account": "test",
  "refkey": "test1234",
  "type": "at",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "at": {
      "senderkey": "12345",
      "templatecode": "template",
      "message": "알림톡 본문",
      "button": [
        { "name": "자세히 보기", "type": "WL", "url_mobile": "https://example.com" }
      ]
    }
  },
  "resend": { "first": "sms" },
  "recontent": {
    "sms": { "message": "SMS 대체 발송 본문" }
  }
}
```

#### 1차·2차 대체 (RCS → AT → SMS)

```
{
  "type": "rcs",
  "content": { "rcs": { "messagebaseid": "SL000000", "chatbotid": "...", "message": { "title": "...", "description": "..." } } },
  "resend": { "first": "at", "second": "sms" },
  "recontent": {
    "at": { "senderkey": "...", "templatecode": "...", "message": "AT 본문" },
    "sms": { "message": "SMS 대체 발송" }
  }
}
```

#### 지원 조합 (본 발송별)

대체 가능 조합은 본 발송 채널에 따라 정해집니다. 카카오(알림톡·브랜드)끼리 대체는 불가하며, **2차 대체는 1차가 RCS(카카오 본발송 시) 또는 카카오(RCS 본발송 시)일 때만** 가능합니다. 2차 대체는 항상 문자(`SMS`·`LMS`·`MMS`)입니다.

| 본 발송 | 1차 대체 (`resend.first`) | 2차 대체 (`resend.second`) |
| --- | --- | --- |
| 알림톡 `AT`·`AI` | `SMS`·`LMS`·`MMS` 또는 `RCS` | 1차가 `RCS`일 때만 `SMS`·`LMS`·`MMS` |
| 브랜드 `UT`~`UA` (8종) | `SMS`·`LMS`·`MMS` 또는 `RCS` | 1차가 `RCS`일 때만 `SMS`·`LMS`·`MMS` |
| `RCS` | `SMS`·`LMS`·`MMS` 또는 카카오(`AT`·`AI`·`UT`~`UA`) | 1차가 카카오일 때만 `SMS`·`LMS`·`MMS` |

각 대체 채널의 본문은 `recontent.<채널>` 에 넣습니다(미입력 시 본 발송 본문을 대체 채널 규격에 맞게 자동 변환). 자세한 사양은 [API 메시지 전송](https://bizppurio.github.io/bizapi#메시지-전송) 참고.

#### 본문 처리 (recontent)

| 시나리오 | `recontent.<type>` |
| --- | --- |
| 원본 본문 그대로 사용 | **비워두기** — 자동으로 원본 본문 사용 |
| 다른 본문 사용 | 명시 입력 |
| SMS 길이 초과 가능성 | 항상 명시 입력 (90바이트 이하로 짧게) |

> **WARNING:** **SMS 대체의 함정** — `recontent`를 비워두면 원본 본문을 사용하지만, **본문이 SMS 90바이트를 초과하면 발송되지 않습니다**. SMS 대체는 항상 짧은 본문으로 별도 작성하세요.

#### 결과 매핑

대체 발송이 실제로 발생하면 결과 리포트는 **추가 레코드**로 생성됩니다. `refkey`로 원본 트랜잭션을 매핑하고, 결과 코드 분기(`WAPINFO`(SKT/KTF/LGT/KAO) · `RESEND_FLAG`)로 어떤 채널에서 도달했는지 식별하세요.

#### 운영 권장

1. **대체 발송 활성화 신청** — 비즈뿌리오 계정에 사용 권한 사전 확인
2. **대체 본문 별도 작성** — 알림톡 1300자 본문이 SMS 90바이트로 잘리지 않게 별도 작성
3. **`refkey` 매핑** — 결과 수신 시 대체 발송 분기에 대비
4. **트래픽 비용** — 알림톡 대비 SMS는 단가가 높으므로 대체 발송율 모니터링

---

### 재발송 결정 트리

"재발송이 필요"하다는 요구사항이 들어왔을 때:

본 발송이 아직 발생하지 않았는가?예 — 설계 단계대체 발송 (RESEND) 함께 등록아니오고객사가 발송 결과(리포트)를 받지 못했는가?예 — 발송 후 35일 이내 (비즈뿌리오 보관 중)결과 재요청 — POST /v2/report예 — 35일 경과비즈뿌리오 [발송 조회]에서 확인

## Rate Limit

비즈뿌리오 API는 기준 시간 내 **호출 가능 횟수**가 제한됩니다. 제한을 초과하면 HTTP 429와 함께 `code: 5002`가 반환됩니다.

> **NOTE:** **Rate Limit 카운트 대상**: 토큰 발급 / 메시지 발송 / 결과 재요청 / 파일 업로드 — **모든 API 요청**이 카운트됩니다.

### 응답 헤더 — `RateLimit-*`

성공·실패 응답 모두 다음 헤더가 포함됩니다.

| 헤더 | 의미 |
| --- | --- |
| `RateLimit-Limit` | 기준 시간 내 최대 요청 가능 횟수 |
| `RateLimit-Remaining` | 기준 시간 내 남은 요청 가능 횟수 |
| `RateLimit-Reset` | 기준 시간 갱신까지 남은 시간 (ms) |

### 초과 시 응답

```
HTTP/1.1 429 Too Many Requests
Content-type: application/json
RateLimit-Limit: 1000
RateLimit-Remaining: 0
RateLimit-Reset: 0.299
```

```
{
  "code": 5002,
  "description": "too many requests",
  "refkey": "test1234"
}
```

### 백오프 — `RateLimit-Reset` 활용

429 응답 시 `RateLimit-Reset` 헤더 값만큼 대기 후 재시도하는 것이 가장 정확합니다.

```
response = post(...)
if response.status == 429:
    sleep(response.headers["RateLimit-Reset"] + 여유분)
    retry()
```

재시도 간격은 충분히 늘려 제한 값에 다시 도달하지 않게 하고, `RateLimit-Remaining`이 0에 가까워지면 호출 측에서 미리 속도를 낮추세요. 헤더를 활용하기 어려운 환경에서는 일반적인 지수 백오프(시도마다 대기 2배 + jitter, 상한 30초)를 적용합니다.

### 운영 권장

1. **토큰 캐싱** — 토큰은 24시간 유효하므로 매 요청마다 발급하면 Rate Limit을 빨리 소진합니다.
2. **동시성 제한** — 무제한 병렬 호출 대신 동시 호출 수를 제한하세요 (세마포어·커넥션 풀).
3. **대량 발송은 큐로 분산** — 큐에 적재 후 워커가 일정 속도로 소비하고, 429 발생 건은 재시도 큐로 복귀시키세요.

### 제한 상향 신청

기본 Rate Limit이 부족하면 비즈뿌리오 [고객센터](mailto:bizppurio@daou.co.kr)로 상향 요청 가능. 다음 정보를 함께 전달:

- 비즈뿌리오 계정 (`bizId`)
- 사용 시나리오 (트랜잭션·캠페인·채널 종류)
- 예상 일/시간당 발송 건수
- 피크 시간대

### 관련 코드

| 코드 | HTTP | 설명 | 권장 처리 |
| --- | --- | --- | --- |
| `5002` | 429 | Rate Limit 초과 | `RateLimit-Reset` 만큼 백오프 |
| `5004` | 503 | 너무 많은 커넥션 | 짧은 백오프 (1~5초) |
| `5003` | 502 | 인프라 일시 오류 | 재시도 (지수 백오프) |
| `5005` | 504 | 게이트웨이 타임아웃 | 재시도 (지수 백오프) |

전체 코드는 [BIZAPI 응답 상태 코드](https://bizppurio.github.io/response-codes) 참고.

## 이미지 업로드

MMS는 이미지를 본문에 첨부하는 메시지입니다. **파일 본체는 별도 엔드포인트로 사전 업로드**하고, MMS 발송 시에는 발급받은 **`filekey`만 참조**하는 2단계 흐름입니다.

### 흐름

① POST /v2/filefilekey 발급② POST /v3/message (mms)content.mms.file[].key = filekeytype: "IMG"③ 결과 수신

### 1단계 — 파일 업로드

### 제약

| 항목 | 값 |
| --- | --- |
| Content-Type | `multipart/form-data` |
| 확장자 | `jpg`, `jpeg` |
| 크기 | **300 KB 이하** |
| 1회 업로드 | **1개** (3장 첨부 시 3번 호출) |

### 호출

```
curl -X POST https://api.bizppurio.com/v2/file \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -F "account=bizUserId001" \
  -F "sendtime=1640962800" \
  -F "file=@1.jpg"
```

### 응답

```
{
  "filekey": "0920msg_123912934949595969"
}
```

`sendtime` 미입력 시 현재 시각으로 처리. 업로드 후 **+1일 이내**에만 사용 가능.

### 2단계 — MMS 발송

업로드 응답의 `filekey`를 `content.mms.file[].key`에 사용:

```
{
  "account": "bizUserId001",
  "refkey": "promo-2026042801",
  "type": "mms",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "mms": {
      "subject": "신상 출시 안내",
      "message": "신상품을 만나보세요!",
      "file": [
        { "type": "IMG", "key": "0920msg_123912934949595969" },
        { "type": "IMG", "key": "0920msg_123912934949595970" },
        { "type": "IMG", "key": "0920msg_123912934949595971" }
      ]
    }
  }
}
```

| 필드 | 값 |
| --- | --- |
| `content.mms.file` | 최대 **3개** |
| `content.mms.file[].type` | 현재 `IMG`만 지원 |
| `content.mms.file[].key` | `/v2/file` 응답의 `filekey` |
| `content.mms.message` | **선택** — 이미지만 발송도 가능 |

자세한 사양은 [MMS 채널](https://bizppurio.github.io/bizapi#메시지-전송) · [MMS 파일 업로드 API](https://bizppurio.github.io/bizapi#파일-업로드) 참고.

### 운영 권장 — 이미지 캐싱

같은 이미지를 반복 발송할 때마다 업로드하면 비효율적입니다.

고객사 DBimage\_id · file\_url · bizppurio\_filekey · uploaded\_at발송 시filekey가 있고 발급 +1일 이내인가?예filekey 그대로 사용 — 업로드 생략아니오POST /v2/file — 새 filekey 발급DB에 filekey · 발급시각 갱신

`filekey`는 발급 시점 기준 **+1일 이내**에만 사용 가능하므로 캐시할 수 있습니다.

### 브랜드메시지 이미지는 다른 흐름

| 채널 | 이미지 등록 방법 | 참조 필드 |
| --- | --- | --- |
| MMS | `POST /v2/file` → `filekey` | `content.mms.file[].key` |
| 브랜드메시지 (BT) | [KAPI 브랜드메시지 이미지 업로드](https://bizppurio.github.io/kapi#브랜드-이미지) (`/v4/brand/image/...`) | `content.<type>.image.img_url` |
| RCS | [RAPI 파일 등록](https://bizppurio.github.io/rapi#파일) → `fileId` | `content.rcs.message.media = "maapfile://{fileId}"` |

### 자주 발생하는 문제

| 증상 | 원인·해결 |
| --- | --- |
| `code: 3014` (데이터 포맷 에러) | `file[]`이 비어있음 — MMS는 `file` 필수 |
| `9019` 지원하지 않는 첨부파일 | 확장자 / 크기 검증 |
| `9027` MMS 첨부파일 이미지 사이즈 초과 | 300KB 초과 |
| `9017` 존재하지 않는 첨부파일 | `filekey` 만료 (1일 경과) |
| `9018` 0바이트 첨부파일 | 파일 손상 — 다시 업로드 |

## 국제 메시지

해외 수신자에게 메시지를 발송할 때는 **국가 코드**, **인코딩(GSM vs 유니코드)**, **채널별 제약**을 모두 고려해야 합니다.

### 채널별 길이 제한

| 채널 | 길이 제한 | 인코딩 |
| --- | --- | --- |
| `sms` | 최대 **140 byte** | GSM 표준 1byte/자, 유니코드 2byte/자 |
| `lms` | 최대 **420 byte** | 동일 |
| `at` (알림톡) | 한글/영문 **1000자** | — |

> **WARNING:** **SMS/LMS는 메시지에 유니코드 1자라도 포함되면 전체가 유니코드 기준으로 처리**됩니다 (140byte → 70자, 420byte → 200자).

### GSM 캐릭터 셋

`sms`/`lms` 국제 발송에서 1byte로 처리되는 문자입니다.

| 분류 | 1자 byte | 예시 |
| --- | --- | --- |
| GSM 표준 | 1 | `! " # $ % ' ( ) * + , - . / : ; < = > ? @ _`, 숫자, 영문 대소문자, `Ä Å Æ Ç É Ñ Ø ø Ü ß Ö à ä å æ è é ì ñ ò ö ù ü Δ Φ Γ Λ Ω Π Ψ Σ Θ Ξ` |
| GSM 확장 | **2** | `| ^ € { } [ ] ~ \` |

### 인코딩 예시

| 메시지 | 문자당 byte | 총 byte |
| --- | --- | --- |
| `Bonjour monde` | 1 | 13 |
| `This ^ That` | `^`만 2byte (확장) | 12 |
| `안녕하세요` | 2 (유니코드) | 10 |
| `안녕 DAOU` | 전체 유니코드 (1자라도 포함되면) | 14 |

### 수신 번호 표기 — 두 가지 방식

### 방식 1: `to`에 국가 코드 포함

```
{ "to": "00211012345678" }   // 미국(1) + 01012345678
```

`00` 또는 `+` 접두 없이 **국제 표기 그대로**.

### 방식 2: `country` 파라미터 분리

```
{
  "country": "1",
  "to": "01012345678"
}
```

이 방식이 가독성이 좋고, **알림톡**에서는 이 방식만 사용 가능합니다.

### 채널별 국제 발송 지원

| 메시지 유형 | `country` 파라미터 | 대체 발송 |
| --- | --- | --- |
| `sms` / `lms` | ✓ | — |
| `at` / `ai` | ✓ | `sms` / `lms` 대체 가능 |
| `mms` | (사양 제한) | — |
| `rcs` | 미지원 | — |
| `ntalk` | 미지원 | — |

### 운영 권장

1. **국가별 단가** — 비즈뿌리오 [고객센터](mailto:bizppurio@daou.co.kr)에 사용 국가별 단가 사전 확인
2. **본문 길이 검증** — 국제 SMS는 70자 컷 (유니코드)이 빈번하므로 호출 전 byte 계산
3. **수신 번호 정규화** — 사용자 입력에서 `+`, `-`, `()` 등 제거 후 국가 코드 분리
4. **알림톡 국제 발송** — 카카오톡 자체가 국제 사용 가능하므로 효율적이지만, **수신자가 카카오톡 사용자**여야 함
5. **시간대 고려** — `sendtime`은 한국 표준시(GMT+9) 기준 — 해외 시간대로 발송 시 환산 필요

### 발송 예시

### SMS — 미국

```
{
  "type": "sms",
  "from": "07000000000",
  "country": "1",
  "to": "01012345678",
  "content": { "sms": { "message": "Hello from Bizppurio." } }
}
```

### LMS — 일본

```
{
  "type": "lms",
  "from": "07000000000",
  "country": "81",
  "to": "9012341234",
  "content": {
    "lms": {
      "subject": "お知らせ",
      "message": "ビズプリオから日本のお客様向けのメッセージです。"
    }
  }
}
```

(일본어는 유니코드 — 200자까지)

### 알림톡 + SMS 대체 (해외)

```
{
  "type": "at",
  "country": "1",
  "to": "01012345678",
  "content": { "at": { "senderkey": "...", "templatecode": "...", "message": "..." } },
  "resend": { "first": "sms" },
  "recontent": { "sms": { "message": "Backup SMS for international." } }
}
```

## 결과 수신

비즈뿌리오 발송 결과(통신사·카카오·RCS의 도달 결과)를 받는 방법은 **Webhook**과 **Polling** 두 가지입니다.

| 방식 | 적용 | 설명 |
| --- | --- | --- |
| **Webhook (URL Push)** | API 권장 | 비즈뿌리오 → 고객사 URL로 결과 PUSH |
| **Polling** | API (옵션) | 고객사가 주기적으로 결과 조회 — 사전 신청 필요 |

---

### Webhook (URL Push) — API 권장

비즈뿌리오 서버가 결과를 고객사가 사전 등록한 URL로 PUSH합니다.

### 흐름

고객사 서버결과 수신 URL (사전 등록)비즈뿌리오 서버POST {등록 URL} — application/jsonDEVICE · CMSGID · MSGID · PHONE · MEDIA · RESULT · REFKEY …200 OK 응답

### 사전 준비

[Webhook (URL Push)](https://bizppurio.github.io/bizapi#전송-결과-조회)를 사용하려면 결과 수신 URL(IP/PORT)을 비즈뿌리오에 사전 등록해야 합니다. 비즈뿌리오 사이트 **[내 정보] → [API 관리]** 또는 고객센터로 등록 요청.

| 항목 | 값 |
| --- | --- |
| URL 예시 | `https://yourdomain.com/api/bizppurio/result` |
| 포트 | 443 / 80 외 포트는 별도 방화벽 허용 신청 필요 |

### 운영 권장

1. **HTTPS 권장** — HTTP는 결과 데이터가 평문으로 노출됨
2. **HTTP 200 OK 즉시 반환** — 처리는 비동기로 큐에 넣고 우선 200 응답
3. **멱등성** — 같은 `MSGID`가 두 번 들어와도 중복 처리되지 않도록
4. **`REFKEY`로 원본 매칭** — 고객사 내부 트랜잭션과 매칭
5. **인증** — 비즈뿌리오는 별도 인증 헤더를 보내지 않음. URL 자체에 시크릿 토큰을 포함하거나 IP 화이트리스트(고정 IP)로 보호
6. **누락 복구** — 일정 주기로 [결과 재요청](https://bizppurio.github.io/bizapi#전송-결과-조회) 호출하여 누락분 복구

### 키 컨벤션 주의

| Webhook | 모두 **대문자** (`DEVICE`, `MSGID`, `RESULT`) |
| --- | --- |
| Polling 응답 | 모두 **소문자** (`device`, `msgid`, `result`) |

같은 의미의 같은 데이터지만 키 케이스가 다릅니다. 두 방식을 함께 쓴다면 정규화 필요.

---

### Polling — API (옵션)

고객사가 주기적으로 결과를 조회하는 방식. **사전 신청 필요**.

### 흐름

고객사 서버비즈뿌리오 서버① POST /v1/result/request② 최대 1000개 결과 응답결과 처리③ POST /v1/result/confirm — 처리 완료 표시 (필수)confirm 누락 시 같은 결과가 다음 polling에서 재응답

> **WARNING:** **`/v1/result/confirm` 호출 누락**하면 같은 결과가 다음 polling에서 계속 응답됩니다. **3일** 내 처리 안 하면 결과 데이터는 제거됩니다.

### Webhook vs Polling 비교

| 항목 | Webhook | Polling |
| --- | --- | --- |
| 도달 방향 | 비즈뿌리오 → 고객사 | 고객사 → 비즈뿌리오 |
| 인프라 요구 | 공개 가능한 수신 URL 필요 | 호출 가능한 outbound만 |
| 지연 | 즉시 | 폴링 주기에 따름 |
| 사전 신청 | 필요 (URL 등록) | 필요 (Polling 사용 신청) |
| 누락 복구 | 결과 재요청으로 복구 | 정기 polling으로 복구 |
| **권장 환경** | DMZ 외부 통신 가능 | 폐쇄망·아웃바운드 only |

자세한 사양은 [전송 결과 조회](https://bizppurio.github.io/bizapi#전송-결과-조회) 참고.

---

### 결과 재요청 (`/v2/report`)

Webhook 수신 서버가 일시 다운되었거나 결과를 잃어버렸을 때, 비즈뿌리오 서버에 보관된 결과를 **Webhook URL로 다시 PUSH**하도록 요청합니다. **새 발송이 일어나지 않습니다.**

```
curl -X POST https://api.bizppurio.com/v2/report \
  -H "Authorization: Bearer ${ACCESS_TOKEN}" \
  -H "Content-type: application/json" \
  -d '{
    "account": "bizUserId001",
    "messagekey": "190922175225820#ft002951seXXXXXX"
  }'
```

응답은 단순 성공/실패이며, 실제 결과는 등록된 Webhook URL로 PUSH됩니다.

- 비즈뿌리오 보관 주기 **35일** 경과 메시지는 조회 불가 (`code: 3012`)
- 통신사로부터 결과 미수신 상태인 메시지는 조회 불가 (`code: 3013`)
- Polling 방식은 별도로 [결과 요청](https://bizppurio.github.io/bizapi#전송-결과-조회) 사용

자세한 사양은 [전송 결과 재요청](https://bizppurio.github.io/bizapi#전송-결과-조회) 참고.

---

### 결과 코드 분류

| 코드 범위 | 채널 | 의미 |
| --- | --- | --- |
| `4100` | SMS | 정상 전달 |
| `6600` | LMS/MMS | 정상 전달 |
| `7000` | 카카오 (AT/FT/BT) | 정상 |
| `8000` | RCS | 정상 |
| `5000` | NTALK | 정상 |
| 그 외 | 채널별 실패 사유 | [발송 결과 코드](https://bizppurio.github.io/response-codes) |
| `9000` 시리즈 | 공통 | [실패 사유 코드](https://bizppurio.github.io/response-codes) |

### 운영 패턴 — 단일 결과 수집 파이프라인

세 방식 모두 **일관된 결과 데이터 모델**로 통합하면 다운스트림 코드가 단순해집니다.

Webhook 수신 핸들러Polling 워커정규화 모듈키 케이스 통일코드 → 의미 매핑REFKEY 기반 트랜잭션 매핑고객사 결과 테이블

### 자주 발생하는 문제

| 증상 | 원인·해결 |
| --- | --- |
| Webhook이 도달하지 않음 | URL/IP 등록 확인, 방화벽 인바운드 허용 |
| Webhook 중복 도달 | 정상 동작 — 멱등성으로 처리 |
| Polling 결과가 항상 같음 | `/v1/result/confirm` 호출 누락 |

## 첨부 파일 규격

채널별로 지원하는 첨부파일 포맷과 크기 제한입니다.

### 채널별 지원 포맷

| 채널 | 카테고리 | 지원 포맷 |
| --- | --- | --- |
| **MMS** | Image | `jpg` |
| **FAX** | Docs | `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `hwp`, `pdf`, `txt`, `html` |
|  | Image | `bmp`, `gif`, `jpg`, `png` |

### 채널별 크기 제한

| 채널 | 항목 | 제한 |
| --- | --- | --- |
| **MMS** | 이미지 1개 | **300 KB 이하** ([MMS 파일 업로드](https://bizppurio.github.io/bizapi#파일-업로드)) |
| **MMS** | 첨부 개수 | 최대 **3개** |

### RCS 미디어

RCS는 별도의 이미지·동영상 등록 절차를 사용합니다.

| 항목 | 사양 |
| --- | --- |
| 이미지 등록 | 비즈뿌리오 사이트 [메시지관리] → [RCS 관리] → [RCS 이미지 관리] |
| 이미지 유효기간 | 등록일로부터 **365일** (자동 삭제) |
| 이미지 URL 포맷 | `maapfile://{fileId}` |
| 동영상 URL 형식 | YouTube 3가지 형식 + `,maapfile://{썸네일 fileId}` |

자세한 RCS 미디어 사양은 [RCS 채널 페이지](https://bizppurio.github.io/bizapi#메시지-전송)를 참고하세요.

## RCS 연동 규격

메시지 API(`type: rcs`)로 발송하는 RCS의 상세 규격입니다. RCS는 **안드로이드 RCS**(채팅+ 지원 단말)와 **통합 RCS**(이통 3사 표준 규격) 두 체계로 구분되며, `messagebaseid` 값으로 메시지 유형이 결정됩니다.

> **사전 준비**: ① RCS 브랜드 개설·대행사 설정 ([RCS 비즈센터](https://www.rcsbizcenter.com)) → ② RCS 브랜드 등록 ([비즈뿌리오](https://www.bizppurio.com)) → ③ 발신번호·템플릿 등록/승인

## MESSAGEBASE\_ID

`messagebaseid` 는 메시지 포맷(카드 유형)을 지정하는 코드입니다. 통합 RCS는 단말 제조사와 무관하게 국내 이통 3사에서 제공하는 RCS 표준 규격입니다(안드로이드 10 이상, iOS 26 이상 지원). 상품 타입·발송 변수는 안드로이드 RCS와 동일하지만 아래 항목이 다릅니다.

| 항목 | 안드로이드 RCS | 통합 RCS |
| --- | --- | --- |
| 수신 가능 단말 | 안드로이드 채팅+ 지원 단말 (예: 삼성 갤럭시) | 국내 이통사 RCS 연동 모든 단말 |
| (광고) 표기 | `header: "1"` 설정 | `header`는 `"0"`만 허용 (`"1"` 입력 시 실패) — 타이틀·본문에 직접 표기, 글자 수에 포함 |
| 무료수신거부 표기 | `footer` 설정 | `footer` 미사용 — 본문 끝에 직접 표기, 글자 수에 포함 |
| `copyallowed` | 지원 (메시지별 복사 가능 여부 설정) | 미지원 (단말 정책에 따름) |
| 오픈리치카드 | 지원 | 미지원 |

### 안드로이드 RCS

안드로이드 채팅+ 지원 단말(예: 삼성 갤럭시)에서 수신 가능한 표준 포맷입니다.

| MESSAGEBASE ID | 메시지 유형 | 카드(형태) | 카드 장수 | 카드별 최대 버튼 수 | 최대 본문 글자 수 |
| --- | --- | --- | --- | --- | --- |
| `SS000000` | SMS | Standalone | 1 | 1 | 100 |
| `SL000000` | LMS | Standalone | 1 | 3 | 1,300 |
| `SMwThT00` | MMS | 세로형(Tall) | 1 | 2 | 1,300 |
| `SMwThM00` | MMS | 세로형(Medium) | 1 | 2 | 1,300 |
| `CMwMhM0200` | MMS | 슬라이드형(Medium, 2장) | 2 | 2 | 글자/라인 수 정의\* |
| `CMwMhM0300` | MMS | 슬라이드형(Medium, 3장) | 3 | 2 | 글자/라인 수 정의\* |
| `CMwMhM0400` | MMS | 슬라이드형(Medium, 4장) | 4 | 2 | 글자/라인 수 정의\* |
| `CMwMhM0500` | MMS | 슬라이드형(Medium, 5장) | 5 | 2 | 글자/라인 수 정의\* |
| `CMwMhM0600` | MMS | 슬라이드형(Medium, 6장) | 6 | 2 | 글자/라인 수 정의\* |
| `CMwShS0200` | MMS | 슬라이드형(Small, 2장) | 2 | 2 | 글자/라인 수 정의\* |
| `CMwShS0300` | MMS | 슬라이드형(Small, 3장) | 3 | 2 | 글자/라인 수 정의\* |
| `CMwShS0400` | MMS | 슬라이드형(Small, 4장) | 4 | 2 | 글자/라인 수 정의\* |
| `CMwShS0500` | MMS | 슬라이드형(Small, 5장) | 5 | 2 | 글자/라인 수 정의\* |
| `CMwShS0600` | MMS | 슬라이드형(Small, 6장) | 6 | 2 | 글자/라인 수 정의\* |
| `OMHITV0001` | 신규 MMS | 이미지 & 타이틀 강조형 (3:4) | 1 | 2 | 150 |
| `OMHITS0001` | 신규 MMS | 이미지 & 타이틀 강조형 (1:1) | 1 | 2 | 150 |
| `OMHIMV0001` | 신규 MMS | 이미지 강조형 (3:4) | 1 | 2 | 150 |
| `OMHIMS0001` | 신규 MMS | 이미지 강조형 (1:1) | 1 | 2 | 150 |
| `OMTBNV0001` | 신규 MMS | 썸네일형 (세로) | 1 | 2 | 150 |
| `OMTBNH0001` | 신규 MMS | 썸네일형 (가로) | 1 | 2 | 150 |
| `OMSNSS0001` | 신규 MMS | SNS형 | 1 | 2 | 150 |
| `OMSNSH0001` | 신규 MMS | SNS형 (중간버튼) | 1 | 2 | 150 |
| `UBR.`로 시작 (템플릿별 상이) | 템플릿 | 서술(description) | 1 | 2 | 90 |
| `UBR.`로 시작 (템플릿별 상이) | 템플릿 | 스타일(cell) | 1 | 2 | 90 |
| `UBR.`로 시작 (템플릿별 상이) | 템플릿 | 기본(free) | 1 | 0 | 90 |
| `IBR.`로 시작 (템플릿별 상이) | 이미지 템플릿 | 신규 MMS 동일 (8종) | 1 | 2 | 1,000 |
| `LBR.`로 시작 (템플릿별 상이) | LMS 템플릿 | LMS 템플릿 (4종) | 1 | 2 | 1,300 |

> **템플릿(`UBR.`/`IBR.`/`LBR.`)** 은 RBC에 등록한 RCS 템플릿입니다. `UBR.`(텍스트)은 **정보성 전용**으로 고정부+변수부 합산 90자 초과 시 전송 불가, `IBR.`(이미지)는 `media` 파라미터 입력이 불필요합니다.

#### 글자수 및 라인수 정의 (슬라이드형 CMw…)

- **글자 수**: 1줄당 정상적으로 표현 가능한 글자 수 (한글 '가' 기준 측정)
- **줄(라인) 수**: expand 없이 메시지 버블 최대 크기에서 표현 가능한 description 줄 수

**LMS (Standalone, No media)** — 글자 수: 타이틀 16 / 디스크립션 18 / 버튼명 17

| 줄 수 (접힌 경우) | 버튼 0개 | 버튼 1개 | 버튼 2개 | 버튼 3개 |
| --- | --- | --- | --- | --- |
| 디스크립션 only | 28 | 26 | 24 | 22 |
| 타이틀 1줄 + 디스크립션 | 27 | 25 | 23 | 20 |
| 타이틀 2줄 + 디스크립션 | 26 | 23 | 21 | 19 |

**MMS 세로형 (Standalone, Media Top)** — 글자 수: 타이틀 16 / 디스크립션 18 / 버튼명 17

| 줄 수 — Media Tall (접힌 경우) | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 9 | 8 | 6 |
| 타이틀 1줄 + 디스크립션 | 8 | 6 | 4 |
| 타이틀 2줄 + 디스크립션 | 7 | 5 | 3 |

| 줄 수 — Media Medium (접힌 경우) | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 15 | 13 | 11 |
| 타이틀 1줄 + 디스크립션 | 14 | 12 | 10 |
| 타이틀 2줄 + 디스크립션 | 13 | 11 | 9 |

**MMS 슬라이드형 Medium (Carousel Medium)** — 글자 수: 타이틀 13 / 디스크립션 14 / 버튼명 13

| 줄 수 — Media 없음 (RCS A2P 단말 기준) | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 28 | 26 | 23 |
| 타이틀 1줄 + 디스크립션 | 27 | 25 | 23 |
| 타이틀 2줄 + 디스크립션 | 26 | 23 | 21 |
| 타이틀 3줄 + 디스크립션 | 24 | 22 | 20 |

| 줄 수 — Media Medium (RCS A2P 단말 기준) | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 17 | 15 | 13 |
| 타이틀 1줄 + 디스크립션 | 16 | 14 | 12 |
| 타이틀 2줄 + 디스크립션 | 15 | 13 | 11 |
| 타이틀 3줄 + 디스크립션 | 14 | 12 | 10 |

**MMS 슬라이드형 Small (Carousel Small)** — 글자 수: 타이틀 5 / 디스크립션 6 / 버튼명 5

| 줄 수 — Media Short (RCS A2P 단말 기준) | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 20 | 18 | 16 |
| 타이틀 1줄 + 디스크립션 | 19 | 17 | 15 |
| 타이틀 2줄 + 디스크립션 | 18 | 16 | 14 |
| 타이틀 3줄 + 디스크립션 | 17 | 15 | 13 |
| 타이틀 4줄 + 디스크립션 | 16 | 14 | 12 |
| 타이틀 5줄 + 디스크립션 | 15 | 13 | 11 |

### 통합 RCS

통합 RCS는 단말 제조사와 무관하게 국내 이통 3사에서 제공하는 RCS 표준 규격입니다.

| MESSAGEBASE ID | 상품 | 유형명 | 최대 버튼 수 | 최대 본문 글자 수 |
| --- | --- | --- | --- | --- |
| `RPSSAXX001` | RCS SMS | 통합 SMS 카드 | 1 | 100 |
| `RPLSAXX001` | RCS LMS | 통합 LMS 카드 | 3 | 1,300 |
| `RPMSMMX001` | RCS MMS | 통합 MMS 카드 M | 2 | 1,300 |
| `RPMSMTX001` | RCS MMS | 통합 MMS 카드 T | 2 | 1,300 |
| 브랜드별 자동 발급\* | 텍스트 템플릿 | 통합 프리 템플릿 | — | 90 |
| 템플릿 등록 필요 | 텍스트 템플릿 | 통합 정보성 템플릿 | — | 90 |
| 템플릿 등록 필요 | 이미지 템플릿 | 통합 이미지 템플릿 M | — | 1,000 |
| 템플릿 등록 필요 | 이미지 템플릿 | 통합 이미지 템플릿 T | — | 1,000 |

> **\* 통합 프리 템플릿**: 브랜드마다 1개 자동 발급되는 정보성 템플릿으로, 사전 등록 없이 사용합니다. messagebase ID는 RBC 템플릿 목록에서 확인하세요. 기존 발급된 프리 템플릿은 2026-07-31까지 병행 이용 가능합니다.

## MESSAGE

메시지 베이스에서 치환할 본문 객체입니다. `messagebaseid`에 따라 필드 구성이 달라집니다.

- 단일 카드: `title`, `description`, `media`
- 캐러셀: `title1`/`description1`/`media1`, `title2`/… (카드 순서대로 넘버링)
- 신규 MMS / 통합 RCS: `subTitle1`/`subDesc1`, `subMedia1`/`subMediaUrl1` 등
- 텍스트 템플릿(`UBR.~`): 템플릿 변수 키/값 자유 형태

### 이미지 첨부 media

`maapfile://{fileId}` 형식으로 입력합니다 (예: `maapfile://BR.i6dOpSm8N8.20200302150000.001`). 이미지는 비즈뿌리오 사이트의 [메시지관리 → RCS 관리 → RCS 이미지 관리]에서 등록하며, 등록일로부터 **365일간** 발송 가능합니다 (이후 자동 삭제).

### 동영상 스트리밍 첨부 media

RCS MMS는 `media` 필드에 이미지 대신 **YouTube 스트리밍 URL**을 입력해 동영상을 첨부할 수 있습니다. 아래 **3가지 형태의 YouTube URL만** 지원하며, 정확한 형식을 준수해야 합니다 (일부만 일치해도 실패).

- `https://www.youtube.com/watch?v=[videoId]`
- `https://youtu.be/[videoId]`
- `https://m.youtube.com/watch?v=[videoId]`

썸네일은 **등록된 이미지(`maapfile://{fileId}`)만** 사용 가능하며, YouTube URL 뒤에 **콤마(`,`)로 이어서** 입력합니다. 콤마 외 공백이 포함되면 실패합니다.

```
"media": "https://www.youtube.com/watch?v=[videoId],maapfile://{썸네일용 fileId}"
```

동영상 발송 시 Footer에 **'동영상 재생 시 데이터 요금제가 적용됩니다.'** 문구가 자동 삽입됩니다.

## BUTTON

`button` 은 버튼 배열입니다(캐러셀은 카드별 객체, 버튼 없는 카드는 `{}`로 순서 유지). 각 `suggestions[].action` 은 아래 7종 중 정확히 1개만 포함하며, `displayText`(출력 텍스트)와 선택적 `postback.data`(챗봇 콜백)를 가집니다.

| # | Action | 동작 | 중첩 필드 |
| --- | --- | --- | --- |
| 1 | `urlAction` | URL 연결 | `openUrl` → `url` |
| 2 | `dialerAction` | 전화 걸기 | `dialPhoneNumber` → `phoneNumber` |
| 3 | `mapAction` | 지도 보여주기 | `showLocation` → `location`(`latitude`/`longitude`/`label`) |
| 4 | `mapAction` | 위치 공유 | `requestLocationPush` |
| 5 | `composeAction` | 메시지 전송 | `composeTextMessage` → `phoneNumber`/`text` |
| 6 | `calendarAction` | 캘린더 등록 | `createCalendarEvent` → `startTime`/`endTime`/`title`/`description` |
| 7 | `clipboardAction` | 복사 | `copyToClipboard` → `text` |

필드 레벨 스키마·예시는 [RCS 발송](https://bizppurio.github.io/bizapi#post-v3messagercs)의 요청 본문을 참고하세요.

## 카카오 연동 규격

참고

알림톡과 브랜드메시지는 사용 가능한 버튼 타입이 다릅니다. 각 발송 페이지에서 이 규격을 참조합니다.

### 알림톡 버튼

| type | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| WL | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| AL | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| DS | 버튼 클릭 시 배송조회 페이지로 이동 | name type | name type |
| BK | 해당 버튼 텍스트 발송 | name type | name type |
| MD | 해당 버튼 텍스트 + 메시지 본문 발송 | name type | name type |
| BC | 상담톡을 이용하는 카카오톡 채널만 이용 가능 | name type chat\_extra | name type |
| BT | 카카오 i 오픈빌더의 챗봇을 사용하는 카카오톡 채널만 이용 가능 | name type chat\_extra chat\_event | name type |
| AC | 버튼 클릭 시 카카오톡 채널 추가 | name type | name type |
| P1 | 이미지 보안 전송 플러그인 | name type | name type |
| P2 | 개인정보이용 플러그인 | name type | name type |
| P3 | 원클릭 결제 플러그인 | name type | name type *(다음 중 1가지 이상)* oneclick\_id product\_id |
| BF | 카카오 비즈니스폼을 실행 | name type biz\_form\_id | name type biz\_form\_id |
| TN | 전화 앱 실행 모바일 환경에서만 이용 가능 | name type tel\_number | name type tel\_number |
| MP | 버튼 클릭 시 지도 보기 | name type map\_address map\_coordinates | name type *(다음 중 1가지 이상)* map\_address map\_coordinates |

### 알림톡 바로연결

바로연결은 `WL`·`AL`·`BK`·`BC`·`BT`·`BF` 6종만 지원합니다.

| type | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| WL | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| AL | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| BK | 해당 버튼 텍스트 발송 | name type | name type |
| BC | 상담톡을 이용하는 카카오톡 채널만 이용 가능 | name type chat\_extra | name type |
| BT | 카카오 i 오픈빌더의 챗봇을 사용하는 카카오톡 채널만 이용 가능 | name type chat\_extra chat\_event | name type |
| BF | 카카오 비즈니스폼을 실행 | name type biz\_form\_id | name type biz\_form\_id |

### 브랜드메시지 선택 가이드

브랜드메시지 8종 타입(UT/UI/UW/UL/UC/UM/UP/UA)은 동일한 페이로드 구조를 사용하며, **4가지만 정하면** 발송할 수 있습니다.

STEP 1. 누구에게?

| 대상 | sendtarget | targeting | 사전 준비 |
| --- | --- | --- | --- |
| 고객사 회원(광고 수신동의) | `marketing` | **필수** (M/N/O) | 발송 권한 신청 |
| 채널 친구 | `friend` | 미사용 | 발신프로필 등록 |

STEP 2. 템플릿을 쓰나?

| 형태 | brandmessagetype | templatecode | 다음 |
| --- | --- | --- | --- |
| 기본형(템플릿 O) | `BASIC` | 필수 | STEP 3 |
| 자유형(템플릿 X) | `FREE` | 없음 | STEP 4 (본문 직접) |

STEP 3. (기본형) 변수·본문 작성 방식

발송 페이지 스키마 탭에서 선택합니다.  
**· 변수 미사용**(BASIC): 변수 필드 생략  
**· 변수 분리**(BASIC): `*variable`(messagevariable / buttonvariable / couponvariable / imagevariable / videovariable / commercevariable / carouselvariable) 사용  
**· 전문 방식**(BASIC): message / button / coupon / image / header / item / carousel / commerce / video / additionalcontent 직접 구성

STEP 4. 자유형(FREE)

템플릿·`templatecode` 없이 본문을 직접 구성합니다(본문 필드는 전문 방식과 동일).

### 브랜드메시지 타겟팅

고객사 회원 대상

고객사 회원 대상은 **사전 발송 권한 신청**이 필요합니다. `targeting` 값(M/N/O)으로 광고 수신동의 회원과 채널 친구의 교집합 범위를 지정합니다.

고객사발송 대상채널친구M고객사발송 대상채널친구N고객사발송 대상채널친구O

**M — 고객사의 광고성 정보 수신동의 회원**: 광고성 정보 수신동의 회원(카카오톡 수신 동의) 전체에 발송합니다. 무료수신거부 정보는 발송 채널의 발신프로필에서 등록·관리합니다.  
**N — 수신동의 회원 − 채널 친구**: 수신동의 회원에서 채널 친구를 **제외**하고 발송합니다.  
**O — 수신동의 회원 ∩ 채널 친구**: 수신동의 회원 중 **채널 친구인 경우에만** 발송합니다. 채널의 수신거부 방법으로 080 무료수신거부 번호를 안내합니다.

채널 친구 대상

채널 친구 대상은 발신프로필 등록 후 바로 발송할 수 있습니다. 채널 친구 중 고객사의 발송 요청 대상에만 발송하며, 채널의 수신거부 방법으로 채널 차단 정보를 안내합니다.

### 브랜드메시지 타입별 구성

타입별로 실제 메시지에 그려지는 구성 요소와 배치입니다(고객사 회원 대상·채널 친구 대상 동일). 각 박스의 이름은 전문 방식 본문 필드명입니다. 이미지·동영상은 발송 전 사전 등록이 필요합니다.

텍스트 UT · TEXT

message

attachment.button

attachment.coupon

- 메시지 필수 (최대 1,300자)
- 버튼·쿠폰 선택

이미지 UI · IMAGE

attachment.image

message

attachment.button

attachment.coupon

- 이미지·메시지 필수 (메시지 최대 1,300자)
- 버튼·쿠폰 선택
- KAPI 이미지 사전 등록

와이드 이미지 UW · WIDE

attachment.image  
(와이드)

message

attachment.button

attachment.coupon

- 와이드 이미지·메시지 필수 (메시지 최대 76자)
- 버튼·쿠폰 선택
- KAPI 이미지 사전 등록

와이드 아이템 리스트 UL · WIDE\_ITEM\_LIST

header

1번  
attachment.item.list

2번 · item.list

3번 · item.list

4번 · item.list

attachment.button

attachment.coupon

- 헤더·아이템 리스트(최소 3, 최대 4) 필수
- 버튼·쿠폰 선택
- KAPI 이미지 사전 등록

커머스 UM · COMMERCE

attachment.image

attachment.commerce

additional\_content

attachment.button

attachment.coupon

- 커머스 이미지·커머스 요소 필수
- (커머스 요소 중 title·regular\_price 필수)
- 부가 정보·버튼·쿠폰 선택
- KAPI 이미지 사전 등록

프리미엄 동영상 UP · PREMIUM\_VIDEO

attachment.video

header

message

attachment.button

attachment.coupon

- 비디오 필수
- 헤더·메시지·버튼·쿠폰 선택
- 비즈뿌리오 웹 또는 KAPI에서 비디오 사전 등록

캐러셀 피드 UC · CAROUSEL\_FEED

1번  
carousel  
.list

2번  
carousel  
.list

···

더보기  
carousel  
.tail

- 캐러셀 리스트(최소 2, 최대 6) 필수
- 더보기 선택
- KAPI 이미지 사전 등록

캐러셀 커머스 UA · CAROUSEL\_COMMERCE

인트로  
carousel  
.head

1번  
carousel  
.list

···

더보기  
carousel  
.tail

- 캐러셀 리스트 필수 · 인트로·더보기 선택
- 인트로 사용 시 리스트 1~5개, 미사용 시 2~6개
- KAPI 이미지 사전 등록

### 브랜드메시지 버튼

알림톡과 달리 `AC`·`WL`·`AL`·`BK`·`MD`·`BC`·`BT`·`BF` 8종만 지원하며, 비즈니스폼은 `biz_form_key`(알림톡은 `biz_form_id`)를 사용합니다.

| type | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| AC | 버튼 클릭 시 카카오톡 채널 추가 강조형 버튼(노란색)으로 표기 name은 '채널 추가' 고정 캐러셀형은 전체 1개만 가능 타겟팅 M·N만 사용 가능 | name type | name type |
| WL | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| AL | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| BK | 해당 버튼 텍스트 발송 | name type | name type |
| MD | 해당 버튼 텍스트 + 메시지 본문 발송 | name type | name type |
| BC | 상담톡을 이용하는 카카오톡 채널만 이용 가능 | name type chat\_extra | name type |
| BT | 카카오 i 오픈빌더의 챗봇을 사용하는 카카오톡 채널만 이용 가능 | name type chat\_extra chat\_event | name type |
| BF | 카카오 비즈니스폼을 실행 강조형 버튼(노란색) name은 '톡에서 예약/설문/응모하기' 중 사용 | name type biz\_form\_key | name type biz\_form\_key |

### 브랜드메시지 타입별 사용 필드

기본형(`BASIC`) 메시지에서 변수 영역을 채우는 방식은 **변수 분리 방식**과 **전문 방식** 두 가지입니다. 변수가 존재하는 영역의 정보만 전달하며, 고객사 회원 대상·채널 친구 대상이 동일합니다.

| 메시지 타입 | 변수 분리 방식 | 전문 방식 |
| --- | --- | --- |
| TEXT | messagevariable buttonvariable couponvariable | message button coupon |
| IMAGE | messagevariable buttonvariable couponvariable imagevariable | message button coupon image |
| WIDE | messagevariable buttonvariable couponvariable imagevariable | message button coupon image |
| WIDE\_ITEM\_LIST | messagevariable buttonvariable couponvariable imagevariable | header item.list button coupon |
| CAROUSEL\_FEED | carouselvariable[] *(내부: message/button/coupon/image variable)* | carousel.list[].header carousel.list[].message carousel.list[].attachment |
| PREMIUM\_VIDEO | messagevariable buttonvariable couponvariable videovariable | header message video button coupon |
| COMMERCE | messagevariable buttonvariable couponvariable commercevariable imagevariable | additionalcontent button coupon commerce image |
| CAROUSEL\_COMMERCE | carouselvariable *(인트로/리스트 각 variable)* | carousel.head carousel.list[].additional\_content carousel.list[].attachment |

변수 분리 방식 vs 전문 방식 — 예시 비교 (COMMERCE)

같은 COMMERCE 메시지를 두 방식으로 작성한 예시입니다. 공통 envelope와 `brandmessagetype: BASIC`·`templatecode`는 동일하며, **`content.<type>` 안의 변수 영역 작성 방식만 다릅니다.**

**① 변수 분리 방식** — `*variable` 맵에 "템플릿 변수명": "치환값" 형태로 변수 영역만 전달합니다.

① 변수 분리 방식

```
{
  "account": "test",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "test",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "template",
      "targeting": "M",
      "messagevariable": {
        "부가정보": "부가정보"
      },
      "commercevariable": {
        "정상가격": "30000",
        "할인가격": "20000",
        "할인율": "10",
        "정액할인가격": "10"
      },
      "buttonvariable": {
        "모바일링크": "www.bizppurio.com"
      },
      "couponvariable": {
        "할인금액": "10",
        "모바일링크": "www.bizppurio.com"
      }
    }
  }
}
```

**② 전문 방식** — 변수명 대신 실제 본문 필드(`additionalcontent`/`image`/`commerce`/`button`/`coupon`)를 직접 구성합니다.

② 전문 방식

```
{
  "account": "test",
  "refkey": "test1234",
  "type": "um",
  "from": "07000000000",
  "to": "01012345678",
  "content": {
    "um": {
      "senderkey": "test",
      "brandmessagetype": "BASIC",
      "sendtarget": "marketing",
      "templatecode": "template",
      "targeting": "M",
      "additionalcontent": "부가정보",
      "image": {
        "img_url": "{img_url}"
      },
      "commerce": {
        "regular_price": 30000,
        "discount_price": 20000,
        "discount_rate": 10
      },
      "button": [
        {
          "type": "WL",
          "url_mobile": "https://www.bizppurio.com"
        }
      ],
      "coupon": {
        "title": "10원 할인 쿠폰",
        "url_mobile": "https://www.bizppurio.com"
      }
    }
  }
}
```

TIP

언제 무엇을? — 사전 등록한 템플릿의 변수만 치환할 때는 변수 분리 방식(키가 곧 템플릿 변수명)이 간결합니다. 본문 구성요소를 직접 제어해야 할 때는 전문 방식을 사용합니다.


---

# BIZCLIENT

> 원본: [https://bizppurio.github.io/bizclient/](https://bizppurio.github.io/bizclient/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

BIZCLIENTv5.2.6

# 비즈뿌리오 BIZCLIENT

## 공통 사항

**BIZCLIENT 아키텍처** — 큐 테이블 기반 동작 모델과 테이블 구조

### 동작 모델

고객사 애플리케이션BIZ\_MSG큐 테이블 (고객사 DB)BIZCLIENT 모듈비즈뿌리오 서버38300 / 38400 outbound① INSERT② 폴링전송③ 결과 수신④ 업데이트 (STATUS=2 · CALL\_STATUS)⑤ 완료 후 이동BIZ\_LOG\_YYYYMM월별 로그 테이블

1. 고객사 애플리케이션이 발송 데이터를 **`biz_msg`** 테이블에 INSERT (`STATUS = 0`, 발송 대기)
2. BIZCLIENT 모듈이 큐를 폴링하여 비즈뿌리오 서버로 메시지 전송
3. 비즈뿌리오 서버가 발송 결과를 BIZCLIENT 모듈로 회신
4. BIZCLIENT 모듈이 `biz_msg` 의 `CALL_STATUS` 를 기록하고 `STATUS = 2` 로 변경 (비즈뿌리오 서버가 `biz_msg` 를 직접 갱신하지 않음)
5. 처리 완료된 레코드는 월별 로그 테이블 **`BIZ_LOG_YYYYMM`** 으로 이동

### 자동 생성되는 테이블

테이블 생성 권한이 있다면 BIZCLIENT 가 다음 테이블들을 자동 생성:

| 테이블 | 용도 |
| --- | --- |
| `biz_msg` | 메시지 발송 큐 (`STATUS=2` 까지 데이터 존재) |
| `BIZ_LOG_YYYYMM` | 월별 로그 — 발송 완료 데이터 이동 |
| `BIZ_RCS` | RCS 발송 큐 (RCS 사용 시) |
| `BIZ_RCS_LOG_YYYYMM` | RCS 월별 로그 |
| `BIZ_ATTACHMENTS` | 첨부파일 데이터 (`FILE_HANDLING_MODE` 설정 시) |
| `BIZ_ATTACHMENTS_LOG_YYYYMM` | 첨부파일 월별 로그 |

> ℹ️ **RCS 사용 활성화** — 기본값에서는 RCS 테이블이 생성되지 않습니다. `uds.conf` 에 `MESSAGE_SUPPORT_TYPE = ALL` 을 추가해야 RCS 테이블이 생성됩니다.

### 백업 옵션

| 옵션 | 기본값 | 설명 |
| --- | --- | --- |
| `RCS_BACKUP_OPTION` | `N` | `Y` 시 RCS 데이터를 로그 테이블로 이동, 미설정 시 `BIZ_RCS` 데이터 재사용 |
| `ATTACHMENTS_BACKUP_OPTION` | `N` | 첨부파일 테이블 동작도 동일 |

### 테이블 간 관계

```
biz_msg.RCS_REFKEY  ──참조──▶  BIZ_RCS.REFKEY      (RCS 메시지의 본문/버튼)
biz_msg.CMID + ATTACHED_FILE  ──참조──▶  BIZ_ATTACHMENTS.MSG_KEY + SEQ
                                        (첨부파일 테이블 모드 사용 시)
```

### 데이터 식별 키

- **`CMID`** — 고객사가 부여하는 고유 키 (Primary Key, ASCII 문자만, 중복 불가)
- **`UMID`** — 비즈뿌리오 서버가 부여하는 메시지 키 (대체 발송의 매핑 키로도 사용)
- **`CINFO`** — Client Indexed Info, 사내 부서/팀 구분용. [bizppurio.com](http://bizppurio.com) [발송 조회] 의 SUB ID 에 표시

> ⚠️ `CMID` 중복 시 발송은 진행되지만 **결과 리포트 반영이 정상적이지 않을 수 있습니다**. 반드시 고유한 값을 사용하세요.

## 사전 준비

BIZCLIENT 모듈 도입 전 준비 사항 — 사전 점검 / 설치 / 연동 / 용어.

## 사전 점검

**BIZCLIENT 발송 선결 조건** — 발송 전에 필요한 계정·채널·브랜드 등록

### 1. 비즈뿌리오 계정

| 항목 | 절차 |
| --- | --- |
| 회원 가입 | [www.bizppurio.com](https://www.bizppurio.com) |
| 서비스 사용 승인 요청 | 전화 1599-9782 / 이메일 [bizppurio@daou.co.kr](mailto:bizppurio@daou.co.kr) |

### 2. 카카오톡 비즈메시지 (AT/BT 사용 시)

| 단계 | 위치 |
| --- | --- |
| 카카오톡 채널 개설 및 비즈니스 채널 신청 | [카카오 비즈니스](https://center-pf.kakao.com) |
| 발신프로필 키 생성 | [비즈뿌리오 사이트](https://bizppurio.com) |
| 알림톡 템플릿 등록·승인 | [비즈뿌리오 사이트](https://bizppurio.com) (알림톡 사용 시 필수) |
| 브랜드메시지 이미지 등록 | [KAPI](https://bizppurio.github.io/kapi#브랜드-이미지) (브랜드메시지에 이미지 삽입 시) |

### 3. RCS (RCS 사용 시)

| 단계 | 위치 |
| --- | --- |
| RCS 브랜드 개설 및 대행사 설정 | [RCS 비즈센터](https://www.rcsbizcenter.com) |
| RCS 브랜드 등록 | [비즈뿌리오 사이트](https://bizppurio.com) |
| RCS 발신번호 등록 | [비즈뿌리오 사이트](https://bizppurio.com) |
| RCS 템플릿 등록·승인 | [비즈뿌리오 사이트](https://bizppurio.com) (템플릿 사용 시 필수) |

## 설치

**BIZCLIENT 설치 및 구동**

### 시스템 요구사항

| 항목 | 사양 |
| --- | --- |
| 운영체제 | JDK 1.8 이상 운용 가능한 OS |
| CPU | 2 GHz 이상 듀얼코어 또는 그 이상의 코어 |
| 메모리 | 4 GB 이상 RAM 권장 |
| 디스크 공간 | SMS 100만 건 전송 시 약 400 MB 사용 |
| JDK | **1.8 이상** |
| DBMS | MySQL / Oracle / MSSQL (정식 지원). Sybase / DB2 / DB2 AS400 / CUBRID / EDB / PostgreSQL (버전 따라 지원) |

### 방화벽 오픈

| 환경 | 도메인:포트 | 방향 |
| --- | --- | --- |
| 운영 | `bizppurio.com:38300, 38400` | Outbound |
| 검수 | `biztest.ppurio.com:38300, 38400` | Outbound (2025-08-14 기준 미지원) |

> ⚠️ **기존 BIZCLIENT 구동 여부 확인** — 한 비즈뿌리오 계정을 여러 클라이언트 모듈에 세팅할 경우 리포트가 분산되어 정상 반영되지 않을 수 있습니다. **하나의 모듈에 하나의 계정** 을 권장.

### DBMS / JDBC 드라이버

- DBMS 의 버전에 따라 JDBC 드라이버의 JDK 요구사항이 달라질 수 있음
- 사용하는 DBMS 에 맞게 JDBC 드라이버 교체 위치: `{모듈위치}/lib/jdbc/{드라이버}.jar`
- 낮은 버전의 DBMS 를 지원하기 위해 기본 JDBC 드라이버는 버전이 낮게 세팅됨 — 운영 시 사용하는 DBMS 버전에 맞는 드라이버로 교체 필요

| DBMS | 비고 |
| --- | --- |
| Microsoft SQL Server 2008 이상 | `uds.conf` 에 `DBNAME=MSSQL2005` 로 설정 (내부적으로 Microsoft JDBC Driver 4.1 사용, JDK 1.7 이상 필요) |

### 설치 및 구동 (Linux/Unix)

```
# 1) 업로드한 모듈 압축 해제
unzip biz_client_v5xxx.zip

# 2) 환경 설정 파일 수정: {모듈경로}/config/uds.conf
#    - 계정    : UDS_ID / UDS_PW            (bizppurio.com 계정)
#    - DB 연결 : DBNAME / DBURL / DBUSER / DBPASS
#      예) DBNAME=MYSQL   (MYSQL / MSSQL2005 / ORACLE 등)
#          DBURL=jdbc:mysql://localhost:3306/bizclient

# 3) 시작 / 중지: {모듈경로}/script
cd {모듈경로}/script
./biz_start        # 시작
./biz_stop         # 중지
```

### 설치 및 구동 (Windows)

```
# 1) 업로드한 모듈 압축 해제
unzip biz_client_v5xxx.zip

# 2) 환경 설정 파일 수정: {모듈경로}\config\uds.conf   (Linux 와 동일 — 계정·DB 연결 정보)

# 3) 서비스 등록 / 시작 / 중지: {모듈경로}\bat
cd {모듈경로}\bat
service-install.bat      # Windows 서비스 등록
service-start.bat        # 시작 (서비스 상태가 '시작' 으로 변경)
service-stop.bat         # 중지
service-uninstall.bat    # 서비스 제거
```

서비스 등록 후 **제어판 → 관리도구 → 서비스 → "Daoutech BizClient"** 에서도 직접 시작/종료할 수 있습니다.

### RCS 발송 활성화 (선택)

기본값은 RCS 미지원. RCS 를 사용하려면 `uds.conf` 에 다음을 추가:

```
MESSAGE_SUPPORT_TYPE = ALL
```

설정 후 BIZCLIENT 를 재시작하면 `BIZ_RCS` 테이블이 자동 생성됨.

## 연동

**BIZCLIENT 고객시스템 연동 구성** — `biz_msg` / `BIZ_RCS` / `BIZ_ATTACHMENTS` 테이블의 전체 컬럼 정의

BIZCLIENT 는 고객사 데이터베이스에 큐 테이블을 생성하고, 고객사 애플리케이션이 발송 데이터를 INSERT 하면 모듈이 이를 감지하여 비즈뿌리오 서버로 전송하는 방식으로 동작합니다.

### biz\_msg 컬럼

### 공통 컬럼 (모든 메시지 타입)

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `CMID` | String(32) | Y | 데이터 ID, 고유한 값. Primary Key. **기본 ASCII 문자만** (옵션으로 확장 가능) | — |
| `UMID` | String(32) | N | 비즈뿌리오 서버에서 정의한 MESSAGE ID | — |
| `MSG_TYPE` | Number(1) | Y | SMS=0 / FAX=2 / PHONE=3 / MMS=5 / AT=6 / RCS=8 / BT=9 (LMS는 5 + `ATTACHED_FILE` 비어있을 때) | `0` |
| `STATUS` | Number(1) | Y | 발송 상태 — [발송 용어 — STATUS](https://bizppurio.github.io/bizclient#발송상태변화-status) 참고 | `0` |
| `REQUEST_TIME` | Datetime | Y | 데이터 등록 시간 | `NOW()` |
| `SEND_TIME` | Datetime | Y | 발송 기준 시간 (예약 발송 시 미래) | `NOW()` |
| `REPORT_TIME` | Datetime | N | 단말기 수신 시간 | — |
| `DEST_PHONE` | String(16) | Y | 수신번호 | — |
| `DEST_NAME` | String(32) | N | 수신자명 | — |
| `SEND_PHONE` | String(16) | Y | 발신자번호 (사전 등록 필수) | — |
| `SEND_NAME` | String(32) | N | 발신자명 | — |
| `MSG_BODY` | String(4000) | Y | 메시지 내용 | — |
| `CALL_STATUS` | String(4) | N | 발송 결과 리포트 ([발송 결과 코드](https://bizppurio.github.io/response-codes) 참조) | — |
| `TEL_INFO` | String(10) | N | 통신사 정보 (SKT/KTF/LGT/KKO) | — |
| `CINFO` | String(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 | — |

### 채널별 전용 컬럼

**FAX/MMS 전용**

- `SUBJECT` String(64) — 제목

**AT/BT (브랜드메시지) 전용**

- `NATION_CODE` String(5) Y — 국가코드 (한국 = 82)
- `SENDER_KEY` String(40) Y — 발신프로필 키
- `TEMPLATE_CODE` String(64) Y — 템플릿 코드

**AT 전용**

- `RESPONSE_METHOD` String(8) — 발송 방식 (`PUSH`)

**AT 전용 (대체 발송)**

- `TIMEOUT` String(4) — 대체 발송용 타임아웃 시간
- `RE_TYPE` String(3) — 대체 발송 메시지 타입 ([발송 용어 — 대체발송타입](https://bizppurio.github.io/bizclient#대체발송타입-retype) 참조)

**AT/BT/RCS 공용 (대체 발송)**

- `RE_BODY` String(4000) — 대체 발송 메시지 내용
- `RE_PART` String(1) — 대체 발송 처리 주체 (`C`: CLIENT, `S`: Server)

**FAX 전용**

- `COVER_FLAG` Number(1) — 표지 발송 옵션 (0/1, 기본 0)
- `USE_PAGE` Number(2) — 발송 페이지 수 (기본 0)

**PHONE 전용**

- `SMS_FLAG` Number(1) — 실패 시 문자 전송 옵션 (기본 0)
- `REPLY_FLAG` Number(1) — 시나리오 답변 기능 (`Y`:1, `N`:0, 기본 0)
- `VXML_FILE` String(64) — 음성 시나리오 파일 이름
- `USE_TIME` Number(4) — 발송 소요 시간 (초, 기본 0)
- `SN_RESULT` Number(1) — 설문 조사 응답 값 (`0~9`, 기본 0)

**FAX/PHONE 공용**

- `RETRY_CNT` Number(4) — 재시도 횟수

**MMS/FAX/PHONE/AT 공용**

- `ATTACHED_FILE` String(1000) — [기본 MODE] 첨부파일명 (여러 개는 `\|` 구분) / [첨부파일 테이블 MODE] 첨부파일 테이블 KEY

**legacy (적용 채널 없음 — 과거 친구톡 전용, v5.2.4 친구톡 제거)**

- `USER_KEY` String(30) — 옐로아이디 봇 사용자 식별키
- `AD_FLAG` String(1) — 광고성 메시지 표기 노출 여부 (`Y`/`N`, 기본 `Y`)

**RCS 전용**

- `RCS_REFKEY` String(32) — `BIZ_RCS` 테이블의 `REFKEY` 참조

### BIZ\_RCS 컬럼

RCS 메시지의 본문·버튼을 별도 테이블에 등록. `biz_msg.RCS_REFKEY` 가 이 테이블의 `REFKEY` 를 참조.

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `REFKEY` | String(32) | Y | 테이블 참조키 (Primary Key) | — |
| `CHATBOT_ID` | String(40) | Y | RCS 비즈센터 챗봇 ID | — |
| `HEADER` | String(1) | Y | 메시지 상단 식별 문구 (`0`: Web 발신, `1`: 광고). 통합 RCS 는 `0` 만 허용 | — |
| `FOOTER` | String(64) | N | 메시지 하단 수신거부 문구 (안드로이드 RCS 전용) | — |
| `MESSAGEBASE_ID` | String(40) | Y | RCS 공통 포맷 또는 템플릿 ID (전체 목록은 RCS 발송 페이지 참조) | — |
| `COPY_ALLOWED` | String(1) | N | 복사·공유 메뉴 (`Y`/`N`) — 안드로이드 RCS 전용 | `N` |
| `RCS_BODY` | String(4000) | Y | 메시지 베이스에서 치환할 파라미터 정보 (JSON) | — |
| `BUTTONS` | String(4000) | N | 메시지에 삽입할 버튼 정보 (JSON) | — |
| `AGENCY_KEY` | String(64) | N | 대행사 Key (2차 대행사인 경우 필수) | — |
| `BRAND_KEY` | String(64) | Y | 브랜드별 제공되는 특수 키 | — |

### BIZ\_ATTACHMENTS 컬럼

`FILE_HANDLING_MODE` 설정에 따라 생성되는 첨부파일 테이블.

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `MSG_KEY` | String(32) | Y | `biz_msg` 와 매칭되는 KEY (복합키) | — |
| `SEQ` | Number | Y | 데이터 시퀀스 (복합키) | — |
| `TYPE` | String(10) | Y | 데이터 타입 (`FILE` / `HTTP` / `JSON`) | `FILE` |
| `CONTENTS` | String(2000) | Y | 데이터 값 (파일명 / URL / JSONString) | — |

> ℹ️ 첨부파일 테이블 사용은 변수가 포함된 웹·앱 링크 처리 등 JSON 파일을 미리 만들기 어려운 경우 유용.

### 인덱스 권고

- 메시지 테이블의 **`CMID`** 컬럼은 Primary Key 로 자동 설정
- 로그 테이블은 중복 키 허용 (PK 미설정)
- 추가 인덱스는 운영 환경에 맞게 자유롭게 추가 가능

## 용어

**BIZCLIENT 발송 용어** — `MSG_TYPE` / `STATUS` / `CALL_STATUS` / `RE_TYPE` 정의

### 메시지 인코딩

메시지 문자열은 **KSC 5601 (ISO 10646-1 / Unicode 1.1)** 한글 코드에 정의된 문자열입니다.

- AT, 브랜드메시지(BT), RCS, 국제문자 메시지는 이모지 포함 가능
- 이동통신 3사 인코딩 범위를 벗어나는 문자는 `?` 로 표시되어 전송될 수 있음

메시지 타입별 이모지 발송 가능 여부:

| 메시지 타입 | 이모지 발송 |
| --- | --- |
| SMS | ✗ |
| LMS / MMS | ✗ |
| 알림톡 (AT) · 브랜드메시지 (BT) | ✓ |
| RCS | ✓ |
| FAX / PHONE | ✗ |

> ℹ️ FAX 발송 문서 파일에 입력된 이모지는 정상 발송됩니다.

이모지 데이터 발송 시 DBMS 캐릭터 셋 권장 (로그 테이블은 주기적으로 생성되므로 확인 필요):

| DBMS | Version | Character Set |
| --- | --- | --- |
| MySQL / MariaDB | 5.5.3 이상 | `UTF8MB4_GENERAL_CI` |
| Oracle | 9i 이상 | `AL32UTF8` |
| MSSQL | SQL Server 2017 이상 | `Latin1_General_100_CI_AI_SC_UTF8` |

### 메시지타입 (MSG\_TYPE)

| 코드 | 메시지 타입 | 비고 |
| --- | --- | --- |
| `0` | SMS | 단문 |
| `2` | FAX |  |
| `3` | PHONE |  |
| `5` | LMS / MMS | LMS 는 `ATTACHED_FILE` 비어있을 때, MMS 는 첨부 있을 때 |
| `6` | AT | 카카오 알림톡 |
| `7` | FT | 카카오 친구톡 (서비스 종료 — 과거 발송 이력 조회 시에만 노출, v5.2.4 친구톡 제거) |
| `8` | RCS |  |
| `9` | BT | 카카오 브랜드메시지 |

### 발송상태변화 (STATUS)

`biz_msg.STATUS` 의 정상 발송 흐름은 다음과 같습니다.

0발송 대기7발송 중1발송 후 대기2발송 완료로그 테이블 이동

| 값 | 상태 | 설명 |
| --- | --- | --- |
| `0` | 발송 대기 | 신규 INSERT 시 기본값 |
| `7` | 발송 중 | BIZCLIENT 가 비즈뿌리오 서버로 전송 중 |
| `1` | 발송 후 대기 | 서버 송신 완료, 결과 수신 대기 |
| `2` | 발송 결과 업데이트 | 결과 수신 완료 → 로그 테이블 이동 대상 |
| `3` | 리포트 재요청 후 대기 | 결과 재요청 후 대기 |
| `11` | 클라이언트 대체 발송 처리 중 | 대체 발송 진행 중 |
| `13` | 대체 발송 미설정 상태에서 대체 발송 처리됨 | **로그테이블 미이동** |

> ℹ️ `STATUS=13` 이 발생하는 경우 대체 발송 사용 여부 설정을 변경해야 합니다.

### 대체발송타입 (RE\_TYPE)

메시지 타입(AT/BT/RCS)에 따라 최대 2차 대체 발송까지 가능. `RE_TYPE` 필드에 다음 값을 입력:

**AT / BT 발송 시**

| 1차 대체 | 2차 대체 | `RE_TYPE` |
| --- | --- | --- |
| SMS | — | `SMS` |
| MMS | — | `MMS` |
| RCS | — | `R` |
| RCS | SMS | `RS` |
| RCS | MMS | `RM` |

**RCS 발송 시**

| 1차 대체 | 2차 대체 | `RE_TYPE` |
| --- | --- | --- |
| SMS | — | `SMS` |
| MMS | — | `MMS` |
| AT | — | `K` |
| AT | SMS | `KS` |
| AT | MMS | `KM` |
| BT | — | `U` |
| BT | SMS | `US` |
| BT | MMS | `UM` |

## Quickstart BIZCLIENT

BIZCLIENT를 설치하고 첫 SMS를 보내는 가장 짧은 경로입니다.

### 사전 조건

- 비즈뿌리오 운영 또는 검수 계정 (계정ID·암호)
- 등록된 발신번호 1개
- 본인 휴대폰 번호 (수신 테스트용)
- JDK 1.8 이상이 설치된 서버 (Linux/Unix 또는 Windows)
- MySQL / Oracle / MSSQL DB 접속 정보 (테이블 생성 권한 포함)
- 비즈뿌리오 서버로의 outbound: `bizppurio.com:38300, 38400`

준비가 안 됐다면 [사전 준비](https://bizppurio.github.io/docs#공통-사전-준비)와 [설치 및 구동](https://bizppurio.github.io/bizclient#설치) 페이지를 먼저 확인하세요.

### 1. BIZCLIENT 설치

### Linux/Unix

```
# 모듈 압축 해제
unzip biz_client_v5xxx.zip
cd biz_client_v5xxx

# 환경 설정
vi config/uds.conf
```

`uds.conf`:

```
UDS_ID    = bizUserId001               # bizppurio.com 계정
UDS_PW    = mypassword
DBNAME    = MYSQL                    # MYSQL / MSSQL2005 / ORACLE 등
DBURL     = jdbc:mysql://localhost:3306/bizclient
DBUSER    = bizclient
DBPASS    = mydbpassword
```

```
# 시작
cd script
./biz_start
```

### Windows

```
unzip biz_client_v5xxx.zip
notepad config\uds.conf       # 같은 항목 설정
cd bat
service-install.bat
service-start.bat
```

> **NOTE:** 모듈을 처음 시작하면 **`BIZ_MSG`**, **`BIZ_LOG_YYYYMM`** 테이블이 자동 생성됩니다. 테이블 생성 권한이 있어야 합니다.

### 2. SMS 1건 발송

`BIZ_MSG` 테이블에 INSERT 1번이면 끝입니다.

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, MSG_BODY
) VALUES (
  0,                                   -- SMS = 0
  'QS-001',                            -- 고유 키 (ASCII)
  NOW(), NOW(),                        -- 등록·발송 시간
  '01012345678',                       -- 수신번호 (본인 번호)
  '07000000000',                       -- 등록된 발신번호
  '비즈뿌리오 BIZCLIENT Quickstart 테스트입니다.'
);
```

INSERT 직후 `BIZ_MSG.STATUS = 0` (발송 대기). BIZCLIENT 모듈이 큐를 폴링하여 수 초 내에 비즈뿌리오 서버로 전송합니다.

> **WARNING:** **`CMID`는 고유해야** 합니다. 중복되면 발송은 되지만 결과 리포트 매핑이 누락될 수 있습니다.

### 3. 발송 결과 확인

### STATUS 진행

```
SELECT CMID, STATUS, CALL_STATUS, REQUEST_TIME, REPORT_TIME
FROM biz_msg
WHERE CMID = 'QS-001';
```

| `STATUS` | 의미 |
| --- | --- |
| `0` | 발송 대기 — 모듈이 픽업 전 |
| `7` | 발송 중 — 비즈뿌리오 서버로 전송 중 |
| `1` | 발송 후 대기 — 결과 수신 대기 |
| `2` | 발송 완료 — 결과 업데이트, 곧 로그 테이블로 이동 |

자세한 상태 흐름은 [발송 용어 — STATUS](https://bizppurio.github.io/bizclient#발송상태변화-status) 참고.

### CALL\_STATUS

발송 완료(`STATUS = 2`)되면 `CALL_STATUS` 컬럼에 결과 코드가 기록됩니다.

| `CALL_STATUS` | 의미 |
| --- | --- |
| `4100` | 단말기 전달 성공 |
| `4400`~ | 음영 지역, 단말기 전원 꺼짐 등 |
| `4430` / `4431` | 스팸 차단 |

전체 코드는 [발송 결과 코드](https://bizppurio.github.io/response-codes) 참고.

### 로그 테이블 이동

`STATUS = 2`인 데이터는 다음 폴링 주기에 월별 로그 테이블로 이동합니다.

```
SELECT CMID, CALL_STATUS, REPORT_TIME
FROM biz_log_202604       -- BIZ_LOG_YYYYMM
WHERE CMID = 'QS-001';
```

### 트러블슈팅

| 증상 | 원인·해결 |
| --- | --- |
| `STATUS=0`에서 멈춤 | 모듈이 구동 중인지 확인 (`./biz_start` / Windows 서비스) |
| `STATUS=0`에서 멈춤 (모듈은 구동) | DB 접속 정보(`uds.conf` `DBURL`/`DBUSER`/`DBPASS`) 확인 |
| `STATUS=2`인데 `CALL_STATUS` 비어있음 | 통상 1~5분 내 갱신. 5분 이상이면 비즈뿌리오 [발송 조회]에서 직접 확인 |
| `STATUS=13` | 대체 발송 미설정 상태에서 대체 발송 → 비즈뿌리오에 대체 발송 사용 가능 설정 요청 |
| `CALL_STATUS=9020` | 발송 시간 24시간 초과 — `SEND_TIME` 확인 |
| `CALL_STATUS=9026` | 블랙리스트 차단 |

자세한 진단은 [BIZCLIENT 발송상태변화](https://bizppurio.github.io/bizclient#용어)·[발송 결과 코드](https://bizppurio.github.io/response-codes) 참고.

## 메시지 전송

**BIZCLIENT 메시지 전송** — `biz_msg` 큐 테이블 INSERT 방식 발송.

세부 채널별 컬럼·예시·제약은 아래 `POST /bizclient/biz_msg` 페이지로 들어가서 확인하세요.
우측 **Request samples** 탭에서 채널별 SQL 을 확인할 수 있습니다.

sql/bizclient/biz\_msg/sms

### SMS

`SMS` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 0`

SMS 단문 문자 메시지를 `biz_msg` 테이블 INSERT 로 발송. EUC-KR 90바이트 이하.

### biz\_msg 컬럼 (SMS)

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | SMS = 0 | `0` |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) | — |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 | `NOW()` |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래) | `NOW()` |
| `DEST_PHONE` | string(16) | Y | 수신번호 | — |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) | — |
| `MSG_BODY` | string(4000) | Y | 메시지 내용 (90바이트 이하) | — |
| `DEST_NAME` | string(32) | N | 수신자명 | — |
| `SEND_NAME` | string(32) | N | 발신자명 | — |
| `CINFO` | string(32) | N | Client Indexed Info (특수기호 `\/:*?"<>|.` 사용 불가) | — |

### 제한사항

- 본문 90바이트 초과 시 LMS 자동 전환 없음 — 호출 측에서 명시
- 첨부 파일 미지원 (MMS 섹션 참고)
- 광고성 메시지는 `(광고)`/수신거부 표기 필수

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, MSG_BODY
) VALUES (
  0, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '본 메시지는 SMS 테스트 메시지 입니다.'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/lms

### LMS

`LMS` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 5`

장문 문자 메시지 (제목 + 본문). EUC-KR 본문 최대 2000바이트, 제목 최대 64바이트.  
LMS와 MMS는 동일 `MSG_TYPE` 을 공유하며 `ATTACHED_FILE` 컬럼이 비어 있으면 LMS로 발송.

### biz\_msg 컬럼 (LMS)

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | LMS = 5 (MMS와 공유) |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 (`NOW()`) |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래, `NOW()`) |
| `DEST_PHONE` | string(16) | Y | 수신번호 |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) |
| `SUBJECT` | string(64) | N | 제목 (최대 64바이트) |
| `MSG_BODY` | string(4000) | Y | 본문 (최대 2000바이트) |
| `DEST_NAME` | string(32) | N | 수신자명 |
| `SEND_NAME` | string(32) | N | 발신자명 |
| `CINFO` | string(32) | N | Client Indexed Info (특수기호 `\/:*?"<>|.` 사용 불가) |

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  SUBJECT, MSG_BODY
) VALUES (
  5, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  'LMS 제목', '본 메시지는 LMS 테스트 메시지 입니다.'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/mms

### MMS

`MMS` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 5 (+ ATTACHED_FILE)`

이미지 첨부 멀티미디어 메시지. LMS 컬럼 + `ATTACHED_FILE`. 본문은 선택 (이미지만 발송 가능).

> ℹ️ 여러 첨부 파일은 `|` 문자로 구분해 `ATTACHED_FILE` 한 컬럼에 입력. 첨부파일 테이블 모드 사용 시 이 컬럼에는 첨부파일 테이블 KEY를 입력. 이미지 파일 업로드·경로/테이블 모드는 [첨부파일 관리 (FILE\_HANDLING\_MODE)](https://bizppurio.github.io/bizclient#첨부파일-관리-—-filehandlingmode) 참고.

### biz\_msg 컬럼 (MMS)

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | MMS = 5 (LMS와 공유) |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 (`NOW()`) |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래, `NOW()`) |
| `DEST_PHONE` | string(16) | Y | 수신번호 |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) |
| `SUBJECT` | string(64) | N | 제목 (최대 64바이트) |
| `MSG_BODY` | string(4000) | N | 본문 (이미지만 발송 시 생략 가능) |
| `ATTACHED_FILE` | string(1000) | N | 첨부파일 경로 (여러 개는 `|` 로 구분) 또는 첨부파일 테이블 KEY |
| `DEST_NAME` | string(32) | N | 수신자명 |
| `SEND_NAME` | string(32) | N | 발신자명 |
| `CINFO` | string(32) | N | Client Indexed Info (특수기호 `\/:*?"<>|.` 사용 불가) |

### 첨부 제약 (BIZCLIENT)

- 파일당 **60 KB 이하**
- 첨부 최대 3개

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  SUBJECT, MSG_BODY, ATTACHED_FILE
) VALUES (
  5, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  'MMS 제목', '본 메시지는 MMS 테스트 메시지 입니다.',
  'image1.jpg|image2.jpg'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/rcs

### RCS

`RCS` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 8`

통신사 RCS 메시지 (안드로이드 RCS / 통합 RCS). 발송 전 본문/버튼을 별도 테이블에 등록한 뒤 발송 요청.

### 사전 준비

- [RCS 비즈센터(RBC)](https://www.rcsbizcenter.com) 에서 챗봇 ID, 브랜드 키 발급
- 사용할 `MESSAGEBASE_ID` 결정 — 목록·카드 유형별 선택 방법은 [RCS 연동 규격](https://bizppurio.github.io/bizclient#rcs-연동-규격) 참고
- 2023.08.01 이후 잘못된 `agencykey` / `brandkey` 는 실패 처리

발송은 `BIZ_RCS`(본문·버튼) 등록 후 `biz_msg`(`RCS_REFKEY` 로 연결)에 INSERT 하는 **2단계** 구조입니다.

> ℹ️ `BIZ_RCS.BUTTONS` 의 외부 키는 **대문자** `BUTTONS` 이지만, JSON 내부 키는 **소문자** `suggestions` / `action` 사용 (대소문자 구분).

### BIZ\_RCS 컬럼

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `REFKEY` | string(32) | Y | RCS 본문 식별 키 (Primary Key) |
| `CHATBOT_ID` | string(40) | Y | RCS 비즈센터 챗봇 ID |
| `HEADER` | string(1) | Y | 메시지 상단 식별 (`0`=Web / `1`=광고) |
| `FOOTER` | string(64) | N | 메시지 하단 수신거부 문구 |
| `MESSAGEBASE_ID` | string(40) | Y | RCS 공통 포맷 또는 템플릿 ID |
| `COPY_ALLOWED` | string(1) | N | 복사/공유 메뉴 (`Y`/`N`, 기본 `N`) |
| `RCS_BODY` | string(4000) | Y | 메시지 베이스 치환 파라미터 (JSON) |
| `BUTTONS` | string(4000) | N | 버튼 정보 (JSON) |
| `AGENCY_KEY` | string(64) | N | 대행사 Key (2차 대행사 필수) |
| `BRAND_KEY` | string(64) | Y | 브랜드별 제공되는 특수 키 |

### RCS 상세 규격

MESSAGEBASE\_ID 목록·카드 유형별 글자수·통합 RCS·RCS\_BODY·media·BUTTONS 규격은 [RCS 연동 규격](https://bizppurio.github.io/bizclient#rcs-연동-규격) 을 참고하세요.

### biz\_msg 컬럼 (RCS, `BIZ_RCS` 등록 후 사용)

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | RCS = 8 |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 (`NOW()`) |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래, `NOW()`) |
| `RCS_REFKEY` | string(32) | Y | `BIZ_RCS.REFKEY` 참조 |
| `DEST_PHONE` | string(16) | Y | 수신번호 |
| `SEND_PHONE` | string(16) | Y | 발신번호 |
| `RE_TYPE` | string(3) | N | 대체발송 타입 |
| `RE_BODY` | string(4000) | N | 대체발송 본문 |
| `RE_PART` | string(1) | N | 대체발송 처리 주체 (`C`/`S`) |
| `CINFO` | string(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 |

### 대체 발송

RCS 미수신 단말은 SMS/MMS/AT/BT 자동 전환 (1차/2차 대체 가능).

SQL

```
INSERT INTO BIZ_RCS (
  REFKEY, CHATBOT_ID, HEADER, FOOTER, MESSAGEBASE_ID,
  AGENCY_KEY, BRAND_KEY, RCS_BODY, BUTTONS
) VALUES (
  'RCS_TEMPLATE_005', '15999782', 0, '01012345678', 'CMwMhM0300',
  '', 'BK.9T0MMk615qoEOl1',
  '{"description1":"첫번째 이미지 설명","media1":"maapfile://...","description2":"..."}',
  '{"buttons":[{"suggestions":[{"action":{"urlAction":{"openUrl":{"url":"https://..."}}}, "displayText":"이동"}]}]}'
);

INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, RCS_REFKEY
) VALUES (
  8, 'SEND_BIZ_MSG_001', NOW(), NOW(),
  '01012345678', '15999782', 'RCS_TEMPLATE_005'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/alimtalk

### 카카오 알림톡

`카카오 알림톡 (AT / AI)` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 6`

사전 검수된 템플릿을 기반으로 발송하는 카카오톡 메시지. 친구 추가가 필요 없음.  
버튼·바로연결·아이템리스트 사용 시 ATTACHMENT JSON 파일을 별도 업로드합니다 — 업로드 방식은 [첨부파일 관리 (FILE\_HANDLING\_MODE)](https://bizppurio.github.io/bizclient#첨부파일-관리-—-filehandlingmode) 참고.

### 사전 준비

- 카카오 비즈메시지 채널 + 템플릿 모두 검수 승인 필수
- 발신 프로필 키 / 템플릿 코드 발급

> ℹ️ `MSG_BODY` 는 **변수가 이미 치환된 최종 본문**입니다. 비즈뿌리오 사이트에 등록한 템플릿 원문을 호출 측(고객사 애플리케이션)에서 치환한 결과를 INSERT 합니다. 변수(`#{...}`)는 BIZCLIENT 가 치환하지 않습니다.

### 템플릿 변수 치환 예시

| 구분 | 내용 |
| --- | --- |
| 템플릿 원문 (비즈뿌리오 사이트 등록) | `#{고객명} 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.` |
| `MSG_BODY` 입력값 (치환 결과) | `박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.` |

> ⚠️ 치환 결과 본문은 검수 승인된 템플릿 내용과 일치해야 합니다. 불일치 시 발송이 실패할 수 있습니다.

### ATTACHMENT JSON 사용 (버튼·바로연결·아이템리스트·강조표기)

JSON 파일을 환경설정의 `FILE_PATH` 경로에 업로드한 뒤 `ATTACHED_FILE` 에 파일명 지정 (UTF-8):

```
INSERT INTO biz_msg (
  MSG_TYPE, ..., TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  ATTACHED_FILE
) VALUES (
  6, ..., '{템플릿코드}', '{발신프로필키}', '82',
  '{파일명}.json'
);
```

웹·앱 링크에 변수가 있어 JSON 파일을 미리 만들기 어려운 경우 `FILE_HANDLING_MODE` 테이블 활용.

### biz\_msg 컬럼 (AT)

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | AT = 6 |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 (`NOW()`) |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래, `NOW()`) |
| `DEST_PHONE` | string(16) | Y | 수신번호 |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) |
| `SENDER_KEY` | string(40) | Y | 발신 프로필 키 |
| `TEMPLATE_CODE` | string(64) | Y | 템플릿 코드 |
| `NATION_CODE` | string(5) | Y | 국가 코드 (한국 = 82) |
| `MSG_BODY` | string(4000) | Y | 치환 완료된 본문 |
| `ATTACHED_FILE` | string(1000) | N | ATTACHMENT JSON 파일명 또는 첨부파일 테이블 KEY |
| `RESPONSE_METHOD` | string(8) | N | 발송 방식 (`PUSH`) |
| `TIMEOUT` | string(4) | N | 대체 발송용 타임아웃 (초) |
| `RE_TYPE` | string(3) | N | 대체 발송 타입 |
| `RE_BODY` | string(4000) | N | 대체 발송 본문 |
| `RE_PART` | string(1) | N | 대체 발송 처리 주체 (`C`=CLIENT / `S`=Server) |
| `CINFO` | string(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 |

### ATTACHMENT JSON · 버튼 규격

버튼·바로연결·아이템리스트·강조표기 등 ATTACHMENT JSON 상세 규격과 예시는 [카카오 연동 규격](https://bizppurio.github.io/bizclient#카카오-연동-규격) 을 참고하세요.

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/brand

### 카카오 브랜드메시지

`카카오 브랜드메시지` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 9`

브랜드메시지는 **고객사의 광고성 정보 수신 동의 회원**(`send_target: marketing`) 또는 **카카오 채널 친구**(`send_target: friend`) 대상으로 발송하는 광고성 메시지 상품입니다. `biz_msg` 에 `MSG_TYPE=9` 로 INSERT 하고, 말풍선 형태·본문·버튼 등 상세 구성은 **ATTACHMENT JSON 파일**(snake\_case 키)로 전달합니다.

말풍선 형태는 ATTACHMENT JSON 의 `extra.chat_bubble_type` 으로 지정하며 8종을 지원합니다:

| chat\_bubble\_type | 설명 |
| --- | --- |
| `TEXT` | 텍스트 |
| `IMAGE` | 이미지 |
| `WIDE` | 와이드 이미지 |
| `WIDE_ITEM_LIST` | 와이드 아이템 리스트 |
| `PREMIUM_VIDEO` | 프리미엄 동영상 |
| `COMMERCE` | 커머스 |
| `CAROUSEL_FEED` | 캐러셀 피드 |
| `CAROUSEL_COMMERCE` | 캐러셀 커머스 |

타입별 본문/이미지/버튼 필드 구성·글자수, 변수 분리 방식과 전문 방식의 차이는 [카카오 연동 규격](https://bizppurio.github.io/bizclient#카카오-연동-규격) 을 참고하세요.

### 발송 조건

- 광고성 메시지 — **08:00 ~ 20:50 (KST)**. 해외 사용자(해외 전화번호 가입자)는 시간 제한 없음
- 채널을 차단한 사용자에게는 발송되지 않음
- 고객사 회원 대상(`marketing`) 발송은 **사전 권한 신청 필요** + 카카오톡 25.4.0 이상 사용자에게만 발송
- 채널 친구 대상(`friend`) 발송은 발신프로필 등록 후 바로 발송 가능

**사전 등록**: 카카오 채널 + 템플릿 + 이미지/동영상 (KAPI 등록)

### biz\_msg 컬럼 (BT)

| 컬럼 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | BT = 9 |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 (`NOW()`) |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래, `NOW()`) |
| `DEST_PHONE` | string(16) | Y | 수신번호 |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) |
| `SENDER_KEY` | string(40) | Y | 발신 프로필 키 |
| `NATION_CODE` | string(5) | Y | 국가 코드 (한국 = 82) |
| `TEMPLATE_CODE` | string(64) | Y | 템플릿 코드 (기본형 필수) |
| `MSG_BODY` | string(4000) | N | 본문 (전문 방식에서 변수 있는 경우 필수) |
| `ATTACHED_FILE` | string(1000) | Y | ATTACHMENT JSON 파일명 |
| `RE_TYPE` | string(3) | N | 대체 발송 타입 (대체 발송 사용 시) |
| `RE_BODY` | string(4000) | N | 대체 발송 본문 |
| `RE_PART` | string(1) | N | 대체 발송 처리 주체 (`C`=CLIENT / `S`=Server) |
| `CINFO` | string(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 |

> ℹ️ 브랜드메시지도 미수신 시 SMS/MMS 등으로 **대체 발송**이 가능합니다 (`RE_TYPE`/`RE_BODY`/`RE_PART`). 타입 매핑은 [발송 용어 — 대체발송타입](https://bizppurio.github.io/bizclient#대체발송타입-retype) 참고.

### ATTACHMENT JSON 규격 · 예시

`chat_bubble_type` 8종·전문방식 본문 객체 스키마·CAROUSEL 객체, 그리고 발송 대상·방식별 전체 예시는 [카카오 연동 규격](https://bizppurio.github.io/bizclient#카카오-연동-규격) 을 참고하세요.

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  SENDER_KEY, NATION_CODE, TEMPLATE_CODE, ATTACHED_FILE
) VALUES (
  9, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '{발신프로필키}', '82', '{템플릿코드}', '{파일명}.json'
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

말풍선 타입 · TYPE

대상

방식

고객사 회원 › 변수 미사용 › 텍스트brand\_message\_type: BASICsend\_target: marketingtargeting: O

attach.json· TEXT

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  }
}
```

### ATTACHMENT JSON 예시 — 전체 64개 조합

발송 대상 2 × 방식 4 × 타입 8. 인쇄/PDF 전용 선형 목록입니다.

고객사 회원 › 변수 미사용 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  }
}
```

고객사 회원 › 변수 미사용 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M"
  }
}
```

고객사 회원 › 변수 미사용 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  }
}
```

고객사 회원 › 변수 분리 방식 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "carousel_variable": [
      {
        "message_variable": {
          "변수1": "치환 메시지"
        },
        "image_variable": {
          "변수2": "https://example.com/image.jpg"
        },
        "button_variable": {
          "변수3": "https://www.bizppurio.com/"
        },
        "coupon_variable": {
          "변수4": "https://www.bizppurio.com/"
        }
      },
      {
        "message_variable": {
          "변수1": "치환 메시지"
        },
        "image_variable": {
          "변수2": "https://example.com/image.jpg"
        },
        "button_variable": {
          "변수3": "https://www.bizppurio.com/"
        },
        "coupon_variable": {
          "변수4": "https://www.bizppurio.com/"
        }
      }
    ]
  }
}
```

고객사 회원 › 변수 분리 방식 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "M",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "message_variable": {
      "변수1": "변수1"
    },
    "commerce_variable": {
      "변수2": "변수2"
    },
    "button_variable": {
      "변수7": "test15",
      "변수8": "test14"
    },
    "coupon_variable": {
      "변수5": "test5",
      "변수6": "test6"
    }
  }
}
```

고객사 회원 › 변수 분리 방식 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "carousel_variable": [
      {
        "button_variable": {
          "변수5": "test3",
          "변수4": "test4"
        },
        "coupon_variable": {
          "변수5": "test3",
          "변수6": "test6"
        }
      },
      {
        "button_variable": {
          "변수5": "test3",
          "변수4": "test4"
        },
        "coupon_variable": {
          "변수5": "test3",
          "변수6": "test6"
        }
      }
    ]
  }
}
```

고객사 회원 › 전문 방식 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명",
    "url_mobile": "https://www.bizppurio.com/변수10",
    "url_pc": "https://www.bizppurio.com/변수9",
    "scheme_ios": "https://www.bizppurio.com/변수8"
  }
}
```

고객사 회원 › 전문 방식 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL"
    },
    {
      "type": "WL"
    },
    {
      "type": "WL"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

고객사 회원 › 전문 방식 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

고객사 회원 › 전문 방식 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "header": "템플릿 헤더변수1"
  },
  "item": {
    "list": [
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg"
      }
    ]
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "#{변수6} 쿠폰 설명"
  }
}
```

고객사 회원 › 전문 방식 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "carousel": {
      "list": [
        {
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/변수4",
                "url_pc": "https://www.bizppurio.com/변수3"
              },
              {
                "type": "WL"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/변수4",
                "url_pc": "https://www.bizppurio.com/변수3"
              },
              {
                "type": "WL"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        }
      ]
    }
  }
}
```

고객사 회원 › 전문 방식 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "header": "템플릿 헤더변수1"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수6",
      "url_pc": "https://www.bizppurio.com/변수5"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

고객사 회원 › 전문 방식 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "additional_content": "템플릿 부가정보변수1"
  },
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "#{변수6} 쿠폰 설명"
  },
  "commerce": {
    "title": "상품 제목변수2."
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    }
  ],
  "image": {
    "img_url": "https://example.com/image.jpg"
  }
}
```

고객사 회원 › 전문 방식 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "marketing",
    "targeting": "O",
    "carousel": {
      "head": {
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/11",
        "scheme_ios": "https://www.bizppurio.com/11",
        "header": "캐러셀 인트로 헤더#{변수1}",
        "content": "캐러셀 인트로 내용변수2",
        "image_url": "https://example.com/image.jpg"
      },
      "list": [
        {
          "additional_content": "변수4",
          "header": "상품 제목변수4",
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_pc": "https://www.bizppurio.com/변수4",
                "url_mobile": "https://www.bizppurio.com/변수4"
              },
              {
                "type": "WL",
                "url_pc": "https://www.bizppurio.com/변수5",
                "url_mobile": "https://www.bizppurio.com/변수5"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "commerce": {
              "title": "상품 제목변수4"
            }
          }
        }
      ]
    }
  }
}
```

고객사 회원 › 자유형 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "O"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼3",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼4",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

고객사 회원 › 자유형 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "O"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼3",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼4",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

고객사 회원 › 자유형 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "O"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

고객사 회원 › 자유형 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "O",
    "header": "헤덩"
  },
  "item": {
    "list": [
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀 입니다.",
        "url_mobile": "https://daou.co.kr/"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀2 입니다.",
        "url_mobile": "https://daou.co.kr/2"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀3 입니다.",
        "url_mobile": "https://daou.co.kr/3"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀4 입니다.",
        "url_mobile": "https://daou.co.kr/4"
      }
    ]
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://m.naver.com"
  }
}
```

고객사 회원 › 자유형 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "M",
    "carousel": {
      "list": [
        {
          "header": "헤더입니다.",
          "message": "안녕하세요! 겨울 신상 패딩을 할인 판매 중입니다.\n지금 89,000원에 만나보세요.",
          "attachment": {
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ],
            "image": {
              "img_url": "https://example.com/image.jpg"
            },
            "coupon": {
              "title": "배송비 할인 쿠폰",
              "description": "쿠폰 상세 내용입니다.",
              "url_mobile": "https://bizppurio.com"
            }
          }
        },
        {
          "header": "헤더입니다.",
          "message": "안녕하세요! 겨울 신상 패딩을 할인 판매 중입니다.\n지금 89,000원에 만나보세요.",
          "attachment": {
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ],
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        }
      ],
      "tail": {
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/"
      }
    }
  }
}
```

고객사 회원 › 자유형 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "M",
    "header": "헤더입니다."
  },
  "video": {
    "video_url": "https://tv.kakao.com/channel/XXXXX/cliplink/447422551",
    "thumbnail_url": "http://thumb.kakaocdn.net/dna/kamp/source/XXXXX/thumbs/thumb.jpg"
  },
  "button": [
    {
      "name": "버튼명입니다.",
      "type": "WL",
      "url_mobile": "https://www.naver.com"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

고객사 회원 › 자유형 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "M",
    "additional_content": "부가정보"
  },
  "commerce": {
    "title": "상품명입니다.",
    "regular_price": 10,
    "discount_price": 10,
    "discount_rate": "10",
    "discount_fixed": "10"
  },
  "image": {
    "img_url": "https://example.com/image.jpg"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://www.naver.com"
  }
}
```

고객사 회원 › 자유형 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "FREE",
    "send_target": "marketing",
    "targeting": "M",
    "carousel": {
      "head": {
        "header": "이번 주 특가 모음",
        "content": "엄선한 상품을 특별한 가격에 만나보세요.",
        "image_url": "https://example.com/image.jpg",
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/"
      },
      "list": [
        {
          "additional_content": "",
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            },
            "commerce": {
              "title": "타이틀",
              "regular_price": 10,
              "discount_price": 10,
              "discount_rate": 10,
              "discount_fixed": 10
            },
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ]
          }
        }
      ],
      "tail": {
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/"
      }
    }
  }
}
```

채널 친구 › 변수 미사용 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "targeting": "O"
  }
}
```

채널 친구 › 변수 미사용 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  }
}
```

채널 친구 › 변수 미사용 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "targeting": "O"
  }
}
```

채널 친구 › 변수 분리 방식 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "image_variable": {
      "변수2": "https://example.com/image.jpg"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "carousel_variable": [
      {
        "message_variable": {
          "변수1": "치환 메시지"
        },
        "image_variable": {
          "변수2": "https://example.com/image.jpg"
        },
        "button_variable": {
          "변수3": "https://www.bizppurio.com/"
        },
        "coupon_variable": {
          "변수4": "https://www.bizppurio.com/"
        }
      },
      {
        "message_variable": {
          "변수1": "치환 메시지"
        },
        "image_variable": {
          "변수2": "https://example.com/image.jpg"
        },
        "button_variable": {
          "변수3": "https://www.bizppurio.com/"
        },
        "coupon_variable": {
          "변수4": "https://www.bizppurio.com/"
        }
      }
    ]
  }
}
```

채널 친구 › 변수 분리 방식 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "치환 문구"
    },
    "button_variable": {
      "변수7": "https://www.bizppurio.com/"
    },
    "coupon_variable": {
      "변수5": "https://www.bizppurio.com/"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "message_variable": {
      "변수1": "변수1"
    },
    "commerce_variable": {
      "변수2": "변수2"
    },
    "button_variable": {
      "변수7": "test15",
      "변수8": "test14"
    },
    "coupon_variable": {
      "변수5": "test5",
      "변수6": "test6"
    }
  }
}
```

채널 친구 › 변수 분리 방식 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "carousel_variable": [
      {
        "button_variable": {
          "변수5": "test3",
          "변수4": "test4"
        },
        "coupon_variable": {
          "변수5": "test3",
          "변수6": "test6"
        }
      },
      {
        "button_variable": {
          "변수5": "test3",
          "변수4": "test4"
        },
        "coupon_variable": {
          "변수5": "test3",
          "변수6": "test6"
        }
      }
    ]
  }
}
```

채널 친구 › 전문 방식 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명",
    "url_mobile": "https://www.bizppurio.com/변수10",
    "url_pc": "https://www.bizppurio.com/변수9",
    "scheme_ios": "https://www.bizppurio.com/변수8"
  }
}
```

채널 친구 › 전문 방식 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL"
    },
    {
      "type": "WL"
    },
    {
      "type": "WL"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

채널 친구 › 전문 방식 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "BASIC",
    "send_target": "friend"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수4",
      "url_pc": "https://www.bizppurio.com/변수3"
    },
    {
      "type": "WL"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

채널 친구 › 전문 방식 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "header": "템플릿 헤더변수1"
  },
  "item": {
    "list": [
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "아이템 제목1234",
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/22",
        "scheme_ios": "https://www.bizppurio.com/11"
      },
      {
        "img_url": "https://example.com/image.jpg"
      }
    ]
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "#{변수6} 쿠폰 설명"
  }
}
```

채널 친구 › 전문 방식 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "carousel": {
      "list": [
        {
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/변수4",
                "url_pc": "https://www.bizppurio.com/변수3"
              },
              {
                "type": "WL"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/변수4",
                "url_pc": "https://www.bizppurio.com/변수3"
              },
              {
                "type": "WL"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        },
        {
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        }
      ]
    }
  }
}
```

채널 친구 › 전문 방식 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "header": "템플릿 헤더변수1"
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/변수6",
      "url_pc": "https://www.bizppurio.com/변수5"
    }
  ],
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "변수6 쿠폰 설명"
  }
}
```

채널 친구 › 전문 방식 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "additional_content": "템플릿 부가정보변수1"
  },
  "coupon": {
    "title": "변수5 무료 쿠폰",
    "description": "#{변수6} 쿠폰 설명"
  },
  "commerce": {
    "title": "상품 제목변수2."
  },
  "button": [
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    },
    {
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/11",
      "url_pc": "https://www.bizppurio.com/22"
    }
  ],
  "image": {
    "img_url": "https://example.com/image.jpg"
  }
}
```

채널 친구 › 전문 방식 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "BASIC",
    "send_target": "friend",
    "carousel": {
      "head": {
        "url_mobile": "https://www.bizppurio.com/11",
        "url_pc": "https://www.bizppurio.com/11",
        "scheme_ios": "https://www.bizppurio.com/11",
        "header": "캐러셀 인트로 헤더#{변수1}",
        "content": "캐러셀 인트로 내용변수2",
        "image_url": "https://example.com/image.jpg"
      },
      "list": [
        {
          "additional_content": "변수4",
          "header": "상품 제목변수4",
          "attachment": {
            "button": [
              {
                "type": "WL",
                "url_pc": "https://www.bizppurio.com/변수4",
                "url_mobile": "https://www.bizppurio.com/변수4"
              },
              {
                "type": "WL",
                "url_pc": "https://www.bizppurio.com/변수5",
                "url_mobile": "https://www.bizppurio.com/변수5"
              }
            ],
            "coupon": {
              "title": "변수5 무료 쿠폰",
              "description": "변수6 쿠폰 설명"
            },
            "commerce": {
              "title": "상품 제목변수4"
            }
          }
        }
      ]
    }
  }
}
```

채널 친구 › 자유형 › 텍스트 (TEXT)

```
{
  "extra": {
    "chat_bubble_type": "TEXT",
    "brand_message_type": "FREE",
    "send_target": "friend"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼3",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼4",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

채널 친구 › 자유형 › 이미지 (IMAGE)

```
{
  "extra": {
    "chat_bubble_type": "IMAGE",
    "brand_message_type": "FREE",
    "send_target": "friend"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼3",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼4",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

채널 친구 › 자유형 › 와이드 이미지 (WIDE)

```
{
  "extra": {
    "chat_bubble_type": "WIDE",
    "brand_message_type": "FREE",
    "send_target": "friend"
  },
  "image": {
    "img_url": "https://example.com/image.jpg",
    "img_link": "https://www.bizppurio.com/"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

채널 친구 › 자유형 › 와이드 리스트 (WIDE\_ITEM\_LIST)

```
{
  "extra": {
    "chat_bubble_type": "WIDE_ITEM_LIST",
    "brand_message_type": "FREE",
    "send_target": "friend",
    "header": "헤덩"
  },
  "item": {
    "list": [
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀 입니다.",
        "url_mobile": "https://daou.co.kr/"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀2 입니다.",
        "url_mobile": "https://daou.co.kr/2"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀3 입니다.",
        "url_mobile": "https://daou.co.kr/3"
      },
      {
        "img_url": "https://example.com/image.jpg",
        "title": "타이틀4 입니다.",
        "url_mobile": "https://daou.co.kr/4"
      }
    ]
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://m.naver.com"
  }
}
```

채널 친구 › 자유형 › 캐러셀 피드 (CAROUSEL\_FEED)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_FEED",
    "brand_message_type": "FREE",
    "send_target": "friend",
    "carousel": {
      "list": [
        {
          "header": "헤더입니다.",
          "message": "안녕하세요! 겨울 신상 패딩을 할인 판매 중입니다.\n지금 89,000원에 만나보세요.",
          "attachment": {
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ],
            "image": {
              "img_url": "https://example.com/image.jpg"
            },
            "coupon": {
              "title": "배송비 할인 쿠폰",
              "description": "쿠폰 상세 내용입니다.",
              "url_mobile": "https://bizppurio.com"
            }
          }
        },
        {
          "header": "헤더입니다.",
          "message": "안녕하세요! 겨울 신상 패딩을 할인 판매 중입니다.\n지금 89,000원에 만나보세요.",
          "attachment": {
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ],
            "image": {
              "img_url": "https://example.com/image.jpg"
            }
          }
        }
      ],
      "tail": {
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/"
      }
    }
  }
}
```

채널 친구 › 자유형 › 프리미엄 동영상 (PREMIUM\_VIDEO)

```
{
  "extra": {
    "chat_bubble_type": "PREMIUM_VIDEO",
    "brand_message_type": "FREE",
    "send_target": "friend",
    "header": "헤더입니다."
  },
  "video": {
    "video_url": "https://tv.kakao.com/channel/XXXXX/cliplink/447422551",
    "thumbnail_url": "http://thumb.kakaocdn.net/dna/kamp/source/XXXXX/thumbs/thumb.jpg"
  },
  "button": [
    {
      "name": "버튼명입니다.",
      "type": "WL",
      "url_mobile": "https://www.naver.com"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://bizppurio.com"
  }
}
```

채널 친구 › 자유형 › 커머스 (COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "COMMERCE",
    "brand_message_type": "FREE",
    "send_target": "friend",
    "additional_content": "부가정보"
  },
  "commerce": {
    "title": "상품명입니다.",
    "regular_price": 10,
    "discount_price": 10,
    "discount_rate": "10",
    "discount_fixed": "10"
  },
  "image": {
    "img_url": "https://example.com/image.jpg"
  },
  "button": [
    {
      "name": "버튼1",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    },
    {
      "name": "버튼2",
      "type": "WL",
      "url_mobile": "https://www.bizppurio.com/",
      "url_pc": "https://www.bizppurio.com/"
    }
  ],
  "coupon": {
    "title": "배송비 할인 쿠폰",
    "description": "쿠폰 상세 내용입니다.",
    "url_mobile": "https://www.naver.com"
  }
}
```

채널 친구 › 자유형 › 캐러셀 커머스 (CAROUSEL\_COMMERCE)

```
{
  "extra": {
    "chat_bubble_type": "CAROUSEL_COMMERCE",
    "brand_message_type": "FREE",
    "send_target": "friend",
    "carousel": {
      "head": {
        "header": "이번 주 특가 모음",
        "content": "엄선한 상품을 특별한 가격에 만나보세요.",
        "image_url": "https://example.com/image.jpg",
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/"
      },
      "list": [
        {
          "additional_content": "",
          "attachment": {
            "image": {
              "img_url": "https://example.com/image.jpg"
            },
            "commerce": {
              "title": "타이틀",
              "regular_price": 10,
              "discount_price": 10,
              "discount_rate": 10,
              "discount_fixed": 10
            },
            "button": [
              {
                "name": "버튼1",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              },
              {
                "name": "버튼2",
                "type": "WL",
                "url_mobile": "https://www.bizppurio.com/",
                "url_pc": "https://www.bizppurio.com/"
              }
            ]
          }
        }
      ],
      "tail": {
        "url_mobile": "https://www.bizppurio.com/",
        "url_pc": "https://www.bizppurio.com/",
        "scheme_ios": "https://www.bizppurio.com/",
        "scheme_android": "https://www.bizppurio.com/"
      }
    }
  }
}
```

sql/bizclient/biz\_msg/fax

### FAX

`FAX` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 2`

한글 100자 이하의 표지를 포함한 이미지 및 문서를 팩스 번호로 발송하는 채널.

> ⚠️ **FAX는 BIZCLIENT 전용 채널입니다.**

### biz\_msg 컬럼 (FAX)

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | FAX = 2 | — |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) | — |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 | `NOW()` |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래) | `NOW()` |
| `DEST_PHONE` | string(16) | Y | 수신 팩스번호 | — |
| `SEND_PHONE` | string(16) | Y | 발신 팩스번호 (사전 등록 필수) | — |
| `SUBJECT` | string(64) | N | 표지 제목 (한글 100자 이하) | — |
| `MSG_BODY` | string(4000) | N | 표지 내용 | — |
| `ATTACHED_FILE` | string(1000) | Y | 첨부 파일 경로 (여러 개는 `|` 로 구분) | — |
| `COVER_FLAG` | number(1) | N | 팩스 표지 사용 여부 (`0`/`1`) | `0` |
| `USE_PAGE` | number(2) | N | 발송 페이지 수 | `0` |
| `RETRY_CNT` | number(4) | N | 재시도 횟수 | — |
| `CINFO` | string(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 | — |

### 응답·결과

- 발송 결과: `biz_msg.STATUS` (0:대기, 7:발송중, 1:발송 후 대기, 2:발송완료) 및 `CALL_STATUS` 컬럼
- 발송 페이지 수: `USE_PAGE` 컬럼에 기록

### 제한사항

| 항목 | 값 |
| --- | --- |
| 표지 제목 | 한글 100자 이하 |
| 첨부 포맷 | TIF / PDF 등 |

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  SUBJECT, MSG_BODY,
  ATTACHED_FILE, COVER_FLAG
) VALUES (
  2, '201XXXXXXXXX', NOW(), NOW(),
  '03112341234', '0212341234',
  '표지제목', '표지내용',
  'document.tif',
  1
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

sql/bizclient/biz\_msg/phone

### PHONE

`PHONE` 채널 — `biz_msg` 테이블 INSERT 방식으로 발송합니다.

`MSG_TYPE = 3`

한글 100자 이하의 TTS(Text-to-Speech) 또는 3분 미만의 WAVE 시나리오 파일을 전화로 발송하는 채널.  
시나리오 답변(설문 응답) 기능도 지원.

> ⚠️ **PHONE은 BIZCLIENT 전용 채널입니다.**

### biz\_msg 컬럼 (PHONE)

| 컬럼 | 타입 | 필수 | 설명 | 기본값 |
| --- | --- | --- | --- | --- |
| `MSG_TYPE` | number(1) | Y | PHONE = 3 | — |
| `CMID` | string(32) | Y | 데이터 ID (Primary Key, ASCII) | — |
| `REQUEST_TIME` | datetime | Y | 데이터 등록 시간 | `NOW()` |
| `SEND_TIME` | datetime | Y | 발송 기준 시간 (예약 시 미래) | `NOW()` |
| `DEST_PHONE` | string(16) | Y | 수신번호 | — |
| `SEND_PHONE` | string(16) | Y | 발신번호 (사전 등록 필수) | — |
| `MSG_BODY` | string(4000) | Y | TTS 변환 본문 (한글 100자 이하) | — |
| `VXML_FILE` | string(64) | N | 음성 시나리오 파일명 (WAVE 시나리오 사용 시) | — |
| `REPLY_FLAG` | number(1) | N | 시나리오 답변 기능 (`Y`:1, `N`:0) | `0` |
| `SMS_FLAG` | number(1) | N | 실패 시 문자 전송 옵션 | `0` |
| `RETRY_CNT` | number(4) | N | 재시도 횟수 | — |
| `USE_TIME` | number(4) | N | 발송 소요 시간 (초) | `0` |
| `SN_RESULT` | number(1) | N | 설문 조사 응답 값 (`0~9`) | `0` |
| `ATTACHED_FILE` | string(1000) | N | WAVE 파일 경로 | — |
| `CINFO` | string(32) | N | Client Indexed Info — 사내 구분용. 특수기호 `\/:*?"<>|.` 사용 불가 | — |

### 응답·결과

- 발송 결과: `biz_msg.STATUS` (0:대기, 7:발송중, 1:발송 후 대기, 2:발송완료) 및 `CALL_STATUS` 컬럼
- 시나리오 답변 결과: `SN_RESULT` 컬럼에 `0~9` 의 값으로 기록
- 통화 시간: `USE_TIME` 컬럼 (초 단위)

### 제한사항

| 항목 | 값 |
| --- | --- |
| TTS 본문 | 한글 100자 이하 |
| WAVE 파일 | 3분 미만, `wav` (8bit-8KHz mono 형식) |
| 설문 응답 | `0~9` 단일 자리 숫자 |

SQL

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, MSG_BODY
) VALUES (
  3, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '본 메시지는 테스트입니다. 다우기술 비즈메시지 서비스를 이용해주셔서 감사합니다.'
);

INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, MSG_BODY,
  VXML_FILE, REPLY_FLAG
) VALUES (
  3, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  'TTS 백업 본문',
  'survey_2025.vxml',
  1
);
```

응답

200INSERT 성공 시 `STATUS=0` (발송 대기) 로 큐에 적재. BIZCLIENT 모듈이 폴링 →
`STATUS=7` (발송 중) → `1` (발송 후 대기) → `2` (발송 완료). `CALL\_STATUS`
컬럼에 [결과 코드](#tag/BizclientCodes) 기록.

## 가이드

BIZCLIENT 운영 가이드 — 대체발송 / 지원 가능한 첨부파일 타입 / 운영 설정 옵션.

## 대체발송

**BIZCLIENT 대체 발송 쿼리** — AT/BT + 1·2차 대체 발송 / RCS + 1·2차 대체 발송 INSERT 예제

발송 실패 시 다른 채널로 자동 전환. `RE_TYPE` 코드 정의는 [발송 용어 — 대체발송타입](https://bizppurio.github.io/bizclient#대체발송타입-retype) 참고.

> ⚠️ 대체 발송을 사용하려면 비즈뿌리오 ID 가 **대체 발송 사용 가능** 으로 설정되어 있어야 합니다.

### 매핑 키 동작

대체 발송이 이루어진 경우 BIZCLIENT 는 결과 리포트 업데이트 시점에 대체 발송 정보를 **추가 레코드** 로 신규 생성:

- 원본 메시지의 `UMID` ↔ 1차 대체 발송의 `CMID`
- 2차 대체가 있는 경우: `UMID` 앞에 `RE_` 가 추가된 문자열이 2차 대체의 `CMID` (1차 대체의 `UMID` 도 같은 값)

### SMS / MMS 대체 — 본문 동작

- `RE_BODY` 를 비워두면 원본 `MSG_BODY` 가 그대로 사용됨
- 단, **SMS 대체** 의 경우 `MSG_BODY` 길이가 SMS 허용 길이(90바이트) 를 초과하면 발송되지 않음

### AT/BT + 1차 대체 발송

**AT + SMS 대체**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RE_BODY
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'SMS', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.'
);
```

**AT + MMS 대체**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RE_BODY, ATTACHED_FILE
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'MMS', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.', '{첨부파일명.jpg}'
);
```

**AT + RCS 대체** — `BIZ_RCS` 에 미리 등록된 RCS 메시지의 `REFKEY` 를 `RCS_REFKEY` 로 연결.

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RCS_REFKEY
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'R', '{RCS_REFKEY}'
);
```

BT 의 1차 대체도 위 패턴과 동일하며, `MSG_TYPE` 만 9(BT) 로 변경.

### AT/BT + 2차 대체 발송

1차 대체가 RCS 이고, RCS 도 실패할 경우 2차 대체로 SMS 또는 MMS 발송.

**AT + RCS(1차) + SMS(2차)**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RE_BODY, RCS_REFKEY
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'RS', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.', '{RCS_REFKEY}'
);
```

**AT + RCS(1차) + MMS(2차)**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RE_BODY, ATTACHED_FILE, RCS_REFKEY
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'RM', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.', '{첨부파일명.jpg}', '{RCS_REFKEY}'
);
```

### RCS + 1차 대체 발송

RCS 발송 실패 시 SMS / MMS / AT / BT 중 하나로 대체.

**RCS + SMS 대체**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  RCS_REFKEY, RE_TYPE, RE_BODY
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '{RCS_REFKEY}', 'SMS', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.'
);
```

**RCS + MMS 대체**

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  RCS_REFKEY, RE_TYPE, RE_BODY, ATTACHED_FILE
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '{RCS_REFKEY}', 'MMS', '[다우기술] 응모하신 프로모션에 당첨되셨습니다.', '{첨부파일.jpg}'
);
```

**RCS + AT 대체** — 알림톡 대체 발송은 `MSG_BODY` 필드의 데이터를 사용.

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RCS_REFKEY
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'K', '{RCS_REFKEY}'
);
```

**RCS + BT 대체** — 브랜드메시지 대체도 `MSG_BODY`·`TEMPLATE_CODE` 등 채널 필수 컬럼을 그대로 포함하며 `RE_TYPE = U`.

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RCS_REFKEY
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234',
  '박다우 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  'U', '{RCS_REFKEY}'
);
```

### RCS + 2차 대체 발송

RCS 발송 실패 시 1차로 AT/BT, 그것도 실패하면 2차로 SMS/MMS 발송. `RE_TYPE` 은 1차·2차 코드를 조합합니다 (`KS` = AT→SMS, `KM` = AT→MMS, `US` = BT→SMS, `UM` = BT→MMS).

> 1차가 AT/BT 이므로 **`MSG_BODY` 에 1차(AT/BT) 본문**, **`RE_BODY` 에 2차(SMS/MMS) 본문**을 넣습니다. `TEMPLATE_CODE`·`SENDER_KEY`·`NATION_CODE` 등 1차 채널의 필수 컬럼을 모두 포함해야 합니다.

**RCS + AT(1차) + SMS(2차)** — `RE_TYPE = KS`

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, RCS_REFKEY, RE_TYPE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE, RE_BODY
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234', '{RCS_REFKEY}', 'KS',
  '박다우 고객님, 알림톡 대체 본문입니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  '[다우기술] SMS 2차 대체 본문입니다.'
);
```

**RCS + AT(1차) + MMS(2차)** — `RE_TYPE = KM`

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, RCS_REFKEY, RE_TYPE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE, RE_BODY, ATTACHED_FILE
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234', '{RCS_REFKEY}', 'KM',
  '박다우 고객님, 알림톡 대체 본문입니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  '[다우기술] MMS 2차 대체 본문입니다.', '{첨부파일명.jpg}'
);
```

**RCS + BT(1차) + SMS(2차)** — `RE_TYPE = US`

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, RCS_REFKEY, RE_TYPE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE, RE_BODY
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234', '{RCS_REFKEY}', 'US',
  '박다우 고객님, 브랜드메시지 대체 본문입니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  '[다우기술] SMS 2차 대체 본문입니다.'
);
```

**RCS + BT(1차) + MMS(2차)** — `RE_TYPE = UM`

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME,
  DEST_PHONE, SEND_PHONE, RCS_REFKEY, RE_TYPE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE, RE_BODY, ATTACHED_FILE
) VALUES (
  8, '201XXXXXXXXX', NOW(), NOW(),
  '01012345678', '0212341234', '{RCS_REFKEY}', 'UM',
  '박다우 고객님, 브랜드메시지 대체 본문입니다.',
  '{템플릿코드}', '{발신프로필키}', '82',
  '[다우기술] MMS 2차 대체 본문입니다.', '{첨부파일명.jpg}'
);
```

전체 코드 매핑은 [발송 용어 — 대체발송타입](https://bizppurio.github.io/bizclient#대체발송타입-retype) 표 참고.

## 지원 가능한 첨부파일 타입

메시지 타입별로 첨부 가능한 파일 형식입니다.

### MMS

| 구분 | 지원 형식 |
| --- | --- |
| Image | `jpg` |
| Audio | `ma3` |
| Video | `k3g` |

> Image 를 제외한 타입에 대해서는 전송은 되지만 일부 이동통신사 또는 단말기에서 지원하지 않을 수 있습니다.

### AT / BT

| 구분 | 지원 형식 |
| --- | --- |
| Button / Image | `json` |

### PHONE

| 구분 | 지원 형식 |
| --- | --- |
| Voice | `wav` (8bit-8KHz mono 형식) |

### FAX

| 구분 | 지원 형식 |
| --- | --- |
| Docs | `doc`, `docx`, `xls`, `xlsx`, `ppt`, `pptx`, `hwp`, `pdf`, `txt`, `html` |
| Image | `bmp`, `gif`, `jpg`, `png` |

## 운영 설정 옵션

`config/uds.conf` (및 `config/data/columns-{DBMS}.json`) 에서 설정하는 모듈 운영 옵션입니다.

### DB 데이터 복호화 — `DB_DATA_DECRYPTION`

`BIZ_MSG.MSG_BODY` / `RE_BODY` 등 본문이 **암호화 상태로 DB에 저장**된 경우, 모듈이 복호화 후 발송하도록 설정합니다. 기본 대상 필드는 `MSG_BODY` 와 `RE_BODY` 입니다.

| 옵션 | 값 | 설명 |
| --- | --- | --- |
| `DB_DATA_DECRYPTION` | `A` (API) | 어플리케이션 단에서 AES 복호화 |
|  | `P` (PLUG-IN) | DBMS 에 설치된 복호화 함수(모듈)로 복호화 |
|  | `N` (NONE) | 사용 안 함 (기본값) |
| `DB_DATA_DECRYPTION_FIELDS` | (필드 목록) | 복호화 대상 필드. 기본 `MSG_BODY|RE_BODY`, 복수는 `|` 로 구분 |
| `USE_DECRYPTION_SEPARATION_FIELD` | `Y` / `N` | 복호화 구분 필드(`USE_DECRYPT`) 사용 여부. 기본 `Y` |
| `DB_DATA_DECRYPTION_FUNCTION` | (함수명) | PLUG-IN 방식 — 복호화 함수명 |
| `DB_DATA_DECRYPTION_PARAMETER_COUNT` | (1~) | PLUG-IN 함수 매개변수 개수. 기본 `1` |
| `DB_DATA_DECRYPTION_PARAMETER_1~N` | (값) | 매개변수 — 복호화 대상 필드 위치를 비워두면 모듈이 자동 입력 |
| `DB_DATA_DECRYPTION_KEY` | (키) | API 방식 — AES 키. 모듈 정상 구동 시 자동 암호화되어 저장됨 |

**사용 예시 (PLUG-IN 방식)**

```
DB_DATA_DECRYPTION = P
DB_DATA_DECRYPTION_FIELDS = MSG_BODY|RE_BODY
DB_DATA_DECRYPTION_FUNCTION = DECRYPT_FUNCTION
DB_DATA_DECRYPTION_PARAMETER_COUNT = 3
DB_DATA_DECRYPTION_PARAMETER_1 = 1
DB_DATA_DECRYPTION_PARAMETER_2 = 'PHONE'
DB_DATA_DECRYPTION_PARAMETER_3 =
```

→ 호출되는 SQL: `DECRYPT_FUNCTION(1, 'PHONE', MSG_BODY)` / `DECRYPT_FUNCTION(1, 'PHONE', RE_BODY)`

### 백업 시 본문 길이만 보관 — `USE_BACKUP_PERSONAL_DATA_PROCESSING` (Y/N)

발송 완료 메시지가 메시지 테이블에서 로그 테이블로 이동할 때, `MSG_BODY` / `RE_BODY` 의 **내용 대신 길이만 저장**합니다 (개인정보 최소 보관).

### 첨부파일 관리 — `FILE_HANDLING_MODE`

| 값 | 동작 |
| --- | --- |
| `0` | (기본값) 첨부파일 데이터를 `biz_msg` 에서 관리. `ATTACHED_FILE` 에 파일명, 여러 개는 `|` 로 구분 |
| `1` | `FILE_PATH1~FILE_PATH5` 컬럼(사용자 추가)으로 관리. 파일명을 포함한 전체 경로 입력 |
| `2` | `BIZ_ATTACHMENTS` 테이블로 관리. 데이터 타입 입력 필요, 여러 개는 동일 `MSG_KEY` 입력 |

`FILE_HANDLING_MODE = 2` 의 데이터 타입: `FILE`(기본, 파일명) / `HTTP`(http·https URL) / `JSON`(알림톡·브랜드메시지 버튼 정보 JSONString).

`FILE_HANDLING_MODE = 2` 사용 시 `biz_msg.ATTACHED_FILE` 에 `MSG_KEY` 를 넣고, 같은 키로 `BIZ_ATTACHMENTS` 테이블에 첨부 데이터를 등록합니다.

```
INSERT INTO biz_msg (
  MSG_TYPE, CMID, REQUEST_TIME, SEND_TIME, DEST_PHONE, SEND_PHONE,
  MSG_BODY, TEMPLATE_CODE, SENDER_KEY, NATION_CODE,
  RE_TYPE, RE_BODY, ATTACHED_FILE
) VALUES (
  6, '201XXXXXXXXX', NOW(), NOW(), '01012341234', '0212341234',
  '홍길동 고객님 다우기술 비즈메시지 프로모션에 당첨 되었습니다', '{템플릿코드}', '{발신프로필키}', '82',
  'MMS', '[다우기술] 응모하신 프로모션에 당첨되었습니다.', 'msgKey1'
);
```

`BIZ_ATTACHMENTS` 테이블 — 동일 `MSG_KEY`(`msgKey1`) 로 첨부 데이터 등록:

| `MSG_KEY` | `SEQ` | `TYPE` | `CONTENTS` |
| --- | --- | --- | --- |
| `msgKey1` | 1 | `FILE` | 파일명 |
| `msgKey1` | 2 | `HTTP` | `http://www.daou.co.kr/file/jpg.jpg` |
| `msgKey1` | 3 | `JSON` | 버튼 정보 JSON (아래) |

```
{
  "button": [
    { "name": "미리 주문하기", "type": "WL", "url_mobile": "http://www.bizppurio.com" },
    { "name": "상담원 연결하기", "type": "MD" },
    { "name": "발송 알림 설정 보기", "type": "AL", "scheme_android": "daumapps://open", "scheme_ios": "daumapps://open" }
  ]
}
```

### 첨부파일 경로 — `ABSOLUTE_PATH_OPTION` (Y/N)

| 값 | 동작 |
| --- | --- |
| `N` | (상대경로) 파일명만 입력하고 기본 경로 `./spool` 에 파일을 둔다 |
| `Y` | (절대경로) 파일명을 포함한 전체 경로(예: `D:/img.jpg`)를 입력 |

### 모듈 재구동 시 오래된 데이터 처리 — `SEND_VALID_TIME` (0~1440, 분)

모듈 재구동 / DB 세션 재연결 시 실패 처리할 발송 유효 시간 (대상: `SEND_TIME`).

| 값 | 의미 |
| --- | --- |
| `0` | 사용 안 함 |
| `1~1440` | 재구동 시점 기준 N분 지난 미발송 메시지 실패 처리 (결과코드 `9034`) |
| 기본값 | `180` (3시간) |

### 잔존 메시지 처리

네트워크 장애·모듈 비정상 종료 등으로 백업되지 않고 메시지 테이블에 남은 메시지를, 서버에 결과 재요청하거나 실패 처리하여 백업 테이블로 이동시키는 옵션입니다. BATCH 스레드에서 5분 간격으로 동작합니다.

> ⚠️ 사용 시 비즈 서버 통계와 고객사 DB 통계 간 불일치가 발생할 수 있습니다.

| 옵션 | 기본값 | 설명 |
| --- | --- | --- |
| `REPORT_RECONFIRM_OPTION` (Y/N) | `N` | `STATUS=1`(발송 후 대기)이고 발송 후 55시간(최대 리포트 타임아웃) 경과 메시지에 리포트 1회 재요청 후 `STATUS=3` 으로 변경. 3일 내 미수신 시 실패 처리 (결과코드 `9023`) |
| `REPORT_RECONFIRM_COUNT` | `100` | 재요청 대상 메시지 FETCH COUNT |
| `REMOVE_PRESEND_MSG_OPTION` (Y/N) | `N` | `STATUS=7`(발송 중)이고 발송 후 3일(클라이언트 타임아웃) 경과 메시지를 실패 처리 (결과코드 `9037`) |
| `REMOVE_PRESEND_MSG_COUNT` | `100` | 처리 대상 메시지 FETCH COUNT |
| `WAIT_REPORT_HOUR` | `55` | 최대 리포트 타임아웃 시간 (최소 `55`) |
| `CLIENT_TIMEOUT_HOUR` | `72` | 클라이언트 타임아웃 시간 (최소 `56` = `WAIT_REPORT_HOUR + 1`) |

### RCS 대행사 ID — `AGENCY_ID` (`biz_client_v3000` 이상)

RCS 발신번호(chatbotId)에 대한 발송 권한이 대행사에 있는지 체크하며, 권한이 없으면 발송 실패합니다. 다우기술 대행사 ID 는 `daoutech` 이며 미설정 시 기본값으로 적용됩니다.

- `config/data/columns-{DBMS}.json` 의 RCS `BUTTONS` 컬럼 정보 아래 `AGENCY_ID` 컬럼 정보 추가
- 기존 `BIZ_RCS` 테이블이 있으면 `AGENCY_ID VARCHAR(20)` 컬럼 추가
- 모듈 재구동 후 `AGENCY_ID` 컬럼에 대행사 ID 를 입력하여 발송

### 최초 발신사업자 식별코드 — `RESELLERCODE` (`biz_client_v3015` 이상)

특수부가사업자 등록번호(숫자 9자리)를 의미합니다.

- `USE_RESELLERCODE` (Y/N, 기본 `N`) 설정 시 메시지 발송에 식별코드를 포함하여 전송
- 메시지/로그 테이블에 `RESELLERCODE VARCHAR(10)` 컬럼 추가 (신규 설치 시 옵션 설정 후 구동하면 자동 추가)

### CMID 추가 문자 사용 (`biz_client_v4000` 이상)

`CMID` 는 기본적으로 ASCII 범위 문자열만 입력 가능합니다.

| 값 | 동작 |
| --- | --- |
| `Y` | (기본값) ASCII 문자로 구성된 `CMID` 만 사용 |
| `N` | ASCII 범위 외 한글·특수문자 포함 허용 |

> ⚠️ 허용되지 않는 `CMID` 입력 시 발송이 실패할 수 있습니다.

### 중복 발송 제한 — `DUPLICATE_SENDING_POLICY` (`biz_client_v3016` 이상)

BIZCLIENT는 동일 메시지의 의도치 않은 중복 발송을 차단합니다. 옵션은 `uds.conf` 에서 설정합니다.

| 옵션 | 값 | 설명 |
| --- | --- | --- |
| `DUPLICATE_SENDING_POLICY` | `0` | 사용 안 함 (기본값) |
|  | `1` | 수신번호 기준으로 중복 차단 |
|  | `2` | 수신번호 + 메시지 내용 기준으로 차단 |
| `DUPLICATE_SENDING_MONIT_SECONDS` | 30 ~ 60 | 모니터링 시간 (초). 기본 30 |
| `DUPLICATE_SENDING_ALLOW_COUNT` | 1 이상 | 모니터링 시간 내 허용 발송 수. 기본 1 |

- **적용 채널**: `SMS`, `LMS`, `MMS`, `AT`, `BT`, `RCS`. 버튼·첨부파일·대체 발송 본문은 비교하지 않습니다.
- 차단된 메시지는 `CALL_STATUS = 9041` (중복 발송 차단) 로 기록됩니다.

> 예) 사용자가 OTP 인증 버튼을 1초 내 3번 클릭 → `정책 = 2` + `ALLOW_COUNT = 1` 이면 1건만 발송되고 나머지 2건은 차단됩니다.

## RCS 연동 규격

BIZCLIENT RCS 발송의 상세 규격입니다 — MESSAGEBASE\_ID(안드로이드/통합)·MESSAGE(RCS\_BODY·media)·BUTTON.

## MESSAGEBASE\_ID

`MESSAGEBASE_ID` 는 메시지 포맷(카드 유형)을 지정하는 코드입니다. 발송하려는 메시지 형태에 맞는 ID 를 선택해 입력합니다. RCS 는 **안드로이드 RCS** 와 **통합 RCS** 두 체계로 구분됩니다.

### 안드로이드 RCS 와 통합 RCS 공통점

- 동일한 상품 타입 및 발송 변수 이용

### 안드로이드 RCS 와 통합 RCS 차이점

| 구분 | (기존) 안드로이드 RCS | 통합 RCS |
| --- | --- | --- |
| 수신 가능 단말 | 안드로이드 채팅+ 지원 단말 (예: 삼성 갤럭시) | 국내 이통사와 RCS 연동된 모든 단말 \* 애플 단말기 iOS 26 이상 \* 안드로이드 10(Q-OS 버전) 이상 |
| (광고) 표기 | `BIZ_RCS.HEADER` 로 (광고) 표기 설정 | 메시지 타이틀 혹은 본문에 직접 표기하여 메시지 발송 (글자수에 포함) |
| 무료수신거부 표기 | `BIZ_RCS.FOOTER` 로 표기 설정 | 메시지 본문 끝에 직접 표기하여 메시지 발송 (글자수에 포함됨) |
| CopyAllowed | 지원 — 메시지별 사용자 복사 가능 여부 설정 가능 | 미지원 — 단말 정책에 따라 복사 기능 제공됨 |
| KISA 안심마크 (확인된 발신번호) | 삼성 단말에 한하여 표시 | 삼성 단말에 한하여 표시 |
| 대화방 메뉴 | 지원 | 삼성 단말에 한하여 제공, 연내 타 제조사 확대 예정 |
| 오픈리치카드 | 지원 | 미지원 |

### 안드로이드 RCS

안드로이드 채팅+ 지원 단말(예: 삼성 갤럭시)에서 수신 가능한 표준 포맷입니다.

#### MESSAGEBASE\_ID 목록

| MESSAGEBASE\_ID | 상품 | 메시지 타입 | 카드(형태) | 카드 장수 | 카드별 최대 버튼 수 | 최대 본문 글자 수 |
| --- | --- | --- | --- | --- | --- | --- |
| `SS000000` | SMS | Standalone | - | 1 | 1 | 100 |
| `SL000000` | LMS | Standalone | - | 1 | 3 | 1300 |
| `SMwThT00` | MMS | 세로형(Tall) | - | 1 | 2 | 1300 |
| `SMwThM00` | MMS | 세로형(Medium) | - | 1 | 2 | 1300 |
| `CMwMhM0200` | MMS | 슬라이드형(Medium, 2장) | Carousel Medium | 2 | 2 | 글자/라인수 정의 표 참고 |
| `CMwMhM0300` | MMS | 슬라이드형(Medium, 3장) | Carousel Medium | 3 | 2 | 글자/라인수 정의 표 참고 |
| `CMwMhM0400` | MMS | 슬라이드형(Medium, 4장) | Carousel Medium | 4 | 2 | 글자/라인수 정의 표 참고 |
| `CMwMhM0500` | MMS | 슬라이드형(Medium, 5장) | Carousel Medium | 5 | 2 | 글자/라인수 정의 표 참고 |
| `CMwMhM0600` | MMS | 슬라이드형(Medium, 6장) | Carousel Medium | 6 | 2 | 글자/라인수 정의 표 참고 |
| `CMwShS0200` | MMS | 슬라이드형(Small, 2장) | Carousel Small | 2 | 2 | 글자/라인수 정의 표 참고 |
| `CMwShS0300` | MMS | 슬라이드형(Small, 3장) | Carousel Small | 3 | 2 | 글자/라인수 정의 표 참고 |
| `CMwShS0400` | MMS | 슬라이드형(Small, 4장) | Carousel Small | 4 | 2 | 글자/라인수 정의 표 참고 |
| `CMwShS0500` | MMS | 슬라이드형(Small, 5장) | Carousel Small | 5 | 2 | 글자/라인수 정의 표 참고 |
| `CMwShS0600` | MMS | 슬라이드형(Small, 6장) | Carousel Small | 6 | 2 | 글자/라인수 정의 표 참고 |
| `OMHITV0001` | MMS (신규) | 이미지 & 타이틀 강조형 (3:4) | - | 1 | 2 | 150 |
| `OMHITS0001` | MMS (신규) | 이미지 & 타이틀 강조형 (1:1) | - | 1 | 2 | 150 |
| `OMHIMV0001` | MMS (신규) | 이미지 강조형 (3:4) | - | 1 | 2 | 150 |
| `OMHIMS0001` | MMS (신규) | 이미지 강조형 (1:1) | - | 1 | 2 | 150 |
| `OMTBNV0001` | MMS (신규) | 썸네일형 (세로) | - | 1 | 2 | 150 |
| `OMTBNH0001` | MMS (신규) | 썸네일형 (가로) | - | 1 | 2 | 150 |
| `OMSNSS0001` | MMS (신규) | SNS형 | - | 1 | 2 | 150 |
| `OMSNSH0001` | MMS (신규) | SNS형 | - | 1 | 2 | 150 |

> 카드 장수가 2 이상인 슬라이드형(Carousel)의 본문 글자수는 고정값이 아니라 아래 **카드 유형별 글자수/라인수 정의** 표를 따릅니다.

#### 템플릿군 (접두 코드별)

| 시작 접두 | 상품/구분 | 템플릿 유형 | 카드 장수 | 카드별 최대 버튼 수 | 최대 본문 글자 수 |
| --- | --- | --- | --- | --- | --- |
| `UBR` 로 시작 | 템플릿별 상이 (텍스트 템플릿) | 서술(description) | 1 | 2 | 90 |
| `UBR` 로 시작 | 템플릿별 상이 (텍스트 템플릿) | 스타일(cell) | 1 | 2 | 90 |
| `UBR` 로 시작 | 템플릿별 상이 (텍스트 템플릿) | 기본(free) | 1 | 0 | 90 |
| `IBR` 로 시작 | 템플릿별 상이 (이미지 템플릿) | 신규 MMS 동일 (8종) | 1 | 2 | 1000 |
| `LBR` 로 시작 | 템플릿별 상이 (LMS 템플릿) | LMS 템플릿 (4종) | 1 | 2 | 1300 |

> - 포토 여부 / 타이틀 글자 수 / 버튼 개수에 따라 입력 가능한 본문 글자 수가 상이할 수 있습니다.
> - 이미지 사이즈 규격이 일치해야 정상적으로 발송됩니다.

---

#### 카드 유형별 글자수 / 라인수 정의

- **글자 수**: 1줄당 정상적으로 표현 가능한 글자 수, 한글 '가' 기준 측정
- **줄(라인) 수**: expand 없이 메시지 버블 최대 크기에서 표현 가능한 description 줄 수

##### LMS (Standalone, No media)

**[글자 수]**

| 항목 | 타이틀 | 디스크립션 | 버튼명 |
| --- | --- | --- | --- |
| 글자 수 | 16 | 18 | 17 |

**[줄 수 (접혀있는 경우)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 | 버튼 3개 |
| --- | --- | --- | --- | --- |
| 디스크립션 only | 28 | 26 | 24 | 22 |
| 타이틀 1줄 + 디스크립션 | 27 | 25 | 23 | 20 |
| 타이틀 2줄 + 디스크립션 | 26 | 23 | 21 | 19 |

##### MMS (Standalone Media Top — 세로형)

**[글자 수]**

| 항목 | 타이틀 | 디스크립션 | 버튼명 |
| --- | --- | --- | --- |
| 글자 수 | 16 | 18 | 17 |

**[줄 수 (Media Tall 인 경우, 접혀있는 경우)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 9 | 8 | 6 |
| 타이틀 1줄 + 디스크립션 | 8 | 6 | 4 |
| 타이틀 2줄 + 디스크립션 | 7 | 5 | 3 |

**[줄 수 (Media Medium 인 경우, 접혀있는 경우)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 15 | 13 | 11 |
| 타이틀 1줄 + 디스크립션 | 14 | 12 | 10 |
| 타이틀 2줄 + 디스크립션 | 13 | 11 | 9 |

##### MMS (Carousel Medium — 슬라이드형)

**[글자 수]**

| 항목 | 타이틀 | 디스크립션 | 버튼명 |
| --- | --- | --- | --- |
| 글자 수 | 13 | 14 | 13 |

**[줄 수 (Media 없는 경우, RCS A2P 단말 기준)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 28 | 26 | 23 |
| 타이틀 1줄 + 디스크립션 | 27 | 25 | 23 |
| 타이틀 2줄 + 디스크립션 | 26 | 23 | 21 |
| 타이틀 3줄 + 디스크립션 | 24 | 22 | 20 |

**[줄 수 (Media Medium 인 경우, RCS A2P 단말 기준)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 17 | 15 | 13 |
| 타이틀 1줄 + 디스크립션 | 16 | 14 | 12 |
| 타이틀 2줄 + 디스크립션 | 15 | 13 | 11 |
| 타이틀 3줄 + 디스크립션 | 14 | 12 | 10 |

##### MMS (Carousel Small — 슬라이드형)

**[글자 수]**

| 항목 | 타이틀 | 디스크립션 | 버튼명 |
| --- | --- | --- | --- |
| 글자 수 | 5 | 6 | 5 |

**[줄 수 (Media Short 인 경우, RCS A2P 단말 기준)]**

| 구성 | 버튼 0개 | 버튼 1개 | 버튼 2개 |
| --- | --- | --- | --- |
| 디스크립션 only | 20 | 18 | 16 |
| 타이틀 1줄 + 디스크립션 | 19 | 17 | 15 |
| 타이틀 2줄 + 디스크립션 | 18 | 16 | 14 |
| 타이틀 3줄 + 디스크립션 | 17 | 15 | 13 |
| 타이틀 4줄 + 디스크립션 | 16 | 14 | 12 |
| 타이틀 5줄 + 디스크립션 | 15 | 13 | 11 |

---

### 통합 RCS

통합 RCS 는 단말 제조사 상관없이 국내 이통 3사에서 제공하는 RCS 표준 규격입니다.
(안드로이드, iOS 26 버전 이상 지원)

#### 통합 RCS — MESSAGEBASE\_ID

| MESSAGEBASE\_ID | 상품 | 유형명 | 최대 버튼 수 | 최대 본문 글자 수 |
| --- | --- | --- | --- | --- |
| `RPSSAXX001` | RCS SMS | 통합 SMS 카드 | 1 | 100 |
| `RPLSAXX001` | RCS LMS | 통합 LMS 카드 | 3 | 1300 |
| `RPMSMMX001` | RCS MMS | 통합 MMS 카드 M | 2 | 1300 |
| `RPMSMTX001` | RCS MMS | 통합 MMS 카드 T | 2 | 1300 |
| 브랜드별 자동 발급\* | 텍스트 템플릿 | 통합 프리 템플릿 | - | 90 |
| 템플릿 등록 필요 | 텍스트 템플릿 | 통합 정보성 템플릿 | - | 90 |
| 템플릿 등록 필요 | 이미지 템플릿 | 통합 이미지 템플릿 M | - | 500 |
| 템플릿 등록 필요 | 이미지 템플릿 | 통합 이미지 템플릿 T | - | 500 |

> **\* 프리템플릿**
>
> - 브랜드마다 "통합 프리 템플릿" 1개가 자동 발급됩니다. (기존 발급된 프리 템플릿은 2026년 7월 31일까지 동시 이용 가능)
> - 사전 등록 없이 자동 발급되는 정보성 템플릿입니다. 각 브랜드의 통합 프리 템플릿 `messagebase ID` 는 RBC 접속하여 템플릿 목록에서 확인할 수 있습니다.

#### 통합 RCS 기본 정보 (안드로이드 RCS 와의 분기)

| 키 | 설명 |
| --- | --- |
| `HEADER` | `"0"`만 허용, `"1"` 입력 시 실패 처리됨. "(광고)" 표시 기능 지원되지 않음 |
| `FOOTER` | 사용하지 않는 값입니다. |
| `COPY_ALLOWED` | 사용하지 않는 값입니다. |
| `title` (RCS\_BODY) | 광고 메시지인 경우 "(광고)" 표기 |
| `description` (RCS\_BODY) | 광고 메시지인 경우 본문 끝에 무료수신거부 방식 표기 필요 |

## MESSAGE

메시지 본문(`RCS_BODY`)과 첨부 미디어(`media`) 규격입니다.

`RCS_BODY` 는 사용하는 `MESSAGEBASE_ID`(메시지 포맷)의 유형에 따라 입력 키가 달라집니다. 모든 값은 `string` 타입입니다.

### 1. 리치카드 넘버링형 (신규 MMS 제외)

| 키 | 필수 | 설명 |
| --- | --- | --- |
| `media` | N | main 첨부파일 |
| `title` | N | main 제목 |
| `description` | N | main 내용 |
| `title1` | N | 1번째 카드 제목 |
| `description1` | N | 1번째 카드 본문 텍스트 |
| `media1` | N | 1번째 카드 첨부파일 |

> 리치카드 개수·순서에 따라 넘버링합니다 (`title1`, `title2` …).

### 2. 이미지 & 타이틀 강조형 (신규 MMS · 이미지 템플릿)

| 키 | 필수 | 설명 |
| --- | --- | --- |
| `media` | Y | 이미지 (\*이미지 템플릿 발송 시 불필요) |
| `mediaUrl` | N | 이미지 클릭 시 랜딩 URL |
| `title` | N | 제목 |
| `subTitle1` | Y | 소제목 1 |
| `subDesc1` | Y | 소본문 1 |

### 3. 이미지 강조형 · SNS형 (신규 MMS · 이미지 템플릿)

| 키 | 필수 | 설명 |
| --- | --- | --- |
| `media` | Y | 이미지 (\*이미지 템플릿 발송 시 불필요) |
| `mediaUrl` | N | 이미지 클릭 시 랜딩 URL |
| `title` | N | 제목 |
| `description` | Y | 본문 |

### 4. 썸네일형 (세로) (신규 MMS · 이미지 템플릿)

| 키 | 필수 | 설명 |
| --- | --- | --- |
| `media` | Y | main 첨부파일 (\*이미지 템플릿 발송 시 불필요) |
| `mediaUrl` | N | 이미지 클릭 시 랜딩 URL |
| `title` | N | 제목 |
| `description` | N | 본문 |
| `subMedia1` | Y | 서브 이미지 1 |
| `subMediaUrl1` | N | 서브 이미지 1 클릭 시 랜딩 URL |
| `subDesc1` | Y | 소본문 1 |

> 리치카드 개수·순서에 따라 넘버링합니다 (`subMedia1`, `subMedia2` …).

### 5. 썸네일형 (가로) (신규 MMS · 이미지 템플릿)

| 키 | 필수 | 설명 |
| --- | --- | --- |
| `media` | Y | main 첨부파일 (\*이미지 템플릿 발송 시 불필요) |
| `mediaUrl` | N | 이미지 클릭 시 랜딩 URL |
| `title` | N | 제목 |
| `description` | N | 본문 |
| `subMedia1` | Y | 서브 이미지 1 |
| `subMediaUrl1` | N | 서브 이미지 1 클릭 시 랜딩 URL |

### RCS\_BODY 넘버링 규칙

카드가 1개면 넘버 없이, 2개 이상이면 카드 순서대로 `1`, `2` … 를 키 뒤에 붙입니다.

```
// 카드 1개
{
  "title": "카드",
  "media": "등록된 이미지 URL",
  "description": "안녕하세요!"
}
```

```
// 카드 2개 이상
{
  "title1": "카드",
  "media1": "등록된 이미지 URL",
  "description1": "안녕하세요!",
  "title2": "카드2",
  "media2": "등록된 이미지 URL",
  "description2": "안녕하세요!"
}
```

통합 RCS LMS (`MESSAGEBASE_ID = RPLSAXX001`) 예시:

```
{
  "title": "줄바꿈 없는 14자 권장, 최대 30자",
  "description": "(광고)\n안녕하세요! RCS LMS\n무료 수신 거부 080-1234-5678"
}
```

### media 종류

#### 1. 이미지

비즈뿌리오 사이트 `[메시지관리] – [RCS 관리] – [RCS 이미지 관리]` 에서 등록한 이미지를 사용합니다.
이미지는 **등록일로부터 365일간 발송 가능**하며 이후 자동 삭제됩니다.

| 항목 | 값 |
| --- | --- |
| 포맷 | `maapfile://{fileId}` |
| 예시 | `"media": "maapfile://..."` |

#### 2. 동영상 스트리밍

- 3가지 형태의 YouTube URL 주소를 지원하며, **정확한 형식을 준수해야** 합니다 (일부만 일치하면 실패).
- 동영상 썸네일은 등록된 이미지만 사용 가능하며, YouTube URL 뒤에 콤마(`,`)와 함께 입력합니다. **콤마 외 공백을 포함하면 실패**합니다.
- 동영상 발송 시 Footer 에 `동영상 재생 시 데이터 요금제가 적용됩니다.` 문구가 자동 삽입됩니다.

| 항목 | 값 |
| --- | --- |
| 포맷 | `https://www.youtube.com/watch?v=[videoId],maapfile://{썸네일 fileId}` |
| 포맷 | `https://youtu.be/[videoId],maapfile://{썸네일 fileId}` |
| 포맷 | `https://m.youtube.com/watch?v=[videoId],maapfile://{썸네일 fileId}` |
| 예시 | `"media1": "https://www.youtube.com/watch?v=...,maapfile://..."` |

## BUTTON

RCS 에 버튼 링크를 추가할 경우 `BIZ_RCS.BUTTONS` 필드에 아래 JSON 구조로 입력합니다.

> ℹ️ 외부 컬럼 키는 **대문자** `BUTTONS` 이지만, JSON 내부 키는 **소문자** `suggestions` / `action` 을 사용합니다 (JSON KEY 대소문자 구분).

| 단계 | 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- | --- |
| BUTTONS | `suggestions` | Array | N | 버튼 묶음 |
| suggestions | `action` | Object | Y | 버튼 동작 |
| action | `[Action 타입]` | Object | Y | 아래 Action 타입 중 하나 |
| action | `displayText` | text | Y | 버튼에 출력될 텍스트 |
| action | `postback` | Object | N | 콜백 데이터 |
| postback | `data` | text | Y | postback 데이터 |

### Action 타입

| # | Action | 동작 | 중첩 필드 |
| --- | --- | --- | --- |
| 1 | `urlAction` | URL 연결하기 | `openUrl` → `url` |
| 2 | `dialerAction` | 전화 연결하기 | `dialPhoneNumber` → `phoneNumber` |
| 3 | `mapAction` | 지도 보여주기 | `showLocation` → `location`(`query` / `latitude` / `longitude` / `label`), `fallbackUrl` |
| 4 | `mapAction` | 현재 위치 공유하기 | `requestLocationPush` |
| 5 | `calendarAction` | 캘린더 등록 | `createCalendarEvent` → `startTime` / `endTime` / `title` / `description` |
| 6 | `composeAction` | 메시지 전송 | `composeTextMessage` → `phoneNumber` / `text` |
| 7 | `clipboardAction` | 복사하기 | `copyToClipboard` → `text` |

### BUTTONS 카드 패딩 규칙

`BUTTONS` 배열은 **카드 수만큼** 요소를 가집니다. 버튼이 없는 카드는 빈 객체 `{}` 로 패딩합니다.
예) 카드 3장에 버튼이 각각 2개 / 0개 / 1개인 경우:

```
{
  "buttons": [
    { "suggestions": [
        { "action": {
            "urlAction": { "openUrl": { "url": "https://..." } },
            "displayText": "Open website or deep link",
            "postback": { "data": "set_by_chatbot_open_url" }
        }},
        { "action": {
            "urlAction": { "openUrl": { "url": "https://..." } },
            "displayText": "Open website or deep link",
            "postback": { "data": "set_by_chatbot_open_url_2" }
        }}
    ]},
    {},
    { "suggestions": [
        { "action": {
            "urlAction": { "openUrl": { "url": "https://..." } },
            "displayText": "Open website or deep link",
            "postback": { "data": "set_by_chatbot_open_url_2" }
        }}
    ]}
  ]
}
```

## 카카오 연동 규격

BIZCLIENT 카카오 발송(알림톡·브랜드메시지)의 ATTACHMENT JSON 상세 규격과 발송 예시입니다. 각 발송 페이지(알림톡·브랜드메시지)에서 이 페이지를 참조합니다.

## 알림톡

알림톡 발송 시 `ATTACHED_FILE` 로 첨부하는 ATTACHMENT JSON 규격입니다.

### ATTACHMENT JSON 최상위 구조

| 키 | 타입 | 설명 |
| --- | --- | --- |
| `button` | BUTTON[] | 버튼 배열 (최대 5개) |
| `item_highlight` | ITEMHIGHLIGHT | 아이템 하이라이트 |
| `item` | ITEM | 아이템리스트와 요약정보 |
| `extra` | EXTRA | 추가 기능 (강조표기·바로연결·헤더·대표링크·`msg_type` AT/AI 구분 등) |

### 알림톡 버튼

#### ATTACHMENT JSON · `button[]` 필드

JSON 파일 최상위 `button` 은 배열이며 **최대 5개**. 바로연결(`extra.supplement.quick_reply`) 사용 시 버튼은 **2개로 제한**.

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `name` | text(14) | Y | 버튼 제목. `AC` 타입인 경우 `채널 추가` 로 고정 |
| `type` | text(2) | Y | 버튼 타입 (아래 [버튼 타입] 참조) |
| `url_pc` | text | - | PC 환경에서 버튼 클릭 시 이동할 URL |
| `url_mobile` | text | - | Mobile 환경에서 버튼 클릭 시 이동할 URL |
| `scheme_ios` | text | - | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme |
| `scheme_android` | text | - | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme |
| `chat_extra` | text(50) | - | 상담톡/봇 전환 시 전달할 메타정보 |
| `chat_event` | text(50) | - | 봇 전환 시 연결할 봇 이벤트명 |
| `plugin_id` | text(24) | - | 플러그인 ID |
| `relay_id` | text | - | 플러그인 실행 시 `X-Kakao-Plugin-Relay-Id` 헤더로 전달받을 값 |
| `oneclick_id` | text | - | 원클릭 결제 플러그인에서 사용하는 결제 정보 |
| `product_id` | text | - | 원클릭 결제 플러그인에서 사용하는 결제 정보 |
| `biz_form_id` | text | - | 카카오 비즈니스폼 ID (`BF` 타입) |
| `tel_number` | text(14) | - | 전화번호 (숫자·하이픈 포함 최대 14자). ex) `1234-5678`, `12345678` |
| `map_address` | text | - | 지도보기 버튼 클릭 시 보여줄 주소. ex) `경기도 성남시 분당구 판교역로 166` |
| `map_coordinates` | text | - | 지도보기 버튼 클릭 시 보여줄 위경도 좌표. `map_address` 와 함께 입력 시 `map_address` 우선. ex) `37.3955,127.1103` |

#### 버튼 타입

| `type` | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| `WL` | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| `AL` | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| `DS` | 버튼 클릭 시 배송조회 페이지로 이동 | name type | name type |
| `BK` | 해당 버튼 텍스트 발송 | name type | name type |
| `MD` | 해당 버튼 텍스트 + 메시지 본문 발송 | name type | name type |
| `BC` | 상담톡을 이용하는 카카오톡 채널만 이용 가능 | name type chat\_extra | name type |
| `BT` | 카카오 I 오픈빌더의 챗봇을 사용하는 카카오톡 채널만 이용 가능 | name type chat\_extra chat\_event | name type |
| `AC` | 버튼 클릭 시 카카오톡 채널 추가 | name type | name type |
| `P1` | 이미지 보안 전송 플러그인 | name type | name type |
| `P2` | 개인정보이용 플러그인 | name type | name type |
| `P3` | 원클릭 결제 플러그인 | name type | name type *(다음 중 1가지 이상)* oneclick\_id product\_id |
| `BF` | 카카오 비즈니스폼을 실행 | name type biz\_form\_id | name type biz\_form\_id |
| `TN` | 전화 앱 실행 (모바일 환경에서만 이용 가능) | name type tel\_number | name type tel\_number |
| `MP` | 버튼 클릭 시 지도 보기 | name type map\_address map\_coordinates | name type *(다음 중 1가지 이상)* map\_address map\_coordinates |

#### `item_highlight` 필드 (아이템 하이라이트, JSON · N)

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `title` | text(30) | Y | 타이틀 (이미지가 있는 경우 최대 21자). 내용 마지막에 `\s` 플래그 포함 시 취소선 스타일 적용 |
| `description` | text(19) | Y | 부가정보 (이미지가 있는 경우 최대 13자) |

#### `item` 필드 (아이템리스트 + 요약정보, JSON · N)

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `list` | array | N | 아이템리스트 (최소 2개 ~ 최대 10개) |
| `list[].title` | text(6) | Y | 타이틀 |
| `list[].description` | text(23) | Y | 부가정보 |
| `summary` | json | N | 아이템 요약정보 |
| `summary.title` | text(6) | Y | 타이틀 |
| `summary.description` | text(14) | Y | 가격정보. 허용 문자: 통화기호(유니코드 통화기호, 元/円/원), 통화코드(ISO 4217), 숫자, 콤마, 소수점, 공백. 소수점 2자리까지 허용 |

#### `extra` 필드 (추가 기능, JSON)

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `title` | text(50) | - | 템플릿 내용 중 강조 표기할 핵심 정보 (강조표기) |
| `supplement` | json | - | 메시지에 첨부할 바로연결 (아래 [supplement] 참조) |
| `price` | number | - | 모먼트 광고 전환 최적화 전용. 메시지 내 포함된 가격/금액/결제금액 |
| `currency_type` | text(3) | - | 모먼트 광고 전환 최적화 전용. 가격/금액/결제금액의 통화단위. KRW·USD·EUR 등 국제 통화 코드 |
| `header` | text(16) | - | 메시지 상단에 표기할 제목 |
| `msg_type` | text | N | 카카오 발송 유형 (`AT`: 알림톡, `AI`: 이미지 알림톡). 이미지(IMAGE) 강조 유형 템플릿은 `AI` 필수 입력 |
| `link` | json | N | 대표 링크 (아래 필드) |
| `link.url_mobile` | text | - | Mobile 환경에서 클릭 시 이동할 URL |
| `link.url_pc` | text | - | PC 환경에서 클릭 시 이동할 URL |
| `link.scheme_android` | text | - | Mobile Android 환경에서 클릭 시 실행할 Application Custom Scheme |
| `link.scheme_ios` | text | - | Mobile iOS 환경에서 클릭 시 실행할 Application Custom Scheme |

### 알림톡 바로연결

#### `extra.supplement` 필드 (바로연결)

바로연결은 알림톡 하단에 가로 슬라이드 형태로 표시되며 웹/앱 연결·상담톡 전환 등을 호출하는 기능. **상담톡 또는 챗봇을 사용하는 발신프로필만** 이용 가능. **최대 10개**까지 사용 가능하며, 사용 시 **버튼 개수는 2개로 제한**.

`supplement.quick_reply[]` 필드:

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `name` | text(14) | Y | 바로연결 제목 |
| `type` | text(2) | Y | 바로연결 타입 (아래 [바로연결 타입] 참조) |
| `scheme_android` | text | - | Mobile Android 환경에서 바로연결 클릭 시 실행할 Application Custom Scheme |
| `scheme_ios` | text | - | Mobile iOS 환경에서 바로연결 클릭 시 실행할 Application Custom Scheme |
| `url_mobile` | text | - | Mobile 환경에서 바로연결 클릭 시 이동할 URL |
| `url_pc` | text | - | PC 환경에서 클릭 시 이동할 URL |
| `chat_extra` | text(50) | - | 상담톡/봇 전환 시 전달할 메타정보 |
| `chat_event` | text(50) | - | 봇 전환 시 연결할 봇 이벤트명 |

#### 바로연결 타입

| `type` | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| `WL` | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| `AL` | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| `BK` | 해당 버튼 텍스트 발송 | name type | name type |
| `BC` | 상담톡을 이용하는 카카오톡 채널만 이용 가능 | name type chat\_extra | name type |
| `BT` | 카카오 I 오픈빌더의 챗봇을 사용하는 카카오톡 채널만 이용 가능 | name type chat\_extra chat\_event | name type |
| `BF` | 카카오 비즈니스폼을 실행 | name type biz\_form\_id | name type biz\_form\_id |

#### 강조표기 / 아이템리스트 규칙

- **강조표기** (`extra.title`): 템플릿 내용 중 강조 표기할 핵심 정보. 강조 표기 타이틀은 **최대 50자**, 알림톡 본문(1000자)에 **포함되지 않음**.
- **이미지·아이템리스트**: 기본 텍스트 알림톡에 (이미지 / 헤더 / 아이템 하이라이트 / 아이템리스트 / 아이템 요약정보) 5개 항목이 추가 구성. 이미지·헤더·아이템 하이라이트 영역을 필요에 따라 **1개 이상 필수 선택**하여 템플릿 등록. 템플릿당 고정 이미지만 사용 가능. **아이템리스트는 최소 2개 ~ 최대 10개**.
- **`msg_type`**: 알림톡 템플릿 강조 유형이 이미지형(IMAGE)인 경우에만 `msg_type` 을 `AI` 로 설정해야 발송 가능. (아이템리스트형이고 이미지가 포함된 경우 `AI` 로 설정 시 발송 실패)

#### ATTACHMENT JSON 예시

버튼 (`button.json`):

```
{
  "button": [
    { "name": "미리 주문하기", "type": "WL", "url_mobile": "http://www.bizppurio.com" },
    { "name": "상담원 연결하기", "type": "MD" },
    { "name": "방송 알림 설정 보기", "type": "AL", "scheme_android": "daumapps://open", "scheme_ios": "daumapps://open" }
  ]
}
```

바로연결 (`quick_reply.json`):

```
{
  "button": [
    { "name": "비즈뿌리오 바로가기", "type": "WL", "url_mobile": "https://www.bizppurio.com/" }
  ],
  "extra": {
    "supplement": {
      "quick_reply": [
        { "name": "비즈뿌리오", "type": "WL", "url_mobile": "https://www.bizppurio.com/" },
        { "name": "봇키워드하기", "type": "BK" },
        { "name": "메시지전달하기", "type": "MD" },
        { "name": "상담톡전환", "type": "BC" }
      ]
    }
  }
}
```

강조표기 (`title.json`, 버튼 없는 경우):

```
{
  "extra": { "title": "입금 123,456 원" }
}
```

강조표기 (`title.json`, 버튼 있는 경우):

```
{
  "button": [
    { "name": "채널 추가", "type": "AC" }
  ],
  "extra": { "title": "입금 123,456 원" }
}
```

이미지형 (`alimtalk_image.json`):

```
{
  "extra": { "msg_type": "AI" }
}
```

아이템리스트형 — 헤더 + 아이템리스트 + 요약정보 (`alimtalk_item_list.json`):

```
{
  "item": {
    "list": [
      { "title": "가입일자", "description": "2021.5.23" },
      { "title": "이름", "description": "김카카오" }
    ],
    "summary": { "title": "구매가격", "description": "18,000 원" }
  },
  "extra": { "header": "카카오 가입을 환영합니다.", "msg_type": "AT" }
}
```

아이템리스트형 — 버튼 + 이미지 + 아이템 하이라이트 (`alimtalk_item_list_button.json`):

```
{
  "button": [
    { "name": "채널 추가", "type": "AC" }
  ],
  "item_highlight": { "title": "가입 환영 포인트", "description": "10,000P" }
}
```

## 브랜드메시지

### 브랜드메시지 소개

#### ATTACHMENT JSON extra (snake\_case)

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `chat_bubble_type` | text | Y | TEXT / IMAGE / WIDE / WIDE\_ITEM\_LIST / CAROUSEL\_FEED / PREMIUM\_VIDEO / COMMERCE / CAROUSEL\_COMMERCE |
| `brand_message_type` | text | Y | BASIC (기본형) / FREE (자유형) |
| `send_target` | text | Y | 발송 대상 — `marketing` (고객사의 광고성 정보 수신동의 회원) / `friend` (채널 친구) |
| `targeting` | text | N | 타겟팅 — `send_target=marketing` 일 때만 **필수**(발송 권한 신청 필요), `friend` 일 때 미사용. `M` 광고성 수신동의 회원 / `N` 수신동의 회원 ∖ 채널 친구 / `O` 수신동의 회원 ∩ 채널 친구 |
| `push_alarm` | text(1) | N | Y/N (기본 Y) |
| `group_tag_key` | text(40) | N | 그룹태그 키 (자유형) — 등록 시 템플릿 통계 확인 가능 |
| `message_variable` | object | N | 메시지 영역 변수 (key=템플릿 변수명, value=치환값) |
| `button_variable` | object | N | 버튼 링크 변수 |
| `coupon_variable` | object | N | 쿠폰 링크 변수 |
| `image_variable` | array | N | 이미지 변수 |
| `video_variable` | object | N | 비디오 변수 (PREMIUM\_VIDEO 전용) |
| `commerce_variable` | object | N | 커머스 변수 (COMMERCE 전용) |
| `carousel_variable` | array | N | 캐러셀 변수 배열 |
| `unsubscribe_phone_number` | text(13) | N | 무료수신거부 전화번호 — `send_target=marketing` 일 때만 사용 (예: 080-1234-1234) |
| `unsubscribe_auth_number` | text(10) | N | 무료수신거부 인증번호 — `marketing` 일 때만 사용, `unsubscribe_phone_number` 없이 단독 입력 불가 |
| `header` | text(25) | N | 헤더. WIDE\_ITEM\_LIST 최대 20자(BASIC 최소 1자)·PREMIUM\_VIDEO 최대 20자, 둘 다 줄바꿈 불가 |
| `additional_content` | text(34) | N | 부가정보. COMMERCE 최대 34자(공백 포함, 줄바꿈 ≤1) |
| `adult` | text(1) | N | 성인용 메시지 여부 Y/N. 기본값 N (**자유형(FREE) 전용**) |
| `carousel` | json | N | 캐러셀 객체. CAROUSEL\_FEED / CAROUSEL\_COMMERCE 시 필수. 아래 **CAROUSEL** 참조 |

#### chat\_bubble\_type 8종 · 본문 영역(message) 글자수

`extra.chat_bubble_type` 으로 말풍선 형태를 지정한다. 본문 텍스트는 `MSG_BODY` 컬럼(=`message`)에 넣는다.

| chat\_bubble\_type | 설명 | message(=MSG\_BODY) | image | item | video | commerce | carousel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `TEXT` | 텍스트 | 필수, 최대 1,300자 (줄바꿈 ≤99, URL 가능) | - | - | - | - | - |
| `IMAGE` | 이미지 | 필수, 최대 1,300자 (줄바꿈 ≤99, URL 가능) | 필수 | - | - | - | - |
| `WIDE` | 와이드 이미지 | 필수, 최대 76자 (줄바꿈 ≤5) | 필수 | - | - | - | - |
| `WIDE_ITEM_LIST` | 와이드 리스트 | 사용 안 함 | - | 필수 | - | - | - |
| `PREMIUM_VIDEO` | 프리미엄 동영상 | 선택, 최대 76자 (줄바꿈 ≤5) | - | - | 필수 | - | - |
| `COMMERCE` | 커머스 | 사용 안 함 | 필수 | - | - | 필수 | - |
| `CAROUSEL_FEED` | 캐러셀 피드 | 사용 안 함 | - | - | - | - | 필수 |
| `CAROUSEL_COMMERCE` | 캐러셀 커머스 | 사용 안 함 | - | - | - | - | 필수 |

- `unsubscribe_phone_number` / `unsubscribe_auth_number` 는 **둘 다 미입력 시** 발신프로필에 등록된 무료수신거부 정보로 발송된다. `unsubscribe_phone_number` 없이 `unsubscribe_auth_number` 만 입력은 불가.

#### 전문방식 본문 객체 스키마 (brand\_message\_type=BASIC)

전문방식은 등록된 템플릿 구조를 기준으로 **본문 객체를 직접** 전송한다. 변수가 존재하는 영역의 정보만 전달하고(변수 없는 영역은 생략), 변수가 있는 버튼을 보낼 때는 `type` 필드를 함께 전달한다(변수 없는 버튼도 `type` 필요).

##### image (json) — IMAGE·WIDE·COMMERCE 필수

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `img_url` | text | (BASIC: N) / **FREE: Y** | 이미지 업로드 API로 등록한 이미지 URL |
| `img_link` | text(1000) | N | 이미지 클릭 시 이동 URL. 미설정 시 카카오톡 내 이미지 뷰어 사용 |

##### item (json) — WIDE\_ITEM\_LIST 필수 (ITEM 구조)

`item.list[]` 배열. 와이드 아이템 목록.

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `list` | array | (BASIC: N) / **FREE: Y** | 와이드 아이템 목록 |
| `list[].title` | text(25) | 조건부 | 아이템 제목. 1번째 최대 25자, 2~5번째 최대 30자 (줄바꿈 ≤1) |
| `list[].img_url` | text | (BASIC: N) / **FREE: Y** | 아이템 이미지 URL |
| `list[].url_mobile` | text(1000) | (BASIC: N) / **FREE: Y** | Mobile 클릭 시 이동 URL |
| `list[].url_pc` | text(1000) | N | PC 클릭 시 이동 URL |
| `list[].scheme_android` | text(1000) | N | Android custom scheme |
| `list[].scheme_ios` | text(1000) | N | iOS custom scheme |

##### coupon (json) — 메시지 최하단에 쿠폰 추가

채널 쿠폰 URL(포맷 `alimtalk=coupon://`) 사용 시 `scheme_android`·`scheme_ios` 중 하나 필수. 기본 쿠폰 사용 시 `url_mobile` 필수.

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `title` | text | (BASIC: N) / **FREE: Y** | 쿠폰 제목. 형식: `${숫자}원 할인쿠폰`(1~99,999,999) / `${숫자}% 할인 쿠폰`(1~100) / `배송비 할인 쿠폰` / `${7자 이내} 무료 쿠폰` / `${7자 이내} UP 쿠폰` |
| `description` | text | (BASIC: N) / **FREE: Y** | 쿠폰 설명. WIDE·WIDE\_ITEM\_LIST·PREMIUM\_VIDEO 최대 18자, 그 외 최대 12자 (줄바꿈 불가) |
| `url_pc` | text(1000) | N | PC 클릭 시 이동 URL |
| `url_mobile` | text(1000) | 조건부 | Mobile 클릭 시 이동 URL |
| `scheme_android` | text(1000) | 조건부 | Android custom scheme |
| `scheme_ios` | text(1000) | 조건부 | iOS custom scheme |

> 변수분리 방식에서 `coupon_variable` 에 `"ESCAPE_COUPON"` 을 넣으면 템플릿에 쿠폰이 있어도 말풍선에 포함하지 않는다.

##### commerce (json) — COMMERCE 필수

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `title` | text(30) | (BASIC: N) / **FREE: Y** | 상품 제목. 최대 30자 (줄바꿈 불가) |
| `regular_price` | number | (BASIC: N) / **FREE: Y** | 정상 가격 (0 ~ 99,999,999) |
| `discount_price` | number | N | 할인 후 가격 (0 ~ 99,999,999) |
| `discount_rate` | number | N | 할인율 (1 ~ 100). 할인가격 존재 시 discount\_rate·discount\_fixed 중 하나 필수 |
| `discount_fixed` | number | N | 정액 할인 가격 (0 ~ 999,999). 할인가격 존재 시 discount\_rate·discount\_fixed 중 하나 필수 |

##### video (json) — PREMIUM\_VIDEO 필수

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `video_url` | text(500) | (BASIC: N) / **FREE: Y** | 카카오TV 동영상 URL |
| `thumbnail_url` | text(500) | 조건부 | 동영상 썸네일용 이미지 URL(이미지 업로드 API 등록). 없으면 기본 썸네일. **비공개 동영상이면 필수** |

### 브랜드메시지 버튼

개수 제약: TEXT·IMAGE = 쿠폰 적용 시 최대 4개·그 외 최대 5개 / WIDE·WIDE\_ITEM\_LIST = 최대 2개 / PREMIUM\_VIDEO = 최대 1개 / COMMERCE = 최소 1개·최대 2개

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `name` | text(28) | (BASIC: 생략 가능) / **FREE: Y** | 버튼 제목. TEXT·IMAGE 최대 14자, 그 외 최대 8자 |
| `type` | text(2) | Y | 버튼 타입(AC/WL/AL/BK/MD/BC/BT/BF). 버튼 타입별 속성 참조 |
| `url_pc` | text(1000) | N | PC 클릭 시 이동 URL |
| `url_mobile` | text(1000) | 조건부 | Mobile 클릭 시 이동 URL |
| `scheme_ios` | text(1000) | 조건부 | iOS custom scheme |
| `scheme_android` | text(1000) | 조건부 | Android custom scheme |
| `chat_extra` | text | N | 상담톡↔카카오톡 챗봇 전환 시 전달 메타정보 (BC/BT) |
| `chat_event` | text | N | 카카오톡 챗봇 전환 시 연결할 이벤트명 (BT) |
| `biz_form_key` | text | N | 비즈니스폼 업로드로 발급받은 bizFormKey (BF) |

**버튼 타입별 속성**

| 타입 | 설명 | 사용 가능 파라미터 | 필수 파라미터 |
| --- | --- | --- | --- |
| `AC` | 카카오톡 채널 추가(강조형 노란 버튼). TEXT·IMAGE는 첫 번째, 그 외 두 번째 버튼 위치. `name`은 "채널 추가" 고정. 캐러셀형은 전체 통틀어 1개. targeting M·N만 가능 | name type | name type |
| `WL` | 지정한 웹 링크로 이동 | name type url\_mobile url\_pc | name type url\_mobile |
| `AL` | 지정한 앱 스킴 또는 웹 링크로 이동 | name type scheme\_android scheme\_ios url\_mobile url\_pc | name type *(다음 중 2가지 이상)* scheme\_android scheme\_ios url\_mobile |
| `BK` | 버튼 텍스트 발송 | name type | name type |
| `MD` | 버튼 텍스트 + 본문 발송 | name type | name type |
| `BC` | 상담톡 채널 전용 | name type chat\_extra | name type |
| `BT` | 카카오 I 오픈빌더 챗봇 채널 전용 | name type chat\_extra chat\_event | name type |
| `BF` | 카카오 비즈니스폼 실행(강조형). AC 유무에 따라 위치 가변. `name`은 "톡에서 예약하기/설문하기/응모하기" 중 | name type biz\_form\_key | name type biz\_form\_key |

### CAROUSEL 객체 (CAROUSEL\_FEED · CAROUSEL\_COMMERCE)

`extra.carousel` 하위 `head`(인트로) · `list[]`(아이템) · `tail`(더보기)로 구성.

**리스트 개수 규칙: 인트로(head) 사용 시 1~6개, 미사용 시 2~6개.**

#### carousel.head (json) — 캐러셀 인트로 (선택)

| 키 | 타입 | 필수(BASIC / FREE) | 설명 |
| --- | --- | --- | --- |
| `header` | text(20) | (BASIC: N) / **FREE: Y** | 인트로 헤더 (줄바꿈 불가) |
| `content` | text(50) | (BASIC: N) / **FREE: Y** | 인트로 내용 (줄바꿈 ≤2) |
| `image_url` | text | (BASIC: N) / **FREE: Y** | 인트로 이미지 주소 |
| `url_mobile` | text(1000) | 조건부 | Mobile 클릭 시 이동 URL |
| `url_pc` | text(1000) | N | PC 클릭 시 이동 URL |
| `scheme_android` | text(1000) | N | Android custom scheme |
| `scheme_ios` | text(1000) | N | iOS custom scheme |

#### carousel.list[] (array) — 캐러셀 리스트

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `header` | text(20) | CAROUSEL\_FEED 필수 / CAROUSEL\_COMMERCE 사용불가 | 리스트 헤더. 최대 20자 (줄바꿈 불가) |
| `message` | text(180) | CAROUSEL\_FEED 필수 / CAROUSEL\_COMMERCE 사용불가 | 아이템 메시지. 최대 180자 (줄바꿈 ≤10) |
| `additional_content` | text(34) | N (CAROUSEL\_FEED 사용불가) | 부가 정보. 최대 34자 (줄바꿈 ≤1) |
| `attachment` | json | Y | 아이템 이미지·버튼·쿠폰·커머스 정보 (아래) |

**carousel.list[].attachment**

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `button` | array | N | 캐러셀 리스트 버튼 목록. 최소 1개·최대 2개. 항목 키: `name`(필수, TEXT·IMAGE 14자/그 외 8자) · `type`(필수) · `scheme_android` · `scheme_ios` · `url_mobile` · `url_pc` · `chat_extra` · `chat_event` · `biz_form_key` |
| `image` | json | CAROUSEL\_FEED 필수 | `img_url`(필수, 캐러셀 썸네일 이미지 주소) · `img_link`(클릭 시 이동 URL, 미설정 시 이미지 뷰어) |
| `coupon` | json | N | 캐러셀 리스트 최하단 노출. 키 구성은 본문 coupon 과 동일(title·description·url\_*·scheme\_*) |
| `commerce` | json | CAROUSEL\_COMMERCE 필수 / CAROUSEL\_FEED 사용불가 | 본문 commerce 와 동일(title·regular\_price·discount\_price·discount\_rate·discount\_fixed) |

#### carousel.tail (json) — 더보기 버튼 (선택)

| 키 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| `url_mobile` | text(1000) | Y | Mobile 클릭 시 이동 URL |
| `url_pc` | text(1000) | N | PC 클릭 시 이동 URL |
| `scheme_ios` | text(1000) | N | iOS custom scheme |
| `scheme_android` | text(1000) | N | Android custom scheme |

### 채널 친구 대상(send\_target=friend) 차이점

대부분 marketing 과 동일하며 차이만 정리한다.

- `extra.send_target: friend` 입력 필수. `targeting` 불필요.
- 변수 미사용 발송 가능 — `extra`(chat\_bubble\_type·brand\_message\_type·send\_target) 만으로 발송. 변수 존재 시 전문방식 또는 변수분리 방식 선택.
- 발신프로필 등록 후 바로 발송 가능(별도 권한 신청 불필요).


---

# 카카오 비즈메시지 관리 API (KAPI)

> 원본: [https://bizppurio.github.io/kapi/](https://bizppurio.github.io/kapi/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

KAPIv4.18

# 비즈뿌리오 KAPI

## 공통 사항

카카오 비즈메시지 관리 API (KAPI) — 카카오톡 채널의 알림톡 템플릿·발신프로필·이미지·그룹 등 발송 자원을 관리하는 API.

### 연동 규격

| 항목 | 값 |
| --- | --- |
| 프로토콜 | HTTPS |
| 도메인 | `https://kapi.ppurio.com/` |
| 메서드 | POST 전용 |
| 인코딩 | UTF-8 |
| Content-Type | `application/json; charset=utf-8` |
| 인증 | 요청 본문 `bizId` + `apiKey` (Authorization 헤더 없음) |
| 권장 응답 대기 시간 | 15~40초 |

### 인증 흐름

고객사비즈뿌리오 서버([kapi.ppurio.com](http://kapi.ppurio.com))① 모든 API 요청 — 본문에 bizId + apiKey 포함② 결과 응답토큰 발급·Authorization 헤더 없음 — 매 요청마다 인증 정보 반복

KAPI 는 별도 토큰 발급 절차가 없습니다. 매 요청마다 `bizId` 와 `apiKey` 를 본문에 포함합니다. 단, 발신프로필 등록을 위한 카카오 채널 인증 토큰 (Yellow ID 휴대폰 SMS 수신) 은 별도이며 [프로필 API](https://bizppurio.github.io/kapi#프로필) 를 참고하세요.

### 공통 응답 형식

```
{ "code": "200", "message": "...", "data": { ... } }
```

| 필드 | 설명 |
| --- | --- |
| `code` | 결과 코드 (`200` = 성공, 그 외 [코드 정의](https://bizppurio.github.io/response-codes) 참고) |
| `message` | 결과 메시지 |
| `data` | 성공 시 응답 본문 (엔드포인트별 상이) |

### Rate Limit

- 본 API 자체에 별도 명시된 Rate Limit 은 없으나, 비정상 다회 호출 시 일시 차단될 수 있습니다.
- 발송 자체는 비즈뿌리오 본 계정의 [메시지 API Rate Limit](https://bizppurio.github.io/bizapi#공통-사항) 을 적용받습니다.

### 자원 ↔ 발송 연결

KAPI 는 발송을 수행하지 않습니다. 발송은 [`POST /v3/message`](https://bizppurio.github.io/bizapi#post-v3message) (메시지 API) 또는 BIZCLIENT 의 `biz_msg` 테이블 INSERT 로 수행하며, KAPI 로 등록·관리한 자원이 발송 페이로드에 사용됩니다.

| KAPI 에서 등록·관리 | 카카오 발송에서 사용 |
| --- | --- |
| 알림톡 템플릿 (`templateCode`) | `content.at.templatecode` / `content.ai.templatecode` |
| 발신프로필 키 (`senderKey`) | `content.at.senderkey` / `content.ai.senderkey` / `content.ut.senderkey` |
| 브랜드메시지 템플릿 (`templateCode`) | `content.ut.templatecode` (카카오 브랜드) |
| 이미지 키 (`imageId` / `imageUrl`) | 발송 페이로드 내 이미지 URL/key |
| 그룹 태그 키 (`groupTagKey`) | 통계 분류 키 |

### 추가 사항

- **템플릿 상태 변화**
  - `serviceStatus`: `REG` → `REQ` → `REJ` | `STP` | `RDY` → `ACT` → `DMT`/`BLK`
  - `status`: `S` (중지) / `A` (정상) / `R` (대기)
  - `inspectionStatus`: `REG` → `REQ` → `REJ` | `APR` (승인)
- **발신 프로필 키 타입** (`senderKeyType`): `S` 일반 발신프로필 (기본값) / `G` 발신프로필 그룹

## 알림톡 템플릿

알림톡 템플릿 CRUD · 검수 · 사용 중지 · 휴면 해제 · 전환 · 공용 템플릿 (17개 엔드포인트)

post/v3/kakao/template/add

### 템플릿 등록

템플릿을 신규 등록합니다. 사전에 발신프로필 또는 발신프로필 그룹이 등록되어 있어야 합니다.  
등록 직후 상태는 `serviceStatus: REG`(등록) / `status: R`(대기).

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | — | 템플릿 코드 (영문/숫자/`_`/`-`, 빈 값이면 자동 생성) |
| templateName | string | 필수 | 템플릿 이름 |
| templateMessageType | string | 필수 | BA=기본형 / EX=부가정보형 / AD=채널추가형 / MI=복합형  = BA | EX | AD | MI |
| templateEmphasizeType | string | 필수 | NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM\_LIST(아이템리스트형)  = NONE | TEXT | IMAGE | ITEM\_LIST |
| templateContent | string | 필수 | 템플릿 내용 |
| templatePreviewMessage | string(40) | — | 미리보기 메시지 (최대 40자) |
| templateExtra | string | — | 부가정보 — `templateMessageType`이 `EX`/`MI`일 때 필수 |
| templateImageName | string | — | 이미지 파일명 — `templateEmphasizeType`이 `IMAGE`일 때 필수 |
| templateImageUrl | string | — | 이미지 링크 — `templateEmphasizeType`이 `IMAGE`일 때 필수 |
| templateTitle | string | — | 강조 표기 핵심 정보 — `templateEmphasizeType`이 `TEXT`일 때 필수 |
| templateSubtitle | string | — | 강조 표기 보조 문구 — `templateEmphasizeType`이 `TEXT`일 때 필수 |
| templateHeader | string(16) | — | 헤더 (최대 16자) |
| templateItemHighlight | object | — | 아이템 하이라이트 |
| └title | string | — | 타이틀 (최대 30자, 썸네일 이미지 있으면 21자) |
| └description | string | — | 상세 설명 (최대 19자, 썸네일 이미지 있으면 13자) |
| └imageUrl | string(500) | — | 썸네일 이미지 주소 (최대 500자) |
| templateItem | object | — | 아이템 정보 — `templateEmphasizeType`이 `ITEM_LIST`일 때 `list` 필수 |
| └list | array<object>(2~10) | — | 아이템 배열 (2~10개) |
| └title | string(6) | 필수 | 타이틀 |
| └description | string(23) | 필수 | 부가정보 |
| └summary | object | — | 아이템 요약 정보 |
| └title | string(6) | — | 요약 타이틀 |
| └description | string(14) | — | 가격정보 — 변수·화폐 단위·숫자·쉼표·마침표만 |
| templateRepresentLink | object | — | 대표 링크 (각 필드 최대 500자) |
| └linkAnd | string(500) | — | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkMo | string(500) | — | Mobile 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkIos | string(500) | — | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkPc | string(500) | — | PC 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| categoryCode | string | 필수 | 템플릿 카테고리 코드 |
| securityFlag | boolean | — | 보안 템플릿 여부 (OTP 등). true 시 메인 디바이스 외 메시지 텍스트 미노출 |
| buttons | array<object>(~5) | — | 버튼 배열 (최대 5개, 바로연결 사용 시 2개) |
| └name | string | 필수 | 버튼명 — `AC`: "채널추가" 고정 / `TN`: "전화 연결"·"고객센터 연결"·"상담원 연결" 중 하나 |
| └linkType | string | 필수 | 버튼 링크타입 (DS:배송조회, WL:웹링크, AL:앱링크, BK:봇키워, MD: 메시지전달, AC: 채널추가, BC: 상담톡전환, BT: 봇전환, P1: 이미지 보안전송 플러그인, P2 : 개인정보이용 플러그인, P3: 원클릭 결제 플러그인, TN: 전화하기, MP: 지도보기)  = DS | WL | AL | BK | MD | AC | BC | BT | P1 | P2 | P3 | TN | MP |
| └linkAnd | string | — | Android 앱 링크 (AL 사용 시 필수, AL은 tell:// 신규 등록 불가) |
| └linkIos | string | — | iOS 앱 링크 (AL 사용 시 필수) |
| └linkMo | string | — | 모바일 웹 링크 (WL 사용 시 필수) |
| └linkPc | string | — | PC 웹 링크 (WL 사용 시 선택) |
| └pluginId | string | — | 플러그인 ID (P1/P2/P3 사용 시 필수) |
| └telNumber | string | — | 전화번호 (TN 사용 시 필수) |
| quickReplies | array<object>(~10) | — | 바로연결 배열 (최대 10개, 상담톡 채널만) |
| └name | string | 필수 | 바로연결명 |
| └linkType | string | 필수 | 바로연결 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, MD: 메시지전달, BC : 상담톡전환, BT: 봇전환)  = WL | AL | BK | MD | BC | BT |
| └linkAnd | string | — | Android 앱 링크 주소 (AL 사용시 필수) |
| └linkIos | string | — | IOS 앱 링크 주소 (AL 사용시 필수) |
| └linkMo | string | — | 모바일 웹 링크 주소 (WL 사용시 필수) |
| └linkPc | string | — | PC 웹 링크 주소 (WL 사용시 선택) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/add" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "senderKeyType": "S",
  "templateName": "봇키워드 버튼 템플릿",
  "templateContent": "봇키워드 테스트",
  "templateMessageType": "MI",
  "templateExtra": "부가정보",
  "templateEmphasizeType": "NONE",
  "categoryCode": "001001",
  "buttons": [
    {
      "name": "주문 확인",
      "linkType": "WL",
      "linkMo": "https://example.com/order"
    }
  ]
}'
```

응답

200등록 성공 — `data`는 등록된 템플릿 상세 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 템플릿 상세 + 상태·검수·차단·휴면·댓글 |
| └senderKey | string | — | 발신 프로필 키 |
| └senderKeyType | string | — | S=일반 / G=그룹  = S | G |
| └templateCode | string | — | 템플릿 코드 (영문, 숫자, 언더바(\_), 하이픈(-)만 입력 가능, 최대 30자, 빈 값일 경우 자동 생성) |
| └templateName | string | — | 템플릿 이름 |
| └templateMessageType | string | — | BA=기본형 / EX=부가정보형 / AD=채널추가형 / MI=복합형  = BA | EX | AD | MI |
| └templateEmphasizeType | string | — | NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM\_LIST(아이템리스트형)  = NONE | TEXT | IMAGE | ITEM\_LIST |
| └templateContent | string | — | 템플릿 내용 |
| └templatePreviewMessage | string | — | 미리보기 메시지 |
| └templateExtra | string | null | — | 부가정보 (EX/MI 타입일 때) |
| └templateImageName | string | null | — | 이미지 파일명 (IMAGE 타입일 때) |
| └templateImageUrl | string | null | — | 이미지 링크 (IMAGE 타입일 때) |
| └templateTitle | string | null | — | 강조 표기 핵심 정보 (TEXT 타입일 때) |
| └templateSubtitle | string | null | — | 강조 표기 보조 문구 (TEXT 타입일 때) |
| └templateHeader | string | null | — | 헤더 (ITEM\_LIST 타입일 때) |
| └templateItemHighlight | object | null | — | 아이템 하이라이트 (ITEM\_LIST 타입일 때) |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └imageUrl | string | null | — | 썸네일 이미지 주소 |
| └templateItem | object | null | — | 아이템 정보 (ITEM\_LIST 타입일 때) |
| └list | array<object> | — | 아이템 목록 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └summary | object | null | — | 아이템 요약 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └templateRepresentLink | object | null | — | 대표 링크 |
| └linkPc | string | null | — | PC 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkMo | string | null | — | Mobile 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkAnd | string | null | — | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkIos | string | null | — | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └categoryCode | string | — | 템플릿 카테고리 코드 |
| └securityFlag | boolean | — | 보안 템플릿 여부 |
| └inspectionStatus | string | — | REG / REQ / REJ / APR(승인)  = REG | REQ | REJ | APR |
| └createdAt | string | — | 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └status | string | — | S(중지) / A(정상) / R(대기/발송전)  = S | A | R |
| └block | boolean | — | 템플릿 차단 여부 |
| └dormant | boolean | — | 휴면 여부 |
| └buttons | array<object> | — | 버튼 목록 (최대 5개) |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └quickReplies | array<object> | — | 바로연결 목록 (최대 10개) — 버튼과 동일 구조 |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └comments | array<object> | — | 댓글 배열 |
| └content | string | — | 댓글 내용 |
| └createdAt | string | — | 등록일 |
| └status | string | — | REQ(등록) / INQ(문의) / APR(승인) / REJ(반려) / REP(답변)  = REQ | INQ | APR | REJ | REP |
| └userName | string | — | 댓글 작성자 |
| └attachment | array<object> | — | 첨부파일 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "senderKey": "662be6bf96868232ec4fbXXXXXXXXXXXXX",
    "senderKeyType": "S",
    "templateCode": "BA_NONE_O",
    "templateName": "기본형_선택안함_O",
    "templateMessageType": "BA",
    "templateEmphasizeType": "NONE",
    "templateContent": "테스트(test) 기본형_선택안함_O",
    "templatePreviewMessage": "기본형_선택안함_O 미리보기",
    "templateExtra": "*차량 이용 시, 주차가능 여부를 반드시 문의하시기 바랍니다.",
    "templateImageName": "이미지",
    "templateImageUrl": "https://mud-kage.kakao.com/dn/sample/img_l.jpg",
    "templateTitle": "회원 가입 안내",
    "templateSubtitle": "Sample",
    "templateHeader": "헤더",
    "templateItemHighlight": {
      "title": "타이틀",
      "description": "설명",
      "imageUrl": "https://mud-kage.kakao.com/dn/sample/img_l.jpg"
    },
    "templateItem": {
      "list": [
        {
          "title": "타이틀",
          "description": "설명"
        }
      ],
      "summary": {
        "title": "타이틀",
        "description": "100원"
      }
    },
    "templateRepresentLink": {
      "linkPc": "https://www.bizppurio.com/",
      "linkMo": "https://www.bizppurio.com/",
      "linkAnd": "https://www.bizppurio.com/",
      "linkIos": "https://www.bizppurio.com/"
    },
    "categoryCode": "999999",
    "securityFlag": true,
    "inspectionStatus": "APR",
    "createdAt": "2025-06-10 18:28:03",
    "modifiedAt": "2025-06-11 11:01:55",
    "status": "A",
    "block": true,
    "dormant": true,
    "buttons": [
      {
        "name": "버튼1",
        "linkType": "WL",
        "ordering": 1,
        "linkPc": "https://www.bizppurio.com/",
        "linkMo": "https://www.bizppurio.com/",
        "linkAnd": "string",
        "linkIos": "string",
        "pluginId": "string",
        "bizFormId": "string",
        "telNumber": "string"
      }
    ],
    "quickReplies": [
      {
        "name": "버튼1",
        "linkType": "WL",
        "ordering": 1,
        "linkPc": "https://www.bizppurio.com/",
        "linkMo": "https://www.bizppurio.com/",
        "linkAnd": "string",
        "linkIos": "string",
        "pluginId": "string",
        "bizFormId": "string",
        "telNumber": "string"
      }
    ],
    "comments": [
      {
        "content": "string",
        "createdAt": "string",
        "status": "REQ",
        "userName": "string",
        "attachment": [
          {}
        ]
      }
    ]
  }
}
```

post/v3/kakao/template/codeCheck

### 템플릿 코드 유효성 검증

등록하려는 템플릿 코드의 유효성을 검증합니다. 영문/숫자/`_`/`-`만 허용, 최대 30자.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/codeCheck" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200검증 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/list

### 템플릿 목록 조회

발신프로필에 등록된 템플릿 목록을 페이지네이션으로 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | S=일반(default) / G=그룹  = S | G |
| page | integer | — | 페이지 번호 |
| count | integer | — | 페이지당 개수 |
| keyword | string(2~50) | — | 검색 키워드 |
| startDate | string | — | 생성일 시작 (yyyyMMddHHmmss) |
| endDate | string | — | 생성일 종료 |
| templateStatus | string | — | 템플릿 상태 필터  = REG | REQ | REJ | STP | RDY | ACT | DMT | BLK |
| categoryCodeList | array<string> | — | 카테고리 코드 배열 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/list" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "page": 1,
  "count": 30,
  "categoryCodeList": [
    "002001"
  ]
}'
```

응답

200목록 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| totalCount | integer | 필수 | 전체 건수 |
| totalPage | integer | 필수 | 전체 페이지 수 |
| currentPage | integer | 필수 | 현재 페이지 |
| data | object | 필수 | 성공 시 반환 데이터 |
| └list | array<object> | — | 성공 시 템플릿 목록 |
| └senderKey | string | — | 발신프로필 키 |
| └senderKeyType | string | — | 발신프로필 키 타입  = S | G |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └categoryCode | string | — | 템플릿 카테고리 코드 |
| └createdAt | string | — | 등록일 |
| └modifiedAt | string | — | 수정일 |
| └serviceStatus | string | — | 템플릿 상태 (REG: 등록, REQ: 검수요청, REJ: 반려, STP: 차단, RDY: 발송전, ACT: 정상, DMT: 휴면, BLK: 차단)  = REG | REQ | REJ | STP | RDY | ACT | DMT | BLK |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "totalCount": 0,
  "totalPage": 0,
  "currentPage": 0,
  "data": {
    "list": [
      {
        "senderKey": "string",
        "senderKeyType": "S",
        "templateCode": "string",
        "templateName": "string",
        "categoryCode": "string",
        "createdAt": "string",
        "modifiedAt": "string",
        "serviceStatus": "REG"
      }
    ]
  }
}
```

post/v3/kakao/template/detail

### 템플릿 상세 조회

등록된 템플릿의 모든 필드 + 상태 · 검수 · 차단 · 휴면 · 댓글 정보를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/detail" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "662be6bf96868232ec4fbXXXXXXXXXXXXX",
  "templateCode": "BA_NONE_O"
}'
```

응답

200템플릿 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 템플릿 상세 + 상태·검수·차단·휴면·댓글 |
| └senderKey | string | — | 발신 프로필 키 |
| └senderKeyType | string | — | S=일반 / G=그룹  = S | G |
| └templateCode | string | — | 템플릿 코드 (영문, 숫자, 언더바(\_), 하이픈(-)만 입력 가능, 최대 30자, 빈 값일 경우 자동 생성) |
| └templateName | string | — | 템플릿 이름 |
| └templateMessageType | string | — | BA=기본형 / EX=부가정보형 / AD=채널추가형 / MI=복합형  = BA | EX | AD | MI |
| └templateEmphasizeType | string | — | NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM\_LIST(아이템리스트형)  = NONE | TEXT | IMAGE | ITEM\_LIST |
| └templateContent | string | — | 템플릿 내용 |
| └templatePreviewMessage | string | — | 미리보기 메시지 |
| └templateExtra | string | null | — | 부가정보 (EX/MI 타입일 때) |
| └templateImageName | string | null | — | 이미지 파일명 (IMAGE 타입일 때) |
| └templateImageUrl | string | null | — | 이미지 링크 (IMAGE 타입일 때) |
| └templateTitle | string | null | — | 강조 표기 핵심 정보 (TEXT 타입일 때) |
| └templateSubtitle | string | null | — | 강조 표기 보조 문구 (TEXT 타입일 때) |
| └templateHeader | string | null | — | 헤더 (ITEM\_LIST 타입일 때) |
| └templateItemHighlight | object | null | — | 아이템 하이라이트 (ITEM\_LIST 타입일 때) |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └imageUrl | string | null | — | 썸네일 이미지 주소 |
| └templateItem | object | null | — | 아이템 정보 (ITEM\_LIST 타입일 때) |
| └list | array<object> | — | 아이템 목록 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └summary | object | null | — | 아이템 요약 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └templateRepresentLink | object | null | — | 대표 링크 |
| └linkPc | string | null | — | PC 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkMo | string | null | — | Mobile 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkAnd | string | null | — | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkIos | string | null | — | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └categoryCode | string | — | 템플릿 카테고리 코드 |
| └securityFlag | boolean | — | 보안 템플릿 여부 |
| └inspectionStatus | string | — | REG / REQ / REJ / APR(승인)  = REG | REQ | REJ | APR |
| └createdAt | string | — | 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └status | string | — | S(중지) / A(정상) / R(대기/발송전)  = S | A | R |
| └block | boolean | — | 템플릿 차단 여부 |
| └dormant | boolean | — | 휴면 여부 |
| └buttons | array<object> | — | 버튼 목록 (최대 5개) |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └quickReplies | array<object> | — | 바로연결 목록 (최대 10개) — 버튼과 동일 구조 |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └comments | array<object> | — | 댓글 배열 |
| └content | string | — | 댓글 내용 |
| └createdAt | string | — | 등록일 |
| └status | string | — | REQ(등록) / INQ(문의) / APR(승인) / REJ(반려) / REP(답변)  = REQ | INQ | APR | REJ | REP |
| └userName | string | — | 댓글 작성자 |
| └attachment | array<object> | — | 첨부파일 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "senderKey": "662be6bf96868232ec4fbXXXXXXXXXXXXX",
    "senderKeyType": "S",
    "templateCode": "BA_NONE_O",
    "templateName": "기본형_선택안함_O",
    "templateMessageType": "BA",
    "templateEmphasizeType": "NONE",
    "templateContent": "테스트(test) 기본형_선택안함_O",
    "templatePreviewMessage": "기본형_선택안함_O 미리보기",
    "templateExtra": null,
    "templateImageName": null,
    "templateImageUrl": null,
    "templateTitle": null,
    "templateSubtitle": null,
    "templateHeader": null,
    "templateItemHighlight": null,
    "templateItem": null,
    "templateRepresentLink": {
      "linkAnd": "https://www.bizppurio.com/",
      "linkIos": "https://www.bizppurio.com/",
      "linkMo": "https://www.bizppurio.com/",
      "linkPc": "https://www.bizppurio.com/"
    },
    "categoryCode": "999999",
    "securityFlag": false,
    "inspectionStatus": "APR",
    "createdAt": "2025-06-10 18:28:03",
    "modifiedAt": "2025-06-11 11:01:55",
    "status": "A",
    "block": false,
    "dormant": false,
    "buttons": [
      {
        "name": "버튼1",
        "linkType": "WL",
        "ordering": 1,
        "linkAnd": null,
        "linkIos": null,
        "linkMo": "https://www.bizppurio.com/",
        "linkPc": "https://www.bizppurio.com/",
        "pluginId": null,
        "bizFormId": null,
        "telNumber": null
      },
      {
        "name": "버튼2",
        "linkType": "WL",
        "ordering": 2,
        "linkAnd": null,
        "linkIos": null,
        "linkMo": "https://www.bizppurio.com/",
        "linkPc": "https://www.bizppurio.com/",
        "pluginId": null,
        "bizFormId": null,
        "telNumber": null
      }
    ],
    "quickReplies": [],
    "comments": [
      {
        "content": "안녕하세요. 카카오톡 알림톡 검수 담당자입니다.\n\n신청하신 메시지 테스트 템플릿으로 확인하여 승인되었습니다.\n\n감사합니다.",
        "createdAt": "2025-06-11 10:48:52",
        "status": "APR",
        "userName": "검수자",
        "attachment": []
      }
    ]
  }
}
```

post/v3/kakao/template/update

### 템플릿 수정

템플릿 내용을 수정합니다.

> ⚠️ 템플릿 상태가 \*\*대기(R)\*\*이고 검수상태가 **등록(REG)** 또는 \*\*반려(REJ)\*\*인 경우에만 수정 가능합니다.

`templateCode`는 기존 코드를 가리키며, 코드 자체를 변경하려면 `newTemplateCode`를 추가로 전달합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | — | 템플릿 코드 (영문/숫자/`_`/`-`, 빈 값이면 자동 생성) |
| templateName | string | 필수 | 템플릿 이름 |
| templateMessageType | string | 필수 | BA=기본형 / EX=부가정보형 / AD=채널추가형 / MI=복합형  = BA | EX | AD | MI |
| templateEmphasizeType | string | 필수 | NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM\_LIST(아이템리스트형)  = NONE | TEXT | IMAGE | ITEM\_LIST |
| templateContent | string | 필수 | 템플릿 내용 |
| templatePreviewMessage | string(40) | — | 미리보기 메시지 (최대 40자) |
| templateExtra | string | — | 부가정보 — `templateMessageType`이 `EX`/`MI`일 때 필수 |
| templateImageName | string | — | 이미지 파일명 — `templateEmphasizeType`이 `IMAGE`일 때 필수 |
| templateImageUrl | string | — | 이미지 링크 — `templateEmphasizeType`이 `IMAGE`일 때 필수 |
| templateTitle | string | — | 강조 표기 핵심 정보 — `templateEmphasizeType`이 `TEXT`일 때 필수 |
| templateSubtitle | string | — | 강조 표기 보조 문구 — `templateEmphasizeType`이 `TEXT`일 때 필수 |
| templateHeader | string(16) | — | 헤더 (최대 16자) |
| templateItemHighlight | object | — | 아이템 하이라이트 |
| └title | string | — | 타이틀 (최대 30자, 썸네일 이미지 있으면 21자) |
| └description | string | — | 상세 설명 (최대 19자, 썸네일 이미지 있으면 13자) |
| └imageUrl | string(500) | — | 썸네일 이미지 주소 (최대 500자) |
| templateItem | object | — | 아이템 정보 — `templateEmphasizeType`이 `ITEM_LIST`일 때 `list` 필수 |
| └list | array<object>(2~10) | — | 아이템 배열 (2~10개) |
| └title | string(6) | 필수 | 타이틀 |
| └description | string(23) | 필수 | 부가정보 |
| └summary | object | — | 아이템 요약 정보 |
| └title | string(6) | — | 요약 타이틀 |
| └description | string(14) | — | 가격정보 — 변수·화폐 단위·숫자·쉼표·마침표만 |
| templateRepresentLink | object | — | 대표 링크 (각 필드 최대 500자) |
| └linkAnd | string(500) | — | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkMo | string(500) | — | Mobile 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkIos | string(500) | — | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkPc | string(500) | — | PC 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| categoryCode | string | 필수 | 템플릿 카테고리 코드 |
| securityFlag | boolean | — | 보안 템플릿 여부 (OTP 등). true 시 메인 디바이스 외 메시지 텍스트 미노출 |
| buttons | array<object>(~5) | — | 버튼 배열 (최대 5개, 바로연결 사용 시 2개) |
| └name | string | 필수 | 버튼명 — `AC`: "채널추가" 고정 / `TN`: "전화 연결"·"고객센터 연결"·"상담원 연결" 중 하나 |
| └linkType | string | 필수 | 버튼 링크타입 (DS:배송조회, WL:웹링크, AL:앱링크, BK:봇키워, MD: 메시지전달, AC: 채널추가, BC: 상담톡전환, BT: 봇전환, P1: 이미지 보안전송 플러그인, P2 : 개인정보이용 플러그인, P3: 원클릭 결제 플러그인, TN: 전화하기, MP: 지도보기)  = DS | WL | AL | BK | MD | AC | BC | BT | P1 | P2 | P3 | TN | MP |
| └linkAnd | string | — | Android 앱 링크 (AL 사용 시 필수, AL은 tell:// 신규 등록 불가) |
| └linkIos | string | — | iOS 앱 링크 (AL 사용 시 필수) |
| └linkMo | string | — | 모바일 웹 링크 (WL 사용 시 필수) |
| └linkPc | string | — | PC 웹 링크 (WL 사용 시 선택) |
| └pluginId | string | — | 플러그인 ID (P1/P2/P3 사용 시 필수) |
| └telNumber | string | — | 전화번호 (TN 사용 시 필수) |
| quickReplies | array<object>(~10) | — | 바로연결 배열 (최대 10개, 상담톡 채널만) |
| └name | string | 필수 | 바로연결명 |
| └linkType | string | 필수 | 바로연결 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, MD: 메시지전달, BC : 상담톡전환, BT: 봇전환)  = WL | AL | BK | MD | BC | BT |
| └linkAnd | string | — | Android 앱 링크 주소 (AL 사용시 필수) |
| └linkIos | string | — | IOS 앱 링크 주소 (AL 사용시 필수) |
| └linkMo | string | — | 모바일 웹 링크 주소 (WL 사용시 필수) |
| └linkPc | string | — | PC 웹 링크 주소 (WL 사용시 선택) |
| newTemplateCode | string(30) | — | 수정하려는 템플릿 코드 (영문/숫자/`_`/`-`, 최대 30자) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/update" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateName": "주문 완료 안내 (아이템리스트)",
  "templateMessageType": "MI",
  "templateEmphasizeType": "ITEM_LIST",
  "templateContent": "#{고객명}님, 주문이 완료되었습니다.\n주문 내역을 확인해 주세요.",
  "templateExtra": "본 메시지는 주문 고객에게 발송됩니다.",
  "templateHeader": "주문 완료",
  "categoryCode": "001001",
  "templateItemHighlight": {
    "title": "주문번호",
    "description": "#{주문번호}"
  },
  "templateItem": {
    "list": [
      {
        "title": "상품명",
        "description": "#{상품명}"
      },
      {
        "title": "결제금액",
        "description": "#{결제금액}원"
      }
    ],
    "summary": {
      "title": "합계",
      "description": "#{합계금액}원"
    }
  },
  "buttons": [
    {
      "name": "주문 상세보기",
      "linkType": "WL",
      "linkMo": "https://m.example.com/orders",
      "linkPc": "https://example.com/orders"
    }
  ],
  "newTemplateCode": "string"
}'
```

응답

200수정 성공 — `data`는 수정된 템플릿 상세 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 템플릿 상세 + 상태·검수·차단·휴면·댓글 |
| └senderKey | string | — | 발신 프로필 키 |
| └senderKeyType | string | — | S=일반 / G=그룹  = S | G |
| └templateCode | string | — | 템플릿 코드 (영문, 숫자, 언더바(\_), 하이픈(-)만 입력 가능, 최대 30자, 빈 값일 경우 자동 생성) |
| └templateName | string | — | 템플릿 이름 |
| └templateMessageType | string | — | BA=기본형 / EX=부가정보형 / AD=채널추가형 / MI=복합형  = BA | EX | AD | MI |
| └templateEmphasizeType | string | — | NONE / TEXT(강조표기형) / IMAGE(이미지형) / ITEM\_LIST(아이템리스트형)  = NONE | TEXT | IMAGE | ITEM\_LIST |
| └templateContent | string | — | 템플릿 내용 |
| └templatePreviewMessage | string | — | 미리보기 메시지 |
| └templateExtra | string | null | — | 부가정보 (EX/MI 타입일 때) |
| └templateImageName | string | null | — | 이미지 파일명 (IMAGE 타입일 때) |
| └templateImageUrl | string | null | — | 이미지 링크 (IMAGE 타입일 때) |
| └templateTitle | string | null | — | 강조 표기 핵심 정보 (TEXT 타입일 때) |
| └templateSubtitle | string | null | — | 강조 표기 보조 문구 (TEXT 타입일 때) |
| └templateHeader | string | null | — | 헤더 (ITEM\_LIST 타입일 때) |
| └templateItemHighlight | object | null | — | 아이템 하이라이트 (ITEM\_LIST 타입일 때) |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └imageUrl | string | null | — | 썸네일 이미지 주소 |
| └templateItem | object | null | — | 아이템 정보 (ITEM\_LIST 타입일 때) |
| └list | array<object> | — | 아이템 목록 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └summary | object | null | — | 아이템 요약 |
| └title | string | — | 타이틀 (최대 30자까지 입력 가능, 썸네일 이미지가 있을 경우 21자까지 입력) |
| └description | string | — | 상세 설명 (최대 19자까지 입력 가능, 썸네일 이미지가 있을 경우 13자까지 입력) |
| └templateRepresentLink | object | null | — | 대표 링크 |
| └linkPc | string | null | — | PC 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkMo | string | null | — | Mobile 환경에서 버튼 클릭 시 이동할 URL (최대 500자) |
| └linkAnd | string | null | — | Mobile Android 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └linkIos | string | null | — | Mobile iOS 환경에서 버튼 클릭 시 실행할 Application Custom Scheme (최대 500자) |
| └categoryCode | string | — | 템플릿 카테고리 코드 |
| └securityFlag | boolean | — | 보안 템플릿 여부 |
| └inspectionStatus | string | — | REG / REQ / REJ / APR(승인)  = REG | REQ | REJ | APR |
| └createdAt | string | — | 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └status | string | — | S(중지) / A(정상) / R(대기/발송전)  = S | A | R |
| └block | boolean | — | 템플릿 차단 여부 |
| └dormant | boolean | — | 휴면 여부 |
| └buttons | array<object> | — | 버튼 목록 (최대 5개) |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └quickReplies | array<object> | — | 바로연결 목록 (최대 10개) — 버튼과 동일 구조 |
| └name | string | — | 버튼 이름 |
| └linkType | string | — | WL=웹링크 / AL=앱링크 / DS=배송조회 / BK=봇키워드 / MD=메시지전달 / BT=봇전환 / BC=상담톡전환 / AC=채널추가  = WL | AL | DS | BK | MD | BT | BC | AC |
| └ordering | integer | — | 버튼 순서 |
| └linkPc | string | null | — | PC 웹링크 (WL) |
| └linkMo | string | null | — | 모바일 웹링크 (WL) |
| └linkAnd | string | null | — | 안드로이드 앱링크 (AL) |
| └linkIos | string | null | — | iOS 앱링크 (AL) |
| └pluginId | string | null | — | 플러그인 ID |
| └bizFormId | string | null | — | 비즈니스폼 ID |
| └telNumber | string | null | — | 전화번호 |
| └comments | array<object> | — | 댓글 배열 |
| └content | string | — | 댓글 내용 |
| └createdAt | string | — | 등록일 |
| └status | string | — | REQ(등록) / INQ(문의) / APR(승인) / REJ(반려) / REP(답변)  = REQ | INQ | APR | REJ | REP |
| └userName | string | — | 댓글 작성자 |
| └attachment | array<object> | — | 첨부파일 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "senderKey": "662be6bf96868232ec4fbXXXXXXXXXXXXX",
    "senderKeyType": "S",
    "templateCode": "BA_NONE_O",
    "templateName": "기본형_선택안함_O",
    "templateMessageType": "BA",
    "templateEmphasizeType": "NONE",
    "templateContent": "테스트(test) 기본형_선택안함_O",
    "templatePreviewMessage": "기본형_선택안함_O 미리보기",
    "templateExtra": "*차량 이용 시, 주차가능 여부를 반드시 문의하시기 바랍니다.",
    "templateImageName": "이미지",
    "templateImageUrl": "https://mud-kage.kakao.com/dn/sample/img_l.jpg",
    "templateTitle": "회원 가입 안내",
    "templateSubtitle": "Sample",
    "templateHeader": "헤더",
    "templateItemHighlight": {
      "title": "타이틀",
      "description": "설명",
      "imageUrl": "https://mud-kage.kakao.com/dn/sample/img_l.jpg"
    },
    "templateItem": {
      "list": [
        {
          "title": "타이틀",
          "description": "설명"
        }
      ],
      "summary": {
        "title": "타이틀",
        "description": "100원"
      }
    },
    "templateRepresentLink": {
      "linkPc": "https://www.bizppurio.com/",
      "linkMo": "https://www.bizppurio.com/",
      "linkAnd": "https://www.bizppurio.com/",
      "linkIos": "https://www.bizppurio.com/"
    },
    "categoryCode": "999999",
    "securityFlag": true,
    "inspectionStatus": "APR",
    "createdAt": "2025-06-10 18:28:03",
    "modifiedAt": "2025-06-11 11:01:55",
    "status": "A",
    "block": true,
    "dormant": true,
    "buttons": [
      {
        "name": "버튼1",
        "linkType": "WL",
        "ordering": 1,
        "linkPc": "https://www.bizppurio.com/",
        "linkMo": "https://www.bizppurio.com/",
        "linkAnd": "string",
        "linkIos": "string",
        "pluginId": "string",
        "bizFormId": "string",
        "telNumber": "string"
      }
    ],
    "quickReplies": [
      {
        "name": "버튼1",
        "linkType": "WL",
        "ordering": 1,
        "linkPc": "https://www.bizppurio.com/",
        "linkMo": "https://www.bizppurio.com/",
        "linkAnd": "string",
        "linkIos": "string",
        "pluginId": "string",
        "bizFormId": "string",
        "telNumber": "string"
      }
    ],
    "comments": [
      {
        "content": "string",
        "createdAt": "string",
        "status": "REQ",
        "userName": "string",
        "attachment": [
          {}
        ]
      }
    ]
  }
}
```

post/v3/kakao/template/delete

### 템플릿 삭제

템플릿을 삭제합니다.

> ⚠️ 템플릿 상태가 \*\*대기(R)\*\*이고 검수상태가 **등록(REG)** 또는 \*\*반려(REJ)\*\*인 경우에만 삭제 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/delete" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/category/all

### 템플릿 카테고리 전체 조회

템플릿 등록 시 사용할 카테고리 목록 전체를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/category/all" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string"
}'
```

응답

200카테고리 전체 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | 필수 | 성공 시 카테고리 목록 |
| └code | string | — | 카테고리 코드 |
| └name | string | — | 카테고리 이름 |
| └groupName | string | — | 카테고리 그룹 이름 |
| └Inclusion | string | — | 카테고리 적용 대상 템플릿 설명 |
| └exclusion | string | — | 카테고리 제외 대상 템플릿 설명 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "code": "string",
      "name": "string",
      "groupName": "string",
      "Inclusion": "string",
      "exclusion": "string"
    }
  ]
}
```

post/v3/kakao/template/category

### 템플릿 카테고리 단건 조회

카테고리 코드에 해당하는 특정 템플릿 카테고리를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| categoryCode | string | 필수 | 카테고리 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/category" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "categoryCode": "string"
}'
```

응답

200카테고리 단건

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 카테고리 정보 |
| └code | string | — | 카테고리 코드 |
| └name | string | — | 카테고리 이름 |
| └groupName | string | — | 카테고리 그룹 이름 |
| └inclusion | string | — | 카테고리 적용 대상 템플릿 설명 |
| └exclusion | string | — | 카테고리 제외 대상 템플릿 설명 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "code": "string",
    "name": "string",
    "groupName": "string",
    "inclusion": "string",
    "exclusion": "string"
  }
}
```

post/v3/kakao/template/request

### 템플릿 검수 요청

템플릿 검수를 요청합니다.

> ⚠️ 템플릿 상태가 \*\*대기(R)\*\*이고 검수상태가 \*\*등록(REG)\*\*인 경우에만 요청 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |
| comment | string(500) | — | 의견 또는 문의사항 (최대 500자) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/request" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001",
  "comment": "string"
}'
```

응답

200검수 요청 접수

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/request\_with\_file

### 템플릿 검수 요청 (파일 첨부)

검수 요청과 함께 첨부파일을 함께 전송합니다.

| 파일 사양 | 값 |
| --- | --- |
| 지원 포맷 | `png`, `jpg`, `jpeg`, `gif`, `pdf`, `hwp`, `doc`, `docx` |
| 개당 크기 제한 | **50 MB** |
| 첨부 개수 | 다수 가능 |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string | 필수 | 템플릿 코드 |
| comment | string(500) | 필수 | 의견 또는 문의사항 (최대 500자) |
| attachment | array<string <binary>> | — | 업로드할 파일 (다수 가능) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/request_with_file" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001",
  "comment": "검수 요청합니다. 첨부파일 확인 부탁드립니다."
}'
```

응답

200검수 요청 (파일 포함) 접수

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/cancel\_request

### 템플릿 검수 요청 취소

> ⚠️ 템플릿 상태가 \*\*대기(R)\*\*이고 검수상태가 \*\*검수 요청(REQ)\*\*인 경우에만 요청 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/cancel_request" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200검수 요청 취소

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/stop

### 템플릿 사용 중지

> ⚠️ 템플릿 상태가 \*\*대기(R) 또는 정상(A)\*\*이고 검수상태가 \*\*승인(APR)\*\*인 경우에만 요청 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/stop" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200사용 중지 처리

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/reuse

### 템플릿 사용 중지 해제

> ⚠️ 템플릿 상태가 \*\*중지(S)\*\*이고 검수상태가 \*\*승인(APR)\*\*인 경우에만 요청 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/reuse" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200사용 중지 해제

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/cancel\_approval

### 템플릿 승인 취소

승인된 템플릿이 **대기(R)** 상태일 때 승인 취소가 가능합니다.  
취소 시 상태가 \*\*등록(REG)\*\*으로 변경되며 재 검수 요청 가능.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/cancel_approval" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200승인 취소

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/release

### 템플릿 휴면 해제

장기간 미사용으로 휴면된 템플릿을 해제합니다. **해제 후 30일간 사용하지 않으면 재 휴면 처리**됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/release" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200휴면 해제

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/convertAddCh

### 템플릿 전환 (채널 추가 버튼 부여)

기등록된 템플릿(`BA` / `EX`)을 **"채널 추가 버튼"** 및 \*\*"채널 추가 안내 문구"\*\*가 포함된 템플릿으로 전환합니다.

| 변환 | BA → AD / EX → MI |
| --- | --- |
| 채널 추가 버튼 | 무조건 맨 처음으로 추가 |

### 전환 실패 조건

- 템플릿에 버튼이 이미 5개인 경우
- 채널 추가 안내 문구(36자) 추가로 본문 964자 초과
- 기존 템플릿에 바로연결이 있으면서 버튼이 2개인 경우
- 휴면 등 비정상 상태의 템플릿

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/convertAddCh" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200전환 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/template/public/list

### 공용 템플릿 목록 조회

공용 템플릿 목록을 조회합니다. 세부 내용은 [템플릿 상세 조회](https://bizppurio.github.io/kapi#post-v3kakaotemplatedetail)에서 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| since | string | — | 기준 시간 (yyyyMMddHHmmss). 기본: 요청 시간 1일 전 |
| page | integer | — | 요청 페이지 번호 (기본값: 1) |
| count | integer | — | 페이지 별 템플릿 개수 (기본값: 100) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/template/public/list" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "since": "string",
  "page": 1,
  "count": 100
}'
```

응답

200공용 템플릿 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| hasNext | boolean | — | 다음 페이지 존재 여부 |
| data | array<object> | 필수 | 성공 시 반환 데이터 |
| └templateCode | string(30) | — | 템플릿 코드 |
| └templateName | string(200) | — | 템플릿 이름 |
| └status | string | — | 공용 템플릿 상태 (S: 중지, A: 정상, R: 대기/발송전)  = S | A | R |
| └categoryCode | string | — | 템플릿 카테고리코드 |
| └releaseDate | string | — | 제공일자 (8자) |
| └previewImageUrl | string | — | 발송 샘플 이미지 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "hasNext": true,
  "data": [
    {
      "templateCode": "string",
      "templateName": "string",
      "status": "S",
      "categoryCode": "string",
      "releaseDate": "string",
      "previewImageUrl": "string"
    }
  ]
}
```

## 파일

알림톡 템플릿용·발송용·하이라이트 이미지 업로드 (3개 엔드포인트, `multipart/form-data`)

post/v3/kakao/image/alimtalk/template

### 알림톡 템플릿 등록용 이미지 업로드

이미지 알림톡 또는 아이템 리스트 알림톡 **템플릿 등록 시** 사용될 이미지를 업로드합니다.

| 항목 | 값 |
| --- | --- |
| 파일 포맷 | `jpg`, `png` |
| 최대 크기 | 500 KB |
| 가로 사이즈 | 500px 이상 |
| 가로:세로 비율 | **2:1** |

응답의 `image` URL을 [템플릿 등록](https://bizppurio.github.io/kapi#post-v3kakaotemplateadd)의 `templateImageUrl`에 사용.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/image/alimtalk/template" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

post/v3/kakao/image/alimtalk

### 알림톡 발송 이미지 업로드

이미지 알림톡 또는 아이템 리스트 알림톡 **발송 시** 사용될 이미지를 업로드합니다.

| 항목 | 값 |
| --- | --- |
| 파일 포맷 | `jpg`, `png` |
| 최대 크기 | 500 KB |
| 가로 사이즈 | 500px 이상 |
| 가로:세로 비율 | **2:1 이상 3:4 이하** |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/image/alimtalk" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

post/v3/kakao/image/alimtalk/itemHighlight

### 알림톡 아이템 하이라이트 이미지 업로드

아이템 리스트 알림톡 발송 시 사용될 **아이템 하이라이트 썸네일 이미지**를 업로드합니다.

| 항목 | 값 |
| --- | --- |
| 파일 포맷 | `jpg`, `png` |
| 최대 크기 | 500 KB |
| 가로 사이즈 | 108px 이상 |
| 가로:세로 비율 | **1:1 (정사각형)** |

응답의 `image` URL을 [템플릿 등록](https://bizppurio.github.io/kapi#post-v3kakaotemplateadd)의 `templateItemHighlight.imageUrl`에 사용.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/image/alimtalk/itemHighlight" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

## 프로필

발신프로필 등록·조회·휴면 해제, 무료수신거부, 광고성 수신동의 증적 (11개 엔드포인트)

post/v3/kakao/profile/token

### 발신프로필 인증토큰 요청

발신프로필 등록을 위한 카카오톡 채널 인증 토큰을 요청합니다.  
토큰은 Yellow ID(카카오톡 채널 관리자)의 휴대폰번호로 수신됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| phoneNumber | string | 필수 | 토큰을 수신할 휴대폰번호 (Yellow ID 핸드폰번호와 일치) |
| yellowId | string | 필수 | 카카오톡 채널 (@ID) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/token" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "phoneNumber": "01012345678",
  "yellowId": "@my_channel"
}'
```

응답

200토큰 발송 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/profile/category/all

### 발신프로필 카테고리 전체 조회

발신프로필 등록 시 사용할 카테고리 목록 전체를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/category/all" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string"
}'
```

응답

200카테고리 전체 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | 필수 | 성공 시 카테고리 목록 |
| └code | string | — | 카테고리 코드 |
| └name | string | — | 카테고리 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "code": "string",
      "name": "string"
    }
  ]
}
```

post/v3/kakao/profile/category

### 발신프로필 카테고리 단건 조회

카테고리 코드에 해당하는 특정 발신프로필 카테고리를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| categoryCode | string | 필수 | 카테고리 코드 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/category" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "categoryCode": "string"
}'
```

응답

200카테고리 단건

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 카테고리 정보 |
| └code | string | — | 카테고리 코드 |
| └name | string | — | 카테고리 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "code": "string",
    "name": "string"
  }
}
```

post/v3/kakao/profile/create

### 발신프로필 등록

[발신프로필 인증토큰 요청](https://bizppurio.github.io/kapi#post-v3kakaoprofiletoken)으로 받은 토큰을 사용하여 발신프로필을 등록합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| token | string | 필수 | 수신받은 인증 토큰 |
| phoneNumber | string | 필수 | 토큰을 수신할 휴대폰번호 (Yellow ID의 핸드폰번호와 일치) |
| yellowId | string | 필수 | 카카오톡 채널 (@ID) |
| categoryCode | string | 필수 | 카테고리 코드 |
| unsubscribePhoneNumber | string(13) | — | 무료수신거부 전화번호 (예: 080-1111-2222) |
| unsubscribeAuthNumber | string(10) | — | 무료수신거부 인증번호 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/create" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "token": "12345678",
  "phoneNumber": "01012345678",
  "yellowId": "@my_channel",
  "categoryCode": "00100010001",
  "unsubscribePhoneNumber": "080-1111-2222",
  "unsubscribeAuthNumber": "00000"
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 발신프로필 정보 |
| └senderKey | string | — | 발급된 발신프로필 키 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "senderKey": "string"
  }
}
```

post/v3/kakao/profile

### 발신프로필 조회

발신프로필 정보를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string"
}'
```

응답

200발신프로필 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 발신프로필 상세 정보 |
| └senderKey | string | — | 조회된 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 UUID |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └status | string | — | 발신프로필 상태 |
| └block | boolean | — | 발신프로필 차단 여부 |
| └dormant | boolean | — | 발신프로필 휴면 여부 |
| └profileStatus | string | — | A=activated / C=deactivated / B=block / E=deleting / D=deleted  = A | C | B | E | D |
| └createdAt | string | — | 발신프로필 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └categoryCode | string | — | 발신프로필 카테고리코드 |
| └unsubscribePhoneNumber | string | — | 무료수신거부 전화번호 |
| └unsubscribeAuthNumber | string | — | 무료수신거부 인증번호 |
| └bizchat | boolean | — | 상담톡 사용 여부 |
| └brandMessage | boolean | — | 브랜드메시지 사용 여부 |
| └committalCompanyName | string | — | 위탁사 이름 (상담톡 관련) |
| └channelKey | string | — | 메시지 전송 결과 수신 채널키 |
| └businessProfile | boolean | — | 카카오톡 채널 비즈니스 인증 여부 |
| └businessType | string | — | 카카오톡 채널 비즈니스 인증 타입 |
| └profileSpamLevel | string | — | 카카오톡 채널 스팸 상태 |
| └profileMessageSpamLevel | string | — | 카카오톡 메시지 스팸 상태 |
| └clearBlockUrl | string | — | 알림톡 차단 해제 링크 |
| └groups | array<object> | — | 발신프로필이 속한 그룹 목록 (전체/다중 조회 응답에만 포함) |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └createdAt | string | — | 발신프로필 등록일 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "senderKey": "string",
    "uuid": "string",
    "name": "string",
    "status": "string",
    "block": true,
    "dormant": true,
    "profileStatus": "A",
    "createdAt": "string",
    "modifiedAt": "string",
    "categoryCode": "string",
    "unsubscribePhoneNumber": "string",
    "unsubscribeAuthNumber": "string",
    "bizchat": true,
    "brandMessage": true,
    "committalCompanyName": "string",
    "channelKey": "string",
    "businessProfile": true,
    "businessType": "string",
    "profileSpamLevel": "string",
    "profileMessageSpamLevel": "string",
    "clearBlockUrl": "string",
    "groups": [
      {
        "groupKey": "string",
        "name": "string",
        "createdAt": "string"
      }
    ]
  }
}
```

post/v3/kakao/profile/use

### 발신프로필 리스트 전체 조회

`bizId`/`apiKey`로 인증된 모든 발신프로필을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/use" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string"
}'
```

응답

200성공·실패 분리 리스트

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 성공 시 데이터 |
| └success | array<object> | — | 성공케이스에 대한 리스트 |
| └senderKey | string | — | 조회된 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 UUID |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └status | string | — | 발신프로필 상태 |
| └block | boolean | — | 발신프로필 차단 여부 |
| └dormant | boolean | — | 발신프로필 휴면 여부 |
| └profileStatus | string | — | A=activated / C=deactivated / B=block / E=deleting / D=deleted  = A | C | B | E | D |
| └createdAt | string | — | 발신프로필 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └categoryCode | string | — | 발신프로필 카테고리코드 |
| └unsubscribePhoneNumber | string | — | 무료수신거부 전화번호 |
| └unsubscribeAuthNumber | string | — | 무료수신거부 인증번호 |
| └bizchat | boolean | — | 상담톡 사용 여부 |
| └brandMessage | boolean | — | 브랜드메시지 사용 여부 |
| └committalCompanyName | string | — | 위탁사 이름 (상담톡 관련) |
| └channelKey | string | — | 메시지 전송 결과 수신 채널키 |
| └businessProfile | boolean | — | 카카오톡 채널 비즈니스 인증 여부 |
| └businessType | string | — | 카카오톡 채널 비즈니스 인증 타입 |
| └profileSpamLevel | string | — | 카카오톡 채널 스팸 상태 |
| └profileMessageSpamLevel | string | — | 카카오톡 메시지 스팸 상태 |
| └clearBlockUrl | string | — | 알림톡 차단 해제 링크 |
| └groups | array<object> | — | 발신프로필이 속한 그룹 목록 (전체/다중 조회 응답에만 포함) |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └createdAt | string | — | 발신프로필 등록일 |
| └fail | array<object> | — | 실패케이스에 대한 리스트 |
| └senderKey | string | — | 발신 프로필 키 |
| └code | string | — | 실패 결과 코드 |
| └message | string | — | 실패 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "success": [
      {
        "senderKey": "string",
        "uuid": "string",
        "name": "string",
        "status": "string",
        "block": true,
        "dormant": true,
        "profileStatus": "A",
        "createdAt": "string",
        "modifiedAt": "string",
        "categoryCode": "string",
        "unsubscribePhoneNumber": "string",
        "unsubscribeAuthNumber": "string",
        "bizchat": true,
        "brandMessage": true,
        "committalCompanyName": "string",
        "channelKey": "string",
        "businessProfile": true,
        "businessType": "string",
        "profileSpamLevel": "string",
        "profileMessageSpamLevel": "string",
        "clearBlockUrl": "string",
        "groups": [
          {}
        ]
      }
    ],
    "fail": [
      {
        "senderKey": "string",
        "code": "string",
        "message": "string"
      }
    ]
  }
}
```

post/v3/kakao/profile/multi

### 발신프로필 리스트 조회 (다중 키)

특정 `senderKey` 배열에 대해 일괄 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | array<string> | 필수 | 발신프로필 키 배열 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/multi" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": [
    "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
    "03423ege2545c0b2XXXXXXXXXXXXX"
  ]
}'
```

응답

200성공·실패 분리 리스트

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 성공 시 데이터 |
| └success | array<object> | — | 성공케이스에 대한 리스트 |
| └senderKey | string | — | 조회된 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 UUID |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └status | string | — | 발신프로필 상태 |
| └block | boolean | — | 발신프로필 차단 여부 |
| └dormant | boolean | — | 발신프로필 휴면 여부 |
| └profileStatus | string | — | A=activated / C=deactivated / B=block / E=deleting / D=deleted  = A | C | B | E | D |
| └createdAt | string | — | 발신프로필 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └categoryCode | string | — | 발신프로필 카테고리코드 |
| └unsubscribePhoneNumber | string | — | 무료수신거부 전화번호 |
| └unsubscribeAuthNumber | string | — | 무료수신거부 인증번호 |
| └bizchat | boolean | — | 상담톡 사용 여부 |
| └brandMessage | boolean | — | 브랜드메시지 사용 여부 |
| └committalCompanyName | string | — | 위탁사 이름 (상담톡 관련) |
| └channelKey | string | — | 메시지 전송 결과 수신 채널키 |
| └businessProfile | boolean | — | 카카오톡 채널 비즈니스 인증 여부 |
| └businessType | string | — | 카카오톡 채널 비즈니스 인증 타입 |
| └profileSpamLevel | string | — | 카카오톡 채널 스팸 상태 |
| └profileMessageSpamLevel | string | — | 카카오톡 메시지 스팸 상태 |
| └clearBlockUrl | string | — | 알림톡 차단 해제 링크 |
| └groups | array<object> | — | 발신프로필이 속한 그룹 목록 (전체/다중 조회 응답에만 포함) |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 카카오톡 채널 발신프로필 명 |
| └createdAt | string | — | 발신프로필 등록일 |
| └fail | array<object> | — | 실패케이스에 대한 리스트 |
| └senderKey | string | — | 발신 프로필 키 |
| └code | string | — | 실패 결과 코드 |
| └message | string | — | 실패 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "success": [
      {
        "senderKey": "string",
        "uuid": "string",
        "name": "string",
        "status": "string",
        "block": true,
        "dormant": true,
        "profileStatus": "A",
        "createdAt": "string",
        "modifiedAt": "string",
        "categoryCode": "string",
        "unsubscribePhoneNumber": "string",
        "unsubscribeAuthNumber": "string",
        "bizchat": true,
        "brandMessage": true,
        "committalCompanyName": "string",
        "channelKey": "string",
        "businessProfile": true,
        "businessType": "string",
        "profileSpamLevel": "string",
        "profileMessageSpamLevel": "string",
        "clearBlockUrl": "string",
        "groups": [
          {}
        ]
      }
    ],
    "fail": [
      {
        "senderKey": "string",
        "code": "string",
        "message": "string"
      }
    ]
  }
}
```

post/v3/kakao/profile/recover

### 미사용 프로필 휴면 해제

장기 미사용으로 휴면 상태인 발신프로필을 차단 해제합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/profile/recover" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string"
}'
```

응답

200휴면 해제 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v4/kakao/profile/unsubscribeContent/update

### 발신프로필 무료수신거부 정보 수정

브랜드메시지 발송 시 사용되는 080 무료수신거부 정보를 수정합니다.

> ℹ️ 이 엔드포인트는 **`/v4/`** 버전 경로를 사용합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| unsubscribePhoneNumber | string(13) | 필수 | 무료수신거부 전화번호 (예: 080-1111-2222) |
| unsubscribeAuthNumber | string(10) | — | 무료수신거부 인증번호 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/profile/unsubscribeContent/update" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "unsubscribePhoneNumber": "080-1111-2222",
  "unsubscribeAuthNumber": "00000"
}'
```

응답

200수정 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v4/kakao/profile/marketingAgree/upload

### 광고성 정보 수신동의 증적자료 파일 업로드

브랜드메시지 사용 신청 전제 조건. 광고성 정보 수신동의 증적자료를 업로드합니다.

| 항목 | 값 |
| --- | --- |
| 확장자 | `jpg`, `png` |
| 최대 크기 | 5 MB |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| file | string <binary> | 필수 | 업로드할 증적 파일 (jpg/png, 5MB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/profile/marketingAgree/upload" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string",
  "file": "{binary}"
}'
```

응답

200업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 업로드 정보 |
| └fileKey | string | — | 파일 키 |
| └fileUrl | string | — | 파일 url |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "fileKey": "string",
    "fileUrl": "string"
  }
}
```

post/v4/kakao/profile/brandMessage/apply

### 발신프로필 브랜드메시지 사용 신청

브랜드메시지 타겟팅 **M / N** 사용을 신청합니다.  
신청 전 광고성 정보 수신동의 증적자료가 업로드되어 있어야 합니다 ([업로드](https://bizppurio.github.io/kapi#post-v4kakaoprofilemarketingagreeupload)).

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/profile/brandMessage/apply" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string"
}'
```

응답

200신청 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

## 그룹

발신프로필 그룹 조회 · 구성원 추가/삭제 (4개 엔드포인트)

post/v3/kakao/group

### 그룹 조회

발신프로필 그룹 목록을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/group" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string"
}'
```

응답

200그룹 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | — | 성공 시 그룹 목록 |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 그룹이름 |
| └createdAt | string | — | 생성일자 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "groupKey": "string",
      "name": "string",
      "createdAt": "string"
    }
  ]
}
```

post/v3/kakao/group/all

### 그룹 전체 조회

발신프로필 그룹 전체 목록을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/group/all" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string"
}'
```

응답

200그룹 전체 목록 ([그룹 조회](#operation/kapiListGroups)와 동일 구조)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | — | 성공 시 그룹 목록 |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 그룹이름 |
| └createdAt | string | — | 생성일자 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "groupKey": "string",
      "name": "string",
      "createdAt": "string"
    }
  ]
}
```

post/v3/kakao/group/profile/add

### 그룹에 발신프로필 추가

발신프로필 그룹에 발신프로필을 추가합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupKey | string | 필수 | 발신프로필 그룹 키 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/group/profile/add" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "groupKey": "grp_marketing_01",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX"
}'
```

응답

200추가 결과 (그룹 정보 포함)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 그룹 정보 |
| └groupKey | string | — | 그룹 key |
| └name | string | — | 그룹이름 |
| └createdAt | string | — | 생성일자 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "groupKey": "string",
    "name": "string",
    "createdAt": "string"
  }
}
```

post/v3/kakao/group/profile/delete

### 그룹에서 발신프로필 삭제

발신프로필 그룹에서 발신프로필을 삭제합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupKey | string | 필수 | 발신프로필 그룹 키 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/group/profile/delete" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string",
  "groupKey": "string"
}'
```

응답

200삭제 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

## 그룹 태그

통계용 그룹태그 CRUD (5개 엔드포인트, `/v4/` 경로)

post/v4/kakao/groupTag

### 그룹태그 한 건 조회

그룹태그 키에 해당하는 특정 그룹태그를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupTagKey | string | 필수 | 그룹태그 키 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/groupTag" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string",
  "groupTagKey": "string"
}'
```

응답

200그룹태그 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 그룹태그 정보 |
| └groupTagKey | string | — | 그룹태그 키 |
| └groupTagName | string | — | 그룹태그 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "groupTagKey": "string",
    "groupTagName": "string"
  }
}
```

post/v4/kakao/groupTag/list

### 그룹태그 목록 조회

발신프로필에 등록된 그룹태그 목록 전체를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/groupTag/list" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string"
}'
```

응답

200그룹태그 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | 필수 | 성공 시 그룹태그 목록 |
| └groupTagKey | string | — | 그룹태그 키 |
| └groupTagName | string | — | 그룹태그 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "groupTagKey": "string",
      "groupTagName": "string"
    }
  ]
}
```

post/v4/kakao/groupTag/create

### 그룹태그 등록

메시지 발송 요청 시 사용하는 그룹태그를 등록합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupTagName | string(1~200) | 필수 | 그룹태그 이름 — 한글/영문/숫자/특수문자(`!@%&*-_?~/.,`)/공백 포함 1~200자 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/groupTag/create" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "groupTagName": "6월 프로모션"
}'
```

응답

200등록 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 그룹태그 정보 |
| └groupTagKey | string | — | 그룹태그 키 |
| └groupTagName | string | — | 그룹태그 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "groupTagKey": "string",
    "groupTagName": "string"
  }
}
```

post/v4/kakao/groupTag/update

### 그룹태그 수정

그룹태그 키에 해당하는 그룹태그 이름을 수정합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupTagKey | string | 필수 | 그룹태그 키 |
| newGroupTagName | string(1~200) | 필수 | 변경할 그룹태그 이름 (1~200자) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/groupTag/update" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "groupTagKey": "gt_0001",
  "newGroupTagName": "7월 프로모션"
}'
```

응답

200수정 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | 필수 | 성공 시 그룹태그 정보 |
| └groupTagKey | string | — | 그룹태그 키 |
| └groupTagName | string | — | 그룹태그 이름 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "groupTagKey": "string",
    "groupTagName": "string"
  }
}
```

post/v4/kakao/groupTag/delete

### 그룹태그 삭제

그룹태그 키에 해당하는 그룹태그를 삭제합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| groupTagKey | string | 필수 | 그룹태그 키 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/groupTag/delete" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "senderKey": "string",
  "groupTagKey": "string"
}'
```

응답

200삭제 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

## 플러그인 콜백

알림톡 플러그인(이미지 보안·개인정보) 콜백 URL CRUD (4개 엔드포인트)

post/v3/kakao/plugin/callbackUrl/list

### 플러그인 콜백 URL 조회

발신프로필 키로 해당 카카오톡 채널에 등록된 플러그인 콜백 URL 목록을 조회합니다.

### pluginType

- `SECURE_IMAGE` — 이미지 보안 전송 (알림톡 버튼 `P1`)
- `ONE_TIME_PROFILE` — 개인정보 이용 (알림톡 버튼 `P2`)

> ℹ️ 플러그인 콜백 URL은 **플러그인당 1개**, 카카오톡 채널 기준으로 저장됩니다. 동일한 카카오톡 채널의 콜백 URL은 공유됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/plugin/callbackUrl/list" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX"
}'
```

응답

200플러그인 콜백 URL 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | array<object> | 필수 | 성공 시 플러그인 콜백 URL 목록 |
| └pluginId | string | — | 플러그인 아이디 |
| └pluginType | string | — | 플러그인 타입 (SECURE\_IMAGE: 보안이미지전송, ONE\_TIME\_PROFILE: 개인정보이용)  = SECURE\_IMAGE | ONE\_TIME\_PROFILE |
| └pluginTypeName | string | — | 플러그인 타입 이름 |
| └callbackUrl | string | — | Callback Url |
| └modifiable | boolean | — | 수정 가능 여부 (다른 허브파트너 등록 시 false) |
| └deletable | boolean | — | 삭제 가능 여부 (다른 허브파트너 등록 시 false) |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": [
    {
      "pluginId": "string",
      "pluginType": "SECURE_IMAGE",
      "pluginTypeName": "string",
      "callbackUrl": "string",
      "modifiable": true,
      "deletable": true
    }
  ]
}
```

post/v3/kakao/plugin/callbackUrl/create

### 플러그인 콜백 URL 등록

발신프로필 키로 해당 카카오톡 채널에 플러그인 콜백 URL을 등록합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| pluginType | string | 필수 | 플러그인 타입 (SECURE\_IMAGE, ONE\_TIME\_PROFILE)  = SECURE\_IMAGE | ONE\_TIME\_PROFILE |
| pluginId | string | 필수 | 플러그인 아이디 |
| callbackUrl | string | 필수 | 콜백 URL |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/plugin/callbackUrl/create" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "pluginType": "SECURE_IMAGE",
  "pluginId": "12345",
  "callbackUrl": "https://example.com/secure-image"
}'
```

응답

200등록 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/plugin/callbackUrl/update

### 플러그인 콜백 URL 수정

발신프로필 키로 해당 카카오톡 채널에 등록된 플러그인 콜백 URL을 수정합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| pluginId | string | 필수 | 플러그인 아이디 |
| callbackUrl | string | 필수 | 콜백 URL |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/plugin/callbackUrl/update" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "pluginId": "12345",
  "callbackUrl": "https://example.com/secure-image-v2"
}'
```

응답

200수정 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v3/kakao/plugin/callbackUrl/delete

### 플러그인 콜백 URL 삭제

발신프로필 키로 해당 카카오톡 채널에 등록된 플러그인 콜백 URL을 삭제합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| pluginId | string | 필수 | 플러그인 아이디 |

```
curl -X POST "https://kapi.ppurio.com/v3/kakao/plugin/callbackUrl/delete" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "pluginId": "12345"
}'
```

응답

200삭제 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

## 브랜드 템플릿

카카오 브랜드메시지(UT~UA) 기본형 템플릿 CRUD + 변경 이력 (6개 엔드포인트, `/v4/` 경로)

post/v4/kakao/brand/template/add

### 브랜드메시지 템플릿 등록

브랜드메시지 기본형 템플릿을 등록합니다.  
사전에 발신프로필이 등록되어 있어야 하고, 메시지 타입(`chatBubbleType`)에 맞는 이미지 규격을 사용해야 합니다.

### 메시지 타입별 필수 파라미터

| `chatBubbleType` | 필수 |
| --- | --- |
| `TEXT` | `senderKey`, `templateName`, `chatBubbleType`, `content` |
| `IMAGE` | + `imageUrl` |
| `WIDE` | + `imageUrl` |
| `WIDE_ITEM_LIST` | `senderKey`, `templateName`, `chatBubbleType`, `header`, `mainWideItem`, `subWideItemList` |
| `CAROUSEL_FEED` | `senderKey`, `templateName`, `chatBubbleType`, `carousel.list` |
| `PREMIUM_VIDEO` | `senderKey`, `templateName`, `chatBubbleType`, `video` |
| `COMMERCE` | + `imageUrl`, `commerce`, `buttons` |
| `CAROUSEL_COMMERCE` | `senderKey`, `templateName`, `chatBubbleType`, `carousel.list` |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | S=일반(default) / G=그룹  = S | G |
| templateName | string | 필수 | 템플릿 이름 (수정 시 최대 200자) |
| chatBubbleType | string | 필수 | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조)  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| adult | boolean | — | 성인 콘텐츠 여부 |
| header | string | — | WIDE\_ITEM\_LIST 1~20자 / PREMIUM\_VIDEO 최대 20자 (줄바꿈 불가) |
| content | string | — | TEXT/IMAGE 최대 1300자 / WIDE/PREMIUM\_VIDEO 최대 76자 |
| additionalContent | string(34) | — | 부가정보 (줄바꿈 최대 1개) |
| imageUrl | string | — | 이미지 업로드 API로 등록한 이미지 URL |
| imageLink | string | — | 이미지 클릭 시 이동 URL |
| carousel | object | — | 캐러셀 (CAROUSEL\_FEED / CAROUSEL\_COMMERCE 사용) |
| └head | object | — | 캐러셀 인트로 (CAROUSEL\_COMMERCE에서 사용) |
| └header | string(20) | — | 인트로 헤더 (줄바꿈 불가) |
| └content | string(50) | — | 인트로 내용 (줄바꿈 최대 2개) |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └list | array<object> | — | 캐러셀 리스트 (CAROUSEL\_COMMERCE는 인트로 포함 시 1~6개, 미포함 시 2~6개) |
| └header | string(20) | — | CAROUSEL\_FEED 헤더 (줄바꿈 불가) |
| └content | string(180) | — | CAROUSEL\_FEED 내용 (줄바꿈 최대 10개) |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 캐러셀 리스트 이미지 URL |
| └imageLink | string | — | 캐러셀 리스트 이미지 클릭시 이동할 URL |
| └commerce | object | — | 커머스 요소. 가격 미입력 시 고정 변수로 저장:   - `regularPrice` 미입력 → `#{정상가격}` - `discountPrice` 미입력 → `#{할인가격}` - `discountRate` 미입력 → `#{할인율}` - `discountFixed` 미입력 → `#{정액할인가격}` |
| └title | string | 필수 | 상품 제목 (줄바꿈 불가, 변수 가능) |
| └regularPrice | integer(0~99999999) | — | 정상 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{정상가격})로 저장 |
| └discountPrice | integer(0~99999999) | — | 할인 후 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{할인가격})로 저장 |
| └discountRate | integer(1~100) | — | 할인율 (1 ~ 100) 값이 없을 경우 고정 변수(변수명: #{할인율})로 저장 |
| └discountFixed | integer(0~999999) | — | 정액 할인 가격 (0 ~ 999,999) 값이 없을 경우 고정 변수(변수명: #{정액할인가격})로 저장 |
| └regularPriceName | string | — | 정상 가격 고정변수명 (`regularPrice` 미입력 시 응답에 반환) |
| └discountPriceName | string | — | 할인 후 가격 고정변수명 (`discountPrice` 미입력 시 응답에 반환) |
| └discountRateName | string | — | 할인율 고정변수명 (`discountRate` 미입력 시 응답에 반환) |
| └discountFixedName | string | — | 정액 할인 가격 고정변수명 (`discountFixed` 미입력 시 응답에 반환) |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └name | string | 필수 | 버튼 제목 — TEXT/IMAGE 14자, 그 외 8자 (줄바꿈 불가) |
| └linkType | string | 필수 | 버튼 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, AC: 채널추가, BF: 비즈니스폼, BT :봇전환, BC:상담톡전환 )  = WL | AL | BK | AC | BF | BT | BC |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └bizFormId | string | — | 비즈니스폼 ID (`BF` 사용 시) |
| └ordering | integer | — | 버튼 정렬 순서 |
| └coupon | object | — | 쿠폰 요소. `title`은 5가지 형식만 허용:   - `#{할인금액}원 할인 쿠폰` - `#{할인율}% 할인 쿠폰` - `배송비 할인 쿠폰` - `#{상품명} 무료 쿠폰` (상품명 7자) - `#{상품명} UP 쿠폰` |
| └title | string | — | 5가지 형식 중 하나 |
| └description | string | — | WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO 최대 18자 / 그 외 12자 (줄바꿈 불가) |
| └linkMobile | string | — | 기본 쿠폰 사용 시 필수 |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | 채널 쿠폰 URL (alimtalk=coupon://) 사용 시 `linkIos`와 함께 둘 중 하나 필수 |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └tail | object | — | 더보기 버튼 (변수 사용 불가) |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| mainWideItem | object | — | 와이드 아이템 (WIDE\_ITEM\_LIST 사용) |
| └title | string | — | 아이템 제목 |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 아이템 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| subWideItemList | array<object>(~4) | — | 와이드 리스트 2~5번째 아이템 |
| └title | string | — | 아이템 제목 |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 아이템 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| video | object | — | 동영상 객체 (PREMIUM\_VIDEO 필수) |
| commerce | object | — | 커머스 요소. 가격 미입력 시 고정 변수로 저장:   - `regularPrice` 미입력 → `#{정상가격}` - `discountPrice` 미입력 → `#{할인가격}` - `discountRate` 미입력 → `#{할인율}` - `discountFixed` 미입력 → `#{정액할인가격}` |
| └title | string | 필수 | 상품 제목 (줄바꿈 불가, 변수 가능) |
| └regularPrice | integer(0~99999999) | — | 정상 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{정상가격})로 저장 |
| └discountPrice | integer(0~99999999) | — | 할인 후 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{할인가격})로 저장 |
| └discountRate | integer(1~100) | — | 할인율 (1 ~ 100) 값이 없을 경우 고정 변수(변수명: #{할인율})로 저장 |
| └discountFixed | integer(0~999999) | — | 정액 할인 가격 (0 ~ 999,999) 값이 없을 경우 고정 변수(변수명: #{정액할인가격})로 저장 |
| └regularPriceName | string | — | 정상 가격 고정변수명 (`regularPrice` 미입력 시 응답에 반환) |
| └discountPriceName | string | — | 할인 후 가격 고정변수명 (`discountPrice` 미입력 시 응답에 반환) |
| └discountRateName | string | — | 할인율 고정변수명 (`discountRate` 미입력 시 응답에 반환) |
| └discountFixedName | string | — | 정액 할인 가격 고정변수명 (`discountFixed` 미입력 시 응답에 반환) |
| buttons | array<object> | — | 버튼 배열. `AC` 버튼은 TEXT/IMAGE에서 첫번째, 그 외 메시지 타입에서 마지막 위치에 등록 필요. |
| └name | string | 필수 | 버튼 제목 — TEXT/IMAGE 14자, 그 외 8자 (줄바꿈 불가) |
| └linkType | string | 필수 | 버튼 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, AC: 채널추가, BF: 비즈니스폼, BT :봇전환, BC:상담톡전환 )  = WL | AL | BK | AC | BF | BT | BC |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └bizFormId | string | — | 비즈니스폼 ID (`BF` 사용 시) |
| └ordering | integer | — | 버튼 정렬 순서 |
| coupon | object | — | 쿠폰 요소. `title`은 5가지 형식만 허용:   - `#{할인금액}원 할인 쿠폰` - `#{할인율}% 할인 쿠폰` - `배송비 할인 쿠폰` - `#{상품명} 무료 쿠폰` (상품명 7자) - `#{상품명} UP 쿠폰` |
| └title | string | — | 5가지 형식 중 하나 |
| └description | string | — | WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO 최대 18자 / 그 외 12자 (줄바꿈 불가) |
| └linkMobile | string | — | 기본 쿠폰 사용 시 필수 |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | 채널 쿠폰 URL (alimtalk=coupon://) 사용 시 `linkIos`와 함께 둘 중 하나 필수 |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/add" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateName": "봄 신상 캐러셀 피드",
  "chatBubbleType": "CAROUSEL_FEED",
  "adult": false,
  "carousel": {
    "list": [
      {
        "header": "봄 신상 트렌치코트",
        "content": "가볍고 따뜻한 봄 트렌치코트를 만나보세요.",
        "imageUrl": "https://mud-kage.kakao.com/dn/sample1/img_l.jpg",
        "buttons": [
          {
            "name": "구매하기",
            "linkType": "WL",
            "linkMobile": "https://m.example.com/coat",
            "linkPc": "https://example.com/coat"
          }
        ],
        "coupon": {
          "title": "#{할인금액}원 할인 쿠폰",
          "description": "봄맞이 특별 할인",
          "linkIos": "alimtalk=coupon://_sample/_ios",
          "linkAndroid": "alimtalk=coupon://_sample/_and"
        }
      },
      {
        "header": "여름 시즌 원피스",
        "content": "시원한 소재의 여름 원피스 컬렉션.",
        "imageUrl": "https://mud-kage.kakao.com/dn/sample2/img_l.jpg",
        "buttons": [
          {
            "name": "구매하기",
            "linkType": "WL",
            "linkMobile": "https://m.example.com/dress",
            "linkPc": "https://example.com/dress"
          }
        ]
      }
    ],
    "tail": {
      "linkMobile": "https://m.example.com/new"
    }
  }
}'
```

응답

200등록 결과 (등록된 템플릿 상세)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 브랜드메시지 템플릿 상세 정보 |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └chatBubbleType | string | — | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조) |
| └content | string | — | 템플릿 내용 - TEXT, IMAGE - 최대 1,300자 (줄바꿈: 최대 99개, URL 형식 입력 가능) - WIDE, PREMIUM\_VIDEO - 최대 76자 (줄바꿈: 최대 5개) |
| └adult | boolean | — | 성인 콘텐츠 여부 |
| └imageLink | string | — | 이미지 클릭시 이동 URL |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └header | string | — | 캐러셀 인트로 헤더 최대 20자 (줄바꿈: 불가) |
| └additionalContent | string | — | 템플릿 부가정보 - 공백 포함 최대 34자 (줄바꿈: 최대 1개) |
| └carousel | object | — | 커머스 요소 |
| └wideItemList | array<object> | — | 와이드 리스트 목록 (9.1 템플릿 등록 참고) |
| └video | object | — | O |
| └commerce | object | — | 메시지 표기 방식에 따라 regularPrice, discountPrice, discountRate, discountFixed은 다음과 같이 사용할 수 있습니다. 정상 가격으로 표기 : regularPrice 정상 가격 + 할인 후 가격(할인율 포함)으로 표기 : regularPrice, discountPrice, discountRate 정상 가격 + 할인 후 가격(정액 할인 가격 포함)으로 표기 : regularPrice, discountPrice, discountFixed regularPrice, discountPrice, discountRate, discountFixed 값을 입력하지 않을 경우 고정 변수명으로 저장됩니다. 고정 변수명을 사용하면 메시지 발송 시 금액을 변경하여 메시지를 발송 할 수 있습니다. |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └coupon | object | — | 채널 쿠폰 URL(포맷: alimtalk=coupon://) 사용시 linkAndroid, linkIos 중 하나 필수 입력 채널 쿠폰 URL이 아닌 기본 쿠폰 사용시 linkMobile 필수 입력 |
| └createdAt | string | — | 등록일시 (yyyy-MM-dd HH:mm:ss) |
| └modifiedAt | string | — | 수정일시 (yyyy-MM-dd HH:mm:ss) |
| └status | string | — | A=등록 / S=차단  = A | S |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "templateCode": "string",
    "templateName": "string",
    "chatBubbleType": "string",
    "content": "string",
    "adult": true,
    "imageLink": "string",
    "imageUrl": "string",
    "header": "string",
    "additionalContent": "string",
    "carousel": {},
    "wideItemList": [
      {}
    ],
    "video": {},
    "commerce": {},
    "buttons": [
      {}
    ],
    "coupon": {},
    "createdAt": "string",
    "modifiedAt": "string",
    "status": "A"
  }
}
```

post/v4/kakao/brand/template/detail

### 브랜드메시지 템플릿 조회

발신프로필에 등록된 브랜드메시지 템플릿의 상세 정보를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/detail" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200템플릿 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 브랜드메시지 템플릿 상세 정보 |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └chatBubbleType | string | — | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조) |
| └content | string | — | 템플릿 내용 - TEXT, IMAGE - 최대 1,300자 (줄바꿈: 최대 99개, URL 형식 입력 가능) - WIDE, PREMIUM\_VIDEO - 최대 76자 (줄바꿈: 최대 5개) |
| └adult | boolean | — | 성인 콘텐츠 여부 |
| └imageLink | string | — | 이미지 클릭시 이동 URL |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └header | string | — | 캐러셀 인트로 헤더 최대 20자 (줄바꿈: 불가) |
| └additionalContent | string | — | 템플릿 부가정보 - 공백 포함 최대 34자 (줄바꿈: 최대 1개) |
| └carousel | object | — | 커머스 요소 |
| └wideItemList | array<object> | — | 와이드 리스트 목록 (9.1 템플릿 등록 참고) |
| └video | object | — | O |
| └commerce | object | — | 메시지 표기 방식에 따라 regularPrice, discountPrice, discountRate, discountFixed은 다음과 같이 사용할 수 있습니다. 정상 가격으로 표기 : regularPrice 정상 가격 + 할인 후 가격(할인율 포함)으로 표기 : regularPrice, discountPrice, discountRate 정상 가격 + 할인 후 가격(정액 할인 가격 포함)으로 표기 : regularPrice, discountPrice, discountFixed regularPrice, discountPrice, discountRate, discountFixed 값을 입력하지 않을 경우 고정 변수명으로 저장됩니다. 고정 변수명을 사용하면 메시지 발송 시 금액을 변경하여 메시지를 발송 할 수 있습니다. |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └coupon | object | — | 채널 쿠폰 URL(포맷: alimtalk=coupon://) 사용시 linkAndroid, linkIos 중 하나 필수 입력 채널 쿠폰 URL이 아닌 기본 쿠폰 사용시 linkMobile 필수 입력 |
| └createdAt | string | — | 등록일시 (yyyy-MM-dd HH:mm:ss) |
| └modifiedAt | string | — | 수정일시 (yyyy-MM-dd HH:mm:ss) |
| └status | string | — | A=등록 / S=차단  = A | S |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "templateCode": "string",
    "templateName": "string",
    "chatBubbleType": "string",
    "content": "string",
    "adult": true,
    "imageLink": "string",
    "imageUrl": "string",
    "header": "string",
    "additionalContent": "string",
    "carousel": {},
    "wideItemList": [
      {}
    ],
    "video": {},
    "commerce": {},
    "buttons": [
      {}
    ],
    "coupon": {},
    "createdAt": "string",
    "modifiedAt": "string",
    "status": "A"
  }
}
```

post/v4/kakao/brand/template/update

### 브랜드메시지 템플릿 수정

[등록](https://bizppurio.github.io/kapi#post-v4kakaobrandtemplateadd)의 모든 필드 + `templateCode` (필수). `templateName` 최대 200자.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | S=일반(default) / G=그룹  = S | G |
| templateName | string | 필수 | 템플릿 이름 (수정 시 최대 200자) |
| chatBubbleType | string | 필수 | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조)  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| adult | boolean | — | 성인 콘텐츠 여부 |
| header | string | — | WIDE\_ITEM\_LIST 1~20자 / PREMIUM\_VIDEO 최대 20자 (줄바꿈 불가) |
| content | string | — | TEXT/IMAGE 최대 1300자 / WIDE/PREMIUM\_VIDEO 최대 76자 |
| additionalContent | string(34) | — | 부가정보 (줄바꿈 최대 1개) |
| imageUrl | string | — | 이미지 업로드 API로 등록한 이미지 URL |
| imageLink | string | — | 이미지 클릭 시 이동 URL |
| carousel | object | — | 캐러셀 (CAROUSEL\_FEED / CAROUSEL\_COMMERCE 사용) |
| └head | object | — | 캐러셀 인트로 (CAROUSEL\_COMMERCE에서 사용) |
| └header | string(20) | — | 인트로 헤더 (줄바꿈 불가) |
| └content | string(50) | — | 인트로 내용 (줄바꿈 최대 2개) |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └list | array<object> | — | 캐러셀 리스트 (CAROUSEL\_COMMERCE는 인트로 포함 시 1~6개, 미포함 시 2~6개) |
| └header | string(20) | — | CAROUSEL\_FEED 헤더 (줄바꿈 불가) |
| └content | string(180) | — | CAROUSEL\_FEED 내용 (줄바꿈 최대 10개) |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 캐러셀 리스트 이미지 URL |
| └imageLink | string | — | 캐러셀 리스트 이미지 클릭시 이동할 URL |
| └commerce | object | — | 커머스 요소. 가격 미입력 시 고정 변수로 저장:   - `regularPrice` 미입력 → `#{정상가격}` - `discountPrice` 미입력 → `#{할인가격}` - `discountRate` 미입력 → `#{할인율}` - `discountFixed` 미입력 → `#{정액할인가격}` |
| └title | string | 필수 | 상품 제목 (줄바꿈 불가, 변수 가능) |
| └regularPrice | integer(0~99999999) | — | 정상 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{정상가격})로 저장 |
| └discountPrice | integer(0~99999999) | — | 할인 후 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{할인가격})로 저장 |
| └discountRate | integer(1~100) | — | 할인율 (1 ~ 100) 값이 없을 경우 고정 변수(변수명: #{할인율})로 저장 |
| └discountFixed | integer(0~999999) | — | 정액 할인 가격 (0 ~ 999,999) 값이 없을 경우 고정 변수(변수명: #{정액할인가격})로 저장 |
| └regularPriceName | string | — | 정상 가격 고정변수명 (`regularPrice` 미입력 시 응답에 반환) |
| └discountPriceName | string | — | 할인 후 가격 고정변수명 (`discountPrice` 미입력 시 응답에 반환) |
| └discountRateName | string | — | 할인율 고정변수명 (`discountRate` 미입력 시 응답에 반환) |
| └discountFixedName | string | — | 정액 할인 가격 고정변수명 (`discountFixed` 미입력 시 응답에 반환) |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └name | string | 필수 | 버튼 제목 — TEXT/IMAGE 14자, 그 외 8자 (줄바꿈 불가) |
| └linkType | string | 필수 | 버튼 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, AC: 채널추가, BF: 비즈니스폼, BT :봇전환, BC:상담톡전환 )  = WL | AL | BK | AC | BF | BT | BC |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └bizFormId | string | — | 비즈니스폼 ID (`BF` 사용 시) |
| └ordering | integer | — | 버튼 정렬 순서 |
| └coupon | object | — | 쿠폰 요소. `title`은 5가지 형식만 허용:   - `#{할인금액}원 할인 쿠폰` - `#{할인율}% 할인 쿠폰` - `배송비 할인 쿠폰` - `#{상품명} 무료 쿠폰` (상품명 7자) - `#{상품명} UP 쿠폰` |
| └title | string | — | 5가지 형식 중 하나 |
| └description | string | — | WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO 최대 18자 / 그 외 12자 (줄바꿈 불가) |
| └linkMobile | string | — | 기본 쿠폰 사용 시 필수 |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | 채널 쿠폰 URL (alimtalk=coupon://) 사용 시 `linkIos`와 함께 둘 중 하나 필수 |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └tail | object | — | 더보기 버튼 (변수 사용 불가) |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| mainWideItem | object | — | 와이드 아이템 (WIDE\_ITEM\_LIST 사용) |
| └title | string | — | 아이템 제목 |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 아이템 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| subWideItemList | array<object>(~4) | — | 와이드 리스트 2~5번째 아이템 |
| └title | string | — | 아이템 제목 |
| └imageUrl | string | — | 이미지 업로드 API로 등록한 아이템 이미지 URL |
| └linkMobile | string | — | MOBILE 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 쿠폰 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 쿠폰 클릭 시 실행할 application custom scheme |
| video | object | — | 동영상 객체 (PREMIUM\_VIDEO 필수) |
| commerce | object | — | 커머스 요소. 가격 미입력 시 고정 변수로 저장:   - `regularPrice` 미입력 → `#{정상가격}` - `discountPrice` 미입력 → `#{할인가격}` - `discountRate` 미입력 → `#{할인율}` - `discountFixed` 미입력 → `#{정액할인가격}` |
| └title | string | 필수 | 상품 제목 (줄바꿈 불가, 변수 가능) |
| └regularPrice | integer(0~99999999) | — | 정상 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{정상가격})로 저장 |
| └discountPrice | integer(0~99999999) | — | 할인 후 가격 (0 ~ 99,999,999) 값이 없을 경우 고정 변수(변수명: #{할인가격})로 저장 |
| └discountRate | integer(1~100) | — | 할인율 (1 ~ 100) 값이 없을 경우 고정 변수(변수명: #{할인율})로 저장 |
| └discountFixed | integer(0~999999) | — | 정액 할인 가격 (0 ~ 999,999) 값이 없을 경우 고정 변수(변수명: #{정액할인가격})로 저장 |
| └regularPriceName | string | — | 정상 가격 고정변수명 (`regularPrice` 미입력 시 응답에 반환) |
| └discountPriceName | string | — | 할인 후 가격 고정변수명 (`discountPrice` 미입력 시 응답에 반환) |
| └discountRateName | string | — | 할인율 고정변수명 (`discountRate` 미입력 시 응답에 반환) |
| └discountFixedName | string | — | 정액 할인 가격 고정변수명 (`discountFixed` 미입력 시 응답에 반환) |
| buttons | array<object> | — | 버튼 배열. `AC` 버튼은 TEXT/IMAGE에서 첫번째, 그 외 메시지 타입에서 마지막 위치에 등록 필요. |
| └name | string | 필수 | 버튼 제목 — TEXT/IMAGE 14자, 그 외 8자 (줄바꿈 불가) |
| └linkType | string | 필수 | 버튼 링크타입 (WL:웹링크, AL:앱링크, BK:봇키워드, AC: 채널추가, BF: 비즈니스폼, BT :봇전환, BC:상담톡전환 )  = WL | AL | BK | AC | BF | BT | BC |
| └linkMobile | string | — | MOBILE 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | MOBILE Android 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| └bizFormId | string | — | 비즈니스폼 ID (`BF` 사용 시) |
| └ordering | integer | — | 버튼 정렬 순서 |
| coupon | object | — | 쿠폰 요소. `title`은 5가지 형식만 허용:   - `#{할인금액}원 할인 쿠폰` - `#{할인율}% 할인 쿠폰` - `배송비 할인 쿠폰` - `#{상품명} 무료 쿠폰` (상품명 7자) - `#{상품명} UP 쿠폰` |
| └title | string | — | 5가지 형식 중 하나 |
| └description | string | — | WIDE/WIDE\_ITEM\_LIST/PREMIUM\_VIDEO 최대 18자 / 그 외 12자 (줄바꿈 불가) |
| └linkMobile | string | — | 기본 쿠폰 사용 시 필수 |
| └linkPc | string | — | PC 환경에서 캐러셀 인트로 클릭 시 이동할 URL |
| └linkAndroid | string | — | 채널 쿠폰 URL (alimtalk=coupon://) 사용 시 `linkIos`와 함께 둘 중 하나 필수 |
| └linkIos | string | — | MOBILE iOS 환경에서 캐러셀 인트로 클릭 시 실행할 application custom scheme |
| templateCode | string | 필수 | 수정 대상 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/update" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateName": "봄 신상 캐러셀 피드",
  "chatBubbleType": "CAROUSEL_FEED",
  "adult": false,
  "carousel": {
    "list": [
      {
        "header": "봄 신상 트렌치코트",
        "content": "가볍고 따뜻한 봄 트렌치코트를 만나보세요.",
        "imageUrl": "https://mud-kage.kakao.com/dn/sample1/img_l.jpg",
        "buttons": [
          {
            "name": "구매하기",
            "linkType": "WL",
            "linkMobile": "https://m.example.com/coat",
            "linkPc": "https://example.com/coat"
          }
        ],
        "coupon": {
          "title": "#{할인금액}원 할인 쿠폰",
          "description": "봄맞이 특별 할인",
          "linkIos": "alimtalk=coupon://_sample/_ios",
          "linkAndroid": "alimtalk=coupon://_sample/_and"
        }
      },
      {
        "header": "여름 시즌 원피스",
        "content": "시원한 소재의 여름 원피스 컬렉션.",
        "imageUrl": "https://mud-kage.kakao.com/dn/sample2/img_l.jpg",
        "buttons": [
          {
            "name": "구매하기",
            "linkType": "WL",
            "linkMobile": "https://m.example.com/dress",
            "linkPc": "https://example.com/dress"
          }
        ]
      }
    ],
    "tail": {
      "linkMobile": "https://m.example.com/new"
    }
  },
  "templateCode": "string"
}'
```

응답

200수정 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 브랜드메시지 템플릿 상세 정보 |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └chatBubbleType | string | — | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조) |
| └content | string | — | 템플릿 내용 - TEXT, IMAGE - 최대 1,300자 (줄바꿈: 최대 99개, URL 형식 입력 가능) - WIDE, PREMIUM\_VIDEO - 최대 76자 (줄바꿈: 최대 5개) |
| └adult | boolean | — | 성인 콘텐츠 여부 |
| └imageLink | string | — | 이미지 클릭시 이동 URL |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └header | string | — | 캐러셀 인트로 헤더 최대 20자 (줄바꿈: 불가) |
| └additionalContent | string | — | 템플릿 부가정보 - 공백 포함 최대 34자 (줄바꿈: 최대 1개) |
| └carousel | object | — | 커머스 요소 |
| └wideItemList | array<object> | — | 와이드 리스트 목록 (9.1 템플릿 등록 참고) |
| └video | object | — | O |
| └commerce | object | — | 메시지 표기 방식에 따라 regularPrice, discountPrice, discountRate, discountFixed은 다음과 같이 사용할 수 있습니다. 정상 가격으로 표기 : regularPrice 정상 가격 + 할인 후 가격(할인율 포함)으로 표기 : regularPrice, discountPrice, discountRate 정상 가격 + 할인 후 가격(정액 할인 가격 포함)으로 표기 : regularPrice, discountPrice, discountFixed regularPrice, discountPrice, discountRate, discountFixed 값을 입력하지 않을 경우 고정 변수명으로 저장됩니다. 고정 변수명을 사용하면 메시지 발송 시 금액을 변경하여 메시지를 발송 할 수 있습니다. |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └coupon | object | — | 채널 쿠폰 URL(포맷: alimtalk=coupon://) 사용시 linkAndroid, linkIos 중 하나 필수 입력 채널 쿠폰 URL이 아닌 기본 쿠폰 사용시 linkMobile 필수 입력 |
| └createdAt | string | — | 등록일시 (yyyy-MM-dd HH:mm:ss) |
| └modifiedAt | string | — | 수정일시 (yyyy-MM-dd HH:mm:ss) |
| └status | string | — | A=등록 / S=차단  = A | S |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "templateCode": "string",
    "templateName": "string",
    "chatBubbleType": "string",
    "content": "string",
    "adult": true,
    "imageLink": "string",
    "imageUrl": "string",
    "header": "string",
    "additionalContent": "string",
    "carousel": {},
    "wideItemList": [
      {}
    ],
    "video": {},
    "commerce": {},
    "buttons": [
      {}
    ],
    "coupon": {},
    "createdAt": "string",
    "modifiedAt": "string",
    "status": "A"
  }
}
```

post/v4/kakao/brand/template/delete

### 브랜드메시지 템플릿 삭제

> ⚠️ 템플릿 상태가 \*\*등록(A)\*\*인 경우에만 삭제 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/delete" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001"
}'
```

응답

200삭제 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string"
}
```

post/v4/kakao/brand/template/list

### 브랜드메시지 템플릿 목록 조회

발신프로필에 등록된 브랜드메시지 템플릿 목록을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 (S:일반(default), G:그룹)  = S | G |
| status | string | — | A=정상 / S=차단  = A | S |
| page | string | — | 요청 페이지 (default: 1) |
| count | string | — | 페이지 별 템플릿 개수 (default: 30) |
| keyword | string(2~50) | — | 검색 키워드 |
| startDate | string | — | 생성일자 기준 시작일자 (yyyyMMddHHmmss) |
| endDate | string | — | 생성일자 기준 종료일자 (yyyyMMddHHmmss) |
| chatBubbleType | string | — | 메시지 타입 검색 조건  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/list" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "page": "1",
  "count": "30",
  "status": "A"
}'
```

응답

200목록 응답

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| totalCount | integer | 필수 | 전체 건수 |
| totalPage | integer | 필수 | 전체 페이지 수 |
| currentPage | integer | 필수 | 현재 페이지 |
| data | object | 필수 | 성공 시 반환 데이터 |
| └hasNext | boolean | — | 다음 페이지 여부 |
| └list | array<object> | — | 성공 시 템플릿 목록 |
| └senderKey | string | — | 발신프로필 키 |
| └senderKeyType | string | — | 발신프로필 키 타입  = S | G |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └createdAt | string | — | 등록일 |
| └modifiedAt | string | — | 최종 수정일 |
| └serviceStatus | string | — | 템플릿 상태 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "totalCount": 0,
  "totalPage": 0,
  "currentPage": 0,
  "data": {
    "hasNext": true,
    "list": [
      {
        "senderKey": "string",
        "senderKeyType": "S",
        "templateCode": "string",
        "templateName": "string",
        "createdAt": "string",
        "modifiedAt": "string",
        "serviceStatus": "string"
      }
    ]
  }
}
```

post/v4/kakao/brand/template/history

### 브랜드메시지 템플릿 변경 이력 조회

브랜드메시지 템플릿의 변경 이력을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default) / G=그룹  = S | G |
| templateCode | string(30) | 필수 | 템플릿 코드 |
| page | string | — | 요청 페이지 번호 |
| count | string | — | 페이지 별 템플릿 개수 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/template/history" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "templateCode": "order_confirm_001",
  "page": "string",
  "count": "string"
}'
```

응답

200변경 이력 (각 항목은 템플릿 상세 + version + insertedAt)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| hasNext | boolean | — | 다음 페이지 존재 여부 |
| data | array<allOf> | 필수 | 성공 시 반환 데이터 |
| └templateCode | string | — | 템플릿 코드 |
| └templateName | string | — | 템플릿 이름 |
| └chatBubbleType | string | — | 메시지 타입 ('9.1.1. 메시지 타입 별 필수 파라미터' 참조) |
| └content | string | — | 템플릿 내용 - TEXT, IMAGE - 최대 1,300자 (줄바꿈: 최대 99개, URL 형식 입력 가능) - WIDE, PREMIUM\_VIDEO - 최대 76자 (줄바꿈: 최대 5개) |
| └adult | boolean | — | 성인 콘텐츠 여부 |
| └imageLink | string | — | 이미지 클릭시 이동 URL |
| └imageUrl | string | — | 이미지 업로드 API 로 등록한 이미지 URL |
| └header | string | — | 캐러셀 인트로 헤더 최대 20자 (줄바꿈: 불가) |
| └additionalContent | string | — | 템플릿 부가정보 - 공백 포함 최대 34자 (줄바꿈: 최대 1개) |
| └carousel | object | — | 커머스 요소 |
| └wideItemList | array<object> | — | 와이드 리스트 목록 (9.1 템플릿 등록 참고) |
| └video | object | — | O |
| └commerce | object | — | 메시지 표기 방식에 따라 regularPrice, discountPrice, discountRate, discountFixed은 다음과 같이 사용할 수 있습니다. 정상 가격으로 표기 : regularPrice 정상 가격 + 할인 후 가격(할인율 포함)으로 표기 : regularPrice, discountPrice, discountRate 정상 가격 + 할인 후 가격(정액 할인 가격 포함)으로 표기 : regularPrice, discountPrice, discountFixed regularPrice, discountPrice, discountRate, discountFixed 값을 입력하지 않을 경우 고정 변수명으로 저장됩니다. 고정 변수명을 사용하면 메시지 발송 시 금액을 변경하여 메시지를 발송 할 수 있습니다. |
| └buttons | array<object> | — | 버튼 요소에는 전체 버튼을 통틀어 최대 20개(중복 제외)의 변수 사용이 가능합니다. 변수명은 최대 20자 이내 한/영/숫자/허용된 특수기호('-', '\_')로만 입력 가능합니다. (단, 변수 선언 후 필드 별 최대 글자수는 초과할 수 없습니다.) AC 버튼을 사용할 경우, TEXT, IMAGE 는 첫번째 버튼으로, 그 외 메시지 타입의 경우 마지막 버튼으로 등록해주셔야 합니다. |
| └coupon | object | — | 채널 쿠폰 URL(포맷: alimtalk=coupon://) 사용시 linkAndroid, linkIos 중 하나 필수 입력 채널 쿠폰 URL이 아닌 기본 쿠폰 사용시 linkMobile 필수 입력 |
| └createdAt | string | — | 등록일시 (yyyy-MM-dd HH:mm:ss) |
| └modifiedAt | string | — | 수정일시 (yyyy-MM-dd HH:mm:ss) |
| └status | string | — | A=등록 / S=차단  = A | S |
| └version | integer | — | 템플릿 버전 |
| └insertedAt | string | — | 변경 생성일시 (yyyy-MM-dd HH:mm:ss) |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "hasNext": true,
  "data": [
    {
      "templateCode": "string",
      "templateName": "string",
      "chatBubbleType": "string",
      "content": "string",
      "adult": true,
      "imageLink": "string",
      "imageUrl": "string",
      "header": "string",
      "additionalContent": "string",
      "carousel": {},
      "wideItemList": [
        {}
      ],
      "video": {},
      "commerce": {},
      "buttons": [
        {}
      ],
      "coupon": {},
      "createdAt": "string",
      "modifiedAt": "string",
      "status": "A",
      "version": 0,
      "insertedAt": "string"
    }
  ]
}
```

## 브랜드 이미지

브랜드메시지(UI/UM/UP/UW/UL/UC/UA)용 이미지 업로드 (6개 엔드포인트, `/v4/` `multipart/form-data`)

post/v4/kakao/brand/image/default

### 브랜드메시지 이미지 업로드 (기본)

메시지 타입이 **이미지(UI), 커머스(UM), 프리미엄 동영상(UP)** 인 브랜드메시지 이미지.

| 항목 | 값 |
| --- | --- |
| 파일 포맷 | `jpg`, `png` |
| 최대 크기 | 5 MB |
| 권장 사이즈 | 800 × 400px (가로 500px 이상) |
| 가로:세로 비율 | 2:1 ~ 3:4 |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/default" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

post/v4/kakao/brand/image/wide

### 브랜드메시지 와이드 이미지 업로드

메시지 타입이 **와이드 이미지(UW)** 인 브랜드메시지.

| 항목 | 값 |
| --- | --- |
| 권장 사이즈 | 800 × 600px (가로 500px 이상) |
| 가로:세로 비율 | 2:1 ~ 1:1 |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/wide" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

post/v4/kakao/brand/image/wideItemList/first

### 와이드 아이템 첫번째 리스트 이미지 업로드

메시지 타입이 **와이드 리스트(UL)** 인 브랜드메시지의 **1번째** 리스트 이미지.

| 항목 | 값 |
| --- | --- |
| 가로 사이즈 | 500px 이상 |
| 가로:세로 비율 | **2:1** |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| image | string <binary> | 필수 | 업로드할 이미지 파일 (jpg/png, 500KB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/wideItemList/first" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "image": "{binary}"
}'
```

응답

200업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| image | string | — | 성공 시 이미지가 등록된 카카오 서버 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "image": "string"
}
```

post/v4/kakao/brand/image/wideItemList

### 와이드 아이템 리스트 이미지 업로드 (2~5번째)

와이드 리스트(UL) 의 **2~5번째** 이미지. 아이템 리스트 갯수에 맞춰 `imageList[]` 로 업로드.

| 항목 | 값 |
| --- | --- |
| 가로 사이즈 | 500px 이상 |
| 가로:세로 비율 | **1:1** |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| imageList | array<string <binary>> | 필수 | 이미지 파일 배열 (jpg/png, 각 5MB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/wideItemList" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "imageList": [
    "{binary}"
  ]
}'
```

응답

200다중 업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 업로드 결과 |
| └success | array<object> | — | 성공 결과 목록 |
| └formField | string | — | 업로드 field 이름 |
| └url | string | — | 이미지가 등록된 카카오 서버 URL |
| └failure | array<object> | — | 실패 결과 목록 |
| └formField | string | — | 업로드 field |
| └error | object | — | 에러 정보 |
| └code | string | — | 에러 코드 |
| └message | string | — | 에러 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "success": [
      {
        "formField": "string",
        "url": "string"
      }
    ],
    "failure": [
      {
        "formField": "string",
        "error": {
          "code": "string",
          "message": "string"
        }
      }
    ]
  }
}
```

post/v4/kakao/brand/image/carouselFeed

### 캐러셀 피드 이미지 업로드

메시지 타입이 **캐러셀 피드(UC)** 인 브랜드메시지. 캐러셀 리스트 갯수만큼 `imageList[]` 업로드.

| 항목 | 값 |
| --- | --- |
| 권장 사이즈 | 800 × 600px / 800 × 400px (가로 500px 이상) |
| 가로:세로 비율 | 2:1 ~ 3:4 |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| imageList | array<string <binary>> | 필수 | 이미지 파일 배열 (jpg/png, 각 5MB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/carouselFeed" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "imageList": [
    "{binary}"
  ]
}'
```

응답

200다중 업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 업로드 결과 |
| └success | array<object> | — | 성공 결과 목록 |
| └formField | string | — | 업로드 field 이름 |
| └url | string | — | 이미지가 등록된 카카오 서버 URL |
| └failure | array<object> | — | 실패 결과 목록 |
| └formField | string | — | 업로드 field |
| └error | object | — | 에러 정보 |
| └code | string | — | 에러 코드 |
| └message | string | — | 에러 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "success": [
      {
        "formField": "string",
        "url": "string"
      }
    ],
    "failure": [
      {
        "formField": "string",
        "error": {
          "code": "string",
          "message": "string"
        }
      }
    ]
  }
}
```

post/v4/kakao/brand/image/carouselCommerce

### 캐러셀 커머스 이미지 업로드

메시지 타입이 **캐러셀 커머스(UA)** 인 브랜드메시지. **캐러셀 인트로 + 캐러셀 리스트 갯수**에 맞춰 `imageList[]` 업로드.

| 항목 | 값 |
| --- | --- |
| 권장 사이즈 | 800 × 600px / 800 × 400px (가로 500px 이상) |
| 가로:세로 비율 | 2:1 ~ 3:4 (**전체 이미지 비율 동일해야 함**) |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| imageList | array<string <binary>> | 필수 | 이미지 파일 배열 (jpg/png, 각 5MB 이하) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/image/carouselCommerce" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "string",
  "apiKey": "string",
  "imageList": [
    "{binary}"
  ]
}'
```

응답

200다중 업로드 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 업로드 결과 |
| └success | array<object> | — | 성공 결과 목록 |
| └formField | string | — | 업로드 field 이름 |
| └url | string | — | 이미지가 등록된 카카오 서버 URL |
| └failure | array<object> | — | 실패 결과 목록 |
| └formField | string | — | 업로드 field |
| └error | object | — | 에러 정보 |
| └code | string | — | 에러 코드 |
| └message | string | — | 에러 메시지 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "success": [
      {
        "formField": "string",
        "url": "string"
      }
    ],
    "failure": [
      {
        "formField": "string",
        "error": {
          "code": "string",
          "message": "string"
        }
      }
    ]
  }
}
```

## 브랜드 동영상

브랜드메시지용 동영상 조회·업로드 등록·업로드 (3개 엔드포인트). 업로드는 발급받은 URL·토큰(5분 유효)으로 카카오 서버 직접 호출.

post/v4/kakao/brand/video/search

### 동영상 조회

`vid`와 발신프로필 키로 카카오에 등록된 동영상 단건을 조회합니다.

- 발신프로필 그룹(`senderKeyType: G`)은 동영상 기능을 지원하지 않습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| vid | string | 필수 | 동영상 ID |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default). G=그룹은 동영상 기능 미지원 (오류 응답)  = S | G |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/video/search" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "vid": "459409228304",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX"
}'
```

응답

200조회 성공 — `data`는 동영상 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 동영상 정보 |
| └vid | string | — | 동영상 ID |
| └status | string | — | 동영상 상태   - `REGISTERED` 업로드 등록 / `ENCODING` 인코딩 중 - `PUBLIC` 공개 (발송 및 템플릿 등록 가능) / `PRIVATE` 비공개 (템플릿 등록 가능) - `VIOLATED` 위반 동영상 / `ILLEGAL` 불법촬영물 동영상 - `DELETED` 삭제된 동영상 / `ERROR` 업로드·인코딩 중 에러 발생  = REGISTERED | ENCODING | PUBLIC | PRIVATE | VIOLATED | ILLEGAL | DELETED | ERROR |
| └title | string | — | 동영상 제목 |
| └thumbnailUrl | string | — | 썸네일 이미지 URL |
| └videoUrl | string | — | 동영상 재생 URL |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "vid": "string",
    "status": "REGISTERED",
    "title": "string",
    "thumbnailUrl": "string",
    "videoUrl": "string"
  }
}
```

post/v4/kakao/brand/video/upload/register

### 동영상 업로드 등록

동영상 파일을 업로드하기 위한 업로드 URL과 토큰을 발급받습니다.

- 발급된 `uploadUrl`과 `token`은 **5분 동안 유효**하며, 해당 시간 내에 카카오에 직접 multipart 파일 업로드를 수행해야 합니다.
- 발신프로필 그룹(`senderKeyType: G`)은 동영상 기능을 지원하지 않습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| senderKeyType | string | — | 발신 프로필 키 타입 — S=일반(default). G=그룹은 동영상 기능 미지원 (오류 응답)  = S | G |
| fileName | string(250) | 필수 | 업로드할 파일 이름 (확장자 포함, 최대 250자) |
| fileSize | integer <int64>(1~) | 필수 | 업로드할 파일 크기 (byte, 1 이상) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/brand/video/upload/register" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "fileName": "brand_video.mp4",
  "fileSize": 10485760
}'
```

응답

200등록 성공 — `data`는 업로드 등록 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 업로드 등록 결과 |
| └vid | string | — | 발급된 동영상 ID |
| └uploadUrl | string | — | 동영상 파일 업로드 URL (multipart 업로드 대상) |
| └token | string | — | 업로드 시 사용할 인증 토큰 |

응답 · 200

```
{
  "code": "200",
  "message": "string",
  "data": {
    "vid": "string",
    "uploadUrl": "string",
    "token": "string"
  }
}
```

post/{uploadUrl}

### 동영상 업로드 (카카오 직접 호출)

[동영상 업로드 등록](https://bizppurio.github.io/kapi#post-v4kakaobrandvideouploadregister) API 로 발급받은 `uploadUrl`과 `token`을 이용하여 **카카오에 동영상 파일을 직접 업로드**합니다.

- **비즈뿌리오(kapi)를 거치지 않는** 카카오 서버 직접 호출 — 요청 URL 은 발급받은 `uploadUrl` 그대로 사용합니다.
- 토큰 발급 후 **5분 내에** 호출해야 합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| file | string <binary> | 필수 | 업로드할 동영상 파일 (binary, multipart) |

```
curl -X POST "kakao://direct-upload/{uploadUrl}" \
  -H "Content-Type: application/json" \
  -d '{
  "file": "{binary}"
}'
```

응답

200업로드 결과 (카카오 응답)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| vid | string | — | 동영상 ID |
| playUrl | string | — | 동영상 재생 URL |
| duration | number | — | 동영상 길이 (초) |
| message | string | — | 에러 메시지 (성공 시 공란) |

응답 · 200

```
{
  "vid": "string",
  "playUrl": "string",
  "duration": 0,
  "message": "string"
}
```

## 통계

발송·템플릿 일별/월별 통합 통계 (4개 엔드포인트, `/v4/` 경로). 전날 데이터는 매일 오전 7시경 일배치 처리.

post/v4/kakao/stat/send/daily

### 발송 통합 일별 통계

발신프로필 키 기준 일별 발송 통계 (알림톡 / 브랜드메시지 통합).

- 실시간 통계는 제공되지 않으며, 전날 데이터는 매일 **오전 7시경** 일배치 처리 후 제공됩니다.
- 조회 가능한 기간은 최대 93일입니다.
- **요청 제한**(Rate Limit): IP당 **초당 1건 / 전역 초당 300건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분  = alimtalk | brandmessage |
| startDate | string | 필수 | 조회 시작일 (yyyyMMdd) |
| endDate | string | 필수 | 조회 종료일 (yyyyMMdd) |
| messageType | string | — | 알림톡 메시지 타입  = AT | AI |
| receiveUserType | string | — | 수신자 유형  = PhoneNumber | AppUserId | UserKey | None |
| messageSpec | string | — | 브랜드메시지 타입  = BASIC | FREESTYLE |
| chatBubbleType | string | — | 브랜드메시지 말풍선 타입  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| targeting | string | — | 타겟팅 여부 — 브랜드메시지 전용   - `M` 광고성 수신동의 회원 - `N` 수신동의 회원 ∖ 채널 친구 - `O` 수신동의 회원 ∩ 채널 친구 - `I` 고객사 발송 요청 대상 ∩ 채널 친구 - `F` 전체 채널 친구  = M | N | O | I | F |
| friendType | string | — | 브랜드메시지 친구 타입  = F | N |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/stat/send/daily" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "startDate": "20260601",
  "endDate": "20260607",
  "messageType": "AT"
}'
```

응답

200일별 발송 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | allOf | — | 알림톡 일별 발송 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageType | string | 필수 | 메시지 타입  = AT | AI |
| └receiveUserType | string | — | 수신자 유형 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └unknownRequestCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |
| └readCount | integer <int64> | — | 열람수 |
| └브랜드메시지 | allOf | — | 브랜드메시지 일별 발송 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageSpec | string | 필수 | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └receiveUserType | string | — | 수신자 유형 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |
| └readCount | integer <int64> | — | 열람수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └listClickCount | integer <int64> | — | 리스트 클릭수 |
| └thumbnailClickCount | integer <int64> | — | 썸네일 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "messageType": "AT",
        "receiveUserType": "PhoneNumber",
        "chargedSuccessCount": 1200,
        "freeContractSuccessCount": 30,
        "freeTemplateSuccessCount": 15,
        "unknownRequestCount": 2,
        "validFailCount": 18,
        "invalidFailCount": 5,
        "readCount": 980
      },
      {
        "date": "20260602",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "messageType": "AT",
        "receiveUserType": "PhoneNumber",
        "chargedSuccessCount": 1340,
        "freeContractSuccessCount": 28,
        "freeTemplateSuccessCount": 12,
        "unknownRequestCount": 1,
        "validFailCount": 22,
        "invalidFailCount": 4,
        "readCount": 1105
      }
    ]
  }
}
```

post/v4/kakao/stat/send/monthly

### 발송 통합 월별 통계

발신프로필 키 기준 월별 발송 통계.

- 조회 가능한 기간은 최대 12개월입니다.
- **요청 제한**(Rate Limit): IP당 **초당 1건 / 전역 초당 300건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| startMonth | string | 필수 | 조회 시작 월 (yyyyMM) |
| endMonth | string | 필수 | 조회 종료 월 (yyyyMM) |
| messageType | string | — | 메시지 타입 — 알림톡 전용 (AT: 알림톡 / AI: 알림톡 이미지)  = AT | AI |
| receiveUserType | string | — | 수신자 유형 (PhoneNumber / AppUserId / UserKey / None)  = PhoneNumber | AppUserId | UserKey | None |
| messageSpec | string | — | 메시지 타입 — 브랜드메시지 전용 (BASIC / FREESTYLE)  = BASIC | FREESTYLE |
| chatBubbleType | string | — | 말풍선 타입 — 브랜드메시지 전용 (TEXT / IMAGE / WIDE / WIDE\_ITEM\_LIST / CAROUSEL\_FEED / PREMIUM\_VIDEO / COMMERCE / CAROUSEL\_COMMERCE)  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| targeting | string | — | 타겟팅 여부 — 브랜드메시지 전용   - `M` 광고성 수신동의 회원 - `N` 수신동의 회원 ∖ 채널 친구 - `O` 수신동의 회원 ∩ 채널 친구 - `I` 고객사 발송 요청 대상 ∩ 채널 친구 - `F` 전체 채널 친구  = M | N | O | I | F |
| friendType | string | — | 친구 타입 — 브랜드메시지 전용 (F: 친구 / N: 비친구)  = F | N |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/stat/send/monthly" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "brandmessage",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "startMonth": "202601",
  "endMonth": "202603",
  "messageSpec": "BASIC"
}'
```

응답

200월별 발송 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | allOf | — | 알림톡 월별 발송 통계 행 |
| └statMonth | string | — | 월 (yyyyMM) |
| └senderKey | string | — | 발신프로필 키 |
| └messageType | string | 필수 | 메시지 타입  = AT | AI |
| └receiveUserType | string | — | 수신자 유형 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └unknownRequestCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |
| └readCount | integer <int64> | — | 열람수 |
| └브랜드메시지 | allOf | — | 브랜드메시지 월별 발송 통계 행 |
| └statMonth | string | — | 월 (yyyyMM) |
| └senderKey | string | — | 발신프로필 키 |
| └messageSpec | string | 필수 | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └receiveUserType | string | — | 수신자 유형 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |
| └readCount | integer <int64> | — | 열람수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └listClickCount | integer <int64> | — | 리스트 클릭수 |
| └thumbnailClickCount | integer <int64> | — | 썸네일 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "data": {
    "list": [
      {
        "statMonth": "202606",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "messageType": "AT",
        "receiveUserType": "PhoneNumber",
        "chargedSuccessCount": 32000,
        "freeContractSuccessCount": 820,
        "freeTemplateSuccessCount": 410,
        "unknownRequestCount": 40,
        "validFailCount": 500,
        "invalidFailCount": 120,
        "readCount": 26800
      },
      {
        "statMonth": "202606",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "messageType": "AI",
        "receiveUserType": "PhoneNumber",
        "chargedSuccessCount": 5400,
        "freeContractSuccessCount": 120,
        "freeTemplateSuccessCount": 60,
        "unknownRequestCount": 6,
        "validFailCount": 80,
        "invalidFailCount": 12,
        "readCount": 4400
      }
    ]
  }
}
```

post/v4/kakao/stat/template/daily

### 템플릿 통합 일별 통계

템플릿별 발송·열람·클릭 통계. 페이징 방식.

- 실시간 통계는 제공되지 않으며, 전날 데이터는 매일 **오전 7시경** 일배치 처리 후 제공됩니다.
- 조회 가능한 기간은 최대 93일입니다.
- **요청 제한**(Rate Limit): IP당 **초당 1건 / 전역 초당 300건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분  = alimtalk | brandmessage |
| startDate | string | 필수 | 조회 시작일 (yyyyMMdd) |
| endDate | string | 필수 | 조회 종료일 (yyyyMMdd) |
| messageType | string | — | 알림톡 메시지 타입  = AT | AI |
| receiveUserType | string | — | 수신자 유형  = PhoneNumber | AppUserId | UserKey | None |
| messageSpec | string | — | 브랜드메시지 타입  = BASIC | FREESTYLE |
| chatBubbleType | string | — | 브랜드메시지 말풍선 타입  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| targeting | string | — | 타겟팅 여부 — 브랜드메시지 전용   - `M` 광고성 수신동의 회원 - `N` 수신동의 회원 ∖ 채널 친구 - `O` 수신동의 회원 ∩ 채널 친구 - `I` 고객사 발송 요청 대상 ∩ 채널 친구 - `F` 전체 채널 친구  = M | N | O | I | F |
| friendType | string | — | 브랜드메시지 친구 타입  = F | N |
| page | integer | — | 페이지 번호 |
| count | integer | — | 페이지당 건수 |
| templateCode | string | — | 템플릿 코드 필터 |
| groupTagKey | string | — | 그룹태그 키 필터 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/stat/template/daily" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "startDate": "20260601",
  "endDate": "20260607",
  "messageType": "AT",
  "page": 1,
  "count": 10,
  "templateCode": "string",
  "groupTagKey": "string"
}'
```

응답

200템플릿 일별 통계 (페이징)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| totalCount | integer | 필수 | 전체 건수 |
| totalPage | integer | 필수 | 전체 페이지 수 |
| currentPage | integer | 필수 | 현재 페이지 |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | allOf | — | 알림톡 템플릿 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └templateCode | string | — | 템플릿 코드 |
| └messageType | string | 필수 | 메시지 타입  = AT | AI |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └unknownRequestCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └readCount | integer <int64> | — | 열람수 |
| └qrClickCount | integer <int64> | — | QR 클릭수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |
| └브랜드메시지 | allOf | — | 브랜드메시지 템플릿 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └templateCode | string | — | 템플릿 코드 |
| └messageSpec | string | 필수 | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └groupTagKey | string | — | 그룹태그 키 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └readCount | integer <int64> | — | 열람수 |
| └imageClickCount | integer <int64> | — | 이미지 클릭수 |
| └listClickCount | integer <int64> | — | 리스트 클릭수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "totalCount": 2,
  "currentPage": 1,
  "totalPage": 1,
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "templateCode": "TALK_0001",
        "messageType": "AT",
        "chargedSuccessCount": 800,
        "freeContractSuccessCount": 20,
        "freeTemplateSuccessCount": 10,
        "unknownRequestCount": 1,
        "validFailCount": 9,
        "readCount": 640,
        "qrClickCount": 30,
        "buttonClickCount": 120,
        "etcClickCount": 5
      },
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "templateCode": "TALK_0007",
        "messageType": "AI",
        "chargedSuccessCount": 420,
        "freeContractSuccessCount": 12,
        "freeTemplateSuccessCount": 6,
        "unknownRequestCount": 0,
        "validFailCount": 5,
        "readCount": 355,
        "qrClickCount": 18,
        "buttonClickCount": 74,
        "etcClickCount": 3
      }
    ]
  }
}
```

post/v4/kakao/stat/template/monthly

### 템플릿 통합 월별 통계

템플릿별 월별 통계.

- 조회 가능한 기간은 최대 3개월입니다.
- **요청 제한**(Rate Limit): IP당 **초당 1건 / 전역 초당 300건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| startMonth | string | 필수 | 조회 시작 월 (yyyyMM) |
| endMonth | string | 필수 | 조회 종료 월 (yyyyMM) |
| messageType | string | — | 메시지 타입 — 알림톡 전용 (AT: 알림톡 / AI: 알림톡 이미지)  = AT | AI |
| receiveUserType | string | — | 수신자 유형 (PhoneNumber / AppUserId / UserKey / None)  = PhoneNumber | AppUserId | UserKey | None |
| messageSpec | string | — | 메시지 타입 — 브랜드메시지 전용 (BASIC / FREESTYLE)  = BASIC | FREESTYLE |
| chatBubbleType | string | — | 말풍선 타입 — 브랜드메시지 전용 (TEXT / IMAGE / WIDE / WIDE\_ITEM\_LIST / CAROUSEL\_FEED / PREMIUM\_VIDEO / COMMERCE / CAROUSEL\_COMMERCE)  = TEXT | IMAGE | WIDE | WIDE\_ITEM\_LIST | CAROUSEL\_FEED | PREMIUM\_VIDEO | COMMERCE | CAROUSEL\_COMMERCE |
| targeting | string | — | 타겟팅 여부 — 브랜드메시지 전용   - `M` 광고성 수신동의 회원 - `N` 수신동의 회원 ∖ 채널 친구 - `O` 수신동의 회원 ∩ 채널 친구 - `I` 고객사 발송 요청 대상 ∩ 채널 친구 - `F` 전체 채널 친구  = M | N | O | I | F |
| friendType | string | — | 친구 타입 — 브랜드메시지 전용 (F: 친구 / N: 비친구)  = F | N |
| page | integer | — | 페이지 번호 (기본값: 1) |
| count | integer | — | 페이지당 건수 (기본값: 10) |
| templateCode | string | — | 템플릿 코드 |
| groupTagKey | string | — | 그룹태그 키 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/stat/template/monthly" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "brandmessage",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "startMonth": "202601",
  "endMonth": "202603",
  "messageSpec": "BASIC",
  "page": 1,
  "count": 10,
  "templateCode": "string",
  "groupTagKey": "string"
}'
```

응답

200템플릿 월별 통계 (페이징)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| totalCount | integer | 필수 | 전체 건수 |
| totalPage | integer | 필수 | 전체 페이지 수 |
| currentPage | integer | 필수 | 현재 페이지 |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | allOf | — | 알림톡 템플릿 월별 통계 행 |
| └statMonth | string | — | 월 (yyyyMM) |
| └senderKey | string | — | 발신프로필 키 |
| └templateCode | string | — | 템플릿 코드 |
| └messageType | string | 필수 | 메시지 타입  = AT | AI |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └unknownRequestCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └readCount | integer <int64> | — | 열람수 |
| └qrClickCount | integer <int64> | — | QR 클릭수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |
| └브랜드메시지 | allOf | — | 브랜드메시지 템플릿 월별 통계 행 |
| └statMonth | string | — | 월 (yyyyMM) |
| └senderKey | string | — | 발신프로필 키 |
| └templateCode | string | — | 템플릿 코드 |
| └messageSpec | string | 필수 | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └groupTagKey | string | — | 그룹태그 키 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └readCount | integer <int64> | — | 열람수 |
| └imageClickCount | integer <int64> | — | 이미지 클릭수 |
| └listClickCount | integer <int64> | — | 리스트 클릭수 |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "totalCount": 2,
  "currentPage": 1,
  "totalPage": 1,
  "data": {
    "list": [
      {
        "statMonth": "202606",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "templateCode": "TALK_0001",
        "messageType": "AT",
        "chargedSuccessCount": 21000,
        "freeContractSuccessCount": 520,
        "freeTemplateSuccessCount": 260,
        "unknownRequestCount": 22,
        "validFailCount": 240,
        "readCount": 17600,
        "qrClickCount": 820,
        "buttonClickCount": 3100,
        "etcClickCount": 140
      },
      {
        "statMonth": "202606",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "templateCode": "TALK_0007",
        "messageType": "AI",
        "chargedSuccessCount": 9800,
        "freeContractSuccessCount": 240,
        "freeTemplateSuccessCount": 120,
        "unknownRequestCount": 8,
        "validFailCount": 90,
        "readCount": 7900,
        "qrClickCount": 360,
        "buttonClickCount": 1400,
        "etcClickCount": 60
      }
    ]
  }
}
```

## 통계 · 카카오 직접 조회

발신프로필·템플릿 기준 발송·유효읽음·클릭 일별 통계를 카카오에서 직접 조회 (6개 엔드포인트, `/v4/kakao/kakaoStat/` 경로). DB 집계 통계와 달리 rate limit 필터를 우회하며, 조회 모드(`CHARGE_PENDING`/`FINAL`)를 함께 제공.

post/v4/kakao/kakaoStat/send

### 발송 일별 통계 (카카오 직접 조회)

발신프로필 키 기준 일별 발송수 통계를 카카오에서 직접 조회합니다 (페이징). 알림톡 / 브랜드메시지 통합.

- 실시간 통계는 제공되지 않으며, 전날 데이터는 매일 **오전 7시경** 일배치 처리 후 제공됩니다. (\*내부 상황에 따라 변경 가능)
- 알림톡은 ACK 타임아웃 반영으로 D+1에 최초 제공, D+2에 확정됩니다.
- 조회 모드(`mode`)는 D+2 확정 전 `CHARGE_PENDING`(과금 미확정), 이후 `FINAL`(과금 확정).
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/send" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "page": 1,
  "count": 500
}'
```

응답

200발송 일별 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (CHARGE\_PENDING: 과금 미확정 / FINAL: 확정)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | object | — | 알림톡 발송 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageType | string | — | 메시지 타입  = AT | AI |
| └receiveUserType | string | — | 수신자 유형 |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └unknownRequestCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |
| └브랜드메시지 | object | — | 브랜드메시지 발송 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └receiveUserType | string | — | 수신자 유형 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └invalidFailCount | integer <int64> | — | 발송불가 무효 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "messageType": "AT",
        "receiveUserType": "PhoneNumber",
        "chargedSuccessCount": 1200,
        "freeContractSuccessCount": 30,
        "freeTemplateSuccessCount": 15,
        "unknownRequestCount": 2,
        "validFailCount": 18,
        "invalidFailCount": 5
      }
    ]
  }
}
```

post/v4/kakao/kakaoStat/read

### 발송 유효 읽음 일별 통계 (카카오 직접 조회)

발신프로필 키 기준 일별 유효 읽음 통계를 카카오에서 직접 조회합니다 (페이징). 알림톡 / 브랜드메시지 통합.

- 유효 읽음 통계는 2024-04-01부터 제공되며, 같은 메시지에 대한 유효 읽음은 중복 집계되지 않습니다.
- `D`(당일)·`D+1`·`D+2` 경과일별로 집계 제공, `D+3` 이후는 미제공. 특정 조회일의 총 유효 읽음수는 `elapsedDay` 0~2 를 합산해야 합니다.
- 발송 성공이 10건 이하이면 유효 읽음 데이터는 제공되지 않습니다. 일별 통계 `mode`는 `FINAL`(확정)만 제공.
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |
| elapsedDay | integer(0~2) | — | 조회일 기준 경과일수 (0~2, 기본값 0). 총합은 0~2 합산 필요 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/read" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "page": 1,
  "count": 500,
  "elapsedDay": 0
}'
```

응답

200발송 유효 읽음 일별 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (일별은 FINAL 만 제공)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | object | — | 알림톡 유효 읽음 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageType | string | — | 메시지 타입  = AT | AI |
| └receiveUserType | string | — | 수신자 유형 |
| └readCount | integer <int64> | — | 열람수 |
| └브랜드메시지 | object | — | 브랜드메시지 유효 읽음 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └receiveUserType | string | — | 수신자 유형 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └readCount | integer <int64> | — | 열람수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "messageSpec": "BASIC",
        "chatBubbleType": "TEXT",
        "receiveUserType": "PhoneNumber",
        "targeting": "O",
        "friendType": "F",
        "readCount": 980
      }
    ]
  }
}
```

post/v4/kakao/kakaoStat/click

### 발송 클릭 일별 통계 (카카오 직접 조회)

발신프로필 키 기준 일별 클릭 통계를 카카오에서 직접 조회합니다 (페이징). **브랜드메시지 전용** — 알림톡은 미제공.

- 클릭 통계는 2024-04-01부터 제공되며, 같은 메시지의 클릭은 중복 집계됩니다.
- `D`(당일)·`D+1`·`D+2` 경과일별로 집계 제공, `D+3` 이후는 미제공. 특정 조회일의 총 클릭수는 `elapsedDay` 0~2 를 합산해야 합니다.
- 발송 성공이 10건 이하이면 클릭 데이터는 제공되지 않습니다. 일별 통계 `mode`는 `FINAL`(확정)만 제공.
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (brandmessage 전용  = brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| elapsedDay | integer(0~2) | — | 조회일 기준 경과일수 (0~2, 기본값 0). 총합은 0~2 합산 필요 |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/click" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "brandmessage",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "elapsedDay": 0
}'
```

응답

200발송 클릭 일별 통계 (브랜드메시지)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (일별은 FINAL 만 제공)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<object> | — | 통계 목록 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └receiveUserType | string | — | 수신자 유형 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └buttonClickCount | integer <int64> | — | 버튼 클릭수 |
| └listClickCount | integer <int64> | — | 리스트 클릭수 |
| └thumbnailClickCount | integer <int64> | — | 썸네일 클릭수 |
| └etcClickCount | integer <int64> | — | 그외 클릭수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "messageSpec": "FREESTYLE",
        "chatBubbleType": "WIDE",
        "receiveUserType": "PhoneNumber",
        "targeting": "M",
        "friendType": "F",
        "buttonClickCount": 120,
        "listClickCount": 40,
        "thumbnailClickCount": 15,
        "etcClickCount": 8
      }
    ]
  }
}
```

post/v4/kakao/kakaoStat/template/send

### 템플릿 발송 일별 통계 (카카오 직접 조회)

템플릿·그룹태그 기준 일별 발송수 통계를 카카오에서 직접 조회합니다 (페이징). 알림톡 / 브랜드메시지 통합.

- `templateCode` 와 `groupTagKey` 는 **둘 중 하나만** 선택합니다. 브랜드메시지 자유형은 그룹태그를 사용한 경우에만 제공됩니다.
- 알림톡은 ACK 타임아웃 반영으로 D+1에 최초 제공, D+2에 확정됩니다. `mode`는 D+2 확정 전 `CHARGE_PENDING`, 이후 `FINAL`.
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |
| templateCode | string | — | 템플릿 코드 (groupTagKey와 둘 중 하나만 선택) |
| groupTagKey | string | — | 그룹 태그 키 (templateCode와 둘 중 하나만 선택) |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/template/send" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "page": 1,
  "count": 500,
  "templateCode": "string",
  "groupTagKey": "string"
}'
```

응답

200템플릿 발송 일별 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (CHARGE\_PENDING: 과금 미확정 / FINAL: 확정)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | object | — | 알림톡 템플릿 발송 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └messageType | string | — | 메시지 타입  = AT | AI |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeTemplateSuccessCount | integer <int64> | — | 성공 비과금 (템플릿) |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └unknownCount | integer <int64> | — | 성공 불확실 비과금 |
| └validFailCount | integer <int64> | — | 발송불가 유효 |
| └브랜드메시지 | object | — | 브랜드메시지 템플릿 발송 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └groupTagKey | string | — | 그룹 태그 키 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └chargedSuccessCount | integer <int64> | — | 성공 과금 |
| └freeContractSuccessCount | integer <int64> | — | 성공 비과금 (계약) |
| └validFailCount | integer <int64> | — | 발송불가 유효 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "templateCode": "TALK_0001",
        "messageType": "AT",
        "chargedSuccessCount": 800,
        "freeTemplateSuccessCount": 10,
        "freeContractSuccessCount": 20,
        "unknownCount": 1,
        "validFailCount": 9
      }
    ]
  }
}
```

post/v4/kakao/kakaoStat/template/read

### 템플릿 유효 읽음 일별 통계 (카카오 직접 조회)

템플릿·그룹태그 기준 일별 유효 읽음 통계를 카카오에서 직접 조회합니다 (페이징). 알림톡 / 브랜드메시지 통합.

- `templateCode` 와 `groupTagKey` 는 **둘 중 하나만** 선택합니다. 브랜드메시지 자유형은 그룹태그를 사용한 경우에만 제공됩니다.
- 유효 읽음 통계는 2024-04-01부터 제공, 중복 집계되지 않습니다. 총 유효 읽음수는 `elapsedDay` 0~2 를 합산해야 합니다.
- 발송 성공이 10건 이하이면 미제공. 일별 통계 `mode`는 `FINAL`(확정)만 제공.
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |
| templateCode | string | — | 템플릿 코드 (groupTagKey와 둘 중 하나만 선택) |
| groupTagKey | string | — | 그룹 태그 키 (templateCode와 둘 중 하나만 선택) |
| elapsedDay | integer(0~2) | — | 조회일 기준 경과일수 (0~2, 기본값 0). 총합은 0~2 합산 필요 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/template/read" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "page": 1,
  "count": 500,
  "templateCode": "string",
  "groupTagKey": "string",
  "elapsedDay": 0
}'
```

응답

200템플릿 유효 읽음 일별 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (일별은 FINAL 만 제공)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | object | — | 알림톡 템플릿 유효 읽음 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └messageType | string | — | 메시지 타입  = AT | AI |
| └readCount | integer <int64> | — | 열람수 |
| └브랜드메시지 | object | — | 브랜드메시지 템플릿 유효 읽음 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └groupTagKey | string | — | 그룹 태그 키 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └readCount | integer <int64> | — | 열람수 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "templateCode": "TALK_0001",
        "messageType": "AT",
        "readCount": 640
      }
    ]
  }
}
```

post/v4/kakao/kakaoStat/template/click

### 템플릿 클릭 일별 통계 (카카오 직접 조회)

템플릿·그룹태그 기준 일별 클릭 통계를 카카오에서 직접 조회합니다 (페이징). 알림톡 / 브랜드메시지 통합. 응답의 `clickInfo` 에 클릭 상세가 담깁니다.

- `templateCode` 와 `groupTagKey` 는 **둘 중 하나만** 선택합니다. 브랜드메시지 자유형은 그룹태그를 사용한 경우에만 제공됩니다.
- 클릭 통계는 2024-04-01부터 제공, 중복 집계됩니다. 총 클릭수는 `elapsedDay` 0~2 를 합산해야 합니다.
- 발송 성공이 10건 이하이면 미제공. 일별 통계 `mode`는 `FINAL`(확정)만 제공.
- **요청 제한**(Rate Limit): 엔드포인트별 · IP당 **초당 20건 / 분당 1000건**. 초과 시 결과 코드 `429`(요청 횟수 초과)를 반환합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오ID |
| apiKey | string | 필수 | API Key |
| senderKey | string | 필수 | 발신 프로필 키 (`senderKeyType`이 `G`인 경우 그룹 키) |
| product | string | 필수 | 상품 구분 (alimtalk / brandmessage)  = alimtalk | brandmessage |
| date | string | 필수 | 조회일 (yyyyMMdd) |
| page | integer | — | 조회 페이지 번호 (기본값 1) |
| count | integer | — | 한 페이지당 크기 (최대 10,000, 기본값 500) |
| templateCode | string | — | 템플릿 코드 (groupTagKey와 둘 중 하나만 선택) |
| groupTagKey | string | — | 그룹 태그 키 (templateCode와 둘 중 하나만 선택) |
| elapsedDay | integer(0~2) | — | 조회일 기준 경과일수 (0~2, 기본값 0). 총합은 0~2 합산 필요 |

```
curl -X POST "https://kapi.ppurio.com/v4/kakao/kakaoStat/template/click" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "xxxxxxxxx",
  "product": "alimtalk",
  "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
  "date": "20260601",
  "page": 1,
  "count": 500,
  "templateCode": "string",
  "groupTagKey": "string",
  "elapsedDay": 0
}'
```

응답

200템플릿 클릭 일별 통계

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 ([KAPI 공통](https://bizppurio.github.io/kapi#공통-사항) 참고) |
| message | string | — | 실패 시 결과 메시지 |
| mode | string | — | 조회 모드 (일별은 FINAL 만 제공)  = CHARGE\_PENDING | FINAL |
| data | object | — | 통계 데이터 |
| └list | array<oneOf> | — | 통계 목록 |
| └알림톡 | object | — | 알림톡 템플릿 클릭 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └messageType | string | — | 메시지 타입  = AT | AI |
| └clickInfo | object | — | 알림톡 템플릿 클릭 정보 |
| └buttonOrders | array<integer> | — | 알림톡 템플릿에 등록된 순서별 클릭수 (number[5]) |
| └buttonType | object | — | 버튼 링크 타입별 클릭수 — WL:웹링크, AL:앱링크, DS:배송조회, BK:버튼텍스트 발송, MD:버튼텍스트+본문 발송, BT:봇전환, BC:상담톡전환, AC:채널추가, P1/P2/P3:플러그인, BF:비즈니스폼, TN:전화앱실행, MP:지도보기 |
| └qrType | object | — | 바로연결 링크 타입별 클릭수 — WL:웹링크, AL:앱링크, DS:배송조회, BK:상담톡전환, MD:봇전환, BT:비즈니스폼 |
| └etc | integer | — | 그외 클릭수 (스킴 |
| └브랜드메시지 | object | — | 브랜드메시지 템플릿 클릭 일별 통계 행 |
| └date | string | — | 날짜 (yyyyMMdd) |
| └senderKey | string | — | 발신프로필 키 |
| └uuid | string | — | 카카오톡 채널 |
| └templateCode | string | — | 템플릿 코드 |
| └groupTagKey | string | — | 그룹 태그 키 |
| └messageSpec | string | — | 메시지 타입  = BASIC | FREESTYLE |
| └chatBubbleType | string | — | 말풍선 타입 |
| └targeting | string | — | 타겟팅 여부 (M / N / O / I / F)  = M | N | O | I | F |
| └friendType | string | — | 친구 타입  = F | N |
| └clickInfo | object | — | 브랜드메시지 클릭 정보 |
| └buttonOrders | array<integer> | — | 버튼 순서별 클릭수 (number[30]). 캐러셀은 1카드당 최대 3개(버튼2+쿠폰); buttonOrders[0]=첫째 카드 첫 버튼, buttonOrders[3]=둘째 카드 첫 버튼 |
| └imageOrders | array<integer> | — | 이미지 순서별 클릭수 (number[10]) |
| └listOrders | array<integer> | — | 리스트 순서별 클릭수 (number[5]) |
| └etc | integer | — | 그외 클릭수 (스킴 |

응답 · 200

```
{
  "code": "200",
  "message": "정상적으로 처리되었습니다.",
  "mode": "FINAL",
  "data": {
    "list": [
      {
        "date": "20260601",
        "senderKey": "05aa099bcbc5220a8c0b2XXXXXXXXXXXXX",
        "uuid": "@bizppurio",
        "templateCode": "BRAND_0007",
        "groupTagKey": "EVENT_0601",
        "messageSpec": "FREESTYLE",
        "chatBubbleType": "WIDE_ITEM_LIST",
        "targeting": "M",
        "friendType": "F",
        "clickInfo": {
          "buttonOrders": [
            12,
            3
          ],
          "imageOrders": [
            8
          ],
          "listOrders": [
            5,
            2,
            1
          ],
          "etc": 4
        }
      }
    ]
  }
}
```

## 가이드

알림톡 템플릿 등록·운영에 사용하는 타입·상태·버튼 코드 정의입니다.

## 템플릿 메시지 유형

`templateMessageType` 필드 값입니다.

| 코드 | 설명 | 비고 |
| --- | --- | --- |
| `BA` | 기본형 | — |
| `EX` | 부가정보형 | `templateExtra` 필수 |
| `AD` | 채널추가형 | `buttons` 자동 삽입 (`AC`) |
| `MI` | 복합형 | `templateExtra` 필수, `buttons` 자동 삽입 (`AC`) |

## 템플릿 강조 유형

`templateEmphasizeType` 필드 값입니다.

| 코드 | 설명 | 필수 추가 필드 |
| --- | --- | --- |
| `NONE` | 선택 안 함 | — |
| `TEXT` | 강조 표기형 | `templateTitle`, `templateSubtitle` |
| `IMAGE` | 이미지형 | `templateImageName`, `templateImageUrl` |
| `ITEM_LIST` | 아이템 리스트형 | `templateItem.list` |

## 발신 프로필 키 타입

`senderKeyType` 필드 값입니다.

- `S` — 일반 발신프로필 (기본값)
- `G` — 발신프로필 그룹

## 템플릿 상태 변화

- `serviceStatus`: `REG` → `REQ` → `REJ` | `STP` | `RDY` → `ACT` → `DMT`/`BLK`
- `status`: `S` (중지) / `A` (정상) / `R` (대기)
- `inspectionStatus`: `REG` → `REQ` → `REJ` | `APR` (승인)


---

# RCS 관리 API (RAPI)

> 원본: [https://bizppurio.github.io/rapi/](https://bizppurio.github.io/rapi/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

RAPIv1.2

# 비즈뿌리오 RAPI

## 공통 사항

RCS 자원 관리 API (RAPI) — RCS 메시지 발송에 필요한 브랜드·챗봇·파일·메시지베이스를 등록·조회·수정·삭제하는 관리 API.

> **NOTE:** RAPI 는 RCS 메시지를 발송하지 않습니다. 발송은 [메시지 API](https://bizppurio.github.io/bizapi#메시지-전송) 또는 [BIZCLIENT](https://bizppurio.github.io/bizclient#메시지-전송) 를 사용합니다.

### 연동 규격

| 항목 | 값 |
| --- | --- |
| 프로토콜 | HTTPS |
| 도메인 | `https://rapi.bizppurio.com/` |
| 메서드 | POST 전용 |
| 인코딩 | UTF-8 |
| Content-Type | `application/json; charset=utf-8` |
| 인증 | Bearer 토큰 (`Authorization: Bearer {accessToken}`) |

### 인증 흐름

고객사비즈뿌리오 서버([rapi.bizppurio.com](http://rapi.bizppurio.com))① POST /rcsapi/v1/token — bizId + apiKey② accessToken(1시간) + refreshToken + expiresIn③ 후속 호출 — Authorization: Bearer {accessToken}④ 만료 직전 POST /rcsapi/v1/refresh\_token — 재발급만료 직전 refreshToken 으로 access 재발급 (재로그인 불필요)

자세한 토큰 발급은 [토큰 API](https://bizppurio.github.io/rapi#토큰) 를 참고하세요.

### 공통 응답 형식

```
{ "code": "0000", "message": "Success", "data": { ... } }
```

| 필드 | 설명 |
| --- | --- |
| `code` | 결과 코드 (`0000` = 성공) |
| `message` | 결과 메시지 |
| `data` | 성공 시 응답 본문 (엔드포인트별 상이) |

> **NOTE:** RAPI 결과 코드는 RCS 메시지 발송 결과 코드 (`8000` / `8200` / `8800` 등) 와 별개입니다. 발송 결과는 [전송 결과 코드](https://bizppurio.github.io/response-codes)를 참고하세요.

### Rate Limit

- 본 API 자체에 별도 명시된 Rate Limit 은 없으나, 비정상 다회 호출 시 일시 차단될 수 있습니다.
- 토큰 발급 API 는 IP 단위 별도 제한이 있을 수 있으니 토큰 캐싱 권장.

### 자원 ↔ 발송 연결

RAPI 로 등록·관리하는 자원은 다음과 같이 [메시지 API](https://bizppurio.github.io/bizapi#메시지-전송) / [BIZCLIENT](https://bizppurio.github.io/bizclient#메시지-전송) 에서 사용됩니다.

| RAPI 에서 등록·관리 | RCS 발송에서 사용 |
| --- | --- |
| 챗봇 ID (`chatbotId`) | API `content.rcs.chatbotid` / BIZCLIENT `BIZ_RCS.CHATBOT_ID` |
| 브랜드 키 (`brandKey`) | API `content.rcs.brandkey` / BIZCLIENT `BIZ_RCS.BRAND_KEY` |
| 메시지베이스 ID (`messagebaseId`) | API `content.rcs.messagebaseid` / BIZCLIENT `BIZ_RCS.MESSAGEBASE_ID` |
| 등록된 이미지 (`fileId`) | API `content.rcs.message.media` (`maapfile://{fileId}` 형식) |
| 대행사 키 (`agencyKey`) | API `content.rcs.agencykey` / BIZCLIENT `BIZ_RCS.AGENCY_KEY` |

### 자원 의존 관계

브랜드챗봇chatbotId파일fileId메시지베이스messagebaseIdRCS 발송 시content.rcs.chatbotidRCS 발송 시media = "maapfile://{fileId}"RCS 발송 시content.rcs.messagebaseid

## 토큰

RAPI 는 `bizId` + `apiKey` 로 인증 토큰을 발급받은 뒤 후속 호출에 사용합니다.

- 도메인: `https://rapi.bizppurio.com/`
- 인증 토큰 발급 후 `Authorization: Bearer {accessToken}` 헤더 첨부
- 만료 직전에 `refreshToken` 으로 재발급하도록 운영하세요.

post/rcsapi/v1/token

### 인증 토큰 생성

`bizId` + `apiKey` 를 본문에 담아 호출하여 RAPI 인증 토큰을 발급받습니다.

- `accessToken` 은 후속 RAPI 호출 시 `Authorization: Bearer {accessToken}` 형식으로 첨부합니다.
- `refreshToken` 은 [토큰 재발급](https://bizppurio.github.io/rapi#post-rcsapiv1refreshtoken) 호출에 사용합니다.
- 만료 직전에 재발급하도록 운영하세요. 만료된 토큰은 후속 호출에서 인증 오류가 발생합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오 사용자 ID |
| apiKey | string | 필수 | 발급받은 API Key |

```
curl -X POST "https://rapi.bizppurio.com/rcsapi/v1/token" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "[발급 받은 API Key]"
}'
```

응답

200토큰 발급 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └accessToken | string | 필수 | 인증 토큰 |
| └expiresIn | integer | 필수 | 만료 시간 (unixtime) |
| └refreshToken | string | 필수 | 리프레시 토큰 |

응답 · 200

```
{
  "code": "200",
  "data": {
    "accessToken": "[인증 토큰]",
    "expiresIn": 1568694883,
    "refreshToken": "[리프레시 토큰]"
  }
}
```

post/rcsapi/v1/refresh\_token

### 인증 토큰 재생성

만료되거나 만료 임박한 `accessToken` 을 `refreshToken` 으로 재발급합니다.  
응답 구조는 [토큰 생성](https://bizppurio.github.io/rapi#post-rcsapiv1token) 과 동일하며, 새로운 `accessToken` / `expiresIn` / `refreshToken` 이 발급됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| accessToken | string | 필수 | 만료되거나 만료 임박한 인증 토큰 |
| refreshToken | string | 필수 | 인증 토큰 생성 시 받은 리프레시 토큰 |

```
curl -X POST "https://rapi.bizppurio.com/rcsapi/v1/refresh_token" \
  -H "Content-Type: application/json" \
  -d '{
  "accessToken": "string",
  "refreshToken": "string"
}'
```

응답

200재발급 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └accessToken | string | 필수 | 인증 토큰 |
| └expiresIn | integer | 필수 | 만료 시간 (unixtime) |
| └refreshToken | string | 필수 | 리프레시 토큰 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "accessToken": "string",
    "expiresIn": 0,
    "refreshToken": "string"
  }
}
```

## 브랜드

이미 BizCenter(RBC) 에 등록된 브랜드를 비즈뿌리오 파트너에 연동하고 상세 정보를 조회합니다.

브랜드 자체 등록·수정·삭제 승인은 [기업 전용 브랜드 관리](https://bizppurio.github.io/rapi#기업-전용-브랜드) 에서 처리됩니다.

post/rcsapi/v1/brand/link

### 브랜드 연동

BizCenter(RBC) 에 등록된 브랜드를 현재 파트너에 연동합니다.

브랜드 자체 등록·수정·삭제 승인은 [기업 전용 브랜드 관리](https://bizppurio.github.io/rapi#기업-전용-브랜드) 를 참고하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | BizCenter 에서 생성한 브랜드 ID |
| brandKey | string | 필수 | BizCenter 에서 기업 관리자가 발급하여 대행사에 전달한 브랜드 Key |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/link" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "brandKey": "string"
}'
```

응답

200연동 성공 — `data` 는 [브랜드 상세](#operation/rapiGetBrand) 와 동일한 객체

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 브랜드 상세 |
| └brandId | string | — | 브랜드 ID |
| └brandKey | string | — | 브랜드 Key |
| └partnerId | string | — | 파트너 아이디 |
| └name | string | — | 브랜드 명 |
| └status | string | — | 브랜드 상태 |
| └registerDate | string | — | 브랜드 등록일시 |
| └updateDate | string | — | 브랜드 최종 수정일시 |
| └linkDate | string | — | 파트너 연동일시 |
| └chatbotDate | string | — | 챗봇 최종 수정일시 |
| └messagebaseDate | string | — | 템플릿 최종 수정일시 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string",
    "brandKey": "string",
    "partnerId": "string",
    "name": "string",
    "status": "string",
    "registerDate": "string",
    "updateDate": "string",
    "linkDate": "string",
    "chatbotDate": "string",
    "messagebaseDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/brand

### 브랜드 연동 상세 조회

해당 파트너와 연동된 브랜드를 조회합니다.

> RAPI 는 대부분 POST 이지만 이 엔드포인트는 **GET** 입니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200브랜드 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 브랜드 상세 |
| └brandId | string | — | 브랜드 ID |
| └brandKey | string | — | 브랜드 Key |
| └partnerId | string | — | 파트너 아이디 |
| └name | string | — | 브랜드 명 |
| └status | string | — | 브랜드 상태 |
| └registerDate | string | — | 브랜드 등록일시 |
| └updateDate | string | — | 브랜드 최종 수정일시 |
| └linkDate | string | — | 파트너 연동일시 |
| └chatbotDate | string | — | 챗봇 최종 수정일시 |
| └messagebaseDate | string | — | 템플릿 최종 수정일시 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string",
    "brandKey": "string",
    "partnerId": "string",
    "name": "string",
    "status": "string",
    "registerDate": "string",
    "updateDate": "string",
    "linkDate": "string",
    "chatbotDate": "string",
    "messagebaseDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

## 챗봇

브랜드 산하의 챗봇(`chatbotId`) CRUD + 목록·상세 조회 (5개 엔드포인트).

RCS 메시지 발송 시 챗봇 ID 는 `chatbotid` 필드로 사용됩니다.
A2P 챗봇의 경우 `chatbotId` 는 발신번호와 동일하며, CHAT 서비스의 경우 별도 ID 가 발급됩니다.

post/rcsapi/v1/brand/chatbot/create

### 챗봇 등록

브랜드 내에서 사용할 챗봇을 등록합니다. **등록된 챗봇은 브랜드 포털 관리자의 승인 후 사용 가능**합니다.

> A2P 챗봇의 경우 `chatbotId` 는 발신번호와 동일합니다. CHAT 서비스의 경우 별도 ID 가 발급됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | BizCenter 에서 생성한 브랜드 ID |
| mdn | string | 필수 | 등록할 발신번호 (국가번호 생략) |
| rcsReply | string | 필수 | 수신모드 설정 — SMS MO:0 / RCS Postback:1  = 0 | 1 |
| subTitle | string | 필수 | 챗봇 명 |
| subNumCertificate | string <binary> | 필수 | 통신 서비스 이용 증명원 파일 |
| display | string | — | 전시 여부 |
| service | string | — | A2P/CHAT 서비스 유형 |
| subNum | string | — | 챗봇 ID (A2P 의 경우 발신번호와 동일) |
| webhook | string | — | service 가 chat 인 경우 필수 (URL) |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/chatbot/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "mdn": "string",
  "rcsReply": "0",
  "subTitle": "string",
  "subNumCertificate": "{binary}",
  "display": "string",
  "service": "string",
  "subNum": "string",
  "webhook": "string"
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 챗봇 요약 (목록용) |
| └chatbotId | string | — | 챗봇 ID |
| └brandId | string | — | — |
| └subNum | string | — | 회신번호 |
| └subTitle | string | — | 챗봇 명 |
| └display | string | — | 전시 상태 |
| └isMainNum | boolean | — | 대표번호 여부 |
| └groupId | string | — | 그룹 ID |
| └approvalResult | string | — | 승인 상태 |
| └approvalDate | string | — | 챗봇 승인 일시 |
| └registerDate | string | — | 챗봇 등록 일시 |
| └updateDate | string | — | 챗봇 수정일시 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "chatbotId": "string",
    "brandId": "string",
    "subNum": "string",
    "subTitle": "string",
    "display": "string",
    "isMainNum": true,
    "groupId": "string",
    "approvalResult": "string",
    "approvalDate": "string",
    "registerDate": "string",
    "updateDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/brand/chatbot/update

### 챗봇 수정

챗봇을 수정합니다. **승인 상태에 따라 처리 여부가 결정**됩니다 (저장 / 반려 / 승인 상태).

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | BizCenter 에서 생성한 브랜드 ID |
| mdn | string | 필수 | 등록할 발신번호 (국가번호 생략) |
| rcsReply | string | 필수 | 수신모드 설정 — SMS MO:0 / RCS Postback:1  = 0 | 1 |
| subTitle | string | 필수 | 챗봇 명 |
| subNumCertificate | string <binary> | 필수 | 통신 서비스 이용 증명원 파일 |
| display | string | — | 전시 여부 |
| service | string | — | A2P/CHAT 서비스 유형 |
| subNum | string | — | 챗봇 ID (A2P 의 경우 발신번호와 동일) |
| webhook | string | — | service 가 chat 인 경우 필수 (URL) |
| chatbotId | string | 필수 | 수정 대상 챗봇 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/chatbot/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "mdn": "string",
  "rcsReply": "0",
  "subTitle": "string",
  "subNumCertificate": "{binary}",
  "display": "string",
  "service": "string",
  "subNum": "string",
  "webhook": "string",
  "chatbotId": "string"
}'
```

응답

200수정 성공 — `data` 는 풀 필드 셋 (`approvalReason` / `botTcPage` / `mediaUrl` / `registerId` / `searchWeight` / `service` / `webhook` 포함)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └chatbotId | string | — | 챗봇 ID |
| └brandId | string | — | — |
| └subNum | string | — | 회신번호 |
| └subTitle | string | — | 챗봇 명 |
| └display | string | — | 전시 상태 |
| └isMainNum | boolean | — | 대표번호 여부 |
| └groupId | string | — | 그룹 ID |
| └approvalResult | string | — | 승인 상태 |
| └approvalDate | string | — | 챗봇 승인 일시 |
| └registerDate | string | — | 챗봇 등록 일시 |
| └updateDate | string | — | 챗봇 수정일시 |
| └approvalReason | string | — | 승인 사유 |
| └botTcPage | string | — | 이용 약관 페이지 URL (수정 불가) |
| └mediaUrl | array<object> | — | 이미지 파일 ID / URL / 사용 유형 배열 |
| └registerId | string | — | 챗봇 등록 계정 ID |
| └updateId | string | — | 챗봇 수정 계정 ID |
| └searchWeight | string | — | 검색 우선 순위 (수정 불가) |
| └service | string | — | A2P/CHAT 서비스 유형 |
| └webhook | string | — | CHAT 서비스 시 webhook URL |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "chatbotId": "string",
    "brandId": "string",
    "subNum": "string",
    "subTitle": "string",
    "display": "string",
    "isMainNum": true,
    "groupId": "string",
    "approvalResult": "string",
    "approvalDate": "string",
    "registerDate": "string",
    "updateDate": "string",
    "approvalReason": "string",
    "botTcPage": "string",
    "mediaUrl": [
      {}
    ],
    "registerId": "string",
    "updateId": "string",
    "searchWeight": "string",
    "service": "string",
    "webhook": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/brand/chatbot/remove

### 챗봇 삭제

지정한 챗봇을 삭제합니다.

> ⚠️ **한번 삭제된 챗봇은 복원이 불가능**하며, 동일한 ID 로 다시 생성할 수 없습니다. 삭제는 승인 상태에 따라 처리 여부가 결정됩니다 (저장 / 반려 / 승인).

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | BizCenter 에서 생성한 브랜드 ID |
| chatbotId | string | 필수 | 삭제할 챗봇 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/chatbot/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "chatbotId": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └chatbotId | string | — | 삭제된 챗봇 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "chatbotId": "string"
  }
}
```

get/rcsapi/v1/brand/chatbot/list

### 챗봇 목록 조회

브랜드에 등록된 챗봇 목록을 페이지네이션으로 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand/chatbot/list" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200챗봇 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └chatbotId | string | — | 챗봇 ID |
| └brandId | string | — | — |
| └subNum | string | — | 회신번호 |
| └subTitle | string | — | 챗봇 명 |
| └display | string | — | 전시 상태 |
| └isMainNum | boolean | — | 대표번호 여부 |
| └groupId | string | — | 그룹 ID |
| └approvalResult | string | — | 승인 상태 |
| └approvalDate | string | — | 챗봇 승인 일시 |
| └registerDate | string | — | 챗봇 등록 일시 |
| └updateDate | string | — | 챗봇 수정일시 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "chatbotId": "string",
      "brandId": "string",
      "subNum": "string",
      "subTitle": "string",
      "display": "string",
      "isMainNum": true,
      "groupId": "string",
      "approvalResult": "string",
      "approvalDate": "string",
      "registerDate": "string",
      "updateDate": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/brand/chatbot/info

### 챗봇 상세 조회

지정 챗봇의 풀 필드 셋을 조회합니다. 응답 구조는 [챗봇 수정](https://bizppurio.github.io/rapi#post-rcsapiv1brandchatbotupdate) 응답과 동일합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand/chatbot/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200챗봇 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └chatbotId | string | — | 챗봇 ID |
| └brandId | string | — | — |
| └subNum | string | — | 회신번호 |
| └subTitle | string | — | 챗봇 명 |
| └display | string | — | 전시 상태 |
| └isMainNum | boolean | — | 대표번호 여부 |
| └groupId | string | — | 그룹 ID |
| └approvalResult | string | — | 승인 상태 |
| └approvalDate | string | — | 챗봇 승인 일시 |
| └registerDate | string | — | 챗봇 등록 일시 |
| └updateDate | string | — | 챗봇 수정일시 |
| └approvalReason | string | — | 승인 사유 |
| └botTcPage | string | — | 이용 약관 페이지 URL (수정 불가) |
| └mediaUrl | array<object> | — | 이미지 파일 ID / URL / 사용 유형 배열 |
| └registerId | string | — | 챗봇 등록 계정 ID |
| └updateId | string | — | 챗봇 수정 계정 ID |
| └searchWeight | string | — | 검색 우선 순위 (수정 불가) |
| └service | string | — | A2P/CHAT 서비스 유형 |
| └webhook | string | — | CHAT 서비스 시 webhook URL |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "chatbotId": "string",
    "brandId": "string",
    "subNum": "string",
    "subTitle": "string",
    "display": "string",
    "isMainNum": true,
    "groupId": "string",
    "approvalResult": "string",
    "approvalDate": "string",
    "registerDate": "string",
    "updateDate": "string",
    "approvalReason": "string",
    "botTcPage": "string",
    "mediaUrl": [
      {}
    ],
    "registerId": "string",
    "updateId": "string",
    "searchWeight": "string",
    "service": "string",
    "webhook": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

## 파일

RCS 메시지에서 사용할 Maap 미디어 파일을 등록·조회합니다 (2개 엔드포인트).

등록된 파일은 `fileId` 로 식별되며, RCS 발송 시 `maapfile://{fileId}` 형식으로 참조합니다.
이미지는 등록일로부터 **365일** 발송 가능합니다.

post/rcsapi/v1/file/send

### 파일 등록

RCS 메시지에서 사용할 Maap 미디어 파일을 등록합니다.

등록된 파일은 `fileId` 로 식별되며, RCS 발송 시 `maapfile://{fileId}` 형식으로 참조합니다.

> 이미지는 등록일로부터 **365일** 발송 가능합니다 (이후 자동 삭제). 운영 시 파일 ID 만료 관리가 필요합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| file | string <binary> | 필수 | 업로드 파일 |
| fileId | string | 필수 | 파일 ID |
| mimeType | string | 필수 | Mime type (image/jpeg, image/png 등) |
| usageType | string | 필수 | 사용 타입 — chatbot / messagebase / send  = chatbot | messagebase | send |

```
curl -X POST "{baseUrl}/rcsapi/v1/file/send" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "file": "{binary}",
  "fileId": "string",
  "mimeType": "string",
  "usageType": "chatbot"
}'
```

응답

200등록 성공 — `data` 는 [파일 상세](#operation/rapiGetFile) 와 동일

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 파일 상세 |
| └fileId | string | — | — |
| └mimeType | string | — | — |
| └usageType | string | — | = chatbot | messagebase | send |
| └status | string | — | 파일 상태  = ready | expired |
| └expiryDate | string | — | 유효 기간 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "fileId": "string",
    "mimeType": "string",
    "usageType": "chatbot",
    "status": "ready",
    "expiryDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/file/info

### 파일 상세 조회

등록된 Maap 미디어 파일의 상세 정보를 통신사 별로 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/file/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200파일 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 파일 상세 |
| └fileId | string | — | — |
| └mimeType | string | — | — |
| └usageType | string | — | = chatbot | messagebase | send |
| └status | string | — | 파일 상태  = ready | expired |
| └expiryDate | string | — | 유효 기간 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "fileId": "string",
    "mimeType": "string",
    "usageType": "chatbot",
    "status": "ready",
    "expiryDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

## 메시지베이스

메시지베이스(`messagebaseId`) CRUD + 양식·이통사 기본 제공 조회 (9개 엔드포인트).

메시지베이스는 RCS 메시지의 포맷·카드 수·버튼 수를 결정하는 식별자입니다.

post/rcsapi/v1/brand/msgbase/create

### 메시지베이스 등록

브랜드 내에서 사용할 메시지베이스(템플릿) 를 등록합니다. **등록된 메시지베이스는 브랜드 포털 관리자의 승인 후 사용 가능**합니다.

> `formattedString` 은 [메시지베이스 양식 목록](https://bizppurio.github.io/rapi#get-rcsapiv1msgbaseformlist) 또는 [메시지베이스 유형 상세](https://bizppurio.github.io/rapi#get-rcsapiv1msgbaseforminfo) 에서 조회한 양식의 `formattedString` 을 사용해야 합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | 브랜드 ID |
| custTmpltId | string | 필수 | 사용자 지정 템플릿 ID (정규식 ^[0-9A-Za-z]{1,25}$) |
| messagebaseformId | string | 필수 | 메시지베이스 폼 ID (정규식 ^[0-9A-Za-z]{1,10}$) |
| formattedString | object | 필수 | 메시지베이스 포맷 (양식 조회 결과 사용) |
| tmpltName | string | 필수 | 메시지베이스 명 |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "custTmpltId": "string",
  "messagebaseformId": "string",
  "formattedString": {},
  "tmpltName": "string"
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └messagebaseId | string | — | 메시지베이스 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string"
  }
}
```

post/rcsapi/v1/brand/msgbase/update

### 메시지베이스 수정

지정한 메시지베이스를 수정합니다.

> ⚠️ **승인 상태에 따라 처리 여부가 결정**됩니다 (저장 / 반려 상태). 템플릿 수정 시 기존에 사용된 `messagebaseformId` 는 **동일한 cardType**(`cell`, `description`) 에 한해 변경 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | 브랜드 ID |
| custTmpltId | string | 필수 | 사용자 지정 템플릿 ID (정규식 ^[0-9A-Za-z]{1,25}$) |
| messagebaseformId | string | 필수 | 메시지베이스 폼 ID (정규식 ^[0-9A-Za-z]{1,10}$) |
| formattedString | object | 필수 | 메시지베이스 포맷 (양식 조회 결과 사용) |
| tmpltName | string | 필수 | 메시지베이스 명 |
| messagebaseId | string | 필수 | 수정 대상 메시지베이스 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "custTmpltId": "string",
  "messagebaseformId": "string",
  "formattedString": {},
  "tmpltName": "string",
  "messagebaseId": "string"
}'
```

응답

200수정 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └messagebaseId | string | — | 메시지베이스 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string"
  }
}
```

post/rcsapi/v1/brand/msgbase/remove

### 메시지베이스 삭제

지정한 메시지베이스를 삭제합니다.

> ⚠️ **한번 삭제된 메시지베이스는 복원이 불가능**하며, 동일한 ID 로 다시 생성할 수 없습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | 브랜드 ID |
| messagebaseId | string | 필수 | 삭제 대상 메시지베이스 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "messagebaseId": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └messagebaseId | string | — | 메시지베이스 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string"
  }
}
```

get/rcsapi/v1/brand/msgbase/list

### 메시지베이스 목록 조회

브랜드에 등록된 메시지베이스 목록을 페이지네이션으로 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand/msgbase/list" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200메시지베이스 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └messagebaseId | string | — | — |
| └messagebaseformId | string | — | — |
| └brandId | string | — | — |
| └groupId | string | — | — |
| └tmpltName | string | — | 메시지베이스 명 |
| └status | string | — | 메시지베이스 상태 |
| └approvalResult | string | — | 승인 상태 |
| └approvalReason | string | — | 승인 사유 |
| └approvalDate | string | — | 승인 일시 |
| └registerDate | string | — | — |
| └registerId | string | — | — |
| └updateDate | string | — | — |
| └updateId | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "messagebaseId": "string",
      "messagebaseformId": "string",
      "brandId": "string",
      "groupId": "string",
      "tmpltName": "string",
      "status": "string",
      "approvalResult": "string",
      "approvalReason": "string",
      "approvalDate": "string",
      "registerDate": "string",
      "registerId": "string",
      "updateDate": "string",
      "updateId": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/brand/msgbase/info

### 메시지베이스 상세 조회

지정 메시지베이스의 풀 필드 셋을 조회합니다 (`formattedString`, `attribute`, `cardType`, `params`, `guideInfo`, `policyInfo`, `spec` 등 포함).

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand/msgbase/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200메시지베이스 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └messagebaseId | string | — | — |
| └messagebaseformId | string | — | — |
| └brandId | string | — | — |
| └groupId | string | — | — |
| └tmpltName | string | — | 메시지베이스 명 |
| └status | string | — | 메시지베이스 상태 |
| └approvalResult | string | — | 승인 상태 |
| └approvalReason | string | — | 승인 사유 |
| └approvalDate | string | — | 승인 일시 |
| └registerDate | string | — | — |
| └registerId | string | — | — |
| └updateDate | string | — | — |
| └updateId | string | — | — |
| └agencyId | string | — | 대행사 ID |
| └attribute | array<object> | — | — |
| └cardType | string | — | 카드 종류 (Cell / Description / Free) |
| └formattedString | object | — | 메시지베이스 포맷 |
| └guideInfo | object | — | — |
| └inputText | string | — | — |
| └params | array<object> | — | — |
| └policyInfo | object | — | — |
| └spec | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string",
    "messagebaseformId": "string",
    "brandId": "string",
    "groupId": "string",
    "tmpltName": "string",
    "status": "string",
    "approvalResult": "string",
    "approvalReason": "string",
    "approvalDate": "string",
    "registerDate": "string",
    "registerId": "string",
    "updateDate": "string",
    "updateId": "string",
    "agencyId": "string",
    "attribute": [
      {}
    ],
    "cardType": "string",
    "formattedString": {},
    "guideInfo": {},
    "inputText": "string",
    "params": [
      {}
    ],
    "policyInfo": {},
    "spec": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/msgbaseform/list

### 메시지베이스 양식 목록 조회

오픈리치카드 템플릿 작성에 사용할 양식 목록을 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/msgbaseform/list" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200양식 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └messagebaseformId | string | — | — |
| └formName | string | — | — |
| └cardType | string | — | — |
| └bizCategory | string | — | — |
| └bizCondition | array<string> | — | 대상 업태 배열 |
| └bizService | string | — | — |
| └registerDate | string | — | — |
| └updateDate | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "messagebaseformId": "string",
      "formName": "string",
      "cardType": "string",
      "bizCategory": "string",
      "bizCondition": [
        "string"
      ],
      "bizService": "string",
      "registerDate": "string",
      "updateDate": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/msgbaseform/info

### 메시지베이스 유형 상세 조회

지정 양식의 풀 필드 셋을 조회합니다. 응답의 `formattedString` 을 이용하여 브랜드용 메시지베이스를 [등록](https://bizppurio.github.io/rapi#post-rcsapiv1brandmsgbasecreate) 할 수 있습니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/msgbaseform/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200양식 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └messagebaseformId | string | — | — |
| └formName | string | — | — |
| └cardType | string | — | — |
| └bizCategory | string | — | — |
| └bizCondition | array<string> | — | 대상 업태 배열 |
| └bizService | string | — | — |
| └registerDate | string | — | — |
| └updateDate | string | — | — |
| └formattedString | object | — | 메시지베이스 포맷 |
| └guideInfo | object | — | — |
| └mediaUrl | array<object> | — | — |
| └params | array<object> | — | — |
| └policyInfo | object | — | — |
| └productCode | string | — | 상품 코드 (sms / lms / mms 등) |
| └spec | string | — | — |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseformId": "string",
    "formName": "string",
    "cardType": "string",
    "bizCategory": "string",
    "bizCondition": [
      "string"
    ],
    "bizService": "string",
    "registerDate": "string",
    "updateDate": "string",
    "formattedString": {},
    "guideInfo": {},
    "mediaUrl": [
      {}
    ],
    "params": [
      {}
    ],
    "policyInfo": {},
    "productCode": "string",
    "spec": "string"
  }
}
```

get/rcsapi/v1/msgbase/common/list

### 메시지베이스 조회 (이통사 기본 제공)

리치카드 규격의 SMS, LMS, MMS 용 메시지베이스(`SS000000`, `SL000000`, `SMwT*`, `CMwM*` 등) 를 조회합니다.

### 카드 종류

- `standalone`
- `standalone media top`
- `standalone horizontal`
- `carousel medium`
- `carousel small`

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/msgbase/common/list" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200이통사 기본 제공 메시지베이스 목록 — [메시지베이스 목록 조회](#operation/rapiListMessagebases) 와 동일 응답 구조

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └messagebaseId | string | — | — |
| └messagebaseformId | string | — | — |
| └brandId | string | — | — |
| └groupId | string | — | — |
| └tmpltName | string | — | 메시지베이스 명 |
| └status | string | — | 메시지베이스 상태 |
| └approvalResult | string | — | 승인 상태 |
| └approvalReason | string | — | 승인 사유 |
| └approvalDate | string | — | 승인 일시 |
| └registerDate | string | — | — |
| └registerId | string | — | — |
| └updateDate | string | — | — |
| └updateId | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "messagebaseId": "string",
      "messagebaseformId": "string",
      "brandId": "string",
      "groupId": "string",
      "tmpltName": "string",
      "status": "string",
      "approvalResult": "string",
      "approvalReason": "string",
      "approvalDate": "string",
      "registerDate": "string",
      "registerId": "string",
      "updateDate": "string",
      "updateId": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/msgbase/common/info

### 메시지베이스 상세 조회 (이통사 기본 제공)

이통사가 기본 제공하는 메시지베이스의 풀 필드 셋을 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/msgbase/common/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200메시지베이스 상세 — [메시지베이스 상세 조회](#operation/rapiGetMessagebase) 와 동일 응답 구조

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └messagebaseId | string | — | — |
| └messagebaseformId | string | — | — |
| └brandId | string | — | — |
| └groupId | string | — | — |
| └tmpltName | string | — | 메시지베이스 명 |
| └status | string | — | 메시지베이스 상태 |
| └approvalResult | string | — | 승인 상태 |
| └approvalReason | string | — | 승인 사유 |
| └approvalDate | string | — | 승인 일시 |
| └registerDate | string | — | — |
| └registerId | string | — | — |
| └updateDate | string | — | — |
| └updateId | string | — | — |
| └agencyId | string | — | 대행사 ID |
| └attribute | array<object> | — | — |
| └cardType | string | — | 카드 종류 (Cell / Description / Free) |
| └formattedString | object | — | 메시지베이스 포맷 |
| └guideInfo | object | — | — |
| └inputText | string | — | — |
| └params | array<object> | — | — |
| └policyInfo | object | — | — |
| └spec | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string",
    "messagebaseformId": "string",
    "brandId": "string",
    "groupId": "string",
    "tmpltName": "string",
    "status": "string",
    "approvalResult": "string",
    "approvalReason": "string",
    "approvalDate": "string",
    "registerDate": "string",
    "registerId": "string",
    "updateDate": "string",
    "updateId": "string",
    "agencyId": "string",
    "attribute": [
      {}
    ],
    "cardType": "string",
    "formattedString": {},
    "guideInfo": {},
    "inputText": "string",
    "params": [
      {}
    ],
    "policyInfo": {},
    "spec": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/msgbaseform/logo

### 기본 로고 이미지 정보 조회

RBC 에서 템플릿용으로 제공되는 기본 로고 이미지 정보를 조회합니다.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/msgbaseform/logo" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200로고 이미지 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └fileId | string | — | — |
| └fileName | string | — | — |
| └fileUrl | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "fileId": "string",
      "fileName": "string",
      "fileUrl": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/brand/msgbase/v2/create

### 메시지베이스 등록 (v2)

브랜드 내에서 사용할 메시지베이스를 v2 규격으로 등록합니다. `messagebaseformId` 의 `params` 각 항목 중 템플릿에 표시될 내용을 `body` 로 입력하여 원하는 템플릿을 등록합니다.

> **등록된 메시지베이스는 RCS Biz Center 운영자의 승인 후 사용 가능**합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | 브랜드 ID |
| custTmpltId | string | 필수 | 사용자 지정 템플릿 ID |
| messagebaseformId | string | 필수 | 메시지베이스 폼 ID |
| tmpltName | string(40) | 필수 | 메시지베이스 명 |
| body | array<object> | 필수 | — |
| └lineIndex | integer | — | 라인 인덱스 |
| └param | string | — | 파라미터 키 |
| └value | string | — | 표시될 값 |
| └decoration | object | — | 본문 항목 데코레이션 |
| └background | string | — | — |
| └height | string | — | — |
| └textAlignment | string | — | — |
| └textColor | string | — | — |
| └textSize | string | — | — |
| └textStyle | string | — | — |
| buttons | array<object> | — | — |
| └suggestions | array<object> | — | — |
| └action | object | — | 버튼 액션 정의 |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/v2/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "custTmpltId": "string",
  "messagebaseformId": "string",
  "tmpltName": "string",
  "body": [
    {
      "lineIndex": 0,
      "param": "string",
      "value": "string",
      "decoration": {
        "background": "string",
        "height": "string",
        "textAlignment": "string",
        "textColor": "string",
        "textSize": "string",
        "textStyle": "string"
      }
    }
  ],
  "buttons": [
    {
      "suggestions": [
        {
          "action": {}
        }
      ]
    }
  ]
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └messagebaseId | string | — | 메시지베이스 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string"
  }
}
```

post/rcsapi/v1/brand/msgbase/v2/update

### 메시지베이스 수정 (v2)

메시지베이스의 ID 를 유지하면서 내용을 변경합니다. 수정 등록된 템플릿은 **RCS Biz Center 운영자의 승인이 필요**합니다.

지원 범위: 텍스트 템플릿(타이틀 선택형 / 타이틀 자유형), 이미지 템플릿, LMS 템플릿.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | 브랜드 ID |
| messagebaseId | string | 필수 | 메시지베이스 ID |
| messagebaseformId | string | 필수 | 메시지베이스 폼 ID |
| tmpltName | string(40) | 필수 | 메시지베이스 명 |
| body | array<object> | 필수 | — |
| └lineIndex | integer | — | 라인 인덱스 |
| └param | string | — | 파라미터 키 |
| └value | string | — | 표시될 값 |
| └decoration | object | — | 본문 항목 데코레이션 |
| └background | string | — | — |
| └height | string | — | — |
| └textAlignment | string | — | — |
| └textColor | string | — | — |
| └textSize | string | — | — |
| └textStyle | string | — | — |
| buttons | array<object> | — | — |
| └suggestions | array<object> | — | — |
| └action | object | — | 버튼 액션 정의 |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/v2/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "messagebaseId": "string",
  "messagebaseformId": "string",
  "tmpltName": "string",
  "body": [
    {
      "lineIndex": 0,
      "param": "string",
      "value": "string",
      "decoration": {
        "background": "string",
        "height": "string",
        "textAlignment": "string",
        "textColor": "string",
        "textSize": "string",
        "textStyle": "string"
      }
    }
  ],
  "buttons": [
    {
      "suggestions": [
        {
          "action": {}
        }
      ]
    }
  ]
}'
```

응답

200수정 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └messagebaseId | string | — | 메시지베이스 ID |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "messagebaseId": "string"
  }
}
```

post/rcsapi/v1/brand/msgbase/v2/file/create

### 이미지 템플릿용 파일 등록

이미지 템플릿에서 사용할 이미지 파일을 등록합니다.

> 파일을 먼저 등록해야 이미지 템플릿 등록 시 `fileId` 를 지정할 수 있습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| brandId | string | 필수 | BizCenter 에서 생성한 브랜드 ID |
| file | string <binary> | 필수 | 업로드 파일 |

```
curl -X POST "{baseUrl}/rcsapi/v1/brand/msgbase/v2/file/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "brandId": "string",
  "file": "{binary}"
}'
```

응답

200등록 성공 — `data.fileId` 발급

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └fileId | string | — | 파일 ID |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "fileId": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

get/rcsapi/v1/brand/msgbase/v2/file/info

### 이미지 템플릿용 파일 정보 조회

이미지 템플릿용으로 등록한 이미지 파일 정보를 조회합니다.

지원 범위: 이미지 템플릿, LMS 템플릿.

cURL

```
curl -X GET "{baseUrl}/rcsapi/v1/brand/msgbase/v2/file/info" \
  -H "Authorization: Bearer {accessToken}"
```

응답

200파일 정보

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └fileId | string | — | — |
| └fileName | string | — | — |
| └imageWidth | string | — | — |
| └imageHeight | string | — | — |
| └url | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "fileId": "string",
      "fileName": "string",
      "imageWidth": "string",
      "imageHeight": "string",
      "url": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

## 기업 전용 브랜드

기업관리자 계정으로 브랜드 자체를 등록·수정·삭제·권한 위임할 수 있는 엔드포인트 (11개).

모든 엔드포인트는 **기업관리자 권한**이 필요합니다.

post/rcsapi/v1/corp/brand/create

### 브랜드 등록 승인

기업관리자 계정으로 등록된 브랜드 승인을 요청합니다.

**1개 이상의 발신번호**를 반드시 함께 등록해야 하며, 브랜드 프로필 이미지·발신번호 통신서비스 가입증명원이 필요합니다.

> **brandBackground 제거 방법** — 등록되어 있는 백그라운드 이미지를 제거하려면 파일 없이 `brandBackground` form key 만 전송합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID (기업관리자) |
| clientSecret | string | 필수 | API Key |
| regBrand | object | 필수 | 브랜드 등록·수정 공용 객체 |
| └name | string | 필수 | 브랜드 홈에 표시되는 브랜드 이름 |
| └description | string | 필수 | 브랜드 홈에 표시되는 브랜드 설명 |
| └tel | string | 필수 | 브랜드 홈 전화번호 (대표 발신번호와 별개) |
| └categoryId | string | 필수 | 브랜드 카테고리 ID |
| └subCategoryId | string | 필수 | 브랜드 하위 카테고리 ID |
| └categoryOpt | string | — | 검색용 키워드 임의 등록 |
| └zipCode | string | — | 우편번호 |
| └roadAddress | string | — | 도로명주소 |
| └detailAddress | string | — | 상세주소 |
| └email | string | — | 이메일주소 |
| └webSiteUrl | string | — | 홈페이지 주소 |
| └menus | array<object> | — | 메뉴 정보 (브랜드 홈에 최대 4개. `chat` 기본 등록 → 지정 가능 최대 3개.  `call` 미포함 + 2개 이하 시 `call` 자동 추가) |
| └buttonType | string | 필수 | 버튼 유형  = call | web | app | store | order | buy | tickets | moreinfo |
| └applink | object | — | APP Link 패키지 정보 객체 |
| └packageName | string | 필수 | APP 실행 패키지명 |
| └action | string | 필수 | APP 실행 Action |
| └uri | string | 필수 | APP 실행 URI |
| └weblink | string | — | call 외 모든 버튼의 Web URL (http:// 또는 https:// 로 시작) |
| brandProfile | string <binary> | 필수 | 프로필 이미지 (388x388~1080x1080, 1:1 비율 권장, png, 최대 1MB) |
| brandBackground | string <binary> | 필수 | 백그라운드 이미지 (388x388~1080x1080, jpg/png, 최대 1MB) |
| mainMdn | string | 필수 | 대표 발신번호 (chatbots 에 포함되어 있어야 함) |
| chatbots | array<object> | 필수 | 등록할 챗봇 정보 (최대 1000개) |
| └mdn | string | 필수 | 등록할 발신번호 (국가번호 생략) |
| └subTitle | string | 필수 | 챗봇 명 |
| └rcsReply | string | 필수 | 수신 모드 (SMS MO:0 / RCS Postback:1)  = 0 | 1 |
| └subnum | string | — | 챗봇 ID (A2P 의 경우 발신번호와 동일) |
| └display | string | — | 전시 여부 |
| └service | string | — | A2P/CHAT 서비스 유형 |
| └webhook | string | — | service=chat 시 필수 (URL) |
| subNumCertificate | string <binary> | 필수 | 통신서비스 이용증명원 파일 |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "regBrand": {
    "name": "string",
    "description": "string",
    "tel": "string",
    "categoryId": "string",
    "subCategoryId": "string",
    "categoryOpt": "string",
    "zipCode": "string",
    "roadAddress": "string",
    "detailAddress": "string",
    "email": "string",
    "webSiteUrl": "string",
    "menus": [
      {
        "buttonType": "call",
        "applink": {
          "packageName": "string",
          "action": "string",
          "uri": "string"
        },
        "weblink": "string"
      }
    ]
  },
  "brandProfile": "{binary}",
  "brandBackground": "{binary}",
  "mainMdn": "string",
  "chatbots": [
    {
      "mdn": "string",
      "subTitle": "string",
      "rcsReply": "0",
      "subnum": "string",
      "display": "string",
      "service": "string",
      "webhook": "string"
    }
  ],
  "subNumCertificate": "{binary}"
}'
```

응답

200등록 성공 — `data.brandId` 발급

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └brandId | string | — | 발급된 브랜드 ID |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/update

### 브랜드 수정 승인 요청

기업관리자 계정으로 **승인 이력이 없는 브랜드**(검수 반려 또는 승인 요청 미진행 상태) 를 수정 승인 요청합니다.

> 이미 운영중인 브랜드의 정보를 수정하려면 [운영중 브랜드 정보 수정](https://bizppurio.github.io/rapi#post-rcsapiv1corpbrand-allowedupdate) 을 사용하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID (기업관리자) |
| clientSecret | string | 필수 | API Key |
| regBrand | object | 필수 | 브랜드 등록·수정 공용 객체 |
| └name | string | 필수 | 브랜드 홈에 표시되는 브랜드 이름 |
| └description | string | 필수 | 브랜드 홈에 표시되는 브랜드 설명 |
| └tel | string | 필수 | 브랜드 홈 전화번호 (대표 발신번호와 별개) |
| └categoryId | string | 필수 | 브랜드 카테고리 ID |
| └subCategoryId | string | 필수 | 브랜드 하위 카테고리 ID |
| └categoryOpt | string | — | 검색용 키워드 임의 등록 |
| └zipCode | string | — | 우편번호 |
| └roadAddress | string | — | 도로명주소 |
| └detailAddress | string | — | 상세주소 |
| └email | string | — | 이메일주소 |
| └webSiteUrl | string | — | 홈페이지 주소 |
| └menus | array<object> | — | 메뉴 정보 (브랜드 홈에 최대 4개. `chat` 기본 등록 → 지정 가능 최대 3개.  `call` 미포함 + 2개 이하 시 `call` 자동 추가) |
| └buttonType | string | 필수 | 버튼 유형  = call | web | app | store | order | buy | tickets | moreinfo |
| └applink | object | — | APP Link 패키지 정보 객체 |
| └packageName | string | 필수 | APP 실행 패키지명 |
| └action | string | 필수 | APP 실행 Action |
| └uri | string | 필수 | APP 실행 URI |
| └weblink | string | — | call 외 모든 버튼의 Web URL (http:// 또는 https:// 로 시작) |
| brandProfile | string <binary> | 필수 | 프로필 이미지 (388x388~1080x1080, 1:1 비율 권장, png, 최대 1MB) |
| brandBackground | string <binary> | 필수 | 백그라운드 이미지 (388x388~1080x1080, jpg/png, 최대 1MB) |
| mainMdn | string | 필수 | 대표 발신번호 (chatbots 에 포함되어 있어야 함) |
| chatbots | array<object> | 필수 | 등록할 챗봇 정보 (최대 1000개) |
| └mdn | string | 필수 | 등록할 발신번호 (국가번호 생략) |
| └subTitle | string | 필수 | 챗봇 명 |
| └rcsReply | string | 필수 | 수신 모드 (SMS MO:0 / RCS Postback:1)  = 0 | 1 |
| └subnum | string | — | 챗봇 ID (A2P 의 경우 발신번호와 동일) |
| └display | string | — | 전시 여부 |
| └service | string | — | A2P/CHAT 서비스 유형 |
| └webhook | string | — | service=chat 시 필수 (URL) |
| subNumCertificate | string <binary> | 필수 | 통신서비스 이용증명원 파일 |
| brandId | string | 필수 | 수정 대상 브랜드 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "regBrand": {
    "name": "string",
    "description": "string",
    "tel": "string",
    "categoryId": "string",
    "subCategoryId": "string",
    "categoryOpt": "string",
    "zipCode": "string",
    "roadAddress": "string",
    "detailAddress": "string",
    "email": "string",
    "webSiteUrl": "string",
    "menus": [
      {
        "buttonType": "call",
        "applink": {
          "packageName": "string",
          "action": "string",
          "uri": "string"
        },
        "weblink": "string"
      }
    ]
  },
  "brandProfile": "{binary}",
  "brandBackground": "{binary}",
  "mainMdn": "string",
  "chatbots": [
    {
      "mdn": "string",
      "subTitle": "string",
      "rcsReply": "0",
      "subnum": "string",
      "display": "string",
      "service": "string",
      "webhook": "string"
    }
  ],
  "subNumCertificate": "{binary}",
  "brandId": "string"
}'
```

응답

200수정 성공 — `data.brandId` 반환

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └brandId | string | — | 발급된 브랜드 ID |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand-allowed/update

### 운영중 브랜드 정보 수정

기업관리자·기업담당자 계정으로 **현재 운영 중인 브랜드**의 정보를 수정합니다.

> **브랜드명 또는 프로필 이미지가 수정되면 재 승인이 필요**하며, 상태가 승인대기로 변경됩니다.  
> **brandBackground 제거 방법** — 등록되어 있는 백그라운드 이미지를 제거하려면 파일 없이 `brandBackground` form key 만 전송합니다.

등록 엔드포인트와 달리 `chatbots` / `subNumCertificate` 를 받지 않으며, `personId` 가 필수입니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID (기업관리자/기업담당자) |
| clientSecret | string | 필수 | API Key |
| brandId | string | 필수 | 수정 대상 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID (=기업관리자) |
| regBrand | object | 필수 | 브랜드 등록·수정 공용 객체 |
| └name | string | 필수 | 브랜드 홈에 표시되는 브랜드 이름 |
| └description | string | 필수 | 브랜드 홈에 표시되는 브랜드 설명 |
| └tel | string | 필수 | 브랜드 홈 전화번호 (대표 발신번호와 별개) |
| └categoryId | string | 필수 | 브랜드 카테고리 ID |
| └subCategoryId | string | 필수 | 브랜드 하위 카테고리 ID |
| └categoryOpt | string | — | 검색용 키워드 임의 등록 |
| └zipCode | string | — | 우편번호 |
| └roadAddress | string | — | 도로명주소 |
| └detailAddress | string | — | 상세주소 |
| └email | string | — | 이메일주소 |
| └webSiteUrl | string | — | 홈페이지 주소 |
| └menus | array<object> | — | 메뉴 정보 (브랜드 홈에 최대 4개. `chat` 기본 등록 → 지정 가능 최대 3개.  `call` 미포함 + 2개 이하 시 `call` 자동 추가) |
| └buttonType | string | 필수 | 버튼 유형  = call | web | app | store | order | buy | tickets | moreinfo |
| └applink | object | — | APP Link 패키지 정보 객체 |
| └packageName | string | 필수 | APP 실행 패키지명 |
| └action | string | 필수 | APP 실행 Action |
| └uri | string | 필수 | APP 실행 URI |
| └weblink | string | — | call 외 모든 버튼의 Web URL (http:// 또는 https:// 로 시작) |
| brandProfile | string <binary> | 필수 | 프로필 이미지 (388x388~1080x1080, 1:1 비율 권장, png, 최대 1MB) |
| brandBackground | string <binary> | 필수 | 백그라운드 이미지 (388x388~1080x1080, jpg/png, 최대 1MB). 파일 없이 form key 만 전송 시 제거 |
| mainMdn | string | 필수 | 대표 발신번호 (chatbots 에 포함되어 있어야 함) |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand-allowed/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string",
  "regBrand": {
    "name": "string",
    "description": "string",
    "tel": "string",
    "categoryId": "string",
    "subCategoryId": "string",
    "categoryOpt": "string",
    "zipCode": "string",
    "roadAddress": "string",
    "detailAddress": "string",
    "email": "string",
    "webSiteUrl": "string",
    "menus": [
      {
        "buttonType": "call",
        "applink": {
          "packageName": "string",
          "action": "string",
          "uri": "string"
        },
        "weblink": "string"
      }
    ]
  },
  "brandProfile": "{binary}",
  "brandBackground": "{binary}",
  "mainMdn": "string"
}'
```

응답

200수정 성공 — `data` 는 [브랜드 상세](#operation/rapiCorpGetBrand) 의 풀 필드 셋

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └name | string | 필수 | 브랜드 홈에 표시되는 브랜드 이름 |
| └description | string | 필수 | 브랜드 홈에 표시되는 브랜드 설명 |
| └tel | string | 필수 | 브랜드 홈 전화번호 (대표 발신번호와 별개) |
| └categoryId | string | 필수 | 브랜드 카테고리 ID |
| └subCategoryId | string | 필수 | 브랜드 하위 카테고리 ID |
| └categoryOpt | string | — | 검색용 키워드 임의 등록 |
| └zipCode | string | — | 우편번호 |
| └roadAddress | string | — | 도로명주소 |
| └detailAddress | string | — | 상세주소 |
| └email | string | — | 이메일주소 |
| └webSiteUrl | string | — | 홈페이지 주소 |
| └menus | array<object> | — | 메뉴 정보 (브랜드 홈에 최대 4개. `chat` 기본 등록 → 지정 가능 최대 3개.  `call` 미포함 + 2개 이하 시 `call` 자동 추가) |
| └buttonType | string | 필수 | 버튼 유형  = call | web | app | store | order | buy | tickets | moreinfo |
| └applink | object | — | APP Link 패키지 정보 객체 |
| └packageName | string | 필수 | APP 실행 패키지명 |
| └action | string | 필수 | APP 실행 Action |
| └uri | string | 필수 | APP 실행 URI |
| └weblink | string | — | call 외 모든 버튼의 Web URL (http:// 또는 https:// 로 시작) |
| └brandId | string | — | — |
| └brandKey | string | — | — |
| └status | string | — | 브랜드 상태 |
| └categoryName | string | — | — |
| └subCategoryName | string | — | — |
| └mediaurl | array<object> | — | 미디어 정보 (fieldId / typeName / url) |
| └fieldId | string | — | — |
| └typeName | string | — | — |
| └url | string | — | — |
| └registerDate | string | — | — |
| └updateDate | string | — | — |
| └approvalDate | string | — | — |
| └chatbotDate | string | — | — |
| └messagebaseDate | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "name": "string",
    "description": "string",
    "tel": "string",
    "categoryId": "string",
    "subCategoryId": "string",
    "categoryOpt": "string",
    "zipCode": "string",
    "roadAddress": "string",
    "detailAddress": "string",
    "email": "string",
    "webSiteUrl": "string",
    "menus": [
      {
        "buttonType": "call",
        "applink": {
          "packageName": "string",
          "action": "string",
          "uri": "string"
        },
        "weblink": "string"
      }
    ],
    "brandId": "string",
    "brandKey": "string",
    "status": "string",
    "categoryName": "string",
    "subCategoryName": "string",
    "mediaurl": [
      {
        "fieldId": "string",
        "typeName": "string",
        "url": "string"
      }
    ],
    "registerDate": "string",
    "updateDate": "string",
    "approvalDate": "string",
    "chatbotDate": "string",
    "messagebaseDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/remove

### 브랜드 삭제 요청 (승인 이력 없음)

기업관리자 계정으로 **승인 이력이 없는 브랜드**의 삭제를 요청합니다.

> 승인된(운영 중) 브랜드를 삭제하려면 [승인된 브랜드 삭제](https://bizppurio.github.io/rapi#post-rcsapiv1corpbrand-allowedremove) 를 사용하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID (기업관리자) |
| clientSecret | string | 필수 | API Key |
| brandId | string | 필수 | 삭제 대상 브랜드 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └brandId | string | — | 발급된 브랜드 ID |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand-allowed/remove

### 승인된 브랜드 삭제 요청

기업관리자 계정으로 **승인 후 사용 중인 브랜드**의 삭제를 요청합니다.

> ⚠️ **삭제 조건**:
>
> - 토큰 발급 계정과 `personId` 가 동일해야 함
> - `brandId` 에 대한 브랜드 관리자 권한 보유
> - **반려/저장 상태**는 즉시 삭제 가능
> - **승인 후 사용 중인 브랜드**는 등록된 발신번호·템플릿을 먼저 삭제해야 처리됨

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID (기업관리자) |
| clientSecret | string | 필수 | API Key |
| brandId | string | 필수 | 삭제 대상 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID (=기업관리자) |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand-allowed/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | — |
| └brandId | string | — | 발급된 브랜드 ID |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "brandId": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/info

### 브랜드 상세 내용 조회

기업관리자/기업담당자 계정으로 브랜드의 상세 내용을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID |
| clientSecret | string | 필수 | API Key |
| personId | string | 필수 | 브랜드 관리자 ID |
| brandId | string | 필수 | 브랜드 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/info" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "personId": "string",
  "brandId": "string"
}'
```

응답

200브랜드 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | allOf | — | — |
| └name | string | 필수 | 브랜드 홈에 표시되는 브랜드 이름 |
| └description | string | 필수 | 브랜드 홈에 표시되는 브랜드 설명 |
| └tel | string | 필수 | 브랜드 홈 전화번호 (대표 발신번호와 별개) |
| └categoryId | string | 필수 | 브랜드 카테고리 ID |
| └subCategoryId | string | 필수 | 브랜드 하위 카테고리 ID |
| └categoryOpt | string | — | 검색용 키워드 임의 등록 |
| └zipCode | string | — | 우편번호 |
| └roadAddress | string | — | 도로명주소 |
| └detailAddress | string | — | 상세주소 |
| └email | string | — | 이메일주소 |
| └webSiteUrl | string | — | 홈페이지 주소 |
| └menus | array<object> | — | 메뉴 정보 (브랜드 홈에 최대 4개. `chat` 기본 등록 → 지정 가능 최대 3개.  `call` 미포함 + 2개 이하 시 `call` 자동 추가) |
| └buttonType | string | 필수 | 버튼 유형  = call | web | app | store | order | buy | tickets | moreinfo |
| └applink | object | — | APP Link 패키지 정보 객체 |
| └packageName | string | 필수 | APP 실행 패키지명 |
| └action | string | 필수 | APP 실행 Action |
| └uri | string | 필수 | APP 실행 URI |
| └weblink | string | — | call 외 모든 버튼의 Web URL (http:// 또는 https:// 로 시작) |
| └brandId | string | — | — |
| └brandKey | string | — | — |
| └status | string | — | 브랜드 상태 |
| └categoryName | string | — | — |
| └subCategoryName | string | — | — |
| └mediaurl | array<object> | — | 미디어 정보 (fieldId / typeName / url) |
| └fieldId | string | — | — |
| └typeName | string | — | — |
| └url | string | — | — |
| └registerDate | string | — | — |
| └updateDate | string | — | — |
| └approvalDate | string | — | — |
| └chatbotDate | string | — | — |
| └messagebaseDate | string | — | — |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "name": "string",
    "description": "string",
    "tel": "string",
    "categoryId": "string",
    "subCategoryId": "string",
    "categoryOpt": "string",
    "zipCode": "string",
    "roadAddress": "string",
    "detailAddress": "string",
    "email": "string",
    "webSiteUrl": "string",
    "menus": [
      {
        "buttonType": "call",
        "applink": {
          "packageName": "string",
          "action": "string",
          "uri": "string"
        },
        "weblink": "string"
      }
    ],
    "brandId": "string",
    "brandKey": "string",
    "status": "string",
    "categoryName": "string",
    "subCategoryName": "string",
    "mediaurl": [
      {
        "fieldId": "string",
        "typeName": "string",
        "url": "string"
      }
    ],
    "registerDate": "string",
    "updateDate": "string",
    "approvalDate": "string",
    "chatbotDate": "string",
    "messagebaseDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/categories

### 브랜드 카테고리 조회

브랜드 등록 시 사용 가능한 카테고리 목록을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | — |
| clientSecret | string | 필수 | — |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/categories" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string"
}'
```

응답

200카테고리 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | — |
| └categoryId | string | — | 브랜드 카테고리 ID |
| └categoryName | string | — | 브랜드 카테고리 명 |
| └subCategories | array<object> | — | 하위 카테고리 배열 |
| └subCategoryId | string | — | — |
| └subCategoryName | string | — | — |
| └updateDate | string | — | 카테고리 수정 일자 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "categoryId": "string",
      "categoryName": "string",
      "subCategories": [
        {
          "subCategoryId": "string",
          "subCategoryName": "string"
        }
      ],
      "updateDate": "string"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/privilege/create

### 권한 등록

브랜드 관리자 권한을 가진 기업관리자 계정으로 브랜드의 부 관리자 또는 대행사 권한을 등록합니다.

### 권한 상태 (`status`)

| 상태 | 의미 |
| --- | --- |
| `Waiting` | 브랜드 운영권한 신청 후 브랜드 관리자 승인 대기 |
| `Processing` | 권한 정보를 이통3사에 동기화 중 |
| `Ok` | 권한 상태 정상 |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID |
| clientSecret | string | 필수 | API Key |
| brandId | string | 필수 | 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID |
| regPrivileges | array<object> | 필수 | 기업관리자/기업담당자 또는 대행사 정보 배열 |
| └id | string | 필수 | 기업관리자/기업담당자/대행사 ID |
| └privilegeType | string | 필수 | 권한 유형 (위임 시 Manager 고정)  = Manager | SubManager | Agency |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/privilege/create" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string",
  "regPrivileges": [
    {
      "id": "string",
      "privilegeType": "Manager"
    }
  ]
}'
```

응답

200권한 등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | 권한 정보 배열 |
| └id | string | — | 대상 ID |
| └privilegeType | string | — | = Manager | SubManager | Agency |
| └status | string | — | 권한 상태  = Waiting | Processing | Ok |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "id": "string",
      "privilegeType": "Manager",
      "status": "Waiting"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/privilege/update

### 권한 위임

브랜드 관리자 권한을 다른 기업관리자에게 위임합니다.

> **위임 시 기존 브랜드 관리자는 자동으로 부관리자로 변경**됩니다. `regPrivilege.privilegeType` 은 `Manager` 로 고정합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | BizCenter ID |
| clientSecret | string | 필수 | API Key |
| brandId | string | 필수 | 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID |
| regPrivilege | object | 필수 | 권한 대상 정보 |
| └id | string | 필수 | 기업관리자/기업담당자/대행사 ID |
| └privilegeType | string | 필수 | 권한 유형 (위임 시 Manager 고정)  = Manager | SubManager | Agency |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/privilege/update" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string",
  "regPrivilege": {
    "id": "string",
    "privilegeType": "Manager"
  }
}'
```

응답

200위임 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | 권한 정보 배열 |
| └id | string | — | 대상 ID |
| └privilegeType | string | — | = Manager | SubManager | Agency |
| └status | string | — | 권한 상태  = Waiting | Processing | Ok |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "id": "string",
      "privilegeType": "Manager",
      "status": "Waiting"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/privilege/remove

### 권한 삭제

브랜드 부관리자, 대행사 권한 중 지정 대상을 삭제합니다.

> ⚠️ **브랜드 관리자는 삭제 불가**. 브랜드 관리자를 변경하려면 [권한 위임](https://bizppurio.github.io/rapi#post-rcsapiv1corpbrandprivilegeupdate) 을 사용하세요.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | — |
| clientSecret | string | 필수 | — |
| brandId | string | 필수 | 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID |
| id | string | 필수 | 삭제 대상 부관리자/대행사 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/privilege/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string",
  "id": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | 권한 정보 배열 |
| └id | string | — | 대상 ID |
| └privilegeType | string | — | = Manager | SubManager | Agency |
| └status | string | — | 권한 상태  = Waiting | Processing | Ok |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "id": "string",
      "privilegeType": "Manager",
      "status": "Waiting"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

post/rcsapi/v1/corp/brand/privilege/list

### 권한 조회

브랜드에 등록된 운영 권한 정보를 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| clientId | string | 필수 | — |
| clientSecret | string | 필수 | — |
| brandId | string | 필수 | 브랜드 ID |
| personId | string | 필수 | 브랜드 관리자 ID |

```
curl -X POST "{baseUrl}/rcsapi/v1/corp/brand/privilege/list" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "clientId": "string",
  "clientSecret": "string",
  "brandId": "string",
  "personId": "string"
}'
```

응답

200권한 목록

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | array<object> | — | 권한 배열 |
| └id | string | — | 대상 ID |
| └privilegeType | string | — | = Manager | SubManager | Agency |
| └status | string | — | 권한 상태  = Waiting | Processing | Ok |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": [
    {
      "id": "string",
      "privilegeType": "Manager",
      "status": "Waiting"
    }
  ],
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```

## 대행사

광고 대행사가 자사 권한으로 호출하는 전용 엔드포인트 (1개) — RCS 포토 발송용 미디어 파일 등록.

post/rcsapi/v1/agency/file

### 대행사 전용 파일 등록

광고 대행사가 자사 권한으로 RCS 포토 발송용 미디어 파일을 등록합니다.

> RCS 발송 시 대행사가 등록한 파일을 사용하려면, 발송 페이로드에 `agencykey` 필드를 함께 전송해야 합니다.

### 일반 파일 등록과의 차이

| 구분 | 엔드포인트 | 사용 타입 | 지원 mimeType |
| --- | --- | --- | --- |
| 일반 사용자 | `POST /rcsapi/v1/file/send` | `chatbot` / `messagebase` / `send` | image/jpeg, image/png 등 |
| **대행사** | `POST /rcsapi/v1/agency/file` | **`send` 만** | jpeg / png / gif / bmp |

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| file | string <binary> | 필수 | 업로드 파일 |
| fileId | string(64) | 필수 | 파일 ID (64byte) |
| mimeType | string | 필수 | Mime type — image/jpeg / image/png / image/gif / image/bmp  = image/jpeg | image/png | image/gif | image/bmp |
| usageType | string | 필수 | 사용 타입 — send (대행사 전용은 send 만 지원)  = send |

```
curl -X POST "{baseUrl}/rcsapi/v1/agency/file" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "file": "{binary}",
  "fileId": "string",
  "mimeType": "image/jpeg",
  "usageType": "send"
}'
```

응답

200등록 성공 — `data` 는 [파일 상세 조회 응답](#operation/rapiGetFile) 과 동일

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | — |
| message | string | — | — |
| data | object | — | 파일 상세 |
| └fileId | string | — | — |
| └mimeType | string | — | — |
| └usageType | string | — | = chatbot | messagebase | send |
| └status | string | — | 파일 상태  = ready | expired |
| └expiryDate | string | — | 유효 기간 |
| pagination | object | — | 페이지네이션 정보 |
| └limit | integer | 필수 | 조회 최대 건수 (기본 100, 최대 1000) |
| └offset | integer | 필수 | 시작 offset (기본 0) |
| └total | integer | 필수 | 총 개수 |

응답 · 200

```
{
  "code": "string",
  "message": "string",
  "data": {
    "fileId": "string",
    "mimeType": "string",
    "usageType": "chatbot",
    "status": "ready",
    "expiryDate": "string"
  },
  "pagination": {
    "limit": 100,
    "offset": 0,
    "total": 0
  }
}
```


---

# 네이버 톡톡 관리 API (NAPI)

> 원본: [https://bizppurio.github.io/napi/](https://bizppurio.github.io/napi/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

NAPIv1.0.0

# 비즈뿌리오 NAPI

## 공통 사항

네이버 톡톡 관리 API (NAPI) — 네이버 톡톡 발송에 필요한 파트너·그룹·이미지·템플릿을 등록·조회·수정·삭제하는 관리 API.

> **NOTE:** NAPI 는 네이버 톡톡 메시지를 발송하지 않습니다. 발송은 [메시지 API](https://bizppurio.github.io/bizapi#post-v3message) 의 `content.ntalk` 을 사용합니다. (BIZCLIENT 미지원, NTALK 은 API 전용)

### 연동 규격

| 항목 | 값 |
| --- | --- |
| 프로토콜 | HTTPS |
| 도메인 | `https://napi.bizppurio.com/` |
| 메서드 | POST 전용 |
| 인코딩 | UTF-8 |
| Content-Type | `application/json; charset=utf-8` |
| 인증 | Bearer 토큰 (`Authorization: Bearer {accessToken}`) |
| 권장 응답 대기 시간 | 30초 |

### 인증 흐름

고객사비즈뿌리오 서버([napi.bizppurio.com](http://napi.bizppurio.com))① POST /token/refresh — bizId + apiKey (IP 10 r/m)② refreshToken (1주)③ POST /token/access — refreshToken④ accessToken (4시간)⑤ 후속 호출 — Authorization: Bearer {accessToken}만료 시 ③단계부터 재발급 (refreshToken 유효하면 재로그인 불필요)

자세한 토큰 발급은 [토큰 API](https://bizppurio.github.io/napi#토큰) 를 참고하세요.

### 공통 응답 형식

성공·실패 모두 다음 형식. **`429` 외 검증·인증 실패는 HTTP 200** + 본문 `code` 로 결과 전달.

```
{ "code": "200", "message": "요청 성공", "data": { ... } }
```

| 필드 | 설명 |
| --- | --- |
| `code` | 결과 코드 (`200` = 성공, 그 외 [코드 정의](https://bizppurio.github.io/response-codes) 참고) |
| `message` | 결과 메시지 |
| `data` | 성공 시 응답 본문 (엔드포인트별 상이) |
| `errors` | 필드 검증 실패 시 `{ field, value, reason }` 배열 |

### Rate Limit

| 구분 | 제한 |
| --- | --- |
| 토큰 API (`/token/*`) | **IP 기준 10 r/m** |
| 그 외 자원 API | **계정 기준 100 r/m** |

초과 시 HTTP 429 + 응답 헤더 `X-Rate-Limit-Limit` / `X-Rate-Limit-Remaining` / `X-Rate-Limit-Retry-After-Seconds`.

### 자원 ↔ 발송 연결

NAPI 로 등록·관리하는 자원은 다음과 같이 [메시지 API](https://bizppurio.github.io/bizapi#post-v3message) `content.ntalk` 에서 사용됩니다.

| NAPI 에서 등록·관리 | 네이버 톡톡 발송에서 사용 |
| --- | --- |
| 파트너 키 (`naverPartnerKey`) | `content.ntalk.partnerkey` |
| 템플릿 코드 (`templateCode`) | `content.ntalk.templatecode` |
| 이미지 해시 ID (`imageHashId`) | `content.ntalk.extra.attachment.imageHashId` |
| 템플릿 그룹 키 (`templateGroupKey`) | `content.ntalk.groupkey` |

### 추가 사항

**템플릿 상태 변화**:

- `templateStatusType` (검수): `REGISTERED` → `PENDING` → `APPROVED` | `REJECTED`
- `templateSendingStatusType` (발송): `WAITING` → `SENDING` | `BLOCKED`
- **`WAITING` 이 아닌 상태에서는 수정/삭제 불가**

## 토큰

refreshToken (1주) · accessToken (4시간) 발급 (2개 엔드포인트, 인증 헤더 없음, IP 단위 10 r/m)

post/token/refresh

### Refresh-Token 발행

`accessToken` 발행에 사용하는 장기 토큰입니다. **유효 기간 1주**.

- 비즈뿌리오 사이트에 등록된 모듈 계정(`bizId`) + 발급받은 `apiKey` 필요
- 토큰 발급 API는 **IP 단위 10 r/m** Rate Limit 적용
- 응답으로 `refreshToken`과 즉시 사용 가능한 `accessToken` 한 쌍을 함께 반환

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| bizId | string | 필수 | 비즈뿌리오 사용자 ID |
| apiKey | string | 필수 | 발급받은 API Key |

```
curl -X POST "https://napi.bizppurio.com/token/refresh" \
  -H "Content-Type: application/json" \
  -d '{
  "bizId": "bizUserId001",
  "apiKey": "123cr0wSXXXXXXXX"
}'
```

응답

200토큰 발급 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └refreshToken | string | — | 리프레시 토큰 (1주 유효) |
| └accessToken | string | — | 인증 토큰 (4시간 유효) |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "refreshToken": "...",
    "accessToken": "..."
  }
}
```

429요청 한도 초과 (IP 단위 10 r/m)

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |

응답 · 429

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ]
}
```

post/token/access

### Access-Token 발행

API 인증에 사용하는 단기 토큰입니다. **유효 기간 4시간**.

- `refreshToken`만으로 호출
- 만료 시 다시 발급하여 `Authorization: Bearer {accessToken}` 헤더에 사용

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| refreshToken | string | 필수 | 리프레시 토큰 |

```
curl -X POST "https://napi.bizppurio.com/token/access" \
  -H "Content-Type: application/json" \
  -d '{
  "refreshToken": "..."
}'
```

응답

200토큰 발급 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └accessToken | string | — | 인증 토큰 (4시간 유효) |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "accessToken": "..."
  }
}
```

## 파트너

네이버 톡톡 파트너(발송 계정) 정보 조회 (1개 엔드포인트).

파트너 **등록은 NAPI에서 불가** — 비즈뿌리오 웹에서만 가능. 사전에 네이버 톡톡 파트너센터에서 파트너 계정 생성·승인 후 대행사(다우기술)를 등록해야 `naverPartnerKey` 가 발급됩니다.

post/v1/partner/get

### 파트너 조회

네이버 톡톡에 등록된 파트너(발송 계정) 정보를 조회합니다.

> ℹ️ 파트너 **등록**은 NAPI에서 불가하며 비즈뿌리오 웹에서만 가능합니다. 사전에 네이버 톡톡 파트너센터에서 파트너 계정 생성·승인 후 대행사(다우기술) 등록이 완료되어 있어야 합니다.

응답에는 소속 그룹 정보(`templateGroups[]`), 템플릿 상태별 개수(`templateCount`), 계정 정보(`account`)가 포함됩니다. `accountStatusType` 코드는 `NORMAL` · `PAUSE` · `SYSPAUSE` · `PREBLOCK` · `BLOCK` · `DELETED`.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 네이버 톡톡 파트너 키 (파트너센터에서 파트너 계정 생성·승인 후 대행사 등록 완료된 파트너의 키) |

```
curl -X POST "https://napi.bizppurio.com/v1/partner/get" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "fAO8bJKWXXXXXXXX"
}'
```

응답

200파트너 조회 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └templateGroups | array<object> | — | 소속된 그룹 정보 배열 |
| └name | string | — | 그룹명 |
| └templateGroupKey | string | — | 그룹키 |
| └templateCount | object | — | 템플릿 상태별 개수 |
| └approved | integer | — | 검수완료 템플릿 수 |
| └rejected | integer | — | 검수반려 템플릿 수 |
| └pending | integer | — | 검수요청 템플릿 수 |
| └registered | integer | — | 등록 템플릿 수 |
| └account | object | — | 파트너 계정 정보 |
| └profileName | string | — | 프로필명 |
| └accountStatus | string | — | 계정상태 (한글 라벨) |
| └accountStatusType | string | — | 계정상태 코드  = NORMAL | PAUSE | SYSPAUSE | PREBLOCK | BLOCK | DELETED |
| └accountId | string | — | 톡톡계정 ID |
| └partnerKey | string | — | 파트너키 |
| └chatYn | boolean | — | 상담 기능 사용 여부 |
| └businessTypeCategoryName | string | — | 업종분류 |
| └registerDate | string | — | 등록일 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "templateGroups": [
      {
        "name": "그룹 1",
        "templateGroupKey": "..."
      },
      {
        "name": "비즈뿌리오",
        "templateGroupKey": "..."
      }
    ],
    "templateCount": {
      "approved": 260,
      "rejected": 260,
      "pending": 260,
      "registered": 260
    },
    "account": {
      "profileName": "다우기술",
      "accountStatus": "사용중",
      "accountStatusType": "NORMAL",
      "accountId": "...",
      "partnerKey": "...",
      "chatYn": true,
      "businessTypeCategoryName": "인터넷/통신 > 인터넷서비스",
      "registerDate": "2024.05.17. 14:09:27"
    }
  }
}
```

## 그룹

파트너 그룹 생성 · 구성원 추가/제거 (3개 엔드포인트). 그룹의 템플릿은 파트너 개별 템플릿과 **별도로 관리**됩니다.

post/v1/group/register

### 파트너 그룹 추가

파트너 그룹을 생성합니다. 생성된 그룹의 `templateGroupKey`를 이용해 파트너 추가 및 그룹 템플릿 관리에 사용합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| groupName | string | 필수 | 그룹명 |

```
curl -X POST "https://napi.bizppurio.com/v1/group/register" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "groupName": "TP-GROUP-TEST"
}'
```

응답

200그룹 생성 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └name | string | — | 그룹명 |
| └templateGroupKey | string | — | 그룹키 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "name": "string",
    "templateGroupKey": "string"
  }
}
```

post/v1/group/add/partner

### 파트너 그룹에 파트너 추가

파트너 그룹에 파트너를 추가합니다. 추가된 파트너는 해당 그룹의 그룹 템플릿을 사용할 수 있습니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| naverPartnerId | string | 필수 | 파트너 ID |

```
curl -X POST "https://napi.bizppurio.com/v1/group/add/partner" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "dOn5qlguXXXXXXXX",
  "naverPartnerId": "w4tXXXXXXXX"
}'
```

응답

200추가 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └name | string | — | 그룹명 |
| └templateGroupKey | string | — | 그룹키 |
| └naverPartners | array<string> | — | 그룹에 속한 파트너 ID 리스트 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "name": "string",
    "templateGroupKey": "string",
    "naverPartners": [
      "string"
    ]
  }
}
```

post/v1/group/remove/partner

### 파트너 그룹에서 파트너 제거

파트너 그룹에 존재하는 네이버 파트너를 제거합니다. 요청·응답 구조는 [파트너 추가](https://bizppurio.github.io/napi#post-v1groupaddpartner)와 동일합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| naverPartnerId | string | 필수 | 파트너 ID |

```
curl -X POST "https://napi.bizppurio.com/v1/group/remove/partner" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "naverPartnerId": "string"
}'
```

응답

200제거 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └name | string | — | 그룹명 |
| └templateGroupKey | string | — | 그룹키 |
| └naverPartners | array<string> | — | 그룹에 속한 파트너 ID 리스트 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "name": "string",
    "templateGroupKey": "string",
    "naverPartners": [
      "string"
    ]
  }
}
```

## 이미지

파트너 / 그룹 이미지 URL · 파일 업로드 (4개 엔드포인트).

- 포맷 JPG / JPEG / PNG / GIF, **300 KB** 이하
- `imageType: content` 권장 552×552 / `imageType: feed` **598×300 고정** (혜택 피드용)
- 반환된 `imageHashId` 를 템플릿의 `sampleImageHashId` · `feedDisplayImageHashId` · `thumbnailImageHashId` 등에 사용

post/v1/image/upload/url

### 이미지 URL 업로드

원격 URL의 이미지를 업로드하여 `imageHashId`를 발급받습니다.

- 포맷 JPG / JPEG / PNG / GIF, **300 KB** 이하
- `imageType: content` 권장 552×552, `imageType: feed` **598×300 고정**

> 📖 상세 규격은 [버튼·이미지 규격](https://bizppurio.github.io/napi#버튼·이미지-규격) 참고.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| imageUrl | string | 필수 | 업로드할 이미지 URL |
| imageType | string | — | content(기본 552×552 권장) / feed(598×300 고정)  = content | feed |

```
curl -X POST "https://napi.bizppurio.com/v1/image/upload/url" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "imageUrl": "https://...",
  "naverPartnerKey": "fAO8bJKWXXXXXXXX",
  "imageType": "content"
}'
```

응답

200업로드 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └imageHashId | string | — | 이미지 해시 ID — 템플릿에서 참조 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "imageHashId": "..."
  }
}
```

post/v1/image/upload/file

### 이미지 파일 업로드

로컬 이미지 파일(`multipart/form-data`)을 업로드합니다.

- 포맷 JPG / JPEG / PNG / GIF, **300 KB** 이하
- `imageType` 동작은 [이미지 URL 업로드](https://bizppurio.github.io/napi#post-v1imageuploadurl) 참고

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| file | string <binary> | 필수 | 업로드할 이미지 파일 |
| imageType | string | — | content(기본) 또는 feed  = content | feed |

```
curl -X POST "https://napi.bizppurio.com/v1/image/upload/file" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "file": "{binary}",
  "imageType": "content"
}'
```

응답

200업로드 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └imageHashId | string | — | 이미지 해시 ID — 템플릿에서 참조 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "imageHashId": "string"
  }
}
```

post/v1/image/group/upload/url

### 발송 그룹 이미지 URL 업로드

파트너 그룹 단위로 사용할 이미지를 URL로 업로드합니다. 응답 구조는 [이미지 URL 업로드](https://bizppurio.github.io/napi#post-v1imageuploadurl)와 동일합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| imageUrl | string | 필수 | 업로드할 이미지의 URL |
| imageType | string | — | = content | feed |

```
curl -X POST "https://napi.bizppurio.com/v1/image/group/upload/url" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "imageUrl": "string",
  "imageType": "content"
}'
```

응답

200업로드 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └imageHashId | string | — | 이미지 해시 ID — 템플릿에서 참조 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "imageHashId": "string"
  }
}
```

post/v1/image/group/upload/file

### 발송 그룹 이미지 파일 업로드

파트너 그룹 단위로 사용할 이미지를 파일(`multipart/form-data`)로 업로드합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| file | string <binary> | 필수 | 업로드할 이미지 파일 |
| imageType | string | — | = content | feed |

```
curl -X POST "https://napi.bizppurio.com/v1/image/group/upload/file" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "file": "{binary}",
  "imageType": "content"
}'
```

응답

200업로드 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └imageHashId | string | — | 이미지 해시 ID — 템플릿에서 참조 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "imageHashId": "string"
  }
}
```

## 템플릿

정보성 · 광고성(혜택) 네이버 톡톡 템플릿 CRUD · 검수 요청 · 이력 (9개 엔드포인트).

**상품 종류** (`productCode`)

- `INFORMATION` — 정보성 (알림 / 선물 전달)
- `BENEFIT` — 마케팅/광고성 (혜택)

**템플릿 타입** (`templateType`)

- 정보성: `BASIC` · `GIFT` · `TABLE`
- 혜택: `BENEFIT` · `BENEFIT_LMS` · `BENEFIT_CAROUSEL_COMMERCE` · `BENEFIT_CAROUSEL_FEED` · `BENEFIT_LIST_COMMERCE` · `BENEFIT_LIST_FEED`

발송 메시지 API 의 [네이버 톡톡 ContentNtalk](https://bizppurio.github.io/bizapi#post-v3message) 의 템플릿 타입 코드(ID/IG/IT/BD/BM/BC/BL/CT)와 일관성 있게 매핑되며, NAPI 는 자원 등록 / 관리 단에서 위 풀네임을 사용합니다.

### 검색 노출 동작 (`searchResultExposure`)

- `true`: 버튼 URL 개인화 **불가**. 변수(`#{}`) 사용 시 등록 실패.
- `false`: 발송 시점 URL 포함 가능.

### 카테고리 `B005` (소식) 제약

`categoryType` · `benefitTypes` · `discountInfo` · `feedDisplayEndedAt` · `feedDisplayImageHashId` · `validityInfo` **사용 불가**, `searchResultExposure` 는 항상 `false`.

post/v1/template/get

### 템플릿 조회

등록된 템플릿의 상세 정보를 조회합니다.

> 📖 `templateType`별 구성은 [템플릿 타입·노출 예시](https://bizppurio.github.io/napi#템플릿-타입·노출-예시) 참고.

템플릿 타입별 추가 필드:

- `GIFT`: `sampleCoupon`
- `TABLE`: `tableElements[]` + `pushNotice`
- `BENEFIT*`: `benefit` 객체 (`title`, `feedDisplayImageHashId`, `categoryType`, `benefitTypes`, `discountInfo`, `validityInfo` 등)

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/get" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "templateCode": "string"
}'
```

응답

200템플릿 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/register/information

### 정보성 템플릿 생성

정보성(`productCode: INFORMATION`) 템플릿을 신규 등록합니다.

> 📖 타입별 노출 예시·필드는 [템플릿 타입·노출 예시](https://bizppurio.github.io/napi#템플릿-타입·노출-예시), 버튼·이미지 규격은 [버튼·이미지 규격](https://bizppurio.github.io/napi#버튼·이미지-규격) 참고.

`templateType` 별 필드:

- `BASIC` — 텍스트 + 버튼 (이미지 없음)
- `IMAGE` — 텍스트 + 이미지(`sampleImageHashId`) + 버튼
- `GIFT` — `sampleCoupon` 객체 사용 (쿠폰 첨부 시 이미지 첨부 불가)
- `TABLE` — `text` 대신 `pushNotice`, `tableInfo.elementList[]` 필수 (1~6개)

등록 직후 템플릿 상태는 `templateStatusType: REGISTERED` / `templateSendingStatusType: WAITING`.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string(64) | 필수 | 네이버 파트너 키 |
| templateCode | string(64) | 필수 | 관리 템플릿 코드 — 영문/숫자/`-` 구성, 파트너별 유니크 |
| templateType | string | — | BASIC(기본형) · IMAGE(이미지형) · GIFT(선물) · TABLE(테이블)  = BASIC | IMAGE | GIFT | TABLE |
| text | string(2048) | — | 발송 텍스트 — 변수(`#{name}`) 사용 가능, 알파벳·숫자·한글·`/-_~` 허용. 변수 치환 결과 150자 이내 |
| pushNotice | string(2048) | — | TABLE 형에서 `text` 대신 사용하는 푸시 알림 메시지 |
| categoryCode | string(8) | 필수 | 템플릿 카테고리 코드 — `G/C/F/D/P/S/T/R` 시리즈 |
| buttons | array<object>(~5) | — | 버튼 배열 (최대 5개) |
| └type | string | 필수 | = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | 필수 | 템플릿 내 유니크 코드 |
| └buttonName | string(20) | 필수 | 버튼 표시 문구 (기본형 20자, 커머스형 8자) |
| └mobileUrl | string | — | WEB\_LINK 모바일 URL |
| └pcUrl | string | — | WEB\_LINK PC URL |
| └iOsAppScheme | string | — | APP\_LINK iOS 스킴 |
| └aOsAppScheme | string | — | APP\_LINK Android 스킴 |
| sampleImageHashId | string | — | 이미지 API로 발급받은 해시 ID (쿠폰 첨부 시 이미지 첨부 불가) |
| sampleCoupon | object | — | 쿠폰 정보 (정보성-GIFT 형에서 사용) |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| couponDescription | array<object>(~10) | — | 쿠폰 설명 `{title, content}` 쌍 배열 (최대 10개) |
| └title | string | — | — |
| └content | string | — | — |
| tableInfo | object | — | 테이블 정보 (TABLE 형 필수) |
| └elementList | array<object>(1~6) | — | 테이블 요소 배열 (1~6개) |
| └subtitle | string(30) | — | 서브 타이틀 |
| └title | string(30) | — | 타이틀 |
| └strikeTitle | boolean | — | 타이틀 취소선 |
| └thumbnailImageUrl | string | — | 썸네일 이미지 URL |
| └thumbnailImageHashId | string | — | 썸네일 이미지 해시 ID |
| └table | array<object>(~10) | — | 테이블 항목 — `{title(7자), content(20자)}` × 최대 10개 (본문 없으면 필수) |
| └title | string(7) | — | — |
| └content | string(20) | — | — |
| └text | string(1000) | — | 본문 — `table`이 없으면 필수 |
| └additionalContent | string(500) | — | 부가정보 |
| └buttons | array<object>(~5) | — | — |
| └type | string | 필수 | = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | 필수 | 템플릿 내 유니크 코드 |
| └buttonName | string(20) | 필수 | 버튼 표시 문구 (기본형 20자, 커머스형 8자) |
| └mobileUrl | string | — | WEB\_LINK 모바일 URL |
| └pcUrl | string | — | WEB\_LINK PC URL |
| └iOsAppScheme | string | — | APP\_LINK iOS 스킴 |
| └aOsAppScheme | string | — | APP\_LINK Android 스킴 |
| └updateMode | string | — | 수정 시 항목별 동작 (그룹 템플릿 수정에서 사용)  = delete | update | add |

```
curl -X POST "https://napi.bizppurio.com/v1/template/register/information" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "fAO8bJKWXXXXXXXX",
  "templateCode": "TP-INFORMATION-BASIC-XXXXXXXX",
  "text": "템플릿 등록 테스트입니다.",
  "categoryCode": "R006",
  "templateType": "BASIC",
  "buttons": [
    {
      "type": "WEB_LINK",
      "buttonCode": "BTN-CODE-1",
      "buttonName": "웹 링크 버튼"
    }
  ]
}'
```

응답

200등록 성공 — 응답 구조는 [템플릿 조회](#operation/napiGetTemplate)와 동일

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/register/benefit

### 광고성(혜택) 템플릿 생성

광고성·혜택 템플릿을 신규 등록합니다. `templateType` 은 6종입니다.

| templateType | 설명 |
| --- | --- |
| `BENEFIT` | 기본형 |
| `BENEFIT_LMS` | LMS형(장문) |
| `BENEFIT_CAROUSEL_COMMERCE` | 캐러셀 커머스형 |
| `BENEFIT_CAROUSEL_FEED` | 캐러셀 피드형 |
| `BENEFIT_LIST_COMMERCE` | 리스트 커머스형 |
| `BENEFIT_LIST_FEED` | 리스트 피드형 |

혜택 소재는 모두 `benefit` 객체 안에 담습니다.

| 구분 | 필드 |
| --- | --- |
| 필수 | `title` · `feedDisplayImageHashId`(598×300) · `categoryType` · `benefitTypes` · `validityInfo` · `searchResultExposure` |
| 둘 중 하나 필수 | `blockCallNumber` / `blockMessageUrl` |
| 캐러셀·리스트형 추가 | `introduction` · `products[]` · `moreButton*` |

**제약**

- 검색 노출(`searchResultExposure: true`) 시 버튼 URL 개인화 불가
- `categoryCode: B005`(소식) 선택 시 `categoryType`·`benefitTypes`·`discountInfo`·`feedDisplayEndedAt`·`feedDisplayImageHashId`·`validityInfo` **사용 불가**, `searchResultExposure`는 항상 `false`

> 📖 타입별 노출 예시는 [템플릿 타입·노출 예시](https://bizppurio.github.io/napi#템플릿-타입·노출-예시), `benefit` 구성·할인·유효기간·소식 제약은 [혜택 메시지 구성](https://bizppurio.github.io/napi#혜택-메시지-구성) 참고.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string(64) | 필수 | — |
| templateType | string | 필수 | = BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| templateCode | string(64) | 필수 | 영문/숫자, 파트너별 유니크 |
| categoryCode | string | 필수 | 혜택 카테고리 코드 — `B001` 쿠폰 · `B002` 적립금 · `B003` 추가 증정 · `B004` 기타 이벤트 · `B005` 소식 |
| text | string | — | 발송 텍스트 (기본형 360자, LMS형 2000자) |
| sampleImageHashId | string | — | 톡톡 말풍선용 이미지 해시 ID (기본형 필수, LMS형 선택) |
| buttons | array<object> | — | — |
| └type | string | 필수 | = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | 필수 | 템플릿 내 유니크 코드 |
| └buttonName | string(20) | 필수 | 버튼 표시 문구 (기본형 20자, 커머스형 8자) |
| └mobileUrl | string | — | WEB\_LINK 모바일 URL |
| └pcUrl | string | — | WEB\_LINK PC URL |
| └iOsAppScheme | string | — | APP\_LINK iOS 스킴 |
| └aOsAppScheme | string | — | APP\_LINK Android 스킴 |
| benefit | object | 필수 | 혜택 소재 정보. `blockCallNumber`(080 광고수신거부 번호)와 `blockMessageUrl`(https URL) 중 **하나는 반드시** 입력. |
| └title | string | — | 혜택 제목 (한글 20자 내 권장) |
| └categoryType | string | — | 혜택 카테고리 — 피드 [인기] 탭 분류  = FASHION | BEAUTY | DIGITAL\_APPLIANCE | LIVING | FOOD | KIDS | SPORTS\_LEISURE | NECESSITIES | BOOK\_HOBBY | FINANCE | ETC |
| └benefitTypes | array<string>(~2) | — | 혜택 유형 1~2개. LMS형은 `EVENT` 고정 |
| └feedDisplayEndedAt | string | — | 피드 표시용 만료일 (YYYY-MM-DD, 최대 2주) |
| └feedDisplayImageHashId | string | — | 피드 노출 이미지 해시 ID — 598×300 고정 (`imageType: feed`로 업로드) |
| └searchResultExposure | boolean | — | 검색결과 노출 여부 (미입력 시 false). true 시 버튼 URL 개인화 불가 — 등록 시점에 URL 확정 필요 |
| └blockCallNumber | string(13) | — | 080 광고수신거부 전화번호 (`080-123-1234` 형식). `blockMessageUrl`과 둘 중 하나 필수 |
| └blockMessageUrl | string | — | https 광고수신거부 URL. `blockCallNumber`와 둘 중 하나 필수 |
| └blockContactType | string | — | 광고수신거부 연락 유형 — TELEPHONE(전화번호) / LINK(링크)  = TELEPHONE | LINK |
| └moreButtonType | string | — | 더보기 버튼 타입 (캐러셀형) — WEB\_LINK / APP\_LINK  = WEB\_LINK | APP\_LINK |
| └moreButtonUrl | string | — | 더보기 버튼 URL (캐러셀형). 미입력 시 더보기 버튼 미표시 |
| └moreButtonMobileUrl | string | — | 더보기 버튼 모바일 URL. 미입력 시 `moreButtonUrl` 값 사용 |
| └moreButtoniOsAppScheme | string | — | 더보기 버튼 iOS 앱 스킴 (moreButtonType=APP\_LINK) |
| └moreButtonaOsAppScheme | string | — | 더보기 버튼 Android 앱 스킴 (moreButtonType=APP\_LINK) |
| └introduction | object | — | 캐러셀 / 리스트형 인트로 (캐러셀 커머스 · 리스트 커머스 · 리스트 피드 필수) |
| └headerImageHashId | string | — | 헤더 이미지 해시 ID (`headerImageUrl`과 둘 중 하나 필수) |
| └headerImageUrl | string | — | 헤더 이미지 URL (`headerImageHashId`와 둘 중 하나 필수) |
| └title | string | 필수 | 인트로 제목 (한글 20자 이내) |
| └description | string | — | 인트로 내용 (캐러셀 커머스 60자 · 리스트 커머스/피드 70자) |
| └buttonType | string | — | 인트로 버튼 타입 — WEB\_LINK / APP\_LINK  = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | — | 인트로 버튼 코드 |
| └buttonTitle | string | — | 캐러셀 커머스 필수 버튼 제목 (8자) |
| └pcUrl | string | — | — |
| └mobileUrl | string | — | — |
| └iOsAppScheme | string | — | — |
| └aOsAppScheme | string | — | — |
| └products | array<object>(~6) | — | 상품 배열 (캐러셀/리스트형) — 캐러셀 커머스 2~5 · 캐러셀 피드 2~6 · 리스트 커머스 3~6 · 리스트 피드 2~3 |
| └imageHashId | string | — | 상품 이미지 해시 ID (`imageUrl`과 둘 중 하나 필수) |
| └imageUrl | string | — | 상품 이미지 URL (`imageHashId`와 둘 중 하나 필수) |
| └title | string | — | 상품명 (한글 20자 이내) |
| └description | string | — | 상품 설명 (캐러셀 피드 100자 필수) |
| └originalPrice | number | — | 할인 전 가격 |
| └currentPrice | number | — | 할인 후 가격 (originalPrice 이하) |
| └buttonType | string | — | 상품 버튼 타입 — WEB\_LINK / APP\_LINK  = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | — | 상품 버튼 코드 |
| └pcUrl | string | — | — |
| └mobileUrl | string | — | — |
| └iOsAppScheme | string | — | — |
| └aOsAppScheme | string | — | — |
| └buttons | array<object> | — | — |
| └type | string | 필수 | = WEB\_LINK | APP\_LINK |
| └buttonCode | string(64) | 필수 | 템플릿 내 유니크 코드 |
| └buttonName | string(20) | 필수 | 버튼 표시 문구 (기본형 20자, 커머스형 8자) |
| └mobileUrl | string | — | WEB\_LINK 모바일 URL |
| └pcUrl | string | — | WEB\_LINK PC URL |
| └iOsAppScheme | string | — | APP\_LINK iOS 스킴 |
| └aOsAppScheme | string | — | APP\_LINK Android 스킴 |
| └discountInfo | object | — | 할인 정보 |
| └discountType | string | — | AMOUNT(할인금액) / RATE(할인률) / POINT(적립금)  = AMOUNT | RATE | POINT |
| └discountAmount | number | — | 할인금액 — discountType=AMOUNT |
| └discountRate | number | — | 할인률 — discountType=RATE |
| └maxDiscountAmount | number | — | 최대 할인금액 — discountType=RATE |
| └minimumOrderAmount | number | — | 최소 주문금액 (POINT/PRODUCT/DELIVERY/ORDER 포함 시 1,000원 이상) |
| └accumulateAmount | number | — | 적립금액 — discountType=POINT |
| └landingPageUrl | string | — | 혜택 클릭 시 이동 페이지 (POINT/PRODUCT/DELIVERY/ORDER 필수) |
| └benefitContent | string | — | 혜택 본문 (예 "3,000원 할인 쿠폰") |
| └benefitKindType | string | — | 혜택 종류 — COUPON(쿠폰) / POINT(적립금)  = COUPON | POINT |
| └couponPublicationType | string | — | 쿠폰 발급 방식 — DOWNLOAD(다운로드) / IMMEDIATE(즉시발급)  = DOWNLOAD | IMMEDIATE |
| └validityInfo | object | — | 혜택 유효 기간 |
| └validType | string | 필수 | PERIOD(기간 설정) / EXPIRATION(발급일 기준 N일)  = PERIOD | EXPIRATION |
| └validDays | number | — | EXPIRATION 유형 — 다운로드 후 N일간 유효 |
| └validStartedAt | string | — | PERIOD 유형 시작일 (YYYY-MM-DD) |
| └validEndedAt | string | — | PERIOD 유형 종료일 (YYYY-MM-DD) |

```
curl -X POST "https://napi.bizppurio.com/v1/template/register/benefit" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "fAO8bJKWXXXXXXXX",
  "templateType": "BENEFIT",
  "templateCode": "TP-BENEFIT-BASIC-XXXXXXXX",
  "categoryCode": "B002",
  "text": "역대급 할인 오늘 단 하루만 진행! 패션 PICK 5시간 후 종료!",
  "sampleImageHashId": "om_TcC1RXXXXXXXX",
  "buttons": [
    {
      "type": "WEB_LINK",
      "buttonName": "구매하기",
      "buttonCode": "POPUP_BUTTON1",
      "mobileUrl": "https://m.naver.com",
      "pcUrl": "https://www.naver.com"
    }
  ],
  "benefit": {
    "title": "곧 종료 시즌 막바지 ~70% 대박할인!",
    "feedDisplayImageHashId": "om_TcC1RXXXXXXXX",
    "feedDisplayEndedAt": "2024-12-30",
    "categoryType": "LIVING",
    "benefitTypes": [
      "TIMESALE",
      "PRODUCT"
    ],
    "discountInfo": {
      "discountType": "AMOUNT",
      "discountAmount": 3000,
      "minimumOrderAmount": 10000,
      "landingPageUrl": "https://www.naver.com"
    },
    "validityInfo": {
      "validType": "PERIOD",
      "validStartedAt": "2024-12-30",
      "validEndedAt": "2024-12-30"
    },
    "blockCallNumber": "080-123-1234"
  }
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/modify

### 템플릿 수정

등록 또는 검수 반려된 템플릿을 수정합니다. 수정된 템플릿은 `templateStatusType: REGISTERED`로 업데이트됩니다.

- 수정이 필요한 필드만 요청. **변경 불가**: `productCode` · `templateCode` · `naverPartnerKey` · `templateType` · `createdAt` · `modifiedAt`.
- `buttons`·`couponDescription` 등 목록형은 **입력 전체로 교체**. 모두 제거하려면 `"buttons": []`.

**배열형태의 데이터 수정 (테이블형, 혜택 캐러셀/리스트)**

각 항목별로 `updateMode`를 사용하여 삭제·수정·추가를 진행합니다. `updateMode`는 소문자로 입력하며 다음과 같습니다.

- `delete` : 해당 순서의 항목을 삭제합니다.
- `update` : 해당 순서의 항목에 입력된 필드를 수정합니다. 일반 수정처럼 필요 항목만 입력 가능합니다.
- `add` : 입력한 페이로드를 이용하여 목록의 마지막에 항목을 추가합니다.

> ⚠️ 발송대기(`WAITING`)가 아닌 템플릿은 수정/삭제 불가.

요청 본문

object

수정할 필드 + `naverPartnerKey` + `templateCode` (정보성/광고성 페이로드 동일 구조)

```
curl -X POST "https://napi.bizppurio.com/v1/template/modify" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "fAO8bJKWXXXXXXXX",
  "productCode": "INFORMATION",
  "templateCode": "TP-INFORMATION-BASIC-XXXXXXXX",
  "text": "템플릿 수정 테스트입니다.",
  "categoryCode": "S001",
  "templateType": "BASIC",
  "buttons": []
}'
```

응답

200수정 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/inspect

### 템플릿 검수 요청

등록·수정된 템플릿을 검수 요청합니다. 검수 요청 시 `templateStatusType`이 `PENDING`으로 변경됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| templateCode | string | 필수 | 템플릿 코드 |
| comment | string | — | 검수 요청 메모 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/inspect" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "templateCode": "string",
  "comment": "string"
}'
```

응답

200검수 요청 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └templateCode | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": {
    "templateCode": "TP-INFORMATION-TABLE-XXXXXXXX"
  }
}
```

post/v1/template/inspect/cancel

### 템플릿 검수 요청 취소

검수 요청 상태의 템플릿을 취소합니다. 요청 파라미터는 [검수 요청](https://bizppurio.github.io/napi#post-v1templateinspect)과 동일합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| templateCode | string | 필수 | 템플릿 코드 |
| comment | string | — | 검수 요청 메모 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/inspect/cancel" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "templateCode": "string",
  "comment": "string"
}'
```

응답

200취소 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ]
}
```

post/v1/template/inspect/history

### 템플릿 검수 요청 이력

템플릿의 검수 요청·취소·반려 이력을 시간 순으로 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/inspect/history" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "templateCode": "string"
}'
```

응답

200이력 조회 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | array<object> | — | 이력 배열 |
| └content | string | — | 이력 내용 (검수 요청 / 취소 / 반려 등) |
| └createdAt | string | — | 발생 시점 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "data": [
    {
      "content": "검수 요청",
      "createdAt": "2024-12-26 15:40:39"
    },
    {
      "content": "취소",
      "createdAt": "2024-12-26 15:41:06"
    }
  ]
}
```

post/v1/template/remove

### 템플릿 삭제

템플릿을 삭제합니다.

> ⚠️ 발송대기(`WAITING`)가 아닌 템플릿은 삭제 불가.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "string",
  "templateCode": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └templateCode | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "templateCode": "string"
  }
}
```

post/v1/template/search

### 최근 변경된 템플릿 조회

지정 기간 내 변경된 템플릿을 페이지네이션으로 조회합니다. `modifiedOnly: false`로 호출하면 미수정 템플릿도 포함됩니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| naverPartnerKey | string | 필수 | 파트너 키 |
| fromDate | string | 필수 | 조회 시작 일자 (`yyyyMMdd` 또는 `YYYY-MM-DD`) |
| toDate | string | — | 조회 종료 일자 — 미입력 시 현재 일자 |
| page | integer | — | — |
| count | integer | — | — |
| modifiedOnly | boolean | — | 수정된 값만 조회 (false 시 미수정 템플릿도 포함) |

```
curl -X POST "https://napi.bizppurio.com/v1/template/search" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "naverPartnerKey": "fAO8bJKWXXXXXXXX",
  "fromDate": "2024-12-25",
  "toDate": "2024-12-30",
  "page": 1,
  "count": 3
}'
```

응답

200검색 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | array<object> | — | 검색 결과 배열 |
| └id | string | — | — |
| └code | string | — | 템플릿 코드 |
| └partnerId | string | — | — |
| └freeOfCharge | boolean | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": [
    {
      "id": "string",
      "code": "string",
      "partnerId": "string",
      "freeOfCharge": true,
      "createdAt": "string",
      "modifiedAt": "string"
    }
  ]
}
```

## 그룹 템플릿

파트너 그룹에 속한 여러 파트너가 **공유**하는 그룹 템플릿 CRUD · 검수 (8개 엔드포인트).

요청·응답 페이로드는 [템플릿 관리](https://bizppurio.github.io/napi#템플릿)와 동일하며, 식별자만 `naverPartnerKey` → `templateGroupKey` 로 대체됩니다. 경로 패턴도 `/v1/template/*` → `/v1/template/group/*`.

post/v1/template/group/get

### 그룹 템플릿 조회

파트너 그룹에 등록된 그룹 템플릿을 조회합니다.

요청·응답 구조는 [템플릿 조회](https://bizppurio.github.io/napi#post-v1templateget)와 동일하며 `naverPartnerKey` 대신 **`templateGroupKey`** 를 사용합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/get" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "YT1EN2VAXXXXXXXX",
  "templateCode": "TP-GROUP-BASIC-XXXXXXXX"
}'
```

응답

200그룹 템플릿 상세

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/group/register

### 그룹 템플릿 생성

파트너 그룹용 템플릿을 신규 등록합니다. 페이로드는 [정보성 템플릿 생성](https://bizppurio.github.io/napi#post-v1templateregisterinformation) / [광고성 템플릿 생성](https://bizppurio.github.io/napi#post-v1templateregisterbenefit)과 동일하며, `naverPartnerKey` 대신 `templateGroupKey`를 사용합니다.

요청 본문

object

정보성/광고성 페이로드 + `templateGroupKey`

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/register" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "YT1EN2VAXXXXXXXX",
  "templateCode": "TP-GROUP-BASIC-XXXXXXXX",
  "text": "템플릿 등록 테스트입니다.",
  "categoryCode": "S001",
  "buttons": [
    {
      "type": "WEB_LINK",
      "buttonCode": "BTN-CODE-1",
      "buttonName": "웹 링크 버튼"
    }
  ]
}'
```

응답

200등록 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/group/modify

### 그룹 템플릿 수정

그룹 템플릿을 수정합니다. 동작 규칙은 [템플릿 수정](https://bizppurio.github.io/napi#post-v1templatemodify)과 동일하며, 식별자만 `templateGroupKey`로 대체됩니다.

요청 본문

object

수정할 필드 + `templateGroupKey` + `templateCode` (정보성/광고성 페이로드 동일 구조)

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/modify" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "dOn5qlguXXXXXXXX",
  "productCode": "INFORMATION",
  "templateCode": "TP-GROUP-BASIC-XXXXXXXX",
  "text": "템플릿 수정 테스트입니다.",
  "categoryCode": "S001",
  "templateType": "BASIC",
  "buttons": []
}'
```

응답

200수정 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | 템플릿 상세 데이터. `templateType`에 따라 일부 필드가 추가/생략됩니다.   - 정보성-BASIC: 공통 필드만 - 정보성-GIFT: `sampleCoupon` - 정보성-TABLE: `tableElements[]` + `pushNotice` - 광고성-BENEFIT\*: `benefit` 객체 |
| └id | string | — | — |
| └templateType | string | — | `CARD_PAYMENT`(productCode=`CARDINFO`)는 카드결제 알림 템플릿으로, 별도 채널에서 등록되며 NAPI 등록 엔드포인트로는 생성하지 않습니다.  = BASIC | IMAGE | GIFT | TABLE | CARD\_PAYMENT | BENEFIT | BENEFIT\_LMS | BENEFIT\_CAROUSEL\_COMMERCE | BENEFIT\_CAROUSEL\_FEED | BENEFIT\_LIST\_COMMERCE | BENEFIT\_LIST\_FEED |
| └productCode | string | — | = INFORMATION | BENEFIT | CARDINFO |
| └code | string | — | 템플릿 코드 |
| └text | string | — | — |
| └partnerId | string | — | — |
| └templateGroupKey | string | — | 그룹 템플릿일 때만 포함 |
| └categoryCode | string | — | — |
| └templateStatusType | string | — | 검수 상태 — REGISTERED(등록) / PENDING(검수요청) / APPROVED(검수완료) / REJECTED(반려)  = REGISTERED | PENDING | APPROVED | REJECTED |
| └templateSendingStatusType | string | — | 발송 상태 — WAITING(발송대기) / SENDING(발송중) / BLOCKED(차단). 발송대기 외 상태는 수정/삭제 불가.  = WAITING | SENDING | BLOCKED |
| └freeOfCharge | boolean | — | — |
| └buttons | array<object> | — | — |
| └sampleCoupon | allOf | — | — |
| └code | string(100) | 필수 | 쿠폰 코드 (한글/영문/숫자/`-`) |
| └name | string(20) | 필수 | 쿠폰 이름 |
| └endDate | string | 필수 | 쿠폰 만료일 (YYYY-MM-DD) |
| └publisher | string | — | 쿠폰 발행처 (생략 시 파트너 프로필명) |
| └imageUrl | string | — | 쿠폰 바코드 이미지 URL (300KB, 552×552 권장) |
| └pushNotice | string | — | — |
| └tableElements | array<object> | — | — |
| └benefit | object | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "id": "string",
    "templateType": "BASIC",
    "productCode": "INFORMATION",
    "code": "string",
    "text": "string",
    "partnerId": "string",
    "templateGroupKey": "string",
    "categoryCode": "string",
    "templateStatusType": "REGISTERED",
    "templateSendingStatusType": "WAITING",
    "freeOfCharge": true,
    "buttons": [
      {}
    ],
    "sampleCoupon": {
      "code": "string",
      "name": "string",
      "endDate": "string",
      "publisher": "string",
      "imageUrl": "string"
    },
    "pushNotice": "string",
    "tableElements": [
      {}
    ],
    "benefit": {},
    "createdAt": "string",
    "modifiedAt": "string"
  }
}
```

post/v1/template/group/remove

### 그룹 템플릿 삭제

그룹 템플릿을 삭제합니다. 발송대기(`WAITING`) 상태에서만 가능합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/remove" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "templateCode": "string"
}'
```

응답

200삭제 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └templateCode | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "templateCode": "string"
  }
}
```

post/v1/template/group/inspect

### 그룹 템플릿 검수 요청

그룹 템플릿의 검수를 요청합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| templateCode | string | 필수 | 템플릿 코드 |
| comment | string | — | 검수 요청 메모 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/inspect" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "templateCode": "string",
  "comment": "string"
}'
```

응답

200검수 요청 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | object | — | — |
| └templateCode | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": {
    "templateCode": "string"
  }
}
```

post/v1/template/group/inspect/cancel

### 그룹 템플릿 검수 요청 취소

그룹 템플릿 검수 요청을 취소합니다. 파라미터는 [그룹 템플릿 검수 요청](https://bizppurio.github.io/napi#post-v1templategroupinspect)과 동일.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| templateCode | string | 필수 | 템플릿 코드 |
| comment | string | — | 검수 요청 메모 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/inspect/cancel" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "templateCode": "string",
  "comment": "string"
}'
```

응답

200취소 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ]
}
```

post/v1/template/group/inspect/history

### 그룹 템플릿 검수 요청 이력

그룹 템플릿의 검수 요청·취소·반려 이력을 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| templateCode | string | 필수 | 템플릿 코드 |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/inspect/history" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "string",
  "templateCode": "string"
}'
```

응답

200이력 조회 성공

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | array<object> | — | 이력 배열 |
| └content | string | — | 이력 내용 (검수 요청 / 취소 / 반려 등) |
| └createdAt | string | — | 발생 시점 |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": [
    {
      "content": "string",
      "createdAt": "string"
    }
  ]
}
```

post/v1/template/group/search

### 최근 변경된 그룹 템플릿 조회

지정 기간 내 변경된 그룹 템플릿을 페이지네이션으로 조회합니다.

요청 본문

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| templateGroupKey | string | 필수 | 그룹 키 |
| fromDate | string | 필수 | 조회 시작 일자 (YYYY-MM-DD) |
| toDate | string | — | 조회 종료 일자 — 미입력 시 현재 |
| page | integer | — | — |
| count | integer | — | — |
| modifiedOnly | boolean | — | — |

```
curl -X POST "https://napi.bizppurio.com/v1/template/group/search" \
  -H "Authorization: Bearer {accessToken}" \
  -H "Content-Type: application/json" \
  -d '{
  "templateGroupKey": "dOn5qlguXXXXXXXX",
  "fromDate": "2024-11-05",
  "toDate": "2024-11-11",
  "page": 1,
  "count": 5
}'
```

응답

200검색 결과

| 파라미터 | 타입 | 필수 | 설명 |
| --- | --- | --- | --- |
| code | string | 필수 | 결과 코드 (200=성공 · 400=잘못된 요청 · 401=인증 없음 · 403=접근 권한 없음 · 404=없는 페이지 · 429=요청 한도 초과 · 500=내부 에러) |
| message | string | 필수 | — |
| errors | array<object> | — | 필드 검증 실패 시 상세 오류 배열 |
| └field | string | — | — |
| └value | string | — | — |
| └reason | string | — | — |
| data | array<object> | — | 검색 결과 배열 |
| └id | string | — | — |
| └code | string | — | 템플릿 코드 |
| └partnerId | string | — | — |
| └freeOfCharge | boolean | — | — |
| └createdAt | string | — | — |
| └modifiedAt | string | — | — |

응답 · 200

```
{
  "code": "200",
  "message": "요청 성공",
  "errors": [
    {
      "field": "string",
      "value": "string",
      "reason": "string"
    }
  ],
  "data": [
    {
      "id": "string",
      "code": "string",
      "partnerId": "string",
      "freeOfCharge": true,
      "createdAt": "string",
      "modifiedAt": "string"
    }
  ]
}
```

## 템플릿 타입·노출 예시

네이버 톡톡 템플릿은 **정보성(`productCode: INFORMATION`) 4종**과 **광고성·혜택(`productCode: BENEFIT`) 6종**으로 나뉩니다. 등록 시 `productCode`는 `templateType`에 따라 자동 고정되므로 요청에 포함하지 않습니다. 코드값 목록은 [코드 정의](https://bizppurio.github.io/response-codes)를 참고하세요.

## 정보성 템플릿

| templateType | 설명 | 핵심 필드 | 톡톡 말풍선 |
| --- | --- | --- | --- |
| `BASIC` | 텍스트 기본형 | `text`, `buttons` | 텍스트 + 버튼 |
| `IMAGE` | 이미지형 | `text`, `sampleImageHashId`, `buttons` | 상단 이미지 + 텍스트 + 버튼 |
| `GIFT` | 선물(쿠폰) | `text`, `sampleCoupon`, `couponDescription` | 쿠폰 카드 + 본문 |
| `TABLE` | 테이블형 | `pushNotice`, `tableInfo.elementList[]` | 썸네일 + 표/본문 + 버튼 (요소 1~6개) |

기본선물알림테이블N고객센터: 010-1234-5678'네이버톡톡'에서 문자 대신 톡톡으로발송된 정보성 메시지입니다.정보성 메시지 종류의 템플릿입니다. 이미지를 첨부할 수있고, 버튼도 추가 가능합니다.링크 바로가기1링크 바로가기2수신거부: 채팅창 설정 > 알림받기 관리톡톡 소식받기 취소P고객센터: 010-1234-5678'네이버톡톡'에서 문자 대신 톡톡으로발송된 정보성 메시지입니다.1234-5678-9012-3456교환권 유효기간: 2023.01.26.까지교환권 저장쿠폰 번호복사N고객센터: 010-1234-5678● 스마트톡톡 ⓘ그린편의점13,000원카드종류그린톡톡 카드승인취소 일시06/03 15:00자세한 포인트 적립 내역은그린톡톡 앱에서 확인 가능합니다.

### TABLE형 — 필드 → 화면 노출 위치

테이블형은 `tableInfo.elementList[]`의 각 필드가 톡톡 말풍선의 정해진 위치에 노출됩니다.

스마트톡톡①승인 취소②30,000원③④⑤그린카드김\*린님취소일시24.11.18⑥적립 포인트도 함께 취소⑦※ 카드별 이용내역 안내⑧포인트 확인하기① title — 타이틀② subtitle — 서브 타이틀③ strikeTitle — 타이틀 취소선④ thumbnailImageHashId / Url — 썸네일⑤ table[].title · content — 표 항목(1~10)⑥ text — 본문(테이블 미사용 시)⑦ additionalContent — 부가정보⑧ buttons — 버튼(요소당 최대 5)

## 광고성 혜택 템플릿

혜택 소재는 모두 `benefit` 객체 안에 담습니다. **타입별 `introduction`·`products` 요건, 할인·유효기간 규칙은 [혜택 메시지 구성](https://bizppurio.github.io/napi#혜택-메시지-구성)** 에서 다룹니다.

| templateType | 설명 |
| --- | --- |
| `BENEFIT` | 기본형 — 단일 말풍선(이미지 + 본문 + 쿠폰칩 + 버튼) |
| `BENEFIT_LMS` | LMS형 — 장문 본문(최대 2,000자), 이미지·캐러셀 없음 |
| `BENEFIT_CAROUSEL_COMMERCE` | 캐러셀 커머스형 — 가로 상품 카드, **가격(원가·할인가·할인율) 노출** + 인트로 필수 |
| `BENEFIT_CAROUSEL_FEED` | 캐러셀 피드형 — 가로 상품 카드, **설명·버튼 노출**(가격 없음) |
| `BENEFIT_LIST_COMMERCE` | 리스트 커머스형 — 세로 상품 리스트, **가격 노출** + 인트로 필수 |
| `BENEFIT_LIST_FEED` | 리스트 피드형 — 세로 상품 리스트, **가격 없음** + 인트로 필수 |

> 💡 **커머스형 ↔ 피드형 구분**: *커머스형*은 상품 가격을 노출(쇼핑 중심), *피드형*은 가격 대신 상품 설명·버튼을 노출합니다. *캐러셀*은 가로 스크롤, *리스트*는 세로 나열입니다.

기본형BENEFITSEASON SALE 80%곧 종료 시즌 막바지 ~70% 할인역대급 할인 오늘 단 하루만!5시간 후 종료, 오늘 출발 상품바로가기2,000원 할인쿠폰10,000원 이상 결제 시 사용가능사용기간 21.10.12 ~ 21.12.1COUPON↓LMS형BENEFIT\_LMS[안내] 보이스피싱 예방 수칙안녕하세요, 고객님.진화하는 신종 보이스피싱·스미싱,예방이 최우선입니다!① 출처 불명 문자·링크 클릭 금지② 앱 설치 시 권한 꼭 확인하기③ 휴대폰 백신 프로그램 설치④ 소액결제 차단 기능 설정⑤ 명의도용방지 서비스 가입⑥ 의심 시 경찰(112)에 신고▶ 문의 : 모바일 고객센터 114(무료)펼쳐서 더보기 ∨캐러셀 커머스형BENEFIT\_CAROUSEL\_COMMERCE‹ 1/5 ›니트 가디건69,000원 30%구매하기2,000원 할인쿠폰10,000원 이상↓신상 바지78,000원구매2,000원캐러셀 피드형BENEFIT\_CAROUSEL\_FEED‹ 1/6 ›가을 신상 코트멋진 가을 보내세요구매하기니트 가디건아웃핏구매리스트 커머스형BENEFIT\_LIST\_COMMERCE메인 이미지지금부터 시즌 세일!특별한 아웃핏으로 멋진 가을니트 가디건69,000원 30%127,000신상 바지78,000원신상 코트129,000원리스트 피드형BENEFIT\_LIST\_FEED메인 이미지2023 F/W 신상품 이벤트특별한 추가 할인 이벤트Monthly Event - August아이들과 함께 특별한 시간가을 신상 코트 특가

> ℹ️ 위 목업은 레이아웃 구조 이해용입니다. 실제 노출은 기기 해상도·네이버 톡톡 정책에 따라 달라질 수 있습니다.

### 혜택 탭 · 검색 피드 노출

네이버 앱 푸시·알림으로 도착한 메시지는 **발송 후 7일간** `알림 목록·혜택 추천`, `혜택 피드`, `톡톡 메시지` 등 여러 지면에 노출됩니다. 각 지면에는 `feedDisplayImageHashId`(598×300)·`benefit.title`·`benefitTypes`·`discountInfo`가 함께 표시됩니다.

알림 목록·혜택 추천혜택 피드톡톡 메시지오늘 받은 알림D자주 구매한 DaouStore 소식 알림 ›[1,000원 할인 쿠폰]DaouStore의 혜택을 확인해보세요!N네이버 · 4시간 전네플스앱 오픈 위크, 오전 10시[~1만원] 선착순 쿠폰 받기 ▶이전 알림전체혜택·이벤트금융·자산활동·소식네이버여행상품 · 어제 04:58(광고) 연말 출발 비행기표까지1인당 1만원 할인 + 적립네이버페이 · 어제 12:00(광고) 새 카드 이벤트 최대 49만원상당 돌려드려요우리집 · 어제 10:02(광고) 이번 달 관리비 안내곧 끝나요! 놓치면 아쉬운 혜택✕최근 자주 찾은DaouStore5번 구매16일 전 구매비즈뿌리오몰5번 구매3개월 전 구매브랜드스토어3,000원 쿠폰7개 더보기 ∨최근 5번 구매한SEASON OFF상품 최대 50% 세일DaouStore놓치면 후회 시즌오프 ✨스토어에서 1,000원 할인 쿠폰으로특가에 추가 할인까지 놓치지 마세요!🎟 1,000원 할인 쿠폰받기 ↓DaouStore상담가능보통 25분 내 응답, 응답률 99%🔔 ☰수신거부: 알림설정 > 알림받기 취소3.17.(월)광고 02-0000-0000SEASON OFF놓치면 후회! DaouStore 시즌오프 ✨스토어에서 1,000원 할인 쿠폰으로특가에 추가 할인까지 놓치지 마세요!대형 수하물 캐리어 77cm250,000원300,000원기내용 여행 캐리어 57cm200,000원250,000원여행용 파우치 풀세트45,000원60,000원시즌오프기획베스트셀러

## 템플릿 상태

| `templateStatusType` (검수) | 의미 |
| --- | --- |
| `REGISTERED` | 등록 |
| `PENDING` | 검수요청 |
| `APPROVED` | 검수완료 |
| `REJECTED` | 반려 |

| `templateSendingStatusType` (발송) | 의미 |
| --- | --- |
| `WAITING` | 발송대기 |
| `SENDING` | 발송중 |
| `BLOCKED` | 차단 |

> 검수 완료 후 발송 전까지 `WAITING`, 발송 시작 시 `SENDING` 으로 전환됩니다. **`WAITING` 외 상태는 수정·삭제 불가**.

## 파트너 계정 상태

파트너 조회 응답의 `accountStatusType` 값입니다.

| 코드 | 설명 |
| --- | --- |
| `NORMAL` | 사용중 |
| `PAUSE` | 사용중지 |
| `SYSPAUSE` | 시스템사용중지 |
| `PREBLOCK` | 사용보류 |
| `BLOCK` | 사용제재 |
| `DELETED` | 삭제 |

## 템플릿 카테고리 코드

등록 시 발송 상황에 맞는 `categoryCode` 를 입력합니다. 혜택(B) 코드는 `productCode: BENEFIT` 전용입니다.

#### 게임 (G)

| 코드 | 중분류 |  | 코드 | 중분류 |
| --- | --- | --- | --- | --- |
| `G001` | 취소예정 |  | `G007` | 입금확인(삽니다) |
| `G002` | 종료예정 |  | `G008` | 판매신청 |
| `G003` | 취소 |  | `G009` | 흥정신청 |
| `G004` | 즉시구매 |  | `G010` | 흥정수락 |
| `G005` | 종료 |  | `G011` | 재흥정 |
| `G006` | 입금확인(팝니다) |  |  |  |

#### 고객 (C)

| 코드 | 중분류 |
| --- | --- |
| `C001` | 방문완료 |
| `C002` | 방문 담당자안내 |
| `C003` | A/S 완료안내 |
| `C004` | 필수고지안내 |

#### 금융 (F)

| 코드 | 중분류 |  | 코드 | 중분류 |
| --- | --- | --- | --- | --- |
| `F001` | 입금알림 |  | `F005` | 종가 알림 |
| `F002` | 출금알림 |  | `F006` | 체결내역알림 |
| `F003` | 목표가 도달 |  | `F007` | 정기적 수신동의 |
| `F004` | 수익률 도달 |  | `F009` | 금융 일반 |

#### 배송 (D)

| 코드 | 중분류 |  | 코드 | 중분류 |
| --- | --- | --- | --- | --- |
| `D001` | 택배사 도착 |  | `D006` | 대리수령완료 |
| `D002` | 배송중 |  | `D007` | 위탁배송지 배송완료 |
| `D003` | 도착예정 |  | `D008` | 반품수거방문 |
| `D004` | 배송완료 |  | `D009` | 배송 일반 |
| `D005` | 배송시간안내 |  |  |  |

#### 선물 (P)

| 코드 | 중분류 |
| --- | --- |
| `P001` | 선물도착알림 |

#### 쇼핑 (S)

| 코드 | 중분류 |  | 코드 | 중분류 |
| --- | --- | --- | --- | --- |
| `S001` | 입금안내 |  | `S020` | 배송지연 |
| `S002` | 입금요청 |  | `S021` | 상품유의사항 |
| `S003` | 주문결제완료 |  | `S022` | 회원그룹변경 |
| `S004` | 무통장입금완료 |  | `S023` | 주문완료 |
| `S005` | 배송대기 |  | `S024` | 상품준비중 |
| `S006` | 발송조치 |  | `S025` | 반품완료 |
| `S007` | 배송완료 |  | `S026` | 교환완료 |
| `S008` | 취소접수 |  | `S027` | 결제취소 |
| `S009` | 반품접수 |  | `S028` | 부분취소 |
| `S010` | 문의답변완료 |  | `S029` | 픽업상품 미수령 |
| `S011` | 교환접수 |  | `S030` | 쿠폰 만료 안내 |
| `S012` | 환불완료 |  | `S031` | 수신거부 처리 |
| `S013` | 회원가입 |  | `S032` | 정기결제 신청 |
| `S014` | 회원인증 안내 |  | `S033` | 정기결제 취소 |
| `S015` | 비밀번호 안내 |  | `S034` | 정기결제상품 품절 |
| `S016` | 회원탈퇴 |  | `S035` | 정기결제 건너뛰기 |
| `S017` | 재입고 안내 |  | `S036` | 정기결제 예정일 |
| `S018` | 적립금 소멸 안내 |  | `S037` | 정기결제 완료 |
| `S019` | 본인확인 인증번호 발송 |  | `S038` | 정기결제 실패 |

#### 여행 (T)

| 코드 | 중분류 |  | 코드 | 중분류 |
| --- | --- | --- | --- | --- |
| `T001` | 예약확정 |  | `T004` | 결제 요청 |
| `T002` | 예약취소 |  | `T005` | 맞춤여행 |
| `T003` | 바우처발송 |  | `T006` | 여행안내 |

#### 카드이용알림 (R)

| 코드 | 중분류 |
| --- | --- |
| `R006` | 가입완료 |
| `R007` | 가입실패 |
| `R014` | 가입확인알림 |
| `R018` | 카드이용관련안내 |

#### 혜택 (B) — `productCode: BENEFIT` 전용

| 코드 | 중분류 |
| --- | --- |
| `B001` | 쿠폰 |
| `B002` | 적립금 |
| `B003` | 추가 증정 |
| `B004` | 기타 이벤트 |
| `B005` | 소식 |

## 혜택 메시지 구성

광고성(`productCode: BENEFIT`) 템플릿의 `benefit` 객체 구성 규칙입니다. 타입별 레이아웃은 [템플릿 타입·노출 예시](https://bizppurio.github.io/napi#템플릿-타입·노출-예시)를 참고하세요.

## 타입별 구성 요건

| templateType | `introduction` | `products` 개수 | 비고 |
| --- | --- | --- | --- |
| `BENEFIT` (기본형) | — | — | `text`(360자) + `sampleImageHashId` |
| `BENEFIT_LMS` | — | — | `text`(2,000자), `benefitTypes`=`EVENT` 고정 |
| `BENEFIT_CAROUSEL_COMMERCE` | **필수** (`description` 60자) | 2~5 | 상품 `originalPrice`/`currentPrice` |
| `BENEFIT_CAROUSEL_FEED` | — | 2~6 | 상품 `description`(100자)·버튼 1개 필수 |
| `BENEFIT_LIST_COMMERCE` | **필수** (`description` 70자) | 3~6 | 상품 가격 노출 |
| `BENEFIT_LIST_FEED` | **필수** (`description` 70자) | 2~3 | — |

## 할인 정보

`benefitTypes`에 `PRODUCT`·`DELIVERY`·`ORDER`·`POINT`가 포함되면 `discountInfo` 객체가 필수입니다.

| discountType | 필수 필드 | 설명 |
| --- | --- | --- |
| `AMOUNT` | `discountAmount` | 정액 할인 |
| `RATE` | `discountRate`, `maxDiscountAmount` | 정률 할인(최대 할인액 동반) |
| `POINT` | `accumulateAmount` | 적립 |

- `minimumOrderAmount` — `POINT`/`PRODUCT`/`DELIVERY`/`ORDER` 포함 시 **1,000원 이상**.
- `landingPageUrl` — 동일 조건에서 필수(혜택 클릭 시 이동 페이지).
- `benefitKindType` `COUPON`/`POINT`, `couponPublicationType` `DOWNLOAD`/`IMMEDIATE`.

## 유효기간

`validityInfo` 객체로 혜택 유효기간을 지정합니다.

- `PERIOD` — `validStartedAt` ~ `validEndedAt` (YYYY-MM-DD).
- `EXPIRATION` — 발급일 기준 `validDays` 일간.

## 검색결과 노출

`benefit.searchResultExposure` 값:

- **`true`** — 검색결과 노출. 단 **개인화 변수(`#{}`) 사용 불가** — 등록 시 버튼 URL을 확정해야 하며, `#{}` 포함 시 등록 실패.
- **`false`**(기본) — 정보성 알림처럼 타이틀·기본형/LMS형 본문에 `#{}` 개인화 변수 사용 가능.

## 소식 카테고리 제약

`categoryCode: B005`(소식) 선택 시 혜택탭 관련 파라미터(`categoryType`·`benefitTypes`·`discountInfo`·`validityInfo`·`feedDisplayImageHashId`·`feedDisplayEndedAt`)를 넣으면 **등록 실패**하며, `searchResultExposure`는 항상 `false`로 고정됩니다.

## 광고 수신거부

`blockCallNumber`(080 번호, `080-123-1234` 형식)와 `blockMessageUrl`(https URL) **둘 중 하나는 반드시** 입력합니다.

## 혜택 카테고리

혜택 탭 [인기] 분류에 활용되는 `categoryType` 값입니다.

| 코드 | 분류 |  | 코드 | 분류 |
| --- | --- | --- | --- | --- |
| `FASHION` | 패션 |  | `SPORTS_LEISURE` | 스포츠·레저 |
| `BEAUTY` | 뷰티 |  | `NECESSITIES` | 생활용품 |
| `DIGITAL_APPLIANCE` | 디지털·가전 |  | `BOOK_HOBBY` | 도서·취미 |
| `LIVING` | 리빙 |  | `FINANCE` | 금융 |
| `FOOD` | 식품 |  | `ETC` | 기타 |
| `KIDS` | 출산·육아 |  |  |  |

## 혜택 유형

`benefitTypes` 는 최소 1개·최대 2개 선택하며, LMS형은 `EVENT` 로 고정됩니다.

| 코드 | 설명 | `discountInfo` 필수 |
| --- | --- | --- |
| `TIMESALE` | 타임 세일 | N |
| `GIFT` | 사은품 증정 | N |
| `BONUS` | 1+1 | N |
| `BRANDDAY` | 브랜드 데이 | N |
| `EVENT` | 이벤트 | N |
| `PRODUCT` | 상품 할인 | **Y** |
| `DELIVERY` | 배송비 할인 | **Y** |
| `ORDER` | 장바구니 할인 | **Y** |
| `POINT` | 적립 | **Y** |

## 버튼·이미지 규격

## 버튼 타입

모든 버튼은 `type`·`buttonName`(기본형 20자/커머스형 8자)을 가지며, `searchResultExposure: false`일 때 `buttonCode`로 URL 개인화가 가능합니다.

| type | 설명 | 필수 파라미터 |
| --- | --- | --- |
| `WEB_LINK` | 웹페이지로 이동 | `mobileUrl` (또는 `pcUrl` 중 1개 이상) |
| `APP_LINK` | 앱 스킴/웹링크로 이동 | `iOsAppScheme`·`aOsAppScheme` (발송 시 모두) |

- 한 버튼에서 `WEB_LINK`와 `APP_LINK`를 중복 사용할 수 없습니다.
- 발송(보내기) API로 발송 시 `WEB_LINK`는 `mobileUrl`·`pcUrl` 모두, `APP_LINK`는 `iOsAppScheme`·`aOsAppScheme` 모두 필수입니다.

## 템플릿 타입별 버튼 규칙

혜택 템플릿은 타입별로 버튼 사용 규칙이 다릅니다.

| 항목 | 기본형 | LMS형 | 캐러셀 커머스 | 캐러셀 피드 | 리스트 커머스 | 리스트 피드 |
| --- | --- | --- | --- | --- | --- | --- |
| 버튼 사용 | 선택 | 없음 | 필수 | 필수 | 필수 | 필수 |
| 버튼명 수정 | 가능 | — | `구매하기` 고정 | 가능 | `구매하기` 고정 | 가능 |
| 버튼 개수 | 최대 2 | — | 카드당 최대 1 | 카드당 최대 2 | 3~6 | 2~3 |
| 앱 링크(`APP_LINK`) | 가능 | — | 가능 | 가능 | 가능 | 가능 |

> 리스트형의 버튼 개수는 상품(`products`) 항목 수와 같습니다(각 상품이 링크 1개). 정보성 템플릿의 버튼은 최대 5개입니다.

## 이미지 규격

이미지 업로드 API([URL](https://bizppurio.github.io/napi#post-v1imageuploadurl) / [파일](https://bizppurio.github.io/napi#post-v1imageuploadfile))로 `imageHashId`를 발급받아 템플릿에 첨부합니다.

| imageType | 용도 | 해상도 | 최대 크기 | 포맷 |
| --- | --- | --- | --- | --- |
| `content`(기본) | 일반 이미지 (말풍선·썸네일) | 552×552 권장(제한 없음, 미달 시 크롭) | 300 KB | JPG·JPEG·PNG·GIF |
| `feed` | 혜택 피드 노출 이미지 | **598×300 고정** (그 외 업로드 에러) | 300 KB | JPG·JPEG·PNG·GIF |

- 혜택 템플릿의 `benefit.feedDisplayImageHashId`는 반드시 `feed`(598×300)로 업로드한 해시를 사용합니다.
- 쿠폰(`GIFT`)이 첨부된 경우 이미지 첨부는 불가합니다.


---

# 응답 코드

> 원본: [https://bizppurio.github.io/response-codes/](https://bizppurio.github.io/response-codes/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

REFERENCE · 응답 코드

# 응답 코드

서비스별 결과 코드입니다. 응답 본문의 `code` 필드가 처리 결과를 나타내며(`1000`=성공), 발송 후 통신사·카카오·RCS·네이버가 반환하는 채널별 **전송 결과 코드**는 각 서비스 탭의 품목별 표를 참고하세요.

HTTP

비즈뿌리오 API는 **인증 오류(4xx)와 Rate Limit(429)**를 제외하면 HTTP 상태를 따로 명시하지 않고 **200으로 응답**합니다. 실제 성공/실패는 응답 본문의 `code` 값으로 판단하세요.

989개 코드

| 서비스 | 코드 | HTTP | 설명 |
| --- | --- | --- | --- |
| 메시지 API | 1000 | 200 | 공통  성공 |
| 메시지 API | 2000 | 400 | 공통  메시지가 유효하지 않음 |
| 메시지 API | 3000 | 400 | 공통  비즈뿌리오 계정에 접속 허용 IP가 등록되어 있지 않음 |
| 메시지 API | 3001 | 400 | 공통  인증 토큰 발급 호출 시 Basic Authentication 정보가 유효하지 않음 |
| 메시지 API | 3002 | 400 | 공통  토큰이 유효하지 않음 |
| 메시지 API | 3003 | 400 | 공통  IP가 유효하지 않음 |
| 메시지 API | 3004 | 400 | 공통  계정이 유효하지 않음 |
| 메시지 API | 3005 | 400 | 공통  인증 정보가 유효하지 않음 (Bearer) |
| 메시지 API | 3006 | 400 | 공통  비즈뿌리오 계정이 존재하지 않음 |
| 메시지 API | 3007 | 400 | 공통  비즈뿌리오 계정의 암호가 유효하지 않음 |
| 메시지 API | 3008 | 400 | 공통  비즈뿌리오에 허용된 접속 수를 초과함 |
| 메시지 API | 3009 | 400 | 공통  비즈뿌리오 계정이 중지 상태 |
| 메시지 API | 3010 | 400 | 공통  비즈뿌리오 계정에 등록된 접속 허용 IP와 일치하지 않음 |
| 메시지 API | 3011 | 400 | 공통  비즈뿌리오 내에서 알 수 없는 오류 |
| 메시지 API | 3012 | 400 | 공통  비즈뿌리오에 존재하지 않는 메시지 (예: 보관 주기 35일 경과) |
| 메시지 API | 3013 | 400 | 공통  완료 처리되지 않은 메시지 (예: 통신사로부터 결과 미수신) |
| 메시지 API | 3014 | 400 | 공통  데이터 포맷 에러 |
| 메시지 API | 5000 | 400 | 공통  전송 결과 재 요청 실패 |
| 메시지 API | 5001 | 404 | 공통  요청한 URI 리소스가 존재하지 않음 |
| 메시지 API | 5002 | 429 | 공통  너무 많은 요청 ( Rate Limit 초과) |
| 메시지 API | 9000 | 500 | 공통  알 수 없는 오류 |
| 메시지 API | 5003 | 502 | 공통  알 수 없는 오류 |
| 메시지 API | 5004 | 503 | 공통  너무 많은 커넥션 |
| 메시지 API | 5005 | 504 | 공통  알 수 없는 오류 |
| 메시지 API | 4100 | – | SMS › 전체 코드  전달 |
| 메시지 API | 4400 | – | SMS › 전체 코드  음영 지역 |
| 메시지 API | 4401 | – | SMS › 전체 코드  단말기 전원 꺼짐 |
| 메시지 API | 4402 | – | SMS › 전체 코드  단말기 메시지 저장 초과 |
| 메시지 API | 4403 | – | SMS › 전체 코드  메시지 삭제됨 |
| 메시지 API | 4404 | – | SMS › 전체 코드  가입자 위치 정보 없음 |
| 메시지 API | 4405 | – | SMS › 전체 코드  단말기 BUSY |
| 메시지 API | 4410 | – | SMS › 전체 코드  잘못된 번호 |
| 메시지 API | 4411 | – | SMS › 전체 코드  NPDB 에러 |
| 메시지 API | 4412 | – | SMS › 전체 코드  착신 거절 |
| 메시지 API | 4413 | – | SMS › 전체 코드  SMSC 형식 오류 |
| 메시지 API | 4414 | – | SMS › 전체 코드  비가입자, 결번, 서비스 정지 |
| 메시지 API | 4420 | – | SMS › 전체 코드  기타 에러 |
| 메시지 API | 4421 | – | SMS › 전체 코드  타임아웃 |
| 메시지 API | 4422 | – | SMS › 전체 코드  단말기 일시 정지 |
| 메시지 API | 4423 | – | SMS › 전체 코드  단말기 착신 거부 |
| 메시지 API | 4424 | – | SMS › 전체 코드  URL SMS 미지원 휴대폰 |
| 메시지 API | 4425 | – | SMS › 전체 코드  단말기 호 처리 중 |
| 메시지 API | 4426 | – | SMS › 전체 코드  재시도 한도 초과 |
| 메시지 API | 4427 | – | SMS › 전체 코드  기타 단말기 문제 |
| 메시지 API | 4428 | – | SMS › 전체 코드  시스템 에러 |
| 메시지 API | 4430 | – | SMS › 전체 코드  스팸 |
| 메시지 API | 4431 | – | SMS › 전체 코드  발송 제한 수신거부 (스팸) |
| 메시지 API | 4432 | – | SMS › 전체 코드  회신 번호 차단 (개인) |
| 메시지 API | 4433 | – | SMS › 전체 코드  회신 번호 차단 (기업) |
| 메시지 API | 4434 | – | SMS › 전체 코드  회신 번호 사전 등록제에 의한 미등록 차단 |
| 메시지 API | 4435 | – | SMS › 전체 코드  KISA 신고 스팸 회신 번호 차단 |
| 메시지 API | 4436 | – | SMS › 전체 코드  회신 번호 사전 등록제 번호 규칙 위반 |
| 메시지 API | 4443 | – | SMS › 전체 코드  스팸 차단 |
| 메시지 API | 6600 | – | LMS / MMS › 전체 코드  전달 |
| 메시지 API | 6601 | – | LMS / MMS › 전체 코드  타임 아웃 |
| 메시지 API | 6602 | – | LMS / MMS › 전체 코드  핸드폰 호 처리 중 |
| 메시지 API | 6603 | – | LMS / MMS › 전체 코드  음영 지역 |
| 메시지 API | 6604 | – | LMS / MMS › 전체 코드  전원이 꺼져 있음 |
| 메시지 API | 6605 | – | LMS / MMS › 전체 코드  메시지 저장 개수 초과 |
| 메시지 API | 6606 | – | LMS / MMS › 전체 코드  잘못된 번호 |
| 메시지 API | 6607 | – | LMS / MMS › 전체 코드  서비스 일시 정지 |
| 메시지 API | 6608 | – | LMS / MMS › 전체 코드  기타 단말기 문제 |
| 메시지 API | 6609 | – | LMS / MMS › 전체 코드  착신 거절 |
| 메시지 API | 6610 | – | LMS / MMS › 전체 코드  기타 에러 |
| 메시지 API | 6611 | – | LMS / MMS › 전체 코드  통신사의 SMC 형식 오류 |
| 메시지 API | 6612 | – | LMS / MMS › 전체 코드  게이트웨이의 형식 오류 |
| 메시지 API | 6613 | – | LMS / MMS › 전체 코드  서비스 불가 단말기 |
| 메시지 API | 6614 | – | LMS / MMS › 전체 코드  핸드폰 호 불가 상태 |
| 메시지 API | 6615 | – | LMS / MMS › 전체 코드  SMC 운영자에 의해 삭제 |
| 메시지 API | 6616 | – | LMS / MMS › 전체 코드  통신사의 메시지 큐 초과 |
| 메시지 API | 6617 | – | LMS / MMS › 전체 코드  통신사의 스팸 처리 |
| 메시지 API | 6618 | – | LMS / MMS › 전체 코드  공정위의 스팸 처리 |
| 메시지 API | 6619 | – | LMS / MMS › 전체 코드  게이트웨이의 스팸 처리 |
| 메시지 API | 6620 | – | LMS / MMS › 전체 코드  발송 건수 초과 |
| 메시지 API | 6621 | – | LMS / MMS › 전체 코드  메시지의 길이 초과 |
| 메시지 API | 6622 | – | LMS / MMS › 전체 코드  잘못된 번호 형식 |
| 메시지 API | 6623 | – | LMS / MMS › 전체 코드  잘못된 데이터 형식 |
| 메시지 API | 6624 | – | LMS / MMS › 전체 코드  MMS 정보를 찾을 수 없음 |
| 메시지 API | 6625 | – | LMS / MMS › 전체 코드  NPDB 에러 |
| 메시지 API | 6626 | – | LMS / MMS › 전체 코드  080 수신거부 (SPAM) |
| 메시지 API | 6627 | – | LMS / MMS › 전체 코드  발송 제한 수신거부 (SPAM) |
| 메시지 API | 6628 | – | LMS / MMS › 전체 코드  회신 번호 차단 (개인) |
| 메시지 API | 6629 | – | LMS / MMS › 전체 코드  회신 번호 차단 (기업) |
| 메시지 API | 6630 | – | LMS / MMS › 전체 코드  서비스 불가 번호 |
| 메시지 API | 6631 | – | LMS / MMS › 전체 코드  회신 번호 사전 등록제에 의한 미등록 차단 |
| 메시지 API | 6632 | – | LMS / MMS › 전체 코드  KISA 신고 스팸 회신 번호 차단 |
| 메시지 API | 6633 | – | LMS / MMS › 전체 코드  회신 번호 사전 등록제 번호 규칙 위반 |
| 메시지 API | 6641 | – | LMS / MMS › 전체 코드  스팸 차단 |
| 메시지 API | 6670 | – | LMS / MMS › 전체 코드  첨부파일 사이즈 초과 (60K) |
| 메시지 API | 7000 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  정상 |
| 메시지 API | 7058 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  요청한 데이터가 없음 (발신프로필을 찾을 수 없음 등) |
| 메시지 API | 7081 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  권한 없음 (브랜드메시지 API 사용 불가 허브파트너) |
| 메시지 API | 7101 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  파라미터 형식 오류 |
| 메시지 API | 7103 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  발신 프로필 키가 유효하지 않음 |
| 메시지 API | 7106 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  삭제된 발신프로필 |
| 메시지 API | 7107 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  차단 상태의 발신프로필 |
| 메시지 API | 7108 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  차단 상태의 카카오톡 채널 |
| 메시지 API | 7109 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  닫힘 상태의 카카오톡 채널 |
| 메시지 API | 7110 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  삭제된 카카오톡 채널 |
| 메시지 API | 7111 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  삭제대기 상태의 카카오톡 채널 |
| 메시지 API | 7112 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  유효하지 않은 사업자등록번호 |
| 메시지 API | 7113 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  유효하지 않은 app 연결 |
| 메시지 API | 7115 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  유효하지 않은 app user id 요청 |
| 메시지 API | 7125 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  메시지 차단 상태의 카카오톡 채널 |
| 메시지 API | 7126 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  해당 message type에서 사용할 수 없는 response\_method 로 요청 |
| 메시지 API | 7127 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  채널 메시지 제재 상태로 인한 메시지 전송 실패 |
| 메시지 API | 7128 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  해당 타겟팅 옵션 사용할 수 없음 |
| 메시지 API | 7133 | – | 카카오 비즈메시지 › 성공·발신프로필 관련  템플릿 메시지 타입과 chat\_bubble\_type 파라미터 불일치 |
| 메시지 API | 7203 | – | 카카오 비즈메시지 › 메시지 검증  (테스트 발송) 친구톡 전송 시 친구 대상이 아님 |
| 메시지 API | 7204 | – | 카카오 비즈메시지 › 메시지 검증  메시지 내용이 템플릿과 일치하지 않음 |
| 메시지 API | 7205 | – | 카카오 비즈메시지 › 메시지 검증  내부 시스템 오류로 이미지 정보를 읽어오는데 실패 |
| 메시지 API | 7206 | – | 카카오 비즈메시지 › 메시지 검증  시리얼넘버 형식 불일치 |
| 메시지 API | 7300 | – | 카카오 비즈메시지 › 처리·전송 결과  기타 오류 |
| 메시지 API | 7304 | – | 카카오 비즈메시지 › 처리·전송 결과  지원하지 않는 스펙 |
| 메시지 API | 7305 | – | 카카오 비즈메시지 › 처리·전송 결과  성공 불확실 (30일 이내 수신 가능) |
| 메시지 API | 7306 | – | 카카오 비즈메시지 › 처리·전송 결과  카카오 시스템 오류 |
| 메시지 API | 7307 | – | 카카오 비즈메시지 › 처리·전송 결과  처리가 지연된 메시지 |
| 메시지 API | 7308 | – | 카카오 비즈메시지 › 처리·전송 결과  전화번호 오류 |
| 메시지 API | 7311 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지가 존재하지 않음 |
| 메시지 API | 7314 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지 길이 제한 초과 |
| 메시지 API | 7315 | – | 카카오 비즈메시지 › 처리·전송 결과  템플릿 없음 |
| 메시지 API | 7318 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지를 전송할 수 없음 |
| 메시지 API | 7319 | – | 카카오 비즈메시지 › 처리·전송 결과  톡 유저가 아님 |
| 메시지 API | 7320 | – | 카카오 비즈메시지 › 처리·전송 결과  알림톡 수신 차단 |
| 메시지 API | 7321 | – | 카카오 비즈메시지 › 처리·전송 결과  카카오톡 최소 버전 미지원 |
| 메시지 API | 7322 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지 발송 불가 시간 |
| 메시지 API | 7323 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지에 포함된 비디오를 전송할 수 없음 |
| 메시지 API | 7324 | – | 카카오 비즈메시지 › 처리·전송 결과  메시지에 포함된 이미지를 전송할 수 없음 |
| 메시지 API | 7325 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  변수 글자수 제한 초과 |
| 메시지 API | 7326 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  상담/봇 전환 버튼 글자수 제한 초과 |
| 메시지 API | 7327 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  버튼/바로 연결 내용과 템플릿 불일치 |
| 메시지 API | 7328 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  메시지 강조 표기 타이틀과 템플릿 불일치 |
| 메시지 API | 7329 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  메시지 강조 표기 타이틀 길이 제한 초과 (50자) |
| 메시지 API | 7330 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  메시지 타입과 템플릿 강조유형이 일치하지 않음 |
| 메시지 API | 7331 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  헤더가 템플릿과 일치하지 않음 |
| 메시지 API | 7332 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  헤더 길이 제한 초과 (16자) |
| 메시지 API | 7333 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 하이라이트가 템플릿과 일치하지 않음 |
| 메시지 API | 7334 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 하이라이트 타이틀 길이 제한 초과 |
| 메시지 API | 7335 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 하이라이트 디스크립션 길이 제한 초과 |
| 메시지 API | 7336 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 리스트가 템플릿과 일치하지 않음 |
| 메시지 API | 7337 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 리스트의 아이템 디스크립션 길이 제한 초과 |
| 메시지 API | 7338 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 요약정보가 템플릿과 일치하지 않음 |
| 메시지 API | 7339 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 요약정보의 디스크립션 길이 제한 초과 (14자) |
| 메시지 API | 7340 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  아이템 요약정보의 디스크립션에 허용되지 않은 문자 포함 |
| 메시지 API | 7341 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  와이드 아이템 리스트 갯수 최대·최소 갯수 불일치 |
| 메시지 API | 7342 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  대표링크가 템플릿과 일치하지 않음 |
| 메시지 API | 7343 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  이미지 변수 개수 템플릿 불일치 |
| 메시지 API | 7344 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  커머스 쿠폰 변수 템플릿 불일치 |
| 메시지 API | 7345 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  커머스 정보 변수 템플릿 불일치 |
| 메시지 API | 7346 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  부가 정보 최대 길이 제한 오류 |
| 메시지 API | 7347 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  커머스 정보 상품명 최대 길이 제한 오류 |
| 메시지 API | 7348 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  유효하지 않은 그룹 태그 키 입력 |
| 메시지 API | 7350 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  수신동의거부 스펙 (N타입) 미지원 |
| 메시지 API | 7351 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  캐러셀 아이템 리스트 갯수 최소·최대 불일치 |
| 메시지 API | 7352 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  캐러셀 아이템 메시지 길이 OVER |
| 메시지 API | 7353 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  캐러셀이 템플릿과 일치하지 않음 |
| 메시지 API | 7356 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  와이드 리스트 타이틀 길이 제한 오류 |
| 메시지 API | 7358 | – | 카카오 비즈메시지 › 변수·템플릿 검증 — 7325 ~ 7358  캐러셀 헤더 길이 제한 오류 |
| 메시지 API | 7400 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  입력값 오류 발생 |
| 메시지 API | 7401 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  Request Body Parsing Error |
| 메시지 API | 7410 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  시리얼 넘버 오류 |
| 메시지 API | 7411 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  시리얼 넘버 Prefix 날짜 오류 |
| 메시지 API | 7412 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  Template 요청 오류 |
| 메시지 API | 7413 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  유효한 허브파트너를 찾을 수 없음 |
| 메시지 API | 7414 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  유효한 발신프로필을 찾을 수 없음 |
| 메시지 API | 7415 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  유효한 계약을 찾을 수 없음 |
| 메시지 API | 7419 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  유효하지 않은 챗버블 타입 또는 메시지 타입 요청 |
| 메시지 API | 7420 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  메시지 요청 페이로드 생성 오류 |
| 메시지 API | 7421 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  타임아웃 |
| 메시지 API | 7422 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  메시지 결과 조회 오류 |
| 메시지 API | 7423 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  메시지 발송 대상 오류 |
| 메시지 API | 7432 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  템플릿 변수 불일치 |
| 메시지 API | 7433 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  중단된 템플릿 |
| 메시지 API | 7434 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  변경된 템플릿 |
| 메시지 API | 7435 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  지갑 정보를 찾을 수 없음 |
| 메시지 API | 7436 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  지갑 잔액 부족 |
| 메시지 API | 7437 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  메시지 요청 실패 |
| 메시지 API | 7438 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  브랜드메시지 메시지 개수 제한 |
| 메시지 API | 7443 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  만료된 요청 |
| 메시지 API | 7444 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  본문 길이 제한 (30KB) 초과 |
| 메시지 API | 7450 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  계약을 찾을 수 없음 |
| 메시지 API | 7451 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  중복된 계약 |
| 메시지 API | 7452 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  이미 종료된 계약 |
| 메시지 API | 7453 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  처리중인 계약 |
| 메시지 API | 7454 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  계약 생성 실패 |
| 메시지 API | 7455 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  계약 종료 실패 |
| 메시지 API | 7456 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  최대 발송수 초과 |
| 메시지 API | 7460 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  개인화 메시지 과금 정보를 찾을 수 없음 |
| 메시지 API | 7461 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  처리중인 메시지 |
| 메시지 API | 7470 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  동보 발송 요청 실패 |
| 메시지 API | 7471 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  동보 발송 요청을 찾을 수 없음 |
| 메시지 API | 7472 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  동보 발송 요청 상태를 변경할 수 없음 |
| 메시지 API | 7521 | – | 카카오 비즈메시지 › 요청 처리·계약·과금 — 7400 ~ 7472  중복발신제한 |
| 메시지 API | 8000 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  전달 |
| 메시지 API | 8001 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  전문 형식 에러 |
| 메시지 API | 8002 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  지원하지 않는 요청 타입 |
| 메시지 API | 8003 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  유효하지 않은 Json 포맷 |
| 메시지 API | 8004 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  유효하지 않은 파트너 |
| 메시지 API | 8005 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  인증되지 않은 사용자 |
| 메시지 API | 8006 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  기타 에러 |
| 메시지 API | 8200 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  시스템 에러 |
| 메시지 API | 8201 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  이미 발송한 메시지 |
| 메시지 API | 8202 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  Message Convert 실패 |
| 메시지 API | 8203 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  Message Validation 실패 |
| 메시지 API | 8204 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  Message Send 실패 |
| 메시지 API | 8205 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  MaaP FE Request 에러 |
| 메시지 API | 8206 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  MaaP FE Response Status 에러 |
| 메시지 API | 8207 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  MaaP FE API Response Convert 실패 |
| 메시지 API | 8208 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  RCS 메시지를 수신할 통신사가 없습니다. |
| 메시지 API | 8209 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  MessageResendVO Convert 실패 |
| 메시지 API | 8210 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  업로드 파일 크기 초과 |
| 메시지 API | 8211 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  Empty suggestions array 허용 안함 |
| 메시지 API | 8212 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  수신 번호 형식 오류 |
| 메시지 API | 8213 | – | RCS › 성공·기본 처리 — 8000 ~ 8213  삼성 RCS GW 서버 에러 |
| 메시지 API | 8700 | – | RCS › 비즈뿌리오 게이트웨이 판정 — 8700 ~ 8703  타임아웃 |
| 메시지 API | 8701 | – | RCS › 비즈뿌리오 게이트웨이 판정 — 8700 ~ 8703  동일 수신번호 중복 발송 차단 |
| 메시지 API | 8702 | – | RCS › 비즈뿌리오 게이트웨이 판정 — 8700 ~ 8703  사전 미등록 chatbot\_id |
| 메시지 API | 8703 | – | RCS › 비즈뿌리오 게이트웨이 판정 — 8700 ~ 8703  스팸 차단 |
| 메시지 API | 8800 | – | RCS › 인증·계정 — 8800 ~ 8811  Authorization 헤더 파라미터 누락 |
| 메시지 API | 8801 | – | RCS › 인증·계정 — 8800 ~ 8811  Authorization 헤더 값 누락 |
| 메시지 API | 8802 | – | RCS › 인증·계정 — 8800 ~ 8811  토큰이 일치하지 않습니다. |
| 메시지 API | 8803 | – | RCS › 인증·계정 — 8800 ~ 8811  토큰이 만료되었습니다. |
| 메시지 API | 8804 | – | RCS › 인증·계정 — 8800 ~ 8811  인증 토큰 에러 |
| 메시지 API | 8805 | – | RCS › 인증·계정 — 8800 ~ 8811  요청된 계정 정보를 찾을 수 없습니다(BP ID) |
| 메시지 API | 8806 | – | RCS › 인증·계정 — 8800 ~ 8811  요청된 중계사 전송 계정을 찾을 수 없습니다(RCS ID) |
| 메시지 API | 8807 | – | RCS › 인증·계정 — 8800 ~ 8811  잘못된 패스워드 |
| 메시지 API | 8808 | – | RCS › 인증·계정 — 8800 ~ 8811  접근 허용된 IP 가 아닙니다 |
| 메시지 API | 8809 | – | RCS › 인증·계정 — 8800 ~ 8811  메시지 전송을 할 수 없는 상태입니다. (서버의 요청 거부) |
| 메시지 API | 8810 | – | RCS › 인증·계정 — 8800 ~ 8811  RCS 메시지 TPS 가 초과되었습니다. |
| 메시지 API | 8811 | – | RCS › 인증·계정 — 8800 ~ 8811  RCS 메시지 Quota 가 초과되었습니다. |
| 메시지 API | 8812 | – | RCS › 시스템·요청 — 8812 ~ 8838  통신사측 시스템 에러 |
| 메시지 API | 8813 | – | RCS › 시스템·요청 — 8812 ~ 8838  IO 에러 발생 |
| 메시지 API | 8814 | – | RCS › 시스템·요청 — 8812 ~ 8838  중복 Key 오류 |
| 메시지 API | 8815 | – | RCS › 시스템·요청 — 8812 ~ 8838  요청 파라미터 형식 오류 |
| 메시지 API | 8816 | – | RCS › 시스템·요청 — 8812 ~ 8838  요청 Body JSON 파싱 에러 |
| 메시지 API | 8817 | – | RCS › 시스템·요청 — 8812 ~ 8838  데이터를 찾을 수 없음 |
| 메시지 API | 8818 | – | RCS › 시스템·요청 — 8812 ~ 8838  전화번호 형식이 일치하지 않습니다 |
| 메시지 API | 8819 | – | RCS › 시스템·요청 — 8812 ~ 8838  요청을 처리할 수 없는 상태입니다. |
| 메시지 API | 8820 | – | RCS › 시스템·요청 — 8812 ~ 8838  이미 사용 중인 챗봇 ID 입니다. |
| 메시지 API | 8821 | – | RCS › 시스템·요청 — 8812 ~ 8838  챗봇을 생성할 수 없습니다. |
| 메시지 API | 8822 | – | RCS › 시스템·요청 — 8812 ~ 8838  챗봇 정보를 변경할 수 없습니다. |
| 메시지 API | 8823 | – | RCS › 시스템·요청 — 8812 ~ 8838  챗봇이 있는 브랜드는 삭제 할수 없습니다. |
| 메시지 API | 8824 | – | RCS › 시스템·요청 — 8812 ~ 8838  챗봇 Type 은 a2p, chatbot 로 설정해야 함 |
| 메시지 API | 8825 | – | RCS › 시스템·요청 — 8812 ~ 8838  요청 URL Parameter 의 챗봇 Id 와 Body Parameter 불일치 |
| 메시지 API | 8826 | – | RCS › 시스템·요청 — 8812 ~ 8838  잘못된 Webhook 중계사 요청 파라미터 입니다. |
| 메시지 API | 8827 | – | RCS › 시스템·요청 — 8812 ~ 8838  Webhook 중계 시스템 연결 오류 |
| 메시지 API | 8828 | – | RCS › 시스템·요청 — 8812 ~ 8838  중계사 Webhook 전송 요청을 실패 했습니다. |
| 메시지 API | 8829 | – | RCS › 시스템·요청 — 8812 ~ 8838  중계사 Webhook 처리 응답 수신 오류가 발생 했습니다. |
| 메시지 API | 8830 | – | RCS › 시스템·요청 — 8812 ~ 8838  요청을 처리할 수 없는 파일 유형입니다. |
| 메시지 API | 8831 | – | RCS › 시스템·요청 — 8812 ~ 8838  파일 속성 오류 |
| 메시지 API | 8832 | – | RCS › 시스템·요청 — 8812 ~ 8838  fileID 가 없거나 ID 형식에 맞지 않음 |
| 메시지 API | 8833 | – | RCS › 시스템·요청 — 8812 ~ 8838  File 저장 오류 |
| 메시지 API | 8834 | – | RCS › 시스템·요청 — 8812 ~ 8838  Multipart 데이터 전송 오류 |
| 메시지 API | 8835 | – | RCS › 시스템·요청 — 8812 ~ 8838  자사 고객이 아닙니다. |
| 메시지 API | 8836 | – | RCS › 시스템·요청 — 8812 ~ 8838  자사 고객이지만, RCS 메시지를 수신할 수 있는 가입자가 아닙니다. |
| 메시지 API | 8837 | – | RCS › 시스템·요청 — 8812 ~ 8838  단말기기로 RCS 메시지를 전송할 수 없습니다. |
| 메시지 API | 8838 | – | RCS › 시스템·요청 — 8812 ~ 8838  내부 서버 오류가 발생하였습니다. |
| 메시지 API | 8839 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  기업 정보 내용이 누락된 필수항목이 있습니다. |
| 메시지 API | 8840 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  대행사 정보 내용이 누락된 필수 항목이 있습니다. |
| 메시지 API | 8841 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  AgencyID 가 존재하지 않습니다. |
| 메시지 API | 8842 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  BrandID 에 대행 권한이 없는 AgencyID |
| 메시지 API | 8843 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  계약 정보 내용이 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8844 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 정보 내용이 누락된 필수항목이 있습니다. |
| 메시지 API | 8845 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 명이 누락되어 있습니다. |
| 메시지 API | 8846 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 프로필 이미지가 누락되어 있습니다. |
| 메시지 API | 8847 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 CS 번호가 누락되어 있습니다. |
| 메시지 API | 8848 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 메뉴 최대 개수를 초과하였거나 부정확합니다. |
| 메시지 API | 8849 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 카테고리 설정이 잘못되어 있습니다. |
| 메시지 API | 8850 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 홈페이지 설정이 잘못되어 있습니다. |
| 메시지 API | 8851 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 이메일 설정이 잘못되어 있습니다. |
| 메시지 API | 8852 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드 주소가 잘못되어 있습니다. |
| 메시지 API | 8853 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  브랜드ID 가 존재하지 않음 |
| 메시지 API | 8854 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  챗봇 정보 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| 메시지 API | 8855 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  BotID(발신번호)가 전화번호 형식에 맞지 않음 |
| 메시지 API | 8856 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  BrandID 에 존재하지 않는 BotID |
| 메시지 API | 8857 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지베이스 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| 메시지 API | 8858 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  MessagebaseID 가 존재하지 않음 |
| 메시지 API | 8859 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  BrandID 에 존재하지 않는 MessagebaseID 입니다. |
| 메시지 API | 8860 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 formatstring 누락된 필수 항목이 있습니다. |
| 메시지 API | 8861 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 policy Info 가 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8862 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 param 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8863 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 attribute 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8864 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 type 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8865 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebaseID 의 product type 과 일치하지 않음 |
| 메시지 API | 8866 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  MessagebaseForm 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| 메시지 API | 8867 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebaseformID 가 존재하지 않습니다. |
| 메시지 API | 8868 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebase 의 상품코드 에러 |
| 메시지 API | 8869 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  (광고)를 사용할 수 없음 |
| 메시지 API | 8870 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  Action button 이 허용되지 않는 messagebaseID 에서 Action button 을 사용하였음 |
| 메시지 API | 8871 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  허용되지 않은 header 값 사용 |
| 메시지 API | 8872 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  header 값과 일치 하지 않은 footer 사용 (ex. header 가 0 인데, footer 가 있음) |
| 메시지 API | 8873 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  footer 값이 누락되어 있습니다 (ex. header 가 1 인데, footer 가 없음) |
| 메시지 API | 8874 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  footer validation 오류 (ex. 숫자, 하이픈만 가능. 20 자리) |
| 메시지 API | 8875 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  등록한 패턴과 일치 하지 않음 |
| 메시지 API | 8876 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  title 최대글자수를 초과했습니다. |
| 메시지 API | 8877 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  description 최대글자수를 초과했습니다. |
| 메시지 API | 8878 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  최대 버튼수를 초과했습니다. |
| 메시지 API | 8879 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  messagebaseID 의 number of card 와 입력이 일치하지 않음 |
| 메시지 API | 8880 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  최대 미디어 용량을 초과했습니다. |
| 메시지 API | 8881 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  중계사 정보가 부정확하거나 누락된 필수 항목이 있습니다. |
| 메시지 API | 8882 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지 형식이 부정확하거나 누락된 필수항목이 있습니다. |
| 메시지 API | 8883 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지 기술방법이 잘못되었습니다. |
| 메시지 API | 8884 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지 내용이 누락되었거나 부정확합니다. |
| 메시지 API | 8885 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  요청을 처리할 수 없는 메시지 유형입니다. |
| 메시지 API | 8886 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  같은 메시지 ID 로 두번 이상 메시지 발송이 요청됨 |
| 메시지 API | 8887 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  챗봇 권한 오류 |
| 메시지 API | 8888 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  발신 가능한 챗봇 상태가 아님 |
| 메시지 API | 8889 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  대행사 권한 오류 |
| 메시지 API | 8890 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지 유효기간 입력값 오류 |
| 메시지 API | 8891 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지베이스 파라미터의 길이가 한계값 이상 |
| 메시지 API | 8892 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  버튼 필드를 받을 수 없는 메시지베이스 입니다. |
| 메시지 API | 8893 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  최대 버튼 글자수 초과 |
| 메시지 API | 8894 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  버튼 형식 오류 |
| 메시지 API | 8895 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  존재하지 않는 File 이거나 usageType 오류 |
| 메시지 API | 8896 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  실시간 메시지가 인입 후 10 초안에 삼성으로 전달되지 못함 |
| 메시지 API | 8897 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  메시지 베이스의 상태가 'pause'인 메시지 베이스 메시지로 전문 구성하여 전송 시도 |
| 메시지 API | 8898 | – | RCS › 브랜드·챗봇·메시지베이스 — 8839 ~ 8898  필수 파라미터 검증 오류 |
| 메시지 API | 8899 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| 메시지 API | 8900 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 처리 오류가 발생 했습니다. |
| 메시지 API | 8901 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| 메시지 API | 8902 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 처리 오류가 발생 했습니다. |
| 메시지 API | 8903 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| 메시지 API | 8904 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 처리 오류가 발생 했습니다. |
| 메시지 API | 8905 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  잘못된 요청입니다. |
| 메시지 API | 8906 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  삼성 MaaP Gateway NB API 연동 에러 |
| 메시지 API | 8907 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  삼성 MaaP Registry Chatbot API 연동 에러 |
| 메시지 API | 8908 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Capri 연동 에러 |
| 메시지 API | 8909 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 처리 불가 상태 오류가 발생했습니다. |
| 메시지 API | 8910 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 메시지 전송 과금 이력 작성을 실패했습니다. |
| 메시지 API | 8911 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  잘못된 Webhook Url 입니다. |
| 메시지 API | 8912 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  만료된 메시지 입니다. |
| 메시지 API | 8913 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  재시도 횟수 초과로 인해 메시지 전송을 실패했습니다. |
| 메시지 API | 8914 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 발송 메시지가 존재하지 않습니다. |
| 메시지 API | 8915 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 발송 중계사 정보가 존재하지 않습니다. |
| 메시지 API | 8916 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  계약관계가 없습니다. |
| 메시지 API | 8917 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  MaaP 시스템 혹은 RCS 프로토콜 상의 이슈로 발송 실패했습니다. |
| 메시지 API | 8918 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  잘못된 메시지 형식으로 인해 발송 실패되었고 재시도 불가합니다. |
| 메시지 API | 8919 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지 회수 실패했습니다. |
| 메시지 API | 8920 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  RCS 세션 연결 전 만료되어 발송 실패했습니다. |
| 메시지 API | 8921 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Backend 서버 내부 에러 발생했습니다. |
| 메시지 API | 8922 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Backend 서버 타임 아웃 발생했습니다. |
| 메시지 API | 8923 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Limit 범위를 초과하였습니다. |
| 메시지 API | 8924 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Offset 범위가 부정확합니다. |
| 메시지 API | 8925 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  잘못된 통계 타입 입니다. |
| 메시지 API | 8926 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  오픈리치카드 MMS 메시지 파일 포맷 오류 |
| 메시지 API | 8927 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지베이스의 Policy 미 존재(정의) 오류 |
| 메시지 API | 8928 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지베이스 서비스 불가 상태 |
| 메시지 API | 8929 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  개별 최대 미디어 용량을 초과했습니다. |
| 메시지 API | 8930 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지 유효성 검증 오류 |
| 메시지 API | 8931 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  오픈리치카드 메시지 구축 실패 |
| 메시지 API | 8932 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지카드의 버튼 개수가 다릅니다. |
| 메시지 API | 8933 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 발송 준비 중 오류 발생 |
| 메시지 API | 8934 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 발송 결과 갱신 중 오류 발생 |
| 메시지 API | 8935 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  CDR 생성 결과 상태 갱신 중 오류 발생 |
| 메시지 API | 8936 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  처리 결과 상태 갱신 중 오류 발생 |
| 메시지 API | 8937 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지 이력 생성 작업 중 오류 발생 |
| 메시지 API | 8938 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  잘못된 Webhook 요청 오류 |
| 메시지 API | 8939 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 수신 처리 수행 오류 |
| 메시지 API | 8940 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  미 허용 Webhook 이벤트 요청 오류 |
| 메시지 API | 8941 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  중계사 CID Webhook 발송 Url 정보가 미 정의 상태 입니다. |
| 메시지 API | 8942 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  과금 미 처리 대상 중계사 입니다. |
| 메시지 API | 8943 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  수행 명령 객체 미 전달 오류입니다. |
| 메시지 API | 8944 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  처리 미 대상 서비스 입니다. |
| 메시지 API | 8945 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  삼성 MaaP 서버 오류 |
| 메시지 API | 8946 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  파일 메시지 이벤트 오류가 발생했습니다. |
| 메시지 API | 8947 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Webhook 스케줄러 프로세스 오류 |
| 메시지 API | 8948 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  이미 사용 중인 자동응답 메시지 ID 입니다. |
| 메시지 API | 8949 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  이미 사용 중인 Postback ID 입니다. |
| 메시지 API | 8950 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Persistent Menu 를 등록할 수 없습니다. |
| 메시지 API | 8951 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Persistent Menu 의 JSON 데이터 오류 |
| 메시지 API | 8952 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  Reply ID 가 존재하지 않음 |
| 메시지 API | 8953 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  양방향 서비스 사용불가 |
| 메시지 API | 8954 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  빈 칩 리스트를 허용하지 않습니다. |
| 메시지 API | 8955 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  칩 리스트를 사용할 수 없습니다. |
| 메시지 API | 8956 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  버튼 필드에 Reply 를 사용할 수 없습니다. |
| 메시지 API | 8957 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  칩리스트 개수 초과 |
| 메시지 API | 8958 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  유효하지 않은 replyID 이거나 누락되었습니다. |
| 메시지 API | 8959 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  replyID 와 일치하는 수신번호가 아님 |
| 메시지 API | 8960 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  replyID 와 일치하는 챗봇 ID 가 아님 |
| 메시지 API | 8961 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지베이스 상품 코드가 세션 메시지 가능하지 않음 |
| 메시지 API | 8962 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  챗봇이 세션 메시지 가능하지 않음 |
| 메시지 API | 8963 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  요청 양방향 챗봇에 대한 정보가 존재하지 않습니다. |
| 메시지 API | 8964 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  사용 불가 챗봇입니다. |
| 메시지 API | 8965 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  요청 양방향 챗봇에 대한 양방향 중계사 정보가 존재하지 않습니다. |
| 메시지 API | 8966 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  챗봇 MO 발송 Url 정보가 미 정의 상태 입니다. |
| 메시지 API | 8967 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  MO 메시지 DB 등록 오류가 발생했습니다. |
| 메시지 API | 8968 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  자동 응답 메시지 발송 수행 오류가 발생했습니다. |
| 메시지 API | 8969 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  MO 메시지가 존재하지 않습니다. |
| 메시지 API | 8970 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  발송 정보의 agencyKey, brandKey 가 유효하지 않습니다. |
| 메시지 API | 8971 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  자동 응답 메시지 발송 권한이 없습니다. |
| 메시지 API | 8972 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  대화방 MO 발송 권한이 없습니다. |
| 메시지 API | 8973 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  대화방 권한 체크 DB 수행에 실패했습니다. |
| 메시지 API | 8974 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  자사 고객이지만, 요청한 스펙의 RCS 메시지를 수신할 수 있는 가입자가 아닙니다. |
| 메시지 API | 8975 | – | RCS › Webhook·발송 처리 — 8899 ~ 8975  메시지 발송은 정상 처리되었으나, 단말의 최종 수신 여부를 확인할 수 없는 상태입니다. |
| 메시지 API | 9998 | – | RCS › 알 수 없는 오류  RCS GW 내부 서버 에러 |
| 메시지 API | 9999 | – | RCS › 알 수 없는 오류  알려지지 않은 오류 |
| 메시지 API | 5000 | – | 네이버 톡톡 › 성공  성공 |
| 메시지 API | 5001 | – | 네이버 톡톡 › 성공  메시지가 웹으로 전송되었고 앱 푸시는 15초 이내 도달하지 못함 |
| 메시지 API | 5002 | – | 네이버 톡톡 › 성공  카드승인알림 메시지가 가입완료 메시지를 읽지 않은 사용자에게 전달됨 |
| 메시지 API | 5003 | – | 네이버 톡톡 › 성공  메시지가 웹으로 전송됨 |
| 메시지 API | 5100 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  bizppurioPartnerId 누락 |
| 메시지 API | 5101 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  bizppurioPartnerKey 누락 |
| 메시지 API | 5102 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  요청 Body 문제 |
| 메시지 API | 5103 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  전화번호 형식 오류 |
| 메시지 API | 5104 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  bizppurioPartnerKey 또는 groupKey 필수 |
| 메시지 API | 5105 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  유효하지 않은 상품에 해당하는 템플릿 발송 (계약 확인 필요) |
| 메시지 API | 5106 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  등록된 템플릿의 상품코드와 발송 요청한 상품코드가 일치해야 함 |
| 메시지 API | 5107 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  템플릿 코드 누락 |
| 메시지 API | 5108 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  전화번호 누락 |
| 메시지 API | 5109 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  productCode 누락 |
| 메시지 API | 5110 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  혜택 타입 템플릿만 타겟팅 발송 가능 |
| 메시지 API | 5111 | – | 네이버 톡톡 › 요청 검증 — 5100 ~ 5111  검색 노출 가능한 템플릿은 타겟팅 발송 불가 |
| 메시지 API | 5201 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿 필수 파라미터 불일치 |
| 메시지 API | 5202 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿 아이디 미존재 |
| 메시지 API | 5203 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿의 필수 버튼 수와 요청 버튼 수 불일치 |
| 메시지 API | 5204 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  필수 파라미터 불일치 (버튼코드 확인) |
| 메시지 API | 5205 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  등록된 템플릿과 일치하지 않는 메시지 파라미터 |
| 메시지 API | 5206 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  승인되지 않은 템플릿 |
| 메시지 API | 5207 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  관리자에 의해 차단된 템플릿 |
| 메시지 API | 5208 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  샘플 이미지 미등록 |
| 메시지 API | 5209 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  이미지 해시 아이디에 해당하는 파트너 키 또는 템플릿 그룹 키 사용 필요 |
| 메시지 API | 5210 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿에 저장된 템플릿 그룹 아이디와 불일치 |
| 메시지 API | 5211 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  유효하지 않은 템플릿 그룹 키 |
| 메시지 API | 5212 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿 그룹의 파트너 목록에 없는 요청 |
| 메시지 API | 5213 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  이미지 해시 아이디에 해당하는 템플릿 그룹 키 확인 필요 |
| 메시지 API | 5214 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  쿠폰 첨부 시 이미지 첨부 불가 |
| 메시지 API | 5215 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  쿠폰 첨부 시 쿠폰 이미지 URL 필수 |
| 메시지 API | 5216 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  쿠폰 만료기간 필수 — 오늘 이후 날짜를 YYYY-MM-DD 형식으로 입력 |
| 메시지 API | 5217 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  발송처 정보 없음 또는 사업자코드 오류 |
| 메시지 API | 5218 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  할부 개월 수 오류 — 00 ~ 36 사이의 숫자 |
| 메시지 API | 5219 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  거래유형 오류 — 승인 01 / 취소 02 / 거절 03 |
| 메시지 API | 5220 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  거래금액 숫자 변환 실패 |
| 메시지 API | 5221 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  통화코드 오류 — ISO-4217 숫자 코드 |
| 메시지 API | 5222 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  거래일시 변환 실패 — YYYYMMDDHH24MISS 14자리 |
| 메시지 API | 5223 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  원화 표시 거래금액에 소수점 사용 불가 |
| 메시지 API | 5224 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  템플릿 변수 150자 초과 |
| 메시지 API | 5225 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  발송 허용 길이 초과 |
| 메시지 API | 5226 | – | 네이버 톡톡 › 템플릿 검증 — 5201 ~ 5226  쿠폰 이미지 URL 첨부 실패 |
| 메시지 API | 5300 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  유효하지 않은 bizppurioPartnerKey |
| 메시지 API | 5301 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  전화번호에 해당하는 유저 미존재 |
| 메시지 API | 5302 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  맵핑된 파트너 미존재 |
| 메시지 API | 5304 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  전화번호에 해당하는 서로 다른 유저 존재 |
| 메시지 API | 5305 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  발송 파트너 계정 비정상 상태 |
| 메시지 API | 5400 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  유저가 정보성 알림 수신 가능 상태 아님 |
| 메시지 API | 5401 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  유저가 스마트 알림 수신 차단 |
| 메시지 API | 5402 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  유저가 파트너 계정 차단 |
| 메시지 API | 5403 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  카드승인알림 미가입 유저 |
| 메시지 API | 5404 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  카드승인알림 가입 단말기에 푸시 미도달 |
| 메시지 API | 5405 | – | 네이버 톡톡 › 사용자·파트너 — 5300 ~ 5405  카드승인알림 가입 전화번호와 불일치 |
| 메시지 API | 5500 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  NTALK API 호출 실패 |
| 메시지 API | 5600 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  타임아웃 |
| 메시지 API | 5601 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  동일 수신번호 중복 발송 차단 |
| 메시지 API | 5602 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  문구 필터링 |
| 메시지 API | 5603 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  형식 오류 |
| 메시지 API | 5700 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  기타 에러 |
| 메시지 API | 5900 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  네이버 시스템 오류 |
| 메시지 API | 5901 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  게이트웨이 내부 오류 |
| 메시지 API | 5902 | – | 네이버 톡톡 › 시스템 — 5500 ~ 5902  정의되지 않은 응답 코드 |
| BIZCLIENT | 9903 | – | 공통  선불사용자 사용금지 |
| BIZCLIENT | 9904 | – | 공통  Block time (날짜제한) |
| BIZCLIENT | 9081 | – | 공통  선불 사용자 FAX, PHONE 발송 제한 |
| BIZCLIENT | 9082 | – | 공통  발송해제 |
| BIZCLIENT | 9083 | – | 공통  IP 차단 |
| BIZCLIENT | 9084 | – | 공통  DEVICE 발송 제한 |
| BIZCLIENT | 9085 | – | 공통  사용금지 Callback 번호 |
| BIZCLIENT | 9905 | – | 공통  Block time |
| BIZCLIENT | 9907 | – | 공통  지원하지 않는 메시지 타입 |
| BIZCLIENT | 9010 | – | 공통  아이디틀림 |
| BIZCLIENT | 9011 | – | 공통  비밀번호 틀림 |
| BIZCLIENT | 9012 | – | 공통  중복 접속 량 많음 |
| BIZCLIENT | 9014 | – | 공통  알림톡 유효하지 않은 발신프로필키 |
| BIZCLIENT | 9015 | – | 공통  알림톡 발신프로필키 미 입력 |
| BIZCLIENT | 9016 | – | 공통  알림톡 템플릿 미 입력 |
| BIZCLIENT | 9017 | – | 공통  존재하지 않는 첨부파일 |
| BIZCLIENT | 9018 | – | 공통  0 바이트 첨부파일 |
| BIZCLIENT | 9019 | – | 공통  지원하지 않는 첨부파일 |
| BIZCLIENT | 9020 | – | 공통  1. Wrong Data Format 2. SEND\_TIME 기준 24시간이 경과한 메시지 |
| BIZCLIENT | 9021 | – | 공통  Wrong Data Format (지원하지 않는 CMID 형식) |
| BIZCLIENT | 9022 | – | 공통  Wrong Data Format (ex. cinfo 가 특수 문자 / , 공백 을 포함) |
| BIZCLIENT | 9023 | – | 공통  시간제한 (리포트 수신대기 timeout) |
| BIZCLIENT | 9024 | – | 공통  Wrong Data Format (ex. 메시지 본문 길이) |
| BIZCLIENT | 9026 | – | 공통  블랙리스트에 의한 차단 |
| BIZCLIENT | 9027 | – | 공통  MMS 첨부파일 이미지 사이즈 초과 |
| BIZCLIENT | 9028 | – | 공통  첨부파일 URL 구문 오류 |
| BIZCLIENT | 9029 | – | 공통  JSON String 구문 오류 |
| BIZCLIENT | 9030 | – | 공통  지원하지 않는 첨부파일 데이터 타입 |
| BIZCLIENT | 9031 | – | 공통  첨부파일 테이블과 매칭되는 MSG\_KEY 가 없음 |
| BIZCLIENT | 9032 | – | 공통  RCS 테이블과 매칭되는 REFKEY 가 없음 |
| BIZCLIENT | 9033 | – | 공통  지원하지 않는 대체발송 타입 |
| BIZCLIENT | 9034 | – | 공통  발송 유효시간 만료 |
| BIZCLIENT | 9035 | – | 공통  Url 다운로드 실패 |
| BIZCLIENT | 9036 | – | 공통  브랜드톡 변수형 필수 파라미터 불일치 |
| BIZCLIENT | 9037 | – | 공통  시간제한 (클라이언트 timeout) |
| BIZCLIENT | 9038 | – | 공통  암호화 유효성 검증 KEY 를 설정하지 않음 |
| BIZCLIENT | 9039 | – | 공통  암호화 유효성 검증 KEY 가 일치하지 않음 (대체발송) |
| BIZCLIENT | 9040 | – | 공통  암호화 유효성 검증 KEY 가 일치하지 않음(본문) |
| BIZCLIENT | 9041 | – | 공통  중복 발송 차단 (모듈) |
| BIZCLIENT | 9042 | – | 공통  (MTS 커스텀 모듈 사용시) JSON 문법 오류 |
| BIZCLIENT | 9043 | – | 공통  친구톡 발송시 chat\_bubble\_type 이 있음 |
| BIZCLIENT | 9044 | – | 공통  브랜드메시지 발송시 chat\_bubble\_type 이 없음 |
| BIZCLIENT | 9070 | – | 공통  선불 잔액 부족 |
| BIZCLIENT | 9071 | – | 공통  후불 한도 초과 |
| BIZCLIENT | 9072 | – | 공통  정의되지 않은 단가 |
| BIZCLIENT | 9074 | – | 공통  등록되지 않는 MessagebaseID or ChatbotID |
| BIZCLIENT | 9080 | – | 공통  Deny User Ack |
| BIZCLIENT | 9214 | – | 공통  Wrong Phone Num |
| BIZCLIENT | 9311 | – | 공통  Uploaded File Not Found |
| BIZCLIENT | 9908 | – | 공통  PHONE, FAX 선불사용자 제한기능 |
| BIZCLIENT | 9090 | – | 공통  기타에러 |
| BIZCLIENT | 8000 | – | RCS  전달 |
| BIZCLIENT | 8001 | – | RCS  전문 형식 에러 |
| BIZCLIENT | 8002 | – | RCS  지원하지 않는 요청 타입 |
| BIZCLIENT | 8003 | – | RCS  유효하지 않은 Json 포맷 |
| BIZCLIENT | 8004 | – | RCS  유효하지 않은 파트너 |
| BIZCLIENT | 8005 | – | RCS  인증되지 않은 사용자 |
| BIZCLIENT | 8006 | – | RCS  기타 에러 |
| BIZCLIENT | 8200 | – | RCS  시스템 에러 |
| BIZCLIENT | 8201 | – | RCS  이미 발송한 메시지 |
| BIZCLIENT | 8202 | – | RCS  Message Convert 실패 |
| BIZCLIENT | 8203 | – | RCS  Message Validation 실패 |
| BIZCLIENT | 8204 | – | RCS  Message Send 실패 |
| BIZCLIENT | 8205 | – | RCS  MaaP FE Request 에러 |
| BIZCLIENT | 8206 | – | RCS  MaaP FE Response Status 에러 |
| BIZCLIENT | 8207 | – | RCS  MaaP FE API Response Convert 실패 |
| BIZCLIENT | 8208 | – | RCS  RCS 메시지를 수신할 통신사가 없습니다. |
| BIZCLIENT | 8209 | – | RCS  MessageResendVO Convert 실패 |
| BIZCLIENT | 8210 | – | RCS  업로드 파일 크기 초과 |
| BIZCLIENT | 8211 | – | RCS  Empty suggestions array 허용 안함 |
| BIZCLIENT | 8212 | – | RCS  수신 번호 형식 오류 |
| BIZCLIENT | 8213 | – | RCS  삼성 RCS GW 서버 에러 |
| BIZCLIENT | 8700 | – | RCS  타임아웃 |
| BIZCLIENT | 8701 | – | RCS  동일 수신번호 중복 발송 차단 |
| BIZCLIENT | 8702 | – | RCS  사전 미등록 chatbot\_id |
| BIZCLIENT | 8703 | – | RCS  스팸 차단 |
| BIZCLIENT | 8800 | – | RCS  Authorization 헤더 파라미터 누락 |
| BIZCLIENT | 8801 | – | RCS  Authorization 헤더 값 누락 |
| BIZCLIENT | 8802 | – | RCS  토큰이 일치하지 않습니다. |
| BIZCLIENT | 8803 | – | RCS  토큰이 만료되었습니다. |
| BIZCLIENT | 8804 | – | RCS  인증 토큰 에러 |
| BIZCLIENT | 8805 | – | RCS  요청된 계정 정보를 찾을 수 없습니다(BP ID) |
| BIZCLIENT | 8806 | – | RCS  요청된 중계사 전송 계정을 찾을 수 없습니다(RCS ID) |
| BIZCLIENT | 8807 | – | RCS  잘못된 패스워드 |
| BIZCLIENT | 8808 | – | RCS  접근 허용된 IP 가 아닙니다 |
| BIZCLIENT | 8809 | – | RCS  메시지 전송을 할 수 없는 상태입니다. (서버의 요청 거부) |
| BIZCLIENT | 8810 | – | RCS  RCS 메시지 TPS 가 초과되었습니다. |
| BIZCLIENT | 8811 | – | RCS  RCS 메시지 Quota 가 초과되었습니다. |
| BIZCLIENT | 8812 | – | RCS  통신사측 시스템 에러 |
| BIZCLIENT | 8813 | – | RCS  IO 에러 발생 |
| BIZCLIENT | 8814 | – | RCS  중복 Key 오류 |
| BIZCLIENT | 8815 | – | RCS  요청 파라미터 형식 오류 |
| BIZCLIENT | 8816 | – | RCS  요청 Body JSON 파싱 에러 |
| BIZCLIENT | 8817 | – | RCS  데이터를 찾을 수 없음 |
| BIZCLIENT | 8818 | – | RCS  전화번호 형식이 일치하지 않습니다 |
| BIZCLIENT | 8819 | – | RCS  요청을 처리할 수 없는 상태입니다. |
| BIZCLIENT | 8820 | – | RCS  이미 사용 중인 챗봇 ID 입니다. |
| BIZCLIENT | 8821 | – | RCS  챗봇을 생성할 수 없습니다. |
| BIZCLIENT | 8822 | – | RCS  챗봇 정보를 변경할 수 없습니다. |
| BIZCLIENT | 8823 | – | RCS  챗봇이 있는 브랜드는 삭제 할수 없습니다. |
| BIZCLIENT | 8824 | – | RCS  챗봇 Type 은 a2p, chatbot 로 설정해야 함 |
| BIZCLIENT | 8825 | – | RCS  요청 URL Parameter 의 챗봇 Id 와 Body Parameter 불일치 |
| BIZCLIENT | 8826 | – | RCS  잘못된 Webhook 중계사 요청 파라미터 입니다. |
| BIZCLIENT | 8827 | – | RCS  Webhook 중계 시스템 연결 오류 |
| BIZCLIENT | 8828 | – | RCS  중계사 Webhook 전송 요청을 실패 했습니다. |
| BIZCLIENT | 8829 | – | RCS  중계사 Webhook 처리 응답 수신 오류가 발생 했습니다. |
| BIZCLIENT | 8830 | – | RCS  요청을 처리할 수 없는 파일 유형입니다. |
| BIZCLIENT | 8831 | – | RCS  파일 속성 오류 |
| BIZCLIENT | 8832 | – | RCS  fileID 가 없거나 ID 형식에 맞지 않음 |
| BIZCLIENT | 8833 | – | RCS  File 저장 오류 |
| BIZCLIENT | 8834 | – | RCS  Multipart 데이터 전송 오류 |
| BIZCLIENT | 8835 | – | RCS  자사 고객이 아닙니다. |
| BIZCLIENT | 8836 | – | RCS  자사 고객이지만, RCS 메시지를 수신할 수 있는 가입자가 아닙니다. |
| BIZCLIENT | 8837 | – | RCS  단말기기로 RCS 메시지를 전송할 수 없습니다. |
| BIZCLIENT | 8838 | – | RCS  내부 서버 오류가 발생하였습니다. |
| BIZCLIENT | 8839 | – | RCS  기업 정보 내용이 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8840 | – | RCS  대행사 정보 내용이 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8841 | – | RCS  AgencyID 가 존재하지 않습니다. |
| BIZCLIENT | 8842 | – | RCS  BrandID 에 대행 권한이 없는 AgencyID |
| BIZCLIENT | 8843 | – | RCS  계약 정보 내용이 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8844 | – | RCS  브랜드 정보 내용이 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8845 | – | RCS  브랜드 명이 누락되어 있습니다. |
| BIZCLIENT | 8846 | – | RCS  브랜드 프로필 이미지가 누락되어 있습니다. |
| BIZCLIENT | 8847 | – | RCS  브랜드 CS 번호가 누락되어 있습니다. |
| BIZCLIENT | 8848 | – | RCS  브랜드 메뉴 최대 개수를 초과하였거나 부정확합니다. |
| BIZCLIENT | 8849 | – | RCS  브랜드 카테고리 설정이 잘못되어 있습니다. |
| BIZCLIENT | 8850 | – | RCS  브랜드 홈페이지 설정이 잘못되어 있습니다. |
| BIZCLIENT | 8851 | – | RCS  브랜드 이메일 설정이 잘못되어 있습니다. |
| BIZCLIENT | 8852 | – | RCS  브랜드 주소가 잘못되어 있습니다. |
| BIZCLIENT | 8853 | – | RCS  브랜드ID 가 존재하지 않음 |
| BIZCLIENT | 8854 | – | RCS  챗봇 정보 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8855 | – | RCS  BotID(발신번호)가 전화번호 형식에 맞지 않음 |
| BIZCLIENT | 8856 | – | RCS  BrandID 에 존재하지 않는 BotID |
| BIZCLIENT | 8857 | – | RCS  메시지베이스 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8858 | – | RCS  MessagebaseID 가 존재하지 않음 |
| BIZCLIENT | 8859 | – | RCS  BrandID 에 존재하지 않는 MessagebaseID 입니다. |
| BIZCLIENT | 8860 | – | RCS  messagebase 의 formatstring 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8861 | – | RCS  messagebase 의 policy Info 가 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8862 | – | RCS  messagebase 의 param 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8863 | – | RCS  messagebase 의 attribute 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8864 | – | RCS  messagebase 의 type 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8865 | – | RCS  messagebaseID 의 product type 과 일치하지 않음 |
| BIZCLIENT | 8866 | – | RCS  MessagebaseForm 내용이 부정확하거나 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8867 | – | RCS  messagebaseformID 가 존재하지 않습니다. |
| BIZCLIENT | 8868 | – | RCS  messagebase 의 상품코드 에러 |
| BIZCLIENT | 8869 | – | RCS  (광고)를 사용할 수 없음 |
| BIZCLIENT | 8870 | – | RCS  Action button 이 허용되지 않는 messagebaseID 에서 Action button 을 사용하였음 |
| BIZCLIENT | 8871 | – | RCS  허용되지 않은 header 값 사용 |
| BIZCLIENT | 8872 | – | RCS  header 값과 일치 하지 않은 footer 사용 (ex. header 가 0 인데, footer 가 있음) |
| BIZCLIENT | 8873 | – | RCS  footer 값이 누락되어 있습니다 (ex. header 가 1 인데, footer 가 없음) |
| BIZCLIENT | 8874 | – | RCS  footer validation 오류 (ex. 숫자, 하이픈만 가능. 20 자리) |
| BIZCLIENT | 8875 | – | RCS  등록한 패턴과 일치 하지 않음 |
| BIZCLIENT | 8876 | – | RCS  title 최대글자수를 초과했습니다. |
| BIZCLIENT | 8877 | – | RCS  description 최대글자수를 초과했습니다. |
| BIZCLIENT | 8878 | – | RCS  최대 버튼수를 초과했습니다. |
| BIZCLIENT | 8879 | – | RCS  messagebaseID 의 number of card 와 입력이 일치하지 않음 |
| BIZCLIENT | 8880 | – | RCS  최대 미디어 용량을 초과했습니다. |
| BIZCLIENT | 8881 | – | RCS  중계사 정보가 부정확하거나 누락된 필수 항목이 있습니다. |
| BIZCLIENT | 8882 | – | RCS  메시지 형식이 부정확하거나 누락된 필수항목이 있습니다. |
| BIZCLIENT | 8883 | – | RCS  메시지 기술방법이 잘못되었습니다. |
| BIZCLIENT | 8884 | – | RCS  메시지 내용이 누락되었거나 부정확합니다. |
| BIZCLIENT | 8885 | – | RCS  요청을 처리할 수 없는 메시지 유형입니다. |
| BIZCLIENT | 8886 | – | RCS  같은 메시지 ID 로 두번 이상 메시지 발송이 요청됨 |
| BIZCLIENT | 8887 | – | RCS  챗봇 권한 오류 |
| BIZCLIENT | 8888 | – | RCS  발신 가능한 챗봇 상태가 아님 |
| BIZCLIENT | 8889 | – | RCS  대행사 권한 오류 |
| BIZCLIENT | 8890 | – | RCS  메시지 유효기간 입력값 오류 |
| BIZCLIENT | 8891 | – | RCS  메시지베이스 파라미터의 길이가 한계값 이상 |
| BIZCLIENT | 8892 | – | RCS  버튼 필드를 받을 수 없는 메시지베이스 입니다. |
| BIZCLIENT | 8893 | – | RCS  최대 버튼 글자수 초과 |
| BIZCLIENT | 8894 | – | RCS  버튼 형식 오류 |
| BIZCLIENT | 8895 | – | RCS  존재하지 않는 File 이거나 usageType 오류 |
| BIZCLIENT | 8896 | – | RCS  실시간 메시지가 인입 후 10 초안에 삼성으로 전달되지 못함 |
| BIZCLIENT | 8897 | – | RCS  메시지 베이스의 상태가 'pause'인 메시지 베이스 메시지로 전문 구성하여 전송 시도 |
| BIZCLIENT | 8898 | – | RCS  필수 파라미터 검증 오류 |
| BIZCLIENT | 8899 | – | RCS  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| BIZCLIENT | 8900 | – | RCS  Webhook 메시지 처리 오류가 발생 했습니다. |
| BIZCLIENT | 8901 | – | RCS  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| BIZCLIENT | 8902 | – | RCS  Webhook 메시지 처리 오류가 발생 했습니다. |
| BIZCLIENT | 8903 | – | RCS  Webhook 메시지 미 수신 오류가 발생 했습니다. |
| BIZCLIENT | 8904 | – | RCS  Webhook 메시지 처리 오류가 발생 했습니다. |
| BIZCLIENT | 8905 | – | RCS  잘못된 요청입니다. |
| BIZCLIENT | 8906 | – | RCS  삼성 MaaP Gateway NB API 연동 에러 |
| BIZCLIENT | 8907 | – | RCS  삼성 MaaP Registry Chatbot API 연동 에러 |
| BIZCLIENT | 8908 | – | RCS  Capri 연동 에러 |
| BIZCLIENT | 8909 | – | RCS  Webhook 처리 불가 상태 오류가 발생했습니다. |
| BIZCLIENT | 8910 | – | RCS  Webhook 메시지 전송 과금 이력 작성을 실패했습니다. |
| BIZCLIENT | 8911 | – | RCS  잘못된 Webhook Url 입니다. |
| BIZCLIENT | 8912 | – | RCS  만료된 메시지 입니다. |
| BIZCLIENT | 8913 | – | RCS  재시도 횟수 초과로 인해 메시지 전송을 실패했습니다. |
| BIZCLIENT | 8914 | – | RCS  Webhook 발송 메시지가 존재하지 않습니다. |
| BIZCLIENT | 8915 | – | RCS  Webhook 발송 중계사 정보가 존재하지 않습니다. |
| BIZCLIENT | 8916 | – | RCS  계약관계가 없습니다. |
| BIZCLIENT | 8917 | – | RCS  MaaP 시스템 혹은 RCS 프로토콜 상의 이슈로 발송 실패했습니다. |
| BIZCLIENT | 8918 | – | RCS  잘못된 메시지 형식으로 인해 발송 실패되었고 재시도 불가합니다. |
| BIZCLIENT | 8919 | – | RCS  메시지 회수 실패했습니다. |
| BIZCLIENT | 8920 | – | RCS  RCS 세션 연결 전 만료되어 발송 실패했습니다. |
| BIZCLIENT | 8921 | – | RCS  Backend 서버 내부 에러 발생했습니다. |
| BIZCLIENT | 8922 | – | RCS  Backend 서버 타임 아웃 발생했습니다. |
| BIZCLIENT | 8923 | – | RCS  Limit 범위를 초과하였습니다. |
| BIZCLIENT | 8924 | – | RCS  Offset 범위가 부정확합니다. |
| BIZCLIENT | 8925 | – | RCS  잘못된 통계 타입 입니다. |
| BIZCLIENT | 8926 | – | RCS  오픈리치카드 MMS 메시지 파일 포맷 오류 |
| BIZCLIENT | 8927 | – | RCS  메시지베이스의 Policy 미 존재(정의) 오류 |
| BIZCLIENT | 8928 | – | RCS  메시지베이스 서비스 불가 상태 |
| BIZCLIENT | 8929 | – | RCS  개별 최대 미디어 용량을 초과했습니다. |
| BIZCLIENT | 8930 | – | RCS  메시지 유효성 검증 오류 |
| BIZCLIENT | 8931 | – | RCS  오픈리치카드 메시지 구축 실패 |
| BIZCLIENT | 8932 | – | RCS  메시지카드의 버튼 개수가 다릅니다. |
| BIZCLIENT | 8933 | – | RCS  Webhook 발송 준비 중 오류 발생 |
| BIZCLIENT | 8934 | – | RCS  Webhook 발송 결과 갱신 중 오류 발생 |
| BIZCLIENT | 8935 | – | RCS  CDR 생성 결과 상태 갱신 중 오류 발생 |
| BIZCLIENT | 8936 | – | RCS  처리 결과 상태 갱신 중 오류 발생 |
| BIZCLIENT | 8937 | – | RCS  메시지 이력 생성 작업 중 오류 발생 |
| BIZCLIENT | 8938 | – | RCS  잘못된 Webhook 요청 오류 |
| BIZCLIENT | 8939 | – | RCS  Webhook 수신 처리 수행 오류 |
| BIZCLIENT | 8940 | – | RCS  미 허용 Webhook 이벤트 요청 오류 |
| BIZCLIENT | 8941 | – | RCS  중계사 CID Webhook 발송 Url 정보가 미 정의 상태 입니다. |
| BIZCLIENT | 8942 | – | RCS  과금 미 처리 대상 중계사 입니다. |
| BIZCLIENT | 8943 | – | RCS  수행 명령 객체 미 전달 오류입니다. |
| BIZCLIENT | 8944 | – | RCS  처리 미 대상 서비스 입니다. |
| BIZCLIENT | 8945 | – | RCS  삼성 MaaP 서버 오류 |
| BIZCLIENT | 8946 | – | RCS  파일 메시지 이벤트 오류가 발생했습니다. |
| BIZCLIENT | 8947 | – | RCS  Webhook 스케줄러 프로세스 오류 |
| BIZCLIENT | 8948 | – | RCS  이미 사용 중인 자동응답 메시지 ID 입니다. |
| BIZCLIENT | 8949 | – | RCS  이미 사용 중인 Postback ID 입니다. |
| BIZCLIENT | 8950 | – | RCS  Persistent Menu 를 등록할 수 없습니다. |
| BIZCLIENT | 8951 | – | RCS  Persistent Menu 의 JSON 데이터 오류 |
| BIZCLIENT | 8952 | – | RCS  Reply ID 가 존재하지 않음 |
| BIZCLIENT | 8953 | – | RCS  양방향 서비스 사용불가 |
| BIZCLIENT | 8954 | – | RCS  빈 칩 리스트를 허용하지 않습니다. |
| BIZCLIENT | 8955 | – | RCS  칩 리스트를 사용할 수 없습니다. |
| BIZCLIENT | 8956 | – | RCS  버튼 필드에 Reply 를 사용할 수 없습니다. |
| BIZCLIENT | 8957 | – | RCS  칩리스트 개수 초과 |
| BIZCLIENT | 8958 | – | RCS  유효하지 않은 replyID 이거나 누락되었습니다. |
| BIZCLIENT | 8959 | – | RCS  replyID 와 일치하는 수신번호가 아님 |
| BIZCLIENT | 8960 | – | RCS  replyID 와 일치하는 챗봇 ID 가 아님 |
| BIZCLIENT | 8961 | – | RCS  메시지베이스 상품 코드가 세션 메시지 가능하지 않음 |
| BIZCLIENT | 8962 | – | RCS  챗봇이 세션 메시지 가능하지 않음 |
| BIZCLIENT | 8963 | – | RCS  요청 양방향 챗봇에 대한 정보가 존재하지 않습니다. |
| BIZCLIENT | 8964 | – | RCS  사용 불가 챗봇입니다. |
| BIZCLIENT | 8965 | – | RCS  요청 양방향 챗봇에 대한 양방향 중계사 정보가 존재하지 않습니다. |
| BIZCLIENT | 8966 | – | RCS  챗봇 MO 발송 Url 정보가 미 정의 상태 입니다. |
| BIZCLIENT | 8967 | – | RCS  MO 메시지 DB 등록 오류가 발생했습니다. |
| BIZCLIENT | 8968 | – | RCS  자동 응답 메시지 발송 수행 오류가 발생했습니다. |
| BIZCLIENT | 8969 | – | RCS  MO 메시지가 존재하지 않습니다. |
| BIZCLIENT | 8970 | – | RCS  발송 정보의 agencyKey, brandKey 가 유효하지 않습니다. |
| BIZCLIENT | 8971 | – | RCS  자동 응답 메시지 발송 권한이 없습니다. |
| BIZCLIENT | 8972 | – | RCS  대화방 MO 발송 권한이 없습니다. |
| BIZCLIENT | 8973 | – | RCS  대화방 권한 체크 DB 수행에 실패했습니다. |
| BIZCLIENT | 8974 | – | RCS  자사 고객이지만, 요청한 스펙의 RCS 메시지를 수신할 수 있는 가입자가 아닙니다. |
| BIZCLIENT | 8975 | – | RCS  메시지 발송은 정상 처리되었으나, 단말의 최종 수신 여부를 확인할 수 없는 상태입니다. |
| BIZCLIENT | 9998 | – | RCS  RCS GW 내부 서버 에러 |
| BIZCLIENT | 9999 | – | RCS  알려지지 않은 오류 |
| BIZCLIENT | 7000 | – | 알림톡 · 브랜드메시지  전달 |
| BIZCLIENT | 7058 | – | 알림톡 · 브랜드메시지  요청한 데이터가 없음 (발신프로필을 찾을 수 없음 등) |
| BIZCLIENT | 7081 | – | 알림톡 · 브랜드메시지  요청을 처리하기 위한 권한 없음 (브랜드메시지 API 를 사용할 수 없는 허브파트너) |
| BIZCLIENT | 7101 | – | 알림톡 · 브랜드메시지  카카오 형식 오류 |
| BIZCLIENT | 7103 | – | 알림톡 · 브랜드메시지  Sender key (발신프로필키) 유효하지 않음 |
| BIZCLIENT | 7106 | – | 알림톡 · 브랜드메시지  삭제된 Sender key (발신프로필키) |
| BIZCLIENT | 7107 | – | 알림톡 · 브랜드메시지  차단 상태 Sender key (발신프로필키) |
| BIZCLIENT | 7108 | – | 알림톡 · 브랜드메시지  차단 상태 카카오톡 채널 (카카오톡 채널 운영툴에서 확인) |
| BIZCLIENT | 7109 | – | 알림톡 · 브랜드메시지  닫힌 상태 카카오톡 채널 (카카오톡 채널 운영툴에서 확인) |
| BIZCLIENT | 7110 | – | 알림톡 · 브랜드메시지  삭제된 카카오톡 채널 (카카오톡 채널 운영툴에서 확인) |
| BIZCLIENT | 7111 | – | 알림톡 · 브랜드메시지  삭제 대기 상태의 카카오톡 채널 (카카오톡 채널 운영툴에서 확인) |
| BIZCLIENT | 7112 | – | 알림톡 · 브랜드메시지  유효하지 않은 사업자번호 |
| BIZCLIENT | 7113 | – | 알림톡 · 브랜드메시지  유효하지 않은 app 연결 |
| BIZCLIENT | 7115 | – | 알림톡 · 브랜드메시지  유효하지 않은 app user id 요청 |
| BIZCLIENT | 7125 | – | 알림톡 · 브랜드메시지  메시지 차단 상태의 카카오톡 채널 (카카오톡 채널 운영툴에서 확인) |
| BIZCLIENT | 7126 | – | 알림톡 · 브랜드메시지  해당 message type 에서 사용할 수 없는 response\_method 로 요청 |
| BIZCLIENT | 7127 | – | 알림톡 · 브랜드메시지  채널 메시지 제재 상태로 인한 메시지 전송 실패 |
| BIZCLIENT | 7128 | – | 알림톡 · 브랜드메시지  해당 타겟팅 옵션 사용할 수 없음 |
| BIZCLIENT | 7133 | – | 알림톡 · 브랜드메시지  템플릿 메시지 타입과 chat\_bubble\_type 파라미터 불일치 |
| BIZCLIENT | 7203 | – | 알림톡 · 브랜드메시지  친구톡 전송 시 친구대상 아님 |
| BIZCLIENT | 7204 | – | 알림톡 · 브랜드메시지  템플릿 불일치 |
| BIZCLIENT | 7205 | – | 알림톡 · 브랜드메시지  내부 시스템 오류로 이미지 정보를 읽어오는데 실패함 |
| BIZCLIENT | 7206 | – | 알림톡 · 브랜드메시지  시리얼넘버 형식 불일치 |
| BIZCLIENT | 7300 | – | 알림톡 · 브랜드메시지  기타에러 |
| BIZCLIENT | 7304 | – | 알림톡 · 브랜드메시지  지원하지 않는 스펙 |
| BIZCLIENT | 7305 | – | 알림톡 · 브랜드메시지  성공불확실(30 일 이내 수신 가능) |
| BIZCLIENT | 7306 | – | 알림톡 · 브랜드메시지  카카오 시스템 오류 |
| BIZCLIENT | 7307 | – | 알림톡 · 브랜드메시지  처리가 지연된 메시지 |
| BIZCLIENT | 7308 | – | 알림톡 · 브랜드메시지  전화번호 오류 |
| BIZCLIENT | 7311 | – | 알림톡 · 브랜드메시지  메시지가 존재하지 않음 |
| BIZCLIENT | 7314 | – | 알림톡 · 브랜드메시지  메시지 길이 초과 |
| BIZCLIENT | 7315 | – | 알림톡 · 브랜드메시지  템플릿 없음 |
| BIZCLIENT | 7318 | – | 알림톡 · 브랜드메시지  메시지를 전송할 수 없음 |
| BIZCLIENT | 7319 | – | 알림톡 · 브랜드메시지  톡 유저가 아님 |
| BIZCLIENT | 7320 | – | 알림톡 · 브랜드메시지  알림톡 수신 차단 |
| BIZCLIENT | 7321 | – | 알림톡 · 브랜드메시지  카카오톡 최소 버전 미지원 |
| BIZCLIENT | 7322 | – | 알림톡 · 브랜드메시지  메시지 발송 불가 시간 |
| BIZCLIENT | 7323 | – | 알림톡 · 브랜드메시지  메시지에 포함된 비디오를 전송할 수 없음 |
| BIZCLIENT | 7324 | – | 알림톡 · 브랜드메시지  재전송 메시지 존재하지 않음 |
| BIZCLIENT | 7325 | – | 알림톡 · 브랜드메시지  변수 글자수 제한 초과 |
| BIZCLIENT | 7326 | – | 알림톡 · 브랜드메시지  상담/봇 전환 버튼 extra, event 글자수 제한 초과 |
| BIZCLIENT | 7327 | – | 알림톡 · 브랜드메시지  버튼 내용이 템플릿과 일치하지 않음 |
| BIZCLIENT | 7328 | – | 알림톡 · 브랜드메시지  메시지 강조 표기 타이틀이 템플릿과 일치하지 않음 |
| BIZCLIENT | 7329 | – | 알림톡 · 브랜드메시지  메시지 강조 표기 타이틀 길이 제한 초과 (50 자) |
| BIZCLIENT | 7330 | – | 알림톡 · 브랜드메시지  메시지 타입과 템플릿 강조유형이 일치하지 않음 |
| BIZCLIENT | 7331 | – | 알림톡 · 브랜드메시지  헤더가 템플릿과 일치하지 않음 |
| BIZCLIENT | 7332 | – | 알림톡 · 브랜드메시지  헤더 길이 제한 초과(16 자) |
| BIZCLIENT | 7333 | – | 알림톡 · 브랜드메시지  아이템 하이라이트가 템플릿과 일치하지 않음 |
| BIZCLIENT | 7334 | – | 알림톡 · 브랜드메시지  아이템 하이라이트 타이틀 길이 제한 초과 (이미지 없는 경우 30 자, 이미지 있는 경우 21 자) |
| BIZCLIENT | 7335 | – | 알림톡 · 브랜드메시지  아이템 하이라이트 디스크립션 길이 제한 초과 (이미지 없는 경우 19 자, 이미지 있는 경우 13 자) |
| BIZCLIENT | 7336 | – | 알림톡 · 브랜드메시지  아이템 리스트가 템플릿과 일치하지 않음 |
| BIZCLIENT | 7337 | – | 알림톡 · 브랜드메시지  아이템 리스트의 아이템의 디스크립션 길이 제한 초과(23 자) |
| BIZCLIENT | 7338 | – | 알림톡 · 브랜드메시지  아이템 요약정보가 템플릿과 일치하지 않음 |
| BIZCLIENT | 7339 | – | 알림톡 · 브랜드메시지  아이템 요약정보의 디스크립션 길이 제한 초과(14 자) |
| BIZCLIENT | 7340 | – | 알림톡 · 브랜드메시지  아이템 요약정보의 디스크립션에 허용되지 않은 문자 포함 (통화기호/코드, 숫자, 콤마, 소수점, 공백을 제외한 문자 포함) |
| BIZCLIENT | 7341 | – | 알림톡 · 브랜드메시지  와이드 아이템 리스트 갯수 최대 최소 갯수 불일치 |
| BIZCLIENT | 7342 | – | 알림톡 · 브랜드메시지  대표링크가 템플릿과 일치하지 않음 |
| BIZCLIENT | 7343 | – | 알림톡 · 브랜드메시지  이미지 변수 개수 템플릿 불일치 |
| BIZCLIENT | 7344 | – | 알림톡 · 브랜드메시지  커머스 쿠폰 변수 템플릿 불일치 |
| BIZCLIENT | 7345 | – | 알림톡 · 브랜드메시지  커머스 정보 변수 템플릿 불일치 |
| BIZCLIENT | 7346 | – | 알림톡 · 브랜드메시지  부가 정보 최대 길이 제한 오류 |
| BIZCLIENT | 7347 | – | 알림톡 · 브랜드메시지  커머스 정보 상품명 최대 길이 제한 오류 |
| BIZCLIENT | 7348 | – | 알림톡 · 브랜드메시지  유효하지 않은 그룹 태그 키 입력 |
| BIZCLIENT | 7350 | – | 알림톡 · 브랜드메시지  수신동의거부 스펙 (N 타입) 미지원 |
| BIZCLIENT | 7351 | – | 알림톡 · 브랜드메시지  캐러셀 아이템 리스트 갯수 최소, 최대 갯수 불일치 |
| BIZCLIENT | 7352 | – | 알림톡 · 브랜드메시지  캐러셀 아이템 메시지 길이 OVER |
| BIZCLIENT | 7353 | – | 알림톡 · 브랜드메시지  캐러셀이 템플릿과 일치하지 않음 |
| BIZCLIENT | 7356 | – | 알림톡 · 브랜드메시지  와이드 리스트 타이틀 길이 제한 오류 |
| BIZCLIENT | 7358 | – | 알림톡 · 브랜드메시지  캐러셀 헤더 길이 제한 오류 |
| BIZCLIENT | 7400 | – | 알림톡 · 브랜드메시지  입력값 오류 발생 |
| BIZCLIENT | 7401 | – | 알림톡 · 브랜드메시지  Request Body Parsing Error |
| BIZCLIENT | 7410 | – | 알림톡 · 브랜드메시지  시리얼 넘버 오류 |
| BIZCLIENT | 7411 | – | 알림톡 · 브랜드메시지  시리얼 넘버 Prefix 날짜 오류 |
| BIZCLIENT | 7412 | – | 알림톡 · 브랜드메시지  Template 요청 오류 |
| BIZCLIENT | 7413 | – | 알림톡 · 브랜드메시지  유효한 허브파트너를 찾을 수 없음 |
| BIZCLIENT | 7414 | – | 알림톡 · 브랜드메시지  유효한 발신프로필을 찾을 수 없음 |
| BIZCLIENT | 7415 | – | 알림톡 · 브랜드메시지  유효한 계약을 찾을 수 없음 |
| BIZCLIENT | 7419 | – | 알림톡 · 브랜드메시지  유효하지 않은 챗버블 타입 또는 메시지 타입 요청 |
| BIZCLIENT | 7420 | – | 알림톡 · 브랜드메시지  메시지 요청 페이로드 생성 오류 |
| BIZCLIENT | 7421 | – | 알림톡 · 브랜드메시지  타임아웃 |
| BIZCLIENT | 7422 | – | 알림톡 · 브랜드메시지  메시지 결과 조회 오류 |
| BIZCLIENT | 7423 | – | 알림톡 · 브랜드메시지  메시지 발송 대상 오류 |
| BIZCLIENT | 7432 | – | 알림톡 · 브랜드메시지  템플릿 변수 불일치 |
| BIZCLIENT | 7433 | – | 알림톡 · 브랜드메시지  중단된 템플릿 |
| BIZCLIENT | 7434 | – | 알림톡 · 브랜드메시지  변경된 템플릿 |
| BIZCLIENT | 7435 | – | 알림톡 · 브랜드메시지  지갑 정보를 찾을 수 없음 |
| BIZCLIENT | 7436 | – | 알림톡 · 브랜드메시지  지갑 잔액 부족 |
| BIZCLIENT | 7437 | – | 알림톡 · 브랜드메시지  메시지 요청 실패 |
| BIZCLIENT | 7438 | – | 알림톡 · 브랜드메시지  브랜드메시지 메시지 개수 제한 |
| BIZCLIENT | 7443 | – | 알림톡 · 브랜드메시지  만료된 요청 |
| BIZCLIENT | 7444 | – | 알림톡 · 브랜드메시지  본문 길이 제한 (30KB) 초과 |
| BIZCLIENT | 7450 | – | 알림톡 · 브랜드메시지  계약을 찾을 수 없음 |
| BIZCLIENT | 7451 | – | 알림톡 · 브랜드메시지  중복된 계약 |
| BIZCLIENT | 7452 | – | 알림톡 · 브랜드메시지  이미 종료된 계약 |
| BIZCLIENT | 7453 | – | 알림톡 · 브랜드메시지  처리중인 계약 |
| BIZCLIENT | 7454 | – | 알림톡 · 브랜드메시지  계약 생성 실패 |
| BIZCLIENT | 7455 | – | 알림톡 · 브랜드메시지  계약 종료 실패 |
| BIZCLIENT | 7456 | – | 알림톡 · 브랜드메시지  최대 발송수 초과 |
| BIZCLIENT | 7460 | – | 알림톡 · 브랜드메시지  개인화 메시지 과금 정보를 찾을 수 없음 |
| BIZCLIENT | 7461 | – | 알림톡 · 브랜드메시지  처리중인 메시지 |
| BIZCLIENT | 7521 | – | 알림톡 · 브랜드메시지  중복발신제한 |
| BIZCLIENT | 7522 | – | 알림톡 · 브랜드메시지  스팸 차단 |
| BIZCLIENT | 7523 | – | 알림톡 · 브랜드메시지  080 수신거부 (SPAM) |
| BIZCLIENT | 4100 | – | SMS  전달 |
| BIZCLIENT | 4400 | – | SMS  음영 지역 |
| BIZCLIENT | 4401 | – | SMS  단말기 전원 꺼짐 |
| BIZCLIENT | 4402 | – | SMS  단말기 메시지 저장 초과 |
| BIZCLIENT | 4403 | – | SMS  메시지 삭제 됨 |
| BIZCLIENT | 4404 | – | SMS  가입자 위치 정보 없음 |
| BIZCLIENT | 4405 | – | SMS  단말기 BUSY |
| BIZCLIENT | 4410 | – | SMS  잘못된 번호 |
| BIZCLIENT | 4420 | – | SMS  기타에러 |
| BIZCLIENT | 4430 | – | SMS  스팸 |
| BIZCLIENT | 4431 | – | SMS  발송 제한 수신거부(스팸) |
| BIZCLIENT | 4411 | – | SMS  NPDB 에러 |
| BIZCLIENT | 4412 | – | SMS  착신거절 |
| BIZCLIENT | 4413 | – | SMS  SMSC 형식오류 |
| BIZCLIENT | 4414 | – | SMS  비가입자, 결번, 서비스정지 |
| BIZCLIENT | 4421 | – | SMS  타임아웃 |
| BIZCLIENT | 4422 | – | SMS  단말기일시정지 |
| BIZCLIENT | 4423 | – | SMS  단말기착신거부 |
| BIZCLIENT | 4424 | – | SMS  URL SMS 미지원폰 |
| BIZCLIENT | 4425 | – | SMS  단말기 호 처리 중 |
| BIZCLIENT | 4426 | – | SMS  재시도한도초과 |
| BIZCLIENT | 4427 | – | SMS  기타 단말기 문제 |
| BIZCLIENT | 4428 | – | SMS  시스템에러 |
| BIZCLIENT | 4432 | – | SMS  회신번호 차단(개인) |
| BIZCLIENT | 4433 | – | SMS  회신번호 차단(기업) |
| BIZCLIENT | 4434 | – | SMS  회신번호 사전 등록제에 의한 미등록 차단 |
| BIZCLIENT | 4435 | – | SMS  KISA 신고 스팸 회신 번호 차단 |
| BIZCLIENT | 4436 | – | SMS  회신번호 사전 등록제 번호규칙 위반 |
| BIZCLIENT | 4437 | – | SMS  중복발신제한 |
| BIZCLIENT | 4443 | – | SMS  스팸 차단 |
| BIZCLIENT | 6600 | – | MMS  전달 |
| BIZCLIENT | 6601 | – | MMS  타임 아웃 |
| BIZCLIENT | 6602 | – | MMS  핸드폰 호 처리 중 |
| BIZCLIENT | 6603 | – | MMS  음영 지역 |
| BIZCLIENT | 6604 | – | MMS  전원이 꺼져 있음 |
| BIZCLIENT | 6605 | – | MMS  메시지 저장개수 초과 |
| BIZCLIENT | 6606 | – | MMS  잘못된 번호 |
| BIZCLIENT | 6607 | – | MMS  서비스 일시 정지 |
| BIZCLIENT | 6608 | – | MMS  기타 단말기 문제 |
| BIZCLIENT | 6609 | – | MMS  착신 거절 |
| BIZCLIENT | 6610 | – | MMS  기타에러 |
| BIZCLIENT | 6611 | – | MMS  통신사의 SMC 형식 오류 |
| BIZCLIENT | 6612 | – | MMS  게이트웨이의 형식 오류 |
| BIZCLIENT | 6613 | – | MMS  서비스 불가 단말기 |
| BIZCLIENT | 6614 | – | MMS  핸드폰 호 불가 상태 |
| BIZCLIENT | 6615 | – | MMS  SMC 운영자에 의해 삭제 |
| BIZCLIENT | 6616 | – | MMS  통신사의 메시지 큐 초과 |
| BIZCLIENT | 6617 | – | MMS  통신사의 스팸 처리 |
| BIZCLIENT | 6618 | – | MMS  공정위의 스팸 처리 |
| BIZCLIENT | 6619 | – | MMS  게이트웨이의 스팸 처리 |
| BIZCLIENT | 6620 | – | MMS  발송 건수 초과 |
| BIZCLIENT | 6621 | – | MMS  메시지의 길이 초과 |
| BIZCLIENT | 6622 | – | MMS  잘못된 번호 형식 |
| BIZCLIENT | 6623 | – | MMS  잘못된 데이터 형식 |
| BIZCLIENT | 6624 | – | MMS  MMS 정보를 찾을 수 없음 |
| BIZCLIENT | 6625 | – | MMS  NPDB 에러 |
| BIZCLIENT | 6626 | – | MMS  080 수신거부(SPAM) |
| BIZCLIENT | 6627 | – | MMS  발송제한 수신거부(SPAM) |
| BIZCLIENT | 6628 | – | MMS  회신번호 차단(개인) |
| BIZCLIENT | 6629 | – | MMS  회신번호 차단(기업) |
| BIZCLIENT | 6630 | – | MMS  서비스 불가 번호 |
| BIZCLIENT | 6631 | – | MMS  회신번호 사전 등록제에 의한 미등록 차단 |
| BIZCLIENT | 6632 | – | MMS  KISA 신고 스팸 회신 번호 차단 |
| BIZCLIENT | 6633 | – | MMS  회신번호 사전 등록제 번호규칙 위반 |
| BIZCLIENT | 6634 | – | MMS  중복발신제한 |
| BIZCLIENT | 6641 | – | MMS  스팸 차단 |
| BIZCLIENT | 6670 | – | MMS  첨부파일 사이즈 초과(60K) |
| BIZCLIENT | 3200 | – | FAX  전달 |
| BIZCLIENT | 3241 | – | FAX  부분전달 |
| BIZCLIENT | 3211 | – | FAX  통화 중 |
| BIZCLIENT | 3212 | – | FAX  응답 없음 |
| BIZCLIENT | 3213 , 3214 | – | FAX  잘못된 번호 |
| BIZCLIENT | 3242 | – | FAX  사람이 받음 |
| BIZCLIENT | 3216 | – | FAX  호 개통거부 |
| BIZCLIENT | 3217 | – | FAX  번호고장 |
| BIZCLIENT | 3219 | – | FAX  중계선 호 폭주 |
| BIZCLIENT | 3220 | – | FAX  호 개통 시간초과 |
| BIZCLIENT | 3221 | – | FAX  내부시스템 장애 |
| BIZCLIENT | 3222 | – | FAX  발신번호 사전 등록제에 의한 미등록 차단 |
| BIZCLIENT | 3223 | – | FAX  발신번호 사전 등록제 번호규칙 위반 |
| BIZCLIENT | 3243 | – | FAX  송신프로토콜 에러 |
| BIZCLIENT | 3244 | – | FAX  데이터 불량 |
| BIZCLIENT | 3245 | – | FAX  데이터 없음 |
| BIZCLIENT | 3218 , 3250 | – | FAX  기타에러 |
| BIZCLIENT | 3260 | – | FAX  중복발신제한 |
| BIZCLIENT | 1200 | – | PHONE  청취 |
| BIZCLIENT | 1201 | – | PHONE  청취 후 중간종료 |
| BIZCLIENT | 1202 | – | PHONE  청취 후 답변 |
| BIZCLIENT | 1211 | – | PHONE  통화 중 |
| BIZCLIENT | 1212 | – | PHONE  부재 중 |
| BIZCLIENT | 1213 , 1214 | – | PHONE  잘못된 전화번호 |
| BIZCLIENT | 1216 | – | PHONE  호 개통거부 |
| BIZCLIENT | 1217 | – | PHONE  번호고장 |
| BIZCLIENT | 1219 | – | PHONE  중계선 호 폭주 |
| BIZCLIENT | 1220 | – | PHONE  호 개통 시간초과 |
| BIZCLIENT | 1221 | – | PHONE  내부시스템 장애 |
| BIZCLIENT | 1231 | – | PHONE  시나리오 not found |
| BIZCLIENT | 1232 | – | PHONE  시나리오 CGI error |
| BIZCLIENT | 1233 | – | PHONE  Invalid VXML |
| BIZCLIENT | 1234 | – | PHONE  시나리오 DNS error |
| BIZCLIENT | 1218 , 1250 | – | PHONE  기타에러 |
| BIZCLIENT | 2201 | – | PHONE  청취 |
| BIZCLIENT | 2202 | – | PHONE  청취 후 답변 |
| BIZCLIENT | 2211 | – | PHONE  통화 중 |
| BIZCLIENT | 2200 , 2212 | – | PHONE  부재 중 |
| BIZCLIENT | 2213 , 2214 | – | PHONE  잘못된 전화번호 |
| BIZCLIENT | 2216 | – | PHONE  호 개통거부 |
| BIZCLIENT | 2217 | – | PHONE  번호고장 |
| BIZCLIENT | 2219 | – | PHONE  중계선호폭주 |
| BIZCLIENT | 2220 | – | PHONE  호 개통 시간초과 |
| BIZCLIENT | 2221 | – | PHONE  내부시스템 장애 |
| BIZCLIENT | 2231 | – | PHONE  시나리오 Not found |
| BIZCLIENT | 2232 | – | PHONE  시나리오 CGI error |
| BIZCLIENT | 2233 | – | PHONE  Invalid VXML |
| BIZCLIENT | 2234 | – | PHONE  시나리오 DNS error |
| BIZCLIENT | 2218 , 2250 | – | PHONE  기타에러 |
| 카카오 (KAPI) | 200 | 200 | 응답 코드  요청 성공 |
| 카카오 (KAPI) | 101 | 200 | 응답 코드  사용자 없음 |
| 카카오 (KAPI) | 102 | 200 | 응답 코드  유효하지 않은 APIKEY |
| 카카오 (KAPI) | 103 | 200 | 응답 코드  중지된 사용자 |
| 카카오 (KAPI) | 403 | 200 | 응답 코드  권한 없음 |
| 카카오 (KAPI) | 405 | 200 | 응답 코드  파라미터 오류 |
| 카카오 (KAPI) | 504 | 200 | 응답 코드  템플릿 코드 중복 |
| 카카오 (KAPI) | 505 | 200 | 응답 코드  템플릿 이름 중복 |
| 카카오 (KAPI) | 506 | 200 | 응답 코드  템플릿 내용이 1,300자 초과 |
| 카카오 (KAPI) | 507 | 200 | 응답 코드  유효하지 않은 발신 프로필 |
| 카카오 (KAPI) | 508 | 200 | 응답 코드  요청한 데이터가 없음 (삭제 상태의 데이터 요청 시 응답) |
| 카카오 (KAPI) | 509 | 200 | 응답 코드  요청을 처리할 수 있는 상태가 아님 (예: 템플릿 검수 요청이 가능한 상태가 아님) |
| 카카오 (KAPI) | 510 | 200 | 응답 코드  템플릿의 버튼/바로연결 형식이 유효하지 않음 |
| 카카오 (KAPI) | 511 | 200 | 응답 코드  대표링크/버튼/바로연결의 링크가 유효하지 않음 |
| 카카오 (KAPI) | 512 | 200 | 응답 코드  발신프로필 추가 및 그룹 내 발신프로필 추가가 제한된 상태 |
| 카카오 (KAPI) | 513 | 200 | 응답 코드  메시지 결과 수신 채널이 올바르지 않음 |
| 카카오 (KAPI) | 514 | 200 | 응답 코드  비즈니스 인증이 필요한 카카오톡 채널 |
| 카카오 (KAPI) | 518 | 200 | 응답 코드  개발자용 채널은 등록 불가 |
| 카카오 (KAPI) | 519 | 200 | 응답 코드  등록하려는 발신프로필 채널의 고객센터 정보 입력 필요 |
| 카카오 (KAPI) | 525 | 200 | 응답 코드  템플릿의 카테고리가 유효하지 않음 |
| 카카오 (KAPI) | 530 | 200 | 응답 코드  유효하지 않은 비즈플러그인 |
| 카카오 (KAPI) | 554 | 200 | 응답 코드  그룹태그 정보가 존재하지 않음 |
| 카카오 (KAPI) | 555 | 200 | 응답 코드  그룹태그 이름 길이 제한 실패 |
| 카카오 (KAPI) | 556 | 200 | 응답 코드  그룹태그 이름 형식이 올바르지 않음 |
| 카카오 (KAPI) | 557 | 200 | 응답 코드  그룹태그 키 정보가 유효하지 않음 |
| 카카오 (KAPI) | 600 | 200 | 응답 코드  이미지 업로드 실패 |
| 카카오 (KAPI) | 610 | 200 | 응답 코드  파일 업로드 실패 |
| 카카오 (KAPI) | 611 | 200 | 응답 코드  첨부파일의 크기가 50MB를 초과 |
| 카카오 (KAPI) | 612 | 200 | 응답 코드  첨부파일 형식이 유효하지 않음 |
| 카카오 (KAPI) | 613 | 200 | 응답 코드  첨부파일의 개수가 10개를 초과 |
| 카카오 (KAPI) | 614 | 200 | 응답 코드  첨부파일이 존재하지 않음 |
| 카카오 (KAPI) | 620 | 200 | 응답 코드  잘못된 전화번호 형식 |
| 카카오 (KAPI) | 621 | 200 | 응답 코드  파라미터 오류 |
| 카카오 (KAPI) | 801~805 | 200 | 응답 코드  발신프로필 등록이 차단된 상태 |
| 카카오 (KAPI) | 811 | 200 | 응답 코드  발신프로필 등록이 차단된 허브파트너 |
| 카카오 (KAPI) | 950 | 200 | 응답 코드  쿼터 초과 |
| 카카오 (KAPI) | 3018 | 200 | 응답 코드  메시지를 발송할 수 없음 |
| 카카오 (KAPI) | 3019 | 200 | 응답 코드  카카오톡 유저가 아님 |
| 카카오 (KAPI) | 3020 | 200 | 응답 코드  알림톡 수신 차단 |
| 카카오 (KAPI) | 3021 | 200 | 응답 코드  카카오톡 최소 버전 미지원 |
| 카카오 (KAPI) | 4044 | 200 | 응답 코드  브랜드메시지 템플릿 관련 요청을 처리할 수 있는 상태가 아님 (예: "템플릿 삭제는 등록 상태에서만 가능합니다.") |
| 카카오 (KAPI) | 0000 | – | 브랜드 이미지 업로드 코드  - · 요청 성공 |
| 카카오 (KAPI) | 1002 | – | 브랜드 이미지 업로드 코드  InvalidHubPartnerKey · 허브파트너 키가 유효하지 않음 |
| 카카오 (KAPI) | 1003 | – | 브랜드 이미지 업로드 코드  MissingRequiredParameterException · 필수 파라미터가 없음 |
| 카카오 (KAPI) | 2001 | – | 브랜드 이미지 업로드 코드  InvalidImageLengthException · 이미지 용량 초과 |
| 카카오 (KAPI) | 2002 | – | 브랜드 이미지 업로드 코드  InvalidImageShapeException · 발송할 수 없는 이미지 사이즈 |
| 카카오 (KAPI) | 2003 | – | 브랜드 이미지 업로드 코드  InvalidImageFormatException · 지원하지 않는 이미지 형식 |
| 카카오 (KAPI) | 2004 | – | 브랜드 이미지 업로드 코드  FailedToUploadImageException · 내부 시스템 오류로 업로드 실패 |
| 카카오 (KAPI) | 2005 | – | 브랜드 이미지 업로드 코드  InvalidImageCountException · 업로드 가능한 이미지 개수 초과 |
| 카카오 (KAPI) | 6000 | – | 브랜드 이미지 업로드 코드  result.failure[].error.message 참고 · 일부 이미지 업로드 실패 |
| 카카오 (KAPI) | 413 | – | 브랜드 이미지 업로드 코드  Request Entity Too Large · 이미지 용량 초과 |
| 네이버 (NAPI) | 200 | 200 | 요청 성공 |
| 네이버 (NAPI) | 400 | 200 | 잘못된 요청입니다. |
| 네이버 (NAPI) | 401 | 200 | 인증 정보가 없습니다. |
| 네이버 (NAPI) | 403 | 200 | 접근 권한이 없습니다. |
| 네이버 (NAPI) | 404 | 200 | 존재하지 않는 페이지입니다. |
| 네이버 (NAPI) | 429 | 429 | 요청 가능 횟수를 초과하였습니다. |
| 네이버 (NAPI) | 500 | 200 | 내부 에러 |


---

# 운영 가이드

> 원본: [https://bizppurio.github.io/guides/operations/](https://bizppurio.github.io/guides/operations/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

GUIDE · 운영

# 공통 운영

## FAQ

### 일반

### Q. 검수 환경과 운영 환경이 어떻게 다른가요?

| 환경 | API 도메인 | BIZCLIENT 도메인 | 비고 |
| --- | --- | --- | --- |
| 검수 | `dev-api.bizppurio.com` | `biztest.ppurio.com:38300, 38400` (2025-08-14 기준 미지원) | SSL 인증서 검증 우회 가능 |
| 운영 | `api.bizppurio.com` | `bizppurio.com:38300, 38400` | SSL 검증 필수 |

**검수와 운영은 별도의 비즈뿌리오 계정**으로 운영하는 것을 권장합니다.

### Q. 메시지 보관 기간은?

- 비즈뿌리오 서버 메시지 데이터: **35일**
- Polling 결과 데이터: **3일** (조회 또는 완료 처리 안 하면 제거)
- RCS 이미지: **365일** (등록일 기준)

### Q. 단가/요금 정보는 어디서 확인하나요?

비즈뿌리오 [고객센터](mailto:bizppurio@daou.co.kr) 또는 사이트 내 [요금 안내] 메뉴 참고.

### API 호출

### Q. `code 1000`인데 메시지가 안 와요.

`code 1000`은 **비즈뿌리오 서버 접수 성공**을 의미할 뿐, 단말 도달 성공은 아닙니다. 실제 도달 결과는 [Webhook](https://bizppurio.github.io/bizapi#전송-결과-조회) 또는 [Polling](https://bizppurio.github.io/bizapi#전송-결과-조회)으로 받는 `RESULT` 코드를 확인하세요 (`4100`/`6600`/`7000`/`8000`/`5000` = 도달 성공).

### Q. HTTP 429를 어떻게 다루나요?

`RateLimit-Reset` 헤더 값만큼 대기 후 재시도. 자세한 패턴은 [Rate Limit 다루기](https://bizppurio.github.io/bizapi#rate-limit) 참고.

### Q. 토큰 만료 시간을 알 수 있나요?

`/v1/token` 응답의 `expired` 필드 (`yyyyMMddHHmmss` 형식). 만료 1분 전에 재발급하는 것을 권장.

### Q. 동일 메시지 키로 두 번 호출하면 어떻게 되나요?

`refkey`는 **고객사가 부여한 식별자**일 뿐, 비즈뿌리오에서 중복 체크하지 않습니다. 같은 `refkey`로 두 번 호출하면 두 건 발송됩니다. 중복 발송을 막으려면 [BIZCLIENT 중복 발송 제한](https://bizppurio.github.io/bizclient#운영-설정-옵션) 사용 또는 호출 측에서 idempotency 보장.

### BIZCLIENT

### Q. 한 비즈뿌리오 계정을 여러 BIZCLIENT 모듈에 사용할 수 있나요?

**권장하지 않습니다.** 결과 리포트가 분산되어 일부 메시지의 결과 매핑이 누락될 수 있습니다. **하나의 모듈에 하나의 계정** 원칙.

### Q. `STATUS = 13`이 무엇인가요?

대체 발송이 지정되었으나 **비즈뿌리오 ID가 대체 발송 사용 가능 상태가 아닐 때** 발생. 비즈뿌리오 [고객센터](mailto:bizppurio@daou.co.kr)에 대체 발송 사용 신청 필요. 자세한 내용은 [BIZCLIENT 발송상태변화](https://bizppurio.github.io/bizclient#용어) 참고.

### Q. RCS 발송이 시작되지 않습니다.

기본 설정에서는 RCS 테이블이 자동 생성되지 않습니다. `uds.conf`에 `MESSAGE_SUPPORT_TYPE = ALL`을 추가하고 모듈을 재구동하세요. [BIZCLIENT 설치 — RCS 활성화](https://bizppurio.github.io/bizclient#설치) 참고.

### Q. CMID에 한글을 사용할 수 있나요?

기본은 ASCII 문자만 허용. `uds.conf`에서 CMID 추가 문자 사용 옵션을 활성화하면 한글·특수문자 사용 가능 (`biz_client_v4000` 이상). 단, 부분적으로 호환성 이슈 가능성이 있어 ASCII 권장.

### Q. `STATUS = 0`에서 더 이상 진행되지 않습니다.

BIZCLIENT 모듈이 큐를 픽업하지 못한 상태입니다. ① 모듈 구동 여부 확인 (Linux/Unix: 프로세스 / Windows: 서비스 상태), ② `uds.conf` 의 DB 연결 정보(`DBNAME` / `DBURL` / `DBUSER` / `DBPASS`) 확인, ③ BIZCLIENT 가 큐 테이블을 폴링할 수 있는 DB 접근 권한 확인.

### Q. `STATUS = 2`인데 `CALL_STATUS`가 비어 있습니다.

비즈뿌리오 서버가 아직 결과를 회신하지 않은 상태로, 통상 1~5분 내 갱신됩니다. 5분 이상 지연되면 비즈뿌리오 [발송 조회]에서 메시지 상태를 직접 확인하세요.

### Q. 결과 리포트가 일부만 반영됩니다.

한 번에 발송한 메시지의 `CMID`가 중복되면 결과 매핑이 일부 누락될 수 있습니다. `CMID`는 트랜잭션마다 유일한 값을 사용하세요. 동일 계정을 여러 모듈에 세팅한 경우에도 리포트가 분산되므로 한 모듈 = 한 계정 원칙을 지키세요.

### Q. Microsoft SQL Server를 사용합니다. 추가 설정이 있나요?

`uds.conf`에서 `DBNAME = MSSQL2005`로 설정하고(2008 이상도 동일), `lib/jdbc/`의 JDBC 드라이버를 사용 중인 SQL Server 버전에 맞게 교체하세요(기본 드라이버는 낮은 버전).

### Q. BIZCLIENT 운영 시 권고 사항이 있나요?

- 하나의 모듈에는 하나의 비즈뿌리오 계정만 세팅하세요 (결과 리포트 분산 방지).
- DBMS 버전 업그레이드 시 `lib/jdbc/` JDBC 드라이버도 함께 교체하세요.
- 정기 점검 시 로그 테이블(`BIZ_LOG_YYYYMM` 등) 용량을 모니터링하세요 (월별 누적).
- 백업 옵션(`RCS_BACKUP_OPTION`, `ATTACHMENTS_BACKUP_OPTION`) 동작에 따라 데이터 보관 정책이 달라집니다.

### 카카오 비즈메시지

### Q. 알림톡과 브랜드메시지 중 무엇을 써야 하나요?

| 사용 | 채널 |
| --- | --- |
| 정보성 (주문/배송/예약 안내) | **알림톡** — 친구 추가 불필요 |
| 광고/마케팅 | **브랜드메시지** — 채널 친구 또는 마케팅 수신 동의 유저 |

### Q. 알림톡 본문이 1000자가 넘으면?

API 규격서는 1300자, BIZCLIENT 매뉴얼은 1000자로 표기되어 있습니다. **보수적인 1000자 기준**을 권장하며, 초과 시 코드 `7314` (메시지 길이 제한 초과) 발생.

### Q. 브랜드메시지 발송 시간 제약이 있나요?

**08:00 ~ 20:50 KST** (광고성 메시지). 21시 이후 또는 8시 이전 발송 시 코드 `7322` 발생. 해외 사용자는 시간 제한 없음.

### Q. 알림톡 템플릿 검수 기간은?

평균 **영업일 1~3일**. 템플릿 등록 후 [/v3/kakao/template/request](https://bizppurio.github.io/kapi#알림톡-템플릿)로 검수 요청 → 카카오 검수.

### Q. 발신프로필이 휴면(DMT) 상태입니다.

[발신프로필 휴면 해제 API](https://bizppurio.github.io/kapi#프로필) 호출 또는 비즈뿌리오 사이트에서 해제. 휴면 해제 후 30일 미사용 시 재 휴면.

### RCS

### Q. RCS 메시지가 일부 단말에서 안 와요.

안드로이드 RCS는 **채팅+ 지원 단말**(Samsung 갤럭시 등)만 수신 가능. iOS와 일부 안드로이드는 [통합 RCS](https://bizppurio.github.io/bizapi#메시지-전송)(`RP*` 메시지베이스)를 사용해야 합니다 (iOS 26+, Android 10+).

### Q. RCS 이미지를 한 번 업로드한 뒤 계속 사용할 수 있나요?

등록일로부터 **365일** 사용 가능. 이후 자동 삭제되므로 만료 관리 필요.

### Q. RCS 발송 시 `agencykey` / `brandkey`가 자꾸 실패합니다.

2023.08.01 이후 잘못된 `agencykey` / `brandkey`는 발송 실패 처리됩니다. RBC에서 발급된 정확한 키를 사용하세요. 코드 `8970` 발생.

### 발송 결과

### Q. Webhook과 Polling 중 무엇을 쓰나요?

기본은 **Webhook** 권장. 사내망에서 인바운드 통신이 막혀있다면 Polling. 자세한 비교는 [전송 결과 수신 가이드](https://bizppurio.github.io/bizapi#결과-수신) 참고.

### Q. 결과를 못 받았어요. 어떻게 복구하나요?

[POST /v2/report](https://bizppurio.github.io/bizapi#전송-결과-조회)로 결과 재요청. 35일 이내 메시지만 가능.

### Q. 발송 후 얼마나 기다려야 결과를 받나요?

| 채널 | 통상 시간 |
| --- | --- |
| SMS / LMS / MMS | 즉시 ~ 수 분 |
| 알림톡 / 브랜드메시지 | 즉시 ~ 1분 |
| RCS | 즉시 ~ 1분 |
| 카카오 결과 (`7305`) | 30일 이내 (성공 불확실 케이스) |

5분 이상 결과가 없으면 비즈뿌리오 [발송 조회] 사이트에서 직접 확인.

### 장애 대응

### Q. Webhook 수신 서버가 잠시 다운되면 결과가 유실되나요?

유실되지 않습니다. 비즈뿌리오는 PUSH 실패 시 일정 횟수 재시도한 뒤 결과를 보관합니다. 고객사 서버 복구 후 [POST /v2/report](https://bizppurio.github.io/bizapi#전송-결과-조회)로 누락분을 복구하세요 (35일 이내).

### Q. BIZCLIENT 모듈이 비정상 종료되면 남은 메시지는 어떻게 처리되나요?

모듈 종료 시점의 `STATUS=7`(발송 중) 메시지가 남습니다. 모듈을 재구동하면 `SEND_VALID_TIME` 옵션이 일정 시간 경과한 미발송 메시지를, `REMOVE_PRESEND_MSG_OPTION`이 `STATUS=7` 잔존 메시지를 자동 실패 처리합니다. 자세한 옵션은 [재시도 정책](https://bizppurio.github.io/guides/operations#재시도-정책)의 잔존 메시지 처리 참고.

### Q. 일시적으로 Rate Limit에 걸리면 어떻게 대응하나요?

`HTTP 429` + `code 5002` 응답 시 `RateLimit-Reset` 헤더 값만큼 대기한 뒤 재시도하세요. 빈번하게 발생하면 한도 상향을 신청하거나 호출을 분산합니다.

### Q. 토큰이 만료되어 호출이 실패하면?

`code 3002` / `3005` 응답 시 `/v1/token`으로 새 토큰을 발급받아 동일 요청을 1회 재시도하세요.

### 채널 선택

### Q. 처음 연동할 때 어떤 채널부터 추천하나요?

1. **SMS** — 가장 단순, 모든 단말에 도달
2. **LMS / MMS** — 본문 길거나 이미지 필요 시
3. **알림톡** — 카카오톡 사용자 대상 정보성 (단가 저렴)
4. **브랜드메시지** — 마케팅
5. **RCS** — 풍부한 표현이 필요한 경우

### Q. 채널을 자동으로 골라주는 기능이 있나요?

**대체 발송**으로 비슷한 효과를 얻을 수 있습니다. 예: 알림톡 본 발송 → 카카오톡 미사용 단말은 SMS로 자동 대체. 자세한 내용은 [대체 발송 가이드](https://bizppurio.github.io/bizapi#대체-발송) 참고.

## IP 허용 정책

### 발신 IP 화이트리스트 (API)

비즈뿌리오 API는 계정별로 **접속 허용 IP**가 사전 등록되어 있어야 합니다. 미등록 IP에서 호출하면 다음 코드로 거부됩니다.

| 코드 | 설명 |
| --- | --- |
| `3000` | 비즈뿌리오 계정에 접속 허용 IP가 등록되어 있지 않음 |
| `3003` | IP가 유효하지 않음 |
| `3010` | 비즈뿌리오 계정에 등록된 접속 허용 IP와 일치하지 않음 |

### 등록 방법

비즈뿌리오 사이트의 **[내 정보] → [API 관리]** 메뉴 또는 [고객센터](mailto:bizppurio@daou.co.kr)로 다음 정보 전달:

- 비즈뿌리오 계정 (`bizId`)
- 등록할 IP / IP 대역
- 운영 / 검수 환경 구분

> **NOTE:** **검수 vs 운영 IP 분리** — 검수(`dev-api.bizppurio.com`)와 운영(`api.bizppurio.com`)은 별도 계정으로 운영하는 것을 권장합니다. 각 환경별로 다른 IP를 등록하여 코드/네트워크 격리.

> **NOTE:** API Webhook 수신 URL 등록·운영 권장은 [전송 결과 수신 가이드](https://bizppurio.github.io/bizapi#결과-수신)로 통합되었습니다.

### BIZCLIENT 방화벽 정책

BIZCLIENT 모듈이 비즈뿌리오 서버로 outbound 통신을 합니다.

| 환경 | 도메인 | 포트 | 방향 |
| --- | --- | --- | --- |
| 운영 | `bizppurio.com` | 38300, 38400 | **Outbound** |
| 검수 | `biztest.ppurio.com` | 38300, 38400 | **Outbound** (2025-08-14 기준 미지원) |

> **WARNING:** BIZCLIENT는 **양방향 연결을 만들지 않습니다.** 모듈이 서버로 outbound 연결만 하면 결과 리포트도 같은 채널로 수신됩니다. 인바운드 방화벽 추가 작업은 불필요.

### 채널별 추가 등록

| 항목 | 등록 위치 | 비고 |
| --- | --- | --- |
| 발신번호 (SMS/LMS/MMS) | 비즈뿌리오 사이트 | 통신사 사전 등록 |
| 발신번호 (FAX/PHONE) | 비즈뿌리오 사이트 | BIZCLIENT 전용 |
| 카카오 발신프로필 키 | [KAPI 발신프로필 등록](https://bizppurio.github.io/kapi#프로필) | 카카오 채널 보유 필수 |
| RCS 챗봇 ID | [RAPI 챗봇 등록](https://bizppurio.github.io/rapi#챗봇) | RBC 브랜드 등록 후 |
| 네이버 톡톡 파트너 키 | 비즈뿌리오 사이트 | 네이버 톡톡 채널 등록 후 |

## 재시도 정책

### BIZAPI 재시도 정책

| HTTP | `code` | 재시도 권장 | 비고 |
| --- | --- | --- | --- |
| `200` | `1000` | — | 성공 |
| `400` | `2000` | ✗ | 페이로드 오류 — 수정 후 재발송 |
| `400` | `3001` / `3006` / `3007` | ✗ | 자격 증명 오류 |
| `400` | `3002` / `3005` | ✓ (1회) | 토큰 재발급 후 재시도 |
| `400` | `3003` / `3010` | ✗ | IP 화이트리스트 등록 필요 |
| `400` | `3008` | ✓ | 동시 접속 초과 — 호출 분산 |
| `429` | `5002` | ✓ | Rate Limit — `RateLimit-Reset`만큼 백오프 |
| `500` | `9000` | ✓ (3회) | 서버 내부 오류 |
| `502` | `5003` | ✓ | 인프라 일시 오류 |
| `503` | `5004` | ✓ | 너무 많은 커넥션 |
| `504` | `5005` | ✓ | 게이트웨이 타임아웃 |

자세한 코드는 [BIZAPI 응답 상태 코드](https://bizppurio.github.io/response-codes), 백오프 패턴은 [Rate Limit 다루기](https://bizppurio.github.io/bizapi#rate-limit) 참고.

### BIZCLIENT 잔존 메시지 처리

네트워크 장애·모듈 비정상 종료로 결과 업데이트가 누락된 메시지를 자동으로 처리하는 옵션입니다. **BATCH 스레드가 5분 간격으로 동작**.

> **WARNING:** 이 옵션을 사용하면 비즈뿌리오 서버 통계와 고객사 DB 통계의 불일치가 발생할 수 있습니다. 운영 정책에 맞게 활성화하세요.

### `REPORT_RECONFIRM_OPTION` (Y/N)

`STATUS=1`(발송 후 대기) 상태이고 발송 시간이 **55시간(`WAIT_REPORT_HOUR`)** 경과한 메시지에 대해 결과 1회 재요청 + `STATUS=3`로 변경. 이후 발송 시간 기준 3일 이내 결과 미수신 시 실패 처리하여 로그 테이블로 이동.

| 항목 | 기본값 |
| --- | --- |
| `REPORT_RECONFIRM_OPTION` | `N` |
| `REPORT_RECONFIRM_COUNT` | 100 (배치당 처리 메시지 수) |
| 결과 코드 | `9023` (시간 제한, 리포트 수신 대기 timeout) |

### `REMOVE_PRESEND_MSG_OPTION` (Y/N)

`STATUS=7`(발송 중) 상태이고 발송 시간이 **3일(`CLIENT_TIMEOUT_HOUR`)** 경과한 메시지를 실패 처리하여 로그 테이블로 이동.

| 항목 | 기본값 |
| --- | --- |
| `REMOVE_PRESEND_MSG_OPTION` | `N` |
| `REMOVE_PRESEND_MSG_COUNT` | 100 |
| 결과 코드 | `9037` (시간 제한, 클라이언트 timeout) |

### 타임아웃 설정

| 옵션 | 기본값 | 최소값 | 설명 |
| --- | --- | --- | --- |
| `WAIT_REPORT_HOUR` | 55 | 55 | 서버로부터 리포트 수신 가능 최대 시간 |
| `CLIENT_TIMEOUT_HOUR` | 72 | 56 | 클라이언트 타임아웃 — `WAIT_REPORT_HOUR + 1` 이상 |

### `SEND_VALID_TIME` (분)

모듈 재구동 / DB 세션 재연결 시 실패 처리할 발송 유효 시간 (대상: `SEND_TIME` 컬럼).

| 값 | 의미 |
| --- | --- |
| `0` | 사용 안 함 |
| `1 ~ 1440` | 재구동 시점 기준 N분 지난 미발송 메시지를 실패 처리 |
| 기본값 | `180` (3시간) |

결과 코드: `9034` (발송 유효시간 만료)

API Webhook 미도달 시 결과 재요청(`/v2/report`)은 [전송 결과 수신 가이드 — 결과 재요청](https://bizppurio.github.io/bizapi#결과-수신) 참고. 자주 발생하는 장애 대응은 [FAQ — 장애 대응](https://bizppurio.github.io/guides/operations#장애-대응) 참고.


---

# 릴리스 노트

> 원본: [https://bizppurio.github.io/guides/releasenotes/](https://bizppurio.github.io/guides/releasenotes/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

RELEASE NOTES

# 릴리스 노트 (종합)

## 개요

비즈뿌리오의 4개 연동 규격 각각의 변경 이력입니다.

### 현재 사용 중인 버전 확인

| 영역 | 버전 확인 위치 |
| --- | --- |
| 메시지 API | API 응답 헤더 또는 비즈뿌리오 사이트 [API 관리] |
| BIZCLIENT | 모듈 설치 디렉토리의 `version.txt` 또는 `biz_start --version` |
| KAPI / RAPI | 비즈뿌리오 사이트 [API 관리] |

### 변경 통보

비즈뿌리오는 주요 버전 변경(`x.0` → `(x+1).0`) 시 사전 공지를 합니다. 호환성에 영향이 없는 마이너 변경(`x.y.z`)은 공지 없이 적용될 수 있습니다.

연동 시 다음을 권장합니다:

- 응답 코드를 매핑할 때 정확히 일치 비교가 아닌 **prefix 매칭** 또는 **알 수 없는 코드는 실패로 처리**하는 패턴
- 새 채널 타입(예: 신규 `chatBubbleType` 추가)이 들어와도 무시할 수 있도록 처리
- 응답 JSON에 새 필드가 추가되어도 기존 코드가 깨지지 않도록 — strict 파싱 지양

## Appendix

### 용어집

| 용어 | 의미 |
| --- | --- |
| **A2P** | Application-to-Person — 애플리케이션이 개인에게 보내는 메시지 (대부분의 비즈뿌리오 발송) |
| **AT / AI** | Alimtalk Text / Image — 카카오 알림톡 |
| **BIZCLIENT** | 고객사 서버에 설치하는 메시지 발송 클라이언트 모듈 |
| **BizCenter (RBC)** | RCS 비즈센터 — RCS 브랜드·챗봇·메시지베이스 관리 |
| **BT** | Brand Talk — 카카오 브랜드메시지 |
| **chatbotId** | RCS 챗봇 식별자 (A2P 챗봇은 발신번호와 동일) |
| **CMID** | BIZCLIENT 메시지 고유 식별자 (Primary Key) |
| **EUC-KR** | 한국어 인코딩 — SMS/LMS의 본문 길이 계산 기준 |
| **filekey / fileId** | 비즈뿌리오 또는 RCS에서 발급한 첨부파일 식별자 |
| **FREE / BASIC** | 브랜드메시지 발송 모드 — 자유형 / 기본형 |
| **FT** | Friend Talk — 카카오 친구톡 |
| **GSM 캐릭터 셋** | 국제 SMS에서 1byte로 처리되는 표준 문자 집합 |
| **KAPI** | Kakao API — 카카오 비즈메시지 자원 관리 API |
| **LMS** | Long Message Service — 장문 문자 |
| **maapfile** | RCS 미디어 파일 식별 URL 형식 (`maapfile://{fileId}`) |
| **messagebaseId** | RCS 메시지 포맷·카드 수·버튼 수를 결정하는 식별자 |
| **messagekey** | API 응답에서 받는 비즈뿌리오 메시지 키 (UMID와 매핑) |
| **MMS** | Multimedia Message Service — 이미지 첨부 문자 |
| **MO** | Mobile Originated — 단말기에서 발생한 메시지 |
| **MSG\_TYPE** | BIZCLIENT 메시지 채널 코드 (0/2/3/5/6/7/8/9) |
| **NTALK** | Naver Talk Talk — 네이버 톡톡 |
| **OTP** | One-Time Password — 일회용 비밀번호 (보안 메시지) |
| **personId** | BizCenter에서 생성한 브랜드 관리자 ID |
| **RAPI** | RCS API — RCS 자원 관리 API |
| **RBC** | RCS Biz Center — RCS 자원 관리 웹사이트 |
| **RCS** | Rich Communication Services — 통신사 차세대 메시지 |
| **refkey** | 고객사가 부여하는 메시지 식별자 |
| **RESEND** | API 본 발송 실패 시 다른 채널로 자동 재발송하는 기능 |
| **senderKey** | 카카오 발신프로필 키 |
| **TTS** | Text-to-Speech — PHONE 채널의 음성 합성 |
| **UMID** | 비즈뿌리오 서버가 부여하는 메시지 키 (대체발송 매핑 키) |
| **userkey** | 카카오톡 채널 봇을 통해 받은 사용자 식별키 |
| **VXML** | Voice XML — PHONE 시나리오 파일 |
| **YellowID** | 카카오톡 채널의 ID (예: `@bizppurio`) |

### 외부 링크

| 사이트 | URL | 용도 |
| --- | --- | --- |
| 비즈뿌리오 | <https://bizppurio.com> | 계정·발신번호·발신프로필 등록 |
| 카카오 비즈니스 | <https://center-pf.kakao.com> | 카카오톡 채널 개설 |
| RCS 비즈센터 | <https://www.rcsbizcenter.com> | RCS 브랜드·챗봇 관리 |
| 네이버 톡톡 파트너센터 | <https://partner.talk.naver.com> | 네이버 톡톡 채널 |

### 고객 지원

| 항목 | 연락처 |
| --- | --- |
| 전화 | 1599-9782 |
| 이메일 | [bizppurio@daou.co.kr](mailto:bizppurio@daou.co.kr) |


---

# 개정 이력

> 원본: [https://bizppurio.github.io/changelog/](https://bizppurio.github.io/changelog/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

CHANGELOG · 개정 이력

# 개정 이력

서비스별 규격 버전과 주요 변경 사항입니다. 버전은 운영 규격 문서(PDF)와 동기화되며,  
전체 이력은 각 서비스 규격서에서 확인할 수 있습니다.

## 서비스별 버전

메시지 APIBIZAPI

[v3.11.2최신PDF ↓](https://bizppurio.github.io/history/bizapi-v3.11.2.pdf)[v3.11.1PDF ↓](https://bizppurio.github.io/history/bizapi-v3.11.1.pdf)[v3.11PDF ↓](https://bizppurio.github.io/history/bizapi-v3.11.pdf)

BIZCLIENTSQL

[v5.2.6최신PDF ↓](https://bizppurio.github.io/history/bizclient-v5.2.6.pdf)[v5.2.5PDF ↓](https://bizppurio.github.io/history/bizclient-v5.2.5.pdf)[v5.2.4PDF ↓](https://bizppurio.github.io/history/bizclient-v5.2.4.pdf)[v3.16PDF ↓](https://bizppurio.github.io/history/bizclient-v3.16.pdf)

카카오 비즈메시지KAPI

[v4.18최신PDF ↓](https://bizppurio.github.io/history/kapi-v4.18.pdf)[v4.17PDF ↓](https://bizppurio.github.io/history/kapi-v4.17.pdf)[v4.16PDF ↓](https://bizppurio.github.io/history/kapi-v4.16.pdf)[v4.15PDF ↓](https://bizppurio.github.io/history/kapi-v4.15.pdf)

RCS 관리RAPI

[v1.2최신PDF ↓](https://bizppurio.github.io/history/rapi-v1.2.pdf)

네이버 톡톡 관리NAPI

[v1.0.0최신PDF ↓](https://bizppurio.github.io/history/napi-v1.0.0.pdf)

## 변경 이력 · 메시지 API

v3.11.2최신2026-07-30

- ·브랜드메시지 커머스·캐러셀 커머스 할인율 discount\_rate 허용 범위 변경 (0 ~ 100 → 1 ~ 100)

v3.11.12026-06-25

- ·브랜드메시지 와이드 리스트 이미지 아이템 최대 5개·캐러셀 커머스 최대 첨부 6장 확대

v3.112026-05-18

- ·브랜드메시지 타입 명칭 변경 및 targeting O 추가, 친구톡 내용 삭제

v3.10.62026-02-09

- ·알림톡 지도보기 버튼 추가, 결과 코드 추가

v3.10.52025-12-15

- ·통합 RCS 메시지베이스 ID 추가

v3.10.42025-11-13

- ·브랜드메시지 글자수 스펙 변경

v3.10.32025-11-09

- ·브랜드메시지 이미지형 글자수 확대, 연동 규격(Keep-Alive) 설명 추가

v3.10.22025-10-13

- ·알림톡 발송 TN 버튼 설명 추가

v3.10.12025-09-19

- ·알림톡·브랜드메시지 글자 수 확대 (1000 → 1300)

v3.102025-08-05

- ·브랜드메시지 무료수신거부 파라미터 추가

v3.92025-06-27

- ·친구톡 Upgrade 명칭 변경 (브랜드메시지)

v3.82025-03-21

- ·브랜드메시지 타입 추가

v3.62024-11-11

- ·NTALK 발송 타입 추가

v3.52024-08-19

- ·친구톡 신규 타입 추가 (커머스, 프리미엄 동영상, 캐러셀 커머스) / RCS LMS 템플릿 설명 추가 / 국가 코드 길이 제한 변경 (3 → 5)

v3.42024-01-01

- ·Rate Limit 설명 추가

v3.3.32023-12-08

- ·친구톡 캐러셀 스펙 수정

v3.3.22023-09-11

- ·국제 메시지 길이 제한 수정

v3.3.12023-08-02

- ·스팸 차단 결과 코드 추가

v3.32023-04-28

- ·알림톡 대표링크, 친구톡 와이드 아이템 리스트, 친구톡 캐러셀 타입 추가

v3.0.12023-02-22

- ·refkey 제약조건 및 리포트 refkey 필드 추가

v3.02023-02-16

- ·RCS groupid 추가, 이모지 발송 관련 안내문구 추가

v2.8.22023-01-04

- ·RCS 템플릿, 식별코드 설명 추가

v2.8.12022-10-21

- ·리포트 폴링 추가

v2.82022-07-15

- ·파일 인증 추가, 발송 시간 파라미터 추가

v2.7.22022-06-08

- ·RCS MMS 동영상 스트리밍 전송 안내 추가

v2.7.12022-03-04

- ·특부가사업자 식별코드 (resellercode) 추가

v2.72021-10-28

- ·알림톡 이미지·아이템리스트 추가, 리포트 파라미터 (카카오/RCS 대체발송) 추가

v2.62021-08-31

- ·국제 발송 설명 추가, RCS 주요 변경사항 추가

v2.52021-07-15

- ·알림톡 버튼 내용 수정

v2.42021-04-16

- ·알림톡 바로 연결 버튼 설명 추가

v2.32020-11-10

- ·인증 토큰 발급 예시 추가

v2.22020-11-03

- ·인증 토큰 발급 Headers 내용 수정

v2.12020-11-02

- ·RCS 공통 메시지 포맷 내용 수정

v2.02020-10-29

- ·인증 토큰 기능 적용으로 인한 내용 수정 / 리포트 재요청 기능 관련 내용 수정 / RCS 대행사 ID (agencyid) 입력 관련 내용 추가

v1.42020-05-20

- ·내용 수정

v1.32020-04-08

- ·내용 수정

v1.22020-01-15

- ·내용 수정

v1.12019-09-20

- ·내용 수정

v1.02019-09-11

- ·최초 제정

## 변경 이력 · BIZCLIENT

v5.2.6최신2026-07-30

- ·브랜드메시지 커머스·캐러셀 커머스 할인율 discount\_rate 허용 범위 변경 (0 ~ 100 → 1 ~ 100)

v5.2.52026-07-08

- ·브랜드메시지 와이드 리스트 이미지 아이템 최대 5개·캐러셀 커머스 최대 첨부 6장 확대 (문서 현행화)

v5.2.42026-06-01

- ·친구톡 제거, 브랜드메시지 v2 적용 (고객사 회원 대상 marketing / 채널 친구 대상 friend)

v5.2.32026-02-09

- ·알림톡 지도보기 버튼 추가, 결과 코드 추가

v5.2.22025-12-15

- ·통합 RCS 메시지베이스 ID 추가

v5.2.12025-11-13

- ·브랜드메시지 글자수 스펙 변경

v5.22025-09-08

- ·브랜드메시지 타입 분리

v5.02025-08-14

- ·브랜드메시지 추가

v4.8.32025-03-18

- ·알림톡 대표링크 수정

v4.8.22025-02-03

- ·카카오톡 비즈메시지 결과 코드 추가

v4.8.12024-08-19

- ·친구톡 커머스, 프리미엄 동영상, 캐러셀 커머스, RCS LMS 템플릿 설명 추가

v4.72023-12-08

- ·친구톡 캐러셀 스펙 변경

v4.5.32023-08-01

- ·KISA 문구 필터링 결과 코드 추가

v4.5.22023-07-27

- ·RCS 발송 예시 추가

v4.5.12023-07-12

- ·지원 가능 DBMS 축소 (주요 3개 DBMS 외 버전에 따라 지원)

v4.52023-04-28

- ·RCS brandKey, agencyKey 필드 추가, 결과 코드 추가

v4.42023-04-28

- ·친구톡 와이드 아이템 리스트·캐러셀 피드형 추가, 친구톡 쿠폰 강조 버튼 기능 추가, 알림톡 대표링크 추가

v4.02023-02-14

- ·인코딩 타입 수정 (EUC-KR → UTF-8), RCS 이미지 템플릿 및 응답 코드 추가

v3.162022-10-11

- ·중복 발송 제한 기능 추가

v3.15.22022-08-11

- ·RCS 신규 메시지 포맷 리스트, 응답 코드 추가

v3.15.12022-06-08

- ·RCS MMS 동영상 스트리밍 전송 안내 추가, 브랜드톡 설명 제거

v3.152022-02-28

- ·최초 발신사업자 식별코드 (RESELLERCODE) 추가

v3.14.12022-01-25

- ·RCS AGENCY\_ID 설정 안내 추가

v3.142021-12-21

- ·RCS 결과 코드 추가, MySQL/Oracle 인덱스 삭제

v3.132021-09-26

- ·RCS 설명 추가, 버튼 플러그인, 알림톡 이미지·아이템리스트 형 추가

v3.112020-12-16

- ·MySQL, Oracle 인덱스 추가, 알림톡 바로연결 추가

v3.8.22020-10-15

- ·잔존 메시지 처리 옵션 설명 추가

v3.8.12020-09-14

- ·카카오 비즈메시지 버튼 타입 정보 수정 (채널 추가: CA → AC), 브랜드톡 변수형 기능 추가

v3.82020-09-01

- ·SEND\_VALID\_TIME 기본값 변경, 한글 인코딩 안내 추가

v3.7.12020-07-20

- ·내용 업데이트, MESSAGEBASE\_ID 포맷 리스트 추가

v3.72020-07-06

- ·AT 템플릿 예시 추가, 발송 유효시간 만료, RCS 에러 코드 추가, 알림톡/친구톡 에러 코드 수정, 알림톡 강조표기 추가

v3.4.12020-06-04

- ·RCS 필수값 오류 정정

v3.42020-05-13

- ·여신 관련 에러 코드 추가, JDK 버전 변경, 브랜드톡 발송 추가, WAP\_URL 컬럼 삭제

v3.12020-04-06

- ·DB 복호화 PLUG-IN 방식 개선

v3.02020-03-05

- ·RCS 발송, 2차 대체발송 추가, 추가 테이블 백업, 친구톡 와이드, 채널추가 버튼 내용 추가

v1.192020-01-20

- ·내용 추가

v1.182019-10-17

- ·내용 추가

v1.152019-06-21

- ·내용 추가

v1.122019-05-03

- ·내용 추가

v1.112019-04-09

- ·내용 추가

v1.102019-03-28

- ·내용 추가

v1.92019-01-15

- ·내용 추가

v1.8—

- ·내용 추가

v1.72018-08-27

- ·내용 추가

v1.62018-07-16

- ·내용 수정

v1.52017-09-19

- ·내용 추가 및 수정

v1.42017-08-31

- ·버튼링크에 대한 예제 내용 수정

v1.32017-05-25

- ·설치가이드 및 상세매뉴얼을 사용자매뉴얼로 통합, 전체 내용 추가·수정

v1.22017-05-12

- ·내용 추가 및 수정

v1.12017-03-27

- ·내용 추가 및 수정

v1.02016-07-30

- ·신규 작성

## 변경 이력 · 카카오 비즈메시지

v4.18최신2026-08-05

- ·브랜드메시지 템플릿 커머스 할인율 discountRate 허용 범위 변경 (0 ~ 100 → 1 ~ 100), 메시지 통계 조회 타겟팅 targeting 대상에 O(수신동의 회원 ∩ 채널 친구) 추가

v4.172026-07-14

- ·신규: 메시지 통계 (카카오 직접 조회) API 6종 — 발송·유효읽음·클릭 일별, 템플릿 발송·유효읽음·클릭 일별. 요청 제한(Rate Limit) 적용 — 엔드포인트별 IP당 초당 20건 / 분당 1000건

v4.162026-07-07

- ·브랜드메시지 서브 와이드 아이템 리스트 최대 4개(와이드 리스트 총 5개)·캐러셀 커머스 최대 6장 확대

v4.152026-06-01

- ·신규: 브랜드메시지 동영상 관리 API (동영상 조회 / 업로드 등록 / 업로드(카카오 직접 호출))

v4.142026-03-18

- ·와이드 리스트·캐러셀 이미지 업로드 설명 오류 수정, 친구톡 제거

v4.132026-02-25

- ·신규: 메시지 통계 조회 API (발송·템플릿)

v4.122026-01-22

- ·알림톡 템플릿 버튼 linkType 추가: MP (지도보기)

v4.112025-12-16

- ·캐러셀 피드 list.content 줄바꿈 2 → 10회 (오기 수정)

v4.102025-11-12

- ·브랜드메시지 이미지 템플릿 1300자 / 줄바꿈 정책 변경 / 버튼 타입 추가 (BT/BC)

v4.92025-11-05

- ·신규: 그룹 태그 관리 API / 응답 코드 문구 수정

v4.82025-10-29

- ·신규: 공용 템플릿 목록 조회 API

v4.72025-09-24

- ·알림톡 템플릿 — AL 버튼 사용 시 tell:// 스킴 입력 불가 / 브랜드메시지 텍스트 템플릿 1300자

v4.62025-09-03

- ·신규: 발신프로필 무료수신거부 정보 수정, 광고성 정보 수신동의 증적자료 업로드, 발신프로필 브랜드메시지 사용 신청, 브랜드메시지 템플릿 변경 이력 조회·목록 조회

v4.52025-08-05

- ·알림톡 템플릿 — telNumber 파라미터 추가 (linkType=TN)

v4.42025-07-03

- ·알림톡 템플릿 — 대표링크 파라미터 추가

v4.32025-06-17

- ·친구톡 이미지 업로드 제한 변경 / 브랜드메시지 템플릿 파라미터명 (name → templateName)

v4.22025-05-14

- ·응답 코드 추가 / 친구톡 Upgrade → 브랜드메시지 명칭 변경 / imageName 파라미터 삭제 / 와이드 리스트 첫번째 아이템 타이틀 옵셔널

v4.12025-02-06

- ·신규: 친구톡 Upgrade 이미지 업로드, 템플릿 관리 (CRUD)

v4.02024-08-09

- ·신규: 친구톡 이미지 업로드 — 프리미엄 동영상, 커머스, 캐러셀 커머스 추가 / 변경: 기본 타입 용량 500KB → 5MB, 캐러셀 피드 첨부 1~6 → 1~10

v3.92023-12-13

- ·광고추가형 → 채널추가형 명칭 변경, AD/MI 선택 시 채널추가버튼 자동 삽입

v3.82023-11-29

- ·그룹템플릿 templateMessageType AD/MI 사용 가능

v3.72023-10-18

- ·신규: 템플릿 전환 API. 변경: templateAd 제거

v3.62023-06-29

- ·변경: 발신프로필 리스트 조회 (/profile/list) 삭제, 전체조회 (/profile/all) 삭제

v3.52023-04-28

- ·신규: 친구톡 와이드 아이템 리스트, 캐러셀 피드형 이미지 업로드 / 응답 코드 정의 추가

v3.42023-03-21

- ·신규: 발신프로필 리스트 조회·전체조회

v3.32022-04-21

- ·템플릿 카테고리 변경 API 삭제

v3.22021-11-16

- ·이미지 포함 템플릿 등록/수정 API 삭제

v3.12021-10-19

- ·템플릿 코드 설명 추가 (영문/숫자/\_/-, 최대 30자)

v3.02021-07-12

- ·카카오톡 비즈메시지 센터 API v2.40 적용 — 이미지 포함 템플릿 관리, 검수/취소/중지/해제/승인/휴면해제 기능, 비즈메시지 파일 관리, 발신프로필 삭제·휴면해제, 플러그인 콜백 URL 관리

v2.72021-03-02

- ·친구톡 이미지 타입 (일반=I, 와이드=W) 추가

v2.62020-12-10

- ·신규: 발신프로필 전체조회 API. 변경: 알림톡 바로연결 추가

v2.52020-10-27

- ·신규: 카테고리 API. 변경: 템플릿 보안·카테고리·강조표기 추가

v2.42020-03-31

- ·친구톡 이미지 관리 — 와이드 이미지 imageType 옵션 추가

v2.32020-03-02

- ·알림톡 템플릿 — templateMessageType / templateExtra / templateAd 추가

v2.22019-07-03

- ·친구톡 이미지 관리 API 변경 — senderKey 옵션 추가, 이미지 삭제 신규

v2.12019-04-05

- ·프로필 삭제 API 비활성화

v2.02019-03-25

- ·신규: 프로필 관리 / 그룹 관리 / 템플릿 관리(문의요청 파일첨부, 코드 수동생성, 유효성 검증) / 변경: 템플릿 수정·상세조회

v1.32018-09-07

- ·senderKeyType 입력 필수 조건으로 변경

v1.22018-04-17

- ·템플릿 관리 — 등록 예제 추가

v1.12018-03-28

- ·템플릿 관리 — 상태 설명 추가

v1.02017-09-13

- ·초안 작성

## 변경 이력 · RCS 관리

v1.2최신2024-08-21

- ·RCS 이미지 템플릿 관리 API 추가: 메시지베이스 v2 등록·수정, 이미지 템플릿 파일 등록·조회, 기본 로고 이미지 조회

v1.12023-11-29

- ·대행사용 파일 업로드 API 추가

v1.02021-07-28

- ·초안 작성 — 토큰 / 브랜드 / 챗봇 / 파일 / 메시지베이스 / 기업 전용 브랜드 관리

## 변경 이력 · 네이버 톡톡 관리

v1.0최신2024-12-30

- ·초안 작성 — 토큰 / 파트너 / 파트너 그룹 / 이미지 / 정보성·광고성 템플릿 / 그룹 템플릿 관리


---

# 메시지 발송 체험

> 원본: [https://bizppurio.github.io/sandbox/](https://bizppurio.github.io/sandbox/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

SANDBOX

모의 흐름 · 실제 발송되지 않음

# 메시지 발송 체험

고객사에서 단말기까지, 메시지가 전달되는 과정을 한 단계씩 확인합니다.  
각 단계의 **전문(payload)**은 아래에서 입력한 값으로 생성됩니다.

1메시지 구성

type · 채널

account · 계정from · 발신번호to · 수신번호

message · 본문[비즈뿌리오] 인증번호 [123456]를 입력해 주세요.

2메시지 흐름STEP 1 / 7

고객사

발송 요청 생성

고객사 시스템이 발송할 메시지를 비즈뿌리오 메시지 API로 전송합니다.

POST /v3/message · 요청 전문

```
POST https://api.bizppurio.com/v3/message
Authorization: Bearer {accesstoken}

{
  "account": "bizUserId001",
  "type": "sms",
  "from": "07000000000",
  "to": "01012345678",
  "refkey": "test1234",
  "content": {
    "sms": {
      "message": "[비즈뿌리오] 인증번호 [123456]를 입력해 주세요."
    }
  }
}
```


---

# JSON 검증기

> 원본: [https://bizppurio.github.io/json-validator/](https://bizppurio.github.io/json-validator/)  \
> 저장일: 2026-09-06  \
> 이 파일은 공개 개발자 문서의 오프라인 Markdown 스냅샷입니다. 최신 내용은 원본 사이트에서 확인하세요.

TOOL · JSON 검증기

# JSON 검증기

메시지 API 요청 본문을 붙여넣어 문법 오류를 찾고, 보기 좋게 정렬하거나 한 줄로 압축합니다.  
모든 처리는 브라우저에서만 이루어집니다.

8줄180자200 bytes

입력

{
"account": "bizUserId001",
"type": "sms",
"from": "07000000000",
"to": "01012345678",
"refkey": "test1234",
"content": { "sms": { "message": "비즈뿌리오 JSON 검증 테스트" } }
}

✓유효한 JSON최상위 6개 키 · 깊이 3

정렬 결과

```
{
  "account": "bizUserId001",
  "type": "sms",
  "from": "07000000000",
  "to": "01012345678",
  "refkey": "test1234",
  "content": {
    "sms": {
      "message": "비즈뿌리오 JSON 검증 테스트"
    }
  }
}
```
