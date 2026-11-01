<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;

final class ContentListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'content:list';
    }

    public function description(): string
    {
        return 'Список контента домена/алиаса (опционально category)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $category = (string) ($args['category'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if ($allias !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if ($category !== '' && !preg_match('/^[a-z0-9_-]{1,32}$/', $category)) {
            $r->error('Некорректная category');
            return $r;
        }

        $base = $allias === ''
            ? $this->paths->contentRoot() . '/' . $domain
            : $this->paths->content($domain, $allias);

        if (!is_dir($base)) {
            $context->step('Контент не найден', true);
            return $r;
        }

        $entries = $this->scan($base, $domain, $category);

        foreach ($entries as $e) {
            $rel = Fs::rel($e['path'], $this->paths->root());
            $refs = $e['page_refs'];
            $refsStr = $refs === [] ? '' : ', refs=' . count($refs);
            $context->step(
                sprintf('%s (%s, %d B%s)', $rel, $e['mime'], $e['size'], $refsStr),
                true,
                $e['hash'],
            );
            $r->file($rel);
        }

        $context->step(sprintf('Всего: %d', count($entries)), true);

        return $r;
    }

    /**
     * @return list<array{path: string, hash: string, mime: string, size: int, page_refs: list<string>}>
     */
    private function scan(string $base, string $domain, string $categoryFilter): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $basename = basename($path);

            // Пропускаем meta.json
            if (str_ends_with($basename, '.meta.json')) {
                continue;
            }

            $hash = pathinfo($basename, PATHINFO_FILENAME);
            if (!preg_match('/^[a-f0-9]{16}$/', $hash)) {
                continue;
            }

            // Фильтр по category (папка над файлом)
            $dir = dirname($path);
            $catName = basename($dir);
            if ($categoryFilter !== '' && $catName !== $categoryFilter) {
                continue;
            }

            // MIME и page_refs из meta.json (если есть)
            $metaFile = $dir . '/' . $hash . '.meta.json';
            $mime = '';
            $pageRefs = [];
            if (is_file($metaFile)) {
                $meta = json_decode((string) @file_get_contents($metaFile), true);
                if (is_array($meta)) {
                    if (isset($meta['mime']) && is_string($meta['mime'])) {
                        $mime = $meta['mime'];
                    }
                    if (isset($meta['page_refs']) && is_array($meta['page_refs'])) {
                        foreach ($meta['page_refs'] as $ref) {
                            if (is_string($ref)) {
                                $pageRefs[] = $ref;
                            }
                        }
                    }
                }
            }

            $out[] = [
                'path' => $path,
                'hash' => $hash,
                'mime' => $mime !== '' ? $mime : 'unknown',
                'size' => (int) $file->getSize(),
                'page_refs' => $pageRefs,
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $out;
    }
}
