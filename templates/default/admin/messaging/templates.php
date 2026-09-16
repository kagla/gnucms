<?php
// 비즈뿌리오 목록과 GNUCMS 사본은 같은 템플릿이므로 한 표에 합친다. 조회한 페이지의 원격 행 + 그 페이지에 없는 로컬 행 순서다.
// 본문·버튼을 직접 입력하는 경로는 없다. API 키로 가져온 승인 본문만 발송할 수 있다.
$apiKey = !empty($account_status['kapi_configured']);
$localByCode = [];
foreach ($templates as $item) $localByCode[$item['code']] = $item;
$rows = [];
$seen = [];
foreach ($remote_list['items'] ?? [] as $item) {
    $rows[] = ['code' => $item['code'], 'name' => $item['name'], 'remote_status' => $item['status'], 'local' => $localByCode[$item['code']] ?? null, 'listed' => true];
    $seen[$item['code']] = true;
}
foreach ($templates as $item) {
    if (isset($seen[$item['code']])) continue;
    $remote = ($item['source'] ?? '') === 'kapi' ? $item['remote']['inspection'] . ' · ' . $item['remote']['status'] : '—';
    $rows[] = ['code' => $item['code'], 'name' => $item['name'], 'remote_status' => $remote, 'local' => $item, 'listed' => false];
}
$hidden = fn (array $fields): string => '<input type="hidden" name="csrf_token" value="' . $this->e($csrf_token) . '"><input type="hidden" name="environment" value="' . $this->e($environment) . '">'
    . implode('', array_map(fn (string $name, string $value): string => '<input type="hidden" name="' . $name . '" value="' . $this->e($value) . '">', array_keys($fields), $fields));
$postButton = fn (array $fields, string $action, string $label, string $class = 'btn btn-sm btn-outline'): string =>
    '<form method="post" action="' . $this->url('admin.messaging.templates') . '">' . $hidden($fields) . '<button class="' . $class . '" name="action" value="' . $action . '">' . $label . '</button></form>';
// 표 안에서는 작은 버튼, 모달 안에서는 다른 버튼과 같은 기본 크기를 쓴다.
$toggle = fn (array $item, string $size = ' btn-sm'): string => $postButton(['id' => $item['id'], 'revision' => $item['revision'], 'enabled' => $item['enabled'] ? '0' : '1'], 'enable',
    $item['enabled'] ? '사용 안 함으로' : '사용으로', 'btn' . $size . ($item['enabled'] ? ' btn-outline' : ' btn-primary'));
$delete = fn (array $item, string $size = ' btn-sm'): string => '<form method="post" action="' . $this->url('admin.messaging.templates') . '" onsubmit="return confirm(' . $this->e(json_encode('템플릿 "' . $item['name'] . '"을(를) 삭제할까요? 발송 이력은 남지만 이 템플릿으로는 더 발송할 수 없습니다.', JSON_UNESCAPED_UNICODE)) . ')">'
    . $hidden(['id' => $item['id'], 'revision' => $item['revision']]) . '<button class="btn' . $size . ' btn-outline btn-error" name="action" value="delete">삭제</button></form>';
