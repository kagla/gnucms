<?php $values = $form_values; $editing = $page === 'products/edit'; ?>
<link rel="stylesheet" href="<?= $this->asset('shop-admin.css') ?>">
<div class="toolbar page-head"><div><h1><?= $editing ? '상품 수정' : '상품 등록' ?></h1></div><a class="btn" href="<?= $this->e($admin_url) ?>/products">상품 목록</a></div>
<?php if (!$editing || $product !== null): ?>
<form method="post" enctype="multipart/form-data" action="<?= $this->e($admin_url . '/' . $page) ?>" id="shop-product-form" data-costs-ready="<?= $costs_ready ? '1' : '0' ?>" data-known-variants="<?= $this->e(json_encode($product['variants'] ?? [], JSON_THROW_ON_ERROR)) ?>">
  <?php $this->insert('_csrf') ?>
  <input type="hidden" name="id" value="<?= $this->e($values['id']) ?>">
  <input type="hidden" name="version" value="<?= $this->e($values['version']) ?>">
  <input type="hidden" name="image_key" value="<?= $this->e($values['image_key']) ?>">
  <input type="hidden" name="uploaded_images" value="<?= $this->e($values['uploaded_images'] ?? '') ?>" data-uploaded-images>
  <section class="panel card card-body extension-panel">
    <h2 class="card-title">기본 정보</h2>
    <div class="form-grid">
      <label class="full extension-label">상품명<input class="input input-bordered input-block" name="name" maxlength="150" value="<?= $this->e($values['name']) ?>" required></label>
      <div class="full shop-description-editor">
        <label class="extension-label" for="shop-description">상품 설명</label>
        <textarea class="textarea textarea-bordered textarea-block" id="shop-description" name="description" rows="8" maxlength="15000" data-cms-editor><?= $this->e($values['description']) ?></textarea>
      </div>
    </div>
    <label class="extension-label"><input class="checkbox checkbox-primary checkbox-sm" type="checkbox" name="active" value="1"<?= $values['active'] ? ' checked' : '' ?>>판매 목록에 공개</label>
  </section>
  <section class="panel card card-body extension-panel">
    <h2 class="card-title">옵션 구성</h2>
    <p class="muted">옵션은 두 종류, 조합은 100개까지 지원합니다. 옵션이 없으면 이름과 값을 비워 두세요.</p>
    <div class="form-grid">
      <?php foreach ([1, 2] as $n): ?>
      <label class="extension-label">옵션 <?= $n ?> 이름<input class="input input-bordered input-block" name="option<?= $n ?>_name" maxlength="60" value="<?= $this->e($values['option' . $n . '_name']) ?>" placeholder="<?= $n === 1 ? '예: 색상' : '예: 크기' ?>"></label>
      <label class="extension-label">옵션 <?= $n ?> 값 <small>쉼표로 구분</small><input class="input input-bordered input-block" name="option<?= $n ?>_values" maxlength="1000" value="<?= $this->e($values['option' . $n . '_values']) ?>" placeholder="<?= $n === 1 ? '화이트, 블랙' : 'S, M, L' ?>"></label>
      <?php endforeach ?>
      <label class="extension-label">새 조합 기본 판매가 (원)<input class="input input-bordered input-block" name="price" type="number" min="1" max="100000000" value="<?= $this->e($values['price']) ?>" required></label>
      <?php if ($costs_ready): ?><label class="extension-label">새 조합 기본 원가 (원)<input class="input input-bordered input-block" name="cost_price" type="number" min="0" max="100000000" value="<?= $this->e($values['cost_price']) ?>" placeholder="미입력"></label><?php endif ?>
      <label class="extension-label">새 조합 초기 재고 (개)<input class="input input-bordered input-block" name="stock" type="number" min="0" max="1000000" value="<?= $this->e($values['stock']) ?>" required></label>
    </div>
    <p class="muted">이름과 값을 입력한 뒤 조합을 적용하면 아래 표에서 각각 수정할 수 있습니다. 이미 입력한 조합의 판매가·원가·재고는 유지됩니다.</p>
    <div><button class="btn" name="action" value="build" id="shop-build-variants" formnovalidate>옵션 조합 적용</button></div>
    <p id="shop-variant-feedback" role="status" aria-live="polite"></p>
  </section>
  <section class="panel table-wrap card card-body extension-panel">
    <h2 class="card-title">옵션별 판매가·원가와 재고</h2>
    <?php if ($costs_ready): ?><p class="muted">원가는 상품 한 개 기준으로 입력합니다. 빈칸은 미입력, 0은 원가 0원입니다. 원가는 고객에게 표시되지 않으며 변경 후 생성되는 주문부터 적용됩니다.</p>
    <?php else: ?><p class="muted">원가 입력은 <a class="link" href="<?= $this->e($admin_url) ?>">쇼핑몰 데이터 업데이트</a> 후 사용할 수 있습니다.</p><?php endif ?>
    <p class="muted">판매가는 추가금이 아닌 해당 조합의 최종 상품 가격입니다. 재고는 주문에서 확보한 수량을 제외한 판매 가능 수량이며, 0개인 옵션은 품절로 표시됩니다.</p>
    <p class="muted">입력칸 옆 아래 화살표를 누르면 현재 값을 아래 모든 옵션의 같은 항목에 복사합니다. 변경 후 상품 저장을 눌러 주세요.</p>
    <template id="shop-fill-down-template"><button type="button" class="btn btn-sm"><?= $this->icon('chevron-down', 14) ?></button></template>
    <table class="table shop-variant-table"><thead><tr><th>옵션 조합</th><th>판매가 (원)</th><?php if ($costs_ready): ?><th>원가 (원)</th><?php endif ?><th>판매 가능 재고 (개)</th></tr></thead><tbody id="shop-variant-rows">
      <?php foreach ($form_variants as $i => $variant): $label = implode(' / ', array_filter([$variant['option1'] ?? '', $variant['option2'] ?? ''], static fn ($v) => $v !== '')) ?: '기본 상품'; ?>
      <tr>
        <td data-label="옵션 조합"><div><span><?= $this->e($label) ?></span><?php foreach (['id', 'version', 'option1', 'option2'] as $key): ?><input type="hidden" name="variants[<?= $i ?>][<?= $key ?>]" value="<?= $this->e($variant[$key] ?? '') ?>"><?php endforeach ?></div></td>
        <td data-label="판매가 (원)"><input class="input input-bordered" name="variants[<?= $i ?>][price]" type="number" min="1" max="100000000" value="<?= $this->e($variant['price'] ?? '') ?>" aria-label="<?= $this->e($label) ?> 판매가" required></td>
        <?php if ($costs_ready): ?><td data-label="원가 (원)"><input class="input input-bordered" name="variants[<?= $i ?>][cost_price]" type="number" min="0" max="100000000" value="<?= $this->e($variant['cost_price'] ?? '') ?>" placeholder="미입력" aria-label="<?= $this->e($label) ?> 원가"></td><?php endif ?>
        <td data-label="판매 가능 재고 (개)"><input class="input input-bordered" name="variants[<?= $i ?>][stock]" type="number" min="0" max="1000000" value="<?= $this->e($variant['stock'] ?? '') ?>" aria-label="<?= $this->e($label) ?> 재고" required></td>
      </tr>
      <?php endforeach ?>
    </tbody></table>
    <p id="shop-fill-feedback" role="status" aria-live="polite"></p>
    <p class="muted">상품 저장을 누르면 기본 정보, 옵션, 이미지와 순서를 함께 저장합니다. 제거한 옵션의 과거 주문과 재고 이력은 보존됩니다.</p>
  </section>
  <?php $this->insert('_product-images') ?>
  <div class="form-actions"><button class="btn btn-primary" name="action" value="save">상품 저장</button><a class="btn" href="<?= $this->e($admin_url) ?>/products">목록으로</a></div>
</form>
<script src="<?= $this->asset('shop-admin.js') ?>" defer></script>
<?php $this->insert('admin/_editor', ['values' => $values, 'editor_required' => false, 'editor_height' => 220]) ?>
<?php endif ?>
