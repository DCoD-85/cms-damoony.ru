<?php

declare(strict_types=1);

namespace Damoony\Core\Domain;

final class DomainContext
{
    public function __construct(
        public readonly string $domain,
        public readonly string $real,
        public readonly string $allias,
        public readonly string $skin,
        public readonly string $language,
        public readonly bool $available,
        public readonly ?string $stub = null,
        public readonly string $namespace = '',
        public readonly string $scheme = 'www',
        /** @var list<string> */
        public readonly array $supportedLanguages = ['ru'],
    ) {
    }

    public function withAllias(string $allias): self
    {
        return new self(
            domain: $this->domain,
            real: $this->real,
            allias: $allias,
            skin: $this->skin,
            language: $this->language,
            available: $this->available,
            stub: $this->stub,
            namespace: $this->namespace,
            scheme: $this->scheme,
            supportedLanguages: $this->supportedLanguages,
        );
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
