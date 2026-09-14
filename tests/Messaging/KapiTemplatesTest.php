<?php

declare(strict_types=1);

namespace GnuCms\Tests\Messaging;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Error\DomainError;
use GnuCms\Messaging\MessagingService;
use GnuCms\Messaging\TransportFailure;
use GnuCms\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class KapiTemplatesTest extends DatabaseTestCase
{
    private App $app;
    private MessagingService $service;
    private FakeTransport $http;
    private string $root;
    private array $settings;
    private array $remote;

    private function setupService(array $config): void
    {
        $this->root = sys_get_temp_dir() . '/gnucms-kapi-' . bin2hex(random_bytes(6));
        $config['prefix'] = 'kt' . bin2hex(random_bytes(4)) . '_';
        $this->app = new App(['db' => $config, 'storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        (new Schema($this->app->db()))->create();
        $this->http = new FakeTransport();
        $this->service = new MessagingService($this->app, $this->http);
        $this->settings = ['account' => 'kapi-test', 'password' => bin2hex(random_bytes(20)), 'kapi_key' => bin2hex(random_bytes(24)),
            'senderkey' => bin2hex(random_bytes(20)), 'from' => '0212345678', 'test_phone' => '01000000000'];
        $this->service->settings->save('test', $this->settings);
        $this->remote = ['senderKey' => $this->settings['senderkey'], 'senderKeyType' => 'S', 'templateCode' => 'reservation_1',
            'templateName' => '예약 안내', 'templateContent' => " #{이름}님\n예약이 접수되었습니다. ",
            'templateMessageType' => 'BA', 'templateEmphasizeType' => 'NONE', 'inspectionStatus' => 'APR',
            'status' => 'R', 'block' => false, 'dormant' => false, 'securityFlag' => false,
            'buttons' => [['ordering' => 2, 'name' => '문의', 'linkType' => 'WL', 'linkMo' => 'https://example.com/help', 'linkPc' => null],
                ['ordering' => 1, 'name' => '예약 확인', 'linkType' => 'WL', 'linkMo' => 'https://example.com/reservations/#{번호}', 'linkPc' => 'https://example.com/pc/#{번호}']]];
        $this->http->respond = function ($environment, $path, $headers, $body): ?array {
            if ($path === '/v3/kakao/template/detail') return ['status' => 200, 'body' => ['code' => '200', 'data' => $this->remote]];
            if ($path === '/v3/kakao/template/list') return ['status' => 200, 'body' => ['code' => '200', 'totalCount' => 21,
                'totalPage' => 2, 'currentPage' => $body['page'], 'data' => ['list' => [array_intersect_key($this->remote,
                    array_flip(['senderKey', 'senderKeyType', 'templateCode', 'templateName'])) + ['serviceStatus' => 'RDY']]]]];
            return null;
        };
    }

    private function detail(): array
    {
        return $this->service->templateAction('remote-detail', ['environment' => 'test', 'remote_code' => 'reservation_1']);
    }

    private function import(array $detail, array $extra = []): array
    {
        return $this->service->templateAction('remote-import', $extra + ['environment' => 'test', 'remote_code' => $detail['code'],
            'config_revision' => $detail['config_revision'], 'local_revision' => $detail['local_revision']]);
    }

    private function rejected(callable $work, int $status = 422): DomainError
    {
        try { $work(); self::fail('요청이 거절되어야 합니다.'); }
        catch (DomainError $error) { self::assertSame($status, $error->status()); return $error; }
    }

    #[DataProvider('connectionProvider')]
    public function testKeyIsEncryptedPreservedClearedAndNeverExposedInPublicStatus(array $config): void
    {
        $this->setupService($config);
        $payload = $this->app->db()->selectOne('SELECT payload FROM ' . $this->app->db()->table('bp_settings'))['payload'];
        self::assertStringNotContainsString($this->settings['kapi_key'], $payload);
        self::assertArrayNotHasKey('kapi_key', $this->service->settings->summary('test'));
        self::assertArrayNotHasKey('kapi_key', $this->service->status('test'));
        self::assertTrue($this->service->status('test')['kapi_configured']);
        $this->service->settings->save('test', array_replace($this->settings, ['kapi_key' => '']));
        self::assertSame($this->settings['kapi_key'], $this->service->settings->read('test')['kapi_key']);
        $this->service->settings->save('test', $this->settings + ['clear_kapi_key' => '1']);
        self::assertFalse($this->service->status('test')['kapi_configured']);
        $this->rejected(fn () => $this->detail());
        self::assertSame([], $this->http->requests);
        $this->service->settings->save('test', $this->settings);
        $this->service->settings->save('test', array_replace($this->settings, ['account' => 'another-account', 'kapi_key' => '']));
        self::assertFalse($this->service->status('test')['kapi_configured']);
    }

    #[DataProvider('connectionProvider')]
    public function testPagedListingAndDetailDoNotWriteAndImportUsesFreshServerContent(array $config): void
    {
        $this->setupService($config);
        $list = $this->service->templateAction('remote-list', ['environment' => 'test', 'remote_page' => '2']);
        self::assertSame(2, $list['page']);
        self::assertSame(21, $list['total']);
        self::assertSame('발송 전', $list['items'][0]['status']);
        $request = $this->http->requests[0];
        self::assertSame([], $request['headers']);
        self::assertSame('test', $request['environment']);
        self::assertSame(['bizId' => $this->settings['account'], 'apiKey' => $this->settings['kapi_key'],
            'senderKey' => $this->settings['senderkey'], 'senderKeyType' => 'S', 'page' => 2, 'count' => 20], $request['body']);
        $detail = $this->detail();
        self::assertTrue($detail['remote']['sendable'], '승인된 발송 전 R 상태도 허용한다.');
        self::assertSame([], $this->service->templates->all('test'));
        self::assertSame('예약 확인', $detail['content']['buttons'][0]['name']);
        $saved = $this->import($detail, ['message' => '변조', 'source' => 'manual', 'remote' => ['sendable' => true]]);
        self::assertSame($this->remote['templateContent'], $saved['message']);
        self::assertSame('kapi', $saved['source']);
        self::assertSame(['이름', '번호'], $saved['variables']);
        self::assertTrue($saved['enabled']);
        self::assertSame([], $this->service->templates->all('live'));
        $preview = $this->service->preview(['template_id' => $saved['id'], 'variables' => ['이름' => '고객', '번호' => 'a&b']]);
        self::assertSame('https://example.com/reservations/a%26b', $preview['buttons'][0]['url_mobile']);
        self::assertSame(0, $this->http->count('/v3/message'));
        self::assertSame(0, $this->http->count('/v1/token'));
    }

    #[DataProvider('connectionProvider')]
    public function testRefreshPreservesIdentityAndHistoryAndRejectsConcurrentEdits(array $config): void
    {
        $this->setupService($config);
        $first = $this->import($this->detail());
        $this->service->settings->setEnabled('test', true);
        $sent = $this->service->send(['template_id' => $first['id'], 'revision' => $first['revision'], 'environment' => 'test', 'phone' => '01000000000',
            'idempotency_key' => 'kapi-snapshot', 'variables' => ['이름' => '고객', '번호' => '1']]);
        $oldDetail = $this->detail();
        $this->remote['templateContent'] = "#{이름}님,\n예약 내용이 변경되었습니다.";
        $updated = $this->import($oldDetail);
        self::assertSame($first['id'], $updated['id']);
        self::assertNotSame($first['revision'], $updated['revision']);
        self::assertCount(1, $this->service->templates->all('test'));
        self::assertSame($sent['snapshot'], $this->service->detail($sent['id'])['snapshot']);
        $this->rejected(fn () => $this->import($oldDetail));
        $this->rejected(fn () => $this->service->templates->save('test', array_replace($updated, ['message' => '임의 수정'])));
        $disabled = $this->service->templates->save('test', array_replace($updated, ['enabled' => '0',
            'message' => str_replace("\n", "\r\n", $updated['message'])]));
        self::assertFalse($disabled['enabled']);
        self::assertSame($updated['message'], $disabled['message']);
        self::assertSame('kapi', $disabled['source']);
        $detail = $this->detail();
        $count = count($this->http->requests);
        $this->service->settings->save('test', $this->settings);
        $this->rejected(fn () => $this->import($detail));
        self::assertCount($count, $this->http->requests);
    }

    public static function unusableTemplates(): array
    {
        return ['pending' => [['inspectionStatus' => 'REQ']], 'rejected' => [['inspectionStatus' => 'REJ']],
            'blocked' => [['block' => true]], 'dormant' => [['dormant' => true]], 'stopped' => [['status' => 'S']],
            'unknown block' => [['block' => null]], 'unknown status' => [['status' => 'NEW']],
            'service stopped' => [['serviceStatus' => 'STP']], 'image' => [['templateEmphasizeType' => 'IMAGE']],
            'extra' => [['templateMessageType' => 'EX']], 'security' => [['securityFlag' => true]],
            'quick replies' => [['quickReplies' => [['name' => '답장']]]],
            'app button' => [['buttons' => [['name' => '앱', 'linkType' => 'AL', 'linkAnd' => 'app://open']]]],
            'variable host' => [['buttons' => [['name' => '열기', 'linkType' => 'WL', 'linkMo' => 'https://#{도메인}/']]]]];
    }

    #[DataProvider('unusableTemplates')]
    public function testUnapprovedOrUnsupportedTemplatesRemainViewableButCannotBeEnabled(array $changes): void
    {
        $this->setupService(['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null]);
        $valid = $this->remote;
        $this->remote = array_replace($valid, $changes);
        $detail = $this->detail();
        self::assertFalse($detail['remote']['sendable']);
        $this->rejected(fn () => $this->import($detail));
        self::assertSame([], $this->service->templates->all('test'));
        $this->remote = $valid;
        $saved = $this->import($this->detail());
        $this->remote = array_replace($valid, $changes);
        $disabled = $this->import($this->detail());
        self::assertSame($saved['id'], $disabled['id']);
        self::assertFalse($disabled['enabled']);
        self::assertSame($saved['message'], $disabled['message']);
        $this->rejected(fn () => $this->service->preview(['template_id' => $saved['id'], 'variables' => ['이름' => '고객', '번호' => '1']]));
        $this->rejected(fn () => $this->service->templates->save('test', array_replace($disabled, ['enabled' => '1'])));
    }

    #[DataProvider('connectionProvider')]
    public function testFailuresDoNotExposeSecretsOrReplaceLocalTemplates(array $config): void
    {
        $this->setupService($config);
        $saved = $this->import($this->detail());
        $this->http->respond = fn () => ['status' => 401, 'body' => ['code' => '401', 'message' => $this->settings['kapi_key']]];
        $error = $this->rejected(fn () => $this->detail(), 503);
        self::assertStringContainsString('HTTP 401', $error->getMessage());
        self::assertStringNotContainsString($this->settings['kapi_key'], $error->getMessage());
        $this->http->respond = fn () => throw new TransportFailure();
        $this->rejected(fn () => $this->detail(), 503);
        foreach ([['senderKey' => 'wrong'], ['templateCode' => 'wrong'], ['senderKeyType' => 'G']] as $change) {
            $this->http->respond = fn () => ['status' => 200, 'body' => ['code' => '200', 'data' => array_replace($this->remote, $change)]];
            $this->rejected(fn () => $this->detail(), 503);
        }
        self::assertSame($saved, $this->service->templates->get($saved['id']));
        $this->http->respond = fn () => ['status' => 200, 'body' => ['code' => '200', 'totalCount' => 1, 'totalPage' => 1, 'currentPage' => 1, 'data' => ['list' => 'invalid']]];
        $this->rejected(fn () => $this->service->templateAction('remote-list', ['environment' => 'test']), 503);
        $this->rejected(fn () => $this->service->templateAction('remote-list', ['environment' => 'test', 'remote_page' => -1]));
    }

    protected function tearDown(): void
    {
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
}
