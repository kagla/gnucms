<?php

declare(strict_types=1);

/**
 * 스키마 마이그레이션 실행기.
 *
 * Schema::create() 는 테이블이 이미 있으면 아무것도 하지 않으므로, 기능이 추가되며
 * 늘어난 컬럼은 이 스크립트로 따로 반영해야 한다. 지금까지 migrate* 메서드를 부르는
 * 곳이 테스트뿐이어서, 배포한 뒤 컬럼이 없어 조용히 저장되지 않는 일이 있었다.
 *
 * 웹 요청이 들어올 때도 Kernel 이 같은 일을 자동으로 하므로 보통은 부르지 않아도 된다.
 * 배포 직후 첫 요청 전에 미리 맞추고 싶을 때 쓴다.
 *
 *   php bin/migrate.php                 config/config.php 를 읽는다
 *   php bin/migrate.php /경로/config.php 다른 설정 파일을 쓴다
 *
 * 여러 번 돌려도 안전하다.
 */

use GnuCms\Db\Connection;
use GnuCms\Db\Schema;
use GnuCms\Db\SchemaUpgrader;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("명령줄에서만 실행할 수 있습니다.\n");
}

$configFile = $argv[1] ?? __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "설정 파일을 찾을 수 없습니다: {$configFile}\n");
    exit(1);
}

/** @var array $config */
$config = require $configFile;
if (!is_array($config) || !isset($config['db'])) {
    fwrite(STDERR, "설정 파일에 db 항목이 없습니다: {$configFile}\n");
    exit(1);
}

try {
    $db = Connection::create($config['db']);
    $schema = new Schema($db);

    $schema->create();
    echo "  ✓ 테이블 생성(없을 때만)\n";
    $schema->migrateAll();
    echo "  ✓ 스키마 갱신 (판 " . Schema::VERSION . ")\n";
    // 도장·백업 비교 없이, 코어로 흡수된 패키지의 사용 상태만 정리한다(run()의 나머지 경로는 쓰지 않는다).
    $storageDir = rtrim((string) ($config['storage']['dir'] ?? dirname(__DIR__) . '/storage'), '/');
    (new SchemaUpgrader($db, $storageDir))->retireLegacyPackages();
    echo "  ✓ 코어로 흡수된 패키지의 사용 상태 정리\n";
} catch (Throwable $e) {
    fwrite(STDERR, "실패: " . $e->getMessage() . "\n");
    exit(1);
}

echo "마이그레이션을 마쳤습니다.\n";
