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
}