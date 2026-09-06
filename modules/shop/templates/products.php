<link rel="stylesheet" href="<?= $this->asset('shop-admin.css') ?>">
<div class="toolbar page-head">
  <div><h1>상품 목록</h1><p>등록한 상품의 판매 상태와 옵션별 가격·재고를 관리합니다.</p></div>
  <a class="btn btn-primary" href="<?= $this->e($admin_url) ?>/products/new">상품 등록</a>
</div>
<section class="panel table-wrap card card-body extension-panel">
  <form method="get" action="<?= $this->e($admin_url) ?>/products" class="row">
    <input class="input input-bordered input-block" name="q" aria-label="상품명 검색" placeholder="상품명 검색" value="<?= $this->e($input['q'] ?? '') ?>">
    <button class="btn">검색</button>
  </form>
  <?php if ($products === []): ?><p class="empty"><?= ($input['q'] ?? '') === '' ? '등록된 상품이 없습니다. 상품 등록을 눌러 첫 상품을 추가해 주세요.' : '검색 결과가 없습니다.' ?></p>
  <?php else: ?>
  <table class="table"><thead><tr><th>상품</th><th>옵션</th><th>상태</th><th class="num">최저 판매가</th><th class="num">판매 가능 재고</th><th>관리</th></tr></thead><tbody>
    <?php foreach ($products as $item): ?><tr>
      <td data-label="상품"><a class="link" href="<?= $this->e($admin_url) ?>/products/edit?id=<?= $this->e($item['id']) ?>"><?= $this->e($item['name']) ?></a></td>
      <td data-label="옵션"><div class="badge-row shop-product-options">
        <?php foreach ($item['options'] ?? [] as $option): $optionTitle = $option['name'] . ': ' . (implode(', ', $option['values']) ?: '등록된 값 없음'); ?>
          <span class="badge badge-outline" title="<?= $this->e($optionTitle) ?>" aria-label="<?= $this->e($optionTitle) ?>" tabindex="0"><?= $this->e($option['name']) ?></span>
        <?php endforeach ?>
        <?php if (($item['options'] ?? []) === []): ?><span class="muted">없음</span><?php endif ?>
      </div></td>
      <td data-label="상태"><?= $item['active'] ? '공개' : '비공개' ?></td>
      <td data-label="최저 판매가" class="num"><?= number_format((int) $item['min_price']) ?>원</td>
      <td data-label="판매 가능 재고" class="num"><?= number_format((int) $item['available_stock']) ?>개</td>
      <td data-label="관리"><a class="btn btn-sm" href="<?= $this->e($admin_url) ?>/products/edit?id=<?= $this->e($item['id']) ?>">수정</a></td>
    </tr><?php endforeach ?>
  </tbody></table>
  <?php endif ?>
  <nav class="pagination" aria-label="상품 목록 페이지">
    <?php if ($page_number > 1): ?><a class="link" href="<?= $this->e($admin_url) ?>/products?<?= $this->e(http_build_query(['q' => $input['q'] ?? '', 'p' => $page_number - 1])) ?>">이전</a><?php endif ?>
    <?php if (count($products) === 24): ?><a class="link" href="<?= $this->e($admin_url) ?>/products?<?= $this->e(http_build_query(['q' => $input['q'] ?? '', 'p' => $page_number + 1])) ?>">다음</a><?php endif ?>
  </nav>
</section>
