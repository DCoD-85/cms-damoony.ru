<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\App\FrontController;
use Damoony\App\PageHandlerInterface;
use Damoony\Http\Kernel;
use Damoony\Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$app = AppFactory::create($root);

$pages = $app->container()->get(PageHandlerInterface::class);
assert($pages instanceof PageHandlerInterface);

$fc = new FrontController($app->container(), $pages);
$kernel = new Kernel(
    static fn (Request $r) => $fc->handle($r),
    $app->logger(),
    $app->env(),
);

$kernel->handle(Request::fromGlobals())->send();