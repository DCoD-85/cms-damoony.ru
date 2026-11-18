<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Data\Persona;

use Damoony\Core\Session\SessionInterface;
use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\PersonaBridge;
use Damoony\Data\Persona\PersonaRepository;
use PHPUnit\Framework\TestCase;

final class PersonaBridgeTest extends TestCase
{
    private Connection $conn;
    private PersonaRepository $repo;
    private InMemorySession $session;

    protected function setUp(): void
    {
        $this->conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $this->conn->pdo();

        $pdo->exec('CREATE TABLE persona_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT, status TEXT NOT NULL DEFAULT "pending", email_verified_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login TEXT NOT NULL UNIQUE, email TEXT NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT "user", status TEXT NOT NULL DEFAULT "active", persona_id INTEGER, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

        $this->repo = new PersonaRepository($this->conn);
        $this->session = new InMemorySession();
    }

    public function testSyncCreatesLocalUserOnWww(): void
    {
        $account = $this->repo->createAccount('User@Example.test', 'hash', 'active');
        $this->session->set('persona_id', $account);

        $bridge = new PersonaBridge($this->conn, $this->session, $this->repo);
        $bridge->sync('www');

        $users = $this->conn->pdo()->query('SELECT login, email, persona_id FROM users')->fetchAll() ?: [];
        self::assertCount(1, $users);
        self::assertSame('user', $users[0]['login']);
        self::assertSame('User@Example.test', $users[0]['email']);
        self::assertSame($account, (int) $users[0]['persona_id']);
        self::assertNotNull($this->session->get('user_id'));

        // повторный sync не создаёт дубль
        $bridge->sync('www');
        self::assertSame(1, (int) $this->conn->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testSyncIgnoresOtherAliases(): void
    {
        $account = $this->repo->createAccount('dev@example.test', 'hash', 'active');
        $this->session->set('persona_id', $account);

        new PersonaBridge($this->conn, $this->session, $this->repo)->sync('fmanager');

        self::assertSame(0, (int) $this->conn->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertNull($this->session->get('user_id'));
    }

    public function testSyncIgnoresPendingAccount(): void
    {
        $account = $this->repo->createAccount('pending@example.test', null, 'pending');
        $this->session->set('persona_id', $account);

        new PersonaBridge($this->conn, $this->session, $this->repo)->sync('www');

        self::assertSame(0, (int) $this->conn->pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}

final class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function regenerate(): void
    {
    }

    public function destroy(): void
    {
        $this->data = [];
    }
}
