<div class="breadcrumbs"><ul><li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li><li aria-current="page">이니톡 결제</li></ul></div>
<div class="page-head"><div><h1>이니톡 결제</h1><p class="muted">알림톡으로 결제 링크를 보내고 카드결제 현황을 관리합니다. 현재 환경: <strong><?= $config['environment'] === 'live' ? '운영' : '테스트' ?></strong></p></div>
<div class="row-actions"><a class="btn btn-sm btn-primary" href="<?= $this->url('admin.initalk.requests.new') ?>"><?= $this->icon('plus', 14) ?> 결제 생성</a></div></div>
<?php $tabs = [['requests', 'admin.initalk.requests', '통합조회'], ['new', 'admin.initalk.requests.new', '결제 생성'], ['settings', 'admin.initalk.settings', '설정']]; ?>
<nav class="tabs tabs-border settings-tabs" aria-label="이니톡 결제 메뉴"><?php foreach ($tabs as [$key, $route, $label]): ?><a class="tab<?= $active === $key ? ' tab-active' : '' ?>" href="<?= $this->url($route) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= $this->e($label) ?></a><?php endforeach ?></nav>
<?php foreach ($errors as $error): ?><p class="errors alert alert-error" role="alert"><?= $this->e($error) ?></p><?php endforeach ?>
<?php if ($notice !== ''): ?><p class="notice alert alert-success" role="status"><?= $this->e($notice) ?></p><?php endif ?>
