<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainNewCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_dn_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => [],
            'domains_available' => [],
            'domains_namespace' => [],
            'alliases_real' => [],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
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
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function execute(array $args): Result
    {
        $cmd = new DomainNewCommand(new Paths($this->root));
        $result = new Result();
        $ctx = new Context(static function (string $m, bool $ok = true, string $d = '') use ($result): void {
            $result->step($m, $ok, $d);
        });
        $out = $cmd->run($args, $ctx);

        if ($result->steps !== []) {
            $out->steps = array_merge($result->steps, $out->steps);
        }

        return $out;
    }

    public function testCreatesDomainStructureAndConfig(): void
    {
        $r = $this->execute(['domain' => 'example.ru', 'namespace' => 'example']);

        self::assertTrue($r->ok);
        self::assertDirectoryExists($this->root . '/app/example.ru');
        self::assertDirectoryExists($this->root . '/app/example.ru/www/algoritm');
        self::assertFileExists($this->root . '/app/example.ru/www/algoritm/.rules.json');

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertIsArray($site);
        self::assertSame('example.ru', $site['domains_real']['example.ru']);
        self::assertTrue($site['domains_available']['example.ru']);
        self::assertSame('example', $site['domains_namespace']['example.ru']);
        self::assertSame(['www' => 'www'], $site['alliases_real']['example.ru']);
    }

    public function testRejectsInvalidDomain(): void
    {
        $r = $this->execute(['domain' => 'bad domain']);
        self::assertFalse($r->ok);
        self::assertContains('Некорректный домен', $r->errors);
    }

    public function testRejectsDuplicate(): void
    {
        $this->execute(['domain' => 'example.ru']);
        $r = $this->execute(['domain' => 'example.ru']);
        self::assertFalse($r->ok);
    }

    public function testDefaultNamespaceFromDomain(): void
    {
        $r = $this->execute(['domain' => 'foo-bar.test']);
        self::assertTrue($r->ok);
        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertSame('foo_bar_test', $site['domains_namespace']['foo-bar.test']);
    }
}
