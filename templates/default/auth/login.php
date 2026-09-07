<?php $this->layout('layout') ?>
<?php $this->start('title') ?>로그인 · <?= $this->e($site['site_name']) ?><?php $this->stop() ?>
<?php $this->start('body') ?>
<div class="auth-wrap">
  <section class="card auth-card">
    <div class="card-body">
      <div class="auth-head">
        <h1 class="card-title"><?= $this->e($site['site_name']) ?>에 로그인</h1>
      </div>
      <?php if ($unverified_email !== null): ?>
        <div class="alert alert-warning alert-soft auth-notice">
          <span aria-hidden="true"><?= $this->icon('mail', 18) ?></span>
          <div>
            <strong>아직 이메일 인증이 끝나지 않았습니다.</strong>
            <p>가입 때 보낸 인증 메일의 링크를 열어야 로그인할 수 있어요. 받은편지함과 스팸함을 확인해 주세요.</p>
            <form method="post" action="<?= $this->url('auth.verify.resend') ?>">
              <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
              <input type="hidden" name="url" value="<?= $this->e($return_url ?? '') ?>">
              <input type="hidden" name="email" value="<?= $this->e($unverified_email) ?>">
              <?php $this->insert('_turnstile', ['action' => 'verification_resend', 'errors' => $errors]) ?>
              <button class="btn btn-warning btn-sm" type="submit"><?= $this->icon('mail', 15) ?> 인증 메일 다시 보내기</button>
            </form>
          </div>
        </div>
      <?php endif ?>
      <?php if ($site['social_login_enabled']): ?><?php $this->insert('auth/_social') ?><?php endif ?>
      <?php if (!$site['password_login_enabled']): ?><div class="alert alert-info alert-soft auth-notice"><span><?= $this->icon('info', 18) ?></span><span>일반 회원 로그인이 중지되어 있습니다. 관리자는 이메일로 계속 로그인할 수 있습니다.</span></div><?php endif ?>
      <form method="post" action="<?= $this->url('auth.login') ?>">
        <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
        <input type="hidden" name="url" value="<?= $this->e($return_url ?? '') ?>">
        <fieldset class="fieldset<?php if (array_key_exists('email', $errors)): ?> is-invalid<?php endif ?>">
          <legend class="fieldset-legend">이메일</legend>
          <label class="input input-bordered input-block">
            <span class="input-icon" aria-hidden="true"><?= $this->icon('mail', 16) ?></span>
            <input type="email" name="email" value="<?= $this->e($values['email'] ?? '') ?>" autocomplete="email" placeholder="you@example.com" required>
          </label>
          <?php if (array_key_exists('email', $errors)): ?><p class="validator-hint"><?= $this->icon('warning', 14) ?> <?= $this->e($errors['email']) ?></p><?php endif ?>
        </fieldset>
        <fieldset class="fieldset">
          <legend class="fieldset-legend">비밀번호</legend>
          <label class="input input-bordered input-block">
            <span class="input-icon" aria-hidden="true"><?= $this->icon('lock', 16) ?></span>
            <?php // current-password 여야 브라우저가 저장해 둔 계정을 보여 주고 채워 준다.
                  // 자동 채우기를 막고 싶으면 new-password 로 바꾸면 되지만, 그러면 저장된 계정도 안 나온다. ?>
            <input type="password" name="password" autocomplete="current-password" required>
            <?php $this->insert('auth/_pw_toggle') ?>
          </label>
        </fieldset>
        <?php if ($turnstile_required): ?><?php $this->insert('_turnstile', ['action' => 'login', 'errors' => $errors]) ?><?php endif ?>
        <button class="btn btn-primary btn-block btn-lg" type="submit">로그인</button>
      </form>
      <p class="auth-switch">
        <?php if ($registration_available): ?><span>아직 회원이 아니신가요? <a class="link" href="<?= $this->url('auth.register') ?>">회원가입</a></span>
        <span class="auth-switch-sep" aria-hidden="true"></span><?php endif ?>
        <a class="link link-hover" href="<?= $this->url('auth.forgot') ?>">비밀번호 찾기</a>
      </p>
    </div>
  </section>
</div>
<?php $this->stop() ?>
<?php $this->start('scripts') ?>
<?php $this->insert('auth/_pw_toggle_script') ?>
<?php if ($turnstile_enabled && ($turnstile_required || $unverified_email !== null)): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif ?>
<?php $this->stop() ?>
