<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>결제 결과 확인</title></head><body>
<main><h1>결제 결과를 확인하고 있습니다</h1><p>잠시 기다려 주세요. 화면이 이동하지 않으면 아래 버튼을 눌러 주세요.</p>
<form id="toss-confirm" method="post" action="<?= $this->e($action) ?>">
<?php foreach ($fields as $name => $value): ?><input type="hidden" name="<?= $this->e($name) ?>" value="<?= $this->e($value) ?>"><?php endforeach ?>
<button type="submit">결제 결과 확인</button></form></main>
<script nonce="<?= $this->e($nonce) ?>">history.replaceState(null,'',location.pathname);document.getElementById('toss-confirm').submit();</script>
</body></html>
