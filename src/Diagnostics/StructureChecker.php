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
        }

        return $issues;
    }
}
