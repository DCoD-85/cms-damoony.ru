<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;

/**
 * Модуль может перехватить запрос до страниц (auth/redirect/deny).
 */
interface RequestGuardInterface
{
    public function guard(Request $request, string $remainder): ?Response;
}
