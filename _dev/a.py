from pathlib import Path

BASE = Path.cwd()
DIST = BASE / "_dist"
DIST.mkdir(exist_ok=True)

# 1. Скрипт-установщик (для клиентов)
installer = '''<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? 'key';
$message = '';

if ($step === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = $_POST['license_key'] ?? '';
    $domain = $_SERVER['HTTP_HOST'];
    $ch = curl_init('https://damoony.ru/api/activate.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['key' => $key, 'domain' => $domain]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = json_decode(curl_exec($ch), true);
    
    if ($data && $data['ok']) {
        $zipUrl = $data['download_url'];
        $zipFile = __DIR__ . '/core.zip';
        $ch = curl_init($zipUrl);
        $fp = fopen($zipFile, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        fclose($fp);
        
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo(dirname(__DIR__));
            $zip->close();
            unlink($zipFile);
            $step = 'done';
        } else { $message = "Ошибка распаковки."; }
    } else { $message = "Ошибка активации."; }
}
?>
<!DOCTYPE html>
<html><head><title>Установка Damoony</title></head><body>
    <h2>Установка Damoony</h2>
    <?php if ($message) echo "<p style='color:red'>$message</p>"; ?>
    <?php if ($step === 'key'): ?>
        <form method="POST" action="?step=process">
            <input type="text" name="license_key" placeholder="Лицензионный ключ" required>
            <button type="submit">Шаг 1: Скачать ядро</button>
        </form>
    <?php elseif ($step === 'done'): ?>
        <p style="color:green">Ядро скачано! Удалите install.php и выполните настройку через CLI.</p>
    <?php endif; ?>
</body></html>'''

(DIST / "install.php").write_text(installer, encoding="utf-8")

# 2. Скрипт Вендора (API активации)
api_dir = DIST / "vendor-api"
api_dir.mkdir(exist_ok=True)

activate_php = '''<?php
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
'''
(api_dir / "activate.php").write_text(activate_php, encoding="utf-8")

# 3. Скрипт-упаковщик (Packager)
packager = '''<?php
$sourceDir = dirname(__DIR__);
$zipFile = $sourceDir . '/downloads/core-v4.zip';
$exclude = ['.git', 'app', 'config', 'var', 'content', 'downloads', 'api', 'packager.php', '.env', '.idea', 'node_modules', 'install.php', '.github', '.gitignore', '.gitattributes', '_dist'];
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $excludeFile = false;
            foreach ($exclude as $ex) {
                if (str_starts_with($relativePath, $ex . '/') || $relativePath === $ex) { $excludeFile = true; break; }
            }
            if (!$excludeFile) { $zip->addFile($filePath, $relativePath); }
        }
    }
    $zip->close();
    echo "Архив создан: downloads/core-v4.zip\\n";
}'''
(DIST / "packager.php").write_text(packager, encoding="utf-8")

# 4. Инструкция по деплою
readme = """# Damoony Distribution Kit

Этот каталог содержит скрипты для организации продаж и развертывания движка.

## Файлы:
- `install.php` — Скрипт-установщик (Bootstrapper) для клиентов. Класть в корень пустого сайта.
- `vendor-api/activate.php` — API для выдачи лицензий. Класть на сервер Вендора.
- `packager.php` — Утилита сборки ZIP-архива для клиентов. Класть на сервер Вендора.

## Как развернуть Вендора (damoony.ru):
1. Скопировать `vendor-api/activate.php` в публичную папку домена.
2. Скопировать `packager.php` в корень движка.
3. Выполнить `php packager.php` для создания `downloads/core-v4.zip`.

## Как установить движок (Клиент):
1. Залить `install.php` в корень пустого сайта (в webroot).
2. Открыть в браузере и ввести лицензионный ключ.
3. Установщик скачает ядро и распакует его на уровень выше webroot.
"""
(DIST / "README.md").write_text(readme, encoding="utf-8")

print("OK: Distribution kit created in _dist/")