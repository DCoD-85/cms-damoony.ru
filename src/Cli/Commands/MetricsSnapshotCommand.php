<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Observability\MetricsSnapshot;

/**
 * metrics:snapshot — агрегирует var/metrics/requests-*.jsonl за период.
 */
final class MetricsSnapshotCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'metrics:snapshot';
    }

    public function description(): string
    {
        return 'Сводка метрик запросов (--since=1h|24h|7d, --format=text|json|prometheus)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $since = (string) ($args['since'] ?? '24h');
        $seconds = $this->parseSince($since);
        if ($seconds < 0) {
            $r->error('--since ожидает формат: 60s, 30m, 24h, 7d');

            return $r;
        }

        $dir = $this->paths->root() . '/var/metrics';
        if (!is_dir($dir)) {
            $r->warn('Метрик ещё нет: ' . $dir);
            $r->data = ['requests' => 0, 'window' => $since];

            return $r;
        }

        $rows = MetricsSnapshot::read($dir, $seconds);
        $snapshot = MetricsSnapshot::aggregate($rows);
        $snapshot['window'] = $since;

        $format = (string) ($args['format'] ?? 'text');

        $r->data = $snapshot;

        if ($format === 'prometheus') {
            $r->data['text'] = MetricsSnapshot::toPrometheus($snapshot);

            return $r;
        }

        $context->step(sprintf(
            'Запросов: %d, 5xx: %d, p50: %.1f ms, p95: %.1f ms',
            $snapshot['requests'],
            $snapshot['errors_5xx'],
            $snapshot['duration_ms']['p50'],
            $snapshot['duration_ms']['p95'],
        ), true, $since);

        if ($snapshot['requests'] === 0) {
            $context->step('За период запросов нет', true);
        }

        return $r;
    }

    private function parseSince(string $spec): int
    {
        if (preg_match('/^(\d+)([smhd])$/', $spec, $m) !== 1) {
            return -1;
        }

        $units = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];

        return (int) $m[1] * $units[$m[2]];
    }
}
