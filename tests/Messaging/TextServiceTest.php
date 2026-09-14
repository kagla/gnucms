<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Support\RuntimePermit;
use GnuCms\Mail\SecretCipher;
use GnuCms\Messaging\MessagingService;
use GnuCms\Messaging\TransportFailure;
use GnuCms\Support\Clock;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TextServiceTest extends DatabaseTestCase
{
    private App $app;
    private MessagingService $service;
    private FakeTransport $http;
    private string $root;

    private function setupService(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-text-' . bin2hex(random_bytes(6));
        $config['prefix'] = 'tx' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        Clock::freeze('2026-09-07 01:00:00');
        $this->http = new FakeTransport();
        $this->service = new MessagingService($this->app, $this->http);
        $this->service->settings->save('test', ['account' => 'text-test', 'password' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->service->settings->setEnabled('test', true);
    }

    private function input(array $overrides = []): array
    {
        return array_replace(['environment' => 'test', 'type' => 'sms', 'message' => '접수가 완료되었습니다.',
            'phone' => '010-0000-0000', 'idempotency_key' => 'event-1'], $overrides);
    }

    private function receipt(array $sent, array $overrides = []): array
    {
        $attempt = $sent['attempts_detail'][0];
        return array_replace(['DEVICE' => strtoupper($sent['channel']), 'MEDIA' => strtoupper($sent['channel']),
            'MSGID' => 'result-' . $attempt['refkey'], 'CMSGID' => $attempt['messagekey'], 'REFKEY' => $attempt['refkey'],
            'PHONE' => '01000000000', 'RESULT' => $sent['channel'] === 'sms' ? '4100' : '6600',
            'UNIXTIME' => (string) Clock::timestamp()], $overrides);
    }

    private function rejects(callable $action, int $status = 422): void
    {
        try { $action(); self::fail('must reject'); } catch (DomainError $e) { self::assertSame($status, $e->status()); }
    }

    #[DataProvider('connectionProvider')]
    public function testEnvironmentDeterminesRecipientLimitForNewAndLegacySettings(array $config): void
    {
        $this->setupService($config);
        $this->service->settings->save('test', ['account' => 'text-test', 'from' => '0212345678',
            'test_phone' => '01000000000', 'test_only' => '0']);
        // 운영 설정에는 테스트 번호를 입력하지 않아도 된다. 이전 폼의 제한 선택값도 무시한다.
        $this->service->settings->save('live', ['account' => 'text-live', 'password' => bin2hex(random_bytes(20)),
            'from' => '0212345678', 'test_only' => '1']);
        $cipher = new SecretCipher((string) $this->app->config('auth.secret'));
        $db = $this->app->db();
        foreach (['test', 'live'] as $environment) {
            $row = $db->selectOne('SELECT * FROM ' . $db->table('bp_settings') . ' WHERE environment = ?', [$environment]);
            $payload = json_decode($cipher->decrypt($row['payload']), true, 32, JSON_THROW_ON_ERROR);
            self::assertSame($environment === 'test', $payload['test_only']);
            self::assertSame($environment === 'test' ? '01000000000' : '', $payload['test_phone']);
            $this->service->settings->setEnabled($environment, true);
            // 예전 버전에서 환경과 반대로 저장한 제한값을 재현한다. 설정 재저장 없이 적용돼야 한다.
            $payload['test_only'] = $environment === 'live';
            $payload['test_phone'] = '01000000000';
            $db->update('bp_settings', ['payload' => $cipher->encrypt(json_encode($payload, JSON_THROW_ON_ERROR))],
                'environment = :env', ['env' => $environment]);
            $status = $this->service->status($environment);
            self::assertTrue($status['enabled']);
            self::assertSame($row['revision'], $this->service->settings->read($environment)['revision']);
            self::assertSame($environment === 'test', $status['test_only']);
            self::assertSame($environment === 'test' ? '01000000000' : '', $status['test_phone']);
        }
        foreach (['sms', 'lms'] as $type) {
            $input = $this->input(['type' => $type, 'subject' => $type === 'lms' ? '안내' : '',
                'phone' => '01011111111', 'idempotency_key' => $type]);
            $before = count($this->http->requests);
            $this->rejects(fn () => $this->service->previewText($input));
            $this->rejects(fn () => $this->service->sendText($input));
            self::assertCount($before, $this->http->requests);
            foreach (['test' => '01000000000', 'live' => '01011111111'] as $environment => $phone) {
                $send = array_replace($input, ['environment' => $environment, 'phone' => $phone]);
                self::assertSame($phone, $this->service->previewText($send)['phone']);
                self::assertSame('accepted', $this->service->sendText($send)['submission']);
                $request = $this->http->requests[array_key_last($this->http->requests)];
                self::assertSame($environment, $request['environment']);
                self::assertSame($phone, $request['body']['to']);
            }
        }
        self::assertSame(4, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testStandaloneSmsAndLmsUseExactApiPayloadAndEncryptedSnapshots(array $config): void
    {
        $this->setupService($config);
        self::assertSame('', $this->service->settings->read('test')['senderkey']);
        $preview = $this->service->previewText($this->input());
        self::assertSame('01000000000', $preview['phone']);
        self::assertSame(0, $this->http->count('/v3/message'));
        $this->rejects(fn () => $this->service->templates->save('test', ['name' => '알림', 'code' => 'notice', 'message' => '알림']));
        foreach (['sms', 'lms'] as $type) {
            $input = $this->input(['type' => $type, 'idempotency_key' => $type,
                'subject' => $type === 'lms' ? '비공개 제목' : '', 'reference' => 'private-reference']);
            $sent = $this->service->sendText($input);
            self::assertSame('accepted', $sent['submission']);
            self::assertSame('pending', $sent['delivery']);
            self::assertSame($type, $sent['channel']);
            self::assertArrayNotHasKey('phone', $sent['snapshot']);
            self::assertSame('010-****-0000', $sent['phone_mask']);
            self::assertSame('01000000000', $this->service->textDetail($sent['id'])['phone']);
            $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
            $content = ['message' => $input['message']];
            if ($type === 'lms') $content['subject'] = $input['subject'];
            self::assertSame(['account' => 'text-test', 'refkey' => $sent['attempts_detail'][0]['refkey'], 'type' => $type,
                'from' => '0212345678', 'to' => '01000000000', 'content' => [$type => $content]], $body);
            $row = $this->app->db()->selectOne('SELECT * FROM ' . $this->app->db()->table('bp_dispatches') . ' WHERE id = ?', [$sent['id']]);
            foreach (['01000000000', $input['message'], '비공개 제목', 'private-reference'] as $private) self::assertStringNotContainsString($private, json_encode($row, JSON_UNESCAPED_UNICODE));
            self::assertSame($sent['id'], $this->service->sendText($input)['id']);
            $this->rejects(fn () => $this->service->sendText(array_replace($input, ['message' => '변경'])));
            $this->rejects(fn () => $this->service->detail($sent['id']), 404);
            $this->rejects(fn () => $this->service->retry($sent['id']), 404);
            $this->rejects(fn () => $this->service->refresh($sent['id']), 404);
        }
        self::assertSame(2, $this->http->count('/v3/message'));
        self::assertSame(1, $this->http->count('/v1/token'));
        self::assertSame(2, $this->service->textHistory([])['total']);
        self::assertSame(1, $this->service->textHistory(['type' => 'lms'])['total']);
        self::assertSame(0, $this->service->history([])['total']);
        foreach ($this->service->textHistory([])['items'] as $item) {
            self::assertSame('01000000000', $item['phone']);
            foreach (['payload', 'snapshot', 'phone_hash', 'request_hash', 'idempotency_key'] as $private) self::assertArrayNotHasKey($private, $item);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testHistoryStillListsRecipientsWhenOneEncryptedPayloadIsUnreadable(array $config): void
    {
        $this->setupService($config);
        $first = $this->service->sendText($this->input());
        $second = $this->service->sendText($this->input(['idempotency_key' => 'second']));
        $db = $this->app->db();
        $db->update('bp_dispatches', ['payload' => 'unreadable'], 'id = :id', ['id' => $first['id']]);
        $history = $this->service->textHistory([]);
        self::assertSame(2, $history['total']);
        $items = array_column($history['items'], null, 'id');
        self::assertNull($items[$first['id']]['phone']);
        self::assertSame('01000000000', $items[$second['id']]['phone']);
        self::assertStringNotContainsString('unreadable', json_encode($history, JSON_THROW_ON_ERROR));
        self::assertSame(2, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testUnicodeArtworkReachesTheApiAndHistoryWithoutReplacement(array $config): void
    {
        $this->setupService($config);
        foreach (['sms', 'lms'] as $type) {
            $input = $this->input(['type' => $type, 'message' => TextFixtures::ART,
                'subject' => $type === 'lms' ? 'ʕ ᵔᆺᵔ ʔ' : '', 'idempotency_key' => $type]);
            $preview = $this->service->previewText($input);
            self::assertNull($preview['bytes']);
            self::assertSame(TextFixtures::ART, $preview['message']);
            $sent = $this->service->sendText($input);
            self::assertSame('accepted', $sent['submission']);
            self::assertSame('pending', $sent['delivery']);
            $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
            self::assertSame($type, $body['type']);
            self::assertSame(TextFixtures::ART, $body['content'][$type]['message']);
            self::assertSame($preview['content'], $body['content']);
            self::assertSame($body['content'], $this->service->textDetail($sent['id'])['snapshot']['content']);
            self::assertSame($sent['id'], $this->service->sendText($input)['id']);
        }
        self::assertSame(2, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testPermissionsRecipientAndStalePreviewAreRecheckedBeforeSend(array $config): void
    {
        $this->setupService($config);
        $preview = $this->service->previewText($this->input());
        $this->rejects(fn () => $this->service->sendText($this->input(['phone' => '01011111111'])));
        $this->rejects(fn () => $this->service->previewText($this->input(['phone' => '01011111111'])));
        $this->rejects(fn () => $this->service->sendText($this->input(['config_revision' => 'stale'])));
        $this->rejects(fn () => $this->service->sendText($this->input(['environment' => 'live'])));
        $this->rejects(fn () => $this->service->sendText($this->input(['message' => str_repeat('가', 46)])));
        $this->service->settings->setEnabled('test', false);
        $this->rejects(fn () => $this->service->sendText($this->input()));
        $this->service->settings->save('test', ['account' => 'text-test', 'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->service->settings->setEnabled('test', true);
        $this->rejects(fn () => $this->service->sendText($this->input(['config_revision' => $preview['config_revision']])));
        (new RuntimePermit($this->root))->revokeAll();
        $this->rejects(fn () => $this->service->sendText($this->input()));
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testChannelSpecificReceiptsMatchIdentityAndPreserveSuccess(array $config): void
    {
        $this->setupService($config);
        foreach (['sms', 'lms'] as $type) {
            $sent = $this->service->sendText($this->input(['type' => $type, 'idempotency_key' => $type]));
            foreach ([['MEDIA' => 'AT'], ['PHONE' => '01011111111'], ['CMSGID' => 'wrong-key']] as $bad) $this->service->results->receive('test', $this->receipt($sent, $bad));
            $this->service->results->receive('live', $this->receipt($sent));
            self::assertSame('pending', $this->service->textDetail($sent['id'])['delivery']);
            $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => '7000']));
            self::assertSame('uncertain', $this->service->textDetail($sent['id'])['delivery']);
            $failure = $type === 'sms' ? '4434' : '6631';
            $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => $failure, 'UNIXTIME' => (string) (Clock::timestamp() + 1)]));
            self::assertSame('failed', $this->service->textDetail($sent['id'])['delivery']);
            $this->service->settings->setEnabled('test', false);
            $this->service->results->receive('test', $this->receipt($sent));
            $this->service->results->receive('test', $this->receipt($sent));
            $this->service->results->receive('test', $this->receipt($sent, ['RESULT' => $failure, 'UNIXTIME' => (string) (Clock::timestamp() + 2)]));
            $detail = $this->service->textDetail($sent['id']);
            self::assertSame('delivered', $detail['delivery']);
            self::assertCount(4, $detail['receipts']);
            $this->service->settings->setEnabled('test', true);
        }
    }

    #[DataProvider('connectionProvider')]
    public function testUncertainSubmissionsAreNeverResentAndRateLimitRetryIsBounded(array $config): void
    {
        $this->setupService($config);
        foreach (['timeout', 'server', 'bad-response'] as $failure) {
            $this->http->respond = static function ($env, $path) use ($failure): ?array {
                if ($path !== '/v3/message') return null;
                if ($failure === 'timeout') throw new TransportFailure();
                return ['status' => $failure === 'server' ? 503 : 200, 'body' => ['code' => 1000]];
            };
            $input = $this->input(['idempotency_key' => $failure]);
            $sent = $this->service->sendText($input);
            self::assertSame('unknown', $sent['submission']);
            $this->rejects(fn () => $this->service->retryText($sent['id']));
            self::assertSame($sent['id'], $this->service->sendText($input)['id']);
        }
        self::assertSame(3, $this->http->count('/v3/message'));
        $this->http->respond = static fn ($env, $path): ?array => $path === '/v3/message' ? ['status' => 429, 'body' => ['code' => 5002]] : null;
        $limited = $this->service->sendText($this->input(['idempotency_key' => 'limited']));
        $this->rejects(fn () => $this->service->retryText($limited['id']));
        for ($i = 1; $i <= 2; $i++) {
            Clock::freeze('2026-09-07 01:0' . $i . ':00');
            self::assertSame('rejected', $this->service->retryText($limited['id'])['submission']);
        }
        Clock::freeze('2026-09-07 01:03:00');
        $this->rejects(fn () => $this->service->retryText($limited['id']));
        self::assertCount(3, $this->service->textDetail($limited['id'])['attempts_detail']);
    }

    #[DataProvider('connectionProvider')]
    public function testEarlyWebhookAndAuthenticationRefreshKeepTheTextChannel(array $config): void
    {
        $this->setupService($config);
        $this->http->respond = function ($env, $path, $headers, $body): ?array {
            if ($path !== '/v3/message') return null;
            if ($this->http->count($path) === 1) return ['status' => 401, 'body' => ['code' => 3002]];
            self::assertSame('lms', $body['type']);
            $this->service->results->receive('test', ['MSGID' => 'early', 'CMSGID' => 'early-key', 'REFKEY' => $body['refkey'],
                'MEDIA' => 'LMS', 'PHONE' => '01000000000', 'RESULT' => '6600', 'UNIXTIME' => (string) Clock::timestamp()]);
            throw new TransportFailure();
        };
        $sent = $this->service->sendText($this->input(['type' => 'lms']));
        self::assertSame('accepted', $sent['submission']);
        self::assertSame('delivered', $sent['delivery']);
        self::assertSame(2, $this->http->count('/v1/token'));
        self::assertSame(2, $this->http->count('/v3/message'));
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) {
            (new Schema($this->app->db()))->drop();
        }
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        parent::tearDown();
    }
}
