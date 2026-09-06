<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>알림톡 운영 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '알림톡 운영', 'description' => '승인 템플릿으로 발송하고 접수·도달 결과를 확인합니다.', 'actions' => [['url' => $base . '/plugins/bizppurio/settings?environment=' . $environment, 'label' => '플러그인 설정']]]) ?>
<div class="extension-toolbar"><span class="badge badge-soft"><?= $environment === 'live' ? '운영 환경' : '검수 환경' ?></span>
<form method="get" action="<?= $this->e($base) ?>/modules/alimtalk/<?= $page === 'detail' ? 'history' : $this->e($page) ?>" class="row">
<label class="sr-only" for="alimtalk-environment">발송 환경</label><select class="select select-bordered select-block" id="alimtalk-environment" name="environment"><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>검수 환경</option><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영 환경</option></select><button class="secondary btn">환경 보기</button></form></div>
<nav class="tabs tabs-border settings-tabs" aria-label="알림톡 운영 메뉴"><?php foreach (['home' => '요약', 'templates' => '템플릿', 'send' => '웹발송', 'history' => '발송 이력'] as $path => $label): $active = $page === $path || ($page === 'detail' && $path === 'history'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->e($base) ?>/modules/alimtalk/<?= $this->e($path) ?>?environment=<?= $this->e($environment) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<?php if (in_array($page, ['home', 'send'], true)): ?><section class="card card-body extension-panel"><h2 class="card-title">GNUCMS 웹발송</h2><p>승인 템플릿 선택 → 수신번호·변수 입력 → 미리보기 → 발송 → 결과 확인</p>
<?php if ($account_status['configured']): ?><p>계정: <strong><?= $this->e($account_status['account']) ?></strong> · <?= $account_status['account_type'] === 'web' ? '웹발송 계정' : '모듈 연동 계정' ?> · <?= $account_status['enabled'] ? '발송 허용' : '발송 정지' ?></p>
<p>API 인증: <?= $account_status['api_verified'] ? '확인 완료' : '별도 확인 전' ?><?php if ($account_status['test_only']): ?> · 발송 대상: 테스트 번호 <?= $this->e($account_status['test_phone']) ?><?php endif ?></p>
<?php else: ?><p>발송할 계정을 먼저 설정해 주세요. 웹발송 계정도 API 인증 확인 후 사용할 수 있으며, 실제 알림톡 발송 권한은 별도로 필요합니다.</p><?php endif ?>
<?php if (!$account_status['enabled']): ?><p><a class="link" href="<?= $this->e($base) ?>/plugins/bizppurio/settings?environment=<?= $this->e($environment) ?>">계정 설정·인증 확인·발송 허용</a></p><?php endif ?>
<p><a class="link" href="https://www.bizppurio.com/" target="_blank" rel="noopener noreferrer">비즈뿌리오 사이트에서 웹발송 (새 창)</a></p><small>비즈뿌리오 사이트에서 직접 발송하려면 해당 사이트에 로그인해 메시지전송 메뉴를 이용하세요. 사이트 발송 이력은 GNUCMS에 자동으로 가져오지 않습니다.</small></section><?php endif ?>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if (!$ready): ?><section class="card card-body extension-panel"><h2 class="card-title">사용 준비가 필요합니다</h2><p>플러그인 설정에서 데이터를 설치·갱신한 뒤 계정과 발신프로필을 저장해 주세요.</p></section>
<?php elseif ($page === 'templates'): $this->insert('templates'); ?>
<?php elseif ($page === 'send'): $this->insert('send'); ?>
<?php elseif ($page === 'detail'): $this->insert('detail'); ?>
<?php else: ?>
<?php if ($page === 'home'): ?><section class="card card-body extension-panel"><h2 class="card-title">최근 발송</h2><p>승인된 템플릿 <?= count($templates) ?>개가 로컬에 등록되어 있습니다. 템플릿의 원격 승인 상태는 비즈뿌리오에서 확인해 주세요.</p><p>접수됨은 업체가 요청을 받은 상태입니다. 도달 결과를 함께 확인해 주세요.</p></section><?php endif ?>
<?php $this->insert('history') ?>
<?php endif ?><p class="muted">전체 관리자 전용 · 국내 단건 알림톡 · 표시 시각: 한국 시간</p>
<?php $this->stop() ?>
