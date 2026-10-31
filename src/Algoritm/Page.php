<?php

declare(strict_types=1);

namespace Damoony\Algoritm;

use Damoony\Http\Response;
use Damoony\Region\RegionContext;
use Damoony\Region\RegionRegistry;
use Damoony\Ui\Node;

class Page implements PageInterface
{
    /** @param array<string, string> $args */
    public function __construct(
        protected readonly RegionContext $context,
        protected readonly RegionRegistry $regions,
        protected readonly array $args = [],
    ) {
    }

    /** Удобный доступ для управления регионами из логики страницы */
    public function regions(): RegionRegistry
    {
        return $this->regions;
    }

    public function setContent(string $slotName, Node $content): self
    {
        $this->regions->add(new \Damoony\Region\ContentRegion($slotName, $content));
        return $this;
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

    public function isAuthenticated(): bool
    {
        $auth = $this->context->service('auth');
        return $auth instanceof \Damoony\Auth\AuthInterface && $auth->isAuthenticated();
    }

    public function user(): mixed
    {
        $auth = $this->context->service('auth');
        return $auth instanceof \Damoony\Auth\AuthInterface ? $auth->user() : null;
    }
}
