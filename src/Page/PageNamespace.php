<?php

declare(strict_types=1);

namespace Damoony\Page;

final class PageNamespace
{
    /**
     * Резолвит PHP-namespace домена из site.config.
     *
     * @param array<string, mixed> $site
     */
    public static function fromSite(array $site, string $domain): string
    {
        $namespaces = $site['domains_namespace'] ?? [];
        if (is_array($namespaces) && isset($namespaces[$domain]) && is_string($namespaces[$domain])) {
            return $namespaces[$domain];
        }

        return str_replace(['.', '-'], '_', $domain);
    }
}
