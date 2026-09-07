<?php $this->layout('admin/extension') ?>
<?php $this->start('title') ?>예약 안내문 미리보기 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('admin_section') ?>modules<?php $this->stop() ?>
<?php $this->start('extension_body') ?>
<?php $this->insert('admin/_extension_header', ['section' => 'modules', 'heading' => '예약 안내문 미리보기', 'description' => '예약 정보로 안내문 예시를 만듭니다.']) ?>
  <span class="badge badge-soft">모듈 데모 · <?= $uses_plugin ? '메시지 플러그인 연결됨' : '독립 실행' ?></span>
  <?php if ($is_admin_test): ?><p class="badge badge-soft">관리자 테스트 · 사용 설정은 변경되지 않습니다.</p><?php endif ?>
  <p>예약 정보로 안내문 예시를 만듭니다.<br>실제 예약을 저장하거나 메시지를 발송하지 않습니다.</p>
  <p><?= $uses_plugin ? '메시지 플러그인의 알림 형식으로 안내문을 만듭니다.' : '기본 문구로 동작합니다. 메시지 플러그인을 켜면 알림 형식이 적용됩니다.' ?></p>
  <section class="card card-body extension-panel" aria-labelledby="input-title">
    <h2 class="card-title" id="input-title">예약 정보 입력</h2>
    <?php if ($errors !== []): ?>
    <div class="errors alert alert-error" role="alert"><strong>입력 내용을 확인해 주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post" action="<?= $this->e($form_url) ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <label class="extension-label" for="name">이름</label>
      <input class="input input-bordered input-block" id="name" name="name" maxlength="60" required value="<?= $this->e($values['name']) ?>"<?= isset($errors['name']) ? ' aria-invalid="true"' : '' ?>>
      <label class="extension-label" for="date">예약일</label>
      <input class="input input-bordered input-block" id="date" name="date" type="date" required value="<?= $this->e($values['date']) ?>"<?= isset($errors['date']) ? ' aria-invalid="true"' : '' ?>>
      <label class="extension-label" for="time">예약 시간</label>
      <input class="input input-bordered input-block" id="time" name="time" type="time" required value="<?= $this->e($values['time']) ?>"<?= isset($errors['time']) ? ' aria-invalid="true"' : '' ?>>
      <label class="extension-label" for="guests">인원 (1~20명)</label>
      <input class="input input-bordered input-block" id="guests" name="guests" type="number" min="1" max="20" step="1" required value="<?= $this->e($values['guests']) ?>"<?= isset($errors['guests']) ? ' aria-invalid="true"' : '' ?>>
      <div class="card-actions form-actions"><button class="btn btn-primary" type="submit">안내문 생성</button></div>
    </form>
  </section>
  <?php if ($result !== null): ?>
  <section class="card card-body extension-panel" aria-labelledby="result-title"><h2 class="card-title" id="result-title">생성된 안내문</h2><pre><?= $this->e($result) ?></pre></section>
  <?php endif ?>
  <small>관리자 전용 · 날짜와 인원은 안내문 예시용이며 실제 예약 가능 여부는 조회하지 않습니다.</small>
<?php $this->stop() ?>
