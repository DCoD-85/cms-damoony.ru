<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Cli\Commands\DbMigrateCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Db\Connection;
use Damoony\Files\Paths;
use PDO;
use PHPUnit\Framework\TestCase;

final class DbMigrateScenarioTest extends TestCase
{
    private string $root;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_db_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/migrations', 0o777, true);
        $this->dbPath = $this->root . '/test.db';

        $migrations = [
            '001_users.sql' => 'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, login VARCHAR(64) NOT NULL UNIQUE);',
            '002_news.sql' => 'CREATE TABLE news (id INTEGER PRIMARY KEY AUTOINCREMENT, slug VARCHAR(128) NOT NULL UNIQUE);',
            '003_files.sql' => 'CREATE TABLE files (id INTEGER PRIMARY KEY AUTOINCREMENT, slug VARCHAR(128) NOT NULL UNIQUE);',
        ];
        foreach ($migrations as $name => $sql) {
            file_put_contents($this->root . '/migrations/' . $name, $sql);
        }
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function ctx(Result $r): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = '') use ($r): void {
            $r->step($m, $ok, $d);
        });
    }

    public function testS19AppliesAllMigrations(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => $this->dbPath]);
        $cmd = new DbMigrateCommand(new Paths($this->root), connection: $conn);

        $r = new Result();
        $res = $cmd->run([], $this->ctx($r));

        self::assertTrue($res->ok);
        self::assertCount(3, $r->steps);

        $pdo = $conn->pdo();
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        self::assertContains('users', $tables);
        self::assertContains('news', $tables);
        self::assertContains('files', $tables);
        self::assertContains('migrations', $tables);
    }

    public function testS19Idempotent(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => $this->dbPath]);

        $r1 = new Result();
        (new DbMigrateCommand(new Paths($this->root), connection: $conn))->run([], $this->ctx($r1));
        self::assertCount(3, $r1->steps);

        $r2 = new Result();
        (new DbMigrateCommand(new Paths($this->root), connection: $conn))->run([], $this->ctx($r2));
        self::assertCount(1, $r2->steps);
        self::assertSame('Нет новых миграций', $r2->steps[0]['message']);
    }

    public function testS19MissingDir(): void
    {
        $conn = new Connection(['driver' => 'sqlite', 'path' => $this->dbPath]);
        $cmd = new DbMigrateCommand(new Paths($this->root), connection: $conn);

        $r = new Result();
        $res = $cmd->run(['dir' => '/nonexistent'], $this->ctx($r));

        self::assertFalse($res->ok);
    }
}
