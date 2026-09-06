# 단계형 설치기와 알아서 옮기는 스키마 업그레이더 — 설계

2026-08-30. 브랜치 `feat/claude-themes`.

## 목표

1. 설치를 한 화면 폼(원시 DSN 입력)에서 **다섯 단계 설치기**로 바꾼다. 서버 점검 → DB → 사이트 → 첫 관리자 → 완료.
2. 코드를 올린 뒤 **첫 요청에서 앱 스스로** 스키마를 새 판으로 옮기되, 옮기기 전에 백업하고, 실패하면 500 대신 **점검 화면**을 낸다. 관리 서버는 없다. 서버 하나, 사람 한 명이다.
3. 관리 콘솔 사이트 설정에 스키마 판·마지막 옮긴 시각·백업 목록을 보인다.

## 1. 설치기

### 진입

- `public/index.php`: `config/config.php` 가 없고 `public/install.php` 가 있으면 `install.php` 로 302. 둘 다 없으면 지금처럼 "설치가 필요합니다" 글(500).
- `public/install.php`: `config.php` 가 있으면 어느 단계든 "이미 설치되어 있습니다" 만 낸다. Slim·테마·DB 에 기대지 않는 독립 페이지다. CSS 는 파일 안에 둔다(하늘빛 테마 색으로 맞춘다).

### 단계와 상태

단계 사이의 값은 **설치기 전용 세션**(`session_name('gnucms_install')`, 쿠키 HttpOnly·SameSite=Lax)에 둔다. hidden 칸으로 DB 비밀번호를 실어 나르지 않기 위해서다. `?step=N` 으로 단계를 고르며, 앞 단계를 아직 안 끝냈으면 끝낸 마지막 단계로 보낸다. "이전" 링크로 되돌아가면 세션 값이 칸에 다시 채워진다(비밀번호 칸은 빈다).

| 단계 | 화면 | 다음으로 못 가는 조건 |
|---|---|---|
| 1 서버 점검 | 항목 표. 필수: PHP ≥ 8.1, `pdo`, `pdo_sqlite`·`pdo_mysql` 중 하나, `mbstring`, `fileinfo`, `openssl`, `config/` 쓰기 가능, `storage/` 쓰기 가능. 안내만: `mod_rewrite`(감지 가능할 때), `gd` | 필수 항목 하나라도 실패 |
| 2 데이터베이스 | 종류 라디오(SQLite 기본 / MySQL). SQLite: 파일 경로(기본 `storage/board.sqlite`). MySQL: 호스트·포트(3306)·DB 이름·계정·비밀번호. 단추는 "접속 시험 후 다음" 하나 | 접속 실패(원문 메시지 표시). 서버에 해당 PDO 드라이버가 없는 종류는 라디오를 비활성화 |
| 2-보조 | 접속됐는데 GNUCMS 표(`site_settings`)가 이미 있으면 "기존 데이터베이스를 이어 씁니다" 확인 상자를 보이고, 체크해야 넘어간다. 이 경우 표는 새로 만들지 않고, 관리자가 이미 있으면 4단계를 건너뛴다 | 체크 안 함 |
| 3 사이트 | 사이트 이름(기본 GNUCMS), 사이트 주소(요청 host 로 자동 채움), 발신 메일(`no-reply@host`) | 형식 오류 |
| 4 첫 관리자 | 이메일, 표시 이름, 비밀번호, 비밀번호 확인(눈 아이콘 토글). 표시 이름 규칙은 회원가입과 같다(`UserRepository::displayNameRule()`) | 형식 오류, 비밀번호 8자 미만, 확인 불일치 |
| 5 완료 | 실행 순서: `Schema::create()`(이어 쓰기면 `ensureCurrent()`) → `site_name` 저장 → 관리자 생성(`email_verified_at` 채움, `is_admin=1`, `first_admin_claimed=1`) → `config.php` 쓰기 → `install.php` 삭제 시도. 결과 화면에 DB 종류, 관리자 이메일, 로그인 링크. 삭제 못 했으면 붉은 경고 | 어느 단계든 실패하면 오류를 5단계 화면에 내고 세션은 유지(다시 시도 가능). `config.php` 는 맨 마지막에 쓰므로 반쯤 설치된 상태가 남지 않는다 |

### 설정 파일

쓰는 키: `app.url`, `mail.from`, `oauth.{google,naver,kakao,github}`(빈 값), `db.{dsn,username,password}`, `auth.secret`(32바이트 난수), `uploads`, `editor`, `log`, `debug=false`. **빼는 키**: `cors`, `auth.ttl`, `auth.leeway`, `bootstrap_admin` — 코드가 읽지 않는다. `config/config.sample.php` 도 같은 모양으로 고친다.

### 클래스 (`src/Install/`)

- `ServerCheck` — `run(string $configDir, string $storageDir): array{ok: bool, items: list<array{label, ok, required, note}>}`. 확장·버전·쓰기 가능 여부. 순수 함수에 가깝고 테스트에서 `extension_loaded` 대신 주입된 목록을 쓴다.
- `DbSetup` — `dsnFrom(array $input): array{dsn, username, password}`(종류별 칸 → DSN, 검증 오류는 `DomainError::validation`), `probe(array $dbConfig): array{dialect, has_tables, has_admin}`(접속 + `site_settings`·관리자 존재 확인).
- `Installer` — `finish(array $db, array $site, ?array $admin, bool $reuse): array{dialect, admin_email, config_path, self_deleted}`. 위 완료 순서를 수행. `isInstalled()` 유지.
- `InstallSession` — 세션 열기/닫기, 단계 값 저장·읽기, 완료한 단계 번호. install.php 가 얇아지도록 한다.

