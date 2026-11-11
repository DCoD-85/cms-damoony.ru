<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Http\Response;

/**
 * Base class for admin panel pages.
 *
 * Authentication is owned by the page (not the engine):
 * - Unauthenticated request -> 302 redirect to /admin/login
 * - Authenticated request -> renders content() as usual
 *
 * Login page must NOT extend AdminPage (would cause redirect loop).
 */
abstract class AdminPage extends Page
{
    protected function guard(): ?Response
    {
        if (!$this->isAdminAuthenticated()) {
            $adminConfig = $this->context->service('admin_config');
            $defaultLang = $adminConfig instanceof \Damoony\Core\Config
                ? (string) $adminConfig->getByKey(['default_lang'], '')
                : '';
            if ($defaultLang === '') {
                return Response::internalError('admin.config.default_lang not set');
            }

            return Response::redirect('/admin/' . $defaultLang . '/login', 302);
        }

        return null;
    }

    protected function isAdminAuthenticated(): bool
    {
        $auth = $this->context->service('auth');

        return $auth instanceof \Damoony\Admin\AuthInterface && $auth->isLoggedIn();
    }
}
