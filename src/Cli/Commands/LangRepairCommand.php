<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangRepairCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:repair';
    }

    public function description(): string
    {
        return 'Починить drift между config и .l18n/*.json (без --fix — dry-run)';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $fix = ($args['fix'] ?? '0') === '1';
        $prune = ($args['prune'] ?? '0') === '1';
        $promote = ($args['promote'] ?? '0') === '1';

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if ($allias !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();
        if (!isset($site['domains_real'][$domain])) {
            $r->error('Домен не найден');
            return $r;
        }
        $real = (string) $site['domains_real'][$domain];

        $dir = $allias !== ''
            ? $this->paths->l18nAllias($real, $allias)
            : $this->paths->l18nDomain($real);

        // 1. Собрать config-языки (домена или алиаса)
        if ($allias !== '') {
            $configLangs = $site['alliases_langs'][$real][$allias] ?? [];
        } else {
            $configLangs = $site['domains_langs'][$real] ?? [];
        }
        if (!is_array($configLangs)) {
            $configLangs = [];
        }
        $configLangs = array_values(array_filter(
            $configLangs,
            static fn ($l): bool => is_string($l) && preg_match('/^[a-z]{2}$/', $l) === 1,
        ));

        // 2. Собрать файлы
        $fileLangs = [];
        if (Fs::isDir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $lang = basename($file, '.json');
                if (preg_match('/^[a-z]{2}$/', $lang)) {
                    $fileLangs[] = $lang;
                }
            }
            sort($fileLangs);
        }

        // 3. Drift
        $drifts = [];
        foreach ($configLangs as $lang) {
            $file = $dir . '/' . $lang . '.json';
            if (!is_file($file)) {
                $drifts[] = ['kind' => 'config_without_file', 'lang' => $lang, 'file' => Fs::rel($file, $this->paths->root())];
            }
        }
        foreach ($fileLangs as $lang) {
            if (!in_array($lang, $configLangs, true)) {
                $drifts[] = ['kind' => 'file_without_config', 'lang' => $lang, 'file' => Fs::rel($dir . '/' . $lang . '.json', $this->paths->root())];
            }
        }

        // 4. Alias ⊄ domain (только в alias-scope)
        if ($allias !== '') {
            $domainLangsRaw = $site['domains_langs'][$real] ?? [];
            $domainLangs = is_array($domainLangsRaw) ? $domainLangsRaw : [];
            foreach ($configLangs as $lang) {
                if (!in_array($lang, $domainLangs, true)) {
                    $drifts[] = ['kind' => 'alias_lang_not_in_domain', 'lang' => $lang, 'file' => ''];
                }
            }
        }

        if ($drifts === []) {
            $context->step('Drift не найден', true, $allias !== '' ? $real . '/' . $allias : $real);
            $r->data = ['domain' => $domain, 'real' => $real, 'allias' => $allias, 'drifts' => [], 'applied' => []];
            return $r;
        }

        foreach ($drifts as $d) {
            $context->step(sprintf('[%s] %s', $d['kind'], $d['lang']), !$fix, $d['file']);
        }

        if (!$fix) {
            $context->step('Dry-run (для применения --fix=1)', true);
            $r->data = ['domain' => $domain, 'real' => $real, 'allias' => $allias, 'drifts' => $drifts, 'applied' => []];
            return $r;
        }

        // 5. Применить
        if (!Fs::isDir($dir)) {
            Fs::mkdir($dir);
            $r->file(Fs::rel($dir, $this->paths->root()));
        }

        $applied = [];
        $configChanged = false;
        $newConfigLangs = $configLangs;

        foreach ($drifts as $d) {
            if ($d['kind'] === 'config_without_file') {
                if ($prune) {
                    $newConfigLangs = array_values(array_filter($newConfigLangs, static fn ($l) => $l !== $d['lang']));
                    $applied[] = ['kind' => 'removed_from_config', 'lang' => $d['lang']];
                    $configChanged = true;
                } else {
                    $file = $dir . '/' . $d['lang'] . '.json';
                    if (!is_file($file)) {
                        Fs::writeAtomic($file, "{}\n");
                        $r->file(Fs::rel($file, $this->paths->root()));
                        $applied[] = ['kind' => 'created_file', 'lang' => $d['lang']];
                    }
                }
            } elseif ($d['kind'] === 'file_without_config') {
                if (!in_array($d['lang'], $newConfigLangs, true)) {
                    $newConfigLangs[] = $d['lang'];
                    $applied[] = ['kind' => 'added_to_config', 'lang' => $d['lang']];
                    $configChanged = true;
                }
            } elseif ($promote) {
                $domainLangs = $site['domains_langs'][$real] ?? [];
                if (!is_array($domainLangs)) {
                    $domainLangs = [];
                }
                if (!in_array($d['lang'], $domainLangs, true)) {
                    $domainLangs[] = $d['lang'];
                    $site['domains_langs'][$real] = $domainLangs;
                    $applied[] = ['kind' => 'promoted_to_domain', 'lang' => $d['lang']];
                    $configChanged = true;
                }
            }
        }

        if ($allias !== '' && $configChanged) {
            $site['alliases_langs'][$real][$allias] = $newConfigLangs;
        } elseif ($allias === '' && $configChanged) {
            $site['domains_langs'][$real] = $newConfigLangs;
        }

        if ($configChanged) {
            Fs::writeAtomic(
                $siteFile,
                json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            );
            $r->file(Fs::rel($siteFile, $this->paths->root()));
        }

        $r->data = ['domain' => $domain, 'real' => $real, 'allias' => $allias, 'drifts' => $drifts, 'applied' => $applied];
        $context->step(sprintf('Исправлено: %d', count($applied)), true);

        return $r;
    }
}
