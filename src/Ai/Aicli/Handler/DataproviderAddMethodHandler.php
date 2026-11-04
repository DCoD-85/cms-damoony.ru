<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class DataproviderAddMethodHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['dataprovider:add-method'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $alias = $this->stringArg($args, 'alias', 'www');
        $provider = $this->stringArg($args, 'provider');
        $template = $this->stringArg($args, 'template');
        $templateArgs = $args['args'] ?? [];

        // Fallback: некоторые LLM кладут поля шаблона прямо в args (без вложенности)
        if (!is_array($templateArgs) || $templateArgs === []) {
            $templateArgs = $args;
            unset($templateArgs['domain'], $templateArgs['alias'], $templateArgs['provider'], $templateArgs['template']);
        }

        $argsB64 = base64_encode((string) json_encode($templateArgs, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromJson($this->client->runJson(
            'dataprovider:add-method --domain=' . escapeshellarg($domain)
            . ' --alias=' . escapeshellarg($alias)
            . ' --provider=' . escapeshellarg($provider)
            . ' --template=' . escapeshellarg($template)
            . ' --args_b64=' . escapeshellarg($argsB64),
        ));
    }
}
