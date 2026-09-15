<?php $this->layout('pay/layout') ?>
<?php $this->start('title') ?>결제 · <?= $this->e($store_name) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<?php $r = $request; ?>
<?php foreach ($errors as $error): ?><p class="pay-alert" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($failed && $state === 'open'): ?><p class="pay-alert" role="alert">결제가 완료되지 않았습니다. 다시 시도해 주세요.</p><?php endif ?>
<?php if ($state === 'open' || $state === 'unavailable'): ?>
<h1 class="pay-title"><?= $this->e($r['buyer_name'] !== '' ? $r['buyer_name'] . ' 님' : '고객') ?>, 결제할 내역을 확인해 주세요</h1>
<dl class="pay-summary">
<dt>상점명</dt><dd><?= $this->e($store_name) ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?><?php if ($r['product_detail'] !== ''): ?><br><small><?= $this->e($r['product_detail']) ?></small><?php endif ?></dd>
<dt>결제금액</dt><dd class="pay-amount"><?= number_format($r['amount']) ?>원</dd>
<dt>결제기한</dt><dd><?= $this->e($time($r['expires_at'])) ?></dd>
<dt>주문번호</dt><dd><?= $this->e($r['number']) ?></dd>
</dl>
<?php if ($state === 'open'): ?>
<p class="pay-hint">결제 내용에 동의하시면 다음 버튼을 눌러 주세요. 신용카드·간편결제로 결제됩니다.</p>
<form method="post" action="<?= $this->url('pay.start', ['token' => $r['url_token']]) ?>"><input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><button class="pay-button" type="submit">다음</button></form>
<?php else: ?><p class="pay-alert" role="alert">지금은 결제할 수 없습니다. 상점에 문의해 주세요.</p><?php endif ?>
<?php elseif ($state === 'done'): ?>
<h1 class="pay-title">결제가 완료되었습니다</h1>
<dl class="pay-summary">
<dt>주문번호</dt><dd><?= $this->e($r['number']) ?></dd>
<dt>상품명</dt><dd><?= $this->e($r['product_name']) ?></dd>
<dt>결제금액</dt><dd class="pay-amount"><?= number_format($r['amount']) ?>원</dd>
<dt>결제일시</dt><dd><?= $this->e($time($r['paid_at'])) ?></dd>
<?php if ($r['refunded_amount'] > 0): ?><dt>환불</dt><dd><?= number_format($r['refunded_amount']) ?>원<?= $r['status'] === 'refunded' ? ' (전액 환불)' : '' ?></dd><?php endif ?>
</dl>
<p class="pay-hint">승인 내역은 카드사 앱과 문자로도 확인할 수 있습니다.</p>
<?php elseif ($state === 'expired'): ?>
<h1 class="pay-title">결제 기한이 지났습니다</h1>
<p class="pay-hint"><?= $this->e($r['product_name']) ?> (<?= number_format($r['amount']) ?>원)의 결제 기한 <?= $this->e($time($r['expires_at'])) ?>이 지났습니다. 상점에 문의해 주세요.</p>
<?php else: ?>
<h1 class="pay-title">취소된 결제 요청입니다</h1>
<p class="pay-hint">이 결제 요청은 상점에서 취소했습니다. 문의는 상점으로 해 주세요.</p>
<?php endif ?>
<?php $this->stop() ?>
