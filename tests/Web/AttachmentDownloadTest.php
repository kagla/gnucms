<?php

declare(strict_types=1);

namespace GnuCms\Tests\Web;

use GnuCms\Tests\Support\WebTestCase;

final class AttachmentDownloadTest extends WebTestCase
{
    /** @dataProvider connectionProvider */
    public function testAttachmentIsDownloadable(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'free',
            'name'      => '자유게시판',
            'use_file'  => true,
        ]);

        $descriptor = $app->attachments()->upload($acl, 'free', $this->fakeUpload('메모.txt', '안녕하세요'));
        $post = $app->postService()->create($acl, 'free', [
            'title'       => '글',
            'content'     => '본문',
            'attachments' => [$descriptor],
        ]);

        $response = $this->get($app, '/posts/' . $post['id'] . '/files/0');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('안녕하세요', $this->body($response));
        self::assertStringContainsString('attachment;', $response->getHeaderLine('Content-Disposition'));
        // 한글 파일명은 RFC 5987 형식으로만 온전히 전달된다.
        self::assertStringContainsString("filename*=UTF-8''" . rawurlencode('메모.txt'), $response->getHeaderLine('Content-Disposition'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        // Content-Type 은 HtmlContentTypeMiddleware 가 이미 정해진 값을 건드리지 않는다는
        // 것을 확인하는 자리다. 다운로드가 text/html 로 오면 그 가드가 깨진 것이다.
        self::assertSame($descriptor['mime'], $response->getHeaderLine('Content-Type'));
        self::assertNotSame('text/html', $response->getHeaderLine('Content-Type'));
    }

    /** @dataProvider connectionProvider */
    public function testUnknownIndexRendersNotFoundPage(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, ['board_key' => 'free', 'name' => '자유게시판']);
        $post = $app->postService()->create($acl, 'free', ['title' => '글', 'content' => '본문']);

        self::assertSame(404, $this->get($app, '/posts/' . $post['id'] . '/files/7')->getStatusCode());
    }

    /** @dataProvider connectionProvider */
    public function testLegacyAttachmentUrlRedirectsPermanentlyToCanonicalUrl(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);

        $response = $this->get($app, '/p/2/files/0');

        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/posts/2/files/0', $response->getHeaderLine('Location'));
    }

    /**
     * 첨부 다운로드도 글 보기와 같은 경로(PostService::loadForRead())로 권한을 판정한다.
     * 비밀글의 첨부를 게스트가 받으려 하면 403 이어야 한다 — 로그인해도 소용없다는 뜻.
     *
     * @dataProvider connectionProvider
     */
    public function testAttachmentOnSecretPostIsDeniedToGuestWith403(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key'  => 'free',
            'name'       => '자유게시판',
            'use_file'   => true,
            'use_secret' => true,
        ]);

        $descriptor = $app->attachments()->upload($acl, 'free', $this->fakeUpload('메모.txt', '안녕하세요'));
        $post = $app->postService()->create($acl, 'free', [
            'title'       => '비밀글',
            'content'     => '본문',
            'is_secret'   => true,
            'attachments' => [$descriptor],
        ]);

        self::assertSame(403, $this->get($app, '/posts/' . $post['id'] . '/files/0')->getStatusCode());
    }

    /**
     * perm_read = admin 인 게시판의 첨부는 게스트를 로그인으로 보낸다.
     * AttachmentService::download()에서 나오는 401 판정을 웹에서 변환한다.
     *
     * @dataProvider connectionProvider
     */
    public function testAttachmentInAdminOnlyBoardRedirectsGuestToLogin(array $dbConfig): void
    {
        $app = $this->makeApp($dbConfig);
        $acl = $this->adminAcl();
        $app->boardService()->create($acl, [
            'board_key' => 'secret',
            'name'      => '관리자전용',
            'perm_read' => 'admin',
            'use_file'  => true,
        ]);

        $descriptor = $app->attachments()->upload($acl, 'secret', $this->fakeUpload('메모.txt', '안녕하세요'));
        $post = $app->postService()->create($acl, 'secret', [
            'title'       => '글',
            'content'     => '본문',
            'attachments' => [$descriptor],
        ]);

        $this->assertLoginRedirect($this->get($app, '/posts/' . $post['id'] . '/files/0'), '/posts/' . $post['id'] . '/files/0');
    }

    protected function tearDown(): void
    {
        $dir = sys_get_temp_dir() . '/' . GNUCMS_ID . '-test-uploads';
        if (is_dir($dir)) {
            // 재귀적으로 모든 파일과 디렉토리를 삭제한다.
            // year/month/... 중첩 구조를 모두 정리하기 위해 CHILD_FIRST 모드를 사용한다.
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $path) {
                if ($path->isDir()) {
                    rmdir($path->getPathname());
                } else {
                    unlink($path->getPathname());
                }
            }
            rmdir($dir);
        }

        parent::tearDown();
    }
}
