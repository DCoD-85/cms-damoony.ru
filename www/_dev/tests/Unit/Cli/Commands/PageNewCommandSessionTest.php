<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageNewCommandSessionTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pagenew_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['example.ru' => 'example.ru'],
                'domains_namespace' => ['example.ru' => 'example_ru'],
                'alliases_real' => ['example.ru' => ['www' => 'www']],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testWithoutSessionCreatesInProduction(): void
    {
        $this->seedRoot('app/example.ru');

        new PageNewCommand($this->paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/app/example.ru/www/algoritm/about/algoritm_about.php',
        );
        $this->assertFileDoesNotExist(
            $this->root . '/app/.sess_abcdef01.example.ru/www/algoritm/about/algoritm_about.php',
        );
    }

    public function testWithSessionCreatesInSessionNotProduction(): void
    {
        $this->seedRoot('app/example.ru');
        $this->seedRoot('app/.sess_abcdef01.example.ru');
        file_put_contents(
            $this->root . '/app/.sess_abcdef01.example.ru/.session.json',
            json_encode(['permissions' => []], JSON_THROW_ON_ERROR),
        );

        new PageNewCommand($this->paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about', 'session' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/app/.sess_abcdef01.example.ru/www/algoritm/about/algoritm_about.php',
            'страница должна появиться в session',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/example.ru/www/algoritm/about',
            'production не должен меняться',
        );
    }

    public function testInvalidSessionFormatRejected(): void
    {
        $this->seedRoot('app/example.ru');

        new PageNewCommand($this->paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about', 'session' => 'bad!'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/example.ru/www/algoritm/about',
            'production не должен меняться',
        );
    }

    public function testMissingSessionDirRejected(): void
    {
        $this->seedRoot('app/example.ru');

        new PageNewCommand($this->paths)->run(
            ['domain' => 'example.ru', 'allias' => 'www', 'name' => 'about', 'session' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/example.ru/www/algoritm/about',
            'production не должен меняться',
        );
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }

    /** Создаёт root-страницу алиаса: algoritm/algoritm.php (родитель для page:new). */
    private function seedRoot(string $appRel): void
    {
        $dir = $this->root . '/' . $appRel . '/www/algoritm';
        mkdir($dir, 0o775, true);
        file_put_contents($dir . '/algoritm.php', "<?php // stub root\n");
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
