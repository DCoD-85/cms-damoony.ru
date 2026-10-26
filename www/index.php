<?php
declare(strict_types=1);

$start = microtime(true); // Запуск таймера

use Damoony\App\AppFactory;
use Damoony\App\FrontController;
use Damoony\App\PageHandlerInterface;
use Damoony\Http\Kernel;
use Damoony\Http\Middleware\RateLimitMiddleware;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\MiddlewareStack;
use Damoony\Http\Request;
use Damoony\Http\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
putenv('APP_ENV=dev'); // Включаем dev-режим для плашки таймера
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
$response = $kernel->handle(Request::fromGlobals());

// Остановка таймера и внедрение красивой плашки
$time = round((microtime(true) - $start) * 1000, 2);
$contentType = (string) ($response->headers['content-type'] ?? '');

if (str_contains($contentType, 'text/html') && $app->env()->isDev()) {
    $badge = sprintf(
        '<div style="position:fixed; bottom:12px; right:12px; background:rgba(29,29,31,0.85); color:#fff; padding:6px 12px; border-radius:6px; font-family:monospace; font-size:12px; z-index:99999; box-shadow:0 2px 10px rgba(0,0,0,0.2); pointer-events:none; backdrop-filter: blur(4px);">⚡ %s ms</div>',
        $time
    );
    
    // Вставляем перед </body>, или просто в конец, если </body> нет
    $body = str_replace('</body>', $badge . '</body>', $response->body);
    
    $response = new Response(
        $body,
        $response->status,
        $response->headers
    );
}

$response->send();