<?php

declare(strict_types=1);

namespace GnuCms\Tests\Support;

use GnuCms\App;
use GnuCms\Auth\Acl;
use GnuCms\Auth\Identity;
use GnuCms\Db\Schema;
use GnuCms\Web\Kernel;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\UploadedFileInterface;

abstract class WebTestCase extends DatabaseTestCase
{
    /**
     * @param array $configOverrides 기본 설정 위에 덮어쓸 값. 최상위 키 단위로 합쳐진다.
     *                                예: ['debug' => false] 로 프로덕션 오류 화면을 테스트한다.
     */
    /**
     * @param string|null $theme 이 테스트가 화면을 확인하려는 테마를 못박는다. 못박은
     *   테스트는 GNUCMS_TEST_THEME 이 덮지 못한다 — 특정 테마의 마크업(자산 경로, 그
     *   테마에만 있는 안내 문구)을 단언하는 테스트는 테마를 바꿔 돌리면 반드시 깨지는데,
     *   그건 결함이 아니라 테스트가 보려던 대상이 사라진 것이기 때문이다.
     */
    protected function makeApp(array $dbConfig, array $configOverrides = [], ?string $theme = null): App
    {
        $config = array_replace([
            'db'   => $dbConfig,
            'auth' => ['secret' => 'web-test-secret-that-is-long-enough'],
            'uploads' => [
                'dir'         => sys_get_temp_dir() . '/' . GNUCMS_ID . '-test-uploads',
                'max_bytes'   => 1024 * 1024,
                'allowed_ext' => ['txt', 'png', 'pdf'],
            ],
            'storage' => ['dir' => sys_get_temp_dir() . '/' . GNUCMS_ID . '-test-storage'],
            'log'   => ['file' => null],
            'debug' => true,
        ], $configOverrides);

        $app = new App($config);
        // CI·개발 머신에 sendmail 이 없어도 웹 테스트가 외부 메일 환경에 기대지 않게 한다.
        // 메일 내용을 확인하는 테스트는 필요할 때 자체 CollectingMailer 로 다시 바꾼다.
        $app->setMailer(new CollectingMailer());

        $schema = new Schema($app->db());
        $schema->drop();
        $schema->create();

        // 전체 스위트를 다른 테마로 한 번 더 돌릴 때 쓴다: GNUCMS_TEST_THEME=native ./vendor/bin/phpunit
        // 테스트가 테마를 못박았으면 그쪽이 이긴다.
        if ($theme === null) {
            $env = getenv('GNUCMS_TEST_THEME');
            $theme = is_string($env) && $env !== '' ? $env : null;
        }
        if (is_string($theme) && $theme !== '') {
            $app->cms()->saveSettings(['theme' => $theme]);
        }

        return $app;
    }

    /** 게시판·글을 만들 때 쓴다. 1단계에는 로그인이 없으므로 화면은 항상 게스트다. */
    protected function adminAcl(): Acl
    {
        return new Acl(Identity::user('1', '관리자', true));
    }

    protected function get(App $app, string $path, array $query = []): ResponseInterface
    {
        return $this->request($app, 'GET', $path, $query);
    }

    protected function request(App $app, string $method, string $path, array $query = []): ResponseInterface
    {
        $uri = $path . ($query === [] ? '' : '?' . http_build_query($query));
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

        return Kernel::create($app, dirname(__DIR__, 2) . '/templates', '')->handle($request);
    }

    protected function post(App $app, string $path, array $body, array $server = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path, $server)->withParsedBody($body);

        return Kernel::create($app, dirname(__DIR__, 2) . '/templates', '')->handle($request);
    }

    /** @param array<string, UploadedFileInterface> $files */
    protected function postWithFiles(App $app, string $path, array $body, array $files): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withParsedBody($body)
            ->withUploadedFiles($files);

        return Kernel::create($app, dirname(__DIR__, 2) . '/templates', '')->handle($request);
    }

    /** @param array<string, UploadedFileInterface> $files */
    protected function upload(App $app, string $path, array $files, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path)->withUploadedFiles($files);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader((string) $name, (string) $value);
        }

        return Kernel::create($app, dirname(__DIR__, 2) . '/templates', '')->handle($request);
    }

    protected function body(ResponseInterface $response): string
    {
        $response->getBody()->rewind();

        return (string) $response->getBody();
    }

    protected function assertLoginRedirect(ResponseInterface $response, ?string $destination = null, string $base = ''): void
    {
        self::assertSame(303, $response->getStatusCode(), $this->body($response));
        $location = $response->getHeaderLine('Location');
        self::assertSame($base . '/login', parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame($destination === null ? [] : ['url' => $destination], $query);
    }

    /** AttachmentService::upload() 가 받는 $_FILES 형태의 배열을 임시 파일로 만든다. */
    protected function fakeUpload(string $name, string $contents): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sbtest');
        file_put_contents($tmp, $contents);

        return [
            'name'     => $name,
            'type'     => 'text/plain',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($contents),
        ];
    }

    /** 공유 임시 업로드 폴더를 비운다. collectGarbage 의 개수 단언이 이전 실행에 흔들리지 않게. */
    protected function purgeTestUploads(): void
    {
        $root = sys_get_temp_dir() . '/' . GNUCMS_ID . '-test-uploads';
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
}
