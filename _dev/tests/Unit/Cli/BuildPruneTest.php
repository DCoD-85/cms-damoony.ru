<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\BuildPruneCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Data\Db\Connection;
use Damoony\Data\Persona\PersonaRepository;
use PHPUnit\Framework\TestCase;

final class BuildPruneTest extends TestCase
{
    private string $root;
    private PersonaRepository $repo;
    private int $installationId;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_prune_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var/builds', 0o775, true);

        $conn = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $conn->pdo();
        $pdo->exec('CREATE TABLE persona_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, password_hash TEXT, status TEXT NOT NULL DEFAULT "pending", email_verified_at TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE installations (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL, domain TEXT NOT NULL, assembly TEXT NOT NULL, langs TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", token_hash TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, claimed_at TEXT, expires_at TEXT, install_count INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE builds (id INTEGER PRIMARY KEY AUTOINCREMENT, installation_id INTEGER NOT NULL, version TEXT NOT NULL, file TEXT NOT NULL, sha256 TEXT NOT NULL, size INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at TEXT NOT NULL, download_count INTEGER NOT NULL DEFAULT 0)');

        $this->repo = new PersonaRepository($conn);
        $account = $this->repo->createAccount('build@example.test', 'hash', 'active');
        $this->installationId = $this->repo->createInstallation(
            $account,
            'site.test',
            'minimal',
            ['ru'],
            hash('sha256', 'token'),
            'active',
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testDeletesOnlyExpiredBuilds(): void
    {
        $expiredFile = 'install-1-expired.zip';
        $activeFile = 'install-1-active.zip';
        file_put_contents($this->root . '/var/builds/' . $expiredFile, 'zip');
        file_put_contents($this->root . '/var/builds/' . $activeFile, 'zip');

        $this->repo->createBuild($this->installationId, '1', $expiredFile, str_repeat('a', 64), 3, gmdate('Y-m-d H:i:s', time() - 3600));
        $this->repo->createBuild($this->installationId, '2', $activeFile, str_repeat('b', 64), 3, gmdate('Y-m-d H:i:s', time() + 3600));

        $command = new BuildPruneCommand(new Paths($this->root), $this->repo);
        $result = $command->run([], $this->context());

        self::assertTrue($result->ok);
        self::assertSame(1, $result->data['deleted']);
        self::assertFileDoesNotExist($this->root . '/var/builds/' . $expiredFile);
        self::assertFileExists($this->root . '/var/builds/' . $activeFile);
        self::assertSame(0, count($this->repo->expiredBuilds()));
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
