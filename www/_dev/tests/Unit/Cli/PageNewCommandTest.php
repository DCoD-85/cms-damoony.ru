<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageNewCommandTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_page_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        mkdir($this->root . '/app/example.ru/www/algoritm', 0o777, true);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));

        // Root stub по новой модели (создаётся allias:new)
        $algoritm = $this->root . '/app/example.ru/www/algoritm';
        file_put_contents(
            $algoritm . '/algoritm.php',
            "<?php\n\ndeclare(strict_types=1);\n\nnamespace example_ru\\www;\n\nuse Damoony\\Algoritm\\Page;\n",
        );
        file_put_contents(
            $algoritm . '/algoritm.rules.json',
            json_encode(['available' => [], 'auth' => false]),
        );

        $this->paths = new Paths($this->root);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testCreatesPageFiles(): void
    {
        $cmd = new PageNewCommand($this->paths);
        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            new Context(static fn () => null),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->root . '/app/example.ru/www/algoritm/about';
        self::assertDirectoryExists($dir);
        self::assertFileExists($dir . '/algoritm_about.php');
        self::assertFileExists($dir . '/algoritm_about.rules.json');
        self::assertFileExists($dir . '/algoritm_about.editor.json');

        $php = (string) file_get_contents($dir . '/algoritm_about.php');
        self::assertStringContainsString('extends Page', $php);
        self::assertStringContainsString('final class algoritm_about', $php);

        // rules.json создаётся с полными дефолтами
        $rules = json_decode((string) file_get_contents($dir . '/algoritm_about.rules.json'), true);
        self::assertIsArray($rules);
        self::assertArrayHasKey('available', $rules);
        self::assertArrayHasKey('auth', $rules);
    }

    public function testUpdatesParentRules(): void
    {
        $cmd = new PageNewCommand($this->paths);
        $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            new Context(static fn () => null),
        );

        // Новая модель: root rules — в algoritm/algoritm.rules.json
        $rulesFile = $this->root . '/app/example.ru/www/algoritm/algoritm.rules.json';
        $rules = json_decode((string) file_get_contents($rulesFile), true);

        self::assertArrayHasKey('about', $rules['available']);
    }

    public function testNestedPage(): void
    {
        $cmd = new PageNewCommand($this->paths);

        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'docs'], new Context(static fn () => null));

        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'install', 'parent' => 'docs'],
            new Context(static fn () => null),
        );

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertFileExists($this->root . '/app/example.ru/www/algoritm/docs/install/algoritm_docs_install.php');

        // Родительские правила docs узнали о ребёнке — в algoritm_docs.rules.json
        $docsRules = json_decode(
            (string) file_get_contents($this->root . '/app/example.ru/www/algoritm/docs/algoritm_docs.rules.json'),
            true,
        );
        self::assertArrayHasKey('install', $docsRules['available']);
    }

    public function testRejectsDuplicate(): void
    {
        $cmd = new PageNewCommand($this->paths);
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], new Context(static fn () => null));

        $res = $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], new Context(static fn () => null));

        self::assertFalse($res->ok);
        self::assertStringContainsString('Страница уже существует', $res->errors[0]);
    }

    public function testRejectsPathTraversal(): void
    {
        $cmd = new PageNewCommand($this->paths);

        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => '../../etc/passwd'],
            new Context(static fn () => null),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
    }

    public function testRejectsMissingParent(): void
    {
        $cmd = new PageNewCommand($this->paths);

        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about', 'parent' => 'ghost'],
            new Context(static fn () => null),
        );

        self::assertFalse($res->ok);
        self::assertStringContainsString('Родительская папка не найдена', $res->errors[0]);
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
}
