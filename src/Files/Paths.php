<?php

declare(strict_types=1);

namespace Damoony\Files;

final class Paths
{
    public function __construct(private readonly string $root)
    {
    }

    public function root(): string
    {
        return $this->root;
    }

    public function siteConfig(): string
    {
        return $this->root . '/config/site.config';
    }

    public function engineConfig(): string
    {
        return $this->root . '/config/engine.config';
    }

    public function appRoot(): string
    {
        return $this->root . '/app';
    }

    public function sessionDir(string $tabId, string $real): string
    {
        return $this->appRoot() . '/.sess_' . $tabId . '.' . $real;
    }

    public function domain(string $real): string
    {
        return $this->appRoot() . '/' . $real;
    }

    public function allias(string $real, string $allias): string
    {
        return $this->domain($real) . '/' . $allias;
    }

    public function algoritm(string $real, string $allias): string
    {
        return $this->allias($real, $allias) . '/algoritm';
    }

    /**
     * Инфраструктура (skins, admin, shared). Отдаётся статикой через www/media/.
     */
    public function mediaRoot(): string
    {
        return $this->root . '/www/media';
    }

    public function media(string $real, string $allias): string
    {
        return $this->mediaRoot() . '/' . $real . '/' . $allias;
    }

    /**
     * AI/user-контент (картинки страниц). Хранится ВНЕ webroot — только через www/content.php.
     */
    public function contentRoot(): string
    {
        return $this->root . '/content';
    }

    public function content(string $real, string $allias): string
    {
        return $this->contentRoot() . '/' . $real . '/' . $allias;
    }
}
