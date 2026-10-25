<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\I18n\LanguageNegotiator;

final class LanguagePathMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly LanguageNegotiator $negotiator)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $stripped = $this->negotiator->stripLangPrefix($request->path);

        return $next($stripped === $request->path ? $request : $request->withPath($stripped));
    }
}