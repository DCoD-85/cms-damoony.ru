<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Settings\SiteConfig;
use RuntimeException;

/**
 * Резолв «--domain + --allias» в проект и алиас новой схемы.
 *
 * Бросает RuntimeException с готовым для пользователя текстом, чтобы команды
 * не дублировали проверки.
 */
final class TargetResolver
{
    public function __construct(private readonly SiteConfig $site)
    {
    }

    public function resolve(string $domain, string $alias): ResolvedTarget
    {
        if ($domain === '') {
            throw new RuntimeException('Укажите --domain');
        }
        if ($alias === '') {
            throw new RuntimeException('Укажите --allias');
        }

        $project = $this->site->resolve($domain);

        $settings = $project->alias($alias);
        if ($settings === null) {
            throw new RuntimeException(
                sprintf('Алиас "%s" не объявлен у проекта "%s"', $alias, $project->key),
            );
        }

        return new ResolvedTarget($project, $settings);
    }
}
