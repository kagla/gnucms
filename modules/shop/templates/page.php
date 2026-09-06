<?php $titles = ['catalog' => '상품', 'product' => '상품 상세', 'cart' => '장바구니', 'checkout' => '주문 확인', 'orders' => '내 주문', 'order' => '주문 상세', 'admin' => '주문 관리', 'products' => '상품 관리', 'settings' => '상점 설정', 'manage-order' => '주문 처리', 'settlement' => '정산', 'inventory' => '재고']; ?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $this->e($titles[$page] ?? '쇼핑몰') ?> · <?= $this->e($settings['name']) ?></title>
<?php if (!in_array($page, ['catalog', 'product'], true)): ?><meta name="robots" content="noindex,nofollow"><?php endif ?>
<?php $this->insert('_style') ?></head><body>
<header class="shop-header"><a class="brand" href="<?= $this->e($url) ?>/catalog"><span class="brand-mark" aria-hidden="true">S</span><?= $this->e($settings['name']) ?></a><nav aria-label="쇼핑몰"><a href="<?= $this->e($url) ?>/catalog">상품</a><a href="<?= $this->e($url) ?>/cart">장바구니 <span class="count"><?= count($cart) ?></span></a><a href="<?= $this->e($url) ?>/orders">내 주문</a><?php if ($admin): ?><a href="<?= $this->e($url) ?>/admin">관리</a><?php elseif ($user === null): ?><a href="<?= $this->e($base) ?>/login">로그인</a><?php endif ?><a href="<?= $this->e($base) ?>/">사이트 홈</a></nav></header>
<?php if ($admin && in_array($page, ['admin', 'products', 'settings', 'manage-order', 'settlement', 'inventory'], true)): ?>
<nav class="admin-nav" aria-label="상점 관리"><?php foreach (['admin' => '주문', 'products' => '상품', 'inventory' => '재고', 'settlement' => '정산', 'settings' => '설정'] as $path => $label): ?><a class="<?= $page === $path ? 'active' : '' ?>" href="<?= $this->e($url . '/' . $path) ?>"><?= $this->e($label) ?></a><?php endforeach ?><a href="<?= $this->e($base) ?>/admin/plugins">결제 플러그인</a><a href="<?= $this->e($url) ?>/catalog">상점 열기 ↗</a></nav>
<?php endif ?>
<main>
<?php if ($settings['environment'] === 'test'): ?><div class="banner">테스트 상점입니다. 테스트 주문은 운영 정산에 포함되지 않습니다.</div><?php endif ?>
<?php if (!$settings['open'] && in_array($page, ['catalog', 'product', 'checkout'], true)): ?><div class="banner">지금은 판매를 준비하고 있습니다.</div><?php endif ?>
<?php foreach ($errors as $error): ?><div class="alert" role="alert"><?= $this->e($error) ?></div><?php endforeach ?>
<?php if ($notice): ?><div class="notice" role="status"><?= $this->e($notice) ?></div><?php endif ?>
<?php if (!$ready): ?><section class="panel empty"><h1>쇼핑몰을 준비합니다</h1><?php if ($admin): ?><p>상품과 주문 데이터를 설치한 뒤 상점 정보와 결제 플러그인을 설정해 주세요.</p><form method="post" action="<?= $this->e($url) ?>/admin"><?php $this->insert('_csrf') ?><button name="action" value="install">쇼핑몰 데이터 설치</button></form><?php else: ?><p>조금만 기다려 주세요.</p><?php endif ?></section>
<?php elseif ($page === 'export'): ?><p>내보내기 조건을 확인해 주세요.</p>
<?php else: ?><?php $this->insert(in_array($page, ['order', 'manage-order'], true) ? 'order' : $page) ?><?php endif ?>
</main>
<footer><strong><?= $this->e($settings['seller'] ?: $settings['name']) ?></strong><p>대표 <?= $this->e($settings['owner']) ?> · 사업자등록번호 <?= $this->e($settings['business_number']) ?> · 통신판매업 신고 <?= $this->e($settings['commerce_number']) ?></p><p><?= $this->e($settings['address']) ?> · <?= $this->e($settings['phone']) ?> · <?= $this->e($settings['email']) ?></p><p>반품 주소: <?= $this->e($settings['return_address']) ?></p><details><summary>배송·반품 정책과 개인정보 처리 안내</summary><p class="pre"><?= $this->e($settings['policy']) ?></p><p>주문자 정보는 주문 확인·배송·반품·환불 처리에 사용합니다. 주문 및 결제 내역은 판매자가 고지한 기간 동안 보관합니다.</p></details></footer>
</body></html>
