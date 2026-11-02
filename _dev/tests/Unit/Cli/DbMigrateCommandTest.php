<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DbMigrateCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DbMigrateCommandTest extends TestCase
{
    private string $tmpDir;
    private Paths $paths;
    private Context $context;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/damoony_db_test_' . uniqid();
        mkdir($this->tmpDir . '/migrations/archive', 0o777, true);
        file_put_contents($this->tmpDir . '/migrations/001_test.sql', 'CREATE TABLE test (id INT);');

        $this->paths = new Paths($this->tmpDir);
        $this->context = new Context(fn () => null);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/migrations/001_test.sql');
        @rmdir($this->tmpDir . '/migrations/archive');
        @rmdir($this->tmpDir . '/migrations');
        @rmdir($this->tmpDir);
    }

    public function testPathTraversalGuard(): void
    {
        $cmd = new DbMigrateCommand($this->paths);
        $result = $cmd->run(['dir' => '/etc'], $this->context);
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Недопустимый --dir', $result->errors[0]);
    }

    public function testSuccessfulMigration(): void
    {
        $cmd = new DbMigrateCommand(
            $this->paths,
            ['driver' => 'sqlite', 'path' => ':memory:'],
        );
        $result = $cmd->run([], $this->context);
        $this->assertTrue($result->ok);
    }
}
