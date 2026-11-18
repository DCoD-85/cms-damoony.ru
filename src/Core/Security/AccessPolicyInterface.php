<?php

declare(strict_types=1);

namespace Damoony\Core\Security;

use Damoony\Core\Http\Request;

/**
 * Политика доступа: ядро только спрашивает «можно?», решение принимает модуль.
 * Отсутствие политики для защищённой зоны = deny (fail-closed).
 */
interface AccessPolicyInterface
{
    public function allows(Request $request): bool;
}
