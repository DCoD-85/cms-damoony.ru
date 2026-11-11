<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands;

use Damoony\Cli\CommandInterface;
use Damoony\Cli\Context;
use Damoony\Cli\Result;
use Damoony\Core\Config;
use Damoony\Core\Files\Fs;
use Damoony\Core\Files\Paths;

final class LangSyncCommand implements CommandInterface
{
    public function __construct(private readonly Paths $paths)
    {
    }

    public function name(): string
    {
        return 'lang:sync';
    }

    public function description(): string
    {
        return 'Синхронизировать alliases_langs[domain][alias] ← domains_langs[domain]';
    }

    public function run(array $args, Context $context): Result
    {
        $r = new Result();

        $domain = (string) ($args['domain'] ?? '');
        $allias = (string) ($args['allias'] ?? '');

        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            $r->error('Некорректный домен');
            return $r;
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
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

        $domainLangs = $site['domains_langs'][$real] ?? [];
        if (!is_array($domainLangs)) {
            $domainLangs = [];
        }
        $domainLangs = array_values(array_filter(
            $domainLangs,
            static fn ($l): bool => is_string($l) && preg_match('/^[a-z]{2}$/', $l) === 1,
        ));

        if ($domainLangs === []) {
            $r->error(sprintf('У домена %s нет объявленных языков (сначала lang:add)', $real));
            return $r;
        }

        $current = $site['alliases_langs'][$real][$allias] ?? [];
        if (!is_array($current)) {
            $current = [];
        }

        $site['alliases_langs'][$real][$allias] = $domainLangs;

        $dir = $this->paths->l18nAllias($real, $allias);
        if (!Fs::isDir($dir)) {
            Fs::mkdir($dir);
            $r->file(Fs::rel($dir, $this->paths->root()));
        }
        foreach ($domainLangs as $lang) {
            $file = $dir . '/' . $lang . '.json';
            if (!is_file($file)) {
                Fs::writeAtomic($file, "{}\n");
                $r->file(Fs::rel($file, $this->paths->root()));
            }
        }

        Fs::writeAtomic(
            $siteFile,
            json_encode($site, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        );
        $r->file(Fs::rel($siteFile, $this->paths->root()));

        $r->data = [
            'domain' => $domain,
            'real' => $real,
            'allias' => $allias,
            'before' => $current,
            'after' => $domainLangs,
        ];
        $context->step(sprintf('Синхронизировано: %d языков', count($domainLangs)), true, $allias);

        return $r;
    }
}
