<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>결제 요청 상세 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php
$notice = match (true) {
    isset($query['created']) && isset($query['sent']) && $query['sent'] === '1' => '결제 요청을 만들고 알림톡을 발송했습니다.',
    isset($query['created']) && isset($query['send_error']) => '결제 요청을 만들었지만 알림톡 발송에 실패했습니다. 아래에서 다시 보낼 수 있습니다.',
    isset($query['created']) => '결제 요청을 만들었습니다.',
    isset($query['sent']) => $query['sent'] === '1' ? '알림톡을 발송했습니다.' : '알림톡 접수에 실패했습니다. 발송 상세를 확인해 주세요.',
    isset($query['cancelled']) => '결제 전 취소했습니다.',
    isset($query['synced']) => '결제사 조회 결과를 반영했습니다.',
    isset($query['refunded']) => '환불을 처리했습니다.',
    isset($query['refund_closed']) => '미처리 환불 신청을 종료했습니다.',
    default => '',
};
$r = $request; $remaining = $r['amount'] - $r['refunded_amount'];
?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'requests', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<?php if ($r['needs_review']): ?><p class="alert alert-warning" role="alert">결제사 조회 결과와 요청이 일치하지 않거나 보류 중인 환불이 있습니다. 결제 상태 조회로 대조해 주세요.</p>
<?php if (\GnuCms\Initalk\Status::canCancel($r['status'])): ?><p class="muted">확인 필요: 결제 상태 조회로 PG 결과를 대조하고, 결제 내역이 없으면 취소 후 새 요청을 만드세요. 이미 요청한 결제의 결과가 확정되지 않으면 고객의 재결제도 막힙니다.</p><?php endif ?>
<?php endif ?>
<div class="cols initalk-cols">
<section class="card card-body extension-panel"><h2 class="card-title"><?= $this->e($r['number']) ?> <span class="badge badge-soft initalk-status-<?= $this->e($r['status']) ?>"><?= $this->e($r['status_label']) ?></span></h2>
<dl class="initalk-dl">
<dt>구매자명</dt><dd><?= $this->e($r['buyer_name'] !== '' ? $r['buyer_name'] : '보관 만료') ?></dd>
<dt>휴대폰번호</dt><dd><?= $this->e($r['phone'] !== '' ? \GnuCms\Initalk\Phone::format($r['phone']) : '보관 만료') ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?></dd>
<dt>상품 상세</dt><dd><?= $this->e($r['product_detail']) ?></dd>
<dt>금액</dt><dd><?= number_format($r['amount']) ?>원<?php if ($r['refunded_amount'] > 0): ?> (환불 <?= number_format($r['refunded_amount']) ?>원 · 남은 금액 <?= number_format($remaining) ?>원)<?php endif ?></dd>
<dt>결제기한</dt><dd><?= $this->e($time($r['expires_at'])) ?></dd>
<dt>환경</dt><dd><?= $r['environment'] === 'live' ? '운영' : '테스트' ?></dd>
<dt>거래 등록</dt><dd><?= $this->e($time($r['created_at'])) ?></dd>
<dt>최종 상태 변경</dt><dd><?= $this->e($time($r['status_changed_at'])) ?></dd>
<dt>알림톡</dt><dd><?= $r['dispatch_count'] > 0 ? $r['dispatch_count'] . '회 발송 · 마지막 ' . $this->e($time($r['last_dispatched_at'])) : '미발송' ?><?php if ($r['last_dispatch_id'] !== null): ?> · <a class="link" href="<?= $this->url('admin.messaging.detail', ['id' => $r['last_dispatch_id']], ['environment' => $r['environment']]) ?>">발송 상세</a><?php endif ?></dd>
<dt>결제일시</dt><dd><?= $this->e($time($r['paid_at'])) ?></dd>
<dt>TID</dt><dd><?= $this->e($r['transaction_id']) ?></dd>
</dl>
<label class="extension-label" for="initalk-pay-url">결제 링크</label><div class="initalk-link"><input class="input input-bordered input-block" id="initalk-pay-url" readonly value="<?= $this->e($pay_url) ?>"><button type="button" class="btn btn-sm" data-copy="initalk-pay-url">복사</button></div>
<div class="initalk-qr"><img src="<?= $this->url('admin.initalk.request.qr', ['id' => $r['id']]) ?>" alt="결제 링크 QR 코드" width="180" height="180"><p class="muted">대면 결제: 고객이 QR을 찍으면 같은 결제 페이지가 열립니다. <a class="link" href="<?= $this->url('admin.initalk.request.qr', ['id' => $r['id']]) ?>" target="_blank" rel="noopener">새 창에서 크게 보기</a> · <a class="link" href="<?= $this->url('admin.initalk.request.qr', ['id' => $r['id']]) ?>" download="<?= $this->e($r['number']) ?>.svg">SVG 저장</a></p></div>
</section>
<section class="card card-body extension-panel"><h2 class="card-title">작업</h2>
<?php if (\GnuCms\Initalk\Status::canSend($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.send', ['id' => $r['id']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-primary"><?= $r['status'] === 'expired' ? '기한 연장 후 알림톡 발송' : ($r['dispatch_count'] > 0 ? '알림톡 재발송' : '알림톡 발송') ?></button></form><?php endif ?>
<?php if (\GnuCms\Initalk\Status::canCancel($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.cancel', ['id' => $r['id']]) ?>" data-confirm="cancel"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">결제전 취소</button></form><?php endif ?>
<?php if ($r['config_revision'] !== ''): ?><form method="post" action="<?= $this->url('admin.initalk.request.sync', ['id' => $r['id']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="btn btn-outline">결제 상태 조회</button></form><?php endif ?>
<?php if (\GnuCms\Initalk\Status::canRefund($r['status'])): ?><form method="post" action="<?= $this->url('admin.initalk.request.refund', ['id' => $r['id']]) ?>" data-confirm="refund"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="refund_key" value="<?= $this->e($refund_key) ?>">
<label class="extension-label" for="refund-amount">환불 금액 (남은 금액 <?= number_format($remaining) ?>원)</label><input class="input input-bordered input-block" id="refund-amount" name="amount" inputmode="numeric" required value="<?= $remaining ?>">
<label class="extension-label" for="refund-reason">환불 사유</label><input class="input input-bordered input-block" id="refund-reason" name="reason" maxlength="80" required>
<button class="btn btn-error">환불</button></form><?php endif ?>
</section>
</div>
<?php if ($pending_refunds !== []): ?>
<section class="card card-body extension-panel"><h2 class="card-title">보류 중인 환불 신청</h2>
<p class="muted">결제사 응답을 확인하지 못한 환불입니다. 결제 상태 조회로 같은 금액의 취소를 찾으면 연결합니다. 결제사에 취소 내역이 없다면 신청 2시간 뒤부터 종료할 수 있습니다.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>요청 키</th><th class="num">금액</th><th>신청 시각</th><th>경과</th><th>작업</th></tr></thead><tbody>
<?php foreach ($pending_refunds as $pendingKey => $pending): $age = \GnuCms\Support\Clock::timestamp() - $pending['at']; ?>
<tr><td>…<?= $this->e(substr($pendingKey, -8)) ?></td><td class="num"><?= number_format($pending['amount']) ?>원</td><td><?= $this->e($time($pending['at'])) ?></td><td><?= (int) floor($age / 60) ?>분 전</td>
<td><?php if ($age > 7200): ?><form method="post" action="<?= $this->url('admin.initalk.request.refund.close', ['id' => $r['id']]) ?>" data-confirm="refund-close"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="key" value="<?= $this->e($pendingKey) ?>"><button class="btn btn-sm btn-outline">미처리 종료</button></form><?php else: ?><span class="muted">결제 상태 조회로 대조</span><?php endif ?></td></tr>
<?php endforeach ?>
</tbody></table></div></section>
<?php endif ?>
<section class="card card-body extension-panel"><h2 class="card-title">확정 원장</h2><div class="table-wrap"><table class="table"><thead><tr><th>일시</th><th>구분</th><th class="num">금액</th><th>거래·취소 ID</th></tr></thead><tbody>
<?php foreach ($ledger as $row): ?><tr><td><?= $this->e($time($row['at'])) ?></td><td><?= $row['kind'] === 'approve' ? '승인' : '환불' ?></td><td class="num"><?= number_format((int) $row['amount']) ?>원</td><td><?= $this->e($row['reference']) ?></td></tr><?php endforeach ?>
<?php if ($ledger === []): ?><tr><td colspan="4">확정된 결제가 없습니다.</td></tr><?php endif ?></tbody></table></div></section>
<section class="card card-body extension-panel"><h2 class="card-title">이력</h2><ol class="initalk-timeline"><?php foreach ($events as $event): ?><li><small><?= $this->e($time($event['created_at'])) ?></small> <strong><?= $this->e($event['type']) ?></strong> <?= $this->e($event['note']) ?> <small>(<?= $this->e($event['actor']) ?>)</small></li><?php endforeach ?></ol></section>
<?php $this->stop() ?>
<?php $this->start('scripts') ?><script src="<?= $this->asset('initalk.js') ?>"></script><?php $this->stop() ?>
