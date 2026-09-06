<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use GnuCms\Error\DomainError;
use GnuCms\Install\DbSetup;
use GnuCms\Install\Installer;
use GnuCms\Install\InstallSession;
use GnuCms\Install\ServerCheck;
use GnuCms\Validation\Validator;
use GnuCms\Web\BasePath;

$root = dirname(__DIR__);
$installer = new Installer($root . '/config/config.php', $root . '/storage', __FILE__);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** @param array<string, string> $errors */
function err(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<p class="error">' . h($errors[$field]) . '</p>' : '';
}

function field(string $label, string $name, string $value, array $errors, string $type = 'text', string $hint = '', string $attrs = ''): string
{
    return '<label>' . h($label) . ($hint !== '' ? '<span class="hint">' . h($hint) . '</span>' : '')
        . '<input name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '" ' . $attrs . '></label>'
        . err($errors, $name);
}

function page(int $step, string $title, string $body): void
{
    $names = ['서버 점검', '데이터베이스', '사이트', '관리자', '완료'];
    $steps = '';
    foreach ($names as $i => $name) {
        $n = $i + 1;
        $cls = $n < $step ? 'done' : ($n === $step ? 'now' : '');
        $steps .= '<li class="' . $cls . '"><span>' . $n . '</span>' . h($name) . '</li>';
    }
    echo '<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($title) . ' · ' . h(GNUCMS) . ' 설치</title>'
        . '<style>'
        . ':root{color-scheme:light;--bg:#f4f8fd;--panel:#fff;--fg:#0f172a;--muted:#64748b;--line:#dbe4f0;--primary:#2f7fe0;--danger:#d92d20;--ok:#1a7f4b}'
        . '@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#0b1220;--panel:#111a2b;--fg:#e5edf8;--muted:#94a3b8;--line:#243043;--primary:#6aa6f0;--danger:#ff8b81;--ok:#5ad28f}}'
        . '*{box-sizing:border-box}body{margin:0;padding:40px 16px;background:var(--bg);color:var(--fg);font:15px/1.65 system-ui,-apple-system,"Segoe UI","Noto Sans KR",sans-serif}'
        . 'main{max-width:680px;margin:auto;padding:clamp(24px,5vw,40px);border:1px solid var(--line);border-radius:20px;background:var(--panel)}'
        . '.brand{color:var(--primary);font-weight:800;margin-bottom:18px}'
        . 'ol.steps{display:flex;gap:6px;list-style:none;margin:0 0 26px;padding:0;font-size:12px;color:var(--muted)}'
        . 'ol.steps li{display:flex;flex:1;align-items:center;justify-content:center;gap:5px;padding:6px 0;border-top:3px solid var(--line);text-align:center}ol.steps li span{font-weight:800}'
        . 'ol.steps li.now{border-color:var(--primary);color:var(--fg)}ol.steps li.done{border-color:var(--ok)}'
        . 'h1{margin:0 0 8px;font-size:26px;letter-spacing:-.03em}.intro{margin:0 0 22px;color:var(--muted)}'
        . 'label{display:block;margin-top:16px;font-weight:700}.hint{display:block;color:var(--muted);font-weight:400;font-size:12px}'
        . 'input,select{width:100%;margin-top:6px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--panel);color:var(--fg);font:inherit}'
        . 'input:focus,select:focus{outline:0;border-color:var(--primary);box-shadow:0 0 0 3px rgba(47,127,224,.18)}'
        . '.radios{display:flex;gap:8px;margin-top:6px}.radios label{flex:1;margin:0;padding:10px;border:1px solid var(--line);border-radius:10px;text-align:center;font-weight:600;cursor:pointer}'
        . '.radios input{width:auto;margin:0 6px 0 0}.radios label.off{opacity:.45}'
        . '.error{margin:4px 0 0;color:var(--danger);font-size:13px}.alert{padding:12px 14px;border-radius:10px;background:rgba(217,45,32,.08);color:var(--danger);margin-bottom:12px}'
        . '.notice{padding:12px 14px;border-radius:10px;background:rgba(47,127,224,.08);margin:14px 0}'
        . '.done{padding:16px 18px;border-radius:12px;background:rgba(26,127,75,.1);color:var(--ok)}'
        . 'table{width:100%;border-collapse:collapse;margin-top:8px}td,th{padding:8px 6px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}'
        . '.ok{color:var(--ok);font-weight:800}.bad{color:var(--danger);font-weight:800}.opt{color:var(--muted);font-size:12px}'
        . '.actions{display:flex;justify-content:space-between;align-items:center;margin-top:26px}'
        . 'button{min-height:44px;padding:0 20px;border:0;border-radius:10px;background:var(--primary);color:#fff;font:inherit;font-weight:750;cursor:pointer}'
        . 'a{color:var(--primary);font-weight:700}code{padding:2px 5px;border-radius:5px;background:rgba(47,127,224,.12)}'
        . 'dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px}dt{color:var(--muted)}dd{margin:0}'
        . '.pw{position:relative}.pw button{position:absolute;right:6px;bottom:6px;min-height:0;padding:6px 8px;background:transparent;color:var(--muted);font-size:12px}'
        . '</style></head><body><main><div class="brand">' . h(GNUCMS) . '</div><ol class="steps">' . $steps . '</ol>'
        . '<h1>' . h($title) . '</h1>' . $body . '</main>'
        . '<script>document.querySelectorAll("[data-show]").forEach(function(b){b.addEventListener("click",function(){var i=document.getElementById(b.dataset.show);i.type=i.type==="password"?"text":"password";b.textContent=i.type==="password"?"보기":"숨기기"})})</script>'
        . '</body></html>';
}