`public/install.php` 는 단계 라우팅과 HTML 만 맡는다. 화면 조각은 `src/Install/pages/step{N}.php` 로 두지 않고 install.php 안의 함수로 둔다(파일 하나만 복사·삭제하면 되도록).

## 2. 스키마 업그레이더 (`src/Db/SchemaUpgrader`)

`Kernel::create()` 의 `(new Schema($db))->ensureCurrent()` 를 `(new SchemaUpgrader($db, $storageDir))->run()` 으로 바꾼다. `run()` 은 성공하면 조용히 돌아오고, 못 옮기면 `MaintenanceRequired` 예외를 던진다. `index.php` 가 이 예외를 잡아 `MaintenancePage` 를 낸다(Slim 바깥이므로 `ErrorPageMiddleware` 가 못 잡는다).

```
run():
  1. site_settings.schema_version == Schema::stamp() → return           (SELECT 한 번, 지금과 같다)
  2. storage/upgrade-failed.json 이 있고 60초가 안 지났으면 → throw MaintenanceRequired(failed)
  3. storage/upgrade.lock 을 flock(LOCK_EX|LOCK_NB). 못 잡으면 → throw MaintenanceRequired(busy)
  4. 잠금 안에서 도장을 다시 읽는다(다른 요청이 방금 끝냈을 수 있다). 같으면 return
  5. 백업: SQLite 면 VACUUM INTO 'storage/backups/board-v{옛판}-{Ymd-His}.sqlite'.
     backups/ 는 최근 5개만 남긴다. MySQL/MariaDB는 건너뛰고 backup=null
  6. Schema::migrateAll()  (도장 갱신 포함)
  7. site_settings 에 schema_upgraded_at(now), schema_backup(경로 또는 ''), 
     upgrade-failed.json 있으면 삭제
  실패(5~6 의 Throwable):
     error.log 에 원문, upgrade-failed.json 에 {at, message, backup} 기록 → throw MaintenanceRequired(failed)
```

- `Schema` 에 `stampValue()`(public, 지금 private `stamp()`) 와 `storedStamp(): ?string` 을 연다. `ensureCurrent()` 는 테스트와 이어 쓰기용으로 남긴다.
- `storage/backups/`, `storage/upgrade.lock` 은 `.gitignore` 대상(`storage/` 전체가 이미 무시되는지 확인).
- 옛 판 번호는 저장된 도장의 앞부분(`10-…`)에서 뽑는다. 없으면 `v0`.
- 60초 재시도는 파일의 `at` 과 비교한다. 재시도 전에 파일을 지우지 않고, 성공 시에만 지운다(실패가 이어지면 `at` 을 갱신).

## 3. 점검 화면 (`src/Web/MaintenancePage`)

`render(string $kind, ?string $backup): void` — 503 + `Retry-After: 30`, `Cache-Control: no-store`, 독립 HTML(설치기와 같은 색). 두 가지:

- `busy`: "데이터 구조를 새 판으로 옮기는 중입니다. 잠시 뒤 새로고침해 주세요."
- `failed`: "데이터 구조를 새 판으로 옮기지 못했습니다. 관리자가 `storage/logs/error.log` 를 확인해야 합니다. 백업: `storage/backups/…`" (백업이 없으면 그 줄 생략). 오류 원문은 화면에 안 낸다.

## 4. 관리 콘솔 표시

사이트 설정(`admin/settings.php`) 폼 아래에 읽기 전용 "데이터 구조" 카드: 판 번호(`Schema::VERSION`), 마지막으로 옮긴 시각(`schema_upgraded_at`, 없으면 "설치 이후 없음"), 백업 목록(파일명·크기·시각, 최신순). DB 가 SQLite 가 아니면 "MySQL/MariaDB는 DB 도구로 백업하세요" 한 줄. 컨트롤러는 `SchemaUpgrader::status()` 로 받는다.

## 5. 검증

- `tests/Install/ServerCheckTest`(주입 목록으로 실패/성공), `DbSetupTest`(종류별 DSN 조립·검증 오류·probe 의 has_tables/has_admin), `InstallerTest`(완료 순서·config 내용에 cors/ttl 없음·관리자 생성·이어 쓰기·self delete 결과), `InstallSessionTest`(단계 게이트).
- `tests/Web/EntryPointTest`: install.php 가 autoload 를 바로 물고 있는지(기존), index.php 가 install.php 로 보내는 분기.
- `tests/Db/SchemaUpgraderTest`: 도장 같으면 아무것도 안 함, 옛 도장이면 백업 파일 생성 + 도장 갱신 + `schema_upgraded_at`, 6개째에서 정리, 실패 주입 시 failed.json 과 `MaintenanceRequired`, 60초 안 재시도 없음, 잠금 충돌 시 busy.
- `tests/Web/AdminPageTest`: 사이트 설정에 판 번호가 보인다.
- 실제 확인: 임시 디렉터리에 저장소를 복사해 내장 서버로 설치를 끝까지 돌린다(SQLite). 라이브는 도장을 옛 값으로 되돌린 뒤 한 번 요청해 백업 파일과 새 도장을 확인한다.

## 범위 밖

- MySQL/MariaDB 덤프.
- 판을 되돌리는(다운그레이드) 기능. 백업 파일을 손으로 되돌린다.
- 설치기의 소셜 로그인·메일 서버 설정(관리 콘솔에서 한다).
