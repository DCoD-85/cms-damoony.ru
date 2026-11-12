<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\DomainRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

/**
 * DomainRemove — контракт non-self-mapping.
 * Код НЕ патчился: guard'ы уже на месте, тесты фиксируют поведение.
 */
final class DomainRemoveCommandRealDomainTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_domainrm_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/www/media', 0o775, true);
        $this->paths = new Paths($this->root);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testMappedAliasRemovedFromConfigOnly(): void
    {
        $this->seedSite([
            'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
            'alliases_real' => ['real.ru' => ['www' => 'www']],
        ]);
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // prod\n");

        new DomainRemoveCommand($this->paths)->run(
            ['domain' => 'alias.ru', 'force' => true],
            $this->ctx(),
        );

        $this->assertDirectoryExists(
            $this->root . '/app/real.ru/www/algoritm',
            'при удалении alias папка real не должна трогаться',
        );
        $site = $this->loadSite();
        $this->assertArrayNotHasKey('alias.ru', $site['domains_real'] ?? []);
        $this->assertArrayHasKey('real.ru', $site['domains_real'] ?? []);
    }

    public function testRealDomainWithAttachedAliasRejected(): void
    {
        $this->seedSite([
            'domains_real' => ['real.ru' => 'real.ru', 'alias.ru' => 'real.ru'],
        ]);
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // prod\n");

        new DomainRemoveCommand($this->paths)->run(
            ['domain' => 'real.ru', 'force' => true],
            $this->ctx(),
        );

        $this->assertDirectoryExists(
            $this->root . '/app/real.ru/www/algoritm',
            'real с привязанным alias не должен удаляться',
        );
        $site = $this->loadSite();
        $this->assertArrayHasKey('real.ru', $site['domains_real'] ?? []);
        $this->assertArrayHasKey('alias.ru', $site['domains_real'] ?? []);
    }

    public function testRealDomainWithoutAttachedAliasMovedToTrash(): void
    {
        $this->seedSite([
            'domains_real' => ['real.ru' => 'real.ru'],
        ]);
        mkdir($this->root . '/app/real.ru/www/algoritm', 0o775, true);
        file_put_contents($this->root . '/app/real.ru/www/algoritm/algoritm.php', "<?php // prod\n");

        new DomainRemoveCommand($this->paths)->run(
            ['domain' => 'real.ru', 'force' => true],
            $this->ctx(),
        );

        $this->assertDirectoryDoesNotExist($this->root . '/app/real.ru');
        $trash = glob($this->root . '/app/real.ru_trash_*') ?: [];
        $this->assertNotEmpty($trash, 'папка должна быть в trash');
        $site = $this->loadSite();
        $this->assertArrayNotHasKey('real.ru', $site['domains_real'] ?? []);
    }

    /** @param array<string, mixed> $site */
    private function seedSite(array $site): void
    {
        file_put_contents(
            $this->root . '/config/site.config',
            json_encode($site, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
        );
    }

    /** @return array<string, mixed> */
    private function loadSite(): array
    {
        $data = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        return is_array($data) ? $data : [];
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
