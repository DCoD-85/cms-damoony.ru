<?php

declare(strict_types=1);

namespace Damoony\UI\Algoritm;

use Damoony\Core\Http\Response;
use Damoony\UI\Node;
use Damoony\UI\Region\RegionContext;
use Damoony\UI\Region\RegionRegistry;

class Page implements PageInterface
{
    /** @var list<string> */
    protected array $ajaxBlocks = ['top', 'left', 'center', 'right', 'bottom', 'policy', 'toast'];

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

    /** @return list<string> */
    public function ajaxBlocks(): array
    {
        return $this->ajaxBlocks;
    }

    public function setContent(string $slotName, Node $content): self
    {
        $this->regions->add(new \Damoony\UI\Region\ContentRegion($slotName, $content));
        return $this;
    }

    /**
     * Возвращает контент страницы. Если null — будет 204 No Content.
     */
    protected function content(): Node|Response|null
    {
        return null;
    }

    /**
     * Хук для проверки доступа (auth, csrf и т.д.).
     * Если возвращает Response, выполнение content() прерывается.
     */
    protected function guard(): ?Response
    {
        return null;
    }

    /**
     * Финальная точка входа. Движок сам решает, как рендерить результат.
     */
    final public function default(): Node|Response
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->content() ?? Response::text('', 204);
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
