<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class StructureCheckHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['structure:check'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        return AicliResult::fromCli($this->client->run('structure:check'));
    }
}
