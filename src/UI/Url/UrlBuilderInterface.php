<?php

declare(strict_types=1);

namespace Damoony\UI\Url;

interface UrlBuilderInterface
{
    public function internal(string $path): string;
    public function asset(string $path): string;
    public function external(string $url): string;
    public function switchLang(string $lang): string;
}
