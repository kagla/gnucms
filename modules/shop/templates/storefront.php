<?php $titles = ['catalog' => '상품', 'product' => '상품 상세', 'cart' => '장바구니', 'checkout' => '주문 확인', 'orders' => '내 주문', 'order' => '주문 상세', 'admin' => '주문 관리', 'products' => '상품 관리', 'settings' => '상점 설정', 'manage-order' => '주문 처리', 'settlement' => '정산', 'inventory' => '재고']; ?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $this->e($titles[$page] ?? '쇼핑몰') ?> · <?= $this->e($settings['name']) ?></title>
<?php if (!in_array($page, ['catalog', 'product'], true)): ?><meta name="robots" content="noindex,nofollow"><?php endif ?>
<?php $this->insert('_style') ?></head><body>
<header class="shop-header"><a class="brand" href="<?= $this->e($url) ?>/catalog"><span class="brand-mark" aria-hidden="true">S</span><?= $this->e($settings['name']) ?></a><nav aria-label="쇼핑몰"><a href="<?= $this->e($url) ?>/catalog">상품</a><a href="<?= $this->e($url) ?>/cart">장바구니 <span class="count"><?= count($cart) ?></span></a><a href="<?= $this->e($url) ?>/orders">내 주문</a><?php if ($admin): ?><a href="<?= $this->e($url) ?>/admin">관리</a><?php elseif ($user === null): ?><a href="<?= $this->e($base) ?>/login">로그인</a><?php endif ?><a href="<?= $this->e($base) ?>/">사이트 홈</a></nav></header>
<main>
<?php $this->insert('_content') ?>
</main>
<footer><strong><?= $this->e($settings['seller'] ?: $settings['name']) ?></strong><p>대표 <?= $this->e($settings['owner']) ?> · 사업자등록번호 <?= $this->e($settings['business_number']) ?> · 통신판매업 신고 <?= $this->e($settings['commerce_number']) ?></p><p><?= $this->e($settings['address']) ?> · <?= $this->e($settings['phone']) ?> · <?= $this->e($settings['email']) ?></p><p>반품 주소: <?= $this->e($settings['return_address']) ?></p><details><summary>배송·반품 정책과 개인정보 처리 안내</summary><p class="pre"><?= $this->e($settings['policy']) ?></p><p>주문자 정보는 주문 확인·배송·반품·환불 처리에 사용합니다. 주문 및 결제 내역은 판매자가 고지한 기간 동안 보관합니다.</p></details></footer>
</body></html>
