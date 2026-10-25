<?php

declare(strict_types=1);

namespace Damoony\Files;

use RuntimeException;

final class Fs
{
    public static function mkdir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create directory: ' . $path);
        }
    }

    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    public static function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    public static function read(string $path): string
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read file: ' . $path);
        }
        return $raw;
    }

    public static function writeAtomic(string $path, string $content): void
    {
        $dir = dirname($path);
        self::mkdir($dir);

        $tmp = $dir . '/.' . basename($path) . '.tmp.' . bin2hex(random_bytes(4));
        $fh = @fopen($tmp, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Cannot open tmp file: ' . $tmp);
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                throw new RuntimeException('Cannot lock tmp file: ' . $tmp);
            }
            if (fwrite($fh, $content) !== strlen($content)) {
                throw new RuntimeException('Short write to: ' . $tmp);
            }
            fflush($fh);
            flock($fh, LOCK_UN);
        } finally {
            fclose($fh);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Cannot rename tmp to: ' . $path);
        }
    }

    public static function rel(string $path, string $base): string
    {
        $base = rtrim($base, '/') . '/';
        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}