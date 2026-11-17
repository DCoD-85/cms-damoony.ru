<?php

declare(strict_types=1);

namespace Damoony\Tests\Unit\Core;

use Damoony\Cli\Commands\MetricsSnapshotCommand;
use Damoony\Cli\Context;
use Damoony\Core\Files\Paths;
use Damoony\Core\Observability\MetricsSnapshot;
use PHPUnit\Framework\TestCase;

final class MetricsSnapshotTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/damoony_msnap_' . bin2hex(random_bytes(6));
        mkdir($this->root . '/var/metrics', 0o775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    public function testAggregatesStatusesDurationAndDb(): void
    {
        $rows = [
            ['status' => 200, 'duration_ms' => 10.0, 'host' => 'a.ru', 'alias' => 'www', 'db_queries' => 2, 'db_ms' => 4.0],
            ['status' => 200, 'duration_ms' => 30.0, 'host' => 'a.ru', 'alias' => 'www', 'db_queries' => 1, 'db_ms' => 1.0],
            ['status' => 404, 'duration_ms' => 5.0, 'host' => 'b.ru', 'alias' => '', 'db_queries' => 0, 'db_ms' => 0.0],
            ['status' => 500, 'duration_ms' => 100.0, 'host' => 'a.ru', 'alias' => 'login', 'db_queries' => 0, 'db_ms' => 0.0],
        ];

        $snapshot = MetricsSnapshot::aggregate($rows);

        self::assertSame(4, $snapshot['requests']);
        self::assertSame(1, $snapshot['errors_5xx']);
        self::assertSame(['2xx' => 2, '4xx' => 1, '5xx' => 1], $snapshot['by_status']);
        self::assertSame(3, $snapshot['by_host']['a.ru']);
        self::assertSame(2, $snapshot['by_alias']['www']);
        self::assertSame(10.0, $snapshot['duration_ms']['p50']); // nearest-rank
        self::assertSame(100.0, $snapshot['duration_ms']['max']);
        self::assertSame(3, $snapshot['db']['queries']);
        self::assertSame(1.25, $snapshot['db']['avg_ms_per_request']);
    }

    public function testReadFiltersByWindow(): void
    {
        $file = $this->root . '/var/metrics/requests-2026-01-01.jsonl';
        file_put_contents($file, json_encode(['ts' => gmdate('Y-m-d\TH:i:s\Z'), 'status' => 200, 'duration_ms' => 1]) . "\n");
        file_put_contents($file, json_encode(['ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 3 * 86400), 'status' => 500, 'duration_ms' => 2]) . "\n", FILE_APPEND);

        $rows = MetricsSnapshot::read($this->root . '/var/metrics', 3600);

        self::assertCount(1, $rows);
        self::assertSame(200, $rows[0]['status']);
    }

    public function testPrometheusFormat(): void
    {
        $text = MetricsSnapshot::toPrometheus(MetricsSnapshot::aggregate([
            ['status' => 200, 'duration_ms' => 10.0, 'host' => 'a.ru', 'alias' => 'www', 'db_queries' => 1, 'db_ms' => 2.0],
        ]));

        self::assertStringContainsString('damoony_requests_total 1', $text);
        self::assertStringContainsString('damoony_errors_5xx_total 0', $text);
        self::assertStringContainsString('damoony_requests_by_host{host="a.ru"} 1', $text);
        self::assertStringContainsString('damoony_request_duration_ms{quantile="0.5"} 10', $text);
        self::assertStringContainsString('damoony_db_queries_total 1', $text);
    }

    public function testCommandReportsSnapshot(): void
    {
        file_put_contents(
            $this->root . '/var/metrics/requests-2026-01-01.jsonl',
            json_encode(['ts' => gmdate('Y-m-d\TH:i:s\Z'), 'status' => 200, 'duration_ms' => 12.5, 'host' => 'a.ru', 'alias' => 'www', 'db_queries' => 1, 'db_ms' => 0.5]) . "\n",
        );

        $command = new MetricsSnapshotCommand(new Paths($this->root));

        $result = $command->run(['since' => '1h'], $this->context());

        self::assertTrue($result->ok, implode('; ', $result->errors));
        self::assertSame(1, $result->data['requests']);
        self::assertSame(12.5, $result->data['duration_ms']['p50']);
    }

    public function testCommandRejectsBadSince(): void
    {
        $command = new MetricsSnapshotCommand(new Paths($this->root));

        $result = $command->run(['since' => 'yesterday'], $this->context());

        self::assertFalse($result->ok);
        self::assertStringContainsString('--since', $result->errors[0]);
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
