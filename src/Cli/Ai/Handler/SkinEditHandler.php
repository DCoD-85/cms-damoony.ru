<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class SkinEditHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['skin:edit'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $name = $this->stringArg($args, 'name');
        $file = $this->stringArg($args, 'file');
        $content = $this->stringArg($args, 'content');
        $contentB64 = $this->stringArg($args, 'content_b64');

        $this->requireDomain($domain);
        $this->validateAllias($allias);
        $this->validateName($name, 'skin name');
        if (!preg_match('/^(css\/[a-z0-9_]+\.css|js\/main\.js)$/', $file)) {
            throw new \RuntimeException('Invalid file (allowed: css/*.css, js/main.js).');
        }

        $contentArg = $contentB64 !== ''
            ? ' --content_b64=' . escapeshellarg($contentB64)
            : ' --content=' . escapeshellarg($content);

        return AicliResult::fromCli($this->client->run(
            'skin:edit --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($name)
            . ' --file=' . escapeshellarg($file)
            . $contentArg,
        ));
    }
}
