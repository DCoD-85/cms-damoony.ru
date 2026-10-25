<?php
declare(strict_types=1);

use Damoony\Admin\AdminRouter;
use Damoony\Admin\Auth;
use Damoony\Admin\Controller\DashboardController;
use Damoony\Admin\Controller\LoginController;
use Damoony\Admin\Controller\LogoutController;
use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Core\Container;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use Damoony\Http\Kernel;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\Request;
use Damoony\Security\Csrf;
use Damoony\Session\NativeSession;

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$env = Env::fromServer();

$adminConfigFile = $root . '/config/admin.config';
$adminConfig = is_file($adminConfigFile) ? Config::fromFile($adminConfigFile) : Config::fromArray([]);

$logger = new Logger(null, $env);
$errorHandler = new ErrorHandler($logger, $env);
$errorHandler->register();

$session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
$session->start();

$csrf = new Csrf($session);
$auth = new Auth(
    adminConfig: $adminConfig,
    session: $session,
    failsFile: $root . '/var/admin_fails.json',
    ip: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
);

$view = new View('Damoony Admin');

$router = new AdminRouter();
$router->register('login', new LoginController($auth, $csrf, $view));
$router->register('logout', new LogoutController($auth, $csrf, $view));
$router->register('dashboard', new DashboardController($auth, $csrf, $view));

$security = new SecurityHeadersMiddleware(isHttps: ($_SERVER['HTTPS'] ?? '') === 'on');
$handler = static fn (Request $r) => $security->process($r, static fn (Request $r2) => $router->dispatch($r2));

$kernel = new Kernel($handler, $logger, $env);
$kernel->handle(Request::fromGlobals())->send();