<?php

declare(strict_types=1);

namespace Damoony\UI\Region;

use Damoony\UI\Node;

interface RegionInterface
{
    public function name(): string;

    public function enabled(): bool;

    public function render(RegionContext $context): Node;
}
