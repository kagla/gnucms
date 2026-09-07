<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use GnuCms\Tests\Support\AdminViewFixture;
use GnuCms\Payment\{ProviderConfig, Settings};

$scenario = $argv[1] ?? 'core'; $base = $argv[2] ?? '/cms';
$view = AdminViewFixture::view($base);
$common = ['base' => $base, 'environment' => 'test', 'ready' => true, 'errors' => [], 'notice' => '', 'csrf_token' => 'browser-test-csrf'];
if ($scenario === 'core') {
    echo $view->fetch('admin/settings', ['query' => [], 'errors' => [], 'values' => [], 'timezones' => ['Asia/Seoul']]);
} elseif (in_array($scenario, ['core-list', 'core-modules'], true)) {
    $section = $scenario === 'core-list' ? 'plugins' : 'modules';
    $catalog = (new \GnuCms\Extension\Catalog(dirname(__DIR__, 2)))->all();
    $packages = [];
    foreach ($section === 'plugins' ? ['bizppurio', 'payment-toss'] : ['shop', 'alimtalk'] as $id) {
        $package = $catalog[$section . '/' . $id];
        $prefix = $package['route_prefix'] ?? '/' . $section . '/' . $id;
        $package += ['enabled' => true, 'selected' => true,
            'entry_url' => $base . \GnuCms\Extension\RoutePrefix::path($package['admin_route_prefix'] ?? $prefix, $package['entry_path']),
            'public_url' => $package['public_path'] === null ? null : $base . \GnuCms\Extension\RoutePrefix::path($prefix, $package['public_path'])];
        $packages[] = $package;
    }
    echo $view->fetch('admin/extensions/index', ['extension_page' => ['title' => $section === 'plugins' ? '플러그인' : '모듈', 'description' => '확장 기능 관리', 'icon' => 'grid'], 'extension_section' => $section, 'extension_error' => null, 'saved' => false, 'packages' => $packages, 'changed_order' => '[]']);
} elseif (in_array($scenario, ['inicis', 'kcp', 'kspay', 'toss', 'toss-live'], true)) {
    $provider = $scenario === 'toss-live' ? 'toss' : $scenario;
    if ($scenario === 'toss-live') $common['environment'] = 'live';
    echo $view->forExtension('payment', dirname(__DIR__, 2) . '/src/Payment/templates')->fetch('settings', $common + [
        'label' => Settings::PROVIDERS[$provider], 'key' => 'plugins/payment-' . $provider, 'fields' => ProviderConfig::fields($provider),
        'manual' => ProviderConfig::manual($provider), 'integration_ready' => in_array($provider, ['inicis', 'toss'], true),
        'settings' => ['configured' => true, 'enabled' => false, 'merchant_id' => $scenario === 'kcp' ? 'T9999' : '2999900000', 'client_ip' => '192.0.2.10'],
    ]);
} elseif ($scenario === 'bizppurio') {
    echo $view->forExtension('bizppurio', dirname(__DIR__, 2) . '/plugins/bizppurio/templates')->fetch('settings', $common + [
        'webhook' => null, 'settings' => ['configured' => true, 'enabled' => false, 'account' => 'browser-test', 'account_type' => 'module', 'revision' => str_repeat('a', 32),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000'],
    ]);
} elseif (str_starts_with($scenario, 'alimtalk-')) {
    $page = substr($scenario, 9); $id = str_repeat('a', 32);
    $template = ['id' => $id, 'environment' => 'test', 'revision' => 'revision', 'code' => 'order', 'name' => '주문 안내', 'enabled' => true, 'message' => '#{이름}님, 주문이 접수되었습니다.', 'variables' => ['이름'], 'buttons' => []];
    $detail = ['environment' => 'test', 'id' => $id, 'template_name' => '주문 안내', 'created_at' => 1788652800, 'phone_mask' => '010****0000', 'submission' => 'accepted', 'delivery' => 'delivered', 'snapshot' => ['content' => ['at' => ['message' => '테스트 구매자님, 주문이 접수되었습니다.']]], 'attempts_detail' => [], 'receipts' => []];
    echo $view->forExtension('alimtalk', dirname(__DIR__, 2) . '/modules/alimtalk/templates')->fetch('page', $common + [
        'page' => $page, 'templates' => [$template], 'selected' => $template, 'preview' => null, 'confirmation' => null, 'detail' => $detail,
        'history' => ['items' => [$detail], 'page' => 1, 'total' => 1], 'values' => [],
        'account_status' => ['configured' => true, 'enabled' => true, 'api_verified' => true, 'account_type' => 'module', 'account' => 'browser-test', 'test_only' => true, 'test_phone' => '01000000000'],
        'time' => static fn ($time): string => gmdate('Y-m-d H:i', $time), 'status_labels' => ['accepted' => '접수됨', 'delivered' => '도달 성공', 'prepared' => '준비', 'sending' => '접수 확인 중', 'rejected' => '거절', 'unknown' => '확인 중'],
    ]);
} else {
    $module = $scenario === 'demo-reservation'; $section = $module ? 'modules' : 'plugins';
    echo $view->forExtension($scenario, dirname(__DIR__, 2) . '/' . $section . '/' . $scenario . '/templates')->fetch('preview', $common + [
        'form_url' => $base . '/' . $section . '/' . $scenario . '/preview', 'values' => ['title' => '주문 안내', 'body' => '주문이 접수되었습니다.', 'name' => '방문자', 'date' => '2028-02-29', 'time' => '14:00', 'guests' => '2'],
        'result' => '안내문 미리보기입니다.', 'uses_plugin' => false, 'is_admin_test' => false,
    ]);
}