function redirectTo(int $step): void
{
    // 같은 폴더의 install.php 절대 경로로 보낸다. mod_rewrite 로 index.php 가
    // /board/foo 처럼 재작성된 상태에서 왔을 때도 basename 만으로는 상대 경로가
    // 어긋나므로, BasePath::siblingUrl() 로 SCRIPT_NAME 기준 폴더를 계산한다.
    $location = BasePath::siblingUrl((string) ($_SERVER['SCRIPT_NAME'] ?? '/install.php'), 'install.php');
    header('Location: ' . $location . '?step=' . $step, true, 303);
    exit;
}

if ($installer->isInstalled()) {
    page(5, '이미 설치되어 있습니다', '<p>다시 설치하려면 서버에서 <code>config/config.php</code> 를 지우고 이 화면을 새로고침하세요.</p><p><a href="./">사이트로 이동</a></p>');
    exit;
}

session_name('gnucms_install');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
$session = new InstallSession($_SESSION);

$configDir = $root . '/config';
$storageDir = $root . '/storage';
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requested = (int) ($_GET['step'] ?? 1);
$step = $session->allowedStep($requested);
if ($requested !== $step && $method === 'GET') {
    redirectTo($step);
}
$errors = [];
// 재출력(re-render)에 쓸 값. name="x[]" 처럼 배열로 온 값이 있어도 화면을 깨지 않도록
// 문자열로 눌러 둔다. 실제 검증에는 원본 $_POST 를 쓴다(각자 타입을 확인한다).
$post = $method === 'POST' ? array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $_POST) : [];

