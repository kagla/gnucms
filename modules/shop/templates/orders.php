<div class="toolbar"><div><p class="eyebrow">Your orders</p><h1>내 주문</h1><p class="muted">배송 상태를 확인하고 취소·반품·교환을 신청할 수 있습니다.</p></div><a class="button secondary" href="<?= $this->e($url) ?>/catalog">계속 쇼핑하기</a></div>
<form class="row order-filter" method="get" action="<?= $this->e($url) ?>/orders">
  <label for="shop-order-status">주문 상태</label>
  <select id="shop-order-status" name="status"><option value="">전체 주문</option><?php foreach (['pending', 'paid', 'packing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $status): ?><option value="<?= $this->e($status) ?>"<?= ($input['status'] ?? '') === $status ? ' selected' : '' ?>><?= $this->e($status_labels[$status]) ?></option><?php endforeach ?></select><button class="secondary">조회</button>
</form>
<?php if ($orders === []): ?>
<section class="panel empty"><h2><?= ($input['status'] ?? '') === '' ? '아직 주문한 상품이 없습니다' : '해당 상태의 주문이 없습니다' ?></h2><p>마음에 드는 상품을 찾아 첫 주문을 시작해 보세요.</p><a class="button" href="<?= $this->e($url) ?>/catalog">상품 둘러보기</a></section>
<?php else: ?>
<div class="order-list">
<?php foreach ($orders as $row): ?>
  <article class="panel customer-order">
    <div class="toolbar"><span class="badge"><?= $this->e($status_labels[$row['status']] ?? $row['status']) ?></span><time><?= $this->e($time($row['created_at'])) ?></time></div>
    <h2><a href="<?= $this->e($url) ?>/order?id=<?= $this->e($row['id']) ?>"><?= $this->e($row['order_name']) ?></a></h2>
    <p class="order-id">주문번호 <?= $this->e($row['id']) ?></p>
    <div class="summary-line"><span>주문 금액</span><strong><?= number_format((int) $row['total']) ?>원</strong></div>
    <?php if ((int) $row['refunded'] > 0): ?><div class="summary-line"><span>환불 금액</span><span><?= number_format((int) $row['refunded']) ?>원</span></div><?php endif ?>
    <?php if ((int) $row['open_claims'] > 0): ?><p class="muted">취소·반품·교환 신청 <?= (int) $row['open_claims'] ?>건을 처리 중입니다.</p><?php endif ?>
    <?php if ($row['needs_review']): ?><p class="muted">상점에서 결제 내역을 확인하고 있습니다.</p><?php endif ?>
    <a class="button secondary" href="<?= $this->e($url) ?>/order?id=<?= $this->e($row['id']) ?>"><?= $row['status'] === 'pending' ? '주문 확인 및 결제' : '주문 상세·배송 확인' ?></a>
  </article>
<?php endforeach ?>
</div>
<?php endif ?>
<nav class="pagination" aria-label="주문 목록 페이지"><?php if ($page_number > 1): ?><a href="<?= $this->e($url) ?>/orders?<?= $this->e(http_build_query(['p' => $page_number - 1, 'status' => $input['status'] ?? ''])) ?>">← 이전</a><?php endif ?><?php if (count($orders) === 30): ?><a href="<?= $this->e($url) ?>/orders?<?= $this->e(http_build_query(['p' => $page_number + 1, 'status' => $input['status'] ?? ''])) ?>">다음 →</a><?php endif ?></nav>
