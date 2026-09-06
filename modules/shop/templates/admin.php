<div class="toolbar page-head"><div><h1>주문 관리</h1><p class="muted">주문 확인부터 포장, 배송, 반품까지 한 곳에서 처리하세요.</p></div><form method="post" action="<?= $this->e($admin_url) ?>"><?php $this->insert('_csrf') ?><button class="secondary btn" name="action" value="expire">30분 지난 미결제 주문 정리</button></form></div>
<?php if (!$images_ready || !$costs_ready): ?>
<section class="panel card card-body extension-panel">
  <h2 class="card-title">쇼핑몰 데이터 업데이트</h2>
  <p>상품 이미지와 원가 관리를 사용할 수 있도록 데이터를 갱신합니다. 기존 상품·주문·이미지는 보존되며, 과거 원가는 미입력 상태로 유지됩니다.</p>
  <form method="post" action="<?= $this->e($admin_url) ?>"><?php $this->insert('_csrf') ?><button class="btn btn-primary" name="action" value="install">쇼핑몰 데이터 설치/갱신</button></form>
</section>
<?php endif ?>
<section class="panel card card-body extension-panel"><form class="row" method="get" action="<?= $this->e($admin_url) ?>"><label class="extension-label">상태 <select class="select select-bordered select-block" name="status"><option value="">전체 상태</option><?php foreach (['attention', 'pending', 'paid', 'packing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $state): ?><option value="<?= $state ?>"<?= ($input['status'] ?? '') === $state ? ' selected' : '' ?>><?= $this->e($status_labels[$state]) ?></option><?php endforeach ?></select></label><label class="extension-label">환경 <select class="select select-bordered select-block" name="environment"><option value="">전체 환경</option><option value="live"<?= ($input['environment'] ?? '') === 'live' ? ' selected' : '' ?>>운영</option><option value="test"<?= ($input['environment'] ?? '') === 'test' ? ' selected' : '' ?>>테스트</option></select></label><button class="btn btn-primary">주문 보기</button></form></section>
<?php $this->insert('_orders') ?>
