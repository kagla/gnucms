<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= $this->e($label) ?> 결제 설정</title>
<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;background:#f3f5f7;color:#172633}main{max-width:800px;margin:40px auto;padding:24px;background:white;border-radius:14px}label{display:block;margin:16px 0}input,select,button{font:inherit;padding:10px;border:1px solid #ccd3da;border-radius:6px}input{display:block;width:95%}button{cursor:pointer;background:#123f4d;color:#fff}nav a{margin-right:16px}aside{background:#fff4d8;padding:14px}.error{color:#a12626}.notice{color:#07614c}</style></head><body><main>
<nav><a href="<?= $this->e($this->base) ?>/admin/plugins">플러그인 관리</a><a href="<?= $this->e($this->base) ?>/modules/shop/admin">쇼핑몰 관리</a></nav>
<h1><?= $this->e($label) ?> 결제</h1><p>PortOne V2 · 원화 카드결제 · 전체/부분 환불</p>
<aside>PortOne의 해당 결제사 채널을 연결합니다. 이니시스·KCP·KSNET의 직접 계약 키 대신 <strong>PortOne V2 API Secret</strong>을 입력해 주세요. 설정은 암호화되며 이전 주문의 환불을 위해 이전 설정 판도 보관합니다.</aside>
<?php foreach ($errors as $error): ?><p class="error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($notice): ?><p class="notice" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<form method="get" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><label>결제 환경 <select name="environment"><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트</option><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영</option></select></label><button>환경 보기</button></form>
<?php if (!$ready): ?>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><button name="action" value="install">결제 플러그인 데이터 설치</button></form>
<?php else: ?>
<p>API 실행: <strong><?= $settings['enabled'] ? '허용됨' : '정지됨' ?></strong></p>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<label>PortOne 상점 ID<input name="store_id" value="<?= $this->e($settings['store_id']) ?>" placeholder="store-…" required></label>
<label><?= $environment === 'test' ? '테스트' : '운영' ?> 채널 키<input name="channel_key" value="<?= $this->e($settings['channel_key']) ?>" placeholder="channel-key-…" required></label>
<label>PortOne V2 API Secret<input type="password" name="api_secret" autocomplete="new-password" placeholder="<?= $settings['configured'] ? '비워두면 현재 값 유지' : 'V2 API Secret' ?>"></label>
<label>PortOne 웹훅 서명 시크릿<input type="password" name="webhook_secret" autocomplete="new-password" placeholder="<?= $settings['configured'] ? '비워두면 현재 값 유지' : '웹훅 서명 시크릿' ?>"></label>
<button name="action" value="save">설정 저장</button></form>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><p>PortOne의 채널 유형이 선택한 환경과 일치하는지 확인해 주세요. 테스트 결제는 운영 정산에 포함되지 않습니다.</p><button name="action" value="<?= $settings['enabled'] ? 'disable' : 'enable' ?>"<?= !$settings['configured'] ? ' disabled' : '' ?>><?= $settings['enabled'] ? 'API 실행 정지' : 'API 실행 허용' ?></button></form>
<?php endif ?>
<p><a href="https://developers.portone.io/opi/ko/quick-guide/payment" target="_blank" rel="noopener noreferrer">PortOne 공식 연동 문서</a></p></main></body></html>
