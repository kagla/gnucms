<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\App;
use GnuCms\Web\Controller\BoardController;
use GnuCms\Web\Controller\AccountController;
use GnuCms\Web\Controller\AuthController;
use GnuCms\Web\Controller\OauthController;
use GnuCms\Web\Controller\AdminController;
use GnuCms\Web\Controller\FileController;
use GnuCms\Web\Controller\PostController;
use GnuCms\Web\Controller\PageController;
use GnuCms\Web\Controller\AdminCmsController;
use GnuCms\Web\Controller\CmsImageController;
use GnuCms\Web\Controller\CommentController;
use GnuCms\Web\Controller\EditorImageController;
use GnuCms\Web\Controller\NotificationController;
use GnuCms\Web\Controller\AvatarController;
use GnuCms\Web\Controller\SeoController;
use GnuCms\Web\Controller\BackupController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App as SlimApp;
use GnuCms\View\View;
use Slim\Routing\RouteContext;

final class Routes
{
    public static function register(SlimApp $slim, App $app): void
    {
        $seo = new SeoController($app);
        $slim->get('/sitemap.xml', [$seo, 'sitemap'])->setName('seo.sitemap');
        $slim->get('/robots.txt', [$seo, 'robots'])->setName('seo.robots');
        $slim->get('/rss.xml', [$seo, 'siteRss'])->setName('seo.rss');
        $slim->get('/content/rss.xml', [$seo, 'contentRss'])->setName('seo.content_rss');
        $slim->get('/boards/{key}/rss.xml', [$seo, 'boardRss'])->setName('seo.board_rss');

        $slim->get('/media/avatars/{file:[a-f0-9]{32}\\.(?:jpg|png|webp)}', [new AvatarController($app), 'show'])
            ->setName('avatar.show');
        $auth = new AuthController($app);
        $slim->get('/login', [$auth, 'loginForm'])->setName('auth.login');
        $slim->post('/login', [$auth, 'login']);
        $slim->get('/register', [$auth, 'registerForm'])->setName('auth.register');
        $slim->post('/register', [$auth, 'register']);
        $slim->get('/verify-email', [$auth, 'verifyEmail'])->setName('auth.verify');
        $slim->post('/verify-email/resend', [$auth, 'resendVerification'])->setName('auth.verify.resend');
        $slim->get('/forgot-password', [$auth, 'forgotForm'])->setName('auth.forgot');
        $slim->post('/forgot-password', [$auth, 'forgot']);
        $slim->get('/reset-password', [$auth, 'resetForm'])->setName('auth.reset');
        $slim->post('/reset-password', [$auth, 'reset']);
        $slim->post('/logout', [$auth, 'logout'])->setName('auth.logout');

        $account = new AccountController($app);
        $slim->get('/account', [$account, 'editForm'])->setName('account.edit');
        $slim->post('/account', [$account, 'update']);
        $slim->post('/account/withdraw', [$account, 'withdraw'])->setName('account.withdraw');
        $slim->get('/account/withdrawn', [$account, 'withdrawn'])->setName('account.withdrawn');

        $oauth = new OauthController($app);
        $slim->post('/auth/email', [$oauth, 'email'])->setName('oauth.email');
        $slim->get('/auth/complete', [$oauth, 'complete'])->setName('oauth.complete');
        $slim->get('/auth/{provider:google|naver|kakao}', [$oauth, 'start'])->setName('oauth.start');
        $slim->get('/auth/{provider:google|naver|kakao}/callback', [$oauth, 'callback'])->setName('oauth.callback');

        $admin = new AdminController($app);
        $slim->get('/admin', [$admin, 'index'])->setName('admin.index');
        $slim->get('/admin/posts', [$admin, 'posts'])->setName('admin.posts');
        $slim->get('/admin/boards', [$admin, 'boards'])->setName('admin.boards');
        $slim->get('/admin/boards/new', [$admin, 'createForm'])->setName('admin.boards.create');
        $slim->post('/admin/boards/new', [$admin, 'create']);
        $slim->get('/admin/boards/{key}/edit', [$admin, 'editForm'])->setName('admin.boards.edit');
        $slim->post('/admin/boards/{key}/edit', [$admin, 'update']);
        $slim->post('/admin/boards/{key}/delete', [$admin, 'delete'])->setName('admin.boards.delete');
        $slim->get('/admin/members', [$admin, 'members'])->setName('admin.members');
        $slim->get('/admin/login-history', [$admin, 'loginHistory'])->setName('admin.login_history');
        $slim->post('/admin/login-history/delete', [$admin, 'deleteLoginHistory'])
            ->setName('admin.login_history.delete');
        $slim->get('/admin/members/{id:[0-9]+}/edit', [$admin, 'memberEditForm'])->setName('admin.members.edit');
        $slim->post('/admin/members/{id:[0-9]+}/edit', [$admin, 'memberUpdate']);
        $slim->post('/admin/members/{id:[0-9]+}/status', [$admin, 'toggleStatus'])->setName('admin.members.status');

        $cms = new AdminCmsController($app);
        $cmsImages = new CmsImageController($app);
        $slim->get('/admin/settings', [$cms, 'settingsForm'])->setName('admin.settings');
        $slim->post('/admin/settings', [$cms, 'settings']);
        $slim->get('/admin/settings/writing', [$cms, 'writingForm'])->setName('admin.settings.writing');
        $slim->post('/admin/settings/writing', [$cms, 'writing']);
        $slim->get('/admin/settings/security', [$cms, 'securityForm'])->setName('admin.settings.security');
        $slim->post('/admin/settings/security', [$cms, 'security']);
        $slim->post('/admin/settings/security/secret', [$cms, 'turnstileSecret'])
            ->setName('admin.settings.security.secret');
        $slim->get('/admin/settings/social', [$cms, 'oauthForm'])->setName('admin.settings.oauth');
        $slim->post('/admin/settings/social', [$cms, 'oauth']);
        $slim->post('/admin/settings/social/{provider:google|naver|kakao}/secret', [$cms, 'oauthSecret'])
            ->setName('admin.settings.oauth.secret');
        $slim->get('/admin/settings/maintenance', [$cms, 'maintenance'])->setName('admin.settings.maintenance');
        $slim->post('/admin/uploads/gc', [$cms, 'uploadsGc'])->setName('admin.uploads.gc');
        $backups = new BackupController($app);
        $slim->post('/admin/backups', [$backups, 'create'])->setName('admin.backups.create');
        $slim->post('/admin/backups/upload', [$backups, 'upload'])->setName('admin.backups.upload');
        $slim->get('/admin/backups/{name:gnucms-(?:sqlite|mysql)-[0-9-]+\\.(?:zip|tar)}', [$backups, 'download'])
            ->setName('admin.backups.download');
        $slim->post('/admin/backups/{name:gnucms-(?:sqlite|mysql)-[0-9-]+\\.(?:zip|tar)}/verify', [$backups, 'verify'])
            ->setName('admin.backups.verify');
        $slim->post('/admin/backups/{name:gnucms-(?:sqlite|mysql)-[0-9-]+\\.(?:zip|tar)}/restore', [$backups, 'restore'])
            ->setName('admin.backups.restore');
        $slim->post('/admin/backups/{name:gnucms-(?:sqlite|mysql)-[0-9-]+\\.(?:zip|tar)}/delete', [$backups, 'delete'])
            ->setName('admin.backups.delete');
        $slim->post('/admin/schema-backups/{name:board-v[0-9A-Za-z]+-[0-9-]+\\.sqlite}/delete', [$backups, 'deleteAutomatic'])
            ->setName('admin.schema-backups.delete');
        $slim->get('/admin/mail', [$cms, 'mailForm'])->setName('admin.mail');
        $slim->post('/admin/mail', [$cms, 'mail']);
        $slim->post('/admin/mail/password', [$cms, 'mailPassword'])->setName('admin.mail.password');
        $slim->post('/admin/mail/test', [$cms, 'mailTest'])->setName('admin.mail.test');
        $slim->get('/admin/content', [$cms, 'pages'])->setName('admin.content');
        $slim->get('/admin/content/trash', [$cms, 'trash'])->setName('admin.content.trash');
        $slim->post('/admin/content/trash/{id:[0-9]+}/restore', [$cms, 'restore'])->setName('admin.content.restore');
        $slim->post('/admin/content/trash/{id:[0-9]+}/delete', [$cms, 'permanentlyDelete'])
            ->setName('admin.content.permanent_delete');
        $slim->get('/admin/terms', [$cms, 'legal'])->setName('admin.terms');
        $slim->post('/admin/terms/uses', [$cms, 'consentUses'])->setName('admin.terms.uses');
        // 약관은 여기서만 만든다. 내용 관리와 완전히 갈라 헷갈릴 일을 없앤다.
        $slim->get('/admin/terms/new', [$cms, 'termsCreateForm'])->setName('admin.terms.create');
        $slim->post('/admin/terms/new', [$cms, 'termsCreate']);
        // {id} 는 숫자만 받으므로 위의 /admin/terms/uses 와 겹치지 않는다.
        $slim->get('/admin/terms/{id:[0-9]+}/consents', [$cms, 'consents'])->setName('admin.terms.consents');
        $slim->get('/admin/content/new', [$cms, 'createForm'])->setName('admin.content.create');
        $slim->post('/admin/content/new', [$cms, 'create']);
        $slim->post('/admin/terms/setup', [$cms, 'legalSetup'])->setName('admin.terms.setup');
        $slim->get('/admin/content/{id:[0-9]+}/edit', [$cms, 'editForm'])->setName('admin.content.edit');
        $slim->post('/admin/content/{id:[0-9]+}/edit', [$cms, 'update']);
        $slim->get('/admin/content/{id:[0-9]+}/preview', [$cms, 'preview'])->setName('admin.content.preview');
        $slim->post('/admin/content/{id:[0-9]+}/delete', [$cms, 'delete'])->setName('admin.content.delete');
        $slim->post('/admin/editor/images', [$cmsImages, 'upload'])->setName('admin.editor.images');
        $slim->post('/admin/editor/images/discard', [$cmsImages, 'discard'])
            ->setName('admin.editor.images.discard');
        $legacyContentRedirect = static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.content');
            return $response->withHeader('Location', $url)->withStatus(301);
        };
        $slim->get('/admin/pages', $legacyContentRedirect);
        $slim->get('/admin/documents', $legacyContentRedirect);

