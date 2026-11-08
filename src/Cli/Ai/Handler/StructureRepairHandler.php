<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class StructureRepairHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['structure:repair'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        if ($domain !== '') {
            $this->validateDomain($domain);
        }
        $argsStr = $domain !== '' ? ' --domain=' . escapeshellarg($domain) : '';

        return AicliResult::fromCli($this->client->run('structure:repair' . $argsStr));
    }
}
