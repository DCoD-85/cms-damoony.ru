<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * HTTP-клиент с SSRF-защитой и ручными редиректами.
 *
 * - UrlGuard::validate() — ДО запроса
 * - CURLOPT_FOLLOWLOCATION=false — редиректы вручную
 * - Каждый Location → повторная UrlGuard::validate()
 * - max_redirects — лимит
 * - Относительные Location → резолвинг через базовый URL
 */
final class HttpClient
{
    public function __construct(
        private readonly UrlGuard $guard,
        private readonly HttpTransportInterface $transport,
        private readonly int $connectTimeout = 5,
        private readonly int $readTimeout = 10,
        private readonly int $maxSize = 10485760,
        private readonly int $maxRedirects = 3,
        private readonly string $userAgent = 'Damoony-AINET/1.0',
    ) {
    }

    /**
     * @throws UrlGuardException
     * @throws HttpTransportException
     * @throws HttpClientException
     */
    public function get(string $url): HttpResponse
    {
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= $this->maxRedirects; $redirect++) {
            // Валидация URL (включая после редиректа)
            $this->guard->validate($currentUrl);

            $raw = $this->transport->get($currentUrl, [
                'connect_timeout' => $this->connectTimeout,
                'read_timeout' => $this->readTimeout,
                'max_size' => $this->maxSize,
                'user_agent' => $this->userAgent,
            ]);

            $response = new HttpResponse(
                status: (int) $raw['status'],
                headers: (array) $raw['headers'],
                body: (string) $raw['body'],
                truncated: (bool) $raw['truncated'],
            );

            if (!$response->isRedirect()) {
                return $response;
            }

            $location = $response->location();
            if ($location === null) {
                return $response;
            }

            $currentUrl = $this->resolveRedirect($currentUrl, $location);
        }

        throw new HttpClientException(
            "Too many redirects (max {$this->maxRedirects})",
            'Reduce redirect chain or increase limits.max_redirects',
        );
    }

    /**
     * Резолвинг Location: абсолютный или относительный.
     * RFC 3986: https://www.rfc-editor.org/rfc/rfc3986#section-5.2
     */
    private function resolveRedirect(string $baseUrl, string $location): string
    {
        // Абсолютный URL
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

        // Относительный — нужен базовый URL
        $base = parse_url($baseUrl);
        if ($base === false || !isset($base['scheme'], $base['host'])) {
            throw new HttpClientException(
                'Cannot resolve relative redirect: invalid base URL',
                'Base URL must be absolute',
            );
        }

        $scheme = (string) $base['scheme'];
        $host = (string) $base['host'];
        $port = isset($base['port']) ? ':' . $base['port'] : '';
        $path = (string) ($base['path'] ?? '/');

        // //example.com/path — protocol-relative
        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        // /path — от корня
        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        // path — относительно текущей директории
        $dir = substr($path, 0, (int) strrpos($path, '/')) . '/';

        return $scheme . '://' . $host . $port . $dir . $location;
    }
}
