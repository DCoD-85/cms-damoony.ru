<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\NewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use PHPUnit\Framework\TestCase;

final class NewCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_new_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/stubs/assemblies/demo/app/.blocksglobal/top', 0o775, true);
        file_put_contents($this->root . '/stubs/assemblies/demo/assembly.json', json_encode(['name' => 'Demo']));

        $block = $this->root . '/stubs/assemblies/demo/app/.blocksglobal/top/top.php';
        file_put_contents($block, "<?php\nnamespace {{namespace}};\nfinal class blocksglobal_top {}\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testForceRemovesExistingProjectFiles(): void
    {
        $oldApp = $this->root . '/app/demo.test';
        $oldMedia = $this->root . '/www/media/demo.test/www';
        mkdir($oldApp, 0o775, true);
        mkdir($oldMedia, 0o775, true);
        file_put_contents($oldApp . '/stale.php', 'stale');
        file_put_contents($oldMedia . '/stale.css', 'stale');

        $result = $this->command()->run([
            'domain' => 'demo.test',
            'assembly' => 'demo',
            'force' => '1',
        ], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileDoesNotExist($oldApp . '/stale.php');
        self::assertFileDoesNotExist($oldMedia . '/stale.css');
        self::assertFileExists($oldApp . '/.blocksglobal/top/top.php');

        $site = SiteConfig::fromFile($this->root . '/config/site.config');
        self::assertNotNull($site->project('demo.test'));
    }

    public function testWithoutForceExistingFilesRemain(): void
    {
        $oldApp = $this->root . '/app/demo.test';
        mkdir($oldApp, 0o775, true);
        file_put_contents($oldApp . '/stale.php', 'stale');

        $result = $this->command()->run([
            'domain' => 'demo.test',
            'assembly' => 'demo',
        ], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertFileExists($oldApp . '/stale.php');
    }

    private function command(): NewCommand
    {
        return new NewCommand(
            new Paths($this->root),
            SiteConfig::empty(),
            SiteConfigWriter::forRoot($this->root),
        );
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
