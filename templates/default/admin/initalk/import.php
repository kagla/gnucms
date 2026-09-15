<?php $this->layout('admin/layout') ?>
<?php $this->start('admin_body_class') ?>extension-admin initalk-admin<?php $this->stop() ?>
<?php $this->start('seo_meta') ?><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="<?= $this->asset('extensions.css') ?>"><link rel="stylesheet" href="<?= $this->asset('initalk.css') ?>"><?php $this->stop() ?>
<?php $this->start('title') ?>CSV 일괄등록 · 이니톡 결제 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>initalk<?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $this->insert('admin/initalk/_nav', ['active' => 'import', 'errors' => $errors, 'notice' => $notice, 'config' => $config]) ?>
<?php if ($preview === null): ?>
<section class="card card-body extension-panel"><h2 class="card-title">CSV 일괄등록</h2>
<p>샘플 파일의 머리글(상품명, 상품상세, 구매자명, 휴대폰번호, 금액, 결제기한(시간))을 그대로 쓰면 됩니다. 열 순서는 바꿔도 되고 결제기한(시간)은 비우면 기본값(<?= (int) $config['expiry_hours'] ?>시간)입니다. UTF-8 또는 엑셀 저장(CP949) 파일, 1MB·500행 이하.</p>
<p><a class="btn btn-sm btn-outline" href="<?= $this->url('admin.initalk.import.sample') ?>">업로드 샘플 다운로드</a></p>
<?php if ($parse_errors !== []): ?><div class="alert alert-error" role="alert"><strong>검증에 실패한 행이 있어 등록하지 않았습니다. (<?= count($parse_errors) ?>건)</strong><ul><?php foreach ($parse_errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form method="post" action="<?= $this->url('admin.initalk.import') ?>" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<label class="extension-label" for="file">CSV 파일</label><input class="file-input file-input-bordered" type="file" id="file" name="file" accept=".csv,text/csv" required>
<label class="extension-label"><input class="checkbox" type="checkbox" name="send_now" value="1" checked> 결제 알림톡 즉시 전송하기</label>
<div class="card-actions form-actions"><button class="btn btn-primary">검증하고 미리보기</button></div></form></section>
<?php else: ?>
<section class="card card-body extension-panel"><h2 class="card-title">미리보기 <small><?= $this->e($preview['filename']) ?> · <?= count($preview['rows']) ?>건 · 합계 <?= number_format($preview['sum']) ?>원</small></h2>
<div class="table-wrap"><table class="table initalk-table"><thead><tr><th>행</th><th>상품명</th><th>상품 상세</th><th>구매자명</th><th>휴대폰</th><th class="num">금액</th><th>기한(시간)</th></tr></thead><tbody>
<?php foreach ($preview['rows'] as $row): ?><tr><td><?= (int) $row['line'] ?></td><td><?= $this->e($row['product_name']) ?></td><td><?= $this->e($row['product_detail']) ?></td><td><?= $this->e($row['buyer_name']) ?></td><td><?= $this->e(\GnuCms\Initalk\Phone::format($row['phone'])) ?></td><td class="num"><?= number_format($row['amount']) ?>원</td><td><?= (int) $row['expiry_hours'] ?></td></tr><?php endforeach ?>
</tbody></table></div>
<p><?= $preview['send'] ? '거래등록 후 각 건에 결제 알림톡을 바로 보냅니다.' : '거래만 등록하고 알림톡은 통합조회에서 따로 보냅니다.' ?> 미리보기는 10분 동안 유효합니다.</p>
<form method="post" action="<?= $this->url('admin.initalk.import.confirm') ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="token" value="<?= $this->e($preview['token']) ?>">
<div class="card-actions form-actions"><button class="btn btn-primary">거래등록</button><a class="btn btn-ghost" href="<?= $this->url('admin.initalk.import') ?>">다시 올리기</a></div></form></section>
<?php endif ?>
<?php $this->stop() ?>
