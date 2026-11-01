<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DomainRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainRemoveCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_domain_rm_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        mkdir($this->root . '/app/example.ru', 0o777, true);
        file_put_contents($this->root . '/app/example.ru/test.php', '<?php');

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'domains_titles' => ['example.ru' => 'Example'],
            'domains_skins' => ['example.ru' => 'default'],
            'domains_skins_allowed' => ['example.ru' => ['default']],
            'domains_stubs' => ['example.ru' => '503'],
            'domains_scheme' => ['example.ru' => 'www'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
            'alliases_available' => ['example.ru' => ['www' => true]],
            'alliases_langs' => ['example.ru' => ['www' => ['ru']]],
            'alliases_titles' => ['example.ru' => ['www' => 'Main']],
            'alliases_stubs' => ['example.ru' => ['www' => '503']],
            'alliases_skins' => ['example.ru' => ['www' => 'default']],
            'alliases_skins_allowed' => ['example.ru' => ['www' => ['default']]],
            'fillers' => ['example.ru' => ['www' => ['top' => true]]],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testRemovesDomain(): void
    {
        $paths = new Paths($this->root);
        $cmd = new DomainRemoveCommand($paths);

        $res = $cmd->run(['domain' => 'example.ru', 'force' => '1'], new Context(static fn () => null));

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($this->root . '/app/example.ru');

        $dirs = glob($this->root . '/app/example.ru_trash_*');
        self::assertNotEmpty($dirs);

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertArrayNotHasKey('example.ru', $site['domains_real']);
    }

    public function testCleansAllConfigKeys(): void
    {
        $cmd = new DomainRemoveCommand(new Paths($this->root));
        $cmd->run(['domain' => 'example.ru', 'force' => '1'], new Context(static fn () => null));

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);

        $keys = [
            'domains_real', 'domains_available', 'domains_namespace',
            'domains_titles', 'domains_skins', 'domains_skins_allowed', 'domains_stubs', 'domains_scheme',
            'alliases_real', 'alliases_available', 'alliases_langs', 'alliases_titles',
            'alliases_stubs', 'alliases_skins', 'alliases_skins_allowed', 'fillers',
        ];
        foreach ($keys as $k) {
            self::assertArrayNotHasKey('example.ru', $site[$k] ?? [], "Ключ {$k} не очищен");
        }
    }

    public function testRequiresForce(): void
    {
        $cmd = new DomainRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testIdempotentOnMissingDomain(): void
    {
        $cmd = new DomainRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'ghost.ru', 'force' => '1'], new Context(static fn () => null));

        self::assertTrue($res->ok);
        self::assertNotEmpty($res->warnings);
    }

    public function testRejectsPathTraversal(): void
    {
        $cmd = new DomainRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'a..ru', 'force' => '1'], new Context(static fn () => null));

        self::assertFalse($res->ok);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : unlink($p);
        }
        rmdir($dir);
    }
}
