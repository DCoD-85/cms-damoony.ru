<?php

declare(strict_types=1);

use Damoony\Core\Application;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use Damoony\Http\Kernel;
use Damoony\Http\Request;
use Damoony\Http\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$env = Env::fromServer();

$configFile = $root . '/config/engine.config';
$config = is_file($configFile) ? Config::fromFile($configFile) : Config::fromArray([]);

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

$handler = static function (Request $request) use ($env): Response {
    return Response::text("Damoony engine: bootstrap OK\nenv: {$env->value}\npath: {$request->path}\nphp: " . PHP_VERSION);
};

$kernel = new Kernel($handler, $logger, $env);
$kernel->handle(Request::fromGlobals())->send();