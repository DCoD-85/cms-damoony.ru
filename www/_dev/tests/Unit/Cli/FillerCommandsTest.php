<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class FillerCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_fl_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);
        mkdir($this->root . '/app/example.ru/.blocksglobal/top', 0o777, true);
        // Файлы блока (нужны для filler:toggle, который проверяет блок на диске)
        file_put_contents($this->root . '/app/example.ru/.blocksglobal/top/top.php', '<?php');
        file_put_contents($this->root . '/app/example.ru/.blocksglobal/top/top.rules.json', '{"enabled":true,"type":"mixed"}');
        file_put_contents($this->root . '/app/example.ru/.blocksglobal/top/top.editor.json', '[]');
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

    public function testS06CreateFiller(): void
    {
        $r = new Result();
        $res = new FillerNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top'], $this->ctx($r));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        // Filler пишется в {real}/{alias}/.blockslocal/{block}/{block}.*
        self::assertFileExists($this->root . '/app/example.ru/www/.blockslocal/top/top.php');
        self::assertFileExists($this->root . '/app/example.ru/www/.blockslocal/top/top.rules.json');
        self::assertFileExists($this->root . '/app/example.ru/www/.blockslocal/top/top.editor.json');
        // Базовый блок остаётся в .blocksglobal
        self::assertFileExists($this->root . '/app/example.ru/.blocksglobal/top/top.php');

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertTrue($site['fillers']['example.ru']['www']['top']);
    }

    public function testS06RejectsDuplicate(): void
    {
        $cmd = new FillerNewCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top'], $this->ctx(new Result()));
        $res = $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
        self::assertStringContainsString('Filler уже существует', $res->errors[0]);
    }

    public function testS06RejectsMissingBlock(): void
    {
        $res = new FillerNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'ghost'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
        self::assertStringContainsString('Блок не найден', $res->errors[0]);
    }

    public function testS06RejectsMissingAllias(): void
    {
        $res = new FillerNewCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'ghost', 'block' => 'top'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
        self::assertStringContainsString('Алиас не найден', $res->errors[0]);
    }

    public function testS06RejectsPathTraversal(): void
    {
        $res = new FillerNewCommand(new Paths($this->root))
            ->run(['domain' => 'a..ru', 'allias' => 'www', 'block' => 'top'], $this->ctx(new Result()));
        self::assertFalse($res->ok);
    }

    public function testS07ToggleOff(): void
    {
        $paths = new Paths($this->root);
        new FillerNewCommand($paths)->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top'], $this->ctx(new Result()));

        $res = new FillerToggleCommand($paths)
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top', 'value' => '0'], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertFalse($site['fillers']['example.ru']['www']['top']);
    }

    public function testS07ToggleOn(): void
    {
        $paths = new Paths($this->root);
        new FillerToggleCommand($paths)
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top', 'value' => '0'], $this->ctx(new Result()));
        $res = new FillerToggleCommand($paths)
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top', 'value' => '1'], $this->ctx(new Result()));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertTrue($site['fillers']['example.ru']['www']['top']);
    }

    public function testS07ToggleIdempotentOnSameValue(): void
    {
        $paths = new Paths($this->root);
        new FillerToggleCommand($paths)
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top', 'value' => '0'], $this->ctx(new Result()));

        // Запоминаем состояние файла
        $siteBefore = (string) file_get_contents($this->root . '/config/site.config');

        // Повторный toggle на то же значение — не должен менять файл
        $res = new FillerToggleCommand($paths)
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'top', 'value' => '0'], $this->ctx(new Result()));

        self::assertTrue($res->ok);
        self::assertSame($siteBefore, (string) file_get_contents($this->root . '/config/site.config'), 'Файл не должен меняться при идемпотентном toggle');
    }

    public function testS07ToggleRejectsMissingBlock(): void
    {
        $res = new FillerToggleCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'block' => 'ghost', 'value' => '1'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Блок не найден', $res->errors[0]);
    }

    public function testS07ToggleRejectsMissingAllias(): void
    {
        $res = new FillerToggleCommand(new Paths($this->root))
            ->run(['domain' => 'example.ru', 'allias' => 'ghost', 'block' => 'top', 'value' => '1'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Алиас не найден', $res->errors[0]);
    }

    public function testS07ToggleRejectsPathTraversal(): void
    {
        $res = new FillerToggleCommand(new Paths($this->root))
            ->run(['domain' => 'a..ru', 'allias' => 'www', 'block' => 'top', 'value' => '1'], $this->ctx(new Result()));

        self::assertFalse($res->ok);
    }
}
