<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

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

        return AicliResult::fromJson(
            $this->client->runJson(
                'page:list --domain=' . escapeshellarg($domain)
                . ' --allias=' . escapeshellarg($allias),
            ),
        );
    }
}
