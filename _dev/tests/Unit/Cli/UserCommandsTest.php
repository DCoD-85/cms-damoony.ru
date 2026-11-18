<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\UserNewCommand;
use Damoony\Cli\Context;
use Damoony\Data\Db\Connection;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserCommandsTest extends TestCase
{
    private string $root;
    private string $dbFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_admin_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/var', 0o775, true);

        $this->dbFile = $this->root . '/var/test.db';

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                login TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                email TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT "user",
                status TEXT NOT NULL DEFAULT "active",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )',
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testUserNewCreatesUser(): void
    {
        $result = $this->userNew()->run(
            ['login' => 'alice', 'email' => 'a@example.com', 'password' => 'password123'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertTrue($result->data['created']);

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $row = $pdo->query("SELECT * FROM users WHERE login='alice'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('a@example.com', $row['email']);
        self::assertSame('user', $row['role']);
        self::assertTrue(password_verify('password123', (string) $row['password_hash']));
    }

    public function testUserNewRejectsDuplicateWithoutUpdate(): void
    {
        $this->userNew()->run(
            ['login' => 'alice', 'email' => 'a@example.com', 'password' => 'password123'],
            $this->context(),
        );

        $result = $this->userNew()->run(
            ['login' => 'alice', 'email' => 'b@example.com', 'password' => 'password123'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('--update', $result->errors[0]);
    }

    public function testUserNewUpdatesExisting(): void
    {
        $this->userNew()->run(
            ['login' => 'alice', 'email' => 'a@example.com', 'password' => 'password123'],
            $this->context(),
        );

        $result = $this->userNew()->run(
            ['login' => 'alice', 'email' => 'b@example.com', 'password' => 'newpassword1', 'role' => 'admin', 'update' => '1'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertTrue($result->data['updated']);

        $pdo = new PDO('sqlite:' . $this->dbFile);
        $row = $pdo->query("SELECT * FROM users WHERE login='alice'")->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('b@example.com', $row['email']);
        self::assertSame('admin', $row['role']);
    }

    public function testUserNewValidatesInput(): void
    {
        $badLogin = $this->userNew()->run(['login' => 'a', 'email' => 'a@example.com', 'password' => 'password123'], $this->context());
        self::assertFalse($badLogin->ok);

        $badEmail = $this->userNew()->run(['login' => 'alice', 'email' => 'nope', 'password' => 'password123'], $this->context());
        self::assertFalse($badEmail->ok);

        $badRole = $this->userNew()->run(['login' => 'alice', 'email' => 'a@example.com', 'password' => 'password123', 'role' => 'root'], $this->context());
        self::assertFalse($badRole->ok);
    }

    public function testUserNewWithoutDbReportsClearError(): void
    {
        $result = new UserNewCommand(null)->run(
            ['login' => 'alice', 'email' => 'a@example.com', 'password' => 'password123'],
            $this->context(),
        );

        self::assertFalse($result->ok);
        self::assertStringContainsString('БД не сконфигурирована', $result->errors[0]);
    }

    private function userNew(): UserNewCommand
    {
        return new UserNewCommand(new Connection(['driver' => 'sqlite', 'path' => $this->dbFile]));
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
