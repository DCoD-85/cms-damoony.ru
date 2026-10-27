<?php

declare(strict_types=1);

namespace Damoony\Core;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class Container implements ContainerInterface
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function setInstance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new class ("Service not found: {$id}") extends RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->factories);
    }
}
