<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class PageNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['page:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $name = $this->stringArg($args, 'name');
        $this->requireDomain($domain);
        $this->validateAllias($allias);
        $this->validateName($name, 'page name');

        return AicliResult::fromCli($this->client->run(
            'page:new --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name),
        ));
    }
}
