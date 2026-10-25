<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\App\FrontController;
use Damoony\Http\Kernel;
use Damoony\Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$app = AppFactory::create($root);

$fc = new FrontController($app->container());
$kernel = new Kernel(
    static fn (Request $r) => $fc->handle($r),
    $app->logger(),
    $app->env(),
);

$kernel->handle(Request::fromGlobals())->send();