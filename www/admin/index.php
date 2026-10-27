<?php
declare(strict_types=1);

use Damoony\Admin\AdminRouter;
use Damoony\Admin\Auth;
use Damoony\Admin\Controller\AliasDbController;
use Damoony\Admin\Controller\AliasSchemeController;
use Damoony\Admin\Controller\AlliasController;
use Damoony\Admin\Controller\BaseController;
use Damoony\Admin\Controller\DashboardController;
use Damoony\Admin\Controller\DomainController;
use Damoony\Admin\Controller\EditorController;
use Damoony\Admin\Controller\LoginController;
use Damoony\Admin\Controller\LogoutController;
use Damoony\Admin\Controller\SecurityController;
use Damoony\Admin\Controller\StubController;
use Damoony\Admin\View;
use Damoony\Core\Config;
use Damoony\Core\Env;
use Damoony\Core\ErrorHandler;
use Damoony\Core\Logger;
use Damoony\Files\Paths;
use Damoony\Http\Kernel;
use Damoony\Http\Middleware\RateLimitMiddleware;
use Damoony\Http\Middleware\SecurityHeadersMiddleware;
use Damoony\Http\MiddlewareStack;
use Damoony\Http\Request;
use Damoony\Security\Csrf;
use Damoony\Session\NativeSession;
use Damoony\Admin\Controller\ProfileController;

$root = dirname(__DIR__, 2);
if (is_file($root . '/.dev')) {
    putenv('APP_ENV=dev');
}

require_once $root . '/vendor/autoload.php';

$env = Env::fromServer();

$adminConfigFile = $root . '/config/admin.config';
$adminConfig = is_file($adminConfigFile) ? Config::fromFile($adminConfigFile) : Config::fromArray([]);

$siteConfigFile = $root . '/config/site.config';
$siteConfig = is_file($siteConfigFile) ? Config::fromFile($siteConfigFile) : Config::fromArray([]);

$engineConfigFile = $root . '/config/engine.config';
$engineConfig = is_file($engineConfigFile) ? Config::fromFile($engineConfigFile) : Config::fromArray([]);

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
    logsFile: $root . '/var/admin_logs.json',
    ip: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
);

$view = new View('Damoony Admin');
\Damoony\Admin\View::setLoggedIn($auth->isLoggedIn());
$adminLangs = $adminConfig->get('domains_langs', ['ru']);
if (!is_array($adminLangs) || $adminLangs === []) {
    $adminLangs = ['ru'];
}
$defaultLang = (string) $adminConfig->get('default_lang', 'ru');
if (!in_array($defaultLang, $adminLangs, true)) {
    $defaultLang = (string) $adminLangs[0];
}

$reqLang = (string) ($_GET['lang'] ?? '');
if ($reqLang !== '' && in_array($reqLang, $adminLangs, true)) {
    if (!headers_sent()) {
        setcookie('admin_lang', $reqLang, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
    $currentLang = $reqLang;
} else {
    $cookieLang = (string) ($_COOKIE['admin_lang'] ?? '');
    $currentLang = in_array($cookieLang, $adminLangs, true) ? $cookieLang : $defaultLang;
}

View::setLang(new \Damoony\I18n\Translator($currentLang, $defaultLang));

$paths = new Paths($root);

$router = new AdminRouter();
$router->register('login', new LoginController($auth, $csrf, $view));
$router->register('logout', new LogoutController($auth, $csrf, $view));
$router->register('dashboard', new DashboardController($auth, $csrf, $view, $siteConfig));
$router->register('domain', new DomainController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('allias', new AlliasController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('block', new \Damoony\Admin\Controller\BlockController($auth, $csrf, $view, $paths));
$router->register('page', new \Damoony\Admin\Controller\PageController($auth, $csrf, $view, $paths));
$router->register('filler', new \Damoony\Admin\Controller\FillerController($auth, $csrf, $view, $paths, $siteConfig));
$router->register('scheme', new \Damoony\Admin\Controller\SchemeController($auth, $csrf, $view, $paths));
$router->register('media', new \Damoony\Admin\Controller\MediaController($auth, $csrf, $view, $root));
$router->register('settings', new \Damoony\Admin\Controller\SettingsController($auth, $csrf, $view, $siteConfig, $root));
$router->register('editor', new EditorController($auth, $csrf, $view, $root));

// Заглушки для БД (чтобы не было 404)
$router->register('domain_db', new class($auth, $csrf, $view) extends BaseController {
    public function index(\Damoony\Http\Request $request): \Damoony\Http\Response {
        $domain = (string) $request->query('domain', '');
        $card = \Damoony\Ui\Builder::div('admin-card');
        $card->append(\Damoony\Ui\Builder::h(1)->content('База данных домена: ' . $domain));
        $card->append(\Damoony\Admin\View::domainTabs($domain, 'db'));
        $card->append(\Damoony\Ui\Message::info('Раздел находится в разработке.')->build());
        return \Damoony\Http\Response::html($this->view->render($card, ['title' => 'БД домена']));
    }
});

$router->register('allias_scheme', new AliasSchemeController($auth, $csrf, $view));
$router->register('allias_db', new AliasDbController($auth, $csrf, $view));
$router->register('stub', new StubController($auth, $csrf, $view));
$router->register('security', new SecurityController($auth, $csrf, $view));

$router->register('profile', new ProfileController($auth, $csrf, $view, $adminConfig, $root));

// Middleware stack
$loopback = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
$rlWhitelist = $env->isDev() ? $loopback : [];

$stack = new MiddlewareStack(new \Damoony\Core\Container());
$stack->add(new SecurityHeadersMiddleware(
    isHttps: ($_SERVER['HTTPS'] ?? '') === 'on',
    csp: (string) $engineConfig->get('csp', "default-src 'self'; frame-ancestors 'none'"),
));
// Админка: строже лимит, чем фронт; отдельный storageDir, чтобы не смешивать счётчики
$stack->add(new RateLimitMiddleware(
    storageDir: $root . '/var/ratelimit/admin',
    max: 60,
    window: 60,
    ban: 900,
    whitelist: $rlWhitelist,
));

$handler = static fn (Request $r) => $stack->dispatch(
    $r,
    static fn (Request $r2) => $router->dispatch($r2)
);

$kernel = new Kernel($handler, $logger, $env);
$kernel->handle(Request::fromGlobals())->send();