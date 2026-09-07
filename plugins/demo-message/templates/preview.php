<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>메시지 형식 미리보기 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>plugins<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'plugins', 'heading' => '메시지 형식 미리보기', 'description' => '제목과 내용으로 알림 문구를 미리 확인합니다.']) ?>
  <span class="badge badge-soft">플러그인 데모</span>
  <p>제목과 내용을 입력하면 알림 형식의 문구를 만듭니다.<br>실제 메시지를 발송하거나 입력 내용을 저장하지 않습니다.</p>
  <section class="card card-body extension-panel" aria-labelledby="input-title">
    <h2 class="card-title" id="input-title">메시지 입력</h2>
    <?php if ($errors !== []): ?>
    <div class="errors alert alert-error" role="alert"><strong>입력 내용을 확인해 주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post" action="<?= $this->e($form_url) ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <label class="extension-label" for="title">제목</label>
      <input class="input input-bordered input-block" id="title" name="title" maxlength="60" required value="<?= $this->e($values['title']) ?>"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
      <label class="extension-label" for="body">내용</label>
      <textarea class="textarea textarea-bordered textarea-block" id="body" name="body" rows="5" maxlength="1000" required<?= isset($errors['body']) ? ' aria-invalid="true"' : '' ?>><?= $this->e($values['body']) ?></textarea>
      <div class="card-actions form-actions"><button class="btn btn-primary" type="submit">미리보기 생성</button></div>
    </form>
  </section>
  <?php if ($result !== null): ?>
  <section class="card card-body extension-panel" aria-labelledby="result-title"><h2 class="card-title" id="result-title">생성된 메시지</h2><pre><?= $this->e($result) ?></pre></section>
  <?php endif ?>
  <small>관리자 전용 · 다른 모듈에서도 같은 메시지 형식 기능을 사용할 수 있습니다.</small>
<?php $this->stop() ?>
