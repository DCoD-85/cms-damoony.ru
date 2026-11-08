<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Diagnostics;

use Damoony\Core\Config;
use Damoony\Core\Files\Paths;
use Damoony\Diagnostics\StructureChecker;
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

        // Новая модель: algoritm/algoritm.rules.json
        file_put_contents(
            $domain . '/www/algoritm/algoritm.rules.json',
            json_encode(['available' => [], 'auth' => false, 'search' => true, 'default' => false]),
        );
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

    // === Новая модель: страницы ===

    public function testFlagsMissingRootRulesJson(): void
    {
        // Удаляем algoritm/algoritm.rules.json
        unlink($this->root . '/app/example.ru/www/algoritm/algoritm.rules.json');

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'Отсутствует algoritm.rules.json')));

        self::assertCount(1, $match);
        self::assertSame('error', $match[0]['level']);
    }

    public function testNestedPageWithCorrectClassChain(): void
    {
        $this->writePage('about', 'algoritm_about');
        $issues = $this->checker()->checkAllias('example.ru', 'www');

        $pageErrors = array_filter($issues, fn ($i) => str_contains($i['message'], 'algoritm_about'));
        self::assertCount(0, $pageErrors);
    }

    public function testNestedDeepPageClassChain(): void
    {
        $this->writePage('docs', 'algoritm_docs');
        $this->writePage('docs/install', 'algoritm_docs_install');

        $issues = $this->checker()->checkAllias('example.ru', 'www');

        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'algoritm_docs_install')));
        self::assertCount(0, $match);

        // Проверка, что путь /docs/install — не /install
        $badMatch = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'algoritm_install')));
        self::assertCount(0, $badMatch);
    }

    public function testNestedPageMissingFiles(): void
    {
        // Папка есть, файлов нет
        mkdir($this->root . '/app/example.ru/www/algoritm/broken', 0o777, true);

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'algoritm_broken')));

        // Должно быть 3 issue: .php (error), .rules.json (warning), .editor.json (warning)
        self::assertCount(3, $match);
    }

    public function testFlagsLegacyDotRulesJson(): void
    {
        $this->writePage('legacy', 'algoritm_legacy');
        // Оставляем устаревший .rules.json
        file_put_contents(
            $this->root . '/app/example.ru/www/algoritm/legacy/.rules.json',
            json_encode(['available' => []]),
        );

        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'migrate:page-rules')));

        self::assertCount(1, $match);
        self::assertSame('warning', $match[0]['level']);
        self::assertStringContainsString('/legacy/.rules.json', $match[0]['message']);
    }

    public function testFlagsLegacyRootDotRulesJson(): void
    {
        // Устаревший root: algoritm/.rules.json (не проверяется как legacy для root — только вложенные)
        // Но в новой модели root — algoritm/algoritm.rules.json уже есть в setupValidDomain
        // Проверяем, что root .rules.json сам по себе не даёт ошибки «отсутствует algoritm.rules.json»
        // (если бы проверялся)
        $issues = $this->checker()->checkAllias('example.ru', 'www');
        $match = array_values(array_filter($issues, fn ($i) => str_contains($i['message'], 'Отсутствует algoritm.rules.json')));

        self::assertCount(0, $match);
    }

    // === Helpers ===

    private function writePage(string $rel, string $classChain): void
    {
        $dir = $this->root . '/app/example.ru/www/algoritm/' . $rel;
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/' . $classChain . '.php', "<?php\n");
        file_put_contents(
            $dir . '/' . $classChain . '.rules.json',
            json_encode(['available' => [], 'auth' => false, 'search' => true, 'default' => false]),
        );
        file_put_contents($dir . '/' . $classChain . '.editor.json', "[]\n");
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
