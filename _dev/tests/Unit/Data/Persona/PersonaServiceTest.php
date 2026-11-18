<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Persona;

use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\PersonaRepository;
use Damoony\Data\Persona\PersonaService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PersonaServiceTest extends TestCase
{
    private PersonaRepository $repo;
    private PersonaService $service;

    protected function setUp(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $conn->pdo();
        $pdo->exec('CREATE TABLE persona_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT, status TEXT NOT NULL DEFAULT "pending", email_verified_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE persona_invites (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE registration_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT "", email TEXT NOT NULL, comment TEXT, projects TEXT, status TEXT NOT NULL DEFAULT "pending", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, decided_at TEXT, decided_by TEXT)');

        $this->repo = new PersonaRepository($conn);
        $this->service = new PersonaService($this->repo);
    }

    public function testApproveWithInvite(): void
    {
        $id = $this->repo->createRequest('Иван', 'ivan@example.test', '', ['fmanager']);

        $result = $this->service->approveRequest($id, 'https://persona.test', 'cli');

        self::assertSame('ivan@example.test', $result['email']);
        self::assertNotNull($result['invite_url']);
        self::assertStringStartsWith('https://persona.test/invite/', (string) $result['invite_url']);
        self::assertSame('approved', $this->repo->requestById($id)['status']);
        self::assertSame('pending', $this->repo->accountByEmail('ivan@example.test')['status']);
    }

    public function testApproveWithPassword(): void
    {
        $id = $this->repo->createRequest('Пётр', 'petr@example.test', '', []);

        $result = $this->service->approveRequest($id, 'https://persona.test', 'admin', 'password123');

        self::assertNull($result['invite_url']);
        $account = $this->repo->accountByEmail('petr@example.test');
        self::assertSame('active', $account['status']);
        self::assertTrue(password_verify('password123', (string) $account['password_hash']));
    }

    public function testApproveTwiceFails(): void
    {
        $id = $this->repo->createRequest('A', 'a@example.test', '', []);
        $this->service->approveRequest($id, 'https://persona.test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('уже обработана');
        $this->service->approveRequest($id, 'https://persona.test');
    }

    public function testShortPasswordRejected(): void
    {
        $id = $this->repo->createRequest('B', 'b@example.test', '', []);

        $this->expectException(RuntimeException::class);
        $this->service->approveRequest($id, 'https://persona.test', 'admin', 'short');
    }
}
