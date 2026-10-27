<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Domain\DomainContext;
use Damoony\Http\Request;
use Damoony\Ui\Meta;

final class RegionContext
{
    public readonly Meta $meta;

    /** @param array<string, mixed> $services */
    public function __construct(
        public readonly Request $request,
        public readonly DomainContext $domain,
        ?Meta $meta = null,
        public readonly array $services = [],
    ) {
        $this->meta = $meta ?? new Meta();
    }

    public function service(string $name): mixed
    {
        return $this->services[$name] ?? null;
    }
}
