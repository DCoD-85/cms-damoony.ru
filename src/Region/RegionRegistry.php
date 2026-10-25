<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionRegistry
{
    /** @var array<string, RegionInterface> */
    private array $regions = [];

    public function add(RegionInterface $region): self
    {
        $this->regions[$region->name()] = $region;
        return $this;
    }

    public function get(string $name): ?RegionInterface
    {
        return $this->regions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->regions[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->regions);
    }

    /** @return array<string, RegionInterface> */
    public function all(): array
    {
        return $this->regions;
    }
}