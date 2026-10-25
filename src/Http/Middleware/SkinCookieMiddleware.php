<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;

final class SkinCookieMiddleware implements MiddlewareInterface
{
    /** @param list<string> $allowed */
    public function __construct(
        private readonly string $cookieName = 'skin',
        private readonly array $allowed = ['default', 'dark'],
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $skin = $request->query('skin');
        if (!is_string($skin) || !in_array($skin, $this->allowed, true)) {
            return $next($request);
        }

        if (headers_sent()) {
            return $next($request);
        }

        setcookie($this->cookieName, $skin, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $target = $request->query('return') ?? $request->path;
        $query = $request->query;
        unset($query['skin'], $query['return']);
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        return Response::redirect((string) $target, 307);
    }
}