<?php

declare(strict_types=1);

namespace Damoony\Admin\Controller;

use Damoony\Admin\AuthInterface;
use Damoony\Admin\View;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Security\Csrf;

abstract class BaseController
{
    public function __construct(
        protected readonly AuthInterface $auth,
        protected readonly Csrf $csrf,
        protected readonly View $view,
    ) {
    }

    public function requireAuth(): ?Response
    {
        if (!$this->auth->isLoggedIn()) {
            return Response::redirect('/admin/?p=login', 302);
        }
        return null;
    }

    public function requirePost(Request $request): ?Response
    {
        if (!$request->isPost()) {
            return Response::text('Method Not Allowed', 405);
        }
        return null;
    }

    public function requireCsrf(Request $request): ?Response
    {
        if (!$this->csrf->check($request->post)) {
            return Response::text('CSRF token invalid', 403);
        }
        return null;
    }

    public function redirect(string $url): Response
    {
        return Response::redirect($url, 302);
    }
}