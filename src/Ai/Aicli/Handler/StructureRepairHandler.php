<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

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
