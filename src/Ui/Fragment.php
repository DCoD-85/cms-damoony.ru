<?php

declare(strict_types=1);

namespace Damoony\Ui;

final class Fragment implements Node
{
    /** @var list<Node|string> */
    private array $children = [];

    public function __construct(Node|string ...$children)
    {
        foreach ($children as $child) {
            $this->children[] = $child;
        }
    }

    public function tag(): string
    {
        return '';
    }

    public function attrs(): Attrs
    {
        throw new \LogicException('Fragment has no attributes');
    }

    /** @return list<Node|string> */
    public function children(): array
    {
        return $this->children;
    }

    public function append(Node|string $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    public function prepend(Node|string $child): self
    {
        array_unshift($this->children, $child);
        return $this;
    }

    public function parent(): ?Element
    {
        return null;
    }

    public function release(): string
    {
        $out = '';
        foreach ($this->children as $child) {
            $out .= $child instanceof Node ? $child->release() : htmlspecialchars((string) $child, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $out;
    }
}