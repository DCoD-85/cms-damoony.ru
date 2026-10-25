<?php

declare(strict_types=1);

namespace Damoony\Http;

final class Request
{
    /**
     * @param array<string, string>  $query
     * @param array<string, mixed>   $post
     * @param array<string, string>  $headers
     * @param array<string, mixed>   $cookies
     * @param array<string, mixed>   $files
     * @param array<string, string>  $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($path === '') {
            $path = '/';
        }

        return new self(
            method: $method,
            path: $path,
            query: $_GET,
            post: $_POST,
            headers: self::collectHeaders($_SERVER),
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER,
        );
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function collectHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($server['CONTENT_TYPE']) && is_string($server['CONTENT_TYPE'])) {
            $headers['content-type'] = $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH']) && is_string($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $server['CONTENT_LENGTH'];
        }

        return $headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with')) === 'xmlhttprequest';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return is_string($https) && strtolower($https) === 'on';
    }
}