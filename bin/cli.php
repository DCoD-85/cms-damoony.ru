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
use Damoony\Cli\Commands\PageWriteCommand;
use Damoony\Cli\Commands\PageReadCommand;
use Damoony\Cli\Commands\FillerNewCommand;
use Damoony\Cli\Commands\FillerToggleCommand;
use Damoony\Cli\Commands\BlockNewCommand;
use Damoony\Cli\Commands\BlockRemoveCommand;
use Damoony\Cli\Commands\AlliasNewCommand;
use Damoony\Cli\Commands\AlliasRemoveCommand;
use Damoony\Cli\Commands\DomainNewCommand;
use Damoony\Cli\Commands\DomainRemoveCommand; // <-- ДОБАВЛЕНО
use Damoony\Cli\Commands\StructureCheckCommand;
use Damoony\Cli\Commands\StructureRepairCommand;
use Damoony\Cli\Commands\TrashClearCommand;
use Damoony\Cli\Commands\UserNewCommand;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Renderer;
use Damoony\Files\Paths;
use Damoony\Db\Connection;
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

$siteConfigFile = $root . '/config/site.config';
$siteConfig = is_file($siteConfigFile) ? Config::fromFile($siteConfigFile) : Config::fromArray([]);

$registry = new CommandRegistry();
$registry->add(new HelpCommand($registry));
$registry->add(new DomainNewCommand($paths));
$registry->add(new DomainRemoveCommand($paths)); // <-- ЗАРЕГИСТРИРОВАНО
$registry->add(new AlliasNewCommand($paths));
$registry->add(new AlliasRemoveCommand($paths));
$registry->add(new BlockNewCommand($paths));
$registry->add(new BlockRemoveCommand($paths));
$registry->add(new FillerNewCommand($paths));
$registry->add(new FillerToggleCommand($paths));
$registry->add(new PageNewCommand($paths));
$registry->add(new PageToggleCommand($paths));
$registry->add(new PageWriteCommand($paths));
$registry->add(new PageReadCommand($paths));
$registry->add(new SchemeNewCommand($paths));
$registry->add(new DbMigrateCommand($paths, $dbConfig));
$registry->add(new VarsExtractCommand($paths));
$registry->add(new AdminPasswdCommand($paths));
$registry->add(new TrashClearCommand($paths));
$registry->add(new StructureRepairCommand($paths));
$registry->add(new StructureCheckCommand($paths, $siteConfig)); // Оставили только один, с siteConfig

$db = new Connection($dbConfig);
$registry->add(new UserNewCommand($db));

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));