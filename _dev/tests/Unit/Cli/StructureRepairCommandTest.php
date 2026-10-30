<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\StructureRepairCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
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

        $this->assertTrue($res->ok);
        $this->assertDirectoryExists($this->tmpDir . '/app/example.ru/www/algoritm');
        $this->assertFileExists($this->tmpDir . '/app/example.ru/www/algoritm/.rules.json');
        $this->assertFileExists($this->tmpDir . '/app/example.ru/www/algoritm/algoritm.php');
    }

    public function testRestoresMissingSchemeRecord(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->tmpDir . '/app/example.ru/.alliasscheme', 0o777, true);
        file_put_contents($this->tmpDir . '/app/example.ru/.alliasscheme/test.data.php', '<?php');
        file_put_contents($this->tmpDir . '/app/example.ru/.alliasscheme/schemes.json', '{"schemes":{}}');

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        $json = json_decode((string)file_get_contents($this->tmpDir . '/app/example.ru/.alliasscheme/schemes.json'), true);
        $this->assertArrayHasKey('test', $json['schemes']);
    }

    public function testRestoresMissingBlockRecord(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->tmpDir . '/app/example.ru/.alliasglobal/top', 0o777, true);

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        $json = json_decode((string)file_get_contents($this->tmpDir . '/app/example.ru/.alliasglobal/blocks.json'), true);
        $this->assertArrayHasKey('top', $json['blocks']);
    }

    public function testStructureOk(): void
    {
        $this->writeSiteConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->tmpDir . '/app/example.ru/www/algoritm', 0o777, true);
        file_put_contents($this->tmpDir . '/app/example.ru/www/algoritm/.rules.json', '{}');

        $cmd = new StructureRepairCommand($this->paths);
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        // Проверяем, что ничего лишнего не создалось, кроме отсутствовавших папок
        $this->assertFileExists($this->tmpDir . '/app/example.ru/.alliasscheme/schemes.json');
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
