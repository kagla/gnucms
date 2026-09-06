<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// 환경변수가 없으면 MySQL/MariaDB 테스트를 건너뛰므로 실제 실행 대상을 표시한다.
$activeDatabases = ['sqlite'];
if ((string) getenv('TEST_MYSQL_DSN') !== '') {
    $activeDatabases[] = 'mysql';
}
fwrite(
    STDERR,
    '테스트 대상 DB: ' . implode(', ', $activeDatabases)
        . (count($activeDatabases) === 2 ? '' : '  <-- MySQL/MariaDB를 건너뜁니다')
        . PHP_EOL
);
