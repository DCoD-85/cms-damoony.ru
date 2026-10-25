<?php

declare(strict_types=1);

namespace Damoony\Domain;

interface DomainResolverInterface
{
    public function resolve(string $host): DomainContext;

    public function exists(string $domain): bool;

    public function real(string $domain): string;

    public function namespace(string $domain): string;
}