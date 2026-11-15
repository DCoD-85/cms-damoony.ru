<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\SchemeSaveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeSaveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_scs_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru', 0o777, true);

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

    public function testSavesEditorAndCompilesPhp(): void
    {
        $scheme = [
            'children' => [
                ['type' => 'slot', 'name' => 'top'],
                ['type' => 'slot', 'name' => 'center'],
            ],
            'scripts' => [],
        ];
        $json = json_encode($scheme);

        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json' => $json],
                $this->ctx(new Result()),
            );

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->root . '/app/example.ru/.schemesglobal';
        $editor = json_decode((string) file_get_contents($dir . '/main.editor.json'), true);
        self::assertIsArray($editor);
        self::assertSame($scheme, $editor['scheme']);

        $php = require $dir . '/main.php';
        self::assertIsArray($php);
        self::assertSame('layout', $php['class']);
        self::assertCount(2, $php['children']);
    }

    public function testAcceptsBase64(): void
    {
        $scheme = ['children' => [['type' => 'slot', 'name' => 'top']], 'scripts' => []];
        $b64 = base64_encode((string) json_encode($scheme));

        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json_b64' => $b64],
                $this->ctx(new Result()),
            );

        self::assertTrue($res->ok, implode('; ', $res->errors));
    }

    public function testRejectsMissingJson(): void
    {
        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'name' => 'main'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--scheme_json', $res->errors[0]);
    }

    public function testRejectsMissingEditor(): void
    {
        // Удаляем editor.json
        unlink($this->root . '/app/example.ru/.schemesglobal/main.editor.json');

        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json' => '{"children":[]}'],
                $this->ctx(new Result()),
            );

        self::assertFalse($res->ok);
        self::assertStringContainsString('editor.json не найден', $res->errors[0]);
    }

    public function testRejectsInvalidJson(): void
    {
        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json' => '{not valid'],
                $this->ctx(new Result()),
            );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректный JSON', $res->errors[0]);
    }

    public function testDropsUnsafeTag(): void
    {
        // <script>/<iframe>/<style>/<form> etc. must not reach compiled .php
        $scheme = [
            'children' => [
                ['tag' => 'div', 'class' => 'wrap'],
                ['tag' => 'script', 'class' => 'evil'],
                ['tag' => 'iframe', 'class' => 'evil'],
                ['tag' => 'style', 'class' => 'evil'],
                ['tag' => 'form', 'class' => 'evil'],
            ],
        ];

        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json' => json_encode($scheme)],
                $this->ctx(new Result()),
            );

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $php = require $this->root . '/app/example.ru/.schemesglobal/main.php';
        self::assertIsArray($php);
        // Only 'div' survived
        self::assertCount(1, $php['children']);
        self::assertSame('div', $php['children'][0]['tag']);

        // Defense-in-depth: no banned tag anywhere in the compiled file
        $compiled = (string) file_get_contents($this->root . '/app/example.ru/.schemesglobal/main.php');
        foreach (['script', 'iframe', 'style', 'form'] as $banned) {
            self::assertStringNotContainsString("'" . $banned . "'", $compiled, "{$banned} leak in compiled php");
        }
    }

    public function testDropsInvalidSlotName(): void
    {
        // Slot name with path traversal / uppercase / dashes must be skipped
        $scheme = [
            'children' => [
                ['type' => 'slot', 'name' => 'top'],
                ['type' => 'slot', 'name' => '../etc'],
                ['type' => 'slot', 'name' => 'Bad Name'],
                ['type' => 'slot', 'name' => 'a-b'],
            ],
        ];

        $res = new SchemeSaveCommand(new Paths($this->root))
            ->run(
                ['domain' => 'example.ru', 'name' => 'main', 'scheme_json' => json_encode($scheme)],
                $this->ctx(new Result()),
            );

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $php = require $this->root . '/app/example.ru/.schemesglobal/main.php';
        self::assertIsArray($php);
        self::assertCount(1, $php['children'], 'only valid slot survived');
        self::assertSame('top', $php['children'][0]['slot']);
    }
}
