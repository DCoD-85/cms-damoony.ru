<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Модуль со страницами: сам разрешает URL-остаток в PageRoute.
 */
interface PageModuleInterface
{
    /** Путь относительно корня проекта, где лежат страницы модуля */
    public function pagesRoot(): string;

    /** Namespace классов страниц модуля */
    public function pagesNamespace(): string;

    /**
     * @param string $remainder остаток URL после mount (начинается с /)
     */
    public function resolvePage(string $remainder): ?PageRoute;
}
