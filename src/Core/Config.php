<?php

declare(strict_types=1);

namespace Damoony\Core;

use RuntimeException;

final class Config
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Cannot read config: {$path}");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON in config: {$path}");
        }

        return new self($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $segments = $path === '' ? [] : explode('.', $path);
        $cursor = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    public function has(string $path): bool
    {
        $segments = $path === '' ? [] : explode('.', $path);
        $cursor = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }
}