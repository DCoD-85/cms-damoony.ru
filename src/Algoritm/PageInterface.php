<?php

declare(strict_types=1);

namespace Damoony\Algoritm;

use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionRegistry;
use Damoony\Ui\Node;

interface PageInterface
{
    /** @param array<string, string> $args */
    public function __construct(RegionContext $context, RegionRegistry $regions, array $args = []);

    public function default(): Node|Response|null;

    public function isAjax(): bool;

    public function isPost(): bool;
}
