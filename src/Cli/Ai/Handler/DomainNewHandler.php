<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class DomainNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['domain:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $this->requireDomain($domain);

        return AicliResult::fromCli(
            $this->client->run('domain:new --domain=' . escapeshellarg($domain)),
        );
    }
}
