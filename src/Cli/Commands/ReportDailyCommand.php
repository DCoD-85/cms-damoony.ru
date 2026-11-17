<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Paths;
use Damoony\Core\Observability\MetricsSnapshot;
use Damoony\Core\Settings\ObservabilityConfig;
use Throwable;

/**
 * report:daily — суточная сводка: запросы, ошибки, БД, бэкапы, диск, health.
 *
 * `--alert` дублирует сводку в канал алертов (для крона с отправкой владельцу).
 */
final class ReportDailyCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly ObservabilityConfig $observability,
        private readonly HealthCheckCommand $health,
        private readonly AlertNotifyCommand $alerts,
    ) {
    }

    public function name(): string
    {
        return 'report:daily';
    }

    public function description(): string
    {
        return 'Суточная сводка (--alert — отправить в канал алертов)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $since = (string) ($args['since'] ?? '24h');
        $seconds = $this->parseSince($since);
        if ($seconds < 0) {
            $r->error('--since ожидает формат: 24h, 7d');

            return $r;
        }

        $metricsRows = is_dir($this->paths->root() . '/var/metrics')
            ? MetricsSnapshot::read($this->paths->root() . '/var/metrics', $seconds)
            : [];
        $metrics = MetricsSnapshot::aggregate($metricsRows);

        $healthContext = new Context(static function (): void {
        });
        $healthResult = $this->health->run([], $healthContext);
        $healthStatus = (string) ($healthResult->data['status'] ?? 'unknown');

        $backups = glob($this->paths->root() . '/var/backups/backup_*.zip') ?: [];
        $newest = 0;
        foreach ($backups as $file) {
            $newest = max($newest, (int) filemtime($file));
        }

        $alertsCount = $this->countAlerts($seconds);
        $diskFreeMb = $this->diskFreeMb();

        $summary = [
            'window' => $since,
            'requests' => $metrics['requests'],
            'errors_5xx' => $metrics['errors_5xx'],
            'duration_ms' => $metrics['duration_ms'],
            'db' => $metrics['db'],
            'health' => $healthStatus,
            'backups' => count($backups),
            'backup_age_days' => $backups === [] ? null : (int) floor((time() - $newest) / 86400),
            'alerts' => $alertsCount,
            'disk_free_mb' => $diskFreeMb,
        ];

        $r->data = $summary;

        $context->step(sprintf(
            'Запросов: %d, 5xx: %d, p95: %.0f ms, health: %s',
            $summary['requests'],
            $summary['errors_5xx'],
            $summary['duration_ms']['p95'],
            $healthStatus,
        ), true, $since);
        $context->step(sprintf(
            'Бэкапов: %d (последний: %s), алертов: %d, диск: %d MB',
            $summary['backups'],
            $summary['backup_age_days'] === null ? 'нет' : $summary['backup_age_days'] . ' дн. назад',
            $summary['alerts'],
            $summary['disk_free_mb'],
        ), true);

        if (isset($args['alert'])) {
            $channel = $this->alerts->run([
                'message' => $this->formatMessage($summary),
                'level' => $healthStatus === 'fail' ? 'error' : 'info',
                'source' => 'report:daily',
            ], $healthContext);

            if ($channel->ok) {
                $context->step('Сводка отправлена в канал алертов', true);
            } else {
                $r->warn('Не удалось отправить сводку: ' . ($channel->errors[0] ?? 'ошибка'));
            }
        }

        return $r;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function formatMessage(array $summary): string
    {
        return sprintf(
            'Сводка за %s: запросов %d, 5xx %d, p95 %.0f ms, health %s, бэкапов %d, диск %d MB',
            (string) $summary['window'],
            (int) $summary['requests'],
            (int) $summary['errors_5xx'],
            (float) ($summary['duration_ms']['p95'] ?? 0),
            (string) $summary['health'],
            (int) $summary['backups'],
            (int) $summary['disk_free_mb'],
        );
    }

    private function countAlerts(int $seconds): int
    {
        $file = $this->alertsFile();
        if (!is_file($file)) {
            return 0;
        }

        $threshold = time() - $seconds;
        $count = 0;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            try {
                $alert = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                continue;
            }
            if (!is_array($alert)) {
                continue;
            }
            $ts = strtotime((string) ($alert['ts'] ?? ''));
            if ($ts !== false && $ts >= $threshold) {
                $count++;
            }
        }

        return $count;
    }

    private function alertsFile(): string
    {
        $file = $this->observability->alertsFile();
        if (str_starts_with($file, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $file) === 1) {
            return $file;
        }

        return $this->paths->root() . '/' . ltrim($file, '/\\');
    }

    private function diskFreeMb(): int
    {
        $free = @disk_free_space($this->paths->root());

        return $free === false ? 0 : (int) floor($free / 1024 / 1024);
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
