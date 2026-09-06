<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= $this->e($label) ?> 결제 설정</title>
<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;background:#f3f5f7;color:#172633}main{max-width:800px;margin:40px auto;padding:24px;background:white;border-radius:14px}label{display:block;margin:16px 0}input,textarea,select,button{font:inherit;padding:10px;border:1px solid #ccd3da;border-radius:6px}input,textarea{display:block;width:95%}button{cursor:pointer;background:#123f4d;color:#fff}nav a{margin-right:16px}aside{background:#fff4d8;padding:14px}.error{color:#a12626}.notice{color:#07614c}</style></head><body><main>
<nav><a href="<?= $this->e($this->base) ?>/admin/plugins">플러그인 관리</a><a href="<?= $this->e($this->base) ?>/modules/shop/admin">쇼핑몰 관리</a></nav>
<h1><?= $this->e($label) ?> 결제</h1><p>PG 직접 연동 · 원화 과세 카드결제</p>
<aside>가맹점 등록 후 PG에서 발급한 <strong>상점 코드와 인증 정보</strong>를 입력해 주세요. 리셀러 코드는 가맹점 등록에 사용합니다. 인증 정보는 암호화해서 저장합니다.</aside>
<?php if (!$integration_ready): ?><p class="error" role="status">직접 연동 준비 중입니다. 결제창·승인·조회 규격 확인이 끝나기 전에는 쇼핑몰에서 이 결제사를 선택할 수 없습니다.</p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($notice): ?><p class="notice" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<form method="get" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><label>결제 환경 <select name="environment"><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트</option><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영</option></select></label><button>환경 보기</button></form>
<?php if (!$ready): ?>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><button name="action" value="install">결제 플러그인 데이터 설치</button></form>
<?php else: ?>
<p>API 실행: <strong><?= $settings['enabled'] ? '허용됨' : '정지됨' ?></strong></p>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<?php foreach ($fields as $name => $field): ?><label><?= $this->e($field['label']) ?>
<?php if ($field['multiline']): ?><textarea name="<?= $this->e($name) ?>" rows="4" autocomplete="off" spellcheck="false" placeholder="<?= $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '발급받은 PEM 내용' ?>"></textarea>
<?php else: ?><input type="<?= $field['secret'] ? 'password' : 'text' ?>" name="<?= $this->e($name) ?>" value="<?= $field['secret'] ? '' : $this->e($settings[$name] ?? '') ?>" autocomplete="<?= $field['secret'] ? 'new-password' : 'off' ?>"<?= !$field['secret'] ? ' required' : '' ?> placeholder="<?= $field['secret'] && $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '' ?>">
<?php endif ?></label><?php endforeach ?>
<button name="action" value="save">설정 저장</button></form>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><p>PG에서 발급받은 상점 코드가 선택한 환경용인지 확인해 주세요. 테스트 결제는 운영 정산에 포함되지 않습니다.</p><button name="action" value="<?= $settings['enabled'] ? 'disable' : 'enable' ?>"<?= (!$settings['configured'] || !$integration_ready) ? ' disabled' : '' ?>><?= $settings['enabled'] ? 'API 실행 정지' : 'API 실행 허용' ?></button></form>
<?php endif ?>
<p><a href="<?= $this->e($manual) ?>" target="_blank" rel="noopener noreferrer">PG 공식 연동 문서</a></p></main></body></html>
