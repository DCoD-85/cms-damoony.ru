<?php

declare(strict_types=1);

namespace Damoony\Core\Http\Middleware;

use Damoony\Core\Http\MiddlewareInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $isHttps = false,
        private readonly string $csp = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'",
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        // Базовые заголовки безопасности
        $response = $response
            ->withHeader('Content-Security-Policy', $this->csp)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // HSTS (Только для HTTPS, чтобы не ломать локальную разработку)
        if ($this->isHttps) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
