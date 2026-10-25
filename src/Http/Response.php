<?php

declare(strict_types=1);

namespace Damoony\Http;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = ['content-type' => 'text/html; charset=utf-8'],
    ) {
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, ['content-type' => 'text/html; charset=utf-8'] + $headers);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{}';
        }

        return new self($body, $status, ['content-type' => 'application/json; charset=utf-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['content-type' => 'text/plain; charset=utf-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['location' => $location]);
    }

    public static function notFound(string $body = 'Not Found'): self
    {
        return self::text($body, 404);
    }

    public static function internalError(string $body = 'Internal Server Error'): self
    {
        return self::text($body, 500);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[strtolower($name)] = $value;

        return new self($this->body, $this->status, $headers);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }
}