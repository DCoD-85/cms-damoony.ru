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

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return ['login' => 'demo_user'];
    }

    public function login(string $login, string $password): bool
    {
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
