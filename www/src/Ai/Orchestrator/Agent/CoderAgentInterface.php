<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator\Agent;

interface CoderAgentInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function execute(array $context): array;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $pagePlan
     *
     * @return array<string, mixed>
     */
    public function executeForPage(array $context, array $pagePlan): array;
}
