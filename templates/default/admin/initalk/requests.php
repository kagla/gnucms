<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>통합조회 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php
$notice = match (true) {
    isset($query['imported']) => 'CSV로 ' . (int) $query['imported'] . '건을 만들었습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 실패 ' . (int) $query['failed'] . '건.' : '') . ((int) ($query['sent'] ?? 0) > 0 ? ' 알림톡 ' . (int) $query['sent'] . '건 발송.' : ''),
    isset($query['sent']) => '알림톡 ' . (int) $query['sent'] . '건을 발송했습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 실패 ' . (int) $query['failed'] . '건은 상세에서 확인해 주세요.' : ''),
    isset($query['cancelled']) => (int) $query['cancelled'] . '건을 결제 전 취소했습니다.' . ((int) ($query['failed'] ?? 0) > 0 ? ' 취소할 수 없는 ' . (int) $query['failed'] . '건은 건너뛰었습니다.' : ''),
    isset($query['none']) => '선택한 결제 요청이 없습니다.',
    default => '',
};
$value = static fn (string $key): string => is_string($filter[$key] ?? null) ? $filter[$key] : '';
?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'requests', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<div class="stats stats-grid initalk-counts"><?php foreach ($status_labels as $key => $label): if ($key === 'refunded') continue; ?><div class="stat"><div class="stat-title"><?= $this->e($label) ?></div><div class="stat-value"><?= (int) $counts[$key] ?></div></div><?php endforeach ?></div>
<section class="card card-body extension-panel"><h2 class="card-title">검색</h2>
<form method="get" action="<?= $this->url('admin.initalk.requests') ?>" class="initalk-filters">
<label class="extension-label">환경<select class="select select-bordered select-block" name="environment"><option value="test"<?= $filter['environment'] === 'test' ? ' selected' : '' ?>>테스트</option><option value="live"<?= $filter['environment'] === 'live' ? ' selected' : '' ?>>운영</option></select></label>
<label class="extension-label">거래등록일 시작<input class="input input-bordered input-block" type="date" name="from" value="<?= $this->e($value('from')) ?>"></label>
<label class="extension-label">거래등록일 끝<input class="input input-bordered input-block" type="date" name="until" value="<?= $this->e($value('until')) ?>"></label>
<label class="extension-label">휴대폰번호<input class="input input-bordered input-block" type="tel" name="phone" data-phone-format="mobile" value="<?= $this->e($value('phone')) ?>" placeholder="- 없이 숫자만"></label>
<label class="extension-label">구매자명<input class="input input-bordered input-block" name="buyer_name" value="<?= $this->e($value('buyer_name')) ?>"></label>
<label class="extension-label">상품명<input class="input input-bordered input-block" name="product_name" value="<?= $this->e($value('product_name')) ?>"></label>
<label class="extension-label">주문번호<input class="input input-bordered input-block" name="number" value="<?= $this->e($value('number')) ?>" placeholder="IT-"></label>
<label class="extension-label">금액<input class="input input-bordered input-block" name="amount" inputmode="numeric" value="<?= $this->e($value('amount')) ?>"></label>
<label class="extension-label">결제상태<select class="select select-bordered select-block" name="status"><option value="">전체</option><?php foreach ($status_labels as $key => $label): ?><option value="<?= $this->e($key) ?>"<?= $value('status') === $key ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach ?></select></label>
<fieldset class="extension-label"><legend>알림톡</legend><label><input type="radio" name="sendable" value=""<?= $value('sendable') === '' ? ' checked' : '' ?>> 전체</label> <label><input type="radio" name="sendable" value="1"<?= $value('sendable') === '1' ? ' checked' : '' ?>> 발송 가능</label> <label><input type="radio" name="sendable" value="0"<?= $value('sendable') === '0' ? ' checked' : '' ?>> 발송 불가</label></fieldset>
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">조회</button><?php foreach (['1' => '1개월', '2' => '2개월', '3' => '3개월'] as $months => $label): ?><button class="btn btn-outline btn-sm" type="submit" name="months" value="<?= $months ?>"><?= $label ?></button><?php endforeach ?><a class="btn btn-ghost btn-sm" href="<?= $this->url('admin.initalk.requests') ?>">조건 초기화</a></div>
</form></section>
<section class="card card-body extension-panel"><h2 class="card-title">결제 요청 <small><?= (int) $result['total'] ?>건</small></h2>
<form method="post" action="<?= $this->url('admin.initalk.requests.bulk') ?>" data-initalk-bulk>
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($filter['environment']) ?>">
<div class="card-actions"><button class="btn btn-primary btn-sm" name="action" value="send">선택 건 알림톡 발송</button><button class="btn btn-outline btn-sm" name="action" value="cancel">선택 건 결제전 취소</button></div>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th><input type="checkbox" data-initalk-check-all aria-label="전체 선택"></th><th>주문번호</th><th>구매자</th><th>휴대폰</th><th>상품명</th><th class="num">금액</th><th>알림톡</th><th>상태</th><th>등록</th><th>상태 변경</th></tr></thead><tbody>
<?php foreach ($result['items'] as $item): ?><tr>
<td><input type="checkbox" name="ids[]" value="<?= $this->e($item['id']) ?>" aria-label="선택"></td>
<td><a class="link" href="<?= $this->url('admin.initalk.request', ['id' => $item['id']]) ?>"><?= $this->e($item['number']) ?></a></td>
<td><?= $this->e($item['buyer_name'] !== '' ? $item['buyer_name'] : '보관 만료') ?></td>
<td><?= $this->e($item['phone_mask'] !== '' ? $item['phone_mask'] : '보관 만료') ?></td>
<td><?= $this->e($item['product_name']) ?></td>
<td class="num"><?= number_format($item['amount']) ?>원</td>
<td><?= $item['dispatch_count'] > 0 ? $item['dispatch_count'] . '회' : '미발송' ?></td>
<td><span class="badge badge-soft initalk-status-<?= $this->e($item['status']) ?>"><?= $this->e($item['status_label']) ?></span><?php if ($item['needs_review']): ?> <span class="badge badge-warning">확인 필요</span><?php endif ?></td>
<td><?= $this->e($time($item['created_at'])) ?></td>
<td><?= $this->e($time($item['status_changed_at'])) ?></td>
</tr><?php endforeach ?>
<?php if ($result['items'] === []): ?><tr><td colspan="10">조회된 결제 요청이 없습니다.</td></tr><?php endif ?>
</tbody></table></div></form>
<?php $pageQuery = array_filter($filter, static fn ($v, $k): bool => is_string($v) && $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH); $pages = (int) ceil($result['total'] / $result['per_page']); ?>
<nav class="initalk-pager"><?php if ($result['page'] > 1): ?><a class="link" href="<?= $this->url('admin.initalk.requests', [], $pageQuery + ['page' => $result['page'] - 1]) ?>">이전</a><?php endif ?> <?= $result['page'] ?> / <?= max(1, $pages) ?> <?php if ($result['page'] < $pages): ?><a class="link" href="<?= $this->url('admin.initalk.requests', [], $pageQuery + ['page' => $result['page'] + 1]) ?>">다음</a><?php endif ?></nav>
</section>
<p class="muted">목록의 번호는 마스킹됩니다. 전체 번호는 상세에서 전체 관리자만 볼 수 있습니다.</p>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><?php $this->insert('admin/_phone_input') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
