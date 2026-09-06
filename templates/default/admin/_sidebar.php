<aside class="admin-sidebar">
  <div class="admin-sidebar-head">
    <a class="brand" href="<?= $this->url('admin.index') ?>">
      <span class="brand-logo" aria-hidden="true"><?= $this->icon('dashboard', 17) ?></span>
      <span class="admin-brand-copy">
        <strong>관리 콘솔</strong>
        <small><?= $this->e($site['site_name']) ?></small>
      </span>
    </a>
    <button class="btn btn-ghost btn-square btn-sm admin-fold" type="button" aria-expanded="true" aria-label="관리 메뉴 접기"><?= $this->icon('chevron-left', 17) ?></button>
  </div>

  <ul class="menu admin-menu">
    <li class="menu-title">운영</li>
    <li><a href="<?= $this->url('admin.index') ?>"<?php if ($section === 'dashboard'): ?> class="menu-active" aria-current="page"<?php endif ?> title="대시보드"><?= $this->icon('dashboard', 18) ?><span class="menu-text">대시보드</span></a></li>
    <li><a href="<?= $this->url('admin.members') ?>"<?php if ($section === 'members'): ?> class="menu-active" aria-current="page"<?php endif ?> title="회원 관리"><?= $this->icon('users', 18) ?><span class="menu-text">회원 관리</span></a></li>
    <li><a href="<?= $this->url('admin.boards') ?>"<?php if ($section === 'boards'): ?> class="menu-active" aria-current="page"<?php endif ?> title="게시판 관리"><?= $this->icon('board', 18) ?><span class="menu-text">게시판 관리</span></a></li>
    <li><a href="<?= $this->url('admin.content') ?>"<?php if ($section === 'content'): ?> class="menu-active" aria-current="page"<?php endif ?> title="내용 관리"><?= $this->icon('document', 18) ?><span class="menu-text">내용 관리</span></a></li>
    <li><a href="<?= $this->url('admin.terms') ?>"<?php if ($section === 'legal'): ?> class="menu-active" aria-current="page"<?php endif ?> title="약관 관리"><?= $this->icon('scale', 18) ?><span class="menu-text">약관 관리</span></a></li>
    <li><a href="<?= $this->url('admin.login_history') ?>"<?php if ($section === 'login_history'): ?> class="menu-active" aria-current="page"<?php endif ?> title="로그인 기록"><?= $this->icon('history', 18) ?><span class="menu-text">로그인 기록</span></a></li>
    <li class="menu-title">확장</li>
    <li><a href="<?= $this->url('admin.plugins') ?>"<?php if ($section === 'plugins'): ?> class="menu-active" aria-current="page"<?php endif ?> title="플러그인"><?= $this->icon('sparkle', 18) ?><span class="menu-text">플러그인</span></a></li>
    <li><a href="<?= $this->url('admin.modules') ?>"<?php if ($section === 'modules'): ?> class="menu-active" aria-current="page"<?php endif ?> title="모듈"><?= $this->icon('grid', 18) ?><span class="menu-text">모듈</span></a></li>
    <li class="menu-title">설정</li>
    <li><a href="<?= $this->url('admin.settings') ?>"<?php if ($section === 'site'): ?> class="menu-active" aria-current="page"<?php endif ?> title="사이트 설정"><?= $this->icon('cog', 18) ?><span class="menu-text">사이트 설정</span></a></li>
  </ul>
  <?php
    $releaseReady = preg_match('/^\d+\.\d+\.\d+$/D', GNUCMS_VERSION) === 1;
    $versionLabel = 'v' . GNUCMS_VERSION;
    $versionUrl = GNUCMS_REPOSITORY_URL . ($releaseReady ? '/releases/tag/v' . GNUCMS_VERSION : '');
  ?>
  <a class="admin-version" href="<?= $this->e($versionUrl) ?>" target="_blank" rel="noopener noreferrer" title="GitHub에서 GNUCMS <?= $this->e($versionLabel) ?> 보기" aria-label="GitHub에서 GNUCMS <?= $this->e($versionLabel) ?> 보기">
    <span class="admin-version-icon" aria-hidden="true"><?= $this->icon('external', 15) ?></span>
    <span class="admin-version-copy"><strong>GNUCMS</strong><small><?= $this->e($versionLabel) ?></small></span>
  </a>
</aside>
