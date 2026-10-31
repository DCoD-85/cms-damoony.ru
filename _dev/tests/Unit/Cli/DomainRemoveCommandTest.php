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
            'domains_namespace' => ['example.ru' => 'example_ru'],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
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

        $res = $cmd->run(['domain' => 'example.ru', 'force' => true], new Context(static fn () => null));

        $this->assertTrue($res->ok);
        $this->assertDirectoryDoesNotExist($this->root . '/app/example.ru');

        // Проверяем, что папка ушла в карантин
        $dirs = glob($this->root . '/app/example.ru_trash_*');
        $this->assertNotEmpty($dirs);

        // Проверяем, что конфиг очищен
        $site = json_decode((string)file_get_contents($this->root . '/config/site.config'), true);
        $this->assertArrayNotHasKey('example.ru', $site['domains_real']);
    }

    public function testRequiresForce(): void
    {
        $cmd = new DomainRemoveCommand(new Paths($this->root));
        $res = $cmd->run(['domain' => 'example.ru'], new Context(static fn () => null));

        $this->assertFalse($res->ok);
        $this->assertStringContainsString('укажите --force', $res->errors[0]);
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
