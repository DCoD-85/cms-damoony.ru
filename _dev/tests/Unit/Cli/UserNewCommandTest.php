<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\UserNewCommand;
use Damoony\Cli\Context;
use Damoony\Db\Connection;
use PHPUnit\Framework\TestCase;

final class UserNewCommandTest extends TestCase
{
    private UserNewCommand $command;
    private Context $context;

    protected function setUp(): void
    {
        $connection = new Connection(['driver' => 'sqlite', 'path' => ':memory:']);
        $pdo = $connection->pdo();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, login TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, status TEXT)');

        $this->command = new UserNewCommand($connection);
        $this->context = new Context(fn () => null);
    }

    public function testInvalidLogin(): void
    {
        $result = $this->command->run(['login' => 'a', 'email' => 'a@b.com', 'password' => '12345678'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Некорректный login', $result->errors[0]);
    }

    public function testInvalidEmail(): void
    {
        $result = $this->command->run(['login' => 'validlogin', 'email' => 'not-email', 'password' => '12345678'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Некорректный email', $result->errors[0]);
    }

    public function testSuccessfulCreate(): void
    {
        $result = $this->command->run(['login' => 'validlogin', 'email' => 'user@example.com', 'password' => 'strongpass'], $this->context);
        $this->assertTrue($result->ok);
    }

    public function testDuplicateLogin(): void
    {
        $this->command->run(['login' => 'validlogin', 'email' => 'user1@example.com', 'password' => 'strongpass'], $this->context);
        $result = $this->command->run(['login' => 'validlogin', 'email' => 'user2@example.com', 'password' => 'strongpass'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('login уже существует', $result->errors[0]);
    }
}
