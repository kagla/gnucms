<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>대시보드 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'dashboard', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<div class="stats stats-grid initalk-counts"><?php foreach (['created' => 0, 'waiting' => 1, 'paid' => 2, 'expired' => 3, 'cancelled' => 4] as $key => $tone): ?><a class="stat" href="<?= $this->url('admin.initalk.requests', [], ['environment' => $environment, 'status' => $key]) ?>"><div class="stat-figure" data-tone="<?= $tone ?>" aria-hidden="true"><?= $this->icon('tag', 20) ?></div><div class="stat-title"><?= $this->e($status_labels[$key]) ?></div><div class="stat-value"><?= (int) $counts[$key] ?></div><div class="stat-desc">보기 <?= $this->icon('arrow-right', 12) ?></div></a><?php endforeach ?></div>
<div class="cols initalk-cols">
<section class="card card-body extension-panel"><h2 class="card-title"><?= $this->e($month_label) ?> 매출 현황</h2>
<dl class="initalk-dl"><dt>승인</dt><dd><?= (int) $month['approve_count'] ?>건 · <?= number_format($month['approve_amount']) ?>원</dd><dt>환불</dt><dd><?= (int) $month['refund_count'] ?>건 · <?= number_format($month['refund_amount']) ?>원</dd><dt>합계</dt><dd><strong><?= number_format($month['net']) ?>원</strong></dd></dl>
<p><a class="link" href="<?= $this->url('admin.initalk.sales', [], ['environment' => $environment]) ?>">매출·정산 보기</a></p></section>
<section class="card card-body extension-panel"><h2 class="card-title">지급예정금액</h2><p class="initalk-payout"><strong><?= number_format($payout) ?>원</strong></p><p class="muted">승인일 + <?= (int) $config['settlement_days'] ?>일 기준으로 아직 지급예정일이 오지 않은 순액입니다. 실제 PG 정산과 다를 수 있습니다.</p></section>
</div>
<section class="card card-body extension-panel"><h2 class="card-title">최근 결제 요청</h2><div class="table-wrap"><table class="table initalk-table"><thead><tr><th>주문번호</th><th>구매자</th><th>상품명</th><th class="num">금액</th><th>상태</th><th>등록</th></tr></thead><tbody>
<?php foreach ($recent as $item): ?><tr><td><a class="link" href="<?= $this->url('admin.initalk.request', ['id' => $item['id']]) ?>"><?= $this->e($item['number']) ?></a></td><td><?= $this->e($item['buyer_name'] !== '' ? $item['buyer_name'] : '보관 만료') ?></td><td><?= $this->e($item['product_name']) ?></td><td class="num"><?= number_format($item['amount']) ?>원</td><td><?= $this->e($item['status_label']) ?></td><td><?= $this->e($time($item['created_at'])) ?></td></tr><?php endforeach ?>
<?php if ($recent === []): ?><tr><td colspan="6">아직 결제 요청이 없습니다. <a class="link" href="<?= $this->url('admin.initalk.requests.new') ?>">첫 결제를 만들어 보세요.</a></td></tr><?php endif ?>
</tbody></table></div></section>
<?php $this->stop() ?>
