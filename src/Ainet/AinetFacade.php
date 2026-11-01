<?php

declare(strict_types=1);

namespace Damoony\Ainet;

use Damoony\Ainet\ImageSearch\ImageResult;
use Damoony\Ainet\ImageSearch\OpenverseProvider;
use Damoony\Ainet\ImageSearch\ProviderException;
use Damoony\Ainet\ImageSearch\ProviderInterface;
use Damoony\Ainet\ImageSearch\WikimediaProvider;

/**
 * Публичный API библиотеки AINET для AICLI.
 *
 * Все методы возвращают array в формате {ok, data} или {ok: false, error, hint, context}.
 * AinetFacade НЕ пишет на диск. downloadImage возвращает bytes + meta,
 * запись в www/media/ — через cli.php media:store (вызывает AICLI).
 */
final class AinetFacade
{
    /** @param array<string, ProviderInterface> $providers */
    public function __construct(
        private readonly AinetConfig $config,
        private readonly HttpClient $http,
        private readonly Cache $cache,
        private readonly array $providers,
    ) {
    }

    public static function createFromConfig(string $configFile, string $cacheDir): self
    {
        $config = AinetConfig::fromFile($configFile);
        $whitelist = new DomainWhitelist($config->allowedDomains);
        $guard = new UrlGuard(
            $whitelist,
            $config->allowedSchemes,
            $config->allowedPorts,
            $config->blockPrivateIps,
        );
        $http = new HttpClient(
            $guard,
            new CurlTransport(),
            $config->connectTimeout,
            $config->readTimeout,
            $config->maxSize,
            $config->maxRedirects,
        );
        $cache = new Cache($cacheDir, $config->cacheTtlDays * 86400);

        $providers = [
            'openverse' => new OpenverseProvider($http),
            'wikimedia' => new WikimediaProvider($http),
        ];

        return new self($config, $http, $cache, $providers);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $stats = $this->cache->stats();

        return [
            'ok' => true,
            'data' => [
                'providers' => array_keys($this->providers),
                'default_provider' => $this->config->defaultProvider,
                'whitelist_count' => count($this->config->allowedDomains),
                'cache_size_mb' => $stats['size_mb'],
                'cache_entries' => $stats['entries'],
                'rate_limit' => ['max' => $this->config->ratePerMin, 'window' => '1m'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function searchImages(string $query, int $count, ?string $providerName = null): array
    {
        $name = $providerName ?? $this->config->defaultProvider;
        if (!isset($this->providers[$name])) {
            return $this->error(
                "Unknown provider '{$name}'",
                'Available: ' . implode(', ', array_keys($this->providers)),
                ['provider' => $name],
            );
        }

        try {
            $results = $this->providers[$name]->search($query, $count);
        } catch (ProviderException $e) {
            return $this->error($e->getMessage(), $e->hint, ['provider' => $name]);
        }

        return [
            'ok' => true,
            'data' => [
                'provider' => $name,
                'query' => $query,
                'results' => array_map(
                    static fn (ImageResult $r): array => $r->toArray(),
                    $results,
                ),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function fetchJson(string $url): array
    {
        try {
            [$response, $cached] = $this->fetchWithCache($url);
        } catch (AinetException $e) {
            return $this->error($e->getMessage(), $e->hint, ['url' => $url]);
        }

        if (!$response->isSuccess()) {
            return $this->error(
                "HTTP {$response->status}",
                'Server returned error status',
                ['url' => $url, 'status' => $response->status],
            );
        }

        $decoded = json_decode($response->body, true);
        if (!is_array($decoded)) {
            return $this->error('Invalid JSON', 'Response body is not valid JSON', ['url' => $url]);
        }

        return [
            'ok' => true,
            'data' => [
                'json' => $decoded,
                'status' => $response->status,
                'cached' => $cached,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function fetchText(string $url): array
    {
        try {
            [$response, $cached] = $this->fetchWithCache($url);
        } catch (AinetException $e) {
            return $this->error($e->getMessage(), $e->hint, ['url' => $url]);
        }

        if (!$response->isSuccess()) {
            return $this->error(
                "HTTP {$response->status}",
                'Server returned error status',
                ['url' => $url, 'status' => $response->status],
            );
        }

        return [
            'ok' => true,
            'data' => [
                'text' => $response->body,
                'status' => $response->status,
                'size' => $response->size(),
                'cached' => $cached,
            ],
        ];
    }

    /**
     * Скачать картинку. НЕ пишет на диск — возвращает bytes + meta.
     * Запись в www/media/ — через cli.php media:store (в AICLI).
     */
    /** @return array<string, mixed> */
    public function downloadImage(
        string $url,
        string $domain,
        string $allias,
        string $category,
        string $alt = '',
    ): array {
        if (!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) || str_contains($domain, '..')) {
            return $this->error('Invalid domain', 'Must match FQDN (a-z0-9.-), no ".."', ['domain' => $domain]);
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $allias)) {
            return $this->error('Invalid allias', 'Must match [a-z][a-z0-9_]*', ['allias' => $allias]);
        }
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $category)) {
            return $this->error('Invalid category', 'Must match [a-z0-9_-]{1,32}', ['category' => $category]);
        }

        try {
            [$response, $cached] = $this->fetchWithCache($url);
        } catch (AinetException $e) {
            return $this->error($e->getMessage(), $e->hint, ['url' => $url]);
        }

        if (!$response->isSuccess()) {
            return $this->error(
                "HTTP {$response->status}",
                'Server returned error status',
                ['url' => $url, 'status' => $response->status],
            );
        }
        if ($response->truncated) {
            return $this->error(
                'Image too large',
                "Max size {$this->config->maxSize} bytes",
                ['url' => $url, 'size' => $response->size()],
            );
        }

        $mime = $response->mime();
        if (!in_array($mime, $this->config->allowedMimes, true)) {
            return $this->error(
                "Blocked MIME '{$mime}'",
                'Allowed: ' . implode(', ', $this->config->allowedMimes),
                ['url' => $url, 'mime' => $mime],
            );
        }

        $dims = $this->imageDimensions($response->body);
        if ($dims === null) {
            return $this->error('Cannot read image dimensions', 'Image may be corrupted or not a real image', ['url' => $url]);
        }
        [$width, $height] = $dims;
        $min = $this->config->minImageDimension;
        if ($width < $min || $height < $min) {
            return $this->error(
                "Image too small ({$width}x{$height})",
                "Minimum {$min}x{$min}",
                ['url' => $url, 'width' => $width, 'height' => $height],
            );
        }

        $hash = substr(hash('sha256', $response->body), 0, 16);
        $ext = $this->extFromMime($mime);

        $meta = [
            'source_url' => $url,
            'alt' => $alt,
            'size' => $response->size(),
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'downloaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        return [
            'ok' => true,
            'data' => [
                'bytes' => $response->body,   // НЕ сериализуется в JSON, используется AICLI для media:store
                'hash' => $hash,
                'ext' => $ext,
                'mime' => $mime,
                'width' => $width,
                'height' => $height,
                'size' => $response->size(),
                'cached' => $cached,
                'meta' => $meta,
            ],
        ];
    }

    /** @return array{0: HttpResponse, 1: bool} */
    private function fetchWithCache(string $url): array
    {
        $cached = $this->cache->get($url);
        if ($cached !== null) {
            return [$cached, true];
        }

        try {
            $response = $this->http->get($url);
        } catch (UrlGuardException|HttpClientException $e) {
            throw AinetException::wrap($e, ['url' => $url]);
        }

        if ($response->isSuccess() && !$response->truncated) {
            $this->cache->put($url, $response);
        }

        return [$response, false];
    }

    /** @return array{0: int, 1: int}|null */
    private function imageDimensions(string $bytes): ?array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function error(string $message, string $hint = '', array $context = []): array
    {
        return ['ok' => false, 'error' => $message, 'hint' => $hint, 'context' => $context];
    }
}
