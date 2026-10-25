<?php

declare(strict_types=1);

namespace Damoony\Region;

use Damoony\Ui\Node;

interface RegionInterface
{
    public function name(): string;

    public function enabled(): bool;

    public function render(RegionContext $context): Node;
}