<?php

declare(strict_types=1);

namespace Damoony\Ai\Orchestrator;

interface ToolsCollectorInterface
{
    /**
     * @return array{
     *     structure_ok: bool,
     *     structure_errors: list<string>,
     *     db_tables: list<string>,
     *     errors: list<string>
     * }
     */
    public function collect(string $domain, string $alias): array;
}
