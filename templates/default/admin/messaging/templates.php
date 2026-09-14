<?php $this->insert('admin/messaging/_remote_templates') ?>
<?php $locked = ($selected['source'] ?? '') === 'kapi'; $form = $selected ?? []; if ($errors !== []) {
    foreach (['id','revision','code','name','message','enabled'] as $key) if (is_string($values[$key] ?? null)) $form[$key] = $values[$key];
    if (is_array($values['buttons'] ?? null)) {
        $form['buttons'] = [];
        foreach (array_slice(array_values($values['buttons']), 0, 5) as $row) {
            $button = [];
            foreach (['name','url_mobile','url_pc'] as $key) $button[$key] = is_array($row) && is_string($row[$key] ?? null) ? $row[$key] : '';
            $form['buttons'][] = $button;
        }
    }
} ?>
<section class="card card-body extension-panel"><h2 class="card-title">로컬 템플릿 설정</h2>
<?php if ($locked): ?>
<p>비즈뿌리오에서 가져온 템플릿 · 마지막 확인: <?= $this->e($time($selected['remote']['checked_at'])) ?> · 검수: <?= $this->e($selected['remote']['inspection']) ?> · <?= $selected['enabled'] ? '사용' : '사용 안 함' ?></p>
<?php if ($selected['remote']['reason'] !== ''): ?><p class="alert alert-warning"><?= $this->e($selected['remote']['reason']) ?></p><?php endif ?>
<p>본문과 버튼은 비즈뿌리오에서 수정한 뒤 다시 가져오세요. 아래에서는 사용 여부를 변경할 수 있습니다. 발송 시 원격 상태를 실시간 조회하지 않으므로 변경 후 갱신해 주세요.</p>
<form method="post" action="<?= $this->url('admin.messaging.templates') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><input type="hidden" name="remote_code" value="<?= $this->e($selected['code']) ?>"><button class="btn btn-outline" name="action" value="remote-detail">비즈뿌리오 최신 상태 확인</button></form>
<?php endif ?><p>수동 등록 시 비즈뿌리오에서 승인된 기본 텍스트형 본문과 웹링크 버튼을 그대로 입력합니다. 이 화면의 저장은 카카오 검수 신청이 아닙니다.</p>
<nav><a class="link" href="?environment=<?= $this->e($environment) ?>">새 템플릿</a><?php foreach ($templates as $item): ?><a class="link" href="?environment=<?= $this->e($environment) ?>&amp;id=<?= $this->e($item['id']) ?>"><?= $this->e($item['name']) ?></a><?php endforeach ?></nav>
<form method="post" action="<?= $this->url('admin.messaging.templates') ?>">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><input type="hidden" name="id" value="<?= $this->e($form['id'] ?? '') ?>"><input type="hidden" name="revision" value="<?= $this->e($form['revision'] ?? '') ?>">
<label class="extension-label" for="name">템플릿 이름</label><input class="input input-bordered input-block" id="name" name="name"<?= $locked ? ' readonly' : '' ?> required maxlength="100" value="<?= $this->e($form['name'] ?? '') ?>">
<label class="extension-label" for="code">승인 템플릿 코드</label><input class="input input-bordered input-block" id="code" name="code"<?= $locked ? ' readonly' : '' ?> required maxlength="30" value="<?= $this->e($form['code'] ?? '') ?>">
<label class="extension-label" for="message">승인된 본문</label><textarea class="textarea textarea-bordered textarea-block" id="message" name="message"<?= $locked ? ' readonly' : '' ?> required maxlength="1000" rows="7"><?= $this->e($form['message'] ?? '') ?></textarea><small>변수는 #{이름} 형식입니다. 본문과 변수 치환 후 본문 모두 1,000자 이내입니다.</small>
<?php for ($i = 0; $i < 5; $i++): $button = $form['buttons'][$i] ?? []; ?>
<fieldset class="extension-fieldset"><legend class="fieldset-legend">웹링크 버튼 <?= $i + 1 ?> (선택)</legend><label class="extension-label" for="button-name-<?= $i ?>">승인된 버튼 이름</label><input class="input input-bordered input-block" id="button-name-<?= $i ?>" name="buttons[<?= $i ?>][name]"<?= $locked ? ' readonly' : '' ?> maxlength="28" value="<?= $this->e($button['name'] ?? '') ?>">
<label class="extension-label" for="button-mobile-<?= $i ?>">모바일 URL</label><input class="input input-bordered input-block" id="button-mobile-<?= $i ?>" name="buttons[<?= $i ?>][url_mobile]"<?= $locked ? ' readonly' : '' ?> maxlength="500" value="<?= $this->e($button['url_mobile'] ?? '') ?>">
<label class="extension-label" for="button-pc-<?= $i ?>">PC URL (선택)</label><input class="input input-bordered input-block" id="button-pc-<?= $i ?>" name="buttons[<?= $i ?>][url_pc]"<?= $locked ? ' readonly' : '' ?> maxlength="500" value="<?= $this->e($button['url_pc'] ?? '') ?>"></fieldset>
<?php endfor ?><label class="extension-label" for="enabled">사용 여부</label><select class="select select-bordered select-block" id="enabled" name="enabled"><option value="1"<?= !isset($form['enabled']) || $form['enabled'] ? ' selected' : '' ?>>사용</option><option value="0"<?= isset($form['enabled']) && !$form['enabled'] ? ' selected' : '' ?>>사용 안 함</option></select><br><div class="card-actions form-actions"><button class="btn btn-primary" name="action" value="save">로컬 템플릿 저장</button></div>
</form></section>
