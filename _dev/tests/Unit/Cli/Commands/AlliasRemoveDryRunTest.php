<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class AlliasRemoveDryRunTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_aldr_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);

        mkdir($this->paths->allias('real.ru', 'doc') . '/algoritm', 0o777, true);
        mkdir($this->paths->media('real.ru', 'doc'), 0o777, true);

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['real.ru' => 'real_ru'],
            'domains_mirrors' => ['real.ru' => 'real.ru', 'mirror.ru' => 'real.ru'],
            'alliases' => ['real.ru' => [
                'www' => ['available' => true],
                'doc' => ['available' => true],
            ]],
            'fillers' => ['real.ru' => ['doc' => ['top' => true]]],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testDryRunKeepsAlliasMediaAndConfig(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc', 'force' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertTrue($res->data['dry_run']);
        self::assertSame('real.ru', $res->data['real_domain']);

        self::assertDirectoryExists($this->paths->allias('real.ru', 'doc'));
        self::assertDirectoryExists($this->paths->media('real.ru', 'doc'));
        self::assertSame([], glob($this->paths->allias('real.ru', 'doc') . '_trash_*') ?: []);
        self::assertSame([], glob($this->paths->mediaRoot() . '/.trash/real.ru_doc_trash_*') ?: []);

        $site = $this->site();
        self::assertArrayHasKey('doc', $site['alliases']['real.ru']);
        self::assertArrayHasKey('doc', $site['fillers']['real.ru']);
    }

    public function testDryRunReportsPlan(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc', 'force' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertIsString($res->data['allias_dir']);
        self::assertIsString($res->data['media_dir']);
        self::assertContains('alliases', $res->data['config_keys']);
        self::assertContains('fillers', $res->data['config_keys']);
    }

    public function testDryRunWithoutForceStillRejected(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc'], true);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);
        self::assertDirectoryExists($this->paths->allias('real.ru', 'doc'));
    }

    public function testRealRunStillQuarantines(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'doc', 'force' => '1'], false);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertDirectoryDoesNotExist($this->paths->allias('real.ru', 'doc'));
        self::assertNotSame([], glob($this->paths->allias('real.ru', 'doc') . '_trash_*') ?: []);
        self::assertArrayNotHasKey('doc', $this->site()['alliases']['real.ru']);
    }

    private function execute(array $args, bool $dryRun): Result
    {
        return new AlliasRemoveCommand($this->paths)->run(
            $args,
            new Context(static fn (): null => null, false, $dryRun),
        );
    }

    /** @return array<string, mixed> */
    private function site(): array
    {
        return json_decode((string) file_get_contents($this->root . '/config/site.config'), true);
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
