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

        // Фаза 2 backlog: старые команды ещё не переведены на схему domains_app.
        // Явная ошибка вместо тихого «домен не найден».
        if (!isset($site['domains_real']) && isset($site['domains_app'])) {
            throw new \RuntimeException(
                'Команда ещё не переведена на схему domains_app (фаза 2, см. _doc/refactor_site_config.md)',
            );
        }

        $realDomain = (string) ($site['domains_mirrors'][$domain] ?? $domain);
        if (!isset($site['domains_real'][$realDomain])) {
            throw new \RuntimeException('Домен не найден в site.config');
        }

        return $realDomain;
    }
}
