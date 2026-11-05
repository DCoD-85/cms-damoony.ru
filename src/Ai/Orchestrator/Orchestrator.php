<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

final class Orchestrator
{
    public function __construct(
        private readonly ContextManager $context,
        private readonly ToolsCollectorInterface $tools,
    ) {
    }

    /**
     * Запускает оркестрацию для сессии.
     *
     * Шаг 4.1: собирает tools_context (structure:check + db:schema).
     * Вызов агентов — следующий шаг.
     *
     * @return array<string, mixed> Финальный контекст
     */
    public function run(
        string $sessionId,
        string $realDomain,
        string $alias,
        string $prompt,
        string $targetBlock = 'all',
    ): array {
        $this->context->init($prompt, $targetBlock);
        $ctx = $this->context->read();

        $toolCtx = $this->tools->collect($realDomain, $alias);
        $ctx['tools_context'] = $toolCtx;
        $ctx['meta']['status'] = $toolCtx['errors'] === [] ? 'tools_collected' : 'tools_failed';

        $this->context->save($ctx);
        return $ctx;
    }
}
