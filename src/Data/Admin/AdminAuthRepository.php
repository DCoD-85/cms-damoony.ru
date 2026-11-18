<?php

declare(strict_types=1);

namespace Damoony\Data\Admin;

use Damoony\Data\Db\Connection;

/**
 * Хранилище админских учёток, ролей и сессий. Только admin_* таблицы.
 */
final class AdminAuthRepository
{
    public function __construct(private readonly Connection $conn)
    {
    }

    public function createAccount(string $login, string $passwordHash, string $status = 'active'): int
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO admin_accounts (login, password_hash, status)
             VALUES (:login, :hash, :status)',
        )->execute(['login' => $login, 'hash' => $passwordHash, 'status' => $status]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function accountById(int $id): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM admin_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string, mixed>|array{} */
    public function accountByLogin(string $login): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM admin_accounts WHERE login = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function touchLogin(int $accountId): void
    {
        $this->conn->pdo()->prepare('UPDATE admin_accounts SET last_login_at = :now WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $accountId]);
    }

    public function updatePassword(int $accountId, string $passwordHash): void
    {
        $this->conn->pdo()->prepare('UPDATE admin_accounts SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => $passwordHash, 'id' => $accountId]);
    }

    /** @return list<array<string, mixed>> */
    public function accounts(): array
    {
        $stmt = $this->conn->pdo()->query('SELECT * FROM admin_accounts ORDER BY login');
        if ($stmt === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function membersFor(int $accountId): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM admin_members WHERE account_id = :account ORDER BY id');
        $stmt->execute(['account' => $accountId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    public function createMember(int $accountId, string $role, string $scope = '', string $status = 'active'): int
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO admin_members (account_id, role, scope, status)
             VALUES (:account, :role, :scope, :status)',
        )->execute([
            'account' => $accountId,
            'role' => $role,
            'scope' => $scope,
            'status' => $status,
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function member(int $accountId, string $role, string $scope = ''): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM admin_members
             WHERE account_id = :account AND role = :role AND scope = :scope
             LIMIT 1',
        );
        $stmt->execute(['account' => $accountId, 'role' => $role, 'scope' => $scope]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /** @return list<array<string, mixed>> */
    public function activeMembersFor(int $accountId): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM admin_members
             WHERE account_id = :account AND status = :status
             ORDER BY id',
        );
        $stmt->execute(['account' => $accountId, 'status' => 'active']);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    public function createSession(
        int $accountId,
        string $tokenHash,
        string $role,
        string $scope,
        string $ip,
        string $userAgent,
        string $expiresAt,
    ): int {
        $this->conn->pdo()->prepare(
            'INSERT INTO admin_sessions
                (account_id, token_hash, role, scope, ip, user_agent, expires_at)
             VALUES (:account, :token, :role, :scope, :ip, :ua, :expires)',
        )->execute([
            'account' => $accountId,
            'token' => $tokenHash,
            'role' => $role,
            'scope' => $scope,
            'ip' => $ip,
            'ua' => $userAgent,
            'expires' => $expiresAt,
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function sessionByToken(string $tokenHash): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM admin_sessions WHERE token_hash = :token LIMIT 1',
        );
        $stmt->execute(['token' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function touchSession(int $sessionId): void
    {
        $this->conn->pdo()->prepare('UPDATE admin_sessions SET last_seen_at = :now WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $sessionId]);
    }

    public function revokeSession(int $sessionId): void
    {
        $this->conn->pdo()->prepare('UPDATE admin_sessions SET revoked_at = :now WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $sessionId]);
    }

    public function revokeAllSessions(int $accountId): void
    {
        $this->conn->pdo()->prepare(
            'UPDATE admin_sessions SET revoked_at = :now
             WHERE account_id = :account AND revoked_at IS NULL',
        )->execute(['now' => gmdate('Y-m-d H:i:s'), 'account' => $accountId]);
    }

    public function audit(?int $accountId, string $action, string $target, string $ip): void
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO admin_audit (account_id, action, target, ip)
             VALUES (:account, :action, :target, :ip)',
        )->execute([
            'account' => $accountId,
            'action' => $action,
            'target' => $target,
            'ip' => $ip,
        ]);
    }
}
