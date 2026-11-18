<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Модуль, который умеет принимать заход на IP/корень.
 */
interface EntryPointInterface
{
    /**
     * @return string|null путь для редиректа, например /app/admin/superadmin/
     */
    public function entryPathForHost(string $host): ?string;
}
