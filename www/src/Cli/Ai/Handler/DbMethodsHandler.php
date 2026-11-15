<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class DbMethodsHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['db:methods'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $alias = $this->stringArg($args, 'alias', 'www');
        $provider = $this->stringArg($args, 'provider');
        $this->validateDomain($domain);
        $this->validateAllias($alias);

        return AicliResult::fromJson($this->client->runJson(
            'db:methods --domain=' . escapeshellarg($domain)
            . ' --alias=' . escapeshellarg($alias)
            . ' --provider=' . escapeshellarg($provider),
        ));
    }
}
