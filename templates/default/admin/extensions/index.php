<?php $this->layout('admin/layout') ?>
<?php $this->start('title') ?><?= $this->e($extension_page['title']) ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?><?= $this->e($extension_section) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="page-head">
  <div>
    <h1><?= $this->e($extension_page['title']) ?></h1>
    <p class="muted"><?= $this->e($extension_page['description']) ?></p>
    <p class="muted">최근 사용 상태를 변경한 순서로 표시합니다.</p>
  </div>
  <?php if ($packages !== []): ?><div class="page-head-actions"><button class="btn btn-primary" type="submit" form="extension-state-form">저장</button></div><?php endif ?>
</div>
<?php if ($extension_error !== null): ?>
<div class="alert alert-error" role="alert"><?= $this->e($extension_error) ?></div>
<?php elseif ($saved): ?>
<div class="alert alert-success" role="status">사용 여부를 저장했습니다.</div>
<?php endif ?>
<form id="extension-state-form" method="post" action="<?= $this->url('admin.' . $extension_section . '.save') ?>">
<input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
<input type="hidden" name="changed_order" value="<?= $this->e($changed_order) ?>">
<section class="card">
  <?php if ($packages === []): ?>
  <div class="card-body">
    <div class="empty-inline">
      <span class="empty-icon" aria-hidden="true"><?= $this->icon($extension_page['icon'], 24) ?></span>
      <?php if ($extension_error !== null): ?>
      <h2>목록을 불러오지 못했습니다</h2>
      <p>오류를 확인한 후 다시 시도해 주세요.</p>
      <?php else: ?>
      <h2>등록된 <?= $this->e($extension_page['title']) ?>이 없습니다</h2>
      <p>패키지가 추가되면 이곳에서 사용 여부를 설정할 수 있습니다.</p>
      <?php endif ?>
    </div>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table extensions-table">
      <thead><tr><th scope="col">이름</th><th scope="col">버전</th><th scope="col">사용 상태</th></tr></thead>
      <tbody>
      <?php foreach ($packages as $package): ?>
        <tr>
          <td data-label="이름">
            <strong><?= $this->e($package['name']) ?></strong>
            <p><?= $this->e($package['description']) ?></p>
            <?php if ($package['requires'] !== []): ?><small>필수 확장: <?= $this->e(implode(', ', $package['requires'])) ?></small><?php endif ?>
            <?php if ($package['optional'] !== []): ?><p><small>선택 확장: <?= $this->e(implode(', ', $package['optional'])) ?></small></p><?php endif ?>
          </td>
          <td data-label="버전"><?= $this->e($package['version']) ?></td>
          <td data-label="사용 상태">
            <input type="hidden" name="original[<?= $this->e($package['id']) ?>]" value="<?= $package['enabled'] ? '1' : '0' ?>">
            <input type="hidden" name="enabled[<?= $this->e($package['id']) ?>]" value="0">
            <label class="extension-state-toggle">
              <input class="toggle toggle-primary" type="checkbox" role="switch" name="enabled[<?= $this->e($package['id']) ?>]" value="1"<?= $package['selected'] ? ' checked' : '' ?><?= !$package['enabled'] && $package['error'] !== null ? ' disabled' : '' ?> data-extension-id="<?= $this->e($package['id']) ?>" aria-label="<?= $this->e($package['name']) ?> 사용">
              <span data-enabled-label><?= $package['selected'] ? '사용' : '미사용' ?></span>
            </label>
            <?php if ($package['error'] !== null): ?>
              <p><span class="badge badge-error badge-soft">실행 불가</span> <?= $this->e($package['error']) ?></p>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</section>
<input type="hidden" name="complete" value="1">
<?php if ($packages !== []): ?>
<div class="card-actions form-actions"><button class="btn btn-primary" type="submit">저장</button></div>
<?php endif ?>
</form>
<script>
(function () {
  var form = document.getElementById('extension-state-form');
  if (!form) return;
  var order = form.elements.namedItem('changed_order');
  form.addEventListener('change', function (event) {
    var toggle = event.target;
    if (!toggle.matches('[data-extension-id]')) return;
    var id = toggle.getAttribute('data-extension-id');
    var recent = JSON.parse(order.value);
    order.value = JSON.stringify([id].concat(recent.filter(function (item) { return item !== id; })));
    toggle.parentElement.querySelector('[data-enabled-label]').textContent = toggle.checked ? '사용' : '미사용';
  });
})();
</script>
<?php $this->stop() ?>
