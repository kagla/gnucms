<?php if ($settings['environment'] === 'test'): ?><div class="banner<?= !empty($admin_screen) ? ' alert alert-warning' : '' ?>">테스트 상점입니다. 테스트 주문은 운영 정산에 포함되지 않습니다.</div><?php endif ?>
<?php if (!$settings['open'] && in_array($page, ['catalog', 'product', 'checkout'], true)): ?><div class="banner<?= !empty($admin_screen) ? ' alert alert-warning' : '' ?>">지금은 판매를 준비하고 있습니다.</div><?php endif ?>
<?php foreach ($errors as $error): ?><div class="alert alert-error" role="alert"><?= $this->e($error) ?></div><?php endforeach ?>
<?php if ($notice): ?><div class="notice<?= !empty($admin_screen) ? ' alert alert-success' : '' ?>" role="status"><?= $this->e($notice) ?></div><?php endif ?>
<?php if (!$ready): ?><section class="panel empty<?= !empty($admin_screen) ? ' card card-body extension-panel' : '' ?>"><h1>쇼핑몰을 준비합니다</h1><?php if ($admin): ?><p>상품과 주문 데이터를 설치한 뒤 상점 정보와 결제 플러그인을 설정해 주세요.</p><form method="post" action="<?= $this->e($url) ?>/admin"><?php $this->insert('_csrf') ?><button class="btn btn-primary" name="action" value="install">쇼핑몰 데이터 설치</button></form><?php else: ?><p>조금만 기다려 주세요.</p><?php endif ?></section>
<?php elseif ($page === 'export'): ?><p>내보내기 조건을 확인해 주세요.</p>
<?php else: ?><?php $this->insert(in_array($page, ['order', 'manage-order'], true) ? 'order' : $page) ?><?php endif ?>
