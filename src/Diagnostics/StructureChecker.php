<?php

declare(strict_types=1);

namespace Damoony\Diagnostics;

use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfig;
use Damoony\UI\Region\FillerState;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Проверка целостности структуры проекта по схеме domains_app.
 *
 * Severity-aware: `error` блокирует (exit 1), `warning` — информативно.
 */
final class StructureChecker
{
    public function __construct(
        private readonly Paths $paths,
        private readonly SiteConfig $site,
        private readonly FillerState $fillerState,
    ) {
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function checkAllias(string $domain, string $alias): array
    {
        try {
            $project = $this->site->resolve($domain);
        } catch (RuntimeException $e) {
            return [['level' => 'error', 'message' => $e->getMessage()]];
        }

        return $this->checkProjectAllias($project, $alias);
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function checkProjectAllias(ProjectSettings $project, string $alias): array
    {
        $issues = [];

        $algoritmDir = $this->paths->algoritm($project->folder, $alias);
        if (!is_dir($algoritmDir)) {
            return [['level' => 'error', 'message' => "Папка algoritm/{$alias} не найдена"]];
        }

        if (!is_file($algoritmDir . '/algoritm.rules.json')) {
            $issues[] = ['level' => 'error', 'message' => "Отсутствует algoritm.rules.json (root алиаса {$alias})"];
        }
        if (!is_file($algoritmDir . '/algoritm.php')) {
            $issues[] = ['level' => 'error', 'message' => "Отсутствует algoritm.php (root алиаса {$alias})"];
        }

        $issues = array_merge($issues, $this->checkSchemeAndSlots($project, $alias));

        $this->checkPages($algoritmDir, $issues);
        $this->checkSchemes($this->paths->schemesGlobal($project->folder), $issues);
        $this->checkBlocks($this->paths->blocksGlobal($project->folder), $issues);
        $this->checkBlocks($this->paths->blocksLocal($project->folder, $alias), $issues);

        return $issues;
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    private function checkSchemeAndSlots(ProjectSettings $project, string $alias): array
    {
        $issues = [];

        $schemesDir = $this->paths->schemesGlobal($project->folder);
        $localSchemesDir = $this->paths->schemesLocal($project->folder, $alias);

        // Приоритет как в SchemeLoader: local {scheme}/{alias} → global {scheme}/{alias} → www.
        $candidates = [
            $localSchemesDir . '/' . $project->scheme . '.php',
            $localSchemesDir . '/' . $alias . '.php',
            $schemesDir . '/' . $project->scheme . '.php',
            $schemesDir . '/' . $alias . '.php',
            $schemesDir . '/www.php',
        ];

        $schemeFile = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $schemeFile = $candidate;
                break;
            }
        }

        if ($schemeFile === null) {
            $issues[] = ['level' => 'error', 'message' => "Не найдена схема (ни {$alias}.php, ни {$project->scheme}.php)"];

            return $issues;
        }

        $schemeData = require $schemeFile;
        if (!is_array($schemeData)) {
            return $issues;
        }

        $slots = [];
        $this->collectSlots($schemeData, $slots);
        $slots = array_values(array_unique($slots));

        $globalBlocksDir = $this->paths->blocksGlobal($project->folder);
        $localBlocksDir = $this->paths->blocksLocal($project->folder, $alias);

        foreach ($slots as $slot) {
            $hasGlobal = is_file($globalBlocksDir . '/' . $slot . '/' . $slot . '.php');
            $hasLocal = is_file($localBlocksDir . '/' . $slot . '/' . $slot . '.php');

            if (!$hasGlobal && !$hasLocal) {
                $issues[] = [
                    'level' => 'error',
                    'message' => "В схеме есть слот '{$slot}', но нет блока (ни глобального, ни локального)",
                ];
            }
        }

        foreach ($this->blockNames($localBlocksDir) as $name) {
            if ($this->fillerState->isEnabled($project->folder, $alias, $name) && !in_array($name, $slots, true)) {
                $issues[] = [
                    'level' => 'warning',
                    'message' => "Филлер '{$name}' включён, но слота '{$name}' нет в схеме",
                ];
            }
        }

        $this->checkSchemeAssets($project, $alias, $schemeData, $issues);

        return $issues;
    }

    /**
     * Ссылки `meta.css` / `meta.js` должны существовать в media скина.
     *
     * @param array<string, mixed> $schemeData
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkSchemeAssets(ProjectSettings $project, string $alias, array $schemeData, array &$issues): void
    {
        $meta = $schemeData['meta'] ?? null;
        if (!is_array($meta)) {
            return;
        }

        $skin = $this->firstSkin($project->folder, $alias);

        foreach (['css', 'js'] as $kind) {
            $list = $meta[$kind] ?? null;
            if (!is_array($list)) {
                continue;
            }

            foreach ($list as $url) {
                if (!is_string($url) || !str_starts_with($url, '/media/')) {
                    continue;
                }

                if ($skin === null && str_contains($url, '{skin')) {
                    $issues[] = [
                        'level' => 'warning',
                        'message' => "Схема ссылается на {$kind} со скином, но у алиаса {$alias} нет ни одного скина",
                    ];

                    continue;
                }

                $path = $this->assetPath($url, $project->folder, $alias, $skin ?? 'default');
                if ($path !== null && !is_file($path)) {
                    $issues[] = [
                        'level' => 'warning',
                        'message' => "Схема ссылается на несуществующий {$kind}: {$url}",
                    ];
                }
            }
        }
    }

    private function assetPath(string $url, string $folder, string $alias, string $skin): ?string
    {
        // Отбрасываем query/fragment: /media/x.css?v=3 — это тот же файл.
        $parts = preg_split('/[?#]/', $url);
        $url = is_array($parts) && $parts !== [] ? (string) $parts[0] : $url;

        $relative = substr($url, strlen('/media/'));
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $relative = str_replace(
            ['{real}', '{alias}', '{skin_path}', '{skin}'],
            [$folder, $alias, $alias . '/skin/' . $skin, $skin],
            $relative,
        );

        return $this->paths->mediaRoot() . '/' . $relative;
    }

    private function firstSkin(string $folder, string $alias): ?string
    {
        $dir = $this->paths->media($folder, $alias) . '/skin';
        if (!is_dir($dir)) {
            return null;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($dir . '/' . $entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkPages(string $algoritmDir, array &$issues): void
    {
        foreach ($this->dirs($algoritmDir) as $dir) {
            $rel = str_replace('\\', '/', trim(substr($dir, strlen($algoritmDir)), '/\\'));
            if ($rel === '') {
                continue; // root проверен отдельно
            }

            $classChain = 'algoritm_' . str_replace('/', '_', $rel);

            if (!is_file($dir . '/' . $classChain . '.php')) {
                $issues[] = ['level' => 'error', 'message' => "Нет файла {$classChain}.php в папке /{$rel}"];
            }
            if (!is_file($dir . '/' . $classChain . '.rules.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Нет файла {$classChain}.rules.json в папке /{$rel}"];
            }
            if (!is_file($dir . '/' . $classChain . '.editor.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Нет файла {$classChain}.editor.json в папке /{$rel}"];
            }
            if (is_file($dir . '/.rules.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Устаревший /{$rel}/.rules.json — запустите migrate:page-rules"];
            }
        }
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkSchemes(string $schemesDir, array &$issues): void
    {
        if (!is_dir($schemesDir)) {
            return;
        }

        foreach (glob($schemesDir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (!is_file($schemesDir . '/' . $name . '.rules.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.rules.json для схемы {$name}"];
            }
        }
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkBlocks(string $blocksDir, array &$issues): void
    {
        foreach ($this->blockNames($blocksDir) as $name) {
            if (!is_file($blocksDir . '/' . $name . '/' . $name . '.rules.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.rules.json для блока {$name}"];
            }
            if (!is_file($blocksDir . '/' . $name . '/' . $name . '.editor.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.editor.json для блока {$name}"];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function blockNames(string $blocksDir): array
    {
        if (!is_dir($blocksDir)) {
            return [];
        }

        $names = [];
        foreach (scandir($blocksDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            if (is_dir($blocksDir . '/' . $entry)) {
                $names[] = $entry;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function dirs(string $base): array
    {
        $out = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isDir()) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $slots
     */
    private function collectSlots(array $node, array &$slots): void
    {
        if (isset($node['slot']) && is_string($node['slot'])) {
            $slots[] = $node['slot'];
        }

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    $this->collectSlots($child, $slots);
                }
            }
        }
    }
}
