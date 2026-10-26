<?php

declare(strict_types=1);

namespace Damoony\Tests\Scenarios;

use Damoony\Cli\Commands\VarsExtractCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class VarsExtractScenarioTest extends TestCase
{
    private string $root;
    private string $algoritm;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_vx_' . bin2hex(random_bytes(4));
        $this->algoritm = $this->root . '/app/example.ru/www/algoritm';
        mkdir($this->algoritm . '/about', 0777, true);

        file_put_contents($this->algoritm . '/algoritm.php', <<<'PHP'
<?php
class algoritm
{
    public array $available = ['', 'about', 'docs'];
    public bool $auth = false;
    public bool $search = true;
    public bool $default = false;
}
PHP);

        file_put_contents($this->algoritm . '/about/algoritm_about.php', <<<'PHP'
<?php
class algoritm_about
{
    public array $available = [''];
    public bool $auth = true;
    public bool $search = false;
    public bool $default = true;
}
PHP);
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

    public function testS20ExtractsData(): void
    {
        $r = new Result();
        $res = (new VarsExtractCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'no-clean' => '1'], $this->ctx($r));

        self::assertTrue($res->ok);

        $rootData = json_decode((string) file_get_contents($this->algoritm . '/algoritm.data.json'), true);
        self::assertIsArray($rootData);
        self::assertSame(['', 'about', 'docs'], $rootData['available']);
        self::assertFalse($rootData['auth']);
        self::assertTrue($rootData['search']);
        self::assertFalse($rootData['default']);

        $aboutData = json_decode((string) file_get_contents($this->algoritm . '/about/algoritm_about.data.json'), true);
        self::assertIsArray($aboutData);
        self::assertTrue($aboutData['auth']);
        self::assertFalse($aboutData['search']);
        self::assertTrue($aboutData['default']);
    }

    public function testS20Idempotent(): void
    {
        $paths = new Paths($this->root);
        $cmd = new VarsExtractCommand($paths);

        $r1 = new Result();
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'no-clean' => '1'], $this->ctx($r1));

        $r2 = new Result();
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'no-clean' => '1'], $this->ctx($r2));

        self::assertStringContainsString('создано 0', $r2->steps[0]['message']);
        self::assertStringContainsString('пропущено 2', $r2->steps[0]['message']);
    }

    public function testS20CleanRemovesProperties(): void
    {
        $r = new Result();
        (new VarsExtractCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www'], $this->ctx($r));

        $php = (string) file_get_contents($this->algoritm . '/about/algoritm_about.php');
        self::assertStringNotContainsString('public array $available', $php);
        self::assertStringNotContainsString('public bool $auth', $php);
    }

    public function testS20DryDoesNotWrite(): void
    {
        $r = new Result();
        (new VarsExtractCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'www', 'dry' => '1'], $this->ctx($r));

        self::assertFileDoesNotExist($this->algoritm . '/algoritm.data.json');
        self::assertFileDoesNotExist($this->algoritm . '/about/algoritm_about.data.json');
    }

    public function testS20MissingBase(): void
    {
        $r = new Result();
        $res = (new VarsExtractCommand(new Paths($this->root)))
            ->run(['domain' => 'example.ru', 'allias' => 'ghost'], $this->ctx($r));
        self::assertFalse($res->ok);
    }
}