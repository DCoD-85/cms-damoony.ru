#!/usr/bin/env php
<?php

declare(strict_types=1);

use Damoony\Cli\Application;
use Damoony\Cli\ArgvParser;
use Damoony\Cli\CommandRegistry;
use Damoony\Cli\HelpCommand;
use Damoony\Cli\Renderer;

require_once __DIR__ . '/../vendor/autoload.php';

$registry = new CommandRegistry();
$registry->add(new HelpCommand($registry));

$app = new Application($registry, new ArgvParser(), new Renderer());
exit($app->run($_SERVER['argv']));