<?php

declare(strict_types=1);

namespace Damoony\Core;

use Damoony\Core\Observability\RequestId;
use ErrorException;
use Psr\Log\LoggerInterface;
use Throwable;

final class ErrorHandler
{
    private bool $registered = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Env $env,
    ) {
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));

        $this->registered = true;
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        $requestId = RequestId::get();

        $this->logger->error('Uncaught exception: ' . $e->getMessage(), [
            'request_id' => $requestId,
            'class' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $this->render($e, $requestId);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatal, true)) {
            return;
        }

        $requestId = RequestId::get();

        $this->logger->critical('Fatal error: ' . $error['message'], [
            'request_id' => $requestId,
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        if (!headers_sent()) {
            http_response_code(500);
            header('X-Request-Id: ' . $requestId);
            if ($this->env->isDev()) {
                echo '<h1>Fatal error</h1><pre>'
                    . htmlspecialchars($error['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . "\n" . htmlspecialchars($error['file'] . ':' . $error['line'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</pre>';
            } else {
                echo 'Internal Server Error<br>Request ID: ' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');
            }
        }
    }

    private function render(Throwable $e, string $requestId): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('X-Request-Id: ' . $requestId);
        header('Content-Type: text/html; charset=utf-8');

        if ($this->env->isDev()) {
            $class = htmlspecialchars($e::class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $file = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rid = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');

            echo "<h1>{$class}</h1><p>{$message}</p><p><code>{$file}</code></p>"
                . "<p>Request ID: {$rid}</p><pre>{$trace}</pre>";

            return;
        }

        echo 'Internal Server Error<br>Request ID: ' . htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');
    }
}
