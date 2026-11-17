<?php

declare(strict_types=1);

namespace Damoony\Cli\Support;

use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Архив бэкапа проекта: один ZIP с манифестом, разделами app/content/media/db.
 *
 * Манифест — точка правды при восстановлении: что именно лежит в архиве.
 */
final class BackupArchive
{
    public const PARTS = ['app', 'content', 'media', 'db'];

    private const MANIFEST = 'manifest.json';

    /**
     * Разбирает `--only=app,media` в список частей.
     *
     * @return list<string>
     */
    public function parseParts(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return self::PARTS;
        }

        $parts = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!in_array($part, self::PARTS, true)) {
                throw new RuntimeException(sprintf(
                    'Неизвестная часть бэкапа "%s". Допустимо: %s',
                    $part,
                    implode(', ', self::PARTS),
                ));
            }
            $parts[$part] = true;
        }

        if ($parts === []) {
            throw new RuntimeException('Не выбрано ни одной части бэкапа');
        }

        // Порядок частей фиксирован — стабильный манифест.
        $out = [];
        foreach (self::PARTS as $part) {
            if (isset($parts[$part])) {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * Пишет manifest.json в staging-каталог и упаковывает его в ZIP.
     *
     * @param list<string> $parts
     * @param array<string, mixed> $meta
     *
     * @return array<string, mixed> манифест
     */
    public function pack(
        string $stagingDir,
        string $zipPath,
        array $parts,
        array $meta,
        bool $compressMedia = false,
    ): array {
        $manifest = [
            'version' => 1,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'parts' => $this->stats($stagingDir),
        ] + $meta;

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Не удалось собрать manifest.json');
        }

        file_put_contents($stagingDir . '/' . self::MANIFEST, $json . "\n");

        $this->zip($stagingDir, $zipPath, $compressMedia);

        return $manifest;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readManifest(string $dir): ?array
    {
        $file = $dir . '/' . self::MANIFEST;
        if (!is_file($file)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Манифест прямо из ZIP (без распаковки).
     *
     * @return array<string, mixed>|null
     */
    public function readManifestOfArchive(string $zipPath): ?array
    {
        try {
            $phar = new PharData($zipPath);
            if (!isset($phar[self::MANIFEST])) {
                return null;
            }

            $decoded = json_decode($phar[self::MANIFEST]->getContent(), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Удаляет старые бэкапы, оставляя `$keep` новейших.
     *
     * Имена сортируются лексикографически: backup_{folder}_YYYY-mm-dd_HH-MM-SS.zip.
     *
     * @return list<string> удалённые пути
     */
    public function prune(string $dir, string $prefix, int $keep): array
    {
        if ($keep <= 0 || !is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!str_starts_with($entry, $prefix) || !str_ends_with($entry, '.zip')) {
                continue;
            }
            $files[] = $entry;
        }

        rsort($files); // новые первыми
        $delete = array_slice($files, $keep);

        $deleted = [];
        foreach ($delete as $entry) {
            $path = $dir . '/' . $entry;
            if (@unlink($path)) {
                $deleted[] = $path;
            }
        }

        return $deleted;
    }

    /**
     * @return array<string, array{files: int, bytes: int}>
     */
    private function stats(string $dir): array
    {
        $stats = [];
        foreach (self::PARTS as $part) {
            $path = $dir . '/' . ($part === 'db' ? 'db.sql' : $part);
            if ($part === 'db') {
                $stats[$part] = [
                    'files' => is_file($path) ? 1 : 0,
                    'bytes' => is_file($path) ? (int) filesize($path) : 0,
                ];

                continue;
            }

            $files = 0;
            $bytes = 0;
            if (is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                );
                foreach ($iterator as $file) {
                    if ($file instanceof SplFileInfo && $file->isFile()) {
                        $files++;
                        $bytes += (int) $file->getSize();
                    }
                }
            }

            $stats[$part] = ['files' => $files, 'bytes' => $bytes];
        }

        return $stats;
    }

    private function zip(string $tmpDir, string $zipPath, bool $compressMedia = false): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Не удалось создать ZIP: ' . $zipPath);
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || $file->isDir()) {
                    continue;
                }
                $path = $file->getPathname();
                $relative = str_replace('\\', '/', substr($path, strlen($tmpDir) + 1));
                $zip->addFile($path, $relative);

                // Медиа (картинки) по умолчанию не пересжимаем: выигрыша нет, CPU тратится.
                if (!$compressMedia && str_starts_with($relative, 'media/')) {
                    $zip->setCompressionName($relative, ZipArchive::CM_STORE);
                }
            }

            $zip->close();

            return;
        }

        // Fallback: PharData (zip-расширения нет; сжатие выполняет сам Phar).
        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        try {
            $phar = new PharData($zipPath, 0, null, \Phar::ZIP);
            $phar->buildFromDirectory($tmpDir);
        } catch (\Throwable $e) {
            throw new RuntimeException('Не удалось создать ZIP (PharData): ' . $e->getMessage());
        }
    }
}
