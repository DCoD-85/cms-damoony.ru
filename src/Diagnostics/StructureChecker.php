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

        return $issues;
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
