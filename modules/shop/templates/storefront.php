<?php
$this->layout('layout');
$titles = ['catalog' => '상품', 'product' => $product['name'] ?? '상품 상세', 'cart' => '장바구니', 'checkout' => '주문 확인', 'orders' => '내 주문', 'order' => '주문 상세'];
$section = in_array($page, ['product', 'catalog'], true) ? 'catalog' : (in_array($page, ['order', 'orders'], true) ? 'orders' : 'cart');
?>
<?php $this->start('title') ?><?= $this->e($titles[$page] ?? '쇼핑몰') ?> · <?= $this->e($settings['name']) ?><?php $this->stop() ?>
<?php $this->start('nav_section') ?>modules/shop<?php $this->stop() ?>
<?php $this->start('body_class') ?>shop-page<?php $this->stop() ?>
<?php $this->start('seo_meta') ?>
<?php if (!in_array($page, ['catalog', 'product'], true)): ?><meta name="robots" content="noindex,nofollow"><?php endif ?>
<?php $this->insert('_style') ?>
<?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="shop-storefront">
  <header class="shop-header">
    <a class="shop-brand" href="<?= $this->e($public_url) ?>/catalog"><?= $this->icon('gift', 24) ?> <?= $this->e($settings['name']) ?></a>
    <nav aria-label="쇼핑몰">
      <?php foreach (['catalog' => '상품', 'cart' => '장바구니', 'orders' => '내 주문'] as $path => $label): ?>
        <a href="<?= $this->e($url . '/' . $path) ?>"<?= $section === $path ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?><?php if ($path === 'cart'): ?> <span class="count" aria-label="<?= count($cart) ?>종류"><?= count($cart) ?></span><?php endif ?></a>
      <?php endforeach ?>
      <?php if ($admin): ?><a href="<?= $this->e($admin_url) ?>">상점 관리</a><?php elseif ($user === null): ?><a href="<?= $this->e($url) ?>/login">로그인</a><?php endif ?>
    </nav>
  </header>
  <div class="shop-main"><?php $this->insert('_content') ?></div>
  <?php $this->insert('_seller') ?>
</div>
<?php $this->stop() ?>
