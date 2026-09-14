<?php

declare(strict_types=1);

namespace GnuCms\Tests\Initalk;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Settings;
use GnuCms\Messaging\MessagingService;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends DatabaseTestCase
{
    private App $app;
    private string $root;

    private function setupApp(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-initalk-settings-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'is' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        $this->app->setMessaging(new MessagingService($this->app, new FakeTransport()));
        $this->app->cms()->saveSettings(['site_name' => '테스트 상점']);
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    private function template(array $overrides = []): array
    {
        $messaging = $this->app->messaging();
        $messaging->settings->save('test', ['account' => 'initalk-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000']);
        return $messaging->templates->save('test', $overrides + ['code' => 'initalk_pay', 'name' => '결제 안내',
            'message' => "[#{상점명}] #{구매자명}님, #{요청일} 요청하신 결제정보를 안내드립니다.\n금액: #{금액}원\n결제기한: #{결제기한}\n고객센터: #{고객센터}",
            'buttons' => [['name' => '결제하기', 'url_mobile' => 'https://shop.example.test/pay/#{결제토큰}']]]);
    }

    #[DataProvider('connectionProvider')]
    public function testDefaultsValidationAndTemplateRules(array $config): void
    {
        $this->setupApp($config);
        $settings = new Settings($this->app);
        $defaults = $settings->read();
        self::assertSame('테스트 상점', $defaults['store_name']);
        self::assertSame('', $defaults['support_phone']);
        self::assertSame(48, $defaults['expiry_hours']);
        self::assertSame('test', $defaults['environment']);
        self::assertSame(['test' => '', 'live' => ''], $defaults['template']);
        self::assertSame(3, $defaults['settlement_days']);
        $template = $this->template();
        $settings->save(['store_name' => '이니 상점', 'support_phone' => '1588-1234', 'expiry_hours' => '72', 'environment' => 'test',
            'template_test' => $template['id'], 'template_live' => '', 'settlement_days' => '5']);
        $saved = $settings->read();
        self::assertSame('이니 상점', $saved['store_name']);
        self::assertSame('1588-1234', $saved['support_phone']);
        self::assertSame(72, $saved['expiry_hours']);
        self::assertSame(5, $saved['settlement_days']);
        self::assertSame($template['id'], $settings->templateId('test'));
        self::assertSame('', $settings->templateId('live'));
        foreach ([
            ['store_name' => ''], ['expiry_hours' => '0'], ['expiry_hours' => '721'], ['environment' => 'prod'], ['settlement_days' => '-1'],
            ['template_test' => 'not-an-id'], ['template_live' => $template['id']],
        ] as $bad) {
            try {
                $settings->save($bad + ['store_name' => '이니 상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => '', 'template_live' => '']);
                self::fail('rejected: ' . json_encode($bad));
            } catch (DomainError $e) {
                self::assertSame(422, $e->status());
            }
        }
        $noToken = $this->template(['code' => 'no_token', 'buttons' => [['name' => '보기', 'url_mobile' => 'https://shop.example.test/view/#{주문번호}']]]);
        $this->expectException(DomainError::class);
        $settings->save(['store_name' => '이니 상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => $noToken['id'], 'template_live' => '']);
    }

    #[DataProvider('connectionProvider')]
    public function testUnknownVariableInTemplateIsRejected(array $config): void
    {
        $this->setupApp($config);
        $template = $this->template(['code' => 'extra_var', 'message' => '#{구매자명}님 #{없는변수}']);
        $this->expectException(DomainError::class);
        (new Settings($this->app))->save(['store_name' => '상점', 'expiry_hours' => '48', 'environment' => 'test', 'settlement_days' => '3', 'template_test' => $template['id'], 'template_live' => '']);
    }
}
