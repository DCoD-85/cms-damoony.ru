<?php

declare(strict_types=1);

namespace Damoony\Algoritm;

use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Ui\Node;

class Algoritm implements AlgoritmInterface
{
    /** @param array<string, string> $args */
    public function __construct(
        protected readonly RegionContext $context,
        protected readonly array $args = [],
    ) {
    }

    public function default(): Node|Response|null
    {
        return null;
    }

    public function isAjax(): bool
    {
        return $this->context->request->isAjax();
    }

    public function isPost(): bool
    {
        return $this->context->request->isPost();
    }
}