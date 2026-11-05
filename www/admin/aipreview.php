<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\Core\Config;
use Damoony\Files\Paths;
use Damoony\Http\Request;
use Damoony\Session\NativeSession;

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);

try {
    $tabId = $_GET['tab'] ?? '';
    $alias = $_GET['alias'] ?? null;
    $path = $_GET['path'] ?? '/';

    // Отсекаем query string (?t=12345 из cache-buster)
    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        $path = substr($path, 0, $qPos);
    }
    if ($path === '') {
        $path = '/';
    }

    if (!preg_match('/^[a-f0-9]+$/', $tabId)) {
        throw new \RuntimeException('Invalid tab ID');
    }

    // 1. Проверяем авторизацию админа
    $session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $session->start();
    $adminLogin = $session->get('admin_login');
    if (!is_string($adminLogin) || $adminLogin === '') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Forbidden: Admin authentication required.');
    }

    // 2. Проверяем, что сессия существует и принадлежит этому админу
    $paths = new Paths($root);
    $host = $_SERVER['HTTP_HOST'] ?? '';

    // Резолвим хост в реальный домен
    $siteConfigFile = $root . '/config/site.config';
    $site = is_file($siteConfigFile) ? Config::fromFile($siteConfigFile) : Config::fromArray([]);
    $resolver = new \Damoony\Domain\ConfigDomainResolver($site);

    try {
        $domainCtx = $resolver->resolve($host);
        $realDomain = $domainCtx->real;
    } catch (\Throwable) {
        $realDomain = $host;
    }

    if ($alias !== null && !preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
        throw new \RuntimeException('Invalid alias');
    }

    $sessionDir = $paths->sessionDir($tabId, $realDomain);
    $metaFile = $sessionDir . '/.session.json';

    if (!is_file($metaFile)) {
        throw new \RuntimeException('Session not found');
    }

    $meta = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        throw new \RuntimeException('Invalid session meta');
    }

    // 3. Создаем Request и передаем в AppFactory с флагом сессии
    $request = Request::fromGlobals();
    $request = $request->withPath($path); // подменяем путь на реальный

    $app = AppFactory::create($root);
    $container = $app->container();

    // Получаем FrontController и передаем ему ID сессии
    $pages = $container->get(\Damoony\App\PageHandlerInterface::class);
    if (!$pages instanceof \Damoony\App\PageHandlerInterface) {
        throw new \RuntimeException('PageHandler missing');
    }
    $fc = new \Damoony\App\FrontController($container, $pages);
    $response = $fc->handle($request, sessionId: $tabId, aliasOverride: $alias);

    // 4. Внедряем <base> тег и Wireframe CSS
    $contentType = $response->headers['content-type'] ?? '';
    if (str_contains((string)$contentType, 'text/html')) {
        $baseUrl = "/admin/preview/{$tabId}/";
        $html = str_replace('<head>', "<head><base href=\"{$baseUrl}\">", $response->body);
        
        $mode = $_GET['mode'] ?? '';
        if ($mode === 'wireframe') {
            $wireframeCss = '<style>
                body { margin: 0; padding: 10px; box-sizing: border-box; }
                .layout { display: flex; flex-direction: column; gap: 15px; min-height: 100vh; }
                .layout > div { border: 2px dashed #e74c3c; padding: 15px; position: relative; background: rgba(231, 76, 60, 0.05); }
                .layout > div::before { content: "GLOBAL REGION"; position: absolute; top: -12px; left: 10px; background: #e74c3c; color: white; padding: 2px 6px; font-size: 10px; border-radius: 3px; font-weight: bold; letter-spacing: 1px; }
                .layout .page-content { border-color: #2ecc71; background: rgba(46, 204, 113, 0.05); }
                .layout .page-content::before { content: "CENTER (LOCAL BLOCKS)"; background: #2ecc71; }
                .page-content > * { border: 1px solid #3498db; margin: 10px 0; padding: 10px; position: relative; background: #fff; }
                .page-content > *::before { content: attr(class); position: absolute; top: -9px; right: 5px; background: #3498db; color: white; font-size: 9px; padding: 1px 5px; border-radius: 2px; }
            </style>';
            $html = str_replace('</head>', $wireframeCss . '</head>', $html);
        }

        $response = new \Damoony\Http\Response(
            $html,
            $response->status,
            $response->headers,
        );
    }

    $response->send();

} catch (\Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found'; // Скрываем детали
}
