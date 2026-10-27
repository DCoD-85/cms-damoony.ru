<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;

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
