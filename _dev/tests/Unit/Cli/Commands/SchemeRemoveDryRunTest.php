<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli\Commands;

use Damoony\Cli\Commands\SchemeRemoveCommand;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use PHPUnit\Framework\TestCase;

final class SchemeRemoveDryRunTest extends TestCase
{
    private string $root;
    private Paths $paths;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_srem_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/config', 0o777, true);
        $this->paths = new Paths($this->root);

        $schemesDir = $this->paths->domain('real.ru') . '/.schemesglobal';
        mkdir($schemesDir, 0o777, true);
        file_put_contents($schemesDir . '/test.php', '<?php');
        file_put_contents($schemesDir . '/test.rules.json', '{}');
        file_put_contents($schemesDir . '/test.editor.json', '{}');

        file_put_contents($this->root . '/config/site.config', json_encode([
            'domains_real' => ['real.ru' => 'real_ru'],
            'domains_mirrors' => ['real.ru' => 'real.ru', 'mirror.ru' => 'real.ru'],
        ]));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testDryRunKeepsSchemeFiles(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'test', 'force' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertTrue($res->data['dry_run']);
        self::assertSame('real.ru', $res->data['real_domain']);

        $schemesDir = $this->paths->domain('real.ru') . '/.schemesglobal';
        self::assertFileExists($schemesDir . '/test.php');
        self::assertFileExists($schemesDir . '/test.rules.json');
        self::assertFileExists($schemesDir . '/test.editor.json');
        self::assertSame([], glob($schemesDir . '/test_trash_*') ?: []);
    }

    public function testDryRunReportsPlan(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'test', 'force' => '1'], true);

        self::assertTrue($res->ok, implode('; ', $res->errors));
        self::assertSame(['test.php', 'test.rules.json', 'test.editor.json'], $res->data['files']);
        self::assertStringContainsString('test_trash_*', $res->data['trash_dir']);
    }

    public function testDryRunWithoutForceStillRejected(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'test'], true);

        self::assertFalse($res->ok);
        self::assertStringContainsString('--force', $res->errors[0]);

        $schemesDir = $this->paths->domain('real.ru') . '/.schemesglobal';
        self::assertFileExists($schemesDir . '/test.php');
    }

    public function testRealRunStillQuarantines(): void
    {
        $res = $this->execute(['domain' => 'mirror.ru', 'name' => 'test', 'force' => '1'], false);

        self::assertTrue($res->ok, implode('; ', $res->errors));

        $schemesDir = $this->paths->domain('real.ru') . '/.schemesglobal';
        self::assertFileDoesNotExist($schemesDir . '/test.php');
        self::assertNotSame([], glob($schemesDir . '/test_trash_*') ?: []);
        self::assertSame(['test.php', 'test.rules.json', 'test.editor.json'], $res->data['quarantined_files']);
    }

    private function execute(array $args, bool $dryRun): Result
    {
        return new SchemeRemoveCommand($this->paths)->run(
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
