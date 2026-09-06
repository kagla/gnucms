<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>메시지 형식 플러그인 데모</title>
<?php $this->insert('_style') ?>
</head>
<body>
<main>
  <nav aria-label="관리 메뉴"><a href="<?= $this->url('admin.plugins') ?>">플러그인 관리</a><a href="<?= $this->url('admin.modules') ?>">모듈 관리</a></nav>
  <span class="badge">플러그인 데모</span>
  <h1>메시지 형식 미리보기</h1>
  <p>제목과 내용을 입력하면 알림 형식의 문구를 만듭니다.<br>실제 메시지를 발송하거나 입력 내용을 저장하지 않습니다.</p>
  <section class="card" aria-labelledby="input-title">
    <h2 id="input-title">메시지 입력</h2>
    <?php if ($errors !== []): ?>
    <div class="errors" role="alert"><strong>입력 내용을 확인해 주세요.</strong><ul><?php foreach ($errors as $error): ?><li><?= $this->e($error) ?></li><?php endforeach ?></ul></div>
    <?php endif ?>
    <form method="post" action="<?= $this->e($form_url) ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <label for="title">제목</label>
      <input id="title" name="title" maxlength="60" required value="<?= $this->e($values['title']) ?>"<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>>
      <label for="body">내용</label>
      <textarea id="body" name="body" rows="5" maxlength="1000" required<?= isset($errors['body']) ? ' aria-invalid="true"' : '' ?>><?= $this->e($values['body']) ?></textarea>
      <button type="submit">미리보기 생성</button>
    </form>
  </section>
  <?php if ($result !== null): ?>
  <section class="card" aria-labelledby="result-title"><h2 id="result-title">생성된 메시지</h2><pre><?= $this->e($result) ?></pre></section>
  <?php endif ?>
  <small>관리자 전용 · 다른 모듈에서도 같은 메시지 형식 기능을 사용할 수 있습니다.</small>
</main>
</body>
</html>
