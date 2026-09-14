<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>문자 발송 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>messaging<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li aria-current="page">메시지 발송</li></ul></div>
<div class="page-head"><div><h1>문자 발송</h1><p class="muted">SMS·LMS 문자를 작성하고 발송 결과를 확인합니다.</p></div>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.messaging.send') ?>?environment=<?= $this->e($environment) ?>">알림톡 발송</a><a class="btn btn-sm" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">알림톡·문자 설정</a></div></div>
<div class="extension-toolbar"><span class="badge badge-soft"><?= $environment === 'live' ? '운영 환경' : '테스트 환경' ?></span>
<form method="get" action="<?= $this->url($page === 'sms-detail' ? 'admin.messaging.sms.history' : ($page === 'sms-send' ? 'admin.messaging.sms.send' : 'admin.messaging.sms.history')) ?>" class="row">
<label class="sr-only" for="sms-environment">발송 환경</label><select class="select select-bordered select-block" id="sms-environment" name="environment"><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트 환경</option><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영 환경</option></select><button type="submit" class="btn btn-primary">환경 보기</button></form></div>
<nav class="tabs tabs-border settings-tabs" aria-label="문자 운영 메뉴"><?php foreach (['sms-send' => ['admin.messaging.sms.send', '문자 발송'], 'sms-history' => ['admin.messaging.sms.history', '발송 이력']] as $key => [$route, $label]): $active = $page === $key || ($page === 'sms-detail' && $key === 'sms-history'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>?environment=<?= $this->e($environment) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($page === 'sms-send'): ?>
<section class="card card-body extension-panel"><h2 class="card-title">발송 계정</h2>
<?php if ($account_status['configured']): ?><p>계정: <strong><?= $this->e($account_status['account']) ?></strong> · <?= $account_status['enabled'] ? '발송 허용' : '발송 정지' ?></p><p>API 연결 확인: <?= $account_status['api_verified'] ? '확인 완료' : '확인 전' ?></p>
<p>발송 대상: <?php if ($account_status['test_only']): ?>테스트 번호 <?= trim($this->fetch('admin/messaging/_phone', ['phone' => $account_status['test_phone']])) ?>로만 발송<?php else: ?>입력한 국내 휴대폰 번호<?php endif ?></p>
<?php else: ?><p>계정과 사전 등록한 발신번호를 먼저 설정해 주세요.</p><?php endif ?>
<?php if (!$account_status['enabled']): ?><p><a class="link" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">계정 설정·인증 확인·발송 허용</a></p><?php endif ?></section>
<?php $this->insert('admin/messaging/sms_send') ?>
<?php elseif ($page === 'sms-detail'): $this->insert('admin/messaging/sms_detail'); ?>
<?php else: $this->insert('admin/messaging/sms_history'); endif ?>
<p class="muted">전체 관리자 전용 · 국내 휴대폰 단건 SMS·LMS · 표시 시각: 한국 시간</p>
<?php $this->stop() ?>
<?php if ($ready && $page === 'sms-send'): ?><?php $this->start('scripts') ?><?php $this->insert('admin/_phone_input') ?><?php $this->stop() ?><?php endif ?>
