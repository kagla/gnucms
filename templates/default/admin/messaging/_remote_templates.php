<section class="card card-body extension-panel">
  <h2 class="card-title">비즈뿌리오 템플릿 조회</h2>
  <p>등록된 템플릿의 상태와 본문을 확인하고 GNUCMS로 가져옵니다. 승인된 본문과 버튼을 다시 입력할 필요가 없습니다.</p>
  <?php if (empty($account_status['kapi_configured'])): ?>
    <p><a class="link" href="<?= $this->url('admin.settings.messaging') ?>?environment=<?= $this->e($environment) ?>">플러그인 설정에서 KAPI API Key 등록</a></p>
  <?php else: ?>
    <p>조회 계정: <strong><?= $this->e($account_status['account']) ?></strong></p>
    <form method="post" action="<?= $this->url('admin.messaging.templates') ?>">
      <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>">
      <input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
      <button class="btn btn-primary" name="action" value="remote-list">비즈뿌리오 템플릿 조회</button>
    </form>
  <?php endif ?>
  <?php if ($remote_list !== null): ?>
    <p>전체 <?= $this->e($remote_list['total']) ?>개 · <?= $this->e($remote_list['page']) ?>페이지</p>
    <?php if ($remote_list['items'] === []): ?><p>이 계정·발신프로필에 등록된 템플릿이 없습니다.</p><?php else: ?>
      <div class="table-wrap"><table class="table"><thead><tr><th>이름</th><th>코드</th><th>상태</th><th>확인</th></tr></thead><tbody>
      <?php foreach ($remote_list['items'] as $item): ?>
        <tr><td data-label="이름"><?= $this->e($item['name']) ?></td><td data-label="코드"><?= $this->e($item['code']) ?></td><td data-label="상태"><?= $this->e($item['status']) ?></td><td data-label="확인">
          <form method="post" action="<?= $this->url('admin.messaging.templates') ?>">
            <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
            <input type="hidden" name="remote_code" value="<?= $this->e($item['code']) ?>">
            <button class="btn btn-sm btn-outline" name="action" value="remote-detail">상세 확인</button>
          </form>
        </td></tr>
      <?php endforeach ?>
      </tbody></table></div>
    <?php endif ?>
    <div class="card-actions">
      <?php foreach ([-1 => '이전', 1 => '다음'] as $offset => $label): $targetPage = $remote_list['page'] + $offset; if ($targetPage < 1 || $targetPage > $remote_list['pages']) continue; ?>
        <form method="post" action="<?= $this->url('admin.messaging.templates') ?>">
          <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
          <input type="hidden" name="remote_page" value="<?= $this->e($targetPage) ?>"><button class="btn btn-outline" name="action" value="remote-list"><?= $this->e($label) ?></button>
        </form>
      <?php endforeach ?>
    </div>
  <?php endif ?>
  <?php if ($remote_detail !== null): $remote = $remote_detail['remote']; ?>
    <h3><?= $this->e($remote_detail['content']['name']) ?> <small><?= $this->e($remote_detail['code']) ?></small></h3>
    <p>검수: <strong><?= $this->e($remote['inspection']) ?></strong> · 사용 상태: <?= $this->e($remote['status']) ?> · 차단: <?= $remote['blocked'] === false ? '아니오' : ($remote['blocked'] === true ? '예' : '확인 불가') ?> · 휴면: <?= $remote['dormant'] === false ? '아니오' : ($remote['dormant'] === true ? '예' : '확인 불가') ?></p>
    <pre><?= $this->e($remote_detail['content']['message']) ?></pre>
    <?php foreach ($remote_detail['content']['buttons'] as $button): ?>
      <p><strong><?= $this->e($button['name']) ?></strong> (<?= $this->e($button['type']) ?>)<br><?= $this->e($button['url_mobile']) ?><br><?= $this->e($button['url_pc']) ?></p>
    <?php endforeach ?>
    <?php if (!$remote['sendable']): ?><p class="alert alert-warning"><?= $this->e($remote['reason']) ?><?php if ($remote_detail['local_id'] !== null): ?> 상태를 갱신하면 기존 로컬 템플릿의 사용을 중지합니다.<?php endif ?></p><?php endif ?>
    <?php if ($remote['sendable'] || $remote_detail['local_id'] !== null): ?>
      <p><?= $remote_detail['local_id'] === null ? '선택한 환경에 저장합니다.' : '같은 코드의 로컬 템플릿을 갱신합니다. 기존 발송 이력은 보존됩니다.' ?></p>
      <form method="post" action="<?= $this->url('admin.messaging.templates') ?>">
        <input type="hidden" name="csrf_token" value="<?= $this->e($csrf_token) ?>"><input type="hidden" name="environment" value="<?= $this->e($environment) ?>">
        <input type="hidden" name="remote_code" value="<?= $this->e($remote_detail['code']) ?>">
        <input type="hidden" name="config_revision" value="<?= $this->e($remote_detail['config_revision']) ?>"><input type="hidden" name="local_revision" value="<?= $this->e($remote_detail['local_revision']) ?>">
        <button class="btn btn-primary" name="action" value="remote-import"><?= !$remote['sendable'] ? '상태 갱신·사용 중지' : ($remote_detail['local_id'] === null ? 'GNUCMS로 가져오기' : '비즈뿌리오 내용으로 갱신') ?></button>
      </form>
    <?php endif ?>
  <?php endif ?>
</section>
