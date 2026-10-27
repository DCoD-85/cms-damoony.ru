<?php

declare(strict_types=1);

namespace Damoony\Auth;

use Damoony\Db\Connection;
use Damoony\Session\SessionInterface;

final class DbAuth implements AuthInterface
{
    private const SESSION_KEY = 'user_id';

    public function __construct(
        private readonly Connection $connection,
        private readonly SessionInterface $session,
    ) {
    }

    public function isAuthenticated(): bool
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        return $this->session->get(self::SESSION_KEY) !== null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $stmt = $this->connection->pdo()->prepare(
            'SELECT id, login, email, status, created_at FROM users WHERE id = ? AND status = ?',
        );
        $stmt->execute([$this->session->get(self::SESSION_KEY), 'active']);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function login(string $login, string $password): bool
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT id, password_hash, status FROM users WHERE login = ?',
        );
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            return false;
        }
        if (($user['status'] ?? '') !== 'active') {
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, (int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        if ($this->session->isStarted()) {
            $this->session->remove(self::SESSION_KEY);
        }
    }
}
