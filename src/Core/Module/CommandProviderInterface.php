<?php

declare(strict_types=1);

namespace Damoony\Core\Module;

use Damoony\Cli\CommandInterface;

/**
 * Модуль, который добавляет команды в единый CLI.
 */
interface CommandProviderInterface
{
    /** @return list<CommandInterface> */
    public function commands(): array;
}
