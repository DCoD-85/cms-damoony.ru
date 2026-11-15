<?php

declare(strict_types=1);

namespace Damoony\Ainet;

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

    public function get(string $url): HttpResponse
    {
        return $this->request($url, 'GET');
    }

    public function head(string $url): HttpResponse
    {
        return $this->request($url, 'HEAD');
    }

    private function request(string $url, string $method): HttpResponse
    {
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= $this->maxRedirects; $redirect++) {
            $this->guard->validate($currentUrl);

            $raw = $this->transport->get($currentUrl, [
                'method' => $method,
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

    private function resolveRedirect(string $baseUrl, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location) === 1) {
            return $location;
        }

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

        if (str_starts_with($location, '//')) {
            return $scheme . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $dir = substr($path, 0, (int) strrpos($path, '/')) . '/';

        return $scheme . '://' . $host . $port . $dir . $location;
    }
}
