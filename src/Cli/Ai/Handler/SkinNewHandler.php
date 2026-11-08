<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class SkinNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['skin:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $name = $this->stringArg($args, 'name', 'default');
        $this->requireDomain($domain);
        $this->validateAllias($allias);
        $this->validateName($name, 'skin name');

        return AicliResult::fromCli($this->client->run(
            'skin:new --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name),
        ));
    }
}
