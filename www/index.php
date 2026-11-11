<?php

declare(strict_types=1);

$start = microtime(true); // Запуск таймера

use Damoony\Core\App\AppFactory;
use Damoony\Core\App\FrontController;
use Damoony\Core\App\PageHandlerInterface;
use Damoony\Core\Http\Kernel;
use Damoony\Core\Http\Middleware\LanguageCookieMiddleware;
use Damoony\Core\Http\Middleware\RateLimitMiddleware;
use Damoony\Core\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Core\Http\Middleware\SkinCookieMiddleware;
use Damoony\Core\Http\MiddlewareStack;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\Session\SessionInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
if (is_file($root . '/_dev/.dev')) {
    putenv('APP_ENV=dev');
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
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

$loopback = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
$rlWhitelist = $app->env()->isDev() ? $loopback : [];

$stack->add(new RateLimitMiddleware(
    storageDir: $root . '/var/ratelimit',
    max: (int) $app->config()->get('rate_limit.max', 120),
    window: (int) $app->config()->get('rate_limit.window', 60),
    ban: (int) $app->config()->get('rate_limit.ban', 900),
    whitelist: $rlWhitelist,
));

$siteCfg = $container->get('site');
assert($siteCfg instanceof \Damoony\Core\Config);
$skinsAllowedMap = $siteCfg->getByKey(['domains_skins_allowed'], []);
$skinsFlat = [];
if (is_array($skinsAllowedMap)) {
    foreach ($skinsAllowedMap as $list) {
        if (is_array($list)) {
            foreach ($list as $s) {
                if (is_string($s) && $s !== '') {
                    $skinsFlat[$s] = true;
                }
            }
        }
    }
}
$skinsFlat = array_keys($skinsFlat) ?: ['default'];

$session = $container->get(SessionInterface::class);
if ($session instanceof SessionInterface) {
    $stack->add(new LanguageCookieMiddleware($session));
}

$stack->add(new SkinCookieMiddleware(
    cookieName: 'skin',
    allowed: $skinsFlat,
));

$handler = static fn (Request $r) => $stack->dispatch($r, static fn (Request $r2) => $fc->handle($r2));

$kernel = new Kernel($handler, $app->logger(), $app->env());
$response = $kernel->handle(Request::fromGlobals());
if ($response->status === 429) {
    $retryAfter = (int) ($response->headers['retry-after'] ?? 60);
    $response = \Damoony\Core\Error\ErrorPage::tooManyRequests('ru', $retryAfter);
}

// Остановка таймера и внедрение красивой плашки
$time = round((microtime(true) - $start) * 1000, 2);
$contentType = (string) ($response->headers['content-type'] ?? '');

if (str_contains($contentType, 'text/html') && ($app->env()->isDev() || isset($_GET['debug']))) {
    $badge = sprintf(
        '<div id="dt_benchmark" class="dt_benchmark">⚡ %s ms</div>',
        $time,
    );

    // Вставляем перед </body>, или просто в конец, если </body> нет
    $body = str_replace('</body>', $badge . '</body>', $response->body);

    $response = new Response(
        $body,
        $response->status,
        $response->headers,
    );
}

$response->send();
