<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use GnuCms\Tests\Support\AdminViewFixture;

$scenario = $argv[1] ?? 'core'; $base = $argv[2] ?? '/cms';
$view = AdminViewFixture::view($base);
$common = ['base' => $base, 'environment' => 'test', 'ready' => true, 'errors' => [], 'notice' => '', 'csrf_token' => 'browser-test-csrf'];
if ($scenario === 'core') {
    echo $view->fetch('admin/settings', ['query' => [], 'errors' => [], 'values' => [], 'timezones' => ['Asia/Seoul']]);
} elseif (in_array($scenario, ['core-list', 'core-modules'], true)) {
    $section = $scenario === 'core-list' ? 'plugins' : 'modules';
    $catalog = (new \GnuCms\Extension\Catalog(dirname(__DIR__, 2)))->all();
    $packages = [];
    foreach ($section === 'plugins' ? ['bizppurio', 'demo-message'] : ['demo-reservation'] as $id) {
        $package = $catalog[$section . '/' . $id];
        $prefix = $package['route_prefix'] ?? '/' . $section . '/' . $id;
        $package += ['enabled' => true, 'selected' => true,
            'entry_url' => $package['entry_path'] === null ? null : $base . \GnuCms\Extension\RoutePrefix::path($package['admin_route_prefix'] ?? $prefix, $package['entry_path']),
            'public_url' => $package['public_path'] === null ? null : $base . \GnuCms\Extension\RoutePrefix::path($prefix, $package['public_path'])];
        // 공개 주소가 있는 모듈도 표시할 수 있는지 확인하는 예제 데이터다.
        if ($id === 'demo-reservation') $package['public_url'] = $base . '/book';
        $packages[] = $package;
    }
    // 실제 모듈 개수와 무관하게 홀짝 행과 실행 주소 표시를 검증한다.
    if ($section === 'modules') $packages[] = array_replace($packages[0], [
        'key' => 'modules/example', 'id' => 'example', 'name' => '예제 모듈',
        'entry_url' => $base . '/modules/example/preview', 'public_url' => null,
    ]);
    echo $view->fetch('admin/extensions/index', ['extension_page' => ['title' => $section === 'plugins' ? '플러그인' : '모듈', 'description' => '확장 기능 관리', 'icon' => 'grid'], 'extension_section' => $section, 'extension_error' => null, 'saved' => false, 'packages' => $packages, 'changed_order' => '[]']);
} elseif ($scenario === 'bizppurio') {
    echo $view->forExtension('bizppurio', dirname(__DIR__, 2) . '/plugins/bizppurio/templates')->fetch('settings', $common + [
        'webhook' => null, 'settings' => ['configured' => true, 'enabled' => false, 'account' => 'browser-test', 'account_type' => 'module', 'revision' => str_repeat('a', 32),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000'],
    ]);
} else {
    $module = $scenario === 'demo-reservation'; $section = $module ? 'modules' : 'plugins';
    echo $view->forExtension($scenario, dirname(__DIR__, 2) . '/' . $section . '/' . $scenario . '/templates')->fetch('preview', $common + [
        'form_url' => $base . '/' . $section . '/' . $scenario . '/preview', 'values' => ['title' => '주문 안내', 'body' => '주문이 접수되었습니다.', 'name' => '방문자', 'date' => '2028-02-29', 'time' => '14:00', 'guests' => '2'],
        'result' => '안내문 미리보기입니다.', 'uses_plugin' => false, 'is_admin_test' => false,
    ]);
}
