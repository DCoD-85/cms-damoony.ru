<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DomainListCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainListCommandTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_dl_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testDeduplicatesRealDomains(): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => [
                'damoony.ru' => 'damoony_ru',
                'damoony3.ru' => 'damoony_ru',
            ],
            'domains_mirrors' => [
                'damoony.ru' => 'damoony.ru',
                'damoony3.ru' => 'damoony.ru',
            ],
            'domains_mirrors_available' => ['damoony.ru' => true],
            'alliases' => [
                'damoony.ru' => ['www' => ['available' => true], 'doc' => ['available' => true]],
            ],
        ]));
        mkdir($this->root . '/app/damoony_ru', 0o777, true);

        $res = new DomainListCommand($this->paths)->run([], $this->ctx());

        self::assertTrue($res->ok);
        self::assertCount(1, $res->data['rows']);
        self::assertSame(['doc', 'www'], $res->data['rows'][0]['aliases']);
    }

    public function testAliasesByRealValueIgnoringEmptyKey(): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['example.ru' => 'example_ru'],
            'domains_mirrors' => ['example.ru' => 'example.ru'],
            'domains_mirrors_available' => ['example.ru' => true],
            'alliases' => [
                'example.ru' => ['www' => ['available' => true], 'tea' => ['available' => true], 'coffee' => ['available' => true]],
            ],
        ]));
        mkdir($this->root . '/app/example_ru', 0o777, true);

        $res = new DomainListCommand($this->paths)->run([], $this->ctx());

        self::assertTrue($res->ok);
        self::assertSame(['coffee', 'tea', 'www'], $res->data['rows'][0]['aliases']);
    }

    public function testHandlesEmptyConfig(): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => [],
        ]));

        $res = new DomainListCommand($this->paths)->run([], $this->ctx());

        self::assertTrue($res->ok);
        self::assertEmpty($res->data['rows']);
    }

    private function ctx(): Context
    {
        return new Context(static fn (): null => null);
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
}
