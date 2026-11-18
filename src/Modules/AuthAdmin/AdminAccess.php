<?php

declare(strict_types=1);

namespace Damoony\Modules\AuthAdmin;

use Damoony\Core\Config;

/**
 * modauthadmin: политика доступа к серверной админке (/app/admin/*).
 *
 * Ядро только вызывает политику; IP-список и правила — здесь.
 * Пустой/отсутствующий список = доступ только с loopback (fail-closed).
 */
final class AdminAccess
{
    /** @var list<string> */
    private readonly array $allowedIps;

    /**
     * @param list<string> $allowedIps
     */
    public function __construct(array $allowedIps)
    {
        $ips = [];
        foreach ($allowedIps as $ip) {
            $normalized = trim($ip);
            if (filter_var($normalized, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $normalized;
            }
        }

        $this->allowedIps = $ips === [] ? ['127.0.0.1', '::1'] : array_values(array_unique($ips));
    }

    public static function fromConfig(Config $config): self
    {
        $ips = $config->getByKey(['security', 'admin_ips'], []);
        if (!is_array($ips)) {
            $ips = [];
        }

        return new self(array_values(array_map(static fn ($ip): string => (string) $ip, $ips)));
    }

    public function allows(string $ip): bool
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return in_array($ip, $this->allowedIps, true);
    }

    /** @return list<string> */
    public function allowedIps(): array
    {
        return $this->allowedIps;
    }
}
