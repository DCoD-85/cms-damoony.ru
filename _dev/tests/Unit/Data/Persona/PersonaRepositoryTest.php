<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Persona;

use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\PersonaRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersonaRepositoryTest extends TestCase
{
    private PersonaRepository $repo;

    protected function setUp(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $conn->pdo();

        $pdo->exec('CREATE TABLE persona_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT DEFAULT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            email_verified_at TEXT DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        $pdo->exec('CREATE TABLE project_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            project TEXT NOT NULL,
            alias TEXT NOT NULL DEFAULT "",
            role TEXT NOT NULL DEFAULT "user",
            status TEXT NOT NULL DEFAULT "pending",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (account_id, project, alias)
        )');

        $pdo->exec('CREATE TABLE registration_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL,
            comment TEXT DEFAULT NULL,
            projects TEXT DEFAULT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at TEXT DEFAULT NULL,
            decided_by TEXT DEFAULT NULL
        )');

        $pdo->exec('CREATE TABLE installations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            account_id INTEGER NOT NULL,
            domain TEXT NOT NULL,
            assembly TEXT NOT NULL DEFAULT "minimal",
            langs TEXT NOT NULL DEFAULT "ru,en",
            aliases TEXT DEFAULT NULL,
            status TEXT NOT NULL DEFAULT "pending",
            token_hash TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claimed_at TEXT DEFAULT NULL,
            expires_at TEXT DEFAULT NULL,
            install_count INTEGER NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE builds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            installation_id INTEGER NOT NULL,
            version TEXT NOT NULL DEFAULT "",
            file TEXT NOT NULL,
            sha256 TEXT NOT NULL,
            size INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at TEXT NOT NULL,
            download_count INTEGER NOT NULL DEFAULT 0
        )');

        $this->repo = new PersonaRepository($conn);
    }

    public function testAccountLifecycle(): void
    {
        $id = $this->repo->createAccount('user@example.test');

        self::assertGreaterThan(0, $id);
        self::assertSame('pending', $this->repo->accountByEmail('user@example.test')['status']);

        $this->repo->setAccountPassword($id, 'hash');

        $account = $this->repo->accountById($id);
        self::assertSame('active', $account['status']);
        self::assertSame('hash', $account['password_hash']);
    }

    public function testDuplicateEmailRejected(): void
    {
        $this->repo->createAccount('dup@example.test');

        $this->expectException(RuntimeException::class);
        $this->repo->createAccount('dup@example.test');
    }

    public function testMemberIsIdempotentAndStatusChanges(): void
    {
        $account = $this->repo->createAccount('m@example.test');

        $first = $this->repo->createMember($account, 'fmanager');
        $second = $this->repo->createMember($account, 'fmanager');

        self::assertSame($first, $second);
        self::assertSame('active', $this->repo->member($account, 'fmanager')['status']);

        $this->repo->createMember($account, 'developer', '', 'user', 'pending');
        self::assertCount(2, $this->repo->membersFor($account));

        $member = $this->repo->member($account, 'developer');
        $this->repo->setMemberStatus((int) $member['id'], 'active');
        self::assertSame('active', $this->repo->member($account, 'developer')['status']);
    }

    public function testRequestLifecycle(): void
    {
        $id = $this->repo->createRequest('Иван', 'ivan@example.test', 'хочу доступ', ['fmanager']);

        $pending = $this->repo->pendingRequests();
        self::assertCount(1, $pending);
        self::assertSame('Иван', $pending[0]['name']);

        $this->repo->decideRequest($id, 'approved', 'admin');

        self::assertSame([], $this->repo->pendingRequests());
        self::assertSame('approved', $this->repo->requestById($id)['status']);
    }

    public function testInstallationAndBuildLifecycle(): void
    {
        $account = $this->repo->createAccount('dev@example.test');
        $hash = hash('sha256', 'token');

        $installation = $this->repo->createInstallation($account, 'site.ru', 'showcase', ['ru', 'en'], $hash);

        $found = $this->repo->installationByToken($hash);
        self::assertSame($installation, (int) $found['id']);
        self::assertSame('pending', $found['status']);

        $this->repo->markClaimed($installation);
        $claimed = $this->repo->installationById($installation);
        self::assertSame('active', $claimed['status']);
        self::assertSame(1, (int) $claimed['install_count']);
        self::assertNotSame('', (string) $claimed['claimed_at']);

        $build = $this->repo->createBuild(
            $installation,
            '1.0.0',
            'var/builds/1.zip',
            str_repeat('a', 64),
            1024,
            gmdate('Y-m-d H:i:s', time() + 3600),
        );

        self::assertSame($build, (int) $this->repo->activeBuild($installation)['id']);

        $this->repo->createBuild($installation, '1.0.0', 'var/builds/expired.zip', str_repeat('b', 64), 1, gmdate('Y-m-d H:i:s', time() - 60));
        self::assertSame($build, (int) $this->repo->activeBuild($installation)['id']);

        $this->repo->incrementDownload($build);
        self::assertSame(1, (int) $this->repo->activeBuild($installation)['download_count']);
    }
}
