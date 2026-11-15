<?php

declare(strict_types=1);

namespace Damoony\Core\Content;

use Damoony\Core\Files\Paths;
use Damoony\Data\Auth\AuthInterface;
use Damoony\UI\Page\PagePath;

/**
 * Логика отдачи контента (без bootstrap, без HTTP-вывода).
 *
 * Ответственность:
 * - parse(): URI → ContentRequest (или null, если не /content/...)
 * - hostAllowed(): проверка Host == domain из URL
 * - requiresAuth(): читает page_refs из meta.json, резолвит .rules.json страниц
 * - resolveMime(): mime из meta.json или по ext
 */
final class ContentResponder
{
    private const URI_RE = '#^/content/([a-z0-9][a-z0-9.-]*\.[a-z]{2,})/([a-z][a-z0-9_]*)/([a-z0-9_-]{1,32})/([a-f0-9]{16})\.([a-zA-Z0-9]+)$#';

    /** @var array<string, string> */
    private const EXT_MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    public static function parse(string $uri): ?ContentRequest
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (preg_match(self::URI_RE, $path, $m) !== 1) {
            return null;
        }

        return new ContentRequest(
            domain: $m[1],
            allias: $m[2],
            category: $m[3],
            hash: $m[4],
            ext: strtolower($m[5]),
            originalUri: $uri,
        );
    }

    /**
     * Host должен совпадать с domain из URL (без порта, без trailing-dot).
     * Не палим контент чужих доменов.
     */
    /**
     * Host должен совпадать с domain из URL (без порта, без trailing-dot).
     * Не палим контент чужих доменов.
     *
     * @param array<string, mixed> $siteConfig
     */
    public static function hostAllowed(string $domain, string $host, array $siteConfig = []): bool
    {
        $host = strtolower($host);
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = rtrim($host, '.');

        if ($host === strtolower($domain)) {
            return true;
        }

        $real = $siteConfig['domains_real'][$host] ?? null;
        if ($real !== null && strtolower((string)$real) === strtolower($domain)) {
            return true;
        }

        // Разрешаем поддомены только если domain — это FQDN (содержит точку)
        if (str_contains($domain, '.') && str_ends_with($host, '.' . strtolower($domain))) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $meta */
    public static function resolveMime(string $ext, array $meta): string
    {
        $fromMeta = $meta['mime'] ?? null;
        if (is_string($fromMeta) && $fromMeta !== '') {
            return $fromMeta;
        }

        return self::EXT_MIME[strtolower($ext)] ?? 'application/octet-stream';
    }

    /**
     * Требуется ли авторизация для картинки.
     * Смотрит page_refs в meta.json: если хоть одна страница с auth:true —
     * возвращает true.
     *
     * @param array<string, mixed> $meta
     */
    public static function requiresAuth(
        ContentRequest $req,
        array $meta,
        Paths $paths,
    ): bool {
        $refs = $meta['page_refs'] ?? null;
        if (!is_array($refs) || $refs === []) {
            return false; // не привязана к страницам — public
        }

        foreach ($refs as $pageName) {
            if (!is_string($pageName) || $pageName === '') {
                continue;
            }

            // __root__ → name=''
            $pageKey = $pageName === '__root__' ? '' : $pageName;

            try {
                $page = PagePath::resolve($paths, $req->domain, $req->allias, $pageKey, '');
            } catch (\Throwable) {
                continue;
            }

            if (!is_file($page->rulesFile)) {
                continue;
            }
            $raw = @file_get_contents($page->rulesFile);
            if ($raw === false) {
                continue;
            }
            $rules = json_decode($raw, true);
            if (is_array($rules) && ($rules['auth'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет, что пользователь авторизован (если нужно).
     * Возвращает true, если можно отдавать файл.
     */
    public static function userCanAccess(
        bool $requiresAuth,
        ?AuthInterface $auth,
    ): bool {
        if (!$requiresAuth) {
            return true;
        }

        return $auth instanceof AuthInterface && $auth->isAuthenticated();
    }

    public static function loginRedirectUrl(string $originalUri): string
    {
        return '/login?return=' . rawurlencode($originalUri);
    }
}
