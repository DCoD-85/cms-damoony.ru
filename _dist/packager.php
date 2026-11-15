<?php
declare(strict_types=1);

// Ищем корень проекта (поднимаемся вверх, пока не найдем bin/cli.php)
$dir = __DIR__;
while (!is_file($dir . '/bin/cli.php') && dirname($dir) !== $dir) {
    $dir = dirname($dir);
}
$sourceDir = $dir;

$zipFile = $sourceDir . '/www/downloads/core-v4.zip';

$downloadsDir = dirname($zipFile);
if (!is_dir($downloadsDir)) {
    mkdir($downloadsDir, 0755, true);
}

$exclude = [
    '.git', '.github', '.gitignore', '.gitattributes',
    'app', 'config', 'var', 'content', 'data',
    '_dev', '_doc', '_dist', '_trash_v3', '_old_backup',
    'ai_agent', 'a.py',
    'www/downloads', 'www/api', 'www/request.php', 'www/packager.php',
    'api', 'request.php',
    'packager.php', 'install.php', '.env', '.idea', 'node_modules',
    'CREDENTIALS.txt', 'INSTALL_INSTRUCTION.txt', 
    '.phpunit.cache', '.php-cs-fixer.cache', '.phpunit.result.cache'
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
