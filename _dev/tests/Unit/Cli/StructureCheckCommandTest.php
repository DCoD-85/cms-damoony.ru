<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\StructureCheckCommand;
use Damoony\Cli\Context;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class StructureCheckCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_chkcmd_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testChecksMappedAliasFolderNotKey(): void
    {
        $this->createAliasFolders('www');
        $cmd = new StructureCheckCommand(
            new Paths($this->root),
            $this->config(['sub' => 'www']),
        );

        $captured = [];
        $context = new Context(function (string $m, bool $ok, string $d) use (&$captured): void {
            $captured[] = ['message' => $m, 'ok' => $ok];
        });

        $result = $cmd->run([], $context);

        self::assertTrue($result->ok, 'Checker must resolve mapped alias "www", not key "sub"');
        self::assertSame([], $result->errors);

        $joined = implode(' ', array_column($captured, 'message'));
        self::assertStringNotContainsString('algoritm/sub', $joined);
        self::assertStringContainsString('/ www', $joined);
    }

    public function testIdentityMappingStillWorks(): void
    {
        $this->createAliasFolders('www');
        $cmd = new StructureCheckCommand(
            new Paths($this->root),
            $this->config(['www' => 'www']),
        );

        $result = $cmd->run([], new Context(function (): void {}));

        self::assertTrue($result->ok);
    }

    public function testEmptyKeyIsSkipped(): void
    {
        $this->createAliasFolders('www');
        $cmd = new StructureCheckCommand(
            new Paths($this->root),
            $this->config(['' => 'www']),
        );

        $captured = [];
        $context = new Context(function (string $m) use (&$captured): void {
            $captured[] = $m;
        });

        $result = $cmd->run([], $context);

        self::assertTrue($result->ok);
        $aliasLines = array_values(array_filter($captured, fn (string $m) => str_starts_with($m, 'Алиас:')));
        self::assertSame([], $aliasLines, 'Empty key must not trigger any per-alias check');
    }

    private function createAliasFolders(string $alias): void
    {
        $domain = $this->root . '/app/example.ru';
        mkdir($domain . '/' . $alias . '/algoritm', 0o777, true);
        mkdir($domain . '/.alliasglobal', 0o777, true);
        mkdir($domain . '/.alliasscheme', 0o777, true);

        file_put_contents($domain . '/' . $alias . '/algoritm/.rules.json', (string) json_encode(['available' => []]));
        file_put_contents($domain . '/' . $alias . '/algoritm/algoritm.php', "<?php\n");
        file_put_contents(
            $domain . '/.alliasscheme/www.data.php',
            "<?php\nreturn ['tag' => 'div', 'children' => []];\n",
        );
        file_put_contents(
            $domain . '/.alliasscheme/schemes.json',
            (string) json_encode(['schemes' => ['www' => ['enabled' => true]]]),
        );
    }

    /** @param array<string, string> $alliases */
    private function config(array $alliases): Config
    {
        return Config::fromArray([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => $alliases],
        ]);
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
            is_dir($p) ? $this->rmrf($p) : unlink($p);
        }
        rmdir($dir);
    }
}
