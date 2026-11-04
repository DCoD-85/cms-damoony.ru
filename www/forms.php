<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\Core\Config;
use Damoony\Db\Connection;
use Damoony\Db\DataProvider\FormSubmissionProvider;
use Damoony\Http\Middleware\RateLimitMiddleware;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\Security\Csrf;

require_once __DIR__ . '/../vendor/autoload.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$root = dirname(__DIR__);

try {
    $app = AppFactory::create($root);
    $container = $app->container();

    // 1. Rate limit (5 запросов/мин, ban 5 мин)
    $middleware = new RateLimitMiddleware(
        storageDir: $root . '/var/form-ratelimit',
        max: 5,
        window: 60,
        ban: 300,
    );
    $request = Request::fromGlobals();
    $resp = $middleware->process($request, static fn (Request $r): Response => Response::text('OK', 200));
    if ($resp->status === 429) {
        http_response_code(429);
        header('Retry-After: ' . ($resp->headers['retry-after'] ?? '60'));
        exit('Too Many Requests');
    }

    // 2. CSRF
    $csrf = $container->get(Csrf::class);
    assert($csrf instanceof Csrf);
    if (!$csrf->check($_POST)) {
        http_response_code(403);
        exit('CSRF token invalid');
    }

    // 3. Запись в БД
    $db = $container->get(Connection::class);
    assert($db instanceof Connection);
    $site = $container->get('site');
    assert($site instanceof Config);

    $provider = new FormSubmissionProvider($db, $site, 'damoony.ru', 'www');

    $data = $_POST;
    unset($data['_csrf']);

    $formName = (string) ($_POST['_form_name'] ?? 'contact');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if ($data === []) {
        throw new \RuntimeException('Empty form data');
    }

    $provider->submit($data, $formName, $ip);

    // 4. Flash через ?sent=1
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $referer . '?sent=1');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: ' . $e->getMessage();
    exit;
}
