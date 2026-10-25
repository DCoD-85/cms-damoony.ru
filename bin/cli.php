#!/usr/bin/env php
<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
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

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));