<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly bool $isHttps = true,
        private readonly string $csp = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'",
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
            ->withHeader('Content-Security-Policy', $this->csp);

        if ($this->isHttps) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
