<?php

declare(strict_types=1);

namespace Damoony\Admin\Page;

use Damoony\Admin\Policy\AdminPolicy;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\Modules\Admin\AdminModule;
use Damoony\Modules\AuthAdmin\AdminAccess;
use Damoony\UI\Page\Page;

/**
 * Базовая страница админки: IP-политика + серверная сессия.
 * Роль берётся из сессии, не из URL.
 */
abstract class AdminPage extends Page
{
    private const CSRF_COOKIE = 'ADMIN_CSRF';

    /**
     * @return array{account: array<string, mixed>, role: string, scope: string, session_id: int}|null
     */
    protected function adminSession(): ?array
    {
        $access = $this->context->service('admin_access');
        $auth = $this->context->service('admin_auth');
        if (!$access instanceof AdminAccess || !$auth instanceof AdminAuthService) {
            return null;
        }
        if (!$access->allows($this->context->request)) {
            return null;
        }

        return $auth->authenticate((string) ($this->context->request->cookies[AdminModule::COOKIE] ?? ''));
    }

    /**
     * @return array{account: array<string, mixed>, role: string, scope: string, session_id: int}|null
     */
    protected function requireCapability(string $capability, string $domain = '', string $alias = ''): ?array
    {
        $session = $this->adminSession();
        if ($session === null) {
            return null;
        }

        return AdminPolicy::allows($session, $capability, $domain, $alias) ? $session : null;
    }

    protected function csrfToken(): string
    {
        $token = (string) ($this->context->request->cookies[self::CSRF_COOKIE] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            setcookie(self::CSRF_COOKIE, $token, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $token;
    }

    protected function csrfValid(): bool
    {
        $token = (string) ($this->context->request->cookies[self::CSRF_COOKIE] ?? '');
        $posted = (string) $this->context->request->post('_csrf', '');

        return $token !== '' && hash_equals($token, $posted);
    }
}
