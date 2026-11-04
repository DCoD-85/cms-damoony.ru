<?php
declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\Core\Config;
use Damoony\Domain\DomainContext;
use Damoony\Files\Paths;
use Damoony\Session\NativeSession;
use Damoony\Http\Request;

require_once __DIR__ . '/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);

try {
    $tabId = $_GET['tab'] ?? '';
    $path = $_GET['path'] ?? '/';
    
    if (!preg_match('/^[a-f0-9]+$/', $tabId)) {
        throw new \RuntimeException('Invalid tab ID');
    }

    // 1. Проверяем авторизацию админа
    $session = new NativeSession('ADMINSESSID', ($_SERVER['HTTPS'] ?? '') === 'on');
    $session->start();
    $adminSessionId = session_id();

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

    $sessionDir = $paths->sessionDir($tabId, $realDomain);
    $metaFile = $sessionDir . '/.session.json';

    if (!is_file($metaFile)) {
        throw new \RuntimeException('Session not found');
    }

    $meta = json_decode((string)file_get_contents($metaFile), true);
    if (!is_array($meta) || $meta['admin_session_id'] !== $adminSessionId) {
        throw new \RuntimeException('Access denied');
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
    $response = $fc->handle($request, sessionId: $tabId);
    
    // 4. Внедряем <base> тег, чтобы ссылки внутри превью не ломали адреса
    $contentType = $response->headers['content-type'] ?? '';
    if (str_contains((string)$contentType, 'text/html')) {
        $baseUrl = "/admin/preview/{$tabId}/";
        $response = new \Damoony\Http\Response(
            str_replace('<head>', "<head><base href=\"{$baseUrl}\">", $response->body),
            $response->status,
            $response->headers
        );
    }

    $response->send();

} catch (\Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found'; // Скрываем детали
}
