<?php

declare(strict_types=1);

namespace Damoony\Core\Http\Middleware;

use Damoony\Core\Http\MiddlewareInterface;
use Damoony\Core\Http\Request;
use Damoony\Core\Http\Response;
use Damoony\Core\Session\SessionInterface;

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
        if (!is_string($lang) || preg_match('/^[a-z]{2}$/', $lang) !== 1) {
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
