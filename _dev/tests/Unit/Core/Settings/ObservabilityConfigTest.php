<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core\Settings;

use Damoony\Core\Settings\ObservabilityConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ObservabilityConfigTest extends TestCase
{
    public function testDefaultsWhenFileMissing(): void
    {
        $config = ObservabilityConfig::fromFile(__DIR__ . '/nope.config');

        self::assertSame(2, $config->backupMaxAgeDays());
        self::assertSame(1024, $config->diskMinFreeMb());
        self::assertSame('var/alerts.log', $config->alertsFile());
        self::assertNull($config->alertsWebhook());
    }

    public function testOverrides(): void
    {
        $config = ObservabilityConfig::fromArray([
            'health' => ['backup_max_age_days' => 5, 'disk_min_free_mb' => 100],
            'alerts' => ['file' => 'var/other.log', 'webhook' => 'https://example.test/hook'],
        ]);

        self::assertSame(5, $config->backupMaxAgeDays());
        self::assertSame(100, $config->diskMinFreeMb());
        self::assertSame('var/other.log', $config->alertsFile());
        self::assertSame('https://example.test/hook', $config->alertsWebhook());
    }

    public function testNullWebhookIsNull(): void
    {
        $config = ObservabilityConfig::fromArray(['alerts' => ['webhook' => null]]);

        self::assertNull($config->alertsWebhook());
    }

    public function testRejectsNonHttpWebhook(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('http(s)');
        ObservabilityConfig::fromArray(['alerts' => ['webhook' => 'ftp://example.test']]);
    }

    public function testRejectsNegativeThreshold(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('целым ≥ 0');
        ObservabilityConfig::fromArray(['health' => ['backup_max_age_days' => -1]]);
    }
}
