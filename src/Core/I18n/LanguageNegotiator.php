<?php

declare(strict_types=1);

namespace Damoony\Core\I18n;

use Damoony\Core\Domain\DomainContext;
use Damoony\Core\Http\Request;

final class LanguageNegotiator
{
    /** @param list<string> $supported */
    public function __construct(
        private readonly array $supported,
        private readonly string $default,
    ) {
    }

    public function resolve(Request $request, DomainContext $domain): string
    {
        $fromUrl = $this->fromPath($request->path);
        if ($fromUrl !== null) {
            return $fromUrl;
        }

        $cookie = $request->cookies['lang'] ?? $request->cookies['language'] ?? null;
        if (is_string($cookie) && in_array($cookie, $this->supported, true)) {
            return $cookie;
        }

        if (in_array($domain->language, $this->supported, true)) {
            return $domain->language;
        }

        return $this->default;
    }

    public function fromPath(string $path): ?string
    {
        if ($path === '' || $path === '/') {
            return null;
        }

        $trimmed = ltrim($path, '/');
        $first = explode('/', $trimmed, 2)[0];

        return in_array($first, $this->supported, true) ? $first : null;
    }

    public function stripLangPrefix(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $trimmed = ltrim($path, '/');
        $parts = explode('/', $trimmed, 2);
        if (!in_array($parts[0], $this->supported, true)) {
            return $path;
        }

        return '/' . ($parts[1] ?? '');
    }
}
