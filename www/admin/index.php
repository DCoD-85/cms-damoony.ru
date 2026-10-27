<?php
declare(strict_types=1);

putenv('APP_ENV=dev');

use Damoony\Admin\AdminRouter;
use Damoony\Admin\Auth;
use Damoony\Admin\Controller\AlliasController;
use Damoony\Admin\Controller\DashboardController;
use Damoony\Admin\Controller\DomainController;
use Damoony\Admin\Controller\LoginController;
use Damoony\Admin\Controller\LogoutController;
use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use Damoony\Files\Paths;
use Damoony\Http\Kernel;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\Request;
use Damoony\Security\Csrf;
use Damoony\Session\NativeSession;
use Damoony\Admin\Controller\EditorController;

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);
$env = Env::fromServer();

$adminConfigFile = $root . '/config/admin.config';
$adminConfig = is_file($adminConfigFile) ? Config::fromFile($adminConfigFile) : Config::fromArray([]);

$siteConfigFile = $root . '/config/site.config';
$siteConfig = is_file($siteConfigFile) ? Config::fromFile($siteConfigFile) : Config::fromArray([]);

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
$paths = new Paths($root);

$router = new AdminRouter();
$router->register('login', new LoginController($auth, $csrf, $view));
$router->register('logout', new LogoutController($auth, $csrf, $view));
$router->register('dashboard', new DashboardController($auth, $csrf, $view, $siteConfig));
$router->register('domain', new DomainController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('allias', new AlliasController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('block', new \Damoony\Admin\Controller\BlockController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('page', new \Damoony\Admin\Controller\PageController($auth, $csrf, $view, $paths));
$router->register('filler', new \Damoony\Admin\Controller\FillerController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('scheme', new \Damoony\Admin\Controller\SchemeController($auth, $csrf, $view, $paths));
$router->register('media', new \Damoony\Admin\Controller\MediaController($auth, $csrf, $view, $root));
$router->register('settings', new \Damoony\Admin\Controller\SettingsController($auth, $csrf, $view, $siteConfig, $root));
$router->register('editor', new EditorController($auth, $csrf, $view, $root));

$security = new SecurityHeadersMiddleware(isHttps: ($_SERVER['HTTPS'] ?? '') === 'on');
$handler = static fn (Request $r) => $security->process($r, static fn (Request $r2) => $router->dispatch($r2));

$kernel = new Kernel($handler, $logger, $env);
$kernel->handle(Request::fromGlobals())->send();