<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

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
