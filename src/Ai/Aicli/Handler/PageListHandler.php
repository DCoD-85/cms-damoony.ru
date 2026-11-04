<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class PageListHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['page:list'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $this->validateDomain($domain);
        $this->validateAllias($allias);

        return new AicliResult(
            ok: true,
            data: $this->client->runJson(
                'page:list --domain=' . escapeshellarg($domain)
                . ' --allias=' . escapeshellarg($allias),
            ),
        );
    }
}
