<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class TrashClearCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'trash:clear';
    }

    public function description(): string
    {
        return 'Очистить карантин (папки *_trash_<timestamp>)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domainFilter = (string) ($args['domain'] ?? '');
        $force = isset($args['force']);
        $older = (string) ($args['older'] ?? '');

        if ($domainFilter !== '' && !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domainFilter)) {
            $r->error('Некорректный домен');
            return $r;
        }

        $olderSeconds = 0;
        if ($older !== '') {
            if (!preg_match('/^(\d+)([dhm])$/', $older, $m)) {
                $r->error('--older ожидает формат: 7d, 24h, 30m');
                return $r;
            }
            $n = (int) $m[1];
            $olderSeconds = match ($m[2]) {
                'd' => $n * 86400,
                'h' => $n * 3600,
                'm' => $n * 60,
            };
        }

        $appDir = $this->paths->root() . '/app';
        if (!Fs::isDir($appDir)) {
            $r->error('Папка app/ не найдена');
            return $r;
        }

        $now = time();
        $trash = [];

        $domains = scandir($appDir) ?: [];
        foreach ($domains as $d) {
            if ($d === '.' || $d === '..') {
                continue;
            }
            $domDir = $appDir . '/' . $d;
            if (!is_dir($domDir)) {
                continue;
            }
            if ($domainFilter !== '' && $d !== $domainFilter) {
                continue;
            }
            $entries = scandir($domDir) ?: [];
            foreach ($entries as $e) {
                if (!preg_match('/^([a-z][a-z0-9_]*)_trash_(\d+)$/', $e, $m)) {
                    continue;
                }
                $path = $domDir . '/' . $e;
                if (!is_dir($path)) {
                    continue;
                }
                $ts = (int) $m[2];
                $age = $now - $ts;
                if ($olderSeconds > 0 && $age < $olderSeconds) {
                    continue;
                }
                $trash[] = [
                    'domain' => $d,
                    'allias' => $m[1],
                    'path' => $path,
                    'age' => $age,
                ];
            }
        }

        if ($trash === []) {
            $context->step('Карантин пуст');
            return $r;
        }

        usort($trash, static fn (array $a, array $b): int => $b['age'] <=> $a['age']);

        foreach ($trash as $t) {
            $rel = Fs::rel($t['path'], $this->paths->root());
            $ageStr = $this->fmtAge((int) $t['age']);
            if (!$force) {
                $context->step(sprintf('[dry] %s (age: %s)', $rel, $ageStr));
                continue;
            }
            $this->rmrf((string) $t['path']);
            $r->file($rel);
            $context->step(sprintf('Удалено: %s (age: %s)', $rel, $ageStr), true);
        }

        if (!$force) {
            $context->step(sprintf('Найдено %d. Запусти с --force, чтобы удалить', count($trash)));
        } else {
            $context->step(sprintf('Удалено: %d', count($trash)), true);
        }

        return $r;
    }

    private function fmtAge(int $seconds): string
    {
        $d = intdiv($seconds, 86400);
        $h = intdiv($seconds % 86400, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($d > 0) {
            return sprintf('%dd %dh', $d, $h);
        }
        if ($h > 0) {
            return sprintf('%dh %dm', $h, $m);
        }

        return sprintf('%dm', $m);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $i) {
            if ($i === '.' || $i === '..') {
                continue;
            }
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
