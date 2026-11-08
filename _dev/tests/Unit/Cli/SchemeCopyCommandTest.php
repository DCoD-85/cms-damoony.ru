<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SchemeCopyCommand;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeCopyCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_scc_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru', 0o777, true);

        // Создаём исходную схему через SchemeNewCommand
        new SchemeNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));
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

    public function testCopiesThreeFiles(): void
    {
        $res = new SchemeCopyCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->root . '/app/example.ru/.alliasscheme';
        self::assertFileExists($dir . '/main_copy.php');
        self::assertFileExists($dir . '/main_copy.rules.json');
        self::assertFileExists($dir . '/main_copy.editor.json');
    }

    public function testGeneratesUniqueName(): void
    {
        $cmd = new SchemeCopyCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));
        $cmd->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));
        $cmd->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        $dir = $this->root . '/app/example.ru/.alliasscheme';
        self::assertFileExists($dir . '/main_copy.php');
        self::assertFileExists($dir . '/main_copy_1.php');
        self::assertFileExists($dir . '/main_copy_2.php');
    }

    public function testRejectsMissingScheme(): void
    {
        $res = new SchemeCopyCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => 'ghost'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Схема не найдена', $res->errors[0]);
    }

    public function testRejectsInvalidName(): void
    {
        $res = new SchemeCopyCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => 'Bad-Name'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }

    public function testRejectsMissingDomain(): void
    {
        $res = new SchemeCopyCommand(new Paths($this->root))
            ->run(['domain' => 'ghost.ru', 'name' => 'main'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }
}