// ---------- 1. 서버 점검 ----------
if ($step === 1) {
    $result = (new ServerCheck($configDir, $storageDir))->run();
    if ($method === 'POST' && $result['ok']) {
        $session->complete(1);
        redirectTo(2);
    }
    $rows = '';
    foreach ($result['items'] as $item) {
        $rows .= '<tr><td class="' . ($item['ok'] ? 'ok' : 'bad') . '">' . ($item['ok'] ? '✓' : '✗') . '</td>'
            . '<td>' . h($item['label']) . (!$item['required'] ? ' <span class="opt">권장</span>' : '') . '</td>'
            . '<td class="opt">' . h($item['note']) . '</td></tr>';
    }
    $body = '<p class="intro">이 서버에서 ' . h(GNUCMS) . ' 가 돌 수 있는지 봅니다.</p><table>' . $rows . '</table>';
    $body .= $result['ok']
        ? '<form method="post"><div class="actions"><span></span><button type="submit">다음</button></div></form>'
        : '<p class="alert">✗ 표시된 필수 항목을 고친 뒤 <a href="?step=1">다시 점검</a>하세요.</p>';
    page(1, '서버 점검', $body);
    exit;
}

// ---------- 2. 데이터베이스 ----------
if ($step === 2) {
    $types = DbSetup::availableTypes();
    $saved = $session->get('db') ?? [];
    $values = array_merge([
        'type' => in_array('sqlite', $types, true) ? 'sqlite' : (string) ($types[0] ?? ''),
        'sqlite_path' => $storageDir . '/board.sqlite',
        'host' => 'localhost', 'port' => '', 'name' => '', 'user' => '', 'prefix' => '',
    ], (array) ($saved['input'] ?? []), $post);
    $probe = null;
    if ($method === 'POST') {
        try {
            $dbConfig = DbSetup::dsnFrom($_POST);
            $probe = DbSetup::probe($dbConfig);
            if ($probe['has_tables'] && (string) ($post['reuse'] ?? '') !== '1') {
                $errors['reuse'] = '이 DB 에는 이미 ' . h(GNUCMS) . ' 표가 있습니다. 이어 쓰려면 아래를 확인하세요.';
            } else {
                $input = $post;
                unset($input['password'], $input['reuse']);
                $session->set('db', ['config' => $dbConfig, 'probe' => $probe, 'input' => $input, 'reuse' => $probe['has_tables']]);
                $session->complete(2);
                redirectTo(3);
            }
        } catch (DomainError $e) {
            $errors = $e->details() !== [] ? $e->details() : ['_' => $e->getMessage()];
        }
    }
    $radios = '';
    foreach (DbSetup::TYPES as $key => $label) {
        $on = in_array($key, $types, true);
        $radios .= '<label class="' . ($on ? '' : 'off') . '"><input type="radio" name="type" value="' . h($key) . '"'
            . ($values['type'] === $key ? ' checked' : '') . ($on ? '' : ' disabled') . '>' . h($label)
            . ($on ? '' : '<span class="hint">드라이버 없음</span>') . '</label>';
    }
    $body = '<p class="intro">SQLite 는 파일 하나로 끝나고, MySQL/MariaDB는 DB 서버 접속 정보가 필요합니다.</p>'
        . (isset($errors['_']) ? '<p class="alert">' . h($errors['_']) . '</p>' : '')
        . '<form method="post"><div class="radios">' . $radios . '</div>' . err($errors, 'type')
        . '<div id="sqlite">' . field('SQLite 파일 경로', 'sqlite_path', $values['sqlite_path'], $errors, 'text', '웹에서 접근할 수 없는 폴더의 절대 경로') . '</div>'
        . '<div id="server">'
        . field('호스트', 'host', $values['host'], $errors)
        . field('포트', 'port', $values['port'], $errors, 'text', '비우면 기본값 (MySQL/MariaDB 3306)', 'inputmode="numeric"')
        . field('DB 이름', 'name', $values['name'], $errors)
        . field('DB 계정', 'user', $values['user'], $errors)
        . field('DB 비밀번호', 'password', '', $errors, 'password', '', 'autocomplete="off"')
        . '</div>'
        . field('테이블 프리픽스', 'prefix', $values['prefix'], $errors, 'text', '선택 사항. 한 DB에 여러 사이트를 설치할 때 사용합니다. 예: site1_', 'maxlength="30" pattern="[A-Za-z][A-Za-z0-9_]{0,28}_"');
    if (isset($errors['reuse'])) {
        // $errors['reuse'] 는 위에서 h() 로 이미 이스케이프해 만든 문자열이다. 여기서 또
        // h() 를 씌우면 두 번 이스케이프된다.
        $body .= '<div class="notice"><p>' . $errors['reuse'] . '</p><label style="margin:0;font-weight:600"><input type="checkbox" name="reuse" value="1" style="width:auto;margin-right:6px">기존 데이터베이스를 이어 씁니다 (표를 새로 만들지 않고 새 판으로 옮깁니다)</label></div>';
    }
    $body .= '<div class="actions"><a href="?step=1">← 이전</a><button type="submit">접속 시험 후 다음</button></div></form>'
        . '<script>function sw(){var t=document.querySelector("input[name=type]:checked");var s=t&&t.value==="sqlite";document.getElementById("sqlite").style.display=s?"":"none";document.getElementById("server").style.display=s?"none":""}document.querySelectorAll("input[name=type]").forEach(function(r){r.addEventListener("change",sw)});sw()</script>';
    page(2, '데이터베이스', $body);
    exit;
}

