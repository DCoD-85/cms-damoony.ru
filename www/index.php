<?php

declare(strict_types=1);

use Damoony\Core\Application;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$env = Env::fromServer();

$configFile = $root . '/config/engine.config';
$config = is_file($configFile)
    ? Config::fromFile($configFile)
    : Config::fromArray([]);

$container = new Container();
$logger = new Logger(null, $env);
$errorHandler = new ErrorHandler($logger, $env);

$container->setInstance('env', $env);
$container->setInstance('config', $config);
$container->setInstance('logger', $logger);
$container->setInstance('errorHandler', $errorHandler);
$container->setInstance('app', new Application($container, $env, $config, $logger, $errorHandler));

$app = $container->get('app');
assert($app instanceof Application);
$app->boot();

// Пока нет Kernel/Router — заглушка, чтобы front controller был валиден.
header('Content-Type: text/plain; charset=utf-8');
echo "Damoony engine: bootstrap OK\n";
echo "env: " . $env->value . "\n";
echo "php: " . PHP_VERSION . "\n";