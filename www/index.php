<?php
declare(strict_types=1);

// Front controller. Вся логика — за DI-контейнером (агент-архитектор).
// Никакого require_once, никаких $_POST/PDO/echo в бизнес-логике.

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit('composer install required');
}
require $autoload;

// TODO(architect): Kernel::boot()->handle()
echo 'cms-damoony :: bootstrap';
