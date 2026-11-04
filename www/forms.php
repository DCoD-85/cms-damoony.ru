<?php

declare(strict_types=1);

use Damoony\App\AppFactory;
use Damoony\Db\DataProvider\FormSubmissionProvider;
use Damoony\Db\Connection;
use Damoony\Core\Config;

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

    try {
        $provider->submit($data, $formName, $ip);
    } catch (\JsonException $e) {
        throw new \RuntimeException('JSON encode failed: ' . $e->getMessage() . ' (data: ' . print_r($data, true) . ')');
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $referer . '?sent=1');
    exit;

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error: " . $e->getMessage();
    exit;
}
