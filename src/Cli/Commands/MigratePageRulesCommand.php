<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Files\Fs;
use Damoony\Files\Paths;
use Damoony\Page\PageRules;

/**
 * Одноразовая миграция старых .rules.json → новая модель (algoritm_{name}.rules.json).
 *
 * До:  algoritm/.rules.json + about/.rules.json (toggle)
 * После: algoritm/algoritm.rules.json + about/algoritm_about.rules.json
 *
 * Шаги:
 *   1. root: algoritm/.rules.json → algoritm/algoritm.rules.json
 *   2. подпапки: {dir}/.rules.json → {dir}/algoritm_{rel}.rules.json
 *   3. нормализация: все algoritm_*.rules.json получают полный набор полей
 *   4. создание пустых .editor.json (правило 3 файлов)
 *
 * Идемпотентна. Повторный запуск ничего не меняет.
 */
final class MigratePageRulesCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'migrate:page-rules';
    }

    public function description(): string
    {
        return 'Миграция .rules.json → algoritm_{name}.rules.json (новая модель)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domainFilter = (string) ($args['domain'] ?? '');
        $dry = isset($args['dry']);

        $appRoot = $this->paths->appRoot();
        if (!is_dir($appRoot)) {
            $r->error('Папка app/ не найдена');
            return $r;
        }

        $migrated = 0;
        $normalized = 0;
        $errors = 0;

        foreach (scandir($appRoot) ?: [] as $domain) {
            if ($domain === '.' || $domain === '..' || !is_dir($appRoot . '/' . $domain)) {
                continue;
            }
            if (str_contains($domain, '_trash_')) {
                continue;
            }
            if ($domainFilter !== '' && $domain !== $domainFilter) {
                continue;
            }

            foreach (scandir($appRoot . '/' . $domain) ?: [] as $allias) {
                if ($allias === '.' || $allias === '..' || !is_dir($appRoot . '/' . $domain . '/' . $allias)) {
                    continue;
                }
                if (str_starts_with($allias, '.')) {
                    continue;
                }

                $algoritmDir = $appRoot . '/' . $domain . '/' . $allias . '/algoritm';
                if (!is_dir($algoritmDir)) {
                    continue;
                }

                try {
                    $this->migrateAlias($algoritmDir, $dry, $migrated, $normalized);
                } catch (\Throwable $e) {
                    $r->warn("{$domain}/{$allias}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

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

        if ($errors > 0) {
            $r->error("Ошибок при миграции: {$errors}");
        }

        return $r;
    }

    private function migrateAlias(
        string $algoritmDir,
        bool $dry,
        int &$migrated,
        int &$normalized,
    ): void {
        // 1. Root: algoritm/.rules.json → algoritm/algoritm.rules.json
        $oldRoot = $algoritmDir . '/.rules.json';
        $newRoot = $algoritmDir . '/algoritm.rules.json';

        if (is_file($oldRoot)) {
            if ($dry) {
                $migrated++;
            } else {
                $oldRules = PageRules::load($oldRoot);
                if (is_file($newRoot)) {
                    $newRules = PageRules::load($newRoot);
                    $oldRules = $this->mergeRules($oldRules, $newRules);
                }
                PageRules::save($newRoot, $oldRules);
                unlink($oldRoot);
                $migrated++;
            }
        }

        // 2. Подпапки: {dir}/.rules.json → {dir}/{class}.rules.json
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($algoritmDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                continue;
            }

            $dir = $file->getPathname();
            $rel = trim(substr($dir, strlen($algoritmDir)), '/\\');
            $rel = str_replace('\\', '/', $rel);
            if ($rel === '') {
                continue;
            }

            $oldFile = $dir . '/.rules.json';
            if (!is_file($oldFile)) {
                continue;
            }

            $classChain = 'algoritm_' . str_replace('/', '_', $rel);
            $newFile = $dir . '/' . $classChain . '.rules.json';

            if ($dry) {
                $migrated++;
                continue;
            }

            $oldRules = PageRules::load($oldFile);
            if (is_file($newFile)) {
                $newRules = PageRules::load($newFile);
                $oldRules = $this->mergeRules($oldRules, $newRules);
            }
            PageRules::save($newFile, $oldRules);
            unlink($oldFile);
            $migrated++;
        }

        // 3. Нормализация: все algoritm_*.rules.json получают полный набор полей
        $iterator2 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($algoritmDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator2 as $file) {
            if (!$file->isDir()) {
                continue;
            }

            $dir = $file->getPathname();
            $rel = trim(substr($dir, strlen($algoritmDir)), '/\\');
            $rel = str_replace('\\', '/', $rel);
            $classChain = 'algoritm' . ($rel === '' ? '' : '_' . str_replace('/', '_', $rel));
            $rulesFile = $dir . '/' . $classChain . '.rules.json';

            if (!is_file($rulesFile)) {
                continue;
            }

            $current = PageRules::load($rulesFile);
            $normalizedRules = PageRules::withDefaults($current);

            if ($normalizedRules === $current) {
                continue;
            }

            if (!$dry) {
                PageRules::save($rulesFile, $normalizedRules);
            }
            $normalized++;
        }

        // 4. Создание пустых .editor.json (правило 3 файлов)
        $iterator3 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($algoritmDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator3 as $file) {
            if (!$file->isDir()) {
                continue;
            }

            $dir = $file->getPathname();
            $rel = trim(substr($dir, strlen($algoritmDir)), '/\\');
            $rel = str_replace('\\', '/', $rel);
            $classChain = 'algoritm' . ($rel === '' ? '' : '_' . str_replace('/', '_', $rel));

            $phpFile = $dir . '/' . $classChain . '.php';
            $editorFile = $dir . '/' . $classChain . '.editor.json';

            if (is_file($phpFile) && !is_file($editorFile)) {
                if (!$dry) {
                    Fs::writeAtomic($editorFile, "[]\n");
                }
                $normalized++;
            }
        }
    }

    /**
     * Старые правила (routing) + новые (page settings) → единый формат.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return array<string, mixed>
     */
    private function mergeRules(array $old, array $new): array
    {
        $merged = PageRules::withDefaults($new);

        // available — union (withDefaults гарантирует list<string>)
        $oldAvailable = is_array($old['available'] ?? null)
            ? array_values(array_filter($old['available'], 'is_string'))
            : [];

        $merged['available'] = array_values(array_unique(array_merge($oldAvailable, $merged['available'])));

        // auth/search/default — предпочитаем old, если явно задано
        foreach (['auth', 'search', 'default'] as $k) {
            if (array_key_exists($k, $old)) {
                $merged[$k] = (bool) $old[$k];
            }
        }

        return $merged;
    }
}
