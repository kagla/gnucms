<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>알림톡 발송 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>messaging<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li aria-current="page">메시지 발송</li></ul></div>
<div class="page-head"><div><h1>알림톡 발송</h1><p class="muted">승인 템플릿으로 발송하고 접수·도달 결과를 확인합니다.</p></div>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.messaging.sms.send') ?>?environment=<?= $this->e($environment) ?>">문자 발송</a><a class="btn btn-sm" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">알림톡·문자 설정</a></div></div>
<div class="extension-toolbar"><span class="badge badge-soft"><?= $environment === 'live' ? '운영 환경' : '테스트 환경' ?></span>
<form method="get" action="<?= $this->url($page === 'detail' ? 'admin.messaging.history' : 'admin.messaging.' . $page) ?>" class="row">
<label class="sr-only" for="alimtalk-environment">발송 환경</label><select class="select select-bordered select-block" id="alimtalk-environment" name="environment"><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트 환경</option><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영 환경</option></select><button class="btn btn-outline">환경 보기</button></form></div>
<nav class="tabs tabs-border settings-tabs" aria-label="알림톡 운영 메뉴"><?php foreach (['templates' => ['admin.messaging.templates', '템플릿'], 'send' => ['admin.messaging.send', '웹발송'], 'history' => ['admin.messaging.history', '발송 이력']] as $key => [$route, $label]): $active = $page === $key || ($page === 'detail' && $key === 'history'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>?environment=<?= $this->e($environment) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<?php if ($page === 'send'): ?><section class="card card-body extension-panel"><h2 class="card-title">GNUCMS 웹발송</h2><p>승인 템플릿 선택 → 수신번호·변수 입력 → 미리보기 → 발송 → 결과 확인</p>
<?php if ($account_status['configured']): ?><p>계정: <strong><?= $this->e($account_status['account']) ?></strong> · <?= $account_status['account_type'] === 'web' ? '웹발송 계정' : '모듈 연동 계정' ?> · <?= $account_status['enabled'] ? '발송 허용' : '발송 정지' ?></p>
<p>API 연결 확인: <?= $account_status['api_verified'] ? '확인 완료' : '확인 전' ?></p>
<p>발송 대상: <?php if ($account_status['test_only']): ?>테스트 번호 <?= $this->e($account_status['test_phone']) ?>로만 발송<?php else: ?>입력한 국내 휴대폰 번호<?php endif ?></p>
<?php else: ?><p>발송할 계정을 먼저 설정해 주세요. 웹발송 계정도 API 인증 확인 후 사용할 수 있으며, 실제 알림톡 발송 권한은 별도로 필요합니다.</p><?php endif ?>
<?php if (!$account_status['enabled']): ?><p><a class="link" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">계정 설정·인증 확인·발송 허용</a></p><?php endif ?>
<p><a class="link" href="https://www.bizppurio.com/" target="_blank" rel="noopener noreferrer">비즈뿌리오 사이트에서 웹발송 (새 창)</a></p><small>비즈뿌리오 사이트에서 직접 발송하려면 해당 사이트에 로그인해 메시지전송 메뉴를 이용하세요. 사이트 발송 이력은 GNUCMS에 자동으로 가져오지 않습니다.</small></section><?php endif ?>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($page === 'templates'): $this->insert('admin/messaging/templates'); ?>
<?php elseif ($page === 'send'): $this->insert('admin/messaging/send'); ?>
<?php elseif ($page === 'detail'): $this->insert('admin/messaging/detail'); ?>
<?php else: ?>
<?php $this->insert('admin/messaging/history') ?>
<?php endif ?><p class="muted">전체 관리자 전용 · 국내 단건 알림톡 · 표시 시각: 한국 시간</p>
<?php $this->stop() ?>
