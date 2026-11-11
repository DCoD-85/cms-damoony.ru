<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;

interface PageInterface
{
    /** @param array<string, string> $args */
    public function __construct(RegionContext $context, RegionRegistry $regions, array $args = []);

    public function default(): Node|Response;

    /** @return list<string> */
    public function ajaxBlocks(): array;

    public function isAjax(): bool;

    public function isPost(): bool;
}
