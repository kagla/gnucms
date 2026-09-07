<?php

declare(strict_types=1);

namespace GnuCms\Tests\Bizppurio;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Plugins\Bizppurio\Service;
use GnuCms\Plugins\Bizppurio\TransportFailure;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 2) . '/plugins/bizppurio/autoload.php';

final class ServiceTest extends DatabaseTestCase
{
    private App $app;
    private Service $service;
    private FakeTransport $http;
    private string $root;
    private array $template;
    private string $password;

    private function setupService(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-bp-' . bin2hex(random_bytes(6));
        $config['prefix'] = 'bp' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-06 01:00:00');
        $this->http = new FakeTransport();
        $this->service = new Service($this->app, $this->http);
        self::assertFalse($this->service->ready());
        $this->service->install();
        $this->service->install();
        self::assertTrue($this->service->ready());
        $this->password = bin2hex(random_bytes(20));
        $this->service->settings->save('test', $this->settings());
        $this->template = $this->service->templates->save('test', ['name' => '접수 안내', 'code' => 'notice_1',
            'message' => "#{이름}님\n접수가 완료되었습니다.", 'buttons' => [
                ['name' => '확인', 'url_mobile' => 'https://example.com/view?id=#{번호}'],
            ]]);
        $this->service->settings->setEnabled('test', true);
    }

    private function settings(): array
    {
        return ['account' => 'test-account', 'password' => $this->password, 'senderkey' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000', 'test_only' => '1'];
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['environment' => 'test', 'template_id' => $this->template['id'], 'revision' => $this->template['revision'],
            'idempotency_key' => 'event-1', 'phone' => '01000000000', 'variables' => ['이름' => '테스트', '번호' => 'a&b/한글']], $overrides);
    }

