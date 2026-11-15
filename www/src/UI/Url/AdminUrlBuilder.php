<?php

declare(strict_types=1);

namespace Damoony\UI\Url;

use Damoony\Core\App\RequestContext;

final class AdminUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private readonly RequestContext $ctx)
    {
    }

    public function internal(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $prefix = $this->ctx->basePath; // '/admin' или ''

        if ($this->ctx->langStrategy === 'query') {
            $sep = str_contains($path, '?') ? '&' : '?';
            return $prefix . $path . $sep . 'lang=' . rawurlencode($this->ctx->lang);
        }

        if ($this->ctx->langStrategy === 'cookie') {
            return $prefix . $path;
        }

        return $prefix . '/' . rawurlencode($this->ctx->lang) . $path;
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
        return '#';
    }

    public function switchLang(string $lang): string
    {
        $path = '/' . ltrim($this->ctx->currentPath, '/');
        $prefix = $this->ctx->basePath;

        if ($this->ctx->langStrategy === 'query') {
            $sep = str_contains($path, '?') ? '&' : '?';
            return $prefix . $path . $sep . 'lang=' . rawurlencode($lang);
        }

        if ($this->ctx->langStrategy === 'cookie') {
            return $prefix . $path;
        }

        return $prefix . '/' . rawurlencode($lang) . $path;
    }
}
