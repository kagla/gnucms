<?php $this->layout('admin/layout') ?>
<?php $this->start('title') ?><?= $this->e($extension_page['title']) ?> · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?><?= $this->e($extension_section) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="page-head">
  <div>
    <h1><?= $this->e($extension_page['title']) ?></h1>
    <p class="muted"><?= $this->e($extension_page['description']) ?></p>
  </div>
</div>
<?php if ($extension_error !== null): ?>
<div class="alert alert-error" role="alert"><?= $this->e($extension_error) ?></div>
<?php elseif ($saved): ?>
<div class="alert alert-success" role="status">사용 여부를 저장했습니다.</div>
<?php endif ?>
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
    <table class="table">
      <thead><tr><th scope="col">이름</th><th scope="col">버전</th><th scope="col">상태</th><th scope="col">사용 여부</th></tr></thead>
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
          <td data-label="상태">
            <?php if ($package['error'] !== null): ?>
              <span class="badge badge-error badge-soft">실행 불가</span>
              <p><?= $this->e($package['error']) ?></p>
            <?php else: ?>
              <span class="badge badge-soft <?= $package['enabled'] ? 'badge-success' : 'badge-ghost' ?>"><?= $package['enabled'] ? '사용 중' : '사용 안 함' ?></span>
            <?php endif ?>
          </td>
          <td data-label="사용 여부">
            <form method="post" action="<?= $this->url('admin.' . $extension_section . '.state', ['id' => $package['id']]) ?>">
              <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
              <input type="hidden" name="enabled" value="0">
              <label>
                <input class="toggle toggle-primary" type="checkbox" role="switch" name="enabled" value="1"<?= $package['enabled'] ? ' checked' : '' ?><?= !$package['enabled'] && $package['error'] !== null ? ' disabled' : '' ?> aria-label="<?= $this->e($package['name']) ?> 사용">
                사용
              </label>
              <button class="btn btn-outline btn-sm" type="submit"<?= !$package['enabled'] && $package['error'] !== null ? ' disabled' : '' ?>>저장</button>
            </form>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</section>
<?php $this->stop() ?>
