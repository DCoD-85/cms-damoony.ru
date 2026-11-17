#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Damoony CLI (схема config/site.config -> domains_app).
 */

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\Identity\CliLogger;
use Damoony\Cli\Identity\IdentityStore;
use Damoony\Cli\Next\Registry;
use Damoony\Cli\Renderer;
use Damoony\Core\Config;
use Damoony\Data\Db\Connection;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$command = (string) ($_SERVER['argv'][1] ?? 'help');

$identityStore = new IdentityStore($root);
$identity = $identityStore->load();

// Bootstrap: единственная команда, доступная без идентификатора.
if ($identity === null && $command !== 'cli:init') {
    fwrite(STDERR, "CLI не инициализирован. Выполните: php bin/cli.php cli:init --name=<ваше имя>\n");
    exit(1);
}

if ($identity !== null) {
    try {
        $identityStore->assertOwnedByCurrentUser();
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

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

$app = new Application(
    $registry,
    new ArgvParser(),
    new Renderer(),
    new CliLogger($root),
    $identity['user'] ?? '',
);

exit($app->run($_SERVER['argv']));
