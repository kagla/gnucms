<?php if ($oauth_providers !== []): ?>
  <div class="social-list">
    <?php foreach ($oauth_providers as $provider): ?>
      <a class="btn btn-outline btn-block social-btn social-<?= $this->e($provider['key']) ?>" href="<?= $this->url('oauth.start', ['provider' => $provider['key']], empty($return_url) ? [] : ['url' => $return_url]) ?>">
        <span class="social-mark" aria-hidden="true"><?php if ($provider['key'] === 'google'): ?>G<?php elseif ($provider['key'] === 'naver'): ?>N<?php else: ?>K<?php endif ?></span>
        <?= $this->e($provider['label']) ?>로 계속하기
      </a>
    <?php endforeach ?>
  </div>
  <?php if ($site['social_registration_enabled']): ?><?php $this->insert('auth/_social_consent') ?><?php endif ?>
  <?php if ($show_email_divider ?? true): ?><div class="divider">또는 이메일로 계속</div><?php endif ?>
<?php endif ?>
