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

    public function setTotp(int $accountId, ?string $secret, bool $enabled): void
    {
        $this->conn->pdo()->prepare(
            'UPDATE admin_accounts SET totp_secret = :secret, totp_enabled = :enabled WHERE id = :id',
        )->execute(['secret' => $secret, 'enabled' => $enabled ? 1 : 0, 'id' => $accountId]);
    }

    public function createChallenge(int $accountId, string $tokenHash, string $expiresAt): void
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO admin_2fa_challenges (account_id, token_hash, expires_at) VALUES (:account, :token, :expires)',
        )->execute(['account' => $accountId, 'token' => $tokenHash, 'expires' => $expiresAt]);
    }

    /** @return array<string, mixed>|array{} */
    public function challengeByToken(string $tokenHash): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM admin_2fa_challenges WHERE token_hash = :token LIMIT 1');
        $stmt->execute(['token' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function deleteChallenge(int $id): void
    {
        $this->conn->pdo()->prepare('DELETE FROM admin_2fa_challenges WHERE id = :id')->execute(['id' => $id]);
    }

    public function incrementChallenge(int $id): void
    {
        $this->conn->pdo()->prepare('UPDATE admin_2fa_challenges SET attempts = attempts + 1 WHERE id = :id')
            ->execute(['id' => $id]);
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

    /** @return list<array<string, mixed>> */
    public function auditLog(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT a.*, ac.login
             FROM admin_audit a
             LEFT JOIN admin_accounts ac ON ac.id = a.account_id
             ORDER BY a.id DESC
             LIMIT :limit OFFSET :offset',
        );
        $stmt->bindValue('limit', max(1, min(500, $limit)), \PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    public function auditCount(): int
    {
        $stmt = $this->conn->pdo()->query('SELECT COUNT(*) FROM admin_audit');
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
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
