<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Files\Paths;

abstract class AbstractSessionCommand implements CommandInterface
{
    public function __construct(protected readonly Paths $paths)
    {
    }

    protected function validateTab(string $tab): void
    {
        if (!preg_match('/^[a-f0-9]{8,32}$/', $tab)) {
            throw new \RuntimeException('Invalid --tab (must be 8-32 hex chars).');
        }
    }

    protected function validateDomain(string $domain): void
    {
        if ($domain === ''
            || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain)
            || str_contains($domain, '..')) {
            throw new \RuntimeException('Invalid --domain.');
        }
    }

    /**
     * Рекурсивно копирует $src в $dst.
     */
    protected function copyTree(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            throw new \RuntimeException("Source dir missing: {$src}");
        }
        if (!is_dir($dst) && !mkdir($dst, 0o775, true) && !is_dir($dst)) {
            throw new \RuntimeException("Cannot create: {$dst}");
        }

        $items = scandir($src);
        if ($items === false) {
            throw new \RuntimeException("Cannot scan: {$src}");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;

            if (is_dir($srcPath)) {
                if (is_link($srcPath)) {
                    continue; // пропускаем симлинки (безопасность)
                }
                $this->copyTree($srcPath, $dstPath);
            } elseif (is_file($srcPath)) {
                if (!copy($srcPath, $dstPath)) {
                    throw new \RuntimeException("Copy failed: {$srcPath} -> {$dstPath}");
                }
            }
        }
    }

    /**
     * SHA-256 от всех .editor.json в домене (детерминированный порядок).
     */
    protected function hashProduction(string $releaseDir): string
    {
        if (!is_dir($releaseDir)) {
            return hash('sha256', '');
        }

        $files = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($releaseDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iter as $file) {
            if ($file instanceof \SplFileInfo
                && $file->isFile()
                && str_ends_with($file->getFilename(), '.editor.json')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        $ctx = hash_init('sha256');
        foreach ($files as $f) {
            hash_update($ctx, $f);
            hash_update($ctx, (string) file_get_contents($f));
        }

        return hash_final($ctx);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readMeta(string $sessionDir): ?array
    {
        $file = $sessionDir . '/.session.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $meta = json_decode($raw, true);

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function writeMeta(string $sessionDir, array $meta): void
    {
        $file = $sessionDir . '/.session.json';
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Cannot encode .session.json');
        }
        if (file_put_contents($file, $json) === false) {
            throw new \RuntimeException('Cannot write .session.json');
        }
    }

    protected function trashName(string $tab, string $domain): string
    {
        return '_trash_sess_' . $tab . '_' . $domain . '_' . time();
    }
}
