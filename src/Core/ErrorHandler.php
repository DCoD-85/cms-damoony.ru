<?php

declare(strict_types=1);

namespace Damoony\Core;

use Damoony\Core\Observability\RequestId;
use Damoony\UI\Builder;
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
                echo $this->devFatal($error['message'], $error['file'] . ':' . $error['line'], $requestId);
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo "Internal Server Error\nRequest ID: {$requestId}";
            }
        }
    }

    private function devFatal(string $message, string $location, string $requestId): string
    {
        try {
            $wrap = Builder::div('error error-500');
            $wrap->append(Builder::h(1, 'error-head')->content('Fatal error'));
            $wrap->append(Builder::el('pre')->content($message . "\n" . $location));
            $wrap->append(Builder::p()->content('Request ID: ' . $requestId));

            return $wrap->release();
        } catch (Throwable) {
            return 'Fatal error';
        }
    }

    private function render(Throwable $e, string $requestId): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('X-Request-Id: ' . $requestId);

        if ($this->env->isDev()) {
            header('Content-Type: text/html; charset=utf-8');
            echo $this->devException($e, $requestId);

            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "Internal Server Error\nRequest ID: {$requestId}";
    }

    private function devException(Throwable $e, string $requestId): string
    {
        try {
            $wrap = Builder::div('error error-500');
            $wrap->append(Builder::h(1, 'error-head')->content($e::class));
            $wrap->append(Builder::p('error-message')->content($e->getMessage()));
            $wrap->append(Builder::el('p')->append(
                Builder::el('code')->content($e->getFile() . ':' . $e->getLine()),
            ));
            $wrap->append(Builder::p()->content('Request ID: ' . $requestId));
            $wrap->append(Builder::el('pre')->content($e->getTraceAsString()));

            return $wrap->release();
        } catch (Throwable) {
            return 'Internal Server Error';
        }
    }
}
