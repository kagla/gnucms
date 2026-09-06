<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f6f7fb" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b0f19" media="(prefers-color-scheme: dark)">
<title><?php $this->start('title') ?><?= $this->e($site['site_name']) ?><?php $this->stop() ?></title>
<?php $this->start('meta_description') ?><meta name="description" content="<?= $this->e($site['site_tagline']) ?>"><?php $this->stop() ?>
<?php $this->start('seo_meta') ?><?php $this->stop() ?>
<?php $this->start('feed_links') ?><link rel="alternate" type="application/rss+xml" title="<?= $this->e($site['site_name']) ?> RSS" href="<?= $this->e($site_url) ?>/rss.xml"><?php $this->stop() ?>
<?php $this->start('external_service_head') ?>
<?php foreach (['site_verification_html', 'analytics_html', 'adsense_html'] as $headSetting): ?>
<?php if (($site[$headSetting] ?? '') !== ''): ?><?= (string) $site[$headSetting] ?>
<?php endif ?>
<?php endforeach ?>
<?php $this->stop() ?>
<script>
(function(){
  var d=document.documentElement,t=null;
  try{t=localStorage.getItem('<?= $this->e(GNUCMS_ID) ?>-theme')}catch(e){}
  if(t==='light'||t==='dark'){d.setAttribute('data-theme',t)}
  var dark=t==='dark'||(t!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches);
  d.setAttribute('data-theme-mode',dark?'dark':'light');
  try{if(localStorage.getItem('<?= $this->e(GNUCMS_ID) ?>-admin-sidebar')==='collapsed'){d.dataset.adminSidebar='collapsed'}}catch(e){}
})();
</script>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<?php // daisyUI 5 는 빌드 없이 그대로 링크할 수 있는 CSS 를 낸다.
      // 판(리셋)과 컴포넌트는 여기서 오고, 테마 색·전용 화면은 아래 theme.css 가 얹는다.
      // theme.css 는 레이어 밖이라 언제나 이긴다. ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daisyui@5.7.22/daisyui.css">
<?php // 본문 글꼴은 웹폰트를 쓰지 않는다.
      // 웹폰트를 받아 오면 도착 시점에 따라 글자 크기가 바뀌거나(swap = FOUT),
      // 캐시 상태에 따라 화면마다 다른 글꼴로 나온다(optional). 둘 다 눈에 띈다.
      // 시스템 한글 글꼴만 쓰면 캐시와 무관하게 언제나 같은 화면이고, 받을 것도 없다.
      // Pretendard 가 기기에 설치돼 있으면 --font 의 맨 앞이라 그대로 쓰인다. ?>
