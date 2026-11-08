<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class SchemeNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['scheme:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $name = $this->stringArg($args, 'name');
        $this->requireDomain($domain);
        $this->validateName($name, 'scheme name');

        return AicliResult::fromCli($this->client->run(
            'scheme:new --domain=' . escapeshellarg($domain)
            . ' --name=' . escapeshellarg($name),
        ));
    }
}
