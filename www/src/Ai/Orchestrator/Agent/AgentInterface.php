<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

interface AgentInterface
{
    /**
     * Выполняет задачу агента на основе контекста.
     * Возвращает обновленные данные для записи в context.json.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function execute(array $context): array;
}
