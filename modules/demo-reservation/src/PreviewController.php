<?php

declare(strict_types=1);

namespace GnuCmsDemo\Modules\Reservation;

use GnuCms\Error\DomainError;
use GnuCms\Extension\Context;
use GnuCms\View\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

final class PreviewController
{
    public function __construct(private ReservationPreview $service)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->render($request, $response, ['name' => '', 'date' => '', 'time' => '14:00', 'guests' => '2']);
    }

    public function preview(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        $values = [];
        foreach (['name', 'date', 'time', 'guests'] as $field) {
            $values[$field] = is_string($input[$field] ?? null) ? $input[$field] : '';
        }
        try {
            return $this->render($request, $response, $values, [], $this->service->generate($input));
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
        $view = View::forExtension($request, 'demo-reservation', dirname(__DIR__) . '/templates');
        return $view->render($response->withHeader('Cache-Control', 'no-store'), 'preview', [
            'values' => $values, 'errors' => $errors, 'result' => $result,
            'uses_plugin' => $this->service->usesPlugin(),
            'is_admin_test' => $request->getAttribute(Context::TEST_ATTRIBUTE) === true,
            'csrf_token' => $_SESSION['csrf_token'],
            'form_url' => $request->getAttribute(Context::TEST_ATTRIBUTE) === true
                ? $routes->getRouteParser()->urlFor('admin.modules.test', ['id' => 'demo-reservation'])
                : $routes->getBasePath() . '/modules/demo-reservation/preview',
        ]);
    }
}
