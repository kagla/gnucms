<?php if ($product !== null): ?>
<p><a href="<?= $this->e($url) ?>/catalog">← 상품 목록</a></p><div class="cols">
<div class="shop-product-gallery">
<?php if ($product['images'] !== []): ?>
  <a id="shop-product-image-link" href="<?= $this->e($url) ?>/image?file=<?= $this->e($product['images'][0]['filename']) ?>" data-zoom><img id="shop-product-image" class="product-image" src="<?= $this->e($url) ?>/image?file=<?= $this->e($product['images'][0]['filename']) ?>" alt="<?= $this->e($product['name']) ?> 이미지 1"></a>
  <?php if (count($product['images']) > 1): ?><nav class="shop-gallery-thumbs" aria-label="상품 이미지">
    <?php foreach ($product['images'] as $position => $item): ?><a href="<?= $this->e($url) ?>/image?file=<?= $this->e($item['filename']) ?>" data-product-thumb<?= $position === 0 ? ' aria-current="true"' : '' ?> aria-label="상품 이미지 <?= $position + 1 ?> 보기"><img src="<?= $this->e($url) ?>/image?file=<?= $this->e($item['filename']) ?>" alt="<?= $this->e($product['name']) ?> 이미지 <?= $position + 1 ?>" loading="lazy"></a><?php endforeach ?>
  </nav><script src="<?= $this->asset('shop-gallery.js') ?>" defer></script><?php endif ?>
<?php else: ?><div class="placeholder product-image" aria-hidden="true">S</div><?php endif ?>
</div>
<section class="panel stack"><p class="eyebrow">Selected for you</p><h1><?= $this->e($product['name']) ?></h1>
<form class="stack" method="post" action="<?= $this->e($url) ?>/cart"><?php $this->insert('_csrf') ?><input type="hidden" name="action" value="add">
<label>상품 옵션<select name="variant_id" required><?php foreach ($product['variants'] as $variant): ?><option value="<?= $this->e($variant['id']) ?>"<?= (int) $variant['stock'] === 0 ? ' disabled' : '' ?>><?= $this->e(implode(' / ', array_filter([$variant['option1'], $variant['option2']])) ?: '기본 상품') ?> · <?= number_format((int) $variant['price']) ?>원 · <?= (int) $variant['stock'] === 0 ? '품절' : '재고 ' . (int) $variant['stock'] . '개' ?></option><?php endforeach ?></select></label>
<label>수량<input name="quantity" type="number" value="1" min="1" max="99" required></label><button<?= array_sum(array_column($product['variants'], 'stock')) === 0 ? ' disabled' : '' ?>>장바구니 담기</button></form>
<p class="muted">배송비 <?= number_format($settings['shipping']) ?>원<?php if ($settings['free_shipping'] > 0): ?> · <?= number_format($settings['free_shipping']) ?>원 이상 무료배송<?php endif ?></p><p class="muted">반품·교환은 내 주문에서 신청할 수 있습니다.</p></section></div>
<section class="panel"><h2>상품 이야기</h2><div class="prose rich-content shop-description"><?= $this->html($product['description']) ?></div></section>
<?php endif ?>
