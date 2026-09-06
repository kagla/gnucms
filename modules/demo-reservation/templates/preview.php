<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>예약 안내문 모듈 데모</title>
<?php $this->insert('_style') ?>
</head>
<body>
<main>
  <nav aria-label="관리 메뉴"><a href="<?= $this->url('admin.modules') ?>">모듈 관리</a><a href="<?= $this->url('admin.plugins') ?>">플러그인 관리</a></nav>
  <span class="badge">모듈 데모 · <?= $uses_plugin ? '메시지 플러그인 연결됨' : '독립 실행' ?></span>
  <h1>예약 안내문 미리보기</h1>
  <?php if ($is_admin_test): ?><p class="badge">관리자 테스트 · 사용 설정은 변경되지 않습니다.</p><?php endif ?>
  <p>예약 정보로 안내문 예시를 만듭니다.<br>실제 예약을 저장하거나 메시지를 발송하지 않습니다.</p>
  <p><?= $uses_plugin ? '메시지 플러그인의 알림 형식으로 안내문을 만듭니다.' : '기본 문구로 동작합니다. 메시지 플러그인을 켜면 알림 형식이 적용됩니다.' ?></p>
  <section class="card" aria-labelledby="input-title">
    <h2 id="input-title">예약 정보 입력</h2>
    <?php if ($errors !== []): ?>
    <div class="errors" role="alert"><strong>입력 내용을 확인해 주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post" action="<?= $this->e($form_url) ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <label for="name">이름</label>
      <input id="name" name="name" maxlength="60" required value="<?= $this->e($values['name']) ?>"<?= isset($errors['name']) ? ' aria-invalid="true"' : '' ?>>
      <label for="date">예약일</label>
      <input id="date" name="date" type="date" required value="<?= $this->e($values['date']) ?>"<?= isset($errors['date']) ? ' aria-invalid="true"' : '' ?>>
      <label for="time">예약 시간</label>
      <input id="time" name="time" type="time" required value="<?= $this->e($values['time']) ?>"<?= isset($errors['time']) ? ' aria-invalid="true"' : '' ?>>
      <label for="guests">인원 (1~20명)</label>
      <input id="guests" name="guests" type="number" min="1" max="20" step="1" required value="<?= $this->e($values['guests']) ?>"<?= isset($errors['guests']) ? ' aria-invalid="true"' : '' ?>>
      <button type="submit">안내문 생성</button>
    </form>
  </section>
  <?php if ($result !== null): ?>
  <section class="card" aria-labelledby="result-title"><h2 id="result-title">생성된 안내문</h2><pre><?= $this->e($result) ?></pre></section>
  <?php endif ?>
  <small>관리자 전용 · 날짜와 인원은 안내문 예시용이며 실제 예약 가능 여부는 조회하지 않습니다.</small>
</main>
</body>
</html>
