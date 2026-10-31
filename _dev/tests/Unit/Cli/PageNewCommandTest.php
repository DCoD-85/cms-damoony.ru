<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
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

        // Базовый конфиг
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]));

        // Корневой .rules.json
        file_put_contents($this->root . '/app/example.ru/www/algoritm/.rules.json', json_encode(['available' => ['']]));

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

        $this->assertTrue($res->ok);

        $dir = $this->root . '/app/example.ru/www/algoritm/about';
        $this->assertDirectoryExists($dir);
        $this->assertFileExists($dir . '/algoritm_about.php');
        $this->assertFileExists($dir . '/algoritm_about.rules.json');
        $this->assertFileExists($dir . '/algoritm_about.editor.json');

        // Проверяем, что класс наследуется от Page
        $php = file_get_contents($dir . '/algoritm_about.php');
        $this->assertStringContainsString('extends Page', $php);
        $this->assertStringContainsString('final class algoritm_about', $php);
    }

    public function testUpdatesParentRules(): void
    {
        $cmd = new PageNewCommand($this->paths);
        $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            new Context(static fn () => null),
        );

        $rulesFile = $this->root . '/app/example.ru/www/algoritm/.rules.json';
        $rules = json_decode((string) file_get_contents($rulesFile), true);

        $this->assertContains('about', $rules['available']);
    }

    public function testNestedPage(): void
    {
        $cmd = new PageNewCommand($this->paths);

        // Сначала создаём родителя
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'docs'], new Context(static fn () => null));

        // Затем вложенную
        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'install', 'parent' => 'docs'],
            new Context(static fn () => null),
        );

        $this->assertTrue($res->ok);
        $this->assertFileExists($this->root . '/app/example.ru/www/algoritm/docs/install/algoritm_docs_install.php');

        // Проверяем, что родитель docs узнал о ребёнке install
        $docsRules = json_decode((string) file_get_contents($this->root . '/app/example.ru/www/algoritm/docs/.rules.json'), true);
        $this->assertContains('install', $docsRules['available']);
    }

    public function testRejectsDuplicate(): void
    {
        $cmd = new PageNewCommand($this->paths);
        $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], new Context(static fn () => null));

        $res = $cmd->run(['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'], new Context(static fn () => null));

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Страница уже существует', $res->errors[0]);
    }

    public function testRejectsPathTraversal(): void
    {
        $cmd = new PageNewCommand($this->paths);

        // Пытаемся создать страницу с именем, содержащим слеши и точки
        $res = $cmd->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => '../../etc/passwd'],
            new Context(static fn () => null),
        );

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Некорректное имя страницы', $res->errors[0]);
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
