<?php if ($product !== null): ?>
<p><a href="<?= $this->e($url) ?>/catalog">← 상품 목록</a></p><div class="cols">
<div><?php if ($product['image']): ?><img class="product-image" src="<?= $this->e($url) ?>/image?file=<?= $this->e($product['image']) ?>" alt="<?= $this->e($product['name']) ?>"><?php else: ?><div class="placeholder product-image" aria-hidden="true">S</div><?php endif ?></div>
<section class="panel stack"><p class="eyebrow">Selected for you</p><h1><?= $this->e($product['name']) ?></h1>
<form class="stack" method="post" action="<?= $this->e($url) ?>/cart"><?php $this->insert('_csrf') ?><input type="hidden" name="action" value="add">
<label>상품 옵션<select name="variant_id" required><?php foreach ($product['variants'] as $variant): ?><option value="<?= $this->e($variant['id']) ?>"<?= (int) $variant['stock'] === 0 ? ' disabled' : '' ?>><?= $this->e(implode(' / ', array_filter([$variant['option1'], $variant['option2']])) ?: '기본 상품') ?> · <?= number_format((int) $variant['price']) ?>원 · <?= (int) $variant['stock'] === 0 ? '품절' : '재고 ' . (int) $variant['stock'] . '개' ?></option><?php endforeach ?></select></label>
<label>수량<input name="quantity" type="number" value="1" min="1" max="99" required></label><button<?= array_sum(array_column($product['variants'], 'stock')) === 0 ? ' disabled' : '' ?>>장바구니 담기</button></form>
<p class="muted">배송비 <?= number_format($settings['shipping']) ?>원<?php if ($settings['free_shipping'] > 0): ?> · <?= number_format($settings['free_shipping']) ?>원 이상 무료배송<?php endif ?></p><p class="muted">반품·교환은 내 주문에서 신청할 수 있습니다.</p></section></div>
<section class="panel"><h2>상품 이야기</h2><div class="pre"><?= $this->e($product['description']) ?></div></section>
<?php endif ?>
