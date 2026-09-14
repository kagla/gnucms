<?php

declare(strict_types=1);

namespace GnuCms;

use GnuCms\Account\AccountService;
use GnuCms\Account\UserRepository;
use GnuCms\Account\TokenRepository;
use GnuCms\Account\TokenService;
use GnuCms\Account\IdentityRepository;
use GnuCms\Account\LinkingService;
use GnuCms\Account\SocialAuthService;
use GnuCms\Account\LoginEventRepository;
use GnuCms\Account\AvatarService;
use GnuCms\Account\AdminService;
use GnuCms\Account\ConsentRepository;
use GnuCms\Auth\Acl;
use GnuCms\Auth\PasswordThrottle;
use GnuCms\Validation\Validator;
use GnuCms\Auth\Identity;
use GnuCms\Db\Connection;
use GnuCms\Db\SchemaUpgrader;
use GnuCms\Repository\BoardRepository;
use GnuCms\Repository\CommentRepository;
use GnuCms\Repository\NotificationRepository;
use GnuCms\Repository\PostRepository;
use GnuCms\Service\AttachmentService;
use GnuCms\Service\BoardService;
use GnuCms\Service\CommentService;
use GnuCms\Service\NotificationService;
use GnuCms\Service\PostService;
use GnuCms\Spam\WriteRateLimiter;
use GnuCms\Spam\TurnstileSettingsRepository;
use GnuCms\Spam\TurnstileSettingsService;
use GnuCms\Spam\TurnstileVerifier;
use GnuCms\Mail\NativeMailer;
use GnuCms\Mail\MailerInterface;
use GnuCms\Mail\MailSettingsRepository;
use GnuCms\Mail\MailSettingsService;
use GnuCms\Mail\SecretCipher;
use GnuCms\Mail\SmtpMailer;
use GnuCms\Oauth\ProviderRegistry;
use GnuCms\Oauth\OauthSettingsRepository;
use GnuCms\Oauth\OauthSettingsService;
use GnuCms\Cms\CmsRepository;
use GnuCms\Cms\CmsService;
use GnuCms\Cms\ConsentUseRepository;
use GnuCms\Cms\ContentImageService;
use GnuCms\Cms\ContentRenderer;
use GnuCms\Cms\HtmlSanitizer;
use GnuCms\Maintenance\BackupManager;

/**
 * 설정으로부터 객체 그래프를 조립한다. 컨테이너 라이브러리를 쓰지 않는 이유는
 * 객체 수가 열 개 남짓이고 런타임 의존성을 0 으로 유지해야 하기 때문이다.
 */
final class App
{
    /** @var array */
    private $config;

    /** @var Connection|null */
    private $db = null;

    /** @var BoardRepository|null */
    private $boards = null;

    /** @var PostRepository|null */
    private $posts = null;

    /** @var CommentRepository|null */
    private $comments = null;

    /** @var BoardService|null */
    private $boardService = null;

    /** @var PostService|null */
    private $postService = null;

    /** @var CommentService|null */
    private $commentService = null;

    private ?WriteRateLimiter $writeRateLimiter = null;

    private ?TurnstileVerifier $turnstileVerifier = null;

    private ?TurnstileSettingsRepository $turnstileSettings = null;

    private ?TurnstileSettingsService $turnstileSettingsService = null;

    /** @var NotificationRepository|null */
    private $notifications = null;

    /** @var NotificationService|null */
    private $notificationService = null;

    /** @var AttachmentService|null */
    private $attachmentService = null;

    /** @var UserRepository|null */
    private $users = null;

    /** @var AccountService|null */
    private $accountService = null;

    /** @var TokenRepository|null */
    private $tokens = null;

    private ?IdentityRepository $identities = null;

    private ?LinkingService $linkingService = null;

    private ?SocialAuthService $socialAuthService = null;

    private ?LoginEventRepository $loginEvents = null;
    private ?AvatarService $avatars = null;

    private ?ProviderRegistry $providerRegistry = null;

    private ?OauthSettingsRepository $oauthSettings = null;

    private ?OauthSettingsService $oauthSettingsService = null;

    private ?MailerInterface $mailer = null;

    private ?MailSettingsRepository $mailSettings = null;

    private ?MailSettingsService $mailSettingsService = null;

    private ?AdminService $adminService = null;

