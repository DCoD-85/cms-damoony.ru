<?php

declare(strict_types=1);

namespace Damoony\Ai\Aicli\Handler;

use Damoony\Ai\Aicli\AbstractHandler;
use Damoony\Ai\Aicli\AicliResult;

final class EngineStatusHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['engine_status'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $r = $this->client->run('structure:check');

        return new AicliResult(
            ok: $r['ok'],
            data: [
                'status' => $r['ok'] ? 'OK' : 'FAILED',
                'output' => $r['output'],
            ],
            error: $r['ok'] ? '' : 'Structure check failed',
        );
    }
}
