<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class PageCommandsRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_pagereal_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
                'domains_namespace' => ['real.ru' => 'real_ru'],
                'alliases_real' => [
                    'real.ru' => ['www' => 'www'],
                    'alias.ru' => ['www' => 'www'],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        // root production
        $this->seedRoot('app/real.ru');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    // --- page:new ---

    public function testPageNewWithAliasDomainCreatesInRealDir(): void
    {
        new PageNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'name' => 'about'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $this->root . '/app/real.ru/www/algoritm/about/algoritm_about.php',
            'страница должна создаться в app/real.ru/',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/alias.ru',
            'app/alias.ru/ не должно появляться',
        );
    }

    public function testPageNewSessionWithAliasDomainUsesRealSessionDir(): void
    {
        $sessRoot = $this->root . '/app/.sess_abcdef01.real.ru';
        $this->seedRoot('app/.sess_abcdef01.real.ru');
        file_put_contents(
            $sessRoot . '/.session.json',
            json_encode(['permissions' => []], JSON_THROW_ON_ERROR),
        );

        new PageNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'allias' => 'www', 'name' => 'about', 'session' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertFileExists(
            $sessRoot . '/www/algoritm/about/algoritm_about.php',
            'страница должна создаться в session real-домена',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/real.ru/www/algoritm/about',
            'production не должен меняться',
        );
    }

    // --- page:write ---

    public function testPageWriteWithAliasDomainWritesToRealDir(): void
    {
        // создаём страницу напрямую, минуя page:new
        mkdir($this->root . '/app/real.ru/www/algoritm/about', 0o775, true);
        $target = $this->root . '/app/real.ru/www/algoritm/about/algoritm_about.php';
        file_put_contents($target, "<?php // stub\n");
        $before = (string) file_get_contents($target);

        new PageWriteCommand($this->paths)->run(
            [
                'domain' => 'alias.ru',
                'allias' => 'www',
                'name' => 'about',
                'json' => '[{"type":"text","content":"hello"}]',
            ],
            $this->ctx(),
        );

        $after = (string) file_get_contents($target);
        $this->assertNotSame($before, $after, 'production (real.ru) должен обновиться');
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/alias.ru',
            'app/alias.ru/ не должно появляться',
        );
    }

    public function testPageWriteUnknownDomainRejected(): void
    {
        new PageWriteCommand($this->paths)->run(
            [
                'domain' => 'unknown.ru',
                'allias' => 'www',
                'name' => 'about',
                'json' => '[]',
            ],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/app/unknown.ru');
    }

    // --- helpers ---

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
    }

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
