<?php

declare(strict_types=1);

namespace Damoony\Ainet\ImageSearch;

use Damoony\Ainet\HttpClient;
use Damoony\Ainet\HttpClientException;
use Damoony\Ainet\UrlGuardException;

/**
 * Openverse — агрегатор CC-картинок (WordPress).
 * Без API-ключа. Endpoint: https://api.openverse.engineering/v1/images/
 *
 * Поля ответа Openverse:
 *   results[].url, .thumbnail, .width, .height, .license, .creator, .title
 */
final class OpenverseProvider implements ProviderInterface
{
    private const ENDPOINT = 'https://api.openverse.engineering/v1/images/';

    public function __construct(
        private readonly HttpClient $http,
    ) {
    }

    public function name(): string
    {
        return 'openverse';
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
            'q' => $query,
            'page_size' => $count,
        ]);

        try {
            $response = $this->http->get($url);
        } catch (UrlGuardException|HttpClientException $e) {
            throw new ProviderException(
                "Openverse: {$e->getMessage()}",
                'Network or SSRF failure; check whitelist and connectivity',
                $this->name(),
            );
        }

        if (!$response->isSuccess()) {
            throw new ProviderException(
                "Openverse returned HTTP {$response->status}",
                'Provider unavailable or query rejected',
                $this->name(),
            );
        }

        $decoded = json_decode($response->body, true);
        if (!is_array($decoded) || !isset($decoded['results']) || !is_array($decoded['results'])) {
            throw new ProviderException(
                'Openverse: invalid JSON response',
                'Provider returned unexpected shape',
                $this->name(),
            );
        }

        $out = [];
        foreach ($decoded['results'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $image = $this->mapRow($row);
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
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row): ?ImageResult
    {
        $url = $row['url'] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }

        $preview = $row['thumbnail'] ?? $url;
        if (!is_string($preview) || $preview === '') {
            $preview = $url;
        }

        return new ImageResult(
            url: $url,
            previewUrl: $preview,
            width: (int) ($row['width'] ?? 0),
            height: (int) ($row['height'] ?? 0),
            license: (string) ($row['license'] ?? 'unknown'),
            author: (string) ($row['creator'] ?? ''),
            source: 'openverse',
            title: (string) ($row['title'] ?? ''),
        );
    }
}
