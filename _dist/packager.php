<?php
$sourceDir = dirname(__DIR__);
$zipFile = $sourceDir . '/downloads/core-v4.zip';

// Создаем папку, если ее нет (фикс ошибки No such file or directory)
@mkdir(dirname($zipFile), 0755, true);

$exclude = [
    '.git', 'app', 'config', 'var', 'content', 'downloads', 'api',
    'packager.php', '.env', '.idea', 'node_modules', 'install.php',
    '.github', '.gitignore', '.gitattributes', '_dist', 'data',
    'CREDENTIALS.txt', 'INSTALL_INSTRUCTION.txt'
];

$zip = new ZipArchive();
$result = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($result !== true) {
    echo "ОШИБКА: не могу открыть архив для записи ($result)\n";
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
            if (str_starts_with($relativePath, $ex . '/') || $relativePath === $ex) {
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

echo "Архив создан: downloads/core-v4.zip\n";
echo "Размер: " . number_format(filesize($zipFile)) . " байт\n";