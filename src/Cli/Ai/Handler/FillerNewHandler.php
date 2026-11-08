<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

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
