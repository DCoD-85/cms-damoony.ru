<?php

declare(strict_types=1);

namespace Damoony\UI\Page;

use Damoony\Core\Files\Fs;

final class PageRules
{
    /** @return array{available: array<string, string>, auth: bool} */
    public static function defaults(): array
    {
        return [
            'available' => [],
            'auth' => false,
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
     * @return array{available: array<string, string>, auth: bool}
     */
    public static function withDefaults(array $current): array
    {
        $available = $current['available'] ?? [];
        if (!is_array($available)) {
            $available = [];
        }

        return [
            'available' => $available,
            'auth' => ($current['auth'] ?? false) === true,
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

        // Convert list to assoc if it was an old format
        if (array_is_list($available)) {
            $newAvailable = [];
            foreach ($available as $item) {
                if (is_string($item)) {
                    $newAvailable[$item] = '^' . preg_quote($item, '/') . '$';
                }
            }
            $available = $newAvailable;
        }

        if (!isset($available[$childName])) {
            $available[$childName] = '^' . preg_quote($childName, '/') . '$';
        }

        $rules['available'] = $available;

        return $rules;
    }
}
