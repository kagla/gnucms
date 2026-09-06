<?php

declare(strict_types=1);

namespace GnuCmsDemo\Plugins\Message;

use GnuCms\Error\DomainError;
use GnuCms\Validation\Validator;
use GnuCms\View\PhpView;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class PreviewController
{
    public function __construct(private MessageFormatter $formatter)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->render($request, $response, ['title' => '예약 안내', 'body' => '예약 내용을 확인해 주세요.']);
    }

    public function preview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        $values = [];
        foreach (['title', 'body'] as $field) {
            $values[$field] = is_string($input[$field] ?? null) ? $input[$field] : '';
        }
        try {
            $validator = new Validator($input);
            $title = $validator->requiredString('title', 60);
            $body = $validator->requiredString('body', 1000);
            $validator->check();
            return $this->render($request, $response, $values, [], $this->formatter->format($title, $body));
        } catch (DomainError $e) {
            if ($e->status() !== 422) {
                throw $e;
            }
            return $this->render($request, $response->withStatus(422), $values, $e->details());
        }
    }

    private function render(ServerRequestInterface $request, ResponseInterface $response, array $values, array $errors = [], ?string $result = null): ResponseInterface
    {
        $routes = RouteContext::fromRequest($request);
        // 패키지 템플릿만 사용한다. 테마나 코어 템플릿에 파일을 복사하지 않는다.
        $view = new PhpView(
            [dirname(__DIR__) . '/templates'],
            $routes->getRouteParser(),
            $routes->getBasePath(),
            static fn (string $path): string => '',
            static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        return $view->render($response->withHeader('Cache-Control', 'no-store'), 'preview', [
            'values' => $values, 'errors' => $errors, 'result' => $result,
            'csrf_token' => $_SESSION['csrf_token'],
            'form_url' => $routes->getBasePath() . '/plugins/demo-message/preview',
        ]);
    }
}
