<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class DbDescribeHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['db:describe'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $provider = $this->stringArg($args, 'provider');
        $method = $this->stringArg($args, 'method');

        return AicliResult::fromJson($this->client->runJson(
            'db:describe --provider=' . escapeshellarg($provider)
            . ' --method=' . escapeshellarg($method),
        ));
    }
}
