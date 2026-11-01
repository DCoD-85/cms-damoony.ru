<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * Конфиг AINET. Загружается из config/ainet.config (JSON).
 * Все поля имеют дефолты. Отсутствие файла = fail-safe (пустой whitelist).
 */
final class AinetConfig
{
    /**
     * @param list<string> $allowedDomains
     * @param list<string> $allowedSchemes
     * @param list<int> $allowedPorts
     * @param list<string> $allowedMimes
     */
    public function __construct(
        public readonly array $allowedDomains,
        public readonly string $defaultProvider,
        public readonly string $unsplashKey,
        public readonly string $pexelsKey,
        public readonly int $connectTimeout,
        public readonly int $readTimeout,
        public readonly int $maxSize,
        public readonly int $maxRedirects,
        public readonly int $ratePerMin,
        public readonly int $minImageDimension,
        public readonly int $cacheTtlDays,
        public readonly bool $blockPrivateIps,
        public readonly array $allowedSchemes,
        public readonly array $allowedPorts,
        public readonly array $allowedMimes,
    ) {
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            return self::defaults();
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return self::defaults();
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? self::fromArray($decoded) : self::defaults();
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $domains = $data['allowed_domains'] ?? [];
        if (!is_array($domains)) {
            $domains = [];
        }
        $domains = array_values(array_filter($domains, 'is_string'));

        $providers = is_array($data['providers'] ?? null) ? $data['providers'] : [];
        $keys = is_array($providers['keys'] ?? null) ? $providers['keys'] : [];
        $timeouts = is_array($data['timeouts'] ?? null) ? $data['timeouts'] : [];
        $limits = is_array($data['limits'] ?? null) ? $data['limits'] : [];

        $schemes = is_array($data['allowed_schemes'] ?? null) ? $data['allowed_schemes'] : ['http', 'https'];
        $schemes = array_values(array_filter($schemes, 'is_string'));

        $ports = is_array($data['allowed_ports'] ?? null) ? $data['allowed_ports'] : [80, 443];
        $ports = array_values(array_filter(array_map('intval', $ports), static fn (int $p): bool => $p > 0));

        $mimes = is_array($data['allowed_mimes'] ?? null) ? $data['allowed_mimes'] : [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        ];
        $mimes = array_values(array_filter($mimes, 'is_string'));

        return new self(
            allowedDomains: $domains,
            defaultProvider: (string) ($providers['default'] ?? 'openverse'),
            unsplashKey: (string) ($keys['unsplash'] ?? ''),
            pexelsKey: (string) ($keys['pexels'] ?? ''),
            connectTimeout: max(1, (int) ($timeouts['connect'] ?? 5)),
            readTimeout: max(1, (int) ($timeouts['read'] ?? 10)),
            maxSize: max(1024, (int) ($limits['max_size'] ?? 10485760)),
            maxRedirects: max(0, (int) ($limits['max_redirects'] ?? 3)),
            ratePerMin: max(1, (int) ($limits['rate_per_min'] ?? 30)),
            minImageDimension: max(0, (int) ($limits['min_image_dimension'] ?? 200)),
            cacheTtlDays: max(0, (int) ($data['cache_ttl_days'] ?? 7)),
            blockPrivateIps: (bool) ($data['block_private_ips'] ?? true),
            allowedSchemes: $schemes === [] ? ['http', 'https'] : $schemes,
            allowedPorts: $ports === [] ? [80, 443] : $ports,
            allowedMimes: $mimes === [] ? ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'] : $mimes,
        );
    }

    public static function defaults(): self
    {
        return new self(
            allowedDomains: [
                'api.openverse.org',
                'api.openverse.engineering',
                'api.unsplash.com',
                'images.unsplash.com',
                'api.pexels.com',
                'images.pexels.com',
                'commons.wikimedia.org',
                'upload.wikimedia.org',
                'ru.wikipedia.org',
                'en.wikipedia.org',
                'api.github.com',
            ],
            defaultProvider: 'wikimedia',
            unsplashKey: '',
            pexelsKey: '',
            connectTimeout: 5,
            readTimeout: 20,
            maxSize: 10485760,
            maxRedirects: 3,
            ratePerMin: 30,
            minImageDimension: 200,
            cacheTtlDays: 7,
            blockPrivateIps: true,
            allowedSchemes: ['http', 'https'],
            allowedPorts: [80, 443],
            allowedMimes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        );
    }
}
