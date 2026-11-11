<?php

declare(strict_types=1);

use Damoony\Admin\Auth;
use Damoony\Core\App\AppFactory;
use Damoony\Core\App\FrontController;
use Damoony\Core\App\PageHandlerInterface;
use Damoony\Core\Config;
use Damoony\Core\Domain\DomainResolverInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Session\NativeSession;

require_once __DIR__ . '/../../vendor/autoload.php';
$root = dirname(__DIR__, 2);

$app = AppFactory::create($root);
$container = $app->container();

$adminConfig = Config::fromFile($root . '/config/admin.config');
$session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
$session->start();

$auth = new Auth(
    adminConfig: $adminConfig,
    session: $session,
    failsFile: $root . '/var/admin_fails.json',
    logsFile: $root . '/var/admin_logs.json',
    ip: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
);

$pages = $container->get(PageHandlerInterface::class);
$req = Request::fromGlobals();

$defaultRole = (string) $adminConfig->getByKey(['default_role'], '');
if ($defaultRole === '') {
    http_response_code(500);
    echo 'admin.config.default_role not set';
    exit;
}
$ctxRole = (string) ($_SESSION['admin_context']['role'] ?? $defaultRole);
if (!preg_match('/^[a-z][a-z0-9_]*$/', $ctxRole)) {
    http_response_code(400);
    echo 'invalid role';
    exit;
}
$resolver = $container->get(DomainResolverInterface::class);
$domainContext = $resolver->resolve('.admin')->withAllias($ctxRole);

$path = $req->path;
if (str_starts_with($path, '/admin')) {
    $path = substr($path, 6);
}
if ($path === '') {
    $path = '/';
}
$adminRequest = $req->withPath($path);

try {
    $fc = new FrontController($container, $pages);
    $response = $fc->handle($adminRequest, null, null, $ctxRole, $domainContext, $auth);
    $response->send();
} catch (\Throwable $e) {
    http_response_code(500);
    echo '<h1>Admin Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
