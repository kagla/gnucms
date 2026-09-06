# 데이터베이스 테이블 최적화 계획

## 목표

기존 15개 테이블을 기능과 데이터 무결성을 유지하면서 12개로 정리했다. 이후 보안 감사 요구로
로그인 1회당 한 행을 보존하는 `login_events`를 추가해 현재는 13개다. 테이블 수 자체보다
중복 책임, 마이그레이션 잔재, 실제 조회 조건에 맞지 않는 인덱스를 우선 정리한다.

설정·동의 구조 정리 직후의 12개와 이후 추가한 로그인 이력 테이블은 다음과 같다.

```text
boards
posts
comments
users
user_tokens
user_identities
site_settings
contents
consent_uses
consents_given
notifications
password_attempts
login_events
```

## 작업 원칙

- 기존 설치의 데이터를 먼저 새 구조로 복사하고 검증한 뒤 옛 테이블을 삭제한다.
- 신규 설치는 옛 테이블과 옛 컬럼을 처음부터 만들지 않는다.
- 마이그레이션은 여러 번 실행해도 안전해야 한다.
- SQLite, MySQL/MariaDB에서 같은 결과가 나와야 한다.
- 설정 통합 후에도 메일 비밀번호와 시스템 내부 값은 일반 사이트 설정 조회 결과에 노출하지 않는다.
- 첫 관리자 선점은 조건부 `UPDATE`의 영향 행 수로 결정하여 동시성 안전성을 유지한다.

## 1단계: `user_consents` 제거

`user_consents`는 과거 회원 동의 전용 표다. 현재 읽기와 쓰기는 회원·비회원 모두 지원하는
`consents_given`을 사용한다.

1. 옛 표가 있으면 `consents_given`이 없을 때 생성한다.
2. `(subject_type, subject_id, scope, content_id)`가 없는 옛 행만 복사한다.
3. 복사가 끝난 뒤 `user_consents`를 삭제한다.
4. 신규 설치 DDL과 `Schema::TABLES`, 인덱스 목록에서 제거한다.

예상 결과: 테이블 14개, 명시적 인덱스 2개 감소.

## 2단계: `mail_settings` 통합

`mail_settings`와 `site_settings`는 `key/value/updated_at` 구조가 같다. 메일 설정은
`mail.` 접두사를 붙여 `site_settings`로 옮긴다.

```text
enabled       -> mail.enabled
host          -> mail.host
password      -> mail.password
...
```

`MailSettingsRepository`는 접두사를 붙여 저장하고 제거해서 반환한다. `CmsRepository`는
`mail.` 및 `system.` 키를 일반 사이트 설정에서 제외한다. 이전이 끝나면 옛 표를 삭제한다.

예상 결과: 테이블 13개, 명시적 인덱스 1개 감소.

## 3단계: `site_state` 통합

시스템 상태도 `site_settings`에 `system.` 이름 공간으로 저장한다.

```text
first_admin_claimed       -> system.first_admin_claimed
consent_footer_defaulted  -> system.consent_footer_defaulted
schema_version            -> system.schema_version
```

구버전 호환을 위해 기존 `schema_version`은 마이그레이션 시작 시 읽을 수 있게 유지하되,
완료 도장은 `system.schema_version`에 기록한다. 첫 관리자 선점은 다음 형태를 유지한다.

```sql
UPDATE site_settings
SET setting_value = '1', updated_at = ?
WHERE setting_key = 'system.first_admin_claimed'
  AND setting_value = '0'
```

이전이 끝나면 `site_state`를 삭제한다.

정리 직후 결과: 테이블 12개, 명시적 인덱스 1개 감소. 이후 로그인 감사 이력 보존 요구로
`login_events`가 추가되어 현재 최종 테이블 수는 13개다.

## 4단계: `contents` 레거시 컬럼과 인덱스 정리

`consent_uses`로 옮겨진 다음 컬럼은 구버전 데이터 이전 후 제거한다.

- `consent_key`
- `consent_order`
- `consent_required`
- `ux_contents_consent`

저선택도 단일 인덱스 `ix_contents_is_consent`는 실제 공개 목록 조건에 맞춘 복합 인덱스로
대체한다.

```text
(is_consent, deleted_at, status, show_in_menu, sort_order, id)
```

댓글의 자식 존재 확인에 사용되는 `comments(parent_id)` 인덱스도 추가한다. 다른 후보 인덱스는
운영 데이터의 `EXPLAIN` 결과 없이 무조건 추가하지 않는다.

## 검증 기준

- 신규 설치 시 사용자 테이블이 정확히 13개여야 한다. `login_events`는 성공·실패 로그인 이력을
  누적하는 감사 데이터라 10분 잠금 집계인 `password_attempts`와 합치지 않는다.
- 구버전의 동의, 메일 설정, 첫 관리자 상태가 손실 없이 이전되어야 한다.
- 마이그레이션을 두 번 실행해도 결과가 같아야 한다.
- 첫 가입 요청이 동시에 들어와도 관리자 계정은 하나만 생겨야 한다.
- 일반 사이트 설정 조회에서 `mail.*`, `system.*` 값이 보이지 않아야 한다.
- 전체 PHPUnit 테스트가 통과해야 한다.

## 롤백과 백업

운영 업그레이드는 기존 `SchemaUpgrader`의 백업과 잠금 안에서 실행한다. 테이블 삭제가 포함되므로
백업에 실패하면 마이그레이션을 시작하지 않는다. 실패 시에는 생성된 백업으로 DB 전체를 복구하고,
부분적으로 삭제된 옛 테이블을 애플리케이션 코드만으로 재구성하려 하지 않는다.
