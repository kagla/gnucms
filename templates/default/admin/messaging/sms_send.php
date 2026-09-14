<?php $value = static fn (string $key, string $default = ''): string => is_string($values[$key] ?? null) ? $values[$key] : $default; ?>
<section class="card card-body extension-panel"><h2 class="card-title">문자 작성</h2>
<form method="post" action="<?= $this->url('admin.messaging.sms.send') ?>">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
<label class="extension-label" for="type">문자 유형</label><select class="select select-bordered select-block" id="type" name="type"><option value="sms"<?= $value('type', 'sms') === 'sms' ? ' selected' : '' ?>>SMS (단문 · 90바이트)</option><option value="lms"<?= $value('type') === 'lms' ? ' selected' : '' ?>>LMS (장문 · 2,000바이트)</option></select>
<label class="extension-label" for="phone">수신 휴대폰 번호</label><input class="input input-bordered input-block" type="tel" id="phone" name="phone" data-sms-phone inputmode="tel" autocomplete="off" maxlength="20" placeholder="010-1234-5678" required value="<?= $this->e($value('phone')) ?>">
<label class="extension-label" for="subject">제목 (LMS만 사용 · 선택)</label><input class="input input-bordered input-block" id="subject" name="subject" maxlength="64" value="<?= $this->e($value('subject')) ?>"><small>LMS 제목은 EUC-KR 기준 64바이트까지 입력할 수 있습니다. SMS는 제목을 비워 주세요.</small>
<label class="extension-label" for="message">문자 본문</label><textarea class="textarea textarea-bordered textarea-block" id="message" name="message" rows="8" maxlength="2000" required aria-describedby="sms-length-help"><?= $this->e($value('message')) ?></textarea>
<p id="sms-length-help"><small>한글은 보통 2바이트, 영문·숫자는 1바이트입니다. 특수문자 그림도 입력할 수 있습니다. 미리보기에서 길이를 확인하고, 길이가 초과되면 유형을 직접 변경해 주세요.</small></p>
<div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="preview">내용 미리보기</button></div></form></section>
<?php if ($preview !== null): ?><section class="card card-body extension-panel"><h2 class="card-title">최종 발송 내용</h2>
<p><strong><?= $environment === 'live' ? '운영' : '테스트' ?> · <?= $this->e(strtoupper($preview['type'])) ?></strong> · <?php if ($preview['bytes'] === null): ?>본문 길이: 업체 확인 필요 (한도 <?= (int) $preview['limit'] ?>바이트)<?php else: ?><?= (int) $preview['bytes'] ?> / <?= (int) $preview['limit'] ?>바이트<?php endif ?></p>
<p>발신번호 <?= $this->e($preview['from']) ?> → 수신번호 <?= $this->e($preview['phone']) ?></p>
<?php if ($preview['subject'] !== ''): ?><h3 class="form-section-title"><?= $this->e($preview['subject']) ?></h3><small><?php if ($preview['subject_bytes'] === null): ?>제목 길이: 업체 확인 필요 (한도 64바이트)<?php else: ?>제목 <?= (int) $preview['subject_bytes'] ?> / 64바이트<?php endif ?></small><?php endif ?>
<pre><?= $this->e($preview['message']) ?></pre>
<?php if ($preview['bytes'] === null || $preview['subject_bytes'] === null): ?><p class="alert alert-info">특수문자가 포함되어 전체 바이트 수를 확정할 수 없습니다. 입력한 원문으로 발송을 요청하며, 실제 길이와 수신 화면의 문자 표시는 비즈뿌리오·통신사 처리 결과에 따릅니다.</p><?php endif ?>
<p>확인한 내용으로 비즈뿌리오에 실제 발송을 요청합니다. <?php if ($environment === 'test'): ?>테스트 환경의 수신·과금 조건은 계정 계약을 따릅니다. <?php endif ?>미리보기는 10분 동안 유효합니다.</p>
<?php if (!$account_status['enabled']): ?><p class="errors alert alert-error">현재 발송이 정지되어 있습니다. 설정에서 발송을 허용해 주세요.</p><?php endif ?>
<form method="post" action="<?= $this->url('admin.messaging.sms.send') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><input type="hidden" name="confirmation" value="<?= $this->e($confirmation) ?>"><button class="btn btn-primary" name="action" value="send"<?= !$account_status['enabled'] ? ' disabled' : '' ?>>확인한 번호로 문자 발송</button></form></section><?php endif ?>
