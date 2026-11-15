from pathlib import Path

# 1. Новый чистый index.php
index_php = """<?php

declare(strict_types=1);

use Damoony\\App\\AppFactory;
use Damoony\\App\\FrontController;
use Damoony\\App\\PageHandlerInterface;
use Damoony\\Http\\Request;
use Damoony\\Domain\\DomainResolverInterface;
use Damoony\\Session\\NativeSession;
use Damoony\\Admin\\Auth;
use Damoony\\Core\\Config;

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

$ctxRole = $_SESSION['admin_context']['role'] ?? 'superadmin';
$ctxDomain = $_SESSION['admin_context']['domain'] ?? 'damoony.ru';
$ctxAlias = $_SESSION['admin_context']['alias'] ?? 'www';

$resolver = $container->get(DomainResolverInterface::class);
$domainContext = $resolver->resolve($ctxDomain);
if ($ctxAlias !== $domainContext->allias) {
    $domainContext = $domainContext->withAllias($ctxAlias);
}

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
} catch (\\Throwable $e) {
    http_response_code(500);
    echo "<h1>Admin Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
"""
Path("www/admin/index.php").write_text(index_php, encoding="utf-8")
print("Created clean www/admin/index.php")

# 2. Патчим FrontController (чтобы редиректил неавторизованных на /admin/login)
fc_path = Path("src/App/FrontController.php")
fc_text = fc_path.read_text(encoding="utf-8")

find = """        if ($adminRole === null && $match->auth && !$auth->isAuthenticated()) {
            return Response::redirect('/login?return=' . urlencode($request->path), 302);
        }"""
replace = """        if ($match->auth && !$auth->isAuthenticated()) {
            if ($adminRole !== null) {
                return Response::redirect('/admin/login', 302);
            }
            return Response::redirect('/login?return=' . urlencode($request->path), 302);
        }"""
if find in fc_text:
    fc_text = fc_text.replace(find, replace)
    fc_path.write_text(fc_text, encoding="utf-8")
    print("Patched FrontController for Admin auth redirect")