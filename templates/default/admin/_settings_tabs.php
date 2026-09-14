<nav class="tabs tabs-border settings-tabs" aria-label="사이트 설정 구분">
  <a class="tab<?= $active === 'general' ? ' tab-active' : '' ?>"<?= $active === 'general' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings') ?>">기본·홈</a>
  <a class="tab<?= $active === 'writing' ? ' tab-active' : '' ?>"<?= $active === 'writing' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.writing') ?>">회원·글쓰기</a>
  <a class="tab<?= $active === 'security' ? ' tab-active' : '' ?>"<?= $active === 'security' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.security') ?>">보안</a>
  <a class="tab<?= $active === 'oauth' ? ' tab-active' : '' ?>"<?= $active === 'oauth' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.oauth') ?>">소셜 로그인</a>
  <a class="tab<?= $active === 'mail' ? ' tab-active' : '' ?>"<?= $active === 'mail' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.mail') ?>">메일</a>
  <a class="tab<?= $active === 'messaging' ? ' tab-active' : '' ?>"<?= $active === 'messaging' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.messaging') ?>">알림톡·문자</a>
  <a class="tab<?= $active === 'payment' ? ' tab-active' : '' ?>"<?= $active === 'payment' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.payment') ?>">결제</a>
  <a class="tab<?= $active === 'maintenance' ? ' tab-active' : '' ?>"<?= $active === 'maintenance' ? ' aria-current="page"' : '' ?> href="<?= $this->url('admin.settings.maintenance') ?>">시스템·유지보수</a>
</nav>
