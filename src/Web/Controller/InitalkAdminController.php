<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Initalk\Phone;
use GnuCms\Initalk\Status;
use GnuCms\Payment\ExecutionLock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/** 운영 → 이니톡 결제. 전체 관리자 전용, POST는 세션 CSRF. */
final class InitalkAdminController
{
    public function __construct(private App $app)
    {
    }

    private function guard(ServerRequestInterface $request): void
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
    }

    private function actor(): string { return (string) ($this->app->guestAcl()->identity()->displayName() ?? '관리자'); }
    private function actorId(): int { return (int) ($this->app->guestAcl()->identity()->sub() ?? 0); }

    private function input(ServerRequestInterface $request): array
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        return is_array($input) ? $input : [];
    }

    private function environment(array $input): string
    {
        return in_array($input['environment'] ?? '', ['test', 'live'], true) ? $input['environment'] : $this->app->initalk()->settings->read()['environment'];
    }

    private function render(ServerRequestInterface $request, ResponseInterface $response, string $template, array $data): ResponseInterface
    {
        $time = static fn ($timestamp): string => $timestamp === null || (int) $timestamp === 0 ? ''
            : (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i');
        return View::fromRequest($request)->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            'admin/initalk/' . $template, $data + ['config' => $this->app->initalk()->settings->read(), 'status_labels' => Status::LABELS, 'time' => $time,
                'errors' => [], 'notice' => '', 'query' => $request->getQueryParams()]);
    }

    private function redirect(ServerRequestInterface $request, ResponseInterface $response, string $route, array $params = [], array $query = []): ResponseInterface
    {
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor($route, $params, $query);
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $url);
    }

    private function errors(DomainError $e): array
    {
        return $e->status() >= 500 ? ['작업을 완료하지 못했습니다. 결제사·알림톡 연결과 설정을 확인해 주세요.'] : array_values($e->details() ?: [$e->getMessage()]);
    }

    private function payUrl(array $request): string
    {
        return rtrim((string) $this->app->config('app.url', GNUCMS_URL), '/') . '/pay/' . $request['url_token'];
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        return $this->redirect($request, $response, 'admin.initalk.requests');
    }

    public function requests(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $service = $this->app->initalk();
        $service->requests->expire();
        $filter = $request->getQueryParams();
        $filter['environment'] = $this->environment($filter);
        // 검색창은 숫자만 다시 보여준다(_phone_input.php가 화면에서 하이픈을 붙인다) — 방금 입력한 하이픈 섞인 값을 그대로 되돌리지 않는다.
        if (is_string($filter['phone'] ?? null)) $filter['phone'] = preg_replace('/\D/', '', $filter['phone']);
        if (in_array($filter['months'] ?? '', ['1', '2', '3'], true)) {
            $today = (new \DateTimeImmutable('@' . \GnuCms\Support\Clock::timestamp()))->setTimezone(new \DateTimeZone('Asia/Seoul'));
            $filter['until'] = $today->format('Y-m-d');
            $filter['from'] = $today->modify('-' . $filter['months'] . ' months')->format('Y-m-d');
        }
        $result = $service->requests->search($filter, max(1, (int) ($filter['page'] ?? 1)));
        return $this->render($request, $response, 'requests', ['filter' => $filter, 'result' => $result, 'counts' => $service->requests->counts($filter['environment'])]);
    }

    public function bulk(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $environment = $this->environment($input);
        $ids = is_array($input['ids'] ?? null) ? array_values(array_filter($input['ids'], static fn ($id): bool => is_string($id) && preg_match('/^[a-f0-9]{32}$/D', $id) === 1)) : [];
        if ($ids === []) return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'none' => '1']);
        $service = $this->app->initalk();
        $action = $input['action'] ?? '';
        if ($action === 'send') {
            $summary = ExecutionLock::run($this->app->storageDir(), fn (): array => $service->notifier->sendMany($ids, $this->actor()));
            return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'sent' => $summary['sent'], 'failed' => $summary['failed']]);
        }
        if ($action === 'cancel') {
            $done = 0;
            $failed = 0;
            foreach ($ids as $id) {
                try { $service->requests->cancel($id, $this->actor()); $done++; } catch (DomainError $e) { $failed++; }
            }
            return $this->redirect($request, $response, 'admin.initalk.requests', [], ['environment' => $environment, 'cancelled' => $done, 'failed' => $failed]);
        }
        throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
    }

    public function newForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        return $this->render($request, $response, 'request_new', ['values' => []]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $service = $this->app->initalk();
        $config = $service->settings->read();
        try {
            $created = $service->requests->create($input, $config['environment'], $config['expiry_hours'], $this->actorId(), $this->actor());
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status()), 'request_new', ['values' => $input, 'errors' => $this->errors($e)]);
        }
        $query = ['created' => '1'];
        if (($input['send_now'] ?? '') === '1') {
            try {
                $result = ExecutionLock::run($this->app->storageDir(), fn (): array => $service->notifier->send($created['id'], $this->actor()));
                $query['sent'] = $result['submission'] === 'accepted' ? '1' : '0';
            } catch (DomainError $e) {
                $query['send_error'] = '1';
            }
        }
        return $this->redirect($request, $response, 'admin.initalk.request', ['id' => $created['id']], $query);
    }

    /** 휴대폰 번호의 결제 이력 요약(JSON). 번호 자체는 돌려주지 않는다. */
    public function customer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        $summary = ['count' => 0, 'total' => 0, 'last_paid_at' => ''];
        try {
            $found = $this->app->initalk()->requests->customerSummary(Phone::normalize($input['phone'] ?? null), $this->app->initalk()->settings->read()['environment']);
            $summary = ['count' => $found['count'], 'total' => $found['total'],
                'last_paid_at' => $found['last_paid_at'] === null ? '' : (new \DateTimeImmutable('@' . $found['last_paid_at']))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d')];
        } catch (DomainError $e) {
            // 형식이 틀린 번호는 이력 없음으로 답한다.
        }
        $response->getBody()->write(json_encode($summary, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'no-store')->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->guard($request);
        return $this->detail($request, $response, (string) $args['id'], []);
    }

    private function detail(ServerRequestInterface $request, ResponseInterface $response, string $id, array $errors): ResponseInterface
    {
        $service = $this->app->initalk();
        $found = $service->requests->find($id);
        if (Status::canPay($found['status']) && $found['expires_at'] <= \GnuCms\Support\Clock::timestamp()) {
            $service->requests->expireOne($id);
            $found = $service->requests->find($id);
        }
        return $this->render($request, $response, 'request', ['request' => $found, 'events' => $service->events->forRequest($id), 'ledger' => $service->ledger->forRequest($id),
            'refund_key' => bin2hex(random_bytes(16)), 'pay_url' => $this->payUrl($found), 'errors' => $errors]);
    }

    /** @param 'send'|'cancel'|'sync'|'refund' $action */
    public function act(string $action, ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->guard($request);
        $id = (string) $args['id'];
        $service = $this->app->initalk();
        $service->requests->find($id);
        $input = $this->input($request);
        try {
            $query = ExecutionLock::run($this->app->storageDir(), function () use ($service, $action, $id, $input): array {
                switch ($action) {
                    case 'send':
                        $result = $service->notifier->send($id, $this->actor());
                        return ['sent' => $result['submission'] === 'accepted' ? '1' : '0'];
                    case 'cancel':
                        $service->requests->cancel($id, $this->actor());
                        return ['cancelled' => '1'];
                    case 'sync':
                        $service->checkout->sync($id, $this->actor());
                        return ['synced' => '1'];
                    case 'refund':
                        $amount = preg_replace('/[^0-9]/', '', is_scalar($input['amount'] ?? null) ? (string) $input['amount'] : '');
                        $service->checkout->refund($id, $amount === '' ? 0 : (int) $amount, is_string($input['reason'] ?? null) ? $input['reason'] : '',
                            is_string($input['refund_key'] ?? null) ? $input['refund_key'] : '', $this->actor());
                        return ['refunded' => '1'];
                }
                throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
            });
        } catch (DomainError $e) {
            if ($e->status() === 404) throw $e;
            return $this->detail($request, $response->withStatus($e->status()), $id, $this->errors($e));
        }
        return $this->redirect($request, $response, 'admin.initalk.request', ['id' => $id], $query);
    }

    public function settingsForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $config = $this->app->initalk()->settings->read();
        return $this->render($request, $response, 'settings', ['values' => $this->settingsValues($config), 'templates' => $this->templateOptions()]);
    }

    public function saveSettings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $input = $this->input($request);
        try {
            $this->app->initalk()->settings->save($input);
        } catch (DomainError $e) {
            return $this->render($request, $response->withStatus($e->status()), 'settings', ['values' => $input, 'templates' => $this->templateOptions(), 'errors' => $this->errors($e)]);
        }
        return $this->redirect($request, $response, 'admin.initalk.settings', [], ['saved' => '1']);
    }

    public function purge(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->guard($request);
        $count = $this->app->initalk()->requests->purge();
        return $this->redirect($request, $response, 'admin.initalk.settings', [], ['purged' => (string) $count]);
    }

    private function settingsValues(array $config): array
    {
        return ['store_name' => $config['store_name'], 'support_phone' => $config['support_phone'], 'expiry_hours' => (string) $config['expiry_hours'],
            'environment' => $config['environment'], 'template_test' => $config['template']['test'], 'template_live' => $config['template']['live'],
            'settlement_days' => (string) $config['settlement_days']];
    }

    /** @return array{test:list<array>,live:list<array>} */
    private function templateOptions(): array
    {
        $options = ['test' => [], 'live' => []];
        foreach (['test', 'live'] as $environment) {
            try {
                foreach ($this->app->messaging()->templateAction('list', ['environment' => $environment]) as $template) {
                    if ($template['enabled']) $options[$environment][] = ['id' => $template['id'], 'name' => $template['name'], 'code' => $template['code']];
                }
            } catch (\Throwable $e) {
                // 알림톡 설정 전에는 목록이 비어 있다.
            }
        }
        return $options;
    }
}
