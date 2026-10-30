<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Diagnostics;

use Damoony\Core\Config;
use Damoony\Diagnostics\StructureChecker;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class StructureCheckerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_chk_' . bin2hex(random_bytes(4));
        $this->setupValidDomain();
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    private function setupValidDomain(): void
    {
        $domain = $this->root . '/app/example.ru';
        mkdir($domain . '/www/algoritm', 0o777, true);
        mkdir($domain . '/.alliasglobal', 0o777, true);
        mkdir($domain . '/.alliasscheme', 0o777, true);

        file_put_contents($domain . '/www/algoritm/.rules.json', json_encode(['available' => []]));
        file_put_contents($domain . '/www/algoritm/algoritm.php', "<?php\n");
        file_put_contents($domain . '/.alliasscheme/www.php', "<?php\nreturn ['tag' => 'div', 'children' => []];\n");
        file_put_contents($domain . '/.alliasscheme/www.rules.json', json_encode(['enabled' => true]));
    }

    private function checker(): StructureChecker
    {
        $config = Config::fromArray([
            'domains_real' => ['example.ru' => 'example.ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);

        return new StructureChecker(new Paths($this->root), $config);
    }

    public function testCleanStructureHasNoIssues(): void
    {
        $this->writeBlockFiles('top');
        $issues = $this->checker()->checkAllias('example.ru', 'www');
        self::assertSame([], $issues);
    }

    public function testFlagsMissingRulesJson(): void
    {
        mkdir($this->root . '/app/example.ru/.alliasglobal/top', 0o777, true);
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.php', "<?php\n");

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'Отсутствует top.rules.json')));

        self::assertCount(1, $match);
        self::assertSame('warning', $match[0]['level']);
    }

    public function testFlagsMissingEditorJson(): void
    {
        mkdir($this->root . '/app/example.ru/.alliasglobal/top', 0o777, true);
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.php', "<?php\n");
        file_put_contents($this->root . '/app/example.ru/.alliasglobal/top/top.rules.json', '{"enabled":true,"type":"mixed"}');

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'Отсутствует top.editor.json')));

        self::assertCount(1, $match);
        self::assertSame('warning', $match[0]['level']);
    }

    private function writeBlockFiles(string $name): void
    {
        $dir = $this->root . '/app/example.ru/.alliasglobal/' . $name;
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/' . $name . '.php', "<?php\n");
        file_put_contents($dir . '/' . $name . '.rules.json', '{"enabled":true,"type":"mixed"}');
        file_put_contents($dir . '/' . $name . '.editor.json', '{}');
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
