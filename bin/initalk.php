<?php

declare(strict_types=1);

/**
 * 이니톡 결제 유지보수 CLI. 스케줄러(cron)에서 주기적으로 실행한다.
 *
 *   php bin/initalk.php expire   결제기한이 지난 요청을 만료시킨다(결제창을 연 지 30분 이내 건은 보호)
 *   php bin/initalk.php sync     최근 7일 안에 결제창을 연 미결 건과 확인 필요 건을 결제사에 조회한다
 *   php bin/initalk.php purge    종료 90일이 지난 요청의 구매자명·번호를 정리한다
 *   --config=/경로/config.php    다른 설정 파일을 쓴다
 *
 * 스키마는 실행할 때마다 웹 커널과 같은 경로로 맞춘다. sync는 조회하지 못한 건이 있으면 1로 끝낸다.
 */

use GnuCms\App;
use GnuCms\Payment\ExecutionLock;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("명령줄에서만 실행할 수 있습니다.\n");
}

$configFile = __DIR__ . '/../config/config.php';
$command = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--config=')) $configFile = substr($argument, 9);
    else $command = $argument;
}
if (!in_array($command, ['expire', 'sync', 'purge'], true)) {
    fwrite(STDERR, "사용법: php bin/initalk.php expire|sync|purge [--config=경로]\n");
    exit(1);
}
if (!is_file($configFile)) {
    fwrite(STDERR, "설정 파일을 찾을 수 없습니다: {$configFile}\n");
    exit(1);
}
$config = require $configFile;
if (!is_array($config) || !isset($config['db'])) {
    fwrite(STDERR, "설정 파일에 db 항목이 없습니다: {$configFile}\n");
    exit(1);
}

try {
    $app = new App($config, $configFile);
    // 배포 뒤 첫 웹 요청보다 cron이 먼저 돌 수 있다. 웹 커널과 같은 경로로 스키마를 맞춘다.
    $app->schemaUpgrader()->run();
    $service = $app->initalk();
    $failed = 0;
    if ($command === 'expire') {
        $count = $service->requests->expire(1000);
    } elseif ($command === 'purge') {
        $count = $service->requests->purge(1000);
    } else {
        $count = 0;
        foreach ($service->requests->needingSync(7 * 86400) as $id) {
            try {
                ExecutionLock::run($app->storageDir(), static fn () => $service->checkout->sync($id, 'cli'));
                $count++;
            } catch (Throwable $e) {
                $failed++;
                fwrite(STDERR, "  ! " . substr($id, 0, 8) . "…: 조회하지 못했습니다.\n");
            }
        }
    }
    echo "{$command}: {$count}건 처리\n";
    if ($failed > 0) {
        // cron·모니터링이 알아채도록 0으로 끝내지 않는다. 건별 사유는 위에 한 줄씩 있다.
        fwrite(STDERR, "실패 {$failed}건\n");
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    exit(1);
}
