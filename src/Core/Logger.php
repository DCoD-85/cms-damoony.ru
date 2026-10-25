<?php

declare(strict_types=1);

namespace Damoony\Core;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

final class Logger extends AbstractLogger
{
    /** @var resource */
    private $stream;

    /** @param resource|null $stream */
    public function __construct(mixed $stream = null, private readonly Env $env = Env::Prod)
    {
        $this->stream = $stream ?? fopen('php://stderr', 'wb');
        if (!is_resource($this->stream)) {
            throw new \RuntimeException('Logger stream is not writable');
        }
    }

    /**
     * @param mixed[] $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $record = [
            'ts' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
            'env' => $this->env->value,
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = json_encode([
                'ts' => $record['ts'],
                'env' => $record['env'],
                'level' => $record['level'],
                'message' => $record['message'],
                'context' => ['_encode_error' => json_last_error_msg()],
            ]) ?: '{}';
        }

        fwrite($this->stream, $line . "\n");
    }

    /** @param mixed[] $context */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /** @param mixed[] $context */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /** @param mixed[] $context */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /** @param mixed[] $context */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /** @param mixed[] $context */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /** @param mixed[] $context */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /** @param mixed[] $context */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /** @param mixed[] $context */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}