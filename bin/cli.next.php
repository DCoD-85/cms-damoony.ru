#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Новый CLI-стек (схема domains_app).
 *
 * Параллельный запуск со старым bin/cli.php:
 *   php bin/cli.php ...        — legacy-код (схема domains_real, файл site.config.old)
 *   php bin/cli.next.php ...   — новый стек (схема domains_app, файл config/site.config)
 */

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Renderer;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

try {
    $registry = Registry::build($root);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));
