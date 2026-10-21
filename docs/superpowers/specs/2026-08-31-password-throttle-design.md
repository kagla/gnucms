# 비밀번호 무한 대입 방어 — 설계

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

2026-08-31. 브랜치 main.

## 목표
비밀번호를 받는 세 경로(비회원 글·댓글 수정/삭제, 비밀글 열람, 로그인)에
같은 방어를 넣는다: **대상+IP 별로 10분 안에 5번 틀리면 잠시 막는다.**
잠긴 동안은 맞는 비밀번호도 검사하지 않는다.

## 표 `password_attempts` (Schema VERSION 11)
| 칸 | 타입 | 뜻 |
|---|---|---|
| id | AUTO_PK | |
| attempt_key | VARCHAR(120) NOT NULL | 예: `modify:post:23`, `modify:comment:5`, `secret:23`, `login:user@x` |
| client_ip | VARCHAR(64) NOT NULL | REMOTE_ADDR. 없으면 `unknown` |
| fail_count | INTEGER NOT NULL DEFAULT 0 | |
| first_failed_at | INTEGER NOT NULL | 유닉스 초. 창(10분)의 시작 |
UNIQUE (attempt_key, client_ip). 멱등 마이그레이션 + 새 설치 DDL 둘 다.

## `src/Auth/PasswordThrottle.php`
- `MAX_FAILURES = 5`, `WINDOW_SECONDS = 600`.
- `__construct(Connection $db, ?string $clientIp)` — 빈 IP 는 `unknown`.
- `assertNotLocked(string $key, string $field = 'password')` — 창 안에서 5번 이상이면
  `DomainError::validation([$field => '너무 많이 틀렸습니다. N분 뒤 다시 시도해 주세요.'])`.
  창이 지났으면 행을 지우고 통과.
- `recordFailure(string $key)` — 창이 지났으면 1로 새로 시작, 아니면 +1. select 없이 원자적
  UPDATE(CASE 로 창 만료 여부까지 SQL 안에서 판단) 를 먼저 시도하고, 행이 없으면(rowCount 0)
  insert 하며, 그 insert 마저 경합(동시에 다른 요청이 먼저 넣음)으로 지면 UPDATE 를 한 번 더
  시도한다. find-then-write 방식은 동시 요청에서 카운터를 잃어버리거나 UNIQUE 충돌로 500 이
  났었다. 실패 기록마다 확률로(1/20) 만료된 행을 청소해(`sweepExpired()`) 무한 증식을 막는다.
- `clear(string $key)` — 성공 시 행 삭제.
- 시각은 `Clock::timestamp()` (테스트에서 freeze 가능).

## 배선
- `App::passwordThrottle()` (메모이즈): `$_SERVER['REMOTE_ADDR']`. 프록시 헤더는 믿지 않는다(동의 증적과 같은 원칙).
- **비회원 글·댓글**: `Acl::setPasswordThrottle()` 을 `App::guestAcl()` 이 주입.
  `assertCanModify()` 에서 비회원 소유 자원(author_id NULL + guest_password 있음)이고
  비밀번호가 주어졌으면: 검사 전 `assertNotLocked('modify:{post|comment}:{id}')`
  (comment 는 행에 post_id 가 있는 것으로 구분) → 틀리면 recordFailure, 맞으면 clear.
  주입이 없으면(단위 테스트 등) 기존 동작 그대로.
- **비밀글**: `Acl::verifySecret(board, post, password): bool` 추가 — `secret:{id}` 키로
  같은 순서. `PostService::loadForRead()` 의 is_secret 검사가 이것을 쓴다.
- **로그인**: `AccountService::setPasswordThrottle()`. `authenticate()` 첫머리에
  `assertNotLocked('login:{email}', 'email')`, 실패 분기에서 recordFailure,
  비밀번호가 맞으면(미인증 분기 포함) clear.

## 범위 밖
- 세션/쿠키 기반 제한(쿠키를 버리면 무력), 분산 공격(IP 다수) 대응, CAPTCHA,
  프록시 X-Forwarded-For 해석, 관리자 화면의 잠금 해제 UI.
- 프록시/CDN 뒤 배치: REMOTE_ADDR 가 프록시 IP 로 뭉개져 전 사용자가 한 버킷이 되는 문제.
  trusted proxy 설정(어떤 프록시를 믿고 그 뒤의 X-Forwarded-For 를 읽을지)은 후속 과제다.
- 로그인 상태에서의 현재 비밀번호 검증(회원정보 수정·비밀번호 변경, `current_password`)
  스로틀. 이 경로는 이미 로그인해 신원이 확인된 사용자만 겪으므로 이번 방어 범위에 넣지 않았다.

## 검증
- 단위: 5번째까지 통과·6번째 잠김, 잠기면 맞는 비밀번호도 거부, 창 만료 후 초기화,
  다른 IP 무관, clear.
- 웹: 로그인 5번 틀린 뒤 맞는 비밀번호도 '너무 많이' / 비회원 글 수정 잠김 / 비밀글 열람 잠김.
- 기존 395개 그대로.
