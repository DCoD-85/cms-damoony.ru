<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

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

    public function testListsDomainsAndAliases(): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['damoony.ru' => 'damoony_ru', 'damoony3.ru' => 'damoony_ru'],
            'domains_mirrors' => ['damoony.ru' => 'damoony.ru', 'damoony3.ru' => 'damoony.ru'],
            'domains_mirrors_available' => ['damoony.ru' => true],
            'alliases' => ['damoony.ru' => ['www' => ['available' => true], 'doc' => ['available' => false]]],
        ]));
        mkdir($this->root . '/app/damoony_ru', 0o777, true);

        $res = new DomainListCommand($this->paths)->run([], $this->ctx());

        self::assertTrue($res->ok);
        self::assertCount(1, $res->data['rows']);
        $row = $res->data['rows'][0];
        self::assertSame('damoony.ru', $row['domain']);
        self::assertSame('damoony_ru', $row['namespace']);
        self::assertSame(['damoony.ru', 'damoony3.ru'], $row['hosts']);
        self::assertTrue($row['dir_exists']);
        self::assertSame(['doc', 'www'], $row['aliases']);
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
