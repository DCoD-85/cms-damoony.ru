<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\StructureRepairCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class StructureRepairCommandTest extends TestCase
{
    private string $tmpDir;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/damoony_repair_' . uniqid();
        mkdir($this->tmpDir, 0o777, true);
        mkdir($this->tmpDir . '/config', 0o777, true);
        mkdir($this->tmpDir . '/app', 0o777, true);

        $this->paths = new Paths($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
    }

    public function testMissingDomain(): void
    {
        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run([], new Context(static fn () => null));

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Укажите --domain', $res->errors[0]);
    }

    public function testRejectsPathTraversal(): void
    {
        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'a..ru'], new Context(static fn () => null));

        $this->assertFalse($res->ok);
    }

    public function testMissingSiteConfig(): void
    {
        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('site.config не найден', $res->errors[0]);
    }

    public function testDomainNotFoundInConfig(): void
    {
        $this->writeSiteConfig([]);
        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('Домен не найден', $res->errors[0]);
    }

    public function testRestoresMissingAlias(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok, implode('; ', $res->errors));

        $dir = $this->tmpDir . '/app/example.ru/www/algoritm';
        $this->assertDirectoryExists($dir);

        // Новая модель: algoritm.rules.json + algoritm.editor.json (правило 3 файлов)
        $this->assertFileExists($dir . '/algoritm.php');
        $this->assertFileExists($dir . '/algoritm.rules.json');
        $this->assertFileExists($dir . '/algoritm.editor.json');

        // Старой модели быть не должно
        $this->assertFileDoesNotExist($dir . '/.rules.json');

        // rules содержит полные дефолты
        $rules = json_decode((string) file_get_contents($dir . '/algoritm.rules.json'), true);
        $this->assertIsArray($rules);
        $this->assertArrayHasKey('available', $rules);
        $this->assertArrayHasKey('auth', $rules);
        $this->assertArrayHasKey('search', $rules);
        $this->assertArrayHasKey('default', $rules);

        // PHP-код должен быть валидным (PageCompiler генерирует extends Page без битого use)
        $php = (string) file_get_contents($dir . '/algoritm.php');
        $this->assertStringContainsString('extends Page', $php);
        $this->assertStringContainsString('namespace example_ru\\www;', $php);
        $this->assertStringNotContainsString('Damoony\\Algoritm\\Algoritm;', $php);
    }

    public function testRestoresMissingSchemeRecord(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->tmpDir . '/app/example.ru/.schemesglobal', 0o777, true);
        file_put_contents($this->tmpDir . '/app/example.ru/.schemesglobal/test.php', '<?php');

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok, implode('; ', $res->errors));
        $this->assertFileExists($this->tmpDir . '/app/example.ru/.schemesglobal/test.rules.json');
    }

    public function testRestoresMissingBlockRecord(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->tmpDir . '/app/example.ru/.blocksglobal/top', 0o777, true);

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok, implode('; ', $res->errors));
        $this->assertFileExists($this->tmpDir . '/app/example.ru/.blocksglobal/top/top.rules.json');
        $this->assertFileExists($this->tmpDir . '/app/example.ru/.blocksglobal/top/top.editor.json');
    }

    public function testStructureOk(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);

        $dir = $this->tmpDir . '/app/example.ru/www/algoritm';
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/algoritm.php', "<?php\n");
        file_put_contents(
            $dir . '/algoritm.rules.json',
            json_encode(['available' => [], 'auth' => false, 'search' => true, 'default' => false]),
        );
        file_put_contents($dir . '/algoritm.editor.json', "[]\n");

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        // Лишних файлов не создано
        $this->assertFileDoesNotExist($this->tmpDir . '/app/example.ru/.schemesglobal/schemes.json');
        $this->assertFileDoesNotExist($dir . '/.rules.json');
    }

    public function testIdempotentOnSecondRun(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);

        $cmd = new StructureRepairCommand($this->paths);
        $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        // Второй запуск — без изменений (все шаги нашли файлы на месте)
    }

    private function writeSiteConfig(array $data): void
    {
        file_put_contents(
            $this->tmpDir . '/config/site.config',
            json_encode($data, JSON_PRETTY_PRINT),
        );
    }

    private function rmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
