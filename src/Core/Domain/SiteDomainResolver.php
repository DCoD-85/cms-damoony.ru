<?php

declare(strict_types=1);

namespace Damoony\Core\Domain;

use Damoony\Core\Files\Paths;
use Damoony\Core\Settings\AliasSettings;
use Damoony\Core\Settings\ProjectSettings;
use Damoony\Core\Settings\SiteConfig;

/**
 * Резолвер домена по схеме domains_app.
 *
 * Заменяет ConfigDomainResolver: host → проект (mirrors) → алиас.
 * FS-пути строятся от project.folder, namespace берётся из project.namespace.
 */
final class SiteDomainResolver implements DomainResolverInterface
{
    public function __construct(
        private readonly SiteConfig $site,
        private readonly Paths $paths,
        private readonly string $defaultLanguage = 'ru',
    ) {
    }

    public function resolve(string $host): DomainContext
    {
        $host = SiteConfig::normalizeHost($host);

        $matched = $this->match($host);

        if ($matched === null) {
            return new DomainContext(
                domain: $host,
                real: $host,
                allias: '',
                skin: '.skin',
                language: $this->defaultLanguage,
                available: false,
                supportedLanguages: [$this->defaultLanguage],
            );
        }

        [$project, $alias, $mirrorAvailable] = $matched;
        $aliasSettings = $project->alias($alias);

        $languages = $this->languages($project, $aliasSettings);
        $available = $mirrorAvailable && ($aliasSettings === null || $aliasSettings->available);

        $stub = null;
        if (!$available) {
            $stub = $aliasSettings !== null
                ? $aliasSettings->stubOr($project->stub)
                : $project->stub;
        }

        return new DomainContext(
            domain: $host,
            real: $project->folder,
            allias: $alias,
            skin: $this->skin($project->folder, $alias),
            language: $languages[0],
            available: $available,
            stub: $stub,
            namespace: $project->namespace,
            scheme: $project->scheme,
            supportedLanguages: $languages,
        );
    }

    public function exists(string $domain): bool
    {
        return $this->match(SiteConfig::normalizeHost($domain)) !== null;
    }

    public function real(string $domain): string
    {
        $matched = $this->match(SiteConfig::normalizeHost($domain));

        return $matched === null ? SiteConfig::normalizeHost($domain) : $matched[0]->folder;
    }

    public function namespace(string $domain): string
    {
        $matched = $this->match(SiteConfig::normalizeHost($domain));

        return $matched === null
            ? str_replace(['.', '-'], '_', SiteConfig::normalizeHost($domain))
            : $matched[0]->namespace;
    }

    /**
     * @return array{0: ProjectSettings, 1: string, 2: bool}|null проект, алиас, доступность миррора
     */
    private function match(string $host): ?array
    {
        foreach ($this->site->projects() as $project) {
            foreach ($project->hostNames() as $mirror) {
                if ($host === $mirror) {
                    return [$project, $project->defaultAlias, $project->isHostAvailable($mirror)];
                }

                $suffix = '.' . $mirror;
                if (!str_ends_with($host, $suffix)) {
                    continue;
                }

                $prefix = substr($host, 0, -strlen($suffix));
                if ($prefix !== '' && $project->hasAlias($prefix)) {
                    return [$project, $prefix, $project->isHostAvailable($mirror)];
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function languages(ProjectSettings $project, ?AliasSettings $alias): array
    {
        $langs = $alias === null ? [] : $alias->langs;
        if ($langs === []) {
            $langs = $project->langs;
        }
        if ($langs === []) {
            $langs = [$this->defaultLanguage];
        }

        $langs = array_values(array_filter(
            $langs,
            static fn (string $lang): bool => preg_match('/^[a-z]{2}$/', $lang) === 1,
        ));

        return $langs === [] ? [$this->defaultLanguage] : $langs;
    }

    private function skin(string $folder, string $alias): string
    {
        $skinDir = $this->paths->media($folder, $alias) . '/skin/';
        if (!is_dir($skinDir)) {
            return '.skin';
        }

        $skins = array_values(array_filter(
            array_diff(scandir($skinDir) ?: [], ['.', '..']),
            static fn (string $entry): bool => is_dir($skinDir . $entry),
        ));

        $cookie = $_COOKIE['skin'] ?? '';
        if (is_string($cookie) && in_array($cookie, $skins, true)) {
            return $cookie;
        }

        return $skins === [] ? '.skin' : (string) reset($skins);
    }
}
