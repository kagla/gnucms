# GNUCMS 공개 매뉴얼

운영자 전체 설명서와 개발자 문서를 제공하는 선택 모듈입니다. 기본 상태는 사용 안 함이며, 설명서를 제공할 사이트에서 **모듈 → 매뉴얼 → 사용 → 저장**으로 켭니다. 공개 진입점은 `/manual`입니다. 회원·주문·발송 기록이나 비밀 설정을 문서와 검색 자료에 넣지 않습니다.

## 구성

- `extension.json`: `/manual` 공개 경로, 이전 확장 주소 별칭 없음
- `bootstrap.php`: 목차에 있는 고정 GET 경로와 `/manual/search` 등록
- `src/ManualLibrary.php`: 배포 문서 파일 읽기와 이전·다음 문서 연결
- `templates/manual.php`: 사이트 레이아웃 안의 목차·본문·검색 UI
- `www/themes/default/manual.css`, `manual.js`: 스크롤 없는 반응형 본문, 검색·복사·인쇄
- `content/catalog.json`: 그룹·문서 메타데이터와 처음 읽을 문서 순서
- `content/<이름>.json`: 문서 본문
- `content/search.json`: 작성된 설명서만 담은 본문 검색 자료

## 데이터베이스 지원 표기

GNUCMS는 MySQL과 MariaDB를 지원합니다. SQLite·PostgreSQL의 설치와 실행은 지원하지 않습니다. 설치·개발·백업 문서와 검색 자료에 같은 범위를 표시하고, 과거 설계 문서의 DB 예시를 현재 지원 범위로 안내하지 않습니다.

## 문서 추가·수정

1. `catalog.json`의 `articles`에 `slug`, `title`, `summary`, `group`, `audience`, `file`을 등록합니다. slug는 `shop/orders`처럼 영문·숫자·밑줄·하이픈 경로입니다. 파일명은 `shop-orders.json`처럼 슬래시 없는 JSON 이름입니다.
2. 본문 파일에 `screen`, `sections`, `related`, `sources`를 작성합니다. `screen`은 설명 대상 화면의 경로이며 운영 사이트로 상태 변경 링크를 만들지 않습니다.
3. 각 section은 `title`과 `blocks`입니다. 블록은 `p`(text), `note`(title/text), `list`·`steps`(items), `table`(headers/rows), `code`(language/text), `gallery`(images)를 지원합니다. HTML은 사용하지 않으며 모든 본문은 이스케이프합니다.
4. `related`는 목차에 등록된 slug, `sources`는 실제 upstream 소스 경로입니다. 구현과 다른 옛 설계 문서를 사용자 절차의 근거로 삼지 않습니다.
5. 검색 자료의 같은 slug 항목도 수정합니다. `title`, `summary`, `text`는 작성된 설명만 포함하고 코드 예시·실제 DB 값을 수집하지 않습니다.

새 문서는 배포 후 bootstrap이 목차를 읽어 라우트를 등록합니다. 사용자 요청으로 파일 경로를 구성하지 않습니다. 검색은 브라우저에서 작성 문서 인덱스를 읽어 처리하며 검색어를 서버에 저장하거나 외부 검색 서비스로 전송하지 않습니다.

## 테마

사이트의 `layout`과 자산·시간대·전역값을 재사용합니다. `templates/<테마>/extensions/manual/manual.php`로 화면을 재정의할 수 있습니다. 매뉴얼 CSS는 `.manual` 아래에만 적용합니다. 모바일에서는 목차를 접고, 표를 카드로 표시합니다. 브라우저 인쇄에서 PDF로 저장할 수 있습니다.

상태 변경·발송·결제·추가 DB 테이블은 없습니다. 운영자 기능에 접근하려면 기존 관리자 권한을 사용해야 하며 공개 설명서가 관리자 권한을 제공하지 않습니다.

## 화면 썸네일

`gallery.images`에 `file`(테마 자산 상대 경로), `title`, `alt`, `caption`을 작성합니다. 이미지는 `www/themes/default/manual/` 아래에 배치하며 선택 테마에서도 자산 fallback을 사용합니다. 썸네일을 누르면 제목과 원본 이미지를 모달로 표시합니다. 닫기·Escape·배경 클릭으로 닫고, JavaScript가 없으면 이미지 링크를 직접 엽니다.

설치 안내의 6개 JPEG는 실제 `www/install.php`의 화면 렌더링 코드를 사용한 예시입니다. DB 접속이나 실제 설치를 실행하지 않고 로컬 문서 제작 환경에서 캡처했으며 계정·주소는 예시값입니다. 비밀번호와 운영 DB 정보는 포함하지 않습니다. 설치 화면이 바뀌면 썸네일도 갱신합니다. 화면 캡처를 만들 때 검사 코드나 테스트 결과를 설치 성공 증거로 대신 사용하지 않습니다.
