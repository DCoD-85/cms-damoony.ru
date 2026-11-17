<?php

declare(strict_types=1);

namespace Damoony\Core\Observability;

use Damoony\Core\Files\Fs;
use Damoony\Core\Http\Response;
use Throwable;

/**
 * Сбор метрик запроса без нагрузки: всё копится в памяти, на выходе — одна
 * строка JSONL в var/metrics/requests-YYYY-MM-DD.jsonl (без fsync, без локов).
 *
 * Одна строка на запрос; агрегация — отдельно (`metrics:snapshot`).
 */
final class Metrics
{
    private static bool $enabled = false;
    private static string $dir = '';
    private static int $sample = 1;
    private static int $counter = 0;
    private static bool $shutdownRegistered = false;
    private static bool $flushed = false;

    private static float $started = 0.0;
    private static string $project = '';
    private static string $alias = '';
    private static int $dbQueries = 0;
    private static float $dbMs = 0.0;

    public static function configure(bool $enabled, string $dir, int $sample = 1): void
    {
        self::$enabled = $enabled;
        self::$dir = rtrim($dir, '/');
        self::$sample = max(1, $sample);

        // Веб: одна строка за запрос, сброс на shutdown (в т.ч. для exit в content/forms).
        if ($enabled && PHP_SAPI !== 'cli' && !self::$shutdownRegistered) {
            register_shutdown_function(static function (): void {
                $status = http_response_code();
                self::flushStatus(is_int($status) ? $status : 0);
            });
            self::$shutdownRegistered = true;
        }
    }

    /** Только для тестов: сброс накопленного состояния. */
    public static function reset(): void
    {
        self::$counter = 0;
        self::$flushed = false;
        self::$started = 0.0;
        self::$project = '';
        self::$alias = '';
        self::$dbQueries = 0;
        self::$dbMs = 0.0;
    }

    public static function begin(): void
    {
        self::$started = microtime(true);
        self::$flushed = false;
        self::$project = '';
        self::$alias = '';
        self::$dbQueries = 0;
        self::$dbMs = 0.0;
    }

    public static function setContext(string $project, string $alias): void
    {
        self::$project = $project;
        self::$alias = $alias;
    }

    public static function dbQuery(float $ms): void
    {
        self::$dbQueries++;
        self::$dbMs += $ms;
    }

    /**
     * @return array{db_queries: int, db_ms: float}
     */
    public static function dbStats(): array
    {
        return ['db_queries' => self::$dbQueries, 'db_ms' => round(self::$dbMs, 3)];
    }

    public static function flush(Response $response): void
    {
        self::flushStatus($response->status);
    }

    public static function flushStatus(int $status): void
    {
        if (self::$flushed) {
            return;
        }
        self::$flushed = true;
        if (!self::$enabled || self::$dir === '') {
            return;
        }

        self::$counter++;
        if (self::$sample > 1 && self::$counter % self::$sample !== 0) {
            return;
        }

        $durationMs = self::$started > 0.0 ? (microtime(true) - self::$started) * 1000 : 0.0;

        $line = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => RequestId::get(),
            'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'path' => (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'),
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
            'project' => self::$project,
            'alias' => self::$alias,
            'db_queries' => self::$dbQueries,
            'db_ms' => round(self::$dbMs, 2),
        ];

        $json = json_encode($line, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        try {
            Fs::mkdir(self::$dir);
            @file_put_contents(self::$dir . '/requests-' . gmdate('Y-m-d') . '.jsonl', $json . "\n", FILE_APPEND);
        } catch (Throwable) {
            // метрики не должны ломать запрос
        }
    }
}
