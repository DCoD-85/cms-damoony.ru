<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class BlockWriteHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['block:write'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $name = $this->stringArg($args, 'name');
        $this->requireDomain($domain);
        $this->validateName($name, 'block name');

        $blocks = $this->arrayArg($args, 'blocks');
        $encoded = base64_encode((string) json_encode($blocks, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromCli($this->client->run(
            'block:write --domain=' . escapeshellarg($domain)
            . ' --name=' . escapeshellarg($name)
            . ' --json_b64=' . escapeshellarg($encoded),
        ));
    }
}
