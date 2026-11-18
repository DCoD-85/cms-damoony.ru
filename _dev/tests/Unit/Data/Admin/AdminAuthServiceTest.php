<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Admin;

use Damoony\Data\Admin\AdminAuthRepository;
use Damoony\Data\Admin\AdminAuthService;
use Damoony\Data\Db\Connection;
use PHPUnit\Framework\TestCase;

final class AdminAuthServiceTest extends TestCase
{
    private Connection $conn;
    private AdminAuthRepository $repo;
    private AdminAuthService $service;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();
        $pdo->exec('CREATE TABLE admin_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, status TEXT NOT NULL DEFAULT "active", totp_secret TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, last_login_at TEXT)');
        $pdo->exec('CREATE TABLE admin_members (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, role TEXT NOT NULL, scope TEXT NOT NULL DEFAULT "", status TEXT NOT NULL DEFAULT "active", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (account_id, role, scope))');
        $pdo->exec('CREATE TABLE admin_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, role TEXT NOT NULL, scope TEXT NOT NULL DEFAULT "", ip TEXT NOT NULL DEFAULT "", user_agent TEXT NOT NULL DEFAULT "", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TEXT NOT NULL, revoked_at TEXT, last_seen_at TEXT)');
        $pdo->exec('CREATE TABLE admin_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, action TEXT NOT NULL, target TEXT NOT NULL DEFAULT "", ip TEXT NOT NULL DEFAULT "", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

        $this->repo = new AdminAuthRepository($this->conn);
        $this->service = new AdminAuthService($this->repo);
    }

    public function testLoginAndAuthenticate(): void
    {
        $accountId = $this->account('root', 'strong-pass-1');
        $this->repo->createMember($accountId, AdminAuthService::ROLE_SUPERADMIN);

        $login = $this->service->login('root', 'strong-pass-1', '203.0.113.10', 'UA');
        self::assertNotNull($login);
        self::assertSame(AdminAuthService::ROLE_SUPERADMIN, $login['role']);

        $session = $this->service->authenticate($login['token']);
        self::assertNotNull($session);
        self::assertSame($accountId, (int) $session['account']['id']);
        self::assertSame(AdminAuthService::ROLE_SUPERADMIN, $session['role']);
    }

    public function testWrongPasswordAndNoRole(): void
    {
        $accountId = $this->account('root', 'strong-pass-1');
        self::assertNull($this->service->login('root', 'wrong', '127.0.0.1', 'UA'));

        $this->repo->createMember($accountId, AdminAuthService::ROLE_DOMAINADMIN, 'docs.damoony.ru');
        $login = $this->service->login('root', 'strong-pass-1', '127.0.0.1', 'UA');
        self::assertNotNull($login);
        self::assertSame('docs.damoony.ru', $login['scope']);
    }

    public function testRevokeAndExpiry(): void
    {
        $accountId = $this->account('root', 'strong-pass-1');
        $this->repo->createMember($accountId, AdminAuthService::ROLE_SUPERADMIN);

        $login = $this->service->login('root', 'strong-pass-1', '127.0.0.1', 'UA');
        self::assertNotNull($login);

        $this->service->logout($login['token']);
        self::assertNull($this->service->authenticate($login['token']));
    }

    public function testRoleIsRevalidatedAgainstMembership(): void
    {
        $accountId = $this->account('root', 'strong-pass-1');
        $this->repo->createMember($accountId, AdminAuthService::ROLE_SUPERADMIN);
        $login = $this->service->login('root', 'strong-pass-1', '127.0.0.1', 'UA');
        self::assertNotNull($login);

        $this->conn->pdo()->exec('UPDATE admin_members SET status = "disabled"');

        self::assertNull($this->service->authenticate($login['token']));
    }

    public function testPasswordChangeRevokesSessions(): void
    {
        $accountId = $this->account('root', 'strong-pass-1');
        $this->repo->createMember($accountId, AdminAuthService::ROLE_SUPERADMIN);
        $login = $this->service->login('root', 'strong-pass-1', '127.0.0.1', 'UA');
        self::assertNotNull($login);

        $this->service->changePassword($accountId, 'new-strong-pass-2');
        self::assertNull($this->service->authenticate($login['token']));
    }

    private function account(string $login, string $password): int
    {
        return $this->repo->createAccount($login, password_hash($password, PASSWORD_DEFAULT));
    }
}
