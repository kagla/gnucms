<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="light dark">
<title><?= $this->block('title') ?></title>
<link rel="stylesheet" href="<?= $this->asset('pay.css') ?>">
</head>
<body class="pay-body">
<main class="pay-card" id="main">
<header class="pay-head"><span class="pay-store"><?= $this->e($store_name) ?></span><span class="pay-brand">이니톡 결제</span></header>
<?= $this->block('body') ?>
<footer class="pay-foot"><?php if ($support_phone !== ''): ?>고객센터 <a href="tel:<?= $this->e(preg_replace('/[^0-9]/', '', $support_phone)) ?>"><?= $this->e($support_phone) ?></a> · <?php endif ?><?= $this->e($site['site_name']) ?></footer>
</main>
<?= $this->block('scripts') ?>
</body>
</html>
