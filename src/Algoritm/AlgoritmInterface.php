<?php

declare(strict_types=1);

namespace Damoony\Algoritm;

use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Ui\Node;

interface AlgoritmInterface
{
    /** @param array<string, string> $args */
    public function __construct(RegionContext $context, array $args = []);

    public function default(): Node|Response|null;

    public function isAjax(): bool;

    public function isPost(): bool;
}