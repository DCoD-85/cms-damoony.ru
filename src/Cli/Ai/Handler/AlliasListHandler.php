<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class AlliasListHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['allias:list'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $this->validateDomain($domain);

        return AicliResult::fromJson(
            $this->client->runJson('allias:list --domain=' . escapeshellarg($domain)),
        );
    }
}
