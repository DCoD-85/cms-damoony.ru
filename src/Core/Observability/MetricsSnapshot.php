<?php

declare(strict_types=1);

namespace Damoony\Core\Observability;

use Throwable;

/**
 * Агрегация строк metrics-журнала (var/metrics/requests-*.jsonl).
 */
final class MetricsSnapshot
{
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public static function aggregate(array $rows): array
    {
        $total = 0;
        $durations = [];
        $byStatus = [];
        $byHost = [];
        $byAlias = [];
        $errors = 0;
        $dbQueries = 0;
        $dbMs = 0.0;

        foreach ($rows as $row) {
            $total++;
            $status = (int) ($row['status'] ?? 0);
            $class = $status >= 500 ? '5xx' : ($status >= 400 ? '4xx' : ($status >= 300 ? '3xx' : '2xx'));

            $byStatus[$class] = ($byStatus[$class] ?? 0) + 1;
            if ($status >= 500) {
                $errors++;
            }

            $host = (string) ($row['host'] ?? '');
            if ($host !== '') {
                $byHost[$host] = ($byHost[$host] ?? 0) + 1;
            }

            $alias = (string) ($row['alias'] ?? '');
            if ($alias !== '') {
                $byAlias[$alias] = ($byAlias[$alias] ?? 0) + 1;
            }

            $duration = (float) ($row['duration_ms'] ?? 0.0);
            $durations[] = $duration;

            $dbQueries += (int) ($row['db_queries'] ?? 0);
            $dbMs += (float) ($row['db_ms'] ?? 0.0);
        }

        sort($durations);

        return [
            'requests' => $total,
            'errors_5xx' => $errors,
            'by_status' => $byStatus,
            'by_host' => $byHost,
            'by_alias' => $byAlias,
            'duration_ms' => [
                'avg' => $durations === [] ? 0.0 : round(array_sum($durations) / count($durations), 2),
                'p50' => self::percentile($durations, 50),
                'p95' => self::percentile($durations, 95),
                'max' => $durations === [] ? 0.0 : round((float) end($durations), 2),
            ],
            'db' => [
                'queries' => $dbQueries,
                'avg_ms_per_request' => $total > 0 ? round($dbMs / $total, 2) : 0.0,
            ],
        ];
    }

    /**
     * @param list<float> $sorted
     */
    private static function percentile(array $sorted, int $percent): float
    {
        if ($sorted === []) {
            return 0.0;
        }

        $index = (int) ceil(($percent / 100) * count($sorted)) - 1;
        $index = max(0, min($index, count($sorted) - 1));

        return round($sorted[$index], 2);
    }

    /**
     * Читает строки за период (по имени файла с датой).
     *
     * @return list<array<string, mixed>>
     */
    public static function read(string $dir, int $sinceSeconds): array
    {
        $threshold = time() - max(0, $sinceSeconds);
        $rows = [];

        foreach (glob($dir . '/requests-*.jsonl') ?: [] as $file) {
            if ((int) filemtime($file) < $threshold - 86400) {
                continue; // файл заведомо старше периода
            }

            $handle = @fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    continue;
                }
                if (!is_array($row)) {
                    continue;
                }
                $row = self::withTimestamp($row);
                if ($sinceSeconds > 0 && is_int($row['_ts'] ?? null) && $row['_ts'] < $threshold) {
                    continue;
                }
                unset($row['_ts']);
                $rows[] = $row;
            }

            fclose($handle);
        }

        return $rows;
    }

    /**
     * Текстовый формат Prometheus (без внешних зависимостей).
     *
     * @param array<string, mixed> $snapshot
     */
    public static function toPrometheus(array $snapshot): string
    {
        $out = "# HELP damoony_requests_total Всего запросов\n# TYPE damoony_requests_total counter\n";
        $out .= 'damoony_requests_total ' . (int) ($snapshot['requests'] ?? 0) . "\n";

        $out .= "# TYPE damoony_errors_5xx_total counter\ndamoony_errors_5xx_total " . (int) ($snapshot['errors_5xx'] ?? 0) . "\n";

        foreach ((array) ($snapshot['by_status'] ?? []) as $class => $count) {
            $out .= sprintf("damoony_requests_by_status{class=\"%s\"} %d\n", $class, (int) $count);
        }
        foreach ((array) ($snapshot['by_host'] ?? []) as $host => $count) {
            $out .= sprintf("damoony_requests_by_host{host=\"%s\"} %d\n", $host, (int) $count);
        }
        foreach ((array) ($snapshot['by_alias'] ?? []) as $alias => $count) {
            $out .= sprintf("damoony_requests_by_alias{alias=\"%s\"} %d\n", $alias, (int) $count);
        }

        $duration = (array) ($snapshot['duration_ms'] ?? []);
        $out .= '# TYPE damoony_request_duration_ms summary' . "\n";
        $out .= 'damoony_request_duration_ms{quantile="0.5"} ' . (float) ($duration['p50'] ?? 0) . "\n";
        $out .= 'damoony_request_duration_ms{quantile="0.95"} ' . (float) ($duration['p95'] ?? 0) . "\n";
        $out .= 'damoony_request_duration_ms_sum ' . round((float) ($duration['avg'] ?? 0) * (int) ($snapshot['requests'] ?? 0), 2) . "\n";
        $out .= 'damoony_request_duration_ms_count ' . (int) ($snapshot['requests'] ?? 0) . "\n";

        $db = (array) ($snapshot['db'] ?? []);
        $out .= "# TYPE damoony_db_queries_total counter\ndamoony_db_queries_total " . (int) ($db['queries'] ?? 0) . "\n";
        $out .= "# TYPE damoony_db_avg_ms_per_request gauge\ndamoony_db_avg_ms_per_request " . (float) ($db['avg_ms_per_request'] ?? 0) . "\n";

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function withTimestamp(array $row): array
    {
        $ts = (string) ($row['ts'] ?? '');
        $parsed = $ts !== '' ? strtotime($ts) : false;
        $row['_ts'] = $parsed === false ? null : $parsed;

        return $row;
    }
}
