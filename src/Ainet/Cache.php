<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Файловый кэш HTTP-ответов.
 *
 * Структура: var/ainet-cache/<sha256(url)>/body + meta.json
 * TTL: 7 дней (по умолчанию). TTL=0 — кэш отключён.
 *
 * Windows-специфика: clearstatcache() перед FS-операциями,
 * glob() вместо scandir() (стабильнее при удалении).
 */
final class Cache
{
    public function __construct(
        private readonly string $dir,
        private readonly int $ttlSeconds = 604800,
    ) {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o775, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Cannot create cache dir: ' . $this->dir);
        }
    }

    public function get(string $url): ?HttpResponse
    {
        if ($this->ttlSeconds <= 0) {
            return null;
        }

        $entry = $this->dir . '/' . hash('sha256', $url);
        $metaFile = $entry . '/meta.json';
        $bodyFile = $entry . '/body';

        clearstatcache(true, $metaFile);
        clearstatcache(true, $bodyFile);

        if (!is_file($metaFile) || !is_file($bodyFile)) {
            return null;
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return null;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            return null;
        }

        $ts = (int) ($meta['timestamp'] ?? 0);
        if ($ts <= 0 || (time() - $ts) > $this->ttlSeconds) {
            return null;
        }

        $body = @file_get_contents($bodyFile);
        if ($body === false) {
            return null;
        }

        /** @var array<string, string> $headers */
        $headers = [];
        if (isset($meta['headers']) && is_array($meta['headers'])) {
            foreach ($meta['headers'] as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $headers[strtolower($k)] = $v;
                }
            }
        }

        return new HttpResponse(
            status: (int) ($meta['status'] ?? 0),
            headers: $headers,
            body: $body,
            truncated: (bool) ($meta['truncated'] ?? false),
        );
    }

    public function put(string $url, HttpResponse $response): void
    {
        if ($this->ttlSeconds <= 0) {
            return;
        }

        $entry = $this->dir . '/' . hash('sha256', $url);
        if (!is_dir($entry) && !@mkdir($entry, 0o775, true) && !is_dir($entry)) {
            throw new \RuntimeException('Cannot create cache entry: ' . $entry);
        }

        $bodyFile = $entry . '/body';
        if (@file_put_contents($bodyFile, $response->body) === false) {
            throw new \RuntimeException('Cannot write cache body: ' . $bodyFile);
        }

        $meta = [
            'url' => $url,
            'status' => $response->status,
            'headers' => $response->headers,
            'truncated' => $response->truncated,
            'timestamp' => time(),
        ];
        $metaFile = $entry . '/meta.json';
        $encoded = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || @file_put_contents($metaFile, $encoded . "\n") === false) {
            throw new \RuntimeException('Cannot write cache meta: ' . $metaFile);
        }

        clearstatcache();
    }

    public function clear(): int
    {
        clearstatcache();
        if (!is_dir($this->dir)) {
            return 0;
        }

        $count = 0;
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            \Damoony\Files\Fs::rmrf($path);
            $count++;
        }
        clearstatcache();

        return $count;
    }

    /** @return array{entries: int, size_bytes: int, size_mb: float} */
    public function stats(): array
    {
        clearstatcache();
        if (!is_dir($this->dir)) {
            return ['entries' => 0, 'size_bytes' => 0, 'size_mb' => 0.0];
        }

        $entries = 0;
        $size = 0;
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            $entries++;
            $size += $this->dirSize($path);
        }

        return [
            'entries' => $entries,
            'size_bytes' => $size,
            'size_mb' => round($size / 1048576, 4),
        ];
    }

    private function dirSize(string $dir): int
    {
        clearstatcache(true, $dir);
        $total = 0;
        foreach (glob($dir . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $total += $this->dirSize($path);
            } else {
                clearstatcache(true, $path);
                $total += (int) @filesize($path);
            }
        }

        return $total;
    }
}