    private ?\GnuCms\Payment\Settings $paymentSettings = null;
    private ?\GnuCms\Payment\InicisGateway $inicisGateway = null;
    private ?\GnuCms\Messaging\MessagingService $messaging = null;
    private ?\GnuCms\Initalk\Service $initalk = null;

    private ?CmsRepository $cms = null;

    private ?CmsService $cmsService = null;

    private ?ConsentRepository $consents = null;

    private ?ConsentUseRepository $consentUses = null;

    private ?HtmlSanitizer $htmlSanitizer = null;

    private ?ContentRenderer $contentRenderer = null;

    private ?ContentImageService $contentImages = null;

    private ?BackupManager $backupManager = null;

    private ?string $configFile;

    /** @var Identity */
    private $identity;

    public function __construct(array $config, ?string $configFile = null)
    {
        $this->config = $config;
        $this->configFile = $configFile;
        $this->identity = Identity::guest();
        // 검사 기준이 곳곳에 흩어지지 않도록 비밀번호 최소 길이는 여기서 한 번만 정한다.
        Validator::setPasswordMin((int) $this->config('auth.password_min', 8));
    }

    /** 점 표기 경로로 설정을 읽는다. 예: config('auth.secret') */
    public function config(string $path, $default = null)
    {
        $node = $this->config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    public function db(): Connection
    {
        if ($this->db === null) {
            $this->db = Connection::create((array) $this->config('db', []));
        }

        return $this->db;
    }

    /** storage/ 절대 경로. 설정 storage.dir 가 있으면 그것을 쓴다(테스트·특수 배치용). */
    public function storageDir(): string
    {
        return rtrim((string) $this->config('storage.dir', dirname(__DIR__) . '/storage'), '/');
    }

    public function schemaUpgrader(): SchemaUpgrader
    {
        return new SchemaUpgrader($this->db(), $this->storageDir());
    }

    public function backups(): BackupManager
    {
        if ($this->backupManager === null) {
            $site = $this->cmsService()->settings();
            $this->backupManager = new BackupManager(
                $this->db(),
                $this->config,
                $this->storageDir(),
                $this->configFile,
                is_string($site['timezone'] ?? null) ? $site['timezone'] : 'Asia/Seoul'
            );
        }

        return $this->backupManager;
    }

    public function boards(): BoardRepository
    {
        if ($this->boards === null) {
            $this->boards = new BoardRepository($this->db());
        }

        return $this->boards;
    }

    public function posts(): PostRepository
    {
        if ($this->posts === null) {
            $this->posts = new PostRepository($this->db());
        }

        return $this->posts;
    }

    public function comments(): CommentRepository
    {
        if ($this->comments === null) {
            $this->comments = new CommentRepository($this->db());
        }

        return $this->comments;
    }

    public function notifications(): NotificationRepository
    {
        if ($this->notifications === null) {
            $this->notifications = new NotificationRepository($this->db());
        }

        return $this->notifications;
    }

    public function notificationService(): NotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = new NotificationService(
                $this->notifications(),
                $this->posts(),
                $this->comments()
            );
        }