// ---------- 3. 사이트 ----------
if ($step === 3) {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('/^[A-Za-z0-9.\-:\[\]]+$/D', $host) !== 1) {
        $host = 'localhost';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $mailHost = preg_replace('/:\d+$/', '', trim($host, '[]')) ?: 'localhost';
    $values = array_merge([
        'site_name' => GNUCMS,
        'app_url' => $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/'),
        'mail_from' => 'no-reply@' . $mailHost,
    ], $session->get('site') ?? [], $post);
    if ($method === 'POST') {
        try {
            $session->set('site', Installer::siteFrom($_POST));
            $session->complete(3);
            $db = $session->get('db') ?? [];
            redirectTo(!empty($db['probe']['has_admin']) ? 5 : 4);
        } catch (DomainError $e) {
            $errors = $e->details();
        }
    }
    $body = '<p class="intro">나중에 관리 콘솔에서 바꿀 수 있습니다.</p><form method="post">'
        . field('사이트 이름', 'site_name', $values['site_name'], $errors, 'text', '', 'maxlength="50" required')
        . field('사이트 주소', 'app_url', $values['app_url'], $errors, 'url', '인증 메일과 비밀번호 재설정 링크에 씁니다', 'required')
        . field('발신 이메일', 'mail_from', $values['mail_from'], $errors, 'email', '인증 메일을 보낼 주소. 운영 도메인의 주소를 권장합니다', 'required')
        . '<div class="actions"><a href="?step=2">← 이전</a><button type="submit">다음</button></div></form>';
    page(3, '사이트', $body);
    exit;
}

// ---------- 4. 첫 관리자 ----------
if ($step === 4) {
    $db = $session->get('db') ?? [];
    if (!empty($db['probe']['has_admin'])) {
        $session->complete(4);
        redirectTo(5);
    }
    $values = array_merge(['email' => '', 'display_name' => ''], $session->get('admin') ?? [], $post);
    if ($method === 'POST') {
        try {
            $session->set('admin', Installer::adminFrom($_POST));
            $session->complete(4);
            redirectTo(5);
        } catch (DomainError $e) {
            $errors = $e->details();
        }
    }
    $body = '<p class="intro">이 계정이 전역 관리자가 됩니다. 이메일 인증은 건너뜁니다.</p><form method="post">'
        . field('이메일', 'email', $values['email'], $errors, 'email', '', 'required autocomplete="username"')
        . field('표시 이름', 'display_name', $values['display_name'], $errors, 'text', '한글·영문·숫자만. 한글 2자 또는 영문 4자 이상', 'required')
        . '<label>비밀번호<span class="hint">' . h((string) Validator::passwordMin()) . '자 이상</span><div class="pw"><input id="pw1" name="password" type="password" autocomplete="new-password" required><button type="button" data-show="pw1">보기</button></div></label>' . err($errors, 'password')
        . '<label>비밀번호 확인<div class="pw"><input id="pw2" name="password_confirmation" type="password" autocomplete="new-password" required><button type="button" data-show="pw2">보기</button></div></label>' . err($errors, 'password_confirmation')
        . '<div class="actions"><a href="?step=3">← 이전</a><button type="submit">다음</button></div></form>';
    page(4, '첫 관리자', $body);
    exit;
}

