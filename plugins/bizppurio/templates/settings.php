<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>비즈뿌리오 알림톡 설정</title><?php $this->insert('_style') ?></head><body><main>
<nav><a href="<?= $this->e($base) ?>/admin/plugins">플러그인 관리</a><a href="<?= $this->e($base) ?>/admin/modules">모듈 관리</a><a href="<?= $this->e($base) ?>/modules/alimtalk/home?environment=<?= $this->e($environment) ?>">알림톡 운영</a></nav>
<h1>비즈뿌리오 알림톡 설정</h1>
<nav><a href="?environment=test">검수 환경</a><a href="?environment=live">운영 환경</a></nav>
<span class="badge"><?= $environment === 'live' ? '운영' : '검수' ?> · <?= $settings['enabled'] ? '발송 허용' : '발송 정지' ?></span>
<?php if ($notice !== ''): ?><p class="notice" role="status"><?= $this->e($notice) ?></p><?php endif ?>
<?php foreach ($errors as $error): ?><p class="errors" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if (!$ready): ?>
<section><h2>데이터 준비</h2><p>처음 사용하거나 패키지를 갱신했다면 알림톡 데이터를 설치·갱신해 주세요. 기존 발송 데이터는 보존합니다.</p>
<form method="post" action="<?= $this->e($base) ?>/plugins/bizppurio/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><button name="action" value="install">데이터 설치·갱신</button></form></section>
<?php else: ?>
<section><h2>계정과 발신프로필</h2><p>비즈뿌리오에 등록한 계정 유형과 승인된 발신프로필을 입력해 주세요. 설정 저장 시 인증 확인과 발송 허용이 해제됩니다.</p>
<form method="post" action="<?= $this->e($base) ?>/plugins/bizppurio/settings" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<label for="account_type">비즈뿌리오 계정 유형</label><select id="account_type" name="account_type"><option value="module"<?= ($settings['account_type'] ?? 'module') === 'module' ? ' selected' : '' ?>>모듈 연동 계정</option><option value="web"<?= ($settings['account_type'] ?? '') === 'web' ? ' selected' : '' ?>>웹발송 계정</option></select>
<p><small>GNUCMS에서 발송하려면 계정의 REST API 사용 권한이 필요합니다. 웹발송 계정은 저장 후 인증 연결을 확인해야 발송을 허용할 수 있습니다. 인증 성공 후에도 알림톡 사용 승인과 실제 수신 확인이 필요합니다.</small></p>
<label for="account">비즈뿌리오 계정 ID</label><input id="account" name="account" maxlength="20" required value="<?= $this->e($settings['account'] ?? '') ?>">
<label for="password">API 연동용 모듈 비밀번호</label><div class="password-field"><input type="password" id="password" name="password" maxlength="500" autocomplete="new-password" aria-describedby="password-help" placeholder="<?= $settings['configured'] ? '••••••••••••' : '' ?>">
<button type="button" class="password-toggle" id="password-toggle" hidden aria-controls="password" aria-pressed="false" aria-label="비밀번호 표시" title="비밀번호 표시" data-password-set="<?= $settings['configured'] ? '1' : '0' ?>" data-revision="<?= $this->e($settings['revision'] ?? '') ?>">
<svg class="password-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.9M6.1 6.1A22 22 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5-1.2M10 10a3 3 0 0 0 4 4"/></svg>
</button></div><small><?= $settings['configured'] ? '저장되어 있습니다. 눈 아이콘으로 확인하거나 변경할 비밀번호를 입력하세요.' : '처음 저장할 때는 비밀번호가 필요합니다.' ?></small>
<p id="password-error" class="errors" role="alert" hidden></p>
<p id="password-help"><small>홈페이지 로그인 비밀번호와 별도입니다. 발송 계정으로 비즈뿌리오에 로그인한 뒤 비즈라운지 → 모듈연동 환경설정에서 확인·변경해 주세요. 해당 메뉴가 없는 웹발송 계정은 REST API 사용 가능 여부와 인증 정보 발급을 업체에 문의해 주세요. <a href="https://bizmessage.zendesk.com/hc/ko/articles/8091755754127" target="_blank" rel="noopener noreferrer">공식 인증 오류 안내</a></small></p>
<label for="senderkey">발신프로필 키</label><div class="password-field"><input type="password" id="senderkey" name="senderkey" maxlength="40" required autocomplete="off" spellcheck="false" value="<?= $this->e($settings['senderkey'] ?? '') ?>">
<button type="button" class="password-toggle" id="senderkey-toggle" hidden aria-controls="senderkey" aria-pressed="false" aria-label="발신프로필 키 표시" title="발신프로필 키 표시">
<svg class="password-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="password-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3 3.9M6.1 6.1A22 22 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5-1.2M10 10a3 3 0 0 0 4 4"/></svg>
</button></div>
<label for="from">발신번호</label><input id="from" name="from" inputmode="numeric" maxlength="16" required value="<?= $this->e($settings['from'] ?? '') ?>">
<label for="test_phone">테스트 수신번호</label><input id="test_phone" name="test_phone" inputmode="tel" required value="<?= $this->e($settings['test_phone'] ?? '') ?>">
<label for="test_only">발송 대상 범위</label><select id="test_only" name="test_only"><option value="1"<?= ($settings['test_only'] ?? true) ? ' selected' : '' ?>>테스트 번호만</option><option value="0"<?= !($settings['test_only'] ?? true) ? ' selected' : '' ?>>입력한 국내 휴대폰 번호</option></select>
<label for="webhook_ips">업체가 확인한 결과 송신 IP (선택)</label><textarea id="webhook_ips" name="webhook_ips" rows="2"><?= $this->e(implode(' ', $settings['webhook_ips'] ?? [])) ?></textarea><small>공백으로 구분합니다. 프록시를 사용하는 서버는 README의 IP 처리 안내를 확인해 주세요.</small><br>
<button name="action" value="save">설정 저장</button></form></section>
<?php if ($settings['configured']): ?><section><h2>연결과 발송 상태</h2><p>API 인증: <strong><?= !empty($settings['api_verified']) ? '확인 완료' : '확인 필요' ?></strong></p><p>인증 연결 확인은 발송을 정지하고 이 환경에 저장된 계정 ID와 모듈 비밀번호로 다시 인증합니다. 입력값을 바꿨다면 먼저 설정을 저장해 주세요. 발신프로필 키는 토큰 인증에 사용하지 않으며 메시지는 보내지 않습니다.</p>
<form method="post" action="<?= $this->e($base) ?>/plugins/bizppurio/settings"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<button name="action" value="connect">인증 연결 확인</button><button name="action" value="webhook" class="secondary">결과 수신 경로 표시</button>
<?php if ($settings['enabled']): ?><button name="action" value="disable" class="secondary">신규 발송 정지</button><?php else: ?><button name="action" value="enable"<?= ($settings['account_type'] ?? '') === 'web' && empty($settings['api_verified']) ? ' disabled' : '' ?>>이 환경의 발송 허용</button><?php endif ?></form>
<p><a href="<?= $this->e($base) ?>/modules/alimtalk/send?environment=<?= $this->e($environment) ?>">GNUCMS 웹발송 화면 열기</a></p>
<?php if ($webhook !== null): ?><label for="webhook">비즈뿌리오에 등록할 결과 수신 경로</label><textarea id="webhook" rows="4" readonly><?= $this->e($webhook) ?></textarea><small>인증값이 포함된 경로입니다. 업체 등록용으로만 사용하고 공개하지 마세요.</small><?php endif ?>
</section><?php endif ?>
<?php endif ?><section><h2>비즈뿌리오 사이트 웹발송</h2><p>웹발송 계정으로 비즈뿌리오 사이트에 로그인한 뒤 메시지전송 메뉴에서 발송할 수 있습니다. 사이트에서 보낸 내역은 GNUCMS 발송 이력에 자동으로 가져오지 않습니다.</p><a href="https://www.bizppurio.com/" target="_blank" rel="noopener noreferrer">비즈뿌리오 사이트 열기 (새 창)</a></section>
<p class="muted">단건 알림톡 · 문자 대체 발송 없음 · 발송 정지 중에도 결과 수신 유지</p>
</main><?php $this->insert('_password_toggle') ?></body></html>
