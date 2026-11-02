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
    /**
     * Карантин на 6 уровнях:
     *   1. app/<domain>_trash_<ts>/                                         — domain:remove
     *   2. app/<domain>/<allias>_trash_<ts>/                                — allias:remove
     *   3. app/<domain>/.alliasscheme/<name>_trash_<ts>/                    — scheme:remove
     *   4. app/<domain>/.alliasglobal/<name>_trash_<ts>/                    — block:remove
     *   5. content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/   — content:remove
     *   6. www/media/.trash/<domain>_<allias>_skin_<name>_trash_<ts>/       — skin:remove
     */
    private const TRASH_RE = '/^[a-z0-9][a-z0-9._-]*_trash_(\d+)$/';

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

        if ($domainFilter !== '' && (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domainFilter) || str_contains($domainFilter, '..'))) {
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

        $appDir = $this->paths->appRoot();
        $mediaTrashRoot = $this->paths->mediaRoot() . '/.trash';
        $contentTrashRoot = $this->paths->contentRoot() . '/.trash';
        $appExists = Fs::isDir($appDir);
        $mediaTrashExists = is_dir($mediaTrashRoot);
        $contentTrashExists = is_dir($contentTrashRoot);

        if (!$appExists && !$mediaTrashExists && !$contentTrashExists) {
            $context->step('Карантин пуст');
            return $r;
        }

        $now = time();
        $trash = [];  // list<array{path: string, age: int}>

        // 1–4. app/ карантины
        if ($appExists) {
            $domains = scandir($appDir) ?: [];
            foreach ($domains as $d) {
                if ($d === '.' || $d === '..') {
                    continue;
                }
                $domDir = $appDir . '/' . $d;
                if (!is_dir($domDir)) {
                    continue;
                }

                // 1. app/<domain>_trash_<ts>/
                if (preg_match(self::TRASH_RE, $d, $m)) {
                    if ($domainFilter === '' || $this->matchesDomain($d, $domainFilter)) {
                        $this->collect($domDir, (int) $m[1], $now, $olderSeconds, $trash);
                    }
                    continue;
                }

                if ($domainFilter !== '' && $d !== $domainFilter) {
                    continue;
                }

                // 2. app/<domain>/<allias>_trash_<ts>/
                $entries = scandir($domDir) ?: [];
                foreach ($entries as $e) {
                    if ($e === '.' || $e === '..') {
                        continue;
                    }
                    $path = $domDir . '/' . $e;
                    if (!is_dir($path)) {
                        continue;
                    }
                    if (preg_match(self::TRASH_RE, $e, $m)) {
                        $this->collect($path, (int) $m[1], $now, $olderSeconds, $trash);
                    }
                }

                // 3–4. .alliasscheme / .alliasglobal
                foreach (['.alliasscheme', '.alliasglobal'] as $infra) {
                    $infraDir = $domDir . '/' . $infra;
                    if (!is_dir($infraDir)) {
                        continue;
                    }
                    $infraEntries = scandir($infraDir) ?: [];
                    foreach ($infraEntries as $ie) {
                        if ($ie === '.' || $ie === '..') {
                            continue;
                        }
                        $ipath = $infraDir . '/' . $ie;
                        if (!is_dir($ipath)) {
                            continue;
                        }
                        if (preg_match(self::TRASH_RE, $ie, $m)) {
                            $this->collect($ipath, (int) $m[1], $now, $olderSeconds, $trash);
                        }
                    }
                }
            }
        }

        // 5. content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/
        //    (ContentRemoveCommand quarantines here, not in www/media/.trash/)
        if ($contentTrashExists) {
            $entries = scandir($contentTrashRoot) ?: [];
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') {
                    continue;
                }
                $path = $contentTrashRoot . '/' . $e;
                if (!is_dir($path)) {
                    continue;
                }
                if (!preg_match(self::TRASH_RE, $e, $m)) {
                    continue;
                }
                if ($domainFilter !== '' && !str_starts_with($e, $domainFilter . '_')) {
                    continue;
                }
                $this->collect($path, (int) $m[1], $now, $olderSeconds, $trash);
            }
        }

        // 6. www/media/.trash/<domain>_<allias>_skin_<name>_trash_<ts>/
        if ($mediaTrashExists) {
            $entries = scandir($mediaTrashRoot) ?: [];
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') {
                    continue;
                }
                $path = $mediaTrashRoot . '/' . $e;
                if (!is_dir($path)) {
                    continue;
                }
                if (!preg_match(self::TRASH_RE, $e, $m)) {
                    continue;
                }
                if ($domainFilter !== '' && !str_starts_with($e, $domainFilter . '_')) {
                    continue;
                }
                $this->collect($path, (int) $m[1], $now, $olderSeconds, $trash);
            }
        }

        if ($trash === []) {
            $context->step('Карантин пуст');
            return $r;
        }

        usort($trash, static fn (array $a, array $b): int => $b['age'] <=> $a['age']);

        foreach ($trash as $t) {
            $rel = Fs::rel($t['path'], $this->paths->root());
            $ageStr = $this->fmtAge($t['age']);
            if (!$force) {
                $context->step(sprintf('[dry] %s (age: %s)', $rel, $ageStr));
                continue;
            }
            Fs::rmrf($t['path']);
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

    private function matchesDomain(string $trashName, string $filter): bool
    {
        return str_starts_with($trashName, $filter . '_trash_');
    }

    /** @param list<array{path: string, age: int}> $trash */
    private function collect(string $path, int $ts, int $now, int $olderSeconds, array &$trash): void
    {
        $age = $now - $ts;
        if ($olderSeconds > 0 && $age < $olderSeconds) {
            return;
        }
        $trash[] = ['path' => $path, 'age' => $age];
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
}
