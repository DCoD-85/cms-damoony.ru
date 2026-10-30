<?php

declare(strict_types=1);

namespace Damoony\Diagnostics;

use Damoony\Core\Config;
use Damoony\Files\Paths;

final class StructureChecker
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Config $site,
    ) {
    }

    /** @return list<array{level: string, message: string}> */
    public function checkAllias(string $domain, string $allias): array
    {
        $issues = [];
        $realDomain = (string) $this->site->getByKey(['domains_real', $domain], $domain);
        $algoritmDir = $this->paths->domain($realDomain) . '/' . $allias . '/algoritm';

        if (!is_dir($algoritmDir)) {
            return [['level' => 'error', 'message' => "Папка algoritm/{$allias} не найдена"]];
        }

        if (!is_file($algoritmDir . '/.rules.json')) {
            $issues[] = ['level' => 'error', 'message' => "Отсутствует .rules.json в algoritm/{$allias}"];
        }

        if (!is_file($algoritmDir . '/algoritm.php')) {
            $issues[] = ['level' => 'error', 'message' => "Отсутствует главный файл algoritm.php в algoritm/{$allias}"];
        }

        // 1. Проверка стыковки Схемы и Блоков
        $schemeName = (string) $this->site->getByKey(['domains_scheme', $realDomain], 'www');
        $localScheme = $this->paths->domain($realDomain) . '/.alliasscheme/' . $allias . '.php';
        $globalScheme = $this->paths->domain($realDomain) . '/.alliasscheme/' . $schemeName . '.php';

        if (!is_file($localScheme) && !is_file($globalScheme)) {
            $issues[] = ['level' => 'error', 'message' => "Не найдена схема (ни {$allias}.data.php, ни {$schemeName}.data.php)"];
            return $issues;
        }

        $schemeFile = is_file($localScheme) ? $localScheme : $globalScheme;
        $schemeData = require $schemeFile;

        if (is_array($schemeData)) {
            $schemeSlots = [];
            $this->collectSlots($schemeData, $schemeSlots);
            $schemeSlots = array_unique($schemeSlots);

            $blocksDir = $this->paths->domain($realDomain) . '/.alliasglobal';
            $globalBlocks = is_dir($blocksDir) ? glob($blocksDir . '/*', GLOB_ONLYDIR) : [];
            $globalBlocks = array_map('basename', $globalBlocks ?: []);

            foreach ($schemeSlots as $slot) {
                if (!in_array($slot, $globalBlocks, true)) {
                    $localFiller = $blocksDir . '/' . $slot . '/' . $slot . '_' . $allias . '.php';
                    if (!is_file($localFiller)) {
                        $issues[] = ['level' => 'error', 'message' => "В схеме есть слот '{$slot}', но нет глобального блока (или файлов локального филлера)"];
                    }
                }
            }

            $fillers = $this->site->getByKey(['fillers', $realDomain, $allias], []);
            if (is_array($fillers)) {
                foreach ($fillers as $fillerName => $enabled) {
                    if ($enabled && !in_array($fillerName, $schemeSlots, true)) {
                        $issues[] = ['level' => 'warning', 'message' => "Филлер '{$fillerName}' включен, но слота '{$fillerName}' нет в схеме"];
                    }
                }
            }
        }

        // 2. Проверка страниц (наличие PHP и editor.json)
        $this->checkPagesStructure($algoritmDir, $issues);

        // 3. Проверка схем
        $schemesDir = $this->paths->domain($realDomain) . '/.alliasscheme';
        $this->checkSchemesJson($schemesDir, $allias, $issues);

        // 4. Проверка блоков (наличие .rules.json и .editor.json)
        $blocksDir = $this->paths->domain($realDomain) . '/.alliasglobal';
        $this->checkBlocksRules($blocksDir, $issues);

        return $issues;
    }

    /** @param list<array{level: string, message: string}> $issues */
    private function checkPagesStructure(string $algoritmDir, array &$issues): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($algoritmDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dir = $file->getPathname();
                if (basename($dir) === 'algoritm') {
                    continue;
                }

                $name = basename($dir);
                $phpFile = $dir . '/algoritm_' . $name . '.php';
                $rulesFile = $dir . '/algoritm_' . $name . '.rules.json';
                $editorFile = $dir . '/algoritm_' . $name . '.editor.json';

                if (!is_file($phpFile)) {
                    $issues[] = ['level' => 'error', 'message' => "Нет файла algoritm_{$name}.php в папке " . str_replace($algoritmDir, '', $dir)];
                }
                if (!is_file($rulesFile)) {
                    $issues[] = ['level' => 'warning', 'message' => "Нет файла algoritm_{$name}.rules.json в папке " . str_replace($algoritmDir, '', $dir)];
                }
                if (!is_file($editorFile)) {
                    $issues[] = ['level' => 'warning', 'message' => "Нет файла algoritm_{$name}.editor.json в папке " . str_replace($algoritmDir, '', $dir)];
                }
            }
        }
    }

    /** @param list<array{level: string, message: string}> $issues */
    private function checkBlocksRules(string $blocksDir, array &$issues): void
    {
        $folders = is_dir($blocksDir) ? array_map('basename', glob($blocksDir . '/*', GLOB_ONLYDIR) ?: []) : [];
        foreach ($folders as $name) {
            $rulesFile = $blocksDir . '/' . $name . '/' . $name . '.rules.json';
            $editorFile = $blocksDir . '/' . $name . '/' . $name . '.editor.json';

            if (!is_file($rulesFile)) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.rules.json для блока {$name}"];
            }
            if (!is_file($editorFile)) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.editor.json для блока {$name}"];
            }
        }
    }

    /** @param list<array{level: string, message: string}> $issues */
    private function checkSchemesJson(string $schemesDir, string $allias, array &$issues): void
    {
        if (!is_dir($schemesDir)) {
            return;
        }
        $files = glob($schemesDir . '/*.php') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!is_file($schemesDir . '/' . $name . '.rules.json')) {
                $issues[] = ['level' => 'warning', 'message' => "Отсутствует {$name}.rules.json для схемы {$name}"];
            }
        }
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
