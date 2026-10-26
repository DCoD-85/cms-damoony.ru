<?php

declare(strict_types=1);

namespace Damoony\Domain;

final class DomainContext
{
    public function __construct(
        public readonly string $domain,
        public readonly string $real,
        public readonly string $allias,
        public readonly string $logic,
        public readonly string $skin,
        public readonly string $language,
        public readonly bool $available,
        public readonly ?string $stub = null,
        public readonly string $namespace = '',
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function hasStub(): bool
    {
        return $this->stub !== null;
    }
}