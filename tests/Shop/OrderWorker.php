<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/modules/shop/autoload.php';

$config = json_decode(file_get_contents($argv[1]), true, 16, JSON_THROW_ON_ERROR);
$shop = new \GnuCms\Modules\Shop\Service(new \GnuCms\App($config), ['inicis' => new \GnuCms\Tests\Shop\FakeGateway()]);
echo "ready\n"; flush(); fgets(STDIN);
try {
    $shop->createOrder(bin2hex(random_bytes(16)), [$config['variant'] => 1], ['name' => '테스트', 'phone' => '01000000000', 'email' => 'test@example.test',
        'postcode' => '00000', 'address' => '주소', 'consent' => '1'], 'inicis', bin2hex(random_bytes(16)), 13000);
    echo 'ordered';
} catch (\GnuCms\Error\DomainError) { echo 'rejected'; }
