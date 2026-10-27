#!/usr/bin/env php
<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
use Damoony\Cli\Commands\AdminPasswdCommand;
use Damoony\Cli\Commands\VarsExtractCommand;
use Damoony\Cli\Commands\DbMigrateCommand;
use Damoony\Cli\Commands\SchemeNewCommand;
use Damoony\Cli\Commands\PageNewCommand;
use Damoony\Cli\Commands\PageToggleCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Renderer;
use Damoony\Files\Paths;
use Damoony\Db\Connection;
use Damoony\Cli\Commands\UserNewCommand;
use Damoony\Core\Config;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$paths = new Paths($root);

// БД (из engine.config)
$engineConfigFile = $root . '/config/engine.config';
$engineConfig = is_file($engineConfigFile) ? Config::fromFile($engineConfigFile) : Config::fromArray([]);
$dbConfig = $engineConfig->getByKey(['db'], []);

if (!is_array($dbConfig) || $dbConfig === []) {
    $dbConfig = ['driver' => 'sqlite', 'path' => $root . '/var/damoony.db'];
} elseif (($dbConfig['driver'] ?? '') === 'sqlite' && isset($dbConfig['path'])) {
    $path = (string) $dbConfig['path'];
    if (!str_starts_with($path, '/') && !preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
        $dbConfig['path'] = $root . '/' . ltrim($path, '/\\');
    }
}

$registry = new CommandRegistry();
$registry->add(new HelpCommand($registry));
$registry->add(new DomainNewCommand($paths));
$registry->add(new AlliasNewCommand($paths));
$registry->add(new AlliasRemoveCommand($paths));
$registry->add(new BlockNewCommand($paths));
$registry->add(new BlockRemoveCommand($paths));
$registry->add(new FillerNewCommand($paths));
$registry->add(new FillerToggleCommand($paths));
$registry->add(new PageNewCommand($paths));
$registry->add(new PageToggleCommand($paths));
$registry->add(new SchemeNewCommand($paths));
$registry->add(new DbMigrateCommand($paths, $dbConfig));
$registry->add(new VarsExtractCommand($paths));
$registry->add(new AdminPasswdCommand($paths));

$db = new Connection($dbConfig);
$registry->add(new UserNewCommand($db));
$registry->add(new \Damoony\Cli\Commands\TrashClearCommand($paths));

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));