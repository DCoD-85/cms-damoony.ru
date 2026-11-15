<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$input = json_decode((string) file_get_contents('php://input'), true);
$key = (string) ($input['key'] ?? '');
$domain = (string) ($input['domain'] ?? '');

if ($key === '' || $domain === '') {
    echo json_encode(['ok' => false, 'error' => 'key and domain required']);
    exit;
}

// activate.php лежит в www/api/, а request.php в www/.
// data/licenses.json лежит в корне проекта (www/../data)
$dataDir = dirname(__DIR__) . '/../data';
$licFile = $dataDir . '/licenses.json';

if (!is_file($licFile)) {
    echo json_encode(['ok' => false, 'error' => 'License registry not found']);
    exit;
}

$licenses = json_decode(file_get_contents($licFile), true) ?: [];
$match = null;
foreach ($licenses as $lic) {
    if (($lic['key'] ?? '') === $key && ($lic['domain'] ?? '') === $domain && ($lic['status'] ?? '') === 'active') {
        $match = $lic;
        break;
    }
}

// Логируем попытку активации
file_put_contents($dataDir . '/activations.log', date('Y-m-d H:i:s') . " key=$key domain=$domain result=" . ($match ? 'ok' : 'fail') . "\n", FILE_APPEND);

if (!$match) {
    echo json_encode(['ok' => false, 'error' => 'Invalid license or domain mismatch']);
    exit;
}

echo json_encode([
    'ok' => true,
    'download_url' => 'https://damoony.ru/downloads/core-v4.zip',
]);
