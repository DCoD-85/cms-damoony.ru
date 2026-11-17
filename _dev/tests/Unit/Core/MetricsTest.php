<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Core\Observability\Metrics;
use Damoony\Core\Observability\RequestId;
use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/damoony_metrics_' . bin2hex(random_bytes(6));
        RequestId::reset();
        Metrics::reset();
    }

    protected function tearDown(): void
    {
        Metrics::reset();
        RequestId::reset();
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }
    }

    public function testWritesOneLinePerRequest(): void
    {
        Metrics::configure(true, $this->dir, 1);
        Metrics::begin();
        Metrics::setContext('damoony.ru', 'www');
        Metrics::dbQuery(1.5);
        Metrics::dbQuery(0.5);
        Metrics::flushStatus(200);

        $line = $this->lastLine();
        self::assertSame(200, $line['status']);
        self::assertSame('damoony.ru', $line['project']);
        self::assertSame('www', $line['alias']);
        self::assertSame(2, $line['db_queries']);
        self::assertSame(2.0, (float) $line['db_ms']);
        self::assertArrayHasKey('request_id', $line);
        self::assertArrayHasKey('duration_ms', $line);
    }

    public function testDisabledWritesNothing(): void
    {
        Metrics::configure(false, $this->dir, 1);
        Metrics::begin();
        Metrics::flushStatus(500);

        self::assertSame([], glob($this->dir . '/*') ?: []);
    }

    public function testSamplingWritesEveryNthRequest(): void
    {
        Metrics::configure(true, $this->dir, 2);

        Metrics::begin();
        Metrics::flushStatus(200);

        Metrics::begin();
        Metrics::flushStatus(201);

        $lines = $this->lines();
        self::assertCount(1, $lines);
        self::assertSame(201, $lines[0]['status']);
    }

    public function testFlushIsIdempotentPerRequest(): void
    {
        Metrics::configure(true, $this->dir, 1);
        Metrics::begin();
        Metrics::flushStatus(200);
        Metrics::flushStatus(500);

        $lines = $this->lines();
        self::assertCount(1, $lines);
        self::assertSame(200, $lines[0]['status']);
    }

    /** @return array<string, mixed> */
    private function lastLine(): array
    {
        $lines = $this->lines();
        self::assertNotEmpty($lines);

        return $lines[count($lines) - 1];
    }

    /** @return list<array<string, mixed>> */
    private function lines(): array
    {
        $files = glob($this->dir . '/requests-*.jsonl') ?: [];
        if ($files === []) {
            return [];
        }

        $rows = [];
        foreach (file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }
}
