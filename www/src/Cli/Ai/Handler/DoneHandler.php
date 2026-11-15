<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class DoneHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['done'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        return AicliResult::ok(['done' => true]);
    }
}
