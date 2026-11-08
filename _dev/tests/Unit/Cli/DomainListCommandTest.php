<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\DomainListCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainListCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_domain_list_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
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
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir . '/' . $e;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function writeConfig(array $data): void
    {
        file_put_contents($this->root . '/config/site.config', json_encode($data));
    }

    private function execute(): Result
    {
        return new DomainListCommand(new Paths($this->root))
            ->run([], new Context(static fn () => null));
    }

    public function testEmptyConfig(): void
    {
        $this->writeConfig(['domains_real' => []]);
        $res = $this->execute();
        self::assertTrue($res->ok);
        self::assertSame(['rows' => []], $res->data);
    }

    public function testMissingConfig(): void
    {
        $res = $this->execute();
        self::assertFalse($res->ok);
    }

    public function testDeduplicatesRealDomains(): void
    {
        $this->writeConfig([
            'domains_real' => [
                'damoony.ru' => 'damoony.ru',
                'damoony3.ru' => 'damoony.ru',
                'damoony.online' => 'damoony.ru',
                'damoony3test.ru' => 'damoony3test.ru',
            ],
            'domains_available' => ['damoony.ru' => true, 'damoony3test.ru' => true],
            'alliases_real' => [
                'damoony.ru' => ['www' => 'www', 'doc' => 'doc'],
                'damoony3test.ru' => ['www' => 'www'],
            ],
        ]);

        $res = $this->execute();
        self::assertTrue($res->ok);
        self::assertCount(2, $res->data['rows']);

        $domains = array_column($res->data['rows'], 'domain');
        sort($domains);
        self::assertSame(['damoony.ru', 'damoony3test.ru'], $domains);

        $first = $res->data['rows'][0];
        self::assertSame('damoony.ru', $first['domain']);
        self::assertSame(['doc', 'www'], $first['aliases']); // по реальному значению, без ""
    }

    public function testMissingDir(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        $res = $this->execute();
        self::assertFalse($res->data['rows'][0]['dir_exists']);
    }

    public function testDirExists(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'alliases_real' => ['example.ru' => ['www' => 'www']],
        ]);
        mkdir($this->root . '/app/example.ru', 0o777, true);

        $res = $this->execute();
        self::assertTrue($res->data['rows'][0]['dir_exists']);
    }

    public function testAvailabilityFlag(): void
    {
        $this->writeConfig([
            'domains_real' => [
                'on.ru' => 'on.ru',
                'off.ru' => 'off.ru',
            ],
            'domains_available' => ['on.ru' => true, 'off.ru' => false],
            'alliases_real' => ['on.ru' => [], 'off.ru' => []],
        ]);

        $res = $this->execute();
        $byDomain = [];
        foreach ($res->data['rows'] as $row) {
            $byDomain[$row['domain']] = $row;
        }
        self::assertTrue($byDomain['on.ru']['available']);
        self::assertFalse($byDomain['off.ru']['available']);
    }

    public function testAliasesByRealValueIgnoringEmptyKey(): void
    {
        $this->writeConfig([
            'domains_real' => ['example.ru' => 'example.ru'],
            'domains_available' => ['example.ru' => true],
            'alliases_real' => ['example.ru' => [
                '' => 'coffee',
                'coffee' => 'coffee',
                'tea' => 'tea',
                'www' => 'www',
            ]],
        ]);

        $res = $this->execute();
        $row = $res->data['rows'][0];
        self::assertSame(['coffee', 'tea', 'www'], $row['aliases']);
    }
}
