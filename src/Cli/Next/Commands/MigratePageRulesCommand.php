<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Page\PageRules;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * migrate:page-rules — старая модель `.rules.json` → `algoritm_{name}.rules.json`.
 *
 * Идемпотентна. Обходит только проекты и алиасы из domains_app — не все подряд
 * каталоги в app/ (там могут быть карантинные и посторонние папки).
 */
final class MigratePageRulesCommand implements CommandInterface
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
    ) {
    }

    public function name(): string
    {
        return 'migrate:page-rules';
    }

    public function description(): string
    {
        return 'Миграция .rules.json → algoritm_{name}.rules.json (идемпотентно)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $dry = isset($args['dry']);
        $domain = (string) ($args['domain'] ?? '');

        $projects = [];
        if ($domain !== '') {
            try {
                $projects[] = $this->site->resolve($domain);
            } catch (RuntimeException $e) {
                $r->error($e->getMessage());

                return $r;
            }
        } else {
            $projects = array_values($this->site->projects());
        }

        $migrated = 0;
        $normalized = 0;
        $errors = 0;

        foreach ($projects as $project) {
            foreach ($project->aliasNames() as $alias) {
                $algoritmDir = $this->paths->algoritm($project->folder, $alias);
                if (!Fs::isDir($algoritmDir)) {
                    continue;
                }

                try {
                    $this->migrateAlias($algoritmDir, $dry, $migrated, $normalized);
                } catch (Throwable $e) {
                    $r->warn($project->key . '/' . $alias . ': ' . $e->getMessage());
                    $errors++;
                }
            }
        }

        if ($errors > 0) {
            $r->error('Ошибок при миграции: ' . $errors);
        }

        $r->data = [
            'migrated' => $migrated,
            'normalized' => $normalized,
            'errors' => $errors,
            'dry_run' => $dry,
        ];

        $context->step(
            sprintf(
                'Мигрировано: %d, нормализовано: %d, ошибок: %d%s',
                $migrated,
                $normalized,
                $errors,
                $dry ? ' (dry-run)' : '',
            ),
            $errors === 0,
        );

        return $r;
    }

    private function migrateAlias(string $algoritmDir, bool $dry, int &$migrated, int &$normalized): void
    {
        $this->moveRootRules($algoritmDir, $dry, $migrated);
        $this->moveNestedRules($algoritmDir, $dry, $migrated);
        $this->normalizeRules($algoritmDir, $dry, $normalized);
        $this->ensureEditors($algoritmDir, $dry, $normalized);
    }

    private function moveRootRules(string $algoritmDir, bool $dry, int &$migrated): void
    {
        $old = $algoritmDir . '/.rules.json';
        if (!is_file($old)) {
            return;
        }

        if ($dry) {
            $migrated++;

            return;
        }

        $rules = PageRules::load($old);
        $new = $algoritmDir . '/algoritm.rules.json';
        if (is_file($new)) {
            $rules = $this->merge(PageRules::load($new), $rules);
        }

        PageRules::save($new, $rules);
        unlink($old);
        $migrated++;
    }

    private function moveNestedRules(string $algoritmDir, bool $dry, int &$migrated): void
    {
        foreach ($this->dirs($algoritmDir) as $dir) {
            $rel = $this->rel($dir, $algoritmDir);
            if ($rel === '') {
                continue;
            }

            $old = $dir . '/.rules.json';
            if (!is_file($old)) {
                continue;
            }

            $classChain = 'algoritm_' . str_replace('/', '_', $rel);
            $new = $dir . '/' . $classChain . '.rules.json';

            if ($dry) {
                $migrated++;

                continue;
            }

            $rules = PageRules::load($old);
            if (is_file($new)) {
                $rules = $this->merge(PageRules::load($new), $rules);
            }

            PageRules::save($new, $rules);
            unlink($old);
            $migrated++;
        }
    }

    private function normalizeRules(string $algoritmDir, bool $dry, int &$normalized): void
    {
        foreach ($this->dirs($algoritmDir) as $dir) {
            $rel = $this->rel($dir, $algoritmDir);
            $classChain = 'algoritm' . ($rel === '' ? '' : '_' . str_replace('/', '_', $rel));
            $rulesFile = $dir . '/' . $classChain . '.rules.json';

            if (!is_file($rulesFile)) {
                continue;
            }

            $current = PageRules::load($rulesFile);
            $withDefaults = PageRules::withDefaults($current);

            if ($withDefaults === $current) {
                continue;
            }

            if (!$dry) {
                PageRules::save($rulesFile, $withDefaults);
            }
            $normalized++;
        }
    }

    private function ensureEditors(string $algoritmDir, bool $dry, int &$normalized): void
    {
        foreach ($this->dirs($algoritmDir) as $dir) {
            $rel = $this->rel($dir, $algoritmDir);
            $classChain = 'algoritm' . ($rel === '' ? '' : '_' . str_replace('/', '_', $rel));

            if (!is_file($dir . '/' . $classChain . '.php')) {
                continue;
            }
            if (is_file($dir . '/' . $classChain . '.editor.json')) {
                continue;
            }

            if (!$dry) {
                Fs::writeAtomic($dir . '/' . $classChain . '.editor.json', "[]\n");
            }
            $normalized++;
        }
    }

    /**
     * Старые (routing) + новые (page settings) → единый формат.
     *
     * @param array<string, mixed> $new
     * @param array<string, mixed> $old
     *
     * @return array<string, mixed>
     */
    private function merge(array $new, array $old): array
    {
        $merged = PageRules::withDefaults($new);

        $oldAvailable = is_array($old['available'] ?? null)
            ? array_values(array_filter($old['available'], 'is_string'))
            : [];

        $merged['available'] = array_values(array_unique(array_merge($oldAvailable, $merged['available'])));

        foreach (['auth', 'search', 'default'] as $key) {
            if (array_key_exists($key, $old)) {
                $merged[$key] = (bool) $old[$key];
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    private function dirs(string $base): array
    {
        $out = [$base];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isDir()) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function rel(string $dir, string $base): string
    {
        return str_replace('\\', '/', trim(substr($dir, strlen($base)), '/\\'));
    }
}
