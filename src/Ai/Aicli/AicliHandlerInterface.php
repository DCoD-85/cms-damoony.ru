<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli;

/**
 * Контракт обработчика AI-команд.
 *
 * Каждый handler декларирует команды, которые он обрабатывает,
 * и возвращает AicliResult (без role/next — их добавит ResponseBuilder).
 */
interface AicliHandlerInterface
{
    /**
     * Команды, которые обрабатывает handler.
     *
     * Формат: точные имена ('page:new') или prefix ('wizard:*').
     *
     * @return list<string>
     */
    public function commands(): array;

    /**
     * @param array<string, mixed> $args
     */
    public function handle(string $command, array $args, string $role): AicliResult;
}
