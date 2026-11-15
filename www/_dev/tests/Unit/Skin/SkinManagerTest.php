<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Skin;

use Damoony\UI\Skin\SkinManager;
use PHPUnit\Framework\TestCase;

final class SkinManagerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/skins_' . uniqid('', true);
        mkdir($this->root . '/damoony.ru/www/skin/default/css', 0o775, true);
        mkdir($this->root . '/damoony.ru/www/skin/default/js', 0o775, true);
        mkdir($this->root . '/damoony.ru/www/skin/dark/css', 0o775, true);
        file_put_contents($this->root . '/damoony.ru/www/skin/default/css/main.css', 'body{}');
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testList(): void
    {
        $m = new SkinManager($this->root);
        self::assertSame(['dark', 'default'], $m->list('damoony.ru', 'www'));
        self::assertSame([], $m->list('other.ru', 'www'));
    }

    public function testExists(): void
    {
        $m = new SkinManager($this->root);
        self::assertTrue($m->exists('damoony.ru', 'www', 'default'));
        self::assertTrue($m->exists('damoony.ru', 'www', 'dark'));
        self::assertFalse($m->exists('damoony.ru', 'www', 'nope'));
        self::assertFalse($m->exists('damoony.ru', 'www', ''));
        self::assertFalse($m->exists('damoony.ru', 'www', '../etc'));
        self::assertFalse($m->exists('other.ru', 'www', 'default'));
    }

    private function rmrf(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (scandir($d) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $d . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($d);
    }
}
