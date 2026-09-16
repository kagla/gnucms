<?php

declare(strict_types=1);

namespace GnuCms\Web\Controller;

use GnuCms\App;
use GnuCms\Error\DomainError;
use GnuCms\Messaging\Input;
use GnuCms\Support\Clock;
use GnuCms\View\View;
use GnuCms\Web\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Throwable;

/** 운영 → 메시지 발송. 알림톡(templates/send/history/detail)과 문자(sms-send/sms-history/sms-detail) 화면. */
final class MessagingController
{
    public const STATUS_LABELS = ['prepared' => '준비', 'sending' => '접수 확인 중', 'accepted' => '접수됨', 'rejected' => '접수 거절',
        'unknown' => '접수 불명확', 'pending' => '결과 대기', 'delivered' => '도달 성공', 'failed' => '도달 실패', 'uncertain' => '도달 불확실'];

    public function __construct(private App $app)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        $url = RouteContext::fromRequest($request)->getRouteParser()->urlFor('admin.messaging.templates');
        return $response->withStatus(303)->withHeader('Location', $url);
    }

    /** @param string $page templates|send|history|detail|sms-send|sms-history|sms-detail */
    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $this->app->guestAcl()->assertGlobalAdmin();
        if ($request->getMethod() === 'POST') Csrf::assert($request);
        $service = $this->app->messaging();
        $text = str_starts_with($page, 'sms-');
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        if (isset($args['id'])) $input['id'] = $args['id'];
        $environment = $input['environment'] ?? 'test';
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '환경을 확인해 주세요.']);
        $input['environment'] = $environment;
        $routes = RouteContext::fromRequest($request)->getRouteParser();
        $base = RouteContext::fromRequest($request)->getBasePath();
        $data = ['page' => $page, 'base' => $base, 'environment' => $environment, 'ready' => true,
            'errors' => [], 'notice' => '', 'templates' => [], 'selected' => null, 'preview' => null,
            'confirmation' => null, 'detail' => null, 'remote_list' => null, 'remote_detail' => null, 'import_summary' => null, 'sample_values' => [],
            'history' => ['items' => [], 'page' => 1, 'total' => 0],
            'account_status' => ['configured' => false, 'enabled' => false, 'api_verified' => false, 'account_type' => 'module'],
            'values' => $input, 'csrf_token' => $_SESSION['csrf_token'] ?? '', 'status_labels' => self::STATUS_LABELS,
            'time' => static fn ($timestamp): string => (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i:s')];
        try {
            $data['account_status'] = $service->status($environment);
            if (!$text) {
                $data['templates'] = $service->templateAction('list', ['environment' => $environment]);
                $selectedId = $input[$page === 'templates' ? 'id' : 'template_id'] ?? '';
                if (is_string($selectedId) && $selectedId !== '') {
                    $selected = $service->templateAction('get', ['id' => $selectedId]);
                    if ($selected['environment'] !== $environment) throw DomainError::notFound('이 환경의 템플릿이 아닙니다.');
                    $data['selected'] = $selected;
                    if ($page === 'templates' && $selected['enabled']) {
                        // 보기 모달의 발송해 보기 폼에 채울 예시 값. 도메인 자리 변수에는 사이트 호스트를 넣는다.
                        $host = parse_url((string) $this->app->config('site.url', ''), PHP_URL_HOST) ?: $request->getUri()->getHost();
                        $data['sample_values'] = \GnuCms\Messaging\Templates::sampleValues($selected, (string) ($this->app->cms()->settings()['site_name'] ?? 'GNUCMS'), (string) $host);
                    }
                }
            }
            if ($request->getMethod() === 'POST') {
                $action = $input['action'] ?? '';
                if ($page === 'templates' && in_array($action, ['remote-list', 'remote-detail', 'remote-import', 'remote-import-all'], true)) {
                    $result = $service->templateAction($action, $input);
                    if ($action === 'remote-import') return $this->redirect($response, $routes->urlFor('admin.messaging.templates') . '?environment=' . $environment . '&id=' . $result['id']);
                    if ($action === 'remote-import-all') {
                        $data['import_summary'] = $result;
                        $data['notice'] = sprintf('가져옴 %d · 갱신 %d · 사용 중지 %d · 건너뜀 %d (비즈뿌리오 템플릿 %d개 확인)',
                            $result['imported'], $result['refreshed'], count($result['disabled']), count($result['skipped']), $result['total']);
                        $data['templates'] = $service->templateAction('list', ['environment' => $environment]);
                        $data['remote_list'] = $service->templateAction('remote-list', ['environment' => $environment]);
                    } else {
                        $data[$action === 'remote-list' ? 'remote_list' : 'remote_detail'] = $result;
                    }
                } elseif ($page === 'templates' && $action === 'enable') {
                    $saved = $service->templateAction('enable', $input);
                    return $this->redirect($response, $routes->urlFor('admin.messaging.templates') . '?environment=' . $environment . '&id=' . $saved['id']);
                } elseif ($page === 'templates' && $action === 'delete') {
                    // 이니톡 결제가 알림톡 템플릿으로 지정한 사본은 결제 알림이 끊기므로 먼저 바꾸게 한다.
                    if (in_array($this->string($input, 'id'), $this->app->initalk()->settings->read()['template'], true)) {
                        throw DomainError::validation(['template' => '이니톡 결제 알림톡 템플릿으로 지정된 템플릿입니다. 이니톡 결제 설정에서 다른 템플릿을 고른 뒤 삭제해 주세요.']);
                    }
                    $service->templateAction('delete', $input);
                    return $this->redirect($response, $routes->urlFor('admin.messaging.templates') . '?environment=' . $environment);
                } elseif (in_array($page, ['send', 'templates'], true) && $action === 'preview') {
                    $preview = $service->preview($input);
                    if ($preview['environment'] !== $environment) throw DomainError::validation(['environment' => '선택한 환경의 템플릿을 사용해 주세요.']);
                    $phone = $this->string($input, 'phone');
                    if (!preg_match('/^(?:010\d{8}|01[16789]\d{7,8})$/D', str_replace(['-', ' '], '', $phone))) throw DomainError::validation(['phone' => '국내 휴대폰 번호를 입력해 주세요.']);
                    $token = $this->remember('alimtalk_previews', ['environment' => $environment, 'template_id' => $preview['template_id'], 'revision' => $preview['revision'],
                        'config_revision' => $preview['config_revision'], 'phone' => $phone, 'variables' => $input['variables'] ?? []]);
                    $data['preview'] = $preview;
                    $data['confirmation'] = $token;
                } elseif ($page === 'sms-send' && $action === 'preview') {
                    $preview = $service->previewText($input);
                    $token = $this->remember('sms_previews', ['environment' => $environment, 'type' => $preview['type'], 'phone' => $preview['phone'],
                        'subject' => $preview['subject'], 'message' => $preview['message'], 'config_revision' => $preview['config_revision']]);
                    $data['preview'] = $preview;
                    $data['confirmation'] = $token;
                } elseif (in_array($page, ['send', 'sms-send', 'templates'], true) && $action === 'send') {
                    $pending = $this->confirmed($text ? 'sms_previews' : 'alimtalk_previews', $this->string($input, 'confirmation'), $environment);
                    $sent = $text ? $service->sendText($pending) : $service->send($pending);
                    return $this->redirect($response, $routes->urlFor($text ? 'admin.messaging.sms.detail' : 'admin.messaging.detail', ['id' => $sent['id']]) . '?environment=' . $environment);
                } elseif (in_array($page, ['detail', 'sms-detail'], true) && in_array($action, ['retry', 'refresh-result'], true)) {
                    $id = $this->string($input, 'id');
                    $this->detail($service, $id, $environment, $text);
                    if ($action === 'retry') { $text ? $service->retryText($id) : $service->retry($id); }
                    else { $text ? $service->refreshText($id) : $service->refresh($id); }
                    $data['notice'] = $action === 'retry' ? '재시도 결과를 확인해 주세요.' : '결과 재요청을 접수했습니다. 웹훅 수신 후 상태가 갱신됩니다.';
                } elseif (in_array($page, ['history', 'sms-history'], true) && $action === 'purge') {
                    $count = $text ? $service->purgeText() : $service->purge();
                    $data['notice'] = '90일이 지난 ' . ($text ? '문자' : '발송') . ' ' . $count . '건의 수신정보·내용을 삭제했습니다.';
                } else {
                    throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                }
            }
            if (in_array($page, ['history', 'sms-history'], true)) $data['history'] = $text ? $service->textHistory($input) : $service->history($input);
            if (in_array($page, ['detail', 'sms-detail'], true)) $data['detail'] = $this->detail($service, $this->string($input, 'id'), $environment, $text);
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->code() === 'BIZPPURIO_KAPI' ? [$e->getMessage()]
                : ($e->status() >= 500 ? ['작업을 완료하지 못했습니다. 알림톡·문자 설정과 서버 연결을 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]));
        } catch (Throwable $e) {
            $response = $response->withStatus(503);
            $data['errors'] = ['작업을 완료하지 못했습니다. 알림톡·문자 설정과 서버 연결을 확인해 주세요.'];
        }
        return View::fromRequest($request)->render(
            $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'),
            $text ? 'admin/messaging/sms_page' : 'admin/messaging/page', $data
        );
    }

    private function detail(\GnuCms\Messaging\MessagingService $service, string $id, string $environment, bool $text): array
    {
        $detail = $text ? $service->textDetail($id) : $service->detail($id);
        if ($detail['environment'] !== $environment) throw DomainError::notFound('이 환경의 발송 이력이 아닙니다.');
        return $detail;
    }

    /** 미리보기 확인값을 세션에 10분 보관한다(최근 10개). 발송은 이 값만 신뢰한다. */
    private function remember(string $bucket, array $input): string
    {
        $token = bin2hex(random_bytes(16));
        $pending = is_array($_SESSION[$bucket] ?? null) ? $_SESSION[$bucket] : [];
        foreach ($pending as $key => $entry) if (($entry['expires'] ?? 0) < Clock::timestamp()) unset($pending[$key]);
        if (count($pending) >= 10) array_shift($pending);
        $pending[$token] = ['expires' => Clock::timestamp() + 600, 'input' => $input + ['idempotency_key' => $token]];
        $_SESSION[$bucket] = $pending;
        return $token;
    }

    private function confirmed(string $bucket, string $token, string $environment): array
    {
        $pending = $_SESSION[$bucket][$token] ?? null;
        if (!is_array($pending) || $pending['expires'] < Clock::timestamp() || $pending['input']['environment'] !== $environment) {
            throw DomainError::validation(['preview' => '미리보기가 만료되었거나 환경이 다릅니다. 내용을 다시 확인해 주세요.']);
        }
        return $pending['input'];
    }

    private function string(array $input, string $key): string
    {
        if (!is_string($input[$key] ?? null)) throw DomainError::validation([$key => '입력값을 확인해 주세요.']);
        return $input[$key];
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withStatus(303)->withHeader('Cache-Control', 'no-store')->withHeader('Location', $url);
    }
}
