<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\App\FrontController;
use Damoony\App\PageHandlerInterface;
use Damoony\Http\Kernel;
use Damoony\Http\Middleware\RateLimitMiddleware;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\MiddlewareStack;
use Damoony\Http\Request;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$app = AppFactory::create($root);
$container = $app->container();

$pages = $container->get(PageHandlerInterface::class);
assert($pages instanceof PageHandlerInterface);

$fc = new FrontController($container, $pages);

$stack = new MiddlewareStack($container);
$stack->add(new SecurityHeadersMiddleware(
    isHttps: ($_SERVER['HTTPS'] ?? '') === 'on',
    csp: (string) $app->config()->get('csp', "default-src 'self'; frame-ancestors 'none'"),
));
$stack->add(new RateLimitMiddleware(
    storageDir: $root . '/var/ratelimit',
    max: (int) $app->config()->get('rate_limit.max', 120),
    window: (int) $app->config()->get('rate_limit.window', 60),
    ban: (int) $app->config()->get('rate_limit.ban', 900),
));

$handler = static fn (Request $r) => $stack->dispatch($r, static fn (Request $r2) => $fc->handle($r2));

$kernel = new Kernel($handler, $app->logger(), $app->env());
$kernel->handle(Request::fromGlobals())->send();