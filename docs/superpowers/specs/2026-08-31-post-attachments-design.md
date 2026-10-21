# 게시판 파일 첨부 — 설계

> 과거 계획·작업 기록입니다. 작성 당시의 제안·검증 결과는 보존하며 현재 기능 제공을 보장하지 않습니다. 현재 지원 범위는 [공개 매뉴얼](https://gnucms.com/manual/support)과 [지원 범위 문서](https://github.com/kagla/gnucms/blob/main/docs/supported-features.md)를 따릅니다. 백업·확장·쇼핑몰은 현재 코어에 구현되어 있으며 자동 복원·자동 DB 백업은 제공하지 않습니다.

2026-08-31. 브랜치 `main`.

## 목표

`use_file` 이 켜진 게시판의 글 쓰기/수정 화면에 파일 첨부 UI 를 붙인다.
용량·개수는 관리 콘솔 사이트 설정에서 정하고, 드래그로 순서를 바꾼다.
백엔드(서명 디스크립터 방식 `AttachmentService`, `posts.attachments` JSON,
다운로드 라우트, 글 화면 첨부 목록)는 이미 있으므로 그대로 쓴다.

## 1. 설정

### 사이트 설정 (site_settings)

| 키 | 기본 | 뜻 |
|---|---|---|
| `attach_max_mb` | `5` | 파일당 최대 용량(MB). 1~1024 정수 |
| `attach_limit` | `5` | 글당 첨부 개수. 0 = 무제한, 최대 999 |

- `CmsService::DEFAULTS` 에 두 키를 더하고 `settings()` 가 정수로 돌려준다.
- `saveSettings()` 검증: `attach_max_mb` 는 `int(1..1024)`, `attach_limit` 는 `int(0..999)`.
- `admin/settings.php` 의 "표시와 가입" 아래 새 절 "파일 첨부": 두 입력 칸.
  용량 칸 힌트에 서버 한계를 보여 준다:
  `min(upload_max_filesize, post_max_size)` 를 MB 로 환산해
  "서버 PHP 한계는 N MB 입니다. 그보다 크게 적어도 N MB 까지만 받습니다."
- 확장자 허용 목록은 지금처럼 `config.uploads.allowed_ext` 로 둔다(화면 없음).

### 값의 흐름

- `App::attachments()` 가 `AttachmentService` 에 주는 `$config['max_bytes']` 를
  사이트 설정으로 덮는다: `attach_max_mb * 1048576`. 설정을 못 읽으면(설치 직후 등)
  config 값 그대로.
- `PostService` 에 `setAttachmentLimit(int $limit)` (0 = 무제한). `App` 이 조립할 때
  사이트 설정값을 넣는다. `verifyAttachments()` 가 개수를 검사해 초과면
  `['attachments' => '첨부는 N개까지입니다.']` 422.
- 게시판별 허용은 기존 `use_file`(기본 0 = 불가) 그대로. 서버 쪽 거부도 기존
  (`AttachmentService::upload`, `PostService::verifyAttachments`) 그대로.

## 2. 업로드 경로

- `POST /boards/{key}/files` (`boards.files.upload`) → `FileController::upload()`.
- 입력: multipart `file` 한 개 + `csrf_token`. CSRF 는 다른 POST 와 같은 방식.
- 처리: `AttachmentService::upload($acl, $key, $_FILES['file'])` — 권한
  (`assertCanWrite`), `use_file`, 용량, 확장자 검사 후 파일을
  `uploads/YYYY/MM/{32hex}` 로 옮기고 **서명된 디스크립터**
  `{id, name, size, mime, path, sig}` 를 돌려준다.
- 응답: 200 JSON 디스크립터(+ `size_label`). 실패는 DomainError 상태 그대로
  JSON `{error: 문구}` (413 = 용량, 422 = 확장자·게시판 불가, 401/403 = 권한).
- 서명 방식이므로 업로드 시점에 글과 매이지 않아도 안전하다. 임시 표 없음.

## 3. 쓰기/수정 화면 — `posts/_attachments.php`

`create.php` 와 `edit.php` 가 `use_file` 게시판일 때만 insert 하는 조각.

- 구성: "파일 선택" 단추(`<input type=file multiple>`) + 끌어다 놓기 존 +
  첨부 목록 `<ul>`.
- 파일을 고르거나 떨어뜨리면 **파일마다 즉시** `fetch` 로 업로드.
  행 상태: 올리는 중(진행 표시) → 완료(이름·크기·삭제 단추·손잡이) 또는
  실패(붉은 문구 + 행 제거 단추). 실패한 행은 hidden input 을 만들지 않는다.
- **순서 조정**: 행의 손잡이로 HTML5 드래그(외부 라이브러리 없음, ~40줄).
  보조로 ↑/↓ 단추(터치·키보드용). 목록의 DOM 순서가 곧 저장 순서다.
- hidden input: 행마다 `attachments[i][id|name|size|mime|path|sig]`.
  submit 직전이 아니라 행을 만들 때 넣고, 순서는 DOM 이 보장한다.
- 클라이언트 선제 한도: `data-max-bytes`, `data-limit`(0 = 무제한), `data-count`
  를 조각에 심어 초과 파일은 업로드 없이 안내 문구("파일당 N MB, 첨부 N개까지").
  서버 검사가 최종.
- 수정 화면: 저장된 첨부(디스크립터에 서명 없음)를 컨트롤러가
  `AttachmentService::withSignature()` (신규, `sign()` 재사용)로 서명을 다시 붙여
  넘기고, 조각이 목록을 미리 채운다. 빼기·순서 조정 가능. 검증 실패로 폼을
  다시 그릴 때도 같은 방식으로 유지된다.
- 저장: `PostController` 가 `attachments` 배열을 `PostService` 로 넘긴다(기존 경로).
  `verify()` 가 서명·파일 존재를 확인하고 순서대로 저장한다.

## 4. 표시

글 화면(`posts/show.php`)의 기존 첨부 목록 그대로 — 저장 순서대로 나온다.

## 5. 뒷정리 (버려진 파일)

- `AttachmentService::collectGarbage()` 는 있으나 아무 데서도 안 불린다.
  `POST /admin/uploads/gc` (`admin.uploads.gc`) 로 잇고, 사이트 설정의
  "데이터 구조" 카드에 "버려진 파일 정리" 단추를 단다. 결과(개수·용량)는
  `?gc=deleted,bytes` 로 돌아와 알림으로 보여 준다.
- **24시간 가드**: `collectGarbage()` 가 mtime 이 24시간 안 된 파일은 건너뛴다.
  작성 중인 폼이 이미 올린 파일을 지키기 위해서다.

## 6. 검증

- `AttachmentServiceTest`(기존 보강): 사이트 설정 용량 반영, 24시간 가드.
- `PostServiceTest`: 개수 제한(5개째 통과·6개째 422), 0 = 무제한, 순서 보존,
  `withSignature()` 왕복.
- 웹 테스트: 업로드 라우트(성공 JSON·CSRF 없음 403·use_file 꺼짐 422·큰 파일 413),
  글 저장 왕복(첨부 2개 순서 바꿔 저장 → show 에 그 순서), 수정에서 하나 빼기,
  `admin/settings` 저장·검증, gc 단추.
- 헤드리스 크롬 + CDP 로 실제 드래그 순서 조정과 끌어다 놓기 업로드 확인.

## 범위 밖

- 댓글 첨부, 확장자 목록의 화면 설정, 이미지 첨부의 본문 자동 삽입,
  게시판별 용량/개수 설정.
- 업로드 엔드포인트(boards.files.upload)의 개수 제한: 글당 첨부 개수 한도는
  저장(글쓰기/수정) 시에만 검사한다. 업로드 자체는 개수를 세지 않는다.
- 서명(sig)의 게시판·작성자·시간 비귀속: 서명은 파일 내용(디스크립터)의 무결성만
  보증하며, 어느 게시판·누가·언제 올렸는지는 서명에 실리지 않는다.
