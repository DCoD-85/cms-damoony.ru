<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class DbMutateHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['db:mutate'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $domain = $this->stringArg($args, 'domain');
        $alias = $this->stringArg($args, 'alias', 'www');
        $provider = $this->stringArg($args, 'provider');
        $method = $this->stringArg($args, 'method');

        $this->validateDomain($domain);
        $this->validateAllias($alias);

        $params = $args['params'] ?? [];
        if (!is_array($params)) {
            $params = [];
        }
        $paramsB64 = base64_encode((string) json_encode($params, JSON_UNESCAPED_UNICODE));

        return AicliResult::fromJson($this->client->runJson(
            'db:mutate --domain=' . escapeshellarg($domain)
            . ' --alias=' . escapeshellarg($alias)
            . ' --provider=' . escapeshellarg($provider)
            . ' --method=' . escapeshellarg($method)
            . ' --params_b64=' . escapeshellarg($paramsB64)
            . ' --force=1'
            . ' --source=ai'
            . ' --role=' . escapeshellarg($role),
        ));
    }
}
