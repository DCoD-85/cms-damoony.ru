<?php

declare(strict_types=1);

namespace Damoony\Auth;

use Damoony\Session\SessionInterface;

final class SessionAuth implements AuthInterface
{
    private const SESSION_KEY = 'user_auth';

    public function __construct(
        private readonly SessionInterface $session,
    ) {
    }

    public function isAuthenticated(): bool
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        return (bool) $this->session->get(self::SESSION_KEY, false);
    }

    public function user(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return ['login' => 'demo_user']; // Пока демо
    }

    public function login(string $login, string $password): bool
    {
        // Пока простой хардкод для теста
        if ($login === 'user' && $password === 'secret') {
            if (!$this->session->isStarted()) {
                $this->session->start();
            }
            $this->session->regenerate();
            $this->session->set(self::SESSION_KEY, true);
            return true;
        }
        return false;
    }

    public function logout(): void
    {
        if ($this->session->isStarted()) {
            $this->session->remove(self::SESSION_KEY);
        }
    }
}