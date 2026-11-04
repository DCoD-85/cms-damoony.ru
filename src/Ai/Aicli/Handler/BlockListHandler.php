<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class BlockListHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['block:list'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $this->validateDomain($domain);

        return new AicliResult(
            ok: true,
            data: $this->client->runJson('block:list --domain=' . escapeshellarg($domain)),
        );
    }
}
