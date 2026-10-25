<?php

declare(strict_types=1);

namespace Damoony\Domain;

use Damoony\Core\Config;

final class ConfigDomainResolver implements DomainResolverInterface
{
    public function __construct(
        private readonly Config $site,
        private readonly string $defaultLogic = 'alpha.0.0',
        private readonly string $defaultSkin = 'default',
        private readonly string $defaultLanguage = 'ru-ru',
    ) {
    }

    public function resolve(string $host): DomainContext
    {
        $host = strtolower($host);
        $knownDomains = $this->knownDomains();
        $real = null;
        $allias = '';

        foreach ($knownDomains as $known) {
            if ($host === $known) {
                $real = $known;
                $allias = (string) $this->site->getByKey(['alliases_real', $known, 'www'], 'www');
                break;
            }
            $suffix = '.' . $known;
            if (str_ends_with($host, $suffix)) {
                $prefix = substr($host, 0, -strlen($suffix));
                $alliases = $this->site->getByKey(['alliases_real', $known], []);
                if (is_array($alliases) && array_key_exists($prefix, $alliases)) {
                    $real = $known;
                    $allias = $prefix === '' ? 'www' : $prefix;
                    break;
                }
            }
        }

        if ($real === null) {
            return new DomainContext(
                domain: $host,
                real: $host,
                allias: '',
                logic: $this->defaultLogic,
                skin: $this->defaultSkin,
                language: $this->defaultLanguage,
                available: false,
            );
        }

        $available = (bool) $this->site->getByKey(['domains_available', $real], false);
        $stub = null;
        if (!$available) {
            $stub = (string) $this->site->getByKey(['domains_stub', $real, 'current'], 'maintenance');
        }

        return new DomainContext(
            domain: $host,
            real: $real,
            allias: $allias,
            logic: (string) $this->site->getByKey(['domains_logic', $real], $this->defaultLogic),
            skin: (string) $this->site->getByKey(['domains_skins', $real], $this->defaultSkin),
            language: (string) $this->site->get('language', $this->defaultLanguage),
            available: $available,
            stub: $stub,
        );
    }

    public function exists(string $domain): bool
    {
        return in_array(strtolower($domain), $this->knownDomains(), true);
    }

    public function real(string $domain): string
    {
        $ctx = $this->resolve($domain);
        return $ctx->available || $ctx->allias !== '' ? $ctx->real : $domain;
    }

    public function namespace(string $domain): string
    {
        $real = $this->real($domain);
        return (string) $this->site->getByKey(
            ['domains_namespace', $real],
            str_replace('.', '_', $real),
        );
    }

    /** @return list<string> */
    private function knownDomains(): array
    {
        $domains = $this->site->getByKey(['domains_real'], []);
        if (!is_array($domains)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', array_keys($domains)), fn (string $d) => $d !== ''));
    }
}