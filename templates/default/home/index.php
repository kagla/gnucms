<?php $this->layout('layout') ?>
<?php $this->start('title') ?><?= $this->e($site['site_name']) ?> · <?= $this->e($site['site_tagline']) ?><?php $this->stop() ?>
<?php $this->start('seo_meta') ?>
<link rel="canonical" href="<?= $this->e($site_url) ?>/">
<meta property="og:type" content="website"><meta property="og:locale" content="ko_KR">
<meta property="og:site_name" content="<?= $this->e($site['site_name']) ?>">
<meta property="og:title" content="<?= $this->e($site['site_name']) ?>"><meta property="og:description" content="<?= $this->e($site['site_tagline']) ?>">
<meta property="og:url" content="<?= $this->e($site_url) ?>/">
<?php if (strtoupper(trim((string) $site['site_name'])) === 'GNUCMS'): ?>
<meta property="og:image" content="<?= $this->e($site_url) ?>/og.png">
<meta property="og:image:width" content="1731"><meta property="og:image:height" content="909">
<meta property="og:image:alt" content="GNUCMS · 가볍고 유연한 PHP CMS">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="<?= $this->e($site_url) ?>/og.png">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif ?>
<script type="application/ld+json"><?= json_encode([
  '@context' => 'https://schema.org', '@type' => 'WebSite',
  'name' => $site['site_name'], 'url' => $site_url . '/', 'description' => $site['site_tagline'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php $this->stop() ?>
<?php $this->start('nav_section') ?>home<?php $this->stop() ?>
<?php $this->start('body_class') ?>home-page<?php $this->stop() ?>

<?php // 검색을 머리글 한가운데 크게 둔다.
      // 검색은 게시판 단위라서 첫 게시판을 기본 대상으로 삼는다. ?>
<?php $this->start('header_search') ?>
<?php if ($boards !== []): ?>
<form class="header-search" method="get" action="<?= $this->url('posts.index', ['key' => $boards[0]['board_key']]) ?>" role="search">
  <label class="input input-bordered">
    <span class="input-icon" aria-hidden="true"><?= $this->icon('search', 18) ?></span>
    <input type="search" name="q" value="" placeholder="<?= $this->e($boards[0]['name']) ?>에서 검색해 보세요" aria-label="게시글 검색" data-search-input>
  </label>
  <button class="btn btn-primary header-search-btn" type="submit">검색</button>
</form>
<?php endif ?>
<?php $this->stop() ?>

<?php $this->start('body') ?>
<?php
$recent = array_reduce($boards, fn ($c, $b) => $c + count($b['latest_posts']), 0);
// 게시판을 가로질러 조회수가 높은 글을 모은다. 홈 목록에 이미 실려 온 값만 쓴다.
$pool = [];
foreach ($boards as $b) {
    foreach ($b['latest_posts'] as $p) {
        if (!$p['is_secret']) {
            $pool[] = ['post' => $p, 'board' => $b];
        }
    }
}
usort($pool, fn ($x, $y) => $y['post']['view_count'] <=> $x['post']['view_count']);
$hot = array_slice($pool, 0, 6);
?>

<section class="hero rounded-box">
  <div class="hero-content">
    <div class="hero-copy">
      <span class="hero-kicker"><?= $this->icon('sparkle', 14) ?> Curated community</span>
      <h1><?= $this->e($site['home_title']) ?></h1>
      <p><?= $this->e($site['home_intro']) ?></p>
      <div class="hero-actions">
        <?php if ($boards !== []): ?>
          <a class="btn btn-primary" href="<?= $this->url('posts.index', ['key' => $boards[0]['board_key']]) ?>"><?= $this->icon('sparkle', 16) ?> 둘러보기</a>
        <?php endif ?>
        <?php if ($current_user['is_guest'] && $registration_available): ?>
          <a class="btn btn-outline" href="<?= $this->url('auth.register') ?>">회원가입</a>
        <?php elseif ($boards !== []): ?>
          <a class="btn btn-outline" href="<?= $this->url('posts.create', ['key' => $boards[0]['board_key']]) ?>"><?= $this->icon('pencil', 16) ?> 글쓰기</a>
        <?php endif ?>
      </div>
    </div>
    <?php // 게시판 몇 개·글 몇 개 같은 숫자는 사이트가 작을 때 오히려 초라하다.
          // 대신 이 CMS 가 무엇인지 말해 주는 사실 네 줄을 둔다. ?>
    <div class="hero-panel">
      <div class="hero-panel-head"><span>GNUCMS</span><span class="hero-panel-status">Live</span></div>
      <ul class="hero-facts" aria-label="GNUCMS 특징">
        <li><?= $this->icon('board', 17) ?><div><strong>콘텐츠를 한곳에서</strong><span>게시판, 페이지, 회원 관리를 매끄럽게</span></div></li>
        <li><?= $this->icon('grid', 17) ?><div><strong>환경에 구애받지 않게</strong><span>SQLite · MySQL/MariaDB 지원</span></div></li>
        <li><?= $this->icon('sparkle', 17) ?><div><strong>가볍고 유연하게</strong><span>필요한 기능만 담은 단순한 PHP 구조</span></div></li>
      </ul>
    </div>
  </div>
</section>

<?php if ($boards !== []): ?>
  <?php // 카테고리 원형 줄. 게시판마다 아이콘과 색을 돌려 가며 붙인다.
        $cat_icons = ['palette', 'gift', 'star', 'heart', 'sparkle', 'board']; ?>
  <nav class="shortcuts" aria-label="게시판 바로가기">
    <?php $i = 0; foreach ($boards as $board): ?>
      <a class="shortcut" href="<?= $this->url('posts.index', ['key' => $board['board_key']]) ?>">
        <span class="shortcut-icon" data-tone="<?= $this->e($i % 6) ?>" aria-hidden="true"><?= $this->icon($cat_icons[$i % 6], 23) ?></span>
        <span class="shortcut-name"><?= $this->e($board['name']) ?></span>
      </a>
    <?php $i++; endforeach ?>
  </nav>
<?php endif ?>

<?php if ($boards === []): ?>
  <div class="card empty-card">
    <div class="card-body">
      <span class="empty-icon" aria-hidden="true"><?= $this->icon('board', 26) ?></span>
      <h2 class="card-title">아직 만들어진 게시판이 없습니다</h2>
      <p>관리 콘솔에서 첫 게시판을 만들어 보세요.</p>
      <?php if (!$current_user['is_guest'] && $current_user['is_admin']): ?>
        <div class="card-actions"><a class="btn btn-primary" href="<?= $this->url('admin.boards.create') ?>"><?= $this->icon('plus', 16) ?> 게시판 만들기</a></div>
      <?php endif ?>
    </div>
  </div>
<?php else: ?>

  <?php if (count($hot) > 2): ?>
    <section class="feed" aria-labelledby="feed-hot">
      <div class="feed-head">
        <div>
          <h2 id="feed-hot"><?= $this->icon('fire', 19) ?> 지금 많이 보는 글</h2>
          <p class="feed-sub">최근 올라온 글 가운데 가장 많이 열어 본 순서입니다.</p>
        </div>
      </div>
      <ul class="list card rank-list">
        <?php foreach ($hot as $item): ?>
          <li class="list-row">
            <span class="badge badge-ghost badge-sm"><?= $this->e($item['board']['name']) ?></span>
            <a class="rank-title" href="<?= $this->url('posts.show', ['id' => $item['post']['id']]) ?>"><?= $this->e($item['post']['title']) ?></a>
            <span class="rank-meta"><?= $this->icon('eye', 13) ?> <?= $this->e($item['post']['view_count']) ?></span>
          </li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>

  <?php foreach ($boards as $board): ?>
    <section class="feed" aria-labelledby="feed-<?= $this->e($board['board_key']) ?>">
      <div class="feed-head">
        <div>
          <h2 id="feed-<?= $this->e($board['board_key']) ?>"><?= $this->e($board['name']) ?></h2>
          <p class="feed-sub"><?= $this->e($board['description'] ?: '새로운 이야기와 소식을 확인해 보세요.') ?></p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= $this->url('posts.index', ['key' => $board['board_key']]) ?>">전체보기 <?= $this->icon('chevron-right', 15) ?></a>
      </div>

      <?php if ($board['latest_posts'] === []): ?>
        <div class="card empty-card empty-card-sm">
          <div class="card-body">
            <span class="empty-icon" aria-hidden="true"><?= $this->icon('document', 22) ?></span>
            <p>아직 등록된 글이 없습니다</p>
          </div>
        </div>
      <?php else: ?>
        <?php // 홈 표시 방식은 게시판의 목록 형태 설정을 그대로 따른다.
              // 값은 BoardService 가 허용 목록으로 걸러서 내려준다.
              $__type = $this->def($board['list_type'] ?? null, 'list');
              $this->insert(in_array($__type, ['list', 'gallery', 'news', 'magazine'], true) && $this->exists('home/_feed_' . $__type) ? 'home/_feed_' . $__type : 'home/_feed_list', ['board' => $board]) ?>
      <?php endif ?>
    </section>
  <?php endforeach ?>

  <?php // 첫 화면 아래의 안내 블록. 셋 다 이 사이트에서 실제로 되는 일이다. ?>
  <section class="guide" aria-labelledby="guide-title">
    <h2 class="guide-title" id="guide-title"><?= $this->e($site['site_name']) ?> 이렇게 즐겨보세요</h2>
    <div class="guide-grid">
      <div class="guide-card">
        <span class="guide-ico" data-tone="1" aria-hidden="true"><?= $this->icon('palette', 22) ?></span>
        <strong>마음 가는 게시판부터</strong>
        <p>위의 동그란 바로가기를 누르면 그 게시판의 글만 모아 볼 수 있어요.</p>
      </div>
      <div class="guide-card">
        <span class="guide-ico" data-tone="2" aria-hidden="true"><?= $this->icon('pencil', 22) ?></span>
        <strong>사진과 함께 남기기</strong>
        <p>글쓰기에서 사진을 여러 장 끌어놓으면 본문에 그대로 들어갑니다.</p>
      </div>
      <div class="guide-card">
        <span class="guide-ico" data-tone="4" aria-hidden="true"><?= $this->icon('bell', 22) ?></span>
        <strong>답글은 알림으로</strong>
        <p>내 글과 댓글에 답이 달리면 머리글의 종 아이콘에 표시됩니다.</p>
      </div>
    </div>
  </section>
<?php endif ?>
<?php $this->stop() ?>
