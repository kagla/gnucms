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
<section class="card">
  <div class="card-body">
    <div class="empty-inline">
      <span class="empty-icon" aria-hidden="true"><?= $this->icon($extension_page['icon'], 24) ?></span>
      <h2><?= $this->e($extension_page['title']) ?> 관리 준비 중</h2>
      <p>목록과 사용 여부 설정 기능은 아직 제공되지 않습니다.</p>
    </div>
  </div>
</section>
<?php $this->stop() ?>
