<?php

declare(strict_types=1);

namespace Damoony\Admin;

use Damoony\Admin\Controller\BaseController;
use Damoony\Error\ErrorPage;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class AdminRouter
{
    /** @var array<string, BaseController> */
    private array $controllers = [];

    public function register(string $resource, BaseController $controller): self
    {
        $this->controllers[$resource] = $controller;
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $p = $request->query('p', '') ?? '';

        if ($p === '') {
            $p = 'dashboard';
        }

        $parts = explode(':', $p, 2);
        $resource = $parts[0];
        $action = $parts[1] ?? 'index';

        $controller = $this->controllers[$resource] ?? null;
        if ($controller === null) {
            return ErrorPage::notFound();
        }

        if (!method_exists($controller, $action)) {
            return ErrorPage::notFound();
        }

        /** @var Response $response */
        $response = $controller->{$action}($request);
        return $response;
    }
}
