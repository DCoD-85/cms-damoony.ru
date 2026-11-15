<?php

declare(strict_types=1);

namespace Damoony\Core\Http\Middleware;

use Damoony\Core\Http\MiddlewareInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;

final class JsonContentTypeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if ($request->isAjax()) {
            return $response->withHeader('content-type', 'application/json; charset=utf-8');
        }

        return $response;
    }
}