    private function receipt(array $sent, array $overrides = []): array
    {
        $attempt = $sent['attempts_detail'][0];
        return array_replace(['DEVICE' => 'AT', 'MEDIA' => 'AT', 'MSGID' => 'result-' . $attempt['refkey'],
            'CMSGID' => $attempt['messagekey'], 'REFKEY' => $attempt['refkey'], 'PHONE' => '01000000000',
            'RESULT' => '7000', 'UNIXTIME' => (string) Clock::timestamp()], $overrides);
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) {
            foreach (array_reverse(\GnuCms\Plugins\Bizppurio\Schema::TABLES) as $table) $this->app->db()->execute('DROP TABLE IF EXISTS ' . $this->app->db()->table($table));
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testPreviewSendIdempotencyEncryptionAndResults(array $config): void
    {
        $this->setupService($config);
        $preview = $this->service->preview($this->input());
        self::assertSame("테스트님\n접수가 완료되었습니다.", $preview['message']);
        self::assertSame('https://example.com/view?id=a%26b%2F%ED%95%9C%EA%B8%80', $preview['buttons'][0]['url_mobile']);
        self::assertCount(0, $this->http->requests);
        $sent = $this->service->send($this->input());
        self::assertSame('accepted', $sent['submission']);
        self::assertSame('pending', $sent['delivery']);
        self::assertSame($sent['id'], $this->service->send($this->input())['id']);
        self::assertSame(1, $this->http->count('/v3/message'));
        self::assertSame(1, $this->http->count('/v1/token'));
        $this->service->send($this->input(['idempotency_key' => 'event-2']));
        self::assertSame(1, $this->http->count('/v1/token'));
        $raw = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('bp_dispatches') . ' WHERE id = ?', [$sent['id']]);
        self::assertStringNotContainsString('01000000000', $raw['payload']);
        self::assertStringNotContainsString('테스트', $raw['payload']);
        $settings = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('bp_settings'));
        self::assertStringNotContainsString($this->password, $settings['payload']);
        $receipt = $this->receipt($sent);
        $this->service->results->receive('test', $receipt);
        $this->service->results->receive('test', $receipt);
        self::assertSame('delivered', $this->service->detail($sent['id'])['delivery']);
        self::assertCount(1, $this->service->detail($sent['id'])['receipts']);
        $this->service->settings->setEnabled('test', false);
        $this->service->refresh($sent['id']);
        self::assertSame(1, $this->http->count('/v2/report'));
    }

    #[DataProvider('connectionProvider')]
    public function testWebAccountRequiresFreshAuthenticationAndUsesTheSameProtectedSendFlow(array $config): void
    {
        $this->setupService($config);
        $settings = $this->service->settings->read('test');
        $this->service->settings->save('test', array_replace($settings, ['account_type' => 'web', 'test_only' => '1', 'webhook_ips' => '']));
        self::assertSame('web', $this->service->status('test')['account_type']);
        self::assertFalse($this->service->status('test')['api_verified']);
        try { $this->service->settings->setEnabled('test', true); self::fail('web account must be verified'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        self::assertCount(0, $this->http->requests);
        $this->service->connect('test');
        self::assertSame(0, $this->http->count('/v3/message'));
        self::assertTrue($this->service->status('test')['api_verified']);
        self::assertFalse($this->service->status('test')['enabled']);
        self::assertArrayNotHasKey('password', $this->service->status('test'));
        self::assertArrayNotHasKey('webhook_token', $this->service->status('test'));
        $this->service->settings->setEnabled('test', true);
        $sent = $this->service->send($this->input());
        self::assertSame('accepted', $sent['submission']);
        self::assertSame($sent['id'], $this->service->send($this->input())['id']);
        self::assertSame(1, $this->http->count('/v3/message'));
        $this->service->results->receive('test', $this->receipt($sent));
        self::assertSame('delivered', $this->service->detail($sent['id'])['delivery']);
        $this->http->respond = static fn ($env, $path): ?array => $path === '/v1/token' ? ['status' => 403, 'body' => ['code' => 3009]] : null;
        try { $this->service->connect('test'); self::fail('must not use cached authentication'); }
        catch (DomainError $e) { self::assertSame(503, $e->status()); }
        self::assertSame(2, $this->http->count('/v1/token'));
        self::assertFalse($this->service->status('test')['api_verified']);
        self::assertFalse($this->service->status('test')['enabled']);
        try { $this->service->send($this->input(['idempotency_key' => 'blocked'])); self::fail('must not send after failed connection'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        self::assertSame(1, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testAuthenticationReportsProviderCodeWithoutExposingRawResponse(array $config): void
    {
        $this->setupService($config);
        foreach ([3000 => '접속 허용 IP가 등록되어 있지', 3010 => '허용 IP와 일치하지', 3007 => 'API 연동용 모듈 비밀번호가 유효하지',
            3006 => '선택한 검수/운영 환경', 3009 => '중지 상태', 5002 => '호출 횟수 제한', 9999 => '응답 코드로 비즈뿌리오에 확인'] as $code => $expected) {
            $this->http->respond = fn (): array => ['status' => 400, 'body' => ['code' => $code, 'description' => $this->password]];
            try { $this->service->connect('test'); self::fail('must reject authentication'); }
            catch (DomainError $e) {
                self::assertSame('BIZPPURIO_AUTH', $e->code());
                self::assertStringContainsString('HTTP 400 · 코드 ' . $code, $e->getMessage());
                self::assertStringContainsString($expected, $e->getMessage());
                self::assertStringNotContainsString($this->password, $e->getMessage());
                if (!in_array($code, [3000, 3010], true)) self::assertStringNotContainsString('접속 허용 IP', $e->getMessage());
            }
        }
        $this->http->respond = fn (): array => ['status' => 403, 'body' => ['code' => $this->password, 'description' => $this->password]];
        try { $this->service->connect('test'); self::fail('must reject invalid error code'); }
        catch (DomainError $e) { self::assertStringNotContainsString($this->password, $e->getMessage()); }
        $this->http->respond = static function (): array { throw new TransportFailure(); };
        try { $this->service->connect('test'); self::fail('must reject network failure'); }
        catch (DomainError $e) { self::assertStringContainsString('인증 서버의 응답을 확인하지 못했습니다', $e->getMessage()); }
        self::assertSame(0, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testWebAccountVerificationIsInvalidatedBySettingsChangesAndRestore(array $config): void
    {
        $this->setupService($config);
        $settings = $this->service->settings->read('test');
        $input = array_replace($settings, ['account_type' => 'web', 'test_only' => '1', 'webhook_ips' => '']);
        $this->service->settings->save('test', $input);
        $this->service->connect('test');
        $this->service->settings->setEnabled('test', true);
        $this->service->settings->save('test', $input);
        self::assertFalse($this->service->status('test')['api_verified']);
        self::assertFalse($this->service->status('test')['enabled']);
        $this->service->connect('test');
        $this->service->settings->setEnabled('test', true);
        (new \GnuCms\Extension\RuntimePermit($this->root))->revokeAll();
        self::assertFalse($this->service->status('test')['api_verified']);
        self::assertFalse($this->service->status('test')['enabled']);
        try { $this->service->settings->setEnabled('test', true); self::fail('restore must require verification'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        try { $this->service->settings->save('test', array_replace($input, ['account_type' => ['web']])); self::fail('invalid type'); }
        catch (DomainError $e) { self::assertSame(422, $e->status()); }
        self::assertSame('web', $this->service->status('test')['account_type']);
        self::assertSame(0, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testTimeoutIsNotResentAndLateReceiptResolvesIt(array $config): void
    {
        $this->setupService($config);
        $this->http->respond = static function ($env, $path): ?array {
            if ($path === '/v3/message') throw new TransportFailure();
            return null;
        };
        $sent = $this->service->send($this->input());
        self::assertSame('unknown', $sent['submission']);
        $this->service->send($this->input());
        self::assertSame(1, $this->http->count('/v3/message'));
        try { $this->service->retry($sent['id']); self::fail('unknown must not retry'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        $this->service->results->receive('test', $this->receipt($sent, ['CMSGID' => 'late-key']));
        self::assertSame('delivered', $this->service->detail($sent['id'])['delivery']);
    }

    #[DataProvider('connectionProvider')]
    public function testReceiptCanArriveBeforeHttpResponseAndDuplicateClick(array $config): void
    {
        $this->setupService($config);
        $this->http->respond = function ($env, $path, $headers, $body): ?array {
            if ($path !== '/v3/message') return null;
            $duplicate = $this->service->send($this->input());
            self::assertSame('sending', $duplicate['submission']);
            $this->service->results->receive('test', ['MEDIA' => 'AT', 'MSGID' => 'early', 'CMSGID' => 'm' . $body['refkey'],
                'REFKEY' => $body['refkey'], 'PHONE' => $body['to'], 'RESULT' => '7000', 'UNIXTIME' => (string) Clock::timestamp()]);
            return null;
        };
        $sent = $this->service->send($this->input());
        self::assertSame('accepted', $sent['submission']);
        self::assertSame('delivered', $sent['delivery']);
        self::assertSame(1, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testDifferentPayloadSameKeyAndWrongRecipientAreBlocked(array $config): void
    {
        $this->setupService($config);
        $sent = $this->service->send($this->input());
        foreach ([$this->input(['variables' => ['이름' => '변경', '번호' => '1']]), $this->input(['idempotency_key' => 'wrong-phone', 'phone' => '01011111111'])] as $input) {
            try { $this->service->send($input); self::fail('must reject'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        $this->service->results->receive('test', $this->receipt($sent, ['PHONE' => '01011111111']));
        self::assertSame('pending', $this->service->detail($sent['id'])['delivery']);
        self::assertSame(1, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testExpiredAuthenticationAndRateLimitHaveBoundedRetries(array $config): void
    {
        $this->setupService($config);
        $this->http->respond = function ($env, $path): ?array {
            return $path === '/v3/message' && $this->http->count($path) === 1 ? ['status' => 400, 'body' => ['code' => 3002]] : null;
        };
        $sent = $this->service->send($this->input());
        self::assertSame('accepted', $sent['submission']);
        self::assertCount(2, $sent['attempts_detail']);
        self::assertSame(2, $this->http->count('/v1/token'));
        $this->http->respond = static fn ($env, $path): ?array => $path === '/v3/message' ? ['status' => 429, 'body' => ['code' => 5002]] : null;
        $limited = $this->service->send($this->input(['idempotency_key' => 'limited']));
        self::assertSame('rejected', $limited['submission']);
        try { $this->service->retry($limited['id']); self::fail('too early'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2026-09-06 01:01:00');
        $this->http->respond = null;
        self::assertSame('accepted', $this->service->retry($limited['id'])['submission']);
    }

    #[DataProvider('connectionProvider')]
    public function testTemplateRevisionValidationAndRetention(array $config): void
    {
        $this->setupService($config);
        foreach ([['이름' => '이름'], ['이름' => '#{번호}', '번호' => '1'], ['이름' => str_repeat('가', 1000), '번호' => '1']] as $variables) {
            try { $this->service->preview($this->input(['variables' => $variables])); self::fail('invalid variables'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        $sent = $this->service->send($this->input());
        $this->service->templates->save('test', ['id' => $this->template['id'], 'revision' => $this->template['revision'],
            'name' => '수정', 'code' => $this->template['code'], 'message' => '수정된 본문']);
        try { $this->service->send($this->input(['idempotency_key' => 'stale'])); self::fail('stale template'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        Clock::freeze('2027-01-01 00:00:00');
        self::assertSame(1, $this->service->purge());
        self::assertNull($this->service->detail($sent['id'])['snapshot']);
        self::assertSame($sent['id'], $this->service->send($this->input())['id']);
        self::assertSame(1, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testSettingsChangeRevokesPermissionAndCacheAndKeepsReportRecovery(array $config): void
    {
        $this->setupService($config);
        $sent = $this->service->send($this->input());
        $before = $this->service->settings->read('test');
        $this->service->settings->save('test', ['account' => $before['account'], 'password' => '', 'senderkey' => $before['senderkey'],
            'from' => $before['from'], 'test_phone' => $before['test_phone']]);
        self::assertFalse($this->service->settings->summary('test')['enabled']);
        self::assertSame($this->password, $this->service->settings->read('test')['password']);
        $this->service->refresh($sent['id']);
        self::assertSame(2, $this->http->count('/v1/token'));
        $this->service->settings->setEnabled('test', true);
        (new \GnuCms\Extension\RuntimePermit($this->root))->revokeAll();
        self::assertFalse($this->service->settings->summary('test')['enabled']);
        $this->service->api->token($this->service->settings->read('test'));
        self::assertSame(3, $this->http->count('/v1/token'));
        try {
            $this->service->settings->save('test', $this->settings());
            self::fail('unresolved dispatch must prevent sender change');
        } catch (DomainError $e) { self::assertSame(422, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testUncertainAndCorrectedReceiptsDoNotDowngradeDelivery(array $config): void
    {
        $this->setupService($config);
        $sent = $this->service->send($this->input());
        $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => '7305']));
        self::assertSame('uncertain', $this->service->detail($sent['id'])['delivery']);
        $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => '7000']));
        $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => '7319', 'UNIXTIME' => (string) (Clock::timestamp() - 10)]));
        self::assertSame('delivered', $this->service->detail($sent['id'])['delivery']);
        self::assertCount(3, $this->service->detail($sent['id'])['receipts']);
    }

    #[DataProvider('connectionProvider')]
    public function testUnsupportedTemplatesAndHostVariablesAreRejected(array $config): void
    {
        $this->setupService($config);
        foreach ([['type' => 'ai'], ['title' => '강조'], ['buttons' => [['name' => '버튼', 'type' => 'AL', 'url_mobile' => 'https://example.com']]],
            ['buttons' => [['name' => '버튼', 'url_mobile' => 'https://#{도메인}/page']]], ['message' => '#{닫히지 않은 변수']] as $invalid) {
            try {
                $this->service->templates->save('test', array_replace(['name' => '형식 검사', 'code' => 'format', 'message' => '본문'], $invalid));
                self::fail('unsupported template');
            } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        self::assertCount(0, $this->http->requests);
    }
}
