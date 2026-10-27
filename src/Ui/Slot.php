<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Slot implements Node
{
    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tag(): string
    {
        return 'slot';
    }

    public function attrs(): Attrs
    {
        throw new \LogicException('Slot has no attributes');
    }

    /** @return list<Node|string> */
    public function children(): array
    {
        return [];
    }

    public function append(Node|string $child): self
    {
        throw new \LogicException('Cannot append to slot');
    }

    public function prepend(Node|string $child): self
    {
        throw new \LogicException('Cannot prepend to slot');
    }

    public function parent(): ?Element
    {
        return null;
    }

    public function release(): string
    {
        return ''; // Слоты не рендерятся в HTML напрямую, они заменяются регионами
    }
}
