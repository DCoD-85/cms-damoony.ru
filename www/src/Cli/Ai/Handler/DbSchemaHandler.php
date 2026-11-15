<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class DbSchemaHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['db:schema'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $alias = $this->stringArg($args, 'alias', 'www');
        $this->validateDomain($domain);
        $this->validateAllias($alias);

        $json = $this->client->runJson(
            'db:schema --domain=' . escapeshellarg($domain)
            . ' --alias=' . escapeshellarg($alias),
        );

        return AicliResult::fromJson($json);
    }
}
