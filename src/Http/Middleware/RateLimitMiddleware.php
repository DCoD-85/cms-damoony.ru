<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $max = 60,
        private readonly int $window = 60,
        private readonly int $ban = 900,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $ip = $request->ip();
        if ($ip === '') {
            return $next($request);
        }

        $key = 'rl_' . md5($ip);
        $file = rtrim($this->storageDir, '/') . '/' . $key . '.json';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0o775, true);
        }

        $now = time();
        $data = $this->read($file);

        if ($data['banned_until'] > $now) {
            return Response::text('Too Many Requests', 429)
                ->withHeader('Retry-After', (string) ($data['banned_until'] - $now));
        }

        if ($now - $data['start'] > $this->window) {
            $data['start'] = $now;
            $data['count'] = 0;
        }

        $data['count']++;

        if ($data['count'] > $this->max) {
            $data['banned_until'] = $now + $this->ban;
            $this->write($file, $data);
            return Response::text('Too Many Requests', 429)
                ->withHeader('Retry-After', (string) $this->ban);
        }

        $this->write($file, $data);

        return $next($request);
    }

    /** @return array{count: int, start: int, banned_until: int} */
    private function read(string $file): array
    {
        $empty = ['count' => 0, 'start' => time(), 'banned_until' => 0];
        if (!is_file($file)) {
            return $empty;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $empty;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $empty;
        }

        return [
            'count' => (int) ($decoded['count'] ?? 0),
            'start' => (int) ($decoded['start'] ?? time()),
            'banned_until' => (int) ($decoded['banned_until'] ?? 0),
        ];
    }

    /** @param array{count: int, start: int, banned_until: int} $data */
    private function write(string $file, array $data): void
    {
        $raw = json_encode($data);
        if ($raw === false) {
            return;
        }
        $fh = @fopen($file, 'cb');
        if ($fh === false) {
            return;
        }
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $raw);
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
