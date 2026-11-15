<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\TrashClearCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
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

    public function testCollectsDomainTrash(): void
    {
        // app/<domain>_trash_<ts>/  (domain:remove)
        $trash = $this->root . '/app/example.ru_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'domain:remove карантин должен удаляться');
    }

    public function testCollectsSchemeTrash(): void
    {
        // app/<domain>/.schemesglobal/<name>_trash_<ts>/  (scheme:remove)
        mkdir($this->root . '/app/example.ru/.schemesglobal', 0o775, true);
        $trash = $this->root . '/app/example.ru/.schemesglobal/main_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'scheme:remove карантин должен удаляться');
    }

    public function testCollectsBlockTrash(): void
    {
        // app/<domain>/.blocksglobal/<name>_trash_<ts>/  (block:remove)
        mkdir($this->root . '/app/example.ru/.blocksglobal', 0o775, true);
        $trash = $this->root . '/app/example.ru/.blocksglobal/top_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'block:remove карантин должен удаляться');
    }

    public function testCollectsMediaTrash(): void
    {
        // www/media/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/
        mkdir($this->root . '/www/media/.trash', 0o775, true);
        $trash = $this->root . '/www/media/.trash/example.ru_www_coffee_abc123def4567890_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);
        file_put_contents($trash . '/abc123def4567890.png', 'bytes');

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'media:remove карантин должен удаляться');
    }

    public function testMediaTrashDomainFilter(): void
    {
        mkdir($this->root . '/www/media/.trash', 0o775, true);
        $a = $this->root . '/www/media/.trash/example.ru_www_coffee_abc123def4567890_trash_' . (time() - 3600);
        $b = $this->root . '/www/media/.trash/other.ru_www_tea_def456abc1237890_trash_' . (time() - 3600);
        mkdir($a, 0o775, true);
        mkdir($b, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true, 'domain' => 'example.ru'], $this->ctx());

        self::assertFalse(is_dir($a), 'example.ru — удалён');
        self::assertTrue(is_dir($b), 'other.ru — сохранён (не матчит фильтр)');
    }

    public function testMediaTrashWithoutAppDir(): void
    {
        // app/ отсутствует, но www/media/.trash/ есть — не падаем
        $this->rmrf($this->root . '/app');
        mkdir($this->root . '/www/media/.trash', 0o775, true);
        $trash = $this->root . '/www/media/.trash/example.ru_www_coffee_abc123def4567890_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'media-карантин работает без app/');
    }

    public function testCollectsContentTrash(): void
    {
        // content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/
        // ContentRemoveCommand quarantines here, not in www/media/.trash/
        mkdir($this->root . '/content/.trash', 0o775, true);
        $trash = $this->root . '/content/.trash/example.ru_www_coffee_abc123def4567890_trash_' . (time() - 3600);
        mkdir($trash, 0o775, true);
        file_put_contents($trash . '/abc123def4567890.png', 'bytes');

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true], $this->ctx());

        self::assertFalse(is_dir($trash), 'content:remove карантин должен удаляться');
    }

    public function testContentTrashDomainFilter(): void
    {
        mkdir($this->root . '/content/.trash', 0o775, true);
        $a = $this->root . '/content/.trash/example.ru_www_coffee_abc123def4567890_trash_' . (time() - 3600);
        $b = $this->root . '/content/.trash/other.ru_www_tea_def456abc1237890_trash_' . (time() - 3600);
        mkdir($a, 0o775, true);
        mkdir($b, 0o775, true);

        $cmd = new TrashClearCommand(new Paths($this->root));
        $cmd->run(['force' => true, 'domain' => 'example.ru'], $this->ctx());

        self::assertFalse(is_dir($a), 'example.ru — удалён');
        self::assertTrue(is_dir($b), 'other.ru — сохранён (не матчит фильтр)');
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
