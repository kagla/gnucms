<?php

declare(strict_types=1);

use GnuCms\App;
use GnuCms\Extension\StateStore;
use GnuCms\Modules\Shop\Input;
use GnuCms\Modules\Shop\Service;
use GnuCms\Payment\ExecutionLock;
use GnuCms\Payment\PortOneGateway;
use GnuCms\Payment\Settings;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 3) . '/vendor/autoload.php';
require dirname(__DIR__) . '/autoload.php';

$action = $argv[1] ?? '';
$configFile = dirname(__DIR__, 3) . '/config/config.php';
$orderId = null;
$limit = 20;
foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--config=')) $configFile = substr($argument, 9);
    elseif (str_starts_with($argument, '--order=')) $orderId = substr($argument, 8);
    elseif (str_starts_with($argument, '--limit=') && ctype_digit(substr($argument, 8))) $limit = max(1, min(100, (int) substr($argument, 8)));
    else { fwrite(STDERR, "알 수 없는 인수입니다.\n"); exit(2); }
}
if (!in_array($action, ['expire', 'sync', 'reconcile'], true) || ($action === 'sync' && $orderId === null)) {
    fwrite(STDERR, "사용법: php modules/shop/bin/maintenance.php expire [--config=/경로/config.php]\n"
        . "        php modules/shop/bin/maintenance.php sync --order=주문번호 [--config=/경로/config.php]\n"
        . "        php modules/shop/bin/maintenance.php reconcile [--limit=20] [--config=/경로/config.php]\n");
    exit(2);
}
try {
    if (!is_file($configFile)) throw new RuntimeException();
    $config = require $configFile;
    if (!is_array($config)) throw new RuntimeException();
    $app = new App($config, $configFile);
    $enabled = (new StateStore($app->storageDir() . '/extensions'))->read();
    if (!in_array('modules/shop', $enabled, true)) throw new RuntimeException();
    $status = ExecutionLock::run($app->storageDir(), static function () use ($app, $enabled, $action, $orderId, $limit): int {
        $gateways = [];
        foreach (array_keys(Settings::PROVIDERS) as $provider) {
            if (in_array('plugins/payment-' . $provider, $enabled, true)) $gateways[$provider] = new PortOneGateway(new Settings($app, $provider));
        }
        $service = new Service($app, $gateways);
        $service->requireReady();
        if ($action === 'expire') echo $service->expire() . "건을 만료 처리했습니다.\n";
        elseif ($action === 'sync') { $result = $service->sync(Input::id($orderId)); echo '주문 상태: ' . $result['status'] . "\n"; }
        else {
            $db = $app->db();
            $rows = $db->select('SELECT o.id FROM ' . $db->table('shop_orders') . " o WHERE (o.checkout_started > 0 AND o.status IN ('pending','cancelled') AND o.created_at > ?)
                OR o.needs_review = 1 OR EXISTS (SELECT 1 FROM " . $db->table('shop_refunds') . " r WHERE r.order_id = o.id AND r.status = 'pending') ORDER BY o.checked_at, o.id LIMIT " . $limit, [time() - 604800]);
            $failed = 0;
            foreach ($rows as $row) {
                try { $service->sync($row['id']); } catch (Throwable) { $failed++; }
            }
            echo count($rows) . '건 조회, ' . $failed . "건 재확인 필요\n";
            if ($failed > 0) return 1;
        }
        return 0;
    });
    exit($status);
} catch (Throwable) {
    // 인증값·개인정보·DB 접속 문자열을 CLI 오류에 노출하지 않는다.
    fwrite(STDERR, "처리하지 못했습니다. 설정 파일, 확장 활성 상태, 결제 API 허용 및 주문번호를 확인해 주세요.\n");
    exit(1);
}
