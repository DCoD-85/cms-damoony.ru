<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\UI\Page\PagePath;

/**
 * Раскладка runtime-контента: /content/{folder}/{alias}/{category}/{hash}.{ext}.
 *
 * Ключ — папка проекта (realDomain), а не входной хост: один проект = один
 * каталог контента независимо от мирроров.
 */
final class ContentLayout
{
    public const CATEGORY_RE = '/^[a-z0-9_-]{1,32}$/';
    public const HASH_RE = '/^[a-f0-9]{16}$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function categoryDir(string $folder, string $alias, string $category): string
    {
        return $this->paths->content($folder, $alias) . '/' . $category;
    }

    public function url(string $folder, string $alias, string $category, string $hash, string $ext): string
    {
        return '/content/' . $folder . '/' . $alias . '/' . $category . '/' . $hash . '.' . $ext;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readMeta(string $metaFile): ?array
    {
        if (!is_file($metaFile)) {
            return null;
        }

        $raw = @file_get_contents($metaFile);
        if ($raw === false) {
            return null;
        }

        $meta = json_decode($raw, true);

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function writeMeta(string $metaFile, array $meta): void
    {
        Fs::writeAtomic(
            $metaFile,
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    /**
     * Orphan = ни одна страница не ссылается на контент (или страницы больше нет).
     *
     * @param array<string, mixed> $meta
     */
    public function isOrphan(string $folder, string $alias, array $meta): bool
    {
        $refs = $meta['page_refs'] ?? [];
        if (!is_array($refs) || $refs === []) {
            return true;
        }

        foreach ($refs as $ref) {
            if (!is_string($ref) || $ref === '') {
                continue;
            }

            $pageKey = $ref === '__root__' ? '' : $ref;

            try {
                $page = PagePath::resolve($this->paths, $folder, $alias, $pageKey, '');
            } catch (\Throwable) {
                continue;
            }

            if (is_file($page->phpFile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Все файлы контента по hash (сам файл + meta.json).
     *
     * @return list<string>
     */
    public function filesForHash(string $folder, string $alias, string $category, string $hash): array
    {
        $dir = $this->categoryDir($folder, $alias, $category);

        return glob($dir . '/' . $hash . '.*') ?: [];
    }
}
