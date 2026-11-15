<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

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
