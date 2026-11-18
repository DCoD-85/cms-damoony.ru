<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Результат сопоставления URL с mount-точкой модуля.
 */
final class ModuleMatch
{
    public function __construct(
        public readonly ModuleInterface $module,
        public readonly string $remainder,
    ) {
    }
}
