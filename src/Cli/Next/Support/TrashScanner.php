<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * Поиск карантина, который создаёт CLI.
 *
 * Каждый новый тип карантина регистрируется здесь — иначе очистка
 * становится «угадай, где искать». Метка времени берётся из имени.
 */
final class TrashScanner
{
    private const TRASH_RE = '/_trash_(\d+)$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * @return list<array{path: string, ts: int, kind: string}>
     */
    public function find(?string $domainFilter = null): array
    {
        $found = [];

        $app = $this->paths->appRoot();
        foreach ($this->subdirs($app) as $folder => $folderPath) {
            // 1. app/{folder}_trash_{ts} — удаление проекта
            if ($this->match($folder, $folderPath, 'domain', $found)) {
                continue;
            }

            // 5. app/_trash_sess_{tab}_{folder}_{ts} — apply/discard сессии
            if (str_starts_with($folder, '_trash_sess_')) {
                if (preg_match('/_(\d+)$/', $folder, $m) === 1) {
                    $found[] = ['path' => $folderPath, 'ts' => (int) $m[1], 'kind' => 'session'];
                }

                continue;
            }

            if (str_starts_with($folder, '.')) {
                continue;
            }

            foreach ($this->subdirs($folderPath) as $entry => $path) {
                // 2. app/{folder}/{alias}_trash_{ts} — удаление алиаса
                $this->match($entry, $path, 'allias', $found);
            }

            foreach (['.schemesglobal' => 'scheme', '.blocksglobal' => 'block'] as $infra => $kind) {
                foreach ($this->subdirs($folderPath . '/' . $infra) as $entry => $path) {
                    // 3–4. схемы и блоки (карантин файлов/каталогов)
                    $this->match($entry, $path, $kind, $found);
                }
            }
        }

        foreach ([
            $this->paths->mediaRoot() . '/.trash' => 'media',
            $this->paths->contentRoot() . '/.trash' => 'content',
            $this->paths->root() . '/var/trash' => 'lang',
        ] as $trashRoot => $kind) {
            foreach ($this->subdirs($trashRoot) as $entry => $path) {
                $this->match($entry, $path, $kind, $found);
            }
        }

        if ($domainFilter !== null) {
            $found = array_values(array_filter(
                $found,
                static fn (array $item): bool => str_contains(basename($item['path']), $domainFilter . '_')
                    || str_contains(basename($item['path']), $domainFilter . '_trash_'),
            ));
        }

        usort($found, static fn (array $a, array $b): int => $a['ts'] <=> $b['ts']);

        return $found;
    }

    /**
     * @param list<array{path: string, ts: int, kind: string}> $found
     */
    private function match(string $name, string $path, string $kind, array &$found): bool
    {
        if (preg_match(self::TRASH_RE, $name, $m) !== 1) {
            return false;
        }

        $found[] = ['path' => $path, 'ts' => (int) $m[1], 'kind' => $kind];

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function subdirs(string $base): array
    {
        if (!Fs::isDir($base)) {
            return [];
        }

        $out = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $base . '/' . $entry;
            if (is_dir($path)) {
                $out[$entry] = $path;
            }
        }

        return $out;
    }
}
