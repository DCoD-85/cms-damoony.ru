<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Cli;

use Damoony\Cli\Commands\AlertNotifyCommand;
use Damoony\Cli\Commands\HealthCheckCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ObservabilityConfig;
use Damoony\Core\Settings\SiteConfigWriter;
use Damoony\Data\Db\Connection;
use PHPUnit\Framework\TestCase;

final class ObservabilityCommandsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_obs_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0o775, true);
        mkdir($this->root . '/var', 0o775, true);
        mkdir($this->root . '/var/backups', 0o775, true);

        SiteConfigWriter::forRoot($this->root)->save(['domains_app' => []]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testHealthOkWithFreshBackup(): void
    {
        file_put_contents($this->root . '/var/backups/backup_test.zip', 'x');

        $result = $this->health()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('ok', $result->data['status']);
    }

    public function testHealthDegradedWithoutBackups(): void
    {
        $result = $this->health()->run([], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame('degraded', $result->data['status']);
        self::assertStringContainsString('бэкапов', $result->warnings[0]);
    }

    public function testHealthFailsOnInvalidSiteConfig(): void
    {
        file_put_contents($this->root . '/config/site.config', '{"domains_app": "oops"}');

        $result = $this->health()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertSame('fail', $result->data['status']);
    }

    public function testHealthFailsOnStaleBackup(): void
    {
        $file = $this->root . '/var/backups/backup_old.zip';
        file_put_contents($file, 'x');
        touch($file, time() - 10 * 86400);

        $result = $this->health()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertSame('fail', $result->data['status']);
        self::assertStringContainsString('последний бэкап', $result->errors[0]);
    }

    public function testHealthFailsOnDeadDatabase(): void
    {
        $result = $this->health(
            new Connection(['driver' => 'sqlite', 'path' => $this->root . '/missing/sub/db.sqlite']),
        )->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertSame('fail', $result->data['status']);
    }

    public function testAlertWritesToFile(): void
    {
        $result = $this->alert()->run(
            ['message' => 'диск почти полон', 'level' => 'critical', 'source' => 'health'],
            $this->context(),
        );

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['file'], $result->data['channels']);

        $line = trim((string) file_get_contents($this->root . '/var/alerts.log'));
        $alert = json_decode($line, true);
        self::assertIsArray($alert);
        self::assertSame('critical', $alert['level']);
        self::assertSame('health', $alert['source']);
        self::assertSame('диск почти полон', $alert['message']);
    }

    public function testAlertRequiresMessage(): void
    {
        $result = $this->alert()->run([], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--message', $result->errors[0]);
    }

    public function testAlertWarnsWhenWebhookUnreachable(): void
    {
        $config = ObservabilityConfig::fromArray([
            'alerts' => ['file' => 'var/alerts.log', 'webhook' => 'http://127.0.0.1:1/hook'],
        ]);
        $command = new AlertNotifyCommand(new Paths($this->root), $config);

        $result = $command->run(['message' => 'test'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(['file'], $result->data['channels']);
        self::assertStringContainsString('Webhook', $result->warnings[0]);
    }

    private function health(?Connection $db = null): HealthCheckCommand
    {
        return new HealthCheckCommand(
            new Paths($this->root),
            $db ?? new Connection(['driver' => 'sqlite', 'path' => $this->root . '/var/test.sqlite']),
            ObservabilityConfig::defaults(),
        );
    }

    private function alert(): AlertNotifyCommand
    {
        return new AlertNotifyCommand(new Paths($this->root), ObservabilityConfig::defaults());
    }

    private function context(): Context
    {
        return new Context(static function (string $m, bool $ok = true, string $d = ''): void {
        });
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
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
