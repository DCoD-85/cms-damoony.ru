<?php
header('Content-Type: application/json');
$validKeys = [
    'TEST-KEY-1234' => ['domain' => 'idoam.ru', 'status' => 'active']
];
$input = json_decode(file_get_contents('php://input'), true);
$key = $input['key'] ?? '';
$domain = $input['domain'] ?? '';
if (!isset($validKeys[$key]) || $validKeys[$key]['domain'] !== $domain) {
    echo json_encode(['ok' => false, 'error' => 'Invalid license or domain mismatch']);
    exit;
}
echo json_encode([
    'ok' => true,
    'download_url' => 'https://damoony.ru/downloads/core-v4.zip',
    'hash' => 'HASH_OF_ZIP_FILE'
]);
