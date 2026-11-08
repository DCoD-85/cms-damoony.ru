<?php

declare(strict_types=1);

namespace Damoony\UI;

/**
 * Allow-list валидатор URL для скомпилированных страниц/блоков.
 *
 * Attrs::release() делает HTML-escape, но НЕ блокирует scheme:
 * Builder::a('javascript:...') → <a href="javascript:...">. CSP `script-src 'self'`
 * блокирует исполнение, но defense-in-depth требует фильтра на этапе компиляции.
 *
 * При отказе возвращает безопасный fallback ('#' для href, '' для src).
 * Исключение не бросаем: страница рендерится без выполнения опасного URL.
 */
final class UrlSanitizer
{
    /** Schemes, разрешённые в href (навигация, почта, телефон). */
    private const HREF_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Schemes, разрешённые в img src. */
    private const SRC_SCHEMES = ['http', 'https'];

    public static function forHref(string $url): string
    {
        return self::check($url, self::HREF_SCHEMES, '#', false);
    }

    public static function forSrc(string $url): string
    {
        return self::check($url, self::SRC_SCHEMES, '', true);
    }

    /**
     * @param list<string> $schemes
     */
    private static function check(string $url, array $schemes, string $fallback, bool $allowDataImage): string
    {
        $url = trim($url);
        if ($url === '') {
            return $fallback;
        }

        // Relative path или anchor — безопасно.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            // Блок protocol-relative '//evil.com'.
            if (str_starts_with($url, '//')) {
                return $fallback;
            }
            return $url;
        }

        // data:image/* — только для <img src>.
        if ($allowDataImage && preg_match('#^data:image/[a-z0-9.+-]+;#i', $url) === 1) {
            return $url;
        }

        // Абсолютный URL со схемой.
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m) !== 1) {
            // Без схемы и не начинается с / или # — не рискуем.
            return $fallback;
        }

        $scheme = strtolower($m[1]);
        if (!in_array($scheme, $schemes, true)) {
            return $fallback;
        }

        return $url;
    }
}
