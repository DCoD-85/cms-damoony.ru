#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Damoony CLI (схема config/site.config -> domains_app).
 *
 * Старый стек (схема domains_real) выведен из эксплуатации и лежит
 * в bin/cli.old.php до окончательного удаления.
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
