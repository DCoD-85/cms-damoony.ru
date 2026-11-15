<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class SchemeListHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['scheme:list'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $this->validateDomain($domain);

        return AicliResult::fromJson(
            $this->client->runJson('scheme:list --domain=' . escapeshellarg($domain)),
        );
    }
}