<link rel="stylesheet" href="<?= $this->asset('theme.css') ?>">
</head>
<body class="<?php $this->start('body_class') ?><?php $this->stop() ?>" data-section="<?php $this->start('nav_section') ?><?php $this->stop() ?>">
<a class="skip-link btn btn-primary btn-sm" href="#main">본문 바로가기</a>
<?php $this->start('chrome') ?>
<div class="drawer">
  <input id="nav-drawer" type="checkbox" class="drawer-toggle" aria-label="전체 메뉴 열기">
  <div class="drawer-content">

    <?php $this->start('site_header') ?>

    <header class="site-header">
      <div class="navbar wrap">
        <div class="navbar-start">
          <label for="nav-drawer" class="btn btn-ghost btn-square drawer-button" aria-label="메뉴 열기"><?= $this->icon('menu', 21) ?></label>
          <a class="brand" href="<?= $this->url('boards.index') ?>">
            <span class="brand-logo" aria-hidden="true"><?= $this->icon('brand', 19) ?></span>
            <span class="brand-name"><?= $this->e($site['site_name']) ?></span>
          </a>
        </div>

        <?php // 오른쪽은 아이콘 줄 하나로 모은다. 예전 맨 윗줄에 있던 길도 여기로 들어왔다. ?>
        <div class="navbar-end">
          <label for="search-modal" class="btn btn-ghost btn-circle" role="button" tabindex="0"
                 aria-label="검색 열기" title="검색 (/)"><?= $this->icon('search', 20) ?></label>

          <?php if ($current_user['is_guest']): ?>
            <a class="btn btn-ghost btn-sm hide-sm" href="<?= $this->url('auth.login') ?>">로그인</a>
            <?php if ($registration_available): ?><a class="btn btn-primary btn-sm hide-sm" href="<?= $this->url('auth.register') ?>">회원가입</a><?php endif ?>
          <?php else: ?>
            <a class="btn btn-ghost btn-circle bell-link" href="<?= $this->url('notifications.index') ?>"
               aria-label="알림<?php if ($unread_notifications > 0): ?> <?= $this->e($unread_notifications) ?>개<?php endif ?>" title="알림">
              <?= $this->icon('bell', 20) ?>
              <?php if ($unread_notifications > 0): ?><span class="bell-dot" aria-hidden="true"><?= $this->e($unread_notifications > 99 ? '99+' : $unread_notifications) ?></span><?php endif ?>
            </a>
            <?php if ($current_user['is_admin']): ?>
              <a class="btn btn-ghost btn-circle admin-link hide-sm" href="<?= $this->url('admin.index') ?>"
                 aria-label="관리 콘솔" title="관리 콘솔"><?= $this->icon('cog', 20) ?></a>
            <?php endif ?>
            <div class="dropdown dropdown-end hide-sm">
              <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar avatar-placeholder" aria-label="<?= $this->e($current_user['display_name']) ?> 메뉴">
                <div class="avatar-inner" data-tone="<?= $this->e(mb_strlen((string) $current_user['display_name']) % 6) ?>"><?php if (!empty($current_user['avatar_file'])): ?><img src="<?= $this->url('avatar.show', ['file' => $current_user['avatar_file']]) ?>" alt=""><?php else: ?><span><?= $this->e(mb_strtoupper(mb_substr((string) $current_user['display_name'], 0, 1))) ?></span><?php endif ?></div>
              </div>
              <ul tabindex="0" class="dropdown-content menu rounded-box shadow-lg user-menu">
                <li class="menu-title"><?= $this->e($current_user['display_name']) ?></li>
                <li><a href="<?= $this->url('notifications.index') ?>"><?= $this->icon('bell', 17) ?> 알림</a></li>
                <?php if ($current_user['is_admin']): ?><li><a href="<?= $this->url('admin.index') ?>"><?= $this->icon('cog', 17) ?> 관리 콘솔</a></li><?php endif ?>
                <li><a href="<?= $this->url('account.edit') ?>"><?= $this->icon('user', 17) ?> 회원정보 수정</a></li>
                <?php if (isset($public_extensions['modules/shop'])): ?><li><a href="<?= $this->e($public_extensions['modules/shop']['base_url']) ?>/orders"><?= $this->icon('gift', 17) ?> 내 주문</a></li><?php endif ?>
                <?php if ($current_user['is_admin']): ?><li><a href="<?= $this->url('admin.login_history') ?>"><?= $this->icon('history', 17) ?> 로그인 기록</a></li><?php endif ?>
                <li>
                  <form method="post" action="<?= $this->url('auth.logout') ?>">
                    <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
                    <button type="submit"><?= $this->icon('logout', 17) ?> 로그아웃</button>
                  </form>
                </li>
              </ul>
            </div>
          <?php endif ?>

          <button class="btn btn-ghost btn-circle theme-toggle" type="button" data-theme-toggle aria-label="다크 모드로 전환">
            <span class="theme-ico theme-ico-light"><?= $this->icon('sun', 19) ?></span>
            <span class="theme-ico theme-ico-dark"><?= $this->icon('moon', 19) ?></span>
          </button>
        </div>
      </div>

      <?php // 카테고리 줄. 왼쪽의 '전체' 는 서랍을 여는 단추다. ?>
      <?php
      $currentBoardKey = (string) ($board['board_key'] ?? '');
      $currentBoardInHeader = false;
      foreach ($header_boards as $headerBoard) {
          if ($currentBoardKey !== '' && $headerBoard['board_key'] === $currentBoardKey) {
              $currentBoardInHeader = true;
              break;
          }
      }
      ?>
      <div class="header-tabs">
        <div class="wrap">
          <label for="nav-drawer" class="gnb-all" role="button" tabindex="0"><?= $this->icon('grid', 15) ?> 전체</label>
          <nav class="tabs tabs-border" aria-label="주요 메뉴">
            <a class="tab<?php if (trim($this->block('nav_section')) === 'home'): ?> tab-active<?php endif ?>" href="<?= $this->url('boards.index') ?>"<?php if (trim($this->block('nav_section')) === 'home'): ?> aria-current="page"<?php endif ?>>홈</a>
            <a class="tab<?php if (trim($this->block('nav_section')) === 'all'): ?> tab-active<?php endif ?>" href="<?= $this->url('posts.all') ?>"<?php if (trim($this->block('nav_section')) === 'all'): ?> aria-current="page"<?php endif ?>>전체 글</a>
            <?php foreach ($public_extensions ?? [] as $key => $extension): $selected = trim($this->block('nav_section')) === $key; ?>
              <a class="tab<?= $selected ? ' tab-active' : '' ?>" href="<?= $this->e($extension['url']) ?>"<?= $selected ? ' aria-current="page"' : '' ?>><?= $this->e($extension['name']) ?></a>
            <?php endforeach ?>
            <?php if (!$currentBoardInHeader): ?><?php $this->start('extra_tabs') ?><?php $this->stop() ?><?php endif ?>
            <?php foreach ($header_boards as $item): ?>
              <?php $isCurrentBoard = $currentBoardKey !== '' && $currentBoardKey === $item['board_key']; ?>
              <a class="tab<?= $isCurrentBoard ? ' tab-active' : '' ?>" href="<?= $this->url('posts.index', ['key' => $item['board_key']]) ?>"<?= $isCurrentBoard ? ' aria-current="page"' : '' ?>><?= $this->e($item['name']) ?></a>
            <?php endforeach ?>
            <?php foreach ($site_menu as $item): ?><a class="tab" href="<?= $this->url('content.show', ['slug' => $item['slug']]) ?>"><?= $this->e($item['title']) ?></a><?php endforeach ?>
          </nav>
        </div>
      </div>
    </header>

    <?php // 검색창은 돋보기를 눌러야 열린다. 서랍과 같은 체크박스 방식이라
          // JavaScript 가 꺼져 있어도 열리고 닫힌다. ?>
    <input id="search-modal" type="checkbox" class="search-toggle" aria-label="검색 열기">
    <div class="search-modal">
      <label for="search-modal" class="search-modal-overlay" aria-label="검색 닫기"></label>
      <div class="search-modal-panel" role="dialog" aria-label="검색" aria-modal="true">
        <div class="search-modal-head">
          <strong>검색</strong>
          <label for="search-modal" class="btn btn-ghost btn-square btn-sm" role="button" tabindex="0" aria-label="검색 닫기"><?= $this->icon('close', 18) ?></label>
        </div>
        <?php $this->start('header_search') ?>
          <?php // 게시판 문맥이 있는 화면(글 보기·쓰기·고치기)은 그 게시판을 검색하고,
                // 문맥이 없는 화면(알림·계정·인증 등)은 전체 글을 검색한다.
                // home 과 posts/index 는 이 블록을 자기 것으로 덮어쓴다. ?>
          <?php if (isset($board['board_key'])): ?>
            <form class="header-search" method="get" action="<?= $this->url('posts.index', ['key' => $board['board_key']]) ?>" role="search">
              <label class="input input-bordered">
                <span class="input-icon" aria-hidden="true"><?= $this->icon('search', 18) ?></span>
                <input type="search" name="q" value="" placeholder="<?= $this->e($board['name']) ?>에서 검색해 보세요" aria-label="게시글 검색" data-search-input>
              </label>
              <button class="btn btn-primary header-search-btn" type="submit">검색</button>
            </form>
          <?php else: ?>
            <form class="header-search" method="get" action="<?= $this->url('posts.all') ?>" role="search">
              <label class="input input-bordered">
                <span class="input-icon" aria-hidden="true"><?= $this->icon('search', 18) ?></span>
                <input type="search" name="q" value="" placeholder="모든 게시판에서 검색해 보세요" aria-label="전체 글 검색" data-search-input>
              </label>
              <button class="btn btn-primary header-search-btn" type="submit">검색</button>
            </form>
          <?php endif ?>
        <?php $this->stop() ?>
      </div>
    </div>
    <?php $this->stop() ?>

    <?php $this->start('subnav') ?><?php $this->stop() ?>

    <main class="wrap main-area" id="main"><?php $this->start('body') ?><?php $this->stop() ?></main>

    <?php $this->start('site_footer') ?>
    <footer class="footer">
      <div class="wrap footer-inner">
        <aside class="footer-brand">
          <span class="brand-logo brand-logo-sm" aria-hidden="true"><?= $this->icon('brand', 16) ?></span>
          <div>
            <strong><?= $this->e($site['site_name']) ?></strong>
            <p><?= $this->e($site['site_tagline']) ?></p>
          </div>
        </aside>
        <nav class="footer-nav" aria-label="사이트 메뉴">
          <a class="link link-hover" href="<?= $this->url('boards.index') ?>">홈</a>
          <a class="link link-hover" href="<?= $this->url('seo.rss') ?>">RSS</a>
          <?php // '상단 메뉴에 표시' 는 말 그대로 상단 메뉴다. 하단에는 약관만 모아 둔다. ?>
          <?php // 약관은 으레 하단에 모아 둔다. 공개된 약관은 사용처와 무관하게 전부 나온다. ?>
          <?php foreach ($legal_pages as $doc): ?>
            <a class="link link-hover" href="<?= $this->url('terms.show', ['slug' => $doc['slug']]) ?>"><?= $this->e($doc['title']) ?></a>
          <?php endforeach ?>
        </nav>
        <p class="footer-note">
          <?= $this->e($site['site_name']) ?> 은 회원이 직접 올린 글과 사진으로 채워집니다.
          각 글의 저작권은 글쓴이에게 있으며, 신고가 접수된 글은 운영 기준에 따라 처리됩니다.
        </p>
      </div>
    </footer>
    <?php $this->stop() ?>

    <nav class="dock" aria-label="빠른 이동">
      <a href="<?= $this->url('boards.index') ?>"<?php if (trim($this->block('nav_section')) === 'home'): ?> class="dock-active" aria-current="page"<?php endif ?>>
        <?= $this->icon('home', 21) ?><span class="dock-label">홈</span>
      </a>
      <label for="nav-drawer" role="button" tabindex="0">
        <?= $this->icon('grid', 21) ?><span class="dock-label">카테고리</span>
      </label>
      <?php if ($current_user['is_guest']): ?>
        <a href="<?= $this->url('auth.login') ?>"><?= $this->icon('user', 21) ?><span class="dock-label">로그인</span></a>
      <?php else: ?>
        <a href="<?= $this->url('notifications.index') ?>" class="bell-link">
          <?= $this->icon('bell', 21) ?><span class="dock-label">알림</span>
          <?php if ($unread_notifications > 0): ?><span class="bell-dot" aria-hidden="true"><?= $this->e($unread_notifications > 99 ? '99+' : $unread_notifications) ?></span><?php endif ?>
        </a>
        <?php // '내 계정' 은 회원정보 수정으로. 예전엔 홈으로 보내 눌러도 아무 일이 없어 보였다.
              // 관리 콘솔은 머리글 톱니와 서랍에 있으니 독에서는 누구나 같은 자리로 간다. ?>
        <a href="<?= $this->url('account.edit') ?>"<?php if (trim($this->block('nav_section')) === 'account'): ?> class="dock-active" aria-current="page"<?php endif ?>>
          <?= $this->icon('user', 21) ?><span class="dock-label">내 계정</span>
        </a>
      <?php endif ?>
    </nav>
  </div>

  <div class="drawer-side">
    <label for="nav-drawer" class="drawer-overlay" aria-label="메뉴 닫기"></label>
    <div class="drawer-panel">
      <div class="drawer-head">
        <span class="brand">
          <span class="brand-logo" aria-hidden="true"><?= $this->icon('brand', 18) ?></span>
          <span class="brand-name"><?= $this->e($site['site_name']) ?></span>
        </span>
        <?php // 화면 테마 토글은 머리글에 있다. 서랍은 머리글 위에 열리므로 여기 또 두면 둘이 나란히 보인다. ?>
        <label for="nav-drawer" class="btn btn-ghost btn-square btn-sm" aria-label="메뉴 닫기"><?= $this->icon('close', 18) ?></label>
      </div>

      <?php if (!$current_user['is_guest']): ?>
        <div class="drawer-user">
          <div class="avatar avatar-placeholder">
            <div class="avatar-inner" data-tone="<?= $this->e(mb_strlen((string) $current_user['display_name']) % 6) ?>"><?php if (!empty($current_user['avatar_file'])): ?><img src="<?= $this->url('avatar.show', ['file' => $current_user['avatar_file']]) ?>" alt=""><?php else: ?><span><?= $this->e(mb_strtoupper(mb_substr((string) $current_user['display_name'], 0, 1))) ?></span><?php endif ?></div>
          </div>
          <div>
            <strong><?= $this->e($current_user['display_name']) ?></strong>
            <span class="badge badge-soft badge-sm"><?= $this->e($current_user['is_admin'] ? '사이트 소유자' : '회원') ?></span>
          </div>
        </div>
      <?php endif ?>

      <ul class="menu">
        <li class="menu-title">둘러보기</li>
        <li><a href="<?= $this->url('boards.index') ?>"><?= $this->icon('home', 18) ?> 홈</a></li>
        <?php foreach ($public_extensions ?? [] as $extension): ?><li><a href="<?= $this->e($extension['url']) ?>"><?= $this->icon('gift', 18) ?> <?= $this->e($extension['name']) ?></a></li><?php endforeach ?>
        <?php foreach (($boards ?? []) as $navBoard): ?>
          <li><a href="<?= $this->url('posts.index', ['key' => $navBoard['board_key']]) ?>"><?= $this->icon('board', 18) ?> <?= $this->e($navBoard['name']) ?></a></li>
        <?php endforeach ?>
        <?php if (!empty($site_menu)): ?><li class="menu-title">안내</li><?php endif ?>
        <?php foreach ($site_menu as $item): ?><li><a href="<?= $this->url('content.show', ['slug' => $item['slug']]) ?>"><?= $this->icon('document', 18) ?> <?= $this->e($item['title']) ?></a></li><?php endforeach ?>
        <?php if (!$current_user['is_guest']): ?>
          <li class="menu-title">내 활동</li>
          <?php if (isset($public_extensions['modules/shop'])): ?><li><a href="<?= $this->e($public_extensions['modules/shop']['base_url']) ?>/orders"><?= $this->icon('gift', 18) ?> 내 주문</a></li><?php endif ?>
          <li><a href="<?= $this->url('notifications.index') ?>"><?= $this->icon('bell', 18) ?> 알림<?php if ($unread_notifications > 0): ?> <span class="badge badge-primary badge-sm"><?= $this->e($unread_notifications) ?></span><?php endif ?></a></li>
          <li><a href="<?= $this->url('account.edit') ?>"><?= $this->icon('user', 18) ?> 회원정보 수정</a></li>
          <?php if ($current_user['is_admin']): ?><li><a href="<?= $this->url('admin.login_history') ?>"><?= $this->icon('history', 18) ?> 로그인 기록</a></li><?php endif ?>
          <?php if ($current_user['is_admin']): ?><li><a href="<?= $this->url('admin.index') ?>"><?= $this->icon('cog', 18) ?> 관리 콘솔</a></li><?php endif ?>
        <?php endif ?>
      </ul>


      <div class="drawer-foot">
        <?php if ($current_user['is_guest']): ?>
          <a class="btn btn-outline btn-block" href="<?= $this->url('auth.login') ?>">로그인</a>
          <?php if ($registration_available): ?><a class="btn btn-primary btn-block" href="<?= $this->url('auth.register') ?>">회원가입</a><?php endif ?>
        <?php else: ?>
          <form method="post" action="<?= $this->url('auth.logout') ?>">
            <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
            <button class="btn btn-outline btn-block" type="submit"><?= $this->icon('logout', 16) ?> 로그아웃</button>
          </form>
        <?php endif ?>
      </div>
    </div>
  </div>
