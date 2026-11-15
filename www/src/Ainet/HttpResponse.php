<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Иммутабельный HTTP-ответ. Заголовки — lowercase ключи.
 */
final class HttpResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly bool $truncated = false,
    ) {
    }

    public function mime(): string
    {
        $ct = $this->headers['content-type'] ?? '';
        if ($ct === '') {
            return '';
        }
        $semicolon = strpos($ct, ';');

        return $semicolon === false ? trim($ct) : trim(substr($ct, 0, $semicolon));
    }

    public function size(): int
    {
        return strlen($this->body);
    }

    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->location() !== null;
    }

    public function location(): ?string
    {
        $loc = $this->headers['location'] ?? '';

        return $loc === '' ? null : $loc;
    }
}
