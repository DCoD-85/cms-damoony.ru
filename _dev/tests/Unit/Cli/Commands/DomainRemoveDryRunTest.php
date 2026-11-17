<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\DomainRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class DomainRemoveDryRunTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_drdr_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);

        mkdir($this->paths->domain('real.ru') . '/www/algoritm', 0o777, true);
        mkdir($this->paths->mediaRoot() . '/real.ru', 0o777, true);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['real.ru' => 'real_ru'],
            'domains_mirrors' => ['real.ru' => 'real.ru', 'mirror.ru' => 'real.ru'],
            'domains_mirrors_available' => ['real.ru' => true],
            'alliases' => ['real.ru' => ['www' => ['available' => true]]],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testDryRunKeepsDomainMediaAndConfig(): void
    {
        $res = $this->execute(['domain' => 'real.ru', 'force' => '1', 'cascade' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertTrue($res->data['dry_run']);
        self::assertFalse($res->data['binding_only']);
        self::assertSame(['mirror.ru'], $res->data['cascade']);

        self::assertDirectoryExists($this->paths->domain('real.ru'));
        self::assertDirectoryExists($this->paths->mediaRoot() . '/real.ru');
        self::assertSame([], glob($this->root . '/app/real.ru_trash_*') ?: []);
        self::assertSame([], glob($this->paths->mediaRoot() . '/.trash/real.ru_trash_*') ?: []);

        $site = json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
        self::assertArrayHasKey('real.ru', $site['domains_real']);
        self::assertArrayHasKey('mirror.ru', $site['domains_mirrors']);
    }

    public function testDryRunStillRequiresCascade(): void
    {
        $res = $this->execute(['domain' => 'real.ru', 'force' => '1'], true);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--cascade', $res->errors[0]);
        self::assertDirectoryExists($this->paths->domain('real.ru'));
    }

    public function testDryRunBindingOnlyReportsMapping(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'force' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertTrue($res->data['binding_only']);
        self::assertSame('real.ru', $res->data['mapped_to']);
        self::assertDirectoryExists($this->paths->domain('real.ru'));
    }

    public function testDryRunWithoutForceStillRejected(): void
    {
        $res = $this->execute(['domain' => 'real.ru'], true);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
    }

    public function testRealRunStillQuarantines(): void
    {
        $res = $this->execute(['domain' => 'real.ru', 'force' => '1', 'cascade' => '1'], false);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($this->paths->domain('real.ru'));
        self::assertNotSame([], glob($this->root . '/app/real.ru_trash_*') ?: []);
    }

    private function execute(array $args, bool $dryRun): Result
    {
        return new DomainRemoveCommand($this->paths)->run(
            $args,
            new Context(static fn (): null => null, false, $dryRun),
        );
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
}
