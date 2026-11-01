<?php

declare(strict_types=1);

namespace Damoony\Ainet\ImageSearch;

use Damoony\Ainet\HttpClient;
use Damoony\Ainet\HttpClientException;
use Damoony\Ainet\UrlGuardException;

/**
 * Wikimedia Commons — fallback-провайдер (без ключа).
 * Endpoint: https://commons.wikimedia.org/w/api.php
 *
 * Namespace 6 = File. Возвращает imageinfo с URL, размером, лицензией, автором.
 */
final class WikimediaProvider implements ProviderInterface
{
    private const ENDPOINT = 'https://commons.wikimedia.org/w/api.php';

    public function __construct(
        private readonly HttpClient $http,
    ) {
    }

    public function name(): string
    {
        return 'wikimedia';
    }

    public function requiresKey(): bool
    {
        return false;
    }

    public function search(string $query, int $count): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) > 100) {
            throw new ProviderException(
                'Query must be 1-100 chars',
                'Shorten or rephrase the query',
                $this->name(),
            );
        }
        $count = max(1, min(20, $count));

        $url = self::ENDPOINT . '?' . http_build_query([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'search',
            'gsrnamespace' => 6,
            'gsrsearch' => $query,
            'gsrlimit' => $count,
            'prop' => 'imageinfo',
            'iiprop' => 'url|size|extmetadata',
            'iiurlwidth' => 400,
        ]);

        try {
            $response = $this->http->get($url);
        } catch (UrlGuardException|HttpClientException $e) {
            throw new ProviderException(
                "Wikimedia: {$e->getMessage()}",
                'Network or SSRF failure; check whitelist and connectivity',
                $this->name(),
            );
        }

        if (!$response->isSuccess()) {
            throw new ProviderException(
                "Wikimedia returned HTTP {$response->status}",
                'Provider unavailable',
                $this->name(),
            );
        }

        $decoded = json_decode($response->body, true);
        if (!is_array($decoded)) {
            throw new ProviderException(
                'Wikimedia: invalid JSON',
                'Provider returned unexpected shape',
                $this->name(),
            );
        }

        $pages = $decoded['query']['pages'] ?? null;
        if (!is_array($pages)) {
            // Пустой результат — не ошибка, возвращаем []
            return [];
        }

        $out = [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $image = $this->mapPage($page);
            if ($image !== null) {
                $out[] = $image;
            }
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function mapPage(array $page): ?ImageResult
    {
        $info = $page['imageinfo'] ?? null;
        if (!is_array($info) || !isset($info[0]) || !is_array($info[0])) {
            return null;
        }
        $ii = $info[0];

        $url = $ii['url'] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }

        $preview = $ii['thumburl'] ?? $url;
        if (!is_string($preview) || $preview === '') {
            $preview = $url;
        }

        $meta = $ii['extmetadata'] ?? [];
        $license = 'unknown';
        $author = '';
        if (is_array($meta)) {
            if (isset($meta['LicenseShortName']['value']) && is_string($meta['LicenseShortName']['value'])) {
                $license = $meta['LicenseShortName']['value'];
            }
            if (isset($meta['Artist']['value']) && is_string($meta['Artist']['value'])) {
                $author = strip_tags($meta['Artist']['value']);
            }
        }

        return new ImageResult(
            url: $url,
            previewUrl: $preview,
            width: (int) ($ii['width'] ?? 0),
            height: (int) ($ii['height'] ?? 0),
            license: $license,
            author: $author,
            source: 'wikimedia',
            title: (string) ($page['title'] ?? ''),
        );
    }
}
