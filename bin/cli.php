#!/usr/bin/env php
<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
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

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$paths = new Paths($root);

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
$registry->add(new DbMigrateCommand($paths, [
    'driver' => 'sqlite',
    'path' => $root . '/var/damoony.db',
]));
$registry->add(new VarsExtractCommand($paths));

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));