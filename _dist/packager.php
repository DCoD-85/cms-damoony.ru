<?php
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
    echo "Архив создан: downloads/core-v4.zip\n";
}