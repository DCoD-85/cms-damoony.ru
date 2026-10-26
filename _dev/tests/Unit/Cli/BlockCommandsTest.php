<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class BlockCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_bl_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/.alliasglobal', 0777, true);
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

    public function testS04CreateBlock(): void
    {
        $r = new Result();
        $res = (new BlockNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'custom'], $this->ctx($r));

        self::assertTrue($res->ok);
        $dir = $this->root . '/app/example.ru/.alliasglobal/custom';
        self::assertDirectoryExists($dir);
        self::assertFileExists($dir . '/custom.php');
        self::assertFileExists($dir . '/custom.data.php');

        $manifest = json_decode((string) file_get_contents($this->root . '/app/example.ru/.alliasglobal/blocks.json'), true);
        self::assertTrue($manifest['blocks']['custom']['enabled']);
    }

    public function testS04RejectsDuplicate(): void
    {
        $cmd = new BlockNewCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'name' => 'custom'], $this->ctx(new Result()));
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'custom'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS04RejectsInvalidName(): void
    {
        $res = (new BlockNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'BadName'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS05RemoveBlock(): void
    {
        $paths = new Paths($this->root);
        (new BlockNewCommand($paths))->run(['domain' => 'example.ru', 'name' => 'custom'], $this->ctx(new Result()));

        $res = (new BlockRemoveCommand($paths))
            ->run(['domain' => 'example.ru', 'name' => 'custom'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertDirectoryDoesNotExist($this->root . '/app/example.ru/.alliasglobal/custom');

        $manifest = json_decode((string) file_get_contents($this->root . '/app/example.ru/.alliasglobal/blocks.json'), true);
        self::assertArrayNotHasKey('custom', $manifest['blocks']);
    }

    public function testS05MissingBlock(): void
    {
        $res = (new BlockRemoveCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'ghost'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }
}