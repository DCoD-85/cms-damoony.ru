<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;

final class PageWriteHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['page:write'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $allias = $this->stringArg($args, 'allias');
        $name = $this->stringArg($args, 'name');
        $withSchema = $this->stringArg($args, 'with_schema', '0');
        $blocks = $this->arrayArg($args, 'blocks');

        $this->requireDomain($domain);
        $this->validateAllias($allias);
        if ($name !== '') {
            $this->validateName($name, 'page name');
        }

        // img-fix: local_url / src / url -> content (matches bin/aicli.php)
        foreach ($blocks as &$b) {
            if (!is_array($b)) {
                continue;
            }
            if (($b['type'] ?? '') === 'img' && (empty($b['content']) || !is_string($b['content']))) {
                foreach (['local_url', 'src', 'url'] as $k) {
                    $v = $b[$k] ?? null;
                    if (is_string($v) && $v !== '') {
                        $b['content'] = $v;
                        break;
                    }
                }
                unset($b['local_url'], $b['src']);
            }
        }
        unset($b);

        $cliName = ($name === '') ? '__root__' : $name;
        $encoded = base64_encode((string) json_encode($blocks, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromCli($this->client->run(
            'page:write --domain=' . escapeshellarg($domain)
            . ' --allias=' . escapeshellarg($allias)
            . ' --name=' . escapeshellarg($cliName)
            . ' --json_b64=' . escapeshellarg($encoded)
            . ' --with_schema=' . escapeshellarg($withSchema),
        ));
    }
}
