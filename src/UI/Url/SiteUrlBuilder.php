<?php

declare(strict_types=1);

namespace Damoony\UI\Url;

use Damoony\Core\App\RequestContext;

final class SiteUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private readonly RequestContext $ctx)
    {
    }

    public function internal(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($this->ctx->langStrategy === 'query') {
            return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($this->ctx->lang);
        }
        if ($this->ctx->langStrategy === 'cookie') {
            return $path;
        }
        return '/' . rawurlencode($this->ctx->lang) . $path;
    }

    public function asset(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    public function external(string $url): string
    {
        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, 'mailto:')) {
            return $url;
        }
        return '#'; // fallback для безопасности
    }

    public function switchLang(string $lang): string
    {
        $path = '/' . ltrim($this->ctx->currentPath, '/');
        if ($this->ctx->langStrategy === 'query') {
            return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang);
        }
        if ($this->ctx->langStrategy === 'cookie') {
            return $path;
        }
        return '/' . rawurlencode($lang) . $path;
    }
}
