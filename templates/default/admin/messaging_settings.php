<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>알림톡·문자 설정 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>site<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li><a href="<?= $this->url('admin.settings') ?>">설정</a></li><li aria-current="page">알림톡·문자</li></ul></div>
<?php $this->insert('admin/_settings_tabs', ['active' => 'messaging']) ?>
<div class="page-head"><div><h1>알림톡·문자 설정</h1><p class="muted">비즈뿌리오 계정과 환경별 발송 허용을 관리합니다. 이니톡 결제 알림톡과 메시지 발송 화면이 이 설정을 사용합니다.</p></div>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.messaging.templates') ?>?environment=<?= $this->e($environment) ?>">알림톡 운영</a><a class="btn btn-sm" href="<?= $this->url('admin.messaging.sms.send') ?>?environment=<?= $this->e($environment) ?>">문자 발송</a></div></div>
<nav class="tabs tabs-border settings-tabs" aria-label="메시지 계정 환경">
<?php foreach (['test' => '테스트 환경', 'live' => '운영 환경'] as $env => $label): ?><a class="tab<?= $environment === $env ? ' tab-active' : '' ?>" href="?environment=<?= $env ?>"<?= $environment === $env ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?>
</nav>
<span class="badge badge-soft"><?= $environment === 'live' ? '운영' : '테스트' ?> · <?= $settings['enabled'] ? '발송 허용' : '발송 정지' ?></span>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<section class="card card-body extension-panel"><h2 class="card-title">계정과 발신번호</h2><p>비즈뿌리오 계정과 사전 등록한 발신번호를 입력해 주세요. 알림톡을 함께 사용할 때는 승인된 발신프로필도 입력해 주세요. 설정 저장 시 인증 확인과 발송 허용이 해제됩니다.</p>
<form method="post" action="<?= $this->url('admin.settings.messaging') ?>" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<label class="extension-label" for="account_type">비즈뿌리오 계정 유형</label><select class="select select-bordered select-block" id="account_type" name="account_type"><option value="module"<?= ($settings['account_type'] ?? 'module') === 'module' ? ' selected' : '' ?>>모듈 연동 계정</option><option value="web"<?= ($settings['account_type'] ?? '') === 'web' ? ' selected' : '' ?>>웹발송 계정</option></select>
<p><small>GNUCMS에서 발송하려면 계정의 REST API 사용 권한이 필요합니다. 웹발송 계정은 저장 후 인증 연결을 확인해야 발송을 허용할 수 있습니다. 인증 성공 후에도 사용할 메시지 서비스의 권한과 실제 수신 확인이 필요합니다.</small></p>
<label class="extension-label" for="account">비즈뿌리오 계정 ID</label><input class="input input-bordered input-block" id="account" name="account" maxlength="20" required value="<?= $this->e($settings['account'] ?? '') ?>">
<label class="extension-label" for="password">API 연동용 모듈 비밀번호</label><div class="password-field"><input class="input input-bordered input-block" type="password" id="password" name="password" maxlength="500" autocomplete="new-password" aria-describedby="password-help" placeholder="<?= $settings['configured'] ? '••••••••••••' : '' ?>">
<button type="button" class="password-toggle pw-toggle" id="password-toggle" hidden aria-controls="password" aria-pressed="false" aria-label="비밀번호 표시" title="비밀번호 표시" data-password-set="<?= $settings['configured'] ? '1' : '0' ?>" data-revision="<?= $this->e($settings['revision'] ?? '') ?>">
<svg class="password-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.9M6.1 6.1A22 22 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5-1.2M10 10a3 3 0 0 0 4 4"/></svg>
</button></div><small><?= $settings['configured'] ? '저장되어 있습니다. 눈 아이콘으로 확인하거나 변경할 비밀번호를 입력하세요.' : '처음 저장할 때는 비밀번호가 필요합니다.' ?></small>
<p id="password-error" class="errors alert alert-error" role="alert" hidden></p>
<p id="password-help"><small>홈페이지 로그인 비밀번호와 별도입니다. 발송 계정으로 비즈뿌리오에 로그인한 뒤 비즈라운지 → 모듈연동 환경설정에서 확인·변경해 주세요. 해당 메뉴가 없는 웹발송 계정은 REST API 사용 가능 여부와 인증 정보 발급을 업체에 문의해 주세요. <a class="link" href="https://bizmessage.zendesk.com/hc/ko/articles/8091755754127" target="_blank" rel="noopener noreferrer">공식 인증 오류 안내</a></small></p>
<label class="extension-label" for="kapi_key">템플릿 조회용 KAPI API Key (선택)</label>
<input class="input input-bordered input-block" type="password" id="kapi_key" name="kapi_key" maxlength="500" autocomplete="new-password" aria-describedby="kapi-help" value="">
<p id="kapi-help"><small><?= !empty($settings['kapi_configured']) ? '저장되어 있습니다. 비워 두면 기존 키를 유지합니다.' : '저장된 키가 없습니다.' ?> 비즈뿌리오에서 위 계정의 KAPI 사용 권한과 API Key를 발급받아 입력하세요. 모듈 비밀번호와 별도이며, 템플릿 조회에 사용합니다. 테스트·운영 모두 비즈뿌리오의 공통 KAPI 서버에 연결합니다.</small></p>
<?php if (!empty($settings['kapi_configured'])): ?><label><input type="checkbox" name="clear_kapi_key" value="1"> 저장된 KAPI 키 삭제</label><?php endif ?>
<label class="extension-label" for="senderkey">카카오 발신프로필 키 (문자만 사용하면 생략)</label><div class="password-field"><input class="input input-bordered input-block" type="password" id="senderkey" name="senderkey" maxlength="40" autocomplete="off" spellcheck="false" value="<?= $this->e($settings['senderkey'] ?? '') ?>">
<button type="button" class="password-toggle pw-toggle" id="senderkey-toggle" hidden aria-controls="senderkey" aria-pressed="false" aria-label="발신프로필 키 표시" title="발신프로필 키 표시">
<svg class="password-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.9M6.1 6.1A22 22 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5-1.2M10 10a3 3 0 0 0 4 4"/></svg>
</button></div>
<label class="extension-label" for="from">발신번호</label><input class="input input-bordered input-block" type="tel" id="from" name="from" data-phone-format="domestic" inputmode="tel" maxlength="20" required value="<?= $this->e($settings['from'] ?? '') ?>">
<?php if ($environment === 'test'): ?>
<label class="extension-label" for="test_phone">테스트 수신번호</label><input class="input input-bordered input-block" type="tel" id="test_phone" name="test_phone" data-phone-format="mobile" inputmode="tel" maxlength="20" placeholder="010-1234-5678" required value="<?= $this->e($settings['test_phone'] ?? '') ?>">
<p><small>테스트 환경에서는 위 번호로만 발송을 요청할 수 있습니다.</small></p>
<?php else: ?><p><strong>발송 대상: 입력한 국내 휴대폰 번호</strong></p><?php endif ?>
<label class="extension-label" for="webhook_ips">업체가 확인한 결과 송신 IP (선택)</label><input class="input input-bordered input-block" type="text" id="webhook_ips" name="webhook_ips" maxlength="1000" style="width:min(100%,24rem)" value="<?= $this->e(implode(' ', $settings['webhook_ips'] ?? [])) ?>"><small>공백으로 구분합니다. 프록시를 사용하는 서버는 README의 IP 처리 안내를 확인해 주세요.</small><br>
<div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="save">설정 저장</button></div></form></section>
<?php if ($settings['configured']): ?><section class="card card-body extension-panel"><h2 class="card-title">연결과 발송 상태</h2><p>API 연결 확인: <strong><?= !empty($settings['api_verified']) ? '확인 완료' : '확인 전' ?></strong></p><p>인증 연결 확인은 발송을 정지하고 이 환경에 저장된 계정 ID와 모듈 비밀번호로 다시 인증합니다. 입력값을 바꿨다면 먼저 설정을 저장해 주세요. 발신프로필 키는 토큰 인증에 사용하지 않으며 메시지는 보내지 않습니다.</p>
<form method="post" action="<?= $this->url('admin.settings.messaging') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<button class="btn btn-primary" name="action" value="connect">인증 연결 확인</button><button name="action" value="webhook" class="secondary btn">결과 수신 경로 표시</button>
<?php if ($settings['enabled']): ?><button name="action" value="disable" class="secondary btn">신규 발송 정지</button><?php else: ?><button class="btn btn-primary" name="action" value="enable"<?= ($settings['account_type'] ?? '') === 'web' && empty($settings['api_verified']) ? ' disabled' : '' ?>>이 환경의 발송 허용</button><?php endif ?></form>
<p><a class="link" href="<?= $this->url('admin.messaging.send') ?>?environment=<?= $this->e($environment) ?>">알림톡 발송 화면 열기</a> · <a class="link" href="<?= $this->url('admin.messaging.sms.send') ?>?environment=<?= $this->e($environment) ?>">문자 발송 화면 열기</a></p>
<?php if ($webhook !== null): ?><label class="extension-label" for="webhook">비즈뿌리오에 등록할 결과 수신 경로</label><textarea class="textarea textarea-bordered textarea-block" id="webhook" rows="4" readonly><?= $this->e($webhook) ?></textarea><small>인증값이 포함된 경로입니다. 업체 등록용으로만 사용하고 공개하지 마세요.</small><?php endif ?>
</section><?php endif ?>
<section class="card card-body extension-panel"><h2 class="card-title">비즈뿌리오 사이트 웹발송</h2><p>웹발송 계정으로 비즈뿌리오 사이트에 로그인한 뒤 메시지전송 메뉴에서 발송할 수 있습니다. 사이트에서 보낸 내역은 GNUCMS 발송 이력에 자동으로 가져오지 않습니다.</p><a class="link" href="https://www.bizppurio.com/" target="_blank" rel="noopener noreferrer">비즈뿌리오 사이트 열기 (새 창)</a></section>
<p class="muted">단건 알림톡·SMS·LMS · 두 모듈의 발송 허용·정지 설정 공유 · 발송 정지 중에도 결과 수신 유지</p>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><?php $this->insert('admin/_password_toggle') ?><?php $this->insert('admin/_phone_input') ?><?php $this->stop() ?>
