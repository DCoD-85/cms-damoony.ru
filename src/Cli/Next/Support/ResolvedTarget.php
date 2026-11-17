<?php

declare(strict_types=1);

namespace Damoony\Cli\Next\Support;

use Damoony\Core\Settings\AliasSettings;
use Damoony\Core\Settings\ProjectSettings;

/**
 * Результат резолва аргументов CLI: проект + его алиас.
 */
final class ResolvedTarget
{
    public function __construct(
        public readonly ProjectSettings $project,
        public readonly AliasSettings $alias,
    ) {
    }

    public function folder(): string
    {
        return $this->project->folder;
    }

    public function projectKey(): string
    {
        return $this->project->key;
    }

    public function aliasName(): string
    {
        return $this->alias->name;
    }
}
