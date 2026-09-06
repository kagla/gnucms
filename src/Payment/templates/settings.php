<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?><?= $this->e($label) ?> 결제 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>plugins<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'plugins', 'heading' => $label . ' 결제 설정', 'description' => '상점 코드와 인증 정보를 등록하고 환경별 결제 실행을 관리합니다.', 'actions' => [['url' => ($public_extensions['modules/shop']['admin_url'] ?? $this->base . '/admin/shop'), 'label' => '쇼핑몰 관리']]]) ?>
<nav class="tabs tabs-border settings-tabs" aria-label="결제 환경">
<?php foreach (['test' => '테스트 환경', 'live' => '운영 환경'] as $env => $envLabel): ?><a class="tab<?= $environment === $env ? ' tab-active' : '' ?>" href="<?= $this->e($this->base . '/' . $key) ?>/settings?environment=<?= $env ?>"<?= $environment === $env ? ' aria-current="page"' : '' ?>><?= $this->e($envLabel) ?></a><?php endforeach ?>
</nav>
<?php if (!$integration_ready): ?><div class="alert alert-warning" role="status"><span>직접 연동 준비 중입니다. 결제창·승인·조회 규격 확인이 끝나기 전에는 쇼핑몰에서 이 결제사를 선택할 수 없습니다.</span></div><?php endif ?>
<?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><span><?= $this->e($error) ?></span></div><?php endforeach ?>
<?php if ($notice): ?><div class="alert alert-success" role="status"><span><?= $this->e($notice) ?></span></div><?php endif ?>
<?php if (!$ready): ?>
<section class="card settings-card extension-panel"><div class="card-body">
<h2 class="card-title">결제 데이터 준비</h2><p class="card-sub">처음 사용하거나 플러그인을 갱신했다면 결제 데이터를 설치·갱신해 주세요.</p>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="install">결제 플러그인 데이터 설치</button></div></form>
</div></section>
<?php else: ?>
<section class="card settings-card extension-panel"><div class="card-body">
<h2 class="card-title">상점과 인증 정보</h2><p class="card-sub">가맹점 등록 후 PG에서 발급한 상점 코드와 인증 정보를 입력해 주세요. 리셀러 코드는 가맹점 등록에 사용합니다. 인증 정보는 암호화해서 저장합니다.</p>
<?php if ($key === 'plugins/payment-toss'): ?><p class="card-sub">토스페이먼츠 개발자센터에서 같은 MID의 API 개별 연동 클라이언트 키(ck)·시크릿 키(sk)를 입력하세요. API 버전은 2022-11-16을 사용합니다. 카드·간편결제 통합결제창을 제공하며, 주문서형·결제창형 연동 키(gck/gsk)는 지원하지 않습니다.</p><?php endif ?>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<?php foreach ($fields as $name => $field): ?><fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><label for="payment-<?= $this->e($name) ?>"><?= $this->e($field['label']) ?></label></legend>
<?php if ($field['multiline']): ?><textarea class="textarea textarea-bordered textarea-block code-textarea" id="payment-<?= $this->e($name) ?>" name="<?= $this->e($name) ?>" rows="4" autocomplete="off" spellcheck="false" placeholder="<?= $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '발급받은 PEM 내용' ?>"></textarea>
<?php else: ?><input class="input input-bordered input-block" id="payment-<?= $this->e($name) ?>" type="<?= $field['secret'] ? 'password' : 'text' ?>" name="<?= $this->e($name) ?>" value="<?= $field['secret'] ? '' : $this->e($settings[$name] ?? '') ?>" autocomplete="<?= $field['secret'] ? 'new-password' : 'off' ?>"<?= !$field['secret'] ? ' required' : '' ?> placeholder="<?= $field['secret'] && $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '' ?>">
<?php endif ?></fieldset><?php endforeach ?>
<div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="save">설정 저장</button></div></form>
</div></section>
<section class="card settings-card extension-panel"><div class="card-body">
<h2 class="card-title">결제 실행 상태 <span class="badge badge-soft<?= $settings['enabled'] ? ' badge-success' : '' ?>"><?= $settings['enabled'] ? '허용됨' : '정지됨' ?></span></h2>
<p class="card-sub">PG에서 발급받은 상점 코드가 선택한 환경용인지 확인해 주세요. 테스트 결제는 운영 정산에 포함되지 않습니다.</p>
<form method="post" action="<?= $this->e($this->base . '/' . $key) ?>/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><div class="card-actions form-actions"><button class="btn<?= $settings['enabled'] ? '' : ' btn-primary' ?>" name="action" value="<?= $settings['enabled'] ? 'disable' : 'enable' ?>"<?= (!$settings['configured'] || !$integration_ready) ? ' disabled' : '' ?>><?= $settings['enabled'] ? 'API 실행 정지' : 'API 실행 허용' ?></button></div></form>
</div></section>
<?php endif ?>
<p class="muted"><a class="link" href="<?= $this->e($manual) ?>" target="_blank" rel="noopener noreferrer">PG 공식 연동 문서 <?= $this->icon('external', 14) ?></a></p>
<?php $this->stop() ?>
