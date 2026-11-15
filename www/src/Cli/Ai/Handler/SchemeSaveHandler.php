<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class SchemeSaveHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['scheme:save'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $name = $this->stringArg($args, 'name');
        $this->requireDomain($domain);
        $this->validateName($name, 'scheme name');

        $schemeJson = $this->arrayArg($args, 'scheme_json');
        $encoded = base64_encode((string) json_encode($schemeJson, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromCli($this->client->run(
            'scheme:save --domain=' . escapeshellarg($domain)
            . ' --name=' . escapeshellarg($name)
            . ' --scheme_json_b64=' . escapeshellarg($encoded),
        ));
    }
}
