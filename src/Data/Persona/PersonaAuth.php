<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Core\Session\SessionInterface;

/**
 * Сессия persona: вход по глобальной учётной записи (email + пароль).
 */
final class PersonaAuth
{
    private const KEY = 'persona_id';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly PersonaRepository $repo,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = (int) $this->session->get(self::KEY, 0);
        if ($id <= 0) {
            return null;
        }

        $account = $this->repo->accountById($id);
        if ($account === [] || ($account['status'] ?? '') !== 'active') {
            $this->logout();

            return null;
        }

        return $account;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function login(string $email, string $password): bool
    {
        $account = $this->repo->accountByEmail(strtolower(trim($email)));
        if ($account === [] || ($account['status'] ?? '') !== 'active') {
            return false;
        }

        $hash = (string) ($account['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set(self::KEY, (int) $account['id']);

        return true;
    }

    public function logout(): void
    {
        $this->session->remove(self::KEY);
    }

    /** @return array<string, mixed>|array{} */
    public function member(int $accountId, string $project, string $alias = ''): array
    {
        return $this->repo->member($accountId, $project, $alias);
    }

    public function isAdmin(int $accountId, string $project): bool
    {
        $member = $this->repo->member($accountId, $project, '');

        return $member !== [] && ($member['status'] ?? '') === 'active' && ($member['role'] ?? '') === 'admin';
    }
}
