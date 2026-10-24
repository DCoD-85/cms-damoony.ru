<?php
declare(strict_types=1);

// Front controller. Единственная точка входа.
// Никакого require_once, никаких $_POST/PDO/echo в бизнес-логике.

$autoload = __DIR__ . '/../_dev/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('composer install required (cd _dev && composer install)');
}
require $autoload;

// TODO(architect): Kernel::boot()->handle()
echo 'cms-damoony :: bootstrap';
