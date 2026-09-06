<?php
$admin_screen = $admin && in_array($page, ['admin', 'products', 'settings', 'manage-order', 'settlement', 'inventory', 'export'], true);
if (!$admin_screen) { $this->insert('storefront'); return; }
$titles = ['admin' => '주문 관리', 'products' => '상품 관리', 'settings' => '상점 설정', 'manage-order' => '주문 처리', 'settlement' => '정산', 'inventory' => '재고', 'export' => '내보내기'];
$this->layout('admin/extension');
?>
<?php $this->start('title') ?><?= $this->e($titles[$page]) ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li><a href="<?= $this->url('admin.modules') ?>">모듈</a></li><li><a href="<?= $this->e($url) ?>/admin"><?= $this->e($settings['name']) ?></a></li><li aria-current="page"><?= $this->e($titles[$page]) ?></li></ul></div>
<div class="extension-toolbar"><nav class="tabs tabs-border settings-tabs" aria-label="상점 관리"><?php foreach (['admin' => '주문', 'products' => '상품', 'inventory' => '재고', 'settlement' => '정산', 'settings' => '설정'] as $path => $label): $active = $page === $path || ($page === 'manage-order' && $path === 'admin'); ?><a class="tab<?= $active ? ' tab-active' : '' ?>" href="<?= $this->e($url . '/' . $path) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<div class="row-actions"><a class="btn btn-sm" href="<?= $this->url('admin.plugins') ?>">결제 플러그인</a><a class="btn btn-sm" href="<?= $this->e($url) ?>/catalog"><?= $this->icon('external', 15) ?> 상점 보기</a></div></div>
<?php $this->insert('_content', ['admin_screen' => true]) ?>
<?php $this->stop() ?>
