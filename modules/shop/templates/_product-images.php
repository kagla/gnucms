<section class="panel card card-body extension-panel" id="product-images">
  <h2 class="card-title">상품 이미지</h2>
  <?php if (($input['images_saved'] ?? '') === '1'): ?><p class="alert alert-success" role="status">상품 이미지를 저장했습니다.</p><?php endif ?>
  <?php if (!$images_ready): ?>
    <p>여러 이미지와 순서 관리를 사용하려면 쇼핑몰 데이터를 업데이트해 주세요. 기존 상품과 이미지는 보존됩니다.</p>
    <a class="btn" href="<?= $this->e($admin_url) ?>">쇼핑몰 데이터 관리</a>
  <?php else: ?>
    <p class="muted">이미지를 여러 장 선택해 추가할 수 있습니다. 상품당 최대 20장, 파일당 5MB 이하의 JPG·PNG·WebP를 지원합니다.</p>
    <input type="hidden" name="gallery_present" value="1">
    <div id="shop-image-upload" data-image-count="<?= count($form_images) ?>" data-max-files="<?= min(20, max(1, (int) ini_get('max_file_uploads'))) ?>" data-post-limit="<?= ini_parse_quantity((string) ini_get('post_max_size')) ?>" data-file-limit="<?= ini_parse_quantity((string) ini_get('upload_max_filesize')) ?>">
      <label class="extension-label">이미지 선택<input class="file-input file-input-bordered input-block" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple<?= count($form_images) >= 20 ? ' disabled' : '' ?>></label>
    </div>
    <p id="shop-image-upload-feedback" role="status" aria-live="polite"></p>
      <p class="muted" id="shop-image-instructions">이미지나 이동 손잡이를 드래그해 순서를 바꾸세요. 이동 버튼과 키보드 방향키로도 조정할 수 있습니다. 첫 번째 이미지가 대표 이미지이며, 선택한 새 이미지도 정렬할 수 있습니다. 상품 저장을 누르면 이미지와 순서를 함께 반영합니다.</p>
      <ol class="shop-image-list" id="shop-image-list" aria-label="상품 이미지 순서" aria-describedby="shop-image-instructions">
      <?php foreach ($form_images as $position => $item): ?>
        <li class="shop-image-item" data-image-id="<?= $this->e($item['id']) ?>">
          <input type="hidden" name="image_order[]" value="<?= $this->e($item['id']) ?>">
          <img src="<?= $this->e($public_url) ?>/image?file=<?= $this->e($item['filename']) ?>" alt="<?= $this->e($product['name']) ?> 이미지 <?= $position + 1 ?>" draggable="false">
          <div class="shop-image-caption"><span class="badge badge-soft" data-image-position><?= $position === 0 ? '대표 이미지' : ($position + 1) . '번 이미지' ?></span><button type="button" class="btn btn-ghost btn-sm shop-image-handle" data-image-drag aria-label="이미지 <?= $position + 1 ?> 순서 이동" hidden>⠿ 이동</button></div>
          <div class="shop-image-actions"><button type="button" class="btn btn-sm" data-image-move="-1" hidden><?= $this->icon('arrow-left', 15) ?> 이전</button><button type="button" class="btn btn-sm" data-image-move="1" hidden>다음 <?= $this->icon('arrow-right', 15) ?></button></div>
        </li>
      <?php endforeach ?>
      </ol>
      <p id="shop-image-order-feedback" role="status" aria-live="polite"></p>
      <template id="shop-new-image-template">
        <li class="shop-image-item">
          <input type="hidden" name="image_order[]">
          <img alt="선택한 새 이미지" draggable="false">
          <div class="shop-image-caption"><span class="badge badge-soft" data-image-position></span><button type="button" class="btn btn-ghost btn-sm shop-image-handle" data-image-drag>⠿ 이동</button></div>
          <div class="shop-image-actions"><button type="button" class="btn btn-sm" data-image-move="-1">이전</button><button type="button" class="btn btn-sm" data-image-move="1">다음</button><button type="button" class="btn btn-ghost btn-sm" data-image-remove>선택 취소</button></div>
        </li>
      </template>
    <script src="<?= $this->asset('shop-images.js') ?>" defer></script>
  <?php endif ?>
</section>
