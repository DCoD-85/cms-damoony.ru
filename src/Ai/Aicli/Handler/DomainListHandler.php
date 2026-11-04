<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class DomainListHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['domain:list'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        return AicliResult::fromJson($this->client->runJson('domain:list'));
    }
}
