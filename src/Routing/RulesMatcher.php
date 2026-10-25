<?php

declare(strict_types=1);

namespace Damoony\Routing;

final class RulesMatcher
{
    /**
     * @param array<string, mixed> $rules
     * @return array{0: string, 1: array<string, string>}|null
     */
     /**
     * @param array<string, mixed> $rules
     * @return array{0: string, 1: array<string, string>}|null
     */
    public function matchSegment(string $segment, array $rules): ?array
    {
        $available = $rules['available'] ?? [];
        if (!is_array($available)) {
            return null;
        }

        foreach ($available as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }

            if ($pattern === '') {
                if ($segment === '') {
                    return ['', []];
                }
                continue;
            }

            if (!str_contains($pattern, '|')) {
                if ($pattern === $segment) {
                    return [$pattern, []];
                }
                continue;
            }

            [$regex, $target] = explode('|', $pattern, 2) + [1 => ''];
            if ($regex === '' || $target === '') {
                continue;
            }

            $full = '#^' . $regex . '$#u';
            $matched = @preg_match($full, $segment);
            if ($matched === 1) {
                return [$target, [$target => $segment]];
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