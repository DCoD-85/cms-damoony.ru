<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangAddCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:add';
    }

    public function description(): string
    {
        return 'Добавить язык: .i18nglobal/{lang}.json + регистрация в site.config';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');
        $lang = (string) ($args['lang'] ?? '');

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

        $siteFile = $this->paths->siteConfig();
        if (!is_file($siteFile)) {
            $r->error('site.config не найден');
            return $r;
        }
        $site = Config::fromFile($siteFile)->all();

        $real = (string) ($site['domains_mirrors'][$domain] ?? $domain);
        if (!isset($site['domains_real'][$real])) {
            $r->error('Домен не найден');
            return $r;
        }

        $domainLangs = $site['alliases'][$real]['www']['langs'] ?? [];
        if (!is_array($domainLangs)) {
            $domainLangs = [];
        }

        if ($allias !== '') {
            if (!in_array($lang, $domainLangs, true)) {
                $r->error(sprintf('Язык %s не объявлен для домена %s (сначала lang:add --domain=%s --lang=%s)', $lang, $real, $domain, $lang));
                return $r;
            }

            $dir = $this->paths->i18nLocal($real, $allias);
            $alliasLangs = $site['alliases'][$real][$allias]['langs'] ?? [];
            if (!is_array($alliasLangs)) {
                $alliasLangs = [];
            }
            if (in_array($lang, $alliasLangs, true)) {
                $r->warn(sprintf('Язык %s уже объявлен для алиаса %s', $lang, $allias));
            } else {
                $alliasLangs[] = $lang;
                $site['alliases'][$real][$allias]['langs'] = $alliasLangs;
            }
        } else {
            $dir = $this->paths->i18nGlobal($real);
            if (in_array($lang, $domainLangs, true)) {
                $r->warn(sprintf('Язык %s уже объявлен для домена %s', $lang, $real));
            } else {
                $domainLangs[] = $lang;
                $site['alliases'][$real]['www']['langs'] ?? ['ru', 'en'] = $domainLangs;
            }
        }

        if (!Fs::isDir($dir)) {
            Fs::mkdir($dir);
            $r->file(Fs::rel($dir, $this->paths->root()));
        }

        $file = $dir . '/' . $lang . '.json';
        if (!is_file($file)) {
            Fs::writeAtomic($file, "{}\n");
            $r->file(Fs::rel($file, $this->paths->root()));
        }

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $r->data = ['domain' => $domain, 'allias' => $allias, 'lang' => $lang];
        $context->step(sprintf('Язык добавлен: %s', $lang), true, $lang);

        return $r;
    }
}
