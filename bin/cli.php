#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Damoony CLI (схема config/site.config -> domains_app).
 */

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Renderer;
use Damoony\Core\Config;
use Damoony\Data\Db\Connection;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

$dbConfig = [];
$engineFile = $root . '/config/engine.config';
if (is_file($engineFile)) {
    $db = Config::fromFile($engineFile)->getByKey(['db'], []);
    if (is_array($db)) {
        $dbConfig = $db;
    }
}

if ($dbConfig === []) {
    $dbConfig = ['driver' => 'sqlite', 'path' => $root . '/var/damoony.db'];
} elseif (($dbConfig['driver'] ?? '') === 'sqlite' && isset($dbConfig['path'])) {
    $path = (string) $dbConfig['path'];
    if (!str_starts_with($path, '/') && preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) !== 1) {
        $dbConfig['path'] = $root . '/' . ltrim($path, '/\\');
    }
}

try {
    $registry = Registry::build($root, new Connection($dbConfig));
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));
