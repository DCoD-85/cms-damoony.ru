<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\MigratePageRulesCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class MigratePageRulesCommandTest extends TestCase
{
    private string $tmpDir;
    private Paths $paths;
    private Context $context;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/damoony_migrate_test_' . uniqid();
        mkdir($this->tmpDir . '/app/example.ru/www/algoritm/about', 0o777, true);

        // Создаём старый формат правил
        file_put_contents($this->tmpDir . '/app/example.ru/www/algoritm/.rules.json', '{"available":["about"]}');
        file_put_contents($this->tmpDir . '/app/example.ru/www/algoritm/about/.rules.json', '{"auth":true}');
        // Создаём php файл, чтобы сработало создание editor.json
        file_put_contents($this->tmpDir . '/app/example.ru/www/algoritm/about/algoritm_about.php', '<?php');

        $this->paths = new Paths($this->tmpDir);
        $this->context = new Context(fn () => null);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file") ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    public function testSuccessfulMigration(): void
    {
        $cmd = new MigratePageRulesCommand($this->paths);
        $result = $cmd->run([], $this->context);

        $this->assertTrue($result->ok);

        $rootRules = $this->tmpDir . '/app/example.ru/www/algoritm/algoritm.rules.json';
        $aboutRules = $this->tmpDir . '/app/example.ru/www/algoritm/about/algoritm_about.rules.json';
        $aboutEditor = $this->tmpDir . '/app/example.ru/www/algoritm/about/algoritm_about.editor.json';

        $this->assertFileExists($rootRules);
        $this->assertFileExists($aboutRules);
        $this->assertFileExists($aboutEditor);

        $this->assertFileDoesNotExist($this->tmpDir . '/app/example.ru/www/algoritm/.rules.json');
        $this->assertFileDoesNotExist($this->tmpDir . '/app/example.ru/www/algoritm/about/.rules.json');
    }
}
