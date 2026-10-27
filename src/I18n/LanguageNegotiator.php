<?php

declare(strict_types=1);

namespace Damoony\I18n;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;

final class LanguageNegotiator
{
    public const SUPPORTED = ['ru', 'en', 'ch'];

    public function __construct(
        private readonly string $default = 'ru-ru',
    ) {
    }

    public function resolve(Request $request, DomainContext $domain): string
    {
        $fromUrl = $this->fromPath($request->path);
        if ($fromUrl !== null) {
            return $fromUrl;
        }

        $cookie = $request->cookies['lang'] ?? $request->cookies['language'] ?? null;
        if (is_string($cookie) && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }

        return in_array($domain->language, self::SUPPORTED, true) ? $domain->language : $this->default;
    }

    public function fromPath(string $path): ?string
    {
        if ($path === '' || $path === '/') {
            return null;
        }

        $trimmed = ltrim($path, '/');
        $first = explode('/', $trimmed, 2)[0];

        return in_array($first, self::SUPPORTED, true) ? $first : null;
    }

    public function stripLangPrefix(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $trimmed = ltrim($path, '/');
        $parts = explode('/', $trimmed, 2);
        if (!in_array($parts[0], self::SUPPORTED, true)) {
            return $path;
        }

        $rest = $parts[1] ?? '';
        return '/' . $rest;
    }
}