        $slim->get('/health', static function (
            ServerRequestInterface $request,
            ResponseInterface $response
        ) use ($app): ResponseInterface {
            return View::fromRequest($request)->render($response, 'health', [
                'dialect' => $app->db()->dialect()->name(),
            ]);
        });

        $slim->get('/', [new BoardController($app), 'index'])->setName('boards.index');
        $pageController = new PageController($app);
        // 옛 주소 되돌림. FastRoute 는 변수 라우트 뒤의 정적 라우트를 금지하므로
        // 정적 경로를 /content/{slug}·/terms/{slug} 보다 먼저 등록한다.
        // /content/terms 는 옛 테마 푸터가 아직 치는 주소라 살려 둔다.
        foreach (['/terms' => 'service', '/terms/terms' => 'service', '/content/terms' => 'service',
                  '/privacy' => 'privacy'] as $legacyPath => $slug) {
            $slim->get($legacyPath, static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ) use ($slug): ResponseInterface {
                $url = RouteContext::fromRequest($request)->getRouteParser()
                    ->urlFor('terms.show', ['slug' => $slug]);
                return $response->withHeader('Location', $url)->withStatus(301);
            });
        }
        $slim->get('/content/{slug:[a-z0-9][a-z0-9_-]*}', [$pageController, 'show'])->setName('content.show');
        // 약관의 정식 주소는 /terms/{slug} 다. 일반 내용과 주소부터 갈라 둔다.
        $slim->get('/terms/{slug:[a-z0-9][a-z0-9_-]*}', [$pageController, 'showTerms'])->setName('terms.show');
        // 파일 이름 뒤의 -thumb / -view 는 줄여서 내보내는 크기다 (ContentImageService::VARIANTS).
        $slim->get('/media/editor/{key:[a-f0-9]{32}}/{file:[a-f0-9]+(?:-thumb|-view)?\.(?:jpg|png|gif|webp)}',
            [$cmsImages, 'showOwned'])->setName('editor.owned_image');
        $slim->get('/media/editor/{year:[0-9]+}/{month:[0-9]+}/{file:[a-f0-9]+(?:-thumb|-view)?\.(?:jpg|png|gif|webp)}',
            [$cmsImages, 'show'])->setName('editor.image');
        $slim->get('/page/{slug:[a-z0-9][a-z0-9_-]*}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('content.show', [
                'slug' => (string) $args['slug'],
            ]);
            return $response->withHeader('Location', $url)->withStatus(301);
        });
        $posts = new PostController($app);
        $slim->get('/posts', [$posts, 'all'])->setName('posts.all');
        $slim->get('/boards/{key}', [$posts, 'index'])->setName('posts.index');
        $slim->get('/boards/{key}/new', [$posts, 'createForm'])->setName('posts.create');
        $slim->post('/boards/{key}/new', [$posts, 'create']);
        // 옛 주소. 관리 화면(/admin/boards/new 등)과 같게 '만들기 = new' 로 통일했다.
        $slim->get('/boards/{key}/write', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('posts.create', [
                'key' => (string) $args['key'],
            ]);
            return $response->withHeader('Location', $url)->withStatus(301);
        });
        $slim->post('/boards/{key}/write', [$posts, 'create']);
        $slim->get('/b/{key}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('posts.index', [
                'key' => (string) $args['key'],
            ]);
            $query = $request->getUri()->getQuery();
            return $response->withHeader('Location', $url . ($query === '' ? '' : '?' . $query))->withStatus(301);
        });
        $slim->get('/b/{key}/write', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('posts.create', [
                'key' => (string) $args['key'],
            ]);
            return $response->withHeader('Location', $url)->withStatus(301);
        });
        $slim->post('/b/{key}/write', [$posts, 'create']);
        $files = new FileController($app);
        $slim->get('/posts/{id:[0-9]+}', [$posts, 'show'])->setName('posts.show');
        $slim->post('/posts/{id:[0-9]+}/password', [$posts, 'unlockSecret'])->setName('posts.password');
        $slim->get('/posts/{id:[0-9]+}/edit', [$posts, 'editForm'])->setName('posts.edit');
        $slim->post('/posts/{id:[0-9]+}/edit', [$posts, 'update']);
        $slim->post('/posts/{id:[0-9]+}/delete', [$posts, 'destroy'])->setName('posts.delete');
        $slim->get('/posts/{id:[0-9]+}/files/{index:[0-9]+}', [$files, 'download'])
            ->setName('files.download');
        $slim->get('/posts/{id:[0-9]+}/images/{index:[0-9]+}', [$files, 'image'])
            ->setName('files.image');
        // 목록 카드에 쓰는 축소본. 원본은 위 주소로 눌렀을 때만 받아 간다.
        $slim->get('/posts/{id:[0-9]+}/images/{index:[0-9]+}/{variant:thumb|view}', [$files, 'image'])
            ->setName('files.image_variant');

        $comments = new CommentController($app);
        $slim->post('/posts/{id:[0-9]+}/comments', [$comments, 'create'])->setName('comments.create');
        // 정적인 회원 댓글 목록 주소다.
        $slim->get('/comments', [$comments, 'byAuthor'])->setName('comments.byAuthor');
        $slim->get('/comments/{id:[0-9]+}/password', [$comments, 'passwordForm'])->setName('comments.password');
        $slim->post('/comments/{id:[0-9]+}/password', [$comments, 'unlockSecret']);
        $slim->post('/comments/{id:[0-9]+}/ownership', [$comments, 'verifyOwnership'])
            ->setName('comments.ownership');

        $notifications = new NotificationController($app);
        $slim->get('/notifications', [$notifications, 'index'])->setName('notifications.index');
        $slim->get('/notifications/{id:[0-9]+}', [$notifications, 'open'])->setName('notifications.open');
        $slim->post('/notifications/read-all', [$notifications, 'readAll'])->setName('notifications.read_all');

        $slim->get('/comments/{id:[0-9]+}/edit', [$comments, 'editForm'])->setName('comments.edit');
        $slim->post('/comments/{id:[0-9]+}/edit', [$comments, 'update']);
        $slim->post('/comments/{id:[0-9]+}/delete', [$comments, 'destroy'])->setName('comments.delete');

        $slim->post('/boards/{key}/files', [$files, 'upload'])->setName('boards.files.upload');

        // 본문 편집기 이미지. 관리자 전용인 admin.editor.images 와 달리 게시판 권한으로 판단한다.
        $editorImages = new EditorImageController($app);
        $slim->post('/boards/{key}/editor/images', [$editorImages, 'uploadForBoard'])
            ->setName('board.editor.images');
        $slim->post('/boards/{key}/editor/images/discard', [$editorImages, 'discardForBoard'])
            ->setName('board.editor.images.discard');
        $slim->post('/posts/{id:[0-9]+}/comments/images', [$editorImages, 'uploadForComment'])
            ->setName('comment.editor.images');
        $slim->post('/posts/{id:[0-9]+}/comments/images/discard', [$editorImages, 'discardForComment'])
            ->setName('comment.editor.images.discard');
        $slim->get('/p/{id:[0-9]+}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('posts.show', [
                'id' => (string) $args['id'],
            ]);
            $query = $request->getUri()->getQuery();
            return $response->withHeader('Location', $url . ($query === '' ? '' : '?' . $query))->withStatus(301);
        });
        $slim->get('/p/{id:[0-9]+}/files/{index:[0-9]+}', static function (
            ServerRequestInterface $request,
            ResponseInterface $response,
            array $args
        ): ResponseInterface {
            $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('files.download', [
                'id' => (string) $args['id'],
                'index' => (string) $args['index'],
            ]);
            return $response->withHeader('Location', $url)->withStatus(301);
        });
    }
}
