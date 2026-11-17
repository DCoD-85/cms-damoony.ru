<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Next;

use Damoony\Cli\Context;
use Damoony\Cli\Identity\CliLogger;
use Damoony\Cli\Identity\IdentityStore;
use Damoony\Cli\Next\Commands\InitCommand;
use Damoony\Cli\Result;
use PHPUnit\Framework\TestCase;

final class NextIdentityAndLogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_cli_identity_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testCreateAndLoadIdentity(): void
    {
        $store = new IdentityStore($this->root);

        self::assertNull($store->load());

        $created = $store->create('alice');
        self::assertSame('alice', $created['user']);
        self::assertFileExists($this->root . '/var/cli.identity');

        $loaded = $store->load();
        self::assertNotNull($loaded);
        self::assertSame('alice', $loaded['user']);
        self::assertSame($created['secret'], $loaded['secret']);
    }

    public function testCreateRefusesExistingWithoutForce(): void
    {
        $store = new IdentityStore($this->root);
        $store->create('alice');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('уже существует');
        $store->create('bob');
    }

    public function testCreateForceOverwrites(): void
    {
        $store = new IdentityStore($this->root);
        $store->create('alice');
        $store->create('bob', true);

        self::assertSame('bob', $store->load()['user'] ?? null);
    }

    public function testLoggerWritesJsonLineAndRedactsSecrets(): void
    {
        $logger = new CliLogger($this->root);
        $result = new Result();

        $logger->log('alice', 'user:new', [
            'login' => 'bob',
            'password' => 'secret-pass',
            'params_b64' => 'QUJD',
            'domain' => 'damoony.ru',
        ], $result, 12.5);

        $file = $logger->dir() . '/' . gmdate('Y-m-d') . '.log';
        self::assertFileExists($file);

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $entry = json_decode((string) $lines[0], true);
        self::assertIsArray($entry);
        self::assertSame('alice', $entry['user']);
        self::assertSame('user:new', $entry['command']);
        self::assertTrue($entry['ok']);
        self::assertSame('bob', $entry['args']['login']);
        self::assertSame('***', $entry['args']['password']);
        self::assertSame('***', $entry['args']['params_b64']);
        self::assertSame('damoony.ru', $entry['args']['domain']);
        self::assertSame(12.5, $entry['duration_ms']);
    }

    public function testInitCommandCreatesIdentity(): void
    {
        $command = new InitCommand(new IdentityStore($this->root), $this->root);

        $result = $command->run(['name' => 'alice'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('alice', $result->data['user']);
        self::assertFileExists($this->root . '/var/cli.identity');
    }

    public function testInitCommandRejectsBadName(): void
    {
        $command = new InitCommand(new IdentityStore($this->root), $this->root);

        $result = $command->run(['name' => 'bad name!'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('Некорректное имя', $result->errors[0]);
    }

    public function testInitCommandDryRun(): void
    {
        $command = new InitCommand(new IdentityStore($this->root), $this->root);

        $result = $command->run(['name' => 'alice'], $this->dryRunContext());

        self::assertTrue($result->ok);
        self::assertTrue($result->data['dry_run']);
        self::assertFileDoesNotExist($this->root . '/var/cli.identity');
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function dryRunContext(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        }, false, true);
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
