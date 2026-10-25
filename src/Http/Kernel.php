<?php

declare(strict_types=1);

namespace Damoony\Http;

use Damoony\Core\Env;
use Psr\Log\LoggerInterface;
use Throwable;

final class Kernel implements KernelInterface
{
    /** @param callable(Request): Response $handler */
    public function __construct(
        private readonly mixed $handler,
        private readonly LoggerInterface $logger,
        private readonly Env $env,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = ($this->handler)($request);
            if (!$response instanceof Response) {
                $this->logger->error('Handler returned non-Response', ['type' => get_debug_type($response)]);
                return Response::internalError();
            }

            return $response;
        } catch (Throwable $e) {
            $this->logger->error('Kernel caught exception: ' . $e->getMessage(), [
                'class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if ($this->env->isDev()) {
                return Response::text(
                    $e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(),
                    500,
                );
            }

            return Response::internalError();
        }
    }
}