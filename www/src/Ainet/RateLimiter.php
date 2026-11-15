<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Fixed-window rate limiter с файловым хранилищем.
 *
 * Формат файла: {"window_start": <unix_ts>, "hits": <int>}
 * Атомарность: flock(LOCK_EX) вокруг read-modify-write.
 * При битом файле — fail-open (reset окна), чтобы corruption не блокировал всё.
 *
 * Не сбрасывается Cache::clear() — файл лежит рядом с кэшем, не в нём.
 */
final class RateLimiter
{
    public function __construct(
        private readonly string $file,
        private readonly int $maxPerWindow,
        private readonly int $windowSeconds = 60,
    ) {
        if ($this->maxPerWindow < 1) {
            throw new \RuntimeException('RateLimiter: maxPerWindow must be >= 1');
        }
        if ($this->windowSeconds < 1) {
            throw new \RuntimeException('RateLimiter: windowSeconds must be >= 1');
        }
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException('RateLimiter: cannot create dir ' . $dir);
        }
    }

    public function allow(): bool
    {
        $fh = $this->openLocked();
        if ($fh === null) {
            // Не смогли открыть файл — fail-open, разрешаем (иначе всё заблокировано)
            return true;
        }
        try {
            $data = $this->readFromHandle($fh);
            $now = time();
            $start = $data['window_start'];
            $hits = $data['hits'];

            if ($start <= 0 || ($now - $start) >= $this->windowSeconds) {
                $this->writeFromHandle($fh, ['window_start' => $now, 'hits' => 1]);

                return true;
            }
            if ($hits >= $this->maxPerWindow) {
                return false;
            }
            $this->writeFromHandle($fh, ['window_start' => $start, 'hits' => $hits + 1]);

            return true;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public function remaining(): int
    {
        $data = $this->read();
        $now = time();
        $start = $data['window_start'];
        if ($start <= 0 || ($now - $start) >= $this->windowSeconds) {
            return $this->maxPerWindow;
        }

        return max(0, $this->maxPerWindow - $data['hits']);
    }

    public function waitSeconds(): int
    {
        $data = $this->read();
        $start = $data['window_start'];
        if ($start <= 0) {
            return 0;
        }

        return max(0, $start + $this->windowSeconds - time());
    }

    public function reset(): void
    {
        $encoded = json_encode(['window_start' => 0, 'hits' => 0], JSON_UNESCAPED_UNICODE);
        if ($encoded === false || @file_put_contents($this->file, $encoded . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('RateLimiter: cannot write ' . $this->file);
        }
        clearstatcache(true, $this->file);
    }

    /** @return array{used: int, max: int, window: int, remaining: int, reset_in: int} */
    public function stats(): array
    {
        $data = $this->read();
        $now = time();
        $start = $data['window_start'];
        $hits = $data['hits'];

        if ($start <= 0 || ($now - $start) >= $this->windowSeconds) {
            $used = 0;
            $resetIn = 0;
        } else {
            $used = $hits;
            $resetIn = max(0, $start + $this->windowSeconds - $now);
        }

        return [
            'used' => $used,
            'max' => $this->maxPerWindow,
            'window' => $this->windowSeconds,
            'remaining' => max(0, $this->maxPerWindow - $used),
            'reset_in' => $resetIn,
        ];
    }

    /**
     * @return resource|null
     */
    private function openLocked()
    {
        $fh = @fopen($this->file, 'c+');
        if ($fh === false) {
            return null;
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);

            return null;
        }

        return $fh;
    }

    /**
     * @param resource $fh
     *
     * @return array{window_start: int, hits: int}
     */
    private function readFromHandle($fh): array
    {
        rewind($fh);
        $raw = stream_get_contents($fh);
        if ($raw === false || $raw === '') {
            return ['window_start' => 0, 'hits' => 0];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['window_start' => 0, 'hits' => 0];
        }

        return [
            'window_start' => (int) ($decoded['window_start'] ?? 0),
            'hits' => (int) ($decoded['hits'] ?? 0),
        ];
    }

    /**
     * @param resource $fh
     * @param array{window_start: int, hits: int} $data
     */
    private function writeFromHandle($fh, array $data): void
    {
        rewind($fh);
        ftruncate($fh, 0);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            fwrite($fh, $encoded . "\n");
        }
        fflush($fh);
    }

    /** @return array{window_start: int, hits: int} */
    private function read(): array
    {
        clearstatcache(true, $this->file);
        if (!is_file($this->file)) {
            return ['window_start' => 0, 'hits' => 0];
        }
        $raw = @file_get_contents($this->file);
        if ($raw === false || $raw === '') {
            return ['window_start' => 0, 'hits' => 0];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['window_start' => 0, 'hits' => 0];
        }

        return [
            'window_start' => (int) ($decoded['window_start'] ?? 0),
            'hits' => (int) ($decoded['hits'] ?? 0),
        ];
    }
}
