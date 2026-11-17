<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * Синхронизация поля page_refs в /content/.../[hash].meta.json.
 *
 * Страница упоминается в метаданных тех картинок, на которые она ссылается
 * (URL вида /content/{folder}/{alias}/{category}/{hash}.{ext}).
 */
final class ContentRefs
{
    public function __construct(private readonly Paths $paths)
    {
    }

    /**
     * Применить разницу ссылок между старой и новой версией PHP-кода страницы.
     *
     * @return int количество изменённых meta.json
     */
    public function sync(
        string $folder,
        string $alias,
        string $pageKey,
        string $oldPhp,
        string $newPhp,
    ): int {
        $oldRefs = $this->extract($oldPhp, $folder, $alias);
        $newRefs = $this->extract($newPhp, $folder, $alias);

        $oldKeys = $this->keys($oldRefs);
        $newKeys = $this->keys($newRefs);

        $changed = 0;

        foreach ($newRefs as $i => $ref) {
            if (!in_array($newKeys[$i], $oldKeys, true)) {
                $changed += $this->apply($folder, $alias, $ref['category'], $ref['hash'], $pageKey, true);
            }
        }

        foreach ($oldRefs as $i => $ref) {
            if (!in_array($oldKeys[$i], $newKeys, true)) {
                $changed += $this->apply($folder, $alias, $ref['category'], $ref['hash'], $pageKey, false);
            }
        }

        return $changed;
    }

    /**
     * @return list<array{category: string, hash: string}>
     */
    public function extract(string $php, string $folder, string $alias): array
    {
        $pattern = '#/content/'
            . preg_quote($folder, '#')
            . '/'
            . preg_quote($alias, '#')
            . '/([a-z0-9_-]{1,32})/([a-f0-9]{16})\.[a-z]+#';

        if (preg_match_all($pattern, $php, $m, PREG_SET_ORDER) === false) {
            return [];
        }

        $out = [];
        $seen = [];

        /** @var array<int, array{0: string, 1: string, 2: string}> $m */
        foreach ($m as $row) {
            $key = $row[1] . '/' . $row[2];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['category' => $row[1], 'hash' => $row[2]];
        }

        return $out;
    }

    /**
     * @param list<array{category: string, hash: string}> $refs
     *
     * @return list<string>
     */
    private function keys(array $refs): array
    {
        return array_map(static fn (array $r): string => $r['category'] . '/' . $r['hash'], $refs);
    }

    private function apply(
        string $folder,
        string $alias,
        string $category,
        string $hash,
        string $pageKey,
        bool $add,
    ): int {
        $metaFile = $this->paths->content($folder, $alias) . '/' . $category . '/' . $hash . '.meta.json';

        if (!is_file($metaFile)) {
            return 0;
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return 0;
        }

        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            return 0;
        }

        $refs = is_array($meta['page_refs'] ?? null) ? $meta['page_refs'] : [];
        $refs = array_values(array_filter($refs, 'is_string'));

        $before = $refs;

        if ($add) {
            if (!in_array($pageKey, $refs, true)) {
                $refs[] = $pageKey;
            }
        } else {
            $refs = array_values(array_filter($refs, static fn (string $k): bool => $k !== $pageKey));
        }

        if ($before === $refs) {
            return 0;
        }

        $meta['page_refs'] = $refs;

        Fs::writeAtomic(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );

        return 1;
    }
}
