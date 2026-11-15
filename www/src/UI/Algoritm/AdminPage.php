<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Http\Response;
use Damoony\Data\Auth\AuthInterface;

abstract class AdminPage extends Page
{
    protected function guard(): ?Response
    {
        $auth = $this->context->service('auth');
        if (!$auth instanceof AuthInterface) {
            return Response::internalError('Auth service missing');
        }

        $user = $auth->user();
        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            return Response::redirect('/' . $this->context->domain->language . '/login/');
        }

        return null;
    }
}
