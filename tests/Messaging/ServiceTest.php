<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Messaging\MessagingService;
use GnuCms\Messaging\TransportFailure;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ServiceTest extends DatabaseTestCase
{
    private App $app;
    private MessagingService $service;
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
        $this->service = new MessagingService($this->app, $this->http);
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
        self::assertSame('01000000000', $this->service->detail($sent['id'])['phone'], '상세는 수신번호 원문을 준다.');
        $this->service->settings->setEnabled('test', false);
        $this->service->refresh($sent['id']);
        self::assertSame(1, $this->http->count('/v2/report'));
    }

    #[DataProvider('connectionProvider')]
    public function testPastedKeysAreTrimmedBeforeValidation(array $config): void
    {
        $this->setupService($config);
        $sender = bin2hex(random_bytes(20));
        $key = bin2hex(random_bytes(6));
        // 비즈뿌리오 화면에서 복사해 붙여 넣으면 앞뒤 공백·줄바꿈이 따라오기 쉽다.
        $this->service->settings->save('test', array_replace($this->settings(), ['account' => " test-account\n", 'senderkey' => "  $sender \r\n", 'kapi_key' => "\t$key  "]));
        $saved = $this->service->settings->read('test');
        self::assertSame('test-account', $saved['account']);
        self::assertSame($sender, $saved['senderkey']);
        self::assertSame($key, $saved['kapi_key']);
        // 공백만 있으면 비운 것으로 본다(발신프로필키 생략·기존 API 키 유지).
        $this->service->settings->save('test', array_replace($this->settings(), ['account' => 'test-account', 'senderkey' => '   ', 'kapi_key' => ' ']));
        self::assertSame('', $this->service->settings->read('test')['senderkey']);
        self::assertSame($key, $this->service->settings->read('test')['kapi_key']);
    }

    #[DataProvider('connectionProvider')]
    public function testFormattedSettingsNumbersAreStoredAndSentAsDigits(array $config): void
    {
        $this->setupService($config);
        foreach (['010-0000-0000' => '01000000000', '02-1234-5678' => '0212345678', '02-123-4567' => '021234567',
            '031-123-4567' => '0311234567', '070 1234 5678' => '07012345678', '1588-1234' => '15881234'] as $formatted => $digits) {
            $this->service->settings->save('test', ['account' => 'test-account', 'from' => $formatted, 'test_phone' => '010-0000-0000']);
            $saved = $this->service->settings->read('test');
            self::assertSame($digits, $saved['from']);
            self::assertSame('01000000000', $saved['test_phone']);
            $this->service->settings->setEnabled('test', true);
            self::assertSame('accepted', $this->service->send($this->input(['idempotency_key' => $digits]))['submission']);
            $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
            self::assertSame($digits, $body['from']);
            self::assertSame('01000000000', $body['to']);
        }
        foreach (['02-123', '12345678901234567', '02+12345678', '02a12345678'] as $invalid) {
            try {
                $this->service->settings->save('test', ['account' => 'test-account', 'from' => $invalid, 'test_phone' => '010-0000-0000']);
                self::fail('invalid sender must not be saved');
            } catch (DomainError $e) { self::assertSame(422, $e->status()); }
            self::assertSame('15881234', $this->service->settings->read('test')['from']);
        }
        self::assertSame(6, $this->http->count('/v3/message'));
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
        foreach ([3000 => '접속 허용 IP가 등록되어 있지', 3010 => '허용 IP와 일치하지', 3007 => '모듈 비밀번호가 유효하지',
            3006 => '선택한 테스트/운영 환경', 3009 => '중지 상태', 5002 => '호출 횟수 제한', 9999 => '응답 코드로 비즈뿌리오에 확인'] as $code => $expected) {
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
        (new \GnuCms\Support\RuntimePermit($this->root))->revokeAll();
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
    public function testLiveAlimtalkAllowsRecipientsBeyondTheTestNumber(array $config): void
    {
        $this->setupService($config);
        $this->service->settings->save('live', $this->settings());
        $template = $this->service->templates->save('live', ['name' => '운영 안내', 'code' => 'live_notice', 'message' => '접수가 완료되었습니다.']);
        $this->service->settings->setEnabled('live', true);
        $sent = $this->service->send($this->input(['environment' => 'live', 'template_id' => $template['id'],
            'revision' => $template['revision'], 'phone' => '01011111111', 'variables' => []]));
        self::assertSame('accepted', $sent['submission']);
        $request = $this->http->requests[array_key_last($this->http->requests)];
        self::assertSame('live', $request['environment']);
        self::assertSame('01011111111', $request['body']['to']);
        self::assertSame('at', $request['body']['type']);
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
    public function testOfficialRetryPolicyAllowsRetryAfterConcurrencyServerErrorsAndRateLimitReset(array $config): void
    {
        $this->setupService($config);
        $base = strtotime('2026-09-06 01:00:00');
        $cases = [['status' => 400, 'body' => ['code' => 3008], 'wait' => 30], ['status' => 502, 'body' => ['code' => 5003], 'wait' => 30],
            ['status' => 503, 'body' => ['code' => 5004], 'wait' => 30], ['status' => 504, 'body' => ['code' => 5005], 'wait' => 30],
            ['status' => 500, 'body' => ['code' => 9000], 'wait' => 30],
            ['status' => 429, 'body' => ['code' => 5002], 'headers' => ['RateLimit-Reset' => '90.5'], 'wait' => 91]];
        foreach ($cases as $n => $case) {
            Clock::freeze(date('Y-m-d H:i:s', $base));
            $this->http->respond = static fn ($env, $path): ?array => $path === '/v3/message' ? array_intersect_key($case, array_flip(['status', 'body', 'headers'])) : null;
            $sent = $this->service->send($this->input(['idempotency_key' => 'retry-' . $n]));
            self::assertSame('rejected', $sent['submission'], (string) $case['body']['code']);
            self::assertSame((string) $case['body']['code'], $sent['attempts_detail'][0]['result_code']);
            Clock::freeze(date('Y-m-d H:i:s', $base + $case['wait'] - 1));
            try { $this->service->retry($sent['id']); self::fail('too early: ' . $case['body']['code']); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
            Clock::freeze(date('Y-m-d H:i:s', $base + $case['wait']));
            $this->http->respond = null;
            self::assertSame('accepted', $this->service->retry($sent['id'])['submission'], (string) $case['body']['code']);
        }
        // 2000·3014·3015처럼 요청 자체가 거절된 건은 재시도 대상이 아니다.
        $this->http->respond = static fn ($env, $path): ?array => $path === '/v3/message' ? ['status' => 400, 'body' => ['code' => 3015]] : null;
        $sent = $this->service->send($this->input(['idempotency_key' => 'unsupported-type']));
        self::assertSame('rejected', $sent['submission']);
        Clock::freeze(date('Y-m-d H:i:s', $base + 3600));
        $this->http->respond = null;
        try { $this->service->retry($sent['id']); self::fail('3015 must not retry'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testReceiptsClassifyKakaoAndCommonCodesPerOfficialTables(array $config): void
    {
        $this->setupService($config);
        foreach (['7432' => 'failed', '7504' => 'failed', '7461' => 'pending', '9020' => 'failed', '7000' => 'delivered'] as $code => $delivery) {
            $code = (string) $code;
            $sent = $this->service->send($this->input(['idempotency_key' => 'code-' . $code]));
            $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => $code]));
            self::assertSame($delivery, $this->service->detail($sent['id'])['delivery'], $code);
        }
        $text = $this->service->sendText(['environment' => 'test', 'type' => 'sms', 'message' => '안내', 'phone' => '01000000000', 'idempotency_key' => 'sms-9023']);
        $this->service->results->receive('test', $this->receipt($text, ['DEVICE' => 'SMS', 'MEDIA' => 'SMS', 'RESULT' => '9023']));
        self::assertSame('failed', $this->service->textDetail($text['id'])['delivery']);
        self::assertStringContainsString('대표 링크', \GnuCms\Messaging\ResultCodes::describe('7342'));
        self::assertStringContainsString('재시도', \GnuCms\Messaging\ResultCodes::describe('9000'));
        self::assertStringContainsString('중단', \GnuCms\Messaging\ResultCodes::describe('7433'));
    }

    #[DataProvider('connectionProvider')]
    public function testReportReRequestExplainsRetentionAndPendingCarrierResult(array $config): void
    {
        $this->setupService($config);
        $sent = $this->service->send($this->input());
        foreach (['3012' => '35일', '3013' => '아직', '9000' => '결과 수신 URL'] as $code => $hint) {
            $code = (string) $code;
            $this->http->respond = static fn ($env, $path) => $path === '/v2/report' ? ['status' => $code === '9000' ? 500 : 400, 'body' => ['code' => (int) $code]] : null;
            try { $this->service->refresh($sent['id']); self::fail($code); } catch (DomainError $e) {
                self::assertSame(422, $e->status(), $code);
                self::assertStringContainsString($hint, implode(' ', $e->details()));
            }
        }
    }

    public function testSampleValuesFollowVariableRolesAndUrlPositions(): void
    {
        $template = ['message' => "[#{app_name}] #{이름}님 주문 #{order_number} · #{amount}원 · #{date}",
            'buttons' => [['type' => 'WL', 'name' => '보기', 'url_mobile' => 'https://#{action_url}/x/#{id}']],
            'link' => ['url_mobile' => 'https://#{site_url}'], 'variables' => ['app_name', '이름', 'order_number', 'amount', 'date', 'action_url', 'id', 'site_url']];
        $sample = \GnuCms\Messaging\Templates::sampleValues($template, 'GNUCMS 테스트', 'gnucms.example');
        self::assertSame('GNUCMS 테스트', $sample['app_name']);
        self::assertSame('홍길동', $sample['이름']);
        self::assertSame('gnucms.example', $sample['action_url'], '도메인 자리 변수는 사이트 호스트를 넣는다.');
        self::assertSame('gnucms.example', $sample['site_url']);
        self::assertMatchesRegularExpression('/^\d{8}-\d{4}$/', $sample['order_number']);
        self::assertSame('10,000', $sample['amount']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $sample['date']);
        self::assertSame('1', $sample['id']);
        self::assertSame(['app_name', '이름', 'order_number', 'amount', 'date', 'action_url', 'id', 'site_url'], array_keys($sample));
    }

    #[DataProvider('connectionProvider')]
    public function testTemplateRevisionValidationAndRetention(array $config): void
    {
        $this->setupService($config);
        foreach ([['이름' => '이름'], ['이름' => '#{번호}', '번호' => '1']] as $variables) {
            try { $this->service->preview($this->input(['variables' => $variables])); self::fail('invalid variables'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        // 공식 규격의 본문 한도는 변수 치환 후 1,300자다.
        $long = $this->service->templates->save('test', ['name' => '긴 본문', 'code' => 'long_1', 'message' => '#{이름}' . str_repeat('가', 1295)]);
        $longInput = fn (string $name): array => ['environment' => 'test', 'template_id' => $long['id'], 'revision' => $long['revision'], 'variables' => ['이름' => $name]];
        self::assertSame(1300, mb_strlen($this->service->preview($longInput('고객님께서'))['message']));
        try { $this->service->preview($longInput('고객님께서는')); self::fail('over 1300'); } catch (DomainError $e) { self::assertSame(422, $e->status()); }
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
        (new \GnuCms\Support\RuntimePermit($this->root))->revokeAll();
        self::assertFalse($this->service->settings->summary('test')['enabled']);
        $this->service->api->token($this->service->settings->read('test'));
        self::assertSame(3, $this->http->count('/v1/token'));
        // 접수는 됐지만 결과가 안 온 발송은 계정·프로필 변경을 막지 않는다. 결과 웹훅이 없으면 영원히 대기이기 때문이다.
        $changed = $this->settings();
        $this->service->settings->save('test', $changed);
        self::assertSame($changed['senderkey'], $this->service->settings->read('test')['senderkey']);
        self::assertFalse($this->service->templates->get($this->template['id'])['enabled'], '프로필이 바뀌면 기존 템플릿은 사용 중지된다.');
        // 전송 중·불명확 발송이 남아 있으면 계속 막는다.
        foreach (['sending', 'unknown'] as $state) {
            $this->app->db()->update('bp_dispatches', ['submission' => $state], 'id = :id', ['id' => $sent['id']]);
            try {
                $this->service->settings->save('test', $this->settings());
                self::fail('in-flight dispatch must prevent sender change: ' . $state);
            } catch (DomainError $e) {
                self::assertSame(422, $e->status());
                self::assertStringContainsString('전송 중이거나 결과가 불명확한 발송', implode(' ', $e->details()));
            }
        }
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
            ['buttons' => [['name' => '버튼', 'url_mobile' => '#{링크}']]], ['buttons' => [['name' => '버튼', 'url_mobile' => 'https://#{도메인}.example.com/']]],
            ['message' => '#{닫히지 않은 변수']] as $invalid) {
            try {
                $this->service->templates->save('test', array_replace(['name' => '형식 검사', 'code' => 'format', 'message' => '본문'], $invalid));
                self::fail('unsupported template');
            } catch (DomainError $e) { self::assertSame(422, $e->status()); }
        }
        // 카카오가 승인하는 형태대로 도메인 전체가 변수인 URL은 버튼·대표 링크 모두 허용한다. 경로 변수는 계속 인코딩한다.
        $hosted = $this->service->templates->save('test', ['name' => '도메인 변수', 'code' => 'hosted', 'message' => '#{이름}님',
            'buttons' => [['name' => '열기', 'url_mobile' => 'https://#{도메인}/page/#{번호}']], 'link' => ['url_mobile' => 'https://#{도메인}']]);
        $preview = $this->service->preview(['template_id' => $hosted['id'], 'variables' => ['이름' => '고객', '도메인' => 'shop.example.com', '번호' => 'a/b']]);
        self::assertSame('https://shop.example.com/page/a%2Fb', $preview['content']['at']['button'][0]['url_mobile']);
        self::assertSame('https://shop.example.com', $preview['content']['at']['link']['url_mobile']);
        self::assertCount(0, $this->http->requests);
    }
}
