<?php

declare(strict_types=1);

namespace Damoony\Http\Middleware;

use Damoony\Http\MiddlewareInterface;
use Damoony\Http\Request;
use Damoony\Http\Response;
use Damoony\I18n\LanguageNegotiator;
use Damoony\Session\SessionInterface;

final class LanguageCookieMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $cookieName = 'lang',
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $lang = $request->query('lang');
        if (!is_string($lang) || !in_array($lang, LanguageNegotiator::SUPPORTED, true)) {
            return $next($request);
        }

        if (!$this->session->isStarted()) {
            $this->session->start();
        }
        $this->session->set($this->cookieName, $lang);

        if (headers_sent()) {
            return $next($request);
        }

        setcookie($this->cookieName, $lang, [
            'expires' => time() + 86400 * 365,
            'path' => '/',
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $target = $request->query('return') ?? $request->path;
        $query = $request->query;
        unset($query['lang'], $query['return']);
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        return Response::redirect((string) $target, 307);
    }
}