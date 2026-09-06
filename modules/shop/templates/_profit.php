<?php $profit = $report['profit']; ?>
<section class="panel card card-body extension-panel" data-shop-profit>
  <h2 class="card-title">상품별 원가와 이익</h2>
  <p class="muted">선택 기간에 결제된 주문의 현재 손익입니다. 이후 확정된 취소·반품·교환도 반영합니다. 원가는 주문 당시 금액을 사용하고, 교환품은 교환 출고 당시 금액을 사용합니다.</p>
  <p class="muted">상품 이익 = 배송비를 제외한 실매출 − 소진 원가. 재판매 가능한 회수품은 원가를 되돌리며, 재판매 불가 회수품은 비용으로 남습니다. 수수료·실제 배송비·세금·기타 운영비는 포함하지 않습니다.</p>
  <?php if ($profit['missing_cost_quantity'] > 0): ?><p class="alert alert-warning" role="status">원가가 기록되지 않은 소진 수량이 <?= number_format($profit['missing_cost_quantity']) ?>개입니다. 원가와 이익은 계산하지 않습니다. 기존 주문의 원가는 현재 상품 원가로 대체하지 않습니다.</p><?php endif ?>
  <?php if ($profit['unallocated_refund_orders'] > 0): ?><p class="alert alert-warning" role="status">상품에 연결되지 않은 부분 환불이 <?= number_format($profit['unallocated_refund_orders']) ?>개 주문에 있습니다. 해당 주문의 환불 내역을 연결하면 실매출과 이익을 계산할 수 있습니다.</p><?php endif ?>
  <div class="metrics">
    <?php foreach (['net_product_amount' => '상품 실매출', 'cost_amount' => '소진 원가', 'gross_profit' => '상품 이익'] as $key => $label): ?>
    <div class="metric stat card"><span><?= $this->e($label) ?></span><strong><?= $profit[$key] === null ? '계산 불가' : number_format($profit[$key]) . '<small> 원</small>' ?></strong></div>
    <?php endforeach ?>
  </div>
  <div class="table-wrap"><table class="table"><thead><tr><th>상품</th><th class="num">판매 수량</th><th class="num">상품 실매출</th><th class="num">소진 원가</th><th class="num">상품 이익</th><th class="num">이익률</th></tr></thead><tbody>
    <?php foreach ($profit['products'] as $item): ?><tr>
      <td data-label="상품"><a class="link" href="<?= $this->e($admin_url) ?>/products/edit?id=<?= $this->e($item['product_id']) ?>"><?= $this->e($item['name']) ?></a></td>
      <td data-label="판매 수량" class="num"><?= number_format($item['sold_quantity']) ?>개</td>
      <td data-label="상품 실매출" class="num"><?= $item['net_product_amount'] === null ? '환불 확인 필요' : number_format($item['net_product_amount']) . '원' ?></td>
      <td data-label="소진 원가" class="num"><?= $item['cost_amount'] === null ? '미입력 ' . number_format($item['missing_cost_quantity']) . '개' : number_format($item['cost_amount']) . '원' ?></td>
      <td data-label="상품 이익" class="num"><?= $item['gross_profit'] === null ? '계산 불가' : number_format($item['gross_profit']) . '원' ?></td>
      <td data-label="이익률" class="num"><?= $item['margin_rate'] === null ? '—' : number_format($item['margin_rate'], 1) . '%' ?></td>
    </tr><?php endforeach ?>
  </tbody></table></div>
  <?php if ($profit['products'] === []): ?><p>이 기간에 결제된 상품이 없습니다.</p><?php endif ?>
</section>
