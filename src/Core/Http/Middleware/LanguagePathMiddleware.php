<?php

declare(strict_types=1);

namespace Damoony\Core\Http\Middleware;

use Damoony\Core\Http\MiddlewareInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\I18n\LanguageNegotiator;

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
