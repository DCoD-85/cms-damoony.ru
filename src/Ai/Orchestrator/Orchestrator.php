<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

use Damoony\Ai\Orchestrator\Agent\ArchitectAgentInterface;

final class Orchestrator
{
    public function __construct(
        private readonly ContextManager $context,
        private readonly ToolsCollectorInterface $tools,
        private readonly ArchitectAgentInterface $architect,
    ) {
    }

    /**
     * Запускает оркестрацию для сессии.
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

        // 1. Сбор инструментов (БД, структура)
        $toolCtx = $this->tools->collect($realDomain, $alias);
        $ctx['tools_context'] = $toolCtx;
        $ctx['meta']['status'] = $toolCtx['errors'] === [] ? 'tools_collected' : 'tools_failed';
        $this->context->save($ctx);

        // 2. Запуск Архитектора
        if ($toolCtx['errors'] === []) {
            try {
                $plan = $this->architect->execute($ctx);
                $this->context->updateAgentOutput('architect', $plan);
                
                // Обновляем статус
                $ctx = $this->context->read();
                $ctx['meta']['status'] = 'architect_done';
                $this->context->save($ctx);
            } catch (\Throwable $e) {
                $ctx['meta']['status'] = 'architect_failed';
                $ctx['meta']['errors'][] = $e->getMessage();
                $this->context->save($ctx);
                error_log('[Orchestrator] Architect failed: ' . $e->getMessage());
                // Не бросаем исключение, чтобы старый пайплайн AIUI продолжил работать (dry-run)
            }
        }

        return $ctx;
    }
}
