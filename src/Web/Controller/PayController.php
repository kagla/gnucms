<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Checkout;
use GnuCms\Initalk\Status;
use GnuCms\Payment\CallbackToken;
use GnuCms\Payment\ExecutionLock;
use GnuCms\Support\Clock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** 고객이 알림톡 링크로 여는 결제 페이지. 회원·로그인과 무관하며 토큰으로만 요청을 찾는다. */
final class PayController
{
    public function __construct(private App $app)
    {
    }

    private function find(string $token): array
    {
        $requests = $this->app->initalk()->requests;
        $found = $requests->findByToken($token);
        if ($found === null) throw DomainError::notFound('결제 요청을 찾을 수 없습니다.');
        if (Status::canPay($found['status']) && $found['expires_at'] <= Clock::timestamp() && $requests->expireOne($found['id'])) $found = $requests->find($found['id']);
        return $found;
    }

    private function siteUrl(): string { return rtrim((string) $this->app->config('app.url', GNUCMS_URL), '/'); }
    private function basePath(): string { return rtrim((string) parse_url($this->siteUrl(), PHP_URL_PATH), '/'); }

    /** Slim-Psr7 은 실제 process 의 getallheaders() 로 헤더를 만들어, 테스트가 넘긴 서버변수의 HTTP_* 값이
     *  헤더에 실리지 않는다(PSR-7 getServerParams() 에만 남는다). 실제 요청에서는 둘 다 채워지므로 안전하다. */
    private function userAgent(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('User-Agent');
        if ($header !== '') return $header;
        $fromServer = $request->getServerParams()['HTTP_USER_AGENT'] ?? '';
        return is_string($fromServer) ? $fromServer : '';
    }

    private function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $config = $this->app->initalk()->settings->read();
        return View::fromRequest($request)->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'pay/' . $template,
            $data + ['store_name' => $config['store_name'], 'support_phone' => $config['support_phone'], 'errors' => [],
                'time' => static fn ($timestamp): string => $timestamp === null ? '' : (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y년 m월 d일 H:i')]);
    }

    /** 화면에 넘길 요청 정보. 휴대폰 번호는 제외한다. */
    private function safe(array $found): array
    {
        unset($found['phone'], $found['phone_hash'], $found['phone_mask']);
        return $found;
    }

    private function state(array $found): string
    {
        return match (true) {
            in_array($found['status'], [Status::PAID, Status::REFUNDED], true) => 'done',
            $found['status'] === Status::EXPIRED => 'expired',
            $found['status'] === Status::CANCELLED => 'cancelled',
            !$this->app->paymentSettings()->available($found['environment']) => 'unavailable',
            default => 'open',
        };
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $found = $this->find((string) $args['token']);
        $query = $request->getQueryParams();
        return $this->render($request, $response, 'show', ['request' => $this->safe($found), 'state' => $this->state($found),
            'failed' => ($query['failed'] ?? '') === '1', 'errors' => []]);
    }

    public function start(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        Csrf::assert($request);
        $found = $this->find((string) $args['token']);
        $token = (string) $args['token'];
        try {
            $payment = ExecutionLock::run($this->app->storageDir(), fn (): array => $this->app->initalk()->checkout->start(
                $found['id'], Checkout::device($this->userAgent($request)),
                $this->siteUrl() . '/pay/' . $token . '/return', $this->siteUrl() . '/pay/callback'));
        } catch (DomainError $e) {
            $message = $e->status() >= 500 ? '지금은 결제할 수 없습니다. 잠시 후 다시 시도해 주세요.' : implode(' ', array_values($e->details() ?: [$e->getMessage()]));
            return $this->render($request, $response->withStatus($e->status()), 'show', ['request' => $this->safe($found), 'state' => $this->state($found), 'failed' => false, 'errors' => [$message]]);
        }
        return $this->render($request, $response, 'start', ['request' => $this->safe($found), 'payment' => $payment, 'token' => $token]);
    }

    /** 결제창 닫기·실패 복귀. 아직 미결제면 안내 표시와 함께 결제 페이지로. */
    public function back(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $found = $this->find((string) $args['token']);
        $suffix = Status::canPay($found['status']) ? '?failed=1' : '';
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $this->basePath() . '/pay/' . $args['token'] . $suffix);
    }

    /** ExternalRequests 인증기: 요청이 있고 state가 그 요청·결제사·설정 판의 HMAC과 맞아야 한다. */
    public function callbackAuthenticate(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        $id = $query['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) return false;
        try {
            $found = $this->app->initalk()->requests->find($id);
        } catch (DomainError $e) {
            return false;
        }
        return $found['config_revision'] !== '' && CallbackToken::verify($this->app, Checkout::order($found), $query['state'] ?? null);
    }

    /** ExternalRequests 처리기: 승인·조회 후 결제 페이지로 보낸다. 실패는 확인 필요로 표시한다. */
    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $id = (string) $request->getQueryParams()['id'];
        $service = $this->app->initalk();
        $found = $service->requests->find($id);
        ExecutionLock::run($this->app->storageDir(), function () use ($service, $id, $request): void {
            try {
                $service->checkout->complete($id, is_array($request->getParsedBody()) ? $request->getParsedBody() : []);
            } catch (DomainError $e) {
                $service->requests->setReview($id, true, 'system', $e->status() >= 500 ? '승인·조회 결과를 확인해 주세요.' : '인증 결과 검증 실패: ' . implode(' ', array_values($e->details() ?: [$e->getMessage()])));
            }
        });
        return $response->withStatus(303)->withHeader('Location', $this->basePath() . '/pay/' . $found['url_token']);
    }
}
