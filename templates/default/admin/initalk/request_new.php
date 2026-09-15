<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>결제 생성 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $v = static fn (string $key, string $default = ''): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : $default; ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'new', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<section class="card card-body extension-panel"><h2 class="card-title">결제 생성 <small><?= $config['environment'] === 'live' ? '운영' : '테스트' ?> 환경</small></h2>
<p>결제 URL이 포함된 알림톡을 고객 휴대전화로 전송합니다. 알림톡의 링크는 결제기한(기본 <?= (int) $config['expiry_hours'] ?>시간) 동안 유효합니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.requests.new') ?>" autocomplete="off" data-initalk-new>
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="product_name">상품명 (최대 30자)</label><input class="input input-bordered input-block" id="product_name" name="product_name" maxlength="30" required value="<?= $this->e($v('product_name')) ?>">
<label class="extension-label" for="product_detail">상품 상세 (선택, 최대 150자)</label><textarea class="textarea textarea-bordered textarea-block" id="product_detail" name="product_detail" maxlength="150" rows="3"><?= $this->e($v('product_detail')) ?></textarea>
<label class="extension-label" for="buyer_name">구매자명</label><input class="input input-bordered input-block" id="buyer_name" name="buyer_name" maxlength="30" required value="<?= $this->e($v('buyer_name')) ?>">
<label class="extension-label" for="phone">휴대폰번호</label><input class="input input-bordered input-block" type="tel" id="phone" name="phone" data-phone-format="mobile" inputmode="tel" maxlength="20" required placeholder="010-1234-5678" value="<?= $this->e($v('phone')) ?>" data-customer-url="<?= $this->url('admin.initalk.customer') ?>">
<p id="initalk-customer" class="muted" aria-live="polite"></p>
<label class="extension-label" for="amount">금액 (원)</label><input class="input input-bordered input-block" id="amount" name="amount" inputmode="numeric" maxlength="12" required value="<?= $this->e($v('amount')) ?>">
<p id="initalk-amount-words" class="muted" aria-live="polite"></p>
<label class="extension-label" for="expiry_hours">결제기한 (시간, 비우면 기본 <?= (int) $config['expiry_hours'] ?>시간)</label><input class="input input-bordered input-block" id="expiry_hours" name="expiry_hours" inputmode="numeric" maxlength="3" value="<?= $this->e($v('expiry_hours')) ?>">
<label class="extension-label"><input class="checkbox" type="checkbox" name="send_now" value="1"<?= $values === [] || $v('send_now') === '1' ? ' checked' : '' ?>> 결제 알림톡 즉시 전송하기</label>
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">거래등록</button></div>
</form></section>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><?php $this->insert('admin/_phone_input') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