?>
<section class="card card-body extension-panel template-panel"><h2 class="card-title">템플릿</h2>
<?php if (!$apiKey): ?>
<p class="alert alert-warning">알림톡 템플릿은 비즈뿌리오에서 가져와야 합니다. <a class="link" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">설정 → 알림톡·문자에 API 키를 저장</a>한 뒤 이 화면에서 가져오세요. API 키는 비즈뿌리오 내 정보 화면의 [API 키] 값이며, 없으면 고객센터에 아이디를 알려 발급받습니다.</p>
<?php else: ?>
<p>조회 아이디 <strong><?= $this->e($account_status['account']) ?></strong>. 승인·정상 상태의 템플릿을 가져오고, 이미 가져온 템플릿은 최신 내용으로 갱신합니다. 가져온 템플릿은 <strong>사용 안 함</strong>으로 들어오니 발송에 쓸 것만 <strong>사용으로</strong>를 눌러 켜세요. 발송 화면에서는 "사용 중"인 템플릿만 고를 수 있으며, 본문·버튼은 비즈뿌리오에서 수정한 뒤 다시 가져옵니다.</p>
<div class="card-actions">
<?= $postButton([], 'remote-import-all', '승인된 템플릿 모두 가져오기', 'btn btn-primary') ?>
<?= $postButton([], 'remote-list', $remote_list === null ? '비즈뿌리오 템플릿 조회' : '다시 조회', 'btn btn-outline') ?>
</div>
<?php endif ?>
<?php if ($import_summary !== null && ($import_summary['disabled'] !== [] || $import_summary['skipped'] !== [])): ?>
<div class="import-summary-box" role="alert"><h3>⚠ 가져오지 못한 템플릿 <?= count($import_summary['disabled']) + count($import_summary['skipped']) ?>개</h3>
<p>아래 템플릿은 이번에 가져오거나 갱신하지 못했습니다. 사유에 적힌 대로 비즈뿌리오에서 처리한 뒤 다시 가져오세요.</p>
<ul class="import-summary">
<?php foreach ([['disabled', '사용 중지', 'badge-error'], ['skipped', '건너뜀', 'badge-warning']] as [$group, $label, $badge]): foreach ($import_summary[$group] as $row): ?>
<li><span class="badge <?= $badge ?>"><?= $label ?></span> <strong><?= $this->e($row['name'] !== '' ? $row['name'] : $row['code']) ?></strong> <code><?= $this->e($row['code']) ?></code><span class="reason"><?= $this->e($row['reason']) ?></span></li>
<?php endforeach; endforeach ?>
</ul></div>
<?php endif ?>
<?php if ($remote_list !== null): ?><p>비즈뿌리오 전체 <?= $this->e($remote_list['total']) ?>개 · <?= $this->e($remote_list['page']) ?>/<?= $this->e(max(1, $remote_list['pages'])) ?>페이지<?= $remote_list['items'] === [] ? ' · 이 아이디·발신프로필에 등록된 템플릿이 없습니다.' : '' ?></p><?php endif ?>
<?php if ($rows === []): ?><p class="alert alert-info">아직 템플릿이 없습니다.<?= $apiKey ? ' 위에서 <strong>승인된 템플릿 모두 가져오기</strong>를 누르세요.' : '' ?></p>
<?php else: ?>
<div class="table-wrap"><table class="table template-table"><thead><tr><th>템플릿 코드</th><th>템플릿명</th><th>비즈뿌리오 상태</th><th>GNUCMS</th><th>동작</th></tr></thead><tbody>
<?php foreach ($rows as $row): $local = $row['local']; $isSelected = $local !== null && ($selected['id'] ?? '') === $local['id']; ?>
<tr><td data-label="템플릿 코드"><code><?= $this->e($row['code']) ?></code></td>
<td data-label="템플릿명"><?= $isSelected ? '<strong>' . $this->e($row['name']) . '</strong>' : $this->e($row['name']) ?><?php if ($local !== null && ($local['source'] ?? '') !== 'kapi'): ?> <small>(직접 등록)</small><?php endif ?></td>
<td data-label="비즈뿌리오 상태"><?= $this->e($row['remote_status']) ?></td>
<td data-label="GNUCMS"><?php if ($local === null): ?><span class="muted">가져오기 전</span><?php else: ?><span class="badge<?= $local['enabled'] ? ' badge-soft' : '' ?>"><?= $local['enabled'] ? '사용 중' : '사용 안 함' ?></span><?php if (($local['source'] ?? '') === 'kapi' && $local['remote']['reason'] !== ''): ?> <span class="badge badge-warning">발송 불가</span><?php endif ?><?php endif ?></td>
<td data-label="동작"><div class="row-actions">
<?php if ($local !== null): ?><?= $toggle($local) ?><a class="btn btn-sm btn-outline" href="?environment=<?= $this->e($environment) ?>&amp;id=<?= $this->e($local['id']) ?>">보기</a><?= $delete($local) ?><?php endif ?>
<?php if ($apiKey && ($row['listed'] || ($local['source'] ?? '') === 'kapi')): ?><?= $postButton(['remote_code' => $row['code']], 'remote-import-all', $local === null ? '가져오기' : '갱신', $local === null ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline') ?><?= $postButton(['remote_code' => $row['code']], 'remote-detail', '상세') ?><?php endif ?>
</div></td></tr>
<?php endforeach ?>
</tbody></table></div>
<?php endif ?>
<?php if ($remote_list !== null): ?><div class="card-actions">
<?php foreach ([-1 => '이전', 1 => '다음'] as $offset => $label): $targetPage = $remote_list['page'] + $offset; if ($targetPage < 1 || $targetPage > $remote_list['pages']) continue; ?>
<?= $postButton(['remote_page' => (string) $targetPage], 'remote-list', $label, 'btn btn-outline') ?>
<?php endforeach ?>
</div><?php endif ?>
</section>
<?php if ($remote_detail !== null): $remote = $remote_detail['remote']; ?>
<dialog class="template-dialog" id="remote-detail-dialog" open aria-labelledby="remote-detail-title"><div class="card card-body extension-panel"><h2 class="card-title" id="remote-detail-title">비즈뿌리오 상세 · <?= $this->e($remote_detail['content']['name']) ?> <small><code><?= $this->e($remote_detail['code']) ?></code></small></h2>
<p>검수: <strong><?= $this->e($remote['inspection']) ?></strong> · 사용 상태: <?= $this->e($remote['status']) ?> · 차단: <?= $remote['blocked'] === false ? '아니오' : ($remote['blocked'] === true ? '예' : '확인 불가') ?> · 휴면: <?= $remote['dormant'] === false ? '아니오' : ($remote['dormant'] === true ? '예' : '확인 불가') ?></p>
<pre><?= $this->e($remote_detail['content']['message']) ?></pre>
<?php foreach ($remote_detail['content']['buttons'] as $button): ?><p><strong><?= $this->e($button['name']) ?></strong> (<?= $this->e($button['type']) ?>)<br><?= $this->e($button['url_mobile']) ?><?php if ($button['url_pc'] !== ''): ?><br>PC: <?= $this->e($button['url_pc']) ?><?php endif ?></p><?php endforeach ?>
<?php if (!empty($remote_detail['content']['link'])): ?><p>대표 링크: <?= $this->e(implode(' · ', $remote_detail['content']['link'])) ?></p><?php endif ?>
<?php if (!$remote['sendable']): ?><p class="alert alert-warning"><?= $this->e($remote['reason']) ?><?php if ($remote_detail['local_id'] !== null): ?> 상태를 갱신하면 기존 로컬 템플릿의 사용을 중지합니다.<?php endif ?></p><?php endif ?>
<?php if ($remote['sendable'] || $remote_detail['local_id'] !== null): ?><p><?= $remote_detail['local_id'] === null ? '선택한 환경에 사용 안 함 상태로 저장합니다.' : '같은 코드의 로컬 템플릿을 갱신합니다. 기존 발송 이력은 보존됩니다.' ?></p><?php endif ?>
<div class="card-actions">
<?php if ($remote['sendable'] || $remote_detail['local_id'] !== null): ?><?= $postButton(['remote_code' => $remote_detail['code'], 'config_revision' => $remote_detail['config_revision'], 'local_revision' => $remote_detail['local_revision']], 'remote-import',
    !$remote['sendable'] ? '상태 갱신·사용 중지' : ($remote_detail['local_id'] === null ? 'GNUCMS로 가져오기' : '비즈뿌리오 내용으로 갱신'), 'btn btn-primary') ?><?php endif ?>
<form method="dialog"><button class="btn btn-outline">닫기</button></form>
</div></div></dialog>
<?php endif ?>
<?php if ($selected !== null): $kapi = ($selected['source'] ?? '') === 'kapi'; ?>
<dialog class="template-dialog" id="template-dialog" open aria-labelledby="template-dialog-title"><div class="card card-body extension-panel"><h2 class="card-title" id="template-dialog-title"><?= $this->e($selected['name']) ?> <small><code><?= $this->e($selected['code']) ?></code></small></h2>
<p><?= $kapi ? '비즈뿌리오에서 가져옴 · 검수: ' . $this->e($selected['remote']['inspection']) . ' · 상태: ' . $this->e($selected['remote']['status']) . ' · 마지막 확인: ' . $this->e($time($selected['remote']['checked_at'])) : '직접 등록' ?> · <?= $selected['enabled'] ? '사용 중' : '사용 안 함' ?></p>
<?php if ($kapi && $selected['remote']['reason'] !== ''): ?><p class="alert alert-warning"><?= $this->e($selected['remote']['reason']) ?></p><?php endif ?>
<?php if (!$kapi): ?><p class="alert alert-info">비즈뿌리오에서 가져오지 않은 사본입니다. 같은 코드를 비즈뿌리오에서 가져오면 비즈뿌리오 내용으로 대체됩니다.</p><?php endif ?>
<h3>본문</h3><pre id="template-message"><?= $this->e($selected['message']) ?></pre>
<?php if ($selected['buttons'] !== []): ?><h3>버튼</h3><ul><?php foreach ($selected['buttons'] as $button): ?><li><strong><?= $this->e($button['name']) ?></strong> → <?= $this->e($button['url_mobile']) ?><?php if (($button['url_pc'] ?? '') !== ''): ?> · PC: <?= $this->e($button['url_pc']) ?><?php endif ?></li><?php endforeach ?></ul><?php endif ?>
<?php if (!empty($selected['link'])): ?><p>대표 링크: <?= $this->e(implode(' · ', $selected['link'])) ?></p><?php endif ?>
<p>변수: <?= $selected['variables'] === [] ? '없음' : $this->e(implode(', ', $selected['variables'])) ?></p>
<?php if ($selected['enabled']): ?>
<h3>발송해 보기</h3>
<p><?= $environment === 'test' ? '테스트 환경은 설정의 테스트 수신번호로만 발송합니다.' : '운영 환경은 입력한 국내 휴대폰 번호로 발송합니다.' ?> 변수에는 예시 값이 채워져 있으니 필요하면 바꾼 뒤 미리보기를 누르세요.</p>
<form method="post" action="<?= $this->url('admin.messaging.templates') ?>"><?= $hidden(['id' => $selected['id'], 'template_id' => $selected['id'], 'revision' => $selected['revision']]) ?>
<label class="extension-label" for="modal-phone">수신 휴대폰 번호</label><input class="input input-bordered input-block" id="modal-phone" name="phone" inputmode="tel" required value="<?= $this->e(is_string($values['phone'] ?? null) ? $values['phone'] : ($environment === 'test' ? (string) ($account_status['test_phone'] ?? '') : '')) ?>">
<?php foreach ($selected['variables'] as $i => $variable): ?><label class="extension-label" for="modal-variable-<?= $i ?>"><?= $this->e($variable) ?></label><input class="input input-bordered input-block" id="modal-variable-<?= $i ?>" name="variables[<?= $this->e($variable) ?>]" required maxlength="1000" value="<?= $this->e(is_string($values['variables'][$variable] ?? null) ? $values['variables'][$variable] : (string) ($sample_values[$variable] ?? '')) ?>"><?php endforeach ?>
<div class="card-actions"><button class="btn btn-primary" name="action" value="preview">내용 미리보기</button></div></form>
<?php else: ?><p class="muted">발송해 보려면 먼저 <strong>사용으로</strong> 전환하세요.</p><?php endif ?>
<div class="card-actions"><?= $toggle($selected, '') ?><?= $delete($selected, '') ?><?php if ($kapi && $apiKey): ?><?= $postButton(['remote_code' => $selected['code']], 'remote-detail', '비즈뿌리오 최신 상태 확인', 'btn btn-outline') ?><?php endif ?>
<form method="dialog"><button class="btn btn-outline">닫기</button></form></div></div></dialog>
<?php if ($selected['enabled'] && $preview !== null && ($preview['template_id'] ?? null) === $selected['id']): ?>
<dialog class="template-dialog template-dialog-child" id="preview-dialog" open aria-labelledby="preview-dialog-title"><div class="card card-body extension-panel"><h2 class="card-title" id="preview-dialog-title">최종 발송 내용</h2>
<p><strong><?= $environment === 'live' ? '운영' : '테스트' ?></strong> · 수신번호 <?= $this->e(is_string($values['phone'] ?? null) ? $values['phone'] : '') ?> · 템플릿 <?= $this->e($selected['name']) ?></p>
<pre><?= $this->e($preview['message']) ?></pre>
<?php foreach ($preview['buttons'] as $button): ?><p><strong><?= $this->e($button['name']) ?></strong><br><?= $this->e($button['url_mobile']) ?><?php if (isset($button['url_pc'])): ?><br><?= $this->e($button['url_pc']) ?><?php endif ?></p><?php endforeach ?>
<?php if (!empty($preview['link'])): ?><p>대표 링크: <?= $this->e(implode(' · ', $preview['link'])) ?></p><?php endif ?>
<p>버튼을 누르면 비즈뿌리오로 실제 발송을 요청합니다.<?php if ($environment === 'test'): ?> 테스트 환경의 수신·과금 조건은 계정 계약을 따릅니다.<?php endif ?></p>
<?php if (!$account_status['enabled']): ?><p class="errors alert alert-error">현재 발송이 정지되어 있습니다. 설정 → 알림톡·문자에서 발송을 허용한 뒤 진행해 주세요.</p><?php endif ?>
<div class="card-actions"><form method="post" action="<?= $this->url('admin.messaging.templates') ?>"><?= $hidden(['id' => $selected['id'], 'confirmation' => (string) $confirmation]) ?><button class="btn btn-secondary" name="action" value="send"<?= !$account_status['enabled'] ? ' disabled' : '' ?>>확인한 번호로 알림톡 발송</button></form>
<form method="dialog"><button class="btn btn-outline">닫기</button></form></div></div></dialog>
<?php endif ?>
<?php endif ?>
<?php if ($remote_detail !== null || $selected !== null): ?>
<script>
(function(){
  // 서버가 open 상태로 렌더링한 대화상자를 문서 순서대로 진짜 모달로 올린다. 뒤에 오는 미리보기 모달이 보기 모달 위에 겹치고, 닫으면 아래 모달로 돌아간다.
  document.querySelectorAll('dialog.template-dialog[open]').forEach(function(dialog){
    if(typeof dialog.showModal!=='function'){return}
    dialog.removeAttribute('open');dialog.showModal();
    // 첫 버튼에 포커스 링이 그려지지 않도록 대화상자 자체에 포커스를 둔다. Tab을 누르면 첫 버튼부터 이동한다.
    dialog.tabIndex=-1;dialog.focus({preventScroll:true});
    dialog.addEventListener('click',function(event){if(event.target===dialog){dialog.close()}});
  });
  // Esc는 맨 위 모달 하나만 닫는다. Chrome은 사용자 조작 없이 연달아 연 모달을 한 번의 Esc로 모두 닫으므로 키를 직접 처리한다.
  document.addEventListener('keydown',function(event){
    if(event.key!=='Escape'){return}
    var open=Array.prototype.slice.call(document.querySelectorAll('dialog.template-dialog[open]'));
    if(!open.length){return}
    event.preventDefault();open[open.length-1].close();
  });
})();
</script>
<?php endif ?>
