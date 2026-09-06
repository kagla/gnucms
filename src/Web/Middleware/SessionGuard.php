<?php

declare(strict_types=1);

namespace GnuCms\Web\Middleware;

use GnuCms\App;
use GnuCms\Auth\Identity;
use GnuCms\View\ViewInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionGuard implements MiddlewareInterface
{
    private App $app;
    private ViewInterface $view;

    public function __construct(App $app, ViewInterface $view)
    {
        $this->app = $app;
        $this->view = $view;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name(GNUCMS_ID . '_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $request->getUri()->getScheme() === 'https',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        $identity = Identity::guest();
        if (isset($_SESSION['user_id'], $_SESSION['session_epoch'])) {
            $identity = $this->app->accountService()->identityForSession(
                (int) $_SESSION['user_id'],
                (int) $_SESSION['session_epoch']
            );
            if ($identity->isGuest()) {
                unset($_SESSION['user_id'], $_SESSION['session_epoch']);
            }
        }
        $this->app->setIdentity($identity);

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $sessionUser = $identity->isGuest() ? null : $this->app->users()->findById((int) $identity->sub());
        $this->view->addGlobal('current_user', [
            'is_guest' => $identity->isGuest(),
            // 글·댓글이 내 것인지 화면에서 가리려면 작성자 id 와 견줄 값이 필요하다.
            'id' => $identity->sub(),
            'display_name' => $identity->displayName(),
            'is_admin' => $identity->isAdmin(),
            'avatar_file' => $sessionUser['avatar_file'] ?? null,
        ]);
        $this->view->addGlobal('csrf_token', $_SESSION['csrf_token']);
        $this->view->addGlobal('unread_notifications', $this->unreadCount());
        $this->view->addGlobal('header_boards', $this->headerBoards());
        $this->view->addGlobal('public_extensions', $request->getAttribute('gnucms.public_extensions', []));

        try {
            return $handler->handle($request);
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /** 머리글의 알림 배지에 쓴다. 어떤 이유로든 세지 못하면 배지를 감춘다. */
    private function unreadCount(): int
    {
        try {
            return $this->app->notificationService()->unreadCount($this->app->guestAcl());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 로그인 신원을 반영해 읽을 수 있는 상단 게시판만 고른다. */
    private function headerBoards(): array
    {
        try {
            return array_values(array_filter(
                $this->app->boardService()->listBoards($this->app->guestAcl()),
                static fn (array $board): bool => !empty($board['show_in_header'])
            ));
        } catch (\Throwable $e) {
            // 설치·업그레이드 도중 표가 불완전해도 오류 화면까지 정상적으로 그린다.
            return [];
        }
    }
}
