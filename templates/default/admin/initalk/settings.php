<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>설정 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $notice = isset($query['saved']) ? '설정을 저장했습니다.' : (isset($query['purged']) ? '보관 기간이 지난 ' . (int) $query['purged'] . '건의 구매자명·번호를 정리했습니다.' : '');
$v = static fn (string $key): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : ''; ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'settings', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<section class="card card-body extension-panel"><h2 class="card-title">이니톡 결제 설정</h2>
<p>알림톡 계정은 <a class="link" href="<?= $this->url('admin.settings.messaging') ?>">설정 → 알림톡·문자</a>, 이니시스 상점은 <a class="link" href="<?= $this->url('admin.settings.payment') ?>">설정 → 결제</a>에서 먼저 준비합니다. 템플릿은 <a class="link" href="<?= $this->url('admin.messaging.templates') ?>">메시지 발송 → 템플릿</a>에서 가져온 것 중 고릅니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.settings') ?>" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="store_name">상점명 (알림톡·결제 페이지 표시)</label><input class="input input-bordered input-block" id="store_name" name="store_name" maxlength="40" required value="<?= $this->e($v('store_name')) ?>">
<label class="extension-label" for="support_phone">고객센터 번호 (선택)</label><input class="input input-bordered input-block" id="support_phone" name="support_phone" maxlength="20" value="<?= $this->e($v('support_phone')) ?>" placeholder="1588-1234">
<label class="extension-label" for="expiry_hours">기본 결제기한 (시간, 1~720)</label><input class="input input-bordered input-block" id="expiry_hours" name="expiry_hours" inputmode="numeric" maxlength="3" required value="<?= $this->e($v('expiry_hours')) ?>">
<label class="extension-label" for="environment">사용 환경</label><select class="select select-bordered select-block" id="environment" name="environment"><option value="test"<?= $v('environment') === 'test' ? ' selected' : '' ?>>테스트 (알림톡 테스트 계정 · 이니시스 테스트 상점)</option><option value="live"<?= $v('environment') === 'live' ? ' selected' : '' ?>>운영</option></select>
<?php foreach (['test' => '테스트', 'live' => '운영'] as $env => $label): ?>
<label class="extension-label" for="template_<?= $env ?>"><?= $label ?> 환경 알림톡 템플릿</label><select class="select select-bordered select-block" id="template_<?= $env ?>" name="template_<?= $env ?>"><option value="">선택 안 함</option><?php foreach ($templates[$env] as $option): ?><option value="<?= $this->e($option['id']) ?>"<?= $v('template_' . $env) === $option['id'] ? ' selected' : '' ?>><?= $this->e($option['name']) ?> (<?= $this->e($option['code']) ?>)</option><?php endforeach ?></select>
<?php endforeach ?>
<p><small>템플릿 본문에는 #{상점명} #{구매자명} #{요청일} #{상품명} #{금액} #{결제기한} #{고객센터} #{주문번호} 변수를, 웹링크 버튼에는 결제 링크 <?= $this->e(rtrim((string) $site_url, '/')) ?>/pay/#{결제토큰}을 쓸 수 있습니다.</small></p>
<label class="extension-label" for="settlement_days">정산 주기 (승인일 + N일, 0~60)</label><input class="input input-bordered input-block" id="settlement_days" name="settlement_days" inputmode="numeric" maxlength="2" required value="<?= $this->e($v('settlement_days')) ?>">
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">설정 저장</button></div></form></section>
<section class="card card-body extension-panel"><h2 class="card-title">보관 만료 개인정보 정리</h2><p>결제 완료·환불·만료·취소된 지 90일이 지난 요청의 구매자명과 휴대폰번호를 최대 100건씩 지웁니다. 주문번호·금액·원장은 남습니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.purge') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">개인정보 정리 실행</button></form></section>
<?php $this->stop() ?>
