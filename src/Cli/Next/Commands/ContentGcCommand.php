<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Next\Support\ContentLayout;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

/**
 * content:gc для схемы domains_app: orphan-контент в карантин.
 *
 * Orphan = в meta.json нет page_refs, либо все указанные страницы больше не существуют.
 * Требует --force (переместить) или --dry (предпросмотр).
 */
final class ContentGcCommand implements CommandInterface
{
    private const DOMAIN_RE = '/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/';
    private const ALLIAS_RE = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly Paths $paths,
        private readonly ContentLayout $layout,
    ) {
    }

    public function name(): string
    {
        return 'content:gc';
    }

    public function description(): string
    {
        return 'Удалить orphan-контент в карантин (--force | --dry)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $dry = isset($args['dry']);
        $force = isset($args['force']);

        if (!$force && !$dry) {
            $r->error('Укажите --force (в карантин) или --dry (предпросмотр)');

            return $r;
        }

        $domainFilter = (string) ($args['domain'] ?? '');
        if ($domainFilter !== '' && preg_match(self::DOMAIN_RE, $domainFilter) !== 1) {
            $r->error('Некорректный домен');

            return $r;
        }

        $olderSeconds = 0;
        $olderSpec = (string) ($args['older'] ?? '');
        if ($olderSpec !== '') {
            $olderSeconds = $this->parseAge($olderSpec);
            if ($olderSeconds < 0) {
                $r->error('Некорректный --older (формат: 7d, 24h, 30m)');

                return $r;
            }
        }

        $root = $this->paths->contentRoot();
        if (!Fs::isDir($root)) {
            $context->step('Каталог /content/ не найден', true);

            return $r;
        }

        $orphans = 0;
        $trashed = 0;

        foreach (scandir($root) ?: [] as $folder) {
            if ($folder === '.' || $folder === '..' || str_starts_with($folder, '.')) {
                continue;
            }
            if (preg_match(self::DOMAIN_RE, $folder) !== 1) {
                continue;
            }
            if ($domainFilter !== '' && $folder !== $domainFilter) {
                continue;
            }

            foreach (scandir($root . '/' . $folder) ?: [] as $alias) {
                if ($alias === '.' || $alias === '..' || preg_match(self::ALLIAS_RE, $alias) !== 1) {
                    continue;
                }

                $orphans += $this->scanAlias($r, $context, $folder, $alias, $olderSeconds, $dry, $trashed);
            }
        }

        $context->step(sprintf('Orphan: %d, %s: %d', $orphans, $dry ? 'dry-run' : 'в карантине', $trashed), true);

        if (!$dry && $trashed > 0) {
            $r->warn('Очистка карантина: php bin/cli.php trash:clear --force --older=7d');
        }

        return $r;
    }

    private function scanAlias(
        Result $r,
        Context $context,
        string $folder,
        string $alias,
        int $olderSeconds,
        bool $dry,
        int &$trashed,
    ): int {
        $base = $this->paths->content($folder, $alias);
        $orphans = 0;

        foreach (scandir($base) ?: [] as $category) {
            if ($category === '.' || $category === '..' || preg_match(ContentLayout::CATEGORY_RE, $category) !== 1) {
                continue;
            }

            foreach (glob($base . '/' . $category . '/*.meta.json') ?: [] as $metaFile) {
                $hash = basename($metaFile, '.meta.json');
                if (preg_match(ContentLayout::HASH_RE, $hash) !== 1) {
                    continue;
                }

                if ($olderSeconds > 0 && (time() - (int) @filemtime($metaFile)) < $olderSeconds) {
                    continue;
                }

                $meta = $this->layout->readMeta($metaFile);
                if ($meta === null) {
                    $r->warn('Битый meta.json: ' . Fs::rel($metaFile, $this->paths->root()));

                    continue;
                }

                if (!$this->layout->isOrphan($folder, $alias, $meta)) {
                    continue;
                }

                $orphans++;
                $rel = Fs::rel($base . '/' . $category . '/' . $hash, $this->paths->root());

                if ($dry) {
                    $context->step('[dry] orphan: ' . $rel, true, $hash);

                    continue;
                }

                $files = $this->layout->filesForHash($folder, $alias, $category, $hash);
                $trashDir = $this->paths->contentRoot() . '/.trash/'
                    . $folder . '_' . $alias . '_' . $category . '_' . $hash . '_trash_' . time();
                Fs::mkdir($trashDir);

                foreach ($files as $file) {
                    if (!@rename($file, $trashDir . '/' . basename($file))) {
                        $r->error('Не удалось переместить: ' . basename($file));

                        continue;
                    }
                }

                $trashed++;
                $context->step('В карантине: ' . $rel, true, $hash);
            }
        }

        return $orphans;
    }

    private function parseAge(string $spec): int
    {
        if (preg_match('/^(\d+)([dhm])$/', $spec, $m) !== 1) {
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
