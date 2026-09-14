<?php $this->layout('admin/layout') ?>
<?php $this->start('title') ?><?= $this->e($label) ?> 결제 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>site<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li><a href="<?= $this->url('admin.settings') ?>">설정</a></li><li aria-current="page">결제</li></ul></div>
<?php $this->insert('admin/_settings_tabs', ['active' => 'payment']) ?>
<section class="card settings-card">
  <div class="card-body">
    <h1 class="card-title"><?= $this->icon('shield', 19) ?> <?= $this->e($label) ?> 결제 설정</h1>
    <p class="card-sub">상점 코드와 인증 정보를 등록하고 환경별 결제 실행을 관리합니다. 이니톡 결제의 카드결제에 사용합니다. 인증 정보는 암호화해서 저장합니다.</p>
    <nav class="tabs tabs-border settings-tabs" aria-label="결제 환경">
      <?php foreach (['test' => '테스트 환경', 'live' => '운영 환경'] as $env => $envLabel): ?><a class="tab<?= $environment === $env ? ' tab-active' : '' ?>" href="<?= $this->url('admin.settings.payment') ?>?environment=<?= $env ?>"<?= $environment === $env ? ' aria-current="page"' : '' ?>><?= $this->e($envLabel) ?></a><?php endforeach ?>
    </nav>
    <?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><span><?= $this->e($error) ?></span></div><?php endforeach ?>
    <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><span><?= $this->e($notice) ?></span></div><?php endif ?>
    <form method="post" action="<?= $this->url('admin.settings.payment') ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
      <?php foreach ($fields as $name => $field): ?>
      <fieldset class="fieldset<?= isset($errors[$name]) ? ' is-invalid' : '' ?>"><legend class="fieldset-legend"><label for="payment-<?= $this->e($name) ?>"><?= $this->e($field['label']) ?></label></legend>
        <input class="input input-bordered input-block" id="payment-<?= $this->e($name) ?>" type="<?= $field['secret'] ? 'password' : 'text' ?>" name="<?= $this->e($name) ?>" value="<?= $field['secret'] ? '' : $this->e($settings[$name] ?? '') ?>" autocomplete="<?= $field['secret'] ? 'new-password' : 'off' ?>"<?= !$field['secret'] ? ' required' : '' ?> placeholder="<?= $field['secret'] && $settings['configured'] ? '같은 상점에서 비워두면 현재 값 유지' : '' ?>">
      </fieldset>
      <?php endforeach ?>
      <div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="save">설정 저장</button></div>
    </form>
  </div>
</section>
<section class="card settings-card">
  <div class="card-body">
    <h2 class="card-title">결제 실행 상태 <span class="badge badge-soft<?= $settings['enabled'] ? ' badge-success' : '' ?>"><?= $settings['enabled'] ? '허용됨' : '정지됨' ?></span></h2>
    <p class="card-sub">PG에서 발급받은 상점 코드가 선택한 환경용인지 확인해 주세요. 테스트 결제는 운영 정산에 포함되지 않습니다. 백업을 복원하면 실행 허용이 해제됩니다.</p>
    <form method="post" action="<?= $this->url('admin.settings.payment') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><div class="card-actions form-actions"><button class="btn<?= $settings['enabled'] ? '' : ' btn-primary' ?>" name="action" value="<?= $settings['enabled'] ? 'disable' : 'enable' ?>"<?= !$settings['configured'] ? ' disabled' : '' ?>><?= $settings['enabled'] ? 'API 실행 정지' : 'API 실행 허용' ?></button></div></form>
  </div>
</section>
<p class="muted"><a class="link" href="<?= $this->e($manual) ?>" target="_blank" rel="noopener noreferrer">PG 공식 연동 문서 <?= $this->icon('external', 14) ?></a></p>
<?php $this->stop() ?>
