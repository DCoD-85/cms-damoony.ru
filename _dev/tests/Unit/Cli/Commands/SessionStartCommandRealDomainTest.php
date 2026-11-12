<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\SessionStartCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SessionStartCommandRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_sessstart_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/www/media', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                // non-self-mapping: alias.ru -> real.ru
                'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            ], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testSelfMappedDomainUsesOwnDir(): void
    {
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // self\n");

        new SessionStartCommand($this->paths)->run(
            ['domain' => 'real.ru', 'tab' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertDirectoryExists($this->root . '/app/.sess_abcdef01.real.ru');
    }

    public function testNonSelfMappedDomainUsesRealDirNotAliasDir(): void
    {
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // real\n");

        new SessionStartCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'tab' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertDirectoryExists(
            $this->root . '/app/.sess_abcdef01.real.ru',
            'сессия должна создаться в .sess_*.real.ru',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/.sess_abcdef01.alias.ru',
            'сессия НЕ должна создаваться в .sess_*.alias.ru',
        );
        $this->assertFileExists(
            $this->root . '/app/.sess_abcdef01.real.ru/www/algoritm/algoritm.php',
            'в сессии должен быть контент из real.ru, а не пусто',
        );
    }

    public function testUnknownDomainRejected(): void
    {
        new SessionStartCommand($this->paths)->run(
            ['domain' => 'unknown.ru', 'tab' => 'abcdef01'],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/app/.sess_abcdef01.unknown.ru');
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
