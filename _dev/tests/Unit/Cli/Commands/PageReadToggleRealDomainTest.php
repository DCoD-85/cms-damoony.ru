<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\PageReadCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageReadToggleRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pageread_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    // --- page:read ---

    public function testPageReadWithAliasDomainReadsRealDir(): void
    {
        $dir = $this->root . '/app/real.ru/www/algoritm/about';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/algoritm_about.php', "<?php // marker-from-real\n");

        $captured = '';
        $ctx = new Context(static function (string $m, bool $ok = true, string $d = '') use (&$captured): void {
            $captured = $m;
        });

        new PageReadCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'name' => 'about'],
            $ctx,
        );

        $this->assertStringContainsString('marker-from-real', $captured, 'должен прочитать файл из real.ru');
    }

    public function testPageReadUnknownDomainRejected(): void
    {
        new PageReadCommand($this->paths)->run(
            ['domain' => 'unknown.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(),
        );

        // Никаких assert на файл — просто убедиться, что не создан app/unknown.ru
        $this->assertDirectoryDoesNotExist($this->root . '/app/unknown.ru');
    }

    // --- page:toggle ---

    public function testPageToggleWithAliasDomainWritesToRealDir(): void
    {
        $dir = $this->root . '/app/real.ru/www/algoritm/about';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/algoritm_about.php', "<?php // stub\n");
        $rulesFile = $dir . '/algoritm_about.rules.json';
        file_put_contents($rulesFile, json_encode(['available' => [], 'auth' => false], JSON_THROW_ON_ERROR));

        new PageToggleCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->ctx(),
        );

        $after = json_decode((string) file_get_contents($rulesFile), true);
        $this->assertTrue($after['auth'] ?? false, 'rules.json в real.ru должен обновиться');
        $this->assertDirectoryDoesNotExist($this->root . '/app/alias.ru', 'app/alias.ru/ не должно появляться');
    }

    public function testPageToggleUnknownDomainRejected(): void
    {
        new PageToggleCommand($this->paths)->run(
            ['domain' => 'unknown.ru', 'allias' => 'www', 'name' => 'about', 'key' => 'auth', 'value' => '1'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/app/unknown.ru');
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
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
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
