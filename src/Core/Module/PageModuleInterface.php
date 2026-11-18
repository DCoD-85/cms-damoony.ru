<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Модуль со страницами: где лежат файлы и какой namespace у классов.
 */
interface PageModuleInterface
{
    /** Путь относительно корня проекта, например app/.admin */
    public function pagesRoot(): string;

    /** Namespace классов страниц, например Damoony\Admin\Pages */
    public function pagesNamespace(): string;
}
