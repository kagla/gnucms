<footer class="shop-footer">
  <strong><?= $this->e($settings['seller'] ?: $settings['name']) ?></strong>
  <?php if ($settings['seller'] !== ''): ?>
    <p>대표 <?= $this->e($settings['owner']) ?> · 사업자등록번호 <?= $this->e($settings['business_number']) ?><?php if ($settings['commerce_number'] !== ''): ?> · 통신판매업 신고 <?= $this->e($settings['commerce_number']) ?><?php endif ?></p>
    <p><?= $this->e($settings['address']) ?> · <?= $this->e($settings['phone']) ?> · <?= $this->e($settings['email']) ?></p>
    <p>반품 주소: <?= $this->e($settings['return_address']) ?></p>
  <?php endif ?>
  <?php if ($settings['policy'] !== ''): ?>
    <details><summary>배송·반품 정책과 개인정보 처리 안내</summary><p class="pre"><?= $this->e($settings['policy']) ?></p><p>주문자 정보는 주문 확인·배송·반품·환불 처리에 사용합니다. 주문 및 결제 내역은 판매자가 고지한 기간 동안 보관합니다.</p></details>
  <?php endif ?>
</footer>
