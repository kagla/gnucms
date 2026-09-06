<?php

declare(strict_types=1);

return [
    'app' => [
        'url' => 'https://example.com',
    ],

    // 생략하면 GNUCMS 루트의 plugins/와 modules/를 사용한다.
    // 별도 배치 시 두 폴더를 포함한 상위 디렉터리의 절대 경로를 지정한다.
    // 'extensions' => ['root' => '/absolute/path/to/packages'],

    'mail' => [
        'from' => 'no-reply@example.com',
    ],

    // 사용할 프로바이더만 client_id 와 client_secret 을 채운다.
    // 각 콜백 주소: https://example.com/auth/{provider}/callback
    'oauth' => [
        'google' => ['client_id' => '', 'client_secret' => ''],
        'naver' => ['client_id' => '', 'client_secret' => ''],
        'kakao' => ['client_id' => '', 'client_secret' => ''],
    ],

    // DSN 은 sqlite: / mysql: / pgsql: 중 하나로 시작한다.
    'db' => [
        'dsn'      => 'sqlite:' . __DIR__ . '/../storage/board.sqlite',
        'username' => null,
        'password' => null,
        // 한 DB 에 여러 사이트를 둘 때만 사용. 예: site1_ (설치 후에는 바꾸지 않는다)
        'prefix'   => '',
    ],

    // 세션·메일·알림톡 비밀정보 암호화에 쓰는 시크릿. 32바이트 이상 임의 문자열. 설치기가 만들어 준다.
    // 비즈뿌리오 계정은 플러그인 설정 화면에 입력한다. 이 파일에 업체 비밀번호를 추가하지 않는다.
    'auth' => [
        'secret' => 'CHANGE-ME-32-BYTES-OR-LONGER-RANDOM-STRING',
        // 비밀번호 최소 길이. 회원가입·비밀번호 변경·비회원 글 비밀번호 모두 이 값을 따른다.
        'password_min' => 8,
    ],

    // 관리자 사이트 설정 → 보안에서 입력할 수 있다. 여기는 DB 설정이 없을 때의 fallback이다.
    // Cloudflare DNS/CDN 없이도 Turnstile만 쓸 수 있다. secret_key는 공개 저장소에 올리지 않는다.
    'turnstile' => [
        'enabled'    => false,
        'site_key'   => '',
        'secret_key' => '',
        'hostname'   => 'example.com',
    ],

    'uploads' => [
        'dir'         => __DIR__ . '/../storage/uploads',
        // 설치 전 기본값일 뿐이다. 설치 후에는 사이트 설정(관리자 화면의 attach_max_mb)이
        // 우선하며, App::attachments() 가 이 값을 그 설정으로 덮어써 넘긴다.
        'max_bytes'   => 5 * 1024 * 1024,
        'allowed_ext' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip', 'txt',
            'hwp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        ],
    ],

    'editor' => [
        'dir'       => __DIR__ . '/../storage/editor',
        'max_bytes' => 5 * 1024 * 1024,
    ],

    'log' => [
        'file' => __DIR__ . '/../storage/logs/error.log',
    ],

    // true 로 두면 오류 응답에 원문 메시지가 포함된다. 운영에서는 반드시 false.
    'debug' => false,
];
