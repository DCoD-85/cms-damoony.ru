<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;

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
        $this->regions->add(new \Damoony\UI\Region\ContentRegion($slotName, $content));
        return $this;
    }

    protected function content(): Node|Response|null
    {
        return null;
    }

    public function default(): Node|Response|null
    {
        return $this->content();
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
        return $auth instanceof \Damoony\Data\Auth\AuthInterface && $auth->isAuthenticated();
    }

    public function user(): mixed
    {
        $auth = $this->context->service('auth');
        return $auth instanceof \Damoony\Data\Auth\AuthInterface ? $auth->user() : null;
    }
}
