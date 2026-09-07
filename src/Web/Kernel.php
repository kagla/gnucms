<?php

declare(strict_types=1);

namespace GnuCms\Web;

use GnuCms\App;
use GnuCms\Web\Middleware\ErrorPageMiddleware;
use GnuCms\Web\Middleware\HtmlContentTypeMiddleware;
use GnuCms\Web\Middleware\SessionGuard;
use GnuCms\Web\Middleware\SeoHeadersMiddleware;
use GnuCms\Error\DomainError;
use GnuCms\Theme\ThemeManager;
use GnuCms\Validation\Validator;
use GnuCms\View\PhpView;
use GnuCms\Web\Middleware\ViewMiddleware;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;

/**
 * Slim 앱을 조립한다. 미들웨어는 나중에 add 한 것이 바깥이므로
 * 오류 미들웨어를 마지막에 넣어 전부를 감싸게 한다.
 */
final class Kernel
{
    public static function create(App $app, string $templateDir, string $basePath): SlimApp
    {
        $slim = AppFactory::create();
        $slim->setBasePath($basePath);

        // 배포 뒤 첫 요청에서 스스로 새 판으로 옮긴다. 백업·잠금·실패 기록은 SchemaUpgrader 가 한다.
        // 못 옮기면 MaintenanceRequired 가 나고 www/index.php 가 점검 화면을 낸다.
        $app->schemaUpgrader()->run();

        $site = $app->cmsService()->settings();
        $themes = new ThemeManager(
            $templateDir,
            dirname($templateDir) . '/www/themes',
            (string) $site['theme']
        );
        $site['theme'] = $themes->name();

        // 컨트롤러는 이 View 만 안다. 템플릿은 PHP 파일이고 엔진은 PhpView 하나다.
        $view = new PhpView(
            $themes->templatePaths(),
            $slim->getRouteCollector()->getRouteParser(),
            $basePath,
            static fn (string $path): string => $themes->assetUrl($path, $basePath),
            [$app->contentRenderer(), 'render']
        );

        $view->addGlobal('current_user', [
            'is_guest' => true, 'id' => null, 'display_name' => null, 'is_admin' => false,
            'avatar_file' => null,
        ]);
        $view->addGlobal('csrf_token', '');
        $view->addGlobal('unread_notifications', 0);
        $view->addGlobal('password_min', Validator::passwordMin());
        $view->addGlobal('oauth_providers', $app->providerRegistry()->options());
        $view->addGlobal('turnstile_enabled', $app->turnstile()->isEnabled());
        $view->addGlobal('turnstile_configured', $app->turnstile()->isConfigured());
        $view->addGlobal('turnstile_site_key', $app->turnstile()->siteKey());
        $registrationAvailable = (bool) $site['registration_enabled']
            || (bool) $site['social_registration_enabled'];
        $legalDocuments = [];
        // 가입 화면에 붙는 동의 항목 전부. 개수 제한이 없고, 없으면 빈 배열이다.
        $consentDocuments = [];
        // 사이트 하단에 늘어놓을 공개 약관 전부. 사용처와 무관하다.
        $legalPages = [];
        try {
            $legalPages = $app->cmsService()->publishedConsentPages();
            // 동의 항목을 먼저 읽는다. 씨앗 약관이 아직 없어 legalDocuments() 가
            // 튕겨도, 이미 붙여 둔 항목까지 함께 사라지지는 않는다.
            $consentDocuments = $app->cmsService()->consentDocuments('signup');
            if ($app->users()->countAll() > 0) {
                $legalDocuments = $app->cmsService()->legalDocuments();
            }
        } catch (DomainError $e) {
            // 표가 아직 없는 반쯤 적용된 DB 에서 모든 화면이 죽으면 안 된다.
            $registrationAvailable = false;
        }
        $view->addGlobal('site', $site);
        $view->addGlobal('registration_available', $registrationAvailable);
        $view->addGlobal('legal_documents', $legalDocuments);
        $view->addGlobal('consent_documents', $consentDocuments);
        $view->addGlobal('legal_pages', $legalPages);
        $view->addGlobal('site_menu', $app->cmsService()->menu());
        // 실제 목록은 SessionGuard 가 로그인 신원을 복원한 뒤 채운다.
        $view->addGlobal('header_boards', []);
        $view->addGlobal('base_path', $basePath);
        $view->addGlobal('site_url', rtrim((string) $app->config('app.url', GNUCMS_URL), '/'));
        $view->addGlobal('active_theme', $themes->name());
        $view->addGlobal('available_themes', $themes->availableThemes());
        // 사람이 보는 이름은 site.site_name 이 앞서고, GNUCMS 는 그 기본값이다.
        $view->addGlobal('GNUCMS', GNUCMS);
        $view->addGlobal('GNUCMS_ID', GNUCMS_ID);

        $slim->add(new ViewMiddleware($view));
        $slim->addRoutingMiddleware();
        $slim->add(new ErrorPageMiddleware(
            $view,
            (bool) $app->config('debug', false),
            $app->config('log.file') === null ? null : (string) $app->config('log.file'),
            $slim->getRouteCollector()->getRouteParser()
        ));
        $slim->add(new HtmlContentTypeMiddleware());
        $slim->add(new SessionGuard($app, $view));
        $slim->add(new SeoHeadersMiddleware());
        $slim->addBodyParsingMiddleware();

        Routes::register($slim, $app);

        return $slim;
    }
}
