<div class="breadcrumbs"><ul>
  <li><a href="<?= $this->url('admin.index') ?>">사이트 관리</a></li>
  <li><a href="<?= $this->url('admin.' . $section) ?>"><?= $section === 'plugins' ? '플러그인' : '모듈' ?></a></li>
  <li aria-current="page"><?= $this->e($heading) ?></li>
</ul></div>
<div class="page-head">
  <div><h1><?= $this->e($heading) ?></h1><?php if (($description ?? '') !== ''): ?><p class="muted"><?= $this->e($description) ?></p><?php endif ?></div>
  <?php if (($actions ?? []) !== []): ?><div class="row-actions"><?php foreach ($actions as $action): ?><a class="btn btn-sm" href="<?= $this->e($action['url']) ?>"><?= $this->e($action['label']) ?></a><?php endforeach ?></div><?php endif ?>
</div>
