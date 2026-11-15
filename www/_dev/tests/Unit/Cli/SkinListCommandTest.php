<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SkinListCommand;
use Damoony\Cli\Commands\SkinNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SkinListCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_skin_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/www', 0o777, true);
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
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function ctx(): Context
    {
        return new Context(static fn () => null);
    }

    private function makeSkin(string $name): void
    {
        new SkinNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => $name], $this->ctx());
    }

    public function testEmptyList(): void
    {
        $res = new SkinListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www'], $this->ctx());
        self::assertTrue($res->ok);
        self::assertSame([], $res->data['rows']);
        self::assertNull($res->data['active']);
    }

    public function testListsSkins(): void
    {
        $this->makeSkin('default');
        $this->makeSkin('dark');

        $res = new SkinListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www'], $this->ctx());
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);
        self::assertSame('default', $res->data['active']);

        $byName = [];
        foreach ($res->data['rows'] as $row) {
            $byName[$row['name']] = $row;
        }
        self::assertTrue($byName['default']['active']);
        self::assertTrue($byName['default']['has_css']);
        self::assertFalse($byName['dark']['active']);
    }

    public function testRejectsInvalidAllias(): void
    {
        $res = new SkinListCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'Bad-Name'], $this->ctx());
        self::assertFalse($res->ok);
    }
}
