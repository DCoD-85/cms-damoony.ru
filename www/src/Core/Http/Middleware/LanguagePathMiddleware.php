<?php

declare(strict_types=1);

namespace Damoony\Core\Http\Middleware;

use Damoony\Core\Http\MiddlewareInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;

final class LanguagePathMiddleware implements MiddlewareInterface
{
    public function __construct()
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $path = $request->path;
        if ($path === '' || $path === '/') {
            return $next($request);
        }

        $trimmed = ltrim($path, '/');
        $parts = explode('/', $trimmed, 2);
        if (preg_match('/^[a-z]{2}$/', $parts[0]) !== 1) {
            return $next($request);
        }

        $stripped = '/' . ($parts[1] ?? '');

        return $next($stripped === $path ? $request : $request->withPath($stripped));
    }
}
