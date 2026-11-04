<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class BlockNewHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['block:new'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $name = $this->stringArg($args, 'name');
        $type = $this->stringArg($args, 'type', 'visual');
        $this->requireDomain($domain);
        $this->validateName($name, 'block name');
        if (!in_array($type, ['script', 'visual', 'mixed'], true)) {
            throw new \RuntimeException('Invalid block type (script, visual, mixed).');
        }

        return AicliResult::fromCli($this->client->run(
            'block:new --domain=' . escapeshellarg($domain)
            . ' --name=' . escapeshellarg($name)
            . ' --type=' . escapeshellarg($type),
        ));
    }
}
