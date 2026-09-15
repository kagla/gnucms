<?php $this->layout('pay/layout') ?>
<?php $this->start('title') ?>결제창 연결 · <?= $this->e($store_name) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<h1 class="pay-title">카드 결제창으로 이동합니다</h1>
<p class="pay-hint"><?= $this->e($request['product_name']) ?> · <?= number_format($request['amount']) ?>원. 결제창이 열리지 않으면 아래 버튼을 눌러 주세요.</p>
<form id="pay-form" method="post" action="<?= $this->e($payment['action'] ?? '') ?>" accept-charset="<?= $this->e($payment['charset'] ?? 'UTF-8') ?>">
<?php foreach ($payment['fields'] as $field => $value): ?><input type="hidden" name="<?= $this->e($field) ?>" value="<?= $this->e((string) $value) ?>"><?php endforeach ?>
<button class="pay-button" id="pay-button" type="<?= $payment['kind'] === 'inicis' ? 'button' : 'submit' ?>">카드 결제창 열기</button>
</form>
<p id="pay-message" class="pay-hint" role="status"></p>
<p><a class="pay-link" href="<?= $this->url('pay.show', ['token' => $token]) ?>">결제 내역으로 돌아가기</a></p>
<?php $this->stop() ?>
<?php $this->start('scripts') ?>
<?php if ($payment['kind'] === 'inicis'): ?>
<script src="<?= $this->e($payment['script']) ?>"></script>
<script>(()=>{'use strict';const button=document.getElementById('pay-button'),message=document.getElementById('pay-message');const open=()=>{if(!window.INIStdPay){message.textContent='결제창 연결을 확인해 주세요.';return;}window.INIStdPay.pay('pay-form');};button.addEventListener('click',open);window.setTimeout(open,300);})();</script>
<?php else: ?>
<script>(()=>{'use strict';window.setTimeout(()=>{document.getElementById('pay-form').submit();},300);})();</script>
<?php endif ?>
<?php $this->stop() ?>
