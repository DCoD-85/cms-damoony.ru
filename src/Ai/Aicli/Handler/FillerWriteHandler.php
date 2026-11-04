<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class FillerWriteHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['filler:write'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $block = $this->stringArg($args, 'block');
        $this->requireDomain($domain);
        $this->validateAllias($allias);
        $this->validateName($block, 'block name');

        $blocks = $this->arrayArg($args, 'blocks');
        $encoded = base64_encode((string) json_encode($blocks, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromCli($this->client->run(
            'filler:write --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --block=' . escapeshellarg($block)
            . ' --json_b64=' . escapeshellarg($encoded),
        ));
    }
}
