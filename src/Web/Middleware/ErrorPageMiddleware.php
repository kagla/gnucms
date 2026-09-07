<?php

declare(strict_types=1);

namespace GnuCms\Web\Middleware;

use GnuCms\Error\DomainError;
use GnuCms\View\ViewInterface;
use GnuCms\Web\LoginRedirect;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;
use Slim\Interfaces\RouteParserInterface;
use Throwable;

/**
 * 도메인 오류는 그대로 화면으로 옮기고, 그 밖의 예외는 500 으로 뭉갠 뒤 원문을
 * 로그에만 남긴다. 로그에 남기지 않으면 SQL 원문 같은 유일한 단서가 아무 데도
 * 남지 않고 사라진다.
 *
 * 스택의 가장 바깥은 아니다. HtmlContentTypeMiddleware 가 이 미들웨어가 만든
 * 응답까지 감싸야 해서 그보다 바깥(=더 나중에 add)에 등록되어 있다.
 *
 * 라우팅이 실패(404)하면 ViewMiddleware 가 한 번도 실행되지 않으므로 여기서는 요청
 * 속성 대신 생성자로 받은 View 로 직접 그린다. PhpView 는 주소를 만들 때 요청이
 * 필요 없어(RouteParser 가 기준 경로까지 붙인다) 그래도 안전하다.
 */
final class ErrorPageMiddleware implements MiddlewareInterface
{
    /** @var ViewInterface */
    private $view;

    /** @var bool */
    private $debug;

    /** @var string|null */
    private $logFile;

    public function __construct(
        ViewInterface $view,
        bool $debug,
        ?string $logFile,
        private RouteParserInterface $routeParser
    ) {
        $this->view = $view;
        $this->debug = $debug;
        $this->logFile = $logFile === '' ? null : $logFile;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            return $this->respond($request, 404, '찾을 수 없습니다.', '요청하신 주소에 해당하는 것이 없습니다.');
        } catch (HttpException $e) {
            // 404 는 위에서 먼저 잡힌다. 여기는 405(허용되지 않은 메서드) 같은 나머지
            // Slim 라우팅 예외다. 이름으로 잡지 않으면 Throwable 로 떨어져 500 이 된다.
            $status = $e->getCode() > 0 ? $e->getCode() : 500;

            return $this->respond($request, $status, $this->titleFor($status), '요청을 처리할 수 없습니다.');
        } catch (DomainError $e) {
            if ($e->code() === 'INTERNAL') {
                $this->log($e);

                return $this->respond($request, 500, '오류가 발생했습니다.', $this->safeMessage($e));
            }

            $response = $this->respond(
                $request, $e->status(), $this->titleFor($e->status()), $e->getMessage(), $e->details()
            );

            return $e->retryAfter() === null
                ? $response
                : $response->withHeader('Retry-After', (string) $e->retryAfter());
        } catch (Throwable $e) {
            $this->log($e);

            return $this->respond($request, 500, '오류가 발생했습니다.', $this->safeMessage($e));
        }
    }

    /** 편집기 같은 JSON 요청에는 HTML 오류 페이지 대신 해석 가능한 오류를 돌려준다. */
    private function respond(
        ServerRequestInterface $request,
        int $status,
        string $title,
        string $message,
        array $details = []
    ): ResponseInterface {
        if (stripos($request->getHeaderLine('Accept'), 'application/json') === false) {
            if ($status === 401) {
                // POST 작업은 로그인 뒤 GET으로 재실행하지 않는다.
                $url = in_array($request->getMethod(), ['GET', 'HEAD'], true)
                    ? $request->getUri()->getPath()
                        . ($request->getUri()->getQuery() === '' ? '' : '?' . $request->getUri()->getQuery())
                    : null;
                return (new Response())->withStatus(303)->withHeader('Cache-Control', 'no-store')
                    ->withHeader('Location', LoginRedirect::loginUrl($this->routeParser, $url));
            }
            return $this->render($status, $title, $message, $details);
        }

        $detail = reset($details);
        $exactMessage = is_scalar($detail) && (string) $detail !== '' ? (string) $detail : $message;
        $response = (new Response())->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write((string) json_encode([
            'error' => ['message' => $exactMessage, 'status' => $status],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response;
    }

    private function titleFor(int $status): string
    {
        switch ($status) {
            case 401:
                return '로그인이 필요합니다.';
            case 403:
                return '권한이 없습니다.';
            case 404:
                return '찾을 수 없습니다.';
            case 405:
                return '허용되지 않은 방식입니다.';
            case 422:
                return '입력값을 확인해 주세요.';
            case 429:
                return '잠시 쉬어 주세요.';
            default:
                return '요청을 처리할 수 없습니다.';
        }
    }

    private function safeMessage(Throwable $e): string
    {
        return $this->debug
            ? get_class($e) . ': ' . $e->getMessage()
            : '잠시 후 다시 시도해 주세요. 문제가 계속되면 관리자에게 알려 주세요.';
    }

    private function log(Throwable $e): void
    {
        if ($this->logFile === null) {
            return;
        }

        @error_log(
            '[' . gmdate('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
            3,
            $this->logFile
        );
    }

    private function render(int $status, string $title, string $message, array $details = []): ResponseInterface
    {
        $response = (new Response())->withStatus($status);
        return $this->view->render($response, 'error', [
            'title'   => $title,
            'message' => $message,
            'details' => $details,
        ]);
    }
}
