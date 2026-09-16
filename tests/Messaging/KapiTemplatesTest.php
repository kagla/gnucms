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

    /** 가져온 템플릿은 사용 안 함으로 들어오므로 발송·미리보기 검사 전에 켠다. */
    private function enable(array $template): array
    {
        return $this->service->templateAction('enable', ['id' => $template['id'], 'revision' => $template['revision'], 'enabled' => '1']);
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
        self::assertFalse($saved['enabled'], '가져온 템플릿은 사용 안 함으로 들어온다.');
        self::assertSame([], $this->service->templates->all('live'));
        $this->rejected(fn () => $this->service->preview(['template_id' => $saved['id'], 'variables' => ['이름' => '고객', '번호' => 'a&b']]));
        $saved = $this->enable($saved);
        $preview = $this->service->preview(['template_id' => $saved['id'], 'variables' => ['이름' => '고객', '번호' => 'a&b']]);
        self::assertSame('https://example.com/reservations/a%26b', $preview['buttons'][0]['url_mobile']);
        self::assertSame(0, $this->http->count('/v3/message'));
        self::assertSame(0, $this->http->count('/v1/token'));
    }

    #[DataProvider('connectionProvider')]
    public function testRefreshPreservesIdentityAndHistoryAndRejectsConcurrentEdits(array $config): void
    {
        $this->setupService($config);
        $first = $this->enable($this->import($this->detail()));
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
            'extra' => [['templateMessageType' => 'EX']],
            'quick replies' => [['quickReplies' => [['name' => '답장']]]],
            'app button' => [['buttons' => [['name' => '앱', 'linkType' => 'AL', 'linkAnd' => 'app://open']]]],
            'variable scheme' => [['buttons' => [['name' => '열기', 'linkType' => 'WL', 'linkMo' => '#{링크}']]]],
            'app represent link' => [['templateRepresentLink' => ['linkAnd' => 'app://open', 'linkIos' => null, 'linkMo' => 'https://example.com/', 'linkPc' => null]]],
            'over 1300 chars' => [['templateContent' => str_repeat('가', 1301)]]];
    }

    #[DataProvider('connectionProvider')]
    public function testDomainVariableButtonsAndSecurityTemplatesAreImportedAsApprovedByKakao(array $config): void
    {
        $this->setupService($config);
        // 실제 승인 템플릿처럼 버튼 URL의 도메인 전체가 변수이고 보안 템플릿 플래그가 켜져 있다.
        $this->remote['securityFlag'] = true;
        $this->remote['buttons'] = [['ordering' => 1, 'name' => '로그인하기', 'linkType' => 'WL', 'linkMo' => 'https://#{action_url}', 'linkPc' => null]];
        $this->remote['templateContent'] = "[#{app_name}] #{name}님, 회원가입이 완료되었습니다.";
        $detail = $this->detail();
        self::assertTrue($detail['remote']['sendable'], $detail['remote']['reason']);
        $saved = $this->enable($this->import($detail));
        self::assertSame(['app_name', 'name', 'action_url'], $saved['variables']);
        $preview = fn (string $url): array => $this->service->preview(['template_id' => $saved['id'],
            'variables' => ['app_name' => 'GNUCMS', 'name' => '고객', 'action_url' => $url]]);
        // 도메인 변수 값은 그대로 들어가고, 치환 후 URL은 형식 검증을 거친다.
        self::assertSame('https://gnucms.charmgen.com/login?token=a%2Fb', $preview('gnucms.charmgen.com/login?token=a%2Fb')['content']['at']['button'][0]['url_mobile']);
        foreach (['', 'gnucms charmgen.com/login', 'user@evil.example/', 'evil.example/\\x', "a.example/\n", 'a.example:abc/x'] as $bad) {
            $this->rejected(fn () => $preview($bad));
        }
        self::assertSame(0, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testImportAllImportsApprovedRefreshesExistingDisablesRevokedAndReportsSkipped(array $config): void
    {
        $this->setupService($config);
        $pending = array_replace($this->remote, ['templateCode' => 'pending_1', 'templateName' => '검수 전', 'inspectionStatus' => 'REG']);
        $catalogue = ['reservation_1' => &$this->remote, 'pending_1' => &$pending];
        $this->http->respond = function ($environment, $path, $headers, $body) use (&$catalogue): ?array {
            if ($path === '/v3/kakao/template/detail') return ['status' => 200, 'body' => ['code' => '200', 'data' => $catalogue[$body['templateCode']]]];
            if ($path === '/v3/kakao/template/list') return ['status' => 200, 'body' => ['code' => '200', 'totalCount' => 2, 'totalPage' => 1, 'currentPage' => 1,
                'data' => ['list' => array_map(static fn (array $row): array => array_intersect_key($row, array_flip(['senderKey', 'senderKeyType', 'templateCode', 'templateName'])) + ['serviceStatus' => 'RDY'], array_values($catalogue))]]];
            return null;
        };
        $summary = $this->service->templateAction('remote-import-all', ['environment' => 'test']);
        self::assertSame([2, 1, 0, 0, 1], [$summary['total'], $summary['imported'], $summary['refreshed'], count($summary['disabled']), count($summary['skipped'])]);
        self::assertSame(['code' => 'pending_1', 'name' => '검수 전', 'reason' => '카카오 검수를 아직 신청하지 않았습니다(등록 상태). 비즈뿌리오에서 검수 요청 후 승인되면 가져올 수 있습니다.'], $summary['skipped'][0]);
        $local = $this->service->templates->all('test');
        self::assertCount(1, $local);
        self::assertSame('reservation_1', $local[0]['code']);
        self::assertFalse($local[0]['enabled'], '일괄 가져오기도 사용 안 함으로 들어온다.');
        $this->enable($local[0]);
        self::assertTrue($this->service->templates->all('test')[0]['enabled']);
        // 두 번째 실행은 기존 사본을 갱신만 한다.
        $summary = $this->service->templateAction('remote-import-all', ['environment' => 'test']);
        self::assertSame([0, 1, 0], [$summary['imported'], $summary['refreshed'], count($summary['disabled'])]);
        self::assertTrue($this->service->templates->all('test')[0]['enabled'], '갱신은 관리자가 켠 사용 여부를 유지한다.');
        // 승인이 취소되면 사본의 사용을 중지하고 사유를 보고한다.
        $this->remote['inspectionStatus'] = 'REJ';
        $summary = $this->service->templateAction('remote-import-all', ['environment' => 'test']);
        self::assertSame([0, 0, 1], [$summary['imported'], $summary['refreshed'], count($summary['disabled'])]);
        self::assertSame('reservation_1', $summary['disabled'][0]['code']);
        self::assertFalse($this->service->templates->all('test')[0]['enabled']);
        // 코드 하나만 지정하면 목록 조회 없이 그 템플릿만 가져온다.
        $this->remote['inspectionStatus'] = 'APR';
        $requests = count($this->http->requests);
        $summary = $this->service->templateAction('remote-import-all', ['environment' => 'test', 'remote_code' => 'reservation_1']);
        self::assertSame([1, 0, 1, 0], [$summary['total'], $summary['imported'], $summary['refreshed'], count($summary['disabled'])]);
        self::assertSame(1, count($this->http->requests) - $requests);
        self::assertFalse($this->service->templates->all('test')[0]['enabled'], '갱신은 관리자가 끈 사용 여부를 되돌리지 않는다.');
        self::assertSame(0, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testEnableToggleChecksRevisionAndRemoteUsability(array $config): void
    {
        $this->setupService($config);
        $saved = $this->import($this->detail());
        $off = $this->service->templateAction('enable', ['id' => $saved['id'], 'revision' => $saved['revision'], 'enabled' => '0']);
        self::assertFalse($off['enabled']);
        self::assertNotSame($saved['revision'], $off['revision']);
        $this->rejected(fn () => $this->service->templateAction('enable', ['id' => $saved['id'], 'revision' => $saved['revision'], 'enabled' => '1']));
        $on = $this->service->templateAction('enable', ['id' => $saved['id'], 'revision' => $off['revision'], 'enabled' => '1']);
        self::assertTrue($on['enabled']);
        $this->remote['inspectionStatus'] = 'REJ';
        $revoked = $this->import($this->detail(), ['local_revision' => $on['revision']]);
        self::assertFalse($revoked['enabled']);
        $this->rejected(fn () => $this->service->templateAction('enable', ['id' => $saved['id'], 'revision' => $revoked['revision'], 'enabled' => '1']));
        $manual = $this->service->templates->save('test', ['name' => '수동', 'code' => 'manual_9', 'message' => '본문']);
        self::assertFalse($this->service->templateAction('enable', ['id' => $manual['id'], 'revision' => $manual['revision'], 'enabled' => '0'])['enabled']);
    }

    #[DataProvider('connectionProvider')]
    public function testDeleteRemovesTemplateKeepsHistoryAndRefusesStaleOrInFlight(array $config): void
    {
        $this->setupService($config);
        $saved = $this->enable($this->import($this->detail()));
        $this->service->settings->setEnabled('test', true);
        $sent = $this->service->send(['template_id' => $saved['id'], 'revision' => $saved['revision'], 'environment' => 'test', 'phone' => '01000000000',
            'idempotency_key' => 'before-delete', 'variables' => ['이름' => '고객', '번호' => '1']]);
        // 판이 다르면 거절, 처리 중인 발송이 있으면 거절.
        $this->rejected(fn () => $this->service->templateAction('delete', ['id' => $saved['id'], 'revision' => bin2hex(random_bytes(16))]));
        $this->app->db()->update('bp_dispatches', ['submission' => 'sending'], 'id = :id', ['id' => $sent['id']]);
        $this->rejected(fn () => $this->service->templateAction('delete', ['id' => $saved['id'], 'revision' => $saved['revision']]));
        $this->app->db()->update('bp_dispatches', ['submission' => 'accepted'], 'id = :id', ['id' => $sent['id']]);
        $this->service->templateAction('delete', ['id' => $saved['id'], 'revision' => $saved['revision']]);
        self::assertSame([], $this->service->templates->all('test'));
        self::assertSame('예약 안내', $this->service->detail($sent['id'])['template_name'], '발송 이력은 남는다.');
        $this->rejected(fn () => $this->service->templateAction('delete', ['id' => $saved['id'], 'revision' => $saved['revision']]), 404);
        // 삭제 뒤 다시 가져오면 새 사본이 만들어진다.
        $again = $this->import($this->detail());
        self::assertNotSame($saved['id'], $again['id']);
        self::assertFalse($again['enabled']);
    }

    public function testUnusableReasonsNameTheStateAndTheNextStep(): void
    {
        $this->setupService(['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null]);
        $valid = $this->remote;
        $cases = [
            [['inspectionStatus' => 'REG'], '검수를 아직 신청하지 않았습니다'],
            [['inspectionStatus' => 'REQ'], '검수 중입니다'],
            [['inspectionStatus' => 'REJ'], '반려되었습니다'],
            [['block' => true], '차단'],
            [['dormant' => true], '휴면'],
            [['status' => 'S'], '사용 중지'],
            [['serviceStatus' => 'STP'], '차단'],
            [['templateMessageType' => 'EX'], '기본형(BA)만'],
            [['templateEmphasizeType' => 'IMAGE'], '강조표기형·이미지형·아이템리스트형'],
            [['quickReplies' => [['name' => '답장']]], '바로연결'],
            [['buttons' => [['name' => '앱', 'linkType' => 'AL', 'linkAnd' => 'app://open']]], '웹링크(WL) 버튼만'],
            [['templateRepresentLink' => ['linkAnd' => 'app://open', 'linkIos' => null, 'linkMo' => 'https://example.com/', 'linkPc' => null]], '앱 스킴 대표 링크'],
            [['templateContent' => str_repeat('가', 1301)], '1,300자'],
            [['buttons' => [['name' => '열기', 'linkType' => 'WL', 'linkMo' => 'https://#{도메인}.example.com/']]], '버튼 URL'],
        ];
        foreach ($cases as [$changes, $expected]) {
            $this->remote = array_replace($valid, $changes);
            $reason = $this->detail()['remote']['reason'];
            self::assertStringContainsString($expected, $reason, json_encode($changes, JSON_UNESCAPED_UNICODE) . ' → ' . $reason);
        }
        $this->remote = $valid;
        self::assertSame('', $this->detail()['remote']['reason']);
    }

    #[DataProvider('connectionProvider')]
    public function testPreviewMessageAndRepresentativeWebLinkFollowTheOfficialBasicTemplate(array $config): void
    {
        $this->setupService($config);
        // 공식 규격의 기본형(BA/NONE) 예시는 templatePreviewMessage와 templateRepresentLink를 함께 담고 있다.
        $this->remote['templatePreviewMessage'] = '예약 안내 미리보기';
        $this->remote['templateRepresentLink'] = ['linkAnd' => null, 'linkIos' => null, 'linkMo' => 'https://example.com/m/#{번호}', 'linkPc' => 'https://example.com/pc'];
        $this->remote['templateContent'] = '#{이름}님 ' . str_repeat('가', 1293);
        $detail = $this->detail();
        self::assertTrue($detail['remote']['sendable'], $detail['remote']['reason']);
        self::assertSame(['url_mobile' => 'https://example.com/m/#{번호}', 'url_pc' => 'https://example.com/pc'], $detail['content']['link']);
        $saved = $this->enable($this->import($detail));
        self::assertSame(['url_mobile' => 'https://example.com/m/#{번호}', 'url_pc' => 'https://example.com/pc'], $saved['link']);
        self::assertSame(['이름', '번호'], $saved['variables']);
        self::assertSame(1300, mb_strlen($saved['message']));
        $preview = $this->service->preview(['template_id' => $saved['id'], 'variables' => ['이름' => '고객', '번호' => 'a&b']]);
        self::assertSame(['url_mobile' => 'https://example.com/m/a%26b', 'url_pc' => 'https://example.com/pc'], $preview['content']['at']['link']);
        self::assertArrayNotHasKey('templatePreviewMessage', $preview['content']['at']);
        // 대표 링크가 비어 있으면 발송 본문에 link를 넣지 않는다.
        $this->remote['templateRepresentLink'] = ['linkAnd' => null, 'linkIos' => null, 'linkMo' => null, 'linkPc' => null];
        $detail = $this->detail();
        self::assertTrue($detail['remote']['sendable']);
        self::assertArrayNotHasKey('link', $detail['content']);
        $refreshed = $this->import($detail, ['local_revision' => $saved['revision']]);
        self::assertSame([], $refreshed['link']);
        self::assertArrayNotHasKey('link', $this->service->preview(['template_id' => $refreshed['id'], 'variables' => ['이름' => '고객', '번호' => '1']])['content']['at']);
        // 수동 등록도 같은 규칙: 본문 1,300자와 웹 대표 링크는 허용, 1,301자는 거부.
        $manual = $this->service->templates->save('test', ['name' => '수동', 'code' => 'manual_1', 'message' => str_repeat('나', 1300),
            'link' => ['url_mobile' => 'https://example.com/x', 'url_pc' => '']]);
        self::assertSame(['url_mobile' => 'https://example.com/x'], $manual['link']);
        $this->rejected(fn () => $this->service->templates->save('test', ['name' => '수동', 'code' => 'manual_2', 'message' => str_repeat('나', 1301)]));
        $this->rejected(fn () => $this->service->templates->save('test', ['name' => '수동', 'code' => 'manual_3', 'message' => '본문', 'link' => ['scheme_ios' => 'app://x']]));
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

    #[DataProvider('connectionProvider')]
    public function testVendorRejectionExplainsOfficialCodeWithoutEchoingVendorMessage(array $config): void
    {
        $this->setupService($config);
        $listing = fn () => $this->service->templateAction('remote-list', ['environment' => 'test']);
        // 실제 KAPI는 잘못된 키를 message의 괄호 안에 그대로 돌려준다. message는 화면에 내보내지 않는다.
        $this->http->respond = fn () => ['status' => 403, 'body' => ['code' => '403', 'message' => '잘못된 apiKey 입니다.(' . $this->settings['kapi_key'] . ')']];
        $message = $this->rejected($listing, 503)->getMessage();
        self::assertStringContainsString('HTTP 403 · 코드 403)', $message);
        self::assertStringContainsString('API 키가 맞지 않거나 권한이 없습니다', $message);
        self::assertStringContainsString('모듈 비밀번호와는 다른 값', $message);
        self::assertStringNotContainsString('잘못된 apiKey', $message);
        self::assertStringNotContainsString($this->settings['kapi_key'], $message);
        $this->http->respond = fn () => ['status' => 200, 'body' => ['code' => '102']];
        $message = $this->rejected($listing, 503)->getMessage();
        self::assertStringContainsString('HTTP 200 · 코드 102)', $message);
        self::assertStringContainsString('API 키가 맞지 않거나', $message);
        $this->http->respond = fn () => ['status' => 200, 'body' => ['code' => '507', 'message' => $this->settings['senderkey']]];
        $message = $this->rejected($listing, 503)->getMessage();
        self::assertStringContainsString('코드 507)', $message);
        self::assertStringContainsString('발신프로필키가 유효하지 않습니다', $message);
        self::assertStringNotContainsString($this->settings['senderkey'], $message);
        foreach (['101' => '아이디(bizId)가 없습니다', '103' => '중지된 계정', '405' => '파라미터 오류', '621' => '파라미터 오류'] as $code => $reason) {
            $this->http->respond = fn () => ['status' => 200, 'body' => ['code' => $code]];
            self::assertStringContainsString($reason, $this->rejected($listing, 503)->getMessage());
        }
        $this->http->respond = fn () => ['status' => 500, 'body' => ['code' => 999, 'message' => ['nested' => 'x']]];
        $message = $this->rejected($listing, 503)->getMessage();
        self::assertStringContainsString('HTTP 500 · 코드 999). API 키·아이디·발신프로필키를 확인해 주세요.', $message);
        self::assertStringNotContainsString('nested', $message);
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
