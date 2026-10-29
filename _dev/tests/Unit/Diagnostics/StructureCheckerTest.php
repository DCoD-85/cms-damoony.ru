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
        file_put_contents($domain . '/.alliasscheme/www.data.php', "<?php\nreturn ['tag' => 'div', 'children' => []];\n");
        file_put_contents($domain . '/.alliasscheme/schemes.json', json_encode(['schemes' => ['www' => ['enabled' => true]]]));
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
        $this->writeBlocksJson(['top' => ['enabled' => true]]);
        mkdir($this->root . '/app/example.ru/.alliasglobal/top', 0o777, true);

        $issues = $this->checker()->checkAllias('example.ru', 'www');

        self::assertSame([], $issues);
    }

    public function testFlagsManifestEntryWithoutFolder(): void
    {
        $this->writeBlocksJson(['ghost' => ['enabled' => true]]);

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], "Блок 'ghost'")));

        self::assertCount(1, $match);
        self::assertSame('error', $match[0]['level']);
    }

    public function testFlagsFolderWithoutManifestEntry(): void
    {
        $this->writeBlocksJson([]);
        mkdir($this->root . '/app/example.ru/.alliasglobal/orphan', 0o777, true);

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'orphan')));

        self::assertCount(1, $match);
        self::assertSame('warning', $match[0]['level']);
    }

    public function testFlagsMalformedBlocksJson(): void
    {
        file_put_contents(
            $this->root . '/app/example.ru/.alliasglobal/blocks.json',
            '{"blocks": "not-an-array"}',
        );

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'blocks.json повреждён')));

        self::assertCount(1, $match);
        self::assertSame('warning', $match[0]['level']);
    }

    private function writeBlocksJson(array $blocks): void
    {
        file_put_contents(
            $this->root . '/app/example.ru/.alliasglobal/blocks.json',
            json_encode(['blocks' => $blocks]),
        );
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
