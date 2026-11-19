<?php

declare(strict_types=1);

namespace Damoony\Data\Admin;

use RuntimeException;

/**
 * Аутентификация админки: логин, серверная сессия, роль из БД.
 * Роль никогда не берётся из URL/cookie.
 */
final class AdminAuthService
{
    public const SESSION_TTL = 8 * 3600;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_DOMAINADMIN = 'domainadmin';

    public function __construct(private readonly AdminAuthRepository $repo)
    {
    }

    /**
     * @return array{token: string, account: array<string, mixed>, role: string, scope: string}|null
     */
    public function login(string $login, string $password, string $ip, string $userAgent): ?array
    {
        $account = $this->repo->accountByLogin(trim($login));
        if ($account === [] || ($account['status'] ?? '') !== 'active') {
            $this->repo->audit(null, 'login.failed', $login, $ip);

            return null;
        }

        $hash = (string) ($account['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            $this->repo->audit((int) $account['id'], 'login.failed', $login, $ip);

            return null;
        }

        $member = $this->pickRole((int) $account['id']);
        if ($member === null) {
            $this->repo->audit((int) $account['id'], 'login.no_role', $login, $ip);

            return null;
        }

        $role = (string) $member['role'];
        $scope = (string) $member['scope'];
        $token = bin2hex(random_bytes(32));

        $this->repo->createSession(
            (int) $account['id'],
            hash('sha256', $token),
            $role,
            $scope,
            $ip,
            mb_substr($userAgent, 0, 255),
            gmdate('Y-m-d H:i:s', time() + self::SESSION_TTL),
        );
        $this->repo->touchLogin((int) $account['id']);
        $this->repo->audit((int) $account['id'], 'login.ok', $role . ':' . $scope, $ip);

        return ['token' => $token, 'account' => $account, 'role' => $role, 'scope' => $scope];
    }

    /**
     * @return array{account: array<string, mixed>, role: string, scope: string, session_id: int}|null
     */
    public function authenticate(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $session = $this->repo->sessionByToken(hash('sha256', $token));
        if ($session === [] || ($session['revoked_at'] ?? null) !== null) {
            return null;
        }
        if (strtotime((string) $session['expires_at']) < time()) {
            return null;
        }

        $account = $this->repo->accountById((int) $session['account_id']);
        if ($account === [] || ($account['status'] ?? '') !== 'active') {
            return null;
        }

        $role = (string) $session['role'];
        $scope = (string) $session['scope'];
        $member = $this->repo->member((int) $account['id'], $role, $scope);
        if ($member === [] || ($member['status'] ?? '') !== 'active') {
            return null;
        }

        $this->repo->touchSession((int) $session['id']);

        return [
            'account' => $account,
            'role' => $role,
            'scope' => $scope,
            'session_id' => (int) $session['id'],
        ];
    }

    public function logout(string $token): void
    {
        if ($token === '') {
            return;
        }

        $session = $this->repo->sessionByToken(hash('sha256', $token));
        if ($session !== []) {
            $this->repo->revokeSession((int) $session['id']);
            $this->repo->audit((int) $session['account_id'], 'logout', '', '');
        }
    }

    public function changePassword(int $accountId, string $password): void
    {
        if (strlen($password) < 10) {
            throw new RuntimeException('Пароль минимум 10 символов');
        }

        $this->repo->updatePassword($accountId, password_hash($password, PASSWORD_DEFAULT));
        $this->repo->revokeAllSessions($accountId);
        $this->repo->audit($accountId, 'password.changed', '', '');
    }

    public function changePasswordByLogin(string $login, string $password): void
    {
        $account = $this->repo->accountByLogin(trim($login));
        if ($account === []) {
            throw new RuntimeException('Админ не найден: ' . $login);
        }

        $this->changePassword((int) $account['id'], $password);
    }

    public function recordAudit(int $accountId, string $action, string $target, string $ip = ''): void
    {
        $this->repo->audit($accountId, $action, $target, $ip);
    }

    /** @return list<array<string, mixed>> */
    public function accounts(): array
    {
        return $this->repo->accounts();
    }

    /** @return list<array<string, mixed>> */
    public function membersFor(int $accountId): array
    {
        return $this->repo->membersFor($accountId);
    }

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50, int $offset = 0): array
    {
        return $this->repo->auditLog($limit, $offset);
    }

    public function auditCount(): int
    {
        return $this->repo->auditCount();
    }

    public function createAdmin(string $login, string $password, string $role, string $scope = ''): int
    {
        $login = trim($login);
        if (preg_match('/^[a-z0-9_.-]{3,64}$/', $login) !== 1) {
            throw new RuntimeException('Логин: 3–64 символа, латиница/цифры/._-');
        }
        if (strlen($password) < 10) {
            throw new RuntimeException('Пароль минимум 10 символов');
        }
        if (!in_array($role, [self::ROLE_SUPERADMIN, self::ROLE_DOMAINADMIN], true)) {
            throw new RuntimeException('Роль: superadmin или domainadmin');
        }
        if ($role === self::ROLE_DOMAINADMIN && trim($scope) === '') {
            throw new RuntimeException('Для domainadmin укажите домен');
        }
        if ($this->repo->accountByLogin($login) !== []) {
            throw new RuntimeException('Логин уже существует');
        }

        $accountId = $this->repo->createAccount($login, password_hash($password, PASSWORD_DEFAULT));
        $this->repo->createMember($accountId, $role, trim($scope));
        $this->repo->audit($accountId, 'account.created', $role . ':' . trim($scope), '');

        return $accountId;
    }

    /** @return array<string, mixed>|null */
    private function pickRole(int $accountId): ?array
    {
        $members = $this->repo->activeMembersFor($accountId);
        foreach ($members as $member) {
            if (($member['role'] ?? '') === self::ROLE_SUPERADMIN) {
                return $member;
            }
        }

        return $members[0] ?? null;
    }
}
