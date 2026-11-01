<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\Auth\AuthInterface;
use Damoony\Content\ContentResponder;
use Damoony\Files\Paths;

require_once __DIR__ . '/../vendor/autoload.php';

// === 1. HTTP-метод ===
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Method Not Allowed';
    exit;
}

// === 2. Парсинг URI ===
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$req = ContentResponder::parse($requestUri);
if ($req === null) {
    _not_found();
}

// === 3. Host check (не палим чужие домены) ===
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (!ContentResponder::hostAllowed($req->domain, $host)) {
    _not_found();
}

// === 4. Bootstrap (Paths + Auth) ===
$root = dirname(__DIR__);
try {
    $app = AppFactory::create($root);
    $container = $app->container();
    $paths = $container->get(Paths::class);
    if (!$paths instanceof Paths) {
        throw new \RuntimeException('Paths service missing');
    }
} catch (\Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Internal Error';
    exit;
}

// === 5. Файл существует? ===
$file = $paths->content($req->domain, $req->allias)
    . '/' . $req->category . '/' . $req->hash . '.' . $req->ext;
if (!is_file($file)) {
    _not_found();
}

// === 6. Meta (page_refs, mime) ===
$metaFile = dirname($file) . '/' . $req->hash . '.meta.json';
$meta = [];
if (is_file($metaFile)) {
    $raw = @file_get_contents($metaFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }
}

// === 7. Auth (если хоть одна страница-родитель с auth:true) ===
$requiresAuth = ContentResponder::requiresAuth($req, $meta, $paths);
if ($requiresAuth) {
    try {
        $auth = $container->get(AuthInterface::class);
    } catch (\Throwable) {
        $auth = null;
    }
    if (!ContentResponder::userCanAccess(true, $auth instanceof AuthInterface ? $auth : null)) {
        http_response_code(302);
        header('Location: ' . ContentResponder::loginRedirectUrl($req->originalUri));
        exit;
    }
}

// === 8. Headers ===
$mime = ContentResponder::resolveMime($req->ext, $meta);
$size = (int) filesize($file);
$mtime = (int) filemtime($file);
$etag = '"' . $req->hash . '"';

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

// SVG: CSP для защиты от inline-скриптов в new tab
if ($mime === 'image/svg+xml') {
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
}

// === 9. Conditional (304) ===
$ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

// === 10. Body ===
header('Content-Length: ' . $size);
if ($method === 'GET') {
    readfile($file);
}
exit;


function _not_found(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}