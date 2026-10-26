<?php

declare(strict_types=1);

namespace Damoony\Region;

final class RegionRegistry
{
    /** @var array<string, RegionInterface> */
    private array $regions = [];

    /** @var array<string, RegionInterface> */
    private array $baseRegions = [];

    /** @var array<string, bool> */
    private array $disabled = [];

    /** @var array<string, bool> */
    private array $forcedGlobal = [];

    public function add(RegionInterface $region, bool $isBase = false): self
    {
        if ($isBase) {
            $this->baseRegions[$region->name()] = $region;
        }
        $this->regions[$region->name()] = $region;
        return $this;
    }

    public function disable(string $name): self
    {
        $this->disabled[$name] = true;
        return $this;
    }

    public function enable(string $name): self
    {
        unset($this->disabled[$name]);
        return $this;
    }

    public function useGlobal(string $name): self
    {
        $this->forcedGlobal[$name] = true;
        return $this;
    }

    public function useFiller(string $name): self
    {
        unset($this->forcedGlobal[$name]);
        return $this;
    }

    public function get(string $name): ?RegionInterface
    {
        if (isset($this->disabled[$name])) {
            return null;
        }
        if (isset($this->forcedGlobal[$name]) && isset($this->baseRegions[$name])) {
            return $this->baseRegions[$name];
        }
        return $this->regions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /** @return array<string, RegionInterface> */
    public function all(): array
    {
        $result = [];
        foreach (array_keys($this->regions) as $name) {
            $region = $this->get($name);
            if ($region !== null) {
                $result[$name] = $region;
            }
        }
        return $result;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->all());
    }
}