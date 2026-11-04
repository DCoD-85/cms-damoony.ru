<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class FillerNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['filler:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $block = $this->stringArg($args, 'block');
        $this->requireDomain($domain);
        $this->validateAllias($allias);
        $this->validateName($block, 'block name');

        return AicliResult::fromCli($this->client->run(
            'filler:new --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --block=' . escapeshellarg($block),
        ));
    }
}
