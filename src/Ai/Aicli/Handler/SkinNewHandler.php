<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

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
