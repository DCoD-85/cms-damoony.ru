<?php

declare(strict_types=1);

namespace Damoony\Cli\Db\Concerns;

use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * Резолв проекта для dbcli-команд (схема domains_app).
 */
trait ResolvesProject
{
    /**
     * @throws RuntimeException
     */
    protected function resolveProject(SiteConfig $site, string $domain): ProjectSettings
    {
        if ($domain === '') {
            throw new RuntimeException('Укажите --domain');
        }

        return $site->resolve($domain);
    }
}
