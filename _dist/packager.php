<?php
// packager.php лежит в www/
// Сканируем корень проекта, ZIP кладем в www/downloads/
$sourceDir = dirname(__DIR__);              // /var/www/damoony.ru/
$zipFile = __DIR__ . '/downloads/core-v4.zip'; // /var/www/damoony.ru/www/downloads/

$downloadsDir = dirname($zipFile);
if (!is_dir($downloadsDir)) {
    mkdir($downloadsDir, 0777, true);
}

$exclude = [
    '.git', 'app', 'config', 'var', 'content',
    'www/downloads', 'www/api', 'www/request.php', 'www/packager.php',
    'data', '_dist',
    'packager.php', '.env', '.idea', 'node_modules',
    'install.php', '.github', '.gitignore', '.gitattributes',
    'CREDENTIALS.txt', 'INSTALL_INSTRUCTION.txt',
];

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "ОШИБКА: не могу открыть архив\n";
    exit(1);
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);

        $excludeFile = false;
        foreach ($exclude as $ex) {
            if ($relativePath === $ex || str_starts_with($relativePath, $ex . '/')) {
                $excludeFile = true;
                break;
            }
        }
        if (!$excludeFile) {
            $zip->addFile($filePath, $relativePath);
        }
    }
}

if (!$zip->close()) {
    echo "ОШИБКА: не могу закрыть архив\n";
    exit(1);
}

echo "Архив создан: {$zipFile}\n";
echo "Размер: " . number_format(filesize($zipFile)) . " байт\n";