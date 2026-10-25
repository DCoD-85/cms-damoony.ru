<?php

declare(strict_types=1);

namespace Damoony\Session;

final class NativeSession implements SessionInterface
{
    public function __construct(
        private readonly string $name = 'DSESSID',
        private readonly bool $secure = true,
        private readonly string $sameSite = 'Lax',
    ) {
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => $this->sameSite,
        ]);

        if (!@session_start()) {
            throw new \RuntimeException('Failed to start session');
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if ($this->isStarted()) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: '', '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}