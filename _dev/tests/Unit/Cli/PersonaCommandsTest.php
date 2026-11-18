<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\AccountApproveCommand;
use Damoony\Cli\Commands\AccountRequestCommand;
use Damoony\Cli\Commands\AccountSetPasswordCommand;
use Damoony\Cli\Commands\InstallApproveCommand;
use Damoony\Cli\Commands\InstallClaimCommand;
use Damoony\Cli\Commands\InstallCreateCommand;
use Damoony\Cli\Commands\MemberApproveCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\PersonaRepository;
use PHPUnit\Framework\TestCase;

final class PersonaCommandsTest extends TestCase
{
    private PersonaRepository $repo;
    private string $root;

    protected function setUp(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $conn->pdo();

        $pdo->exec('CREATE TABLE persona_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT, status TEXT NOT NULL DEFAULT "pending", email_verified_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE project_members (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, project TEXT NOT NULL, alias TEXT NOT NULL DEFAULT "", role TEXT NOT NULL DEFAULT "user", status TEXT NOT NULL DEFAULT "pending", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (account_id, project, alias))');
        $pdo->exec('CREATE TABLE registration_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL DEFAULT "", email TEXT NOT NULL, comment TEXT, projects TEXT, status TEXT NOT NULL DEFAULT "pending", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, decided_at TEXT, decided_by TEXT)');
        $pdo->exec('CREATE TABLE persona_invites (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, token_hash TEXT NOT NULL UNIQUE, expires_at TEXT NOT NULL, used_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE installations (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, domain TEXT NOT NULL, assembly TEXT NOT NULL DEFAULT "minimal", langs TEXT NOT NULL DEFAULT "ru,en", status TEXT NOT NULL DEFAULT "pending", token_hash TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, claimed_at TEXT, expires_at TEXT, install_count INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE builds (id INTEGER PRIMARY KEY AUTOINCREMENT, installation_id INTEGER NOT NULL, version TEXT NOT NULL DEFAULT "", file TEXT NOT NULL, sha256 TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TEXT NOT NULL, download_count INTEGER NOT NULL DEFAULT 0)');

        $this->repo = new PersonaRepository($conn);

        $this->root = sys_get_temp_dir() . '/damoony_persona_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/stubs/assemblies/minimal', 0o775, true);
        file_put_contents($this->root . '/stubs/assemblies/minimal/assembly.json', '{"name":"Minimal"}');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testRequestApproveInviteAndPassword(): void
    {
        $request = new AccountRequestCommand($this->repo);
        $result = $request->run(['email' => 'new@example.test', 'name' => 'Иван'], $this->context());
        self::assertTrue($result->ok);
        $requestId = (int) $result->data['request_id'];
        self::assertCount(1, $this->repo->pendingRequests());

        $approve = new AccountApproveCommand($this->repo);
        $approved = $approve->run(['request' => $requestId, 'base' => 'https://persona.test'], $this->context());
        self::assertTrue($approved->ok, implode('; ', $approved->errors));

        $account = $this->repo->accountByEmail('new@example.test');
        self::assertSame('pending', $account['status']);

        $url = (string) $approved->data['invite_url'];
        self::assertStringStartsWith('https://persona.test/invite/', $url);
        $token = substr($url, strrpos($url, '/') + 1);

        $setPassword = new AccountSetPasswordCommand($this->repo);
        $activated = $setPassword->run(['token' => $token, 'password' => 'password123'], $this->context());
        self::assertTrue($activated->ok, implode('; ', $activated->errors));
        self::assertSame('active', $this->repo->accountByEmail('new@example.test')['status']);

        // Повторно тот же токен уже не работает
        $again = $setPassword->run(['token' => $token, 'password' => 'password123'], $this->context());
        self::assertFalse($again->ok);
    }

    public function testApproveWithPasswordAndMemberAccess(): void
    {
        $request = new AccountRequestCommand($this->repo);
        $result = $request->run(['email' => 'member@example.test'], $this->context());

        new AccountApproveCommand($this->repo)->run([
            'request' => (int) $result->data['request_id'],
            'password' => 'password123',
        ], $this->context());

        $approved = new MemberApproveCommand($this->repo)->run([
            'account' => 'member@example.test',
            'project' => 'fmanager',
        ], $this->context());

        self::assertTrue($approved->ok, implode('; ', $approved->errors));
        self::assertSame('active', $this->repo->member((int) $this->repo->accountByEmail('member@example.test')['id'], 'fmanager')['status']);
    }

    public function testInstallCreateApproveClaim(): void
    {
        $request = new AccountRequestCommand($this->repo);
        $created = $request->run(['email' => 'dev@example.test'], $this->context());
        new AccountApproveCommand($this->repo)->run([
            'request' => (int) $created->data['request_id'],
            'password' => 'password123',
        ], $this->context());

        $install = new InstallCreateCommand(new Paths($this->root), $this->repo)->run([
            'account' => 'dev@example.test',
            'domain' => 'site.ru',
            'assembly' => 'minimal',
            'langs' => 'ru,en',
        ], $this->context());
        self::assertTrue($install->ok, implode('; ', $install->errors));
        self::assertSame('pending', $install->data['status']);
        $token = (string) $install->data['token'];
        $installationId = (int) $install->data['installation_id'];

        $claim = new InstallClaimCommand($this->repo);
        $notApproved = $claim->run(['token' => $token, 'domain' => 'site.ru'], $this->context());
        self::assertFalse($notApproved->ok);
        self::assertStringContainsString('не подтверждена', $notApproved->errors[0]);

        new InstallApproveCommand($this->repo)->run(['id' => $installationId], $this->context());

        $wrongDomain = $claim->run(['token' => $token, 'domain' => 'other.ru'], $this->context());
        self::assertFalse($wrongDomain->ok);

        $claimed = $claim->run(['token' => $token, 'domain' => 'site.ru'], $this->context());
        self::assertTrue($claimed->ok, implode('; ', $claimed->errors));
        self::assertSame(['ru', 'en'], $claimed->data['langs']);
        self::assertSame(1, (int) $this->repo->installationById($installationId)['install_count']);
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
