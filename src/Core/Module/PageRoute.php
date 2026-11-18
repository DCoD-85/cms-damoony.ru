<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Разрешённый маршрут страниц модуля.
 *
 * Модуль сам решает, где лежат его страницы и какой у них namespace;
 * ядро только использует результат.
 */
final class PageRoute
{
    public function __construct(
        /** Путь относительно корня проекта, например app/.admin/superadmin */
        public readonly string $pagesRoot,
        /** Namespace классов страниц, например Damoony\Admin\Pages\superadmin */
        public readonly string $namespace,
        /** Путь внутри mount, например / или /users/ */
        public readonly string $path,
        /** Роль/контекст модуля (для i18n и URL), может быть null */
        public readonly ?string $role = null,
        /** Корень схем, например app/.admin */
        public readonly string $schemeRoot = '',
        /** Имя схемы, например admin */
        public readonly string $schemeName = 'admin',
    ) {
    }
}
