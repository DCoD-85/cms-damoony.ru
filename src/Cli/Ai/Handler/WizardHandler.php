<?php

declare(strict_types=1);

namespace Damoony\Cli\Ai\Handler;

use Damoony\Ai\WizardManager;
use Damoony\Ai\WizardSession;
use Damoony\Cli\Ai\AbstractHandler;
use Damoony\Cli\Ai\AicliResult;
use Damoony\Core\Files\Paths;

final class WizardHandler extends AbstractHandler
{
    public function commands(): array
    {
        return ['wizard:*'];
    }

    public function handle(string $command, array $args, string $role): AicliResult
    {
        $action = substr($command, 7);
        $paths = new Paths($this->root);

        if ($action === 'start') {
            $session = WizardSession::create($paths);

            return AicliResult::ok([
                'session_id' => $session->id(),
                'stage' => $session->stage(),
                'next' => 'wizard:domain { session_id, domain }',
            ]);
        }

        $sessionId = $this->stringArg($args, 'session_id');
        $session = WizardSession::load($sessionId, $paths);

        if ($session === null) {
            return AicliResult::fail('Session not found or invalid ID');
        }

        $manager = new WizardManager(
            $paths,
            fn (string $a): array => $this->client->run($a),
            fn (string $a): array => $this->client->runJson($a),
        );

        $result = $manager->handle($session, $action, $args);

        return new AicliResult(
            ok: $result->ok,
            data: [
                'session_id' => $session->id(),
                'stage' => $session->stage(),
            ],
            error: implode('; ', $result->errors),
        );
    }
}
