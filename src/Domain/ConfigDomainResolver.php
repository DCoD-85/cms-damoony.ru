<?php

declare(strict_types=1);

namespace Damoony\Domain;

use Damoony\Core\Config;

final class ConfigDomainResolver implements DomainResolverInterface
{
    public function __construct(
        private readonly Config $site,
        private readonly string $defaultSkin = 'default',
        private readonly string $defaultLanguage = 'ru',
    ) {
    }

    public function resolve(string $host): DomainContext
    {
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = strtolower(rtrim($host, '.'));
        $real = null;
        $allias = '';

        foreach ($this->domainsMap() as $incoming => $mapped) {
            if ($host === $incoming) {
                $real = $mapped;
                $allias = (string) $this->site->getByKey(['alliases_real', $mapped, 'www'], 'www');
                break;
            }
            $suffix = '.' . $incoming;
            if (str_ends_with($host, $suffix)) {
                $prefix = substr($host, 0, -strlen($suffix));
                $alliases = $this->site->getByKey(['alliases_real', $mapped], []);
                if (is_array($alliases) && array_key_exists($prefix, $alliases)) {
                    $real = $mapped;
                    $allias = $prefix === '' ? 'www' : $prefix;
                    break;
                }
            }
        }

        // Если домен не найден в конфиге — отдаем "пустой" контекст для 404
        if ($real === null) {
            return new DomainContext(
                domain: $host,
                real: $host,
                allias: '',
                skin: $this->defaultSkin,
                language: $this->defaultLanguage,
                available: false,
            );
        }

        $available = (bool) $this->site->getByKey(['domains_available', $real], false);
        $aliasAvailable = (bool) $this->site->getByKey(['alliases_available', $real, $allias], true);

        $stub = null;
        if (!$available) {
            $stub = (string) $this->site->getByKey(['domains_stub', $real, 'current'], 'maintenance');
        } elseif (!$aliasAvailable) {
            $available = false;
            $stub = (string) $this->site->getByKey(
                ['alliases_stub', $real, $allias, 'current'],
                (string) $this->site->getByKey(['domains_stub', $real, 'current'], 'maintenance'),
            );
        }

        // Определяем язык по умолчанию для этого алиаса (или домена)
        $langs = $this->site->getByKey(['alliases_langs', $real, $allias]);
        if (!is_array($langs) || $langs === []) {
            $langs = $this->site->getByKey(['domains_langs', $real], [$this->defaultLanguage]);
        }
        $lang = is_array($langs) && $langs !== [] ? (string) reset($langs) : $this->defaultLanguage;

        return new DomainContext(
            domain: $host,
            real: $real,
            allias: $allias,
            skin: (string) $this->site->getByKey(['domains_skins', $real], $this->defaultSkin),
            language: $lang,
            available: $available,
            stub: $stub,
            namespace: (string) $this->site->getByKey(['domains_namespace', $real], str_replace('.', '_', $real)),
            scheme: (string) $this->site->getByKey(['domains_scheme', $real], 'www'),
        );
    }

    public function exists(string $domain): bool
    {
        return array_key_exists(strtolower($domain), $this->domainsMap());
    }

    public function real(string $domain): string
    {
        return $this->resolve($domain)->real;
    }

    public function namespace(string $domain): string
    {
        $real = $this->real($domain);
        return (string) $this->site->getByKey(
            ['domains_namespace', $real],
            str_replace('.', '_', $real),
        );
    }

    /** @return array<string, string> */
    private function domainsMap(): array
    {
        $domains = $this->site->getByKey(['domains_real'], []);
        if (!is_array($domains)) {
            return [];
        }

        $map = [];
        foreach ($domains as $incoming => $mapped) {
            if (!is_string($incoming) || $incoming === '') {
                continue;
            }
            if (!is_string($mapped) || $mapped === '') {
                continue;
            }
            $map[$incoming] = $mapped;
        }

        return $map;
    }
}
