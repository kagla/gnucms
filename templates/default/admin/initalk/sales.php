<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>매출·정산 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'sales', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<?php $value = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : ''; ?>
<section class="card card-body extension-panel"><h2 class="card-title">매출 조회 <small><?= $this->e($range_label) ?> · <?= $environment === 'live' ? '운영' : '테스트' ?></small></h2>
<form method="get" action="<?= $this->url('admin.initalk.sales') ?>" class="initalk-filters">
<label class="extension-label">환경<select class="select select-bordered select-block" name="environment"><option value="live"<?= $environment === 'live' ? ' selected' : '' ?>>운영</option><option value="test"<?= $environment === 'test' ? ' selected' : '' ?>>테스트</option></select></label>
<label class="extension-label">월<input class="input input-bordered input-block" type="month" name="month" value="<?= $this->e($month_value) ?>"></label>
<label class="extension-label">시작일 (직접 지정)<input class="input input-bordered input-block" type="date" name="from" value="<?= $this->e($value('from')) ?>"></label>
<label class="extension-label">종료일<input class="input input-bordered input-block" type="date" name="until" value="<?= $this->e($value('until')) ?>"></label>
<div class="card-actions form-actions"><button class="btn btn-primary">조회</button><a class="btn btn-outline btn-sm" href="<?= $this->url('admin.initalk.sales.export', [], array_filter(['environment' => $environment, 'month' => $value('month'), 'from' => $value('from'), 'until' => $value('until')])) ?>">CSV 내보내기</a></div></form>
<div class="stats stats-grid initalk-counts"><div class="stat"><div class="stat-title">승인</div><div class="stat-value"><?= number_format($summary['approve_amount']) ?>원</div><div class="stat-desc"><?= (int) $summary['approve_count'] ?>건</div></div><div class="stat"><div class="stat-title">환불</div><div class="stat-value"><?= number_format($summary['refund_amount']) ?>원</div><div class="stat-desc"><?= (int) $summary['refund_count'] ?>건</div></div><div class="stat"><div class="stat-title">순매출</div><div class="stat-value"><?= number_format($summary['net']) ?>원</div></div></div>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th>일자</th><th class="num">승인 건수</th><th class="num">승인 금액</th><th class="num">환불 건수</th><th class="num">환불 금액</th><th class="num">순매출</th></tr></thead><tbody>
<?php foreach ($daily as $day): ?><tr><td><?= $this->e($day['date']) ?></td><td class="num"><?= (int) $day['approve_count'] ?></td><td class="num"><?= number_format($day['approve_amount']) ?></td><td class="num"><?= (int) $day['refund_count'] ?></td><td class="num"><?= number_format($day['refund_amount']) ?></td><td class="num"><?= number_format($day['net']) ?></td></tr><?php endforeach ?>
<?php if ($daily === []): ?><tr><td colspan="6">이 기간의 확정 결제가 없습니다.</td></tr><?php endif ?></tbody></table></div></section>
<section class="card card-body extension-panel"><h2 class="card-title">정산 캘린더 <small>지급예정일 = 승인·환불일 + <?= (int) $config['settlement_days'] ?>일</small></h2>
<p class="muted">영업일·공휴일은 계산하지 않습니다. 실제 PG 정산서와 다를 수 있습니다.</p>
<table class="table initalk-calendar"><thead><tr><?php foreach (['일', '월', '화', '수', '목', '금', '토'] as $w): ?><th><?= $w ?></th><?php endforeach ?></tr></thead><tbody><tr>
<?php for ($i = 0; $i < $calendar_first_weekday; $i++): ?><td></td><?php endfor ?>
<?php for ($day = 1; $day <= $calendar_days; $day++): $date = $month_value . '-' . sprintf('%02d', $day); ?><td><div class="initalk-cal-day"><?= $day ?></div><?php if (isset($calendar[$date])): ?><div class="initalk-cal-amount<?= $calendar[$date] < 0 ? ' is-negative' : '' ?>"><?= number_format($calendar[$date]) ?></div><?php endif ?></td><?php if (($day + $calendar_first_weekday) % 7 === 0 && $day < $calendar_days): ?></tr><tr><?php endif ?><?php endfor ?>
</tr></tbody></table></section>
<?php $this->stop() ?>
