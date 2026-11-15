<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AlliasCommandsRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_allias_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/www/media', 0o775, true);
        $this->paths = new Paths($this->root);

        file_put_contents(
            $this->root . '/config/site.config',
            json_encode([
                'domains_real' => [
                'real.ru' => 'real_ru',
            ],
            'domains_mirrors' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
                'domains_namespace' => ['real.ru' => 'real_ru'],
                'alliases' => ['real.ru' => ['www' => 'www']],
            ], JSON_THROW_ON_ERROR),
        );

        // production: app/real.ru/www уже есть (root)
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // stub root\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testAlliasNewWithAliasDomainCreatesInRealDir(): void
    {
        new AlliasNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'name' => 'doc'],
            $this->ctx(),
        );

        $this->assertDirectoryExists(
            $this->root . '/app/real.ru/doc/algoritm',
            'алиас должен создаться в app/real.ru/',
        );
        $this->assertFileExists(
            $this->root . '/app/real.ru/doc/algoritm/algoritm.php',
        );
        $this->assertDirectoryDoesNotExist(
            $this->root . '/app/alias.ru',
            'app/alias.ru/ не должно появляться',
        );

        // Конфиг: ключ под realDomain
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        $this->assertTrue(
            $site['alliases']['real.ru']['doc']['available'] ?? false,
            'конфиг должен содержать alliases_available[real.ru][doc]',
        );
        $this->assertArrayNotHasKey(
            'alias.ru',
            $site['alliases'] ?? [],
            'конфиг НЕ должен содержать alliases_available[alias.ru]',
        );
    }



    /**
     * Регресс-тест: AlliasRemove намеренно отклоняет non-self-mapped.
     * Это ПРАВИЛЬНОЕ поведение — фиксируем контракт.
     */


    public function testAlliasRootRulesHaveEmptyPathMapping(): void
    {
        new AlliasNewCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'name' => 'doc'],
            $this->ctx(),
        );

        $rulesFile = $this->root . '/app/real.ru/doc/algoritm/algoritm.rules.json';
        $this->assertFileExists($rulesFile);

        $rules = json_decode((string) file_get_contents($rulesFile), true);
        $this->assertSame(
            '^$',
            $rules['available']['']
                ?? null,
            'allias root rules MUST contain available[""] = "^$"',
        );
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
