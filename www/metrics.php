<?php

declare(strict_types=1);

/**
 * Экспорт метрик (Prometheus text). Доступ — только с токеном.
 *
 * config/observability.config → metrics.token; пустой токен = эндпоинт выключен (404).
 */

use Damoony\Core\Files\Paths;
use Damoony\Core\Observability\MetricsSnapshot;
use Damoony\Core\Settings\ObservabilityConfig;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

try {
    $config = ObservabilityConfig::fromFile($root . '/config/observability.config');
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "# metrics disabled: bad observability.config\n";
    exit;
}

$token = $config->metricsToken();
$given = (string) ($_SERVER['HTTP_X_METRICS_TOKEN'] ?? ($_GET['token'] ?? ''));

if ($token === '' || !hash_equals($token, $given)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found\n";
    exit;
}

$paths = new Paths($root);
$rows = MetricsSnapshot::read($paths->root() . '/var/metrics', 0);
$snapshot = MetricsSnapshot::aggregate($rows);

header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
header('Cache-Control: no-store');
echo MetricsSnapshot::toPrometheus($snapshot);
