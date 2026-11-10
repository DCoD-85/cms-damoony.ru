<?php

declare(strict_types=1);

namespace Damoony\Core\Routing;

final class RulesMatcher
{
    /**
     * @param array<string, mixed> $rules
     *
     * @return array{0: string, 1: array<string, string>}|null
     */
    /**
     * @param array<string, mixed> $rules
    *
     * @return array{0: string, 1: array<string, string>}|null
    */
    public function matchSegment(string $segment, array $rules): ?array
    {
        $available = $rules['available'] ?? [];
        if (!is_array($available)) {
            return null;
        }

        // Normalize old list format to assoc array on the fly (backward compatibility)
        if (array_is_list($available)) {
            $normalized = [];
            foreach ($available as $pattern) {
                if (!is_string($pattern)) {
                    continue;
                }
                if ($pattern === '') {
                    $normalized[''] = '^$';
                } elseif (str_contains($pattern, '|')) {
                    [$regex, $target] = explode('|', $pattern, 2);
                    $normalized[$target === '' ? 'id' : $target] = '^' . $regex . '$';
                } else {
                    $normalized[$pattern] = '^' . preg_quote($pattern, '/') . '$';
                }
            }
            $available = $normalized;
        }

        foreach ($available as $target => $regex) {
            if (!is_string($regex)) {
                continue;
            }

            $full = '#^' . $regex . '$#u';
            if (@preg_match($full, $segment, $matches) === 1) {
                // If there are no capture groups (count($matches) === 1), it's a literal match.
                // We shouldn't pass arguments to FileRouter, otherwise it treats it as a dynamic folder.
                $args = count($matches) > 1 ? [$target => $segment] : [];
                return [$target, $args];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function requiresAuth(array $rules): bool
    {
        return ($rules['auth'] ?? false) === true;
    }
}
