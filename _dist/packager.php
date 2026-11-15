<?php
// packager.php кладется в корень проекта (рядом с src/, bin/)
$sourceDir = __DIR__;
$zipFile = __DIR__ . '/www/downloads/core-v4.zip';

$downloadsDir = dirname($zipFile);
if (!is_dir($downloadsDir)) mkdir($downloadsDir, 0755, true);

$exclude = [
    '.git', 'app', 'config', 'var', 'content',
    'www/downloads', 'www/api', 'www/request.php', 'www/packager.php',
    'data', '_dist', 'packager.php', '.env', '.idea', 'node_modules',
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