        return $this->notificationService;
    }

    public function boardService(): BoardService
    {
        if ($this->boardService === null) {
            $this->boardService = new BoardService($this->db(), $this->boards(), $this->posts(), $this->comments());
        }

        return $this->boardService;
    }

    public function postService(): PostService
    {
        if ($this->postService === null) {
            $this->postService = new PostService(
                $this->boardService(),
                $this->posts(),
                $this->htmlSanitizer(),
                $this->contentImages()
            );
            $this->postService->setWriteRateLimiter($this->writeRateLimiter());
            // 쓰기 규칙은 사이트 설정이 정한다. settings() 는 요청당 한 번만 DB 를 읽는다.
            $this->postService->setContentMinChars((int) $this->cmsService()->settings()['post_min_chars']);
            // attachments() 가 다시 postService() 를 부르므로 여기서 곧장 호출하면 무한
            // 재귀가 된다. 대신 지연 콜백만 넘겨 둔다: PostService 는 첨부 검증이 실제로
            // 필요한 순간(verifyAttachments())에야 이 콜백을 부른다. 이때는 postService()
            // 가 이미 캐시돼 있어 재귀가 없다. 이러면 컨트롤러가 요청마다 attachments()
            // 를 미리 불러 둬야 한다는 계약이 사라진다.
            $this->postService->setAttachmentResolver(function () { $this->attachments(); });
            $this->postService->setUserRepository($this->users());
        }

        return $this->postService;
    }

    public function attachments(): AttachmentService
    {
        if ($this->attachmentService === null) {
            $uploads = (array) $this->config('uploads', []);
            $uploads['max_bytes'] = $this->cmsService()->settings()['attach_max_mb'] * 1048576;
            $this->attachmentService = new AttachmentService(
                $this->boardService(),
                $this->postService(),
                $this->posts(),
                $uploads,
                (string) $this->config('auth.secret', '')
            );
            $this->postService()->setAttachmentLimit($this->cmsService()->settings()['attach_limit']);
            $this->postService()->setAttachmentService($this->attachmentService);
        }

        return $this->attachmentService;
    }

    public function commentService(): CommentService
    {
        if ($this->commentService === null) {
            $this->commentService = new CommentService(
                $this->postService(),
                $this->posts(),
                $this->comments(),
                $this->htmlSanitizer(),
                $this->contentImages(),
                $this->notificationService()
            );
            $this->commentService->setWriteRateLimiter($this->writeRateLimiter());
            $this->commentService->setContentMinChars((int) $this->cmsService()->settings()['comment_min_chars']);
            $this->commentService->setUserRepository($this->users());
            $this->commentService->setBoardService($this->boardService());
        }

        return $this->commentService;
    }

    public function writeRateLimiter(): WriteRateLimiter
    {
        if ($this->writeRateLimiter === null) {
            $settings = $this->cmsService()->settings();
            $this->writeRateLimiter = new WriteRateLimiter($this->db(), [
                'post' => [
                    [(int) $settings['post_rate_interval'], 1],
                    [600, (int) $settings['post_rate_10m']],
                    [86400, (int) $settings['post_rate_day']],
                ],
                'comment' => [
                    [(int) $settings['comment_rate_interval'], 1],
                    [600, (int) $settings['comment_rate_10m']],
                    [86400, (int) $settings['comment_rate_day']],
                ],
            ]);
        }

        return $this->writeRateLimiter;
    }

    public function turnstile(): TurnstileVerifier
    {
        if ($this->turnstileVerifier === null) {
            $config = $this->turnstileSettingsService()->runtime();
            $transport = isset($config['transport']) && is_callable($config['transport'])
                ? $config['transport'] : null;
            $this->turnstileVerifier = new TurnstileVerifier($config, $transport);
        }

        return $this->turnstileVerifier;
    }

    public function turnstileSettings(): TurnstileSettingsRepository
    {
        if ($this->turnstileSettings === null) {
            $this->turnstileSettings = new TurnstileSettingsRepository($this->db());
        }

        return $this->turnstileSettings;
    }

    public function turnstileSettingsService(): TurnstileSettingsService
    {
        if ($this->turnstileSettingsService === null) {
            $this->turnstileSettingsService = new TurnstileSettingsService(
                $this->turnstileSettings(),
                new SecretCipher((string) $this->config('auth.secret', '')),
                (array) $this->config('turnstile', []),
                (string) $this->config('app.url', GNUCMS_URL)
            );
        }

        return $this->turnstileSettingsService;
    }

    /** 관리자에서 키를 바꾼 같은 프로세스에서도 다음 요청부터 새 설정을 쓴다. */
    public function refreshTurnstile(): void
    {
        $this->turnstileVerifier = null;
        $this->passwordThrottle = null;
        $this->accountService = null;
    }

    public function users(): UserRepository
    {
        if ($this->users === null) {
            $this->users = new UserRepository($this->db());
        }

        return $this->users;
    }

    public function avatars(): AvatarService
    {
        if ($this->avatars === null) $this->avatars = new AvatarService($this->storageDir() . '/avatars');
        return $this->avatars;
    }

    public function accountService(): AccountService
    {
        if ($this->accountService === null) {
            if ($this->tokens === null) {
                $this->tokens = new TokenRepository($this->db());
            }
            $this->accountService = new AccountService(
                $this->users(),
                new TokenService($this->tokens),
                $this->mailer(),
                (string) $this->config('app.url', GNUCMS_URL),
                $this->cmsService(),
                $this->consents()
            );
            $this->accountService->setPasswordThrottle($this->passwordThrottle());
        }

        return $this->accountService;
    }

    public function providerRegistry(): ProviderRegistry
    {
        if ($this->providerRegistry === null) {
            $this->providerRegistry = new ProviderRegistry($this->oauthSettingsService()->runtime());
        }
        return $this->providerRegistry;
    }

    public function setProviderRegistry(ProviderRegistry $registry): void
    {
        $this->providerRegistry = $registry;
        $this->socialAuthService = null;
    }

    public function oauthSettings(): OauthSettingsRepository
    {
        if ($this->oauthSettings === null) {
            $this->oauthSettings = new OauthSettingsRepository($this->db());
        }
        return $this->oauthSettings;
    }

    public function oauthSettingsService(): OauthSettingsService
    {
        if ($this->oauthSettingsService === null) {
            $this->oauthSettingsService = new OauthSettingsService(
                $this->oauthSettings(),
                new SecretCipher((string) $this->config('auth.secret', '')),
                (array) $this->config('oauth', []),
                (string) $this->config('app.url', GNUCMS_URL)
            );
        }
        return $this->oauthSettingsService;
    }

    public function refreshOauthProviders(): void
    {
        $this->providerRegistry = null;
        $this->socialAuthService = null;
    }

    /** 테스트·특수 실행에서 메일 전송기를 바꾼다. 이미 조립된 인증 서비스도 함께 새로 만든다. */
    public function setMailer(MailerInterface $mailer): void
    {
        $this->mailer = $mailer;
        $this->accountService = null;
        $this->socialAuthService = null;
    }

    public function socialAuthService(): SocialAuthService
    {
        if ($this->socialAuthService === null) {
            if ($this->linkingService === null) {
                $this->linkingService = new LinkingService(
                    $this->db(), $this->users(), $this->identities(),
                    $this->cmsService(), $this->consents(), $this->avatars()
                );
            }
            $this->socialAuthService = new SocialAuthService(
                $this->providerRegistry(), $this->linkingService, $this->mailer(),
                (string) $this->config('app.url', GNUCMS_URL),
                $this->cmsService()
            );
        }
        return $this->socialAuthService;
    }

    public function identities(): IdentityRepository
    {
        if ($this->identities === null) {
            $this->identities = new IdentityRepository($this->db());
        }
        return $this->identities;
    }

    public function loginEvents(): LoginEventRepository
    {
        if ($this->loginEvents === null) {
            $this->loginEvents = new LoginEventRepository($this->db());
        }
        return $this->loginEvents;
    }

    private function mailer(): MailerInterface
    {
        if ($this->mailer === null) {
            $smtp = $this->mailSettingsService()->runtime();
            $this->mailer = $smtp === null
                ? new NativeMailer(
                    (string) $this->config('mail.from', 'no-reply@localhost'),
                    (string) $this->cmsService()->settings()['site_name']
                )
                : new SmtpMailer($smtp);
        }
        return $this->mailer;
    }

    public function mailSettings(): MailSettingsRepository
    {
        if ($this->mailSettings === null) {
            $this->mailSettings = new MailSettingsRepository($this->db());
        }
        return $this->mailSettings;
    }

    public function mailSettingsService(): MailSettingsService
    {
        if ($this->mailSettingsService === null) {
            $this->mailSettingsService = new MailSettingsService(
                $this->mailSettings(),
                new SecretCipher((string) $this->config('auth.secret', '')),
                (string) $this->config('mail.from', 'no-reply@localhost')
            );
        }
        return $this->mailSettingsService;
    }

    public function paymentSettings(): \GnuCms\Payment\Settings
    {
        return $this->paymentSettings ??= new \GnuCms\Payment\Settings($this, 'inicis');
    }

    public function inicisGateway(): \GnuCms\Payment\InicisGateway
    {
        return $this->inicisGateway ??= new \GnuCms\Payment\InicisGateway($this->paymentSettings());
    }

    /** 테스트에서 모의 전송기를 가진 게이트웨이로 바꾼다. */
    public function setInicisGateway(\GnuCms\Payment\InicisGateway $gateway): void
    {
        $this->inicisGateway = $gateway;
    }

    public function messaging(): \GnuCms\Messaging\MessagingService
    {
        return $this->messaging ??= new \GnuCms\Messaging\MessagingService($this);
    }

    /** 테스트에서 모의 HTTP 전송기를 가진 서비스로 바꾼다. */
    public function setMessaging(\GnuCms\Messaging\MessagingService $service): void
    {
        $this->messaging = $service;
    }

    public function initalk(): \GnuCms\Initalk\Service
    {
        return $this->initalk ??= new \GnuCms\Initalk\Service($this);
    }

    public function sendMailTest(): void
    {
        $settings = $this->mailSettingsService()->runtime();
        if ($settings === null) {
            throw \GnuCms\Error\DomainError::validation(['enabled' => 'SMTP를 사용하도록 설정해 주세요.']);
        }
        $siteName = (string) $this->cmsService()->settings()['site_name'];
        $this->mailer()->send(
            (string) $settings['from_email'],
            '[' . $siteName . '] SMTP 테스트 메일',
            "SMTP 설정이 정상적으로 작동합니다.\n\n이 메일은 {$siteName} 관리자에서 보낸 테스트 메일입니다."
        );
    }

    public function adminService(): AdminService
    {
        if ($this->adminService === null) {
            $this->adminService = new AdminService($this->db(), $this->users(), $this->boardService());
        }
        return $this->adminService;
    }

    public function cms(): CmsRepository
    {
        if ($this->cms === null) {
            $this->cms = new CmsRepository($this->db());
        }
        return $this->cms;
    }

    public function cmsService(): CmsService
    {
        if ($this->cmsService === null) {
            $this->cmsService = new CmsService(
                $this->cms(), $this->htmlSanitizer(), $this->contentImages(),
                $this->consentUses(), $this->consents()
            );
        }
        return $this->cmsService;
    }

    public function htmlSanitizer(): HtmlSanitizer
    {
        if ($this->htmlSanitizer === null) {
            $this->htmlSanitizer = new HtmlSanitizer();
        }
        return $this->htmlSanitizer;
    }

    public function contentRenderer(): ContentRenderer
    {
        if ($this->contentRenderer === null) {
            $this->contentRenderer = new ContentRenderer($this->htmlSanitizer());
        }

        return $this->contentRenderer;
    }

    public function contentImages(): ContentImageService
    {
        if ($this->contentImages === null) {
            $uploads = (array) $this->config('uploads', []);
            $uploadRoot = rtrim((string) ($uploads['dir'] ?? dirname(__DIR__) . '/storage/uploads'), '/');
            $root = (string) $this->config('editor.dir', dirname($uploadRoot) . '/editor');
            $maxBytes = (int) $this->config('editor.max_bytes', 5 * 1024 * 1024);
            $this->contentImages = new ContentImageService($root, $maxBytes);
        }
        return $this->contentImages;
    }

    public function consents(): ConsentRepository
    {
        if ($this->consents === null) {
            $this->consents = new ConsentRepository($this->db());
        }
        return $this->consents;
    }

    public function consentUses(): ConsentUseRepository
    {
        if ($this->consentUses === null) {
            $this->consentUses = new ConsentUseRepository($this->db());
        }
        return $this->consentUses;
    }

    public function setIdentity(Identity $identity): void
    {
        $this->identity = $identity;
    }

    private ?PasswordThrottle $passwordThrottle = null;

    /** 비밀번호 대입 방어. 프록시 헤더는 믿지 않는다(동의 증적과 같은 원칙). */
    public function passwordThrottle(): PasswordThrottle
    {
        if ($this->passwordThrottle === null) {
            $ip = isset($_SERVER['REMOTE_ADDR']) && is_scalar($_SERVER['REMOTE_ADDR'])
                ? (string) $_SERVER['REMOTE_ADDR'] : null;
            $this->passwordThrottle = new PasswordThrottle(
                $this->db(),
                $ip,
                $this->turnstile()->isEnabled() && $this->turnstile()->isConfigured()
            );
        }

        return $this->passwordThrottle;
    }

    public function guestAcl(): Acl
    {
        $acl = new Acl($this->identity);
        $acl->setPasswordThrottle($this->passwordThrottle());
        $acl->setGuestWriteEnabled((bool) $this->cmsService()->settings()['guest_write_enabled']);
        $acl->setSecretGrants(isset($_SESSION['secret_posts']) && is_array($_SESSION['secret_posts'])
            ? $_SESSION['secret_posts'] : []);
        $acl->setCommentSecretGrants(
            isset($_SESSION['secret_comments']) && is_array($_SESSION['secret_comments'])
                ? $_SESSION['secret_comments'] : []
        );
        $acl->setCommentEditGrants(
            isset($_SESSION['comment_edits']) && is_array($_SESSION['comment_edits'])
                ? $_SESSION['comment_edits'] : []
        );

        return $acl;
    }
}
