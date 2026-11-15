<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Общее исключение AINET. Оборачивает UrlGuardException, HttpClientException,
 * ProviderException и ошибки валидации.
 */
final class AinetException extends \RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        public readonly string $hint = '',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $context */
    public static function wrap(\Throwable $e, array $context = []): self
    {
        $hint = '';
        if ($e instanceof UrlGuardException
            || $e instanceof HttpClientException
            || $e instanceof \Damoony\Ainet\ImageSearch\ProviderException) {
            $hint = $e->hint;
        }

        return new self($e->getMessage(), $hint, $context);
    }
}
