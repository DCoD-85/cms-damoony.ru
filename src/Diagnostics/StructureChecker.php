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

        $schemeName = (string) $this->site->getByKey(['domains_scheme', $realDomain], 'www');
        $localScheme = $this->paths->domain($realDomain) . '/.alliasscheme/' . $allias . '.data.php';
        $globalScheme = $this->paths->domain($realDomain) . '/.alliasscheme/' . $schemeName . '.data.php';

        if (!is_file($localScheme) && !is_file($globalScheme)) {
            $issues[] = ['level' => 'error', 'message' => "Не найдена схема (ни {$allias}.data.php, ни {$schemeName}.data.php)"];
            return $issues; // Дальше проверять стыковку нет смысла
        }

        // ПРОВЕРКА СТЫКОВКИ СХЕМЫ И БЛОКОВ
        $schemeFile = is_file($localScheme) ? $localScheme : $globalScheme;
        $schemeData = require $schemeFile;

        if (is_array($schemeData)) {
            $schemeSlots = [];
            $this->collectSlots($schemeData, $schemeSlots);
            $schemeSlots = array_unique($schemeSlots);

            $blocksDir = $this->paths->domain($realDomain) . '/.alliasglobal';
            $globalBlocks = is_dir($blocksDir) ? glob($blocksDir . '/*', GLOB_ONLYDIR) : [];
            $globalBlocks = array_map('basename', $globalBlocks ?: []);

            // 1. Слоты в схеме, для которых нет ни глобального блока, ни локального филлера
            foreach ($schemeSlots as $slot) {
                if (!in_array($slot, $globalBlocks, true)) {
                    $localFiller = $blocksDir . '/' . $slot . '/' . $slot . '_' . $allias . '.php';
                    if (!is_file($localFiller)) {
                        $issues[] = ['level' => 'error', 'message' => "В схеме есть слот '{$slot}', но нет глобального блока (или файлов локального филлера)"];
                    }
                }
            }

            // 2. Включенные филлеры, которых нет в схеме
            $fillers = $this->site->getByKey(['fillers', $realDomain, $allias], []);
            if (is_array($fillers)) {
                foreach ($fillers as $fillerName => $enabled) {
                    if ($enabled && !in_array($fillerName, $schemeSlots, true)) {
                        $issues[] = ['level' => 'warning', 'message' => "Филлер '{$fillerName}' включен, но слота '{$fillerName}' нет в схеме"];
                    }
                }
            }
        }

        // ПРОВЕРКА .edit.json ↔ PHP
        $this->checkPagesJson($algoritmDir, $issues);

        // ПРОВЕРКА schemes.json ↔ файлы
        $schemesDir = $this->paths->domain($realDomain) . '/.alliasscheme';
        $this->checkSchemesJson($schemesDir, $allias, $issues);

        // ПРОВЕРКА blocks.json ↔ папки
        $blocksDir = $this->paths->domain($realDomain) . '/.alliasglobal';
        $this->checkBlocksJson($blocksDir, $issues);

        return $issues;
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkPagesJson(string $algoritmDir, array &$issues): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($algoritmDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === '.edit.json') {
                $dir = $file->getPath();
                $phpFiles = glob($dir . '/algoritm_*.php');
                if (empty($phpFiles)) {
                    $issues[] = ['level' => 'warning', 'message' => 'Есть .edit.json, но нет сгенерированного algoritm_*.php в ' . str_replace($algoritmDir, '', $dir)];
                }
            }
        }
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkBlocksJson(string $blocksDir, array &$issues): void
    {
        $manifestFile = $blocksDir . '/blocks.json';
        $manifestExists = is_file($manifestFile);
        $declared = [];

        if ($manifestExists) {
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest) || !isset($manifest['blocks']) || !is_array($manifest['blocks'])) {
                $issues[] = ['level' => 'warning', 'message' => "blocks.json повреждён или не содержит ключ 'blocks'"];
            } else {
                $declared = array_keys($manifest['blocks']);
            }
        }

        $folders = is_dir($blocksDir)
            ? array_map('basename', glob($blocksDir . '/*', GLOB_ONLYDIR) ?: [])
            : [];

        foreach ($declared as $name) {
            if (!in_array($name, $folders, true)) {
                $issues[] = ['level' => 'error', 'message' => "Блок '{$name}' есть в blocks.json, но папка {$name}/ отсутствует"];
            }
        }

        foreach ($folders as $name) {
            if (!in_array($name, $declared, true)) {
                $issues[] = ['level' => 'warning', 'message' => "Папка блока {$name}/ есть, но записи в blocks.json нет (сирота)"];
            }
        }
    }

    /**
     * @param list<array{level: string, message: string}> $issues
     */
    private function checkSchemesJson(string $schemesDir, string $allias, array &$issues): void
    {
        $manifestFile = $schemesDir . '/schemes.json';
        $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : [];
        $schemes = is_array($manifest) && isset($manifest['schemes']) ? array_keys($manifest['schemes']) : [];

        $files = glob($schemesDir . '/*.data.php') ?: [];
        $fileNames = array_map(fn ($f) => basename($f, '.data.php'), $files);

        // 1. В манифесте есть, а файла нет
        foreach ($schemes as $s) {
            if (!in_array($s, $fileNames, true)) {
                $issues[] = ['level' => 'error', 'message' => "Схема '{$s}' есть в schemes.json, но файл {$s}.data.php отсутствует"];
            }
        }

        // 2. Файл есть, а в манифесте нет (игнорируем локальные схемы алиасов)
        foreach ($fileNames as $f) {
            if (!in_array($f, $schemes, true) && $f !== $allias) {
                $issues[] = ['level' => 'warning', 'message' => "Файл {$f}.data.php есть, но его нет в schemes.json (сирота)"];
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
