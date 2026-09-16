<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\App;
use GnuCms\Db\Schema;
use GnuCms\Messaging\MessagingService;
use GnuCms\Support\Clock;
use GnuCms\Tests\Messaging\FakeTransport;
use GnuCms\Tests\Messaging\TextFixtures;
use GnuCms\Tests\Support\WebTestCase;
use GnuCms\Web\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MessagingTest extends WebTestCase
{
    private string $root;
    private App $app;
    private FakeTransport $http;

    private function setupApp(array $config): MessagingService
    {
        $this->root = sys_get_temp_dir() . '/gnucms-msg-web-' . bin2hex(random_bytes(5));
        $config['prefix'] = 'mw' . bin2hex(random_bytes(4)) . '_';
        $this->app = $this->makeApp($config, ['storage' => ['dir' => $this->root], 'auth' => ['secret' => bin2hex(random_bytes(32))]]);
        $this->http = new FakeTransport();
        $service = new MessagingService($this->app, $this->http);
        $this->app->setMessaging($service);
        return $service;
    }

    private function configure(MessagingService $service, bool $senderKey = true, bool $apiKey = true): void
    {
        $service->settings->save('test', ['account' => 'ops-web-test', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => $senderKey ? bin2hex(random_bytes(20)) : '', 'from' => '0212345678', 'test_phone' => '01000000000',
            'kapi_key' => $apiKey ? bin2hex(random_bytes(12)) : '']);
        $service->settings->setEnabled('test', true);
    }

    private function signIn(bool $admin): void
    {
        $id = $this->app->users()->create(($admin ? 'admin' : 'member') . '@example.com', '', $admin ? '운영자' : '일반회원', $admin);
        $this->get($this->app, '/login');
        session_start();
        $_SESSION['user_id'] = $id;
        $_SESSION['session_epoch'] = 0;
        session_write_close();
    }

    protected function tearDown(): void
    {
        Clock::unfreeze();
        if (isset($this->app)) (new Schema($this->app->db()))->drop();
        if (isset($this->root) && is_dir($this->root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
        parent::tearDown();
    }

    #[DataProvider('connectionProvider')]
    public function testOperationsScreensRequireAdminAndCsrfAndWorkInsideSubdirectory(array $config): void
    {
        $service = $this->setupApp($config);
        $this->assertLoginRedirect($this->get($this->app, '/admin/messaging/templates'), '/admin/messaging/templates');
        $this->signIn(false);
        foreach (['/admin/messaging', '/admin/messaging/send', '/admin/messaging/sms/send'] as $path) self::assertSame(403, $this->get($this->app, $path)->getStatusCode());
        $this->signIn(true);
        self::assertSame(303, $this->get($this->app, '/admin/messaging')->getStatusCode());
        self::assertSame('/admin/messaging/templates', $this->get($this->app, '/admin/messaging')->getHeaderLine('Location'));
        $this->configure($service);
        foreach (['/admin/messaging/send' => ['preview', 'send'], '/admin/messaging/history' => ['purge'], '/admin/messaging/sms/send' => ['preview', 'send']] as $path => $actions) {
            foreach ($actions as $action) self::assertSame(403, $this->post($this->app, $path, ['action' => $action, 'environment' => 'test'])->getStatusCode());
        }
        foreach (['/admin/messaging/templates', '/admin/messaging/send', '/admin/messaging/history', '/admin/messaging/sms/send', '/admin/messaging/sms/history'] as $path) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/cms' . $path);
            $response = Kernel::create($this->app, dirname(__DIR__, 2) . '/templates', '/cms')->handle($request);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('action="/cms' . $path . '"', $this->body($response));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }
        $sidebar = $this->body($this->get($this->app, '/admin'));
        self::assertStringContainsString('메시지 발송', $sidebar);
        self::assertSame(404, $this->get($this->app, '/modules/alimtalk/home')->getStatusCode());
        self::assertSame(404, $this->get($this->app, '/plugins/bizppurio/settings')->getStatusCode());
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testAlimtalkPreviewIsEscapedAndSendUsesOnlyTheConfirmedSnapshot(array $config): void
    {
        $service = $this->setupApp($config);
        $this->configure($service);
        $template = $service->templates->save('test', ['code' => 'hello', 'name' => '<b>안내</b>', 'message' => '#{이름}님 안내입니다.']);
        $this->signIn(true);
        $preview = $this->post($this->app, '/admin/messaging/send', ['action' => 'preview', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'template_id' => $template['id'], 'revision' => $template['revision'], 'phone' => '01000000000', 'variables' => ['이름' => '<script>alert(1)</script>']]);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;', $this->body($preview));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($preview));
        self::assertStringContainsString('name="confirmation"', $this->body($preview));
        self::assertSame(0, $service->history(['environment' => 'test'])['total']);
        self::assertSame(422, $this->post($this->app, '/admin/messaging/send', ['action' => 'send', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'], 'confirmation' => 'not-a-confirmation'])->getStatusCode());
        session_start();
        $token = array_key_last($_SESSION['alimtalk_previews']);
        session_write_close();
        $sent = $this->post($this->app, '/admin/messaging/send', ['action' => 'send', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'confirmation' => $token, 'phone' => '01011111111', 'variables' => ['이름' => '변조']]);
        self::assertSame(303, $sent->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/history/', $sent->getHeaderLine('Location'));
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('01000000000', $body['to']);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;님 안내입니다.', $this->body($preview));
        $detail = $this->get($this->app, parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH), ['environment' => 'test']);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('발송 상세', $this->body($detail));
        self::assertMatchesRegularExpression('/010-?0000-?0000/', $this->body($detail), '발송 상세는 수신번호를 가리지 않는다.');
        self::assertStringNotContainsString('010-****-0000', $this->body($detail));
        self::assertSame(404, $this->get($this->app, parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH), ['environment' => 'live'])->getStatusCode());
        $history = $this->get($this->app, '/admin/messaging/history', ['environment' => 'test']);
        self::assertStringContainsString('010-****-0000', $this->body($history));
        self::assertStringNotContainsString('01000000000', $this->body($history));
    }

    #[DataProvider('connectionProvider')]
    public function testTemplatesTabRequiresApiKeyAndNeverOffersFreeTextEditing(array $config): void
    {
        $service = $this->setupApp($config);
        $this->configure($service, true, false);
        $this->signIn(true);
        $page = $this->body($this->get($this->app, '/admin/messaging/templates', ['environment' => 'test']));
        self::assertStringContainsString('API 키를 저장', $page);
        self::assertStringNotContainsString('value="remote-import-all"', $page);
        self::assertStringNotContainsString('name="message"', $page);
        // 폼이 사라진 뒤에도 옛 저장 요청은 거절된다.
        $saved = $this->post($this->app, '/admin/messaging/templates', ['action' => 'save', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token'],
            'name' => '임의', 'code' => 'free_text', 'message' => '본문']);
        self::assertSame(422, $saved->getStatusCode());
        self::assertSame([], $service->templates->all('test'));
        $before = $service->settings->read('test');
        $service->settings->save('test', ['account' => $before['account'], 'password' => '', 'senderkey' => $before['senderkey'],
            'from' => $before['from'], 'test_phone' => $before['test_phone'], 'kapi_key' => bin2hex(random_bytes(12))]);
        $page = $this->body($this->get($this->app, '/admin/messaging/templates', ['environment' => 'test']));
        self::assertStringContainsString('승인된 템플릿 모두 가져오기', $page);
        self::assertStringNotContainsString('name="message"', $page);
        self::assertCount(0, $this->http->requests);
    }

    #[DataProvider('connectionProvider')]
    public function testRemoteTemplateScreenListsEscapesAndImports(array $config): void
    {
        $service = $this->setupApp($config);
        $service->settings->save('test', ['account' => 'kapi-view', 'password' => bin2hex(random_bytes(20)),
            'senderkey' => bin2hex(random_bytes(20)), 'kapi_key' => bin2hex(random_bytes(24)),
            'from' => '0212345678', 'test_phone' => '01000000000']);
        $this->signIn(true);
        $remote = ['senderKey' => $service->settings->read('test')['senderkey'], 'senderKeyType' => 'S',
            'templateCode' => 'notice', 'templateName' => '<b>예약 안내</b>', 'templateContent' => '#{이름}님 <script>alert(1)</script>',
            'templateMessageType' => 'BA', 'templateEmphasizeType' => 'NONE', 'inspectionStatus' => 'APR',
            'status' => 'A', 'serviceStatus' => 'ACT', 'block' => false, 'dormant' => false, 'buttons' => []];
        // 실제 KAPI처럼 요청한 페이지를 currentPage로 돌려주고 2페이지는 비어 있다.
        // KAPI 경로만 흉내 내고 토큰·발송 경로는 FakeTransport 기본 응답에 맡긴다.
        $this->http->respond = static fn ($environment, $path, $headers, $body) => !str_starts_with($path, '/v3/kakao/') ? null : ['status' => 200, 'body' => $path === '/v3/kakao/template/list'
            ? ['code' => '200', 'totalCount' => 21, 'totalPage' => 2, 'currentPage' => (int) ($body['page'] ?? 1), 'data' => ['list' => ($body['page'] ?? 1) === 1 ? [$remote] : []]]
            : ['code' => '200', 'data' => $remote]];
        $post = fn (array $input) => $this->post($this->app, '/admin/messaging/templates', $input + ['environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        $list = $post(['action' => 'remote-list']);
        self::assertSame(200, $list->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;예약 안내&lt;/b&gt;', $this->body($list));
        self::assertStringContainsString('name="remote_page" value="2"', $this->body($list));
        $detail = $post(['action' => 'remote-detail', 'remote_code' => 'notice']);
        self::assertStringContainsString('GNUCMS로 가져오기', $this->body($detail));
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->body($detail));
        $imported = $post(['action' => 'remote-import', 'remote_code' => 'notice', 'local_revision' => '',
            'config_revision' => $service->settings->read('test')['revision'], 'message' => '변조']);
        self::assertSame(303, $imported->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/templates?environment=test&id=', $imported->getHeaderLine('Location'));
        parse_str((string) parse_url($imported->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $page = $this->get($this->app, '/admin/messaging/templates', $query);
        self::assertSame(200, $page->getStatusCode());
        self::assertStringContainsString('비즈뿌리오 최신 상태 확인', $this->body($page));
        // 가져온 템플릿은 입력칸 없이 읽기 전용으로 보여 주고 사용 여부만 전환한다.
        self::assertStringContainsString('id="template-message"', $this->body($page));
        self::assertStringNotContainsString('name="message"', $this->body($page));
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->body($page));
        self::assertStringContainsString('value="enable"', $this->body($page));
        self::assertSame($remote['templateContent'], $service->templates->all('test')[0]['message']);
        $local = $service->templates->all('test')[0];
        self::assertFalse($local['enabled'], '가져온 템플릿은 사용 안 함으로 들어온다.');
        self::assertStringContainsString('>사용 안 함</span>', $this->body($page));
        self::assertStringNotContainsString('template_id=' . $local['id'], $this->body($page), '사용 안 함인 템플릿은 발송 바로가기를 보여 주지 않는다.');
        $toggled = $post(['action' => 'enable', 'id' => $local['id'], 'revision' => $local['revision'], 'enabled' => '1']);
        self::assertSame(303, $toggled->getStatusCode());
        self::assertTrue($service->templates->get($local['id'])['enabled']);
        // 보기 모달 안에서 예시 값이 채워진 폼으로 미리보기 → 발송까지 이어진다.
        $view = $this->body($this->get($this->app, '/admin/messaging/templates', ['environment' => 'test', 'id' => $local['id']]));
        self::assertStringContainsString('name="variables[이름]"', $view);
        self::assertStringContainsString('value="홍길동"', $view, '변수에 예시 값을 채워 둔다.');
        self::assertStringContainsString('name="phone" inputmode="tel" required value="01000000000"', $view, '테스트 환경은 테스트 수신번호를 채워 둔다.');
        $service->settings->setEnabled('test', true);
        $previewed = $post(['action' => 'preview', 'id' => $local['id'], 'template_id' => $local['id'], 'revision' => $service->templates->get($local['id'])['revision'],
            'phone' => '01000000000', 'variables' => ['이름' => '<b>고객</b>']]);
        self::assertSame(200, $previewed->getStatusCode());
        self::assertStringContainsString('최종 발송 내용', $this->body($previewed));
        self::assertStringContainsString('id="preview-dialog"', $this->body($previewed), '미리보기는 보기 모달 위의 두 번째 모달로 뜬다.');
        self::assertStringContainsString('id="template-dialog"', $this->body($previewed));
        self::assertStringContainsString('&lt;b&gt;고객&lt;/b&gt;님', $this->body($previewed));
        self::assertStringContainsString('name="confirmation"', $this->body($previewed));
        session_start();
        $token = array_key_last($_SESSION['alimtalk_previews']);
        session_write_close();
        $sentFromModal = $post(['action' => 'send', 'id' => $local['id'], 'confirmation' => $token]);
        self::assertSame(303, $sentFromModal->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/history/', $sentFromModal->getHeaderLine('Location'));
        self::assertSame(1, $this->http->count('/v3/message'));
        $all = $post(['action' => 'remote-import-all']);
        self::assertSame(200, $all->getStatusCode());
        self::assertStringContainsString('가져옴 0', $this->body($all));
        self::assertStringContainsString('갱신 1', $this->body($all));
        self::assertStringContainsString('>사용 중</span>', $this->body($all), '한 표의 GNUCMS 열에 사본 상태를 표시한다.');
        self::assertStringNotContainsString('가져오기 전', $this->body($all));
        self::assertTrue($service->templates->all('test')[0]['enabled'], '일괄 갱신은 관리자가 켠 사용 여부를 유지한다.');
        // 이니톡 결제 알림톡으로 지정된 템플릿은 삭제할 수 없고, 그 밖에는 확인 뒤 삭제한다.
        $local = $service->templates->all('test')[0];
        $this->app->cms()->saveSettings(['initalk.template.test' => $local['id']]);
        $blocked = $post(['action' => 'delete', 'id' => $local['id'], 'revision' => $local['revision']]);
        self::assertSame(422, $blocked->getStatusCode());
        self::assertStringContainsString('이니톡 결제', $this->body($blocked));
        self::assertCount(1, $service->templates->all('test'));
        $this->app->cms()->saveSettings(['initalk.template.test' => '']);
        self::assertStringContainsString('value="delete"', $this->body($this->get($this->app, '/admin/messaging/templates', ['environment' => 'test'])));
        $deleted = $post(['action' => 'delete', 'id' => $local['id'], 'revision' => $local['revision']]);
        self::assertSame(303, $deleted->getStatusCode());
        self::assertSame('/admin/messaging/templates?environment=test', $deleted->getHeaderLine('Location'));
        self::assertSame([], $service->templates->all('test'));
        $this->http->respond = static fn () => ['status' => 403, 'body' => ['code' => '403', 'message' => 'provider-internal-diagnostic']];
        $failure = $post(['action' => 'remote-list']);
        self::assertSame(503, $failure->getStatusCode());
        self::assertStringContainsString('HTTP 403', $this->body($failure));
        self::assertStringNotContainsString('provider-internal-diagnostic', $this->body($failure));
        self::assertSame(1, $this->http->count('/v3/message'));
    }

    #[DataProvider('connectionProvider')]
    public function testSmsConfirmedSnapshotCannotBeTamperedWithAndExpires(array $config): void
    {
        $service = $this->setupApp($config);
        $this->configure($service, false);
        $this->signIn(true);
        Clock::freeze('2026-09-07 01:00:00');
        $input = ['action' => 'preview', 'environment' => 'test', 'type' => 'lms', 'phone' => '010-0000-0000',
            'subject' => '<b>제목 ʕ</b>', 'message' => TextFixtures::ART . "\n<script>alert(1)</script>", 'csrf_token' => $_SESSION['csrf_token']];
        $preview = $this->post($this->app, '/admin/messaging/sms/send', $input);
        self::assertSame(200, $preview->getStatusCode());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->body($preview));
        self::assertStringContainsString('본문 길이: 업체 확인 필요', $this->body($preview));
        self::assertSame(0, $this->http->count('/v3/message'));
        session_start();
        $token = array_key_last($_SESSION['sms_previews']);
        session_write_close();
        self::assertSame(422, $this->post($this->app, '/admin/messaging/sms/send', ['action' => 'send', 'confirmation' => $token, 'environment' => 'live', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        $sendInput = ['action' => 'send', 'confirmation' => $token, 'environment' => 'test', 'message' => '변조', 'phone' => '01011111111', 'type' => 'sms', 'csrf_token' => $_SESSION['csrf_token']];
        $sent = $this->post($this->app, '/admin/messaging/sms/send', $sendInput);
        self::assertSame(303, $sent->getStatusCode());
        self::assertStringStartsWith('/admin/messaging/sms/history/', $sent->getHeaderLine('Location'));
        self::assertSame($sent->getHeaderLine('Location'), $this->post($this->app, '/admin/messaging/sms/send', $sendInput)->getHeaderLine('Location'));
        self::assertSame(1, $this->http->count('/v3/message'));
        $body = $this->http->requests[array_key_last($this->http->requests)]['body'];
        self::assertSame('lms', $body['type']);
        self::assertSame('01000000000', $body['to']);
        self::assertSame($input['message'], $body['content']['lms']['message']);
        $path = (string) parse_url($sent->getHeaderLine('Location'), PHP_URL_PATH);
        $detail = $this->get($this->app, $path, ['environment' => 'test']);
        self::assertSame(200, $detail->getStatusCode());
        self::assertStringContainsString('&lt;b&gt;제목 ʕ&lt;/b&gt;', $this->body($detail));
        self::assertStringContainsString('010-0000-0000', $this->body($detail));
        foreach (['retry', 'refresh-result'] as $action) {
            self::assertSame(404, $this->post($this->app, $path, ['action' => $action, 'environment' => 'live', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        }
        $history = $this->get($this->app, '/admin/messaging/sms/history', ['environment' => 'test', 'type' => 'lms']);
        self::assertStringContainsString('LMS', $this->body($history));
        self::assertStringContainsString('010-****-0000', $this->body($history));
        self::assertStringNotContainsString($input['message'], $this->body($history));
        $this->post($this->app, '/admin/messaging/sms/send', $input);
        session_start();
        $next = array_key_last($_SESSION['sms_previews']);
        session_write_close();
        Clock::freeze('2026-09-07 01:11:00');
        self::assertSame(422, $this->post($this->app, '/admin/messaging/sms/send', ['action' => 'send', 'confirmation' => $next, 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']])->getStatusCode());
        self::assertSame(1, $this->http->count('/v3/message'));
        Clock::freeze('2027-01-01 00:00:00');
        $purged = $this->post($this->app, '/admin/messaging/sms/history', ['action' => 'purge', 'environment' => 'test', 'csrf_token' => $_SESSION['csrf_token']]);
        self::assertStringContainsString('1건', $this->body($purged));
        self::assertStringContainsString('보관 만료', $this->body($this->get($this->app, $path, ['environment' => 'test'])));
    }
}
