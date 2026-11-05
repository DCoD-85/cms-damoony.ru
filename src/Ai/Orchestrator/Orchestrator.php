<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

final class Orchestrator
{
    public function __construct(
        private readonly ContextManager $context,
    ) {
    }

    /**
     * Запускает оркестрацию для сессии.
     *
     * @return array<string, mixed> Финальный контекст
     */
    public function run(string $sessionId, string $realDomain, string $prompt, string $targetBlock = 'all'): array
    {
        $this->context->init($prompt, $targetBlock);
        $ctx = $this->context->read();

        // TODO (Шаг 2): tools_context -> Architect -> Writer -> Coder -> Critic
        $ctx['meta']['status'] = 'init';

        $this->context->save($ctx);
        return $ctx;
    }
}
