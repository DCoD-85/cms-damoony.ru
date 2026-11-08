<?php

declare(strict_types=1);

namespace Damoony\UI\Page;

use Damoony\Core\Files\Fs;

final class PageRules
{
    /** @return array{available: list<string>, auth: bool, search: bool, default: bool} */
    public static function defaults(): array
    {
        return [
            'available' => [],
            'auth' => false,
            'search' => true,
            'default' => false,
        ];
    }

    /** @return array<string, mixed> */
    public static function load(string $file): array
    {
        if (!is_file($file)) {
            return self::defaults();
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : self::defaults();
    }

    /** @param array<string, mixed> $rules */
    public static function save(string $file, array $rules): void
    {
        Fs::writeAtomic($file, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    }

    /**
     * Дополняет существующие правила дефолтами (для чтения, не для записи).
     *
     * @param array<string, mixed> $current
     *
     * @return array{available: list<string>, auth: bool, search: bool, default: bool}
     */
    public static function withDefaults(array $current): array
    {
        $available = is_array($current['available'] ?? null)
            ? array_values(array_filter($current['available'], 'is_string'))
            : [];

        return [
            'available' => $available,
            'auth' => ($current['auth'] ?? false) === true,
            'search' => ($current['search'] ?? true) === true,
            'default' => ($current['default'] ?? false) === true,
        ];
    }

    /**
     * @param array<string, mixed> $rules
     *
     * @return array<string, mixed>
     */
    public static function withChild(array $rules, string $childName): array
    {
        $available = $rules['available'] ?? [];
        if (!is_array($available)) {
            $available = [];
        }

        if (!in_array($childName, $available, true)) {
            $available[] = $childName;
        }

        $rules['available'] = array_values(array_unique($available));

        return $rules;
    }
}
