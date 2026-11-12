<?php

declare(strict_types=1);

namespace Damoony\Cli\Commands\Concerns;

use Damoony\Core\Config;
use Damoony\Core\Files\Paths;

/**
 * Резолв realDomain из site.config.
 *
 * Единственная точка правды для CLI-команд, работающих с FS-путями домена:
 * Paths::domain() / algoritm() / media() / content() ожидают realDomain,
 * а пользовательский ввод (--domain) может быть алиасом.
 */
trait ResolvesRealDomain
{
    /**
     * @throws \RuntimeException
     */
    protected function resolveRealDomain(Paths $paths, string $domain): string
    {
        $siteFile = $paths->siteConfig();
        if (!is_file($siteFile)) {
            throw new \RuntimeException('site.config не найден');
        }

        $site = Config::fromFile($siteFile)->all();
        $real = (string) ($site['domains_real'][$domain] ?? '');
        if ($real === '') {
            throw new \RuntimeException('Домен не найден в site.config');
        }

        return $real;
    }
}
