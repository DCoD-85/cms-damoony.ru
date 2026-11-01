<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\SchemeRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeRemoveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_scr_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru', 0o777, true);
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

    public function testRemovesThreeFilesToTrash(): void
    {
        (new SchemeNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        $res = (new SchemeRemoveCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'main', 'force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->root . '/app/example.ru/.alliasscheme';
        self::assertFileDoesNotExist($dir . '/main.php');
        self::assertFileDoesNotExist($dir . '/main.rules.json');
        self::assertFileDoesNotExist($dir . '/main.editor.json');

        // Есть папка карантина main_trash_<timestamp>
        $trashDirs = glob($dir . '/main_trash_*');
        self::assertNotEmpty($trashDirs);
        self::assertFileExists($trashDirs[0] . '/main.php');
        self::assertFileExists($trashDirs[0] . '/main.rules.json');
        self::assertFileExists($trashDirs[0] . '/main.editor.json');
    }

    public function testRejectsWithoutForce(): void
    {
        (new SchemeNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        $res = (new SchemeRemoveCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testIdempotentOnMissingScheme(): void
    {
        $res = (new SchemeRemoveCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'ghost', 'force' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertNotEmpty($res->warnings);
    }

    public function testRejectsMissingDomain(): void
    {
        $res = (new SchemeRemoveCommand(new Paths($this->root)))
            ->run(['domain' => 'ghost.ru', 'name' => 'main', 'force' => '1'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }
}
