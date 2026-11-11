<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangListCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:list';
    }

    public function description(): string
    {
        return 'Список языков: файлы .l18n/{lang}.json + (--with-config) config';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $withConfig = ($args['with-config'] ?? '0') === '1';

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

        $configLangs = [];
        if ($withConfig) {
            if ($allias !== '') {
                $raw = $site['alliases_langs'][$real][$allias] ?? [];
            } else {
                $raw = $site['domains_langs'][$real] ?? [];
            }
            if (is_array($raw)) {
                foreach ($raw as $l) {
                    if (is_string($l) && preg_match('/^[a-z]{2}$/', $l)) {
                        $configLangs[] = $l;
                    }
                }
            }
            sort($configLangs);
        }

        $effective = array_values(array_unique(array_merge($fileLangs, $configLangs)));
        sort($effective);

        $rows = [];
        foreach ($effective as $lang) {
            $hasFile = in_array($lang, $fileLangs, true);
            $inConfig = in_array($lang, $configLangs, true);
            $file = $hasFile ? Fs::rel($dir . '/' . $lang . '.json', $this->paths->root()) : null;
            $rows[] = ['lang' => $lang, 'file' => $file, 'has_file' => $hasFile, 'in_config' => $inConfig];
            if ($hasFile && is_string($file)) {
                $r->file($file);
            }
            $flags = [];
            if ($hasFile) {
                $flags[] = 'file';
            }
            if ($inConfig) {
                $flags[] = 'config';
            }
            $context->step(sprintf('%-4s [%s]', $lang, implode(',', $flags)), true, $lang);
        }

        $r->data = [
            'domain' => $domain,
            'real' => $real,
            'allias' => $allias,
            'with_config' => $withConfig,
            'file_langs' => $fileLangs,
            'config_langs' => $configLangs,
            'effective_langs' => $effective,
            'rows' => $rows,
        ];
        $context->step(sprintf('Всего: %d', count($effective)), true);

        return $r;
    }
}
