<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

/**
 * Минимальный контракт модуля. Версия API — целое число, увеличивается
 * только при несовместимых изменениях контракта.
 */
interface ModuleInterface
{
    public const API_VERSION = 1;

    public function id(): string;

    public function apiVersion(): int;

    /**
     * Префикс URL, который обслуживает модуль (например /app/admin).
     * null — модуль не монтирует маршруты.
     */
    public function mount(): ?string;
}