// ---------- 5. 완료 ----------
$db = $session->get('db') ?? [];
$site = $session->get('site') ?? [];
$admin = $session->get('admin');
if ($admin === null && empty($db['probe']['has_admin'])) {
    // 관리자 없는 빈 DB 인데 4단계를 건너뛰고 왔다. 관리자 정보부터 받는다.
    redirectTo(4);
}
$reuse = !empty($db['reuse']);
if ($method === 'POST') {
    try {
        $result = $installer->finish((array) $db['config'], $site, $reuse && !empty($db['probe']['has_admin']) ? null : $admin, $reuse);
        $session->reset();
        session_destroy();
        $body = '<div class="done"><p><strong>설치가 끝났습니다.</strong> 사용 중인 DB: ' . h($result['dialect']) . '</p>'
            . ($result['admin_email'] !== null ? '<p>관리자: <code>' . h($result['admin_email']) . '</code></p>' : '')
            . '</div>';
        $body .= $result['self_deleted'] === false
            ? '<p class="alert"><strong>install.php 를 지우지 못했습니다.</strong> 지금 <code>www/install.php</code> 를 손으로 삭제하세요. 남겨 두면 설정 파일을 지운 사람이 재설치할 수 있습니다.</p>'
            : '<p class="notice">설치기(<code>install.php</code>)는 스스로 삭제했습니다.</p>';
        $body .= '<p><a href="./login">로그인하러 가기</a> · <a href="./">사이트로 이동</a></p>';
        page(5, '완료', $body);
        exit;
    } catch (DomainError $e) {
        $errors = $e->details() !== [] ? $e->details() : ['_' => $e->getMessage()];
    }
}
$dbLabel = DbSetup::TYPES[(string) ($db['input']['type'] ?? '')] ?? (string) ($db['probe']['dialect'] ?? '');
$body = '<p class="intro">아래 내용으로 설치합니다. 표를 만들고, 관리자를 만들고, <code>config/config.php</code> 를 씁니다.</p>';
foreach ($errors as $message) {
    $body .= '<p class="alert">' . h((string) $message) . '</p>';
}
$body .= '<dl><dt>데이터베이스</dt><dd>' . h($dbLabel) . ($reuse ? ' (기존 DB 이어 쓰기)' : '') . '</dd>'
    . '<dt>테이블 프리픽스</dt><dd>' . h((string) ($db['config']['prefix'] ?? '')) . (((string) ($db['config']['prefix'] ?? '')) === '' ? '사용 안 함' : '') . '</dd>'
    . '<dt>사이트 이름</dt><dd>' . h((string) ($site['site_name'] ?? '')) . '</dd>'
    . '<dt>사이트 주소</dt><dd>' . h((string) ($site['app_url'] ?? '')) . '</dd>'
    . '<dt>발신 이메일</dt><dd>' . h((string) ($site['mail_from'] ?? '')) . '</dd>'
    . '<dt>관리자</dt><dd>' . ($admin !== null && empty($db['probe']['has_admin']) ? h($admin['email']) . ' (' . h($admin['display_name']) . ')' : '기존 DB 의 관리자를 그대로 씁니다') . '</dd></dl>'
    . '<form method="post"><div class="actions"><a href="?step=' . (empty($db['probe']['has_admin']) ? 4 : 3) . '">← 이전</a><button type="submit">설치</button></div></form>';
page(5, '설치 확인', $body);
