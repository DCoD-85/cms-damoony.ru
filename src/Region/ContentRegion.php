<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Ui\Node;

final class ContentRegion implements RegionInterface
{
    public function __construct(
        private readonly string $name,
        private readonly Node $content,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function render(RegionContext $context): Node
    {
        return $this->content;
    }
}
