<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Page\PagePath;

/**
 * Поиск и карантин orphan-контента в /content/.
 *
 * Orphan = картинка, у которой:
 *   - meta.json есть, но page_refs: [] (никогда не привязан к странице);
 *   - meta.json есть, page_refs: [X], но страница <X> не существует на диске;
 *   - все refs указывают на несуществующие страницы.
 *
 * Не трогаем:
 *   - <hash>.<ext> без meta.json — битый кейс (warning, карантин не переносим);
 *   - meta.json с битым JSON — warning;
 *   - живые картинки (хоть один page_ref существует).
 *
 * Карантин: /content/.trash/<domain>_<allias>_<category>_<hash>_trash_<ts>/
 * (совместим с content:remove и trash:clear).
 */
final class ContentGcCommand implements CommandInterface
{
    private const DOMAIN_RE = '/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/';
    private const ALLIAS_RE = '/^[a-z][a-z0-9_]*$/';
    private const CATEGORY_RE = '/^[a-z0-9_-]{1,32}$/';
    private const HASH_RE = '/^[a-f0-9]{16}$/';

    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'content:gc';
    }

    public function description(): string
    {
        return 'Найти и убрать в карантин orphan-контент (пустой page_refs или несуществующая страница)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domainFilter = (string) ($args['domain'] ?? '');
        $olderSpec = (string) ($args['older'] ?? '');
        $dry = isset($args['dry']);
        $force = isset($args['force']);

        if (!$force && !$dry) {
            $r->error('Укажите --force (в карантин) или --dry (предпросмотр)');
            return $r;
        }
        if ($domainFilter !== '' && (!preg_match(self::DOMAIN_RE, $domainFilter) || str_contains($domainFilter, '..'))) {
            $r->error('Некорректный домен');
            return $r;
        }

        $olderSeconds = 0;
        if ($olderSpec !== '') {
            $olderSeconds = $this->parseAge($olderSpec);
            if ($olderSeconds < 0) {
                $r->error('Некорректный --older (формат: 7d, 24h, 30m)');
                return $r;
            }
        }

        $contentRoot = $this->paths->contentRoot();
        if (!is_dir($contentRoot)) {
            $context->step('Каталог /content/ не найден', true);
            return $r;
        }

        $totalOrphans = 0;
        $totalTrashed = 0;

        foreach ($this->collect($contentRoot, $domainFilter, $olderSeconds, $r) as $entry) {
            if (!$this->isOrphan($entry)) {
                continue;
            }
            $totalOrphans++;
            $rel = Fs::rel($entry['path'], $this->paths->root());
            if ($dry) {
                $context->step("[dry] orphan: {$rel}", true, $entry['hash']);
                continue;
            }
            if ($this->moveToTrash($entry, $r)) {
                $totalTrashed++;
                $context->step("В карантине: {$rel}", true, $entry['hash']);
            }
        }

        $context->step(sprintf(
            'Всего orphan: %d, %s: %d',
            $totalOrphans,
            $dry ? 'dry-run' : 'в карантине',
            $totalTrashed,
        ), true);

        if (!$dry && $totalTrashed > 0) {
            $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        }

        return $r;
    }

    /**
     * @return iterable<array{domain: string, allias: string, category: string, hash: string, path: string, meta: array<string, mixed>}>
     */
    private function collect(string $root, string $domainFilter, int $olderSeconds, Result $r): iterable
    {
        foreach ($this->subdirs($root) as $domain => $domainPath) {
            if (!preg_match(self::DOMAIN_RE, $domain)) {
                continue;
            }
            if ($domainFilter !== '' && $domain !== $domainFilter) {
                continue;
            }
            foreach ($this->subdirs($domainPath) as $allias => $alliasPath) {
                if (!preg_match(self::ALLIAS_RE, $allias)) {
                    continue;
                }
                foreach ($this->subdirs($alliasPath) as $category => $categoryPath) {
                    if (!preg_match(self::CATEGORY_RE, $category)) {
                        continue;
                    }
                    foreach (glob($categoryPath . '/*') ?: [] as $path) {
                        $basename = basename($path);
                        if (str_ends_with($basename, '.meta.json')) {
                            continue;
                        }
                        $hash = pathinfo($basename, PATHINFO_FILENAME);
                        if (!preg_match(self::HASH_RE, $hash)) {
                            continue;
                        }
                        $metaFile = $categoryPath . '/' . $hash . '.meta.json';
                        if (!is_file($metaFile)) {
                            $r->warn('Битый контент (нет meta.json): ' . Fs::rel($path, $this->paths->root()));
                            continue;
                        }
                        if ($olderSeconds > 0 && (time() - (int) @filemtime($metaFile)) < $olderSeconds) {
                            continue;
                        }
                        $meta = json_decode((string) @file_get_contents($metaFile), true);
                        if (!is_array($meta)) {
                            $r->warn('Битый meta.json: ' . Fs::rel($metaFile, $this->paths->root()));
                            continue;
                        }
                        yield [
                            'domain' => $domain,
                            'allias' => $allias,
                            'category' => $category,
                            'hash' => $hash,
                            'path' => $path,
                            'meta' => $meta,
                        ];
                    }
                }
            }
        }
    }

    /** @return array<string, string> name => full path */
    private function subdirs(string $base): array
    {
        $out = [];
        foreach (scandir($base) ?: [] as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $base . '/' . $e;
            if (is_dir($p)) {
                $out[$e] = $p;
            }
        }

        return $out;
    }

    /**
     * @param array{domain: string, allias: string, category: string, hash: string, path: string, meta: array<string, mixed>} $entry
     */
    private function isOrphan(array $entry): bool
    {
        $refs = $entry['meta']['page_refs'] ?? [];
        if (!is_array($refs) || $refs === []) {
            return true;
        }
        foreach ($refs as $ref) {
            if (!is_string($ref) || $ref === '') {
                continue;
            }
            $pageKey = $ref === '__root__' ? '' : $ref;
            try {
                $page = PagePath::resolve($this->paths, $entry['domain'], $entry['allias'], $pageKey, '');
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
     * @param array{domain: string, allias: string, category: string, hash: string, path: string, meta: array<string, mixed>} $entry
     */
    private function moveToTrash(array $entry, Result $r): bool
    {
        $contentDir = $this->paths->content($entry['domain'], $entry['allias']) . '/' . $entry['category'];
        $files = glob($contentDir . '/' . $entry['hash'] . '.*') ?: [];
        if ($files === []) {
            return false;
        }
        $trashDir = $this->paths->contentRoot() . '/.trash/'
            . $entry['domain'] . '_' . $entry['allias'] . '_' . $entry['category'] . '_' . $entry['hash'] . '_trash_' . time();
        Fs::mkdir($trashDir);
        foreach ($files as $file) {
            if (!@rename($file, $trashDir . '/' . basename($file))) {
                $r->error('Не удалось переместить: ' . basename($file));

                return false;
            }
        }

        return true;
    }

    private function parseAge(string $spec): int
    {
        if (!preg_match('/^(\d+)([dhm])$/', $spec, $m)) {
            return -1;
        }
        $n = (int) $m[1];
        if ($n <= 0) {
            return -1;
        }

        return match ($m[2]) {
            'd' => $n * 86400,
            'h' => $n * 3600,
            'm' => $n * 60,
        };
    }
}