</div>
<?php $this->stop() ?>

<button class="btn btn-circle to-top" type="button" hidden aria-label="맨 위로 이동"><?= $this->icon('arrow-up', 18) ?></button>

<script>
(function(){
  var root=document.documentElement,mq=window.matchMedia('(prefers-color-scheme: dark)');
  function stored(){try{return localStorage.getItem('<?= $this->e(GNUCMS_ID) ?>-theme')}catch(e){return null}}
  /* 저장된 값이 없으면 첫 방문 동안만 OS 설정을 따르고,
     한 번이라도 아이콘을 누르면 그때부터 라이트/다크로 고정된다. */
  function isDark(){var t=stored();return t==='dark'||(t!=='light'&&mq.matches)}
  function apply(){
    var t=stored(),dark=isDark();
    if(t==='light'||t==='dark'){root.setAttribute('data-theme',t)}else{root.removeAttribute('data-theme')}
    root.setAttribute('data-theme-mode',dark?'dark':'light');
    var btns=document.querySelectorAll('[data-theme-toggle]');
    for(var i=0;i<btns.length;i++){
      var label=dark?'라이트 모드로 전환':'다크 모드로 전환';
      btns[i].setAttribute('aria-label',label);
      btns[i].setAttribute('aria-pressed',dark?'true':'false');
      btns[i].title=label;
    }
    document.dispatchEvent(new CustomEvent('<?= $this->e(GNUCMS_ID) ?>:theme',{detail:{dark:dark}}));
  }
  var toggles=document.querySelectorAll('[data-theme-toggle]');
  for(var j=0;j<toggles.length;j++){
    toggles[j].addEventListener('click',function(){
      var next=isDark()?'light':'dark';
      try{localStorage.setItem('<?= $this->e(GNUCMS_ID) ?>-theme',next)}catch(e){}
      apply();
    });
  }
  if(!stored()){
    if(mq.addEventListener){mq.addEventListener('change',apply)}else if(mq.addListener){mq.addListener(apply)}
  }
  apply();

  /* 검색창은 체크박스로 열린다. 여는 것 자체는 CSS 가 하고,
     스크립트는 초점·스크롤 잠금·Esc 만 거든다. */
  var search=document.getElementById('search-modal');
  function setSearch(open){
    if(!search){return}
    search.checked=open;
    document.body.classList.toggle('is-locked',open);
    if(open){
      var f=document.querySelector('.search-modal [data-search-input], .search-modal input[type="search"]');
      if(f){window.setTimeout(function(){f.focus();f.select()},30)}
    }
  }
  if(search){
    search.addEventListener('change',function(){setSearch(search.checked)});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&search.checked){setSearch(false)}});
  }

  var drawer=document.getElementById('nav-drawer');
  if(drawer){
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&drawer.checked){drawer.checked=false}});
    drawer.addEventListener('change',function(){document.body.classList.toggle('is-locked',drawer.checked)});
  }

  var toTop=document.querySelector('.to-top'),tick=false;
  if(toTop){
    function upd(){toTop.hidden=window.scrollY<560;tick=false}
    window.addEventListener('scroll',function(){if(!tick){tick=true;requestAnimationFrame(upd)}},{passive:true});
    toTop.addEventListener('click',function(){
      window.scrollTo({top:0,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
    });
    upd();
  }

  /* 본문 사진은 축소본이다. 누르면 원본을 덮개로 띄운다.
     스크립트가 없어도 링크가 새 창으로 원본을 열어 주므로 기능은 남는다. */
  var lens=null,lensImg=null,lastFocus=null;
  function closeLens(){
    if(!lens){return}
    lens.hidden=true;
    lensImg.removeAttribute('src');
    document.body.classList.remove('is-locked');
    if(lastFocus){lastFocus.focus()}
  }
  function openLens(href,alt){
    if(!lens){
      lens=document.createElement('div');
      lens.className='lens';
      lens.setAttribute('role','dialog');
      lens.setAttribute('aria-modal','true');
      lens.setAttribute('aria-label','원본 사진');
      lens.innerHTML='<button class="lens-close" type="button" aria-label="닫기">&times;</button><img class="lens-img" alt="">';
      lensImg=lens.querySelector('.lens-img');
      lens.addEventListener('click',function(e){
        if(e.target===lens||e.target.classList.contains('lens-close')){closeLens()}
      });
      document.body.appendChild(lens);
    }
    lensImg.alt=alt||'';
    lensImg.src=href;
    lens.hidden=false;
    document.body.classList.add('is-locked');
    lens.querySelector('.lens-close').focus();
  }
  document.addEventListener('click',function(e){
    var link=e.target.closest?e.target.closest('a[data-zoom]'):null;
    if(!link||e.metaKey||e.ctrlKey||e.shiftKey||e.button){return}
    e.preventDefault();
    lastFocus=link;
    openLens(link.getAttribute('href'),(link.querySelector('img')||{}).alt);
  });

  document.addEventListener('keydown',function(e){
    if(e.key==='Escape'&&lens&&!lens.hidden){closeLens();return}
    if(e.key!=='/'||e.metaKey||e.ctrlKey||e.altKey){return}
    var t=(e.target.tagName||'').toLowerCase();
    if(t==='input'||t==='textarea'||t==='select'||e.target.isContentEditable){return}
    var s=document.querySelector('[data-search-input]');
    if(!s){return}
    e.preventDefault();
    /* 머리글 검색은 모달 안에 있다. 닫혀 있으면 먼저 연다. */
    if(search&&!search.checked&&s.closest('.search-modal')){setSearch(true)}
    else{s.focus();s.select()}
  });
})();
</script>
<?php $this->start('scripts') ?><?php $this->stop() ?>
</body>
</html>
