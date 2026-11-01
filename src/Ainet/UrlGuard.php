<?php

declare(strict_types=1);

namespace Damoony\Ainet;

/**
 * SSRF-защита: валидация URL до любого HTTP-запроса.
 *
 * Проверяет (в порядке):
 * 1. URL не пустой, ≤4096 символов, парсится parse_url
 * 2. scheme ∈ allowed_schemes (http, https) — ДО host
 * 3. host не пустой
 * 4. Нет user:pass в URL
 * 5. port ∈ allowed_ports (80, 443, пустой)
 * 6. Если host — прямой IP: private IP check ДО whitelist
 * 7. host ∈ whitelist (точный матч)
 * 8. query ≤ 2048
 * 9. Если hostname: DNS-резолв → private IP check
 * 10. Fragment отрезается
 */
final class UrlGuard
{
    /** @var list<string> */
    private array $allowedSchemes;
    /** @var list<int> */
    private array $allowedPorts;

    /**
     * @param list<string> $allowedSchemes
     * @param list<int> $allowedPorts
     */
    public function __construct(
        private readonly DomainWhitelist $whitelist,
        array $allowedSchemes = ['http', 'https'],
        array $allowedPorts = [80, 443],
        private readonly bool $blockPrivateIps = true,
    ) {
        $this->allowedSchemes = array_map('strtolower', $allowedSchemes);
        $this->allowedPorts = $allowedPorts;
    }

    /**
     * @throws UrlGuardException
     *
     * @return array{url: string, host: string, port: int|null, scheme: string}
     *
     */
    public function validate(string $url): array
    {
        // 1. Базовая длина
        if ($url === '' || strlen($url) > 4096) {
            throw new UrlGuardException('URL is empty or too long', 'URL must be 1-4096 chars');
        }

        // 2. Парсинг
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            throw new UrlGuardException('Malformed URL', 'URL must be absolute (scheme://host/path)');
        }

        // 3. Scheme — ДО host (для file://, ftp://, и т.д.)
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, $this->allowedSchemes, true)) {
            throw new UrlGuardException(
                "Blocked scheme '{$scheme}'",
                'Only ' . implode(', ', $this->allowedSchemes) . ' allowed',
            );
        }

        // 4. Host
        if (!isset($parts['host']) || $parts['host'] === '') {
            throw new UrlGuardException('Empty host', 'URL must contain hostname');
        }

        // 5. user:pass
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UrlGuardException(
                'URL must not contain user:pass',
                'Credentials in URL are blocked (SSRF protection)',
            );
        }

        $host = strtolower((string) $parts['host']);

        // 6. Port
        $port = $parts['port'] ?? null;
        if ($port !== null && !in_array((int) $port, $this->allowedPorts, true)) {
            throw new UrlGuardException(
                "Blocked port {$port}",
                'Only ' . implode(', ', $this->allowedPorts) . ' allowed',
            );
        }

        // 7. Прямой IP — private check ДО whitelist (без DNS)
        if ($this->blockPrivateIps && filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($host)) {
                throw new UrlGuardException(
                    "Private IP blocked: {$host}",
                    'SSRF protection: cannot fetch internal resources',
                );
            }
        }

        // 8. Whitelist
        if (!$this->whitelist->isAllowed($host)) {
            throw new UrlGuardException(
                "Domain '{$host}' not in whitelist",
                "Add '{$host}' to config/ainet.config allowed_domains",
            );
        }

        // 9. Query
        $query = (string) ($parts['query'] ?? '');
        if (strlen($query) > 2048) {
            throw new UrlGuardException('Query string too long (max 2048)', 'Shorten the query');
        }

        // 10. Hostname — DNS-резолв + private IP check
        if ($this->blockPrivateIps && !filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertNotPrivateIp($host);
        }

        // 11. Пересборка без fragment
        $clean = $this->rebuild($parts);

        return [
            'url' => $clean,
            'host' => $host,
            'port' => $port !== null ? (int) $port : null,
            'scheme' => $scheme,
        ];
    }

    private function assertNotPrivateIp(string $host): void
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records) || $records === []) {
            // Не смогли резолвить — curl вернёт ошибку позже. Не блокируем здесь.
            return;
        }

        foreach ($records as $rec) {
            $ip = null;
            if (isset($rec['ip']) && is_string($rec['ip'])) {
                $ip = $rec['ip'];
            } elseif (isset($rec['ipv6']) && is_string($rec['ipv6'])) {
                $ip = $rec['ipv6'];
            }
            if ($ip === null) {
                continue;
            }
            if ($this->isPrivateIp($ip)) {
                throw new UrlGuardException(
                    "Private IP blocked: {$host} → {$ip}",
                    'SSRF protection: cannot fetch internal resources',
                );
            }
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        // filter_var с FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        // вернёт false для приватных и резервных IP
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        return $isPublic === false;
    }

    /** @param array<string, mixed> $parts */
    private function rebuild(array $parts): string
    {
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }
}
