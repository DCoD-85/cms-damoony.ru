<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\AdminPasswdCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AdminPasswdCommandTest extends TestCase
{
    private string $tmpDir;
    private Paths $paths;
    private AdminPasswdCommand $command;
    private Context $context;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/damoony_cli_test_' . uniqid();
        mkdir($this->tmpDir . '/config', 0o777, true);
        $this->paths = new Paths($this->tmpDir);
        $this->command = new AdminPasswdCommand($this->paths);
        $this->context = new Context(fn () => null);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpDir . '/config/admin.config')) {
            unlink($this->tmpDir . '/config/admin.config');
        }
        if (is_dir($this->tmpDir . '/config')) {
            rmdir($this->tmpDir . '/config');
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testInvalidLogin(): void
    {
        $result = $this->command->run(['name' => 'a', 'password' => '12345678'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Некорректный логин', $result->errors[0]);
    }

    public function testInvalidPassword(): void
    {
        $result = $this->command->run(['name' => 'admin', 'password' => 'short'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Пароль минимум 8 символов', $result->errors[0]);
    }

    public function testSuccessfulSave(): void
    {
        $result = $this->command->run(['name' => 'testuser', 'password' => 'strongpass'], $this->context);
        $this->assertTrue($result->ok);

        $configFile = $this->tmpDir . '/config/admin.config';
        $this->assertFileExists($configFile);

        $data = json_decode(file_get_contents($configFile), true);
        $this->assertEquals('testuser', $data['login']);
        $this->assertTrue(password_verify('strongpass', $data['password_hash']));
    }
}
