<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$key = $input['key'] ?? '';
$domain = $input['domain'] ?? '';

$licFile = __DIR__ . '/../../data/licenses.json';
$licenses = is_file($licFile) ? json_decode(file_get_contents($licFile), true) : [];

if (!is_array($licenses)) $licenses = [];

foreach ($licenses as $lic) {
    if ($lic['key'] === $key && $lic['domain'] === $domain && $lic['status'] === 'active') {
        echo json_encode([
            'ok' => true,
            'download_url' => 'https://damoony.ru/downloads/core-v4.zip',
            'hash' => '',
        ]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Invalid license or domain mismatch']);