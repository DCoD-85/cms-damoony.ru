<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeNewCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_sch_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
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

    public function testS10CreateScheme(): void
    {
        $r = new Result();
        $res = (new SchemeNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'visuo'], $this->ctx($r));

        self::assertTrue($res->ok);
        $file = $this->root . '/app/example.ru/.alliasscheme/visuo.data.php';
        self::assertFileExists($file);

        $data = require $file;
        self::assertIsArray($data);
        self::assertSame('layout-visuo', $data['class']);
        self::assertCount(3, $data['children']);

        $manifest = json_decode((string) file_get_contents($this->root . '/app/example.ru/.alliasscheme/schemes.json'), true);
        self::assertTrue($manifest['schemes']['visuo']['enabled']);
    }

    public function testS10RejectsDuplicate(): void
    {
        $cmd = new SchemeNewCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'name' => 'visuo'], $this->ctx(new Result()));
        $res = $cmd->run(['domain' => 'example.ru', 'name' => 'visuo'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS10RejectsInvalidName(): void
    {
        $res = (new SchemeNewCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'name' => 'BadName'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS10RejectsMissingDomain(): void
    {
        $res = (new SchemeNewCommand(new Paths($this->root)))
            ->run(['domain' => 'ghost.ru', 'name' => 'visuo'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }
}
