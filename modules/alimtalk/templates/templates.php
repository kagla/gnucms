<?php $form = $selected ?? []; if ($errors !== []) {
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
<section><h2>승인 템플릿 설정</h2><p>비즈뿌리오에서 승인된 기본 텍스트형 본문과 웹링크 버튼을 그대로 입력합니다. 이 화면의 저장은 카카오 검수 신청이 아닙니다.</p>
<nav><a href="?environment=<?= $this->e($environment) ?>">새 템플릿</a><?php foreach ($templates as $item): ?><a href="?environment=<?= $this->e($environment) ?>&amp;id=<?= $this->e($item['id']) ?>"><?= $this->e($item['name']) ?></a><?php endforeach ?></nav>
<form method="post" action="<?= $this->e($base) ?>/modules/alimtalk/templates">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>"><input type="hidden" name="id" value="<?= $this->e($form['id'] ?? '') ?>"><input type="hidden" name="revision" value="<?= $this->e($form['revision'] ?? '') ?>">
<label for="name">템플릿 이름</label><input id="name" name="name" required maxlength="100" value="<?= $this->e($form['name'] ?? '') ?>">
<label for="code">승인 템플릿 코드</label><input id="code" name="code" required maxlength="30" value="<?= $this->e($form['code'] ?? '') ?>">
<label for="message">승인된 본문</label><textarea id="message" name="message" required maxlength="1000" rows="7"><?= $this->e($form['message'] ?? '') ?></textarea><small>변수는 #{이름} 형식입니다. 본문과 변수 치환 후 본문 모두 1,000자 이내입니다.</small>
<?php for ($i = 0; $i < 5; $i++): $button = $form['buttons'][$i] ?? []; ?>
<fieldset><legend>웹링크 버튼 <?= $i + 1 ?> (선택)</legend><label for="button-name-<?= $i ?>">승인된 버튼 이름</label><input id="button-name-<?= $i ?>" name="buttons[<?= $i ?>][name]" maxlength="28" value="<?= $this->e($button['name'] ?? '') ?>">
<label for="button-mobile-<?= $i ?>">모바일 URL</label><input id="button-mobile-<?= $i ?>" name="buttons[<?= $i ?>][url_mobile]" maxlength="500" value="<?= $this->e($button['url_mobile'] ?? '') ?>">
<label for="button-pc-<?= $i ?>">PC URL (선택)</label><input id="button-pc-<?= $i ?>" name="buttons[<?= $i ?>][url_pc]" maxlength="500" value="<?= $this->e($button['url_pc'] ?? '') ?>"></fieldset>
<?php endfor ?><label for="enabled">사용 여부</label><select id="enabled" name="enabled"><option value="1"<?= !isset($form['enabled']) || $form['enabled'] ? ' selected' : '' ?>>사용</option><option value="0"<?= isset($form['enabled']) && !$form['enabled'] ? ' selected' : '' ?>>사용 안 함</option></select><br><button name="action" value="save">로컬 템플릿 저장</button>
</form></section>
