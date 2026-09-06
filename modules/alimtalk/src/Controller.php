<?php

declare(strict_types=1);

namespace GnuCms\Modules\Alimtalk;

use GnuCms\Error\DomainError;
use GnuCms\Support\Clock;
use GnuCms\View\PhpView;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Routing\RouteContext;
use Throwable;

/** 운영 화면은 버전 있는 공개 Closure 계약만 사용한다. */
final class Controller
{
    public function __construct(private array $services)
    {
    }

    public function handle(string $page, ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getMethod() === 'POST' ? $request->getParsedBody() : $request->getQueryParams();
        $input = is_array($input) ? $input : [];
        $environment = $input['environment'] ?? 'test';
        if (!in_array($environment, ['test', 'live'], true)) throw DomainError::validation(['environment' => '환경을 확인해 주세요.']);
        $routes = RouteContext::fromRequest($request);
        $base = $routes->getBasePath();
        $ready = ($this->services['ready'])();
        $data = ['page' => $page, 'base' => $base, 'environment' => $environment, 'ready' => $ready,
            'errors' => [], 'notice' => '', 'templates' => [], 'selected' => null, 'preview' => null,
            'confirmation' => null, 'detail' => null, 'history' => ['items' => [], 'page' => 1, 'total' => 0],
            'account_status' => ['configured' => false, 'enabled' => false, 'api_verified' => false, 'account_type' => 'module'],
            'values' => $input, 'csrf_token' => $_SESSION['csrf_token'] ?? ''];
        try {
            if ($ready) {
                $data['account_status'] = ($this->services['status'])($environment);
                $data['templates'] = ($this->services['templates'])('list', ['environment' => $environment]);
                $selectedId = $input[$page === 'templates' ? 'id' : 'template_id'] ?? '';
                if (is_string($selectedId) && $selectedId !== '') {
                    $selected = ($this->services['templates'])('get', ['id' => $selectedId]);
                    if ($selected['environment'] !== $environment) throw DomainError::notFound('이 환경의 템플릿이 아닙니다.');
                    $data['selected'] = $selected;
                }
                if ($request->getMethod() === 'POST') {
                    $action = $input['action'] ?? '';
                    if ($page === 'templates' && $action === 'save') {
                        if (isset($input['buttons']) && is_array($input['buttons'])) {
                            $input['buttons'] = array_values(array_filter($input['buttons'], static fn ($row): bool => !is_array($row)
                                || ($row['name'] ?? '') !== '' || ($row['url_mobile'] ?? '') !== '' || ($row['url_pc'] ?? '') !== ''));
                        }
                        $saved = ($this->services['templates'])('save', $input);
                        return $response->withStatus(303)->withHeader('Location', $base . '/modules/alimtalk/templates?environment=' . $environment . '&id=' . $saved['id']);
                    } elseif ($page === 'send' && $action === 'preview') {
                        $preview = ($this->services['preview'])($input);
                        if ($preview['environment'] !== $environment) throw DomainError::validation(['environment' => '선택한 환경의 템플릿을 사용해 주세요.']);
                        $phone = $this->string($input, 'phone');
                        if (!preg_match('/^(?:010\d{8}|01[16789]\d{7,8})$/D', str_replace(['-', ' '], '', $phone))) throw DomainError::validation(['phone' => '국내 휴대폰 번호를 입력해 주세요.']);
                        $token = bin2hex(random_bytes(16));
                        $pending = is_array($_SESSION['alimtalk_previews'] ?? null) ? $_SESSION['alimtalk_previews'] : [];
                        foreach ($pending as $key => $entry) if (($entry['expires'] ?? 0) < Clock::timestamp()) unset($pending[$key]);
                        if (count($pending) >= 10) array_shift($pending);
                        $pending[$token] = ['expires' => Clock::timestamp() + 600, 'input' => [
                            'environment' => $environment, 'template_id' => $preview['template_id'], 'revision' => $preview['revision'],
                            'config_revision' => $preview['config_revision'], 'phone' => $phone,
                            'variables' => $input['variables'] ?? [], 'idempotency_key' => $token]];
                        $_SESSION['alimtalk_previews'] = $pending;
                        $data['preview'] = $preview;
                        $data['confirmation'] = $token;
                    } elseif ($page === 'send' && $action === 'send') {
                        $confirmation = $this->string($input, 'confirmation');
                        $pending = $_SESSION['alimtalk_previews'][$confirmation] ?? null;
                        if (!is_array($pending) || $pending['expires'] < Clock::timestamp() || $pending['input']['environment'] !== $environment) {
                            throw DomainError::validation(['preview' => '미리보기가 만료되었습니다. 내용을 다시 확인해 주세요.']);
                        }
                        $sent = ($this->services['send'])($pending['input']);
                        return $response->withStatus(303)->withHeader('Location', $base . '/modules/alimtalk/detail?environment=' . $environment . '&id=' . $sent['id']);
                    } elseif ($page === 'detail' && in_array($action, ['retry', 'refresh-result'], true)) {
                        $id = $this->string($input, 'id');
                        $detail = ($this->services['detail'])($id);
                        if ($detail['environment'] !== $environment) throw DomainError::notFound('이 환경의 발송 이력이 아닙니다.');
                        ($this->services[$action])($id);
                        $data['notice'] = $action === 'retry' ? '재시도 결과를 확인해 주세요.' : '결과 재요청을 접수했습니다. 웹훅 수신 후 상태가 갱신됩니다.';
                    } elseif ($page === 'history' && $action === 'purge') {
                        $count = ($this->services['purge'])();
                        $data['notice'] = '90일이 지난 발송 ' . $count . '건의 수신정보·내용을 삭제했습니다.';
                    } else throw DomainError::validation(['action' => '작업을 확인해 주세요.']);
                }
                if (in_array($page, ['home', 'history'], true)) $data['history'] = ($this->services['history'])($input + ['environment' => $environment]);
                if ($page === 'detail') {
                    $data['detail'] = ($this->services['detail'])($this->string($input, 'id'));
                    if ($data['detail']['environment'] !== $environment) throw DomainError::notFound('이 환경의 발송 이력이 아닙니다.');
                }
            }
        } catch (DomainError $e) {
            $response = $response->withStatus($e->status());
            $data['errors'] = $e->status() >= 500 ? ['작업을 완료하지 못했습니다. 플러그인 상태와 서버 연결을 확인해 주세요.'] : ($e->details() ?: [$e->getMessage()]);
        } catch (Throwable $e) {
            $response = $response->withStatus(503);
            $data['errors'] = ['작업을 완료하지 못했습니다. 플러그인 상태와 서버 연결을 확인해 주세요.'];
        }
        $data['status_labels'] = ['prepared' => '준비', 'sending' => '접수 확인 중', 'accepted' => '접수됨', 'rejected' => '접수 거절',
            'unknown' => '접수 불명확', 'pending' => '결과 대기', 'delivered' => '도달 성공', 'failed' => '도달 실패', 'uncertain' => '도달 불확실'];
        $data['time'] = static fn ($timestamp): string => (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(new \DateTimeZone('Asia/Seoul'))->format('Y-m-d H:i:s');
        $view = new PhpView([dirname(__DIR__) . '/templates'], $routes->getRouteParser(), $base,
            static fn (string $path): string => '', static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        return $view->render($response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer'), 'page', $data);
    }

    private function string(array $input, string $key): string
    {
        if (!is_string($input[$key] ?? null)) throw DomainError::validation([$key => '입력값을 확인해 주세요.']);
        return $input[$key];
    }
}
