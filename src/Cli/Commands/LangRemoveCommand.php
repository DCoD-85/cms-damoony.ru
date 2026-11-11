<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangRemoveCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:remove';
    }

    public function description(): string
    {
        return 'Удалить язык (в карантин) + удалить из site.config';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $lang = (string) ($args['lang'] ?? '');
        $force = isset($args['force']);

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z]{2}$/', $lang)) {
            $r->error('Некорректный язык (ISO 639-1)');
            return $r;
        }
        if ($allias !== '' && !preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            $r->error('Некорректный алиас');
            return $r;
        }
        if (!$force) {
            $r->error('Для удаления укажите --force (файл будет перемещён в карантин)');
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

        if ($allias !== '') {
            $dir = $this->paths->l18nAllias($real, $allias);
            $alliasLangs = $site['alliases_langs'][$real][$allias] ?? [];
            if (!is_array($alliasLangs)) {
                $alliasLangs = [];
            }
            if (count($alliasLangs) <= 1 && in_array($lang, $alliasLangs, true)) {
                $r->error('Нельзя удалить последний язык алиаса');
                return $r;
            }
            $site['alliases_langs'][$real][$allias] = array_values(array_filter(
                $alliasLangs,
                static fn ($l): bool => $l !== $lang,
            ));
        } else {
            $dir = $this->paths->l18nDomain($real);
            $domainLangs = $site['domains_langs'][$real] ?? [];
            if (!is_array($domainLangs)) {
                $domainLangs = [];
            }
            if (count($domainLangs) <= 1 && in_array($lang, $domainLangs, true)) {
                $r->error('Нельзя удалить последний язык домена');
                return $r;
            }
            $site['domains_langs'][$real] = array_values(array_filter(
                $domainLangs,
                static fn ($l): bool => $l !== $lang,
            ));
        }

        $file = $dir . '/' . $lang . '.json';
        if (is_file($file)) {
            $trashDir = $this->paths->root() . '/var/trash/lang_' . $real . '_' . $lang . '_trash_' . time();
            if (!Fs::isDir($trashDir)) {
                Fs::mkdir($trashDir);
            }
            @rename($file, $trashDir . '/' . $lang . '.json');
            $context->step('Файл перемещён в карантин', true, Fs::rel($trashDir, $this->paths->root()));
        } else {
            $r->warn(sprintf('Файл %s не найден — только конфиг обновлён', $file));
        }

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $r->data = ['domain' => $domain, 'allias' => $allias, 'lang' => $lang];
        $context->step(sprintf('Язык удалён: %s', $lang), true, $lang);

        return $r;
    }
}
