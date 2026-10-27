<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\TrashClearCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class TrashClearCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_trash_' . uniqid('', true);
        mkdir($this->root . '/app/example.ru', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testDryRunListsWithoutDeleting(): void
    {
        $trash = $this->root . '/app/example.ru/doc_trash_' . (time() - 86400);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run([], $this->ctx());

        self::assertTrue(is_dir($trash), 'dry-run не должен удалять');
    }

    public function testForceDeletes(): void
    {
        $trash = $this->root . '/app/example.ru/doc_trash_' . (time() - 86400);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), '--force должен удалить');
    }

    public function testOlderFilter(): void
    {
        $old = $this->root . '/app/example.ru/old_trash_' . (time() - 10 * 86400);
        $new = $this->root . '/app/example.ru/new_trash_' . (time() - 3600);
        mkdir($old, 0o775, true);
        mkdir($new, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true, 'older' => '7d'], $this->ctx());

        self::assertFalse(is_dir($old), 'старше 7d — удалён');
        self::assertTrue(is_dir($new), 'моложе 7d — сохранён');
    }

    public function testDomainFilter(): void
    {
        $a = $this->root . '/app/example.ru/a_trash_' . (time() - 3600);
        mkdir($this->root . '/app/other.ru', 0o775, true);
        $b = $this->root . '/app/other.ru/b_trash_' . (time() - 3600);
        mkdir($a, 0o775, true);
        mkdir($b, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true, 'domain' => 'example.ru'], $this->ctx());

        self::assertFalse(is_dir($a));
        self::assertTrue(is_dir($b));
    }

    public function testInvalidOlderFormat(): void
    {
        $cmd = new TrashClearCommand(new Paths($this->root));
        $r = $cmd->run(['older' => 'abc'], $this->ctx());

        self::assertFalse($r->ok);
        self::assertNotEmpty($r->errors);
        self::assertStringContainsString('older', $r->errors[0]);
    }

    private function ctx(): Context
    {
        return new Context(static function (string $m, bool $ok, string $d): void {
        });
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
}
