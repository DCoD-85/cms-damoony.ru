<?php

declare(strict_types=1);

namespace Damoony\Http;

final class Cookie
{
    /** @var 'Lax'|'lax'|'None'|'none'|'Strict'|'strict' */
    private readonly string $sameSite;

    public function __construct(
        private readonly bool $secure = true,
        private readonly string $path = '/',
        string $sameSite = 'Lax',
        private readonly bool $httpOnly = true,
    ) {
        $this->sameSite = match ($sameSite) {
            'Lax' => 'Lax',
            'lax' => 'lax',
            'None' => 'None',
            'none' => 'none',
            'Strict' => 'Strict',
            'strict' => 'strict',
            default => 'Lax',
        };
    }

    public function set(string $name, string $value, int $expires = 0, ?string $domain = null): bool
    {
        if (headers_sent()) {
            return false;
        }

        return setcookie($name, $value, [
            'expires' => $expires,
            'path' => $this->path,
            'domain' => $domain ?? '',
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ]);
    }

    public function delete(string $name, ?string $domain = null): bool
    {
        return $this->set($name, '', time() - 3600, $domain);
    }

    public function get(string $name): ?string
    {
        $value = $_COOKIE[$name] ?? null;
        return is_string($value) ? $value : null;
    }
}