<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Data\Db\Connection;
use PDOException;
use RuntimeException;

/**
 * Persona: глобальные учётные записи, членство в проектах, заявки,
 * установки и персональные сборки.
 */
final class PersonaRepository
{
    public function __construct(private readonly Connection $conn)
    {
    }

    public function createAccount(string $email, ?string $passwordHash = null, string $status = 'pending'): int
    {
        try {
            $this->conn->pdo()->prepare(
                'INSERT INTO persona_accounts (email, password_hash, status) VALUES (:email, :hash, :status)',
            )->execute(['email' => $email, 'hash' => $passwordHash, 'status' => $status]);
        } catch (PDOException $e) {
            throw new RuntimeException('Аккаунт с таким email уже существует', 0, $e);
        }

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function accountByEmail(string $email): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM persona_accounts WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string, mixed>|array{} */
    public function accountById(int $id): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM persona_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function setAccountPassword(int $accountId, string $passwordHash): void
    {
        $this->conn->pdo()->prepare(
            'UPDATE persona_accounts SET password_hash = :hash, status = :status WHERE id = :id',
        )->execute(['hash' => $passwordHash, 'status' => 'active', 'id' => $accountId]);
    }

    public function createMember(
        int $accountId,
        string $project,
        string $alias = '',
        string $role = 'user',
        string $status = 'active',
    ): int {
        $existing = $this->member($accountId, $project, $alias);
        if ($existing !== []) {
            return (int) $existing['id'];
        }

        $this->conn->pdo()->prepare(
            'INSERT INTO project_members (account_id, project, alias, role, status)
             VALUES (:account, :project, :alias, :role, :status)',
        )->execute([
            'account' => $accountId,
            'project' => $project,
            'alias' => $alias,
            'role' => $role,
            'status' => $status,
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function member(int $accountId, string $project, string $alias = ''): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM project_members
             WHERE account_id = :account AND project = :project AND alias = :alias
             LIMIT 1',
        );
        $stmt->execute(['account' => $accountId, 'project' => $project, 'alias' => $alias]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /** @return list<array<string, mixed>> */
    public function membersFor(int $accountId): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM project_members WHERE account_id = :account ORDER BY project, alias',
        );
        $stmt->execute(['account' => $accountId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    public function setMemberStatus(int $memberId, string $status): void
    {
        $this->conn->pdo()->prepare('UPDATE project_members SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $memberId]);
    }

    public function createInvite(int $accountId, string $tokenHash, string $expiresAt): int
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO persona_invites (account_id, token_hash, expires_at)
             VALUES (:account, :token, :expires)',
        )->execute(['account' => $accountId, 'token' => $tokenHash, 'expires' => $expiresAt]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function inviteByToken(string $tokenHash): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM persona_invites WHERE token_hash = :token AND used_at IS NULL LIMIT 1',
        );
        $stmt->execute(['token' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function consumeInvite(int $inviteId): void
    {
        $this->conn->pdo()->prepare('UPDATE persona_invites SET used_at = :used WHERE id = :id')
            ->execute(['used' => gmdate('Y-m-d H:i:s'), 'id' => $inviteId]);
    }

    /** @return list<array<string, mixed>> */
    public function installationsFor(int $accountId): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM installations WHERE account_id = :account ORDER BY id DESC',
        );
        $stmt->execute(['account' => $accountId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    /**
     * @param list<string> $projects
     */
    public function createRequest(string $name, string $email, string $comment, array $projects = []): int
    {
        $this->conn->pdo()->prepare(
            'INSERT INTO registration_requests (name, email, comment, projects, status)
             VALUES (:name, :email, :comment, :projects, :status)',
        )->execute([
            'name' => $name,
            'email' => $email,
            'comment' => $comment,
            'projects' => json_encode($projects, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function pendingRequests(): array
    {
        $stmt = $this->conn->pdo()->prepare(
            "SELECT * FROM registration_requests WHERE status = 'pending' ORDER BY created_at, id",
        );
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($stmt->fetchAll() ?: []);

        return $rows;
    }

    /** @return array<string, mixed>|array{} */
    public function requestById(int $id): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM registration_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function decideRequest(int $id, string $status, string $decidedBy): void
    {
        $this->conn->pdo()->prepare(
            'UPDATE registration_requests SET status = :status, decided_at = :decided_at, decided_by = :by
             WHERE id = :id',
        )->execute([
            'status' => $status,
            'decided_at' => gmdate('Y-m-d H:i:s'),
            'by' => $decidedBy,
            'id' => $id,
        ]);
    }

    /**
     * @param list<string> $langs
     */
    public function createInstallation(
        int $accountId,
        string $domain,
        string $assembly,
        array $langs,
        string $tokenHash,
        string $status = 'pending',
    ): int {
        $this->conn->pdo()->prepare(
            'INSERT INTO installations (account_id, domain, assembly, langs, status, token_hash)
             VALUES (:account, :domain, :assembly, :langs, :status, :token)',
        )->execute([
            'account' => $accountId,
            'domain' => $domain,
            'assembly' => $assembly,
            'langs' => implode(',', $langs),
            'status' => $status,
            'token' => $tokenHash,
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function installationByToken(string $tokenHash): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM installations WHERE token_hash = :token LIMIT 1');
        $stmt->execute(['token' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    /** @return array<string, mixed>|array{} */
    public function installationById(int $id): array
    {
        $stmt = $this->conn->pdo()->prepare('SELECT * FROM installations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function markClaimed(int $installationId): void
    {
        $this->conn->pdo()->prepare(
            'UPDATE installations
             SET claimed_at = :claimed, install_count = install_count + 1, status = :status
             WHERE id = :id',
        )->execute([
            'claimed' => gmdate('Y-m-d H:i:s'),
            'status' => 'active',
            'id' => $installationId,
        ]);
    }

    public function setInstallationStatus(int $installationId, string $status): void
    {
        $this->conn->pdo()->prepare('UPDATE installations SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $installationId]);
    }

    public function createBuild(
        int $installationId,
        string $version,
        string $file,
        string $sha256,
        int $size,
        string $expiresAt,
    ): int {
        $this->conn->pdo()->prepare(
            'INSERT INTO builds (installation_id, version, file, sha256, size, expires_at)
             VALUES (:installation, :version, :file, :sha, :size, :expires)',
        )->execute([
            'installation' => $installationId,
            'version' => $version,
            'file' => $file,
            'sha' => $sha256,
            'size' => $size,
            'expires' => $expiresAt,
        ]);

        return (int) $this->conn->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|array{} */
    public function activeBuild(int $installationId): array
    {
        $stmt = $this->conn->pdo()->prepare(
            'SELECT * FROM builds WHERE installation_id = :installation AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['installation' => $installationId, 'now' => gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch();

        return $row === false ? [] : $row;
    }

    public function incrementDownload(int $buildId): void
    {
        $this->conn->pdo()->prepare('UPDATE builds SET download_count = download_count + 1 WHERE id = :id')
            ->execute(['id' => $buildId]);
    }
}
