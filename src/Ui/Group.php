<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Group
{
    /** @var list<Node> */
    private array $items = [];

    public function __construct(private readonly string $cssClass = 'group')
    {
    }

    public function add(Node $node): self
    {
        $this->items[] = $node;
        return $this;
    }

    public function remove(Node $node): self
    {
        $this->items = array_values(array_filter($this->items, fn (Node $n) => $n !== $node));
        return $this;
    }

    /** @return list<Node> */
    public function items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function build(): Element
    {
        $root = new Element('div');
        $root->attrs()->classAdd($this->cssClass);

        foreach ($this->items as $item) {
            $root->append($item);
        }

        return $root;
    }

    public function release(): string
    {
        return $this->build()->release();
    }
}